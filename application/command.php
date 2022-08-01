<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// |  （1）
// +----------------------------------------------------------------------

return [
    'app\cli\controller\SeckillOrderCreate',
    'app\cli\controller\PlatformGoodsToStoreGoods',
    'app\cli\controller\GoodsTaskCalculate_1',
    'app\cli\controller\GoodsTaskCalculate_2',
    'app\cli\controller\GoodsTaskCalculate_3',
    'app\cli\controller\AddGoodsChannelAuth',
    'app\cli\controller\RabbitmqActOrderCalculate',
    'app\cli\controller\RabbitmqActLiveData',
    'app\cli\controller\InvoiceTask',
    'app\cli\controller\ExcelsTasks', //商城Excel导出
    'app\cli\controller\DeliveryByExcelTask',
    'app\cli\controller\WxMsgTemplateSendTask',
    'app\cli\controller\ElectroncardDataByExcelTask',
    'app\cli\controller\AppointgivecouponTask',
    'app\cli\controller\StartActive',
    'app\cli\controller\CheckActive',
    'app\cli\controller\iniCustomTemplate',
    'app\cli\controller\OrderBarrageTask',
    'app\cli\controller\OpenLuckspell',
    'app\cli\controller\SendLuckspell',
];
