<?php
namespace app\platform\controller;

/**
 * 菜单
 */
class Menu extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    public function addonmenu()
    {
        $this->getThreeLevelModule();//三级菜单
        $addons = request()->param('addons'); // 插件名称
        $params = request()->param(); // 插件参数
        $param= '';
        $modalArray = [
			'generalTaskDialogGoodsList',
            'modalGroupShoppingGoodsList',
            'seckillDelGoodsRecordDialog', 
            'seckillGoodsDetailDialog', 
            'seckillRequirementsDialog',
            'progress',
            'modalAntiGoodsList',
            'bargainDialogGoodsList',
            'prizeType',
            'prizeTypeFollow',
            'prizeTypePay',
            'prizeTypeScratch',
            'prizeTypeSmash',
            'prizeTypeWheel',
            'modalGiftGoodsList',
            'modalIntegralGoodsList',
            'modalIntegralGiftList',
            'modalIntegralCouponList',
            'modalMpliveGoodsList',
            'presellGoodsList',
            'selectTopicList',
            'selectGoods',
            'seckillGoodsDialog',
            'addCoupon',
            'appointAddGift',
            'credentialDialog',
            'orderDeliveryModal',
            'modalGiftList',
            'modalUserList',
            'addCouponMemberCard',
            'addMember',
            'mpTemplateDialog',
            'testerList',
            'submitModal',
            'posterDialog',
            'selectModal',
            'shopHeaderMode',
            'banner',
            'addModule',
            'singleBanner',
            'addSingleBanner',
            'hot',
            'homeFloor',
            'homeFloorResponse',
            'serviceMode',
            'serviceModeBack',
            'custom',
            'navMode',
            'navModeBack',
            'goodsInfo',
            'changedGoods',
            'homeHeaderModeBack',
            'homeHeaderMode',
            'helpMode',
            'helpModeBack',
            'linkMode',
            'linkModeBack',
            'copyMode',
            'copyModeBack',
            'rightMode',
            'rightModeBack',
            'homeBanner',
            'homeAdv',
            'homeShop',
            'homeAdvInsert',
            'homeBannerResponse',
            'pageEditModal',
            'createTemplateDialog',
            'appTemplateDialog',
            'checkSupplierStatus',
            'receiveGoodsCodeGoodsList',
            'merchantsAddMember',
            'modalLuckySpellGoodsList',
            'modalShopList',
            'modalLuckySpellGoodsList'
        ];
        if(is_array($params)){
            foreach($params as $key=>$val){
                $param.=$key.'='.$val.'&';
            }
            unset($val);
        }
        
        $this->assign('params', json_decode($param, true));
        $this->assign('hook_name', $addons);
        
        $no_menu = 0;
        if($addons == 'pcCustomTemplate'){
            $no_menu = 1;
        }
        $this->assign('no_menu', $no_menu);
        if(in_array($addons, $modalArray)){//弹窗页面不继承extend
            return view($this->style . 'Menu/addonmenumodal');
        }
        return view($this->style . 'Menu/addonmenu');
    }
}