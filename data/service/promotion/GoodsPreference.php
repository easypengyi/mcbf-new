<?php

namespace data\service\promotion;

use data\service\BaseService;
use data\model\VslMemberModel;

/**
 * 商品优惠价格操作类(运费，商品优惠)(没有考虑订单优惠活动例如满减送)
 *
 */
class GoodsPreference extends BaseService
{
    function __construct()
    {
        parent::__construct();
    }

    /**
     * 查询会员等级折扣
     * @param unknown $uid
     */
    public function getMemberLevelDiscount($uid)
    {
        $member_model = new VslMemberModel();
        $member_level_info = $member_model::get($uid, ['level']);
        if (!empty($member_level_info->level->goods_discount)) {
            return $member_level_info->level->goods_discount / 10;
        } else {
            return 1;
        }
    }

    /*****************************************************************************************订单商品管理结束***************************************************/

}