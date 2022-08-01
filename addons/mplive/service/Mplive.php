<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/25 0025
 * Time: 11:30
 */

namespace addons\mplive\service;
use addons\mplive\model\MpliveGoodsLibraryModel;
use addons\mplive\model\MpliveGoodsModel;
use addons\mplive\model\MpliveGoodsRequestModel;
use addons\mplive\model\MpliveModel;
use addons\mplive\model\MpliveRequestModel;
use data\extend\WchatOauth;
use data\service\AddonsConfig as AddonsConfigService;
use data\service\BaseService;
use think\Db;

class Mplive extends BaseService
{
    public function __construct()
    {
        parent::__construct();
    }

    public function saveMpliveSetting($data)
    {
        if($data['living']){
            $value_arr['living'] = $data['living'];
        }
        if($data['unstart']){
            $value_arr['unstart'] = $data['unstart'];
        }
        if($data['stop']){
            $value_arr['stop'] = $data['stop'];
        }
        if($data['ended']){
            $value_arr['ended'] = $data['ended'];
        }
        if($data['error']){
            $value_arr['error'] = $data['error'];
        }
        if($data['past']){
            $value_arr['past'] = $data['past'];
        }
        $value_arr['is_auto_update'] = $data['is_auto_update'];
        $value_arr['mplive_replay_day'] = $data['mplive_replay_day'];
        $up_data['value'] = json_encode($value_arr);
        $up_data['desc'] = '小程序直播设置';
        $up_data['is_use'] = $data['is_mplive_use'];
//        $up_data['create_time'] = time();
        $up_data['addons'] = 'mplive';
//        $up_data['website_id'] = $this->website_id;
        //by sgw
        $ConfigService = new AddonsConfigService();
        $is_conf = $ConfigService->getAddonsConfig('mplive', $this->website_id);
        if($is_conf){
            $res = $ConfigService->updateAddonsConfig($up_data['value'], $up_data['desc'], $up_data['is_use'], $up_data['addons']);
        }else{
            $res = $ConfigService->addAddonsConfig($up_data['value'], $up_data['desc'], $up_data['is_use'], $up_data['addons']);
        }
        $redis = connectRedis();
        $website_id = $this->website_id;
        $shop_id = $this->instance_id;
        $addons = 'mplive';
        $redis->set($website_id.'_'.$shop_id.'_'.$addons.'_addons_config',json_encode($up_data,true));
        return $res;
    }
    /*
     * 插入请求数
     * **/
    public function saveRequestNum()
    {
        $mplive_request = new MpliveRequestModel();
        //将请求数加1
        $day_time = strtotime(date('Y-m-d 00:00:00'));
        $request_cond['day_time'] = $day_time;
        $request_cond['website_id'] = $this->website_id;
        $request_obj = $mplive_request->where($request_cond)->find();
        //计算请求的时间
        $request_num = !empty($request_obj) ? $request_obj->request_num : 0;
        $remain_num = (100000 - $request_num)>0 ? 100000 - $request_num: 1;
        $now_time = time();
        $day_end = strtotime(date('Y-m-d 23:59:59'));
        $day_sec = $day_end - $now_time > 0 ? $day_end - $now_time : 1;
        $next_sec_time = $day_sec/$remain_num;//下一次更新的间隔时间
        $next_sec_time = $next_sec_time < 1 ? 0 : $next_sec_time;
        $last_request_time = time();
        $next_request_time = $next_sec_time + time();
        if($request_obj){
            $request_obj->request_num = $request_obj->request_num + 1;
            $request_obj->last_request_time = $last_request_time;
            $request_obj->next_request_time = $next_request_time;
            $request_obj->update_time = time();
            $res = $request_obj->save();
        }else{
            $req_data['request_num'] = 1;
            $req_data['day_time'] = $day_time;
            $req_data['last_request_time'] = $last_request_time;
            $req_data['next_request_time'] = $next_request_time;
            $req_data['create_time'] = time();
            $req_data['website_id'] = $this->website_id;
            $res = $mplive_request->save($req_data);
        }
        if($res){
            return true;
        }else{
            return false;
        }
    }
    /*
     * 更新直播间列表
     * **/
    public function updateMpliveList($mp_list)
    {
        $mplive = new MpliveModel();
        $mplive_goods = new MpliveGoodsModel();
        if($mp_list['errcode'] === 0 && $mp_list['room_info']){
            //存入请求次数。
            $this->saveRequestNum();
//                p($mp_list['room_info']);
            $i = 0;
            if($mp_list['room_info']){
                //获取我现在表里的直播间room_id
                $room_id_arr = $mplive->column('roomid');
                $return_room_id_arr = array_column($mp_list['room_info'], 'roomid');
                $delete_roomid = [];
                foreach($room_id_arr as $del_roomid){
                    if(!in_array($del_roomid, $return_room_id_arr)){
                        $delete_roomid[] = $del_roomid;
                    }
                }
                if($delete_roomid){
                    $delete_cond['roomid'] = ['in', $delete_roomid];
                    $mplive->where($delete_cond)->delete();
                    $mplive_goods->where($delete_cond)->delete();
                }
                foreach($mp_list['room_info'] as $k => $room_info){
                    $roomid = $room_info['roomid'];
                    $cond['roomid'] = $roomid;
                    $cond['website_id'] = $this->website_id;
                    $is_mplive = $mplive->getInfo($cond);
                    //表里存在则更新
                    if($is_mplive){
                        //暂时先注释
//                        if($room_info['live_status'] == '103' || $room_info['live_status'] == '107'){//已过期和已结束的不能修改。
//                            continue;
//                        }
                        $up_data[$k]['mplive_id'] = $is_mplive['mplive_id'];
                        $up_data[$k]['name'] = $room_info['name'];
                        $up_data[$k]['start_time'] = $room_info['start_time'];
                        $up_data[$k]['end_time'] = $room_info['end_time'];
                        $up_data[$k]['anchor_name'] = $room_info['anchor_name'];
                        $up_data[$k]['live_status'] = $room_info['live_status'];
                        $up_data[$k]['update_time'] = time();
                        $up_data[$k]['website_id'] = $this->website_id;
                        //goods
                        if($room_info['live_status'] == '102'){
                            if($room_info['goods']){
//                            //先删除。
//                            $mplive_goods->where($cond)->delete();
                                foreach($room_info['goods'] as $room_info_goods){
                                    $goods_cond['roomid'] = $roomid;
                                    $goods_cond['mp_goods_id'] = $room_info_goods['goods_id'];
                                    $is_mplive_goods = $mplive_goods->getInfo($goods_cond);
                                    if($is_mplive_goods){
                                        $update_data1['roomid'] = $room_info['roomid'];
                                        $update_data1['goods_name'] = $room_info_goods['name'];
                                        $update_data1['mp_goods_id'] = $room_info_goods['goods_id'];
                                        $update_data1['url'] = $room_info_goods['url'];
                                        $update_data1['price'] = $room_info_goods['price'];
                                        $update_data1['price2'] = $room_info_goods['price2'];
                                        $update_data1['price_type'] = $room_info_goods['price_type'];
                                        $mplive_goods->save($update_data1, $goods_cond);
                                    }else{
                                        $insert_goods_data[$i]['roomid'] = $room_info['roomid'];
                                        $insert_goods_data[$i]['goods_name'] = $room_info_goods['name'];
                                        $insert_goods_data[$i]['mp_goods_id'] = $room_info_goods['goods_id'];
                                        $insert_goods_data[$i]['goods_cover_img'] = $room_info_goods['cover_img'];
                                        $insert_goods_data[$i]['url'] = $room_info_goods['url'];
                                        $insert_goods_data[$i]['price'] = $room_info_goods['price'];
                                        $insert_goods_data[$i]['price2'] = $room_info_goods['price2'];
                                        $insert_goods_data[$i]['price_type'] = $room_info_goods['price_type'];
                                        $insert_goods_data[$i]['update_time'] = time();
                                        $insert_goods_data[$i]['website_id'] = $this->website_id;
                                        $i++;
                                    }
                                }
                            }
                        }
                    }else{//表里不存在
                        $insert_data[$k]['roomid'] = $room_info['roomid'];
                        $insert_data[$k]['name'] = $room_info['name'];
//                    $insert_data[$k]['cover_img'] = $room_info['cover_img'];
//                    $insert_data[$k]['share_img'] = $room_info['share_img'];
                        $insert_data[$k]['start_time'] = $room_info['start_time'];
                        $insert_data[$k]['end_time'] = $room_info['end_time'];
                        $insert_data[$k]['anchor_name'] = $room_info['anchor_name'];
                        $insert_data[$k]['live_status'] = $room_info['live_status'];
                        $insert_data[$k]['create_time'] = time();
                        $insert_data[$k]['website_id'] = $this->website_id;
//                        $mplive_goods->save($insert_data);
                        //处理商品
                        if($room_info['goods']){
                            foreach($room_info['goods'] as $room_info_goods){
                                $goods_cond['roomid'] = $roomid;
                                $goods_cond['mp_goods_id'] = $room_info_goods['goods_id'];
                                $is_mplive_goods = $mplive_goods->getInfo($goods_cond);
                                if($is_mplive_goods){
                                    $update_data[$i]['mplive_goods_id'] = $is_mplive_goods['mplive_goods_id'];
                                    $update_data[$i]['roomid'] = $room_info['roomid'];
                                    $update_data[$i]['goods_name'] = $room_info_goods['name'];
                                    $update_data[$i]['mp_goods_id'] = $room_info_goods['goods_id'];
                                    $update_data[$i]['url'] = $room_info_goods['url'];
                                    $update_data[$i]['price'] = $room_info_goods['price'];
                                    $update_data[$i]['price2'] = $room_info_goods['price2'];
                                    $update_data[$i]['price_type'] = $room_info_goods['price_type'];
                                    $mplive_goods->save($update_data, $goods_cond);
                                }else{
                                    $insert_goods_data[$i]['roomid'] = $room_info['roomid'];
                                    $insert_goods_data[$i]['goods_name'] = $room_info_goods['name'];
                                    $insert_goods_data[$i]['mp_goods_id'] = $room_info_goods['goods_id'];
                                    $insert_goods_data[$i]['goods_cover_img'] = $room_info_goods['cover_img'];
                                    $insert_goods_data[$i]['url'] = $room_info_goods['url'];
                                    $insert_goods_data[$i]['price'] = $room_info_goods['price'];
                                    $insert_goods_data[$i]['price2'] = $room_info_goods['price2'];
                                    $insert_goods_data[$i]['price_type'] = $room_info_goods['price_type'];
                                    $insert_goods_data[$i]['update_time'] = time();
                                    $insert_goods_data[$i]['website_id'] = $this->website_id;
                                    $i++;
                                }
                            }
                        }
                    }
                }
                if($up_data){
                    $mplive->saveAll($up_data);
                }
                if($update_data){
                    $mplive->saveAll($update_data);
                }
                if($insert_data){
                    $mplive->insertAll($insert_data);
                }
                if($insert_goods_data){
                    $mplive_goods->insertAll($insert_goods_data);
                }
                return ['code' => 1, 'message' => '更新成功'];
            }else{
                $mplive->where(1,1)->delete();
                $mplive_goods->where(1,1)->delete();
            }
        }else{
            if($mp_list['errcode'] === 1){
                return ['code' => -1, 'message' => '代表未创建直播房间'];
            }else{
                return ['code' => -1, 'message' => '更新失败'];
            }
        }
    }
    /*
     * 统计每个状态的数量
     * **/
    public function getStatusCount($condition = [])
    {
        $mplive = new MpliveModel();
        $count = $mplive->where($condition)->count();
        return $count;
    }
    /*
     * 获取商品信息
     * **/
    public function getGoodsInfo($goods_id)
    {
        $goodsSer = new \data\service\Goods();
        $goods_info = $goodsSer->getGoodsDetailById($goods_id, 'goods_id,picture', 1);
        if($goods_info['album_picture']){
            $goods_info['goods_img'] = getApiSrc($goods_info['album_picture']['pic_cover_micro']);
        }
        return $goods_info;
    }

    /**
     * 获取各个状态的商品数
     * @param $cond 条件
     * @return int
     */
    public function getGoodsCount($cond)
    {
        $library_goods = new MpliveGoodsLibraryModel();
        $count = $library_goods->getCount($cond);
        return $count;
    }

    /**
     * 获取直播商品信息
     * @param $cond
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getGoodsLibraryInfo($cond)
    {
        $library_goods = new MpliveGoodsLibraryModel();
        $goods_library_info = $library_goods->getInfo($cond);
        return $goods_library_info;
    }

    /**
     * 保存商品信息
     * @param $data
     * @param $cond
     * @return false|int
     */
    public function saveGoodsLibraryInfo($data, $cond)
    {
        $library_goods = new MpliveGoodsLibraryModel();
        $goods_library_info = $library_goods->save($data, $cond);
        return $goods_library_info;
    }

    /**
     * 根据获取直播商品列表
     * @param $cond
     * @param $field
     * @param $order
     * @return mixed
     */
    public function getGoodsLibraryList($cond, $field, $order)
    {
        $library_goods = new MpliveGoodsLibraryModel();
        $goods_list = $library_goods->getQuery($cond, $field, $order);
        return $goods_list;
    }

    public function updateGoodsStatus($goods_list, $website_id = 0)
    {
        $this->website_id = $website_id ? : $this->website_id;
        if(!$goods_list){
            return ['code' => -1, 'message' => '无商品需要更新'];
        }
        $goods_arr = objToArr($goods_list);
        if(is_array($goods_arr)){
            $mp_goods_id_arr = array_column($goods_arr, 'mp_goods_id');
        }
        $request_arr['goods_ids'] = $mp_goods_id_arr;
        $request_data = json_encode($request_arr);
        $wchat_oauth = new WchatOauth($this->website_id);
        $access_token = $wchat_oauth->getMpAccessToken($this->website_id);
        $request_url = 'https://api.weixin.qq.com/wxa/business/getgoodswarehouse?access_token='.$access_token;
//        $request_url = 'https://api.weixin.qq.com/wxaapi/broadcast/goods/getapproved?access_token='.$access_token;
        $header = ['Content-type:application/json'];
        $update_res = curlRequest($request_url, 'POST', $header, $request_data);
        $update_arr = json_decode($update_res, true);
        //记录请求次数
        $goods_request_mdl = new MpliveGoodsRequestModel();
        $day_time = strtotime(date('Y-m-d 00:00:00'));
        $today_cond['day_time'] = $day_time;
        $today_cond['website_id'] = $this->website_id;
        $goods_request_obj = $goods_request_mdl->where($today_cond)->find();
        if($goods_request_obj){
            $request_num = $goods_request_obj->request_num + 1;
            $next_time = $this->getNextUptime($request_num);
            $goods_request_obj->request_num = $goods_request_obj->request_num + 1;
            $goods_request_obj->next_time = $next_time;
            $goods_request_obj->update_time = time();
            $goods_request_obj->save();
        }else{
            $request_num = 1;
            $goods_request_arr['request_num'] = $request_num;
            $goods_request_arr['day_time'] = $day_time;
            //计算下一次更新的时间
            $next_time = $this->getNextUptime($request_num);
            $goods_request_arr['next_time'] = $next_time;
            $goods_request_arr['create_time'] = time();
            $goods_request_arr['website_id'] = $this->website_id;
            $goods_request_mdl->save($goods_request_arr);

        }
        if($update_arr['errcode'] === 0){
            $library_goods = new MpliveGoodsLibraryModel();
            if($update_arr['goods']){
                //将更新的结果存到
                foreach($update_arr['goods'] as $goods_arr){
                    $mp_goods_id = $goods_arr['goods_id'];
                    $update_data['status'] = $goods_arr['audit_status'];
                    $library_goods->where(['mp_goods_id' => $mp_goods_id, 'website_id' => $this->website_id])->update($update_data);
                }
            }
            return ['code' => 1, 'message' => '更新成功'];
        }else{
            return ['code' => -1, 'message' => $update_arr['errcode']];
        }
    }

    public function getNextUptime($request_num)
    {
        $now_time = time();
        $end_time = strtotime(date('Y-m-d 23:59:59'));
        $remain_time = $end_time - $now_time;
        $remain_num = (10000 - $request_num)>0 ? 10000 - $request_num: 1;
        $day_sec = $remain_time > 0 ? $remain_time : 1;
        $next_sec_time = $day_sec/$remain_num;//下一次更新的间隔时间
        $next_sec_time = $next_sec_time < 1 ? 0 : $next_sec_time;
        $next_time = time() + $next_sec_time;
        return $next_time;
    }

    /**
     * 更新直接在小程序控制台加的商品
     * @param $goods_list
     */
    public function updateLiveGoodsList($goods_list, $status)
    {
        $mplive_gl = new MpliveGoodsLibraryModel();
        $goodsSer = new \data\service\Goods();
        if($goods_list['goods']){
            $i = 0;
            $j = 0;
            foreach($goods_list['goods'] as $k=>$g){
                $mp_goods_id = $g['goodsId'];
                $is_gl_goods = $mplive_gl->getInfo(['mp_goods_id' => $mp_goods_id]);
                if($is_gl_goods){
                    $up_data[$i]['goods_library_id'] = $is_gl_goods['goods_library_id'];
                    $up_data[$i]['goods_name'] = $g['name'];
                    //获取系统商品id
                    $url = $g['url'];
                    $goods_id_str = explode('?', $url)[1];
                    $goods_id = explode('=', $goods_id_str)[1] ? : 0;
                    $up_data[$i]['goods_id'] = $goods_id;
                    $up_data[$i]['mp_goods_id'] = $mp_goods_id;
                    $up_data[$i]['status'] = $status;//商品状态，0：未审核。1：审核中，2：审核通过，3：审核驳回
                    $up_data[$i]['price'] = $g['price'];
                    $up_data[$i]['price_type'] = $g['priceType'];
                    $up_data[$i]['price2'] = $g['price2'];
                    $i++;
                }else{
                    $insert_data[$j]['goods_name'] = $g['name'];
                    //获取系统商品id
                    $url = $g['url'];
                    $goods_id_str = explode('?', $url)[1];
                    $goods_id = explode('=', $goods_id_str)[1] ? : 0;
                    $insert_data[$j]['goods_id'] = $goods_id;
                    $insert_data[$j]['mp_goods_id'] = $mp_goods_id;
                    $goods_img_arr = $goodsSer->getGoodsDetailById($goods_id, 'goods_id,picture', 1);
                    $goods_img = getApiSrc($goods_img_arr['album_picture']['pic_cover_small']) ? : '';
                    $insert_data[$j]['goods_img'] = $goods_img;
                    $insert_data[$j]['status'] = $status;
                    $insert_data[$j]['price'] = $g['price'];
                    $insert_data[$j]['price_type'] = $g['priceType'];
                    $insert_data[$j]['price2'] = $g['price2'];
                    $insert_data[$j]['website_id'] = $this->website_id;
                    $j++;
                }
            }
            if($up_data){
                $res1 = $mplive_gl->saveAll($up_data);
            }
            if($insert_data){
                $res2 = $mplive_gl->saveAll($insert_data);
            }
            if($res1 && $res2){
                return true;
            }
        }
    }
}