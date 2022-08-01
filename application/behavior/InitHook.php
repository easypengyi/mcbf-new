<?php
namespace app\behavior;
// 注意应用或模块的不同命名空间
\think\Loader::addNamespace('data', 'data/');
use data\model\SysAddonsModel;
use data\model\SysHooksModel;
use think\cache;
use think\hook;

class InitHook
{

    public function run(&$param = [])
    {
        if (defined('BIND_MODULE') && BIND_MODULE === 'Install')
            return;
            // 动态加入命名空间
        \think\Loader::addNamespace('addons', 'addons');
        // 获取钩子数据
        $data = cache('hooks');
        if (! $data) {
            $addons_model = new SysAddonsModel();
            $hooks_model = new SysHooksModel();
            $hooks = $hooks_model->column('addons', 'name');
            // 获取钩子的实现插件信息
            foreach ($hooks as $key => $value) {
                if ($value) {
                    $map['status'] = 1;
                    $names = explode(',', $value);
                    $map['name'] = [
                        'IN',
                        $names
                    ];
                    $data = $addons_model->where($map)->column('name', 'id');
                    if ($data) {
                        $addons = array_intersect($names, $data);
                        Hook::add($key, array_map('get_addon_class', $addons));
                    }
                }
            }
            //增加应用内弹窗钩子
            hook::add('modalAntiGoodsList', array_map('get_addon_class', ['anticounterfeiting']));
            hook::add('bargainDialogGoodsList', array_map('get_addon_class', ['bargain']));
            hook::add('prizeType', array_map('get_addon_class', ['festivalcare']));
            hook::add('prizeTypeFollow', array_map('get_addon_class', ['followgift']));
            hook::add('prizeTypePay', array_map('get_addon_class', ['paygift']));
            hook::add('prizeTypeScratch', array_map('get_addon_class', ['scratchcard']));
            hook::add('prizeTypeSmash', array_map('get_addon_class', ['smashegg']));
            hook::add('prizeTypeWheel', array_map('get_addon_class', ['wheelsurf']));
            hook::add('modalGiftGoodsList', array_map('get_addon_class', ['gift']));
            hook::add('modalIntegralGoodsList', array_map('get_addon_class', ['integral']));
            hook::add('modalIntegralGiftList', array_map('get_addon_class', ['integral']));
            hook::add('modalIntegralCouponList', array_map('get_addon_class', ['integral']));
            hook::add('modalMpliveGoodsList', array_map('get_addon_class', ['mplive']));
            hook::add('presellGoodsList', array_map('get_addon_class', ['presell']));
            hook::add('selectTopicList', array_map('get_addon_class', ['thingcircle']));
            hook::add('selectGoods', array_map('get_addon_class', ['thingcircle']));
            // hook::add('seckillGoodsDialog', array_map('get_addon_class', ['seckill']));
            hook::add('seckillDelGoodsRecordDialog', array_map('get_addon_class', ['seckill']));
            hook::add('seckillGoodsDetailDialog', array_map('get_addon_class', ['seckill']));
            hook::add('seckillRequirementsDialog', array_map('get_addon_class', ['seckill']));
            hook::add('addCoupon', array_map('get_addon_class', ['appointgivecoupon']));
            hook::add('appointAddGift', array_map('get_addon_class', ['appointgivecoupon']));
            hook::add('credentialDialog', array_map('get_addon_class', ['credential']));
            hook::add('orderDeliveryModal', array_map('get_addon_class', ['delivery']));
            hook::add('modalGiftList', array_map('get_addon_class', ['giftvoucher']));
            hook::add('modalUserList', array_map('get_addon_class', ['liveshopping']));
            hook::add('addCouponMemberCard', array_map('get_addon_class', ['membercard']));
            hook::add('addMember', array_map('get_addon_class', ['merchants']));
            hook::add('mpTemplateDialog', array_map('get_addon_class', ['miniprogram']));
            hook::add('testerList', array_map('get_addon_class', ['miniprogram']));
            hook::add('submitModal', array_map('get_addon_class', ['miniprogram']));
            hook::add('posterDialog', array_map('get_addon_class', ['poster']));
            hook::add('selectModal', array_map('get_addon_class', ['voucherpackage']));
            hook::add('shopHeaderMode', array_map('get_addon_class', ['pcport']));
            hook::add('banner', array_map('get_addon_class', ['pcport']));
            hook::add('addModule', array_map('get_addon_class', ['pcport']));
            hook::add('singleBanner', array_map('get_addon_class', ['pcport']));
            hook::add('addSingleBanner', array_map('get_addon_class', ['pcport']));
            hook::add('hot', array_map('get_addon_class', ['pcport']));
            hook::add('homeFloor', array_map('get_addon_class', ['pcport']));
            hook::add('homeFloorResponse', array_map('get_addon_class', ['pcport']));
            hook::add('serviceMode', array_map('get_addon_class', ['pcport']));
            hook::add('serviceModeBack', array_map('get_addon_class', ['pcport']));
            hook::add('custom', array_map('get_addon_class', ['pcport']));
            hook::add('navMode', array_map('get_addon_class', ['pcport']));
            hook::add('navModeBack', array_map('get_addon_class', ['pcport']));
            hook::add('goodsInfo', array_map('get_addon_class', ['pcport']));
            hook::add('changedGoods', array_map('get_addon_class', ['pcport']));
            hook::add('homeHeaderModeBack', array_map('get_addon_class', ['pcport']));
            hook::add('homeHeaderMode', array_map('get_addon_class', ['pcport']));
            hook::add('helpMode', array_map('get_addon_class', ['pcport']));
            hook::add('helpModeBack', array_map('get_addon_class', ['pcport']));
            hook::add('linkMode', array_map('get_addon_class', ['pcport']));
            hook::add('linkModeBack', array_map('get_addon_class', ['pcport']));
            hook::add('copyMode', array_map('get_addon_class', ['pcport']));
            hook::add('copyModeBack', array_map('get_addon_class', ['pcport']));
            hook::add('rightMode', array_map('get_addon_class', ['pcport']));
            hook::add('rightModeBack', array_map('get_addon_class', ['pcport']));
            hook::add('homeBanner', array_map('get_addon_class', ['pcport']));
            hook::add('homeAdv', array_map('get_addon_class', ['pcport']));
            hook::add('homeShop', array_map('get_addon_class', ['pcport']));
            hook::add('homeAdvInsert', array_map('get_addon_class', ['pcport']));
            hook::add('homeBannerResponse', array_map('get_addon_class', ['pcport']));
            hook::add('pageEditModal', array_map('get_addon_class', ['pcport']));
            hook::add('createTemplateDialog', array_map('get_addon_class', ['pcport']));
            hook::add('appTemplateDialog', array_map('get_addon_class', ['appshop']));
            hook::add('checkSupplierStatus', array_map('get_addon_class', ['supplier']));
            hook::add('receiveGoodsCodeGoodsList', array_map('get_addon_class', ['receivegoodscode']));
            cache('hooks', Hook::get());
        } else {
            Hook::import($data, false);
        }
    }
}
