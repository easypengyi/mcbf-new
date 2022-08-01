<?php
/**
 * ActiveListModel.php
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

namespace data\model;
use data\model\BaseModel as BaseModel;
use addons\presell\model\VslPresellModel;
use addons\seckill\server\Seckill as SeckillServer;
use addons\groupshopping\model\VslGroupShoppingModel;
use addons\bargain\model\VslBargainModel;
/**
 * 活动列表
 */
class VslActiveListModel extends BaseModel {

    protected $table = 'vsl_active_list';
    protected $rule = [
        'aid'  =>  '',
    ];
    protected $msg = [
        'aid'  =>  '',
    ];
    public function checkGoodsActive($condition=''){

        $viewObj = $this->alias('act')
        ->field('act.a_id');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }
    /**
     * 按商品分组查询排序
     */
    public function countGoodsActive($condition=''){
        $query_count = $this->alias('act')
        ->field('COUNT(*)')
        ->where($condition)
        ->group('act.goods_id')
        ->select();
        $query_count = count($query_count);
        return $query_count;
    }
    /**
     * 按商品分组查询，按时间排序查找各一条数据
     */
    public function checkGoodsOLdActive($check_good_id, $start_time='', $end_time=''){
        //获取商品信息
        $count = 1;
        $goodsSer = new \data\service\Goods();
        $goods_info = $goodsSer->getGoodsDetailById($check_good_id, 'goods_id,shop_id,goods_name,promotion_type');
        
        if($goods_info['promotion_type'] > 0){
            //当前存在活动 检验该商品活动时间是否与参与时间冲突
            if($goods_info['promotion_type'] == 1){ // 秒杀
                //查询时间段内是否存在该商品的秒杀活动
                $seckill_server = new SeckillServer();
                $condition_is_seckill['nsg.goods_id'] = $check_good_id;
                $is_seckill = $seckill_server->isSkuStartSeckill($condition_is_seckill); 
                $seckill_now_time = $is_seckill['seckill_now_time'];
                if($start_time <= $seckill_now_time && $seckill_now_time <= $end_time){
                    $count = 1;
                }
                if($start_time <= $seckill_now_time+24*3600 && $seckill_now_time+24*3600 <= $end_time){
                    $count = 1;
                }
            }else if($goods_info['promotion_type'] == 2){ //团购
                //查询时间段内是否存在该商品的团购活动
                $vslGroupGoods = new VslGroupShoppingModel();
                $group_info = $vslGroupGoods->getInfo(['goods_id' => $check_good_id,'status' => 1], 'group_id');
                //团购商品 由于团购没有起始时间 存在则不允许参加其他活动
                if($group_info){
                    $count = 1;
                }
            }else if($goods_info['promotion_type'] == 3){ //预售
                //查询时间段内是否存在该商品的团购活动
                $vslPresell = new VslPresellModel();
                $presell_info = $vslPresell->getInfo(['goods_id' => $check_good_id,'active_status' => 1,'status' => 1], 'start_time,end_time');
                if($start_time <= $presell_info['start_time'] && $presell_info['start_time'] <= $end_time){
                    $count = 1;
                }
                if($start_time <= $presell_info['end_time'] && $presell_info['end_time'] <= $end_time){
                    $count = 1;
                }
            }else if($goods_info['promotion_type'] == 4){ //砍价
                //查询时间段内是否存在该商品的砍价活动
                $vslBargain = new VslBargainModel();
                $bargain_info = $vslBargain->getInfo(['goods_id' => $check_good_id,'active_status' => 1,'status' => 1], 'start_bargain_time,end_bargain_time');
                if($start_time <= $bargain_info['start_bargain_time'] && $bargain_info['start_bargain_time'] <= $end_time){
                    $count = 1;
                }
                if($start_time <= $bargain_info['end_bargain_time'] && $bargain_info['end_bargain_time'] <= $end_time){
                    $count = 1;
                }
            }else if($goods_info['promotion_type'] == 5){ //限时折扣
                //商品已标记活动 不查询活动列表 直接查询商品活动信息
                $condition['act.goods_id'] = $check_good_id;
                $condition['act.status'] = ['>', 0];
                $condition['act.stime'] = ['<=', $start_time];
                $condition['act.etime'] = ['>=', $start_time];
                $check = $this->checkGoodsActive($condition);
                if($check>0){
                    $count = 11;
                }
                $condition2['act.goods_id'] = $check_good_id;
                $condition2['act.status'] = ['>', 0];
                $condition2['act.stime'] = ['<=', $end_time];
                $condition2['act.etime'] = ['>=', $end_time];
                $check2 = $this->checkGoodsActive($condition2);
                if($check2>0){
                    $count = 12;
                }
            }
        }else{
            $count = 0;
        }
        return $count;
    }   
}