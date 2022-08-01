<?php

namespace addons\discount\controller;

use addons\discount\Discount as baseDiscount;
use addons\discount\model\VslPromotionDiscountGoodsModel;
use data\service\Goods as GoodsService;
use data\model\VslGoodsCategoryModel as VslGoodsCategoryModel;
use data\service\ActiveList;
use addons\discount\server\Discount as discountSer;
use data\service\AddonsConfig as AddonsConfigService;

/**
 * 店铺设置控制器
 *
 * @author  www.vslai.com
 *
 */
class Discount extends baseDiscount
{

    public $instance_id;
    public $instance_name;
    public $discount_ser;

    public function __construct()
    {
        parent::__construct();
        $this->discount_ser = new discountSer();
    }

    public function discountList()
    {
        $page_index = request()->post("page_index", 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $search_text = isset($_POST['search_text']) ? $_POST['search_text'] : '';

        $condition = array(
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'discount_name' => array(
                'like',
                '%' . $search_text . '%'
            )
        );
        $list = $this->discount_ser->getPromotionDiscountList($page_index, $page_size, $condition);
        $list = objToArr($list);
        return $list;
    }


    /**
     * 修改限时折扣
     */
    public function editDiscount()
    {
        $discount_id = isset($_POST['discount_id']) ? $_POST['discount_id'] : '';
        $discount_name = isset($_POST['discount_name']) ? $_POST['discount_name'] : '';
        $start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
        $end_time = isset($_POST['end_time']) ? $_POST['end_time'] . " 23:59:59" : '';
        $level = isset($_POST['level']) ? $_POST['level'] : '1';
        $range = isset($_POST['range']) ? $_POST['range'] : '1';
        $status = isset($_POST['status']) ? $_POST['status'] : '0';
        $range_type = isset($_POST['range_type']) ? $_POST['range_type'] : '2';
        $discount_num = isset($_POST['discount']) ? $_POST['discount'] : '10';
        $remark = isset($_POST['remark']) ? $_POST['remark'] : '';
        $goods_id_array = isset($_POST['goods_id_array']) ? $_POST['goods_id_array'] : '';
        if (strtotime($start_time) > strtotime($end_time)) {
            $json['code'] = -1;
            $json['message'] = "开始时间不能大于结束时间";
            return json_encode($json);
        }

        //new
        $discount_type = isset($_POST['discount_type']) ? $_POST['discount_type'] : '1';
        $uniform_discount_type = isset($_POST['uniform_discount_type']) ? $_POST['uniform_discount_type'] : '0';
        $uniform_discount = isset($_POST['uniform_discount']) ? $_POST['uniform_discount'] : '';
        $integer_type = isset($_POST['integer_type']) ? $_POST['integer_type'] : '0';
        $uniform_price_type = isset($_POST['uniform_price_type']) ? $_POST['uniform_price_type'] : '0';
        $uniform_price = isset($_POST['uniform_price']) ? $_POST['uniform_price'] : '';
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
        $input['extend_category_id_1s'] = $extend_category_id_1s;
        $input['extend_category_id_2s'] = $extend_category_id_2s;
        $input['extend_category_id_3s'] = $extend_category_id_3s;
        $retval = $this->discount_ser->updatePromotionDiscount($discount_id, $discount_name, $start_time, $end_time, $remark, $goods_id_array, $level, $range, $status, $range_type, $discount_num, $discount_type, $uniform_discount_type, $uniform_discount, $integer_type, $uniform_price_type, $uniform_price, $category_extend_id, $extend_category_id_1s, $extend_category_id_2s, $extend_category_id_3s);
        return AjaxReturn($retval);
    }


    /**
     * 获取限时折扣详情
     */
    public function gettailPage()
    {
        $discount_id = request()->post('discount_id', 0);
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        if (empty($discount_id)) {
            $this->error("没有获取到抢购信息");
        }
        $detail = $this->discount_ser->getPromotionDiscountDetailPage($page_index, $page_size, $discount_id);
        return $detail;
    }

    /**
     * 删除限时折扣
     */
    public function delDiscount()
    {
        $discount_id = isset($_POST['discount_id']) ? $_POST['discount_id'] : '';
        if (empty($discount_id)) {
            $this->error("没有获取到抢购信息");
        }
        $res = $this->discount_ser->delPromotionDiscount($discount_id);
        if ($res) {
            $this->addUserLog('删除限时抢购', $discount_id);
        }
        return AjaxReturn($res);
    }


    public function closeDiscount()
    {

        $discount_id = isset($_POST['discount_id']) ? $_POST['discount_id'] : '';
        if (empty($discount_id)) {
            return AjaxReturn(0);
        }
        $res = $this->discount_ser->closePromotionDiscount($discount_id);
        if($res){
            $this->addUserLog('关闭限时抢购', $discount_id);
        }
        return AjaxReturn($res);
    }

    /*
     * 取消限时折扣促销状态、参加的此档活动的商品
     * * */

    public function canclePromotionStatus()
    {
        $goodsSer = new GoodsService();
        $discount_goods = new VslPromotionDiscountGoodsModel();
        $goods_id = request()->post('goods_id', 0);
        $discount_id = request()->post('discount_id', 0);
        $discount_cond['goods_id'] = $goods_id;
        $discount_cond['discount_id'] = $discount_id;
        $discount_goods->where($discount_cond)->delete();
        $condition['goods_id'] = $goods_id;
        $condition['promotion_type'] = 5;
        $condition['website_id'] = $this->website_id;
        $checkGoods = $goodsSer->getGoodsCount($condition);
        if (!$checkGoods) {
            return AjaxReturn(1);
        }
        $data['promotion_type'] = 0;
        $bool = $goodsSer->updateGoods($condition, $data);//商品表更新
        if (!$bool) {
            return AjaxReturn(0);
        }
        return AjaxReturn(1);
    }

    /*
     * 动态获取在当前限时折扣中的商品
     * * */

    public function getCurrDiscountGoodsId()
    {
        $discount_id = $_REQUEST['discount_id'] ? $_REQUEST['discount_id'] : '';
        $info = $this->discount_ser->getPromotionDiscountDetail($discount_id);
        $goods_id_array = [];
        if (!empty($info['goods_list'])) {
            foreach ($info['goods_list'] as $k => $v) {
                $goods_id_array[] = $v['goods_id'];
            }
        }
        return $goods_id_array;
    }

    public function getSerchGoodsList()
    {
        $page_index = request()->post("page_index", 1);
        $page_size = request()->post("page_size", PAGESIZE);
        $shop_range_type = request()->post('shop_range_type', '');
        $search_text = isset($_POST['search_text']) ? $_POST['search_text'] : "";
        $condition['goods_name'] = array(
            "like",
            "%" . $search_text . "%"
        );
        if ($shop_range_type == 1) {
            $condition['shop_id'] = $this->instance_id;
        } else if ($shop_range_type == 2) {
            
        } else {
            $condition['shop_id'] = $this->instance_id;
        }
        if ($_REQUEST['seleted_goods']) {
            $condition['goods_id'] = ['in', $_REQUEST['seleted_goods']];
        }
        $condition['website_id'] = $this->website_id;
        $condition['supplier_goods_id'] = ['=', 0];
        $condition['supplier_id'] = ['=', 0];
        $category_id_1 = request()->post('category_id_1', '');
        $category_id_2 = request()->post('category_id_2', '');
        $category_id_3 = request()->post('category_id_3', '');
        if ($category_id_3 != "") {
            $condition["category_id_3"] = $category_id_3;
        } elseif ($category_id_2 != "") {
            $condition["category_id_2"] = $category_id_2;
        } elseif ($category_id_1 != "") {
            $condition["category_id_1"] = $category_id_1;
        }
        $condition['state'] = 1;
        $goods_detail = new GoodsService();
        $start_time = request()->post('start_time', 0);
        $end_time = request()->post('end_time', 0);
        //所有商品信息
        $activeListServer = new ActiveList();
        $result = $goods_detail->getSearchGoodsList($page_index, $page_size, $condition);
        if ($result) {
            foreach ($result['data'] as $key => $value) {
                $goodsActiveLists = $activeListServer->goodsActiveLists($value['goods_id'], $start_time, $end_time);
                $result['data'][$key]['active_list'] = $goodsActiveLists;
            }
        }
        if (request()->post('discount_id')) {
            //折扣信息
            $info = $this->discount_ser->getPromotionDiscountDetail(request()->post('discount_id'));
            //将折扣信息组装到商品列表里
            foreach ($result['data'] as $key=>$value){
                foreach ($info['goods_list'] as $k=>$v) {
                   if($v['discount_type'] == 1){
                        $result['discount'] = $v['discount'];
                    }
                    if ($value['goods_id'] == $v['goods_id']) {
                        $result['data'][$key]['discount'] = $v['discount'];
                        $result['data'][$key]['discount_type'] = $v['discount_type'];
                    }
                }
            }
        }
        return $result;
    }

    public function getAllSelectedGoods()
    {
        $shop_range_type = request()->post('shop_range_type', '');
        $search_text = isset($_POST['search_text']) ? $_POST['search_text'] : "";
        $condition['goods_name'] = array(
            "like",
            "%" . $search_text . "%"
        );
        if ($shop_range_type == 1) {
            $condition['shop_id'] = $this->instance_id;
        } else if ($shop_range_type == 2) {
            
        } else {
            $condition['shop_id'] = $this->instance_id;
        }
        if ($_REQUEST['seleted_goods']) {
            $condition['goods_id'] = ['in', $_REQUEST['seleted_goods']];
        }
        $condition['website_id'] = $this->website_id;
        $goods_detail = new GoodsService();
        //所有商品信息
        $result = $goods_detail->getSearchGoodsList(1, 0, $condition);

        if (request()->post('discount_id')) {
            //折扣信息
            $info = $this->discount_ser->getPromotionDiscountDetail(request()->post('discount_id'));
            //将折扣信息组装到商品列表里
            foreach ($result['data'] as $key => $value) {
                foreach ($info['goods_list'] as $k => $v) {
                    // echo $value['goods_id']."==".$v['goods_id']."<br/>";
                    if ($value['goods_id'] == $v['goods_id']) {
//                        echo $v['goods_id']."----".$key."------".$v['discount']."<br/>";
                        $result['data'][$key]['discount'] = $v['discount'];
                        $result['data'][$key]['discount_type'] = $v['discount_type'];
                    }
                }
            }
        }
        return $result;
    }

    /**
     * 添加限时折扣
     *  //变更 edit for 2021/01/14 新增限时折扣，先加入活动列表 由列表统一变更活动进行状态 
     */
    public function addDiscount()
    {
        if (request()->isAjax()) {
            $discount_name = isset($_POST['discount_name']) ? $_POST['discount_name'] : '';
            $start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
            $end_time = $_POST['end_time'] . " 23:59:59";
            $level = isset($_POST['level']) ? $_POST['level'] : '1';
            if (getTimeTurnTimeStamp($start_time) > getTimeTurnTimeStamp($end_time)) {
                $json['code'] = -1;
                $json['message'] = "开始时间不能大于结束时间";
                ob_clean();
                print_r(json_encode($json));
                exit;
            }
            $range = isset($_POST['range']) ? $_POST['range'] : '1';
            $status = isset($_POST['status']) ? $_POST['status'] : '0';
            $range_type = isset($_POST['range_type']) ? $_POST['range_type'] : '2';
            $discount_num = isset($_POST['discount']) ? $_POST['discount'] : '10';
            $remark = isset($_POST['remark']) ? $_POST['remark'] : '';
            $goods_id_array = isset($_POST['goods_id_array']) ? $_POST['goods_id_array'] : '';
            //new
            $discount_type = isset($_POST['discount_type']) ? $_POST['discount_type'] : '1';
            $uniform_discount_type = isset($_POST['uniform_discount_type']) ? $_POST['uniform_discount_type'] : '0';
            $uniform_discount = isset($_POST['uniform_discount']) ? $_POST['uniform_discount'] : '';
            $integer_type = isset($_POST['integer_type']) ? $_POST['integer_type'] : '0';
            $uniform_price_type = isset($_POST['uniform_price_type']) ? $_POST['uniform_price_type'] : '0';
            $uniform_price = isset($_POST['uniform_price']) ? $_POST['uniform_price'] : '';
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
            $input['extend_category_id_1s'] = $extend_category_id_1s;
            $input['extend_category_id_2s'] = $extend_category_id_2s;
            $input['extend_category_id_3s'] = $extend_category_id_3s;
            $retval = $this->discount_ser->addPromotiondiscount($discount_name, $start_time, $end_time, $remark, $goods_id_array, $level, $range, $status, $range_type, $discount_num, $this->instance_id, $discount_type, $uniform_discount_type, $uniform_discount, $integer_type, $uniform_price_type, $uniform_price, $category_extend_id, $extend_category_id_1s, $extend_category_id_2s, $extend_category_id_3s);
            return AjaxReturn($retval);
        }
    }

    /**
     * 安装方法
     */
    public function install()
    {
        // TODO: Implement install() method.

        return true;
    }

    /**
     * 卸载方法
     */
    public function uninstall()
    {

        return true;
        // TODO: Implement uninstall() method.
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

    public function discountSet()
    {
        $is_use = $_POST['is_use'] ?: 0;
        $ConfigService = new AddonsConfigService();
        $discountSet = $ConfigService->getAddonsConfig("discount");
        if (!empty($discountSet)) {
            $res = $ConfigService->updateAddonsConfig('', "限时抢扣", $is_use, "discount");
        } else {
            $res = $ConfigService->addAddonsConfig('', '限时抢扣', $is_use, 'discount');
        }
        setAddons('discount', $this->website_id, $this->instance_id);
        return AjaxReturn($res);
    }

}
