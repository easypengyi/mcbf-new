<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/8 0008
 * Time: 11:16
 */

namespace addons\mplive\controller;
use addons\miniprogram\service\MiniProgram;
use addons\mplive\model\MpliveGoodsLibraryModel;
use addons\mplive\model\MpliveGoodsModel;
use addons\mplive\model\MpliveGoodsRequestModel;
use addons\mplive\model\MpliveModel;
use addons\mplive\model\MpliveRequestModel;
use app\wapapi\controller\Upload;
use data\extend\WchatOauth;
use data\service\BaseService;
use data\service\Goods;
use think\Db;
use addons\mplive\Mplive as baseMplive;
use addons\mplive\service\Mplive as mpliveService;

class Mplive extends baseMplive
{
    public function __construct()
    {
        parent::__construct();
        $this->service = new mpliveService();
    }
    /*
     * 保存设置
     * **/
    public function saveMpliveBasicSetting()
    {
//        p(request()->post());exit;
        $is_mplive_use = request()->post('is_mplive_use', '');
        $is_auto_update = request()->post('is_auto_update', '');
        $data['list_show_type'] = request()->post('list_show_type', 1);
        if(request()->post('living')){
            $data['living'] = request()->post('living', 0);
        }
        if(request()->post('unstart')){
            $data['unstart'] = request()->post('unstart', 0);
        }
        if(request()->post('stop')){
            $data['stop'] = request()->post('stop', 0);
        }
        if(request()->post('living')){
            $data['living'] = request()->post('living', 0);
        }
        if(request()->post('ended')){
            $data['ended'] = request()->post('ended', 0);
        }
        if(request()->post('error')){
            $data['error'] = request()->post('error', 0);
        }
        if(request()->post('past')){
            $data['past'] = request()->post('past', 0);
        }
        $data['is_mplive_use'] = $is_mplive_use == 'on' ? 1 : 0;
        $data['is_auto_update'] = $is_auto_update == 'on' ? 1 : 0;
        //直播回放天数
        $mplive_replay_day = request()->post('mplive_replay_day', 0);//0表示不限制
        $data['mplive_replay_day'] = $mplive_replay_day;
        $res = $this->service->saveMpliveSetting($data);
        if($res){
            setAddons('mplive', $this->website_id, $this->instance_id);
//            setAddons('mplive', $this->website_id, $this->instance_id, true);
            $this->addUserLog('小程序直播“设置”保存', $res);
            return ['code' => 1, 'message' => '保存成功'];
        }else{
            return ['code' => 1, 'message' => '保存失败'];
        }
    }
    /*
     * 获取小程序后台创建的直播间列表
     * **/
    public function updateMpLiveList()
    {
        //获取小程序access_token
        $condition['shop_id'] = $this->instance_id;
        $condition['website_id'] = $this->website_id;
        $mini_program_service = new MiniProgram();
        $mini_program_info = $mini_program_service->miniProgramInfo($condition);
        if(!$mini_program_info){
            return ['code' => -1, 'message' => '小程序未授权'];
        }
        $wchat_oauth = new WchatOauth($this->website_id);
        $access_token = $wchat_oauth->getMpAccessToken($this->website_id);
        $request_url = 'https://api.weixin.qq.com/wxa/business/getliveinfo?access_token='.$access_token;
        $request_data = json_encode([
            "start" =>0,
            "limit"=>100
        ]);
        $header = [
            'Content-Type:application/json'
        ];
        $res = curlRequest($request_url, 'POST', $header, $request_data);
        if($res){
            $mp_list = json_decode($res, true);
            //将列表内容存入表中。
            $res = $this->service->updateMpLiveList($mp_list);
            if($res['code'] == -1){
                return $res;
            }
            //拿到总条数
            $total = $mp_list['total'];
            //每页100条
            $page_size = 100;
            //有多少页
            $page_count = ceil($total/$page_size);
            if($page_count > 1){
                for($i=2; $i<$page_count; $i++){
                    //算起点值
                    $offset = $i * $page_size;
                    $request_data = json_encode([
                        "start" =>$offset,
                        "limit"=>100
                    ]);
                    $res2 = curlRequest($request_url, 'POST', $header, $request_data);
                    $mp_list2 = json_decode($res2, true);
                    $res = $this->service->updateMpLiveList($mp_list2);
                }
            }
            return $res;
        }
    }
    /*
     * 获取小程序后台创建的直播间列表
     * **/
    public function updateLiblaryGoodsList()
    {
        //获取小程序access_token
        $condition['shop_id'] = $this->instance_id;
        $condition['website_id'] = $this->website_id;
        $mini_program_service = new MiniProgram();
        $mini_program_info = $mini_program_service->miniProgramInfo($condition);
        if(!$mini_program_info){
            return ['code' => -1, 'message' => '小程序未授权'];
        }
        $wchat_oauth = new WchatOauth($this->website_id);
        $access_token = $wchat_oauth->getMpAccessToken($this->website_id);
        //数据有四个状态 商品状态，0：未审核。1：审核中，2：审核通过，3：审核驳回
        $status_arr = [
            0, 1, 2, 3
        ];
        $act = true;
        $mp_goods_arr = [];
        foreach($status_arr as $status){
            $request_data = [
                "offset" => 0,
                "limit"=> 100,
                "status" => $status,//审核通过
            ];
            $request_str = http_build_query($request_data);
            $request_url = 'https://api.weixin.qq.com/wxaapi/broadcast/goods/getapproved?access_token='.$access_token .'&'.$request_str;
            $header = [
                'Content-Type:text/html;charset=utf8'
            ];
            $res = curlRequest($request_url, 'GET', $header, $request_data);
            if($res){
                $goods_list = json_decode($res, true);
                //将列表内容存入表中。
                $res = $this->service->updateLiveGoodsList($goods_list, $status);
                if($goods_list['goods']){
                    $mp_goods_arr[] = array_column($goods_list['goods'], 'goodsId');
                }
                //拿到总条数
                $total = $goods_list['total'];
                //每页100条
                $page_size = 100;
                //有多少页
                $page_count = ceil($total/$page_size);
                if($page_count > 1){
                    for($i=2; $i<$page_count; $i++){
                        //算起点值
                        $offset = $i * $page_size;
                        $request_data = json_encode([
                            "start" =>$offset,
                            "limit"=>100
                        ]);
                        $res2 = curlRequest($request_url, 'GET', $header, $request_data);
                        $goods_list2 = json_decode($res2, true);
                        if($goods_list2['goods']){
                            $mp_goods_arr[] = array_column($goods_list2['goods'], 'goodsId');
                        }
                        $res = $this->service->updateLiveGoodsList($goods_list2, $status);
                    }
                }
                if(!$res){
                    $act = false;
                }
            }
        }
        $mp_goods_id_arr = [];
        foreach($mp_goods_arr as $goodsid_arr){
            foreach($goodsid_arr as $mp_goods_id){
                $mp_goods_id_arr[] = $mp_goods_id;
            }
        }
        $mplive_gl = new MpliveGoodsLibraryModel();
        //查询出所有的系统的商品id
        $mp_goods_ids = $mplive_gl->column('mp_goods_id');
        $del_goods_id = [];
        foreach($mp_goods_ids as $goodsId){
            if(!in_array($goodsId, $mp_goods_id_arr)){
                $del_goods_id[] = $goodsId;
            }
        }
        if($del_goods_id){
            $del_cond['mp_goods_id'] = ['in', $del_goods_id];
            $mplive_gl->where($del_cond)->delete();
        }
        return $act;
    }
    /*
     * 后台获取直播间列表
     * **/
    public function getMpLiveList()
    {
        //判断后台是否设置了自动更新数据
        $addonsConfigSer = new \data\service\AddonsConfig();
        $conf_arr = $addonsConfigSer->getAddonsConfig('mplive', $this->website_id, 0, 1);
        if($conf_arr['is_auto_update'] == 1){
            $this->isUpdateData();
        }
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $live_info = request()->post('live_info', '');
        $live_status = request()->post('live_status', 0);
        $mplive = new MpliveModel();
        if($live_info){
            $condition['name|roomid'] = [
                'like', '%'.$live_info.'%'
            ];
        }
        if($live_status && $live_status != '-1'){
            $condition['live_status'] = $live_status;
        }
        $condition['website_id'] = $this->website_id;
        $order = 'roomid desc';
        $field = '*, from_unixtime(start_time) as start_date, from_unixtime(end_time) as end_date';
        $mplive_list = $mplive->pageQuery($page_index, $page_size, $condition, $order, $field);
        $mplive_goods = new MpliveGoodsModel();
        foreach($mplive_list['data'] as $k => $mplive_info){
            $roomid = $mplive_info['roomid'];
            $goods_count = $mplive_goods->getCount(['roomid' => $roomid]);
            $mplive_list['data'][$k]['goods_count'] = $goods_count;
        }
        //统计每个状态的数量
        $condition0['website_id'] = $this->website_id;
        $count_arr['total_count'] = $this->service->getStatusCount($condition0);
        $condition1['live_status'] = 101;//101: 直播中
        $condition1['website_id'] = $this->website_id;
        $count_arr['living_count'] = $this->service->getStatusCount($condition1);
        $condition2['live_status'] = 102;//102: 未开始
        $condition2['website_id'] = $this->website_id;
        $count_arr['unstart_count'] = $this->service->getStatusCount($condition2);
        $condition3['live_status'] = 103;//103: 已结束
        $condition3['website_id'] = $this->website_id;
        $count_arr['ended_count'] = $this->service->getStatusCount($condition3);
        $condition4['live_status'] = 104;//104: 禁播
        $condition4['website_id'] = $this->website_id;
        $count_arr['forbid_count'] = $this->service->getStatusCount($condition4);
        $condition5['live_status'] = 105;//105: 暂停中
        $count_arr['stop_count'] = $this->service->getStatusCount($condition5);
        $condition6['live_status'] = 106;//106: 异常
        $condition6['website_id'] = $this->website_id;
        $count_arr['error_count'] = $this->service->getStatusCount($condition6);
        $condition7['live_status'] = 107;//107: 已过期
        $condition7['website_id'] = $this->website_id;
        $count_arr['past_count'] = $this->service->getStatusCount($condition7);
        $mplive_list['count'] = $count_arr;
        //获取今天直播间上次更新时间
        $cond['website_id'] = $this->website_id;
        $mplive_request = new MpliveRequestModel();
        $request_data = $mplive_request->where($cond)->field('last_request_time')->order('next_request_time DESC')->find();
        if($request_data['last_request_time']){
            $mplive_list['last_request_time'] = date('Y-m-d H:i:s', $request_data['last_request_time']);
        }
        return $mplive_list;
    }

    /*
     * 前台获取直播间列表
     * **/
    public function getWapMpLiveList()
    {
        //判断后台是否设置了自动更新数据
        $addonsConfigSer = new \data\service\AddonsConfig();
        $conf_arr = $addonsConfigSer->getAddonsConfig('mplive', $this->website_id, 0, 1);
        if($conf_arr['is_auto_update'] == 1){
            $this->isUpdateData();
        }
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $live_info = request()->post('live_info', '');
        $mplive = new MpliveModel();
        $condition = '';
        if($live_info){
//            $condition['name|roomid'] = [
//                'like', '%'.$live_info.'%'
//            ];
            $condition .= "(name like '%'.$live_info.'%' or roomid '%'.$live_info.'%') and ";
        }
//        $condition['website_id'] = $this->website_id;
        //判断当前显示的条件
        $condition .= '(live_status = 999 ';
        if($conf_arr['living'] == 1){
            $condition .= 'or live_status = 101';
        }
        if($conf_arr['unstart'] == 2){
            $condition .= ' or live_status = 102';
        }
        if($conf_arr['stop'] == 3){
            $condition .= ' or live_status = 105';
        }
        if($conf_arr['ended'] == 4){
            $condition .= ' or (';
            $condition .= 'live_status = 103';
            //判断基础设置是否设置了过期时间
            if($conf_arr['mplive_replay_day']){
                $condition .= ' and end_time >= '.(time() - $conf_arr['mplive_replay_day']*24*3600);
            }
            $condition .= ')';
        }
        if($conf_arr['error'] == 5){
            $condition .= ' or live_status = 106';
        }
        if($conf_arr['past'] == 6){
            $condition .= ' or live_status = 107';
        }
        $condition .= ') and website_id='.$this->website_id;
        $order = 'live_status asc, start_time asc';
        $field = '*, from_unixtime(start_time) as start_date, from_unixtime(end_time) as end_date';
        $mplive_list = $mplive->pageQuery($page_index, $page_size, $condition, $order, $field);
        foreach($mplive_list['data'] as $k =>$mplive_info){
            //未开始的时间格式化
            if($mplive_info['live_status'] == 102){
                //判断预告时间是否是 今天、明天、后天。
                $today = date('d');
                $advance_day = date('d', $mplive_info['start_time']);
                if($today == $advance_day){
                    $day_string = '今天';
                }elseif($advance_day  == ($today+ 1)){
                    $day_string = '明天';
                }elseif($advance_day == ($today + 2)){
                    $day_string = '后天';
                }else{
                    $day_string = date('m-d', $mplive_info['start_time']);
                }
                $mplive_list['data'][$k]['advance_date'] = $day_string.' '.date('h:i', $mplive_info['start_time']);
            }
        }
        $mplive_list['code'] = 1;
        return json($mplive_list);
    }
    /*
     * 判断当前是否需要更新数据
     * **/
    public function isUpdateData()
    {
        //判断更新时间，如果更新时间已经小于当前时间了，可以更新
        $mplive_request = new MpliveRequestModel();
        $req_cond['day_time'] = strtotime(date('Y-m-d 00:00:00'));
        $req_cond['website_id'] = $this->website_id;
        $request_info = $mplive_request->getInfo($req_cond);
        if($request_info){
            if($request_info['next_request_time'] && $request_info['next_request_time'] <= time()){
                $res = $this->updateMpLiveList();
                return $res['code'];
            }else{
                return false;
            }
        }else{
            $res = $this->updateMpLiveList();
            return $res['code'];
        }
    }

    /*
     * 判断当前是否需要更新数据
     * **/
    public function isPlatformUpdateData()
    {
        $res = $this->updateMpLiveList();
        if($res['code'] == 1){
            return json(['code' => 1, 'message' => $res['message']]);
        }else{
            return json(['code' => -1, 'message' => $res['message']]);
        }
    }
    /*
     * 前端更新状态后，如果找不到直播间，则删除。
     * **/
    public function delMpLive()
    {
        try{
            Db::startTrans();
            $mplive_mdl = new MpliveModel();
            $mplive_goods_mdl = new MpliveGoodsModel();
            $roomid = request()->post('roomid', 0);
            $cond['roomid'] = $roomid;
            $cond['website_id'] = $this->website_id;
            $mplive_mdl->where($cond)->delete();
            $res = $mplive_goods_mdl->where($cond)->delete();
            if($res){
                return ['code'=> 1, 'message' => '删除成功'];
            }else{
                return ['code'=> -1, 'message' => '删除失败'];
            }
            Db::commit();
        }catch(\Exception $e){
            return ['code'=> -1, 'message' => $e->getMessage()];
            Db::rollback();
        }
    }
    /*
     * 更新直播间状态
     * **/
    public function updateMplive()
    {
        $roomid = request()->post('roomid', 0);
        $live_status = request()->post('live_status', 0);
        $cond['roomid'] = $roomid;
        $cond['website_id'] = $this->website_id;
        $up_data['live_status'] = $live_status;
        $mplive = new MpliveModel();
        $res = $mplive->save($up_data, $cond);
        if($res){
            $this->addUserLog('小程序直播更新直播间状态', $res);
        }
        return ['code'=>1, 'message'=>'更新状态成功'];
    }
    /*
     * 提交商品审核
     * **/
    public function submitGoods()
    {
        $goods_id = request()->post('goods_id', 0);
        $goods_name = request()->post('goods_name', '');
        $price_type = request()->post('price_type', 0);
        $price = request()->post('price', 0);
        $price2 = request()->post('price2', 0);
        $url = MPGOODSDETAIL.'?goods_id='.$goods_id;
        $goods_img = request()->post('goods_img', '');
        if(!$goods_id || !$price_type || !$url || !$goods_name || !$price){
            return ['code' => -1, 'message' => LACK_OF_PARAMETER];
        }
        $goods_library = new MpliveGoodsLibraryModel();
        $condition['goods_id'] = $goods_id;
        $condition['website_id'] = $this->website_id;
        $is_exists = $goods_library->getInfo($condition);
        if($is_exists){
            return ['code' => -1, 'message' => '该商品已经添加过了，请删除后再操作'];
        }
        if(($price_type == 2 || $price_type == 3) && !$price2){
            return ['code' => -1, 'message' => LACK_OF_PARAMETER];
        }
        $wchat_oauth = new WchatOauth($this->website_id);
        $media_arr = $this->getMediaId($goods_img, 200, 200);
        if($media_arr['code'] == -1){
            return $media_arr;
        }

        $media_id = $media_arr['media_id'];
        $params['goodsInfo']['coverImgUrl'] = $media_id;
        $params['goodsInfo']['name'] = $goods_name;
        $params['goodsInfo']['priceType'] = $price_type;
        $params['goodsInfo']['price'] = $price;
        $params['goodsInfo']['price2'] = '';
        if($price_type == 2 || $price_type == 3){
            $params['goodsInfo']['price2'] = $price2;
        }
        $params['goodsInfo']['url'] = $url;
        $request_data = json_encode($params);
//        var_dump((float)$price);
//        var_dump($params);exit;
        $access_token = $wchat_oauth->getMpAccessToken($this->website_id);
        $request_url = 'https://api.weixin.qq.com/wxaapi/broadcast/goods/add?access_token='.$access_token;
        $header = ['Content-type:application/json'];
        $addgoods_res = curlRequest($request_url, 'POST', $header, $request_data);
//        $res = '{"goodsId":6,"auditId":439055671,"errcode":0}';
//        $addgoods_res = '{"goodsId":7,"auditId":439055682,"errcode":0}';
        $addgoods_arr = json_decode($addgoods_res, true);
        if($addgoods_arr['errcode'] === 0){
            //插入直播商品表
            $library_data['goods_name'] = $goods_name;
            $library_data['goods_id'] = $goods_id;
            $library_data['goods_img'] = $goods_img;
            $library_data['price_type'] = $price_type;
            $library_data['price'] = $price;
            $library_data['price2'] = $price2 ? : 0;
            $library_data['mp_goods_id'] = $addgoods_arr['goodsId'];
            $library_data['audit_id'] = $addgoods_arr['auditId'];
            $library_data['media_id'] = $media_id;
            $library_data['create_time'] = time();
            $library_data['website_id'] = $this->website_id;
            //查询是否已经入库了
            $res = $goods_library->save($library_data);
            if($res){
                $this->addUserLog('小程序直播提交商品审核', $res);
                return ['code' => 1, 'message'=>'添加商品成功'];
            }
        }else{
            return ['code' => -1, 'message'=>$addgoods_arr['errmsg']];
        }
    }

    /**
     * 获取素材id
     * @param $img_file 图片文件路径
     * @return array
     */
    public function getMediaId($img_file, $width, $height)
    {
        $file_str = basename($img_file);
        $file_name = substr(basename($file_str), 0, strpos($file_str, '.'));
        $base_service = new BaseService();
        $wchat_oauth = new WchatOauth($this->website_id);
        $download_dir = 'upload/'.$this->website_id.'/mplive_goods/';
        $thumb_dir = 'upload/'.$this->website_id.'/mplive_goods/thumb/';
        if(!is_dir($download_dir)){
            @mkdir($download_dir, 0777, true);
        }
        if(!is_dir($thumb_dir)){
            @mkdir($thumb_dir, 0777, true);
        }
        $down_file = $download_dir.$file_name.'.jpg';
        $base_service->downFile($img_file, $down_file);
        //将图片转为200*200尺寸
        $upload = new Upload();
        $thumb_file = $thumb_dir.'thumb_'.$file_name.'.jpg';
        $img_res = $upload->uploadThumbFile($down_file, $thumb_file, $width, $height);
        if($img_res['code'] === false){
            return ['code' => -1, 'message' => '图片错误'];
        }
        $new_file = realpath($img_res['path']);
        $media_arr = $wchat_oauth->upload_temp_exec('image', $new_file, $this->website_id);
        if($media_arr['errcode']){
            return ['code' => -1, 'message' => $media_arr['errmsg']];
        }
        if($media_arr && $media_arr['media_id']){
            $media_id = $media_arr['media_id'];
        }
        unlink($new_file);
        if($media_id){
            return ['code' => 1, 'media_id' => $media_id];
        }
    }
    /**
     * 获取商品供小程序商品选择
     * @return
     */
    public function modalMpliveGoodsList()
    {
        $index = request()->post('page_index', 1);
        $goods_type = request()->post('goods_type', 1);
        $search_text = request()->post('search_text');
        if ($search_text) {
            $condition['goods_name'] = ['LIKE', '%' . $search_text . '%'];
        }
        $condition['ng.website_id'] = $this->website_id;
        $condition['ng.state'] = 1;
        //0自营店 1全平台
        if ($goods_type == '0') {
            $condition['ng.shop_id'] = $this->instance_id;
        }
        $goods = new Goods();
        $list = $goods->getModalGoodsList($index, $condition);
        return $list;
    }

    /**
     * 获取直播商品库列表
     * @return array
     */
    public function getMpLiveGoodsList()
    {
        //获取上次更新的时间
        $goods_request = new MpliveGoodsRequestModel();
        $request_cond['day_time'] = strtotime(date('Y-m-d 00:00:00'));
        $request_cond['website_id'] = $this->website_id;
        $request_info = $goods_request->getInfo($request_cond);
        if($request_info['next_time'] < time()){
            $this->updateGoodsStatus();
        }
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $goods_name = request()->post('goods_name', '');
        $status = request()->post('status', 0);
        $goods_library = new MpliveGoodsLibraryModel();
        if($goods_name){
            $cond['goods_name'] = ['like', '%'.$goods_name.'%'];
        }
        if(($status && $status != '-1') || $status === '0'){
            $cond['status'] = $status;
        }
        $cond['website_id'] = $this->website_id;
        $order = 'goods_library_id desc';
        $goods_list = $goods_library->pageQuery($page_index, $page_size, $cond, $order, '*');
        //获取各个状态的数量
        $checking_cond['status'] = 0;//未审核
        $checking_cond['website_id'] = $this->website_id;
        $uncheck_count = $this->service->getGoodsCount($checking_cond);

        $checking_cond['status'] = 1;//审核中
        $checking_cond['website_id'] = $this->website_id;
        $checking_count = $this->service->getGoodsCount($checking_cond);

        $pass_cond['status'] = 2;//审核通过
        $pass_cond['website_id'] = $this->website_id;
        $pass_count = $this->service->getGoodsCount($pass_cond);

        $forbid_cond['status'] = 3;//未审核通过
        $forbid_cond['website_id'] = $this->website_id;
        $forbid_count = $this->service->getGoodsCount($forbid_cond);

        $total_cond['website_id'] = $this->website_id;//全部
        $total_count = $this->service->getGoodsCount($total_cond);
        $goods_list['count']['checking_count'] = $checking_count;
        $goods_list['count']['pass_count'] = $pass_count;
        $goods_list['count']['forbid_count'] = $forbid_count;
        $goods_list['count']['uncheck_count'] = $uncheck_count;
        $goods_list['count']['total_count'] = $total_count;

        //获取上次更新的时间
        $goods_request = new MpliveGoodsRequestModel();
        $request_cond['day_time'] = strtotime(date('Y-m-d 00:00:00'));
        $request_cond['website_id'] = $this->website_id;
        $last_update_time = $goods_request->getInfo($request_cond)['update_time'] ? : '';
        if($last_update_time){
            $last_update_date = date('Y-m-d H:i:s', $last_update_time);
        }
        $goods_list['update_info']['update_date'] = $last_update_date ? : '';
        //获取小程序的权限
        $addon_status = getPortIsOpen($this->website_id);
        $goods_list['addon_status'] = $addon_status;
        return $goods_list;
    }
    /**
     * 获取直播商品库列表
     * @return array
     */
    public function getMpLiveGoodsListForPick()
    {
        //获取上次更新的时间
        $goods_request = new MpliveGoodsRequestModel();
        $request_cond['day_time'] = strtotime(date('Y-m-d 00:00:00'));
        $request_cond['website_id'] = $this->website_id;
        $request_info = $goods_request->getInfo($request_cond);
        if($request_info['next_time'] < time()){
            $this->updateGoodsStatus();
        }
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $goods_name = request()->post('goods_name', '');
        $roomid = request()->post('roomid', 0);
        $goods_library = new MpliveGoodsLibraryModel();
        if($goods_name){
            $cond['goods_name'] = ['like', '%'.$goods_name.'%'];
        }
        $cond['status'] = 2;
        $cond['website_id'] = $this->website_id;
        $mplive_goods = new MpliveGoodsModel();
        $mp_goods_id_arr = $mplive_goods->where(['roomid' => $roomid])->column('mp_goods_id');
        $order = 'goods_library_id desc';
        $goods_list = $goods_library->pageQuery($page_index, $page_size, $cond, $order, '*');
        //获取上次更新的时间
        foreach($goods_list['data'] as $k => $goods_info){
            if(in_array($goods_info['mp_goods_id'], $mp_goods_id_arr)){
                $goods_list['data'][$k]['pick_status'] = 4;
            }
        }
        $goods_request = new MpliveGoodsRequestModel();
        $request_cond['day_time'] = strtotime(date('Y-m-d 00:00:00'));
        $request_cond['website_id'] = $this->website_id;
        $last_update_time = $goods_request->getInfo($request_cond)['update_time'] ? : '';
        if($last_update_time){
            $last_update_date = date('Y-m-d H:i:s', $last_update_time);
        }
        $goods_list['update_info']['update_date'] = $last_update_date ? : '';
        //获取小程序的权限
        $addon_status = getPortIsOpen($this->website_id);
        $goods_list['addon_status'] = $addon_status;
        return $goods_list;
    }

    /**
     * 获取单条商品信息
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getGoodsLibraryInfo()
    {
        $goods_library_id = request()->post('goods_library_id', 0);
        $cond = ['goods_library_id' => $goods_library_id];
        $goods_library_info = $this->service->getGoodsLibraryInfo($cond);
        return ['code'=>1, 'data' => $goods_library_info];
    }

    /**
     * 执行撤回商品审核
     */
    public function actBackoutCheck()
    {
        $goods_library_id = request()->post('goods_library_id', 0);
        $cond = ['goods_library_id' => $goods_library_id];
        $goods_library_info = $this->service->getGoodsLibraryInfo($cond);
        $data['goodsId'] = $goods_library_info['mp_goods_id'];
        $data['auditId'] = $goods_library_info['audit_id'];
        $request_data = json_encode($data);
        $wchat_oauth = new WchatOauth($this->website_id);
        $access_token = $wchat_oauth->getMpAccessToken($this->website_id);
        $request_url = 'https://api.weixin.qq.com/wxaapi/broadcast/goods/resetaudit?access_token='.$access_token;
        $header = ['Content-type:application/json'];
        $update_res = curlRequest($request_url, 'POST', $header, $request_data);
        $update_arr = json_decode($update_res, true);
        if($update_arr['errcode'] === 0){
            $goods_data['update_time'] = time();
            $res = $this->service->saveGoodsLibraryInfo($goods_data, $cond);
            $this->addUserLog('小程序直播撤销商品审核', $res);
            return ['code' => 1, 'message' => '撤销审核成功'];
        }else{
            if($update_arr['errmsg']){
                $msg = $update_arr['errmsg'];
            }else{
                $msg = '撤销审核失败';
            }
            return ['code' => -1, 'message' => $msg];
        }
    }
    /**
     * 更改直播商品信息：名称价格图片等。
     * @return array
     */
    public function updateMpliveGoods()
    {
        $goods_library_id = request()->post('goods_library_id', 0);
        $mp_goods_id = request()->post('mp_goods_id', 0);
        $goods_img = request()->post('goods_img', '');
        $goods_name = request()->post('goods_name', '');
        $price_type = request()->post('price_type', 0);
        $price = request()->post('price', 0);
        $price2 = request()->post('price2', 0);
        $cond = ['goods_library_id' => $goods_library_id];
        if(!$goods_library_id || !$mp_goods_id){
            return ['code' => -1, 'message' => LACK_OF_PARAMETER];
        }
        $goods_library_info = $this->service->getGoodsLibraryInfo($cond);
        //判断当前图片是否和库里的一样
        if($goods_img && ($goods_img != $goods_library_info['goods_img'])){
            $media_id = $this->getMediaId($goods_img, 200, 200);
            $update_data['goodsInfo']['coverImgUrl'] = $media_id;
            $goods_data['media_id'] = $media_id;
            $goods_data['goods_img'] = $goods_img;
        }
        if($goods_name){
            $update_data['goodsInfo']['name'] = $goods_name;
            $goods_data['goods_name'] = $goods_name;
        }
        if($price_type){
            $update_data['goodsInfo']['priceType'] = $price_type;
            $goods_data['price_type'] = $price_type;
        }
        if($price){
            $update_data['goodsInfo']['price'] = $price;
            $goods_data['price'] = $price;
        }
        if($price2){
            $update_data['goodsInfo']['price2'] = $price2;
            $goods_data['price2'] = $price2;
        }
        $update_data['goodsInfo']['goodsId'] = $mp_goods_id;
        $request_data = json_encode($update_data);
        $wchat_oauth = new WchatOauth($this->website_id);
        $access_token = $wchat_oauth->getMpAccessToken($this->website_id);
        $request_url = 'https://api.weixin.qq.com/wxaapi/broadcast/goods/update?access_token='.$access_token;
        $header = ['Content-type:application/json'];
        $update_res = curlRequest($request_url, 'POST', $header, $request_data);
        $update_arr = json_decode($update_res, true);
        if($update_arr['errcode'] === 0){
            $goods_data['update_time'] = time();
            $res = $this->service->saveGoodsLibraryInfo($goods_data, $cond);
            $this->addUserLog('小程序直播更改直播商品信息', $res);
            return ['code' => 1, 'message' => '修改成功'];
        }else{
            if($update_arr['errmsg']){
                $msg = $update_arr['errmsg'];
            }else{
                $msg = '修改失败';
            }
            return ['code' => -1, 'message' => $msg];
        }
    }

    /**
     * 获取小程序直播库的商品状态并更新到数据库
     * @return array
     */
    public function updateGoodsStatus()
    {
        $this->updateLiblaryGoodsList();
        $cond['website_id'] = $this->website_id;
        $field = 'goods_library_id, mp_goods_id';
        $order = 'goods_library_id desc';
        $goods_list = $this->service->getGoodsLibraryList($cond, $field, $order);
        $res = $this->service->updateGoodsStatus($goods_list);
        return $res;
    }

    public function deleteMpliveGoods()
    {
        $mp_goods_id = request()->post('mp_goods_id', 0);
        if(!$mp_goods_id){
            return ['code' => -1, 'message' => LACK_OF_PARAMETER];
        }
        $request_arr['goodsId'] = $mp_goods_id;
        $request_data = json_encode($request_arr);
        $wchat_oauth = new WchatOauth($this->website_id);
        $access_token = $wchat_oauth->getMpAccessToken($this->website_id);
        $goods_library = new MpliveGoodsLibraryModel();
        $request_url = 'https://api.weixin.qq.com/wxaapi/broadcast/goods/delete?access_token='.$access_token;
        $header = ['Content-type:application/json'];
        $delete_res = curlRequest($request_url, 'POST', $header, $request_data);
//        $delete_res = '{"success":true,"openid":"","errcode":0}';
        $delete_arr = json_decode($delete_res, true);
        if($delete_arr['errcode'] === 0 && $delete_arr['success'] === true){
            $del_cond['mp_goods_id'] = $mp_goods_id;
            $del_cond['website_id'] = $this->website_id;
            $res = $goods_library->where($del_cond)->delete();
            if($res){
                $this->addUserLog('小程序直播删除小程序商品', $res);
            }
            return ['code' => 1, 'message' => '删除成功'];
        }else{
            if($delete_arr['errmsg']){
                $msg = $delete_arr['errmsg'];
            }else{
                $msg = '删除失败';
            }
            return ['code' => -1, 'message' => $msg];
        }
    }

    /**
     * 创建直播间
     */
    public function createMplive()
    {
        $post_data = request()->post();
        $request_data['name'] = $post_data['name'];// 房间名字
        $cover_img = getApiSrc($post_data['coverImg']);
        //获取 背景图 的mediaid
        $cover_media_data = $this->getMediaId($cover_img, 1080, 1920);
        if($cover_media_data['code'] == -1){
            $cover_media_data['message'] = '背景图：'.$cover_media_data['message'];
            return json($cover_media_data);
        }
        $request_data['coverImg'] = $cover_media_data['media_id'];// 通过 uploadfile 上传，填写 mediaID
        $request_data['startTime'] = strtotime($post_data['startTime']);// 开始时间
        $request_data['endTime'] = strtotime($post_data['endTime']);// 结束时间
        $request_data['anchorName'] = $post_data['anchorName'];// 主播昵称
        $request_data['anchorWechat'] = $post_data['anchorWechat'];// 主播微信号
        $request_data['subAnchorWechat'] = $post_data['subAnchorWechat'];// 主播副号微信号
//        $request_data['createrWechat'] = $post_data['createrWechat'];// 创建者微信号
        //获取分享图的 mediaid
        $share_img = getApiSrc($post_data['shareImg']);
        $share_media_data = $this->getMediaId($share_img, 800, 640);
        if($share_media_data['code'] == -1){
            $cover_media_data['message'] = '分享图：'.$cover_media_data['message'];
            return json($share_media_data);
        }
        $request_data['shareImg'] = $share_media_data['media_id'];// 通过 uploadfile 上传，填写 mediaID
        //获取 购物直播频道封面图的 mediaid
        $feeds_img = getApiSrc($post_data['feedsImg']);
        $feeds_media_data = $this->getMediaId($feeds_img, 800, 800);
        if($feeds_media_data['code'] == -1){
            $cover_media_data['message'] = '购物直播频道封面图：'.$cover_media_data['message'];
            return json($feeds_media_data);
        }
        $request_data['feedsImg'] = $feeds_media_data['media_id'];// 通过 uploadfile 上传，填写 mediaID
        $request_data['isFeedsPublic'] = $post_data['isFeedsPublic'];// 是否开启官方收录，1 开启，0 关闭
        $request_data['type'] = $post_data['type'];// 直播类型，1 推流 0 手机直播
        $request_data['closeLike'] = $post_data['closeLike'];// 是否关闭点赞 1：关闭
        $request_data['closeGoods'] = $post_data['closeGoods'];// 是否关闭商品货架，1：关闭
        $request_data['closeComment'] = $post_data['closeComment'];// 是否开启评论，1：关闭
        $request_data['closeReplay'] = $post_data['closeReplay'];// 是否关闭回放 1 关闭
        $request_data['closeShare'] = $post_data['closeShare'];// 是否关闭分享 1 关闭
        $request_data['closeKf'] = $post_data['closeKf'];// 是否关闭客服，1 关闭
        $request_data = json_encode($request_data);
        $wchat_oauth = new WchatOauth($this->website_id);
        $access_token = $wchat_oauth->getMpAccessToken($this->website_id);
        $request_url = 'https://api.weixin.qq.com/wxaapi/broadcast/room/create?access_token='.$access_token;
//        $header = ['Content-type:application/json'];
        $header = array("Content-type: application/json;charset='utf-8'", "Accept: application/json", "Cache-Control: no-cache", "Pragma: no-cache");
        $update_res = curlRequest($request_url, 'POST', $header, $request_data);
        $update_arr = json_decode($update_res, true);
        if($update_arr['errcode'] === 0){
            //将副号设为主播身份，副号才可以进入直播间
            $sub_be_anchor_url = 'https://api.weixin.qq.com/wxaapi/broadcast/role/addrole?access_token='.$access_token;
            $sub_be_anchor_data = [
                'username' => $post_data['subAnchorWechat'],
                'role' => 2
            ];
            curlRequest($sub_be_anchor_url, 'POST', $header, json_encode($sub_be_anchor_data));
            //将信息存入表中
            $condition['roomid'] = $update_arr['roomId'];
            $mplive_mdl = new MpliveModel();
            $is_mplive = $mplive_mdl->getInfo($condition);
            if(!$is_mplive){
                $insert_data['roomid'] = $update_arr['roomId'];
                $insert_data['cover_img'] = $cover_img;
                $insert_data['start_time'] = strtotime($post_data['startTime']);
                $insert_data['end_time'] = strtotime($post_data['endTime']);
                $insert_data['name'] = $post_data['name'];
                $insert_data['anchor_name'] = $post_data['anchorWechat'];
                $insert_data['share_img'] = $share_img;
                $insert_data['create_time'] = time();
                $insert_data['website_id'] = $this->website_id;
                $mplive_mdl->insert($insert_data);
            }
            return json(['code' => 1, 'message' =>'创建成功']);
        }elseif($update_arr['errcode'] === 300039){
            return json(['code' => 300039, 'message' =>$update_arr['errmsg'], 'qrcode_url' => $update_arr['qrcode_url']]);
        } else{
            if(!$update_arr['errmsg']){
                $update_arr['errmsg'] = '创建失败';
            }
            return json(['code' => -1, 'message' =>$update_arr['errmsg']]);
        }
//        var_dump($update_arr);
//        p($request_data);exit;
    }
    // 商品弹窗
    public function modalGoods() {
        $roomid = request()->get('roomid');
        $this->assign('roomid', $roomid);
        $this->assign('getMpLiveGoodsLabriry', __URL(addons_url_platform('mplive://Mplive/getMpLiveGoodsListForPick')));
        $this->fetch('template/'. $this->module . '/popupGoodsDialog');
    }

    /**
     * 导入直播间商品
     */
    public function uploadMpliveGoods()
    {
        $goods_str = request()->post('goods_str');
        $roomid = request()->post('roomid');
        $goods_str = htmlspecialchars_decode($goods_str);
        $goods_arr = json_decode($goods_str, true);
        //获取商品id
        $goods_ids = [];
        foreach($goods_arr['data'] as $k => $goods_info){
            $goods_ids[] = $goods_info['mp_goods_id'];
        }
        $data['ids'] = $goods_ids;
        $data['roomId'] = $roomid;
        $request_data = json_encode($data);
        $wchat_oauth = new WchatOauth($this->website_id);
        $access_token = $wchat_oauth->getMpAccessToken($this->website_id);
        $request_url = 'https://api.weixin.qq.com/wxaapi/broadcast/room/addgoods?access_token='.$access_token;
//        $header = ['Content-type:application/json'];
        $header = array("Content-type: application/json;charset='utf-8'", "Accept: application/json", "Cache-Control: no-cache", "Pragma: no-cache");
        $res = curlRequest($request_url, 'POST', $header, $request_data);
        $res = json_decode($res, true);
        if($res['errcode'] === 0){
            return json(['code' => 1, 'message' => '挑选成功']);
        }else{
            if(!$res['message']){
                $res['message'] = '挑选失败';
            }
            return json(['code' => -1, 'message' => $res['message']]);
        }
    }
    // 直播间挑选过的商品
    public function selectedGoods() {
        $roomid = request()->get('roomid');
        $this->assign('roomid', $roomid);
        $this->assign('getMpLiveSelectedGoods', __URL(addons_url_platform('mplive://Mplive/getMpLiveSelectedGoods')));
        $this->fetch('template/'. $this->module . '/selectedGoods');
    }

    public function getMpLiveSelectedGoods()
    {
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $roomid = request()->post('roomid', '');
        $mplive_goods = new MpliveGoodsModel();
//        if($goods_name){
//            $cond['goods_name'] = ['like', '%'.$goods_name.'%'];
//        }
        $cond['roomid'] = $roomid;
        $cond['website_id'] = $this->website_id;
        $order = 'mplive_goods_id desc';
        $goods_list = $mplive_goods->pageQuery($page_index, $page_size, $cond, $order, '*');
        return $goods_list;
    }

    /**
     * 获取直播间二维码
     */
    public function getRoomCode()
    {
        $room_id = request()->post('roomid', 0);
        $request_data['roomId'] = $room_id;
//        $request_data = json_encode($request_data);
        $wchat_oauth = new WchatOauth($this->website_id);
        $access_token = $wchat_oauth->getMpAccessToken($this->website_id);
//        $header = ['Content-type:application/json'];
        $header = array("Content-type: application/json;charset='utf-8'", "Cache-Control: no-cache", "Pragma: no-cache");
        $reqeust_str = http_build_query($request_data);
        $request_url = 'https://api.weixin.qq.com/wxaapi/broadcast/room/getsharedcode?access_token='.$access_token.'&'.$reqeust_str;
        $res = curlRequest($request_url, 'GET', $header, $request_data);
        $res = json_decode($res, true);
        if($res['errcode'] === 0){
            return json(['code' => 1, 'message' => '获取成功', 'data' => $res]);
        }else{
            if(!$res['message']){
                $res['message'] = '获取失败';
            }
            return json(['code' => -1, 'message' => $res['message']]);
        }
    }
}