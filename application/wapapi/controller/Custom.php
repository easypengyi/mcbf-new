<?php

namespace app\wapapi\controller;

use addons\bargain\service\Bargain;
use addons\presell\service\Presell;
use addons\seckill\server\Seckill;
use data\service\Customtemplate as CustomtemplateSer;

class Custom extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * 装修数据
     * @return \think\response\Json
     * @throws \Exception
     */
    public function index()
    {
        $type = request()->post('type');
        $id = request()->post('id');
        $shop_id = request()->post('shop_id', 0);

        $port = requestForm();
        if (empty($type) || ($type == 6 && empty($id))) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        
        $customAllSer = new CustomtemplateSer();
        if ($type != 6) {
            $custom_info = $customAllSer->getUsefulTemplateInfoByType($port,$type,true,$shop_id,$id);
            $data['template_data'] = json_decode(htmlspecialchars_decode($custom_info['template_data']));
            // 处理前端价格显示
            if ($data['template_data']->items) {
                foreach ($data['template_data']->items as $val) {
                    if ($val->id == 'goods' && $val->data) {
                        // 循环处理商品price
                        foreach ($val->data as $k => $v) {
                            if($this->is_seckill){
                                //判断是否是秒杀商品
                                $seckill_server = new Seckill();
                                //判断如果是秒杀商品，则取最低秒杀价
                                $goods_id = $v->goods_id;
                                $seckill_condition['nsg.goods_id'] = $goods_id;
                                $is_seckill = $seckill_server->isSkuStartSeckill($seckill_condition);
                                if($is_seckill){
                                    $v->price = $is_seckill['seckill_price'];
                                }
                            }
                            if($this->is_presell){
                                $presell_server = new Presell();
                                //判断如果是预售商品
                                $goods_id = $v->goods_id;
                                $is_presell = $presell_server->getPresellInfoByGoodsIdIng($goods_id);
                                if($is_presell){
                                    $v->price = $is_presell[0]['all_money'];
                                }
                            }
                            if($this->is_bargain){
                                $bargain_server = new Bargain();
                                //判断如果是预售商品
                                $goods_id = $v->goods_id;
                                $condition_bargain['website_id'] = $this->website_id;
                                $condition_bargain['goods_id'] = $goods_id;
                                $condition_bargain['end_bargain_time'] = ['>', time()];//未结束的
                                $condition_bargain['start_bargain_time'] = ['<', time()];//未结束的
                                $is_bargain = $bargain_server->isBargainByGoodsId($condition_bargain, 0);
                                if($is_bargain){
                                    $v->price = $is_bargain['start_money'];
                                }
                            }
                        }
                
                    }
                }
            }
            return json(['code' => 1, 'message' => '成功获取', 'data' => $data]);
        }
        //自定义模板
        if ($type == 6) {
            $custom_info = $customAllSer->getDiyTemplateInfoById($port, $shop_id, $id);
            $data['template_data'] = json_decode(htmlspecialchars_decode($custom_info['template_data']));
            // 处理前端价格显示
            if ($data['template_data']->items) {
                foreach ($data['template_data']->items as $val) {
                    if ($val->id == 'goods' && $val->data) {
                        // 循环处理商品price
                        foreach ($val->data as $k => $v) {
                            if($this->is_seckill){
                                //判断是否是秒杀商品
                                $seckill_server = new Seckill();
                                //判断如果是秒杀商品，则取最低秒杀价
                                $goods_id = $v->goods_id;
                                $seckill_condition['nsg.goods_id'] = $goods_id;
                                $is_seckill = $seckill_server->isSkuStartSeckill($seckill_condition);
                                if($is_seckill){
                                    $v->price = $is_seckill['seckill_price'];
                                }
                            }
                            if($this->is_presell){
                                $presell_server = new Presell();
                                //判断如果是预售商品
                                $goods_id = $v->goods_id;
                                $is_presell = $presell_server->getPresellInfoByGoodsIdIng($goods_id);
                                if($is_presell){
                                    $v->price = $is_presell[0]['all_money'];
                                }
                            }
                            if($this->is_bargain){
                                $bargain_server = new Bargain();
                                //判断如果是预售商品
                                $goods_id = $v->goods_id;
                                $condition_bargain['website_id'] = $this->website_id;
                                $condition_bargain['goods_id'] = $goods_id;
                                $condition_bargain['end_bargain_time'] = ['>', time()];//未结束的
                                $condition_bargain['start_bargain_time'] = ['<', time()];//未结束的
                                $is_bargain = $bargain_server->isBargainByGoodsId($condition_bargain, 0);
                                if($is_bargain){
                                    $v->price = $is_bargain['start_money'];
                                }
                            }
                        }
                
                    }
                }
            }
            return json(['code' => 1, 'message' => '成功获取', 'data' => $data]);
        }
    }
}
