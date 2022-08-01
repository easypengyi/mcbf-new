<?php
namespace addons\fullcut\controller;

use addons\fullcut\Fullcut as BaseFullCut;
use data\service\Goods as GoodsService;
use addons\fullcut\service\Fullcut as FullcutSer;
use data\model\VslGoodsCategoryModel as VslGoodsCategoryModel;
/**
 * 满减送控制器
 *
 * @author  www.vslai.com
 *
 */
class Fullcut extends BaseFullCut
{

    public function __construct(){
        parent::__construct();
    }


    public function fullCutList()
    {
        $config= new FullcutSer();
        $page_index = request()->post("page_index", 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $search_text = request()->post('search_text', '');
        $shop_id = $this->instance_id;
        $condition = array(
            'website_id' => $this->website_id,
            'mansong_name' => array(
                'like',
                '%' . $search_text . '%'
            ),
            'shop_id' => $shop_id
        );

        $list = $config->getPromotionMansongList($page_index, $page_size, $condition);
        return $list;
    }

    /*
     * 添加满减送活动
     *
     * @return \think\response\View
     */
    public function addFullCut()
    {
        //获取优惠券列表
        $mansong = new FullcutSer();
        $mansong_name = $_POST['mansong_name'];
        $level = $_POST['level'];
        $remark = $_POST['remark'];
        $status = $_POST['status'];
        $range = $_POST['range'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time']." 23:59:59";
        if(strtotime($start_time)>strtotime($end_time)){
            $json['code'] = -1;
            $json['message'] = "开始时间不能大于结束时间";
            return $json;
        }
        if(!empty($_REQUEST['shop_id'])){
            $shop_id = $_REQUEST['shop_id'];
        }else{
            $shop_id = $this->instance_id;
        }
        $type = $_POST['type'];
        $range_type = $_POST['range_type'];
        $rule = $_POST['rule'];
        $goods_id_array = $_POST['goods_id_array'];
        $category_extend_id = isset($_POST['category_extend_id']) ? $_POST['category_extend_id'] : '';
        //分类id 换取三级分类id 
        // 1级扩展分类
        $extend_category_id_1s = "";
        // 2级扩展分类
        $extend_category_id_2s = "";
        // 3级扩展分类
        $extend_category_id_3s = "";
        $extend_category_id = $category_extend_id;
        if (!empty($extend_category_id)) {
            $extend_category_id_str = explode(",", $extend_category_id);
            foreach ($extend_category_id_str as $extend_id) {
                $extend_category_list = $this->getGoodsCategoryId($extend_id);

                if ($extend_category_id_1s === "") {
                    $extend_category_id_1s = $extend_category_list[0];
                } else {
                    $extend_category_id_1s = $extend_category_id_1s . "," . $extend_category_list[0];
                }
                if ($extend_category_id_2s === "") {
                    $extend_category_id_2s = $extend_category_list[1];
                } else {
                    $extend_category_id_2s = $extend_category_id_2s . "," . $extend_category_list[1];
                }
                if ($extend_category_id_3s === "") {
                    $extend_category_id_3s = $extend_category_list[2];
                } else {
                    $extend_category_id_3s = $extend_category_id_3s . "," . $extend_category_list[2];
                }
            }
            unset($extend_id);
        }
        $res = $mansong->addPromotionMansong($mansong_name, $start_time, $end_time, $shop_id, $remark, $type, $range_type, $rule, $goods_id_array,$range,$status,$level,$category_extend_id,$extend_category_id_1s,$extend_category_id_2s,$extend_category_id_3s);
        return AjaxReturn($res);
    }
    
    /*
     * 编辑满减送活动
     */
    public function updateFullCut(){
        $mansong = new FullcutSer();
        $mansong_id = $_POST['mansong_id'];
        $mansong_name = $_POST['mansong_name'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time']." 23:59:59";
        $type = $_POST['type'];
        $remark = $_POST['remark'];
        $range_type = $_POST['range_type'];
        $rule = $_POST['rule'];
        $range = $_POST['range'];
        $status = $_POST['status'];
        $level = $_POST['level'];
        $goods_id_array = $_POST['goods_id_array'];
        if(strtotime($start_time)>strtotime($end_time)){
            $json['code'] = -1;
            $json['message'] = "开始时间不能大于结束时间";
            return $json;
        }
        $res = $mansong->updatePromotionMansong($mansong_id, $mansong_name, $start_time, $end_time, $remark, $type, $range_type, $rule, $goods_id_array,$range,$status,$level);
        if($res){
            return AjaxReturn($res);
        }
    }



    /**
     * 获取筛选后的商品
     *
     * @return unknown
     */
    public function getSerchGoodsList()
    {
        $page_index = request()->post("page_index", 1);
        $page_size = request()->post("page_size", PAGESIZE);
        $shop_range_type = request()->post('shop_range_type');
        $search_text = isset($_POST['search_text'])? $_POST['search_text'] : "";
        $condition['goods_name'] = array(
            "like",
            "%" . $search_text . "%"
        );
        if ($shop_range_type == 1){
            $condition['shop_id'] = $this->instance_id;
            $condition['website_id'] = $this->website_id;
        }
        if($_REQUEST['shop_id']!=''){
            $condition['shop_id'] = $_REQUEST['shop_id'];
        }
        if($_REQUEST['seleted_goods']){
            $condition['goods_id'] = ['in',$_REQUEST['seleted_goods']];
        }
        $goods_detail = new GoodsService();
        $result = $goods_detail->getSearchGoodsList($page_index, $page_size, $condition);
        return $result;
    }



    /**
     * 删除满减送活动
     *
     * @return unknown[]
     */
    public function delMansong()
    {
        $mansong_id = isset($_POST['mansong_id']) ? $_POST['mansong_id'] : '';
        if (empty($mansong_id)) {
            return false;
        }
        $fullcutSer = new FullcutSer();
        $res = $fullcutSer->delPromotionMansong($mansong_id);
        if($res){
            $this->addUserLog('删除满减送活动', $mansong_id);
        }
        return AjaxReturn($res);
    }

    /**
     * 关闭满减送活动
     *
     * @return unknown[]
     */
    public function closeMansong()
    {
        $mansong_id = isset($_POST['mansong_id']) ? $_POST['mansong_id'] : '';
        if (empty($mansong_id)) {
            return false;
        }
        $fullcutSer = new FullcutSer();
        $res = $fullcutSer->closePromotionMansong($mansong_id);
        if($res){
            $this->addUserLog('关闭满减送活动', $mansong_id);
        }
        return AjaxReturn($res);
    }

    public function setConfig()
    {
        $Server = new FullcutSer();
        $is_use = $_POST['is_use'] ?: 0;
        $result = $Server->setConfig($is_use);
        if($result){
            $this->addUserLog('保存满减送设置', $result);
        }
        setAddons('fullcut', $this->website_id, $this->instance_id);
        return AjaxReturn($result);

    }

    public function confirmOrderFullCut()
    {
        $post = request()->post('post_data/a', '');
        if (empty($post)) {
            return json(['code' => 1, 'message' => '空数据', 'data' => []]);
        }
        // 重新处理post的数据结构，将shop_id 和 sku_id作为数组的key
        $new_data = [];
        foreach ($post as $v) {
            $new_data[$v['shop_id']][$v['sku_id']] = $v;
        }
        $goods_man_song_model = new FullcutSer();
        $fullCutLists = $goods_man_song_model->getCartManSong($new_data);
        //var_dump($fullCutLists);
        $return_data = [];
        foreach ($fullCutLists as $shop_id => $full_cut_info) {
            $temp_mansong = [];
            $temp_mansong['man_song_id'] = $full_cut_info['full_cut']['man_song_id'];
            $temp_mansong['rule_id'] = $full_cut_info['full_cut']['rule_id'];
            $temp_mansong['man_song_name'] = $full_cut_info['full_cut']['man_song_name'];
            $temp_mansong['discount'] = $full_cut_info['full_cut']['discount'];
            $temp_mansong['price'] = $full_cut_info['full_cut']['price'];
            $temp_mansong['shop_id'] = $full_cut_info['full_cut']['shop_id'];
            $temp_mansong['use_shop_id'] = $shop_id;
            $temp_mansong['goods_limit'] = $full_cut_info['full_cut']['goods_limit'];
            $temp_mansong['free_shipping'] = $full_cut_info['shipping']['free_shipping'] ? true : false;

            $return_data[] = $temp_mansong;
        }
        return json(['code' => 1, 'message' => '获取成功', 'data' => $return_data]);
    }
    /**
     * 根据当前分类ID查询商品分类的三级分类ID
     *
     * @param unknown $category_id
     */
    private function getGoodsCategoryId($category_id)
    {
        // 获取分类层级
        $goods_category = new VslGoodsCategoryModel();
        $info = $goods_category->get($category_id);
        if ($info['level'] == 1) {
            return array(
                $category_id,
                0,
                0
            );
        }
        if ($info['level'] == 2) {
            // 获取父级
            return array(
                $info['pid'],
                $category_id,
                0
            );
        }
        if ($info['level'] == 3) {
            $info_parent = $goods_category->get($info['pid']);
            // 获取父级
            return array(
                $info_parent['pid'],
                $info['pid'],
                $category_id
            );
        }
    }
}
