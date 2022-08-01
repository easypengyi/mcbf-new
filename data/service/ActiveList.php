<?php
namespace data\service;
use addons\presell\model\VslPresellModel;
use data\service\BaseService;
use data\model\VslActiveListModel;
use data\model\VslGoodsModel;
use addons\groupshopping\model\VslGroupShoppingModel;
use addons\discount\model\VslPromotionDiscountModel;
use addons\luckyspell\server\Luckyspell as luckySpellServer;
use addons\luckyspell\model\VslLuckySpellModel;
use think\Db;
class ActiveList extends BaseService
{
    /**
     * H获取指定时间段内的 活动分类或者商品信息
     * category_extend_id 分类列表 1,2,3
     * goods_id 商品表 1,2,3
     * all_category_extend_id 商品合并分类列表 1,2,3
     */
    public function checkActive($start_time='',$end_time=''){
        $result['category_extend_id'] = '';
        $result['goods_id'] = '';
        $result['all_category_extend_id'] = ''; 
        if($start_time === '' || $end_time === ''){
            return $result;
        }
        if($end_time > 0){
            $end_time  = getTimeTurnTimeStamp($end_time);
            $where['stime'] =['<=',$end_time];
        }
        if($start_time > 0){
            $start_time  = getTimeTurnTimeStamp($start_time);
            $where['etime'] =['>=',$start_time];
        }
        //获取限时抢购(多商品 有分类)、秒杀(单商品)、拼团(单商品)、预售(单商品)、砍价(单商品) 再整理
        $activeListModel = new VslActiveListModel();
        $where['website_id'] = $this->website_id;
        $where['status'] =['neq',2];
        $activeList = $activeListModel->Query($where,'*');
        
        if($activeList){
            $category_extend_id = '';
            $goods_id = '';
            foreach ($activeList as $k => $v) {
                if($v['category_extend_id'] > 0){
                    $category_extend_id .= $v['category_extend_id'].',';
                }
                if($v['goods_id']){
                    $goods_id .= $v['goods_id'].',';
                }
            }
            if($category_extend_id){
                $category_extend_id = rtrim($category_extend_id, ',');
                $result['category_extend_id'] = $category_extend_id;
                $result['all_category_extend_id'] = $category_extend_id;
            }
            
            if($goods_id){
                $goods_id = rtrim($goods_id, ',');
                $result['goods_id'] = $goods_id;
                $goodsSer = new Goods();
                $parm['goods_id'] = ['in',$goods_id];
                $parm['category_id'] = ['neq',0];
                $goods_category = $goodsSer->getGoodsListByCondition($parm, 'category_id');
                $goods_category = array_unique(array_column($goods_category, 'category_id'));
                $goods_category = implode(",", $goods_category);
                $category_extend_id .= ','.$goods_category;
                $result['all_category_extend_id'] = $category_extend_id;
            }
        }
        return $result;
    }
    /**
     * 添加活动列表 
     * data []列表数据
     */
    public function addActive($data){
        $activeListModel = new VslActiveListModel();
        try {
            $res = $activeListModel->save($data);
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    }
    /**
     * 检测一下是否重复添加  -- 同款商品 不同时间段内查询
     * start_time 开始时间
     * end_time   结束时间
     * goods_id   商品id
     * type       活动类型 1限时2秒杀3拼团4预售5砍价
     * act_id     原活动id
     */
    public function activeCanUse($start_time,$end_time,$goods_id,$type,$act_id){
        //商品id换取分类id
        $activeListModel = new VslActiveListModel();
        $where['website_id'] = $this->website_id;
        $where['stime'] =['<=',$end_time];
        $where['etime'] =['>=',$start_time];
        $where['status'] =['neq',2];
        $where['goods_id'] =$goods_id;
        if($act_id){
            $where['act_id'] =['neq',$act_id];
        }
        $activeList = $activeListModel->Query($where,'*');
        if($activeList){
            return false;
        }else{
            return true;
        }
    }  
    /**
     * 变更活动状态
     * act_id   活动id
     * type     活动类型 1限时2秒杀3拼团4预售5砍价
     * status   活动状态0未开始 1正常进行 2结束
     */ 
    public function changeActive($act_id,$type,$status,$website_id){
        $activeListModel = new VslActiveListModel();
        //查询状态十分为进行中，是则变更商品的活动状态信息
        $activeListModel->save(['status'=>$status],['act_id'=>$act_id,'type'=>$type,'website_id'=>$website_id]);
        if($status == 2){
            //关闭活动 去检测商品活动状态并且主动关闭
            $this->closeActive($act_id,$type,$website_id);
        }
    }
    /**
     * 关闭过期活动
     */
    public function closeActive($act_id,$type,$website_id){
        if(empty($act_id) || empty($type) || empty($website_id)){
            return;
        }
        $activeListModel = new VslActiveListModel();
        $active_info = $activeListModel->getInfo(['act_id'=>$act_id,'type'=>$type,'website_id'=>$website_id],'*');
        if(empty($active_info)){
            return;
        }
        if($type == 1){
            $promotion_type = 5;
            $promotion_discount = new VslPromotionDiscountModel();
            $promotion_discount->save(['status'=>4,'end_time'=>time()],['discount_id'=>$active_info['act_id']]);
        }else if($type == 2){
            $promotion_type = 1;
        }else if($type == 3){
            $promotion_type = 2;
            $group_obj = new VslGroupShoppingModel();
            $group_obj->save(['status'=>2],['group_id'=>$active_info['act_id']]);
        }else if($type == 4){
            $promotion_type = 3;
        }else if($type == 6){
            $luckyspell_obj = new VslLuckySpellModel();
            $luckyspell_obj->save(['status'=>2],['group_id'=>$active_info['act_id']]);
            $promotion_type = 6;
        }else{
            $promotion_type = 4;
        }
        $this->closeGoodsActive($promotion_type,$active_info);
    }
    public function closeGoodsActive($promotion_type,$active_info){
        if(empty($active_info['goods_id'])){
            return;
        }
        $goodsSer = new Goods();
        $goodsSer->updateGoods(['goods_id' => ['in',$active_info['goods_id']],'website_id' => $active_info['website_id'],'shop_id' => $active_info['shop_id']], ['promotion_type' => 0,'promote_id' => 0]);
        return;
    }
    /**
     * 更新活动状态
     */
    public function updateActive($start_time,$end_time,$goods_id,$type,$act_id){
        $activeListModel = new VslActiveListModel();
        $activeListModel->save(['stime'=>$start_time,'etime'=>$end_time],['goods_id'=>$goods_id,'act_id'=>$act_id,'type'=>$type]);
    }
    /**
     * 开始活动 更新商品活动信息
     * id 活动id
     */
    public function startActive($id = 0){
        if(empty($id)){
            return;
        }
        $activeListModel = new VslActiveListModel();
        $active_info = $activeListModel->getInfo(['aid'=>$id],'*');
        if(empty($active_info)){
            return;
        }
        // Db::startTrans();
        // 商品促销类型 0无促销，1秒杀，2团购, 3预售, 4砍价，5限时折扣
        //promote_id 
        try {
            // 活动列表 1限时2秒杀3拼团4预售5砍价
            $req = $activeListModel->save(['status'=>1,'stime'=>time()],['aid'=>$id]);
            if($active_info['type'] == 1){
                $promotion_discount = new VslPromotionDiscountModel();
                $res1 = $promotion_discount->save(['status'=>1],['discount_id'=>$active_info['act_id']]);
                if($active_info['goods_id']){
                    $this->updateGoodsActive(5,$active_info);
                }
            }else if($active_info['type'] == 2){
                //判断如果开始了，但是却未审核，则不更新promotion_type。
                $seck_goods = new VslSeckGoodsModel();
                $check_status = $seck_goods->getInfo(['seckill_id' => $id], 'check_status')['check_status'];
                if($check_status == 1){
                    $this->updateGoodsActive(1,$active_info);
                }
            }else if($active_info['type'] == 3){
                $group_obj = new VslGroupShoppingModel();
                $group_obj->save(['status'=>1],['group_id'=>$active_info['act_id']]);
                $this->updateGoodsActive(2,$active_info);
            }else if($active_info['type'] == 4){//预售
                //更新预售活动的状态为开始
                $presell_mdl = new VslPresellModel();
                $presell_mdl->save(['status' => 1],['id' => $active_info['act_id']]);
                $this->updateGoodsActive(3,$active_info);
            }else if($active_info['type'] == 6){ //幸运拼
                $this->updateGoodsActive(6,$active_info);
            }else{
                $this->updateGoodsActive(4,$active_info);
            }   
            return;
            // Db::commit();
        } catch (\Throwable $th) {
            debugFile($th->getMessage(), 'startActive', 111123);
            // Db::rollback();
        }
    }
    /**
     * 更新商品活动状态
     */
    public function updateGoodsActive($promotion_type,$active_info){
        if($promotion_type == 6){
            //开启幸运拼
            $luckySpellServer = new luckySpellServer();
            $luckySpellServer->groupShoppingOn($active_info['act_id'],$active_info['website_id'],$active_info['shop_id']);
        }
        $goodsSer = new Goods();
        $res = $goodsSer->updateGoods(['goods_id' => ['in',$active_info['goods_id']],'website_id' => $active_info['website_id'],'shop_id' => $active_info['shop_id']], ['promotion_type' => $promotion_type,'promote_id' => $active_info['act_id']]);
        return;
    }
    /**
     * 获取指定商品参加的活动列表
     */
    public function goodsActiveLists($goods_id,$start_time='',$end_time='',$type=0){
        $activeListModel = new VslActiveListModel();
        $goodsSer = new Goods();
        $goods_info = $goodsSer->getGoodsDetailById($goods_id);
        $website_id = $goods_info['website_id'];
        $time = time();
        $where = " status != 2 and website_id = {$website_id} and ";
        if($type == 0){
            if($end_time > 0){
                $end_time .= ' 23:59:59';
                //截止时间 变更为当前日期的 23:59:59
                $end_time  = getTimeTurnTimeStamp($end_time);
                $where .= " stime <= $end_time and ";
            }else{
                $where .= " etime > $time and ";
            }
            if($start_time > 0){
                $start_time .= ' 00:00:00';
                //开始时间 变更为当前日期的 00:00:00
                $start_time  = getTimeTurnTimeStamp($start_time);
                $where .= " etime > $start_time and ";
            }
        }else{
            if($end_time > 0){
                //截止时间 变更为当前日期的 23:59:59
                $end_time  = getTimeTurnTimeStamp($end_time);
                $where .= " stime <= $end_time and ";
            }else{
                $where .= " etime > $time and ";
            }
            if($start_time > 0){
                //开始时间 变更为当前日期的 00:00:00
                $start_time  = getTimeTurnTimeStamp($start_time);
                $where .= " etime > $start_time and ";
            }
        }
        
        $where .= " ( ";
        $where .= " FIND_IN_SET( {$goods_id},goods_id ) ";
        //分类忽略，单商品添加，允许 edit 2021/01/20 
        $category_id_1 = $goods_info['category_id_1'];
        $category_id_2 = $goods_info['category_id_2'];
        $category_id_3 = $goods_info['category_id_3'];
        if($category_id_1 > 0){
            $where .= " or FIND_IN_SET($category_id_1,category_extend_id) ";
        }
        if($category_id_2 > 0){
            $where .= " or FIND_IN_SET($category_id_2,category_extend_id) ";
        }
        if($category_id_3 > 0){
            $where .= " or FIND_IN_SET($category_id_3,category_extend_id) ";
        }
        $where .= " ) ";
        
        //获取分类列表 or 获取商品列表
        $sql = "select * from vsl_active_list where {$where}";
        $list = Db::query($sql);
        $activeList = array();
        if($list){
            foreach ($list as $k => $v) {
                $stime = date('Y.m.d',(int)$v['stime']);
                $etime = date('Y.m.d',(int)$v['etime']);
                $type_name = $this->getTypeName($v['type']);
                $data = array(
                    'active' => $v['type'] == 3 ? $stime.$type_name : $stime.'-'.$etime.$type_name,
                );
                array_push($activeList,$data);
            }
        }
        //已存在正在运行的活动 
        if($goods_info['promotion_type']){
            if($goods_info['promote_id'] > 0){
                //查询是否存在 不匹配的就是旧的已存在的活动
                $condition['type'] = $this->changeType($goods_info['promotion_type']);
                $condition['website_id'] = $website_id;
                $condition['act_id'] = $goods_info['promote_id'];
                $checks = $activeListModel->getInfo($condition,'aid');
                if(!$checks){
                    $type_name = $this->getTypeName($goods_info['promotion_type']);
                    $data = array(
                        'active' => $type_name,
                    );
                    array_push($activeList,$data);
                }
            }else{ //未存在该id的就是旧的已存在的活动
                $type_name = $this->getTypeName($goods_info['promotion_type']);
                $data = array(
                    'active' => $type_name,
                );
                array_push($activeList,$data);
            }
        }
        return $activeList;
    }
    /**
     * 修正商品活动类型为活动列表类型
     * 商品促销类型 1秒杀，2团购, 3预售, 4砍价，5限时折扣
     * 活动类型 1限时2秒杀3拼团4预售5砍价
     */
    public function changeType($type){
        switch ($type) {
            case 1:
                $str = 2;
                break;
            case 2:
                $str = 3;
                break;
            case 3:
                $str = 4;
                break;
            case 4:
                $str = 5;
                break;
            case 5:
                $str = 1;
                break;
            case 6:
                $str = 6;
                break;
            default:
                $str = 0;
                break;
        }
        return $str;
    }
    /**
     * 获取商品正在运行的活动名称
     * 商品促销类型 0无促销，1秒杀，2团购, 3预售, 4砍价，5限时折扣
     */
    public function getGoodsTypeName($type){
        switch ($type) {
            case 1:
                $str = '秒杀抢购';
                break;
            case 2:
                $str = '团购活动';
                break;
            case 3:
                $str = '预售活动';
                break;
            case 4:
                $str = '砍价活动';
                break;
            case 5:
                $str = '限时抢购';
                break;
            case 6:
                $str = '幸运拼团';
                break;
            default:
                $str = '未知活动';
                break;
        }
        return $str;
    }
    /**
     * 获取类型名称
     * 活动类型 1限时2秒杀3拼团4预售5砍价
     */
    public function getTypeName($type){
        switch ($type) {
            case 1:
                $str = '限时抢购';
                break;
            case 2:
                $str = '秒杀活动';
                break;
            case 3:
                $str = '拼团活动';
                break;
            case 4:
                $str = '预售活动';
                break;
            case 5:
                $str = '砍价活动';
                break;
            case 6:
                $str = '幸运拼团';
                break;
            default:
                $str = '未知活动';
                break;
        }
        return $str;
    }
    /**
     * 获取指定时间内 指定分类是否已经存在
     */
    public function checkActiveByCategory($category_extend_id,$start_time,$end_time){
        $website_id = $this->website_id;
        $time = time();
        $where = " status != 2 and website_id = $website_id and ";
        if($end_time > 0){
            $end_time  = getTimeTurnTimeStamp($end_time);
            $where .= " stime <= $end_time and ";
        }else{
            $where .= " etime > $time and ";
        }
        if($start_time > 0){
            $start_time  = getTimeTurnTimeStamp($start_time);
            $where .= " etime >= $start_time and ";
        }
        $where .= "  FIND_IN_SET($category_extend_id,category_extend_id) ";
        $sql = "select * from vsl_active_list where {$where}";
        $list = Db::query($sql);
        if($list){
            return -1;
        } 
        return 1;       
    }
    /**
     * 活动页主动删除活动
     * 活动类型 1限时2秒杀3拼团4预售5砍价
     */
    public function delActive($act_id,$type){
        $activeListModel = new VslActiveListModel();
        //查询状态十分为进行中，是则变更商品的活动状态信息
        $res = $activeListModel->destroy([
            'act_id' => $act_id,
            'website_id' => $this->website_id,
            'type' => $type
        ]);
        return $res;
    }
}