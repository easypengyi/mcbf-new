<?php

namespace addons\shop\service;

/**
 * 店铺服务层
 */

use addons\merchants\server\Merchants;
use data\model\VslBankModel;
use data\model\VslMemberFavoritesModel;
use data\model\VslMemberModel;
use data\model\WebSiteModel;
use data\service\BaseService as BaseService;
use addons\shop\model\VslShopModel as VslShopModel;
use addons\shop\model\VslShopGroupModel as VslShopGroupModel;
use addons\shop\model\VslShopApplyModel;
use data\model\AdminUserModel;
use data\model\UserModel;
use data\model\InstanceTypeModel;
use data\service\WebSite;
use data\model\InstanceModel;
use data\model\AuthGroupModel;
use data\model\VslOrderModel;
use addons\shop\model\VslShopAccountModel;
use addons\shop\model\VslShopAccountRecordsModel;
use addons\shop\model\VslShopWithdrawModel;
use addons\shop\model\VslShopBankAccountModel;
use addons\shop\model\VslShopInfoModel;
use addons\shop\service\shopaccount\ShopAccount as ShopAccountService;
use addons\shop\service\shopaccount\ShopAccount;
use addons\shop\model\VslShopOrderReturnModel;
use data\model\VslMemberWithdrawSettingModel;
use data\model\ProvinceModel;
use data\model\CityModel;
use data\model\DistrictModel;
use data\service\Album;
use data\service\User;
use data\model\VslOrderGoodsViewModel;
use data\service\Config as WebConfig;
use data\model\AlbumPictureModel;
use data\service\AddonsConfig as AddonsConfigService;
use data\service\Merchant;
use data\model\VslShopEvaluateModel;
use addons\store\server\Store;
use addons\customform\server\Custom as CustomServer;
use data\service\Member;
use think\Db;
use addons\shop\model\VslShopSeparateModel;
use data\service\Pay\Joinpay;
use data\service\Customtemplate as CustomtemplateSer;
class Shop extends BaseService
{

    function __construct()
    {
        parent::__construct();
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IShop::updateShopSort()
     */
    public function updateShopSort($shop_id, $shop_sort)
    {
        $shop = new VslShopModel();
        $data = array(
            'shop_sort' => $shop_sort
        );
        $shop->save($data, [
            'shop_id' => $shop_id
        ]);

        return $shop_id;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IShop::setRecomment()
     */
    public function setRecomment($shop_id, $shop_recommend)
    {
        $shop = new VslShopModel();
        $data = array(
            'shop_recommend' => $shop_recommend
        );
        $shop->save($data, [
            'shop_id' => $shop_id
        ]);

        return $shop_id;
    }

    /**
     * (non-PHPdoc)
     *
     * 设置店铺分类是否显示
     */
    public function setIsvisible($shop_group_id, $is_visible)
    {
        $shop = new VslShopGroupModel();
        $data = array(
            'is_visible' => $is_visible
        );
        $shop->save($data, [
            'shop_group_id' => $shop_group_id
        ]);

        return $shop_group_id;
    }

    /**
     * (non-PHPdoc)
     * @see \data\api\IShop::setStatus()
     */

    public function setStatus($shop_id, $type)
    {
        $shop = new VslShopModel();
        $data = array(
            'shop_state' => $type
        );
        $shop->save($data, [
            'shop_id' => $shop_id
        ]);
        return $shop_id;
    }
    
    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IShop::getShopList()
     */
    public function getShopList($page_index = 1, $page_size = 0, $where = '', $order = '')
    {
        $shop = new VslShopModel();
        $shop_type = new InstanceTypeModel();
        $shop_group = new VslShopGroupModel();
        $picture = new AlbumPictureModel();

        $list = $shop->pageQuery($page_index, $page_size, $where, $order, '*');

        foreach ($list['data'] as $k => $v) {
            $list['data'][$k]['shop_type_name'] = $shop_type->getInfo([
                'instance_typeid' => $v['shop_type']
            ], 'type_name')['type_name'];

            $list['data'][$k]['group_name'] = $shop_group->getInfo([
                'shop_group_id' => $v['shop_group_id']
            ], 'group_name')["group_name"];

            $shop_picture = $picture->get($v['shop_logo']);
            if (empty($shop_picture)) {
                $shop_picture = array(
                    'pic_cover' => '',
                    'pic_cover_big' => '',
                    'pic_cover_mid' => '',
                    'pic_cover_small' => '',
                    'pic_cover_micro' => '',
                    'upload_type' => 1,
                    'domain' => ''
                );
            }
            $shop_evaluate = $this->getShopEvaluate($v['shop_id']);
            $list['data'][$k]['description_credit'] = $shop_evaluate['shop_desc'];
            $list['data'][$k]['service_credit'] = $shop_evaluate['shop_service'];
            $list['data'][$k]['delivery_credit'] = $shop_evaluate['shop_stic'];
            $list['data'][$k]['picture'] = $shop_picture['pic_cover'];
            $list['data'][$k]['shop_logo_img'] = $shop_picture['pic_cover'];
        }
        return $list;
    }

    /**
     * wap端店铺搜索
     */
    public function shopList($page_index = 1, $page_size = 0, $condition = [], $order = '', $field = '*', $group = '')
    {
        $shop_model = new VslShopModel();
        $shop_group = new VslShopGroupModel();
        $picture = new AlbumPictureModel();
        $query_list_obj = $shop_model->alias('ns')
            ->join('vsl_goods ng', 'ns.shop_id = ng.shop_id', 'LEFT')
            ->field($field);

        $shop_list = $shop_model->viewPageQuerys($query_list_obj, $page_index, $page_size, $condition, $order, $group);
        $count = $shop_model->alias('ns')
            ->where($condition)
            ->count('ns.shop_id');

        $list = $shop_model->setReturnList($shop_list, $count, $page_size);
        $return_shop_list = [];
        foreach ($list['data'] as $k => $v) {
            $return_shop_list[$k]['id'] = $v['id'];
            $return_shop_list[$k]['shop_id'] = $v['shop_id'];
            $return_shop_list[$k]['shop_name'] = $v['shop_name'];
            $evaluate = $this->getShopEvaluate($v['shop_id']);
            $return_shop_list[$k]['description_credit'] = $evaluate['shop_desc'];
            $return_shop_list[$k]['service_credit'] = $evaluate['shop_service'];
            $return_shop_list[$k]['delivery_credit'] = $evaluate['shop_stic'];
            $return_shop_list[$k]['comprehensive'] = $evaluate['comprehensive'] ?: 5;
            
            $shop_logo = '';
            $shop_picture = $picture->getInfo(['pic_id' =>$v['shop_logo']],'pic_cover,pic_cover_mid,pic_cover_micro');
            if (!empty($shop_picture)) {
                $shop_logo = getApiSrc($shop_picture['pic_cover']);
            }
            // 是否显示分类
            $shop_group_info =  $shop_group->getInfo([
                'shop_group_id' => $v['shop_group_id']
            ], 'group_name,is_visible');
            $return_shop_list[$k]['group_name'] = $shop_group_info['group_name'];
            $return_shop_list[$k]['is_visible'] = $shop_group_info['is_visible'] ?: 0;
            $return_shop_list[$k]['shop_logo'] = $shop_logo;
            $return_shop_list[$k]['has_store'] = 0;
            $storeSet = 0;
            if(getAddons('store', $this->website_id, $this->instance_id)){
                $storeServer = new Store();
                $storeSet = $storeServer->getStoreSet($v['shop_id'])['is_use'];
            }
            $return_shop_list[$k]['has_store'] = $storeSet ? : 0;
        }

        // 针对评分进行重新排序（因为上面重新计算过）
        if (strstr('ns.comprehensive DESC', $order)) {
            $return_shop_list = arrSortByValue($return_shop_list, 'comprehensive', 'desc' );
        } else if (strstr('ns.comprehensive ASC', $order)) {
            $return_shop_list = arrSortByValue($return_shop_list, 'comprehensive', 'asc' );
        }

        $list['data'] = $return_shop_list;
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IShop::getShopGroup()
     */
    public function getShopGroup($page_index = 1, $page_size = 0, $where = '', $order = '', $field = '*')
    {
        $shop_group = new VslShopGroupModel();
        $list = $shop_group->pageQuery($page_index, $page_size, $where, $order, $field);
        return $list;
    }

    /**
     * 申请店铺
     * (non-PHPdoc)
     *
     * @see \data\api\IShop::addShopApply()
     */
    public function addShopApply($apply_type, $uid, $company_name, $company_province_id, $company_city_id, $company_district_id, $company_address_detail, $company_phone, $company_type, $company_employee_count, $company_registered_capital, $contacts_name, $contacts_phone, $contacts_email, $contacts_card_no, $contacts_card_electronic_1, $contacts_card_electronic_2, $contacts_card_electronic_3, $business_licence_number, $business_sphere, $business_licence_number_electronic, $shop_name, $apply_state, $apply_message, $apply_year, $shop_group_name, $shop_group_id, $paying_money_certificate, $paying_money_certificate_explain, $paying_amount, $recommend_uid, $post_data='', $merchants_uid=0)
    {
        $user = new UserModel();
        // 得到当前会员的信息
        $shop_apply = new VslShopApplyModel();
        $condition['uid'] = $uid;
        $condition['apply_state'] = array(
            "in",
            '1,2'
        );
        $count = $shop_apply->getCount($condition);
        if ($count > 0) {
            return APPLY_REPEAT;
        }
        $instance_type = new InstanceTypeModel();
        $defaultInstanceType = $instance_type->getInfo(['is_default' => 1, 'website_id' => $this->website_id]);//申请店铺入驻店铺默认版本

        //招商员extend_code换取uid
        if($merchants_uid) {
            $member_mdl = new VslMemberModel();
            $merchants_uid = $member_mdl->Query(['uid' => $merchants_uid],'uid')[0];
            if(empty($merchants_uid)) {
                $merchants_uid = 0;
            }
        }

        if($post_data){
            $data = array(
                "uid" => $uid,
                "shop_type_name" => $defaultInstanceType['type_name'],
                "shop_type_id" => $defaultInstanceType['instance_typeid'],
                "shop_group_name" => $shop_group_name,
                "shop_group_id" => $shop_group_id,
                "shop_name" => $shop_name,
                "post_data" => $post_data,
                "website_id" => $this->website_id
            );
        }else{
            $data = array(
                "apply_type" => $apply_type,
                "uid" => $uid,
                "company_name" => $company_name,
                "company_province_id" => $company_province_id,
                "company_city_id" => $company_city_id,
                "company_district_id" => $company_district_id,
                "company_address_detail" => $company_address_detail,
                "company_phone" => $company_phone,
                "company_type" => $company_type,
                "company_employee_count" => $company_employee_count,
                "company_registered_capital" => $company_registered_capital,
                "contacts_name" => $contacts_name,
                "contacts_phone" => $contacts_phone,
                "contacts_email" => $contacts_email,
                "contacts_card_no" => $contacts_card_no,
                "contacts_card_electronic_1" => $contacts_card_electronic_1,
                "contacts_card_electronic_2" => $contacts_card_electronic_2,
                "contacts_card_electronic_3" => $contacts_card_electronic_3,
                "business_licence_number" => $business_licence_number,
                "business_sphere" => $business_sphere,
                "business_licence_number_electronic" => $business_licence_number_electronic,
                "shop_name" => $shop_name,
                "apply_state" => $apply_state, // 默认输入1
                "apply_message" => $apply_message,
                "apply_year" => $apply_year, // 默认1
                "shop_type_name" => $defaultInstanceType['type_name'],
                "shop_type_id" => $defaultInstanceType['instance_typeid'],
                "shop_group_name" => $shop_group_name,
                "shop_group_id" => $shop_group_id,
                "paying_money_certificate" => $paying_money_certificate,
                "paying_money_certificate_explain" => $paying_money_certificate_explain,
                "paying_amount" => $paying_amount,
                "recommend_uid" => $recommend_uid,
                "website_id" => $this->website_id,
                "merchants_uid" => $merchants_uid,
            );
        }
        
        $shop_apply->save($data);
        $retval = $shop_apply->apply_id;

        // 如果用户是被拒绝过的重新申请的就删除了以前的拒绝信息
        if (!empty($shop_apply->apply_id)) {
            $shop_apply->destroy([
                'uid' => $this->uid,
                'apply_state' => -1
            ]);
        }

        return $retval;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IShop::getShopDetail()
     */
    public function getShopDetail($shop_id)
    {
        $shop = new VslShopModel();
        $shop_group = new VslShopGroupModel();
        $instance_type = new InstanceTypeModel();
        $shop_company = new VslShopInfoModel();
        $shop_info = array();
        $base_info = $shop->getInfo(['shop_id' => $shop_id, 'website_id' => $this->website_id]);
        $shop_info['base_info'] = $base_info;
        $shop_info['base_info']['shop_logo_img'] = '';
        $shop_info['base_info']['shop_evaluate'] = $this->getShopEvaluate($shop_id);
        $shop_info['base_info']['has_store'] = 0;
        if(getAddons('store', $this->website_id)){
            $storeServer = new Store();
             $shop_info['base_info']['has_store'] = (int)$storeServer->getStoreSet($shop_id)['is_use'] ? : 0;
        }
        if (!empty($base_info)) {
            $picture = new AlbumPictureModel();
            if (!empty($base_info['shop_logo'])) {
                $shop_logo = $picture->getInfo(['pic_id' =>$base_info['shop_logo']],'pic_cover,pic_cover_mid,pic_cover_micro');
                $shop_info['base_info']['shop_logo_img'] = $shop_logo['pic_cover'];
            }

            $group_info = $shop_group->get($base_info['shop_group_id']);
            $shop_info['group_info'] = $group_info;
            $instance_type_info = $instance_type->get($base_info['shop_type']);
            $shop_info['instance_type_info'] = $instance_type_info;
            $company_info = $shop_company->getInfo(['shop_id' => $shop_id]);
            $shop_info['company_info'] = $company_info;
        }
        return $shop_info;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IShop::getShopInfo()
     */
    public function getShopInfo($shop_id, $field = '*')
    {
        $shop = new VslShopModel();
        $info = $shop->getInfo([
            'shop_id' => $shop_id,
            'website_id' => $this->website_id
        ], $field);
        return $info;
    }

    /**
     * (non-PHPdoc)
     * shop_id int(11) NOT NULL COMMENT '店铺索引id',
     * shop_name varchar(50) NOT NULL COMMENT '店铺名称',
     * shop_type int(11) NOT NULL COMMENT '店铺类型等级',
     * uid int(11) NOT NULL COMMENT '会员id',
     * shop_group_id int(11) NOT NULL COMMENT '店铺分类',
     * shop_company_name varchar(50) DEFAULT NULL COMMENT '店铺公司名称',
     * province_id mediumint(8) UNSIGNED NOT NULL DEFAULT 0 COMMENT '店铺所在省份ID',
     * city_id mediumint(8) UNSIGNED NOT NULL DEFAULT 0 COMMENT '店铺所在市ID',
     * shop_address varchar(100) NOT NULL DEFAULT '' COMMENT '详细地区',
     * shop_zip varchar(10) NOT NULL DEFAULT '' COMMENT '邮政编码',
     * shop_state tinyint(1) NOT NULL DEFAULT 2 COMMENT '店铺状态，0关闭，1开启，2审核中',
     * shop_close_info varchar(255) DEFAULT NULL COMMENT '店铺关闭原因',
     * shop_sort int(11) NOT NULL DEFAULT 0 COMMENT '店铺排序',
     * shop_create_time varchar(10) NOT NULL DEFAULT '0' COMMENT '店铺时间',
     * shop_end_time varchar(10) DEFAULT NULL COMMENT '店铺关闭时间',
     * shop_logo varchar(255) DEFAULT NULL COMMENT '店铺logo',
     * shop_banner varchar(255) DEFAULT NULL COMMENT '店铺横幅',
     * shop_avatar varchar(150) DEFAULT NULL COMMENT '店铺头像',
     * shop_keywords varchar(255) NOT NULL DEFAULT '' COMMENT '店铺seo关键字',
     * shop_description varchar(255) NOT NULL DEFAULT '' COMMENT '店铺seo描述',
     * shop_qq varchar(50) DEFAULT NULL COMMENT 'QQ',
     * shop_ww varchar(50) DEFAULT NULL COMMENT '阿里旺旺',
     * shop_phone varchar(20) DEFAULT NULL COMMENT '商家电话',
     * shop_domain varchar(50) DEFAULT NULL COMMENT '店铺二级域名',
     * shop_domain_times tinyint(1) UNSIGNED DEFAULT 0 COMMENT '二级域名修改次数',
     * shop_recommend tinyint(1) NOT NULL DEFAULT 0 COMMENT '推荐，0为否，1为是，默认为0',
     * shop_credit int(10) NOT NULL DEFAULT 0 COMMENT '店铺信用',
     * shop_desccredit float NOT NULL DEFAULT 0 COMMENT '描述相符度分数',
     * shop_servicecredit float NOT NULL DEFAULT 0 COMMENT '服务态度分数',
     * shop_deliverycredit float NOT NULL DEFAULT 0 COMMENT '发货速度分数',
     * shop_collect int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '店铺收藏数量',
     * shop_stamp varchar(200) DEFAULT NULL COMMENT '店铺印章',
     * shop_printdesc varchar(500) DEFAULT NULL COMMENT '打印订单页面下方说明文字',
     * shop_sales int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '店铺销量',
     * shop_workingtime varchar(100) DEFAULT NULL COMMENT '工作时间',
     * live_store_name varchar(255) DEFAULT NULL COMMENT '商铺名称',
     * live_store_address varchar(255) DEFAULT NULL COMMENT '商家地址',
     * live_store_tel varchar(255) DEFAULT NULL COMMENT '商铺电话',
     * live_store_bus varchar(255) DEFAULT NULL COMMENT '公交线路',
     * shop_vrcode_prefix char(3) DEFAULT NULL COMMENT '商家兑换码前缀',
     * store_qtian tinyint(1) DEFAULT 0 COMMENT '7天退换',
     * shop_zhping tinyint(1) DEFAULT 0 COMMENT '正品保障',
     * shop_erxiaoshi tinyint(1) DEFAULT 0 COMMENT '两小时发货',
     * shop_tuihuo tinyint(1) DEFAULT 0 COMMENT '退货承诺',
     * shop_shiyong tinyint(1) DEFAULT 0 COMMENT '试用中心',
     * shop_shiti tinyint(1) DEFAULT 0 COMMENT '实体验证',
     * shop_xiaoxie tinyint(1) DEFAULT 0 COMMENT '消协保证',
     * shop_huodaofk tinyint(1) DEFAULT 0 COMMENT '货到付款',
     * shop_free_time varchar(10) DEFAULT NULL COMMENT '商家配送时间',
     * shop_region varchar(50) DEFAULT NULL COMMENT '店铺默认配送区域',
     *
     * @see \data\api\IShop::addshop()
     */

    public function addshop($data)
    {
        $shop_name              = $data['shop_name'];
        $shop_type              = $data['shop_type'];
        $uid                    = $data['uid'];
        $shop_group_id          = $data['shop_group_id'];
        $shop_company_name      = $data['shop_company_name'];
        $province_id            = $data['province_id'];
        $city_id                = $data['city_id'];
        $shop_address           = $data['shop_address'];
        $shop_zip               = $data['shop_zip'];
        $shop_sort              = $data['shop_sort'] ?: 0;
        $recommend_uid          = $data['recommend_uid'] ?: 0;
        $shop_platform_commission_rate = $data['shop_platform_commission_rate'] ?: 0;
        $margin                 = $data['margin'] ?: 0;
        $shop_state             = $data['shop_state'] ?: 0;
        $shop_audit             = $data['shop_audit'] ?: 0;
        $merchants_uid          = $data['merchants_uid'] ?: 0;
        
        
        
        $shop = new VslShopModel();
        $count = $shop->getCount(['uid' => $uid]);
        // 防止出现重复店铺、重复提交问题
        if ($count > 0) {
            return -8888;
        }
        $shop->startTrans();
        try {
            $website = new WebSite();
            $shop_id = $website->addSystemInstance($uid, $shop_name, $shop_type);
            
            $data = array(
                'shop_id' => $shop_id,
                'uid' => $uid,
                'shop_name' => $shop_name,
                'shop_type' => $shop_type,
                'shop_group_id' => $shop_group_id,
                'shop_company_name' => $shop_company_name,
                'province_id' => $province_id,
                'city_id' => $city_id,
                'shop_address' => $shop_address,
                'shop_zip' => $shop_zip,
                'shop_sort' => $shop_sort,
                'margin' => $margin,
                'shop_platform_commission_rate' => $shop_platform_commission_rate,
                'shop_state' => $shop_state,
                'shop_audit' => $shop_audit,
                'recommend_uid' => $recommend_uid,
                'shop_create_time' => time(),
                'website_id' => $this->website_id,
                'merchants_uid' => $merchants_uid,
            );
            // 添加店铺
            $retval = $shop->save($data);
            // 添加店铺账户
            $shop_account = new VslShopAccountModel();
            $data_account = array(
                'shop_id' => $shop_id,
                'website_id' => $this->website_id
            );
            $shop_account->save($data_account);

            if(getAddons('merchants',$this->website_id) && $merchants_uid) {
                //更新招商员等级
                $merchants = new Merchants();
                $merchants->updateMerchantsLevel(['order_id' => 0, 'website_id' => $this->website_id, 'uid' => $merchants_uid]);
            }
            $shop->commit();
            return $shop_id;
        } catch (\Exception $e) {
            
            $shop->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IShop::dealwithShopApply()
     */
    public function dealwithShopApply($shop_apply_id, $type, $shop_platform_commission_rate = 0, $margin = 0, $shop_audit = 0, $refuse_reason = '', $shop_type = 0)
    {
        $shop_apply = new VslShopApplyModel();
        $ConfigService = new AddonsConfigService();
        $shopInfo = $ConfigService->getAddonsConfig('shop',$this->website_id);
        $shopConfig = [];
        if($shopInfo){
            $shopConfig = $shopInfo['value'];
        }
        if ($type == 'disagree') {
            $retval = $shop_apply->save([
                'apply_state' => -1,
                'refuse_reason' => $refuse_reason
            ], [
                'apply_id' => $shop_apply_id
            ]);
            return $retval;
            // 拒绝审核通过
        } elseif ($type == 'agree') {
            $shop_apply = new VslShopApplyModel();
            // 审核通过
            $shop_apply->startTrans();
            try {
                $shop_apply->save([
                    'apply_state' => 2,
                    'shop_group_id' => $shop_type
                ], [
                    'apply_id' => $shop_apply_id
                ]);
                $apply_data = $shop_apply->get($shop_apply_id);
//                $res_data = $this->addshop($apply_data['shop_name'], $apply_data['shop_type_id'], $apply_data['uid'], $apply_data['shop_group_id'], $apply_data['company_name'], $apply_data['company_province_id'], $apply_data['company_city_id'], $apply_data['company_address_detail'], '', '0', $apply_data["recommend_uid"], $shop_platform_commission_rate?:$shopConfig['platform_commission_percentage'], $margin, 1, $shop_audit,$apply_data['merchants_uid']);
                $data = [
                    'shop_name' => $apply_data['shop_name'],
                    'shop_type' => $apply_data['shop_type_id'],
                    'uid' => $apply_data['uid'],
                    'shop_group_id' => $apply_data['shop_group_id'],
                    'shop_company_name' => $apply_data['company_name'],
                    'province_id' => $apply_data['company_province_id'],
                    'city_id' => $apply_data['company_city_id'],
                    'shop_address' => $apply_data['company_address_detail'],
                    'recommend_uid' => $apply_data["recommend_uid"],
                    'shop_platform_commission_rate' => $shop_platform_commission_rate ?: $shopConfig['platform_commission_percentage'],
                    'margin' => $margin,
                    'shop_state' => 1,
                    'shop_audit' => $shop_audit,
                    'merchants_uid' => $apply_data['merchants_uid'],
                ];
                $res_data = $this->addshop($data);
                if ($res_data > 0) {
                    $apply_data['shop_id'] = $res_data;
                    $this->addShopInfo($apply_data);
                    $album_name = "默认相册";
                    $sort = 0;
                    $album = new Album();
                    $add_album = $album->addAlbumClass($album_name, $sort, 0, '', 1, $res_data);
                    
                    $shop_apply->save([
                        'shop_id' => $res_data
                    ], [
                        'apply_id' => $shop_apply_id
                    ]);
                    $shop_apply->commit();
                    return 1;
                } else {
                    $shop_apply->rollback();
                    return $res_data;
                }
            } catch (\Exception $e) {
                $shop_apply->rollback();
                return $e;
            }
        } else {
            return -1;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IShop::getShopApplyList()
     */
    public function getShopApplyList($page_index = 1, $page_size = 0, $where = '', $order = 'apply_id DESC')
    {
        $shop_apply = new VslShopApplyModel();
        $list = $shop_apply->pageQuery($page_index, $page_size, $where, $order, '*');

        if (!empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                $user = new UserModel();
                $userinfo = $user->getInfo([
                    'uid' => $v['uid']
                ], "*");
                $user_name = "";
                $user_tel = "";
                $user_headimg = '';
                if (count($userinfo) > 0) {
                    $user_name = $userinfo["real_name"]?:$userinfo["user_name"]?:$userinfo["nick_name"];
                    $user_tel = $userinfo["user_tel"];
                    $user_headimg = $userinfo["user_headimg"];
                }
                $list['data'][$k]['real_name'] = $user_name;
                $list['data'][$k]['user_tel'] = $user_tel;
                $list['data'][$k]['user_headimg'] = $user_headimg;
            }
        }

        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IShop::getShopTypeList()
     */
    public function getShopTypeList($page_index = 1, $page_size = 0, $where = '', $order = '')
    {
        $instance_type = new InstanceTypeModel();
        $checkInstanceType = $instance_type->getInfo(['is_default' => 1, 'website_id' => $this->website_id]);
        $list = $instance_type->pageQuery($page_index, $page_size, $where, $order, '*');
        if($checkInstanceType){
            return $list;
        }
        $websiteService = new WebSite();
        $merchantVersionId = $websiteService->getWebDetail($this->website_id)['merchant_versionid'];
        if(!$merchantVersionId){
            return $list;
        }
        $merchantVersionService = new Merchant();
        $merchantVersion = $merchantVersionService->getMerchantVersionDetail($merchantVersionId);
        if(!$merchantVersion){
            return $list;
        }
        $data = array(
            'type_name' => '默认版本',
            'type_desc' => '默认版本',
            'type_module_array' => $merchantVersion['shop_type_module_array'],
            'create_time' => time(),
            'modify_time' => time(),
            'website_id' => $this->website_id,
            'is_default' => 1
        );
        $result = $instance_type->save($data);
        if($result){
            $list = $instance_type->pageQuery($page_index, $page_size, $where, $order, '*');
        }
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IShop::addShopGroup()
     */
    public function addShopGroup($group_name, $group_sort, $is_visible)
    {
        $shop_group = new VslShopGroupModel();
        $check = $shop_group->getInfo(['website_id' => $this->website_id,'group_name' => $group_name]);
        if($check){
            return -10010;
        }
        $data = array(
            'group_name' => $group_name,
            'group_sort' => $group_sort,
            'is_visible' => $is_visible,
            'create_time' => time(),
            'modify_time' => time(),
            'website_id' => $this->website_id,
        );
        $shop_group->save($data);
        return $shop_group->shop_group_id;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IShop::updateShopGroup()
     */
    public function updateShopGroup($shop_group_id, $group_name, $group_sort, $is_visible)
    {
        $shop_group = new VslShopGroupModel();
        $check = $shop_group->getInfo(['website_id' => $this->website_id,'group_name' => $group_name,'shop_group_id' => ['<>',$shop_group_id]]);
        if($check){
            return -10010;
        }
        $data = array(
            'is_visible' => $is_visible,
            'group_name' => $group_name,
            'group_sort' => $group_sort,
            'modify_time' => time()
        );
        $shop_group->save($data, [
            'shop_group_id' => $shop_group_id
        ]);
        return $shop_group_id;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IShop::getShopGroupDetail()
     */
    public function getShopGroupDetail($shop_group_id)
    {
        $shop_group = new VslShopGroupModel();
        $info = $shop_group->get($shop_group_id);
        return $info;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IShop::delShopGroup()
     */
    public function delShopGroup($shop_group_id)
    {
        $retval = '';
        $shop = new VslShopModel();
        $shop_list = $shop->getQuery([
            'shop_group_id' => $shop_group_id
        ], 'shop_id', '');
        if (!count($shop_list)) {
            $shop_group = new VslShopGroupModel();
            $retval = $shop_group->destroy([
                'shop_group_id' => $shop_group_id
            ]);
        }
        return $retval;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \data\api\IShop::getShopApplyDetail()
     */
    public function getShopApplyDetail($apply_id)
    {
        $shop_apply = new VslShopApplyModel();
        $shop_apply_info = $shop_apply->get($apply_id);
        if (!empty($shop_apply_info)) {
            $recommend_name = "--";
            $user = new UserModel();
            $user_info = $user->getInfo(array(
                "uid" => $shop_apply_info["recommend_uid"]
            ));
            if (!empty($user_info)) {
                $recommend_name = $user_info["nick_name"];
            }
            $shop_apply_info["recommend_name"] = $recommend_name;
            // 区域解释
            $province_name = "";
            $city_name = "";
            $district_name = "";
            $province = new ProvinceModel();
            $province_info = $province->getInfo(array(
                "province_id" => $shop_apply_info["company_province_id"]
            ), "*");
            if (count($province_info) > 0) {
                $province_name = $province_info["province_name"];
            }
            $shop_apply_info['province_name'] = $province_name;
            $city = new CityModel();
            $city_info = $city->getInfo(array(
                "city_id" => $shop_apply_info["company_city_id"]
            ), "*");
            if (count($city_info) > 0) {
                $city_name = $city_info["city_name"];
            }
            $shop_apply_info['city_name'] = $city_name;
            $district = new DistrictModel();
            $district_info = $district->getInfo(array(
                "district_id" => $shop_apply_info["company_district_id"]
            ), "*");
            if (count($district_info) > 0) {
                $district_name = $district_info["district_name"];
            }
            $shop_apply_info['district_name'] = $district_name;
        }
        return $shop_apply_info;
    }
    /**
     *
     * 获取申请店铺被拒绝理由
     *
     * 
     */
    public function getApplyRefuseReason($uid)
    {
        $shop_apply = new VslShopApplyModel();
        $shop_apply_info = $shop_apply->getInfo(['uid' => $uid],'refuse_reason');
        if(!$shop_apply_info){
            return '';
        }
        return $shop_apply_info['refuse_reason'];
    }

    /*
     * 获取店铺注册信息
     */
    public function getShopInfoDetail($shop_id)
    {
        $picture = new AlbumPictureModel();
        $shopInfoModel = new VslShopInfoModel();
        $shop_info = $shopInfoModel->getInfo(['shop_id' => $shop_id]);
        $shop_picture = $picture->getInfo(['pic_id' =>$shop_info['shop_logo']],'pic_cover,pic_cover_mid,pic_cover_micro');
        if (empty($shop_picture)) {
            $shop_picture = array(
                'pic_cover' => '',
            );
        }
        $shop_info['picture'] = $shop_picture['pic_cover'];
        return $shop_info;
    }
    /*
     * 获取店铺所有者信息
     */
    public function getShopUserDetail($shop_id,$website_id)
    {
        $shopModel = new VslShopModel();
        $shop_info = $shopModel->getInfo(['shop_id' => $shop_id,'website_id' => $website_id],'uid');
        $user = new UserModel();
        $user_info = $user ->getInfo(['uid' => $shop_info['uid']]);
        return $user_info;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IShop::addShopType()
     */
    public function addShopType($type_name, $type_module_array, $type_desc, $type_sort, $is_default = 0)
    {
        $instance_type = new InstanceTypeModel();
        $check = $instance_type->getInfo(['website_id' => $this->website_id,'type_name' => $type_name]);
        if($check){
            return -10011;
        }
        $data = array(
            'website_id' => $this->website_id,
            'type_name' => $type_name,
            'type_module_array' => $type_module_array,
            'type_desc' => $type_desc,
            'type_sort' => $type_sort,
            'create_time' => time(),
            'modify_time' => time(),
            'is_default' => $is_default
        );
        $instance_type->save($data);
        return $instance_type->instance_typeid;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IShop::updateShopType()
     */
    public function updateShopType($instance_typeid, $type_name, $type_module_array, $type_desc, $type_sort)
    {
        $instance_type = new InstanceTypeModel();
        $check = $instance_type->getInfo(['website_id' => $this->website_id,'type_name' => $type_name, 'instance_typeid' => ['<>',$instance_typeid]]);
        if($check){
            return -10011;
        }
        try {
            $instance_type->startTrans();
            $data = array(
                'instance_typeid' => $instance_typeid,
                'type_name' => $type_name,
                'type_module_array' => $type_module_array,
                'type_desc' => $type_desc,
                'type_sort' => $type_sort,
                'modify_time' => time()
            );
            $result = $instance_type->save($data, [
                'instance_typeid' => $instance_typeid
            ]);

            $instance = new InstanceModel();
            $instance_list = $instance->getQuery([
                'instance_typeid' => $instance_typeid
            ], 'instance_id', '');
            $website = new WebSite();
            $dateArr = $website->getWebCreateTime($this->website_id);
            $path = './public/addons_status/' . $dateArr['year'].'/'.$dateArr['month'].'/'.$dateArr['day'].'/'. $this->website_id;
            if($instance_list){
                $instance_arr = '';
                foreach ($instance_list as $item) {
                    $instance_arr .= $item['instance_id'] . ',';
                }
                if(file_exists($path .'/addons_'.$item['instance_id'])){
                    unlink($path .'/addons_'.$item['instance_id']);
                }
                
                $instance_arr = rtrim($instance_arr, ",");
                $auth_group = new AuthGroupModel();

                $retval = $auth_group->save([
                    'module_id_array' => $type_module_array
                ], [
                    'instance_id' => [['<>',0],array(
                        "IN",
                        $instance_arr
                    ),'and'],
                    'is_system' => 1,
                    'website_id' => $this->website_id,
                ]);
            }
            $instance_type->commit();
            return $result;
        } catch (\Exception $e) {
            $instance_type->rollback();
            $retval = $e->getMessage();
            return 0;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IShop::getShopTypeDetail()
     */
    public function getShopTypeDetail($instance_typeid)
    {
        $instance_type = new InstanceTypeModel();
        $shop_type_info = $instance_type->get($instance_typeid);
        return $shop_type_info;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \data\api\IShop::updateShopConfigByshop()
     */
    public function updateShopConfigByshop($shop_id, $shop_logo, $shop_banner, $shop_avatar, $shop_qrcode, $shop_qq, $shop_ww, $shop_phone, $shop_keywords, $shop_description, $shop_intro, $shop_name, $group_id)
    {
        $shop = new VslShopModel();
        if(!$shop_name){
            return -8002;
        }
        $checkShopName = $shop->getInfo(['shop_name' => $shop_name, 'website_id' => $this->website_id]);
        if($checkShopName && $checkShopName['shop_id'] != $shop_id){
            return -8001;
        }
        $data = array(
            'shop_logo' => $shop_logo,
            'shop_banner' => $shop_banner,
            'shop_avatar' => $shop_avatar,
            'shop_qrcode' => $shop_qrcode,
            'shop_qq' => $shop_qq,
            'shop_ww' => $shop_ww,
            'shop_phone' => $shop_phone,
            'shop_keywords' => $shop_keywords,
            'shop_description' => $shop_description,
            'shop_intro' => $shop_intro,
            'shop_group_id' => $group_id,
            'shop_name' => $shop_name
        );
        $res = $shop->save($data, [
            'shop_id' => $shop_id,
            'website_id' => $this->website_id
        ]);
        return $res;
    }

    public function updateCompanyConfigByshop($shopApplyInfo)
    {
        $data = array(
            'company_name' => $shopApplyInfo['company_name'],
            'company_province_id' => $shopApplyInfo['company_province_id'],
            'company_city_id' => $shopApplyInfo['company_city_id'],
            'company_district_id' => $shopApplyInfo['company_district_id'],
            'company_address_detail' => $shopApplyInfo['company_address_detail'],
            'company_phone' => $shopApplyInfo['company_phone'],
            'company_employee_count' => $shopApplyInfo['company_employee_count'],
            'company_registered_capital' => $shopApplyInfo['company_registered_capital'],
            'contacts_name' => $shopApplyInfo['contacts_name'],
            'contacts_phone' => $shopApplyInfo['contacts_phone'],
            'contacts_email' => $shopApplyInfo['contacts_email'],
            'company_type' => $shopApplyInfo['company_type'],
            'contacts_card_no' => $shopApplyInfo['contacts_card_no'],
            'contacts_card_electronic_1' => $shopApplyInfo['contacts_card_electronic_1'],
            'contacts_card_electronic_2' => $shopApplyInfo['contacts_card_electronic_2'],
            'contacts_card_electronic_3' => $shopApplyInfo['contacts_card_electronic_3'],
            'business_licence_number' => $shopApplyInfo['business_licence_number'],
            'business_sphere' => $shopApplyInfo['business_sphere'],
            'business_licence_number_electronic' => $shopApplyInfo['business_licence_number_electronic']
        );
        $shop = new VslShopInfoModel();
        $check = $shop->getInfo(['shop_id' => $shopApplyInfo['shop_id'], 'website_id' => $this->website_id]);
        if ($check) {
            $res = $shop->save($data, [
                'shop_id' => $shopApplyInfo['shop_id'],
                'website_id' => $this->website_id
            ]);
        } else {
            $data['shop_id'] = $shopApplyInfo['shop_id'];
            $data['website_id'] = $this->website_id;
            $res = $shop->save($data);
        }
        return $res;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \data\api\IShop::updateShopConfigByPlatform()
     */
    public function updateShopConfigByPlatform($shopInfo)
    {
        $shopModel = new VslShopModel();
        $check = $shopModel->getInfo(['website_id' => $this->website_id,'shop_name' => $shopInfo['shop_name'], 'shop_id' => ['<>', $shopInfo['shop_id']]]);
        if($check){
            return -8001;
        }
        $goodsSer = new \data\service\Goods();
        try {
            
            $shopModel->startTrans();
            $data = array(
                'shop_name' => $shopInfo['shop_name'],
                'shop_group_id' => $shopInfo['shop_group_id'],
                'shop_platform_commission_rate' => $shopInfo['shop_platform_commission_rate'],
                'shop_type' => $shopInfo['shop_type'],
                'shop_credit' => $shopInfo['shop_credit'],
                'shop_desccredit' => $shopInfo['shop_desccredit'],
                'shop_servicecredit' => $shopInfo['shop_servicecredit'],
                'shop_deliverycredit' => $shopInfo['shop_deliverycredit'],
                'store_qtian' => $shopInfo['store_qtian'],
                'shop_zhping' => $shopInfo['shop_zhping'],
                'shop_erxiaoshi' => $shopInfo['shop_erxiaoshi'],
                'shop_tuihuo' => $shopInfo['shop_tuihuo'],
                'shop_shiyong' => $shopInfo['shop_shiyong'],
                'shop_shiti' => $shopInfo['shop_shiti'],
                'shop_xiaoxie' => $shopInfo['shop_xiaoxie'],
                'shop_huodaofk' => $shopInfo['shop_huodaofk'],
                'shop_state' => $shopInfo['shop_state'],
                'shop_audit' => $shopInfo['shop_audit'],
                'shop_close_info' => $shopInfo['shop_close_info'],
                'margin' => $shopInfo['margin'],
                'shop_sort' => $shopInfo['shop_sort'],
                'shop_qq' => $shopInfo['shop_qq'],
                'shop_phone' => $shopInfo['shop_phone'],
                'shop_logo' => $shopInfo['shop_logo'],
                'shop_hide' => $shopInfo['shop_hide'],
                'joinpay_machid' => isset($shopInfo['joinpay_machid']) ? $shopInfo['joinpay_machid'] : '',
                'shop_create_time' => time(),
            );

            $res = $shopModel->save($data, [
                'shop_id' => $shopInfo['shop_id'],
                'website_id' => $this->website_id
            ]);

            $instanceModel = new InstanceModel();
            $instanceModel->save(['instance_typeid' => $shopInfo['shop_type']], [
                'instance_id' => $shopInfo['shop_id'],
                'website_id' => $this->website_id
            ]);


            $shop = $shopModel->getInfo([
                'shop_id' => $shopInfo['shop_id'],
                'website_id' => $this->website_id
            ], 'uid');
            $adminUser = new AdminUserModel();
            $group = $adminUser->getInfo([
                'uid' => $shop['uid']
            ], 'group_id_array');
            $shoptypeModel = new InstanceTypeModel();
            $shoptype = $shoptypeModel->getInfo([
                'instance_typeid' => $shopInfo['shop_type']
            ], 'type_module_array');
            if ($shoptype) {
                $auth_group = new AuthGroupModel();
                $auth_group->save([
                    'module_id_array' => $shoptype['type_module_array']
                ], [
                    'group_id' => $group['group_id_array']
                ]);
            }
            if(!$shopInfo['shop_audit']){
                $goodsSer->updateGoods(['website_id' => $this->website_id, 'shop_id' => $shopInfo['shop_id'], 'state' => 11], ['state' => 1]);
                $goodsSer->updateGoods(['website_id' => $this->website_id, 'shop_id' => $shopInfo['shop_id'], 'state' => 12], ['state' => 10]);
            }
            $shopModel->commit();
            return $res;
        } catch (\Exception $e) {
            $shopModel->rollback();
            $retval = $e->getMessage();
            return 0;
        }
    }

    public function updateShopApply($apply_id, $company_name, $company_province_id, $company_city_id, $company_district_id, $company_address_detail, $company_phone, $company_employee_count, $company_registered_capital, $contacts_name, $contacts_phone, $contacts_email, $business_licence_number, $business_sphere, $business_licence_number_electronic, $organization_code, $organization_code_electronic, $general_taxpayer, $bank_account_name, $bank_account_number, $bank_name, $bank_code, $bank_address, $bank_licence_electronic, $is_settlement_account, $settlement_bank_account_name, $settlement_bank_account_number, $settlement_bank_name, $settlement_bank_code, $settlement_bank_address, $tax_registration_certificate, $taxpayer_id, $tax_registration_certificate_electronic)
    {
        $data = array(
            'company_name' => $company_name,
            'company_province_id' => $company_province_id,
            'company_city_id' => $company_city_id,
            'company_district_id' => $company_district_id,
            'company_address_detail' => $company_address_detail,
            'company_phone' => $company_phone,
            'company_employee_count' => $company_employee_count,
            'company_registered_capital' => $company_registered_capital,
            'contacts_name' => $contacts_name,
            'contacts_phone' => $contacts_phone,
            'contacts_email' => $contacts_email,
            'business_licence_number' => $business_licence_number,
            'business_sphere' => $business_sphere,
            'business_licence_number_electronic' => $business_licence_number_electronic,
            'organization_code' => $organization_code,
            'organization_code_electronic' => $organization_code_electronic,
            'general_taxpayer' => $general_taxpayer,
            'bank_account_name' => $bank_account_name,
            'bank_account_number' => $bank_account_number,
            'bank_name' => $bank_name,
            'bank_code' => $bank_code,
            'bank_address' => $bank_address,
            'bank_licence_electronic' => $bank_licence_electronic,
            'is_settlement_account' => $is_settlement_account,
            'settlement_bank_account_name' => $settlement_bank_account_name,
            'settlement_bank_account_number' => $settlement_bank_account_number,
            'settlement_bank_name' => $settlement_bank_name,
            'settlement_bank_code' => $settlement_bank_code,
            'settlement_bank_address' => $settlement_bank_address,
            'tax_registration_certificate' => $tax_registration_certificate,
            'taxpayer_id' => $taxpayer_id,
            'tax_registration_certificate_electronic' => $tax_registration_certificate_electronic
        );
        $shop_apply = new VslShopApplyModel();
        $res = $shop_apply->save($data, [
            'apply_id' => $apply_id,
            'website_id' => $this->website_id
        ]);
        return $res;
    }

    /**
     * 用户店铺消费(non-PHPdoc)
     *
     * @see \data\api\IOrder::getShopUserConsume()
     */
    public function getShopUserConsume($shop_id, $uid)
    {
        $order = new VslOrderModel();
        $money = $order->Query([
            'buyer_id' => $uid, 'order_status' => 4
        ], 'order_money');
        if ($money) {
            return array_sum($money);
        } else {
            return 0;
        }

    }

    public function getUserOrderSum($uid)
    {
        $order = new VslOrderModel();
        $num = $order->Query([
            'buyer_id' => $uid, 'order_status' => 4
        ], 'order_id');
        if ($num) {
            return count($num);
        } else {
            return 0;
        }

    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IShop::getShopCommissionWithdrawList()
     */
    public function getShopAccountWithdrawList($page_index, $page_size = 0, $condition = '', $shop_name, $order = '')
    {
        // TODO Auto-generated method stub
        $shop_account_withdraw = new VslShopWithdrawModel();
        $memberSer = new Member();
        if ($shop_name) {
            $condition["sp.shop_name"] = array('like', '%' . $shop_name . '%');
        }
        $list = $shop_account_withdraw->getViewList($page_index, $page_size, $condition, $order);
        foreach ($list['data'] as $k => $v) {
            if ($v['type'] == 1 || $v['type'] == 4) {
                $v['type'] = '银行卡';
            } elseif ($v['type'] == 2) {
                $v['type'] = '微信';
            } elseif ($v['type'] == 3) {
                $v['type'] = '支付宝';
            }
            $v['cash'] = '¥' . $v['cash'];
            $v['ask_for_date'] = date('Y-m-d H:i:s', $v['ask_for_date']);
            if($v['payment_date']>0){
                $v['payment_date'] = date('Y-m-d H:i:s', $v['payment_date']);
            }else{
                $v['payment_date'] = '未到账';
            }
            $list['data'][$k]['status_name'] = $memberSer->getWithdrawStatusName($v['status']);

        }
        unset($v);
        return $list;
    }
    public function getShopWithdrawalCount($condition)
    {
        $commission_withdraw = new VslShopWithdrawModel();
        $user_sum = $commission_withdraw->where($condition)->count();
        if ($user_sum) {
            return $user_sum;
        } else {
            return 0;
        }
    }
    /*
     * (non-PHPdoc)
     * @see \data\api\IShop::getShopBankAccountList()
     */
    public function getShopBankAccountAll($page_index, $page_size = 0, $condition = '', $order = '')
    {
        // TODO Auto-generated method stub
        $shop_bank_account = new VslShopBankAccountModel();
        $all = $shop_bank_account->pageQuery($page_index, $page_size, $condition, $order, "*");
        return $all;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IShop::addShopBankAccount()
     */
    public function addShopBankAccount($shop_id, $type, $realname, $account_number, $remark,$bank_name,$bank_type,$bank_card)
    {
        // TODO Auto-generated method stub
        $shop_bank_account = new VslShopBankAccountModel();
        $shop_bank_account->save(['is_default' => 0], ['shop_id' => $shop_id]);
        $bank = new VslBankModel();
        $bank_names = $bank->getInfo(['bank_code'=>$bank_name],'bank_name')['bank_name'];
        $data = array(
            "shop_id" => $shop_id,
            "website_id" => $this->website_id,
            "type" => $type,
            "realname" => $realname,
            "branch_bank_name" => $bank_names,
            "account_number" => $account_number,
            "remark" => $remark,
            "create_date" => time(),
            'is_default' => 1,
            'bank_type'=>$bank_type,
            'bank_card'=>$bank_card,
            'bank_code'=>$bank_name,
        );
        $shop_bank_account = new VslShopBankAccountModel();
        $shop_bank_account->save($data);

        return $shop_bank_account->id;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IShop::updateShopBankAccount()
     */
    public function updateShopBankAccount($shop_id, $type, $realname, $account_number, $remark,$bank_name,$bank_type,$bank_card, $id)
    {
        // TODO Auto-generated method stub
        $shop_bank_account = new VslShopBankAccountModel();
        $bank = new VslBankModel();
        $bank_names = $bank->getInfo(['bank_code'=>$bank_name],'bank_name')['bank_name'];
        $data = array(
            "type" => $type,
            "branch_bank_name" => $bank_names,
            "realname" => $realname,
            "account_number" => $account_number,
            "remark" => $remark,
            "modify_date" => time(),
            'bank_type'=>$bank_type,
            'bank_card'=>$bank_card,
            'bank_code'=>$bank_name,
        );
        $retval = $shop_bank_account->where(array(
            "shop_id" => $shop_id,
            "id" => $id
        ))->update($data);
        return $retval;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IShop::modifyShopBankAccountIsdefault()
     */
    public function modifyShopBankAccountIsdefault($shop_id, $id)
    {
        // TODO Auto-generated method stub
        $shop_bank_account = new VslShopBankAccountModel();
        $retval = $shop_bank_account->where(array(
            "shop_id" => $shop_id
        ))->update(array(
            "is_default" => 0
        ));
        $retval = $shop_bank_account->where(array(
            "shop_id" => $shop_id,
            "id" => $id
        ))->update(array(
            "is_default" => 1
        ));
        return $retval;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IShop::deleteShopBankAccouht()
     */
    public function deleteShopBankAccouht($condition)
    {
        // TODO Auto-generated method stub
        $shop_bank_account = new VslShopBankAccountModel();
        $condition['shop_id'] = $this->instance_id;
        $retval = $shop_bank_account->destroy($condition);
        return $retval;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IShop::getShopAccount()
     */
    public function getShopAccount($shop_id)
    {
        // TODO Auto-generated method stub
        $shop_account = new ShopAccount();
        $account_obj = $shop_account->getShopAccount($shop_id);
        return $account_obj;
    }

    /*
     * 店铺申请提现
     * (non-PHPdoc)
     * @see \data\api\IShop::applyShopCommissionWithdraw()
     */
    public function applyShopAccountWithdraw($shop_id, $bank_account_id, $cash)
    {
        $cash = abs($cash);
        $Config = new WebConfig();
        $withdraw_type = $Config->getConfig(0, 'WITHDRAW_BALANCE');
        $is_examine = $withdraw_type['value']['is_examine'];//提现自动审核 1自动
        $make_money = $withdraw_type['value']['make_money'];//提现自动打款 1自动
        $status = $is_examine==1 ? 2 : 1;//2自动审核 1自动

        $charge = 0;
        //提现手续费
        if($withdraw_type['value']['withdraw_poundage']) {
            $charge = twoDecimal($cash * $withdraw_type['value']['withdraw_poundage']/100);//手续费
            if($withdraw_type['value']['withdrawals_end'] && $withdraw_type['value']['withdrawals_begin']){
                if ($cash <= $withdraw_type['value']['withdrawals_end'] && $cash >=  $withdraw_type['value']['withdrawals_begin']) {
                    $charge = 0;//免手续费区间
                }
            }
        }
        // 查询店铺的账户情况
        $shop_account_obj = $this->getShopAccount($shop_id);
        if($cash+$charge<= $shop_account_obj['shop_total_money']){
            $service_charge= $cash;
        }else if($cash-$charge>=0){
            $service_charge = $cash-$charge;
        }else{
            return USER_NO_WITHDRAW;
        }
        // 判断是否店铺提现设置是否为空 是否启用
        if ($withdraw_type['is_use'] == 0) {
            $result['code'] = -1;
            $result['message'] = USER_WITHDRAW_NO_USE;
            return $result;
        }
        // 最小提现额判断
        if ($cash < $withdraw_type['value']["withdraw_cash_min"]) {
            $result['code'] = -1;
            $result['message'] = USER_WITHDRAW_MIN;
            return $result;
        }

        $bank = new VslShopBankAccountModel();
        $bank_account_info = $bank->getInfo(['id'=>$bank_account_id]);//type: 1银行卡(自动提现) 2微信 3支付宝 4银行卡(手动提现)
        if($bank_account_info['type']==2){//微信支付
            $user = new UserModel();
            $wx_openid = $user->getInfo(['website_id'=>$this->website_id,'is_member'=>1,'user_tel'=>$bank_account_info['account_number']],'wx_openid')['wx_openid'];
        }
        $shop_account = new shopAccount();
        $rate = $shop_account->getShopAccountRate($shop_id);
        $platform_money = abs(twoDecimal($rate*$cash/100));
        //银行卡
        if($bank_account_info['type']==1 || $bank_account_info['type']==4){
            if($withdraw_type['value']['withdraw_message']){
                $withdraw_message = explode(',',$withdraw_type['value']['withdraw_message']);
                if(in_array(4,$withdraw_message)){
                    $bank_account_info['type'] = 4;
                }
            }
        }
        // 判断店铺金额是否够
        if ($shop_account_obj["shop_total_money"] >= $cash+$platform_money+$charge) {
            $withdraw_no = $this->getWithdrawNo();
            $shop_account_withdraw = new VslShopWithdrawModel();
            $data = array(
                "shop_id" => $shop_id,
                "withdraw_no" => $withdraw_no,
                "type" => $bank_account_info["type"],
                "account_number" => $bank_account_info["account_number"],
                "realname" => $bank_account_info["realname"],
                "remark" => $bank_account_info["remark"],
                "platform_money"=>$platform_money,
                "service_charge"=>$service_charge,
                'charge'=>(-1)*$charge,
                "cash" => (-1)*$cash,
                "uid"=>$this->uid,
                "status" => $status,
                "ask_for_date" => time(),
                "website_id" => $this->website_id
            );
            $id = $shop_account_withdraw->save($data);
            if ($shop_account_withdraw->id > 0) {
                $shop_account_service = new ShopAccount();
                //TODO...优化，可以合并一起
                if($is_examine==1 && $make_money==1){//自动审核,自动打款
                   $res =  $shop_account_service->addShopAccountData($shop_id,  $cash*(-1), $id,$is_examine,$make_money,$wx_openid,$shop_account_withdraw->withdraw_no,$bank_account_info['type'],$bank_account_info['account_number'],$service_charge,$charge,$platform_money,$bank_account_info['realname']);
                }
                if($is_examine==1 && $make_money==2){//自动审核,手动打款
                    $res =$shop_account_service->addShopAccountData($shop_id,  $cash*(-1),  $id, $is_examine,$make_money,$wx_openid,$shop_account_withdraw->withdraw_no,$bank_account_info['type'],$bank_account_info['account_number'],$service_charge,$charge,$platform_money,$bank_account_info['realname']);
                }
                if($is_examine==2 && $make_money==1){//手动审核,自动打款
                    $res =$shop_account_service->addShopAccountData($shop_id, $cash*(-1),  $id, $is_examine,$make_money,$wx_openid,$shop_account_withdraw->withdraw_no,$bank_account_info['type'],$bank_account_info['account_number'],$service_charge,$charge,$platform_money,$bank_account_info['realname']);
                }
                if($is_examine==2 && $make_money==2){//手动审核,手动打款
                    $res = $shop_account_service->addShopAccountData($shop_id,$cash*(-1), $id, $is_examine,$make_money,$wx_openid,$shop_account_withdraw->withdraw_no,$bank_account_info['type'],$bank_account_info['account_number'],$service_charge,$charge,$platform_money,$bank_account_info['realname']);
                }
            }
            return $res;
        } else {
            // 店铺账户可提现资金不足
            return USER_NO_WITHDRAW;
        }
    }

    /*
     * 店铺提现审核
     * (non-PHPdoc)
     * @see \data\api\IShop::shopAccountWithdrawAudit() 当前状态 1待审核2待打款3已打款4拒绝打款 5打款失败-1审核不通过
     */
    public function shopAccountWithdrawAudit($id, $status, $memo)
    {
        // TODO Auto-generated method stub
        $shop_account_withdraw = new VslShopWithdrawModel();
        $shop_account_service = new ShopAccountService();
        // 得到当前提现的具体信息
        $shop_account_withdraw_info = $shop_account_withdraw->getInfo(['id'=>$id],'*');
        if ($status == 2) {
            if($shop_account_withdraw_info['status'] == 2){
                return AjaxReturn(FAIL,[],'请勿重复操作!');
            }
            // 平台通过申请，更新平台的账户情况
            $retval= $shop_account_service->addAuditShopAccountData($shop_account_withdraw_info["platform_money"],$shop_account_withdraw_info["charge"],$shop_account_withdraw_info["service_charge"],$id,$shop_account_withdraw_info['shop_id'],$shop_account_withdraw_info["cash"],$shop_account_withdraw_info["uid"],$shop_account_withdraw_info["withdraw_no"],$shop_account_withdraw_info["type"],$shop_account_withdraw_info["account_number"],$shop_account_withdraw_info["realname"]);
        }
        if ($status == -1) {
            if($shop_account_withdraw_info['status'] == -1){
                return AjaxReturn(FAIL,[],'请勿重复操作!');
            }
            // 平台审核不通过，给店铺打回一笔金额
            $retval=$shop_account_service->addShopAccountRecords($shop_account_withdraw_info["platform_money"],$shop_account_withdraw_info["charge"],$shop_account_withdraw_info["service_charge"],$shop_account_withdraw_info["cash"],$id,$shop_account_withdraw_info['shop_id'],$status, "店铺申请提现, 平台审核不通过。");
        }
        if ($status == 4) {
            if($shop_account_withdraw_info['status'] == 4){
                $results['code'] = -1;
                $results['message'] = "请勿重复操作";
            }
            // 平台拒绝提现，给店铺打回一笔金额
            $retval=$shop_account_service->addShopAccountRecords($shop_account_withdraw_info["platform_money"],$shop_account_withdraw_info["charge"],$shop_account_withdraw_info["service_charge"],$shop_account_withdraw_info["cash"],$id,$shop_account_withdraw_info['shop_id'],$status, "店铺申请提现, 平台拒绝提现。");
        }
        if ($status == 3) {/*银行*/
            if($shop_account_withdraw_info['status'] == 3){
                return AjaxReturn(FAIL,[],'请勿重复操作!');
            }
            // 平台同意打款，更新平台的账户情况
            $retval= $shop_account_service->addAgreeShopAccountData($shop_account_withdraw_info["service_charge"],$id,$shop_account_withdraw_info['shop_id'],$shop_account_withdraw_info["cash"],'店铺申请提现待打款，平台同意在线打款。');
        }
        if ($status == 5) {/*手动*/
            if($shop_account_withdraw_info['status'] == 3){
                return AjaxReturn(FAIL,[],'请勿重复操作!');
            }
            // 平台同意打款，更新平台的账户情况
            $retval= $shop_account_service->addAgreeShopAccountDatas($shop_account_withdraw_info["service_charge"],$id,$shop_account_withdraw_info['shop_id'],$shop_account_withdraw_info["cash"],'店铺申请提现待打款，平台同意手动打款。');
        }
        return $retval;
    }

    /**
     *
     * {@inheritdoc}店铺提现详情
     *
     * @see \ata\api\IWeixin::getKeyReplyDetail($id)
     */
    public function shopAccountWithdrawDetail($id)
    {
        $shop_account_withdraw = new VslShopWithdrawModel();
        $info = $shop_account_withdraw->getInfo(['id' => $id], '*');
        if (!empty($info)) {
            $info['ask_for_date'] = date('Y-m-d H:i:s', $info['ask_for_date']);
            if($info['payment_date']>0){
                $info['payment_date'] = date('Y-m-d H:i:s', $info['payment_date']);
            }else{
                $info['payment_date'] = '未到账';
            }
            $shop = new VslShopModel();
            $info['shop_name'] = $shop->getInfo(['shop_id' => $info['shop_id']], 'shop_name')['shop_name'];
            if ($info['type'] == 1 || $info['type'] == 4) {
                $info['type_name'] = '银行卡';
            } elseif ($info['type'] == 2) {
                $info['type_name'] = '微信';
            } elseif ($info['type'] == 3) {
                $info['type_name'] = '支付宝';
            }
            if ($info['status'] == 2) {
                $info['status_name'] = '待打款';
            } elseif ($info['status'] == 3) {
                $info['status_name'] = '已打款';
            } elseif ($info['status'] == -1) {
                $info['status_name'] = '审核不通过';
            } elseif ($info['status'] == 1) {
                $info['status_name'] = '待审核';
            } elseif ($info['status'] == 4) {
                $info['status_name'] = '拒绝打款';
            } elseif ($info['status'] == 5) {
                $info['status_name'] = '打款失败';
            }
        }
        return $info;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \ata\api\IWeixin::getKeyReplyDetail($id)
     */
    public function getShopBankAccountDetail($shop_id, $id)
    {
        $shop_bank_account = new VslShopBankAccountModel();
        $info = $shop_bank_account->getInfo(['id'=>$id],'');
        return $info;
    }

    /**
     * 生成佣金流水号
     */
    private function getWithdrawNo()
    {
        $no_base = date("ymdhis", time());
        $withdraw_no = $no_base . rand(111, 999);
        return $withdraw_no;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IShop::getShopAccountCountList()
     */
    public function getShopAccountCountList($page_index, $page_size = 0, $condition = '', $order = '', $search_text)
    {
        // TODO Auto-generated method stub
        $shop = new VslShopModel();
        if ($search_text) {
            $shop_id = $shop->Query([
                "shop_name" => array('like', '%' . $search_text . '%')
            ], "shop_id");
            $condition['shop_id'] = ['in', $shop_id];
        }
        $shop_account = new VslShopAccountModel();
        $list = $shop_account->pageQuery($page_index, $page_size, $condition, $order, '*');
        $shop_withdraw = new VslShopWithdrawModel();
        foreach ($list["data"] as $k => $v) {
            $shop = new VslShopModel();
            $shop_info = $shop->getInfo([
                "shop_id" => $v["shop_id"],
            ], "shop_name,shop_logo,shop_platform_commission_rate");
            $shop_account_records = new VslShopAccountModel();
            $shop_account_info = $shop_account_records->getInfo([
                "shop_id" => $v["shop_id"]
            ], "*");
            $shop_withdraw_cash = $shop_withdraw->Query(["shop_id" => $v["shop_id"], 'status' => [['>', 0], ['<', 3]]], 'cash');
            $shop_logo = $shop_info["shop_logo"];
            $shop_name = $shop_info["shop_name"];
            //冻结余额=总额-已提现-提现中 #不再使用该规则
            //edit for 2020/1-/27 调整 冻结中=订单正常的店铺到账金额 状态为未完成，且未关闭的订单店铺到账金额 历史久远的订单是没有shop_order_money 只有order_money
            //营业总额 = 冻结中+可用+已提现+提现中
            $shop_id = $v["shop_id"];
            $sql = "select IFNULL(sum(order_money),0) as total from vsl_order where shop_id = $shop_id and pay_status=2 and order_status not in(5,4) and shop_order_money is null";//已支付金额1
            $result1 = Db::query($sql);
            $sql2 = "select IFNULL(sum(shop_order_money),0) as total from vsl_order where shop_id = $shop_id and pay_status=2 and order_status not in(5,4)";//已支付金额2
            $result2 = Db::query($sql2);
            $list["data"][$k]["freezing_money"] = $result1[0]['total'] + $result2[0]['total']; //冻结金额
            $list["data"][$k]["shop_logo"] = $shop_logo;
            $list["data"][$k]["shop_name"] = $shop_name;
            $list["data"][$k]["shop_entry_money"] = $list["data"][$k]["freezing_money"] + $shop_account_info['shop_total_money'] + $shop_account_info["shop_withdraw"] + $shop_account_info["shop_freezing_money"]; //营业总额
            $list["data"][$k]["withdraw_ing"] = array_sum($shop_withdraw_cash);
            $list["data"][$k]["shop_platform_commission_rate"] = $shop_info['shop_platform_commission_rate'];
            $list["data"][$k]["shop_total_money"] = $shop_account_info['shop_total_money'];
        }
        return $list;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IShop::getShopAccountRecordsList()
     */
    public function getShopAccountRecordsList($page_index, $page_size = 0, $condition = '', $order = '')
    {
        // TODO Auto-generated method stub
        $shop_account_records = new VslShopAccountRecordsModel();
        $list = $shop_account_records->pageQuery($page_index, $page_size, $condition, $order, '*');
        foreach ($list["data"] as $k => $v) {
            // var_dump($v["shop_id"]);
            $shop = new VslShopModel();
            $shop_info = $shop->getInfo([
                "shop_id" => $v["shop_id"]
            ], "shop_name,shop_logo");
            $shop_logo = $shop_info["shop_logo"];
            $shop_name = $shop_info["shop_name"];
            $list["data"][$k]["shop_logo"] = $shop_logo;
            $list["data"][$k]["shop_name"] = $shop_name;
        }
        return $list;
    }

    /**
     * (non-PHPdoc)
     * @see \data\api\IShop::getShopOrderReturnList()
     */
    public function getShopOrderReturnList($page_index, $page_size = 0, $condition = '', $order = '')
    {
        $shop_order_return_model = new VslShopOrderReturnModel();
        $list = $shop_order_return_model->pageQuery($page_index, $page_size, $condition, $order, '*');
        return $list;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IShop::getShopOrderAccountRecordsList()
     */
    public function getShopOrderAccountRecordsList($page_index, $page_size = 0, $condition = '', $order = '')
    {
        $order_goods = new VslOrderGoodsViewModel();
        $return = $order_goods->getOrderGoodsViewList($page_index, $page_size, $condition, $order);
        return $return;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IShop::getShopAll()
     */
    public function getShopAll($condition)
    {
        // TODO Auto-generated method stub
        $shop = new VslShopModel();
        $shop_all = $shop->where($condition)
            ->order(" shop_sales desc ")
            ->limit(10)
            ->select();
        return $shop_all;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IShop::getShopAccountRecordCount()
     */
    public function getShopAccountRecordCount($start_date, $end_date, $shop_id)
    {
        // TODO Auto-generated method stub
        // 可提现余额
        $shop_account_withdraw = new VslShopWithdrawModel();
        $withdraw_condition["shop_id"] = $shop_id;
        $money_condition["shop_id"] = $shop_id;
        if ($start_date != "") {
            $withdraw_condition["ask_for_date"][] = [
                ">",
                getTimeTurnTimeStamp($start_date)
            ];
            $money_condition["create_time"][] = [
                ">",
                getTimeTurnTimeStamp($start_date)
            ];
        }
        if ($end_date != "") {
            $withdraw_condition["ask_for_date"][] = [
                "<",
                getTimeTurnTimeStamp($end_date)
            ];
            $money_condition["create_time"][] = [
                "<",
                getTimeTurnTimeStamp($end_date)
            ];
        }
        // 已提现
        $withdraw_condition["status"] = 1;
        $withdraw_cash = $shop_account_withdraw->where($withdraw_condition)->sum("cash");
        // 提现审核中
        $withdraw_condition["status"] = 0;
        $withdraw_isaudit = $shop_account_withdraw->where($withdraw_condition)->sum("cash");
        $shop_order_account_record = new VslShopOrderReturnModel();
        // 店铺营业额
        $shop_order_money = $shop_order_account_record->where($money_condition)->sum("order_pay_money");
        $array = array(
            "withdraw_cash" => $withdraw_cash,
            "withdraw_isaudit" => $withdraw_isaudit,
            "shop_order_money" => $shop_order_money
        );
        return $array;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IShop::getShopAccountSales()
     */
    public function getShopAccountSales($condition)
    {
        // TODO Auto-generated method stub
        $shop_order_account_records = new VslShopOrderReturnModel();
        // 店铺销售额
        $shop_sales = $shop_order_account_records->where($condition)->sum("order_pay_money");

        // 平台金额
        $platform_money = $shop_order_account_records->where($condition)->sum("platform_money");

        // 店铺金额
        $shop_money = $shop_sales - $platform_money;
        return [
            "shop_sale" => $shop_sales,
            "platform_money" => $platform_money,
            "shop_money" => $shop_money
        ];
    }

    public function updateShopPlatformCommissionRate($shop_id, $shop_platform_commission_rate)
    {
        $shop_account = new VslShopAccountModel();
        $res = $shop_account->save([
            "shop_platform_commission_rate" => $shop_platform_commission_rate
        ], [
            'shop_id' => $shop_id,
            'website_id' => $this->website_id
        ]);
        return $res;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IShop::getShopCount()
     */
    public function getShopCount($condition)
    {
        // TODO Auto-generated method stub
        $shop = new VslShopModel();
        $shop_list = $shop->getQuery($condition, "count(shop_id) as count", "");
        return $shop_list[0]["count"];
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IShop::getShopWithdrawCount()
     */
    public function getShopWithdrawCount($condition)
    {
        // TODO Auto-generated method stub
        $shop_account_withdraw = new VslShopWithdrawModel();
        $withdraw_isaudit = $shop_account_withdraw->getQuery($condition, "sum(cash) as sum", '');
        return $withdraw_isaudit[0]["sum"];
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IShop::addShopBankAccount()
     */
    public function addMemberWithdrawSetting($shop_id, $withdraw_cash_min, $withdraw_multiple, $withdraw_poundage, $withdraw_message, $withdraw_account_type)
    {
        // TODO Auto-generated method stub
        $member_withdraw_setting = new VslMemberWithdrawSettingModel();
        $data = array(
            "shop_id" => $shop_id,
            "withdraw_cash_min" => $withdraw_cash_min,
            "withdraw_multiple" => $withdraw_multiple,
            "withdraw_poundage" => $withdraw_poundage,
            "withdraw_message" => $withdraw_message,
            "withdraw_account_type" => $withdraw_account_type,
            "create_time" => time()
        );
        $member_withdraw_setting->save($data);
        return $member_withdraw_setting->id;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IShop::updateShopBankAccount()
     */
    public function updateMemberWithdrawSetting($shop_id, $withdraw_cash_min, $withdraw_multiple, $withdraw_poundage, $withdraw_message, $withdraw_account_type, $id)
    {
        // TODO Auto-generated method stub
        $member_withdraw_setting = new VslMemberWithdrawSettingModel();
        $data = array(
            "withdraw_cash_min" => $withdraw_cash_min,
            "withdraw_multiple" => $withdraw_multiple,
            "withdraw_poundage" => $withdraw_poundage,
            "withdraw_message" => $withdraw_message,
            "withdraw_account_type" => $withdraw_account_type,
            "modify_time" => time()
        );
        $retval = $member_withdraw_setting->where(array(
            "shop_id" => $shop_id,
            "id" => $id
        ))->update($data);
        return $retval;
    }

    /**
     * 获取提现设置信息
     *
     * @param string $field
     */
    public function getWithdrawInfo($shop_id)
    {
        $member_withdraw_setting = new VslMemberWithdrawSettingModel();
        $info = $member_withdraw_setting->getInfo([
            "shop_id" => $shop_id
        ]);

        return $info;
    }

    /**
     * (non-PHPdoc)
     * @see \data\api\IShop::addPlatformShop()
     * $uid 已选会员uid
     */
    public function addPlatformShop($shopInfo, $shopApplyInfo,$uid)
    {
        $shop_model = new VslShopModel();
        $check = $shop_model->getInfo(['website_id' => $this->website_id,'shop_name' => $shopInfo['shop_name']]);
        if($check){
            return -8001;
        }
        $shop_model->startTrans();
        try {
            $res = $uid;
            if ($res > 0) {
                $data = [
                    'shop_name' => $shopInfo['shop_name'],
                    'shop_type' => $shopInfo['shop_type'],
                    'uid' => $res,
                    'shop_group_id' => $shopInfo['shop_group_id'],
                    'shop_sort' => $shopInfo['shop_sort'],
                    'recommend_uid' => 0,
                    'shop_platform_commission_rate' => $shopInfo['shop_platform_commission_rate'],
                    'margin' => $shopInfo['margin'],
                    'shop_state' => $shopInfo['shop_state'],
                    'shop_audit' => $shopInfo['shop_audit'],
                    'merchants_uid' => $shopInfo['merchants_uid'],
                ];
                $res = $this->addshop($data);
            }
            if ($res > 0) {
                $shopApplyInfo['shop_id'] = $res;
                $this->addShopInfo($shopApplyInfo);
            }
            $website = new WebSite();
            $dateArr = $website->getWebCreateTime($this->website_id);
            $path = './public/addons_status/' . $dateArr['year'].'/'.$dateArr['month'].'/'.$dateArr['day'].'/'. $this->website_id;
            if(file_exists($path .'/addons_'.$res)){
                unlink($path .'/addons_'.$res);
            }
            $shop_model->commit();
            return $res;
        } catch (\Exception $e) {
            $shop_model->rollback();
            return $e->getMessage();
        }


    }

    /*
     * 添加店铺注册信息
     */
    public function addShopInfo($shopApplyInfo)
    {
        $shopInfoModel = new VslShopInfoModel();
        $data = [
            'apply_type' => $shopApplyInfo['apply_type'],
            'company_name' => $shopApplyInfo['company_name'],
            'company_province_id' => $shopApplyInfo['company_province_id'],
            'company_city_id' => $shopApplyInfo['company_city_id'],
            'company_district_id' => $shopApplyInfo['company_district_id'],
            'company_address_detail' => $shopApplyInfo['company_address_detail'],
            'company_phone' => $shopApplyInfo['company_phone'],
            'company_employee_count' => $shopApplyInfo['company_employee_count'],
            'company_registered_capital' => $shopApplyInfo['company_registered_capital'],
            'contacts_name' => $shopApplyInfo['contacts_name'],
            'contacts_phone' => $shopApplyInfo['contacts_phone'],
            'contacts_email' => $shopApplyInfo['contacts_email'],
            'company_type' => $shopApplyInfo['company_type'],
            'contacts_card_no' => $shopApplyInfo['contacts_card_no'],
            'contacts_card_electronic_1' => $shopApplyInfo['contacts_card_electronic_1'],
            'contacts_card_electronic_2' => $shopApplyInfo['contacts_card_electronic_2'],
            'contacts_card_electronic_3' => $shopApplyInfo['contacts_card_electronic_3'],
            'business_licence_number' => $shopApplyInfo['business_licence_number'],
            'business_sphere' => $shopApplyInfo['business_sphere'],
            'business_licence_number_electronic' => $shopApplyInfo['business_licence_number_electronic'],
            'post_data' => $shopApplyInfo['post_data'],
            'website_id' => $this->website_id,
            'joinpay_machid' => isset($shopApplyInfo['joinpay_machid']) ? $shopApplyInfo['joinpay_machid'] : ''
        ];
        $check = $shopInfoModel->getInfo(['shop_id' => $shopApplyInfo['shop_id']], 'id');
        if ($check) {
            $data['update_time'] = time();
            $res = $shopInfoModel->save($data, ['id' => $check['id']]);
        } else {
            $data['create_time'] = time();
            $data['shop_id'] = $shopApplyInfo['shop_id'];
            $res = $shopInfoModel->save($data);
        }
        return $res;
    }

    /**
     * {@inheritdoc}
     * @see \data\api\IShop::updateShopOfflineStoreByshop()
     */
    public function updateShopOfflineStoreByshop($shop_id, $shop_vrcode_prefix, $live_store_name, $live_store_tel, $live_store_address, $live_store_bus, $latitude_longitude)
    {
        $data = array(
            'shop_vrcode_prefix' => $shop_vrcode_prefix,
            'live_store_name' => $live_store_name,
            'live_store_tel' => $live_store_tel,
            'live_store_address' => $live_store_address,
            'live_store_bus' => $live_store_bus,
            'latitude_longitude' => $latitude_longitude

        );
        $shop = new VslShopModel();
        $res = $shop->save($data, ['shop_id' => $shop_id]);
        return $res;
    }

    /*
     * 判断用户是否是店铺超级管理员
     */
    public function getShopByUid($uid)
    {
        $shop = new VslShopModel();
        $base_info = $shop->getInfo(['uid' => $uid], 'id');
        if (!$base_info) {
            return false;
        }
        return $base_info['id'];
    }

    /**
     * (non-PHPdoc)
     *
     * 删除店铺版本
     */
    public function deleteShopLevel($instance_typeid)
    {
        $count = $this->getShopLevelIsUse($instance_typeid);
        $check = $this->checkIsDefault($instance_typeid);
        if ($count > 0) {
            return SHOPLEVEL_ISUSE;
        } 
        if($check){
            return INSTANCE_TYPE_DELETE_ERROR;
        }
        $merchant_version = new InstanceTypeModel();
        $res = $merchant_version->where('instance_typeid', $instance_typeid)->delete();
        return $res;
        
    }

    /*
     * 判断店铺版本下面是否有店铺
     */
    public function getShopLevelIsUse($instance_typeid)
    {
        $instance = new InstanceModel();
        $count = $instance->getCount(['instance_typeid' => $instance_typeid]);
        return $count;
    }
    /*
     * 判断店铺版本是否是默认等级
     */
    public function checkIsDefault($instance_typeid)
    {
        $instance = new InstanceTypeModel();
        $result = $instance->getInfo(['instance_typeid' => $instance_typeid,'is_default' => 1]);
        return $result;
    }

    /*
     * 获取店铺协议
     */
    public function getShopProtocol($key = 'direction')
    {
        $config = new WebConfig();
        // TODO Auto-generated method stub
        $protocol = $config->getConfig(0, $key, $this->website_id);
        if (empty($protocol)) {
            $data = array(
                "title" => "",
                "content" => "",
                "key" => $key
            );
            $res = $config->addConfig(0, $key, json_encode($data), "", 1);
            if (!$res > 0) {
                return null;
            } else {
                $protocol = $config->getConfig(0, $key, $this->website_id);
            }
        }
        $value = $protocol["value"];
        return $value;
    }

    /*
     * 设置店铺协议
     */
    public function setShopProtocol($value, $key = 'direction')
    {
        $config = new WebConfig();
        $param = [
           'value' => $value,
           'website_id' => $this->website_id,
           'instance_id' => 0,
           'key' => $key,
           'desc' => "",
           'is_use' => 1,
        ];
        $res = $config->setConfigOne($param);
        return $res;
    }

    /**
     * 店铺设置
     */
    public function setShopSetting($is_use = 0, $platform_commission_percentage = '0.00')
    {
        $ConfigService = new AddonsConfigService();
        $value = array(
            'platform_commission_percentage' => $platform_commission_percentage//平台抽成比率
        );
        $shop_info = $ConfigService->getAddonsConfig('shop', $this->website_id);
        if (!empty($shop_info)) {
            $res = $ConfigService->updateAddonsConfig($value, "店铺设置", $is_use, "shop");
        } else {
            $res = $ConfigService->addAddonsConfig($value, "店铺设置", $is_use, "shop");
        }
        return $res;
    }

    /*
     * 获取店铺设置
     *
     */
    public function getShopSetting($website_id = 0)
    {
        if ($website_id) {
            $id = $website_id;
        } else {
            $id = $this->website_id;
        }
        $addonsConfigSer = new \data\service\AddonsConfig();
        $info = $addonsConfigSer->getAddonsConfig('shop', $id);
        if (empty($info['value'])) {
            return array(
                'value' => array(
                    'platform_commission_percentage' => ''
                ),
                'is_use' => 0
            );
        } else {
            return $info;
        }
    }

    /**
     * (non-PHPdoc)
     */
    public function addMemberFavouites($fav_id, $fav_type, $log_msg)
    {
        $member_favorites = new VslMemberFavoritesModel();
        $count = $member_favorites->where(array(
            "fav_id" => $fav_id,
            "uid" => $this->uid,
            "fav_type" => $fav_type,
            "website_id" => $this->website_id,
        ))->count("log_id");
        // 检查数据表中，防止用户重复收藏
        if ($count > 0) {
            return 1;
        }
        if ($fav_type == 'shop') {
            $shop = new VslShopModel();
            $shop_info = $shop->getInfo([
                'shop_id' => $fav_id,
                'website_id' => $this->website_id
            ], 'shop_name,shop_logo,shop_collect,shop_state');
            if ($shop_info['shop_state'] != 1){
                return false;
            }
            $data = array(
                'uid' => $this->uid,
                'fav_id' => $fav_id,
                'fav_type' => $fav_type,
                'fav_time' => time(),
                'shop_id' => $fav_id,
                'shop_name' => $shop_info['shop_name'],
                'shop_logo' => empty($shop_info['shop_logo']) ? ' ' : $shop_info['shop_logo'],
                'goods_name' => '',
                'goods_image' => '',
                'log_price' => 0,
                'log_msg' => $log_msg,
                'website_id' => $this->website_id
            );
            $retval = $member_favorites->save($data);
            $shop->save(array(
                'shop_collect' => $shop_info['shop_collect'] + 1
            ), [
                'shop_id' => $fav_id,
                'website_id' => $this->website_id
            ]);
            return $retval;
        }
    }

    /**
     * (non-PHPdoc)
     */
    public function deleteMemberFavorites($fav_id, $fav_type)
    {
        $member_favorites = new VslMemberFavoritesModel();
        if (!empty($this->uid)) {
            if ($fav_type == 'shop') {
                $shop = new VslShopModel();
                $shop_info = $shop->getInfo([
                    'shop_id' => $fav_id,
                    'website_id' => $this->website_id
                ], 'shop_name,shop_logo,shop_collect,shop_state');
                if ($shop_info['shop_state'] != 1){
                    return false;
                }
                $condition = array(
                    'fav_id' => $fav_id,
                    'fav_type' => $fav_type,
                    'uid' => $this->uid,
                    'website_id' => $this->website_id
                );
                $retval = $member_favorites->destroy($condition);
                $shop_collect = empty($shop_info["shop_collect"]) ? 0 : $shop_info["shop_collect"];
                $shop_collect--;
                if ($shop_collect < 0) {
                    $shop_collect = 0;
                }
                $shop->save([
                    'shop_collect' => $shop_collect
                ], [
                    'shop_id' => $fav_id,
                    'website_id' => $this->website_id
                ]);
                return $retval;
            }
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberFavorites()
     */
    public function getMemberShopsFavoritesList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $fav = new VslMemberFavoritesModel();
        $list = $fav->getShopsFavouitesViewList($page_index, $page_size, $condition, $order);
        return $list;
    }
    /*
     * 获取店铺评价
     */
    public function getShopEvaluate($shop_id = 0) {
        $shopEvaluate = new VslShopEvaluateModel();
        $count = $shopEvaluate->getCount(['shop_id' => $shop_id, 'website_id' => $this->website_id]);
        $evaluateData = ['shop_desc' => 5, 'shop_service' => 5, 'shop_stic' => 5, 'comprehensive' => 5];
        if (!$count) {
            return $evaluateData;
        }
        $evaluateData['count'] = $count;
        $evaluateData['shop_desc'] = floor(($shopEvaluate->getSum(['shop_id' => $shop_id, 'website_id' => $this->website_id], 'shop_desc') / $count)*10)/10 ?:5;//保留小数点后一位
        $evaluateData['shop_service'] = floor(($shopEvaluate->getSum(['shop_id' => $shop_id, 'website_id' => $this->website_id], 'shop_service') / $count)*10)/10 ?:5;
        $evaluateData['shop_stic'] = floor(($shopEvaluate->getSum(['shop_id' => $shop_id, 'website_id' => $this->website_id], 'shop_stic') / $count)*10)/10 ?: 5;
        $evaluateData['comprehensive'] = floor((($evaluateData['shop_desc'] + $evaluateData['shop_service'] + $evaluateData['shop_stic']) / 3)*10)/10 ?: 5;
        return $evaluateData;
    }
    
    /*
     * 获取店铺入驻申请自定义表单
     */
    /*
     * 获取订单自定义表单
     */
    public function getShopCustomForm(){
        if(!getAddons('customform', $this->website_id)){
            return [];
        }
        $add_config = new AddonsConfigService();
        $customform =$add_config->getAddonsConfig("customform",$this->website_id, 0, 1);
        $custom_server = new CustomServer();
        $custom_form=[];
        if($customform['shop_apply_dealer']==1){
            $custom_form_id =  $customform['apply_id'];
            $custom_form_info = $custom_server->getCustomFormDetail($custom_form_id)['value'];
            if($custom_form_info){
                $custom_form =  json_decode($custom_form_info,true);
            }
        }
        return $custom_form;
    }

    /**
     * 获取店铺LOGO
     * @param $shop_id int [店铺id]
     * @return string
     */
    public function getShopLogo($shop_id)
    {
        //如果是自营店就取商城sys_website表的logo
        $shop_logo = '';
        if ($shop_id == 0) {
            $website_service = new WebSite();
            $website = $website_service->getWebSiteInfo($this->website_id);
            $shop_logo = $website['logo'];
        }

        //如果是店铺就取店铺vsl_shop的logo
        $logoId = $base_info = $this->getShopInfo($shop_id, 'shop_logo')['shop_logo'];
        $picture = new AlbumPictureModel();
        if (!empty($logoId)) {
            $shopRes = $picture->getInfo(['pic_id' => $logoId],'pic_cover,pic_cover_mid,pic_cover_micro');
            $shop_logo = $shopRes['pic_cover'];
        }

        return __IMG($shop_logo);
    }
    /**分账系列开始 */

    /*
     * 获取分账列表
     */
    public function getShopSeparateCountList($page_index, $page_size = 0, $condition = '', $order = '', $search_text)
    {
        $shop = new VslShopModel();
        if ($search_text) {
            $shop_id = $shop->Query([
                "shop_name" => array('like', '%' . $search_text . '%')
            ], "shop_id");
            $condition['shop_id'] = ['in', $shop_id];
        }
        $shop_account = new VslShopSeparateModel();
        $list = $shop_account->pageQuery($page_index, $page_size, $condition, $order, '*');
        foreach ($list["data"] as $k => $v) {
            $shop = new VslShopModel();
            $shop_info = $shop->getInfo([
                "shop_id" => $v["shop_id"],
            ], "shop_name,shop_logo,shop_platform_commission_rate,joinpay_machid");
            $shop_logo = $shop_info["shop_logo"];
            $shop_name = $shop_info["shop_name"];
            $joinpay_machid = $shop_info["joinpay_machid"];
            $list["data"][$k]["joinpay_machid"] = $joinpay_machid;
            $list["data"][$k]["shop_logo"] = $shop_logo;
            $list["data"][$k]["shop_name"] = $shop_name;
            $list["data"][$k]["add_time"] = date('Y-m-d H:i:s',$list["data"][$k]["add_time"]);
            if(!empty($list["data"][$k]["send_time"])){
                $list["data"][$k]["send_time"] = date('Y-m-d H:i:s',$list["data"][$k]["send_time"]);
            }else{
                $list["data"][$k]["send_time"] = '-';
            }
        }
        return $list;
    }
    /**
     * 确认处理分账信息
     */
    public function shopSeparateSend($id){
        $shopSeparateModel = new VslShopSeparateModel();
        $info = $shopSeparateModel->getInfo(['id'=>$id],'*');
        // Db::startTrans();
        try {
            if(empty($info)){
                $result['code'] = -1;
                $result['message'] = '操作记录不存在';
                return $result;
            }
            //查询相关订单是否已经关闭 关闭则不作任何处理 并且关闭该条记录
            $orderModel = new VslOrderModel();
            $orders = $orderModel->getInfo(['order_no'=>$info['order_no']],'order_status,out_trade_no');
            if($orders['order_status'] == 5){
                Db::commit();
                $result['code'] = -1;
                $result['message'] = '订单已关闭！分账无效';
                return $result;
            }
            //获取店铺对应的商户号
            $shopModel = new VslShopModel();
            $shop_info = $shopModel->getInfo(['shop_id'=>$info['shop_id'],'website_id'=>$this->website_id],'joinpay_machid');
            if(empty($shop_info['joinpay_machid'])){
                Db::commit();
                $result['code'] = -1;
                $result['message'] = '该店铺未设置收款商户号,请设置后重试!';
                return $result;
            }
            $joinpay_server = new Joinpay();
            //其他支付 直走转账流程 转账使用订单号而不是支付号
            if($info['joinpay'] != 1){
                $joinpay_server->transfer($info,$info['order_no'],$shop_info['joinpay_machid']);
                Db::commit();
                $result['code'] = 1;
                $result['message'] = '操作成功';
                return $result;
                exit;
            }
            //汇聚支付
            if($info['pay_money_all'] > $info['pay_money'] || $info['transfer'] > 0){
                //实际到账金额大于支付金额 差额部分需要转账
                
            }
            if($info['pay_money_all'] && $info['pay_money_all'] > 0){
                //发起分账 多商户 manyallocFunds=多次分账 单商户 allocFunds=延迟分账
                $joinpay_server->manyallocFunds($info,$orders['out_trade_no'],$shop_info['joinpay_machid']);
            }
            
            Db::commit();
            $result['code'] = 1;
            $result['message'] = '操作成功';
            return $result;
        } catch (\Throwable $th) {
            Db::rollback();
            $result['code'] = -1;
            $result['message'] = $th->getMessage();
            return $result;
        }
        

    }

    /**
     *店铺提现列表导出Excel
     */
    public function getShopAccountWithdrawListToExcel($page_index, $page_size = 0, $condition = '', $order = '')
    {
        $shop_account_withdraw = new VslShopWithdrawModel();
        $memberSer = new Member();

        $data = $shop_account_withdraw->getViewList($page_index, $page_size, $condition, $order);
        $list['data'] = [];
        if($data['page_count'] >= 1) {
            for ($i = 1; $i <= $data['page_count']; $i++) {
                $res['data'] = [];
                $res['data'] = $shop_account_withdraw->getViewList($i, $page_size, $condition, $order)['data'];
                if($res['data']) {
                    foreach ($res['data'] as $k => $v) {
                        if ($v['type'] == 1 || $v['type'] == 4) {
                            $res['data'][$k]['type'] = '银行卡';
                        } elseif ($v['type'] == 2) {
                            $res['data'][$k]['type'] = '微信';
                        } elseif ($v['type'] == 3) {
                            $res['data'][$k]['type'] = '支付宝';
                        }
                        $res['data'][$k]['cash'] = '¥' . $v['cash'];
                        $res['data'][$k]['ask_for_date'] = date('Y-m-d H:i:s', $v['ask_for_date']);
                        if($v['payment_date']>0){
                            $res['data'][$k]['payment_date'] = date('Y-m-d H:i:s', $v['payment_date']);
                        }else{
                            $res['data'][$k]['payment_date'] = '未到账';
                        }
                        $res['data'][$k]['status'] = $memberSer->getWithdrawStatusName($v['status']);
                    }
                    unset($v);
                    $list['data'] = array_merge($list['data'],$res['data']);
                }
            }
        }

        return $list;
    }

    /**
     * 店铺账户列表导出Excel
     */
    public function getShopAccountCountListToExcel($page_index, $page_size = 0, $condition = '', $order = '')
    {
        $shop_withdraw = new VslShopWithdrawModel();
        $shop_account = new VslShopAccountModel();
        $shop = new VslShopModel();
        $data = $shop_account->pageQuery($page_index, $page_size, $condition, $order, '*');
        $list['data'] = [];
        if($data['page_count'] >= 1) {
            for ($i = 1; $i <= $data['page_count']; $i++) {
                $res['data'] = [];
                $res['data'] = $shop_account->pageQuery($i, $page_size, $condition, $order, '*')['data'];
                if($res['data']) {
                    foreach ($res["data"] as $k => $v) {
                        $shop_info = $shop->getInfo([
                            "shop_id" => $v["shop_id"],
                        ], "shop_name");
                        $shop_withdraw_cash = $shop_withdraw->Query(["shop_id" => $v["shop_id"], 'status' => [['>', 0], ['<', 3]]], 'cash');
                        $shop_name = $shop_info["shop_name"];
                        //冻结余额=总额-已提现-提现中 #不再使用该规则
                        //edit for 2020/1-/27 调整 冻结中=订单正常的店铺到账金额 状态为未完成，且未关闭的订单店铺到账金额 历史久远的订单是没有shop_order_money 只有order_money
                        //营业总额 = 冻结中+可用+已提现+提现中
                        $shop_id = $v["shop_id"];
                        $sql = "select IFNULL(sum(order_money),0) as total from vsl_order where shop_id = $shop_id and pay_status=2 and order_status not in(5,4) and shop_order_money is null";//已支付金额1
                        $result1 = Db::query($sql);
                        $sql2 = "select IFNULL(sum(shop_order_money),0) as total from vsl_order where shop_id = $shop_id and pay_status=2 and order_status not in(5,4)";//已支付金额2
                        $result2 = Db::query($sql2);
                        $res["data"][$k]["freezing_money"] = $result1[0]['total'] + $result2[0]['total']; //冻结金额
                        $res["data"][$k]["shop_name"] = $shop_name;
                        $res["data"][$k]["shop_entry_money"] = $res["data"][$k]["freezing_money"] + $v['shop_total_money'] + $v["shop_withdraw"] + $v["shop_freezing_money"]; //营业总额
                        $res["data"][$k]["withdraw_ing"] = array_sum($shop_withdraw_cash);
                        $res["data"][$k]["shop_total_money"] = $v['shop_total_money'];
                    }
                    unset($v);
                    $list['data'] = array_merge($list['data'],$res['data']);
                }
            }
        }

        return $list;
    }
    
    /**
     * @param $shop_type  [店铺版本]
     * @param $add_type [1更新 2新增]
     * @return bool|void
     */
    public function addShopAndCreateCustomTemplate ($shop_id, $shop_type, $add_type)
    {
        if ($add_type != 2 || $shop_id == 0) {
            return true;
        }
        //查询新增的店铺权限
        //1、先查询移动、小程序、pc端module_id
        $add_config = new AddonsConfigService();
        $ports = '';
        //是否权限
        if (currentEnv() == 1) {
            $ports = '1';
            $mpRes = $add_config->getSysAddonsInfoByName('miniprogram','admin_module_id');
            if ($mpRes['admin_module_id']) {
                $mp_module_id = $mpRes['admin_module_id'];
            }
            $instanceType = new InstanceTypeModel();
            $shop_module_type = $instanceType->getInfo(['instance_typeid' => $shop_type],'type_module_array')['type_module_array'];
            if ($mp_module_id && strpos($shop_module_type,','.$mp_module_id.',')){
                $ports .= ',2';//2表示mp
            }
        } else if (currentEnv() == 2){
            $ports = '2';
        }
        
        //2、生成店铺端对应模板
        if (!$ports) {return;}
        $customS = new CustomtemplateSer();
        $res = $customS->iniAdminBaseCustomTemplate($this->website_id,$shop_id,$ports);
        if ($res['code'] < 0) {
            debugFile($res, '创建店铺时初始化模板报错',$customS->error_url);
        }
    }
}
