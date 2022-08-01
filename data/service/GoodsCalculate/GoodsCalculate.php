<?php

namespace data\service\GoodsCalculate;

/**
 * 商品购销存
 */

use addons\bargain\model\VslBargainModel;
use addons\channel\model\VslChannelGoodsModel;
use addons\channel\model\VslChannelGoodsSkuModel;
use addons\integral\model\VslIntegralGoodsModel;
use addons\seckill\model\VslSeckGoodsModel;
use addons\presell\model\VslPresellModel;
use data\model\VslGoodsModel;
use data\model\VslStoreGoodsModel;
use data\model\VslStoreGoodsSkuModel;
use data\service\BaseService as BaseService;
use data\model\VslGoodsSkuModel;
use data\model\VslOrderModel;
use data\model\VslOrderGoodsModel;
use addons\agent\model\VslAgentGoodsModel;
use addons\agent\model\VslAgentGoodsSkuModel;
use data\service\Goods;

class GoodsCalculate extends BaseService
{

    /**
     * 减少商品库存(购买使用)
     * @param unknown $sku_id //商品属性
     * @param unknown $num //商品数量
     * @param unknown $cost_price //减少成本价  通过加权统计
     */
    public function subGoodsStock($goods_id, $sku_id, $num)
    {
        $goodsSer = new Goods();
        $stock = $goodsSer->getGoodsDetailById($goods_id);
        if ($stock['stock'] < $num) {
            return LOW_STOCKS;
        }
        $goods_sku_model = new VslGoodsSkuModel();
        $sku_stock = $goods_sku_model->getInfo(['sku_id' => $sku_id], 'stock');
        if ($sku_stock['stock'] < $num) {
            return LOW_STOCKS;
        }
        $goodsSer->updateGoods(['goods_id' => $goods_id], ['stock' => $stock['stock'] - $num], $goods_id);
        $retval = $goods_sku_model->save(['stock' => $sku_stock['stock'] - $num], ['sku_id' => $sku_id]);
        return $retval;
    }
    /**
     * 减少商品库存(购买使用)
     * @param unknown $sku_id //商品属性
     * @param unknown $num //商品数量
     * @param unknown $cost_price //减少成本价  通过加权统计
     */
    public function subRedisGoodsStock($goods_id, $sku_id)
    {
        $redis = connectRedis();
        $goods_key = 'goods_'.$goods_id.'_'.$sku_id;
        $sku_stock = $redis->get($goods_key);
        $data['stock'] = $sku_stock;
        $goods_sku_model = new VslGoodsSkuModel();
        $goods_sku_model->save($data, ['sku_id' => $sku_id]);
        $goods_stock = $goods_sku_model->getSum(['goods_id' => $goods_id], 'stock');
        $data2['stock'] = $goods_stock;
        $goodsSer = new Goods();
        $retval = $goodsSer->updateGoods(['goods_id' => $goods_id], $data2, $goods_id);
        return $retval;
    }

    /**
     * 减少商品库存(购买使用)(门店下单)
     * @param unknown $sku_id //商品属性
     * @param unknown $num //商品数量
     * @param unknown $cost_price //减少成本价  通过加权统计
     */
    public function storeSubGoodsStock($goods_id, $sku_id, $num, $store_id)
    {
        if($store_id){
            $store_goods_model = new VslStoreGoodsModel();
            $stock = $store_goods_model->getInfo(['goods_id' => $goods_id, 'store_id' => $store_id], 'stock');
            if ($stock['stock'] < $num) {
                return LOW_STOCKS;
                exit();
            }
            $store_goods_sku_model = new VslStoreGoodsSkuModel();
            $sku_stock = $store_goods_sku_model->getInfo(['sku_id' => $sku_id, 'store_id' => $store_id], 'stock');
            if ($sku_stock['stock'] < $num) {
                return LOW_STOCKS;
                exit();
            }
            $store_goods_model->save(['stock' => $stock['stock'] - $num], ['goods_id' => $goods_id, 'store_id' => $store_id]);
            $retval = $store_goods_sku_model->save(['stock' => $sku_stock['stock'] - $num], ['sku_id' => $sku_id, 'store_id' => $store_id]);
            return $retval;
        }else{
            $goodsSer = new Goods();
            $stock = $goodsSer->getGoodsDetailById($goods_id);
            if ($stock['stock'] < $num) {
                return LOW_STOCKS;
            }
            $goods_sku_model = new VslGoodsSkuModel();
            $sku_stock = $goods_sku_model->getInfo(['sku_id' => $sku_id], 'stock');
            if ($sku_stock['stock'] < $num) {
                return LOW_STOCKS;
            }
            $goodsSer->updateGoods(['goods_id' => $goods_id], ['stock' => $stock['stock'] - $num], $goods_id);
            $retval = $goods_sku_model->save(['stock' => $sku_stock['stock'] - $num], ['sku_id' => $sku_id]);
            return $retval;
        }
    }
    /**
     * 减少redis商品库存(购买使用)(门店下单)
     * @param unknown $sku_id //商品属性
     * @param unknown $num //商品数量
     * @param unknown $cost_price //减少成本价  通过加权统计
     */
    public function storeRedisSubGoodsStock($goods_id, $sku_id, $store_id)
    {
        if($store_id){
            $redis = connectRedis();
            $store_goods_model = new VslStoreGoodsModel();
            $store_redis_key = 'store_goods_'.$goods_id.'_'.$sku_id;
            $store_stock = $redis->get($store_redis_key);
            $store_goods_sku_model = new VslStoreGoodsSkuModel();
            $data['stock'] = $store_stock;
            $store_goods_sku_model->save($data, ['sku_id' => $sku_id, 'store_id' => $store_id]);
            $goods_stock = $store_goods_sku_model->getSum(['goods_id' => $goods_id, 'store_id' => $store_id], 'stock');
            $data2['stock'] = $goods_stock;
            $retval = $store_goods_model->save($data2, ['goods_id' => $goods_id, 'store_id' => $store_id]);
            return $retval;
        }
    }

    /**
     * 减少渠道商商品库存(购买使用)
     * @param unknown $sku_id //商品属性
     * @param unknown $num //商品数量
     * @param unknown $cost_price //减少成本价  通过加权统计
     */
    public function subChannelGoodsStock($goods_id, $sku_id, $num, $channel_id)
    {
        $goods_model = new VslChannelGoodsModel();
        $stock = $goods_model->getInfo(['goods_id' => $goods_id, 'channel_id' => $channel_id], 'stock');
        if ($stock['stock'] < $num) {
            return LOW_STOCKS;
            exit();
        }
        $goods_sku_model = new VslChannelGoodsSkuModel();
        $sku_stock = $goods_sku_model->getInfo(['sku_id' => $sku_id, 'channel_id' => $channel_id], 'stock');
        if ($sku_stock['stock'] < $num) {
            return LOW_STOCKS;
            exit();
        }
        $goods_model->save(['stock' => $stock['stock'] - $num], ['goods_id' => $goods_id, 'channel_id' => $channel_id]);
        $retval = $goods_sku_model->save(['stock' => $sku_stock['stock'] - $num], ['sku_id' => $sku_id, 'channel_id' => $channel_id]);
        return $retval;
    }
    /**
     * 减少redis渠道商商品库存(购买使用)
     * @param unknown $sku_id //商品属性
     * @param unknown $num //商品数量
     * @param unknown $cost_price //减少成本价  通过加权统计
     */
    public function subRedisChannelGoodsStock($goods_id, $sku_id, $channel_id)
    {
        $redis = connectRedis();
        //渠道商库存
        $only_channel_key = 'only_channel_'.$channel_id.'_'.$sku_id;//用于当零售的商品库存同时有渠道商的和上级的时候，购买商品库存更新区分
        $goods_model = new VslChannelGoodsModel();
        $channel_stock = $redis->get($only_channel_key);
        $data['stock'] = $channel_stock;
        $goods_sku_model = new VslChannelGoodsSkuModel();
        $goods_sku_model->save($data, ['sku_id' => $sku_id, 'channel_id' => $channel_id]);
        $goods_stock = $goods_sku_model->getSum(['goods_id' => $goods_id, 'channel_id' => $channel_id], 'stock');
        $data2['stock'] = $goods_stock;
        $retval = $goods_model->save($data2, ['goods_id' => $goods_id, 'channel_id' => $channel_id]);
        //平台库存
        $only_platform_key = 'only_platform_'.$channel_id.'_'.$sku_id;//用于当零售的商品库存同时有渠道商的和上级的时候，购买商品库存更新区分
        $data3['stock'] = $redis->get($only_platform_key);
        $pgoods_model = new VslGoodsModel();
        $pgoods_sku_model = new VslGoodsSkuModel();
        $pgoods_sku_model->save($data3, ['sku_id' => $sku_id]);
        $pgoods_stock = $pgoods_sku_model->getSum(['goods_id' => $goods_id], 'stock');
        $data4['stock'] = $pgoods_stock;
        $pgoods_model->save($data4, ['goods_id' => $goods_id]);
        return $retval;
    }
    /**
     * 减少redis渠道商商品库存(购买使用)
     * @param unknown $sku_id //商品属性
     * @param unknown $num //商品数量
     * @param unknown $cost_price //减少成本价  通过加权统计
     */
    public function subRedisAgentGoodsStock($goods_id, $sku_id, $channel_id)
    {
        $redis = connectRedis();
        $channel_key = 'channel_'.$channel_id.'_'.$sku_id;
        $goods_model = new VslAgentGoodsModel();
        $channel_stock = $redis->get($channel_key);
        $data['stock'] = $channel_stock;
        $goods_sku_model = new VslAgentGoodsSkuModel();
        $goods_sku_model->save($data, ['sku_id' => $sku_id, 'channel_id' => $channel_id]);
        $goods_stock = $goods_sku_model->getSum(['goods_id' => $goods_id, 'channel_id' => $channel_id], 'stock');
        $data2['stock'] = $goods_stock;
        $retval = $goods_model->save($data2, ['goods_id' => $goods_id, 'channel_id' => $channel_id]);
        return $retval;
    }
    /**
     * 增加渠道商商品库存(购买使用)
     * @param unknown $sku_id //商品属性
     * @param unknown $num //商品数量
     * @param unknown $cost_price //减少成本价  通过加权统计
     */
    public function addChannelGoodsStock($goods_id, $sku_id, $num, $channel_id)
    {

        $goods_model = new VslChannelGoodsModel();
        $stock = $goods_model->getInfo(['goods_id' => $goods_id, 'channel_id' => $channel_id], 'stock');
        $goods_sku_model = new VslChannelGoodsSkuModel();
        $sku_stock = $goods_sku_model->getInfo(['sku_id' => $sku_id, 'channel_id' => $channel_id], 'stock');
        $goods_model->save(['stock' => $stock['stock'] + $num], ['goods_id' => $goods_id, 'channel_id' => $channel_id]);
        $retval = $goods_sku_model->save(['stock' => $sku_stock['stock'] + $num], ['sku_id' => $sku_id, 'channel_id' => $channel_id]);
        return $retval;
    }

    /**
     * 添加商品销售(销售商品使用)
     * @param unknown $goods_id
     * @param unknown $num
     */
    public function addGoodsSales($goods_id, $num)
    {
        $goodsSer = new Goods();
        $goods_sales = $goodsSer->getGoodsDetailById($goods_id);
        $retval = $goodsSer->updateGoods(['goods_id' => $goods_id], ['sales' => $goods_sales['sales'] + $num], $goods_id);
        return $retval;
    }

    /**
     * 添加商品销售(销售商品使用)(门店下单)
     * @param unknown $goods_id
     * @param unknown $num
     */
    public function storeAddGoodsSales($goods_id, $num, $store_id)
    {
        //门店商品表
        $store_goods_model = new VslStoreGoodsModel();
        $store_goods_sales = $store_goods_model->getInfo(['goods_id' => $goods_id, 'store_id' => $store_id], 'sales');
        $retval1 = $store_goods_model->save(['sales' => $store_goods_sales['sales'] + $num], ['goods_id' => $goods_id, 'store_id' => $store_id]);
        if ($retval1) {
            return 1;
        }
    }

    /**
     * 添加积分商品销售(销售商品使用)
     * @param unknown $goods_id
     * @param unknown $num
     */
    public function addIntegralGoodsSales($goods_id, $num)
    {
        $goods_model = new VslIntegralGoodsModel();
        $goods_sales = $goods_model->getInfo(['goods_id' => $goods_id], 'sales, real_sales');
        $retval = $goods_model->save(['sales' => $goods_sales['sales'] + $num, 'real_sales' => $goods_sales['real_sales'] + $num], ['goods_id' => $goods_id]);
        return $retval;
    }
    /**
     * 添加门店商品销售(销售商品使用)
     * @param unknown $goods_id
     * @param unknown $num
     */
    public function addStoreGoodsSales($goods_id, $num)
    {
        $goods_model = new VslStoreGoodsModel();
        $goods_sales = $goods_model->getInfo(['goods_id' => $goods_id], 'sales');
        $retval = $goods_model->save(['sales' => $goods_sales['sales'] + $num], ['goods_id' => $goods_id]);
        return $retval;
    }

    /*
     * 添加秒杀销量
     * **/
    public function addSeckillSkuSales($seckill_id, $sku_id, $num)
    {
        $seckill_goods_mdl = new VslSeckGoodsModel();
        $condition['seckill_id'] = $seckill_id;
        $condition['sku_id'] = $sku_id;
        $seckill_sales_list = $seckill_goods_mdl->field('seckill_sales')->where($condition)->find();
        if ($seckill_sales_list) {
            $seckill_sales_list->seckill_sales = $seckill_sales_list->seckill_sales + $num;
            $seckill_sales_list->save();
        }
    }

    /*
     * 添加预售销量
     * **/
    public function addPresellSkuSales($presell_id, $goods_id, $num)
    {
        $presell_mdl = new VslPresellModel();
        $condition['id'] = $presell_id;
        $condition['goods_id'] = $goods_id;
        $presell_sales_list = $presell_mdl->field('presell_sales')->where($condition)->find();
        if ($presell_sales_list) {
            $presell_sales_list->presell_sales = $presell_sales_list->presell_sales + $num;
            $presell_sales_list->save();
        }
    }

    /*
     * 减掉预售销量
     * **/
    public function subPresellSkuSales($presell_id, $goods_id, $num)
    {
        $presell_mdl = new VslPresellModel();
        $condition['id'] = $presell_id;
        $condition['goods_id'] = $goods_id;
        $presell_sales_list = $presell_mdl->field('presell_sales')->where($condition)->find();
        if ($presell_sales_list) {
            $presell_sales_list->presell_sales = $presell_sales_list->presell_sales - $num;
            $presell_sales_list->save();
        }
    }

    /*
     * 添加砍价销量
     * **/
    public function addBargainSkuSales($bargain_id, $goods_id, $num)
    {
        $bargain_mdl = new VslBargainModel();
        $condition['bargain_id'] = $bargain_id;
        $condition['goods_id'] = $goods_id;
        $bargain_sales_list = $bargain_mdl->field('bargain_sales')->where($condition)->find();
        if ($bargain_sales_list) {
            $bargain_sales_list->bargain_sales = $bargain_sales_list->bargain_sales + $num;
            $bargain_sales_list->save();
        }
    }

    /**
     * 添加渠道商商品销售(销售商品使用)
     * @param unknown $goods_id
     * @param unknown $num
     */
    public function addChannelGoodsSales($goods_id, $num, $channel_id)
    {
        $goods_model = new VslChannelGoodsModel();
        $goods_sales = $goods_model->getInfo(['goods_id' => $goods_id, 'channel_id' => $channel_id], 'sales, real_sales');
        $retval = $goods_model->save(['sales' => $goods_sales['sales'] + $num, 'real_sales' => $goods_sales['real_sales'] + $num], ['goods_id' => $goods_id, 'channel_id' => $channel_id]);
        return $retval;
    }

    /**
     * 添加渠道商sku销售(销售商品使用)
     * @param unknown $goods_id
     * @param unknown $num
     */
    public function addChannelSkuSales($sku_id, $num, $channel_id)
    {
        $channel_sku_model = new VslChannelGoodsSkuModel();
        $sku_sales = $channel_sku_model->getInfo(['sku_id' => $sku_id, 'channel_id' => $channel_id], 'sku_sales');
        $retval = $channel_sku_model->save(['sku_sales' => $sku_sales['sku_sales'] + $num], ['sku_id' => $sku_id, 'channel_id' => $channel_id]);
        return $retval;
    }

    /**
     * 减渠道商销售(销售商品使用)
     * @param unknown $goods_id
     * @param unknown $num
     */
    public function subChannelSales($goods_id, $sku_id, $num, $channel_id)
    {
        $goods_model = new VslChannelGoodsModel();
        $goods_sales = $goods_model->getInfo(['goods_id' => $goods_id, 'channel_id' => $channel_id], 'sales, real_sales');
        $goods_model->save(['sales' => $goods_sales['sales'] - $num, 'real_sales' => $goods_sales['real_sales'] - $num], ['goods_id' => $goods_id, 'channel_id' => $channel_id]);
        $channel_sku_model = new VslChannelGoodsSkuModel();
        $sku_sales = $channel_sku_model->getInfo(['sku_id' => $sku_id, 'channel_id' => $channel_id], 'sku_sales');
        $retval = $channel_sku_model->save(['sku_sales' => $sku_sales['sku_sales'] - $num], ['sku_id' => $sku_id, 'channel_id' => $channel_id]);
        return $retval;
    }

    /**
     * 减少商品销售（订单关闭，冲账）
     * @param unknown $goods_id
     * @param unknown $num
     */
    public function subGoodsSales($goods_id, $num)
    {
        $goodsSer = new Goods();
        $goods_sales = $goodsSer->getGoodsDetailById($goods_id);
        $retval = $goodsSer->updateGoods(['goods_id' => $goods_id], ['sales' => $goods_sales['sales'] - $num], $goods_id);
        return $retval;
    }

    /**
     * 减少秒杀销售（订单关闭，冲账）
     * @param unknown $goods_id
     * @param unknown $num
     */
    public function subSeckillGoodsSales($seckill_id, $sku_id, $num)
    {
        $seckill_goods_model = new VslSeckGoodsModel();
        $seckill_sales = $seckill_goods_model->getInfo(['sku_id' => $sku_id, 'seckill_id' => $seckill_id], 'seckill_sales');
        $retval = $seckill_goods_model->save(['seckill_sales' => $seckill_sales['seckill_sales'] - $num], ['sku_id' => $sku_id, 'seckill_id' => $seckill_id]);
        return $retval;
    }

    /**
     * 减少砍价销售（订单关闭，冲账）
     * @param unknown $goods_id
     * @param unknown $num
     */
    public function subBargainGoodsSales($bargain_id, $goods_id, $num)
    {
        $bargain_model = new VslBargainModel();
        $bargain_sales = $bargain_model->getInfo(['goods_id' => $goods_id, 'bargain_id' => $bargain_id], 'bargain_sales');
        $retval = $bargain_model->save(['bargain_sales' => $bargain_sales['bargain_sales'] - $num], ['goods_id' => $goods_id, 'bargain_id' => $bargain_id]);
        return $retval;
    }

    /**
     * 一段时间内的订单项
     * @param unknown $order_condition
     * @return multitype:NULL
     */
    public function getOrderGoodsSelect($order_condition)
    {
        $order_model = new VslOrderModel();
        $order_array = $order_model->where($order_condition)->select();
        $order_goods_list = array();
        foreach ($order_array as $t => $b) {
            $order_item = new VslOrderGoodsModel();
            $item_array = $order_item->where(['order_id' => $b['order_id']])->select();
            $order_goods_list = array_merge($order_goods_list, $item_array);
        }
        unset($b);
        return $order_goods_list;
    }
    /**
     * 减少供应商商品库存(购买使用)
     */
    public function subSupplierGoodsStock($goods_id, $sku_id, $num)
    {
        $goodsSer = new Goods();
        $supplier_goods_id = $goodsSer->getGoodsDetailById($goods_id, 'goods_id,goods_name,sales,real_sales,supplier_goods_id')['supplier_goods_id'];
        if(empty($supplier_goods_id)) {
            return -1;
        }
        $stock = $goodsSer->getGoodsDetailById($supplier_goods_id);
        if ($stock['stock'] < $num) {
            return LOW_STOCKS;
        }
        $goods_sku_model = new VslGoodsSkuModel();
        $supplier_goods_sku_id = $goods_sku_model->Query(['sku_id' => $sku_id], 'supplier_sku_id')[0];
        if(empty($supplier_goods_sku_id)) {
            return -1;
        }
        $sku_stock = $goods_sku_model->getInfo(['sku_id' => $supplier_goods_sku_id], 'stock');
        if ($sku_stock['stock'] < $num) {
            return LOW_STOCKS;
        }
        $goodsSer->updateGoods(['goods_id' => $supplier_goods_id], ['stock' => $stock['stock'] - $num], $supplier_goods_id);
        $goods_sku_model->isUpdate(true)->save(['stock' => $sku_stock['stock'] - $num], ['sku_id' => $supplier_goods_sku_id]);
        //同步库存到上架了该商品的店铺
        $retval = $goodsSer->syncSupplierGoodsStock($supplier_goods_id,$supplier_goods_sku_id);
        return $retval;
    }

    /**
     * 增加供应商商品库存(购买使用)
     */
    public function addSupplierGoodsStock($goods_id, $sku_id, $num)
    {
        $goodsSer = new Goods();
        $supplier_goods_id = $goodsSer->getGoodsDetailById($goods_id, 'goods_id,goods_name,sales,real_sales,supplier_goods_id')['supplier_goods_id'];
        if(empty($supplier_goods_id)) {
            return -1;
        }
        $stock = $goodsSer->getGoodsDetailById($supplier_goods_id);

        $goods_sku_model = new VslGoodsSkuModel();
        $supplier_goods_sku_id = $goods_sku_model->Query(['sku_id' => $sku_id], 'supplier_sku_id')[0];
        if(empty($supplier_goods_sku_id)) {
            return -1;
        }
        $sku_stock = $goods_sku_model->getInfo(['sku_id' => $supplier_goods_sku_id], 'stock');
        $goodsSer->updateGoods(['goods_id' => $supplier_goods_id], ['stock' => $stock['stock'] + $num], $supplier_goods_id);
        $goods_sku_model->isUpdate(true)->save(['stock' => $sku_stock['stock'] + $num], ['sku_id' => $supplier_goods_sku_id]);
        //同步库存到上架了该商品的店铺
        $retval = $goodsSer->syncSupplierGoodsStock($supplier_goods_id,$supplier_goods_sku_id);
        return $retval;
    }
}
