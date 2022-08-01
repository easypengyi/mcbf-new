<?php
/**
 * Goods.php
 *
 * 微商来 - 专业移动应用开发商!
 * =========================================================
 * Copyright (c) 2014 广州领客信息科技有限公司, 保留所有权利。
 * ----------------------------------------------
 * 官方网址: http://www.vslai.com
 *
 * 任何企业和个人不允许对程序代码以任何形式任何目的再发布。
 * =========================================================
 */

namespace data\service;

/**
 * 商品服务层
 */
use addons\bargain\model\VslBargainModel;
use addons\bargain\model\VslBargainRecordModel;
use addons\bargain\service\Bargain;
use addons\bargain\service\Bargain as bargainServer;
use addons\channel\model\VslChannelCartModel;
use addons\channel\model\VslChannelGoodsModel;
use addons\channel\model\VslChannelGoodsSkuModel;
use addons\channel\model\VslChannelModel;
use addons\channel\model\VslChannelOrderSkuRecordModel;
use addons\channel\server\Channel;
use addons\coupontype\server\Coupon;
use addons\discount\server\Discount;
use addons\discount\server\Discount as DiscountServer;
use addons\friendscircle\model\VslFriendscircleMaterial;
use addons\fullcut\service\Fullcut;
use addons\groupshopping\model\VslGroupGoodsModel;
use addons\groupshopping\model\VslGroupShoppingModel;
use addons\groupshopping\server\GroupShopping;
use addons\integral\model\VslIntegralGoodsModel;
use addons\liveshopping\model\LiveGoodsModel;
use addons\presell\service\Presell as PresellServer;
use addons\receivegoodscode\server\ReceiveGoodsCode as ReceiveGoodsCodeSer;
use addons\seckill\model\VslSeckGoodsModel;
use addons\seckill\model\VslSeckillModel;
use addons\seckill\server\Seckill AS SeckillServer;
use addons\seckill\server\Seckill;
use addons\store\server\Store;
use addons\store\server\Store as storeServer;
use addons\supplier\model\VslSupplierModel;
use addons\supplier\server\Supplier as SupplierSer;
use data\model\AlbumPictureModel as AlbumPictureModel;
use data\model\VslActivityOrderSkuRecordModel;
use data\model\VslAttributeModel;
use data\model\VslAttributeValueModel;
use data\model\VslCartModel;
use data\model\VslGoodsAttributeDeletedModel;
use data\model\VslGoodsAttributeModel;
use data\model\VslGoodsBrandModel;
use data\model\VslGoodsCategoryModel as VslGoodsCategoryModel;
use data\model\VslGoodsDeletedModel;
use data\model\VslGoodsDeletedViewModel;
use data\model\VslGoodsDiscountModel;
use data\model\VslGoodsEvaluateModel;
use data\model\VslGoodsGroupModel as VslGoodsGroupModel;
use data\model\VslGoodsModel as VslGoodsModel;
use data\model\VslGoodsSkuDeletedModel;
use data\model\VslGoodsSkuModel as VslGoodsSkuModel;
use data\model\VslGoodsSkuPictureDeleteModel;
use data\model\VslGoodsSkuPictureModel;
use data\model\VslGoodsSpecModel as VslGoodsSpecModel;
use data\model\VslGoodsSpecValueModel as VslGoodsSpecValueModel;
use data\model\VslGoodsViewModel as VslGoodsViewModel;
use data\model\VslKnowledgePaymentContentModel;
use data\model\VslMemberFavoritesModel;
use data\model\VslMemberLevelModel;
use data\model\VslMemberModel;
use data\model\VslOrderGoodsModel;
use data\model\VslGoodsTicketModel;
use data\model\VslOrderModel;
use addons\presell\model\VslPresellGoodsModel;
use addons\presell\model\VslPresellModel;
use addons\shop\model\VslShopModel;
use addons\groupshopping\server\GroupShopping as GroupShoppingServer;
use data\model\VslStoreCartModel;
use data\model\VslStoreGoodsSkuModel;
use data\service\BaseService as BaseService;
use data\service\Member as MemberService;
use data\service\Order\OrderGoods;
use data\service\promotion\GoodsExpress;
use data\service\promotion\GoodsPreference;
use addons\presell\service\Presell as PresellService;
use think\Db;
use think\Exception;
use think\Request;
use data\model\VslStoreGoodsModel as VslStoreGoodsModel;
use data\service\AddonsConfig as AddonsConfigService;
use addons\luckyspell\server\Luckyspell as LuckySpellServer;
use addons\luckyspell\model\VslLuckySpellGoodsModel;

class Goods extends BaseService
{

    private $goods;
    protected $http = '';

    function __construct()
    {
        parent::__construct();
        $is_ssl = Request::instance()->isSsl();
        $this->http = "http://";
        if ($is_ssl) {
            $this->http = 'https://';
        }
        $this->goods = new VslGoodsModel();
        $this->goods_spec_model = new VslGoodsSpecModel();
        $this->goods_spec_value_model = new VslGoodsSpecValueModel();
    }
    
    /*
	 * (non-PHPdoc)
	 * @see \data\api\IGoods::getGoodsList()
	 */
    public function getGoodsList($page_index = 1, $page_size = 0, $condition = [], $order = 'ng.sort desc,ng.create_time desc', $field = '*')
    {
        if (is_array($condition) && !array_key_exists('ng.supplier_id',$condition)){
            $condition['ng.supplier_id'] = $this->supplier_id;
        }
        $goods_view = new VslGoodsViewModel();
        if(getAddons('supplier',$this->website_id)){
			$supplierSer = new SupplierSer();
		}
        if(!$condition['ng.supplier_id']) {
            $condition['nss.shop_state'] = 1;
        }
        // 针对商品分类
        if (!empty($condition['ng.category_id'])) {
            $goods_category = new GoodsCategory();
            $category_list = $goods_category->getCategoryTreeList($condition['ng.category_id']);
            unset($condition['ng.category_id']);
            $query_goods_ids = "";
            $goods_list = $goods_view->getGoodsViewQueryField($condition, "ng.goods_id");
            if (!empty($goods_list) && count($goods_list) > 0) {
                foreach ($goods_list as $goods_obj) {
                    if ($query_goods_ids === "") {
                        $query_goods_ids = $goods_obj["goods_id"];
                    } else {
                        $query_goods_ids = $query_goods_ids . "," . $goods_obj["goods_id"];
                    }
                }
                unset($goods_obj);
                $extend_query = "";
                $category_str = explode(",", $category_list);
                foreach ($category_str as $category_id) {
                    if ($extend_query === "") {
                        $extend_query = " FIND_IN_SET( " . $category_id . ",ng.extend_category_id) ";
                    } else {
                        $extend_query = $extend_query . " or FIND_IN_SET( " . $category_id . ",ng.extend_category_id) ";
                    }
                }
                unset($category_id);
//                $condition = " ng.goods_id in (" . $query_goods_ids . ") and ( ng.category_id in (" . $category_list . ") or " . $extend_query . ")";
                $condition[] = ['exp',"  ng.goods_id in (" . $query_goods_ids . ") and ( ng.category_id in (" . $category_list . ") or " . $extend_query . ") "];
            }
        }
        if (\request()->module() != 'supplier'){
            $condition[] = ['exp', ' if( ng.supplier_info >0, s.status =1, 1)'];//供应商必须为开启
        }
        $list = $goods_view->getGoodsViewList($page_index, $page_size, $condition, $order, $field);
        if (!empty($list['data'])) {
            // 用户针对商品的收藏
            foreach ($list['data'] as $k => $v) {
                if (!empty($this->uid) && $this->is_member) {
                    $member = new Member();
                    $list['data'][$k]['is_favorite'] = $member->getIsMemberFavorites($this->uid, $v['goods_id'], 'goods');
                } else {
                    $list['data'][$k]['is_favorite'] = 0;
                }
                if (!$v['pic_id']) {
                    $list['data'][$k]['pic_id'] = 0;
                }
                if (isset($v['supplier_info']) && getAddons('supplier',$this->website_id)){
                    $list['data'][$k]['supplier_name'] = $supplierSer->getSupplierInfoBySupplierId($v['supplier_info'], 'supplier_name')['supplier_name'];
                }
            }
            unset($v);
        }
        return $list;

        // TODO Auto-generated method stub
    }

    /*
		 * (non-PHPdoc)
		 * @see \data\api\IGoods::getGoodsList()
		 */
    public function getIntegralGoodsList($page_index = 1, $page_size = 0, $condition = [], $order = 'ng.sort desc,ng.create_time desc')
    {
        $integral_goods_view = new VslIntegralGoodsModel();
        $list = $integral_goods_view->getGoodsViewList($page_index, $page_size, $condition, $order);
//        echo $goods_view->getLastSql();exit;
        if (!empty($list['data'])) {
            // 用户针对商品的收藏
            foreach ($list['data'] as $k => $v) {
                if (!empty($this->uid)) {
                    $member = new Member();
                    $list['data'][$k]['is_favorite'] = $member->getIsMemberFavorites($this->uid, $v['goods_id'], 'goods');
                } else {
                    $list['data'][$k]['is_favorite'] = 0;
                }
                if (!$v['pic_id']) {
                    $list['data'][$k]['pic_id'] = 0;
                }
            }
            unset($v);
        }
        return $list;

        // TODO Auto-generated method stub
    }

    /**
     * 直接查询商品列表
     *
     * @param number $page_index
     * @param number $page_size
     * @param string $condition
     * @param string $order
     */
    public function getGoodsViewList($page_index = 1, $page_size = 0, $condition = '', $order = 'ng.sort desc,ng.create_time desc',$field="*")
    {
        $goods_view = new VslGoodsViewModel();
        $list = $goods_view->getGoodsViewList($page_index, $page_size, $condition, $order,$field);
        return $list;
    }

    /*
	 * (non-PHPdoc)
	 * @see \data\api\IGoods::getGoodsCount()
	 */
    public function getGoodsCount($condition)
    {
        $count = $this->goods->where($condition)->count();
        return $count;

        // TODO Auto-generated method stub
    }

    /**
     * 查询商品及其关联表的数据
    */
    public function getGoodsWithData ($conditon, array $with = ['sku'])
    {
        $goods = new VslGoodsModel();
        return $goods::get($conditon, $with);
    }


    /**
     * 查询条件下商品及图片信息
     * @param $condition
     * @return array
     */
    public function getQueryGoodsDataOfPicture ($condition)
    {
        $goods_list = $this->goods->getQuery($condition,'goods_id,picture,goods_name,price,website_id,shop_id,activity_pic');
        $goods_list = objToArr($goods_list);
        if (!$goods_list) {return [];}
//        $goods_ids_list = array_column($goods_list,'goods_id');
        $goods_pic_list = array_column($goods_list,'picture');
        if (!$goods_pic_list) {
            return $goods_list;
        }
        $picture = new AlbumPictureModel();
        $pic_list = $picture->getQuery(['pic_id' => ['in', $goods_pic_list]],'pic_id,pic_cover,pic_cover_big,pic_cover_mid,pic_cover_small');
        $pic_list = objToArr($pic_list);
        $new_list = [];
        foreach ($pic_list as $val)
        {

            foreach ($goods_list as $key => $goods)
            {
                if ($goods['picture'] == $val['pic_id']) {
                    $val = array_merge($goods,$val);
                    $val['pic_cover'] = $val['pic_cover']?getApiSrc($val['pic_cover']):'';
                    $val['pic_cover_big'] = $val['pic_cover_big']?getApiSrc($val['pic_cover_big']):'';
                    $val['pic_cover_mid'] = $val['pic_cover_mid']?getApiSrc($val['pic_cover_mid']):'';
                    $val['pic_cover_small'] = $val['pic_cover_small']?getApiSrc($val['pic_cover_small']):'';
                    $activity_pic = $picture->getInfo(['pic_id' =>$goods['activity_pic']], 'pic_cover')['pic_cover'];
                    $val['activity_pic'] = $activity_pic ? getApiSrc($activity_pic) : '';
                    $new_list[$goods['goods_id']] = $val;
                    unset($goods_list[$key]);
                    continue;
                }
            }
        }

        return $new_list;
    }

    /**
     * 添加修改商品
     * @return \data\model\VslGoodsModel|number
     */
    public function addOrEditGoods($data)
    {
        # 用数组或对象接受参数，方便注释、修改与浏览。
        $goods_id                       = $data['goods_id'];
        $goods_name                     = $data['goods_name'];
        $shopid                         = $data['shopid'];
        $category_id                    = $data['category_id'] ?: 0;
        $category_id_1                  = $data['category_id_1'] ?: 0;
        $category_id_2                  = $data['category_id_2'] ?: 0;
        $category_id_3                  = $data['category_id_3'] ?: 0;
        $supplier_id                    = $data['supplier_id'] ?: 0;
        $brand_id                       = $data['brand_id'] ?: 0;
        $group_id_array                 = $data['group_id_array'];
        $goods_type                     = $data['goods_type'];//实物或虚拟商品标志 1实物商品 0计时计次商品 3虚拟商品 2 F码商品 4知识付费  5电子卡密
        $market_price                   = $data['market_price'] ?: 0.00;//市场价
        $price                          = $data['price'] ?: 0.00;//商品原价格
        $cost_price                     = $data['cost_price'] ?: 0.00;//成本价
        $point_exchange_type            = $data['point_exchange_type'] ?: 0;//积分兑换类型 0 非积分兑换 1 只能积分兑换
        $point_exchange                 = $data['point_exchange'] ?:0;//积分兑换
        $give_point                     = $data['give_point'] ?: 0;//购买商品赠送积分
        $is_member_discount             = $data['is_member_discount'] ?: 0;//参与会员折扣
        $shipping_fee                   = $data['shipping_fee'] ?: 0.00;//统一运费
        $shipping_fee_id                = $data['shipping_fee_id'];
        $stock                          = $data['stock'];
        $max_buy                        = $data['max_buy'] ?: 0;//限购数
        $min_buy                        = $data['min_buy'];
        $min_stock_alarm                = $data['min_stock_alarm'] ?: 0;//库存预警值
        $clicks                         = $data['clicks'] ?: 0;//商品点击数量
        $real_sales                     = $data['sales'];//虚拟销量
        $collects                       = $data['collects'] ?: 0;
        $star                           = $data['star'] ?: 5;
        $evaluates                      = $data['evaluates'] ?:0 ;
        $shares                         = $data['shares'] ?: 0;
        $province_id                    = $data['province_id'] ?: 0;
        $city_id                        = $data['city_id'];
        $picture                        = $data['picture'] ?: 0;
        $keywords                       = $data['keywords'] ?: '';
        $introduction                   = $data['introduction'] ?: '';//商品简介，促销语
        $description                    = $data['description'];
        $QRcode                         = $data['QRcode'] ?: '';
        $code                           = $data['code'];
        $is_stock_visible               = $data['is_stock_visible'] ?: 0;//页面不显示库存
        $is_hot                         = $data['is_hot'] ?: 0;
        $is_recommend                   = $data['is_recommend'] ?: 0;
        $is_new                         = $data['is_new'] ?: 0;
        $sort                           = $data['sort'] ?: 0;
        $img_id_array                   = $data['img_id_array'];//商品图片序列
        $sku_array                      = $data['sku_array'];//规格组
        $state                          = $data['state'] ?: 0;//商品状态 0下架，1正常，10违规（禁售），11待审核，12审核不通过
        $sku_img_array                  = $data['sku_img_array'] ?: '';//商品sku应用图片列表  属性,属性值，图片ID
        $goods_attribute_id             = $data['goods_attribute_id'] ?: 0;//商品类型
        $goods_attribute                = $data['goods_attribute'];
        $goods_spec_format              = $data['goods_spec_format'];//商品规格
        $goods_weight                   = $data['goods_weight'] ?: 0.00;//商品重量
        $goods_volume                   = $data['goods_volume'] ?: 0.00;//商品体积
        $shipping_fee_type              = $data['shipping_fee_type'] ?: 0;//运费类型：0免运费，1统一运费，2选择模板
        $extend_category_id             = $data['extend_category_id'];
        $sku_picture_values             = $data['sku_picture_values'];
        $item_no                        = $data['item_no'] ?: '';//商品货号
        $distribution_rule_val          = $data['distribution_rule_val'];//独立分销规则
        $distribution_rule              = $data['distribution_rule'] ?: 2;//独立分销规则是否开启
        $is_distribution                = $data['is_distribution'] ?: 1;//是否参与分销
        $is_bonus_global                = $data['is_bonus_global'] ?:1;//是否参与全球分红
        $is_bonus_area                  = $data['is_bonus_area'] ?:1;//是否参与区域分红
        $is_bonus_team                  = $data['is_bonus_team'] ?:1;//是否参与团队分红
        $bonus_rule_val                 = $data['bonus_rule_val'] ?:'';//独立分红规则
        $bonus_rule                     = $data['bonus_rule'] ?:2;//独立分红规则是否开启
        $is_promotion                   = $data['is_promotion'] ?: 0;//是否促销 1:是
        $is_shipping_free               = $data['is_shipping_free'] ?: 0;//是否包邮 1: 是
        $is_wxcard                      = $data['is_wxcard'] ?: 2; //微信卡券:1 开启   2:关闭
        $verificationinfo               = $data['verificationinfo'];
        $card_info                      = $data['card_info'];
        $video_id                       = $data['video_id'];//视频id
        $point_deduction_max            = $data['point_deduction_max'];//积分最大抵扣
        $point_return_max               = $data['point_return_max'];//购物返积分
        $goods_count                    = $data['goods_count'] ?: 0;//商品数量，用于运费模板
        $single_limit_buy               = $data['single_limit_buy'];
        $buyagain                       = $data['buyagain'] ?: 0;
        $buyagain_level_rule            = $data['buyagain_level_rule'] ?: 2;
        $buyagain_recommend_type        = $data['buyagain_recommend_type'] ?: 1;
        $buyagain_distribution_val      = $data['buyagain_distribution_val'];
        $payment_content                = $data['payment_content'];
        $for_store                      = $data['for_store'] ?: 0;//门店快捷收银商品, 0否,1是
        $is_goods_poster_open           = $data['is_goods_poster_open'] ?: 0;////是否开启独立商品海报 0-否 1-是。
        $poster_data                    = $data['poster_data'];////商品海报内容
        $px_type                        = $data['px_type'] ?: 1;////海报的像素 1- 640*1008 2- 1080*1920
        $teambonus_rule_val             = $data['teambonus_rule_val'] ?: '';//团队分红商品独立规则：比例/金额
        $electroncard_base_id           = $data['electroncard_base_id'] ?: 0;//电子卡密商品选择的卡密库id
        $delivery_type                  = $data['delivery_type'] ?: 0;//发货设置  1:自动发货  2:自动发货并确认收货  3:自动发货并订单完成  4:手动发货
        $evaluate_give_point            = $data['evaluate_give_point'] ?: '';//好评返积分
        $least_buy                      = $data['least_buy'] ?: 0;//最低起购
        $area_bonus                     = $data['area_bonus'] ?: '';//区域分红规则
        $global_bonus                   = $data['global_bonus'] ?: '';//全球分红规则
        $global_bonus_choose            = $data['global_bonus_choose'] ?: '';//全球独立结算
        $global_bonus_calculation       = $data['global_bonus_calculation'] ?: '';//结算节点
        $area_bonus_choose              = $data['area_bonus_choose'] ?: '';//区域独立结算
        $area_bonus_calculation         = $data['area_bonus_calculation'] ?: '';//结算节点
        $team_bonus_choose              = $data['team_bonus_choose'] ?: '';//团队独立结算
        $team_bonus_calculation         = $data['team_bonus_calculation'] ?: '';//结算节点
        $distribution_bonus_choose      = $data['distribution_bonus_choose'] ?: '';//分销独立结算
        $distribution_bonus_calculation = $data['distribution_bonus_calculation'] ?: '';//结算节点
        $error                          = 0;
        $category_list                  = $this->getGoodsCategoryId($category_id);
        $area_list                      = $data['area_list'];//限购地区（省、市、区）
        $plus_member                    = $data['plus_member'];//是否开启plus会员价
        $form_base_id                   = $data['form_base_id'] ?: 0;//电子卡密商品选择的卡密库id 【暂时去掉】
        $payment_type                   = $data['payment_type'] ?: 0;//供应商商品的结算方式
        $supplier_rebate                = $data['supplier_rebate'];//供应商商品的分佣比例
        $sub_title                      = $data['sub_title'];//副标题
        $activity_pic                   = $data['activity_pic'];//活动图
        $discount_bonus                 = $data['discount_bonus'];//商品权限array

        $coupon_type_id_array                 = serialize($data['coupon_type_id_array']);//优惠券array
        $gift_voucher_id_array                 = serialize($data['gift_voucher_id_array']);//礼品卷array
        $package_voucher_id_array                 = serialize($data['package_voucher_id_array']);//卷包array
        $coupon_type_id_array_num                 = serialize($data['coupon_type_id_array_num']);//优惠券array
        $gift_voucher_id_array_num                 = serialize($data['gift_voucher_id_array_num']);//礼品卷array
        $package_voucher_id_array_num                 = serialize($data['package_voucher_id_array_num']);//卷包array
	$is_commission = $data['is_commission'];
        $cash_method = $data['cash_method'];
        $write_off_method = $data['write_off_method'];
        $cash_reward = $data['cash_reward'];
        $write_off_reward = $data['write_off_reward'];

        // 1级扩展分类
        $extend_category_id_1s = "";
        // 2级扩展分类
        $extend_category_id_2s = "";
        // 3级扩展分类
        $extend_category_id_3s = "";
        if (!empty($extend_category_id)) {
            $extend_category_id_str = explode(",", $extend_category_id);
            foreach ($extend_category_id_str as $extend_id) {
                $extend_category_list = $this->getGoodsCategoryId($extend_id);

                if ($extend_category_id_1s === "") {
                    $extend_category_id_1s = $extend_category_list[0];
                } else {
                    $extend_category_id_1s = $extend_category_id_1s . "," . $extend_category_list[0];
                }
                if ($extend_category_id_2s === "") {
                    $extend_category_id_2s = $extend_category_list[1];
                } else {
                    $extend_category_id_2s = $extend_category_id_2s . "," . $extend_category_list[1];
                }
                if ($extend_category_id_3s === "") {
                    $extend_category_id_3s = $extend_category_list[2];
                } else {
                    $extend_category_id_3s = $extend_category_id_3s . "," . $extend_category_list[2];
                }
            }
            unset($extend_id);
        }
        $this->goods->startTrans();
        try {
            $goods_info = $this->goods->getInfo(['goods_id' => $goods_id]);
            if (isset($verificationinfo['valid_type']) && $verificationinfo['valid_type'] == 1) {
                $verificationinfo['invalid_time'] = time() + ($verificationinfo['valid_days'] * 3600 * 24);
            }
            $data_goods = array(
                'website_id' => $this->website_id,
                'coupon_type_id_array' => $coupon_type_id_array,
                'gift_voucher_id_array' => $gift_voucher_id_array,
                'package_voucher_id_array' => $package_voucher_id_array,
                'coupon_type_id_array_num' => $coupon_type_id_array_num,
                'gift_voucher_id_array_num' => $gift_voucher_id_array_num,
                'package_voucher_id_array_num' => $package_voucher_id_array_num,
                'goods_name' => $goods_name,
                'shop_id' => $shopid,
                'category_id' => $category_id,
                'category_id_1' => $category_list[0],
                'category_id_2' => $category_list[1],
                'category_id_3' => $category_list[2],
                'supplier_id' => $supplier_id,
                'brand_id' => $brand_id,
                'group_id_array' => $group_id_array,
                'goods_type' => $goods_type,
                'market_price' => $market_price,
                'price' => $price,
                'promotion_price' => $price,
                'cost_price' => $cost_price,
                'point_exchange_type' => $point_exchange_type,
                'point_exchange' => $point_exchange,
                'give_point' => $give_point,
                'is_member_discount' => $is_member_discount,
                'shipping_fee' => $shipping_fee,
                'shipping_fee_id' => $shipping_fee_id,
                'stock' => $stock,
                'max_buy' => $max_buy,
                'min_buy' => $min_buy,
                'min_stock_alarm' => $min_stock_alarm,
                'province_id' => $province_id,
                'city_id' => $city_id,
                'picture' => $picture,
                'keywords' => $keywords,
                'introduction' => $introduction,
                'description' => $description,
                'QRcode' => $QRcode,
                'real_sales' => $real_sales,//虚拟销量
                'code' => $code,
                'is_stock_visible' => $is_stock_visible,
                'is_hot' => $is_hot,
                'is_recommend' => $is_recommend,
                'is_new' => $is_new,
                'is_promotion' => $is_promotion,
                'is_shipping_free' => $is_shipping_free,
                'is_wxcard' => $is_wxcard,
                'sort' => $sort,
                'img_id_array' => $img_id_array,
                'state' => $state,
                'sku_img_array' => $sku_img_array,
                'goods_attribute_id' => $goods_attribute_id,
                'goods_spec_format' => $goods_spec_format,
                'goods_weight' => $goods_weight,
                'goods_volume' => $goods_volume,
                'shipping_fee_type' => $shipping_fee_type,
                'extend_category_id' => $extend_category_id,
                'extend_category_id_1' => $extend_category_id_1s,
                'extend_category_id_2' => $extend_category_id_2s,
                'extend_category_id_3' => $extend_category_id_3s,
                'item_no' => $item_no,
                'is_distribution' => $is_distribution,
                'distribution_rule_val' => $distribution_rule_val,
                'distribution_rule' => $distribution_rule,
                'is_bonus_global' => $is_bonus_global,
                'is_bonus_area' => $is_bonus_area,
                'is_bonus_team' => $is_bonus_team,
                'bonus_rule_val' => $bonus_rule_val,
                'teambonus_rule_val' => $teambonus_rule_val,
                'bonus_rule' => $bonus_rule,
                'cancle_times' => isset($verificationinfo['verification_num']) ? $verificationinfo['verification_num'] : '',
                'cart_type' => isset($verificationinfo['card_type']) ? $verificationinfo['card_type'] : '',
                'valid_type' => isset($verificationinfo['valid_type']) ? $verificationinfo['valid_type'] : '',
                'invalid_time' => isset($verificationinfo['invalid_time']) ? $verificationinfo['invalid_time'] : '',
                'store_list' => isset($verificationinfo['store_list']) ? $verificationinfo['store_list'] : '',
                'valid_days' => isset($verificationinfo['valid_days']) ? $verificationinfo['valid_days'] : '',
                'video_id' => $video_id,
                'point_deduction_max' => $point_deduction_max,
                'point_return_max' => $point_return_max,
                'goods_count' => $goods_count,
                'single_limit_buy' => $single_limit_buy,
                'buyagain' => $buyagain,
                'buyagain_level_rule' => $buyagain_level_rule,
                'buyagain_recommend_type' => $buyagain_recommend_type,
                'buyagain_distribution_val' => $buyagain_distribution_val,
                'for_store' => $for_store,
                'is_goods_poster_open' => $is_goods_poster_open,
                'poster_data' => $poster_data,
                'px_type' => $px_type,
                'electroncard_base_id' => $electroncard_base_id,
                'delivery_type' => $delivery_type,
                'evaluate_give_point' => $evaluate_give_point,
                'area_bonus' => $area_bonus,
                'global_bonus' => $global_bonus,
                'global_bonus_choose' => $global_bonus_choose,
                'global_bonus_calculation' => $global_bonus_calculation,
                'area_bonus_choose' => $area_bonus_choose,
                'area_bonus_calculation' => $area_bonus_calculation,
                'team_bonus_choose' => $team_bonus_choose,
                'team_bonus_calculation' => $team_bonus_calculation,
                'distribution_bonus_choose' => $distribution_bonus_choose,
                'distribution_bonus_calculation' => $distribution_bonus_calculation,
                'least_buy' => $least_buy,
                'area_list' => $area_list,
                'plus_member' => $plus_member,
               'form_base_id' => $form_base_id,
                'payment_type' => $payment_type,
                'supplier_rebate' => $supplier_rebate,
                //'form_base_id' => $form_base_id,
                'sub_title' => $sub_title,
                'activity_pic' => $activity_pic,
		'is_commission'            => $is_commission,
                'cash_method'            => $cash_method,
                'write_off_method'            => $write_off_method,
                'cash_reward'            => $cash_reward,
                'write_off_reward'            => $write_off_reward
            );

            // 商品保存之前钩子
            hook("goodsSaveBefore", $data_goods);
            $specArray = $this->changeSpec(json_decode($goods_spec_format,true));
            $redis = connectRedis();
            if ($goods_id == 0) {/*新建*/
                $data_goods['create_time'] = time();
                $data_goods['sale_date'] = time();
                $data_goods['max_buy_time'] = time();
                $res = $this->goods->save($data_goods);

                if (empty($this->goods->goods_id)) {
                    $this->goods->goods_id = $res;
                }
                $data_goods['goods_id'] = $this->goods->goods_id;
                hook("goodsSaveSuccess", $data_goods);
                $goods_id = $this->goods->goods_id;
                // 添加sku
                if (!empty($sku_array)) {
                    $sku_list_array = explode('§', $sku_array);
                    if (empty($sku_list_array[0])) {
                        unset($sku_list_array[0]);//删掉空数据
                    }
                    foreach ($sku_list_array as $k => $v) {
                        $res = $this->addOrUpdateGoodsSkuItem($goods_id, $v, $specArray);
                        if (!$res) {
                            $error = 6;
                        }
                    }
                    unset($v);
                    // sku图片添加
                    $sku_picture_array = array();
                    if (!empty($sku_picture_values)) {
                        $sku_picture_array = json_decode($sku_picture_values, true);
                        foreach ($sku_picture_array as $k => $v) {
                            $res = $this->addGoodsSkuPicture($shopid, $goods_id, $v["spec_id"], $v["spec_value_id"], $v["img_ids"]);
                            if (!$res) {
                                $error = 5;
                            }
                        }
                        unset($v);
                    }
                } else {
                    $goods_sku = new VslGoodsSkuModel();
                    // 添加一条skuitem
                    $sku_data = array(
                        'goods_id' => $this->goods->goods_id,
                        'sku_name' => '',
                        'market_price' => $market_price,
                        'price' => $price,
                        'promote_price' => $price,
                        'cost_price' => $cost_price,
                        'stock' => $stock,
                        'picture' => 0,
                        'code' => $code,
                        'QRcode' => '',
                        'create_date' => time(),
                        'website_id' => $this->website_id,
                        'sku_max_buy' => $data_goods['max_buy'],
                        'sku_max_buy_time' => time()
                    );
                    //将普通商品的库存插入到redis
                    $res = $goods_sku->save($sku_data);
                    $redis_key = 'goods_'.$this->goods->goods_id.'_'.$res;
                    $redis->set($redis_key, $stock);
                    if (!$res) {
                        $error = 4;
                    }
                }
                if ($supplier_id){
                    $this->setSupplierGoodsSkuIdAndSaveGoodsOriginalSkuIds($goods_id);//商品表记录商品sku_id
                }
                //知识付费商品
                if ($goods_type == 4) {
                    $knowledge_payment_content_model = new VslKnowledgePaymentContentModel();
                    foreach ($payment_content as $k => $v) {
                        $payment_content[$k]['goods_id'] = $goods_id;
                        $payment_content[$k]['website_id'] = $this->website_id;
                        $payment_content[$k]['shop_id'] = $this->instance_id;
                        $payment_content[$k]['create_time'] = time();
                    }
                    unset($v);
                    $knowledge_payment_content_model->saveAll($payment_content, true);
                }

                //如果有o2o应用，就需要将商品添加到勾选的对应门店，存进门店商品表
                if ($verificationinfo['store_list']) {
                    $arr = [];
                    $verificationinfo['store_list'] = explode(',', $verificationinfo['store_list']);
                    $store_goods_data = [
                        'goods_id' => $goods_id,
                        'website_id' => $this->website_id,
                        'goods_name' => $goods_name,
                        'shop_id' => $shopid,
                        'category_id' => $category_id,
                        'category_id_1' => $category_list[0],
                        'category_id_2' => $category_list[1],
                        'category_id_3' => $category_list[2],
                        'picture' => $picture,
                        'stock' => $stock,
                        'market_price' => $market_price,
                        'price' => $price,
                        'img_id_array' => $img_id_array,
                        'state' => 0,
//                        'sales' => $real_sales,
                        'create_time' => time()
                    ];
                    $storeGoodsModel = new VslStoreGoodsModel();
                    for ($i = 0; $i < count($verificationinfo['store_list']); $i++) {
                        $store_goods_data['store_id'] = $verificationinfo['store_list'][$i];
                        $arr[] = $store_goods_data;
                    }
                    $storeGoodsModel->saveAll($arr, true);
                }

                //如果是虚拟商品，微信卡券开启则添加卡券
                if ($goods_type == 0 && $is_wxcard == 1) {
                    $ticket = new VslGoodsTicketModel();
                    $weixin_card = new WeixinCard();
                    //图片要先上传至微信图片库
                    $album = new AlbumPictureModel();
                    $pic = $album->getInfo(['pic_id' => $card_info['card_pic_id']], 'pic_cover,domain');
                    //需要将外链图片先存放到服务器，再传入微信后再删除掉
                    $need_delete = 0;
                    $check_url = substr($pic['pic_cover'], 0, 4);
                    if ($check_url == 'http') {
                        $dir = './upload/' . $this->website_id . '/wx_ticket_pic/';
                        if (!is_dir($dir)) {
                            $res = mkdir(iconv("UTF-8", "GBK", $dir), 0777, true);
                        }
                        $file_name = time() . '.jpg';
                        $this->saveImage($pic['pic_cover'], $dir . $file_name);
                        $need_delete = 1;
                        $pic_url = '/upload/' . $this->website_id . '/wx_ticket_pic/' . $file_name;
                    } else {
                        $pic_url = __IMG($pic['pic_cover']);
                    }
                    $card_pic = $weixin_card->uploadLogo($pic_url);
                    if ($need_delete == 1) {
                        unlink('.' . $pic_url);
                    }
                    if (!empty($card_pic['url'])) {
                        $card_info['icon_url'] = $card_pic['url'];
                    }
                    $website = new WebSite();
                    $web_info = $website->getWebSiteInfo();
                    if ($web_info) {
                        $card_info['brand_name'] = $web_info['mall_name'];
                        if ($web_info['logo']) {
                            $logo = substr($web_info['logo'], 0, 4);
                        } else {
                            $web_info['logo'] = "public/static/images/card_logo.png";
                            $logo = substr($web_info['logo'], 0, 4);
                        }
                        //需要将外链图片先存放到服务器，再传入微信后再删除掉
                        $need_delete = 0;
                        $check_url = substr($logo, 0, 4);
                        if ($check_url == 'http') {
                            $dir = './upload/' . $this->website_id . '/wx_ticket_pic/';
                            if (!is_dir($dir)) {
                                $res = mkdir(iconv("UTF-8", "GBK", $dir), 0777, true);
                            }
                            $file_name = time() . '.jpg';
                            $this->saveImage($web_info['logo'], $dir . $file_name);
                            $need_delete = 1;
                            $logo_url = '/upload/' . $this->website_id . '/wx_ticket_pic/' . $file_name;
                        } else {
                            $logo_url = __IMG($web_info['logo']);
                        }
                        $card_pic = $weixin_card->uploadLogo($logo_url);
                        if ($need_delete == 1) {
                            unlink('.' . $logo_url);
                        }
                        if (!empty($card_pic['url'])) {
                            $card_info['logo_url'] = $card_pic['url'];
                        }
                    }
                    $custom_url = getDomain($this->website_id) . '/wap/packages/consumercard/detail';
                    $card_info['custom_url'] = $custom_url;
                    if (getAddons('shop', $this->website_id)) {
                        //获取店铺电话
                        $shop_model = new VslShopModel();
                        $shop_info = $shop_model::get(['shop_id' => $shopid]);
                        $card_info['service_phone'] = $shop_info['shop_phone'];
                    } else {
                        $card_info['service_phone'] = '';
                    }
                    if ($verificationinfo['valid_type'] == 1) {
                        $card_info['type'] = 'DATE_TYPE_FIX_TERM';
                        $card_info['fixed_begin_term'] = 0;
                        $card_info['fixed_term'] = $verificationinfo['valid_days'];
                    } else {
                        $card_info['type'] = 'DATE_TYPE_FIX_TIME_RANGE';
                        $card_info['begin_timestamp'] = time();
                        $card_info['end_timestamp'] = $verificationinfo['invalid_time'];
                    }
                    $card_info['quantity'] = $stock;
                    $ticket_result = $weixin_card->createCard($card_info);
                    //判断是否创建成功
                    if (empty($ticket_result['card_id'])) {
                        return 0;
                    }
                    $ticket_data = array(
                        'goods_id' => $this->goods->goods_id,
                        'card_title' => $card_info['card_title'],
                        'card_color' => $card_info['card_color'],
                        'card_pic_id' => $card_info['card_pic_id'],
                        'card_descript' => $card_info['card_descript'],
                        'store_service' => $card_info['store_service'],
                        'op_tips' => $card_info['op_tips'],
                        'send_set' => $card_info['send_set']
                    );
                    $ticket->save($ticket_data);
                    $this->goods->save(['wx_card_id' => $ticket_result['card_id']], ['goods_id' => $goods_id]);
                }

                //如果有勾选核销门店,将商品sku信息添加到门店商品sku表中
                if ($verificationinfo['store_list']) {
                    $skuModel = new VslGoodsSkuModel();
                    $sku_list = $skuModel->getQuery(['goods_id' => $this->goods->goods_id], '*', '');
                    $storeGoodsSkuModel = new VslStoreGoodsSkuModel();
                    $res = [];
                    foreach ($verificationinfo['store_list'] as $key => $val) {
                        foreach ($sku_list as $k => $v) {
                            $data = [
                                'goods_id' => $this->goods->goods_id,
                                'website_id' => $this->website_id,
                                'shop_id' => $this->instance_id,
                                'sku_id' => $v['sku_id'],
                                'sku_name' => $v['sku_name'],
                                'attr_value_items' => $v['attr_value_items'],
                                'price' => $v['price'],
                                'market_price' => $v['market_price'],
                                'stock' => $v['stock'],
                                'store_id' => $val,
                                'create_time' => time(),
                                'bar_code' => empty($v['sku_name']) ? $item_no : $v['code']
                            ];
                            $res[] = $data;
                            //将门店商品的库存插入到redis
                            $redis_key = 'store_goods_'.$this->goods->goods_id.'_'.$v['sku_id'];
                            $redis->set($redis_key, $v['stock']);
                        }
                        unset($v);
                    }
                    unset($val);
                    $storeGoodsSkuModel->saveAll($res, true);
                }
            } else {/*修改*/
                //如果是供应商商品，则不修改运费设置、结算方式
                if($goods_info['supplier_info']) {
                    unset($data_goods['shipping_fee'],$data_goods['shipping_fee_id'],$data_goods['shipping_fee_type'],$data_goods['payment_type'],$data_goods['supplier_rebate'],$data_goods['goods_count'],$data_goods['goods_volume'],$data_goods['goods_weight']);
                }
                unset($data_goods['goods_type']);//禁止修改商品类型
                //先获取到修改前此商品对应的门店列表
                $before_stroe_list = $goods_info['store_list'];
                $before_stroe_list = explode(',', $before_stroe_list);

                $data_goods['max_buy_time'] = $goods_info['max_buy'] == $max_buy ? $goods_info['max_buy_time'] : time();//如果max_buy变量，时间跟着变
                $data_goods['update_time'] = time();
                //供应商商品编辑时，修改状态
                $data_goods['supplier_pick_status'] = 1;
                $res = $this->goods->save($data_goods, [
                    'goods_id' => $goods_id
                ]);
                $data_goods['goods_id'] = $goods_id;
                //知识付费商品
                if ($goods_type == 4) {
                    //编辑知识付费商品时，每次都重新更新
                    $knowledge_payment_content_model = new VslKnowledgePaymentContentModel();
                    $del_condition = [
                        'website_id' => $this->website_id,
                        'shop_id' => $this->instance_id,
                        'goods_id' => $goods_id,
                    ];
                    $knowledge_payment_content_model->delData($del_condition);
                    foreach ($payment_content as $k => $v) {
                        $knowledge_payment_content_model = new VslKnowledgePaymentContentModel();
                        $v['goods_id'] = $goods_id;
                        $v['website_id'] = $this->website_id;
                        $v['shop_id'] = $this->instance_id;
                        $v['create_time'] = time();
                        $knowledge_payment_content_model->save($v);
                    }
                    unset($v);
                }

                //修改商品时，判断有没有修改核销门店，取消勾选了就要删除对应门店的商品，添加新勾选门店对应的商品
                if ($verificationinfo['store_list']) {
                    $storeGoodsModel = new VslStoreGoodsModel();
                    $skuModel = new VslGoodsSkuModel();
                    $storeGoodsSkuModel = new VslStoreGoodsSkuModel();
                    $verificationinfo['store_list'] = explode(',', $verificationinfo['store_list']);
                    foreach ($verificationinfo['store_list'] as $k => $v) {
                        //每次编辑都同步商品名称，主图，图片数组到门店
                        $storeGoodsModel = new VslStoreGoodsModel();
                        $update_data = [
                            'goods_name' => $data_goods['goods_name'],
                            'picture' => $data_goods['picture'],
                            'img_id_array' => $data_goods['img_id_array']
                        ];
                        $storeGoodsModel->save($update_data, ['goods_id' => $goods_id, 'store_id' => $v]);
                    }
                    unset($v);
                    $condition = [
                        'goods_id' => $goods_id
                    ];
                    $store_id_list = $storeGoodsModel->Query($condition, 'store_id');
                    //如果有差异，说明修改了核销门店
                    if (count($verificationinfo['store_list']) != count($store_id_list)) {
                        if (count($verificationinfo['store_list']) > count($store_id_list)) {# 大于插入
                            $diff = array_diff_assoc($verificationinfo['store_list'], $store_id_list);
                            $diff = array_values($diff);
                            foreach ($diff as $key => $val) {
                                $list = [
                                    'goods_id' => $goods_id,
                                    'website_id' => $data_goods['website_id'],
                                    'shop_id' => $data_goods['shop_id'],
                                    'goods_name' => $data_goods['goods_name'],
                                    'category_id' => $data_goods['category_id'],
                                    'category_id_1' => $data_goods['category_id_1'],
                                    'category_id_2' => $data_goods['category_id_2'],
                                    'category_id_3' => $data_goods['category_id_3'],
                                    'picture' => $data_goods['picture'],
                                    'stock' => $data_goods['stock'],
                                    'market_price' => $data_goods['market_price'],
                                    'price' => $data_goods['price'],
                                    'img_id_array' => $data_goods['img_id_array'],
                                    'sales' => $data_goods['real_sales'],
                                    'state' => 0,
                                    'store_id' => $val,
                                    'create_time' => time()
                                ];
                                $arr[] = $list;
                                //增加sku信息
                                $sku_list = $skuModel->getQuery(['goods_id' => $goods_id], '*', '');
                                foreach ($sku_list as $k => $v) {
                                    $data = [
                                        'goods_id' => $goods_id,
                                        'website_id' => $this->website_id,
                                        'shop_id' => $data_goods['shop_id'],
                                        'sku_id' => $v['sku_id'],
                                        'sku_name' => $v['sku_name'],
                                        'attr_value_items' => $v['attr_value_items'],
                                        'price' => $v['price'],
                                        'market_price' => $v['market_price'],
                                        'stock' => $v['stock'],
                                        'store_id' => $val,
                                        'create_time' => time(),
                                        'bar_code' => empty($v['sku_name']) ? $data_goods['item_no'] : $v['code']
                                    ];
                                    $lists[] = $data;
                                    //将门店商品的库存插入到redis
                                    $redis_key = 'store_goods_'.$goods_id.'_'.$v['sku_id'];
                                    $redis->set($redis_key, $v['stock']);
                                }
                                unset($v);
                            }
                            unset($val);
                            $storeGoodsSkuModel->saveAll($lists, true);
                            $storeGoodsModel->saveAll($arr, true);
                        } else {# 小于 删除
                            //删除取消勾选的门店
                            $diff = array_diff_assoc($store_id_list, $verificationinfo['store_list']);
                            $diff = array_values($diff);
                            if ($diff) {
                                foreach ($diff as $key => $val) {
                                    $data = [
                                        'store_id' => $val,
                                        'goods_id' => $goods_id
                                    ];
                                    $storeGoodsSkuModel->delData($data);
                                    $storeGoodsModel->delData($data);
                                }
                                unset($val);
                            }
                            //增加新勾选的门店
                            $differ = array_diff_assoc($verificationinfo['store_list'], $before_stroe_list);
                            $differ = array_values($differ);
                            if ($differ) {
                                foreach ($differ as $key => $val) {
                                    $list = [
                                        'goods_id' => $goods_id,
                                        'website_id' => $data_goods['website_id'],
                                        'shop_id' => $data_goods['shop_id'],
                                        'goods_name' => $data_goods['goods_name'],
                                        'category_id' => $data_goods['category_id'],
                                        'category_id_1' => $data_goods['category_id_1'],
                                        'category_id_2' => $data_goods['category_id_2'],
                                        'category_id_3' => $data_goods['category_id_3'],
                                        'picture' => $data_goods['picture'],
                                        'stock' => $data_goods['stock'],
                                        'market_price' => $data_goods['market_price'],
                                        'price' => $data_goods['price'],
                                        'img_id_array' => $data_goods['img_id_array'],
                                        'sales' => $data_goods['real_sales'],
                                        'state' => 0,
                                        'store_id' => $val,
                                        'create_time' => time()
                                    ];
                                    $arr[] = $list;
                                    //增加sku信息
                                    $sku_list = $skuModel->getQuery(['goods_id' => $goods_id], '*', '');
                                    foreach ($sku_list as $k => $v) {
                                        $data = [
                                            'goods_id' => $goods_id,
                                            'website_id' => $this->website_id,
                                            'shop_id' => $data_goods['shop_id'],
                                            'sku_id' => $v['sku_id'],
                                            'sku_name' => $v['sku_name'],
                                            'attr_value_items' => $v['attr_value_items'],
                                            'price' => $v['price'],
                                            'market_price' => $v['market_price'],
                                            'stock' => $v['stock'],
                                            'store_id' => $val,
                                            'create_time' => time(),
                                            'bar_code' => empty($v['sku_name']) ? $data_goods['item_no'] : $v['code']
                                        ];
                                        //将门店商品的库存插入到redis
                                        $redis_key = 'store_goods_'.$goods_id.'_'.$v['sku_id'];
                                        $redis->set($redis_key, $v['stock']);
                                        $lists[] = $data;
                                    }
                                    unset($v);
                                }
                                unset($val);
                                $storeGoodsSkuModel->saveAll($lists, true);
                                $storeGoodsModel->saveAll($arr, true);
                            }
                        }
                    } else {
                        //此种情况是取消n个门店的同时又增加n个门店
                        //删除取消勾选的门店
                        $del_diff = array_diff_assoc($before_stroe_list, $verificationinfo['store_list']);
                        $del_diff = array_values($del_diff);
                        if ($del_diff) {
                            foreach ($del_diff as $key => $val) {
                                $data = [
                                    'store_id' => $val,
                                    'goods_id' => $goods_id
                                ];
                                $storeGoodsSkuModel->delData($data);
                                $storeGoodsModel->delData($data);
                            }
                            unset($val);
                        }
                        //增加新勾选的门店
                        $add_diff = array_diff_assoc($verificationinfo['store_list'], $store_id_list);
                        $add_diff = array_values($add_diff);
                        if ($add_diff) {
                            foreach ($add_diff as $key => $val) {
                                $list = [
                                    'goods_id' => $goods_id,
                                    'website_id' => $data_goods['website_id'],
                                    'shop_id' => $data_goods['shop_id'],
                                    'goods_name' => $data_goods['goods_name'],
                                    'category_id' => $data_goods['category_id'],
                                    'category_id_1' => $data_goods['category_id_1'],
                                    'category_id_2' => $data_goods['category_id_2'],
                                    'category_id_3' => $data_goods['category_id_3'],
                                    'picture' => $data_goods['picture'],
                                    'stock' => $data_goods['stock'],
                                    'market_price' => $data_goods['market_price'],
                                    'price' => $data_goods['price'],
                                    'img_id_array' => $data_goods['img_id_array'],
                                    'sales' => $data_goods['real_sales'],
                                    'state' => 0,
                                    'store_id' => $val,
                                    'create_time' => time()
                                ];
                                $arr[] = $list;
                                //增加sku信息
                                $sku_list = $skuModel->getQuery(['goods_id' => $goods_id], '*', '');
                                foreach ($sku_list as $k => $v) {
                                    $data = [
                                        'goods_id' => $goods_id,
                                        'website_id' => $this->website_id,
                                        'shop_id' => $data_goods['shop_id'],
                                        'sku_id' => $v['sku_id'],
                                        'sku_name' => $v['sku_name'],
                                        'attr_value_items' => $v['attr_value_items'],
                                        'price' => $v['price'],
                                        'market_price' => $v['market_price'],
                                        'stock' => $v['stock'],
                                        'store_id' => $val,
                                        'create_time' => time(),
                                        'bar_code' => empty($v['sku_name']) ? $data_goods['item_no'] : $v['code']
                                    ];
                                    //将门店商品的库存插入到redis
                                    $redis_key = 'store_goods_'.$goods_id.'_'.$v['sku_id'];
                                    $redis->set($redis_key, $v['stock']);
                                    $lists[] = $data;
                                }
                                unset($v);
                            }
                            unset($val);
                            $storeGoodsSkuModel->saveAll($lists, true);
                            $storeGoodsModel->saveAll($arr, true);
                        }
                    }
                }
                //此种情况是取消了所有的核销门店
                if ($before_stroe_list && empty($verificationinfo['store_list'])) {
                    $storeGoodsModel = new VslStoreGoodsModel();
                    $storeGoodsSkuModel = new VslStoreGoodsSkuModel();
                    $where = ['goods_id' => $goods_id];
                    $storeGoodsModel->delData($where);
                    $storeGoodsSkuModel->delData($where);
                }
                hook("goodsSaveSuccess", $data_goods);
                if (!empty($sku_array)) {
                    $sku_list_array = explode('§', $sku_array);
                    if (empty($sku_list_array[0])) {
                        unset($sku_list_array[0]);//删掉空数据
                    }
                    $this->deleteSkuItem($goods_id, $sku_list_array);
                    foreach ($sku_list_array as $k => $v) {
                        $res = $this->addOrUpdateGoodsSkuItem($goods_id, $v, $specArray);
                        if ($res > 1) {
                            //如果此商品有对应的核销门店，修改时就要更新sku信息到门店商品sku表
                            if ($verificationinfo['store_list']) {
                                $storeGoodsSkuModel = new VslStoreGoodsSkuModel();
                                $sku_item = explode('¦', $v);
                                $sku_name = $this->createSkuName($sku_item[0], $specArray);
                                foreach ($verificationinfo['store_list'] as $key => $val) {
                                    $data = [
                                        'goods_id' => $goods_id,
                                        'website_id' => $this->website_id,
                                        'shop_id' => $this->instance_id,
                                        'sku_id' => $res,
                                        'sku_name' => $sku_name,
                                        'attr_value_items' => $sku_item[0],
                                        'price' => $sku_item[1],
                                        'market_price' => $sku_item[2],
                                        'stock' => $sku_item[4],
                                        'store_id' => $val,
                                        'create_time' => time(),
                                        'bar_code' => $sku_item[5]
                                    ];
                                    //将门店商品的库存插入到redis
                                    $redis_key = 'store_goods_'.$goods_id.'_'.$res;
                                    $redis->set($redis_key, $sku_item[4]);
                                    $result[] = $data;
                                    //更新门店商品表
                                    $data2 = [
                                        'price' => $sku_item[1],
                                        'market_price' => $sku_item[2],
                                        'stock' => $sku_item[4],
                                    ];
                                    $store_goods_model = new VslStoreGoodsModel();
                                    $store_goods_model->save($data2, ['website_id' => $this->website_id, 'goods_id' => $goods_id, 'store_id' => $val]);
                                }
                                unset($val);
                            }
                        }

                        if (!$res) {
                            $error = 3;
                        }
                    }
                    unset($v);
                    if ($result) {
                        $storeGoodsSkuModel->saveAll($result, true);
                    }
                    //如果此商品有对应的核销门店，修改时就要更新商品货号到门店商品sku表
                    if ($verificationinfo['store_list']) {
                        $skuModel = new VslGoodsSkuModel();
                        $sku_list = $skuModel->getQuery(['goods_id' => $goods_id], '*', '');
                        foreach ($sku_list as $k => $v) {
                            $storeGoodsSkuModel = new VslStoreGoodsSkuModel();
                            $data = [
                                'bar_code' => $v['code']
                            ];
                            $storeGoodsSkuModel->save($data,['sku_id' => $v['sku_id'],'website_id' => $this->website_id]);
                        }
                        unset($v);
                    }
                    $goods_sku = new VslGoodsSkuModel();
                    $del_sku = $goods_sku->destroy([
                        'goods_id' => $goods_id,
                        'sku_name' => array(
                            'EQ',
                            ''
                        )
                    ]);

                    // 修改时先删除原来的规格图片
                    $this->deleteGoodsSkuPicture([
                        "goods_id" => $goods_id
                    ]);
                    // sku图片添加
                    $sku_picture_array = array();
                    if (!empty($sku_picture_values)) {
                        $sku_picture_array = json_decode($sku_picture_values, true);
                        foreach ($sku_picture_array as $k => $v) {
                            $res = $this->addGoodsSkuPicture($shopid, $goods_id, $v["spec_id"], $v["spec_value_id"], $v["img_ids"]);
                            if (!$res) {
                                $error = 1;
                            }
                        }
                        unset($v);
                    }
                } else {
                    $sku_data = array(
                        'goods_id' => $goods_id,
                        'sku_name' => '',
                        'market_price' => $market_price,
                        'price' => $price,
                        'promote_price' => $price,
                        'cost_price' => $cost_price,
                        'stock' => $stock,
                        'picture' => 0,
                        'code' => $code,
                        'QRcode' => '',
                        'update_date' => time(),
                        'website_id' => $this->website_id,
                        'sku_max_buy' => $max_buy,
                        'sku_max_buy_time' => $data_goods['max_buy_time']
                    );

                    $goods_sku = new VslGoodsSkuModel();
                    $sku = $goods_sku->getQuery([
                        'goods_id' => $goods_id
                    ], 'sku_id,sku_name', ''); // 当前SKU没有则添加，否则修改

                    if (count($sku) > 1 || (count($sku) == 1 && $sku[0]['sku_name'])) {//多规格商品改为无规格商品，删除原规格数据，新增一条
                        $goods_sku->destroy(['goods_id' => $goods_id]);
                        $res = $goods_sku->save($sku_data);
                        //将普通商品的库存插入到redis
                        $redis_key = 'goods_'.$goods_id.'_'.$res;
                        $redis->set($redis_key, $stock);
                        //如果此商品有对应的核销门店，修改时就要更新sku信息到门店商品sku表
                        if ($verificationinfo['store_list']) {
                            $storeGoodsSkuModel = new VslStoreGoodsSkuModel();
                            $result = [];
                            $del_sku_condition = [
                                'website_id' => $this->website_id,
                                'shop_id' => $this->instance_id,
                                'goods_id' => $goods_id,
                            ];
                            $storeGoodsSkuModel->delData($del_sku_condition);
                            foreach ($verificationinfo['store_list'] as $key => $val) {
                                $data = [
                                    'goods_id' => $goods_id,
                                    'website_id' => $this->website_id,
                                    'shop_id' => $this->instance_id,
                                    'sku_id' => $goods_sku->sku_id,
                                    'sku_name' => '',
                                    'attr_value_items' => '',
                                    'price' => $price,
                                    'market_price' => $market_price,
                                    'stock' => $stock,
                                    'store_id' => $val,
                                    'create_time' => time(),
                                    'bar_code' => $item_no,
                                ];
                                $result[] = $data;
                            }
                            unset($val);
                            $storeGoodsSkuModel->saveAll($result, true);
                        }
                    } elseif (count($sku) == 1 && !$sku[0]['sku_name']) {
                        $res = $goods_sku->save($sku_data, ['sku_id' => $sku[0]['sku_id']]);
                        //将普通商品的库存插入到redis
                        $redis_key = 'goods_'.$goods_id.'_'.$sku[0]['sku_id'];
                        $redis->set($redis_key, $stock);
                        if ($verificationinfo['store_list']) {
                            foreach ($verificationinfo['store_list'] as $key => $val) {
                                $storeGoodsSkuModel = new VslStoreGoodsSkuModel();
                                $data = [
                                    'bar_code' => $item_no,
                                ];
                                $storeGoodsSkuModel->save($data,['store_id' => $val,'goods_id' => $goods_id,'website_id' => $this->website_id]);
                            }
                            unset($val);
                        }
                    } else {
                        $res = $goods_sku->save($sku_data);
                    }
                }

                //如果是虚拟商品，微信卡券开启则修改卡券
                if ($goods_type == 0 && $is_wxcard == 1) {
                    if ($goods_info['wx_card_id']) {
                        $ticket = new VslGoodsTicketModel();
                        $weixin_card = new WeixinCard();
                        $card_info['card_id'] = $goods_info['wx_card_id'];
                        if ($verificationinfo['valid_type'] == 1) {
                            $card_info['type'] = 'DATE_TYPE_FIX_TERM';
                            $card_info['fixed_begin_term'] = 0;
                            $card_info['fixed_term'] = $verificationinfo['valid_days'];
                        } else {
                            $card_info['type'] = 'DATE_TYPE_FIX_TIME_RANGE';
                            $card_info['end_timestamp'] = $verificationinfo['invalid_time'];
                        }
                        $card_info['quantity'] = $stock;
                        $card_info['quantity2'] = $goods_info['stock'];
                        $ticket_result = $weixin_card->updateCard($card_info);
                        //判断是否修改成功
                        if ($ticket_result['errmsg'] != 'ok') {
                            return 0;
                        }
                    }
                }
                $this->modifyGoodsPromotionPrice($goods_id);
            }

            // 每次都要重新更新商品属性
            $goods_attribute_model = new VslGoodsAttributeModel();
            $goods_attribute_model->destroy([
                'goods_id' => $goods_id
            ]);
            if (!empty($goods_attribute)) {
                if (!is_array($goods_attribute)) {
                    $goods_attribute_array = json_decode($goods_attribute, true);
                } else {
                    $goods_attribute_array = $goods_attribute;
                }
                if (!empty($goods_attribute_array)) {
                    foreach ($goods_attribute_array as $k => $v) {
                        $goods_attribute_model = new VslGoodsAttributeModel();
                        $data = array(
                            'goods_id' => $goods_id,
                            'shop_id' => $shopid,
                            'attr_value_id' => $v['attr_value_id'],
                            'attr_value' => $v['attr_value'],
                            'attr_value_name' => $v['attr_value_name'],
                            'sort' => $v['sort'],
                            'create_time' => time(),
                            'website_id' => $this->website_id,
                        );
                        $goods_attribute_model->save($data);
                    }
                    unset($v);
                }
            }

            // 商品权限折扣
            $this->postGoodsPowerDiscount($discount_bonus, $goods_id);
            if ($error == 0) {
                $this->goods->commit();
                $this->addOrUpdateGoodsToEs($goods_id);
                return $goods_id;
            } else {
                $this->goods->rollback();
                return 0;
            }
        } catch
        (\Exception $e) {
            recordErrorLog($e);
            $this->goods->rollback();
            return $e->getMessage();
        }
        // TODO Auto-generated method stub
    }

    /**
     * 从网上下载图片保存到服务器
     * @param $path 图片网址
     * @param $image_name 保存到服务器的路径 './public/upload/users_avatar/'.time()
     */
    //保存图片
    function saveImage($path, $image_name)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $img = curl_exec($ch);
        curl_close($ch);
        //$image_name就是要保存到什么路径,默认只写文件名的话保存到根目录
        $fp = fopen($image_name, 'w');//保存的文件名称用的是链接里面的名称
        fwrite($fp, $img);
        fclose($fp);

    }


    /**
     * 修改 商品的 促销价格
     *
     * @param unknown $goods_id
     */
    protected function modifyGoodsPromotionPrice($goods_id)
    {
        if(!getAddons('discount', $this->website_id)){
            return;
        }
        $discount_goods = new DiscountServer();
        $goods_sku = new VslGoodsSkuModel();
        $discount = $discount_goods->getDiscountByGoodsid($goods_id);
        if ($discount == -1) {
            // 当前商品没有参加活动
        } else {
            // 当前商品有正在进行的活动
            // 查询出商品的价格进行修改
            $goods_price = $this->getGoodsDetailById($goods_id);
            $this->updateGoods([
                'goods_id' => $goods_id
            ], [
                'promotion_price' => $goods_price['price'] * $discount / 10
            ], $goods_id);
            // 查询出所有的商品sku价格进行修改
            $goods_sku_list = $goods_sku->getQuery([
                'goods_id' => $goods_id
            ], 'sku_id, price', '');
            foreach ($goods_sku_list as $k => $v) {
                $goods_sku = new VslGoodsSkuModel();
                $goods_sku->save([
                    'promote_price' => $v['price'] * $discount / 10
                ], [
                    'sku_id' => $v['sku_id']
                ]);
            }
            unset($v);
        }
    }

    /**
     * 获取单个商品的sku属性
     *
     * {@inheritdoc}
     *
     * @see \data\api\IGoods::getGoodsSkuAll()
     */
    public function getGoodsAttribute($goods_id)
    {
        // 查询商品主表
        $goods_detail = $this->getGoodsDetailById($goods_id);
        $spec_list = array();
        if (!empty($goods_detail) && !empty($goods_detail['goods_spec_format']) && $goods_detail['goods_spec_format'] != "[]") {
            $spec_list = json_decode($goods_detail['goods_spec_format'], true);
            if (!empty($spec_list)) {
                foreach ($spec_list as $k => $v) {
                    foreach ($v["value"] as $m => $t) {
                        if (empty($v["show_type"])) {
                            $spec_list[$k]["show_type"] = 1;
                        }

                        $spec_list[$k]["value"][$m]["picture"] = $this->getGoodsSkuPictureBySpecId($goods_id, $spec_list[$k]["value"][$m]['spec_id'], $spec_list[$k]["value"][$m]['spec_value_id']);
                    }
                    unset($t);
                }
                unset($v);
            }
        }
        return $spec_list;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IGoods::editGoodsOffline()
     */
    public function ModifyGoodsOffline($condition)
    {
        $data = array(
            "state" => 0,
            'update_time' => time()
        );
        $result = $this->updateGoods(['goods_id' => ['in',$condition]], $data);
        if ($result > 0) {
            // 商品下架成功钩子
            hook("goodsOfflineSuccess", [
                'goods_id' => $condition
            ]);
            return SUCCESS;
        } else {
            return UPDATA_FAIL;
        }
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IGoods::editGoodsOnline()
     */
    public function ModifyGoodsOnline($condition)
    {
        $data = array(
            "state" => 1,
            'update_time' => time()
        );
        //如果上架的商品是供应商商品，并且是被供应商下架或删除了，则无法上架
        $goods_ids = explode(',', $condition);
        foreach ($goods_ids as $k => $v) {
            $goods_info = $this->getGoodsDetailById($v);
            if ($goods_info['supplier_operation'] && $goods_info['supplier_info']) {
                if ($goods_info['supplier_operation'] == 1) {
                    return -2;
                } elseif ($goods_info['supplier_operation'] == 2) {
                    return -3;
                }
            }
        }
        unset($v);

        //如果是供应商端的操作，就判断该供应商发布的商品是否需要审核
        $model = $this->getRequestModel();
        if($model == 'supplier' && $this->supplier_id) {
            $supplier_mdl = new VslSupplierModel();
            $check_goods = $supplier_mdl->Query(['supplier_id' => $this->supplier_id],'check_goods')[0];
            if($check_goods) {
                $data['state'] = 11;
            }
        }else{
        if ($this->instance_id) {
            $shop = new \addons\shop\service\Shop();
            $shop_info = $shop->getShopDetail($this->instance_id);
            if ($shop_info['base_info']['shop_audit']) {
                $data['state'] = 11;
            }
        }
        }
        $result = $this->updateGoods(['goods_id' => ['in',$condition]], $data);
        if ($result > 0) {
            // 商品上架成功钩子
            hook("goodsOnlineSuccess", [
                'goods_id' => $condition
            ]);
            return SUCCESS;
        } else {
            return UPDATA_FAIL;
        }
    }

    //检测是否是活动商品
    public function checkIsPromotionGoods($goods_id)
    {
        $goods_info = $this->getGoodsDetailById($goods_id);
        if ($goods_info['promotion_type'] == 0) {
            return true;
        } else {
            return false;
        }
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IGoods::deleteGoods()
     */
    public function deleteGoods($goods_id)
    {
        $redis = connectRedis();
        $storeGoods = new VslStoreGoodsModel();
        $storeGoodsSku = new VslStoreGoodsSkuModel();
        $this->goods->startTrans();
        try {
            // 商品删除之前钩子
            hook("goodsDeleteBefore", [
                'goods_id' => $goods_id
            ]);
            //循环判断商品是否是活动商品
            if (strpos($goods_id, ',') === false) {
                if ($this->checkIsPromotionGoods($goods_id) == false) {
                    return DELETE_PROMOTIONGOODS_FAIL;
                };
            } else {
                $check_goods_array = explode(',', $goods_id);
                foreach ($check_goods_array as $k => $v) {
                    if ($this->checkIsPromotionGoods($goods_id) == false) {
                        return DELETE_PROMOTIONGOODS_FAIL;
                    };
                }
                unset($v);

            }

            $goods_ids = explode(',', $goods_id);
            foreach ($goods_ids as $k => $v) {
                $goods_info = $this->getGoodsDetailById($v);
                if ($goods_info['promotion_type']) {
                    return DELETE_PROMOTIONGOODS_FAIL;
                };
                if ($goods_info['supplier_id']) {/*供应商商品*/
                    //如果删除的是供应商发布的商品，就要下架店铺上架的该商品
                    if ($goods_info['shop_list']!== '' && $goods_info['shop_list']!== null){
                        $picked_supplier_shop_ids = explode(',',$goods_info['shop_list']);
                        foreach ($picked_supplier_shop_ids as $shop_id) {
                            $this->updateGoods([
                                'website_id' => $this->website_id,
                                'shop_id' => $shop_id,
                                'supplier_goods_id' => $v
                            ], [
                                'state' => 0,
                                'supplier_operation' => 1
                            ]);
                        }
                    }
                }else if ($goods_info['supplier_info'] && $goods_info['supplier_goods_id']) {
                    //如果是店铺删除从供应商中挑选的商品，那该店铺就要与供应商商品解绑关系
                    $shop_id = $goods_info['shop_id'];
                    $supplier_goods_info = $this->getGoodsDetailById($goods_info['supplier_goods_id']);
                    $shop_list = $supplier_goods_info['shop_list'];
                    $shop_list_second = $supplier_goods_info['shop_list_second'];
                    if($supplier_goods_info['shop_list'] !=='' && $supplier_goods_info['shop_list'] !== null){
                        $shop_list_arr = explode(',',$supplier_goods_info['shop_list']);
                        $shop_list_arr = array_unique($shop_list_arr);
                        $key=array_search($shop_id, $shop_list_arr);
                        unset($shop_list_arr[$key]);
                        $shop_list = implode(',',$shop_list_arr);
                        $shop_list = trim($shop_list,',');
                    }
                    //重新编辑供应商商品后需要的店铺
                    if($supplier_goods_info['shop_list_second'] !=='' && $supplier_goods_info['shop_list_second'] !== null){
                        $shop_list_second_arr = explode(',',$supplier_goods_info['shop_list_second']);
                        $shop_list_second_arr = array_unique($shop_list_second_arr);
                        $key=array_search($shop_id, $shop_list_second_arr);
                        unset($shop_list_second_arr[$key]);
                        $shop_list_second = implode(',',$shop_list_second_arr);
                        $shop_list_second = trim($shop_list_second,',');
                    }
                    $this->updateGoods([
                        'goods_id' => $goods_info['supplier_goods_id']
                    ], [
                        'shop_list' => $shop_list,
                        'shop_list_second' => $shop_list_second,
                    ]);
                }
            }
            unset($v);
             // 将商品信息添加到商品回收库中
            $this->addGoodsDeleted($goods_id);
            $res = $this->goods->destroy($goods_id);

            if ($res > 0) {
                $goods_id_array = explode(',', $goods_id);
                $goods_sku_model = new VslGoodsSkuModel();
                $goods_attribute_model = new VslGoodsAttributeModel();
                $goods_sku_picture = new VslGoodsSkuPictureModel();
                foreach ($goods_id_array as $k => $v) {
                    // 删除商品sku
                    $goods_sku_model->destroy([
                        'goods_id' => $v
                    ]);
                    // 删除商品属性
                    $goods_attribute_model->destroy([
                        'goods_id' => $v
                    ]);
                    // 删除规格图片
                    $goods_sku_picture->destroy([
                        'goods_id' => $v
                    ]);
                    //从门店商品表中删除
                    $storeGoods->delData(['goods_id' => $v, 'website_id' => $this->website_id, 'shop_id' => $this->instance_id ]);
                    //从门店商品sku表中删除
                    $storeGoodsSku->delData(['goods_id' => $v, 'website_id' => $this->website_id, 'shop_id' => $this->instance_id]);

                    //如果是知识付费商品，则需把付费内容删除
                    $knowledge_payment_content_model = new VslKnowledgePaymentContentModel();
                    $knowledge_payment_content_model->delData(['goods_id' => $v, 'website_id' => $this->website_id, 'shop_id' => $this->instance_id]);
                    //清除商品在redis的库存
                    $goods_key = 'goods_'.$v.'*';
                    $store_goods_key = 'store_goods_'.$v.'*';
                    $redis->del($goods_key);
                    $redis->del($store_goods_key);
                }
                unset($v);
            }
            $this->goods->commit();
            if ($res > 0) {
                $this->deleteGoodsFromEs($goods_id);
                // 商品删除成功钩子
                hook("goodsDeleteSuccess", [
                    'goods_id' => $goods_id
                ]);
                if(getAddons('channel', $this->website_id)){
                    //将渠道商的这个商品的购物车去掉
                    $channel_cond['goods_id'] = $goods_id;
                    $channel_cond['channel_info'] = 'platform';
                    $channel_cart = new VslChannelCartModel();
                    $channel_cart->where($channel_cond)->delete();
                }
                return SUCCESS;
            } else {
                debugLog($res, 'deletegoods=>');
                return DELETE_FAIL;
            }
        } catch (\Exception $e) {
            recordErrorLog($e);
            $this->goods->rollback();
            return DELETE_FAIL;
        }
    }

    /**
     * 商品删除以前 将商品挪到 回收站中
     *
     * @param unknown $goods_ids
     */
    private function addGoodsDeleted($goods_ids)
    {
        $this->goods->startTrans();
        try {
            $goods_id_array = explode(',', $goods_ids);
            foreach ($goods_id_array as $k => $v) {
                // 得到商品的信息 备份商品
                $goods_info = $this->goods->get($v);
                if($this->model !='supplier' &&($goods_info['supplier_info'] || $goods_info['supplier_goods_id'])) {
                    //供应商发布的商品或者店铺从供应商中挑选的商品不进入回收站
                    continue;
                }
                $goods_delete_model = new VslGoodsDeletedModel();
                $goods_info = json_decode(json_encode($goods_info), true);
                $goods_delete_obj = $goods_delete_model->getInfo([
                    "goods_id" => $v
                ]);
                if (empty($goods_delete_obj)) {
                    $goods_info["update_time"] = time();
                    $goods_delete_model->save($goods_info);
                    // 商品的sku 信息备份
                    $goods_sku_model = new VslGoodsSkuModel();
                    $goods_sku_list = $goods_sku_model->getQuery([
                        "goods_id" => $v
                    ], "*", "");
                    foreach ($goods_sku_list as $goods_sku_obj) {
                        $goods_sku_deleted_model = new VslGoodsSkuDeletedModel();
                        $goods_sku_obj = json_decode(json_encode($goods_sku_obj), true);
                        $goods_sku_obj["update_date"] = time();
                        $goods_sku_deleted_model->save($goods_sku_obj);
                    }
                    unset($goods_sku_obj);
                    // 商品的属性 信息备份
                    $goods_attribute_model = new VslGoodsAttributeModel();
                    $goods_attribute_list = $goods_attribute_model->getQuery([
                        'goods_id' => $v
                    ], "*", "");
                    foreach ($goods_attribute_list as $goods_attribute_obj) {
                        $goods_attribute_delete_model = new VslGoodsAttributeDeletedModel();
                        $goods_attribute_obj = json_decode(json_encode($goods_attribute_obj), true);
                        $goods_attribute_delete_model->save($goods_attribute_obj);
                    }
                    unset($goods_attribute_obj);
                    // 商品的sku图片备份
                    $goods_sku_picture = new VslGoodsSkuPictureModel();
                    $goods_sku_picture_list = $goods_sku_picture->getQuery([
                        'goods_id' => $v
                    ], "*", "");
                    foreach ($goods_sku_picture_list as $goods_sku_picture_list_obj) {
                        $goods_sku_picture_delete = new VslGoodsSkuPictureDeleteModel();
                        $goods_sku_picture_list_obj = json_decode(json_encode($goods_sku_picture_list_obj), true);
                        $goods_sku_picture_delete->save($goods_sku_picture_list_obj);
                    }
                    unset($goods_sku_picture_list_obj);
                }
            }
            unset($v);
            $this->goods->commit();
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $this->goods->rollback();
            return $e->getMessage();
        }
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IGoods::getGoodsDetail()
     * types 1平台或者店铺端操作 0移动接口请求
     */
    public function getGoodsDetail($goods_id,$types = 0,$mic_goods = '')
    {

        // 查询商品主表
        $model = \think\Request::instance()->module();
        $condition = ['website_id' => $this->website_id, 'goods_id' => $goods_id];
        if ($model == 'platform' || $model == 'admin' || $this->port == 'admin' || $this->port == 'platform') {
            $condition['shop_id'] = $this->instance_id;
        }
        if($this->model == 'supplier'){
            $condition['supplier_id'] = $this->supplier_id;
        }

        $goods_detail = $this->getGoodsDetailByCondition($condition, '*');


        if ($goods_detail['supplier_goods_id']){
            //供应商关闭则不显示
            $supplierSer = new SupplierSer();
            $supplier_close= $supplierSer->checkSupplierStatusOfGoodsGoodsId($goods_detail['supplier_goods_id']);
            if (!$supplier_close){
                return [];
            }
        }
        if (!$goods_detail) {
            return null;
        }

        $discount_service = getAddons('discount', $this->website_id) ? new Discount() : '';
        $limit_discount_info = getAddons('discount', $this->website_id) ? $discount_service->getPromotionInfo($goods_id, $goods_detail['shop_id'], $this->website_id) : ['discount_num' => 10];
        $member_price = $goods_detail['price'];
        $member_discount = 1;
        $goods_detail['discount_choice'] = 1;
        $goods_detail['member_is_label'] = 0;
        $goods_detail['is_show_member_price'] = 0;
        $goods_detail['price_type'] = 0;
        if ($this->uid) {
            //查询商品是否开启PLUS会员价
            $is_member_discount = 1;
            $goodsPower = $this->getGoodsPowerDiscount($goods_id);
            $goodsPower = json_decode($goodsPower['value'],true);
            $plus_member = $goodsPower['plus_member'] ? : 0;
            if($plus_member == 1) {
                if(getAddons('membercard',$this->website_id)) {
                    $membercard = new \addons\membercard\server\Membercard();
                    $membercard_data = $membercard->checkMembercardStatus($this->uid);
                    if($membercard_data['status']) {
                        if($membercard_data['membercard_info']['is_member_discount']) {
                            //有会员折扣权益
                            $member_price = $member_price * $membercard_data['membercard_info']['member_discount'] / 10;
                            $member_discount = $membercard_data['membercard_info']['member_discount'] / 10;
                            $goods_detail['price_type'] = 1;
                            $goods_detail['discount_choice'] = 2;
                            $goods_detail['is_show_member_price'] = 1;
                            $goods_detail['member_is_label'] = 0;
                            $is_member_discount = 0;
                        }
                        if($membercard_data['membercard_info']['is_free_shipping']) {
                            //有全场包邮权益
                            $goods_detail['shipping_fee_type'] = 0;
                        }
                    }
                }
            }
            if($is_member_discount) {
                // 查询商品是否有开启会员折扣
                $goodsDiscountInfo = $this->getGoodsInfoOfIndependentDiscount($goods_id, $member_price, ['price' => $goods_detail['price'], 'shop_id' => $goods_detail['shop_id']]);
                if ($goodsDiscountInfo) {
                    if($goodsDiscountInfo['is_use'] == 1){
                        $goods_detail['price_type'] = 1; //会员折扣
                    }
                    $member_price = $goodsDiscountInfo['member_price'];
                    $member_discount = $goodsDiscountInfo['member_discount'];
                    $goods_detail['discount_choice'] = $goodsDiscountInfo['discount_choice'];
                    $goods_detail['is_show_member_price'] = $goodsDiscountInfo['is_show_member_price'];
                    $goods_detail['member_is_label'] = $goodsDiscountInfo['member_is_label'];
                }
            }
        }

        $goods_detail['member_price'] = $member_price;
        $goods_detail['member_discount'] = $member_discount;


        if($types){
            // sku多图数据
            $sku_picture_list = $this->getGoodsSkuPicture($goods_id);
            $goods_detail["sku_picture_list"] = $sku_picture_list;
            // 查询商品分组表
            $goods_group = new VslGoodsGroupModel();
            $goods_group_list = $goods_group->all($goods_detail['group_id_array']);
            $goods_detail['goods_group_list'] = $goods_group_list;
        }

        // 查询商品sku表
        $goods_sku = new VslGoodsSkuModel();
        $goods_sku_detail = $goods_sku->where('goods_id=' . $goods_id)->select();
        if($goods_detail['goods_type'] == 6 && $goods_detail['form_base_id']){ //预约商品刷新库存
            //--
        }
        # 处理规格参数
        if($goods_sku_detail){
            foreach ($goods_sku_detail as $k => $goods_sku) {
                $goods_sku_detail[$k]['promote_price'] = $goods_sku_detail[$k]['price']; //商品原价
                $pprice =  $goods_sku_detail[$k]['price'];
                $goods_sku_detail[$k]['member_price'] = $member_price;
                if ($goods_detail['discount_choice'] == 2) {
                    $goods_detail['price_type'] = 1; //会员折扣
                    $goods_sku_detail[$k]['price'] = $member_price;
                }
                if ($goods_detail['discount_choice'] == 1 && $goodsDiscountInfo) {
                    $goods_detail['price_type'] = 1; //会员折扣
                    $goods_sku_detail[$k]['price'] = $pprice * $goodsDiscountInfo['member_discount'];
                }
                if(!$this->uid){ //未登陆
                    $goods_sku_detail[$k]['price'] = $pprice;
                }
                if ($limit_discount_info['discount_type'] == 1) {
                    $goods_detail['price_type'] = 2; //限时折扣
                    $goods_sku_detail[$k]['price'] = $pprice * $limit_discount_info['discount_num'] / 10;
                }
                if ($limit_discount_info['discount_type'] == 2) {
                    $goods_detail['price_type'] = 2; //限时折扣
                    $goods_sku_detail[$k]['price'] = $limit_discount_info['discount_num'];
                }
                if($types == 1 || $this->port == 'platform'){
                    $goods_sku_detail[$k]['price'] = $pprice;
                }
                if($mic_goods == 1){
                    $goods_sku_detail[$k]['price'] = $pprice;
                }
            }
            unset($goods_sku);
        }

        if($limit_discount_info['discount_num'] == 10){
            $limit_discount_info = []; //object异常 --
        }
        $goods_detail['limit_discount_info'] = $limit_discount_info;
        $goods_spec = new VslGoodsSpecModel();
        $goods_detail['sku_list'] = $goods_sku_detail;


        $spec_list = json_decode($goods_detail['goods_spec_format'], true);
        $album = new Album();
        if (!empty($spec_list)) {
            foreach ($spec_list as $k => $v) {
                $sort = $goods_spec->getInfo([
                    "spec_id" => $v['spec_id']
                ], "sort");
                $spec_list[$k]['sort'] = 0;
                if (!empty($sort)) {

                    $spec_list[$k]['sort'] = $sort['sort'];
                }
                foreach ($v["value"] as $m => $t) {
                    if (empty($v['show_type'])) {
                        $spec_list[$k]['show_type'] = 1;
                    }
                    // 查询SKU规格主图，没有返回0
                    $spec_list[$k]["value"][$m]["picture"] = $this->getGoodsSkuPictureBySpecId($goods_id, $spec_list[$k]["value"][$m]['spec_id'], $spec_list[$k]["value"][$m]['spec_value_id']);
                    if (is_numeric($t["spec_value_data"]) && $v["show_type"] == 3) {
                        $picture_detail = $album->getAlubmPictureDetail([
                            "pic_id" => $t["spec_value_data"]
                        ]);
                        if (!empty($picture_detail)) {
                            $spec_list[$k]["value"][$m]["spec_value_data_src"] = __IMG($picture_detail["pic_cover"]);
                        } else {
                            $spec_list[$k]["value"][$m]["spec_value_data_src"] = null;
                        }
                        $spec_list[$k]["value"][$m]["spec_value_data"] = $this->getGoodsSkuPictureBySpecId($goods_id, $spec_list[$k]["value"][$m]['spec_id'], $spec_list[$k]["value"][$m]['spec_value_id']);
                    } else {
                        $spec_list[$k]["value"][$m]["spec_value_data_src"] = null;
                    }
                }
                unset($t);
            }
            unset($v);
            // 排序字段
            $sort = array(
                'field' => 'sort'
            );

            $arrSort = array();
            foreach ($spec_list as $uniqid => $row) {
                foreach ($row as $key => $value) {
                    $arrSort[$key][$uniqid] = $value;
                }
                unset($value);
            }
            unset($row);
            array_multisort($arrSort[$sort['field']], SORT_ASC, $spec_list);
        }
        $goods_detail['spec_list'] = $spec_list;
        // 查询图片表
        $goods_img = new AlbumPictureModel();
        $order = "instr('," . $goods_detail['img_id_array'] . ",',CONCAT(',',pic_id,','))"; // 根据 in里边的id 排序
        $goods_img_list = $goods_img->getQuery([
            'pic_id' => [
                "in",
                $goods_detail['img_id_array']
            ]
        ], '*', $order);
        if (trim($goods_detail['img_id_array']) != "") {
            $img_temp_array = array();
            $img_array = explode(",", $goods_detail['img_id_array']);
            foreach ($img_array as $k => $v) {
                if (!empty($goods_img_list)) {
                    foreach ($goods_img_list as $t => $m) {
                        if ($m["pic_id"] == $v) {
                            $img_temp_array[] = $m;
                        }
                    }
                    unset($m);
                }
            }
            unset($v);
        }
        if($types){
            $goods_detail['picture_detail'] = $goods_detail['album_picture'];
        }
        $goods_detail["img_temp_array"] = $img_temp_array;
        $goods_detail['img_list'] = $goods_img_list;
        //活动图
        $activity_pic = $goods_detail['activity_pic'];
        $activity_pic_url = getApiSrc($goods_img->getInfo(['pic_id' => $activity_pic], 'pic_cover')['pic_cover']);
        $goods_detail['activity_pic_url'] = $activity_pic_url ? : '';
        $goods_detail['video'] = '';
        if ($goods_detail['video_id']) {
            $goods_detail['video'] = $goods_img->get($goods_detail['video_id']) ? $goods_img->get($goods_detail['video_id'])['pic_cover'] : '';
        }
        if($types){
            // 查询分类名称
            $category_name = $this->getGoodsCategoryName($goods_detail['category_id_1'], $goods_detail['category_id_2'], $goods_detail['category_id_3']);

            $cate_arr = explode(">", $category_name);
            $goods_detail['category_name_1'] = $cate_arr[0] ? trim($cate_arr[0]) : '';
            $goods_detail['category_name_2'] = $cate_arr[1] ? trim($cate_arr[1]) : '';
            $goods_detail['category_name_3'] = $cate_arr[2] ? trim($cate_arr[2]) : '';
            $goods_detail['category_name'] = $category_name;
            // 扩展分类
            $extend_category_array = array();

            if (!empty($goods_detail['extend_category_id'])) {
                $extend_category_ids = $goods_detail['extend_category_id'];
                $extend_category_id_1s = $goods_detail['extend_category_id_1'];
                $extend_category_id_2s = $goods_detail['extend_category_id_2'];
                $extend_category_id_3s = $goods_detail['extend_category_id_3'];
                $extend_category_id_str = explode(",", $extend_category_ids);
                $extend_category_id_1s_str = explode(",", $extend_category_id_1s);
                $extend_category_id_2s_str = explode(",", $extend_category_id_2s);
                $extend_category_id_3s_str = explode(",", $extend_category_id_3s);
                foreach ($extend_category_id_str as $k => $v) {
                    $extend_category_name = $this->getGoodsCategoryName($extend_category_id_1s_str[$k], $extend_category_id_2s_str[$k], $extend_category_id_3s_str[$k]);
                    $extend_category_array[] = array(
                        "extend_category_name" => $extend_category_name,
                        "extend_category_id" => $v,
                        "extend_category_id_1" => $extend_category_id_1s_str[$k],
                        "extend_category_id_2" => $extend_category_id_2s_str[$k],
                        "extend_category_id_3" => $extend_category_id_3s_str[$k]
                    );
                }
                unset($v);
            }
            $goods_detail['extend_category_name'] = "";
            $goods_detail['extend_category'] = $extend_category_array;
        }
        if($types){
            // 查询商品类型相关信息
            if ($goods_detail['goods_attribute_id'] > 0 || $goods_detail['goods_attribute_id'] == 0) {
                $attribute_model = new VslAttributeModel();
                $attribute_info = $attribute_model->getInfo([
                    'attr_id' => $goods_detail['goods_attribute_id']
                ], 'attr_name');
                $goods_detail['goods_attribute_name'] = $attribute_info['attr_name'];
                //修改排序，先按属性表的排序，再按商品属性排序

                $sql = "select a.`sort` as `sort`,b.`sort` as `value_sort`,b.`attr_id`,b.`goods_id`,b.`shop_id`,b.`attr_value_id`,b.`attr_value`,b.`attr_value_name`,b.`create_time`  from `vsl_attribute_value` as a right join `vsl_goods_attribute` as b on a.`attr_value_id` = b.`attr_value_id` where b.`goods_id` = $goods_id";
                $goods_attribute_list = Db::query($sql);
                if($goods_attribute_list){
					foreach($goods_attribute_list as $key_attr => $val_attr){
						$goods_attribute_list[$key_attr]['attr_value_name'] = htmlspecialchars_decode($val_attr['attr_value_name']);
					}
                }
                $goods_detail['goods_attribute_list'] = $goods_attribute_list;
            } else {
                $goods_detail['goods_attribute_name'] = '';
                $goods_detail['goods_attribute_list'] = array();
            }
        }
        $goods_detail['mansong_name'] = '';
        if(getAddons('fullcut', $this->website_id) && $types){
            // 查询商品满减送活动
            $goods_mansong = new Fullcut();
            $goods_detail['mansong_name'] = $goods_mansong->getGoodsMansongName($goods_id);
        }

        if (getAddons('shop', $this->website_id)) {
            $shop_model = new VslShopModel();
            $shop_name = $shop_model->getInfo(array(
                'shop_id' => $goods_detail['shop_id'],
                'website_id' => $goods_detail['website_id']
            ), 'shop_name');
            $goods_detail['shop_name'] = $shop_name['shop_name'];
        } else {
            $websiteService = new WebSite();
            $goods_detail['shop_name'] = $websiteService->getWebMallName($this->website_id);
        }
        if($types){
            // 查询商品规格图片
            $goos_sku_picture = new VslGoodsSkuPictureModel();
            $goos_sku_picture_query = $goos_sku_picture->getQuery([
                "goods_id" => $goods_id
            ], "*", '');

            $album_picture = new AlbumPictureModel();
            foreach ($goos_sku_picture_query as $k => $v) {
                if ($v["sku_img_array"] != "") {
                    $spec_name = '';
                    $spec_value_name = '';
                    foreach ($spec_list as $t => $m) {
                        if ($m["spec_id"] == $v["spec_id"]) {
                            foreach ($m["value"] as $c => $b) {
                                if ($b["spec_value_id"] == $v["spec_value_id"]) {
                                    $spec_name = $b["spec_name"];
                                    $spec_value_name = $b["spec_value_name"];
                                }
                            }
                            unset($b);
                        }
                    }
                    unset($m);
                    $goos_sku_picture_query[$k]["spec_name"] = $spec_name;
                    $goos_sku_picture_query[$k]["spec_value_name"] = $spec_value_name;
                    $tmp_img_array = $album_picture->getQuery([
                        "pic_id" => [
                            "in",
                            $v["sku_img_array"]
                        ]
                    ], "*", '');
                    $pic_id_array = explode(',', (string)$v["sku_img_array"]);
                    $goos_sku_picture_query[$k]["sku_picture_query"] = array();
                    // var_dump($pic_id_array);
                    $sku_picture_query_array = array();
                    foreach ($pic_id_array as $t => $m) {
                        foreach ($tmp_img_array as $q => $z) {
                            if ($m == $z["pic_id"]) {
                                // var_dump($z);
                                $sku_picture_query_array[] = $z;
                            }
                        }
                        unset($z);
                    }
                    unset($m);
                    $goos_sku_picture_query[$k]["sku_picture_query"] = $sku_picture_query_array;
                    // $goos_sku_picture_query[$k]["sku_picture_query"] = $album_picture->getQuery(["pic_id"=>["in",$v["sku_img_array"]]], "*", '');
                } else {
                    unset($goos_sku_picture_query[$k]);
                }
            }
            unset($v);
            sort($goos_sku_picture_query);
            $goods_detail["sku_picture_array"] = $goos_sku_picture_query;

            // 查询商品的已购数量
            $orderGoods = new VslOrderGoodsModel();
            $num = $orderGoods->getSum([
                "goods_id" => $goods_id,
                "buyer_id" => $this->uid,
                "order_status" => array(
                    "neq",
                    5
                )
            ], "num");
            $goods_detail["purchase_num"] = $num;
        }
        //如果是知识付费商品,要判断当前用户有没有购买过
        if($goods_detail['goods_type'] == 4) {
            if(empty($this->uid)){
                //没有登录,默认没有购买过
                $goods_detail['is_buy'] = false;
            }else{
                $order_goods_model = new VslOrderGoodsModel();
                $order_model = new VslOrderModel();
                $data = [
                    'website_id' => $this->website_id,
                    'buyer_id' => $this->uid,
                    'goods_id' => $goods_id
                ];
                $order_list = $order_goods_model->getQuery($data, 'order_id','order_id ASC');
                if ($order_list) {
                    foreach ($order_list as $k => $v) {
                        $order_status = $order_model->getInfo(['order_id' => $v['order_id']], 'order_status');
                        if($order_status['order_status'] == 4) {
                            $goods_detail['is_buy'] = true;
                            break;
                        }else{
                            $goods_detail['is_buy'] = false;
                        }
                    }
                    unset($v);
                } else {
                    $goods_detail['is_buy'] = false;
                }
            }
        }
        $goods_detail['goods_name'] = htmlspecialchars_decode($goods_detail['goods_name']);
        $goods_detail['sub_title'] = htmlspecialchars_decode($goods_detail['sub_title']);
        $goods_area_limit['names'] = [];
        if($goods_detail['area_list'] && $types) {
            $area_list = json_decode(htmlspecialchars_decode($goods_detail['area_list']), true);
            $area_arr = explode('§',$area_list['ids']);
            $goods_area_limit['names'] = $area_list['id_names'];
            $goods_area_limit['province_id_array'] = explode(',',$area_arr[0]);
            $goods_area_limit['city_id_array'] = explode(',',$area_arr[1]);
            $goods_area_limit['district_id_array'] = explode(',',$area_arr[2]);
        }
        $goods_detail['goods_area_limit'] = $goods_area_limit;
        return $goods_detail;
        // TODO Auto-generated method stub
    }

    /*
     * 微商中心-根据分类id得到商品列表
     * $channel_id 用于判断是取平台的商品还是渠道商的商品
     * $channel_goods_type 用于判断当前是采购的商品还是自提的商品，自提不用乘进货折扣
     */
    public function getChannelGoodsList($page_index, $page_size, $condition, $order, $channel_id, $uid, $buy_type)
    {
        $channel_server = new Channel();
        $goods_discount_model = new VslGoodsDiscountModel();
        $page_offset = ($page_index - 1) * $page_size;
        // 查询商品主表
        if ($channel_id == 'platform') {
            $goods_mdl = new VslGoodsModel();//联表查询
            $goods_sku_mdl = new VslGoodsSkuModel();
            $channel_id = 'platform';
            $condition['g.supplier_info'] = 0;//过滤供应商
        } else {
            $goods_mdl = new VslChannelGoodsModel();
            $goods_sku_mdl = new VslChannelGoodsSkuModel();
            $channel_id = $channel_id;
            $condition['channel_id'] = $channel_id;
        }
        $condition['g.state'] = 1;
        $goods_spec = new VslGoodsSpecModel();
        $total_count = $goods_mdl->alias('g')
            ->field('goods_id')
            ->where($condition)
            ->order($order)
            ->count();
        $page_count = ceil($total_count / $page_size);
        $goods_list = $goods_mdl->alias('g')
            ->field('goods_id,goods_name,goods_spec_format,img_id_array')
            ->where($condition)
            ->limit($page_offset, $page_size)
            ->order($order)
            ->select();
        $goods_arr = objToArr($goods_list);
        //获取我的渠道商的等级进货比例
        $condition1['c.website_id'] = $this->website_id;
        $condition1['c.uid'] = $uid;
        $channel_info = $channel_server->getMyChannelInfo($condition1);
        $purchase_discount = $channel_info['purchase_discount'] ?: 1;
        $my_weight = $channel_info['weight'];
        foreach ($goods_arr as $k => $goods_info) {
            $goods_id = $goods_info['goods_id'];
            //获取图片
            $goods_img = new AlbumPictureModel();
            $order = "instr('," . $goods_info['img_id_array'] . ",',CONCAT(',',pic_id,','))"; // 根据 in里边的id 排序
            $goods_img_list = $goods_img->getQuery([
                'pic_id' => [
                    "in",
                    $goods_info['img_id_array']
                ]
            ], 'pic_cover', $order);
            $goods_img_arr = objToArr($goods_img_list);
            $temp_img_arr = [];
            foreach ($goods_img_arr as $k2 => $img) {
                $temp_img_arr[] = getApiSrc($img['pic_cover']);
            }
            unset($img);
            //处理一下图片
            $goods_arr[$k]['img_list'] = $temp_img_arr;

            //spec_list sku的内容
            $spec_list = json_decode($goods_info['goods_spec_format'], true);
//            var_dump($spec_list);exit;
            if (!empty($spec_list)) {
                foreach ($spec_list as $k3 => $v) {
                    $sort = $goods_spec->getInfo([
                        "spec_id" => $v['spec_id']
                    ], "sort");
                    $spec_list[$k3]['sort'] = 0;
                    if (!empty($sort)) {
                        $spec_list[$k3]['sort'] = $sort['sort'];
                    }
                    foreach ($v["value"] as $m => $t) {
                        if (empty($t["spec_show_type"])) {
                            $spec_list[$k3]["value"][$m]["spec_show_type"] = 1;
                        }
                        // 查询SKU规格主图，没有返回0
                        $spec_list[$k3]["value"][$m]["picture"] = $this->getGoodsSkuPictureBySpecId($goods_id, $spec_list[$k3]["value"][$m]['spec_id'], $spec_list[$k3]["value"][$m]['spec_value_id']);
                    }
                    unset($t);
                }
                unset($v);
                // 排序字段
                $sort = array(
                    'field' => 'sort'
                );

                $arrSort = array();
                foreach ($spec_list as $uniqid => $row) {
                    foreach ($row as $key => $value) {
                        $arrSort[$key][$uniqid] = $value;
                    }
                    unset($value);
                }
                unset($row);
                array_multisort($arrSort[$sort['field']], SORT_ASC, $spec_list);
            }
            $spec_list = $spec_list ?: [];
//            $goods_detail['spec_list'] = $spec_list;
            if ($channel_id == 'platform') {
                $sku_conditon['goods_id'] = $goods_id;
            } else {
                $sku_conditon['goods_id'] = $goods_id;
                $sku_conditon['channel_id'] = $channel_id;
            }
            //获取sku
            $goods_id = $goods_info['goods_id'];
            $goods_sku_list = $goods_sku_mdl->where($sku_conditon)->select();
            $goods_sku_arr = objToArr($goods_sku_list);
            $goods_sku_detail = $this->getChannelGoodSkuInfo($goods_sku_arr, $spec_list, $purchase_discount, $buy_type, $uid, $my_weight);
            $goods_arr[$k]['channel_info'] = $channel_id;
            $goods_arr[$k]['min_price'] = $goods_sku_detail['min_price'];
            $goods_arr[$k]['max_price'] = $goods_sku_detail['max_price'];
            $goods_arr[$k]['min_market_price'] = $goods_sku_detail['min_market_price'];
            $goods_arr[$k]['max_market_price'] = $goods_sku_detail['max_market_price'];
            $goods_arr[$k]['sku'] = $goods_sku_detail['sku'];
            //将原来的不需要的值去掉
            unset($goods_arr[$k]['img_id_array']);
            unset($goods_arr[$k]['goods_spec_format']);

            if($buy_type == 'purchase') {
                //如果当前渠道商的等级与商品勾选的渠道商等级不一致则删除
                $channel_auth = $goods_discount_model->Query(['goods_id' => $goods_id,'website_id' => $this->website_id],'channel_auth')[0];
                if(empty($channel_auth)) {
                    unset($goods_arr[$k]);
                    $total_count = $total_count - 1;
                    $page_count = ceil($total_count / $page_size);
                }else{
                    $channel_auth = explode(',',$channel_auth);
                    //获取当前用户的渠道商权限
                    if(!in_array($channel_info['channel_grade_id'],$channel_auth)) {
                        unset($goods_arr[$k]);
                        $total_count = $total_count - 1;
                        $page_count = ceil($total_count / $page_size);
                    }
                }
            }
        }
        unset($goods_info);
        $goods_arr = array_values($goods_arr);
        return [
            'code' => 1,
            'message' => '获取成功',
            'data' => [
                'goods_list' => $goods_arr,
                'total_count' => $total_count,
                'page_count' => $page_count,
            ]
        ];
    }

    /*
     * 获取商品的sku规格
     * **/
    public function getChannelGoodSkuInfo($goods_sku_arr, $spec_list, $purchase_discount, $buy_type, $uid, $my_weight)
    {
        $channel_server = new Channel();
        $temp_sku_list = $goods_sku_arr;
        $spec_obj = [];
        $goods_detail['sku']['tree'] = [];
        foreach ($spec_list as $i => $spec_info) {
            $temp_spec = [];
            foreach ($spec_info['value'] as $s => $spec_value) {
                $temp_spec['k'] = $spec_info['spec_name'];
                $temp_spec['k_id'] = $spec_info['spec_id'];
                $temp_spec['v'][$s]['id'] = $spec_value['spec_value_id'];
                $temp_spec['v'][$s]['name'] = $spec_value['spec_value_name'];
                $temp_spec['k_s'] = 's' . $i;
                $spec_obj[$spec_info['spec_id']] = $temp_spec['k_s'];
                $goods_detail['sku']['tree'][$spec_info['spec_id']] = $temp_spec;
            }
            unset($spec_value);
        }
        unset($spec_info);
        //接口需要tree是数组，不是对象，去除tree以spec_id为key的值
        $goods_detail['sku']['tree'] = array_values($goods_detail['sku']['tree']);
        foreach ($goods_sku_arr as $k => $sku) {
            $temp_sku['id'] = $sku['sku_id'];
            $temp_sku['sku_name'] = $sku['sku_name'];
//            if ($buy_type == 'purchase') {
            $channel_con = new \addons\channel\controller\Channel();
            $up_grade_channel_id = $channel_con->getUpChannelInfo();
            $temp_arr = ['channel_id' => $up_grade_channel_id, 'sku_id' => $sku['sku_id'], 'price' => $sku['price'], 'markent_price' => 0];
            $this->getChannelSkuPrice($temp_arr);
            $sku['price'] = $temp_arr['price'];
//            }
            //采购或者出货将价格处理成采购价，自提价格不变。
            if ($buy_type == 'purchase') {
                $sku['price'] = $sku['price'] * $purchase_discount;
            }
            $temp_sku['price'] = $sku['price'];
            $temp_sku['min_buy'] = 1;
            $temp_sku['group_price'] = '';
            $temp_sku['group_limit_buy'] = '';
            $temp_sku['market_price'] = $sku['market_price'];
            //自提要限制单次购买量不超过它本身的库存
            if ($buy_type == 'purchase') {
                $channel_server = new Channel();
                $stock = $channel_server->myAllRefereeChannelSkuStore($uid, $my_weight, $sku['sku_id']);
                //获取sku的所有上级渠道商的库存
                $temp_sku['max_buy'] = $stock;
                $temp_sku['stock_num'] = $stock;
            } else {
                $temp_sku['max_buy'] = 0;
                $temp_sku['stock_num'] = $sku['stock'];
            }
            $temp_sku['attr_value_items'] = $sku['attr_value_items'];
            $sku_temp_spec_array = explode(';', $sku['attr_value_items_format']);
            $temp_sku['s'] = [];
            foreach ($sku_temp_spec_array as $spec_id => $spec_combination) {
                $explode_spec = explode(':', $spec_combination);
                $spec_id = $explode_spec[0];
                $spec_value_id = $explode_spec[1];

                // ios wants string
                if ($spec_value_id) {
                    $temp_sku['s'][] = (string)$spec_value_id;
                    $temp_sku[$spec_obj[$spec_id] ?: 's0'] = (int)$spec_value_id;
                }
            }
            unset($spec_combination);
            $goods_detail['sku']['list'][] = $temp_sku;
            $goods_detail['min_price'] = reset($temp_sku_list)['sku_id'] == $sku['sku_id']
                ? $sku['price'] : ($goods_detail['min_price'] <= $sku['price'] ? $goods_detail['min_price'] : $sku['price']);
            $goods_detail['max_price'] = reset($temp_sku_list)['sku_id'] == $sku['sku_id']
                ? $sku['price'] : ($goods_detail['max_price'] >= $sku['price'] ? $goods_detail['max_price'] : $sku['price']);
            $goods_detail['min_market_price'] = reset($temp_sku_list)['sku_id'] == $sku['sku_id']
                ? $sku['market_price'] : ($goods_detail['min_market_price'] <= $sku['market_price'] ? $goods_detail['min_market_price'] : $sku['market_price']);
            $goods_detail['max_market_price'] = reset($temp_sku_list)['sku_id'] == $sku['sku_id']
                ? $sku['market_price'] : ($goods_detail['max_market_price'] >= $sku['market_price'] ? $goods_detail['max_market_price'] : $sku['market_price']);
        }
        unset($sku);
        return $goods_detail;
    }

    //获取渠道商的商品单价，如果删除了的情况
    public function getChannelSkuPrice(&$sku)
    {
        //不管是采购还是自提，都显示商品表里面的平台价,如果删除了查不到则展示批次的最高平台价
        $platform_goods = new VslGoodsSkuModel();
        $platform_list = $platform_goods->getInfo(['sku_id' => $sku['sku_id']], 'price, market_price');
        if ($platform_list) {
            $sku['price'] = $platform_list['price'];
            $sku['market_price'] = $platform_list['market_price'];
        } else {
            if (getAddons('channel', $this->website_id)) {
                $channel_record = new VslChannelOrderSkuRecordModel();
                $sku_record['my_channel_id'] = $sku['channel_id'];
                $sku_record['sku_id'] = $sku['sku_id'];
                $sku_record['buy_type'] = 1;
                $sku_record['website_id'] = $this->website_id;
                $sku_record_list = objToArr($channel_record->getQuery($sku_record, 'platform_price', ''));
                $platform_price_arr = array_column($sku_record_list, 'platform_price') ?: [$sku['price']];
                $sku['price'] = max($platform_price_arr);
            }
        }
    }
    /*
     * 获取微商中心商品分类列表
     * **/
    public function getChannelGoodsCategoryList($condition, $order, $channel_id)
    {
        //偏移量
        if ($channel_id == 'platform') {
            $goods_mdl = new VslGoodsModel();//联表查询cate
        } else {
            $goods_mdl = new VslChannelGoodsModel();
        }
        $category_list = $goods_mdl->alias('g')
            ->field('gc.category_id,gc.category_name,gc.short_name')
            ->where(['gc.level' => 1, 'g.website_id' => $this->website_id])
            ->join('vsl_goods_category gc', 'g.category_id_1=gc.category_id', 'LEFT')
            ->order('gc.sort DESC')
            ->group('gc.category_id')
            ->select();
        return [
            'code' => 1,
            'message' => '获取成功',
            'data' => [
                'category_list' => $category_list
            ]
        ];
    }

    /**
     * 查询sku多图数据
     *
     * {@inheritdoc}
     *
     * @see \data\api\IGoods::getGoodsSkuPicture()
     */
    public function getGoodsSkuPicture($goods_id)
    {
        $goods_sku = new VslGoodsSkuPictureModel();
        $sku_picture_list = $goods_sku->getQuery([
            "goods_id" => $goods_id
        ], "*", "");
        if(!$sku_picture_list){
            return [];
        }
        foreach ($sku_picture_list as $k => $v) {
            $sku_img_array = $v["sku_img_array"];
            $album_picture_list = array();
            if (!empty($sku_img_array)) {
                $sku_img_str = explode(",", $sku_img_array);
                foreach ($sku_img_str as $img_id) {
                    $picture_model = new AlbumPictureModel();
                    $picture_obj = $picture_model->getInfo([
                        "pic_id" => $img_id
                    ], "*");
                    if (isset($picture_obj) && !empty($picture_obj)) {
                        $album_picture_list[] = $picture_obj;
                    }
                }
                unset($img_id);
            }
            $sku_picture_list[$k]["album_picture_list"] = $album_picture_list;
        }
        unset($v);
        return $sku_picture_list;
    }

    /**
     * 根据商品id、规格id、规格值id查询
     * {@inheritdoc}
     *
     * @see \data\api\IGoods::getGoodsSkuPictureBySpecId()
     */
    public function getGoodsSkuPictureBySpecId($goods_id, $spec_id, $spec_value_id)
    {
        $picture = 0;

        $goods_sku = new VslGoodsSkuPictureModel();
        $sku_img_array = $goods_sku->getInfo([
            "goods_id" => $goods_id,
            "spec_id" => $spec_id,
            "spec_value_id" => $spec_value_id
        ], "sku_img_array");
        if (!empty($sku_img_array)) {
            $array = explode(",", $sku_img_array['sku_img_array']);
            $picture = $array[0];
        }
        return $picture;
    }

    /**
     * 商品规格列表(non-PHPdoc)
     *
     * @see \data\api\IGoods::getGoodsAttributeList()
     */
    public function getGoodsAttributeList($condition, $field, $order)
    {
        $spec = new VslGoodsSpecModel();
        $list = $spec->getQuery($condition, $field, $order);
        return $list;
    }

    /*
     * 添加商品规格
     * (non-PHPdoc)
     * @see \data\api\IGoods::addGoodsSpec()
     */
    public function addGoodsSpec($spec_name, $sort = 0)
    {
        $attribute = new VslGoodsSpecModel();
        $data = array(
            'shop_id' => $this->instance_id,
            'spec_name' => $spec_name,
            'sort' => 0,
            'create_time' => time()
        );
        $find_id = $attribute->get([
            'spec_name' => $spec_name
        ]);
        if (!empty($find_id)) {
            return $find_id['spec_id'];
        } else {
            $res = $attribute->save($data);
            return $attribute->spec_id;
        }

        // TODO Auto-generated method stub
    }

    /*
     * 添加商品规格值
     * (non-PHPdoc)
     * @see \data\api\IGoods::addGoodsSpecValue()
     */
    public function addGoodsSpecValue($spec_id, $spec_value, $sort = 0)
    {
        $spec_value_model = new VslGoodsSpecValueModel();
        $data = array(
            'spec_id' => $spec_id,
            'website_id' => $this->website_id,
            'spec_value_name' => $spec_value,
            'sort' => $sort,
            'create_time' => time()
        );
        $find_id = $spec_value_model->get([
            'spec_value_name' => $spec_value,
            'website_id' => $this->website_id,
            'spec_id' => $spec_id
        ]);
        if (!empty($find_id)) {
            return $find_id['spec_value_id'];
        } else {
            $res = $spec_value_model->save($data);
            return $spec_value_model->spec_value_id;
        }

        // TODO Auto-generated method stub
    }

    /**
     * 添加|修改商品sku列表
     * @param  int  $goods_id
     * @param array $sku_item_array [每一行用'¦'分割的规格数据]
     * @param array $specArray
     * @param array $ext_param [额外参数]
     * @return false|int|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function addOrUpdateGoodsSkuItem($goods_id, $sku_item_array, $specArray = [])
    {
        $redis = connectRedis();
        $sku_item = explode('¦', $sku_item_array);
        $goods_sku = new VslGoodsSkuModel();
        $sku_name = $this->createSkuName($sku_item[0], $specArray);
        $condition = array(
            'goods_id' => $goods_id,
            'attr_value_items' => $sku_item[0]
        );
        $sku_count = $goods_sku->where($condition)->find();
        if (empty($sku_count)) {/*新增*/
            $data = array(
                'goods_id' => $goods_id,
                'sku_name' => $sku_name,
                'attr_value_items' => $sku_item[0],
                'attr_value_items_format' => $sku_item[0],
                'price' => $sku_item[1],
                'promote_price' => $sku_item[1],
                'market_price' => $sku_item[2],
                'cost_price' => $sku_item[3],
                'stock' => $sku_item[4],
                'picture' => 0,
                'code' => $sku_item[5],
                'QRcode' => '',
                'website_id' => $this->website_id,
                'create_date' => time(),
                'sku_max_buy' => $sku_item[6],
                'sku_max_buy_time' => time()
            );
            $goods_sku->save($data);
            //将普通商品的库存插入到redis
            $redis_key = 'goods_'.$goods_id.'_'.$goods_sku->sku_id;
            $redis->set($redis_key, $sku_item[4]);
            return $goods_sku->sku_id;
        } else {/*修改*/
            //如果sku_max_buy被修改，则sku_max_buy_time更新
            $sku_max_buy_time = $sku_count['sku_max_buy_time'];
            if ($sku_count['sku_max_buy'] != $sku_item[6]) {
                $sku_max_buy_time = time();
            }
            $data = array(
                'goods_id' => $goods_id,
                'sku_name' => $sku_name,
                'price' => $sku_item[1],
                'promote_price' => $sku_item[1],
                'market_price' => $sku_item[2],
                'cost_price' => $sku_item[3],
                'stock' => $sku_item[4],
                'code' => $sku_item[5],
                'QRcode' => '',
                'website_id' => $this->website_id,
                'update_date' => time(),
                'sku_max_buy' => $sku_item[6],
                'sku_max_buy_time' => $sku_max_buy_time,
            );
            //将普通商品的库存插入到redis
            $redis_key = 'goods_'.$goods_id.'_'.$sku_count['sku_id'];
            $redis->set($redis_key, $sku_item[4]);
            $res = $goods_sku->save($data, [
                'sku_id' => $sku_count['sku_id']
            ]);
            return $res;
        }
    }

    private function deleteSkuItem($goods_id, $sku_list_array)
    {
        $sku_item_list_array = array();
        foreach ($sku_list_array as $k => $sku_item_array) {
            $sku_item = explode('¦', $sku_item_array);
            $sku_item_list_array[] = $sku_item[0];
        }
        unset($sku_item_array);
        $goods_sku = new VslGoodsSkuModel();
        $storeGoodsSkuModel = new VslStoreGoodsSkuModel();
        $list = $goods_sku->where('goods_id=' . $goods_id)->select();
        if (!empty($list)) {
            foreach ($list as $k => $v) {
                if (!in_array($v['attr_value_items'], $sku_item_list_array)) {
                    $goods_sku->destroy($v['sku_id']);
                    //门店商品sku表也要删除
                    $storeGoodsSkuModel->delData(['sku_id' => $v['sku_id']]);
                }
            }
            unset($v);
        }
    }

    /**
     * 组装sku name
     *
     * @param unknown $pvs
     * @return string
     */
    public function createSkuName($pvs,$specArray = [])
    {
        $name = '';
        $pvs_array = explode(';', $pvs);
        foreach ($pvs_array as $k => $v) {
            $value = explode(':', $v);
            $prop_id = $value[0];
            $prop_value = $value[1];
            $goods_spec_value_model = new VslGoodsSpecValueModel();
            $value_name = $goods_spec_value_model->getInfo([
                'spec_value_id' => $prop_value
            ], 'spec_value_name');
            if(isset($specArray[$prop_value]['name'])){
                $value_name['spec_value_name'] = $specArray[$prop_value]['name'];
            }
            $name = $name . $value_name['spec_value_name'] . ' ';
        }
        unset($v);
        return $name;
    }

    /**
     * 根据当前分类ID查询商品分类的三级分类ID
     *
     * @param unknown $category_id
     */
    private function getGoodsCategoryId($category_id)
    {
        // 获取分类层级
        $goods_category = new VslGoodsCategoryModel();
        $info = $goods_category->get($category_id);
        if ($info['level'] == 1) {
            return array(
                $category_id,
                0,
                0
            );
        }
        if ($info['level'] == 2) {
            // 获取父级
            return array(
                $info['pid'],
                $category_id,
                0
            );
        }
        if ($info['level'] == 3) {
            $info_parent = $goods_category->get($info['pid']);
            // 获取父级
            return array(
                $info_parent['pid'],
                $info['pid'],
                $category_id
            );
        }
    }

    /**
     * 根据当前商品分类组装分类名称
     *
     * @param unknown $category_id_1
     * @param unknown $category_id_2
     * @param unknown $category_id_3
     */
    /**
     * category_name 转换为 short_name
     */
    public function getGoodsCategoryName($category_id_1, $category_id_2, $category_id_3)
    {

        $name = '';
        $goods_category = new VslGoodsCategoryModel();
        $info_1 = $goods_category->getInfo([
            'category_id' => $category_id_1
        ], 'short_name');
        $info_2 = $goods_category->getInfo([
            'category_id' => $category_id_2
        ], 'short_name');
        $info_3 = $goods_category->getInfo([
            'category_id' => $category_id_3
        ], 'short_name');

        if (!empty($info_1['short_name'])) {
            $lg_symbol = !empty($info_2['short_name']) ? '>' : '';
            $name = $info_1['short_name'] . $lg_symbol;
        }
        if (!empty($info_2['short_name'])) {
            $lg_symbol = !empty($info_3['short_name']) ? '>' : '';
            $name = $name . '' . $info_2['short_name'] . $lg_symbol;
        }
        if (!empty($info_3['short_name'])) {
            $name = $name . '' . $info_3['short_name'];
        }
        return $name;
    }

    /**
     * 获取条件查询出商品
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::getSearchGoodsList()
     */
    public function getSearchGoodsList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        if (!getAddons('shop', $this->website_id)) {
            $condition['shop_id'] = $this->instance_id;
        }
        $result = $this->goods->pageQuery($page_index, $page_size, $condition, $order, $field);
        foreach ($result['data'] as $k => $v) {
            $picture = new AlbumPictureModel();
            $pic_info = array();
            $pic_info['pic_cover'] = '';
            if (!empty($v['picture'])) {
                $pic_info = $picture->getInfo(['pic_id' => $v['picture']], 'pic_cover,pic_cover_mid,pic_cover_micro');
            }
            $result['data'][$k]['picture_info'] = $pic_info;
            if (getAddons('shop', $this->website_id)) {
                $shop = new VslShopModel();
                $shop_info = $shop->getInfo(['shop_id' => $v['shop_id']]);
            } else {
                $shop_info['shop_name'] = $this->mall_name;
            }
            $result['data'][$k]['shop_name'] = $shop_info['shop_name'];
            if (isset($v['promotion_type'])) {
                $result['data'][$k]['promotion_name'] = $this->getGoodsPromotionType($v['promotion_type']);
            }
            if(isset($v['activity_pic']) && !empty($v['activity_pic'])){
                //activity_pic
                $activity_pic = $picture->getInfo(['pic_id' => $v['activity_pic']], 'pic_cover')['pic_cover'];
            }
            $result['data'][$k]['activity_pic'] = getApiSrc($activity_pic);
        }
        unset($v);
        return $result;
    }

    /**
     * 获取购物车中项目
     *
     * @param array $cart_id_array
     *
     * return array $cart_lists
     */
    public function getCartList(array $cart_id_array, &$msg = '')
    {
        $cart = new VslCartModel();
        $cart_lists = $cart::all(['cart_id' => ['IN', $cart_id_array]], ['goods', 'sku', 'goods_picture']);
        foreach ($cart_lists as $k => $v) {
            $cartlist[$k]['anchor_id'] = $v['anchor_id'];
            $goods_name = $v->goods->goods_name;
            if (mb_strlen($v->goods->goods_name) > 10) {
                $goods_name = mb_substr($v->goods->goods_name, 0, 10) . '...';
            }
            if (empty($v->sku)) {
                $cart->destroy(['cart_id' => $v->cart_id]);
                unset($cart_lists[$k]);
                $msg .= $goods_name . "商品该sku规格不存在，已移除" . PHP_EOL;
                continue;
            }
            if ($v->sku->stock <= 0) {
//                $cart->destroy(['cart_id' => $v->cart_id]);
//                unset($cart_lists[$k]);
                $msg .= $goods_name . "商品该sku规格库存不足" . PHP_EOL;
                continue;
            }
            if ($v->goods->state != 1) {
//                $this->cartDelete($v->cart_id);
//                unset($cart_lists[$k]);
                $msg .= $goods_name . "商品该sku规格已下架" . PHP_EOL;
                continue;
            }
            $num = $v->num;
            if ($v->goods->max_buy != 0 && $v->goods->max_buy < $v->num) {
                $num = $v->goods->max_buy;
                $msg .= $goods_name . "商品该sku规格购买量大于最大购买量，购买数量已更改" . PHP_EOL;
            }

            if ($v->sku->stock < $num) {
                $num = $v->sku->stock;
            }
            if ($num != $v->num) {
                // 更新购物车
                $this->cartAdjustNum($v->cart_id, $v->sku->stock);
                $v->num = $num;
                $msg .= $goods_name . "商品该sku规格购买量大于库存，购买数量已更改" . PHP_EOL;
            }
            $v->stock = $v->sku->stock;
            $v->max_buy = $v->goods->max_buy;
            $v->point_exchange_type = $v->goods->point_exchange_type;
            $v->point_exchange = $v->goods->point_exchange;
            $v->picture_info = $v->goods_picture;
            //如果是秒杀商品并且没有结束，则取秒杀价
            if (!$v->seckill_id && getAddons('seckill', $this->website_id, $this->instance_id)) {
                $sec_server = new SeckillServer();
                $sku_id = $v->sku->sku_id;
                $condition_seckill['nsg.sku_id'] = $sku_id;
                $seckill_info = $sec_server->isSkuStartSeckill($condition_seckill);
                if ($seckill_info) {
                    $v->seckill_id = $seckill_info['seckill_id'];
                }
            }
            if (!empty($v->seckill_id) && getAddons('seckill', $this->website_id, $this->instance_id)) {
                $sec_server = new SeckillServer();
                //判断当前秒杀活动的商品是否已经开始并且没有结束
                $condition_seckill['s.website_id'] = $this->website_id;
                $condition_seckill['s.seckill_id'] = $v->seckill_id;
                $condition_seckill['nsg.sku_id'] = $v->sku->sku_id;
                $is_seckill = $sec_server->isSeckillGoods($condition_seckill);
                if (!$is_seckill) {
                    $v->price = $v->sku->price;
                    $v->seckill_id = 0;
                    $this->cartAdjustSec($v->cart_id, 0);
                    $msg .= $goods_name . "商品该sku规格秒杀活动已经结束，已更改为正常状态商品价格" . PHP_EOL;
                } else {
                    //取该商品该用户购买了多少
                    $sku_id = $v->sku->sku_id;
                    $uid = $this->uid;
                    $website_id = $this->website_id;
                    $buy_num = $this->getActivityOrderSku($uid, $sku_id, $website_id, $v->seckill_id);
                    $sec_sku_info_list = $sec_server->getSeckillSkuInfo(['seckill_id' => $v->seckill_id, 'sku_id' => $v->sku->sku_id]);
                    $v->stock = $sec_sku_info_list->remain_num;
                    $v->max_buy = (($sec_sku_info_list->seckill_limit_buy - $buy_num) < 0) ? 0 : $sec_sku_info_list->seckill_limit_buy - $buy_num;
                    $v->price = $sec_sku_info_list->seckill_price;
                }
            } else {
                $v->price = $v->sku->price;
            }
            unset($cart_lists[$k]->good, $cart_lists[$k]->sku, $cart_lists[$k]->goods_picture);
        }
        unset($v);
        return $cart_lists;
    }

    /**
     * 获取购物车项目(PC端)
     * @param array $cart_id_array
     *
     * @return $shop_goods_lists
     */
    public function getCartListsNew(array $cart_id_array, &$msg = '', $store_id, $cart_ids)
    {
        $cart = new VslCartModel();
        $store_goods_sku_model = new VslStoreGoodsSkuModel();
        $goods_sku_model = new VslGoodsSkuModel();
        $promotion = getAddons('discount', $this->website_id) ? new Discount() : '';
        $goods_man_song_model = getAddons('fullcut', $this->website_id) ? new Fullcut() : '';
        $seckill_server = getAddons('seckill', $this->website_id, $this->instance_id) ? new Seckill() : '';
        if ($store_id && $cart_ids) {
            foreach ($cart_ids as $v) {
                $data = $cart::get(['cart_id' => $v], ['goods', 'goods_picture']);
                $store_sku = $store_goods_sku_model->getInfo(['sku_id' => $data['sku_id'], 'store_id' => $store_id], '*');
                if (empty($store_sku)) {
                    $sku = $goods_sku_model->getInfo(['sku_id' => $data['sku_id']], '*');
                    $data['sku'] = $sku;
                } else {
                    $data['sku'] = $store_sku;
                }
                $cart_lists[] = $data;
            }
            unset($v);
        } else {
            $cart_lists = $cart::all(['cart_id' => ['IN', $cart_id_array]], ['goods', 'sku', 'goods_picture']);
        }

        $shop_goods_lists = [];
        $cart_sku_info = [];
        $shipping_lists = [];
        $goods_sku_list = '';
        $shop_goods = [];
        $member_model = new VslMemberModel();
        $member_level_info = $member_model->getInfo(['uid' => $this->uid])['member_level'];
        $member_level = new VslMemberLevelModel();
        $member_info = $member_level->getInfo(['level_id' => $member_level_info]);
        $member_discount = $member_info['goods_discount'] / 10;
        $member_is_label = $member_info['is_label'];

        //获取购物车sku的一些信息
        foreach ($cart_lists as $k => $v) {
            if (empty($v->sku)) {
                $cart->destroy(['cart_id' => $v->cart_id]);
                unset($cart_lists[$k]);
                continue;
            }
            if ($v->sku['stock'] <= 0) {
                $cart->destroy(['cart_id' => $v->cart_id]);
                unset($cart_lists[$k]);
                continue;
            }
            if ($v->goods->state != 1) {
                $this->cartDelete($v->cart_id);
                unset($cart_lists[$k]);
                continue;
            }
            $shop_id_array[] = $v['shop_id'];
            $num = $v->num;
            if ($v->goods->max_buy != 0 && $v->goods->max_buy < $v->num) {
                $num = $v->goods->max_buy;
            }

            if ($v->sku['stock'] < $num) {
                $num = $v->sku['stock'];
            }
            if ($num != $v->num) {
                // 更新购物车
                $this->cartAdjustNum($v->cart_id, $v->sku->stock);
                $v->num = $num;
            }
            if (getAddons('discount', $this->website_id)) {
                $promotion_info = $promotion->getPromotionInfo($v->goods_id, $v->goods->shop_id, $v->goods->website_id);
            } else {
                $promotion_info['discount_num'] = 10;
            }
            $seckill_id = $v->seckill_id;
            if (!empty($seckill_id) && getAddons('seckill', $this->website_id, $this->instance_id)) {
                $seckill_server = new Seckill();
                //判断该sku是否在秒杀活动内
                $is_seckill_condition['s.seckill_id'] = $seckill_id;
                $is_seckill_condition['nsg.sku_id'] = $v->sku_id;
                $is_seckill = $seckill_server->isSeckillGoods($is_seckill_condition);
                if ($is_seckill) {
                    //秒杀价
                    $seckill_price = $is_seckill['seckill_price'];
                    //库存
                    $remain_num = $is_seckill['remain_num'];
                    //限购
                    $limit_buy = $is_seckill['seckill_limit_buy'];
                    $uid = $this->uid;
                    $website_id = $this->website_id;
                    //获取我已经买了多少个
                    $buy_num = $this->getActivityOrderSku($uid, $v->sku_id, $website_id, $seckill_id);
//                $buy_num = 0;
                    $v->stock = $remain_num;
                    $v->max_buy = (($limit_buy - $buy_num) < 0) ? 0 : ($limit_buy - $buy_num);
                    $v->price = $seckill_price;
                    //秒杀不享受其它优惠
                    $v->sku->price = $seckill_price;
                    $member_discount = 1;
                    $promotion_info['discount_num'] = 10;
                } else {
                    $v->stock = $v->sku['stock'];
                    $v->max_buy = $v->goods->max_buy;
                    $v->price = $v->sku['price'];
                    $msg .= $v->goods->goods_name . '商品秒杀活动已结束，已恢复商品原有价格' . PHP_EOL;
                }
            } else {
                $v->stock = $v->sku['stock'];
                $v->max_buy = $v->goods->max_buy;
                $v->price = $v->sku['price'];
            }
            $v->point_exchange_type = $v->goods->point_exchange_type;
            $v->point_exchange = $v->goods->point_exchange;
            $v->picture_info = $v->goods_picture;
            $v->member_dicount = $member_discount;
            if ($member_is_label) {
                $v->member_price = round($v->sku->price * $member_discount);
            } else {
                $v->member_price = round($v->sku->price * $member_discount, 2);
            }

//            // todo... by sgw
            if ($this->uid) {
                $goodsDiscountInfo = $this->getGoodsInfoOfIndependentDiscount($v['goods_id'], $v->price);
                if ($goodsDiscountInfo) {
                    $v->member_price = $goodsDiscountInfo['member_price'];
                    $v->member_dicount = $goodsDiscountInfo['member_discount'];
                }
            }

            $v->discount_id = $promotion_info['discount_id'] ?: '';
            $v->promotion_discount = round($promotion_info['discount_num'] / 10, 2);
            $v->promotion_shop_id = $promotion_info['shop_id'];
            //限时抢购new
            if ($promotion_info['integer_type'] == 1) {
                $v->promotion_price = round($v->sku->price * $promotion_info['discount_num'] / 10);
                $v->discount_price = round($v->member_price * $promotion_info['discount_num'] / 10);
            } else {
                $v->promotion_price = round($v->sku->price * $promotion_info['discount_num'] / 10, 2);
                $v->discount_price = round($v->member_price * $promotion_info['discount_num'] / 10, 2);
            }
            if ($promotion_info['discount_type'] == 2) {
                $v->promotion_price = $promotion_info['discount_num'];
                $v->discount_price = $promotion_info['discount_num'];
            }
            if (getAddons('shop', $this->website_id)) {
                $shop = new VslShopModel();
                $shop_goods_lists[$v->shop_id]['shop']['shop_name'] = $shop->getInfo(['shop_id' => $v->shop_id, 'website_id' => $this->website_id])['shop_name'];
            } else {
                $shop_goods_lists[$v->shop_id]['shop']['shop_name'] = $this->mall_name;
            }

//            $shop_goods[$v->shop_id][$v->goods_id]['count'] += $v->num;
//            $shop_goods[$v->shop_id][$v->goods_id]['goods_id'] = $v->goods_id;
            unset($cart_lists[$k]->good, $cart_lists[$k]->sku, $cart_lists[$k]->goods_picture, $cart_lists[$k]->shop, $cart_lists[$k]->website);
            $shop_goods_lists[$v->shop_id]['sku'][$v->sku_id] = $v->toArray();
            $goods_sku_list .= ',' . $v->sku_id . ':' . $v->num;
            $cart_sku_info[$v->shop_id][$v->sku_id] = ['sku_id' => $v->sku_id, 'goods_id' => $v->goods_id, 'price' => $v->price, 'discount_price' => $v->discount_price, 'num' => $v->num];
        }
        unset($v);
//        $goods_express = new GoodsExpress();
//        foreach($shop_goods as $shop_id => $goods){
//               $shipping_lists[$shop_id]['shop_shipping_fee'] = $goods_express->getGoodsExpressTemplate($goods, 0);
//        }
//        $return['shipping_lists'] = $shipping_lists;
        $return['goods_sku_list'] = substr($goods_sku_list, 1); // 商品sku列表
        $return['shop_goods_lists'] = $shop_goods_lists;
        $return['full_cut_lists'] = getAddons('fullcut', $this->website_id) ? $goods_man_song_model->getCartManSong($cart_sku_info) : [];

        foreach ($return['full_cut_lists'] as $shop_id => $full_cut_info) {
            foreach ($full_cut_info['discount_percent'] as $sku_id => $discount_percent) {
                if (!empty($full_cut_info['full_cut']) && $full_cut_info['full_cut']['discount'] > 0) {
                    $cart_sku_info[$shop_id][$sku_id]['full_cut_amount'] = $full_cut_info['full_cut']['discount'];
                    $cart_sku_info[$shop_id][$sku_id]['full_cut_percent'] = $full_cut_info['discount_percent'][$sku_id];
                    $cart_sku_info[$shop_id][$sku_id]['full_cut_percent_amount'] = round($full_cut_info['discount_percent'][$sku_id] * $full_cut_info['full_cut']['discount'], 2);
                }
            }
            unset($discount_percent);
        }
        unset($full_cut_info);
        $return['cart_sku_info'] = $cart_sku_info;

        return $return;
    }

    /**
     * 暂时是移动端/app结算页面的数据获取/计算
     * @param array $sku_list
     * @param string $msg
     *
     * @return array $return_data
     */
    public function paymentData(array $sku_list, $record_id = '', $group_id = '', $presell_id = '', $un_order = 0, $website_id = 0, $uid = 0,$luckyspell_id=0)
    {
        try {
            if (!$website_id) {
                $website_id = $this->website_id;
            }
            if (!$uid) {
                $uid = $this->uid;
            }

            //        $sku_list = objToArr($sku_list);
            // 获取非秒杀,团购商品,各个类型所需的数据结构
            // $promotion_sku_list 需要计算折扣,满减,优惠券的商品,即非秒杀,团购商品
            // $return_data 全部数据
            // $return_data[$shop_id]['total_amount'] 店铺应付金额
            // $return_data[$shop_id]['goods_list'] 店铺商品
            // $return_data[$shop_id]['full_cut'] 店铺满减
            // $return_data[$shop_id]['coupon_list'] 店铺优惠券列表
            // $return_data[$shop_id]['member_promotion'] 店铺会员优惠总金额
            // $return_data[$shop_id]['discount_promotion'] 店铺限时折扣优惠总金额
            // $return_data[$shop_id]['full_cut_promotion'] 店铺满减送优惠总金额
            // $promotion_sku_list 获取满减送，优惠券信息的商品数据
            $sku_model = new VslGoodsSkuModel();
            if (getAddons('liveshopping', $website_id)) {
                $live_goods_mdl = new LiveGoodsModel();
            }
			if (getAddons('bargain', $website_id)) {
				$bargain_record = new VslBargainRecordModel();
			}
            $new_sku_list = $return_data = $sku_id_array = $seckill_sku = $shipping_sku = $record_sku = $promotion_sku_list = [];
            foreach ($sku_list as $k => $v) {
                //判断是否参与了砍价
                if ($v['bargain_id']) {
                    $is_bargain_record = $bargain_record->getInfo(['bargain_id' => $v['bargain_id'], 'uid' => $this->uid]);
                    //如果未领取砍价，则将砍价id置为0.
                    if (!$is_bargain_record) {
                        $v['bargain_id'] = 0;
                    }
                }
                $new_sku_list[$v['sku_id']] = $v;
                $sku_id_array[] = $v['sku_id'];
                $sku_detail[$k] = $sku_model::get(['sku_id' => $v['sku_id']], ['goods']);
                $sku_detail[$k]['channel_id'] = $v['channel_id'] ?: 0;
                $sku_detail[$k]['bargain_id'] = $v['bargain_id'] ?: 0;
                //主播id 判断当前商品是否属于anchor_id
                $goods_id = $sku_detail[$k]['goods_id'];
                $anchor_id = $v['anchor_id'] ?: 0;
                if (getAddons('liveshopping', $website_id)) {
                    $anchor_cond['goods_id'] = $goods_id;
                    $anchor_cond['anchor_id'] = $anchor_id;
                    //                $anchor_cond['is_recommend'] = 1;
                    $live_goods_info = $live_goods_mdl->getInfo($anchor_cond);
                    if ($live_goods_info) {
                        $sku_detail[$k]['anchor_id'] = $anchor_id;
                    }
                }
                $sku_detail[$k]['coupon_id'] = empty($v['coupon_id']) ? 0 : $v['coupon_id'];
                $sku_detail[$k]['num'] = $v['num'] ?: 0;
                if (isset($v['receive_goods_code'])) {
                    if (count($v['receive_goods_code']) == count(array_unique($v['receive_goods_code']))) {
                        $sku_detail[$k]['receive_goods_code_temp'] = $v['receive_goods_code'];
                    }
                } else {
                    $sku_detail[$k]['receive_goods_code_temp'] = [];
                }
            }
            unset($v);
            $discount_service = getAddons('discount', $website_id) ? new Discount() : '';
            $full_cut_service = getAddons('fullcut', $website_id) ? new Fullcut() : '';
            $shop = getAddons('shop', $website_id) ? new VslShopModel() : '';
            $order_goods_service = new OrderGoods();
            $album_picture_model = new AlbumPictureModel();
            $sec_server = getAddons('seckill', $website_id, $this->instance_id) ? new SeckillServer() : '';
            $goods_service = new Goods();
            $group_server = getAddons('groupshopping', $website_id, $this->instance_id) ? new GroupShoppingServer() : '';
            $addonsConfigSer = new AddonsConfigService();
            $total_account = 0;
            if ($sku_detail) {
                $temp_goods_ids = array_column(objToArr($sku_detail), 'goods_id');
                $flag_receive_goods_code_arr = [];
                foreach ($sku_detail as $k => &$v) {
                    $temp_sku = [];
                    $presell_shop_id = $v->goods->shop_id;
                    //砍价活动id
                    $bargain_id = $new_sku_list[$v->sku_id]['bargain_id'] ?: 0;
                    $channel_id = $new_sku_list[$v->sku_id]['channel_id'] ?: 0;
                    $temp_sku['goods_name'] = $v->goods->goods_name;
                    if (isset($v['anchor_id']) && !empty($v['anchor_id'])) {
                        $temp_sku['anchor_id'] = $v['anchor_id'];
                    }
                    if (getAddons('presell', $website_id, $this->instance_id) && !$un_order) {
                        $presell = new PresellService();
                        $is_presell = $presell->getPresellInfoByGoodsIdIng($v->goods_id);
                        $presell_arr = objToArr($is_presell);
                        $presell_id = $presell_arr[0]['id'];  //预售
                    }
                    $is_group = getAddons('groupshopping', $website_id, $this->instance_id) && $group_server->isGroupGoods($v->goods_id);
                    //判断此用户有没有上级渠道商，如果有，库存显示平台库存+直属上级渠道商的库存
                    if ($v->goods->shop_id == 0) {
                        if (getAddons('channel', $website_id, 0)) {
                            if (empty($channel_id)) {
                                $member_model = new VslMemberModel();
                                $referee_id = $member_model->Query(['uid' => $uid, 'website_id' => $website_id], 'referee_id')[0];
                                if ($referee_id) {//如果有上级，判断是不是渠道商
                                    $channel_model = new VslChannelModel();
                                    $is_channel = $channel_model->Query(['uid' => $referee_id, 'website_id' => $website_id], 'channel_id')[0];
                                    if ($is_channel) {//如果上级是渠道商，判断上级渠道商有没有采购过这个商品
                                        $channel_goods_sku_model = new VslChannelGoodsSkuModel();
                                        $channel_goods_id = $channel_goods_sku_model->Query(['goods_id' => $v->goods_id, 'channel_id' => $is_channel, 'sku_id' => $v->sku_id, 'website_id' => $website_id], 'sku_id')[0];
                                        if ($channel_goods_id) {
                                            $channel_id = $is_channel;
                                        }
                                    }
                                }
                            } else {
                                //检查有没有采购过该sku
                                $channel_goods_sku_model = new VslChannelGoodsSkuModel();
                                $channel_goods_id = $channel_goods_sku_model->Query(['goods_id' => $v->goods_id, 'channel_id' => $channel_id, 'sku_id' => $v->sku_id, 'website_id' => $website_id], 'sku_id')[0];
                                if (empty($channel_goods_id)) {
                                    $channel_id = 0;
                                }
                            }
                        }
                    }
                    // 活动影响的内容 是 价格、限购、库存
                    //判断当前秒杀活动的商品是否已经开始并且没有结束
                    if (!empty($new_sku_list[$v->sku_id]['seckill_id']) && getAddons('seckill', $website_id, $this->instance_id) && !$un_order) {
                        $seckill_id = $new_sku_list[$v->sku_id]['seckill_id'];
                        //判断当前秒杀活动的商品是否已经开始并且没有结束
                        $condition_seckill['s.website_id'] = $website_id;
                        $condition_seckill['nsg.sku_id'] = $v->sku_id;
                        $condition_seckill['s.seckill_id'] = $seckill_id;
                        $is_seckill = $sec_server->isSeckillGoods($condition_seckill);
                        if (!$is_seckill && !$un_order) {
                            $temp_sku['price'] = $v->price;
                            $temp_sku['seckill_id'] = 0;
                            if (!empty($new_sku_list[$v->sku_id]['cart_id'])) {
                                $this->cartAdjustSec($new_sku_list[$v->sku_id]['cart_id'], 0);
                            }
                        } else {
                            //取该商品该用户购买了多少
                            $sku_id = $v->sku_id;
                            $sec_sku_info_list = $sec_server->getSeckillSkuInfo(['seckill_id' => $seckill_id, 'sku_id' => $v->sku_id]);
                            $temp_sku['stock'] = $sec_sku_info_list->remain_num;
                            $temp_sku['price'] = $sec_sku_info_list->seckill_price;
                            $temp_sku['member_price'] = $sec_sku_info_list->seckill_price;
                            $temp_sku['discount_price'] = $sec_sku_info_list->seckill_price;
                            $temp_sku['channel_id'] = $channel_id?:0;
                        }
                    } elseif ((!empty($group_id) || !empty($record_id))) {
                        if (!$un_order) {
                            if ($is_group) {
                                $group_sku_info = $group_server->getGroupSkuInfo(['sku_id' => $v->sku_id, 'goods_id' => $v->goods_id, 'group_id' => $group_id]);
                                $buy_num = $goods_service->getActivityOrderSkuForGroup($uid, $v->sku_id, $website_id, $group_id);

                                $temp_sku['price'] = $group_sku_info->group_price;
                                $temp_sku['member_price'] = $group_sku_info->group_price;
                                $temp_sku['discount_price'] = $group_sku_info->group_price;
                                if($channel_id) {
                                    //如果有上级渠道商，库存显示平台库存+直属上级渠道商的库存
                                    $sku_id = $v->sku_id;
                                    $channel_sku_mdl = new VslChannelGoodsSkuModel();
                                    $channel_cond['channel_id'] = $channel_id;
                                    $channel_cond['sku_id'] = $sku_id;
                                    $channel_cond['website_id'] = $website_id;
                                    $channel_stock = $channel_sku_mdl->getInfo($channel_cond, 'stock')['stock'];
                                    $temp_sku['stock'] = $v->stock + $channel_stock;
                                }else{
                                    $temp_sku['stock'] = $v->stock;
                                }
                                $temp_sku['channel_id'] = $channel_id?:0;
                            }
                        }
                    } elseif (!empty($luckyspell_id) && $luckyspell_id>0) {
                        if (!$un_order) {
                            $luckyspell_server = getAddons('luckyspell', $website_id, $this->instance_id) ? new LuckySpellServer() : '';
                            $is_luckyspell = getAddons('luckyspell', $website_id, $this->instance_id) && $luckyspell_server->isGroupGoods($v->goods_id);
                            if ($is_luckyspell) {
                                $group_sku_info = $luckyspell_server->getGroupSkuInfo(['sku_id' => $v->sku_id, 'goods_id' => $v->goods_id, 'group_id' => $luckyspell_id]);
                                $buy_num = $goods_service->getActivityOrderSkuForGroup($uid, $v->sku_id, $website_id, $luckyspell_id);

                                $temp_sku['price'] = $group_sku_info->group_price;
                                $temp_sku['member_price'] = $group_sku_info->group_price;
                                $temp_sku['discount_price'] = $group_sku_info->group_price;
                                if($channel_id) {
                                    //如果有上级渠道商，库存显示平台库存+直属上级渠道商的库存
                                    $sku_id = $v->sku_id;
                                    $channel_sku_mdl = new VslChannelGoodsSkuModel();
                                    $channel_cond['channel_id'] = $channel_id;
                                    $channel_cond['sku_id'] = $sku_id;
                                    $channel_cond['website_id'] = $website_id;
                                    $channel_stock = $channel_sku_mdl->getInfo($channel_cond, 'stock')['stock'];
                                    $temp_sku['stock'] = $v->stock + $channel_stock;
                                }else{
                                    $temp_sku['stock'] = $v->stock;
                                }
                                $temp_sku['channel_id'] = $channel_id?:0;
                            }
                        }
                    } elseif (getAddons('presell', $website_id, $this->instance_id) && !empty($presell_id) && !$un_order) {
                        $temp_sku['presell_id'] = $presell_id;
                        $temp_sku['channel_id'] = $channel_id?:0;
                    } elseif (!empty($bargain_id) && getAddons('bargain', $website_id, $this->instance_id) && !$un_order) {//砍价活动
                        $bargain_server = new Bargain();
                        $condition_bargain['bargain_id'] = $bargain_id;
                        $condition_bargain['website_id'] = $website_id;
                        $is_bargain = $bargain_server->isBargain($condition_bargain, $uid);
                        if ($is_bargain && !$un_order) {
                            $bargain_stock = $is_bargain['bargain_stock'];
                            $temp_sku['price'] = $is_bargain['my_bargain']->now_bargain_money;
                            $temp_sku['discount_price'] = $is_bargain['my_bargain']->now_bargain_money;
                            $temp_sku['stock'] = $bargain_stock;
                            $temp_sku['bargain_id'] = $bargain_id;
                            $temp_sku['channel_id'] = $channel_id?:0;
                        }
                    } else {
                        //普通商品
                        if ($v->goods->state != 1) {
                            throw new Exception('商品为不可购买状态');
                        }
                        if ($v->stock < $new_sku_list[$v->sku_id]['num'] && empty($presell_id) && empty($channel_id)) {
                            $temp_sku['num'] = $v->stock;
                        }
                        $temp_sku['stock'] = $v->stock;
                        if (!empty($channel_id) && getAddons('channel', $website_id) && !$un_order) {
                            $sku_id = $v->sku_id;
                            $channel_sku_mdl = new VslChannelGoodsSkuModel();
                            $channel_cond['channel_id'] = $channel_id;
                            $channel_cond['sku_id'] = $sku_id;
                            $channel_cond['website_id'] = $website_id;
                            $channel_stock = $channel_sku_mdl->getInfo($channel_cond, 'stock')['stock'];
                            $temp_sku['stock'] = $channel_stock + $v->stock;
                            $temp_sku['channel_id'] = $channel_id;
                        }
                        $temp_sku['price'] = $v->price;
                        //限时折扣
                        $limit_discount_info = getAddons('discount', $website_id) ? $discount_service->getPromotionInfo($v->goods_id, $v->goods->shop_id, $v->goods->website_id) : ['discount_num' => 10];
                        $temp_sku['discount_id'] = $limit_discount_info['discount_id'] ?: '';
                        //查询商品是否开启PLUS会员价
                        $is_member_discount = 1;
                        $goodsPower = $this->getGoodsPowerDiscount($v->goods_id);
                        $goodsPower = json_decode($goodsPower['value'],true);
                        $plus_member = $goodsPower['plus_member'] ? : 0;
                        if($plus_member == 1) {
                            if(getAddons('membercard',$website_id)) {
                                $membercard = new \addons\membercard\server\Membercard();
                                $membercard_data = $membercard->checkMembercardStatus($uid);
                                if($membercard_data['status'] && $membercard_data['membercard_info']['is_member_discount']) {
                                    //有会员折扣权益
                                    $temp_sku['member_price'] = $temp_sku['price'] * $membercard_data['membercard_info']['member_discount'] / 10;
                                    $shop_member_price = $v->price - $temp_sku['member_price'];
                                    $platform_member_price = $v->price - $temp_sku['member_price'];
                                    //如果存在限时折扣 则会员价为原价
                                    if($limit_discount_info['discount_id']){
                                        $temp_sku['member_price'] = $temp_sku['price'];
                                    }else{
                                        $return_data[$v->goods->shop_id]['shop_member_price'] += $shop_member_price * $new_sku_list[$v->sku_id]['num'];
                                        $return_data[$v->goods->shop_id]['platform_member_price'] += $platform_member_price * $new_sku_list[$v->sku_id]['num'];
                                    }
                                    $is_member_discount = 0;
                                }
                            }
                        }

                        if($is_member_discount) {
                            $goodsDiscountInfo = $this->getGoodsInfoOfIndependentDiscount($v->goods_id, $v->price, ['price' => $v->goods->price, 'shop_id' => $v->goods->shop_id]);//计算会员折扣价
                            //会员折扣后的价格
                            if ($goodsDiscountInfo) {
                                $temp_sku['member_price'] = $goodsDiscountInfo['member_price'];
                                //如果存在限时折扣 则会员价为原价
                                if($limit_discount_info['discount_id']){
                                    $temp_sku['member_price'] = $temp_sku['price'];
                                }else{
                                    $return_data[$v->goods->shop_id]['shop_member_price'] += $goodsDiscountInfo['shop_member_price'] * $new_sku_list[$v->sku_id]['num'];
                                    $return_data[$v->goods->shop_id]['platform_member_price'] += $goodsDiscountInfo['platform_member_price'] * $new_sku_list[$v->sku_id]['num'];
                                }
                            }
                        }
                        //限时折扣处理
                        if ($limit_discount_info['integer_type'] == 1) {
                            $temp_sku['discount_price'] = round($temp_sku['member_price'] * $limit_discount_info['discount_num'] / 10);
                        } else {
                            $temp_sku['discount_price'] = round($temp_sku['member_price'] * $limit_discount_info['discount_num'] / 10, 2);
                        }
                        if ($limit_discount_info['discount_type'] == 2) {
                            $temp_sku['discount_price'] = $limit_discount_info['discount_num'];
                        }
                        if ($limit_discount_info['shop_id'] > 0) {
                            $return_data[$v->goods->shop_id]['shop_member_price'] += ($temp_sku['member_price'] - $temp_sku['discount_price']) * $new_sku_list[$v->sku_id]['num'];
                        } else {
                            $return_data[$v->goods->shop_id]['platform_member_price'] += ($temp_sku['member_price'] - $temp_sku['discount_price']) * $new_sku_list[$v->sku_id]['num'];
                        }
                    }

                    # 领货码
                    if(isset($v['receive_goods_code_temp']) && is_array($v['receive_goods_code_temp'])){
                        $v['receive_goods_code_temp'] = array_diff($v['receive_goods_code_temp'],$flag_receive_goods_code_arr);
                        $temp_sku['receive_goods_code_num_ids'] = [];
                        foreach ($v['receive_goods_code_temp'] as $receive_goods_code) {
                            $checkRes = $this->getReceiveGoodsCodeConfigInfo(decryptBase64HaveKey($receive_goods_code), $website_id, $v['goods']['shop_id']);
                            if (!in_array($checkRes['data']['goods_id'], $temp_goods_ids)) {
                                throw new Exception('请绑定对应的商品领货码');
                            }
                            if ($checkRes['code'] < 0) {
                                return $checkRes;
                            }
                            //查询码的信息
                            if ($checkRes['data']['goods_id'] == $v['goods_id']){
                                $receiveGoodsCodeSer = new ReceiveGoodsCodeSer();
                                $code_id = $receiveGoodsCodeSer->getReceiveGoodsCodeIdByBase64CodeNo($receive_goods_code,$v['goods']['shop_id']);//code_id
                                if($code_id){
                                    $flag_receive_goods_code_arr[] = $receive_goods_code;
                                    $temp_sku['receive_goods_code_ids'][] = $code_id;
                                    $temp_sku['receive_goods_code_num_ids'][]= $code_id;
                                }
                                $checkRes['data']['code_id'] = $code_id;
                                $temp_sku['receive_goods_code_data'][] = $checkRes['data'];//店铺中，商品对应码对应的数据
                            }
                        }
                        $temp_sku['receive_goods_code_used'] = $v['receive_goods_code_temp'];
                    }

                    $temp_sku['min_buy'] = 1;
                    $return_data[$v->goods->shop_id]['shop_id'] = $v->goods->shop_id;
                    if (empty($return_data[$v->goods->shop_id]['shop_name'])) {
                        if (getAddons('shop', $website_id)) {
                            $return_data[$v->goods->shop_id]['shop_name'] = $shop->getInfo(['shop_id' => $v->goods->shop_id, 'website_id' => $v->goods->website_id])['shop_name'];
                        } else {
                            $return_data[$v->goods->shop_id]['shop_name'] = $this->mall_name;
                        }
                    }
                    $temp_sku['sku_id'] = $v->sku_id;
                    $temp_sku['num'] = $new_sku_list[$v->sku_id]['num'];
                    $temp_sku['goods_id'] = $v->goods_id;
                    $temp_sku['shop_id'] = $v->goods->shop_id;
                    $temp_sku['goods_type'] = $v->goods->goods_type;
                    $temp_sku['point_deduction_max'] = $v->goods->point_deduction_max;
                    $temp_sku['point_return_max'] = $v->goods->point_return_max;
                    $temp_sku['shipping_fee_type'] = $v->goods->shipping_fee_type;
                    //暂时取商品的图片，不取规格图
                    $picture_info = $album_picture_model->get($v->goods->picture);
                    $temp_sku['goods_pic'] = $picture_info ? getApiSrc($picture_info->pic_cover) : '';

                    //                    $temp_sku['discount_id'] = $limit_discount_info['discount_id'] ?: '';
                    $temp_sku['seckill_id'] = $new_sku_list[$v->sku_id]['seckill_id'] ?: '';
                    if (empty($is_bargain) && empty($temp_sku['seckill_id']) && (empty($group_id) && empty($record_id)) && empty($presell_id) && empty($luckyspell_id) && !$un_order) {
                        //普通商品进入 各类金额汇总-》总价 等等 待处理
                        $promotion_sku_list[$v->goods->shop_id][$v->sku_id] = $temp_sku;
                        $return_data[$v->goods->shop_id]['total_amount'] += $temp_sku['discount_price'] * $temp_sku['num'];
                        // 用于计算 折扣 类型优惠券的总额
                        $return_data[$v->goods->shop_id]['amount_for_coupon_discount'] += $temp_sku['discount_price'] * $temp_sku['num'];
                        // 店铺会员优惠总金额   会员折扣不写入优惠金额 -- 待定
                        $return_data[$v->goods->shop_id]['member_promotion'] += ($temp_sku['price'] - $temp_sku['member_price']) * $temp_sku['num'];
                        // 店铺限时折扣优惠总金额
                        $return_data[$v->goods->shop_id]['discount_promotion'] += ($temp_sku['member_price'] - $temp_sku['discount_price']) * $temp_sku['num'];
                    } else {
                        $return_data[$v->goods->shop_id]['total_amount'] += $temp_sku['price'] * $temp_sku['num'];
                        // 将显示的价格全部设置为discount_price
                        $temp_sku['discount_price'] = $temp_sku['price'];
                        // 店铺会员优惠总金额
                        $return_data[$v->goods->shop_id]['member_promotion'] += 0;
                        // 店铺限时折扣优惠总金额
                        $return_data[$v->goods->shop_id]['discount_promotion'] += 0;
                    }
                    // 规格
                    $spec_info = [];
                    if ($v['attr_value_items']) {
                        $sku_spec_info = explode(';', $v['attr_value_items']);
                        foreach ($sku_spec_info as $k_spec => $v_spec) {
                            $spec_value_id = explode(':', $v_spec)[1];
                            $spec_info[$k_spec] = $order_goods_service->getSpecInfo($spec_value_id, $temp_sku['goods_id']);
                        }
                        unset($v_spec);
                    }
                    $temp_sku['spec'] = $spec_info;
                    //判断是否有传预售ID
                    if (!$presell_id) {
                        $return_data[$presell_shop_id]['presell_info'] = null;
                    } else {

                        if (getAddons('presell', $website_id, $this->instance_id) && !$un_order) {
                            //从SKUID和预售ID找到相关信息
                            $presell = new PresellService();
                            $sku_id = $sku_list[0]['sku_id'];
                            $info = $presell->getPresellBySku($presell_id, $sku_id);
                            if ($info) {
                                //判断当前用户购买了多少件该活动商品
                                $p_cond['activity_id'] = $presell_id;
                                $p_cond['uid'] = $uid;
                                $p_cond['sku_id'] = $v->sku_id;
                                $p_cond['buy_type'] = 4;
                                $p_cond['website_id'] = $website_id;
                                $aosr_mdl = new VslActivityOrderSkuRecordModel();
                                $user_already_buy = $aosr_mdl->getInfo($p_cond, 'num')['num'];
                                $return_data[$presell_shop_id]['presell_info']['maxbuy'] = ($info['maxbuy'] - $user_already_buy) > 0 ? ($info['maxbuy'] - $user_already_buy) : 0;
                                $return_data[$presell_shop_id]['presell_info']['firstmoney'] = $info['firstmoney'] ?: 0;
                                $return_data[$presell_shop_id]['presell_info']['allmoney'] = $info['allmoney'] ?: 0;
                                $return_data[$presell_shop_id]['presell_info']['presellnum'] = $info['presellnum'] ?: 0;
                                $return_data[$presell_shop_id]['presell_info']['vrnum'] = $info['vrnum'] ?: 0;
                                $return_data[$presell_shop_id]['presell_info']['pay_start_time'] = $info['pay_start_time'] ?: 0;
                                $return_data[$presell_shop_id]['presell_info']['pay_end_time'] = $info['pay_end_time'] ?: 0;
                                $return_data[$presell_shop_id]['presell_info']['goods_num'] = $sku_list[0]['num'] ?: 0;
                                $return_data[$presell_shop_id]['total_amount'] = $info['firstmoney'] * $sku_list[0]['num'] ?: 0;
                                #领货码     TODO...
                                $return_data[$presell_shop_id]['presell_info']['receive_goods_code_data'] = $temp_sku['receive_goods_code_data'] ?:[];

                                $have_buy = $presell->getPresellSkuNum($presell_id, $temp_sku['sku_id']);
                                $return_data[$presell_shop_id]['presell_info']['over_num'] = $info['presellnum'] - $have_buy;  //已购买人数
                                $total_account = $info['firstmoney'] * $new_sku_list[$v->sku_id]['num'];
                                $temp_sku['price'] = $info['firstmoney'];
                            }
                        }
                    }

                    //优惠券
                    if (getAddons('coupontype', $website_id) && !$un_order) {
                        $temp_sku['coupon_id'] = isset( $v->coupon_id) ? $v->coupon_id : 0;
                    }

                    $return_data[$v->goods->shop_id]['goods_list'][] = $temp_sku;
                    // 下面的满减送和优惠券可能不进去循环，先初始化一些数据
                    $return_data[$v->goods->shop_id]['full_cut'] = (object)[];
                    $return_data[$v->goods->shop_id]['coupon_list'] = [];
                    $return_data[$v->goods->shop_id]['coupon_num'] = 0;

                    //卡券核销门店
                    if (getAddons('store', $website_id, $this->instance_id) && $v['goods']['goods_type'] == 0 && !$un_order) {
                        $store = new Store();
                        //判断是否开启了门店自提
                        $storeSet = $store->getStoreSet($v->goods->shop_id)['is_use'];
                        if($storeSet) {
                            $store_list = $v['goods']['store_list'];
                            if (empty($store_list)) {
                                $return_data[$v->goods->shop_id]['store_list'] = [];
                            } else {
                                $store_id = explode(',', $store_list); //适用的门店ID
                                $condition = [];
                                $condition['website_id'] = $v['website_id'];
                                $condition['store_id'] = ['IN', $store_id];
                                $lng = input('lng', 0);
                                $lat = input('lat', 0);
                                $place = ['lng' => $lng, 'lat' => $lat];
                                $store_list = $store->storeListForFront(1, 20, $condition, $place);
                                if (empty($store_list)) {
                                    $return_data[$v->goods->shop_id]['store_list'] = [];
                                } else {
                                    $return_data[$v->goods->shop_id]['store_list'] = $store_list['store_list'];
                                }
                            }
                        }else{
                            $return_data[$v->goods->shop_id]['store_list'] = [];
                        }
                    }
                }
                unset($v);
            }

            // 满减送
            if (getAddons('fullcut', $website_id) && !$un_order) {
                $full_cut_lists = $full_cut_service->getPaymentFullCut($promotion_sku_list); //异常点
                foreach ($full_cut_lists as $kk => $vv) {
                    if (empty($vv['man_song_id'])) {
                        unset($full_cut_lists[$kk]);
                    }
                }
                unset($vv);
                $full_cut_limit = [];
                foreach ($full_cut_lists as $shop_id => $full_cut_info) {
                    if ($full_cut_info['discount_percent']) {
                        foreach ($full_cut_info['discount_percent'] as $sku_id => $discount_percent) {
                            if (!empty($full_cut_info) && $full_cut_info['discount'] > 0) {
                                // 计算优惠券需要的信息
                                $promotion_sku_list[$shop_id][$sku_id]['full_cut_amount'] = $full_cut_info['discount'];
                                $promotion_sku_list[$shop_id][$sku_id]['full_cut_percent'] = $full_cut_info['discount_percent'][$sku_id];
                                $promotion_sku_list[$shop_id][$sku_id]['full_cut_percent_amount'] = round($full_cut_info['discount_percent'][$sku_id] * $full_cut_info['discount'], 2);
                            }
                        }
                    }

                    $return_data[$shop_id]['total_amount'] -= $full_cut_info['discount'];
                    $return_data[$shop_id]['amount_for_coupon_discount'] -= $full_cut_info['discount'];
                    $full_cut_limit[$shop_id] = $full_cut_info['goods_limit'];
                    unset($full_cut_info['discount_percent']);
                    //重新赋值discount 原规则是rule_discount,是需要展示的值，由于前端更新字段需要重新发布小程序等 所以在后台循环重新赋值 同时把真实值保留 需要展示总的优惠额

                    $full_cut_info['real_discount'] = $full_cut_info['discount'];
                    $full_cut_info['discount'] = $full_cut_info['rule_discount'];

                    $return_data[$shop_id]['full_cut'] = $full_cut_info ?: (object)[];
                    if (!empty($presell_id)) {
                        $return_data[$shop_id]['full_cut'] = (object)[];
                    }

                }

                unset($full_cut_info);
                if (empty($presell_id)) {
                    $full_cut_compute = [];
                    foreach ($promotion_sku_list as $k => $v) {
                        foreach ($v as $k2 => $v2) {
                            $full_cut_compute[$k2]['full_cut_amount'] = $v2['full_cut_amount'];
                            $full_cut_compute[$k2]['full_cut_percent'] = $v2['full_cut_percent'];
                            $full_cut_compute[$k2]['full_cut_percent_amount'] = $v2['full_cut_percent_amount'];
                        }
                        unset($v2);
                    }
                    unset($v);

                    foreach ($return_data as $k => $v) {
                        $full_cut_goods = [];

                        if (!empty($full_cut_limit[$k])) {
                            foreach ($full_cut_limit[$k] as $k3 => $v3) {
                                $full_cut_goods[$v3] = 1;
                            }
                            unset($v3);
                            if ($v['goods_list']) {
                                foreach ($v['goods_list'] as $k2 => $v2) {
                                    if ($full_cut_goods[$v2['goods_id']] == 1) {
                                        $return_data[$k]['goods_list'][$k2]['full_cut_sku_amount'] = $full_cut_compute[$v2['sku_id']]['full_cut_amount'];
                                        $return_data[$k]['goods_list'][$k2]['full_cut_sku_percent'] = $full_cut_compute[$v2['sku_id']]['full_cut_percent'];
                                        $return_data[$k]['goods_list'][$k2]['full_cut_sku_percent_amount'] = $full_cut_compute[$v2['sku_id']]['full_cut_percent_amount'];
                                    }
                                }
                                unset($v2);
                            }
                        } else {
                            if ($v['goods_list']) {
                                foreach ($v['goods_list'] as $k2 => $v2) {
                                    $return_data[$k]['goods_list'][$k2]['full_cut_sku_amount'] = $full_cut_compute[$v2['sku_id']]['full_cut_amount'];
                                    $return_data[$k]['goods_list'][$k2]['full_cut_sku_percent'] = $full_cut_compute[$v2['sku_id']]['full_cut_percent'];
                                    $return_data[$k]['goods_list'][$k2]['full_cut_sku_percent_amount'] = $full_cut_compute[$v2['sku_id']]['full_cut_percent_amount'];
                                }
                                unset($v2);
                            }
                        }
                    }
                    unset($v);
                }

            }
            //end 满减送

            // 优惠券
            if (getAddons('coupontype', $website_id) && !$un_order) {
                $coupon_service = new Coupon();

                $coupon_list = $coupon_service->getMemberCouponListNew($promotion_sku_list); // 获取优惠券

                $coupon_compute = [];
                foreach ($coupon_list as $shop_id => $v) {
                    foreach ($v['coupon_info'] as $coupon_id => $c) {
                        $temp_coupon = [];
                        $temp_coupon['coupon_id'] = $c['coupon_id'];
                        $temp_coupon['coupon_name'] = $c['coupon_type']['coupon_name'];
                        $temp_coupon['coupon_genre'] = $c['coupon_type']['coupon_genre'];
                        $temp_coupon['shop_range_type'] = $c['coupon_type']['shop_range_type'];
                        $temp_coupon['at_least'] = $c['coupon_type']['at_least'];
                        $temp_coupon['money'] = $c['coupon_type']['money'];
                        $temp_coupon['discount'] = $c['coupon_type']['discount'];
                        $temp_coupon['start_time'] = $c['coupon_type']['start_time'];
                        $temp_coupon['end_time'] = $c['coupon_type']['end_time'];
                        $temp_coupon['shop_id'] = $c['coupon_type']['shop_id'];
                        $return_data[$shop_id]['coupon_list'][] = $temp_coupon;
                    }
                    unset($c);
                    $return_data[$shop_id]['coupon_num'] = count($v['coupon_info']);
                    $coupon_compute[$shop_id] = $v['sku_percent'];
                    //有预售则清空
                    if (!empty($presell_id)) {
                        $return_data[$shop_id]['coupon_list'][] = [];
                        $return_data[$shop_id]['coupon_num'] = 0;
                    }
                }
                unset($v);

                if (empty($presell_id)) {
                    foreach ($return_data as $k => $v) {
                        $return_data[$k]['coupon_promotion'] = 0;
                        if ($v['goods_list']) {
                            foreach ($v['goods_list'] as $k2 => $v2) {
                                if ($v2['coupon_id'] > 0) {
                                    $return_data[$k]['goods_list'][$k2]['coupon_sku_percent'] = $coupon_compute[$k][$v2['coupon_id']][$v2['sku_id']]['coupon_percent'];
                                    $return_data[$k]['goods_list'][$k2]['coupon_sku_percent_amount'] = $coupon_compute[$k][$v2['coupon_id']][$v2['sku_id']]['coupon_percent_amount'];
                                    $return_data[$k]['coupon_promotion'] += $return_data[$k]['goods_list'][$k2]['coupon_sku_percent_amount'];
                                }
                            }
                            unset($v2);
                            $return_data[$k]['total_amount'] -= $return_data[$k]['coupon_promotion'];
                        }
                    }
                    unset($v);
                }
            }

            #领货码
        //        $codeFlag = $addonsConfigSer->isAddonsIsLeastOne('receivegoodscode',$website_id);
            $codeFlag = getAddons('receivegoodscode',$website_id);
            if ($codeFlag){
                $codeSer = new ReceiveGoodsCodeSer();
                $codeList = $codeSer->getUserOfCodeList($uid,2);//用户所有店铺的已绑定且有效领货码
            }
            foreach ($return_data as $k => &$v) {
                if ($total_account != 0) {
                    $v['total_amount'] = $total_account;
                } else {
                    $v['total_amount'] = ($v['total_amount'] > 0) ? $v['total_amount'] : 0;
                }
                $v['amount_for_coupon_discount'] = ($v['amount_for_coupon_discount'] > 0) ? $v['amount_for_coupon_discount'] : 0;

                #领货码
                if ($codeFlag){
                    $copyRes = $codeSer->getReceiveGoodsCodeOfSetting($v['shop_id']);
                    if ($copyRes['data']['is_use'] == 1){
                        $copy_writing = $copyRes['data']['copy_writing'] ?: '领货码';
                        $temp_receive_goods_code = [];
                        $shop_goods_ids = array_column($v['goods_list'],'goods_id');
                        foreach($codeList as $k1 => $code){
                            if ($code['shop_id'] == $v['shop_id'] && in_array($code['goods_id'],$shop_goods_ids)){
                                $temp_receive_goods_code[] = [
                                    'code_id'       => $code['code_id'],
                                    'code'          => encryptBase64HaveKey($code['code']['code']),
                                    'discount_type' => $code['config_info']['discount_type'],
                                    'discount_price'=> $code['config_info']['discount_price'],
                                    'validity_type' => $code['code']['validity_type'],
                                    'start_time'    => $code['code']['start_time'],
                                    'end_time'      => $code['code']['end_time'],
                                    'goods_info'    => $code['goods_info'],
                                ];
                            }
                        }
                        unset($code);
                        $v['receive_goods_code'] = [
                            'copy_writing' => $copy_writing,
                            'count' => count($temp_receive_goods_code),
                            'data' => $temp_receive_goods_code,
                        ];
                        unset($temp_receive_goods_code);
                        $temp_receive_goods_code_useds = reset($v['goods_list'])['receive_goods_code_used']?:'';
                        if ($temp_receive_goods_code_useds) {
                            $code_goods_info = [];
                            foreach ($temp_receive_goods_code_useds as $temp_receive_goods_code_used){
                                $code_goods_info[] = $codeSer->getReceiveCodeAndGoodsInfoByBase64CodeNo($temp_receive_goods_code_used,$v['shop_id']);;
                            }
                            $v['receive_goods_code_used'] = $code_goods_info;
                        }
                    }
                }
                //获取领货码配置，是否可以使用多张
                $is_use_many = 1;
                $v['is_use_coupon'] = 1;
                if (getAddons('receivegoodscode', $this->website_id, $k)) {
                    $addonsRes = $addonsConfigSer->getAddonsConfig('receivegoodscode', $this->website_id, $k);
                    if ($addonsRes) {
                        if (isset($addonsRes['value']['is_use_many']) && $addonsRes['value']['is_use_many'] == 0) {
                            $is_use_many = 0;
                        }
                        if (isset($addonsRes['value']['is_use_coupon']) && $addonsRes['value']['is_use_coupon'] == 0) {
                            $v['is_use_coupon'] = 0;
                        }
                    }
                }
                if (empty($is_use_many)) {
                    foreach ($v['goods_list'] as $k1 => $v1) {
                        if ($v1['receive_goods_code_num_ids'] && count($v1['receive_goods_code_num_ids']) > $v1['num']) {
                            array_pop($v['receive_goods_code_used']);
                            array_pop($v['goods_list'][$k1]['receive_goods_code_used']);
                            array_pop($v['goods_list'][$k1]['receive_goods_code_data']);
                            array_pop($v['goods_list'][$k1]['receive_goods_code_ids']);
                        }
                        unset($v['goods_list'][$k1]['receive_goods_code_num_ids']);
                    }
                }
            }
            unset($v);
            unset($temp_goods_ids);
            return $return_data;
        }catch (\Exception $e){
            recordErrorLog($e);
//            return AjaxReturn(FAIL,[],' 行号:'.$e->getLine().' 错误:'. $e->getMessage());
        }
    }

    /**
     * 获取购物车
     *
     * @param unknown $uid
     */
    public function getCart($uid, $shop_id = 0, &$msg = '')
    {
        if ($uid > 0) {
            $cart = new VslCartModel();
            $cart_goods_list = null;
            if ($shop_id == 0) {
                $cart_goods_list = $cart->getQuery([
                    'buyer_id' => $this->uid,
                    'website_id' => $this->website_id
                ], '*', '');
            } else {

                $cart_goods_list = $cart->getQuery([
                    'buyer_id' => $this->uid,
                    'shop_id' => $shop_id,
                    'website_id' => $this->website_id
                ], '*', '');
            }

        } else {
            $cart_goods_list = cookie('cart_array' . $this->website_id);
            if (empty($cart_goods_list)) {
                $cart_goods_list = array();
            } else {
                $cart_goods_list = json_decode($cart_goods_list, true);
            }
        }

        if (!empty($cart_goods_list)) {
            foreach ($cart_goods_list as $k => $v) {
                $goods_info = $this->getGoodsDetailById($v['goods_id']);

                //获取当前商品是否在什么活动中
                $promotion_type = $goods_info['promotion_type'];
                $cart_goods_list[$k]['promotion_type'] = $promotion_type;
                // 获取商品sku信息
                $goods_sku = new VslGoodsSkuModel();
                $sku_info = $goods_sku->getInfo([
                    'sku_id' => $v['sku_id']
                ], 'stock, price, sku_name, promote_price,sku_max_buy');
                $goods_name = $goods_info['goods_name'];
                if (mb_strlen($goods_info['goods_name']) > 10) {
                    $goods_name = mb_substr($v->goods['goods_name'], 0, 10) . '...';
                }

                // 验证商品或sku是否存在,不存在则从购物车移除
                if ($uid > 0) {
                    if (empty($goods_info)) {
                        $cart->destroy([
                            'goods_id' => $v['goods_id'],
                            'buyer_id' => $uid
                        ]);
                        unset($cart_goods_list[$k]);
                        $msg .= "购物车内商品发上变化，已重置购物车" . PHP_EOL;
                        continue;
                    }
                    if (empty($sku_info)) {
                        unset($cart_goods_list[$k]);
                        $cart->destroy([
                            'buyer_id' => $uid,
                            'sku_id' => $v['sku_id']
                        ]);
                        $msg .= $goods_name . "商品无sku规格信息，已移除" . PHP_EOL;
                        continue;
                    }
                } else {
                    if (empty($goods_info)) {
                        unset($cart_goods_list[$k]);
                        $this->cartDelete($v['cart_id']);
                        $msg .= "购物车内商品发上变化，已重置购物车" . PHP_EOL;
                        continue;
                    }
                    if (empty($sku_info)) {
                        unset($cart_goods_list[$k]);
                        $this->cartDelete($v['cart_id']);
                        $msg .= $goods_name . "商品无sku规格信息，已移除" . PHP_EOL;
                        continue;
                    }
                }
                if ($goods_info['state'] != 1) {
//                    unset($cart_goods_list[$k]);
//                    // 更新cookie购物车
//                    $this->cartDelete($v['cart_id']);
                    $msg .= $goods_name . "商品该sku规格已下架" . PHP_EOL;
                    continue;
                }
                $num = $v['num'];

                //判断此用户有没有上级渠道商，如果有，库存显示平台库存+直属上级渠道商的库存
                $channel_stock = 0;
                if(getAddons('channel',$this->website_id,0)) {
                    $member_model = new VslMemberModel();
                    $referee_id = $member_model->getInfo(['uid'=>$this->uid,'website_id'=>$this->website_id],'referee_id');
                    if($referee_id['referee_id']) {//如果有上级，判断是不是渠道商
                        $channel_model = new VslChannelModel();
                        $is_channel = $channel_model->getInfo(['uid'=>$referee_id['referee_id'],'website_id'=>$this->website_id],'channel_id');
                        if($is_channel) {//如果上级是渠道商，判断上级渠道商有没有采购过这个商品
                            $channel_sku_mdl = new VslChannelGoodsSkuModel();
                            $channel_cond['channel_id'] = $is_channel['channel_id'];
                            $channel_cond['sku_id'] = $v['sku_id'];
                            $channel_cond['website_id'] = $this->website_id;
                            $channel_stock = $channel_sku_mdl->getInfo($channel_cond, 'stock')['stock'];
                        }
                    }
                }

                if ($sku_info['stock'] + $channel_stock < $num) {
                    $num = $sku_info['stock'] + $channel_stock;
                }
                // 商品最小购买数大于现购买数
                if ($goods_info['min_buy'] > 0 && $num < $goods_info['min_buy']) {
                    $num = $goods_info['min_buy'];
                    $msg .= $goods_name . "商品该sku规格现购买数小于最小购买数，已修改购物数量" . PHP_EOL;
                }
                // 商品最小购买数大于现有库存
                if ($goods_info['min_buy'] > $sku_info['stock'] + $channel_stock) {
//                    unset($cart_goods_list[$k]);
//                    // 更新cookie购物车
//                    $this->cartDelete($v['cart_id']);
                    $msg .= $goods_name . "商品该sku规格最小购买数大于现有库存，已修改购物数量" . PHP_EOL;
                    continue;
                }
                if ($num != $v['num']) {
                    // 更新购物车
                    $cart_goods_list[$k]['num'] = $num;
                    $this->cartAdjustNum($v['cart_id'], $num);
                }
                // 预售
                if((getAddons('presell', $this->website_id, $this->instance_id))){
                    //判断当前商品是否在预售活动中
                    $presell = new PresellService();
                    $is_presell = $presell->getIsInPresell($v['goods_id']);
                }

                // 为cookie信息完善商品和sku信息
                if ($uid > 0) {

                    // 查看用户会员价
                    // todo... 会员折扣 by sgw商品价格计算
                    //查询商品是否开启PLUS会员价
                    $is_member_discount = 1;
                    $goodsPower = $this->getGoodsPowerDiscount($v->goods_id);
                    $goodsPower = json_decode($goodsPower['value'],true);
                    $plus_member = $goodsPower['plus_member'] ? : 0;
                    if($plus_member == 1) {
                        if(getAddons('membercard',$this->website_id)) {
                            $membercard = new \addons\membercard\server\Membercard();
                            $membercard_data = $membercard->checkMembercardStatus($this->uid);
                            if($membercard_data['status'] && $membercard_data['membercard_info']['is_member_discount']) {
                                //有会员折扣权益
                                $member_price = $sku_info['price'] * $membercard_data['membercard_info']['member_discount'] / 10;
                                $is_member_discount = 0;
                            }
                        }
                    }

                    if($is_member_discount) {
                        $goodsDiscountInfo = $this->getGoodsInfoOfIndependentDiscount($v->goods_id, $sku_info['price'], ['price' => $goods_info['price'], 'shop_id' => $goods_info['shop_id']]);//计算会员折扣价
                        if ($goodsDiscountInfo) {
                            $member_price = $goodsDiscountInfo['member_price'];
                        }
                    }

                    if (getAddons('seckill', $this->website_id, $this->instance_id)) {
                        //判断是否有秒杀的商品并且是否过期，若有直接取秒杀价
                        $sec_server = new SeckillServer();
                        if (!empty($v['seckill_id'])) {
                            $condition_seckill['s.seckill_id'] = $v['seckill_id'];
                            $condition_seckill['nsg.sku_id'] = $v['sku_id'];
                            $is_seckill = $sec_server->isSeckillGoods($condition_seckill);
                        } else {
                            $condition_seckill['nsg.sku_id'] = $v['sku_id'];
                            $is_seckill = $sec_server->isSkuStartSeckill($condition_seckill);
                            if ($is_seckill) {
                                $v['seckill_id'] = $is_seckill['seckill_id'];
                                $seckill_data['cart_id'] = $v["cart_id"];
                                $seckill_data['seckill_id'] = $is_seckill['seckill_id'];
                                $cart->data($seckill_data, true)->isupdate(true)->save();
                            }
                        }
                    }

                    # todo... max_buy
                    $cart_goods_list[$k]['max_buy']   = $this->getUserMaxBuyGoodsSkuCount(0,$v['goods_id'],$v['sku_id']);
                    $cart_goods_list[$k]['least_buy'] = $goods_info['least_buy'];
                    if ($is_seckill) {
                        //取该商品该用户购买了多少
                        $sku_id = $v['sku_id'];
                        $uid = $this->uid;
                        $website_id = $this->website_id;
                        $buy_num = $this->getActivityOrderSku($uid, $sku_id, $website_id, $v['seckill_id']);
                        $sec_sku_info_list = $sec_server->getSeckillSkuInfo(['seckill_id' => $v->seckill_id, 'sku_id' => $sku_id]);
                        $sku_info['stock'] = $sec_sku_info_list->remain_num;
//                        $goods_info['max_buy'] = (($sec_sku_info_list->seckill_limit_buy - $buy_num) < 0) ? $sec_sku_info_list->seckill_limit_buy : $sec_sku_info_list->seckill_limit_buy - $buy_num;
//                        $goods_info['max_buy'] = $goods_info['max_buy'] > $sku_info['stock'] ? $sku_info['stock'] : $goods_info['max_buy'];
                        //如果最大购买数小于购物车的数量并且不等于0
//                        if ($goods_info['max_buy'] != 0 && $goods_info['max_buy'] < $v['num']) {
//                            // 更新购物车
//                            $cart_goods_list[$k]['num'] = $goods_info['max_buy'];
//                            $this->cartAdjustNum($v['cart_id'], $goods_info['max_buy']);
//                        }
//                        if ($goods_info['max_buy'] == 0) {
//                            unset($cart_goods_list[$k]);
//                            $this->cartDelete($v['cart_id']);
//                            $msg .= $goods_name . "商品已达上限" . PHP_EOL;
//                            continue;
//                        }
//                        $sku_info['stock'] = $goods_info['max_buy'];
                        $price = (float)$sec_sku_info_list->seckill_price;
                        $cart_goods_list[$k]['max_buy']   = $this->getUserMaxBuyGoodsSkuCount(1,$is_seckill['seckill_id'],$v['sku_id']);
                        $cart_goods_list[$k]['least_buy'] = $is_seckill['least_buy'] ?: 0;

                    }elseif($is_presell){
                        $can_buy = $presell->getMeCanBuy($is_presell['presell_id'], $v['sku_id']);
                        $sku_info['stock'] = $can_buy;
//                        $goods_info['max_buy'] = $can_buy;
                        $price = $is_presell['all_money'];
                        $cart_goods_list[$k]['max_buy']   = $this->getUserMaxBuyGoodsSkuCount(3,$is_presell['id'],$v['sku_id']);
                        $cart_goods_list[$k]['least_buy'] = $is_presell['least_buy'] ?: 0;
                    }else {
//                        $cart_goods_list[$k]['promotion_type'] =0;
                        $price = $member_price;
                    }
//                    var_dump($is_seckill);
                    $update_data = array(
                        "goods_name" => $goods_info["goods_name"],
                        "sku_name" => $sku_info["sku_name"],
                        "goods_picture" => $v['goods_picture'], // $goods_info["picture"],
                        "price" => $price
                    );
                    // 更新数据
                    $cart->save($update_data, [
                        "cart_id" => $v["cart_id"]
                    ]);
                    $cart_goods_list[$k]["price"] = $price;
                    $cart_goods_list[$k]["oprice"] = $sku_info['price'];
                    $cart_goods_list[$k]["goods_name"] = $goods_info["goods_name"];
                    $cart_goods_list[$k]["sku_name"] = $sku_info["sku_name"];
                    $cart_goods_list[$k]["goods_picture"] = $v['goods_picture']; // $goods_info["picture"];
                    $cart_goods_list[$k]['stock'] = $sku_info['stock'] + $channel_stock;
//                    $cart_goods_list[$k]['max_buy'] = $goods_info['max_buy'];
                    $cart_goods_list[$k]['state'] = $goods_info['state'];
                } else {
                    if (!empty($v['seckill_id']) && getAddons('seckill', $this->website_id, $this->instance_id)) {
                        //判断是否有秒杀的商品并且是否过期，若有直接取秒杀价
                        $condition_seckill['s.seckill_id'] = $v['seckill_id'];
                        $condition_seckill['nsg.sku_id'] = $v['sku_id'];
                        $sec_server = new SeckillServer();
                        $is_seckill = $sec_server->isSeckillGoods($condition_seckill);
                        if ($is_seckill) {
                            $cart_goods_list[$k]["price"] = $is_seckill['seckill_price'];
                            $remain_num = $is_seckill['remain_num'];
                            $limit_buy = $is_seckill['seckill_limit_buy'];
                            $cart_goods_list[$k]['stock'] = $remain_num;
//                            $cart_goods_list[$k]['max_buy'] = $limit_buy;
                        } else {
                            $cart_goods_list[$k]["price"] = $sku_info["price"];
                            $cart_goods_list[$k]['stock'] = $sku_info['stock'];
//                            $cart_goods_list[$k]['max_buy'] = $goods_info['max_buy'];
                        }
                    }elseif($is_presell){
                        $cart_goods_list[$k]["price"] = $is_presell["all_money"];
                        $presell_goods = new VslPresellGoodsModel();
                        $presell_sku_info = $presell_goods->getInfo(['presell_id' => $is_presell['presell_id'], 'sku_id' => $v['sku_id']]);
                        $cart_goods_list[$k]['stock'] = $presell_sku_info['presell_num'];
//                        $cart_goods_list[$k]['max_buy'] = $presell_sku_info['max_buy'];
                    } else {
                        $cart_goods_list[$k]["price"] = $sku_info["price"];
                        $cart_goods_list[$k]['stock'] = $sku_info['stock'];
//                        $cart_goods_list[$k]['max_buy'] = $goods_info['max_buy'];
                    }
                    $cart_goods_list[$k]["goods_name"] = $goods_info["goods_name"];
                    $cart_goods_list[$k]["sku_name"] = $sku_info["sku_name"];
                    $cart_goods_list[$k]["goods_picture"] = $v['goods_picture']; // $goods_info["picture"];
                }
                if ($cart_goods_list[$k]["max_buy"] == -1) {
                    unset($cart_goods_list[$k]);
                    $this->cartDelete($v['cart_id']);
                    $msg .= $goods_name . "商品已达上限" . PHP_EOL;
                    continue;
                }
//                $cart_goods_list[$k]['min_buy'] = $goods_info['min_buy'];
                $cart_goods_list[$k]['point_exchange_type'] = $goods_info['point_exchange_type'];
                $cart_goods_list[$k]['point_exchange'] = $goods_info['point_exchange'];
                $cart_goods_list[$k]['sku_name_arr'] = array_filter(explode(' ', $sku_info["sku_name"]));
            }
            unset($v);
            // 为购物车图片
            foreach ($cart_goods_list as $k => $v) {
                $picture = new AlbumPictureModel();
                $picture_info = $picture->getInfo(['pic_id' => $v['goods_picture']], 'pic_cover, pic_cover_small,pic_cover_micro,pic_cover_mid');
                $cart_goods_list[$k]['picture_info'] = $picture_info;
            }
            unset($v);
            sort($cart_goods_list);
        }

        return $cart_goods_list;
    }

    /**
     * 添加购物车(non-PHPdoc)
     *
     * @see \data\api\IGoods::addCart()
     */
    public function addCart($uid, $shop_id, $goods_id, $goods_name, $sku_id, $sku_name, $price, $num, $picture, $bl_id, $seckill_id = 0, $anchor_id = 0,$supplier_id=0)
    {
        try{
            if(getAddons('liveshopping', $this->website_id)){
                if($anchor_id){
                    $live_goods_mdl = new LiveGoodsModel();
                    $anchor_cond['goods_id'] = $goods_id;
                    $anchor_cond['anchor_id'] = $anchor_id;
//                $anchor_cond['is_recommend'] = 1;
                    $live_goods_info = $live_goods_mdl->getInfo($anchor_cond);
                    if(!$live_goods_info){
                        $anchor_id = 0;
                    }
                }
            }
            if (getAddons('seckill', $this->website_id, $this->instance_id)) {
                //判断是否有seckill_id并且是否已经开始
                $sec_server = new SeckillServer();
                //判断当前商品是否为秒杀商品并且已经开始未结束
                $condition_seckill['s.seckill_id'] = $seckill_id;
                $condition_seckill['nsg.goods_id'] = $goods_id;
                $is_seckill = $sec_server->isSeckillGoods($condition_seckill);
            }
            $stock = $this->getSkuBySkuid($sku_id,'stock')['stock'];//获取规格库存
            if ($is_seckill) {
                //获取限购数量
                $seckill_sku_list = $sec_server->getSeckillSkuInfo(['seckill_id' => $seckill_id, 'sku_id' => $sku_id]);
                $limit_buy = $seckill_sku_list->seckill_limit_buy;
                $seckill_id = $seckill_id;
                if ($limit_buy != 0) {
                    if ($num > $limit_buy) {
                        $num = $limit_buy;
                    }
                }
                //如果库存不足了
                $redis = connectRedis();
                $redis_goods_sku_seckill_key = 'seckill_' . $seckill_id . '_' . $goods_id . '_' . $sku_id;//每个活动的库存都不一样
                $is_index = $redis->get($redis_goods_sku_seckill_key);
                if (!$is_index) {
                    return -2;
                }
            } else {
                $seckill_id = 0;
            }
            // 检测当前购物车中是否存在产品
            if ($uid > 0) {
                $cart = new VslCartModel();
                $condition = array(
                    'buyer_id' => $uid,
                    'sku_id' => $sku_id
                );
                //多用户shopid重新获取
                $shop_id = $this->getGoodsShopid($goods_id);
                if (getAddons('shop', $this->website_id)) {
                    //获取店铺名称
                    $shop_model = new VslShopModel();
                    $shop_info = $shop_model::get(['shop_id' => $shop_id, 'website_id' => $this->website_id]);
                    $shop_name = $shop_info['shop_name'];
                } else {
                    $shop_name = $this->mall_name;
                }
                $count = $cart->where($condition)->count();

                if ($count == 0 || empty($count)) {
                    $data = array(
                        'buyer_id' => $uid,
                        'shop_id' => $shop_id,
                        'shop_name' => $shop_name,
                        'goods_id' => $goods_id,
                        'goods_name' => $goods_name,
                        'sku_id' => $sku_id,
                        'sku_name' => $sku_name,
                        'price' => $price,
                        'num' => $num,
                        'goods_picture' => $picture,
                        'bl_id' => $bl_id,
                        'website_id' => $this->website_id,
                        'seckill_id' => $seckill_id,
                        'anchor_id' => $anchor_id,
                        'supplier_id' => $supplier_id,
                    );
                    $cart->save($data);
                    $retval = $cart->cart_id;
                } else {
                    $cart = new VslCartModel();
                    // 查询商品限购
                    $get_num = $cart->getInfo($condition, 'cart_id,num');
                $max_buy = $this->getGoodsDetailById($goods_id);

                $new_num = $num + $get_num['num'];
                if ($new_num > $stock) {
                    return -2;
                }
                if ($is_seckill) {
                    $price = $seckill_sku_list->seckill_price;
                    if ($limit_buy != 0) {
                        if ($new_num > $limit_buy) {
                            $new_num = $limit_buy;
                        }
                    }
                    $data['seckill_id'] = $seckill_id;
                    $data['num'] = $new_num;
                    $data['price'] = $price;
                } else {
                    if ($max_buy['max_buy'] != 0) {

                        if ($new_num > $max_buy['max_buy']) {

                            $new_num = $max_buy['max_buy'];
                        }
                    }
//                    $data['seckill_id'] = $seckill_id;
                        $data = array(
                            'num' => $new_num
                        );
                    }
                    if($anchor_id){
                        $data['anchor_id'] = $anchor_id;
                    }
                    $retval = $cart->save($data, $condition);
                    if ($retval) {
                        $retval = $get_num['cart_id'];
                    }
                }
            } else {
                $cart_array = cookie('cart_array' . $this->website_id);
                $shop_id = $this->getGoodsShopid($goods_id);
                if (getAddons('shop', $this->website_id)) {
                    //获取店铺名称
                    $shop_model = new VslShopModel();
                    $shop_info = $shop_model::get(['shop_id' => $shop_id, 'website_id' => $this->website_id]);
                    $shop_name = $shop_info['shop_name'];
                } else {
                    $shop_name = $this->mall_name;
                }
                $data = array(
                    'shop_id' => $shop_id,
                    'shop_name' => $shop_name,
                    'goods_id' => $goods_id,
                    'sku_id' => $sku_id,
                    'num' => $num,
                    'goods_picture' => $picture,
                    'anchor_id' => $anchor_id,
                );
                if ($is_seckill) {
                    $data['seckill_id'] = $seckill_id;
                }
                $cart_array = json_decode($cart_array, true);
                if (!empty($cart_array)) {
                    $tmp_array = array();
                    foreach ($cart_array as $k => $v) {
                        $tmp_array[] = $v['cart_id'];
                    }
                    unset($v);
                    $cart_id = max($tmp_array) + 1;
                    $is_have = true;
                    foreach ($cart_array as $k => $v) {
                        if ($v["goods_id"] == $goods_id && $v["sku_id"] == $sku_id) {
                            $is_have = false;
                            if (($data["num"] + $v["num"]) > $stock) {
                                return -2;
                            }
                            $cart_array[$k]["num"] = $data["num"] + $v["num"];
                        }
                    }
                    unset($v);
                    if ($is_have) {
                        $data["cart_id"] = $cart_id;
                        $cart_array[] = $data;
                    }
                } else {
                    $data["cart_id"] = 1;
                    $cart_array[] = $data;
                }
                $cart_array_string = json_encode($cart_array);
                cookie('cart_array' . $this->website_id, $cart_array_string, 3600);
                $retval = 1;
            }
            return $retval;
        }catch(\Exception $e){
//            recordErrorLog($e);
            return 0;
        }

    }

    /**
     * 购物车数量修改(non-PHPdoc)
     *
     * @see \data\api\IGoods::cartAdjustNum()
     */
    public function cartAdjustNum($cart_id, $num)
    {
        if ($this->uid > 0) {
            $cart = new VslCartModel();
            $data = array(
                'num' => $num
            );
            $retval = $cart->save($data, [
                'cart_id' => $cart_id
            ]);
            return $retval;
        } else {
            $result = $this->updateCookieCartNum($cart_id, $num);
            return $result;
        }
    }

    /**
     * 购物车秒杀商品修改(non-PHPdoc)
     *
     * @see \data\api\IGoods::cartAdjustNum()
     */
    public function cartAdjustSec($cart_id, $seckill_id)
    {
        if ($this->uid > 0) {
            $cart = new VslCartModel();
            $data = array(
                'seckill_id' => $seckill_id
            );
            $retval = $cart->save($data, [
                'cart_id' => $cart_id
            ]);
            return $retval;
        }
    }

    /**
     * 门店购物车秒杀商品修改(non-PHPdoc)
     *
     * @see \data\api\IGoods::cartAdjustNum()
     */
    public function storeCartAdjustSec($cart_id, $seckill_id)
    {
        if ($this->uid > 0) {
            $cart = new VslStoreCartModel();
            $data = array(
                'seckill_id' => $seckill_id
            );
            $retval = $cart->save($data, [
                'cart_id' => $cart_id
            ]);
            return $retval;
        }
    }

    /**
     * 购物车项目删除(non-PHPdoc)
     *
     * @see \data\api\IGoods::cartDelete()
     */
    public function cartDelete($cart_id_array)
    {
        if ($this->uid > 0) {
            $cart = new VslCartModel();
            $retval = $cart->destroy($cart_id_array);
            return $retval;
        } else {
            $result = $this->deleteCookieCart($cart_id_array);
            return $result;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::getGroupGoodsList()
     */
    public function getGroupGoodsList($goods_group_id, $condition = '', $num = 0, $order = '')
    {
        $goods_list = array();
        $goodsSer = new Goods();
        $condition['state'] = 1;
        $list = $goodsSer->getGoodsListByCondition($condition, '*', $order);
        foreach ($list as $k => $v) {
            $v['picture_info'] = $v['album_picture'];
            $group_id_array = explode(',', $v['group_id_array']);
            if (in_array($goods_group_id, $group_id_array) || $goods_group_id == 0) {
                $goods_list[] = $v;
            }
        }
        unset($v);
        foreach ($goods_list as $k => $v) {
            if (!empty($this->uid)) {
                $member = new Member();
                $goods_list[$k]['is_favorite'] = $member->getIsMemberFavorites($this->uid, $v['goods_id'], 'goods');
            } else {
                $goods_list[$k]['is_favorite'] = 0;
            }

            $goods_sku = new VslGoodsSkuModel();
            // 获取sku列表
            $sku_list = $goods_sku->where([
                'goods_id' => $v['goods_id']
            ])->select();
            $goods_list[$k]['sku_list'] = $sku_list;
        }
        unset($v);
        if ($num == 0) {
            return $goods_list;
        } else {
            $count_list = count($goods_list);
            if ($count_list > $num) {
                return array_slice($goods_list, 0, $num);
            } else {
                return $goods_list;
            }
        }
    }


    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::getGoodsEvaluate()
     */
    public function getGoodsEvaluate($goods_id)
    {
        $goodsEvaluateModel = new VslGoodsEvaluateModel();
        $condition['goods_id'] = $goods_id;
        $field = 'order_id, orderno, order_goods_id, goods_id, goods_name, goods_price, goods_image, storeid, storename, content, addtime, image, explain_first, member_name, uid, is_anonymous, scores, again_content, again_addtime, again_image, again_explain';
        return $goodsEvaluateModel->getQuery($condition, $field, 'id ASC');
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::getGoodsEvaluateList()
     */
    public function getGoodsEvaluateList($page_index = 1, $page_size = 0, $condition = array(), $order = '', $field = '*')
    {
        $goodsEvaluateModel = new VslGoodsEvaluateModel();
        $evaluates = $goodsEvaluateModel->getViewList($page_index, $page_size, $condition, $order);
        foreach ($evaluates['data'] as &$evaluate) {
            $evaluate['nick_name'] = $evaluate['nick_name'] ?: ($evaluate['user_name'] ?: ($evaluate['user_name_default'] ?: '匿名'));
            $evaluate['user_img'] = $evaluate['user_headimg'] ?: $evaluate['head_img_default'];
        }
        unset($evaluate);
        return $evaluates;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::getGoodsShopid()
     */
    public function getGoodsShopid($goods_id)
    {
        $goods_info = $this->getGoodsDetailById($goods_id);
        return $goods_info['shop_id'];
    }

    /**
     * (non-PHPdoc)
     * @evaluate_count总数量 @imgs_count带图的数量 @praise_count好评数量 @center_count中评数量 bad_count差评数量
     *
     * @see \data\api\IGoods::getGoodsEvaluateCount()
     */
    public function getGoodsEvaluateCount($goods_id)
    {
        $goods_evaluate = new VslGoodsEvaluateModel();
        $evaluate_count_list['evaluate_count'] = $goods_evaluate->where([
            'goods_id' => $goods_id,
            'is_show' => 1,
            'website_id' => $this->website_id
        ])->count();

        $evaluate_count_list['again_count'] = $goods_evaluate->where([
            'goods_id' => $goods_id,
            'is_show' => 1,
            'website_id' => $this->website_id,
            'again_content' => ['NEQ', '']
        ])->count();

        $evaluate_count_list['imgs_count'] = $goods_evaluate->where([
            'goods_id' => $goods_id,
            'is_show' => 1,
            'website_id' => $this->website_id
        ])
            ->where('image|again_image', 'NEQ', '')
            ->count();

        $evaluate_count_list['praise_count'] = $goods_evaluate->where([
            'goods_id' => $goods_id,
            'explain_type' => 5,
            'is_show' => 1,
            'website_id' => $this->website_id
        ])->count();
        $evaluate_count_list['center_count'] = $goods_evaluate->where([
            'goods_id' => $goods_id,
            'explain_type' => 3,
            'is_show' => 1,
            'website_id' => $this->website_id
        ])->count();
        $evaluate_count_list['bad_count'] = $goods_evaluate->where([
            'goods_id' => $goods_id,
            'explain_type' => 1,
            'is_show' => 1,
            'website_id' => $this->website_id
        ])->count();
        return $evaluate_count_list;
    }

    /**
     * (non-PHPdoc)
     * @point平均分数 @ratio展示百分比
     *
     */
    public function getGoodsEvaluateDetail($goods_id)
    {
        $goodsEvaluateData = ['point' => 0, 'ratio' => 0];
        $goods_evaluate = new VslGoodsEvaluateModel();
        $count = $goods_evaluate->where([
            'goods_id' => $goods_id,
            'is_show' => 1,
            'website_id' => $this->website_id
        ])->count();
        if (!$count) {
            return $goodsEvaluateData;
        }
        $goodsEvaluateData['point'] = number_format($goods_evaluate->getSum(['goods_id' => $goods_id, 'is_show' => 1, 'website_id' => $this->website_id], 'explain_type') / $count, 1, '.', '');
        $goodsEvaluateData['ratio'] = intval($goodsEvaluateData['point'] * 20);
        return $goodsEvaluateData;
    }

    /**
     * 获取商家或店铺的商品评论总数(non-PHPdoc)
     * @praise_count好评数量 @center_count中评数量 bad_count差评数量
     *
     * @see \data\api\IGoods::getGoodsEvaluateCount()
     */
    public function getEvaluateCount($shop_id = 0)
    {
        $goods_evaluate = new VslGoodsEvaluateModel();

        if ($shop_id > 0) {
            $evaluate_count_list['praise_count'] = $goods_evaluate->where([
                'shop_id' => $shop_id,
                'explain_type' => 5,
                'website_id' => $this->website_id
            ])->count();
            $evaluate_count_list['center_count'] = $goods_evaluate->where([
                'shop_id' => $shop_id,
                'explain_type' => 3,
                'website_id' => $this->website_id
            ])->count();
            $evaluate_count_list['bad_count'] = $goods_evaluate->where([
                'shop_id' => $shop_id,
                'explain_type' => 1,
                'website_id' => $this->website_id
            ])->count();

        } else {
            $evaluate_count_list['praise_count'] = $goods_evaluate->where([
                'explain_type' => 5,
                'website_id' => $this->website_id
            ])->count();
            $evaluate_count_list['center_count'] = $goods_evaluate->where([
                'explain_type' => 3,
                'website_id' => $this->website_id
            ])->count();
            $evaluate_count_list['bad_count'] = $goods_evaluate->where([
                'explain_type' => 1,
                'website_id' => $this->website_id
            ])->count();
        }


        return $evaluate_count_list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::getGoodsRank()
     */
    public function getGoodsRank($condition)
    {
        $goods = new VslGoodsModel();//列表查询
        $goods_list = $goods->where($condition)
            ->order(" real_sales desc ")
            ->limit(6)
            ->select();
        return $goods_list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::getGoodsExpressTemplate()
     */
    public function getGoodsExpressTemplate($goods_id, $province_id, $city_id, $district_id)
    {
        $goods_express = new GoodsExpress();
        $retval = $goods_express->getGoodsExpressTemplate($goods_id, $province_id, $city_id, $district_id)['totalFee'];
        return $retval;
    }


    /**
     * (non-PHPdoc)
     * 获取所有商品品牌
     * @see \data\api\IGoods::getGoodsExpressTemplate()
     */
    public function getAllBrand($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $goods_brand = new VslGoodsBrandModel();
        $brand_list = $goods_brand->pageQuery($page_index, $page_size, $condition, $order, $field);
        return $brand_list;
    }


    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::getGoodsSpecList()
     */
    public function getGoodsSpecList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $goods_spec = new VslGoodsSpecModel();
        $goods_spec_value = new VslGoodsSpecValueModel();
        $goods_attr = new VslAttributeModel();
        $condition['website_id'] = $this->website_id;
        $goods_spec_list = $goods_spec->pageQuery($page_index, $page_size, $condition, $order, $field);
        if (!empty($goods_spec_list['data'])) {
            foreach ($goods_spec_list['data'] as $ks => $vs) {
                $attrValue = '';
                if ($vs['goods_attr_id']) {
                    $attrCheck = explode(',', $vs['goods_attr_id']);
                    foreach ($attrCheck as $ka => $va) {
                        $attrValue .= $goods_attr->getInfo(['attr_id' => $va], 'attr_name')['attr_name'] . ',';
                    }
                    unset($va);
                    $attrValue = substr($attrValue, 0, strlen($attrValue) - 1);
                }
                $goods_spec_value_name = '';
                $condition = ['spec_id' => $vs['spec_id']];
                if ($this->instance_id == 0) {
                    $condition['shop_id'] = $this->instance_id;
                } else {
                    $condition['shop_id'] = array(['=', 0], ['=', $this->instance_id], 'or');
                }
                $spec_value_list = $goods_spec_value->getQuery($condition, '*', 'sort asc');
                foreach ($spec_value_list as $kv => $vv) {
                    $goods_spec_value_name = $goods_spec_value_name . ',' . $vv['spec_value_name'];
                }
                unset($vv);
                $goods_spec_list['data'][$ks]['spec_value_list'] = $spec_value_list;
                $goods_spec_value_name = $goods_spec_value_name == '' ? '' : substr($goods_spec_value_name, 1);
                $goods_spec_list['data'][$ks]['spec_value_name_list'] = $goods_spec_value_name;
                $goods_spec_list['data'][$ks]['attr_value_list'] = $attrValue;
            }
            unset($vs);
        }
        return $goods_spec_list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::getGoodsSpecDetail()
     */
    public function getGoodsSpecDetail($spec_id)
    {
        $goods_spec = new VslGoodsSpecModel();
        $goods_spec_value = new VslGoodsSpecValueModel();
        $info = $goods_spec->getInfo([
            'spec_id' => $spec_id
        ], '*');
        $goods_spec_value_name = '';
        $goods_spec_value_name_platform = '';//c端把bc端规格值分离
        if (!empty($info)) {
            // 去除规格属性空值
            $goods_spec_value->destroy([
                'spec_id' => $info['spec_id'],
                'spec_value_name' => ''
            ]);
            $condition = ['spec_id' => $info['spec_id'], 'website_id' => $this->website_id];
            if ($this->instance_id == 0) {
                $condition['shop_id'] = $this->instance_id;
            } else {
                $condition['shop_id'] = array(['=', 0], ['=', $this->instance_id], 'or');
            }
            $spec_value_list = $goods_spec_value->getQuery($condition, '*', 'sort asc');
            foreach ($spec_value_list as $kv => $vv) {
                if ($this->instance_id) {
                    if ($vv['shop_id']) {
                        $goods_spec_value_name = $goods_spec_value_name . ',' . $vv['spec_value_name'];
                    } else {
                        $goods_spec_value_name_platform = $goods_spec_value_name_platform . ',' . $vv['spec_value_name'];
                    }
                } else {
                    $goods_spec_value_name = $goods_spec_value_name . ',' . $vv['spec_value_name'];
                }

            }
            unset($vv);
        }
        $info['spec_value_name_list'] = substr($goods_spec_value_name, 1);
        $info['spec_value_name_list_platform'] = substr($goods_spec_value_name_platform, 1);
        $info['spec_value_list'] = $spec_value_list;
        return $info;
    }

    /*
     * 删除属性表里的规格ID
     */
    public function deleteSpecFromAttr($spec_id, $attr_id)
    {

        $attribute = new VslAttributeModel();
        $attribute_info = $attribute->getInfo([
            "attr_id" => $attr_id
        ], "*");
        $spec_array = explode(',', $attribute_info['spec_id_array']);
        foreach ($spec_array as $k => $v) {
            if ($spec_id == $v) {
                unset($spec_array[$k]);
            }
        }
        unset($v);
        $new_spec = implode(',', $spec_array);
        return $attribute->save(['spec_id_array' => $new_spec], ['attr_id' => $attr_id]);
    }

    /*
     * 删除规格表里的品类id
     */
    public function deleteAttrFromSpec($spec_id, $attr_id)
    {

        $specModel = new VslGoodsSpecModel();
        $specInfo = $specModel->getInfo([
            "spec_id" => $spec_id
        ], "goods_attr_id");
        $attrArr = explode(',', $specInfo['goods_attr_id']);
        foreach ($attrArr as $k => $v) {
            if ($attr_id == $v) {
                unset($attrArr[$k]);
            }
        }
        unset($v);
        $newAttrArr = implode(',', $attrArr);
        return $specModel->save(['goods_attr_id' => $newAttrArr], ['spec_id' => $spec_id]);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::addGoodsSpec()
     */
    public function addGoodsSpecService($shop_id, $spec_name, $show_type, $is_visible, $sort, $spec_value_str, $attr_id, $is_screen)
    {
        $supplier_id = $this->supplier_id ?: 0;//供应商id
        $model = Request::instance()->module();
        $goods_spec = new VslGoodsSpecModel();
        $checkRepeat = $goods_spec->getInfo(['spec_name' => $spec_name, 'website_id' => $this->website_id, 'shop_id' => $shop_id, 'supplier_id' => $supplier_id]);
        if ($checkRepeat) {
            return -10031;
        }
        $goods_spec->startTrans();
        try {
            if ($model == 'platform') {
                $is_platform = 1;
            } else {
                $is_platform = 0;
            }
            $data = array(
                'shop_id' => $shop_id,
                'website_id' => $this->website_id,
                'spec_name' => $spec_name,
                'show_type' => $show_type,
                'is_visible' => $is_visible,
                'sort' => $sort,
                "is_screen" => $is_screen,
                'create_time' => time(),
                'goods_attr_id' => $attr_id,
                'is_platform' => $is_platform,
                'supplier_id' => $supplier_id
            );
            $goods_spec->save($data);
            $spec_id = $goods_spec->spec_id;
            // 添加规格并修改上级分类关联规格
            if ($attr_id > 0) {
                $attribute = new VslAttributeModel();
                $attribute_info = $attribute->getInfo([
                    "attr_id" => $attr_id
                ], "*");
                if ($attribute_info["spec_id_array"] == '') {
                    $attribute->save([
                        "spec_id_array" => $spec_id
                    ], [
                        "attr_id" => $attr_id
                    ]);
                } else {
                    $attribute->save([
                        "spec_id_array" => $attribute_info["spec_id_array"] . "," . $spec_id
                    ], [
                        "attr_id" => $attr_id
                    ]);
                }
            }
            $spec_value_array = explode(',', $spec_value_str);
            $spec_value_array = array_filter($spec_value_array); // 去空
            $spec_value_array = array_unique($spec_value_array); // 去重复
            foreach ($spec_value_array as $k => $v) {
                $spec_value = array();
                if ($show_type == 2) {
                    $spec_value = explode(':', $v);
                    $this->addGoodsSpecValueService($spec_id, $spec_value[0], $spec_value[1], 1, 255);
                } else {
                    $this->addGoodsSpecValueService($spec_id, $v, '', 1, 255);
                }
            }
            unset($v);
            $goods_spec->commit();
            $data['spec_id'] = $spec_id;
            hook("goodsSpecSaveSuccess", $data);
            return $spec_id;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $goods_spec->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::updateGoodsSpecService()
     */
    public function updateGoodsSpecService($spec_id, $shop_id, $spec_name, $show_type, $is_visible, $sort, $spec_value_str, $is_screen, $attr_id, $seleted_attr_str)
    {

        $goods_spec = new VslGoodsSpecModel();
        $checkRepeat = $goods_spec->getInfo(['spec_name' => $spec_name, 'website_id' => $this->website_id, 'shop_id' => $shop_id, 'spec_id' => ['<>', $spec_id]]);
        if ($checkRepeat) {
            return -10031;
        }
        $goods_spec->startTrans();
        try {
            $data = array(
                'spec_name' => $spec_name,
                'show_type' => $show_type,
                'is_visible' => $is_visible,
                'is_screen' => $is_screen,
                'goods_attr_id' => $attr_id,
                'sort' => $sort
            );
            $res = $goods_spec->save($data, [
                'spec_id' => $spec_id
            ]);
            if (!empty($spec_value_str)) {
                $specValueModel = new VslGoodsSpecValueModel();
                $specValueNotIn = $specValueModel->Query(['spec_value_name' => ['not in', $spec_value_str], 'spec_id' => $spec_id, 'website_id' => $this->website_id, 'shop_id' => $this->instance_id], 'spec_value_id');
                if ($specValueNotIn) {//删除数据库中不属于前台提交的规格值
                    $specValueModel->delData(['spec_value_id' => ['in', implode(',', $specValueNotIn)]]);
                }
                $spec_value_array = explode(',', $spec_value_str);
                $spec_value_array = array_filter($spec_value_array); // 去空
                $spec_value_array = array_unique($spec_value_array); // 去重复
                foreach ($spec_value_array as $k => $v) {
                    $spec_value = array();
                    if ($show_type == 2) {
                        $spec_value = explode(':', $v);
                        $this->addGoodsSpecValueService($spec_id, $spec_value[0], $spec_value[1], 1, $k);
                    } elseif ($v) {
                        $this->addGoodsSpecValueService($spec_id, $v, '', 1, $k);
                    }
                }
                unset($v);
            } else {
                $specValueModel = new VslGoodsSpecValueModel();
                $specValueModel->delData(['spec_id' => $spec_id, 'website_id' => $this->website_id, 'shop_id' => $this->instance_id]);
            }
            $data['spec_id'] = $spec_id;
            hook("goodsSpecSaveSuccess", $data);
            $new_attr_id = explode(',', $attr_id);//表单提交过来的ID
            $seleted_attr = explode(',', $seleted_attr_str); //提交前的ID
            $attribute = new VslAttributeModel();
            //循环传过来的属性ID，判断规格是否在此属性ID里面，无则增加，少则删除
            foreach ($new_attr_id as $k => $v) {
                $attribute_info = $attribute->Query([
                    "attr_id" => $v
                ], "*");
                //单个属性规格信息
                foreach ($attribute_info as $value) {
                    //拿到规格字符串
                    $spec_str = explode(',', $value['spec_id_array']);
                    //判断规格ID，没有则增加
                    if (!in_array($spec_id, $spec_str)) {
                        $spec_str = $value['spec_id_array'] . ',' . $spec_id;
                        $attribute->save(['spec_id_array' => $spec_str], ['attr_id' => $value['attr_id']]);
                    }
                }
                unset($value);
            }
            unset($v);
            //循环最初的属性ID，如果没有在提交后的ID里，则表示删除ID
            foreach ($seleted_attr as $v1) {
                if (!in_array($v1, $new_attr_id)) {
                    //删除
                    $this->deleteSpecFromAttr($spec_id, $v1);
                }
            }
            unset($v1);
            $goods_spec->commit();
            return $res;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $goods_spec->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::addGoodsSpecValue()
     */
    public function addGoodsSpecValueService($spec_id, $spec_value_name, $spec_value_data, $is_visible, $sort = 0)
    {
        $supplier_id = $this->supplier_id ?:0;//供应商
        $goods_spec_value = new VslGoodsSpecValueModel();
        $goodsSpecModel = new VslGoodsSpecModel();
        $checkSpecHas = $goodsSpecModel->getCount(['spec_id' => $spec_id]);//检查规格是否已经被删除了，删除了不能添加规格值
        if (!$checkSpecHas) {
            return -10032;
        }
        $data = array(
            'spec_id' => $spec_id,
            'spec_value_name' => $spec_value_name,
            'spec_value_data' => $spec_value_data,
            'is_visible' => $is_visible,
            'sort' => $sort,
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'supplier_id' => $supplier_id,
            'create_time' => time()
        );
        $checkCondition = ['spec_value_name' => $spec_value_name, 'shop_id' => $this->instance_id, 'website_id' => $this->website_id, 'spec_id' => $spec_id,'supplier_id' => $supplier_id];
        if ($this->instance_id) {
            $checkCondition['shop_id'] = array(['=', 0], ['=', $this->instance_id], 'or');
        }
        if ($supplier_id){
            $checkCondition[] = ['exp','supplier_id ='.$supplier_id.' OR (supplier_id=0 AND shop_id=0)'];
        }
        $check = $goods_spec_value->getInfo($checkCondition,'spec_value_id, sort');
        if ($check) {
            if($check['sort'] != $sort){
                $goods_spec_value->save(['sort' => $sort], ['spec_value_id' => $check['spec_value_id']]);
            }
            return -10012;
        }
        $goods_spec_value->save($data);
        return $goods_spec_value->spec_value_id;
    }


    public function updateGoodsSpecValueService($data, $condition)
    {

        $goods_spec_value = new VslGoodsSpecValueModel();
        $retval = $goods_spec_value->save($data, $condition);
        return $retval;
    }

    public function addGoodsEvaluateReply($id, $replyContent, $replyType)
    {
        $goodsEvaluate = new VslGoodsEvaluateModel();
        if ($replyType == 1) {
            return $goodsEvaluate->save([
                'explain_first' => $replyContent,
                'explain_time' => time()
            ], [
                'id' => $id
            ]);
        } elseif ($replyType == 2) {
            return $goodsEvaluate->save([
                'again_explain' => $replyContent,
                'again_explain_time' => time()
            ], [
                'id' => $id
            ]);
        }
    }

    public function setEvaluateShowStatu($id)
    {
        $goodsEvaluate = new VslGoodsEvaluateModel();
        $showStatu = $goodsEvaluate->getInfo([
            'id' => $id
        ], 'is_show');
        if ($showStatu['is_show'] == 1) {
            return $goodsEvaluate->save([
                'is_show' => 0
            ], [
                'id' => $id
            ]);
        } elseif ($showStatu['is_show'] == 0) {
            return $goodsEvaluate->save([
                'is_show' => 1
            ], [
                'id' => $id
            ]);
        }
    }

    public function deleteEvaluate($id)
    {
        $goodsEvaluate = new VslGoodsEvaluateModel();
        return $goodsEvaluate->destroy($id);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::deleteGoodsSpecValue()
     */
    public function deleteGoodsSpecValue($spec_id, $spec_value_id)
    {
        // 检测是否使用
        //$res = $this->checkGoodsSpecValueIsUse($spec_id, $spec_value_id);
        // 检测规格属性数量
        $result = $this->getGoodsSpecValueCount([
            'spec_id' => $spec_id
        ]);

        if ($result == 1) {
            return -2;
        } else {
            $goods_spec_value = new VslGoodsSpecValueModel();
            return $goods_spec_value->destroy($spec_value_id);
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::getGoodsSpecValueCount()
     */
    public function getGoodsSpecValueCount($condition)
    {
        $spec_value = new VslGoodsSpecValueModel();
        $count = $spec_value->where($condition)->count();
        return $count;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::deleteGoodsSpec()
     */
    public function deleteGoodsSpec($spec_id)
    {
        $goods_spec = new VslGoodsSpecModel();
        $goods_spec_value = new VslGoodsSpecValueModel();
        $goods_spec->startTrans();
        try {
            $spec_id_array = explode(',', $spec_id);
            foreach ($spec_id_array as $k => $v) {
                $goods_spec->destroy($v);
                $goods_spec_value->destroy([
                    'spec_id' => $v
                ]);
            }
            unset($v);
            $goods_spec->commit();
            hook("goodsSpecDeleteSuccess", $spec_id);
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $goods_spec->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     * 删除商品属性
     * @see \data\api\IGoods::deleteGoodsSpec()
     */
    public function deleteGoodsAttr($attr_value_id)
    {
        $attrValueModel = new VslAttributeValueModel();
        $res = $attrValueModel->delData(['attr_value_id' => $attr_value_id]);
        return $res;
    }

    /*
     * 修改商品规格是否启动
     * **/
    public function updateGoodsSpecShow($spec_id, $is_visible)
    {
        $goods_spec = new VslGoodsSpecModel();
        $res['is_visible'] = $is_visible;
        $bool = $goods_spec->where(['spec_id' => $spec_id])->update($res);
        return $bool;
    }

    /*
     * 修改商品品类是否启动
     * **/
    public function updateGoodsAttrShow($attr_id, $is_use)
    {
        $goods_spec = new VslAttributeModel();
        $res['is_use'] = $is_use;
        $bool = $goods_spec->where(['attr_id' => $attr_id])->update($res);
        return $bool;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::modifyGoodsSpecField()
     */
    public function modifyGoodsSpecField($spec_id, $field_name, $field_value)
    {
        $goods_spec = new VslGoodsSpecModel();
        return $goods_spec->save([
            "$field_name" => $field_value
        ], [
            'spec_id' => $spec_id
        ]);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::modifyGoodsSpecValueField()
     */
    public function modifyGoodsSpecValueField($spec_value_id, $field_name, $field_value)
    {
        $goods_spec_value = new VslGoodsSpecValueModel();
        return $goods_spec_value->save([
            "$field_name" => $field_value
        ], [
            'spec_value_id' => $spec_value_id
        ]);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::getGoodsAttributeServiceList()
     */
    public function getAttributeServiceList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $attribute = new VslAttributeModel();
        $attribute_value = new VslAttributeValueModel();
        $categoryModel = new VslGoodsCategoryModel();
        $caegoryServer = new GoodsCategory();
        $list = $attribute->pageQuery($page_index, $page_size, $condition, $order, $field);
        if (!empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                $new_array = $attribute_value->getQuery([
                    'attr_id' => $v['attr_id']
                ], 'attr_value_name', '');
                $value_str = '';
                foreach ($new_array as $kn => $vn) {
                    $value_str = $value_str . ',' . $vn['attr_value_name'];
                }
                unset($vn);
                $value_str = substr($value_str, 1);
                $list['data'][$k]['value_str'] = $value_str;

                $value_stra = '';
                if (!empty($list['data'][$k]['spec_id_array'])) {
                    $goods_spec_model = new VslGoodsSpecModel();
                    $spec_value = $goods_spec_model::all(['spec_id' => ['IN', $list['data'][$k]['spec_id_array']]]);
                    foreach ($spec_value as $a => $n) {
                        $value_stra = $value_stra . ',' . $n['spec_name'];
                    }
                    unset($n);
                    $value_stra = mb_substr($value_stra, 1);
                    $list['data'][$k]['spec_value'] = $value_stra;
                }
                $categoryList = $categoryModel->getQuery(['attr_id' => $v['attr_id'], 'website_id' => $this->website_id], 'category_id', 'sort asc');
                if ($categoryList) {
                    foreach ($categoryList as $ck => $cv) {
                        $categoryList[$ck]['category_names'] = $caegoryServer->getCategoryNameLine($cv['category_id']);
                    }
                    unset($cv);
                }
                $list['data'][$k]['categorys'] = $categoryList;
            }
            unset($v);
        }
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::addGoodsAttributeService()
     */
    public function addAttributeService($attr_name, $is_use, $spec_id_array, $sort, $value_string, $brand_id_array = '', $cate_obj_arr = [])
    {
        $attribute = new VslAttributeModel();
        $attribute->startTrans();
        try {
            $data = array(
                "attr_name" => $attr_name,
                "is_use" => $is_use,
                "website_id" => $this->website_id,
                "spec_id_array" => $spec_id_array ?: '',
                "sort" => $sort,
                "create_time" => time(),
                "brand_id_array" => $brand_id_array ?: ''
            );
            $attribute->save($data);
            $attr_id = $attribute->attr_id;
            $checkArray = [];
            if (!empty($value_string)) {
                $value_array = explode(';', $value_string);
                foreach ($value_array as $k => $v) {
                    $new_array = array();
                    $new_array = explode('|', $v);
                    if (in_array($new_array[0], $checkArray)) {
                        return -10017;
                    }
                    $checkArray[] = $new_array[0];
                    $this->addAttributeValueService($attr_id, $new_array[0], $new_array[1], $new_array[2], $new_array[3], $new_array[4]);
                }
                unset($v);
            }
            if ($cate_obj_arr) {
                foreach ($cate_obj_arr as $val) {
                    $goodsCategory = new VslGoodsCategoryModel();
                    $goodsCategory->save(['attr_id' => $attr_id, 'attr_name' => $attr_name], ['category_id' => $val]);
                }
                unset($val);
            }
            $attribute->commit();
            $data['attr_id'] = $attr_id;
            hook("goodsAttributeSaveSuccess", $data);
            return $attr_id;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $attribute->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::updateAttributeService()
     */
    public function updateAttributeService($attr_id, $attr_name, $is_use, $spec_id_array, $sort, $value_string)
    {
        $attribute = new VslAttributeModel();
        $attribute->startTrans();
        try {
            //删除旧的数据
            Db::query("delete from `vsl_attribute_value` where attr_id = " . $attr_id);
            $data = array(
                "attr_name" => $attr_name,
                "is_use" => $is_use,
                "spec_id_array" => $spec_id_array,
                "sort" => $sort,
                "modify_time" => time()
            );
            $res = $attribute->save($data, [
                'attr_id' => $attr_id
            ]);
            if (!empty($value_string)) {
                $value_array = explode(';', $value_string);
                foreach ($value_array as $k => $v) {
                    $new_array = array();
                    $new_array = explode('|', $v);
                    $this->addAttributeValueService($attr_id, $new_array[0], $new_array[1], $new_array[2], $new_array[3], $new_array[4]);
                }
                unset($v);
            }
            $attribute->commit();
            $data['attr_id'] = $attr_id;
            hook("goodsAttributeSaveSuccess", $data);
            return $res;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $attribute->rollback();
            return $e->getMessage();
        }
    }


    //B端修改属性
    public function updateAttributeServicePlatfom($attr_id, $attr_name, $is_use, $spec_id_array, $sort, $value_string, $brand_id_array = '', $cate_obj_arr = [])
    {
        $attribute = new VslAttributeModel();
        $attribute->startTrans();
        try {
            $data = array(
                "attr_name" => $attr_name,
                "is_use" => $is_use,
                "spec_id_array" => $spec_id_array,
                "sort" => $sort,
                "modify_time" => time(),
                "brand_id_array" => $brand_id_array
            );
            $res = $attribute->save($data, [
                'attr_id' => $attr_id
            ]);
            //删除没有提交过来的ID
            $attribute_value = new VslAttributeValueModel();
            $condition['attr_id'] = $attr_id;
            $value_list = $attribute_value->getQuery($condition, 'attr_value_id', '');
            //变成一维数组
            $attr_value_list = array();
            foreach ($value_list as $k => $v) {
                $attr_value_list[] = $v['attr_value_id'];
            }
            unset($v);
            $goods_attr = array();
            $checkArray = [];

            if (!empty($value_string)) {
                $value_array = explode(';', $value_string);
                foreach ($value_array as $k => $v) {
                    $new_array = array();
                    $new_array = explode('|', $v);
                    $new_array[5] = (int)$new_array[5];
                    if (in_array($new_array[0], $checkArray)) {
                        return -10017;
                    }
                    $checkArray[] = $new_array[0];
                    if (!empty($new_array[5])) {
                        $goods_attr[] = $new_array[5];
                        $this->addAttributeValueService($attr_id, $new_array[0], $new_array[1], $new_array[2], $new_array[3], $new_array[4], $new_array[5]);
                    } else {
                        $this->addAttributeValueService($attr_id, $new_array[0], $new_array[1], $new_array[2], $new_array[3], $new_array[4]);
                    }

                }
                unset($v);
            }

            //循环数据库的attr_value和传过来的，多出来的则删除
            foreach ($attr_value_list as $k => $v) {
                if (!in_array($v, $goods_attr)) {
                    $condition['attr_value_id'] = $v;
                    $attribute_value->delData($condition);
                }
            }
            unset($v);
            if (!in_array($new_array[5], $attr_value_list) && !empty($new_array[5])) {
                $attribute_value->delData(['attr_value_id' => $new_array[5]]);
            }
            $goodsCategory = new VslGoodsCategoryModel();
            $goodsCategory->save(['attr_id' => 0, 'attr_name' => ''], ['attr_id' => $attr_id]);
            if ($cate_obj_arr) {
                foreach ($cate_obj_arr as $val) {
                    $goodsCategory = new VslGoodsCategoryModel();
                    $goodsCategory->isUpdate(true)->save(['attr_id' => $attr_id, 'attr_name' => $attr_name], ['category_id' => $val]);
                }
                unset($val);
            }
            $attribute->commit();
            $data['attr_id'] = $attr_id;
            hook("goodsAttributeSaveSuccess", $data);
            return $res;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $attribute->rollback();
            return $e->getMessage();
        }
    }


    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::addAttributeValueService()
     */
    public function addAttributeValueService($attr_id, $attr_value_name, $type, $sort, $is_search, $value, $attr_value_id = '')
    {

        $attribute_value = new VslAttributeValueModel();
        $data = array(
            'attr_id' => $attr_id,
            'attr_value_name' => $attr_value_name,
            'type' => $type,
            'sort' => $sort,
            'is_search' => $is_search,
            'value' => $value,
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'supplier_id' => $this->supplier_id,
        );
        if (empty($attr_value_id)) {
            $attribute_value->save($data);
            return $attribute_value->attr_value_id;
        } else {
            $condition['attr_value_id'] = $attr_value_id;
            $attribute_value->save($data, $condition);
            $goodsAttr = new VslGoodsAttributeModel();
            $goodsAttr->save(['attr_value' => $attr_value_name], $condition);
            return $attr_value_id;

        }
    }

    /*
     * 添加属性值
     */
    public function addAttributeValueName($attr_value_id, $attr_value_name)
    {
        $attributeValue = new VslAttributeValueModel();
        $checkAttr = $attributeValue->getInfo(['attr_value_id' => $attr_value_id], 'attr_value_id,value');
        if (!$checkAttr) {
            return 0;
        }
        $attrValue = $checkAttr['value'];
        if (!$attrValue) {
            $attrValue = $attr_value_name;//没有属性
        } else {
            $arrValue = explode(',', $attrValue);
            if (in_array($attr_value_name, $arrValue)) {
                return QUESTION_NAME_REPEAT;
            }
            array_push($arrValue, $attr_value_name);//已有属性
            $attrValue = implode(',', $arrValue);
        }
        $res = $attributeValue->isUpdate(true)->save(['value' => $attrValue], ['attr_value_id' => $checkAttr['attr_value_id']]);
        return $res;
    }


    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::getAttributeServiceDetail()
     */
    public function getAttributeServiceDetail($attr_id, $condition = [])
    {
        $attribute = new VslAttributeModel();
        $info = $attribute->get($attr_id);
        $array = Array();
        if (!empty($info)) {
            $condition['attr_id'] = $attr_id;
            $condition['website_id'] = $this->website_id;
            $condition['shop_id'] = array(['=', 0], ['=', $this->instance_id], 'or');
            if ($this->supplier_id){
                $condition[] = ['exp','supplier_id ='.$this->supplier_id.' OR (supplier_id=0 AND shop_id=0)'];
            }
            $array = $this->getAttributeValueServiceList(1, 0, $condition, 'sort');
            $info['value_list'] = $array;
        } else {
            $condition['attr_id'] = $attr_id;
            $condition['website_id'] = $this->website_id;
            $condition['shop_id'] = array(['=', 0], ['=', $this->instance_id], 'or');
            if ($this->supplier_id){
                $condition[] = ['exp','supplier_id ='.$this->supplier_id.' OR (supplier_id=0 AND shop_id=0)'];
            }
            $array = $this->getAttributeValueServiceList(1, 0, $condition, 'sort');
            $info['value_list'] = $array;
        }
        return $info;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::getAttributeValueServiceList()
     */
    public function getAttributeValueServiceList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $attribute_value = new VslAttributeValueModel();
        return $attribute_value->pageQuery($page_index, $page_size, $condition, $order, $field);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::deleteAttributeService()
     */
    public function deleteAttributeService($attr_id)
    {
        $attribute = new VslAttributeModel();
        $attribute_value = new VslAttributeValueModel();
        $res = $attribute->destroy($attr_id);
        $attribute_value->destroy([
            'attr_id' => $attr_id
        ]);
        hook("goodsAttributeDeleteSuccess", [
            'attr_id' => $attr_id
        ]);
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::deleteAttributeValueService()
     */
    public function deleteAttributeValueService($attr_id, $attr_value_id)
    {
        $attribute_value = new VslAttributeValueModel();
        // 检测类型属性数量
        $result = $this->getGoodsAttrValueCount([
            'attr_id' => $attr_id
        ]);
        if ($result == 1) {
            return -2;
        } else {
            return $attribute_value->destroy($attr_value_id);
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::getGoodsAttrValueCount()
     */
    public function getGoodsAttrValueCount($condition)
    {
        $attr_value = new VslAttributeValueModel();
        $count = $attr_value->where($condition)->count();
        return $count;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::modifyAttributeValueService()
     */
    public function modifyAttributeValueService($attr_value_id, $field_name, $field_value)
    {
        $attribute_value = new VslAttributeValueModel();
        return $attribute_value->save([
            "$field_name" => $field_value
        ], [
            'attr_value_id' => $attr_value_id
        ]);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::modifyAttributeFieldService()
     */
    public function modifyAttributeFieldService($attr_id, $field_name, $field_value)
    {
        $attribute = new VslAttributeModel();
        return $attribute->save([
            "$field_name" => $field_value
        ], [
            'attr_id' => $attr_id
        ]);
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IGoods::getAttributeInfo()
     */
    public function getAttributeInfo($condition)
    {
        // TODO Auto-generated method stub
        $attribute = new VslAttributeModel();
        $info = $attribute->getInfo($condition, "*");
        return $info;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IGoods::getGoodsSpecQuery()
     */
    public function getGoodsSpecQuery($condition)
    {
        // TODO Auto-generated method stub
        $goods_spec = new VslGoodsSpecModel();
        $album = new Album();
        //TODO... 供应商
        if ($this->instance_id == 0) {
            $condition['shop_id'] = $this->instance_id;
        } else if($this->supplier_id){
            $condition[] = ['exp','supplier_id ='.$this->supplier_id.' OR (supplier_id=0 AND shop_id=0)'];
        } else {
            $condition['shop_id'] = array(['=', 0], ['=', $this->instance_id], 'or');
        }
        $goods_spec_query = $goods_spec->getQuery($condition, "*", 'sort DESC');
        $goods_spec_value = new VslGoodsSpecValueModel();
        foreach ($goods_spec_query as $k => $v) {
            $condition_spec = ["spec_id" => $v["spec_id"], "website_id" => $this->website_id];
            if ($this->instance_id == 0) {
                $condition_spec['shop_id'] = $this->instance_id;
            } else if($this->supplier_id){
                $condition_spec[] = ['exp','supplier_id ='.$this->supplier_id.' OR (supplier_id=0 AND shop_id=0)'];
            } else {
                $condition_spec['shop_id'] = array(['=', 0], ['=', $this->instance_id], 'or');
            }
            $goods_spec_value_query = $goods_spec_value->getQuery($condition_spec, "*", 'sort asc');
            foreach ($goods_spec_value_query as $key => $val) {
                $goods_spec_value_query[$key]['pic'] = '';
                if ($v['show_type'] == '3' && $val['spec_value_data']) {
                    $pic = $album->getAlubmPictureDetail([
                        "pic_id" => $val["spec_value_data"]
                    ]);
                    $goods_spec_value_query[$key]['pic'] = __IMG($pic['pic_cover_micro']);
                }

            }
            unset($val);
            $goods_spec_query[$k]["values"] = $goods_spec_value_query;
        }
        unset($v);
        return $goods_spec_query;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IGoods::getGoodsAttrSpecQuery()
     */
    public function getGoodsAttrSpecQuery($condition)
    {
        $brand = new VslGoodsBrandModel();
        // TODO Auto-generated method stub
        if ($condition["attr_id"] == 0) {
            $spec_list = $this->getGoodsSpecQuery(['is_visible' => 1, 'website_id' => $this->website_id, 'goods_attr_id' => [['=', 0], ['=', ' '], 'or']]);
        } else {
            $goods_attribute = $this->getAttributeInfo($condition);
            $condition_spec["spec_id"] = array(
                "in",
                $goods_attribute['spec_id_array']
            );
            $condition_spec["is_visible"] = 1;
            $condition_spec["website_id"] = $this->website_id;
            $spec_list = $this->getGoodsSpecQuery($condition_spec); // 商品规格

        }
        $brand_list = $brand->getQuery(['website_id' => $this->website_id, 'brand_id' => ['in', $goods_attribute['brand_id_array']]], '', '');
        $attribute_detail = $this->getAttributeServiceDetail($condition["attr_id"], [
            'is_search' => 1
        ]);
        $attribute_list = $attribute_detail['value_list']['data'];

        foreach ($attribute_list as $k => $v) {
            $value_items = explode(",", $v['value']);
            $attribute_list[$k]['value_items'] = $value_items;
        }
        unset($v);
        $list["spec_list"] = $spec_list; // 商品规格集合
        $list["attribute_list"] = $attribute_list; // 商品属性集合
        $list['brand_list'] = $brand_list ?: [];
        return $list;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IGoods::getGoodsAttributeQuery()
     */
    public function getGoodsAttributeQuery($condition)
    {
        // TODO Auto-generated method stub
        $goods_attribute = new VslGoodsAttributeModel();
        $query = $goods_attribute->getQuery($condition, "*", "");
        return $query;
    }

    /**
     * 回收商品的分页查询
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::getGoodsDeletedList()
     */
    public function getGoodsDeletedList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        // 针对商品分类
        if (!empty($condition['ng.category_id'])) {
            $goods_category = new GoodsCategory();
            $category_list = $goods_category->getCategoryTreeList($condition['ng.category_id']);
            $condition['ng.category_id'] = array(
                'in',
                $category_list
            );
        }
        $goods_view = new VslGoodsDeletedViewModel();

        $list = $goods_view->getGoodsViewList($page_index, $page_size, $condition, $order);
        if (!empty($list['data'])) {
            // 用户针对商品的收藏
            foreach ($list['data'] as $k => $v) {
                if (!empty($this->uid)) {
                    $member = new Member();
                    $list['data'][$k]['is_favorite'] = $member->getIsMemberFavorites($this->uid, $v['goods_id'], 'goods');
                } else {
                    $list['data'][$k]['is_favorite'] = 0;
                }
            }
            unset($v);
        }
        return $list;
    }


    /**
     * 商品恢复
     * (non-PHPdoc)
     *
     * @see \data\api\IGoods::regainGoodsDeleted()
     */
    public function regainGoodsDeleted($goods_ids)
    {
        $goods_array = explode(",", $goods_ids);
        $this->goods->startTrans();
        try {
            foreach ($goods_array as $goods_id) {
                $goods_delete_model = new VslGoodsDeletedModel();
                $goods_delete_obj = $goods_delete_model->getInfo([
                    "goods_id" => $goods_id
                ]);
                $goods_delete_obj = json_decode(json_encode($goods_delete_obj), true);
                $goods_model = new VslGoodsModel();//商品表更新
                $goods_model->save($goods_delete_obj);
                $goods_delete_model->where("goods_id=$goods_id")->delete();
                // sku 恢复
                $goods_sku_delete_model = new VslGoodsSkuDeletedModel();
                $sku_delete_list = $goods_sku_delete_model->getQuery([
                    "goods_id" => $goods_id
                ], "*", "");
                foreach ($sku_delete_list as $sku_obj) {
                    $sku_obj = json_decode(json_encode($sku_obj), true);
                    $sku_model = new VslGoodsSkuModel();
                    $sku_model->save($sku_obj);
                }
                $goods_sku_delete_model->where("goods_id=$goods_id")->delete();
                // 属性恢复
                $goods_attribute_delete_model = new VslGoodsAttributeDeletedModel();
                $attribute_delete_list = $goods_attribute_delete_model->getQuery([
                    "goods_id" => $goods_id
                ], "*", "");
                foreach ($attribute_delete_list as $attribute_delete_obj) {
                    $attribute_delete_obj = json_decode(json_encode($attribute_delete_obj), true);
                    $attribute_model = new VslGoodsAttributeModel();
                    $attribute_model->save($attribute_delete_obj);
                }
                $goods_attribute_delete_model->where("goods_id=$goods_id")->delete();
                // sku图片恢复
                $goods_sku_picture_delete = new VslGoodsSkuPictureDeleteModel();
                $goods_sku_picture_delete_list = $goods_sku_picture_delete->getQuery([
                    'goods_id' => $goods_id
                ], "*", "");
                foreach ($goods_sku_picture_delete_list as $goods_sku_picture_list_delete_obj) {
                    $goods_sku_picture = new VslGoodsSkuPictureModel();
                    $goods_sku_picture_list_delete_obj = json_decode(json_encode($goods_sku_picture_list_delete_obj), true);
                    $goods_sku_picture->save($goods_sku_picture_list_delete_obj);
                }
                $goods_sku_picture_delete->where("goods_id=$goods_id")->delete();

                if(getAddons('friendscircle',$this->website_id)) {
                    //如果有朋友圈素材应用，就判断恢复的商品有没有关联朋友圈素材，有就恢复关联
                    $friendscircle_material_mdl = new VslFriendscircleMaterial();
                    $material_ids = $friendscircle_material_mdl->getQuery(['origin_goods_id' =>$goods_id],'id','');
                    if($material_ids) {
                        foreach ($material_ids as $k => $v) {
                            $friendscircle_material_mdl = new VslFriendscircleMaterial();
                            $friendscircle_material_mdl->isUpdate(true)->save(['goods_id' => $goods_id],['id' => $v['id']]);
                        }
                    }
                }
            }
            unset($goods_id);
            $this->goods->commit();
            foreach ($goods_array as $goods_id) {
                $this->addOrUpdateGoodsToEs($goods_id);
            }
            return SUCCESS;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $this->goods->rollback();
            return UPDATA_FAIL;
        }
    }

    /**
     * 删除回收站商品
     *
     * @param unknown $goods_id
     * @return string
     */
    public function deleteRecycleGoods($goods_id)
    {
        $goods_id = explode(',', $goods_id);
        $goods_list = [];
        if (count($goods_id) > 1) {
            $id = '';
            foreach ($goods_id as $k => $v) {
                //先判断是否是活动商品
                $goods_info = $this->getGoodsDetailById($v);
                $goods_list[] = $goods_info;
                if ($goods_info['promotion_type'] != 0) {
                    return DELETE_FAIL;
                }
                $id .= $v . ',';
            }
            unset($v);
            $id = substr($id, 0, -1);
        } else {
            $id = current($goods_id);
            //先判断是否是活动商品
            $goods_info = $this->getGoodsDetailById($id);
            if ($goods_info){
                $goods_list[] = $goods_info;
                if ($goods_info['promotion_type'] != 0) {
                    return DELETE_FAIL;
                }
            }
        }

        $goods_delete = new VslGoodsDeletedModel();
        $goods_delete->startTrans();
        try {
//            $res = $goods_delete->where("goods_id in ($id) AND shop_id = {$this->instance_id} ")->delete();
            $res = $goods_delete->where("goods_id in ($id) AND shop_id = {$this->instance_id} AND supplier_id={$this->supplier_id}")->delete();
            if ($res > 0) {
                $goods_id_array = $goods_id;
                $goods_sku_model = new VslGoodsSkuDeletedModel();
                $goods_attribute_model = new VslGoodsAttributeDeletedModel();
                $goods_sku_picture_delete = new VslGoodsSkuPictureDeleteModel();
                foreach ($goods_id_array as $k => $v) {
                    // 删除商品sku
                    $goods_sku_model->where("goods_id = $v")->delete();
                    // 删除商品属性
                    $goods_attribute_model->where("goods_id = $v")->delete();
                    // 删除
                    $goods_sku_picture_delete->where("goods_id = $v")->delete();
                }
                unset($v);
                //删除微信卡券
                foreach ($goods_list as $key => $value) {
                    if (!empty($value) && !empty($value['wx_card_id']) && $value['is_wxcard'] == 1) {
                        $weixin_card = new WeixinCard();
                        $weixin_card->cardDelete($value['wx_card_id']);
                    }
                }
                unset($value);
            }
            $goods_delete->commit();
            if ($res > 0) {
                return SUCCESS;
            } else {
                return DELETE_FAIL;
            }
        } catch (\Exception $e) {
            recordErrorLog($e);
            $goods_delete->rollback();
            return DELETE_FAIL;
        }
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IGoods::deleteCookieCart()删除cookie购物车
     */
    private function deleteCookieCart($cart_id_array)
    {
        // TODO Auto-generated method stub
        // 获取删除条件拼装
        $cart_id_array = trim($cart_id_array);
        if (empty($cart_id_array) && $cart_id_array != 0) {
            return 0;
        }
        // 获取购物车
        $cart_goods_list = cookie('cart_array' . $this->website_id);
        if (empty($cart_goods_list)) {
            $cart_goods_list = array();
        } else {
            $cart_goods_list = json_decode($cart_goods_list, true);
        }
        foreach ($cart_goods_list as $k => $v) {
            if (strpos((string)$cart_id_array, (string)$v["cart_id"]) !== false) {
                unset($cart_goods_list[$k]);
            }
        }
        unset($v);
        if (empty($cart_goods_list)) {
            cookie('cart_array' . $this->website_id, null);
            return 1;
        } else {
            sort($cart_goods_list);
            try {
                cookie('cart_array' . $this->website_id, json_encode($cart_goods_list), 3600);
                return 1;
            } catch (\Exception $e) {
                recordErrorLog($e);
                return 0;
            }
        }
    }

    /**
     * 修改cookie购物车的数量
     *
     * @param unknown $cart_id
     * @param unknown $num
     * @return number
     */
    private function updateCookieCartNum($cart_id, $num)
    {
        // 获取购物车
        $cart_goods_list = cookie('cart_array' . $this->website_id);
        if (empty($cart_goods_list)) {
            $cart_goods_list = array();
        } else {
            $cart_goods_list = json_decode($cart_goods_list, true);
        }
        foreach ($cart_goods_list as $k => $v) {
            if ($v["cart_id"] == $cart_id) {
                $cart_goods_list[$k]["num"] = $num;
            }
        }
        unset($v);
        sort($cart_goods_list);
        try {
            cookie('cart_array' . $this->website_id, json_encode($cart_goods_list), 3600);
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            return 0;
        }
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IGoods::syncUserCart()
     */
    public function syncUserCart($uid)
    {
        // TODO Auto-generated method stub
        $cart = new VslCartModel();
        $cart_query = $cart->getQuery([
            "buyer_id" => $uid
        ], '*', '');
        // 获取购物车
        $cart_goods_list = cookie('cart_array' . $this->website_id);
//        $cart_goods_list = '[{"shop_id":0,"goods_id":26,"sku_id":"48","num":4,"goods_picture":107,"seckill_id":33,"cart_id":1}]';
        if (empty($cart_goods_list)) {
            $cart_goods_list = array();
        } else {
            $cart_goods_list = json_decode($cart_goods_list, true);
        }
        $goods_sku = new VslGoodsSkuModel();

        // 遍历cookie购物车
        if (!empty($cart_goods_list)) {
            foreach ($cart_goods_list as $k => $v) {
                // 商品信息
                $goods_info = $this->getGoodsDetailById($v['goods_id']);
                // sku信息
                $sku_info = $goods_sku->getInfo([
                    'sku_id' => $v['sku_id']
                ], 'price, sku_name, promote_price');
                if (empty($goods_info)) {
                    break;
                }
                if (empty($sku_info)) {
                    break;
                }
                // 查看用户会员价
                $goods_preference = new GoodsPreference();
                if (!empty($this->uid)) {
                    $member_model = new VslMemberModel();
                    $member_level_info = $member_model->getInfo(['uid' => $uid])['member_level'];
                    $member_level = new VslMemberLevelModel();
                    $member_info = $member_level->getInfo(['level_id' => $member_level_info]);
                    $member_discount = $member_info['goods_discount'] / 10;
                    $member_is_label = $member_info['is_label'];
                } else {
                    $member_discount = 1;
                }
                //未登录加入秒杀商品进入购物车的情况
                if (!empty($v['seckill_id']) && getAddons('seckill', $this->website_id, $this->instance_id)) {
                    $condition_seckill['sku_id'] = $v['sku_id'];
                    $condition_seckill['seckill_id'] = $v['seckill_id'];
                    $seckill_server = new SeckillServer();
                    $price = $seckill_server->getSeckillSkuInfo($condition_seckill);
                } else {
                    if ($member_is_label) {
                        $member_price = round($member_discount * $sku_info['price']);
                    } else {
                        $member_price = round($member_discount * $sku_info['price'], 2);
                    }
                    if ($member_price > $sku_info["promote_price"]) {
                        $price = $sku_info["promote_price"];
                    } else {
                        $price = $member_price;
                    }
                }

                // 判断此用户有无购物车
                if (empty($cart_query)) {
                    // 获取商品sku信息
                    $this->addCart($uid, $this->instance_id, $v["goods_id"], $goods_info["goods_name"], $v["sku_id"], $sku_info["sku_name"], $price, $v["num"], $goods_info["picture"], 0, $v['seckill_id'], $v['anchor_id']);
                } else {
                    $is_have = true;
                    foreach ($cart_query as $t => $m) {
                        if ($m["sku_id"] == $v["sku_id"] && $m["goods_id"] == $v["goods_id"]) {
                            $is_have = false;
                            $num = $m["num"] + $v["num"];
                            $this->cartAdjustNum($m["cart_id"], $num);
                            break;
                        }
                    }
                    if ($is_have) {
                        $this->addCart($uid, $this->instance_id, $v["goods_id"], $goods_info["goods_name"], $v["sku_id"], $sku_info["sku_name"], $price, $v["num"], $goods_info["picture"], 0, $v['seckill_id'], $v['anchor_id']);
                    }
                }
            }
            unset($v);
        }
        cookie('cart_array' . $this->website_id, null);
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IGoods::addGoodsSkuPicture()
     */
    public function addGoodsSkuPicture($shop_id, $goods_id, $spec_id, $spec_value_id, $sku_img_array)
    {
        // TODO Auto-generated method stub
        $goods_sku_picture = new VslGoodsSkuPictureModel();
        $data = array(
            "shop_id" => $shop_id,
            "goods_id" => $goods_id,
            "spec_id" => $spec_id,
            "spec_value_id" => $spec_value_id,
            "sku_img_array" => $sku_img_array,
            "create_time" => time(),
            "modify_time" => time()
        );
        $retval = $goods_sku_picture->save($data);
        return $retval;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IGoods::deleteGoodsSkuPicture()
     */
    public function deleteGoodsSkuPicture($condition)
    {
        // TODO Auto-generated method stub
        $goods_sku_picture = new VslGoodsSkuPictureModel();
        $retval = $goods_sku_picture->destroy($condition);
        return $retval;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IGoods::getGoodsSkuQuery()
     */
    public function getGoodsSkuQuery($condition)
    {
        // TODO Auto-generated method stub
        $goods_sku_model = new VslGoodsSkuModel();
        $goods_query = $goods_sku_model->getQuery($condition, "goods_id", "");
        return $goods_query;
    }


    /**
     * 修改商品名称或促销语
     */
    public function updateGoodsNameOrIntroduction($goods_id, $up_type, $up_content)
    {
        $condition = array(
            "goods_id" => $goods_id,
            "website_id" => $this->website_id
        );
        if ($up_type == "goods_name") {
			$res = $this->updateGoods($condition, [
                "goods_name" => $up_content
            ]);
            //判断这个商品有没有核销门店，如果有，就要同步到核销门店
            $store_list = $this->getGoodsDetailById($goods_id, 'goods_id,gjp_goods_id,store_list')['store_list'];
            if ($store_list) {
                $store_list = explode(',', $store_list);
                foreach ($store_list as $k => $v) {
                    $store_goods_model = new VslStoreGoodsModel();
                    $store_condition = [
                        "goods_id" => $goods_id,
                        "website_id" => $this->website_id,
                        "store_id" => $v
                    ];
                    $store_goods_model->save(["goods_name" => $up_content], $store_condition);
                }
                unset($v);
            }
            return $res;
        } elseif ($up_type == "price") {
            $goods_sku = new VslGoodsSkuModel();
            $res = $goods_sku->save([
                "price" => $up_content
            ], ['goods_id' => $goods_id]);
            if (!$res) {
                return -1;
            }
			return $this->updateGoods($condition, [
                "price" => $up_content
            ]);
        } elseif ($up_type == "market_price") {
            $goods_sku = new VslGoodsSkuModel();
            $res = $goods_sku->save([
                "market_price" => $up_content
            ], ['goods_id' => $goods_id]);
            if (!$res) {
                return -1;
            }
            return $this->updateGoods($condition, [
                "market_price" => $up_content
            ]);
        } elseif ($up_type == "stock") {
            $goods_sku = new VslGoodsSkuModel();
            $res = $goods_sku->save([
                "stock" => $up_content
            ], ['goods_id' => $goods_id]);
            if (!$res) {
                return -1;
            }
            return $this->updateGoods($condition, [
                "stock" => $up_content
            ]);
        } elseif ($up_type == 'short_name') {
            return $this->updateGoods($condition, [
                'short_name' => $up_content
            ]);
        }

    }

    /*
     * 获取商品归属平台还是店铺
     * [param] str $goods_id 商品id
     * return str 0-店铺 1-平台
     * **/
    public function getGoodsType($goods_id)
    {
        $goods_type_list = $this->getGoodsDetailById($goods_id);
        $shop_id = $goods_type_list['shop_id'];
        if ($shop_id === 0) {
            return 1;
        } else {
            return 0;
        }
    }

    /*
     * 获取店铺id
     * **/
    public function getSkuShopId($condition)
    {
        $goods_mdl = new VslGoodsModel();//联表查询sku
        $goods_sku_info = $goods_mdl->alias('g')->join('vsl_goods_sku gs', 'g.goods_id = gs.goods_id', 'left')->where($condition)->find();
        return $goods_sku_info;
    }

    /*
     * 获取商品sku最大购买量
     * **/
    public function getActivityOrderSku($uid, $sku_id, $website_id, $seckill_id)
    {
        $redis = connectRedis();
        $user_buy_sku_num_key = 'buy_' . $seckill_id . '_' . $uid . '_' . $sku_id . '_num';
        $buy_num = $redis->get($user_buy_sku_num_key);
        if (!$buy_num) {
            $activity_os_mdl = new VslActivityOrderSkuRecordModel();
            $activity_os_info = $activity_os_mdl->where(['uid' => $uid, 'sku_id' => $sku_id, 'website_id' => $website_id, 'buy_type' => 1, 'activity_id' => $seckill_id])->find();
//                    echo $activity_os_mdl->getLastSql();exit;
            $buy_num = $activity_os_info['num'];
            $redis->set($user_buy_sku_num_key, $buy_num);
        }
        return $buy_num;
    }

    /*
     * 拼团获取商品sku最大购买量
     * **/
    public function getActivityOrderSkuForGroup($uid, $sku_id, $website_id, $group_id, $buy_type=2)
    {
        $activity_os_mdl = new VslActivityOrderSkuRecordModel();
        $buy_num = $activity_os_mdl->getSum(['uid' => $uid, 'sku_id' => $sku_id, 'website_id' => $website_id, 'buy_type' => $buy_type, 'activity_id' => $group_id], 'num');
        return $buy_num;
    }

    /*
     * 活动获取商品sku最大购买量
     * **/
    public function getActivityOrderSkuNum($uid, $sku_id, $website_id, $buy_type, $activity_id)
    {
        $activity_os_mdl = new VslActivityOrderSkuRecordModel();
        $activity_os_info = $activity_os_mdl->where(['uid' => $uid, 'sku_id' => $sku_id, 'website_id' => $website_id, 'buy_type' => $buy_type, 'activity_id' => $activity_id])->find();
        $buy_num = $activity_os_info['num'];
        return $buy_num;
    }

    /*
     * 根据sku_id获取库存
     * **/
    public function getSkuBySkuid($sku_id,$field="*")
    {
        $goodsSkuModel = new VslGoodsSkuModel();
        return $goodsSkuModel->getInfo(['sku_id' => $sku_id], $field);
    }

    /**
     * 获取运费模板在goods和goods 回收站的数目
     * @param array $condition
     * @return int
     */
    public function freightTemplateCount(array $condition)
    {
        $goods_count = $this->getGoodsCount($condition);
        $goods_delete_model = new VslGoodsDeletedModel();
        $goods_delete_count = $goods_delete_model->where($condition)->count();
        return $goods_count + $goods_delete_count;
    }

    /*
     * 获取模态框的商品列表
     * **/
    public function getModalGoodsList($index, $condition, $seckill_date = '')
    {
        $list = $this->getgoodslist($index, PAGESIZE, $condition);
//        p($list);exit;
//        unset($list['data'][3]);
        if (!empty($list['data'])) {
            //处理删除第一个是空sku，第二个为有sku的情况
            foreach ($list['data'] as $k => $v) {
                if (!empty($v['sku_list'][0]['attr_value_items']) || !empty($v['sku_list'][1]['attr_value_items'])) {
                    unset($list['data'][$k]['sku_list'][0]);
                }
                if (!empty($list['data'][$k])) {
                    $goods_list[$k]['goods_id'] = $v['goods_id'];
                }
            }
            unset($v);
            //删除多余的字段
            $sku_list = [];
            if (!empty($list['data'])) {
                foreach ($list['data'] as $k => $v) {
                    $goods_spec_format = $v['goods_spec_format'];
                    $goods_spec_arr = json_decode($goods_spec_format, true);
                    $goods_list[$k]['goods_id'] = $v['goods_id'];
                    $goods_list[$k]['goods_name'] = $v['goods_name'];
                    $goods_list[$k]['price'] = $v['price'];
                    $goods_list[$k]['market_price'] = $v['market_price'];
                    if ($v['promotion_type'] == 1) {//如果是秒杀类型的商品，判断24小时内是否有该活动商品
                        if ($seckill_date) {
                            $seckill_server = new SeckillServer();
                            $is_add_seckill = $seckill_server->IsSeckillInTwentyFour($v['goods_id'], $seckill_date);
                            if ($is_add_seckill) {
                                $goods_list[$k]['promotion_type'] = $v['promotion_type'];
                                $goods_list[$k]['promotion_name'] = $this->getGoodsPromotionType($v['promotion_type']);
                            }
                        } else {
                            $goods_list[$k]['promotion_type'] = $v['promotion_type'];
                            $goods_list[$k]['promotion_name'] = $this->getGoodsPromotionType($v['promotion_type']);
                        }
                    } else {
                        $goods_list[$k]['promotion_type'] = $v['promotion_type'];
                        $goods_list[$k]['promotion_name'] = $this->getGoodsPromotionType($v['promotion_type']);
                    }
                    //处理skulist对象
                    $v['sku_list'][0]['attr_value_items'] = trim($v['sku_list'][0]['attr_value_items']);
                    if (!empty($v['sku_list'][0]['attr_value_items'])) {
                        foreach ($v['sku_list'] as $sku_key => $sku_value) {
                            $sku_val_item = $sku_value['attr_value_items'];
                            $sku_val_arr = explode(';', $sku_val_item);
                            $th_name_str = '';
                            $show_value_str = '';
                            $show_type_str = '';
                            foreach ($sku_val_arr as $sku_val_key => $sku_val_value) {
                                $sku_val_value_arr = explode(':', $sku_val_value);
                                //按照规格规则中的顺序定义tr头
                                $sku_tr_id = $sku_val_value_arr[1];
                                //这里屏蔽掉是因为 如果规格删掉了，则从规格表里面取不到规格值了，导致商品报错。
                                /*$val_type = $this->getGoodSku(['spec_value_id' => $sku_tr_id]);
								$val_type_arr = $val_type[0]->toArray();
								p($val_type_arr);exit;*/
                                $val_type_arr = [];
                                foreach ($goods_spec_arr as $k0 => $v0) {
                                    foreach ($v0['value'] as $k01 => $v01) {
                                        if ($v01['spec_value_id'] == $sku_tr_id) {
                                            $val_type_arr['goods_spec']['show_type'] = $v01['spec_show_type'];
                                            $val_type_arr['goods_spec']['spec_name'] = $v01['spec_name'];
                                            $val_type_arr['spec_value_name'] = $v01['spec_value_name'];
                                        }
                                    }
                                }
                                $show_type = $val_type_arr['goods_spec']['show_type'];
                                //根据show_type，获取规格的值，如图片的路径
                                if ($show_type == '3') {//图片
                                    $val_type_str = $val_type_arr['spec_value_name'];//暂时展示中文。
                                } else if ($show_type == '2') {//颜色
                                    $val_type_str = $val_type_arr['spec_value_name'];
                                } else {
                                    $val_type_str = $val_type_arr['spec_value_name'];
                                }
                                //拼接所有规格展示类型对应的值
                                $show_value_str .= $val_type_str . '&*#';
                                //拼接th的名字
                                $th_name_str .= trim($val_type_arr['goods_spec']['spec_name']) . '§';
                                //拼接展示类型
                                $show_type_str .= $show_type . ' ';
                            }
                            $th_name_str = trim($th_name_str);
                            $show_type_str = trim($show_type_str);
                            $show_value_str = trim($show_value_str, '&*#');
                            // var_dump($show_value_str);
                            $sku_list = $sku_value->toArray();
                            //处理sku的id对应value
                            $sku_id_str = $sku_list['attr_value_items'];
                            $sku_id_str_arr = explode(';', $sku_id_str);
                            $sku_value_str = trim($show_value_str);
                            $sku_value_str_arr = explode('&*#', $sku_value_str);
                            $im_str = '';
                            $new_im_str = '';
                            for ($i = 0; $i < count($sku_value_str_arr); $i++) {
                                $im_str .= $sku_id_str_arr[$i] . ';';
                                $im_str = trim($im_str, ';');
                                $new_im_str .= $im_str . '=' . $sku_value_str_arr[$i] . '&*#';
                            }
                            $new_im_str = trim($new_im_str, '&*#');
                            $new_im_str = str_replace('&*#', '§', $new_im_str);
                            $v['sku_list'][$sku_key]['new_im_str'] = $new_im_str;
                            $v['sku_list'][$sku_key]['th_name_str'] = $th_name_str;
                            $v['sku_list'][$sku_key]['show_type_str'] = $show_type_str;
//                            if($k == 3){
////                                p($v['sku_list']);exit;
////                            }
                        }
                        /*************************当sku规格错乱的时候排序****************************/
                        $temp = [];
                        foreach ($v['sku_list'] as $k1 => $sort_sku) {
                            $sort_arr = explode('§', $sort_sku['new_im_str']);
                            $sort_str = $sort_arr[0];
                            $temp[$sort_str][$k1] = $sort_sku;
                        }
                        $i = 0;
                        $sku_temp = [];
                        foreach ($temp as $k2 => $r) {
                            foreach ($r as $last_val) {
                                $sku_temp[$i] = $last_val;
                                $i++;
                            }
                        }
                        $v['sku_list'] = $sku_temp;
                    } else {
                        $v['sku_list'] = $v['sku_list'][0];
                    }

                    $goods_list[$k]['shop_name'] = $v['shop_name'] ?: $this->mall_name;
                    $goods_list[$k]['pic_cover'] = getApiSrc($v['pic_cover']);
                    $goods_list[$k]['sku_list'] = $v['sku_list'];
                }
                unset($v);
            }
        } else {
            $goods_list = [];
        }

        //处理sku字符串
        if (!empty($goods_list)) {
            foreach ($goods_list as $sku_key2 => $sku_value2) {
                $goods_list[$sku_key2]['sku_list'] = json_encode($sku_value2['sku_list'], JSON_UNESCAPED_UNICODE);
            }
            unset($sku_value2);
            $list['data'] = $goods_list;
        } else {
            $list['data'] = '';
            $list['page_count'] = 0;
            $list['total_count'] = 0;
        }
        return $list;
    }

    public function goodsAttribute(array $condition, array $with = [])
    {
        $goods_attribute_model = new VslGoodsAttributeModel();
        $list = $goods_attribute_model::all($condition, $with);
        $return_data = [];
        foreach ($list as $v) {
            if (!$v->attr_value_name || !$v->attr_value) {
                continue;
            }
            $temp['attr_value'] = $v->attr_value;
            $temp['attr_value_name'] = htmlspecialchars_decode(htmlspecialchars_decode($v->attr_value_name));
            $temp['attr_value_id'] = $v->attr_value_id;
            $temp['sort'] = $v->sort;
            $return_data[] = $temp;
        }
        unset($v);
        return $return_data;
    }

    /**
     * 获取商品活动类型
     *
     * @param unknown $type_id
     */
    public function getGoodsPromotionType($type_id)
    {
        if (!$type_id) {
            return '';
        }
        $order_type = array(
            array(
                'type_id' => '1',
                'type_name' => '秒杀活动'
            ),
            array(
                'type_id' => '2',
                'type_name' => '拼团活动'
            ),
            array(
                'type_id' => '3',
                'type_name' => '预售活动'
            ),
            array(
                'type_id' => '4',
                'type_name' => '砍价活动'
            ),
            array(
                'type_id' => '5',
                'type_name' => '限时折扣'
            )
        );
        $type_name = '';
        foreach ($order_type as $k => $v) {
            if ($v['type_id'] == $type_id) {
                $type_name = $v['type_name'];
            }
        }
        unset($v);
        return $type_name;
    }

    /**
     * 违规下架
     */
    public function ModifyGoodsOutline($condition)
    {
        $goods_ids = $condition['goods_ids'];
        if (!$goods_ids) {
            return UPDATA_FAIL;
        }
        $data = array(
            "state" => 10,
            'update_time' => time(),
            'illegal_reason' => $condition['reason'],
        );
		$result = $this->updateGoods(['goods_id' => ['in',$goods_ids]], $data);
        if ($result > 0) {
            if($condition['is_supplier']) {
                //如果违规下架的是供应商商品，那么店铺上架了该商品都要变成下架
                $goods_ids = explode(',', $goods_ids);
                foreach ($goods_ids as $k => $v) {
                    $goods_info = $this->getGoodsDetailById($v);//供应商商品信息

                    if ($goods_info['shop_list'] !==''&& $goods_info['shop_list'] !== null){
                        $shop_ids = explode(',', $goods_info['shop_list']);
                        $shop_ids = array_unique($shop_ids);
                        foreach ($shop_ids as $shop_id){
                            unset($condition);
                            $condition = [
                                'website_id' => $this->website_id,
                                'shop_id'   => $shop_id,
                                'supplier_id' => 0,
                                'supplier_goods_id' => $v,
                            ];
                            $this->updateGoods($condition, ['state' => 10,'supplier_operation' => 2]);
                        }
                    }

                }
                unset($v);
            }
            return SUCCESS;
        } else {
            return UPDATA_FAIL;
        }
    }

    /**
     * 商品審核
     */
    public function ModifyGoodsAudit($condition)
    {
        $goods_ids = $condition['goods_ids'];
        if (!$goods_ids) {
            return UPDATA_FAIL;
        }
        $data = array(
            "state" => $condition['state'],
            'update_time' => time(),
            'illegal_reason' => $condition['reason'],
        );
        $result = $this->updateGoods(['goods_id' => ['in',$goods_ids]], $data);
        if ($result > 0) {
            if($condition['state'] == 1) {
                //如果审核的是供应商商品，那么店铺上架了该商品都要把supplier_operation置空
                $goods_ids = explode(',', $goods_ids);
                foreach ($goods_ids as $k => $v) {
                    $goods_info = $this->getGoodsDetailById($v);
                    if ($goods_info['shop_id'] == -1 && $goods_info['supplier_id']) {
                        $supplier_goods_ids = $this->goods->Query(['supplier_goods_id' => $v], 'goods_id');
                        if ($supplier_goods_ids) {
                            foreach ($supplier_goods_ids as $k1 => $v1) {
                                $this->updateGoods(['goods_id' => $v1], ['supplier_operation' => 0]);
                            }
                            unset($v1);
                        }
                    }
                }
                unset($v);
            }
            return SUCCESS;
        } else {
            return UPDATA_FAIL;
        }
    }

    /**
     * 修改标签
     */
    public function editLabel($goods_id, $label)
    {
        $goods_mdl = new VslGoodsModel();//商品表更新
        $value = $goods_mdl->where(['goods_id' => $goods_id])->value($label);
        $data = [];
        $data[$label] = ($value == 1) ? 0 : 1;
        $res = $this->updateGoods(['goods_id' => $goods_id], $data, $goods_id);
        return $res;
    }

    /**
     * 上传图片到云
     */
    public function modifyImageUrl2AliOss($url = '')
    {
        // 是本域名的就不要上传云
        $domain = parse_url($url);
        $domain_name = $domain['scheme'] . '://' . $domain['host'];//https://www.baidu.com
        if ($domain_name == Request::instance()->domain()) {
            return $url;
        }
        // 先下载本地，再上传云
        $ext = substr(strrchr($url, '.'), 1);
        $ext_name = basename($url, "." . $ext);//不带后缀文件名
        $image = https_request($url);
        $path = 'upload/network/' . $this->website_id . '/';//云存储的地址

        // 上传云
        return getImageFromYun($image, $path, $ext_name);
    }

    /**压缩图片并转base64
     * @param string $url 图片url
     * @return string [base64]
     */
    public function thumbAndTransBase64Code($url = '')
    {
        if (empty($url)) {
            return;
        }
        // 存本地并压缩
        $image = https_request($url);
        $file_name = transAndThumbImg($image, 'upload/temp/', 'temp', 600, 300);
        // 图片转base64
        $base64_img = base64EncodeImage($file_name);
        @unlink($file_name);
        return $base64_img;
    }

    /*
     * 品类列表修改属性值
     */
    public function updateAttributeValueService($attr_id, $value_string)
    {
        $attribute_value = new VslAttributeValueModel();
        $condition['attr_id'] = $attr_id;
        $value_list = $attribute_value->getQuery($condition, 'attr_value_id', '');
        //变成一维数组
        $attr_value_list = array();
        foreach ($value_list as $k => $v) {
            $attr_value_list[] = $v['attr_value_id'];
        }
        unset($v);
        $goods_attr = array();
        $checkArray = [];
        if (!empty($value_string)) {
            $value_array = explode(';', $value_string);
            foreach ($value_array as $k => $v) {
                $new_array = array();
                $new_array = explode('|', $v);
                if (in_array($new_array[0], $checkArray)) {
                    return -10017;
                }
                $checkArray[] = $new_array[0];
                if (!empty($new_array[5])) {
                    $goods_attr[] = $new_array[5];
                    $this->addAttributeValueService($attr_id, $new_array[0], $new_array[1], $new_array[2], $new_array[3], $new_array[4], $new_array[5]);
                } else {
                    $this->addAttributeValueService($attr_id, $new_array[0], $new_array[1], $new_array[2], $new_array[3], $new_array[4]);
                }

            }
            unset($v);
        }
        //循环数据库的attr_value和传过来的，多出来的则删除
        foreach ($attr_value_list as $k => $v) {
            if (!in_array($v, $goods_attr)) {
                $condition['attr_value_id'] = $v;
                $attribute_value->delData($condition);
            }
        }
        unset($v);
        if (!in_array($new_array[5], $attr_value_list) && !empty($new_array[5])) {
            $attribute_value->delData(['attr_value_id' => $new_array[5]]);
        }
        return 1;
    }

    /*
     * 品类列表修改关联规格
     */
    public function updateAttributeSpecService($attr_id, $spec_id_array)
    {
        $attribute = new VslAttributeModel();
        $attr = $attribute->getInfo(['attr_id' => $attr_id], ['spec_id_array']);
        if (!$attr) {
            return 0;
        }
        $attribute->startTrans();
        try {
            $data = array(
                "spec_id_array" => $spec_id_array,
                "modify_time" => time()
            );
            $res = $attribute->save($data, [
                'attr_id' => $attr_id
            ]);
            //如果本来有关联规格，检查是否有取消的规格，并从规格表删掉关联的品类
            if ($attr['spec_id_array']) {
                $specIdArr = explode(',', $attr['spec_id_array']);
                $newSpecIdArr = explode(',', $spec_id_array);
                foreach ($specIdArr as $spec_id) {
                    if (!in_array($spec_id, $newSpecIdArr)) {
                        $this->deleteAttrFromSpec($spec_id, $attr_id);
                    }
                }
                unset($spec_id);
            }
            $attribute->commit();
            return $res;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $attribute->rollback();
            return DELETE_FAIL;
        }

    }

    /*
     * 品类列表修改关联规格
     */
    public function updateAttributeBrandService($attr_id, $brand_id_array)
    {
        $attribute = new VslAttributeModel();
        $data = array(
            "brand_id_array" => $brand_id_array,
            "modify_time" => time()
        );
        $res = $attribute->save($data, [
            'attr_id' => $attr_id
        ]);
        return $res;
    }

    /*
     * 品类列表修改关联分类
     */
    public function updateAttributeCateService($attr_id, $attr_name, $cate_obj_arr = [])
    {
        $goodsCategory = new VslGoodsCategoryModel();
        $goodsCategory->save(['attr_id' => 0, 'attr_name' => ''], ['attr_id' => $attr_id]);
        if ($cate_obj_arr) {
            foreach ($cate_obj_arr as $val) {
                $goodsCategory = new VslGoodsCategoryModel();
                $goodsCategory->save(['attr_id' => $attr_id, 'attr_name' => $attr_name], ['category_id' => $val]);
            }
            unset($val);
        }
        return 1;
    }

    /**
     * 是否开启独立折扣的浏览权限
     * @param $goods_id
     */
    public function isIndependentBrowse($goods_id)
    {
        $goodsDiscount = new VslGoodsDiscountModel();
        $condition['goods_id'] = $goods_id;
        $condition['website_id'] = $this->website_id;
        $res = $goodsDiscount->getInfo($condition);
        if (!isset($res['browse_auth_u']) && !isset($res['browse_auth_d']) && !isset($res['browse_auth_s'])) {
            return false;// 未设置
        }

        return true;
    }

    /**
     * 是否开启独立折扣的浏览权限
     * @param $goods_id
     */
    public function isIndependentBuy($goods_id)
    {
        $goodsDiscount = new VslGoodsDiscountModel();
        $condition['goods_id'] = $goods_id;
        $condition['website_id'] = $this->website_id;
        $res = $goodsDiscount->getInfo($condition);
        if (!isset($res['buy_auth_u']) && !isset($res['buy_auth_d']) && !isset($res['buy_auth_s'])) {
            return false;// 未设置
        }
        return true;

    }

    /**
     * 购买该商品权限
     * @param $uid [int] 用户id
     * @param $goods_id [int] 商品id
     */
    public function isAllowToBuyThisGoods($uid, $goods_id)
    {
        $is_set_bug = $this->isIndependentBuy($goods_id);
        if (!$is_set_bug) {
            return true; //该商品未设置就不用判断购买权限了
        }
        $goodsPower = $this->getGoodsPowerDiscount($goods_id);

        if (!isset($goodsPower['buy_auth_u']) && !isset($goodsPower['buy_auth_d']) && !isset($goodsPower['buy_auth_s'])) {
            return true;
        }
        if ($goodsPower['buy_auth_u'] == '' && $goodsPower['buy_auth_d'] == '' && $goodsPower['buy_auth_s'] == '') {
            return true;
        }

        // 用户购买权限
        $userService = new User();
        $userLevle = $userService->getUserLevelAndGroupLevel($uid);//优先级: user_level会员 > distributor_level分销商 > member_group会员标签;
        if (empty($userLevle)) {
            return true;
        }
        if ($userLevle['user_level']) {
            if (!isset($goodsPower['buy_auth_u']) || $goodsPower['buy_auth_u'] == '') {//不设权限表示都可以购买
                return true;
            }
            $id = $userLevle['user_level'];
            if (in_array($id, explode(',', $goodsPower['buy_auth_u']))) {
                return true;
            }
        }
        if (isset($userLevle['user_level']) && $userLevle['user_level'] == 0){
            return true;
        }

        if ($userLevle['distributor_level']) {
            if (!isset($goodsPower['buy_auth_d']) || $goodsPower['buy_auth_d'] == '') {
                return true;
            }
            $id = $userLevle['distributor_level'];
            if (in_array($id, explode(',', $goodsPower['buy_auth_d']))) {
                return true;
            }
        }
        if ($userLevle['member_group']) {
            if (!isset($goodsPower['buy_auth_s']) || $goodsPower['buy_auth_s'] == '') {
                return true;
            }
            $ids = $userLevle['member_group'];// eg: '3,2'
            $ids = explode(',', $ids);
            if ($temp = array_values(array_intersect($ids, explode(',', $goodsPower['buy_auth_s'])))) {//两个数组交集
                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    /**
     * 浏览该商品权限
     * @param $uid
     * @param $goods_id
     */
    public function isAllowToBrowse($uid, $goods_id)
    {
        if (empty($uid)) {
            return true;
        }
        $is_set_browse = $this->isIndependentBrowse($goods_id);
        if (!$is_set_browse) {
            return true; //该商品未设置就不用判断浏览权限了
        }
        // 获取该用户的权限
        $userService = new User();
        $userLevle = $userService->getUserLevelAndGroupLevel($uid);// member_group会员标签; distributor_level分销商; user_level会员
        if (!empty($userLevle)) {
            $level_flag = false;
            $sql1 = '';
            $sql2 = '(';
            // 会员权限
            if ($userLevle['user_level']) {
                $level_flag = true;
                $u_id = $userLevle['user_level'];
                $sql1 .= "instr(CONCAT( ',',browse_auth_u, ',' ), '," . $u_id . ",' ) OR ";
                $sql2 .= "browse_auth_u IS NULL OR browse_auth_u = '' ";
            }
            // 分销商权限
            if ($userLevle['distributor_level']) {
                $level_flag = true;
                $d_id = $userLevle['distributor_level'];
                $sql1 .= "instr(CONCAT( ',', browse_auth_d, ',' ), '," . $d_id . ",' ) OR ";
                $sql2 .= " OR browse_auth_d IS NULL OR browse_auth_d = '' ";
            }
            // 标签权限
            if ($userLevle['member_group']) {
                $level_flag = true;
                $g_ids = explode(',', $userLevle['member_group']);
                foreach ($g_ids as $g_id) {
                    $sql1 .= "instr(CONCAT( ',', browse_auth_s, ',' ), '," . $g_id . ",' ) OR ";
                    $sql2 .= " OR browse_auth_s IS NULL OR browse_auth_s = '' ";
                }
                unset($g_id);
            } else {
                $sql1 .= " ";
            }
            $sql2 .= " )";
            if ($level_flag){
                $condition[] = ['exp', $sql1 . $sql2];
            }
        }

        $condition['goods_id'] = $goods_id;
        $condition['website_id'] = $this->website_id;
        $goodsDiscount = new VslGoodsDiscountModel();
        $goodsDiscountRes = $goodsDiscount->getInfo($condition, 'id');
        if ($goodsDiscountRes) {
            return true;
        }
        return false;
    }

    /**
     * 查询商品拥有的权限折扣
     * @param $goods_id
     */
    public function getGoodsPowerDiscount($goods_id)
    {
        $goodsDiscount = new VslGoodsDiscountModel();
        $condition = [
            'type' => 1,
            'goods_id' => $goods_id,
            'website_id' => $this->website_id,
        ];
        $res = $goodsDiscount->getInfo($condition);
        return $res;
    }

    /**
     * 获取商品会员折扣以及会员独立后的信息
     * @param $goods_id
     * @param $goods_price 商品原价格
     * @return member_price     // 折扣后价格
     * @return member_discount   // 折扣率 例如 1就是没有折扣 0.9折扣90%
     * @return is_show_member_price //是否显示折后价
     * @return member_is_label   // 是否取整
     * @return discount_choice   // 折扣方式选择 1：折扣  2：固定金额（折扣率为1）
     */
    public function getGoodsInfoOfIndependentDiscount($goods_id, $goods_price = '', $goods_detail = [])
    {
        if(!$goods_detail){
            $goods_detail = $this->getGoodsDetailById($goods_id);
        }
        // 店铺的使用会员折扣
        // 自营店  分销商 > 会员独立 > 会员折扣
        if ($goods_price === '') {
            $goods_price = $goods_detail['price'];
        }
        // 查询商品是否有开启会员折扣
        $goodsPower = $this->getGoodsPowerDiscount($goods_id);
        $goods_detail['is_use'] = $goodsPower['is_use']; //是否参与会员折扣 1参与 0不参与
        if ($goods_detail['shop_id'] == 0) {
            if ($goodsPower && $goodsPower['is_use'] == 0) { /*关闭会员折扣，则商品不参与折扣（C端不用折扣）*/
                $goods_detail['is_use'] = 0;
                $goods_detail['member_price'] = $goods_price;
                $goods_detail['member_discount'] = 1;
                $goods_detail['member_is_label'] = 0;
                $goods_detail['is_show_member_price'] = 0;
                //平台总会员折扣
                $goods_detail['platform_member_price'] = 0;
                $goods_detail['shop_member_price'] = 0;

                return $goods_detail;
            } else if ($goodsPower && $goodsPower['is_use'] == 1) {/*独立折扣*/
                $value = json_decode($goodsPower['value'], TRUE);
                $goods_detail['is_show_member_price'] = 1;
                // 查询会员的等级
                $userService = new User();
                $userLevle = $userService->getUserLevelAndGroupLevel($this->uid);//distributor_level分销商; user_level会员
                if ($value['is_distributor_obj_open'] == 1 && $value['distributor_obj']) {/*-- 独立分销商折扣 --*/
                    $id = $userLevle['distributor_level'];
                    $is_label = $value['distributor_obj']['d_is_label'];//是否取整1取，0不取
                    $discount_choice = $value['distributor_obj']['d_discount_choice'];//折扣方式选择
                    if ($discount_choice == 1) {//折扣
                        $member_discount_val = $value['distributor_obj']['d_level_data'][$id]['val'] ?:0;
                        $goods_detail['member_discount'] = $member_discount_val / 10 ?: 1;
                        if ($is_label == 1) {
                            $goods_detail['member_price'] = round($goods_detail['member_discount'] * $goods_price);
                        } else {
                            $goods_detail['member_price'] = round($goods_detail['member_discount'] * $goods_price, 2);
                        }

                        $goods_detail['member_is_label'] = $is_label ?: 0;
                        $goods_detail['discount_choice'] = 1;
                    }
                    if ($discount_choice == 2) {//固定金额
                        $goods_detail['member_price'] = $value['distributor_obj']['d_level_data'][$id]['val'] ?: $goods_price;
                        $goods_detail['member_discount'] = 1;
                        $goods_detail['member_is_label'] = 0;
                        $goods_detail['discount_choice'] = 2;
                    }

                    return $goods_detail;

                } else if ($value['is_user_obj_open'] == 1 && $value['user_obj']) {/* -- 独立会员折扣 --*/
                    $id = $userLevle['user_level']; //分销商取（会员等级）
                    $is_label = $value['user_obj']['u_is_label'];//是否取整1取，0不取
                    $discount_choice = $value['user_obj']['u_discount_choice'];//折扣方式选择
                    if ($discount_choice == 1) {//折扣
                        $member_discount_val = $value['user_obj']['u_level_data'][$id]['val'] ?:0;//折扣
                        $goods_detail['member_discount'] = $member_discount_val / 10 ?: 1;
                        if ($is_label == 1) {
                            $goods_detail['member_price'] = round($goods_detail['member_discount'] * $goods_price);
                        } else {
                            $goods_detail['member_price'] = round($goods_detail['member_discount'] * $goods_price, 2);
                        }
                        $goods_detail['member_is_label'] = $is_label ?: 0;
                        $goods_detail['discount_choice'] = 1;
                    }
                    if ($discount_choice == 2) {//固定金额
                        $goods_detail['member_price'] = $value['user_obj']['u_level_data'][$id]['val'] ?: $goods_price;
                        $goods_detail['member_discount'] = 1;
                        $goods_detail['member_is_label'] = 0;
                        $goods_detail['discount_choice'] = 2;
                    }
                    //平台总会员折扣
                    $goods_detail['platform_member_price'] = 0;
                    $goods_detail['shop_member_price'] = $goods_price - $goods_detail['member_price'];

                    return $goods_detail;
                }
            }
        }

        // 店铺 || 使用会员折扣(非独立)
        $member_model = new VslMemberModel();
        $member_info = $member_model::get($this->uid)->level;
        $goods_detail['discount_choice'] = 1;
        $goods_detail['member_discount'] = $member_info['goods_discount'] / 10 > 0 ? $member_info['goods_discount'] / 10 : 1;
        $goods_detail['member_is_label'] = $member_info['is_label'] ?: 0;
        if ($member_info['is_label'] == 1) {
            $goods_detail['member_price'] = round($goods_detail['member_discount'] * $goods_price);
        } else {
            $goods_detail['member_price'] = round($goods_detail['member_discount'] * $goods_price, 2);
        }
        //平台总会员折扣
        $goods_detail['platform_member_price'] = $goods_price - $goods_detail['member_price'];
        if ($goods_detail['member_discount'] == 1) {
            $goods_detail['is_show_member_price'] = 0;
        } else {
            $goods_detail['is_show_member_price'] = 1;
        }

        return $goods_detail;
    }

    /**
     * 商品浏览、折扣权限保存
     * @param $discount_bonus
     * @param $goods_id
     * @return \multitype
     */
    public function postGoodsPowerDiscount ($discount_bonus,$goods_id)
    {

        // 存入vsl_goods_discount
        $discount_data = [];
        if ($discount_bonus['discount_look_obj']) {// 浏览权限
            $discount_data['browse_auth_u'] = $discount_bonus['discount_look_obj']['member_level_id'] ? implode(',', $discount_bonus['discount_look_obj']['member_level_id']) : 0;
            $discount_data['browse_auth_d'] = $discount_bonus['discount_look_obj']['distributor_level_id'] ? implode(',', $discount_bonus['discount_look_obj']['distributor_level_id']) : 0;
            $discount_data['browse_auth_s'] = $discount_bonus['discount_look_obj']['user_group_level_id'] ? implode(',', $discount_bonus['discount_look_obj']['user_group_level_id']) : 0;
        }

        if ($discount_bonus['discount_buy_obj']) {//购买权限
            $discount_data['buy_auth_u'] = $discount_bonus['discount_buy_obj']['member_level_id2'] ? implode(',', $discount_bonus['discount_buy_obj']['member_level_id2']) : 0;
            $discount_data['buy_auth_d'] = $discount_bonus['discount_buy_obj']['distributor_level_id2'] ? implode(',', $discount_bonus['discount_buy_obj']['distributor_level_id2']) : 0;
            $discount_data['buy_auth_s'] = $discount_bonus['discount_buy_obj']['user_group_level_id2'] ? implode(',', $discount_bonus['discount_buy_obj']['user_group_level_id2']) : 0;
        }

        if ($this->instance_id==0 && $discount_bonus['discount_channel_obj']) {//渠道商权限
            $discount_data['channel_auth'] = $discount_bonus['discount_channel_obj']['channel_level_id'] ? implode(',', $discount_bonus['discount_channel_obj']['channel_level_id']) : 0;
        }

        $discount_data['is_use'] = 0;
        if ($discount_bonus['is_member_discount_open'] == 1) {// 开启会员折扣1开2关
            $discount_data['is_use'] = 1;
        }
        $discount_data['value'] = json_encode($discount_bonus);

        $discount = new VslGoodsDiscountModel();
        $d_condition = [
            'goods_id' => $goods_id,
            'type' => 1,//权限折扣
            'shop_id' => $this->instance_id,
            'website_id' => $this->website_id,
        ];
        $oldDiscoutn = $discount->getInfo($d_condition);
        if ($goods_id > 0 && $oldDiscoutn) {//编辑
            $discount_data['update_time'] = time();
            $discount->save($discount_data, $d_condition);
        } else {//新增
            $discount_data['goods_id'] = $goods_id;
            $discount_data['create_time'] = time();
            $discount_data['type'] = 1;
            $discount_data['shop_id'] = $this->instance_id;
            $discount_data['website_id'] = $this->website_id;
            $discount->save($discount_data);
        }
        unset($d_condition);
        return AjaxReturn(SUCCESS);
    }

    /**
     * 该商品用户目前最大可购买数量
     * @param $goods_id
     * @param int $sku_id
     * @return int|mixed  -1:不能购买/不能增加数量, 0:无限购, 数字表示最大限购数
     */
    public function getGoodsMaxBuyNums($goods_id, $sku_id = 0)
    {
        // 查询商品库存
        $good_sku = new VslGoodsSkuModel();
        $goods = $this->getGoodsDetailById($goods_id);
        if ($sku_id) {
            $sku_condition = [
                'sku_id' => $sku_id
            ];
            $goods_sku = $good_sku->getInfo($sku_condition, 'stock');
            if ($goods_sku) {
                $goods['stock'] = $goods_sku['stock'];
            }
        }
        if ($goods['max_buy'] == 0) {
            return 0;
        }
        return $goods['stock'];// todo... 暂时这样处理，后面修改

    }

    /**
     * 查询商品主图
     * @param $goods_id int [商品id]
     * @param bool $default bool [是否默认取第一个主图]
     * @return array [商品图片]
     */
    public function getGoodsMasterImg($goods_id, $default = false)
    {
        $goods_info = $this->getGoodsDetailById($goods_id, 'goods_id,gjp_goods_id,img_id_array,picture', 1);
        if ($default) {
            // 获取商品所有主图
            $goods_img = new AlbumPictureModel();
            $order = "instr('," . $goods_info['img_id_array'] . ",',CONCAT(',',pic_id,','))"; // 根据 in里边的id 排序
            $goodsImg = $goods_img->getQuery([
                'pic_id' => [
                    "in",
                    $goods_info['img_id_array']
                ]
            ], '*', $order);
        } else {
            $goodsImg[] = $goods_info['album_picture'];
        }
        if ($goodsImg) {
            $baseImg = [];
            foreach ($goodsImg as $key => $pic) {
                $baseImg[$key] = $this->thumbAndTransBase64Code(getApiSrc($pic['pic_cover']));
            }
            unset($pic);
        }

        return $baseImg;
    }

    /*
     * 编辑知识付费商品时，读取付费内容列表
     */
    public function getKnowledgePaymentList($goods_id)
    {
        $knowledge_payment_content_model = new VslKnowledgePaymentContentModel();
        $res = $knowledge_payment_content_model->getQuery(['goods_id' => $goods_id, 'website_id' => $this->website_id, 'shop_id' => $this->instance_id], '*', 'id ASC');
        return $res;
    }

    /*
     * 前端读取付费内容列表
     */
    public function wapGetKnowledgePaymentList($goods_id, $uid)
    {
        if ($uid) {
            //判断当前用户有没有购买过此商品
            $order_goods_model = new VslOrderGoodsModel();
            $order_model = new VslOrderModel();
            $data = [
                'website_id' => $this->website_id,
                'buyer_id' => $uid,
                'goods_id' => $goods_id
            ];
            $order_list = $order_goods_model->getQuery($data, 'order_id','order_id ASC');
            if ($order_list) {
                foreach ($order_list as $k => $v) {
                    $order_status = $order_model->getInfo(['order_id' => $v['order_id']], 'order_status');
                    if($order_status['order_status'] == 4) {
                        $is_buy = true;
                            break;
                    } else {
                        $is_buy = false;
                    }
                }
                unset($v);
            } else {
                $is_buy = false;
            }
        } else {
            $is_buy = false;
        }
        $knowledge_payment_content_model = new VslKnowledgePaymentContentModel();
        $res = $knowledge_payment_content_model->getQuery(['goods_id' => $goods_id, 'website_id' => $this->website_id], '*', 'id ASC');
        foreach ($res as $k => $v) {
            $data = [
                'knowledge_payment_id' => $v['id'],
                'knowledge_payment_name' => $v['name'],
                'knowledge_payment_type' => $v['type'],
                'knowledge_payment_is_see' => $v['is_see'],
            ];
            $konwledge_payment_list[] = $data;
        }
        unset($v);
        return [
            'konwledge_payment_list' => $konwledge_payment_list,
            'is_buy' => $is_buy
        ];
    }

    /**
     * 前台会员试看或者观看知识付费商品内容
     */
    public function seeKnowledgePayment($knowledge_payment_id, $uid)
    {
        $knowledge_payment_content_model = new VslKnowledgePaymentContentModel();
        $order_model = new VslOrderModel();
        $order_goods_model = new VslOrderGoodsModel();
        $goods_id = $knowledge_payment_content_model->getInfo(['id' => $knowledge_payment_id], 'goods_id');

        //判断当前用户有没有购买过此商品
        $data = [
            'website_id' => $this->website_id,
            'buyer_id' => $uid,
            'goods_id' => $goods_id['goods_id']
        ];
        $order_list = $order_goods_model->getQuery($data, 'order_id','order_id ASC');
        if ($order_list) {
            foreach ($order_list as $k => $v) {
                $order_status = $order_model->getInfo(['order_id' => $v['order_id']], 'order_status');
                if($order_status['order_status'] == 4) {
                    $is_buy = true;
                        break;
                } else {
                    $is_buy = false;
                }
            }
            unset($v);
        } else {
            $is_buy = false;
        }

        //查询当前点击的付费内容
        $list = $knowledge_payment_content_model->getInfo(['id' => $knowledge_payment_id], '*');

        //查询商品信息
        $goods_info = $this->getGoodsDetailById($goods_id['goods_id'], 'goods_id,goods_name,sales,real_sales,description,picture', 1);
        $list['goods_picture'] = $goods_info['album_picture']['pic_cover'];
        $list['goods_name'] = $goods_info['goods_name'];
        $list['total_count'] = $knowledge_payment_content_model->getCount(['goods_id' => $goods_id['goods_id']]);
        $list['is_buy'] = $is_buy;
        $list['sales'] = $goods_info['sales'] + $goods_info['real_sales'];
        $list['description'] = str_replace('"//', '"https://', $goods_info['description']);
        return $list;
    }

    /*
     * 会员中心->我的课程
     */
    public function myCourse($search_text, $page_index, $page_size, $uid)
    {
        $order_model = new VslOrderModel();
        $order_goods_model = new VslOrderGoodsModel();
        $albumPictureModel = new AlbumPictureModel();
        $knowledge_payment_content_model = new VslKnowledgePaymentContentModel();

        //查询此用户所有购买过的知识付费商品
        $condition = [
            'website_id' => $this->website_id,
            'buyer_id' => $uid,
            'goods_type' => 4
        ];
        if ($search_text) {
            $condition['goods_name'] = ['LIKE', '%' . $search_text . '%'];
        }

        $order_info = $order_goods_model->pageQuery($page_index, $page_size, $condition, 'order_id DESC', 'order_id,goods_id,goods_name,goods_picture');
        if ($order_info['data']) {
            foreach ($order_info['data'] as $k => $v) {
                $order_status = $order_model->getInfo(['order_id' => $v['order_id']], 'order_status');
                if ($order_status['order_status'] == 4) {
                    $data['goods_id'] = $v['goods_id'];
                    $data['goods_name'] = $v['goods_name'];
                    $data['goods_picture'] = $albumPictureModel->getInfo(['pic_id' => $v['goods_picture']], 'pic_cover')['pic_cover'];
                    $data['total_count'] = $knowledge_payment_content_model->getCount(['goods_id' => $v['goods_id']]);
                    $knowledge_payment_list[] = $data;
                }
            }
            unset($v);
        }

        if($knowledge_payment_list) {
            return [
                'knowledge_payment_list' => $knowledge_payment_list,
                'page_count' => $order_info['page_count'],
                'total_count' => $order_info['total_count'],
            ];
        }else{
            return [
                'knowledge_payment_list' => [],
                'page_count' => 0,
                'total_count' => 0,
            ];
        }
    }

    /*
     * 去学习
     */
    public function goLearn($goods_id)
    {
        $knowledge_payment_content_model = new VslKnowledgePaymentContentModel();
        //默认取第一条数据
        $list = $knowledge_payment_content_model->getQuery(['goods_id' => $goods_id, 'website_id' => $this->website_id], '*', 'id ASC')[0];
        $goods_info = $this->getGoodsDetailById($goods_id, 'goods_id,goods_name,sales,real_sales,description,picture', 1);
        $list['goods_picture'] = $goods_info['album_picture']['pic_cover'];
        $list['goods_name'] = $goods_info['goods_name'];
        $list['total_count'] = $knowledge_payment_content_model->getCount(['goods_id' => $goods_id]);
        $list['is_buy'] = true;
        $list['sales'] = $goods_info['sales'] + $goods_info['real_sales'];
        $list['description'] = str_replace('"//', '"https://', $goods_info['description']);
        return $list;
    }

    /**
     * 新的获取商品基本信息以及对应的活动信息的接口，后面多个地方可通用
     */
    public function getGoodsBasicInfo($goods_id,$store_id)
    {
        //判断后台配置的是哪种库存方式 1:门店独立库存 2:店铺统一库存  默认为1
        if(getAddons('store',$this->website_id,$this->instance_id)) {
            $storeServer = new storeServer();
            $stock_type = $storeServer->getStoreSet(0)['stock_type'] ? $storeServer->getStoreSet(0)['stock_type'] : 1;
        }else{
            $stock_type = 2;
        }

        if($store_id && $stock_type == 1){
            //门店自提
            $store_goods_model = new VslStoreGoodsModel();
            $store_goods_sku_model = new VslStoreGoodsSkuModel();
            $store_goods_condition = [
                'goods_id' =>  $goods_id,
                'store_id' =>  $store_id,
                'website_id' => $this->website_id
            ];
            $goods_info = $store_goods_model->getInfo($store_goods_condition,'*');
            $goods_sku_detail = $store_goods_sku_model->getQuery($store_goods_condition, '*', '');
        }else{
            //快递配送
            $goods_sku_model = new VslGoodsSkuModel();
            $goods_condition = [
                'goods_id' =>  $goods_id,
            ];
            $goods_info = $this->getGoodsDetailById($goods_id);
            $goods_sku_detail = $goods_sku_model->getQuery($goods_condition, '*', '');
        }

        //sku信息
        $goods_spec = new VslGoodsSpecModel();
        $goods_detail = $this->getGoodsDetailById($goods_info['goods_id']);
        $spec_list = json_decode($goods_detail['goods_spec_format'], true);
        $album = new Album();
        if (!empty($spec_list)) {
            foreach ($spec_list as $k1 => $v1) {
                $sort = $goods_spec->getInfo([
                    "spec_id" => $v1['spec_id']
                ], "sort");
                $spec_list[$k1]['sort'] = 0;
                if (!empty($sort)) {
                    $spec_list[$k1]['sort'] = $sort['sort'];
                }
                foreach ($v1["value"] as $m => $t) {
                    if (empty($v1['show_type'])) {
                        $spec_list[$k1]['show_type'] = 1;
                    }
                    // 查询SKU规格主图，没有返回0
                    $spec_list[$k1]["value"][$m]["picture"] = $this->getGoodsSkuPictureBySpecId($goods_id, $spec_list[$k1]["value"][$m]['spec_id'], $spec_list[$k1]["value"][$m]['spec_value_id']);
                    if (is_numeric($t["spec_value_data"]) && $v1["show_type"] == 3) {
                        $picture_detail = $album->getAlubmPictureDetail([
                            "pic_id" => $t["spec_value_data"]
                        ]);
                        if (!empty($picture_detail)) {
                            $spec_list[$k1]["value"][$m]["spec_value_data_src"] = __IMG($picture_detail["pic_cover_micro"]);
                        } else {
                            $spec_list[$k1]["value"][$m]["spec_value_data_src"] = null;
                        }
                        $spec_list[$k1]["value"][$m]["spec_value_data"] = $this->getGoodsSkuPictureBySpecId($goods_id, $spec_list[$k1]["value"][$m]['spec_id'], $spec_list[$k1]["value"][$m]['spec_value_id']);
                    } else {
                        $spec_list[$k1]["value"][$m]["spec_value_data_src"] = null;
                    }
                }
                unset($t);
            }
            unset($v1);
            // 排序字段
            $sort = array(
                'field' => 'sort'
            );

            $arrSort = array();
            foreach ($spec_list as $uniqid => $row) {
                foreach ($row as $key => $value) {
                    $arrSort[$key][$uniqid] = $value;
                }
                unset($value);
            }
            unset($row);
            array_multisort($arrSort[$sort['field']], SORT_ASC, $spec_list);
        }
        $goods_info['spec_list'] = $spec_list;

        //关联相册表，查出商品对应的图片
        $albumPictureModel = new AlbumPictureModel();
        $order = "instr('," . $goods_detail['img_id_array'] . ",',CONCAT(',',pic_id,','))"; // 根据 in里边的id 排序
        $goods_img_list = $albumPictureModel->getQuery([
            'pic_id' => [
                "in",
                $goods_detail['img_id_array']
            ]
        ], '*', $order);

        foreach ($goods_img_list as $k => $pic) {
            $goods_info['goods_images'][] = getApiSrc($pic['pic_cover']);
        }
        unset($pic);
        // 处理图片域名,替换后上传云服务器（图片域名为第三方的）,目的是为了图片域名必须在小程序downloaddomain中
        if (!empty($goods_img_list[0])) {
            $upload_url = $this->modifyImageUrl2AliOss($goods_info['goods_images'][0]);
            $goods_info['goods_image_yun'] = $upload_url;
        }

        //视频
        $goods_info['video'] = '';
        if ($goods_detail['video_id']) {
            $goods_info['video'] = $albumPictureModel->get($goods_detail['video_id']) ? $albumPictureModel->get($goods_detail['video_id'])['pic_cover'] : '';
        }

        //计算会员折扣
        $discount_service = getAddons('discount', $this->website_id) ? new Discount() : '';
        $limit_discount_info = getAddons('discount', $this->website_id) ? $discount_service->getPromotionInfo($goods_id, $this->instance_id, $this->website_id) : ['discount_num' => 10];

        $member_price = $goods_info['price'];
        $member_discount = 1;
        $goods_info['discount_choice'] = 1;
        $goods_info['member_is_label'] = 0;
        $goods_info['is_show_member_price'] = 0;
        $goods_info['price_type'] = 0;
        if ($this->uid) {
            //查询商品是否开启PLUS会员价
            $is_member_discount = 1;
            $goodsPower = $this->getGoodsPowerDiscount($goods_id);
            $goodsPower = json_decode($goodsPower['value'],true);
            $plus_member = $goodsPower['plus_member'] ? : 0;
            if($plus_member == 1) {
                if(getAddons('membercard',$this->website_id)) {
                    $membercard = new \addons\membercard\server\Membercard();
                    $membercard_data = $membercard->checkMembercardStatus($this->uid);
                    if($membercard_data['status']) {
                        if($membercard_data['membercard_info']['is_member_discount']) {
                            //有会员折扣权益
                            $member_price = $member_price * $membercard_data['membercard_info']['member_discount'] / 10;
                            $member_discount = $membercard_data['membercard_info']['member_discount'] / 10;
                            $goods_info['price_type'] = 1;
                            $goods_info['discount_choice'] = 1;
                            $goods_info['is_show_member_price'] = 1;
                            $goods_info['member_is_label'] = 0;
                            $is_member_discount = 0;
                        }
                        if($membercard_data['membercard_info']['is_free_shipping']) {
                            //有全场包邮权益
                            $goods_detail['shipping_fee_type'] = 0;
                        }
                    }
                }
            }

            if($is_member_discount) {
                // 查询商品是否有开启会员折扣
                $goodsDiscountInfo = $this->getGoodsInfoOfIndependentDiscount($goods_id, $member_price, ['price' => $goods_detail['price'], 'shop_id' => $goods_detail['shop_id']]);
                if ($goodsDiscountInfo) {
                    if($goodsDiscountInfo['is_use'] == 1){
                        $goods_info['price_type'] = 1; //会员折扣
                    }
                    $member_price = $goodsDiscountInfo['member_price'];
                    $member_discount = $goodsDiscountInfo['member_discount'];
                    $goods_info['discount_choice'] = $goodsDiscountInfo['discount_choice'];
                    $goods_info['is_show_member_price'] = $goodsDiscountInfo['is_show_member_price'];
                    $goods_info['member_is_label'] = $goodsDiscountInfo['member_is_label'];
                }
            }
        }
        $goods_info['member_price'] = $member_price;
        $goods_info['member_discount'] = $member_discount;

        //处理sku的价格
        foreach ($goods_sku_detail as $k => $goods_sku) {
            $pprice =  $goods_sku_detail[$k]['price'];
            $goods_sku_detail[$k]['member_price'] = $member_price;
            if ($goods_info['discount_choice'] == 2) {
                $goods_info['price_type'] = 1; //会员折扣
                $goods_sku_detail[$k]['price'] = $member_price;
            }
            if ($goods_info['discount_choice'] == 1) {
                $goods_info['price_type'] = 1; //会员折扣
                $goods_sku_detail[$k]['price'] = $pprice * $goodsDiscountInfo['member_discount'];
            }
            if ($limit_discount_info['discount_type'] == 1) {
                $goods_info['price_type'] = 2; //限时折扣
                $goods_sku_detail[$k]['price'] = $pprice * $limit_discount_info['discount_num'] / 10;
            }
            if ($limit_discount_info['discount_type'] == 2) {
                $goods_info['price_type'] = 2; //限时折扣
                $goods_sku_detail[$k]['price'] = $limit_discount_info['discount_num'];
            }

        }
        unset($goods_sku);
        if($limit_discount_info['discount_num'] == 10){
            $limit_discount_info = (object)[];
        }
        $goods_info['limit_discount_info'] = $limit_discount_info;
        $goods_info['sku_list'] = $goods_sku_detail;

        $goods_info['mansong_name'] = '';
        if(getAddons('fullcut', $this->website_id)){
            // 查询商品满减送活动
            $goods_mansong = new Fullcut();
            $goods_info['mansong_name'] = $goods_mansong->getGoodsMansongName($goods_id);
        }
        // 查询商品的已购数量
        $orderGoods = new VslOrderGoodsModel();
        $num = $orderGoods->getSum([
            "goods_id" => $goods_id,
            "buyer_id" => $this->uid,
            "order_status" => array(
                "neq",
                5
            )
        ], "num");
        $goods_info["purchase_num"] = $num;

        $goods_info["goods_detail"] = $goods_detail;
        return $goods_info;
    }

    /**
     * 购物车编辑规格或数量（新版购物车）
     */
    public function newGetCartList($cart_id, $num, $store_id, $sku_list,$shop_id, &$msg = '')
    {
        $cart = new VslCartModel();
        $cart_lists = $cart::get(['cart_id' => $cart_id], ['goods', 'goods_picture', 'sku']);
        $goods_name = $cart_lists['goods_name'];
        if (mb_strlen($cart_lists['goods']['goods_name']) > 10) {
            $goods_name = mb_substr($cart_lists['goods']['goods_name'], 0, 10) . '...';
        }
        //如果是秒杀商品并且没有结束，则取秒杀价
        if (!$cart_lists['seckill_id'] && getAddons('seckill', $this->website_id, $this->instance_id)) {
            $sec_server = new SeckillServer();
            $sku_id = $cart_lists['sku']['sku_id'];
            $condition_seckill['nsg.sku_id'] = $sku_id;
            $seckill_info = $sec_server->isSkuStartSeckill($condition_seckill);
            if ($seckill_info) {
                $cart_lists['seckill_id'] = $seckill_info['seckill_id'];
            }
        }
        if (!empty($cart_lists['seckill_id']) && getAddons('seckill', $this->website_id, $this->instance_id)) {
            $sec_server = new SeckillServer();
            //判断当前秒杀活动的商品是否已经开始并且没有结束
            $condition_seckill['s.website_id'] = $this->website_id;
            $condition_seckill['s.seckill_id'] = $cart_lists['seckill_id'];
            $condition_seckill['nsg.sku_id'] = $cart_lists['sku']['sku_id'];
            $is_seckill = $sec_server->isSeckillGoods($condition_seckill);
            if (!$is_seckill) {
                $cart_lists['price'] = $cart_lists['sku']['price'];
                $cart_lists['seckill_id'] = 0;
                $this->cartAdjustSec($cart_id, 0);
                $msg .= $goods_name . "商品该sku规格秒杀活动已经结束，已更改为正常状态商品价格" . PHP_EOL;
            } else {
                //取该商品该用户购买了多少
                $sku_id = $cart_lists['sku']['sku_id'];
                $uid = $this->uid;
                $website_id = $this->website_id;
                $buy_num = $this->getActivityOrderSku($uid, $sku_id, $website_id, $cart_lists['seckill_id']);
                $sec_sku_info_list = $sec_server->getSeckillSkuInfo(['seckill_id' => $cart_lists['seckill_id'], 'sku_id' => $cart_lists['sku']['sku_id']]);
                $cart_lists['stock'] = $sec_sku_info_list['remain_num'];
                $cart_lists['max_buy'] = (($sec_sku_info_list['seckill_limit_buy'] - $buy_num) < 0) ? 0 : $sec_sku_info_list['seckill_limit_buy'] - $buy_num;
                $cart_lists['max_buy'] = $cart_lists['max_buy'] > $cart_lists['stock'] ? $cart_lists['stock'] : $cart_lists['max_buy'];
                $cart_lists['price'] = $sec_sku_info_list['seckill_price'];
            }
        } else {
            $cart_lists['price'] = $cart_lists['sku']['price'];
        }

        if (getAddons('presell', $this->website_id, $this->instance_id)) {
            $presell = new PresellService();
            $is_presell = $presell->getIsInPresell($cart_lists['sku']['goods_id']);
            if($is_presell){
                $can_buy = $presell->getMeCanBuy($is_presell['presell_id'], $cart_lists['sku']['sku_id']);
                $presell_goods = new VslPresellGoodsModel();
                $presell_sku_info = $presell_goods->getInfo(['sku_id' => $cart_lists['sku']['sku_id'], 'presell_id' => $is_presell['presell_id']]);
                $cart_lists['stock'] = $presell_sku_info['presell_num'];
                $cart_lists['max_buy'] = $can_buy > $cart_lists['stock'] ? $cart_lists['stock'] : $can_buy;
                $cart_lists['price'] = $is_presell['all_money'];
            }
        }

        if($is_seckill){
            $max_buy = $cart_lists['max_buy'];
            $stock = $cart_lists['max_buy'];
        }elseif($is_presell){
            $max_buy = $cart_lists['max_buy'];
            $stock = $cart_lists['max_buy'];
        }else{
            $max_buy = 0;
            $stock = $cart_lists['sku']['stock'];
        }

        //判断后台配置的是哪种库存方式 1:门店独立库存 2:店铺统一库存  默认为1
        if(getAddons('store',$this->website_id,$this->instance_id)) {
            $storeServer = new storeServer();
            $stock_type = $storeServer->getStoreSet(0)['stock_type'] ? $storeServer->getStoreSet(0)['stock_type'] : 1;
        }else{
            $stock_type = 2;
        }

        if(empty($num)){
            //只改规格
            if($store_id && $stock_type == 1) {
                $store_goods_sku_model = new VslStoreGoodsSkuModel();
                $cart_lists['sku'] = $store_goods_sku_model->getInfo(['sku_id'=>$sku_list['sku_id'],'store_id'=>$store_id],'');
            }else{
                $goods_model =  new VslGoodsSkuModel();
                $cart_lists['sku'] = $goods_model->getInfo(['sku_id'=>$sku_list['sku_id']],'');
            }

            if ($stock <= 0) {
                $msg .= $goods_name . "商品该sku规格库存不足" . PHP_EOL;
            }
            if ($max_buy != 0 && $max_buy < $sku_list['num']) {
                $sku_list['num'] = $max_buy;
                $msg .= $goods_name . "商品该sku规格购买量大于最大购买量，购买数量已更改" . PHP_EOL;
            }
            //执行修改
            if($this->uid){
                $goods_model =  new VslGoodsSkuModel();
                //查询商品是否开启PLUS会员价
                $is_member_discount = 1;
                $goodsPower = $this->getGoodsPowerDiscount($cart_lists['sku']['goods_id']);
                $goodsPower = json_decode($goodsPower['value'],true);
                $plus_member = $goodsPower['plus_member'] ? : 0;
                if($plus_member == 1) {
                    if(getAddons('membercard',$this->website_id)) {
                        $membercard = new \addons\membercard\server\Membercard();
                        $membercard_data = $membercard->checkMembercardStatus($this->uid);
                        if($membercard_data['status'] && $membercard_data['membercard_info']['is_member_discount']) {
                            //有会员折扣权益
                            $update_data['price'] = $cart_lists['sku']['price'] * $membercard_data['membercard_info']['member_discount'] / 10;
                            $is_member_discount = 0;
                        }
                    }
                }

                if ($is_member_discount) {
                    //计算此规格的会员价
                    $platform_sku_info = $goods_model->getInfo(['sku_id'=>$sku_list['sku_id']],'goods_id,price');
                    $goodsDiscountInfo = $this->getGoodsInfoOfIndependentDiscount($platform_sku_info['goods_id'], $platform_sku_info['price']);//计算会员折扣价
                    if ($goodsDiscountInfo) {
                        $update_data['price'] = $goodsDiscountInfo['member_price'];
                    }else{
                        $update_data['price'] = $platform_sku_info['price'];
                    }
                }

                $update_data['sku_id'] = $sku_list['sku_id'];
                $update_data['sku_name'] = $cart_lists['sku']['sku_name'];
                $update_data['num'] = $sku_list['num'];
                $cart->save($update_data,['cart_id'=>$cart_id,'website_id'=>$this->website_id]);
            }
            $cart_lists['sku_id'] = $sku_list['sku_id'];
        }elseif(empty($sku_list)){
            //只改数量
            if($store_id && $stock_type == 1){
                $store_goods_sku_model = new VslStoreGoodsSkuModel();
                $cart_lists['sku'] = $store_goods_sku_model->getInfo(['sku_id'=>$cart_lists['sku_id'],'store_id'=>$store_id],'');
            }else{
                $goods_model =  new VslGoodsSkuModel();
                $cart_lists['sku'] = $goods_model->getInfo(['sku_id'=>$cart_lists['sku_id']],'');
            }
            if (empty($cart_lists['sku'])) {
                $cart->destroy(['cart_id' => $cart_lists['cart_id']]);
                $msg .= $goods_name . "商品该sku规格不存在，已移除" . PHP_EOL;
            }
            if ($stock <= 0) {
                $msg .= $goods_name . "商品该sku规格库存不足" . PHP_EOL;
            }
            if ($cart_lists['goods']['state'] != 1) {
                $msg .= $goods_name . "商品该sku规格已下架" . PHP_EOL;
            }
            if ($max_buy != 0 && $max_buy < $num) {
                $num = $max_buy;
                $this->cartAdjustNum($cart_id, $num);
                $msg .= $goods_name . "商品该sku规格购买量大于最大购买量，购买数量已更改" . PHP_EOL;
            }

            //快递配送,判断此用户有没有上级渠道商，如果有，库存显示平台库存+直属上级渠道商的库存
            $new_stock = 0;
            if(getAddons('channel',$this->website_id,0)) {
                if(empty($store_id)) {
                    $member_model = new VslMemberModel();
                    $referee_id = $member_model->Query(['uid'=>$this->uid,'website_id'=>$this->website_id],'referee_id')[0];
                    if($referee_id) {//如果有上级，判断是不是渠道商
                        $channel_model = new VslChannelModel();
                        $is_channel = $channel_model->Query(['uid'=>$referee_id,'website_id'=>$this->website_id],'channel_id')[0];
                        if($is_channel) {//如果上级是渠道商，判断上级渠道商有没有采购过这个商品
                            $channel_sku_mdl = new VslChannelGoodsSkuModel();
                            $channel_cond['channel_id'] = $is_channel;
                            $channel_cond['sku_id'] = $cart_lists['sku_id'];
                            $channel_cond['website_id'] = $this->website_id;
                            $channel_stock = $channel_sku_mdl->getInfo($channel_cond, 'stock')['stock'];
                            $new_stock = $cart_lists['sku']['stock'] + $channel_stock;
                        }
                    }
                }
            }

            if($new_stock) {
                if($num >= $new_stock){
                    $this->cartAdjustNum($cart_id,$new_stock);
                    $msg .= $goods_name . "商品该sku规格购买量大于库存，购买数量已更改" . PHP_EOL;
                }
            }else{
                if($num >= $stock){
                    $this->cartAdjustNum($cart_id, $cart_lists['sku']['stock']);
                    $msg .= $goods_name . "商品该sku规格购买量大于库存，购买数量已更改" . PHP_EOL;
                }
            }

            //执行修改
            if(empty($msg)){
                $this->cartAdjustNum($cart_id, $num);
            }
        }
        unset($cart_lists['goods'], $cart_lists['sku'], $cart_lists['goods_picture']);

        return $cart_lists;
    }

    /*
     * 改变商品规格存储,即便规格值被删除,sku仍然能够从商品使用的规格获取到相应规格值
     */
    private function changeSpec($specArray = []){
        if(!$specArray){
            return [];
        }
        $newArray = [];
        foreach($specArray as $val){

            if(!$val['value'] || !is_array($val['value'])){
                continue;
            }
            foreach($val['value'] as $v){
                $newArray[$v['spec_value_id']]['name'] = $v['spec_value_name'];
            }
            unset($v);
        }
        unset($val);
        return $newArray;
    }

    /**
     * 返回商品分类
     * @param $good_id int [商品id]
     * @return $categoryName string [分类拼接字串]
     */
    public function getGoodsCategoryNameByGoodId($good_id)
    {
        $goodsRes = $this->getGoodsDetailById($good_id);
        if (!$goodsRes) {
            return;
        }
        $categoryName = $this->getGoodsCategoryName($goodsRes['category_id_1'], $goodsRes['category_id_2'], $goodsRes['category_id_3'] );
        return $categoryName;
    }
    /*
     * 通过goods_id获取sku_id
     */
    public function getSkuIdByGoodsId($goods_id = 0){
        $goodsSkuModel = new VslGoodsSkuModel();
        $sku = $goodsSkuModel->getInfo(['goods_id' => $goods_id], 'sku_id');
        if(!$sku){
            return 0;
        }
        return $sku['sku_id'];
    }
    /*
     * 获取活动的价格
     * **/
    public function getPromotionPrice($promotion_type, $goods_id, $price = 0, $shop_id=0, $website_id = 0)
    {
        if(!$promotion_type || !$goods_id){
            return 0;
        }
        $promotion_price = 0;
        switch($promotion_type){
            case 1://秒杀
                if(getAddons('seckill', $this->website_id)){
                    //获取商品是否在秒杀中
                    $condition_is_seckill['nsg.goods_id'] = $goods_id;
                    $seckill_server = new Seckill();
                    $is_seckill = $seckill_server->isSkuStartSeckill($condition_is_seckill);
                    if($is_seckill){
                        $seckill_id = $is_seckill['seckill_id'];
                        $condition_seckill['ns.website_id'] = $this->website_id;
                        $condition_seckill['ns.seckill_id'] = $seckill_id;
                        $condition_seckill['nsg.goods_id'] = $goods_id;
                        $seckill_sku_price_arrs = $seckill_server->getGoodsSkuArr($condition_seckill, 'nsg.seckill_price');
                        $seckill_price_arr = array_column($seckill_sku_price_arrs, 'seckill_price');
                        $promotion_price = min($seckill_price_arr);
                    }
                }

                break;
            case 2://团购
                if(getAddons('groupshopping', $this->website_id)){
                    $group_server = new GroupShopping();
                    $group_goods_mdl = new VslGroupGoodsModel();
                    $is_group = $group_server->isGroupGoods($goods_id);
                    $group_goods_arr = objToArr($group_goods_mdl->where(['group_id' => $is_group, 'goods_id'=>$goods_id])->select());
                    if($group_goods_arr){
                        $group_price_arr = array_column($group_goods_arr, 'group_price');
                        $promotion_price = min($group_price_arr);
                    }
                }

                break;
            case 3://预售
                if(getAddons('presell', $this->website_id)){
                    $presell = new PresellService();
                    $presell_goods = new VslPresellGoodsModel();
                    //获取当前商品的预售活动
                    $presell_info = $presell->getPresellInfoByGoodsId($goods_id);
                    if($presell_info){
                        $where['presell_id'] = $presell_info[0]['presell_id'];
                        $where['goods_id'] = $goods_id;
                        $presell_goods_arr = objToArr($presell_goods->where($where)->select());
                        $presell_price_arr = array_column($presell_goods_arr, 'all_money');
                        $promotion_price = min($presell_price_arr);
                    }
                }

                break;
            case 5://限时折扣
                if(getAddons('discount', $this->website_id)){
                    $discount = new Discount();
                    $limit_discount_info = $discount->getPromotionInfo($goods_id, $shop_id, $website_id);
                    if($limit_discount_info['discount_type'] == 1){
                        $promotion_price = $limit_discount_info['discount_num']/10 * $price;
                    }elseif($limit_discount_info['discount_type'] == 2){
                        $promotion_price = $limit_discount_info['discount_num'];
                    }

                }
                break;
            case 6://幸运团购
                if(getAddons('luckyspell', $this->website_id)){
                    $group_server = new LuckySpellServer();
                    $group_goods_mdl = new VslLuckySpellGoodsModel();
                    $is_group = $group_server->isGroupGoods($goods_id);
                    $group_goods_arr = objToArr($group_goods_mdl->where(['group_id' => $is_group, 'goods_id'=>$goods_id])->select());
                    if($group_goods_arr){
                        $group_price_arr = array_column($group_goods_arr, 'group_price');
                        $promotion_price = min($group_price_arr);
                    }
                }

                break;
        }
        return $promotion_price;
    }

    public function getMinSkuPrice($goods_id)
    {
        $skuModel = new VslGoodsSkuModel();
        $goods_sku_price = $skuModel->getMin(['goods_id' => $goods_id], 'price');
        return $goods_sku_price;
    }

    /**
     * 商品评价信息
     * @param $condition
     * @param string $field
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getGoodsEvaluateInfo($condition, $field = '*')
    {
        $goodsEvaluate = new VslGoodsEvaluateModel();
        return $goodsEvaluate->getInfo($condition, $field);
    }

    /**
     * 批量操作
     */
    public function batchOperation($data)
    {
        Db::startTrans();
        try {
            $goods_ids = explode(',', $data['goods_ids']);
            if ($data['type'] == 1) {
                //批量修改价格
                if ($data['edit_type'] == 1) {
                    //按公式修改
                    if ($data['decimal_type'] == 1) {
                        //尾数四舍五入
                        foreach ($goods_ids as $k => $v) {
                            $goods_info = $this->getGoodsDetailById($v);
                            if($goods_info['payment_type'] == 2 && $goods_info['supplier_info']) {
                                //从供应商中挑选的商品，并且是以扣除佣金结算的，则不能修改售价
                                $res = 1;
                                continue;
                            }
                            if ($data['sign'] == 1) {//加
                                $goods_info['price'] = round($goods_info['price'] + $data['price'], 2);
                            } elseif ($data['sign'] == 2) {//减
                                $goods_info['price'] = $goods_info['price'] - $data['price'] >= 0 ? round($goods_info['price'] - $data['price'], 2) : 0;
                            } elseif ($data['sign'] == 3) {//乘
                                $goods_info['price'] = round($goods_info['price'] * $data['price'], 2);
                            } elseif ($data['sign'] == 4) {//除
                                $goods_info['price'] = round($goods_info['price'] / $data['price'], 2);
                            }
                            //修改商品表
                            $res = $this->updateGoods(['goods_id' => $v], ['price' => $goods_info['price']], $v);

                            if ($res) {
                                $goods_sku_mdl = new VslGoodsSkuModel();
                                $sku_list = $goods_sku_mdl->getQuery(['goods_id' => $v], 'sku_id,price', '');
                                foreach ($sku_list as $k1 => $v1) {
                                    $goods_sku_mdl = new VslGoodsSkuModel();
                                    if ($data['sign'] == 1) {//加
                                        $sku_price = round($v1['price'] + $data['price'], 2);
                                    } elseif ($data['sign'] == 2) {//减
                                        $sku_price = $v1['price'] - $data['price'] >= 0 ? round($v1['price'] - $data['price'], 2) : 0;
                                    } elseif ($data['sign'] == 3) {//乘
                                        $sku_price = round($v1['price'] * $data['price'], 2);
                                    } elseif ($data['sign'] == 4) {//除
                                        $sku_price = round($v1['price'] / $data['price'], 2);
                                    }
                                    //修改sku表
                                    $goods_sku_mdl->isUpdate(true)->save(['price' => $sku_price, 'promote_price' => $sku_price], ['sku_id' => $v1['sku_id'], 'goods_id' => $v]);
                                }
                                unset($v1);
                            }
                        }
                        unset($v);
                    } else {
                        //尾数进一
                        foreach ($goods_ids as $k => $v) {
                            $goods_info = $this->getGoodsDetailById($v);
                            if($goods_info['payment_type'] == 2 && $goods_info['supplier_info']) {
                                //从供应商中挑选的商品，并且是以扣除佣金结算的，则不能修改售价
                                $res = 1;
                                continue;
                            }
                            if ($data['sign'] == 1) {//加
                                $goods_info['price'] = ceil($goods_info['price'] + $data['price']);
                            } elseif ($data['sign'] == 2) {//减
                                $goods_info['price'] = $goods_info['price'] - $data['price'] >= 0 ? ceil($goods_info['price'] - $data['price']) : 0;
                            } elseif ($data['sign'] == 3) {//乘
                                $goods_info['price'] = ceil($goods_info['price'] * $data['price']);
                            } elseif ($data['sign'] == 4) {//除
                                $goods_info['price'] = ceil($goods_info['price'] / $data['price']);
                            }
                            //修改商品表
                            $res = $this->updateGoods(['goods_id' => $v], ['price' => $goods_info['price']], $v);

                            if ($res) {
                                $goods_sku_mdl = new VslGoodsSkuModel();
                                $sku_list = $goods_sku_mdl->getQuery(['goods_id' => $v], 'sku_id,price', '');
                                foreach ($sku_list as $k1 => $v1) {
                                    $goods_sku_mdl = new VslGoodsSkuModel();
                                    if ($data['sign'] == 1) {//加
                                        $sku_price = ceil($v1['price'] + $data['price']);
                                    } elseif ($data['sign'] == 2) {//减
                                        $sku_price = $v1['price'] - $data['price'] >= 0 ? ceil($v1['price'] - $data['price']) : 0;
                                    } elseif ($data['sign'] == 3) {//乘
                                        $sku_price = ceil($v1['price'] * $data['price']);
                                    } elseif ($data['sign'] == 4) {//除
                                        $sku_price = ceil($v1['price'] / $data['price']);
                                    }
                                    //修改sku表
                                    $goods_sku_mdl->isUpdate(true)->save(['price' => $sku_price, 'promote_price' => $sku_price], ['sku_id' => $v1['sku_id'], 'goods_id' => $v]);
                                }
                                unset($v1);
                            }
                        }
                        unset($v);
                    }
                } else {
                    //统一价格
                    foreach ($goods_ids as $k => $v) {
                        $goods_info = $this->getGoodsDetailById($v);
                        if($goods_info['payment_type'] == 2 && $goods_info['supplier_info']) {
                            //从供应商中挑选的商品，并且是以扣除佣金结算的，则不能修改售价
                            $res = 1;
                            continue;
                        }
                        //修改商品表
                        $res = $this->updateGoods(['goods_id' => $v], ['price' => $data['price']], $v);

                        if ($res) {
                            $goods_sku_mdl = new VslGoodsSkuModel();
                            //修改sku表
                            $goods_sku_mdl->isUpdate(true)->save(['price' => $data['price'], 'promote_price' => $data['price']], ['goods_id' => $v]);
                        }
                    }
                    unset($v);
                }
            } elseif ($data['type'] == 3) {
                $redis = connectRedis();
                //批量修改库存
                if ($data['edit_type'] == 1) {
                    //按公式修改
                    foreach ($goods_ids as $k => $v) {
                        //判断修改的商品是否是电子卡密商品或者是供应商商品，如果是，则不修改
                        $goods_info = $this->getGoodsDetailById($v);
                        if($goods_info['goods_type'] == 5 || $goods_info['supplier_info']) {
                            $res = 1;
                            continue;
                        }else{
                            $stock = 0;
                            $goods_sku_mdl = new VslGoodsSkuModel();
                            $sku_list = $goods_sku_mdl->getQuery(['goods_id' => $v], 'sku_id,stock', '');
                            foreach ($sku_list as $k1 => $v1) {
                                $goods_sku_mdl = new VslGoodsSkuModel();
                                if ($data['sign'] == 1) {//加
                                    $sku_stock = $v1['stock'] + $data['stock'];
                                } elseif ($data['sign'] == 2) {//减
                                    $sku_stock = $v1['stock'] - $data['stock'] >= 0 ? $v1['stock'] - $data['stock'] : 0;
                                }
                                //修改sku表
                                $goods_sku_mdl->isUpdate(true)->save(['stock' => $sku_stock], ['sku_id' => $v1['sku_id'], 'goods_id' => $v]);
                                //同步商品库存到redis
                                $goods_key = 'goods_'.$v.'_'.$v1['sku_id'];
                                if(!$redis->get($goods_key)){
                                    $redis->set($goods_key, $v['stock']);
                                }
                                $stock += $sku_stock;
                            }
                            unset($v1);
                            //修改商品表
                            $res = $this->updateGoods(['goods_id' => $v], ['stock' => $stock], $v);
                        }
                    }
                    unset($v);
                } else {
                    //统一库存
                    foreach ($goods_ids as $k => $v) {
                        //判断修改的商品是否是电子卡密商品或者是供应商商品，如果是，则不修改
                        $goods_info = $this->getGoodsDetailById($v);
                        if($goods_info['goods_type'] == 5 || $goods_info['supplier_info']) {
                            $res = 1;
                            continue;
                        }else{
                            $goods_sku_mdl = new VslGoodsSkuModel();
                            //修改sku表
                            $goods_sku_mdl->isUpdate(true)->save(['stock' => $data['stock']], ['goods_id' => $v]);
                            $sku_id_arr = $goods_sku_mdl->getQuery(['goods_id' => $v], 'sku_id');
                            foreach($sku_id_arr as $sku_info){
                                //同步商品库存到redis
                                $goods_key = 'goods_'.$v.'_'.$sku_info['sku_id'];
                                if(!$redis->get($goods_key)){
                                    $redis->set($goods_key, $v['stock']);
                                }
                            }
                            //查出这个商品有几个sku
                            $sku_num = $goods_sku_mdl->getCount(['goods_id' => $v]);
                            $stock = $sku_num * $data['stock'];

                            //修改商品表
                            $res = $this->updateGoods(['goods_id' => $v], ['stock' => $stock], $v);
                        }
                    }
                    unset($v);
                }
            } elseif ($data['type'] == 2) {
                //修改佣金
                $data = [
                    'is_distribution' => $data['distribution_bonus']['is_distribution'],
                    'distribution_rule_val' => $data['distribution_bonus']['distribution_val'],
                    'distribution_rule' => $data['distribution_bonus']['distribution_rule'],

                    'buyagain' => $data['distribution_bonus']['buyagain'],
                    'buyagain_level_rule' => $data['distribution_bonus']['buyagain_level_rule'],
                    'buyagain_recommend_type' => $data['distribution_bonus']['buyagain_recommend_type'],
                    'buyagain_distribution_val' => $data['distribution_bonus']['buyagain_distribution_val'],
                ];
                $res = $this->updateGoods(['goods_id' => ['in',$goods_ids]], $data);
            }
            Db::commit();
            return $res;
        } catch (\Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    /**
     *
     * @param $promotion_type
     * @param $sku_id 根据$promotion_type的对应sku_id
     * @return int
     */

    /**
     * 该商品规格最大限购数（设置的max_buy-已购买数量）-1不能购买 0不限购（前端与库存比较）其他:实际限购数
     * @param $promotion_type   促销类型 0无促销，1秒杀，2拼团, 3预售, 4砍价
     * @param $main_id  根据$promotion_type对应的主要id
     * @param $main_sku_id 根据$promotion_type对应的商品规格id
     * @return int 该规格还能购买数
     * @throws \think\Exception\DbException
     */
    public function getUserMaxBuyGoodsSkuCount($promotion_type, $main_id, $main_sku_id=0)
    {
        # 订单类型通过vsl_order表的order_type区分
        # 订单类型订单类型order_type: 1为普通 5拼团订单，6秒杀订单，7预售订单，8砍价订单
        // 未登录用户不查询
        if (!$this->uid) {return 0;}
        // 登录了，查询对应max_buy多少；再查询订单已经购买多少单，返回最终值
        $can_count = 0;
        switch ($promotion_type) {
            /*普通*/
            case 0:
                $goodSkuModel = new VslGoodsSkuModel();
                $condition = [
                    'website_id'        => $this->website_id,
                    'goods_id'          => $main_id,
                    'sku_id'            => $main_sku_id,
                ];
                $goodSkuInfo = $goodSkuModel->getInfo($condition, 'sku_max_buy,sku_max_buy_time');
                unset($condition);
                if ($goodSkuInfo['sku_max_buy'] == 0) {return 0;}
                $condition = [
                    'vsl_order.website_id'          => $this->website_id,
                    'vsl_order.order_status'        => ['in', '1,2,3,4,7'],
                    'vsl_order.order_type'          => ['=', 1],
                    'vsl_order.buyer_id'            => $this->uid,
                    'vsl_order.pay_time'            => ['gt', $goodSkuInfo['sku_max_buy_time'] ?: time()],
                    'vsl_order_goods.buyer_id'      => $this->uid,
                    'vsl_order_goods.sku_id'        => $main_sku_id,
                    'vsl_order_goods.goods_id'      => $main_id,
                ];
                $orderGoodsModel = new VslOrderGoodsModel();
                $orderRes        = $orderGoodsModel::all($condition, ['order']);
                $orderRes        = objToArr($orderRes);
                $buyed_count     = array_sum(array_column($orderRes,'num'));
                $can_count       = ($goodSkuInfo['sku_max_buy'] - $buyed_count) > 0 ? $goodSkuInfo['sku_max_buy'] - $buyed_count : -1;
                break;
            /*秒杀*/
            case 1:
                $seckillGoodsModel = new VslSeckGoodsModel();
                $condition = [
                    'seckill_id'        => $main_id,//秒杀用seckill_id
                    'sku_id'            => $main_sku_id,
                ];
                $seckillGoodsRes = $seckillGoodsModel->getInfo($condition, 'goods_id,seckill_limit_buy,max_buy_time');
                unset($condition);
                if ($seckillGoodsRes['seckill_limit_buy'] == 0) {return 0;}
                $buyed_count = $this->getActivityOrderSkuNum($this->uid, $main_sku_id, $this->website_id, 1, $main_id);
                $can_count   = ($seckillGoodsRes['seckill_limit_buy'] - $buyed_count) > 0 ? $seckillGoodsRes['seckill_limit_buy'] - $buyed_count : -1;
                break;
            /*拼团*/
            case 2:
                $groupGoodsModel = new VslGroupGoodsModel();
                $condition = [
                    'group_id'          => $main_id,//拼团用group_id
                    'sku_id'            => $main_sku_id,
                ];
                $groupGoodsRes =  $groupGoodsModel->getInfo($condition, 'goods_id,group_limit_buy,max_buy_time');
                unset($condition);
                if ($groupGoodsRes['group_limit_buy'] == 0) {return 0;}
                $buyed_count = $this->getActivityOrderSkuNum($this->uid, $main_sku_id, $this->website_id, 2, $main_id);
                $can_count   = ($groupGoodsRes['group_limit_buy'] - $buyed_count) > 0 ? $groupGoodsRes['group_limit_buy'] - $buyed_count : -1;
                break;
            /*预售*/
            case 3:
                $presellGoodsModel = new VslPresellGoodsModel();
                $condition = [
                    'presell_id'        => $main_id,//预售用presell_id
                    'sku_id'            => $main_sku_id,
                ];
                $presellGoodsRes =  $presellGoodsModel->getInfo($condition, 'goods_id,max_buy,max_buy_time');
                unset($condition);
                if ($presellGoodsRes['max_buy'] == 0) {return 0;}
                $buyed_count = $this->getActivityOrderSkuNum($this->uid, $main_sku_id, $this->website_id, 4, $main_id);
                $can_count   = ($presellGoodsRes['max_buy'] - $buyed_count) > 0 ? $presellGoodsRes['max_buy'] - $buyed_count : -1;
                break;
            /*砍价*/
            case 4:
                $bargainModel = new VslBargainModel();
                $condition = [
                    'bargain_id'        => $main_id,//砍价用bargain_id
//                    'sku_id'            => $main_sku_id,
                ];
                $bargainRes =  $bargainModel->getInfo($condition, 'goods_id,limit_buy,max_buy_time');
                unset($condition);
                if ($bargainRes['limit_buy'] == 0) {return 0;}
                $buyed_count = $this->getActivityOrderSkuNum($this->uid, $main_sku_id, $this->website_id, 3, $main_id);
                $can_count   = ($bargainRes['limit_buy'] - $buyed_count) > 0 ? $bargainRes['limit_buy'] - $buyed_count : -1;
                break;
                /*幸运拼团*/
                case 6:
                    $groupGoodsModel = new VslLuckySpellGoodsModel();
                    $condition = [
                        'group_id'          => $main_id,//拼团用group_id
                        'sku_id'            => $main_sku_id,
                    ];
                    $groupGoodsRes =  $groupGoodsModel->getInfo($condition, 'goods_id,group_limit_buy,max_buy_time');
                    unset($condition);
                    if ($groupGoodsRes['group_limit_buy'] == 0) {return 0;}
                    $buyed_count = $this->getActivityOrderSkuNum($this->uid, $main_sku_id, $this->website_id,6, $main_id);
                    $can_count   = ($groupGoodsRes['group_limit_buy'] - $buyed_count) > 0 ? $groupGoodsRes['group_limit_buy'] - $buyed_count : -1;
                    break;
        }
        return $can_count;
    }

    /**
     * 获取商品起购量
     * @param $promotion_type 促销类型 0无促销，1秒杀，2拼团, 3预售
     * @param $main_id 根据$promotion_type对应的主要id
     * @return int
     */
    public function getUserLeastBuyGoods($promotion_type, $main_id)
    {
        # 订单类型订单类型1为普通 5拼团订单，6秒杀订单，7预售订单，8砍价订单
        // 未登录用户不查询
        if (!$this->uid) {return 0;}
        // 登录了，查询对应max_buy多少；再查询订单已经购买多少单，返回最终值
        $can_count = 0;
        switch ($promotion_type) {
            /*普通*/
            case 0:
                $condition = [
                    'website_id'        => $this->website_id,
                    'goods_id'          => $main_id
                ];
                $goodInfo = $this->getGoodsDetailByCondition($condition, 'least_buy');
                $least_buy = $goodInfo['least_buy'];
                break;
            /*秒杀*/
            case 1:
                $seckillModel = new VslSeckillModel();
                $condition = [
                    'seckill_id'        => $main_id,//秒杀用seckill_id
                ];
                $seckillRes = $seckillModel->getInfo($condition, 'least_buy');
                $least_buy = $seckillRes['least_buy'];
                break;
            /*拼团*/
            case 2:
                $groupModel = new VslGroupShoppingModel();
                $condition = [
                    'group_id'          => $main_id,//拼团用group_id
                ];
                $groupRes =  $groupModel->getInfo($condition, 'least_buy');
                $least_buy = $groupRes['least_buy'];
                break;
            /*预售*/
            case 3:
                $presellModel = new VslPresellModel();
                $condition = [
                    'id'        => $main_id,//预售用presell_id
                ];
                $presellRes =  $presellModel->getInfo($condition, 'least_buy');
                $least_buy = $presellRes['least_buy'];
                break;
            case 6:
                #幸运拼 暂时没有起购现在 只能每次购买1
                $least_buy = 1;
                break;
        }
        return $least_buy;
    }

    /**
     * 获取商品限购地区并整理返回
     */
    public function getGoodsAreaList ($goods_id)
    {
        $goodsRes = $this->getGoodsDetailById($goods_id);
        $return = [
            'province' => [],
            'city' => [],
            'district' => []
        ];
        //3虚拟商品  4知识付费  5电子卡密 6预约商品
        if (in_array($goodsRes['goods_type'], [3,4,5,6])) {
            return $return;//不需要验证
        }

        $area_list = htmlspecialchars_decode($goodsRes['area_list']);
        if ($area_list) {
            $areaArr = json_decode($area_list, true);
            if ($areaArr['ids']) {
                $idsArr = explode('§', $areaArr['ids']);
                $return['province'] = explode(',', $idsArr[0]);
                $return['city'] = explode(',', $idsArr[1]);
                $return['district'] = explode(',', $idsArr[2]);
            }
        }

        return $return;
    }

    /**
     * 是否客户的地区属于商品地区限购的允许范围
     * @param $district int [district 街道id]
     * @param $goods_forbid_area_arr
     * @return bool
     */
    public function isUserAreaBelongGoodsAllowArea ($district, $goods_forbid_area_arr)
    {
        # 只用限制区就行
        if (in_array($district,$goods_forbid_area_arr['district'])){
            return true;
        }
        return false;
    }

    /**
     * 用户收货地址
     * @param string $address_id 地址id
     * @return mixed
     */
    public function getMemberExpressAddress ($address_id = '')
    {
        if (empty($address_id)) {
            $address_condition['uid'] = $this->uid;
            $address_condition['is_default'] = 1;
        } else {
            $address_condition['uid'] = $this->uid;
            $address_condition['id'] = $address_id;
        }
        $member_service = new MemberService();
        $address = $member_service->getMemberExpressAddress($address_condition, ['area_province', 'area_city', 'area_district']);

        return $address;
    }

    /**
     * 转为自营商品
     */
    public function changeToSelfGoods($goods_id)
    {
        $save_data = [
            'payment_type' => 0,
            'supplier_rebate' => 0,
            'supplier_info' => 0,
            'supplier_goods_id' => 0,
            'supplier_operation' => 0,
            'state' => 1,
        ];

        //goods_sku表的supplier_sku_id置空
        $update_sku_data = [
            'supplier_sku_id' => 0
        ];
        $goods_sku_mdl = new VslGoodsSkuModel();
        $sku_list = $goods_sku_mdl->getQuery(['goods_id' => $goods_id],'sku_id','sku_id asc');
        foreach ($sku_list as $k => $v) {
            $goods_sku_mdl = new VslGoodsSkuModel();
            $goods_sku_mdl->isUpdate(true)->save($update_sku_data,['sku_id' => $v['sku_id']]);
        }
        unset($v);
        $res = $this->goods->isUpdate(true)->save($save_data,['goods_id' => $goods_id]);
        return $res;
    }

    /**
     * 检查是否是供应商商品
     */
    public function checkIsSupplierGoods($goods_id)
    {
        $supplier_id = $this->getGoodsDetailById($goods_id, 'goods_id,goods_name,sales,real_sales,supplier_info')['supplier_info'];
        return $supplier_id;
    }

    /**
     * 查询【供应商】商品的结算方式及分佣比例
     * @param $goods_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getSupplierPaymentTypeInfoByGoodsId ($goods_id)
    {
        $goodsInfo = $this->getGoodsDetailById($goods_id);
        if(!$goodsInfo){
            return [];
        }
        $result = [
            'payment_type' => $goodsInfo['payment_type'],
            'supplier_rebate' => $goodsInfo['supplier_rebate']
        ];
        return $result;
    }

    /**
     * 同步供应商商品库存
     */
    public function syncSupplierGoodsStock($supplier_goods_id,$supplier_goods_sku_id)
    {
        Db::startTrans();
        try{
            $goods_list = $this->goods->Query(['supplier_goods_id' => $supplier_goods_id],'goods_id');
            if($goods_list) {
                //供应商商品库存
                $goods_stock = $this->goods->Query(['goods_id' => $supplier_goods_id],'stock')[0];
                foreach ($goods_list as $k => $v) {
                    $this->updateGoods(['goods_id' => $v], ['stock' => $goods_stock], $v);
                }
                unset($v);
                //供应商商品sku库存
                $goods_sku_mdl = new VslGoodsSkuModel();
                $goods_sku_stock = $goods_sku_mdl->Query(['sku_id' => $supplier_goods_sku_id],'stock')[0];
                $sku_list = $goods_sku_mdl->getQuery(['supplier_sku_id' => $supplier_goods_sku_id],'sku_id, goods_id');
                $redis = connectRedis();
                foreach ($sku_list as $k => $v) {
                    $goods_sku_mdl = new VslGoodsSkuModel();
                    $goods_sku_mdl->isUpdate(true)->save(['stock' => $goods_sku_stock],['sku_id' => $v['sku_id']]);
                    //同步商品库存到redis
                    $goods_key = 'goods_'.$v['goods_id'].'_'.$v['sku_id'];
                    if(!$redis->get($goods_key)){
                        $redis->set($goods_key, $goods_sku_stock);
                    }
                }
                unset($v);
            }
            Db::commit();
            return 1;
        }catch (\Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    /**
     * 获取领货码的配置信息
     * @param $code_no
     * @param $website_id
     * @param $shop_id
     * @return array|\multitype
     * @throws \think\Exception\DbException
     */
    public function getReceiveGoodsCodeConfigInfo ($code_no,$website_id,$shop_id)
    {
        if (!getAddons('receivegoodscode', $website_id, $shop_id)){
            return AjaxReturn(ADDONS_IS_NOT_EXIST);
        }
        $codeSer = new ReceiveGoodsCodeSer();
        $codeConfig = $codeSer->getReceiveGoodsCodeConfigByCodeNo($code_no,$website_id,$shop_id);
        if(!$codeConfig){
            return AjaxReturn(RECEIVE_GOODS_CODE_ERROR);
        }
        if (!in_array($codeConfig['code_status'], [0,2])){
            return AjaxReturn(RECEIVE_GOODS_CODE_ERROR);
        }
        if ($codeConfig['code_config']['validity_type'] == 2 && $codeConfig['code_config']['end_time'] < time() ){
            return AjaxReturn(RECEIVE_GOODS_CODE_ERROR);
        }
        $return_data = [
            'discount_type'     => $codeConfig['code_config']['discount_type'],
            'discount_price'    => $codeConfig['code_config']['discount_type'] == 2 ? $codeConfig['code_config']['discount_price'] : 0,
            'goods_id'          => $codeConfig['code_config']['goods_id'],
        ];

        return AjaxReturn(SUCCESS,$return_data);
    }

     /**
     * 移动商家端添加修改商品
     * @return \data\model\VslGoodsModel|number
     */
    public function addOrEditGoodsForBiz($data)
    {
        # 用数组或对象接受参数，方便注释、修改与浏览。
        $goods_id                       = $data['goods_id'];
        $goods_name                     = $data['goods_name'];
        $shopid                         = $data['shopid'];
        $category_id                    = $data['category_id'] ?: 0;
        $supplier_id                    = $data['supplier_id'] ?: 0;
        $goods_type                     = $data['goods_type'];//实物或虚拟商品标志 1实物商品 0计时计次商品 3虚拟商品 2 F码商品 4知识付费  5电子卡密
        $market_price                   = $data['market_price'] ?: 0.00;//市场价
        $price                          = $data['price'] ?: 0.00;//商品原价格
        $cost_price                     = $data['cost_price'] ?: 0.00;//成本价
        $shipping_fee                   = $data['shipping_fee'] ?: 0.00;//统一运费
        $shipping_fee_id                = $data['shipping_fee_id'];
        $stock                          = $data['stock'];
        $picture                        = $data['picture'] ?: 0;
        $description                    = $data['description'];
        $code                           = $data['code'];
        $img_id_array                   = $data['img_id_array'];//商品图片序列
        $sku_array                      = $data['sku_array'];//规格组
        $state                          = $data['state'] ?: 0;//商品状态 0下架，1正常，10违规（禁售），11待审核，12审核不通过
        $sku_img_array                  = $data['sku_img_array'] ?: '';//商品sku应用图片列表  属性,属性值，图片ID
        $goods_attribute_id             = $data['goods_attribute_id'] ?: 0;//商品类型
        $goods_attribute                = $data['goods_attribute'];
        $goods_spec_format              = $data['goods_spec_format'];//商品规格
        $goods_weight                   = $data['goods_weight'] ?: 0.00;//商品重量
        $goods_volume                   = $data['goods_volume'] ?: 0.00;//商品体积
        $shipping_fee_type              = $data['shipping_fee_type'] ?: 0;//运费类型：0免运费，1统一运费，2选择模板
        $sku_picture_values             = $data['sku_picture_values'];
        $item_no                        = $data['item_no'] ?: '';//商品货号
        $verificationinfo               = $data['verificationinfo'];
        $goods_count                    = $data['goods_count'] ?: 0;//商品数量，用于运费模板
        $sub_title                      = $data['sub_title'] ?: 0;//副标题
        $activity_pic                    = $data['activity_pic'] ?: 0;//活动图
        $error                          = 0;
        $category_list                  = $this->getGoodsCategoryId($category_id);
        $this->goods->startTrans();
        try {
            $goods_info = $this->goods->getInfo(['goods_id' => $goods_id]);
            $data_goods = array(
                'website_id' => $this->website_id,
                'goods_name' => $goods_name,
                'shop_id' => $shopid,
                'category_id' => $category_id,
                'category_id_1' => $category_list[0],
                'category_id_2' => $category_list[1],
                'category_id_3' => $category_list[2],
                'goods_type' => $goods_type,
                'market_price' => $market_price,
                'price' => $price,
                'promotion_price' => $price,
                'cost_price' => $cost_price,
                'shipping_fee' => $shipping_fee,
                'shipping_fee_id' => $shipping_fee_id,
                'stock' => $stock,
                'picture' => $picture,
                'description' => $description,
                'code' => $code,
                'img_id_array' => $img_id_array,
                'state' => $state,
                'sku_img_array' => $sku_img_array,
                'goods_attribute_id' => $goods_attribute_id,
                'goods_spec_format' => json_encode($goods_spec_format,true),
                'goods_weight' => $goods_weight,
                'goods_volume' => $goods_volume,
                'shipping_fee_type' => $shipping_fee_type,
                'item_no' => $item_no,
                'goods_count' => $goods_count,
                'store_list' => $verificationinfo['store_list'],
                'sub_title' => $sub_title,
                'activity_pic' => $activity_pic,
            );
            if($this->model == 'supplier'){
                $data_goods['supplier_id'] = $supplier_id;
            }
            // 商品保存之前钩子
            hook("goodsSaveBefore", $data_goods);
            $specArray = $this->changeSpec($goods_spec_format);
            if ($goods_id == 0) {
                $data_goods['create_time'] = time();
                $data_goods['sale_date'] = time();
                $data_goods['max_buy_time'] = time();
                $res = $this->goods->save($data_goods);
                if (empty($this->goods->goods_id)) {
                    $this->goods->goods_id = $res;
                }
                $data_goods['goods_id'] = $this->goods->goods_id;
                hook("goodsSaveSuccess", $data_goods);
                $goods_id = $this->goods->goods_id;
                // 添加sku
                if (!empty($sku_array)) {
                    $sku_list_array = explode('§', $sku_array);
                    if (empty($sku_list_array[0])) {
                        unset($sku_list_array[0]);//删掉空数据
                    }
                    foreach ($sku_list_array as $k => $v) {
                        $res = $this->addOrUpdateGoodsSkuItem($goods_id, $v, $specArray);
                        if (!$res) {
                            $error = 1;
                        }
                    }
                    unset($v);
                    // sku图片添加
                    $sku_picture_array = array();
                    if (!empty($sku_picture_values)) {
                        $sku_picture_array = $sku_picture_values;
                        foreach ($sku_picture_array as $k => $v) {
                            $res = $this->addGoodsSkuPicture($shopid, $goods_id, $v["spec_id"], $v["spec_value_id"], $v["img_ids"]);
                            if (!$res) {
                                $error = 1;
                            }
                        }
                        unset($v);
                    }
                } else {
                    $goods_sku = new VslGoodsSkuModel();
                    // 添加一条skuitem
                    $sku_data = array(
                        'goods_id' => $this->goods->goods_id,
                        'sku_name' => '',
                        'market_price' => $market_price,
                        'price' => $price,
                        'promote_price' => $price,
                        'cost_price' => $cost_price,
                        'stock' => $stock,
                        'picture' => 0,
                        'code' => $code,
                        'QRcode' => '',
                        'create_date' => time(),
                        'website_id' => $this->website_id,
                        'sku_max_buy_time' => time()
                    );
                    $res = $goods_sku->save($sku_data);
                    if (!$res) {
                        $error = 1;
                    }
                }
            } else {/*修改*/
                //如果是供应商商品，则不修改运费设置、结算方式
                if($goods_info['supplier_info']) {
                    unset($data_goods['shipping_fee'],$data_goods['shipping_fee_id'],$data_goods['shipping_fee_type'],$data_goods['payment_type'],$data_goods['supplier_rebate'],$data_goods['goods_count'],$data_goods['goods_volume'],$data_goods['goods_weight']);
                }
                $data_goods['update_time'] = time();
                $res = $this->goods->save($data_goods, [
                    'goods_id' => $goods_id
                ]);
                $data_goods['goods_id'] = $goods_id;
                 //修改商品时，判断有没有修改核销门店，取消勾选了就要删除对应门店的商品，添加新勾选门店对应的商品
                if ($verificationinfo['store_list']) {
                    $storeGoodsSkuModel = new VslStoreGoodsSkuModel();
                    $verificationinfo['store_list'] = explode(',', $verificationinfo['store_list']);
                    foreach ($verificationinfo['store_list'] as $k => $v) {
                        //每次编辑都同步商品名称，主图，图片数组到门店
                        $storeGoodsModel = new VslStoreGoodsModel();
                        $update_data = [
                            'goods_name' => $data_goods['goods_name'],
                            'picture' => $data_goods['picture'],
                            'img_id_array' => $data_goods['img_id_array']
                        ];
                        $storeGoodsModel->save($update_data, ['goods_id' => $goods_id, 'store_id' => $v]);
                    }
                    unset($v);
                }
                hook("goodsSaveSuccess", $data_goods);
                if (!empty($sku_array)) {
                    $sku_list_array = explode('§', $sku_array);
                    if (empty($sku_list_array[0])) {
                        unset($sku_list_array[0]);//删掉空数据
                    }
                    $this->deleteSkuItem($goods_id, $sku_list_array);
                    foreach ($sku_list_array as $k => $v) {
                        $res = $this->addOrUpdateGoodsSkuItem($goods_id, $v, $specArray);
                        if ($res > 1) {
                            //如果此商品有对应的核销门店，修改时就要更新sku信息到门店商品sku表
                            if ($verificationinfo['store_list']) {
                                $storeGoodsSkuModel = new VslStoreGoodsSkuModel();
                                $sku_item = explode('¦', $v);
                                $sku_name = $this->createSkuName($sku_item[0], $specArray);
                                foreach ($verificationinfo['store_list'] as $key => $val) {
                                    $data = [
                                        'goods_id' => $goods_id,
                                        'website_id' => $this->website_id,
                                        'shop_id' => $this->instance_id,
                                        'sku_id' => $res,
                                        'sku_name' => $sku_name,
                                        'attr_value_items' => $sku_item[0],
                                        'price' => $sku_item[1],
                                        'market_price' => $sku_item[2],
                                        'stock' => $sku_item[4],
                                        'store_id' => $val,
                                        'create_time' => time(),
                                        'bar_code' => $sku_item[5]
                                    ];
                                    $result[] = $data;
                                    //更新门店商品表
                                    $data2 = [
                                        'price' => $sku_item[1],
                                        'market_price' => $sku_item[2],
                                        'stock' => $sku_item[4],
                                    ];
                                    $store_goods_model = new VslStoreGoodsModel();
                                    $store_goods_model->save($data2, ['website_id' => $this->website_id, 'goods_id' => $goods_id, 'store_id' => $val]);
                                }
                                unset($val);
                            }
                        }
                        if (!$res) {
                            $error = 1;
                        }
                    }
                    unset($v);
                    if ($result) {
                        $storeGoodsSkuModel->saveAll($result, true);
                    }
                    //如果此商品有对应的核销门店，修改时就要更新商品货号到门店商品sku表
                    if ($verificationinfo['store_list']) {
                        $skuModel = new VslGoodsSkuModel();
                        $sku_list = $skuModel->getQuery(['goods_id' => $goods_id], '*', '');
                        foreach ($sku_list as $k => $v) {
                            $storeGoodsSkuModel = new VslStoreGoodsSkuModel();
                            $data = [
                                'bar_code' => $v['code']
                            ];
                            $storeGoodsSkuModel->save($data,['sku_id' => $v['sku_id'],'website_id' => $this->website_id]);
                        }
                        unset($v);
                    }
                    $goods_sku = new VslGoodsSkuModel();
                    $goods_sku->destroy([
                        'goods_id' => $goods_id,
                        'sku_name' => array(
                            'EQ',
                            ''
                        )
                    ]);

                    // 修改时先删除原来的规格图片
                    $this->deleteGoodsSkuPicture([
                        "goods_id" => $goods_id
                    ]);
                    // sku图片添加
                    $sku_picture_array = array();
                    if (!empty($sku_picture_values)) {
                        $sku_picture_array = $sku_picture_values;
                        foreach ($sku_picture_array as $k => $v) {
                            $res = $this->addGoodsSkuPicture($shopid, $goods_id, $v["spec_id"], $v["spec_value_id"], $v["img_ids"]);
                            if (!$res) {
                                $error = 1;
                            }
                        }
                        unset($v);
                    }
                } else {
                    $sku_data = array(
                        'goods_id' => $goods_id,
                        'sku_name' => '',
                        'market_price' => $market_price,
                        'price' => $price,
                        'promote_price' => $price,
                        'cost_price' => $cost_price,
                        'stock' => $stock,
                        'picture' => 0,
                        'code' => $code,
                        'QRcode' => '',
                        'update_date' => time(),
                        'website_id' => $this->website_id,
                        'sku_max_buy' => $goods_info['max_buy'],
                        'sku_max_buy_time' => $goods_info['max_buy_time']
                    );

                    $goods_sku = new VslGoodsSkuModel();
                    $sku = $goods_sku->getQuery([
                        'goods_id' => $goods_id
                    ], 'sku_id,sku_name', ''); // 当前SKU没有则添加，否则修改
                    if (count($sku) > 1 || (count($sku) == 1 && $sku[0]['sku_name'])) {//多规格商品改为无规格商品，删除原规格数据，新增一条
                        $goods_sku->destroy(['goods_id' => $goods_id]);
                        $res = $goods_sku->save($sku_data);
                        //如果此商品有对应的核销门店，修改时就要更新sku信息到门店商品sku表
                        if ($verificationinfo['store_list']) {
                            $storeGoodsSkuModel = new VslStoreGoodsSkuModel();
                            $result = [];
                            $del_sku_condition = [
                                'website_id' => $this->website_id,
                                'shop_id' => $this->instance_id,
                                'goods_id' => $goods_id,
                            ];
                            $storeGoodsSkuModel->delData($del_sku_condition);
                            foreach ($verificationinfo['store_list'] as $key => $val) {
                                $data = [
                                    'goods_id' => $goods_id,
                                    'website_id' => $this->website_id,
                                    'shop_id' => $this->instance_id,
                                    'sku_id' => $goods_sku->sku_id,
                                    'sku_name' => '',
                                    'attr_value_items' => '',
                                    'price' => $price,
                                    'market_price' => $market_price,
                                    'stock' => $stock,
                                    'store_id' => $val,
                                    'create_time' => time(),
                                    'bar_code' => $item_no,
                                ];
                                $result[] = $data;
                            }
                            unset($val);
                            $storeGoodsSkuModel->saveAll($result, true);
                        }
                    } elseif (count($sku) == 1 && !$sku[0]['sku_name']) {
                        $res = $goods_sku->save($sku_data, ['sku_id' => $sku[0]['sku_id']]);
                        if ($verificationinfo['store_list']) {
                            foreach ($verificationinfo['store_list'] as $key => $val) {
                                $storeGoodsSkuModel = new VslStoreGoodsSkuModel();
                                $data = [
                                    'bar_code' => $item_no,
                                ];
                                $storeGoodsSkuModel->save($data,['store_id' => $val,'goods_id' => $goods_id,'website_id' => $this->website_id]);
                            }
                            unset($val);
                        }
                    } else {
                        $res = $goods_sku->save($sku_data);
                    }
                }
                $this->modifyGoodsPromotionPrice($goods_id);
            }

            // 每次都要重新更新商品属性
            $goods_attribute_model = new VslGoodsAttributeModel();
            $goods_attribute_model->destroy([
                'goods_id' => $goods_id
            ]);
            if (!empty($goods_attribute)) {
                if (!is_array($goods_attribute)) {
                    $goods_attribute_array = json_decode($goods_attribute, true);
                } else {
                    $goods_attribute_array = $goods_attribute;
                }
                if (!empty($goods_attribute_array)) {
                    foreach ($goods_attribute_array as $k => $v) {
                        $goods_attribute_model = new VslGoodsAttributeModel();
                        $data = array(
                            'goods_id' => $goods_id,
                            'shop_id' => $shopid,
                            'attr_value_id' => $v['attr_value_id'],
                            'attr_value' => $v['attr_value'],
                            'attr_value_name' => $v['attr_value_name'],
                            'sort' => $v['sort'],
                            'create_time' => time(),
                            'website_id' => $this->website_id,
                        );
                        $goods_attribute_model->save($data);
                    }
                    unset($v);
                }
            }

            if ($error == 0) {
                $this->goods->commit();
                $this->addOrUpdateGoodsToEs($goods_id);
                return $goods_id;
            } else {
                $this->goods->rollback();
                return 0;
            }
        } catch
        (\Exception $e) {
            recordErrorLog($e);
            $this->goods->rollback();
            return $e->getMessage();
        }
        return 0;

        // TODO Auto-generated method stub
    }

    /**
     * 新建商品数据时候，记录商品创建时候的原始sku_id
     * @param $goods_id
     * @return false|int
     */
    public function setSupplierGoodsSkuIdAndSaveGoodsOriginalSkuIds ($goods_id)
    {
        $goods_sku = new VslGoodsSkuModel();
        $sku_ids = $goods_sku->Query(['website_id' => $this->website_id,'goods_id' => $goods_id],'sku_id');
        $original_sku_ids = '';
        foreach($sku_ids as $sku_id){
            $original_sku_ids .= $sku_id.',';
        }
        unset($sku_id);
        $original_sku_ids = rtrim($original_sku_ids,',');

        return $this->updateGoods(['goods_id'=>$goods_id], ['original_sku_ids'=> $original_sku_ids]);
    }

    /**
     * 逻辑判断：是否供应商的商品需要同步到商家、店铺
     * @param $supplier_goods_id
     * @param $goodsInfo
     * @return \multitype
     */
    public function synSupplierGoodsData ($supplier_goods_id,array $goodsInfo=[])
    {
        Db::startTrans();
        try {
            if (!$supplier_goods_id) {
                return;
            }
            $goods_info = $this->goods->getInfo(['goods_id' => $supplier_goods_id], 'shop_list,shop_id,supplier_id,stock,cost_price,payment_type,supplier_rebate,shipping_fee,shipping_fee_id,shipping_fee_type,state');
            if ($goods_info['shop_list']!==''){
                $shop_ids = explode(',', $goods_info['shop_list']);
                foreach ($shop_ids as $shop_id){
                    $conditon = [
                        'website_id' => $this->website_id,
                        'shop_id'   => $shop_id,
                        'supplier_id' => 0,
                        'supplier_goods_id' => $supplier_goods_id,
                    ];
                    $synData = [
                        'stock' => $goods_info['stock'],
                        'cost_price' => $goods_info['cost_price'],
                        'payment_type' => $goods_info['payment_type'],
                        'supplier_rebate' => $goods_info['supplier_rebate'],
                        'shipping_fee' => $goods_info['shipping_fee'],
                        'shipping_fee_id' => $goods_info['shipping_fee_id'],
                        'shipping_fee_type' => $goods_info['shipping_fee_type'],
                    ];
                    if ($goods_info['state'] == 0){
                        $synData['supplier_operation'] = 2;//下架
                    }
                    $this->updateGoods($conditon, $synData);
                }
            }
            Db::commit();
            return AjaxReturn(SUCCESS);
        }catch (\Exception $e){
            recordErrorLog($e);
            Db::rollback();
            return AjaxReturn(FAIL,[],$e->getMessage());
        }

    }


    /**
     * 记录供应商商品的被挑选的店铺，如果店铺挑选过该商品，且shop_id在shop_list_second中，供销市场就要同时展示该商品数据
     * @param     $supplier_goods_id
     * @param int $website_id
     * @param int $supplier_id
     * @return false|int
     */
    public function recodeSupplierGoodsPickedShopList ($supplier_goods_id,$website_id =0,$supplier_id=0)
    {
        $website_id = $website_id ?: $this->website_id;
        $supplier_id = $supplier_id ?: $this->supplier_id;
        $goodsM = new VslGoodsModel();//商品表更新
        $condition = ['website_id' => $website_id, 'supplier_id' => $supplier_id, 'goods_id' => $supplier_goods_id];
        $shop_list = $this->getGoodsDetailByCondition($condition, 'shop_list')['shop_list'];
        return $this->updateGoods($condition, ['shop_list_second' => $shop_list]);
    }


    /**
     * 是否供应商的商品被上架的店家数等于重新覆盖上架数
     * 因为考虑供应商商品已被店铺上架，但供应商商品重新编辑为‘新’商品到供销市场供选择
     * @param $supplier_goods_id [供应商商品id]
     * @return bool
     */
    public function isPickNumsEqPickCoverNums ($supplier_goods_id)
    {
        $goodsInfo = $this->getGoodsDetailById($supplier_goods_id);
        $shopNums = count(explode(',',$goodsInfo['shop_list']));
        if ($goodsInfo['supplier_pick_cover_nums'] != $shopNums){
            return false;
        }
        return true;
    }

    /**
     * 删除挑选过该商品规格的商家、店铺商品数据
     * @param $del_goods_id [需要删除的商家、店铺的商品id]
     * @param $original_sku_ids [供应商商品创建时候的规格id用 ，分割的]
     * @return int
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function delShopHasPickOfSupplierGoodsSku ($del_goods_id,$original_sku_ids)
    {
        //1、查出店铺原绑定过该商品的规格sku_id
        $original_sku_arr = explode(',',$original_sku_ids);
        $sku_con = [
            'website_id'    => $this->website_id,
            'goods_id'      => $del_goods_id,
            'supplier_sku_id'      => ['in', $original_sku_arr],
        ];
        $goodsSkuM = new VslGoodsSkuModel();
        $delGoodsSkuList = $goodsSkuM->Query($sku_con,'sku_id');
        if ($delGoodsSkuList){
            return $goodsSkuM->delData(['sku_id' => ['in',$delGoodsSkuList]]);
        }
    }

    /**
     * 删除店铺挑选了的供应商商品及关联的商品规格数据
     */
    public function delShopPickedSupplierGoodsAndGoodsSku ($supplier_goods_id,$website_id=0, $shop_id=0)
    {
        $website_id = $website_id?:$this->website_id;
        $shop_id = $shop_id?:$this->instance_id;
        $goodsM = new VslGoodsModel();//列表查询
        $goodsObj = $goodsM::get($condition = [
            'website_id'=> $website_id,
            'shop_id'=> $shop_id,
            'supplier_goods_id' => $supplier_goods_id
        ]);
        $goodsObj->sku()->delete();
        return $goodsObj->delete();
    }

    /**
     * sku_id关联的goods 信息
     * @param $sku_id
     * @return VslGoodsSkuModel
     * @throws \think\Exception\DbException
     */
    public function getGoodsInfoByGoodsSku ($sku_id)
    {
        $skuModel = new VslGoodsSkuModel();
        return $skuModel::get(['vsl_goods_sku.sku_id' =>$sku_id],['goods']);
    }
    /**
     * 获取装修涉及的营销活动商品列表
     * @param $data
     * @return array|\data\model\multitype
     */
    public function getCustomPromotionGoodsList ($data)
    {
        $promotion_type = $data['promotion_type'];
        $page_index = $data['page_index'];
        $page_size = $data['page_size'];
        $search_text = $data['search_text'];
        $goods_type = $data['goods_type'];//0自营 1全平台 2店铺

        if ($promotion_type == 2){/*拼团*/
            $group_condition = [
                'ng.website_id' => $this->website_id,
                'ng.shop_id' => $this->instance_id,
                'ng.status' => ['neq',2],
            ];
            if($goods_type==1){
                unset($group_condition['ng.shop_id']);
            }
            if ($search_text) {
                $group_condition['vg.goods_name'] = ['like', "%" . $search_text . "%"];
            }
            $groupServer = new GroupShoppingServer();
            $list = $groupServer->groupShoppingList($page_index, $page_size, $group_condition);
            if ($list['data']){
                foreach ($list['data'] as $k => $v){
                    $list['data'][$k]['price'] = $v['sku_price']['min_price'];
                    $list['data'][$k]['pic_cover'] = $v['pic_cover'] ?getApiSrc($v['pic_cover']) : '';
                    $list['data'][$k]['pic_cover_mid'] = $v['pic_cover_mid'] ?getApiSrc($v['pic_cover_mid']) : '';
                    if ($this->instance_id == 0) {
                        $list['data'][$k]['goods_type'] = $v['shop_id'] >0 ? 1 : 0;
                    }else{
                        $list['data'][$k]['goods_type'] = 2;
                    }
                }
            }
        }elseif($promotion_type == 3){/*预售*/
            $presell_condition = [
                'ng.website_id' => $this->website_id,
                'ng.shop_id' => $this->instance_id,
                'ng.end_time' => ['>',time()],
            ];
            if($goods_type==1){
                unset($presell_condition['ng.shop_id']);
            }
            if ($search_text) {
                $presell_condition['vg.goods_name'] = ['like', "%" . $search_text . "%"];
            }
            $presellServer = new PresellServer();
            $list = $presellServer->presellGoodsList($page_index,$page_size,$presell_condition);
            if ($list['data']){
                foreach ($list['data'] as $k => $v){
                    $list['data'][$k]['pic_cover'] = $v['pic_cover']?getApiSrc($v['pic_cover']):'';
                    $list['data'][$k]['pic_cover_mid'] = $v['pic_cover_mid']?getApiSrc($v['pic_cover_mid']):'';
                    $list['data'][$k]['price'] = $v['allmoney'];
                    $list['data'][$k]['buy_num'] = $presellServer->getPresellBuyNum($v['id']);
                    if ($this->instance_id == 0) {
                        $list['data'][$k]['goods_type'] = $v['shop_id'] >0 ? 1 : 0;
                    }else{
                        $list['data'][$k]['goods_type'] = 2;
                    }
                }
            }
        }elseif($promotion_type == 4){/*砍价*/
            $bargain_condition = [
                'b.website_id' => $this->website_id,
                'b.shop_id' => $this->instance_id,
                'end_bargain_time' => ['>',time()],
            ];
            if($goods_type==1){
                unset($bargain_condition['b.shop_id']);
            }
            if ($search_text) {
                $bargain_condition['b.goods_name'] = ['like', "%" . $search_text . "%"];
            }
            $order = 'bargain_id DESC';
            $bargainServer = new bargainServer();
            $list = $bargainServer->bargainList($page_index, $page_size, $bargain_condition, $order);
            if ($list['data']){
                foreach ($list['data'] as $k => $v){
                    $list['data'][$k]['pic_cover'] = $v['pic_cover']?getApiSrc($v['pic_cover']):'';
                    $list['data'][$k]['pic_cover_mid'] = $v['pic_cover_mid']?getApiSrc($v['pic_cover_mid']):'';
                    $list['data'][$k]['price'] = $v['start_money'];
                    $list['data'][$k]['buy_num'] = $bargainServer->getBargianBuyNum($v['bargain_id']);
                    if ($this->instance_id == 0) {
                        $list['data'][$k]['goods_type'] = $v['shop_id'] >0 ? 1 : 0;
                    }else{
                        $list['data'][$k]['goods_type'] = 2;
                    }
                }
            }
        }elseif($promotion_type == 5){/*限时抢购*/
            $discountSer = new DiscountServer();
            $goods_list = $discountSer->getStartingPromotionDiscountData($this->website_id, $this->instance_id,$page_size,$goods_type,$search_text);
            $list['data'] = $goods_list ?:[];
        }elseif($promotion_type == 6){/*幸运拼*/
            $luckspell_condition = [
                'ng.website_id' => $this->website_id,
                'ng.shop_id' => $this->instance_id,
                'ng.status' => ['neq',2]
            ];
            if($goods_type==1){
                unset($luckspell_condition['ng.shop_id']);
            }
            $luckspell = new LuckySpellServer();
            $luckList = $luckspell->luckySpellList($page_index,$page_size,$luckspell_condition);
            $list['data'] = $luckList['data'] ?:[];
        }

        return $list;
    }

    /**
     * 获取装修涉及的营销活动商品列表
     * @param $data
     * @return array|\data\model\multitype
     */
    public function getCustomPromotionGoodsListForWap ($data)
    {
        $promotion_type = $data['promotion_type'];//促销类型 0无促销，1秒杀，2团购, 3预售, 4砍价，5限时折扣, 6幸运拼
        $page_index = $data['page_index'];
        $page_size = $data['page_size'];
        $order = $data['order'];//1价格 2数量 3开始时间 4结束时间 5收藏数
        $sort = $data['sort'];//升序asc 降序 desc
        $goods_type = $data['goods_type'];//0自营 1全平台 2店铺(需要传shop_id)
        $shop_id = $data['shop_id'];
        $recommend_type = $data['recommend_type'];//推荐类型0自动 1手动
        $goods_ids = $data['goods_ids'];//$recommend_type=1才有
        $goods_ids = $goods_ids?explode(',',$goods_ids):[];
        //秒杀数据
        $condition_time = $data['condition_time'];
        $condition_day = $data['condition_day'];
        $tag_status = $data['tag_status'];

        $return_list = [];
        $order_sort = '';
        $album = new AlbumPictureModel();
        if ($promotion_type ==1){/*秒杀*/
            $seckill_condition = [
                'ns.website_id' => $this->website_id,
                'nsg.check_status' => 1,
                'nsg.del_status' => 1,
            ];
            if($condition_time == 'good_rushed' && $condition_day == 'good_rushed'){
                //当前时间大于开始时间，小于结束时间
                $time = time();
                $past_time = $time - 24*3600;
                $seckill_condition['ns.seckill_now_time'] = [
                    ['>=', $past_time],['<=', $time]
                ];
            }else{
                if($condition_day == 'today'){
                    $seckill_name = $condition_time;
                    $seckill_time = strtotime(date('Y-m-d'));
                }elseif($condition_day == 'tomorrow'){
                    $seckill_name = $condition_time;
                    $seckill_time = strtotime(date('Y-m-d', strtotime('+1 day')));
                }
                $seckill_condition['ns.seckill_name'] = $seckill_name;
                $seckill_condition['ns.seckill_time'] = $seckill_time;
            }

            if ($recommend_type == 1){
                $seckill_condition['nsg.goods_id'] = ['in',$goods_ids];
                $order_sort = 'ns.create_time desc';
            }else{
                switch ($order)
                {
                    case 2:
                        $order_sort .= ' nsg.seckill_sales'.' '.$sort;//销量
                        break;
                    case 3:
                        $order_sort .= ' ns.seckill_time'.' '.$sort;//活动申请时间
                        break;
                    case 5:
                        $order_sort .= ' g.collects'.' '.$sort;//商品收藏数
                        break;
                }
            }
            $seckillServer = new SeckillServer();
            $list = $seckillServer->getSeckillGoodsList($page_index, $page_size, $seckill_condition,$order_sort);
            $list = objToArr($list);
            if ($list['data']){
                $seck_goods_mdl = new VslSeckGoodsModel();
                foreach ($list['data'] as $k => $data){
                    //查询该商品sku所有的活动库存、剩余库存、虚拟抢购量
                    $seckill_id = $data['seckill_id'];
                    $goods_id = $data['goods_id'];
                    $sec_goods_info = $seck_goods_mdl->getInfo(['goods_id' => $goods_id, 'seckill_id' => $seckill_id], 'sum(seckill_num) as seckill_num, sum(remain_num) as remain_num, sum(seckill_vrit_num) as seckill_vrit_num');
                    //已抢购 活动库存-剩余库存+虚拟抢购量
                    $robbed_num = (int)$sec_goods_info['seckill_num'] - ((int)$sec_goods_info['remain_num']);// 调整：虚拟抢购量显示在进度条中
                    //已抢购百分数
                    $robbed_percent = round($robbed_num/(int)$sec_goods_info['seckill_num']*100)."％"  ;
                    //获取当前的时间点和今天日期
                    $h = (int)date('H');
                    if($condition_day == 'good_rushed' && $condition_time == 'good_rushed'){
                        $rob_time = '马上抢';
                    }elseif($condition_day == 'today' && $h == $condition_time){
                        $rob_time = '马上抢';
                    }elseif($condition_day == 'today' && $h < $condition_time){
                        $rob_time = $seckill_name.'点开抢';
                    }elseif($condition_day == 'tomorrow' && $h > $condition_time){
                        $rob_time = '明日'.$seckill_name.'点开抢';
                    }
                    //判断商品是否收藏过
                    $member_favorite_mdl = new VslMemberFavoritesModel();
                    $is_collection = $member_favorite_mdl->getInfo(
                        ['fav_id'=>$data['goods_id'],
                         'fav_type'=>'goods',
                         'seckill_id'=>$data['seckill_id']],
                        '*');
                    $is_collection = $is_collection ? true : false;
                    $return_list[$k]['goods_id'] = $data['goods_id'];
                    $pic_cover = $album->getInfo(['pic_id' => $data['activity_pic']], 'pic_cover')['pic_cover'] ? : '';
                    $return_list[$k]['activity_pic'] = getApiSrc($pic_cover);
                    $return_list[$k]['goods_name'] = $data['goods_name'];
                    $return_list[$k]['seckill_id'] = $data['seckill_id'];
                    $return_list[$k]['goods_id'] = $data['goods_id'];
                    $return_list[$k]['goods_img'] = $data['pic_cover_big'];
                    $return_list[$k]['remain_num'] = $data['remain_num'];
                    $return_list[$k]['robbed_num'] = $robbed_num;
                    $return_list[$k]['seckill_num'] = (int)$data['seckill_num'];
                    $return_list[$k]['robbed_percent'] = $robbed_percent;
                    $return_list[$k]['price'] = (float)$data['price'];
                    $return_list[$k]['seckill_price'] = (float)$data['seckill_price'];
                    $return_list[$k]['rob_time'] = $rob_time;
                    $return_list[$k]['condition_day'] = $condition_day;
                    $return_list[$k]['condition_time'] = $condition_time;
                    $return_list[$k]['tag_status'] = $tag_status;
                    $return_list[$k]['is_collection'] = $is_collection;
                }
                $page_data['total_count'] = $list['total_count'];
                $page_data['page_count'] = $list['page_count'];
            }
        } elseif ($promotion_type == 2) {/*拼团*/
            $group_condition = [
                'ng.website_id' => $this->website_id,
                'ng.shop_id' => $shop_id,
                'ng.status' => ['neq',2],
            ];
            if($goods_type==1){
                unset($group_condition['ng.shop_id']);
            }elseif($goods_type==0){
                $group_condition['ng.shop_id'] = 0;
            }
            if ($recommend_type == 1){
                $group_condition['ng.goods_id'] = ['in',$goods_ids];
                $order_sort = 'ng.create_time desc';
            }else{
                switch ($order)
                {
                    case 1:
                        $order_sort .= ' ngg.group_price'.' '. $sort;//价格
                        break;
                    case 2:
                        $order_sort .= ' ng.create_time'.' '.$sort;//已团数量goods_total
                        break;
                    case 3:
                        $order_sort .= ' ng.create_time'.' '.$sort;//开始时间
                        break;
                    case 4:
                        $order_sort .= ' (ng.create_time + ng.group_time)'.' '.$sort;//结束时间
                        break;
                }
            }
            $groupServer = new GroupShoppingServer();
            $list = $groupServer->groupShoppingList($page_index, $page_size, $group_condition,$order_sort);
            $list = objToArr($list);
            if ($list['data']){
                foreach ($list['data'] as $k => $v){
                    $return_list[$k]['group_price'] = $v['group_price'];
                    $return_list[$k]['price'] = $v['price'];
                    $return_list[$k]['group_num'] = $v['tuxedo_situation']['tuxedo'];//参团人数
                    $return_list[$k]['goods_total'] = $v['goods_total'];//已团件数
                    $return_list[$k]['user'] = $v['user'];//参团会员信息
                    $return_list[$k]['group_id'] = $v['group_id'];
                    $return_list[$k]['goods_id'] = $v['goods_id'];
                    $pic_cover = $album->getInfo(['pic_id' => $v['activity_pic']], 'pic_cover')['pic_cover'] ? : '';
                    $return_list[$k]['activity_pic'] = getApiSrc($pic_cover);
                    $return_list[$k]['goods_name'] = $v['goods_name'];
                    $return_list[$k]['pic_cover'] = $v['pic_cover'] ?getApiSrc($v['pic_cover']) : '';
                    $return_list[$k]['pic_cover_mid'] = $v['pic_cover_mid'] ?getApiSrc($v['pic_cover_mid']) : '';
                }
                if($order == 2){
                    $sort = ($sort == 'desc'|| $sort == 'DESC') ? SORT_DESC :SORT_ASC;
                    array_multisort(array_column($return_list,'goods_total'),$sort,$return_list);/*按已团件数排序*/
                }
                $page_data['total_count'] = $list['total_count'];
                $page_data['page_count'] = $list['page_count'];
            }
        } elseif($promotion_type == 3) {/*预售*/
            $presell_condition = [
                'ng.website_id' => $this->website_id,
                'ng.shop_id' => $shop_id,
//                'ng.status' => ['neq',3,],
                'ng.end_time' => ['>',time()],
            ];
            if($goods_type==1){
                unset($presell_condition['ng.shop_id']);
            }elseif($goods_type==0){
                $presell_condition['ng.shop_id'] = 0;
            }
            if ($recommend_type == 1){/*手动*/
                $presell_condition['ng.goods_id'] = ['in',$goods_ids];
                $order_sort = 'ng.create_time desc';
            }else{
                $presell_condition['ng.start_time'] = ['<', time()];
                switch ($order)
                {
                    case 1:
                        $order_sort .= ' ng.firstmoney'.' '. $sort;//定金
                        break;
                    case 2:
                        $order_sort .= ' ng.create_time'.' '.$sort;//已订数量goods_total
                        break;
                    case 3:
                        $order_sort .= ' ng.start_time'.' '.$sort;//开始时间
                        break;
                    case 4:
                        $order_sort .= ' ng.end_time'.' '.$sort;//结束时间
                        break;
                }
            }
            $presellServer = new PresellServer();
            $list = $presellServer->presellGoodsList($page_index,$page_size,$presell_condition,$order_sort);
            $list = objToArr($list);
            if ($list['data']){
                foreach ($list['data'] as $k => $v){
                    $return_list[$k]['pic_cover'] = $v['pic_cover']?getApiSrc($v['pic_cover']):'';
                    $return_list[$k]['pic_cover_mid'] = $v['pic_cover_mid']?getApiSrc($v['pic_cover_mid']):'';
                    $return_list[$k]['allmoney'] = $v['allmoney'];
                    $return_list[$k]['firstmoney'] = $v['firstmoney'];
                    $return_list[$k]['finalmoney'] = bcsub($v['allmoney'],$v['firstmoney'],2);
                    $return_list[$k]['buy_num'] = $presellServer->getPresellBuyNum($v['id']) + $v['vrnum'];
                    $return_list[$k]['presell_id'] = $v['id'];
                    $return_list[$k]['presell_name'] = $v['name'];
                    $return_list[$k]['goods_id'] = $v['goods_id'];
                    $pic_cover = $album->getInfo(['pic_id' => $v['activity_pic']], 'pic_cover')['pic_cover'] ? : '';
                    $return_list[$k]['activity_pic'] = getApiSrc($pic_cover);
                    $return_list[$k]['goods_name'] = $v['goods_name'];
                }
                if($order == 2){
                    $sort = ($sort == 'desc'|| $sort == 'DESC') ? SORT_DESC :SORT_ASC;
                    array_multisort(array_column($return_list,'buy_num'),$sort,$return_list);/*按已订数量排序*/
                }
                $page_data['total_count'] = $list['total_count'];
                $page_data['page_count'] = $list['page_count'];
            }
        } elseif($promotion_type == 4) {/*砍价*/
            $bargain_condition = [
                'b.website_id' => $this->website_id,
                'b.shop_id' => $shop_id,
                'end_bargain_time' => ['>',time()],
            ];
            if($goods_type==1){
                unset($bargain_condition['b.shop_id']);
            }elseif($goods_type==0){
                $bargain_condition['b.shop_id'] = 0;
            }
            if ($recommend_type == 1){/*手动*/
                $bargain_condition['b.goods_id'] = ['in',$goods_ids];
                $order_sort = 'b.bargain_id desc';
            }else{
                switch ($order)
                {
                    case 1:
                        $order_sort .= ' b.first_bargain_money'.' '. $sort;//起砍价
                        break;
                    case 2:
                        $order_sort .= ' b.bargain_id'.' '.$sort;//已砍数量goods_total
                        break;
                    case 3:
                        $order_sort .= ' b.start_bargain_time'.' '.$sort;//开始时间
                        break;
                    case 4:
                        $order_sort .= ' b.end_bargain_time'.' '.$sort;//结束时间
                        break;
                }
            }

            $bargainServer = new bargainServer();
            $list = $bargainServer->bargainList($page_index, $page_size, $bargain_condition, $order_sort);
            $list = objToArr($list);
            if ($list['data']){
                foreach ($list['data'] as $k => $v){
                    $return_list[$k]['pic_cover'] = $v['pic_cover']?getApiSrc($v['pic_cover']):'';
                    $return_list[$k]['pic_cover_mid'] = $v['pic_cover_mid']?getApiSrc($v['pic_cover_mid']):'';
                    $return_list[$k]['price'] = $v['price'];
                    $return_list[$k]['start_money'] = $v['start_money'];
                    $return_list[$k]['buy_num'] = $bargainServer->getBargianBuyNum($v['bargain_id']);
                    $return_list[$k]['bargain_id'] = $v['bargain_id'];
                    $return_list[$k]['bargain_name'] = $v['bargain_name'];
                    $return_list[$k]['goods_id'] = $v['goods_id'];
                    $pic_cover = $album->getInfo(['pic_id' => $v['activity_pic']], 'pic_cover')['pic_cover'] ? : '';
                    $return_list[$k]['activity_pic'] = getApiSrc($pic_cover);
                    $return_list[$k]['goods_name'] = $v['goods_name'];
                }
                if($order == 2){
                    $sort = ($sort == 'desc'|| $sort == 'DESC') ? SORT_DESC :SORT_ASC;
                    array_multisort(array_column($return_list,'buy_num'),$sort,$return_list);/*按已砍数量排序*/
                }
                $page_data['total_count'] = $list['total_count'];
                $page_data['page_count'] = $list['page_count'];
            }
        }elseif($promotion_type == 5){/*限时抢购*/
            $goods_ids = $recommend_type == 1 ? $goods_ids : [];//0自动（默） 1手动
            $discountSer = new DiscountServer();
            $goods_list = $discountSer->getStartingPromotionDiscountData($this->website_id, $shop_id,$page_size,$goods_type,'',$goods_ids);
            if ($recommend_type == 0 && $sort && $order && $goods_list) {
                $sort = ($sort == 'ASC'|| $sort == 'asc') ? SORT_ASC : SORT_DESC;
                //处理排序
                $order = (int)$order;
                switch ($order)
                {
                    case 1:/*抢购价*/
                        $goods_list = my_array_multisort($goods_list,'discount_price', $sort);
                        break;
                    case 3:/*开始时间*/
                        $goods_list = my_array_multisort($goods_list,'start_time', $sort);
                        break;
                    case 4:/*结束时间*/
                        $goods_list = my_array_multisort($goods_list,'end_time', $sort);
                        break;
                }
            }

            $return_list = $goods_list;
            $page_data['total_count'] = $page_data['page_count'] = count($goods_list);
        }elseif($promotion_type == 6){/*幸运拼*/
            $luckspell_condition = [
                'ng.website_id' => $this->website_id,
                'ng.shop_id' => $shop_id,
                'ng.status' => ['neq',2]
            ];
            if($goods_type==1){
                unset($luckspell_condition['ng.shop_id']);
            }
            if ($recommend_type == 1){/*手动*/
                $luckspell_condition['ng.goods_id'] = ['in',$goods_ids];
            }
            
            switch ($order)
            {
                case 1:
                    $order_sort = 'ngg.group_price '.$sort;/*拼团价*/
                    break;
                case 2:
                    $ext_fields = ' SUM(CASE vgsr.status WHEN 1 THEN 1 ELSE 0 END) + ng.luckyspell_suc_count AS count ';/*成团次数*/
                    $order_sort = 'count '.$sort;
                    break;
                default:
                    $order_sort = 'ng.create_time desc';
                    break;
            }
            
            $luckspell = new LuckySpellServer();
            $luck_list = $luckspell->luckySpellList($page_index,$page_size,$luckspell_condition,$order_sort,$ext_fields);
            $luck_list = objToArr($luck_list);
            $return_list = $luck_list['data'];
        }
        
        $list['data'] = $return_list;
        $list['total_count'] = $page_data['total_count'];
        $list['page_count'] = $page_data['page_count'];
        return $list;
    }
    
    /*
     * 根据id获取商品信息
     */
    public function getGoodsDetailById($goodsId = 0, $field = '*', $pic = 0, $cate = 0, $sup = 0, $shop = 0){
        if(!$goodsId){
            return [];
        }
        $goodsInfo = [];
        if(config('ecs.host')){
            
            $esSer = new EcService();
            $goodsInfo = $esSer->table('goods')->field('*')->findById((int)$goodsId);
            if($goodsInfo){
                return $goodsInfo;
            }
            $goodsInfoSql = $this->getGoodsInfoFromSql($goodsId, '*', 1, 1, 1, 1);
            if($goodsInfoSql){
                $esSer->table('goods')->insert($goodsInfoSql, $goodsId);
                return $goodsInfoSql;
            }
        }else{
            
            $goodsInfo = $this->getGoodsInfoFromSql($goodsId, $field, $pic, $cate, $sup, $shop);
        }
        return $goodsInfo;
    }
    /*
     * 根据条件获取单个商品信息
     */
    public function getGoodsDetailByCondition($condition = [], $field = '*', $pic = 0, $cate = 0, $sup = 0, $shop = 0){
        if(config('ecs.host')){
            $esSer = new EcService();
            $goodsInfo = $esSer->table('goods')->condition($condition)->field('*')->find();
        }else{
            $goodsInfo = $this->getGoodsInfoFromSql($condition, $field, $pic, $cate, $sup, $shop);
        }
        return $goodsInfo;
    }
    
    /**
     * 修改商品
     * @param type $condition
     * @param type $data
     * @param type $goodsId
     * @return boolean
     */
    public function updateGoods($condition = [], $data = [], $goodsId = 0){
        if(!$data || (!$condition && !$goodsId)){
            return false;
        }
        $goodsModel = new VslGoodsModel();
        try{
            if($goodsId){//更新条件只需要goods_id
                $res = $goodsModel->where(['goods_id' => $goodsId])->update($data);
                if(config('ecs.host')){
                    $ecSer = new EcService();
                    $ecSer->table('goods')->update($data, $goodsId);
                    $ecSer->table('goods')->refreshIndex();
                }
                return $res;
            }
            $goodsIds = $goodsModel->Query($condition, 'goods_id');
            if(!$goodsIds){
                return true;
            }
            debugLog($goodsIds, 'updateGoods1==>');
            $res = $goodsModel->where($condition)->update($data);
            debugLog($res, 'updateGoods2==>');
            if(config('ecs.host')){
                $ecSer = new EcService();
                foreach($goodsIds as $val){
                    $ecSer->table('goods')->update($data, $val);
                }
                unset($val);
                $ecSer->table('goods')->refreshIndex();
            }
            return $res;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     *
     * @param type $condition
     * @param type $field
     * @param type $value
     * @param type $goodsId
     * @param type $type 0表示加,1表示减
     * @return boolean
     */
    public function updateGoodsIncOrDec($condition = [], $field = '', $value = '',  $goodsId = 0, $type = 0){
        if(!$field || (!$condition && !$goodsId)){
            return false;
        }
        $goodsModel = new VslGoodsModel();
        $redis = connectRedis();
        if($goodsId){//更新条件只需要goods_id
            if(!$type){
                $res = $goodsModel->where(['goods_id' => $goodsId])->setInc($field, $value);
            }else{
                $res = $goodsModel->where(['goods_id' => $goodsId])->setDec($field, $value);
            }
            $redis->del('goods_'. $goodsId);
            return $res;
        }
        $goodsIds = $goodsModel->Query($condition, 'goods_id');
        if(!$goodsIds){
            return true;
        }
        if(!$type){
            $res = $goodsModel->where($condition)->setInc($field, $value);
        }else{
            $res = $goodsModel->where($condition)->setDec($field, $value);
        }
        foreach($goodsIds as $val){
            $redis->del('goods_'. $val);
        }
        unset($val);
        return $res;
    }
    
    /**
     * 根据条件查询商品列表
     * @param type $condition
     * @param type $field
     * @param type $order
     * @return type
     */
    public function getGoodsListByCondition($condition = [], $field = '', $order = ''){
        if(config('ecs.host')){
            if($field && $field != '*'){
                $field = explode(',', $field);
            }
            $esSer = new EcService();
            $list = $esSer->table('goods')->condition($condition)->field($field)->selectAll();
        }else{
            $list = $this->goods->getQuery($condition, $field, $order);
            if($list && ($field == '*' || !$field || strpos($field,'picture') !== false)){
                $pictureModel = new AlbumPictureModel();
                foreach($list as $key => $val){
                    if(!$val['picture']){
                        $list[$key]['album_picture'] = [];
                        continue;
                    }
                    $list[$key]['album_picture'] = $pictureModel->getInfo(['pic_id' => $val['picture']],'pic_cover,pic_cover_small,pic_cover_mid,pic_cover_big,pic_cover_micro');
                }
            }
        }
        return $list;
    }
    
    /**
     * 添加或修改商品到缓存
     * @param type $goods_id
     * @return boolean
     */
    public function addOrUpdateGoodsToEs($goods_id = 0){
        if(!config('ecs.host')){
            return true;
        }
        if(!$goods_id){
            return false;
        }
        $esSer = new EcService();
        $goodsInfo = $this->getGoodsInfoFromSql($goods_id, '*', 1, 1, 1, 1);
        if(!$goodsInfo){
            return false;
        }
        $checkIsIn = $esSer->table('goods')->findById($goods_id);
        if($checkIsIn){
            $res = $esSer->table('goods')->update($goodsInfo, $goods_id);
        }else{
            $res = $esSer->table('goods')->insert($goodsInfo, $goods_id);
        }
        return $res;
    }

    /**
     * 从数据库获取商品信息重新组装数据
     * @param type $goods_id
     * @param type $field
     * @param type $pic
     * @param type $cate
     * @param type $sup
     * @param type $shop
     * @return string
     */
    public function getGoodsInfoFromSql($goods_id = 0, $field = '*', $pic = 0, $cate = 0, $sup = 0, $shop = 0){
        if(!$goods_id){
            return [];
        }
        $condition = ['goods_id' => $goods_id];
        if(is_array($goods_id)){
            $condition = $goods_id;
        }
        //由于商品表不存在shop_name 需要先移除该字段
        if($field != '*'){
            $str = explode(',',$field);
            if(in_array('shop_name',$str)){
                foreach ($str as $k3 => $v3) {
                    if($v3 == 'shop_name'){
                        unset($str[$k3]);
                    }
                }
            }
            if(count($str) > 0){
                $field = implode(',',$str);
            }else{
                $field = '*';
            }
        }
        if(config('database.last_hostport')){
            $conn = config('database');
            $conn['hostport'] = $conn['last_hostport'];
            $conn['params'] = [];
            $goodsInfo = Db::table('vsl_goods')->connect($conn)->where($condition)->field($field)->find();
            if(!$goodsInfo){
                return [];
            }
            if($pic){
                $goodsInfo['album_picture'] = Db::table('sys_album_picture')->connect($conn)->where(['pic_id' => $goodsInfo['picture']])->field('pic_cover,pic_cover_small,pic_cover_mid,pic_cover_big,pic_cover_micro')->find();
            }
            if($cate){
                $categoryInfo = Db::table('vsl_goods_category')->connect($conn)->where(['category_id' => $goodsInfo['category_id']])->field('category_name,short_name')->find();
                $goodsInfo['category_name'] = $categoryInfo ? $categoryInfo['category_name'] : '';
                $goodsInfo['category_short_name'] = $categoryInfo ? $categoryInfo['short_name'] : '';
            }
            $goodsInfo['supplier_name'] = '';
            $goodsInfo['supplier_status'] = 0;
            if($sup && getAddons('supplier', $goodsInfo['website_id']) && $goodsInfo['supplier_info']){
                $supplierInfo = Db::table('vsl_supplier')->connect($conn)->where(['website_id' => $goodsInfo['website_id'], 'shop_id' => $goodsInfo['shop_id'], 'supplier_id' => $goodsInfo['supplier_info']])->field('supplier_name, status')->find();
                $goodsInfo['supplier_name'] = $supplierInfo ? $supplierInfo['supplier_name'] : '';
                $goodsInfo['supplier_status'] = $supplierInfo ? $supplierInfo['status'] : 0;
            }
            $goodsInfo['shop_name'] = '';
            $goodsInfo['shop_state'] = 0;
            if($shop && getAddons('shop', $goodsInfo['website_id'])){
                $shopInfo = Db::table('vsl_shop')->connect($conn)->where(['website_id' => $goodsInfo['website_id'], 'shop_id' => $goodsInfo['shop_id']])->field('shop_name,shop_state')->find();
                $goodsInfo['shop_name'] = $shopInfo['shop_name'];
                $goodsInfo['shop_state'] = $shopInfo['shop_state'];
            }
            //组装权限信息,便于移动端根据会员权限检索
            $goodsInfo['browse_auth_u'] = '';
            $goodsInfo['browse_auth_d'] = '';
            $goodsInfo['browse_auth_s'] = '';
            $discountInfo = Db::table('vsl_goods_discount')->connect($conn)->where(['goods_id' => $goodsInfo['goods_id']])->field('browse_auth_u,browse_auth_d,browse_auth_s')->find();
            if($discountInfo){
                $goodsInfo['browse_auth_u'] = $discountInfo['browse_auth_u'] ? ','.$discountInfo['browse_auth_u'].',' : '';
                $goodsInfo['browse_auth_d'] = $discountInfo['browse_auth_d'] ? ','.$discountInfo['browse_auth_d'].',' : '';
                $goodsInfo['browse_auth_s'] = $discountInfo['browse_auth_s'] ? ','.$discountInfo['browse_auth_s'].',' : '';
            }
            //组装sku最大价格和最小价格信息,便于移动端根据价格区间权限检索
            $goodsInfo['max_price'] = Db::table('vsl_goods_sku')->connect($conn)->where(['goods_id' => $goodsInfo['goods_id']])->max('market_price');
            $goodsInfo['min_price'] = Db::table('vsl_goods_sku')->connect($conn)->where(['goods_id' => $goodsInfo['goods_id']])->min('market_price');
        }else{
            
            $goodsInfo = $this->goods->getInfo($condition, $field);
            if(!$goodsInfo){
                return [];
            }
           
            if($pic){
                $pictureModel = new AlbumPictureModel();
                $goodsInfo['album_picture'] = $pictureModel->getInfo(['pic_id' => $goodsInfo['picture']],'pic_cover,pic_cover_small,pic_cover_mid,pic_cover_big,pic_cover_micro');
            }
            if($cate){
                $categoryModel = new VslGoodsCategoryModel();
                $categoryInfo = $categoryModel->getInfo(['category_id' => $goodsInfo['category_id']], 'category_name,short_name');
                $goodsInfo['category_name'] = $categoryInfo ? $categoryInfo['category_name'] : '';
                $goodsInfo['category_short_name'] = $categoryInfo ? $categoryInfo['short_name'] : '';
            }
            $goodsInfo['supplier_name'] = '';
            $goodsInfo['supplier_status'] = 0;
            if($sup && getAddons('supplier', $goodsInfo['website_id']) && $goodsInfo['supplier_info']){
                $supplierModel = new VslSupplierModel();
                $supplierInfo = $supplierModel->getInfo(['website_id' => $goodsInfo['website_id'], 'shop_id' => $goodsInfo['shop_id'], 'supplier_id' => $goodsInfo['supplier_info']], 'supplier_name, status');
                $goodsInfo['supplier_name'] = $supplierInfo ? $supplierInfo['supplier_name'] : '';
                $goodsInfo['supplier_status'] = $supplierInfo ? $supplierInfo['status'] : 0;
            }
            $goodsInfo['shop_name'] = '';
            $goodsInfo['shop_state'] = 0;
            if($shop && getAddons('shop', $goodsInfo['website_id'])){
                $shopModel = new VslShopModel();
                $shopInfo = $shopModel->getInfo(['website_id' => $goodsInfo['website_id'], 'shop_id' => $goodsInfo['shop_id']], 'shop_name,shop_state');
                $goodsInfo['shop_name'] = $shopInfo['shop_name'];
                $goodsInfo['shop_state'] = $shopInfo['shop_state'];
            }
            //组装权限信息,便于移动端根据会员权限检索
            $goodsInfo['browse_auth_u'] = '';
            $goodsInfo['browse_auth_d'] = '';
            $goodsInfo['browse_auth_s'] = '';
            $goodsDiscountModel = new VslGoodsDiscountModel();
            $discountInfo = $goodsDiscountModel->getInfo(['goods_id' => $goodsInfo['goods_id']], 'browse_auth_u,browse_auth_d,browse_auth_s');
            if($discountInfo){
                $goodsInfo['browse_auth_u'] = $discountInfo['browse_auth_u'] ? ','.$discountInfo['browse_auth_u'].',' : '';
                $goodsInfo['browse_auth_d'] = $discountInfo['browse_auth_d'] ? ','.$discountInfo['browse_auth_d'].',' : '';
                $goodsInfo['browse_auth_s'] = $discountInfo['browse_auth_s'] ? ','.$discountInfo['browse_auth_s'].',' : '';
            }
            //组装sku最大价格和最小价格信息,便于移动端根据价格区间权限检索
            $skuModel = new VslGoodsSkuModel();
            $goodsInfo['max_price'] = $skuModel->getMax(['goods_id' => $goodsInfo['goods_id']], 'market_price');
            $goodsInfo['min_price'] = $skuModel->getMin(['goods_id' => $goodsInfo['goods_id']], 'market_price');
        }
        //组装分类信息,将所有分类id存到一起,便于移动端根据分类id检索
        $goodsInfo['all_cate_ids'] = '';
        if($goodsInfo['category_id'] || $goodsInfo['extend_category_id'] ){
            $goodsInfo['all_cate_ids'] = ',';
            $goodsInfo['all_cate_ids'] .= $goodsInfo['category_id_1'] ? $goodsInfo['category_id_1'].',' : '';
            $goodsInfo['all_cate_ids'] .= $goodsInfo['category_id_2'] ? $goodsInfo['category_id_2'].',' : '';
            $goodsInfo['all_cate_ids'] .= $goodsInfo['category_id_3'] ? $goodsInfo['category_id_3'].',' : '';
            $goodsInfo['all_cate_ids'] .= $goodsInfo['extend_category_id_1'] ? $goodsInfo['extend_category_id_1'].',' : '';
            $goodsInfo['all_cate_ids'] .= $goodsInfo['extend_category_id_2'] ? $goodsInfo['extend_category_id_2'].',' : '';
            $goodsInfo['all_cate_ids'] .= $goodsInfo['extend_category_id_3'] ? $goodsInfo['extend_category_id_3'].',' : '';
            $goodsInfo['all_cate_ids'] = rtrim($goodsInfo['all_cate_ids'],',').',';
        }
        return $goodsInfo;
    }
    /**
     * 从缓存分页查询商品
     * @param type $page_index
     * @param type $page_size
     * @param type $condition
     * @param type $order
     * @param type $field
     * @return type
     */
    public function getPageGoodsList($page_index = 0, $page_size = 0, $condition = [], $order = null, $field = ''){
        if(config('ecs.host')){
            if($field && !$field != '*'){
                $field = explode(',', str_replace(' ', '', $field));
                $field[] = 'album_picture';
            }
            if($order){
                $arr = explode(' ', $order);
                $order = [$arr[0] => $arr[1]];
            }
           
           
            $esSer = new EcService();
            $list = $esSer->table('goods')->condition($condition)->field($field)->order($order)->pageAll($page_index,$page_size);
        }else{
            $list = $this->goods->pageQuery($page_index, $page_size, $condition, $order, $field);
            if($list['data'] && ($field == '*' || !$field || strpos($field,'picture') !== false)){
                $pictureModel = new AlbumPictureModel();
                foreach($list['data'] as $key => $val){
                    if(!$val['picture']){
                        $list[$key]['album_picture'] = [];
                        continue;
                    }
                    $list['data'][$key]['album_picture'] = $pictureModel->getInfo(['pic_id' => $val['picture']],'pic_cover,pic_cover_small,pic_cover_mid,pic_cover_big,pic_cover_micro');
                }
            }
        }
        return $list;
    }
    /**
     * 获取商品最低价格或最高价格
     * @param type $condition
     * @param type $type 最小值或最大值
     */
    public function getGoodsMinOrMaxPrice($condition = [], $type = 'min'){
        $price = 0;

            if($type == 'min'){
                $price = $this->goods->where($condition)->min('price');
            }else{
                $price = $this->goods->where($condition)->max('price');
            }
        
        return $price;
    }
    /**
     * 移动端从缓存获取商品列表
     * @param type $page_size
     * @param type $query
     * @param type $last_id
     * @param type $sort
     * @return type
     */
    public function getWapGoodsEs($page_size = 0, $query = '', $last_id = 0, $sort = []){
        $ecService = new EcService();
        if($last_id){
            $ecService->searchAfter(explode(',', $last_id));
        }
        $list = $ecService->queryByTranslate($query, $page_size, $sort);
        
        return $list;
    }
    /**
     * 从缓存删除商品
     * @param type $goods_id
     * @return boolean
     */
    public function deleteGoodsFromEs($goods_id = 0){
        if(!config('ecs.host')){
            return true;
        }
        if(!$goods_id){
            return false;
        }
        $ecService = new EcService();
        $result = $ecService->table('goods')->deleteById($goods_id);
        return $result;
    }
}
