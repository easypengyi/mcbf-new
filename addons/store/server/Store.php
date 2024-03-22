<?php

namespace addons\store\server;

use addons\bargain\service\Bargain;
use addons\channel\model\VslChannelGoodsSkuModel;
use addons\coupontype\server\Coupon;
use addons\discount\server\Discount;
use addons\fullcut\service\Fullcut;
use addons\groupshopping\server\GroupShopping as GroupShoppingServer;
use addons\membercard\server\Membercard as MembercardSer;
use addons\presell\service\Presell as PresellService;
use addons\receivegoodscode\server\ReceiveGoodsCode as ReceiveGoodsCodeSer;
use addons\seckill\server\Seckill as SeckillServer;
use addons\shop\model\VslShopModel;
use addons\store\model\VslStoreCommissionModel;
use addons\store\model\VslStoreCommissionRecordsModel;
use addons\store\model\VslStoreMessageModel;
use data\model\VslActivityOrderSkuRecordModel;
use data\model\VslBankModel;
use data\model\VslGoodsCategoryModel as VslGoodsCategoryModel;
use data\model\VslGoodsDiscountModel;
use data\model\VslGoodsSkuModel as VslGoodsSkuModel;
use data\model\VslGoodsSkuPictureModel;
use data\model\VslGoodsSpecModel as VslGoodsSpecModel;
use data\model\VslMemberBankAccountModel;
use data\model\VslMemberModel;
use data\model\VslOrderGoodsModel;
use data\model\VslStoreGoodsModel as VslStoreGoodsModel;
use data\model\VslStoreGoodsSkuModel as VslStoreGoodsSkuModel;
use data\service\AddonsConfig as AddonsConfigService;
use data\service\Album;
use data\service\BaseService;
use addons\store\model\VslStoreModel;
use addons\store\model\VslStoreJobsModel;
use addons\store\model\VslStoreAssistantModel;
use data\model\VslOrderModel;
use data\model\AlbumPictureModel;
use data\service\Goods;
use data\service\Order\Order as OrderBusiness;
use data\service\Address;
use addons\shop\service\Shop;
use data\service\Config as WebConfig;
use data\service\Order\Order as orderServer;
use data\service\Order\OrderGoods;
use data\service\Pay\AliPay;
use data\service\Pay\Joinpay;
use data\service\Pay\tlPay;
use data\service\Pay\WeiXinPay;
use data\service\Upload\AliOss;
use addons\store\model\VslStoreEvaluateModel;
use addons\store\model\VslStoreSetModel;
use data\service\User;
use think\Db;
use think\Exception;
use \think\Session as Session;
use data\model\VslStoreCartModel as VslStoreCartModel;
use data\service\Member;
use data\model\UserModel;
use data\service\Order;
use think\Cookie;
use addons\luckyspell\server\Luckyspell as luckySpellServer;

/**
 * O2O数据处理
 * Class Good
 * @package addons\store\server
 */
class Store extends BaseService
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取门店列表
     * @param int|string $page_index
     * @param int|string $page_size
     * @param array $condition
     *
     * @return array $list
     */
    public function storeList($page_index = 1, $page_size = 0, array $condition = [])
    {
        $storeMdl = new VslStoreModel();
        $list = $storeMdl->pageQuery($page_index, $page_size, $condition, 'create_time desc', '*');
        return $list;
    }

    /**
     * 前台获取门店列表
     * @param int|string $page_index
     * @param int|string $page_size
     * @param array $condition
     *
     * @return array $list
     */
    public function storeListForFront($page_index = 1, $page_size = 0, array $condition = [], array $place = [])
    {
        $storeMdl = new VslStoreModel();
        $storeGoodsModel = new VslStoreGoodsModel();
        $list = $storeMdl->pageQuery($page_index, $page_size, $condition, 'create_time desc', '*');
        if ($list['data']) {
            //重新组装数组
            $newList = [];
            $address = new Address();
            if (getAddons('shop', $this->website_id)) {
                $shop = new Shop();
            }
            foreach ($list['data'] as $key => $val) {
                $newList[$key]['distance'] = $this->sphere_distance(['lat' => $val['lat'], 'lng' => $val['lng']], $place);
                $newList[$key]['store_id'] = $val['store_id'];
                $newList[$key]['shop_id'] = $val['shop_id'];
                $newList[$key]['website_id'] = $val['website_id'];
                $newList[$key]['store_name'] = $val['store_name'];
                $newList[$key]['store_tel'] = $val['store_tel'];
                $newList[$key]['province_name'] = $address->getProvinceName($val['province_id']);
                $newList[$key]['city_name'] = $address->getCityName($val['city_id']);
                $newList[$key]['dictrict_name'] = $address->getDistrictName($val['district_id']);
                // 查询图片表
                $goods_img = new AlbumPictureModel();
                $order = "instr('," . $val['img_id_array'] . ",',CONCAT(',',pic_id,','))"; // 根据 in里边的id 排序
                $goods_img_list = $goods_img->getQuery([
                    'pic_id' => [
                        "in",
                        $val['img_id_array']
                    ]
                ], 'pic_id,pic_cover', $order);
                if (trim($val['img_id_array']) != "") {
                    $img_temp_array = array();
                    $img_array = explode(",", $val['img_id_array']);
                    foreach ($img_array as $ki => $vi) {
                        if (!empty($goods_img_list)) {
                            foreach ($goods_img_list as $t => $m) {
                                if ($m["pic_id"] == $vi) {
                                    $img_temp_array[] = $m;
                                }
                            }
                        }
                    }
                }
                if ($img_temp_array) {
                    foreach ($img_temp_array as $kk => $vv) {
                        $img_temp_array[$kk]['pic_cover'] = __IMG($vv['pic_cover']);
                    }
                }
                $newList[$key]["img_temp_array"] = $img_temp_array;
                $newList[$key]['store_img'] = __IMG($img_temp_array[0]['pic_cover']);
                $newList[$key]['address'] = $val['address'];
                $newList[$key]['lat'] = $val['lat'];
                $newList[$key]['lng'] = $val['lng'];
                $newList[$key]['status'] = $val['status'];
                $newList[$key]['start_time'] = $val['start_time'];
                $newList[$key]['finish_time'] = $val['finish_time'];
                $newList[$key]['shop_name'] = '';
                $newList[$key]['score'] = $val['score'];
                if ($shop) {
                    $newList[$key]['shop_name'] = $shop->getShopInfo($val['shop_id'], 'shop_name')['shop_name'];
                }

                //查询每个门店的商品
                $where = [
                    'website_id' => $val['website_id'],
                    'shop_id' => $val['shop_id'],
                    'store_id' => $val['store_id'],
                    'state' => 1
                ];
                $newList[$key]['goods'] = $storeGoodsModel->viewPageQuery($storeGoodsModel, '1', '4', $where, 'price desc');
                //查询每个门店的商品图片
                foreach ($newList[$key]['goods'] as $k1 => $v1) {
                    $arr = $goods_img->Query(['pic_id' => $v1['picture']], 'pic_cover')[0];
                    $newList[$key]['goods'][$k1]['goods_img'] = getApiSrc($arr);
                    unset($arr);
                }
                //统计每个门店下所有商品的总销量
                $newList[$key]['total_sales'] = $storeGoodsModel->getSum($where, 'sales');
            }
            array_multisort(array_column($newList, 'distance'), SORT_ASC, $newList);
            $list['store_list'] = $newList;
        } else {
            $list['store_list'] = [];
        }
        unset($list['data']);
        return $list;
    }

    /**
     * 获取店员列表
     * @param int|string $page_index
     * @param int|string $page_size
     * @param array $condition
     *
     * @return array $list
     */
    public function assistantList($page_index = 1, $page_size = 0, array $condition = [])
    {
        $storeMdl = new VslStoreAssistantModel();
        $list = $storeMdl->getAssistantViewList($page_index, $page_size, $condition, 'vsa.create_time desc');
        return $list;
    }

    /**
     * 获取岗位列表
     * @param int|string $page_index
     * @param int|string $page_size
     * @param array $condition
     *
     * @return array $list
     */
    public function jobsList($page_index = 1, $page_size = 0, array $condition = [])
    {
        $storeMdl = new VslStoreJobsModel();
        $list = $storeMdl->pageQuery($page_index, $page_size, $condition, 'create_time desc', '*');
        return $list;
    }

    /*
     * 删除门店
     */

    public function deleteStore($store_id)
    {
        if (!$store_id) {
            return -1006;
        }

        $storeMdl = new VslStoreModel();
        $store = $storeMdl->getInfo(['store_id' => $store_id, 'website_id' => $this->website_id, 'shop_id' => $this->instance_id]);
        if (!$store) {
            return -10014;
        }

        //有过订单的门店不能删除
        $order_model = new VslOrderModel();
        $condition = [
            'store_id|card_store_id' => $store_id
        ];
        $have_order = $order_model->getInfo($condition, '');
        if ($have_order) {
            return -1;
        }

        $retval = $storeMdl->delData(['store_id' => $store_id, 'website_id' => $this->website_id, 'shop_id' => $this->instance_id]);
        return $retval;
    }

    /*
     * 删除店员
     */

    public function deleteAssistant($assistant_id)
    {
        if (!$assistant_id) {
            return -1006;
        }

        $assistantModel = new VslStoreAssistantModel();
        $assistant = $assistantModel->getInfo(['assistant_id' => $assistant_id, 'website_id' => $this->website_id, 'shop_id' => $this->instance_id]);
        if (!$assistant) {
            return -10014;
        }
        $retval = $assistantModel->delData(['assistant_id' => $assistant_id, 'website_id' => $this->website_id, 'shop_id' => $this->instance_id]);
        return $retval;
    }

    /*
     * 删除岗位
     */

    public function deleteJobs($jobs_id)
    {
        if (!$jobs_id) {
            return -1006;
        }

        $jobsModel = new VslStoreJobsModel();
        $store = $jobsModel->getInfo(['jobs_id' => $jobs_id, 'website_id' => $this->website_id, 'shop_id' => $this->instance_id]);
        if (!$store) {
            return -10014;
        }
        $retval = $jobsModel->delData(['jobs_id' => $jobs_id, 'website_id' => $this->website_id, 'shop_id' => $this->instance_id]);
        return $retval;
    }

    /**
     * 添加店员
     * @param array $input
     * @return int
     */
    public function addStore(array $input)
    {
        $storeModel = new VslStoreModel();
        $storeModel->startTrans();
        try {
            $data = array(
                'shop_id' => $this->instance_id,
                'website_id' => $this->website_id,
                'store_name' => $input['store_name'],
                'status' => $input['status'],
                'img_id_array' => $input['img_id_array'],
                'store_tel' => $input['store_tel'],
                'province_id' => $input['province_id'],
                'city_id' => $input['city_id'],
                'district_id' => $input['district_id'],
                'address' => $input['address'],
                'lat' => trim($input['lat']),
                'lng' => trim($input['lng']),
                'finish_time' => $input['finish_time'],
                'start_time' => $input['start_time'],
                'update_time' => time(),
                'create_time'      => time(),
                'is_commission'    => $input['is_commission'],
                'cash_method'      => $input['cash_method'],
                'write_off_method' => $input['write_off_method'],
                'cash_reward'      => $input['cash_reward'],
                'write_off_reward' => $input['write_off_reward']
            );
            $res = $storeModel->save($data);
            $storeModel->commit();
            return $res;
        } catch (\Exception $e) {
            $storeModel->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 更新店员
     * @param array $input
     * @return int
     */
    public function updateStore(array $input)
    {
        $storeModel = new VslStoreModel();
        $storeModel->startTrans();
        $store_id = $input['store_id'];
        if (!$store_id) {
            return -1006;
        }
        try {
            $data = array(
                'shop_id' => $this->instance_id,
                'website_id' => $this->website_id,
                'store_name' => $input['store_name'],
                'status' => $input['status'],
                'img_id_array' => $input['img_id_array'],
                'store_tel' => $input['store_tel'],
                'province_id' => $input['province_id'],
                'city_id' => $input['city_id'],
                'district_id' => $input['district_id'],
                'address' => $input['address'],
                'lat' => trim($input['lat']),
                'lng' => trim($input['lng']),
                'finish_time' => $input['finish_time'],
                'start_time' => $input['start_time'],
                'update_time'      => time(),
                'is_commission'    => $input['is_commission'],
                'cash_method'      => $input['cash_method'],
                'write_off_method' => $input['write_off_method'],
                'cash_reward'      => $input['cash_reward'],
                'write_off_reward' => $input['write_off_reward']
            );
            $storeModel->save($data, ['store_id' => $store_id]);
            $storeModel->commit();
            return $store_id;
        } catch (\Exception $e) {
            $storeModel->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 添加店员
     * @param array $input
     * @return int
     */
    public function addAssistant(array $input)
    {
        $assistantModel = new VslStoreAssistantModel();
        $checkAssistant = $assistantModel->getInfo(['assistant_tel' => $input['assistant_tel'], 'website_id' => $this->website_id], 'assistant_id');
        if ($checkAssistant) {
            return -8005;
        }
        $assistantModel->startTrans();
        try {
            $data = array(
                'shop_id' => $this->instance_id,
                'website_id' => $this->website_id,
                'store_id' => $input['store_id'],
                'reservation_times' => $input['reservation_times'],
                'jobs_id' => $input['jobs_id'],
                'assistant_name' => $input['assistant_name'],
                'status' => $input['status'],
                'assistant_tel' => $input['assistant_tel'],
                'password' => md5($input['password']),
                'update_time' => time(),
                'create_time'      => time(),
                'is_commission'    => $input['is_commission'],
                'cash_method'      => $input['cash_method'],
                'write_off_method' => $input['write_off_method'],
                'cash_reward'      => $input['cash_reward'],
                'write_off_reward' => $input['write_off_reward']
            );
            $res = $assistantModel->save($data);
            $assistantModel->commit();
            return $res;
        } catch (\Exception $e) {
            $assistantModel->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 更新店员
     * @param array $input
     * @return int
     */
    public function updateAssistant(array $input)
    {
        $assistantModel = new VslStoreAssistantModel();
        $checkAssistant = $assistantModel->getInfo(['assistant_tel' => $input['assistant_tel'], 'website_id' => $this->website_id, 'assistant_id' => ['<>', $input['assistant_id']]], 'assistant_id');
        if ($checkAssistant) {
            return -8005;
        }
        $assistantModel->startTrans();
        $assistant_id = $input['assistant_id'];
        if (!$assistant_id) {
            return -1006;
        }
        try {
            $data = array(
                'shop_id' => $this->instance_id,
                'website_id' => $this->website_id,
                'store_id' => $input['store_id'],
                'reservation_times' => $input['reservation_times'],
                'jobs_id' => $input['jobs_id'],
                'assistant_name' => $input['assistant_name'],
                'status' => $input['status'],
                'assistant_tel' => $input['assistant_tel'],
                //'password' => md5($input['password']),
                'update_time'      => time(),
                'is_commission'    => $input['is_commission'],
                'cash_method'      => $input['cash_method'],
                'write_off_method' => $input['write_off_method'],
                'cash_reward'      => $input['cash_reward'],
                'write_off_reward' => $input['write_off_reward']
            );
            $assistantModel->save($data, ['assistant_id' => $assistant_id]);
            $assistantModel->commit();
            return 1;
        } catch (\Exception $e) {
            $assistantModel->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 添加岗位
     * @param array $input
     * @return int
     */
    public function addJobs(array $input)
    {
        $jobsModel = new VslStoreJobsModel();
        $jobsModel->startTrans();
        try {
            $data = array(
                'shop_id' => $this->instance_id,
                'website_id' => $this->website_id,
                'jobs_name' => $input['jobs_name'],
                'module_id_array' => $input['module_id_array'],
                'update_time' => time(),
                'create_time' => time()
            );
            $res = $jobsModel->save($data);
            $jobsModel->commit();
            return $res;
        } catch (\Exception $e) {
            $jobsModel->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 编辑岗位
     * @param array $input
     * @return int
     */
    public function updateJobs(array $input)
    {
        $jobsModel = new VslStoreJobsModel();
        $jobs_id = $input['jobs_id'];
        if (!$jobs_id) {
            return -1006;
        }
        $jobsModel->startTrans();
        try {
            $data = array(
                'shop_id' => $this->instance_id,
                'website_id' => $this->website_id,
                'jobs_name' => $input['jobs_name'],
                'module_id_array' => $input['module_id_array'],
                'update_time' => time(),
                'create_time' => time()
            );
            $jobsModel->save($data, ['jobs_id' => $jobs_id]);
            $jobsModel->commit();
            return $jobs_id;
        } catch (\Exception $e) {
            $jobsModel->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 获取门店详情
     * @param int $store_id
     * @return array $info
     */
    public function storeDetail($store_id)
    {
        if (!$store_id) {
            return false;
        }
        $storeModel = new VslStoreModel();
        $info = $storeModel->get($store_id);
        // 查询图片表
        $goods_img = new AlbumPictureModel();
        $order = "instr('," . $info['img_id_array'] . ",',CONCAT(',',pic_id,','))"; // 根据 in里边的id 排序
        $goods_img_list = $goods_img->getQuery([
            'pic_id' => [
                "in",
                $info['img_id_array']
            ]
        ], 'pic_id,pic_cover', $order);
        if (trim($info['img_id_array']) != "") {
            $img_temp_array = array();
            $img_array = explode(",", $info['img_id_array']);
            foreach ($img_array as $ki => $vi) {
                if (!empty($goods_img_list)) {
                    foreach ($goods_img_list as $t => $m) {
                        if ($m["pic_id"] == $vi) {
                            $img_temp_array[] = $m;
                        }
                    }
                }
            }
        }
        $info["img_temp_array"] = $img_temp_array;
        $address = new Address();
        $address_name['province_name'] = $address->getProvinceName($info['province_id']);
        $address_name['city_name'] = $address->getCityName($info['city_id']);
        $address_name['dictrict_name'] = $address->getDistrictName($info['district_id']);
        $info["detailed_address"] = $address_name['province_name'] . $address_name['city_name'] . $address_name['dictrict_name'] . $info['address'];
        $shop_name = $this->mall_name;
        if (getAddons('shop', $this->website_id)) {
            $shop = new Shop();
            $shop_info = $shop->getShopInfo($info['shop_id'], 'shop_name');
            $shop_name = $shop_info['shop_name'] ? : $this->mall_name;
        }
        $info['shop_name'] = $shop_name;
        return $info;
    }

    /**
     * 获取店员详情
     * @param int $assistant_id
     * @return array $info
     */
    public function assistantDetail($assistant_id, $store_id = 0)
    {
        if (!$assistant_id) {
            return [];
        }
        $assistantModel = new VslStoreAssistantModel();
        $info = $assistantModel->getInfo(['assistant_id' => $assistant_id],
            'assistant_id,store_id,jobs_id,shop_id,website_id,assistant_name,assistant_tel,status,assistant_headimg,is_commission,cash_method,cash_reward,write_off_method,write_off_reward,reservation_times');
        $info['store_info'] = $this->storeDetail($store_id);
        $info['jobs_info'] = $this->jobDetail($info['jobs_id']);
        $apl_commission = 0;//提现权限
        if ($info['jobs_info']['module_id_array']) {
            $act_rules = explode(',', $info['jobs_info']['module_id_array']);
            if (in_array(12, $act_rules)) {
                $apl_commission = 1;
            }
        }
        $info['apl_commission'] = $apl_commission;
        $info['commission'] = 0; //可提现
        $info['total_commission'] = 0; //累计
        $info['freezing_commission'] = 0; //冻结
        // 或者基础设置 查看是店员或门店奖金模式
        $store_set = $this->getStoreSetTask($info['website_id'], $info['shop_id']);
        if (in_array($store_set['settlement_type'], [1, 2])) {
            $account_id = $store_set['settlement_type'] == 1 ? $info['assistant_id'] : $info['store_id'];
            $account_type = $store_set['settlement_type'];
            $storeCommissionModel = new VslStoreCommissionModel();
            $commission_info = $storeCommissionModel->getInfo(['account_type' => $account_type, 'account_id' => $account_id, 'website_id' => $info['website_id']]);
            if ($commission_info) {
                $info['commission'] = floatval($commission_info['commission']); //可提现
                $info['total_commission'] = round($commission_info['commission'] + $commission_info['freezing_commission'] + $commission_info['withdrawals'], 2); //累计
                $info['freezing_commission'] = floatval($commission_info['freezing_commission']); //冻结
            }
        }
        //获取奖金信息
        $model_list = $this->getModule($info['jobs_info']['module_id_array']);
        foreach ($model_list as $key=> $m_item){
            if($m_item['module_id'] == 4){
                $model_list[] = [
                    "module_id"=> "11",
                    "module_name"=> "预约订单"
                ];
            }
        }
        $info['jobs_info']['operation'] = $model_list;
        $shop_name = $this->mall_name;
        if (getAddons('shop', $this->website_id)) {
            $shop = new Shop();
            $shop_info = $shop->getShopInfo($info['shop_id'], 'shop_name');
            $shop_name = $shop_info['shop_name'] ? : $this->mall_name;
        }
        $info['shop_name'] = $shop_name;

        //后台配置的库存方式
        $info['stock_type'] = (int)$this->getStoreSet($info['shop_id'])['stock_type'];

        return $info;
    }

    /**
     * 获取店员详情
     * @param array $condition
     * @return array $info
     */
    public function getAssistantInfo($condition = array())
    {
        if (!$condition) {
            return [];
        }
        $assistantModel = new VslStoreAssistantModel();
        $info = $assistantModel->getInfo($condition, 'assistant_id,store_id,jobs_id,shop_id,website_id,assistant_name,assistant_tel,status,assistant_headimg,wx_openid');
        return $info;
    }

    /**
     * 获取岗位详情
     * @param int $jobs_id
     * @return array $info
     */
    public function jobDetail($jobs_id)
    {
        if (!$jobs_id) {
            return [];
        }
        $assistantModel = new VslStoreJobsModel();
        $info = $assistantModel->get($jobs_id);
        return $info;
    }

    /*
     * 根据经纬度计算两点之间的距离
     */

    public function sphere_distance($placeA = array(), $placeB = array(), $radius = 6378.135)
    {
        $rad = doubleval(M_PI / 180.0);
        if (!$placeB) {
            $placeB = $this->getCurrentLat();
        }
        $lat1 = doubleval($placeA['lat']) * $rad;
        $lon1 = doubleval($placeA['lng']) * $rad;
        $lat2 = doubleval($placeB['lat']) * $rad;
        $lon2 = doubleval($placeB['lng']) * $rad;
        $theta = $lon2 - $lon1;
        $dist = acos(sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos($theta));
        if ($dist < 0) {
            $dist += M_PI;
        }
        // 单位为 千米
        return $dist = round($dist * $radius, 2);
    }

    /*
     * 根据ip获取当前经纬度
     */

    public function getCurrentLat()
    {
        $getIp = $_SERVER["REMOTE_ADDR"];
        $content = file_get_contents("http://api.map.baidu.com/location/ip?ak=SYxeNlfDv7XwfdsytsmgxEoCCACxCKTI");
        $json = json_decode($content);
        $data = '';
        $data['lng'] = $json->{'content'}->{'point'}->{'x'}; //按层级关系提取经度数据
        $data['lat'] = $json->{'content'}->{'point'}->{'y'}; //按层级关系提取纬度数据
        $data['address'] = $json->{'content'}->{'address'};
        return $data;
    }

    /**
     * 生成核销码
     *
     *
     * @return string $billno
     */
    public function createVerificationCode()
    {
        $billno = 'A' . mt_rand(100000000, 1000000000) . mt_rand(0, 9);
        while (1) {
            $order_model = new VslOrderModel();
            $count = $order_model->getCount(['order_no' => $billno]);
            if ($count <= 0) {
                break;
            }
            $billno = 'A' . mt_rand(100000000, 1000000000) . mt_rand(0, 9);
        }
        return $billno;
    }

    /**
     * 订单提货(non-PHPdoc)
     *
     */
    public function pickupOrder($order_id, $assistantId)
    {
        $checkOrder = $this->checkOrderCanPick($order_id);//检查订单是否可以提货
        if ($checkOrder < 0) {
            return $checkOrder;
        }
        $order = new OrderBusiness();
        $retval = $order->pickupOrder($order_id, $assistantId);
        if ($retval) {
            $this->deleteVerCode($order_id);
        }
        if ($retval == 1) {
            //提货成功 计算业绩
            $this->calculateStoreCommission($order_id, $this->website_id, 2);
        }
        return $retval;
    }

    /*
     * 核销订单核销二维码保存
     */

    public function orderVerCodeSet($verification_qrcode = '', $order_id = 0)
    {
        if (!$verification_qrcode || !$order_id) {
            return false;
        }
        $data = array(
            'verification_qrcode' => $verification_qrcode
        );
        $orderModel = new VslOrderModel();
        $result = $orderModel->save($data, [
            'order_id' => $order_id
        ]);
        return $result;
    }

    /*
     * 查询店铺是否拥有门店,作为是否o2o的依据
     */

    public function checkHasStoreForShop($shop_id = 0)
    {
        $storeModel = new VslStoreModel();
        $count = $storeModel->getCount(['shop_id' => $shop_id, 'website_id' => $this->website_id]);
        if (!$count) {
            return 0;
        }
        return 1;
    }

    /*
     * 核销完成删除门店订单核销码
     */

    public function deleteVerCode($order_id = 0)
    {
        if (!$order_id) {
            return false;
        }
        $orderModel = new VslOrderModel();
        $order = $orderModel->getInfo(['order_id' => $order_id], 'verification_qrcode');
        if (!$order['verification_qrcode']) {
            return false;
        }
        $qrcode = $order['verification_qrcode'];
        if (!strstr($qrcode, 'http')) {
            @unlink($qrcode);
        } elseif (@fopen($qrcode, 'r')) {
            $config = new WebConfig();
            $upload_type = $config->getConfigMaster(0, 'UPLOAD_TYPE', 0, 1);
            if ($upload_type == 2) {
                $alioss = new AliOss();
                $data = $alioss->deleteAliOss($qrcode);
                return $data;
            } else {
                return false;
            }
        }
        return true;
    }

    /*
     * 获取店员操作台权限
     */

    public function getModule($module_id_array = '')
    {
        if (!$module_id_array) {
            return [];
        }
        $module = explode(',', $module_id_array);
        $newList = [];
        foreach ($module as $key => $val) {
            if (!$val || $val == 12) {
                continue;
            }
            $newList[$key]['module_id'] = $val;
            $newList[$key]['module_name'] = $this->getModuleName($val);
        }
        return $newList;
    }

    /*
     * 获取操作台权限名称
     */

    public function getModuleName($module_id = 0)
    {
        if (!$module_id) {
            return '';
        }
        $module_name_arr = [
            1 => '扫码核销',
            2 => '卡券核销',
            3 => '礼品核销',
            4 => '门店订单',
            5 => '销售统计',
            6 => '店员管理',
            7 => '商品管理',
            8 => '售后订单',
            9 => '核销记录',
            10 => '门店收银',
        ];
        return $module_name_arr[$module_id];
    }

    public function getAssistantId($user_token = '')
    {
        $user_token = $user_token ?: $_SERVER['HTTP_USER_TOKEN'];
        $uid = Session::get($user_token);
        $base = new BaseService();
        $model = $base->getRequestModel();
        if ($uid == Session::get($model . 'assistant_id') && !is_null($uid)) {
            return $uid;
        } else {
            $assistantModel = new VslStoreAssistantModel();
            $user_info = $assistantModel::get(['user_token' => $user_token]);
            if ($user_info && $user_info['status'] == 1) {
                Session::set($model . 'assistant_id', $user_info['assistant_id']);
                Session::set(md5($user_info['assistant_id']), $user_info['assistant_id']);
                Session::set($model . 'instance_id', $user_info['shop_id']);
                Session::set($model . 'website_id', $user_info['website_id']);
                Session::set($model . 'store_id', $user_info['store_id']);
                $assistantModel->save(['login_num' => $user_info['login_num'] + 1], ['user_token' => $user_token]);
                return $user_info['assistant_id'];
            }
            return false;
        }
    }

    /**
     * 店员登录
     *
     * @param unknown $user_name
     * @param unknown $password
     */
    public function login($assistant_tel, $password = '')
    {
        $this->Logout();
        $condition = [
            'assistant_tel' => $assistant_tel,
            'website_id' => $this->website_id,
        ];
        if ($password) {
            $condition['password'] = md5($password);
        }
        $assistantModel = new VslStoreAssistantModel();
        $assistantInfo = $assistantModel->getInfo($condition, $field = 'assistant_id,status,assistant_name,assistant_headimg,shop_id,website_id,store_id');
        if (!empty($assistantInfo)) {
            $is_use = $this->getStoreSet()['is_use'];
            if(empty($is_use)) {
                return -1099;
            }
            if ($assistantInfo['status'] == 0) {
                return USER_LOCK;
            } else {
                $this->initLoginInfo($assistantInfo);
                //登录成功后增加用户的登录次数
                $set_inc_condition['assistant_id'] = $assistantInfo['assistant_id'];
                $assistantModel->save(['login_num' => $assistantInfo['login_num'] + 1, 'user_token' => md5($assistantInfo['assistant_id'])], $set_inc_condition);
                return $assistantInfo;
            }
        } else {
            return USER_ERROR;
        }
    }

    /**
     * 店员user_token登录
     *
     * @param unknown $user_name
     * @param unknown $password
     */
    public function loginByUserToken($user_token)
    {
        $this->Logout();
        $condition = [
            'user_token' => $user_token,
            'website_id' => $this->website_id,
        ];
        $assistantModel = new VslStoreAssistantModel();
        $assistantInfo = $assistantModel->getInfo($condition, $field = 'assistant_id,status,assistant_name,assistant_headimg,shop_id,website_id,store_id');
        if (!empty($assistantInfo)) {
            if ($assistantInfo['status'] == 0) {
                return USER_LOCK;
            } else {
                $this->initLoginInfo($assistantInfo);
                //登录成功后增加用户的登录次数
                $set_inc_condition['assistant_id'] = $assistantInfo['assistant_id'];
                $assistantModel->save(['login_num' => $assistantInfo['login_num'] + 1, 'user_token' => md5($assistantInfo['assistant_id'])], $set_inc_condition);
                return $assistantInfo;
            }
        } else {
            return USER_NBUND;
        }
    }

    /**
     * 店员user_token登录
     *
     * @param unknown $user_name
     * @param unknown $password
     */
    public function loginByOpenid($open_id)
    {
        $this->Logout();
        $condition = [
            'wx_openid' => $open_id,
            'website_id' => $this->website_id,
        ];
        $assistantModel = new VslStoreAssistantModel();
        $assistantInfo = $assistantModel->getInfo($condition, $field = 'assistant_id,status,assistant_name,assistant_headimg,shop_id,website_id,store_id');
        if (!empty($assistantInfo)) {
            if ($assistantInfo['status'] == 0) {
                return USER_LOCK;
            } else {
                $this->initLoginInfo($assistantInfo);
                //登录成功后增加用户的登录次数
                $set_inc_condition['assistant_id'] = $assistantInfo['assistant_id'];
                $assistantModel->save(['login_num' => $assistantInfo['login_num'] + 1, 'user_token' => md5($assistantInfo['assistant_id'])], $set_inc_condition);
                return $assistantInfo;
            }
        } else {
            return USER_NBUND;
        }
    }

    /**
     * 店员登录之后初始化数据
     * @param unknown $user_info
     */
    public function initLoginInfo($user_info)
    {
        $base = new BaseService();
        $model = $base->getRequestModel();
        Session::set($model . 'assistant_id', $user_info['assistant_id']);
        Session::set(md5($user_info['assistant_id']), $user_info['assistant_id']);
        Session::set($model . 'instance_id', $user_info['shop_id']);
        Session::set($model . 'website_id', $user_info['website_id']);
        return true;
    }

    /*
     * 更新店员部分内容
     */

    public function updateAssistantFiled(array $data, $condition, $only_update_one_row = true)
    {
        $assistantModel = new VslStoreAssistantModel();
        //只允许修改修改一条记录
        if ($only_update_one_row && ($assistantModel->getCount($condition) != 1)) {
            return 0;
        }
        return $assistantModel->save($data, $condition);
    }

    /**
     * 店员退出
     */
    public function Logout()
    {
        Session::destroy();
    }

    /**
     * 门店评价-添加
     */
    public function addStoreEvaluate($data)
    {
        $storeEvaluateModel = new VslStoreEvaluateModel();
        $res = $storeEvaluateModel->save($data);
        $storeModel = new VslStoreModel();
        $storeEvaluate = $this->getStoreEvaluate($data['store_id']);
        $storeData['score'] = number_format(($storeEvaluate['store_service'] * $storeEvaluate['count'] + $data['store_service']) / ($storeEvaluate['count'] + 1), 1);
        $storeModel->save($storeData, ['store_id' => $data['store_id'], 'shop_id' => $data['shop_id'], 'website_id' => $data['website_id']]);
        return $res;
    }

    /*
     * 获取门店评价
     */

    public function getStoreEvaluate($store_id = 0)
    {
        $storeEvaluateModel = new VslStoreEvaluateModel();
        $count = $storeEvaluateModel->getCount(['store_id' => $store_id, 'website_id' => $this->website_id]);
        $evaluateData = ['store_service' => 0];
        if (!$count) {
            return $evaluateData;
        }
        $evaluateData['count'] = $count;
        $evaluateData['store_service'] = number_format($storeEvaluateModel->getSum(['store_id' => $store_id, 'website_id' => $this->website_id], 'store_service') / $count, 1);
        return $evaluateData;
    }

    /*
     * 根据店员id获取所能管理的门店列表
     */

    public function storeListByAssistantId($assistant_id = 0, $page_index = 1, $page_size = 0)
    {
        if (!$assistant_id) {
            return false;
        }
        $assistantModel = new VslStoreAssistantModel();

        $store_ids = $assistantModel->getInfo(['assistant_id' => $assistant_id], 'store_id');
        if (!$store_ids) {
            return false;
        }
        $storeList = $this->storeList($page_index, $page_size, ['store_id' => ['in', $store_ids['store_id']]]);
        foreach ($storeList['data'] as $key => $val) {
            // 查询图片表
            $goods_img = new AlbumPictureModel();
            $order = "instr('," . $val['img_id_array'] . ",',CONCAT(',',pic_id,','))"; // 根据 in里边的id 排序
            $goods_img_list = $goods_img->getQuery([
                'pic_id' => [
                    "in",
                    $val['img_id_array']
                ]
            ], 'pic_id,pic_cover', $order);
            if (trim($val['img_id_array']) != "") {
                $img_temp_array = array();
                $img_array = explode(",", $val['img_id_array']);
                foreach ($img_array as $ki => $vi) {
                    if (!empty($goods_img_list)) {
                        foreach ($goods_img_list as $t => $m) {
                            if ($m["pic_id"] == $vi) {
                                $img_temp_array[] = $m;
                            }
                        }
                    }
                }
            }
            if ($img_temp_array) {
                foreach ($img_temp_array as $kk => $vv) {
                    $img_temp_array[$kk]['pic_cover'] = __IMG($vv['pic_cover']);
                }
            }
            $storeList['data'][$key]['store_img'] = __IMG($img_temp_array[0]['pic_cover']);
            $storeList['data'][$key]['shop_name'] = $this->mall_name;
            if (getAddons('shop', $this->website_id)) {
                $shop = new Shop();
                $shop_info = $shop->getShopInfo($val['shop_id'], 'shop_name');
                $storeList['data'][$key]['shop_name'] = $shop_info['shop_name'] ?: $this->mall_name;

            }
        }
        return $storeList;
    }

    /*
     * 检查选择的门店是否属于当前店员
     */

    public function checkStore($store_id = 0, $assistant_id = 0)
    {
        if (!$store_id || !$assistant_id) {
            return false;
        }
        $assistantModel = new VslStoreAssistantModel();
        $assInfo = $assistantModel->getInfo(['assistant_id' => $assistant_id], 'store_id');
        $store_ids = explode(',', $assInfo['store_id']);
        if (!in_array($store_id, $store_ids)) {
            return false;
        }
        return true;
    }

    /*
     * 启用店员
     */

    public function enableAssistant($assistant_id = 0)
    {
        if (!$assistant_id) {
            return LACK_OF_PARAMETER;
        }
        $assistantModel = new VslStoreAssistantModel();
        $assInfo = $assistantModel->getInfo(['assistant_id' => $assistant_id], 'assistant_tel');
        if (!$assInfo) {
            return 0;
        }
        $checkAss = $assistantModel->getInfo(['assistant_tel' => $assInfo['assistant_tel'], 'assistant_id' => ['<>', $assistant_id], 'website_id' => $this->website_id, 'status' => 1], 'assistant_id');
        if ($checkAss) {
            return ASSIS_UNOPEN;
        }
        $result = $assistantModel->save(['status' => 1, 'update_time' => time()], ['assistant_id' => $assistant_id]);
        if (!$result) {
            return 0;
        }
        return $result;
    }

    /*
     * 禁用店员
     */

    public function unableAssistant($assistant_id = 0)
    {
        if (!$assistant_id) {
            return LACK_OF_PARAMETER;
        }
        $assistantModel = new VslStoreAssistantModel();
        $assInfo = $assistantModel->getInfo(['assistant_id' => $assistant_id], 'assistant_tel');
        if (!$assInfo) {
            return 0;
        }
        $result = $assistantModel->save(['status' => 0, 'update_time' => time()], ['assistant_id' => $assistant_id]);
        if (!$result) {
            return 0;
        }
        return $result;
    }

    /*
     * 微信第三方登录(non-PHPdoc)
     * @see \data\api\IMember::wchatLogin()
     */
    public function wchatLogin($openid)
    {
        $this->Logout();
        $condition = array(
            'wx_openid' => $openid,
            'website_id' => $this->website_id
        );
        $assistantModel = new VslStoreAssistantModel();
        $user_info = $assistantModel->getInfo($condition, $field = 'assistant_id,assistant_name,shop_id,website_id,status');
        if (!empty($user_info)) {
            if ($user_info['status'] == 0) {
                return USER_LOCK;
            } else {
                $this->initLoginInfo($user_info);
                return $user_info['assistant_id'];
            }
        } else {
            return USER_NBUND;
        }
        // TODO Auto-generated method stub
    }

    /*
     * 获取门店配置
     */
    public function getStoreSet($shop_id = 0)
    {
        if (!$shop_id) {
            $shop_id = $this->instance_id;
        }
        $storeSetModel = new VslStoreSetModel();
        $info = $storeSetModel->getInfo([
            'shop_id' => $shop_id,
            'website_id' => $this->website_id
        ], '*');
       
        $info['value'] = json_decode(str_replace("&quot;", "\"", $info['value']), true);
        $storeSet = $info['value'];
        return $storeSet;
    }

    /*
     * 设置门店配置
     */
    public function storeSet($data = array())
    {
        $storeSetModel = new VslStoreSetModel();
        if (is_array($data)) {
            $value = json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        $info = $storeSetModel->getInfo([
            'shop_id' => $this->instance_id,
            'website_id' => $this->website_id
        ], 'id');
        $saveData = array(
            'value' => $value
        );
        if ($info) {
            $saveData['modify_time'] = time();
            $result = $storeSetModel->save($saveData, ['id' => $info['id']]);
        } else {
            $saveData['create_time'] = time();
            $saveData['shop_id'] = $this->instance_id;
            $saveData['website_id'] = $this->website_id;
            $result = $storeSetModel->save($saveData);
        }
        return $result;
    }

    /*
     * 检查订单是否可以提货
     */
    public function checkOrderCanPick($order_id = 0)
    {
        if (!$order_id) {
            return -1;
        }
        $orderModel = new VslOrderModel();
        $orderInfo = $orderModel->getInfo(['order_id' => $order_id]);
        if (!$orderInfo) {
            return -10005;
        }
        if ($orderInfo['order_type'] == 5 && getAddons('groupshopping', $orderInfo['website_id'], $orderInfo['shop_id'])) {
            if (!$orderInfo['group_record_id'] || !$orderInfo['group_id']) {
                return -10005;
            }
            $groupShopping = new \addons\groupshopping\server\GroupShopping();
            $groupRecordDetail = $groupShopping->groupRecordDetail($orderInfo['group_record_id']);
            if ($groupRecordDetail['status'] != 1) {
                return -10018;
            }
        }
        return 1;
    }

    /*
     * 门店首页
     */
    public function storeIndex($condition, $place)
    {
        //查询对应的门店信息
        $storeModel = new VslStoreModel();
        $list = $storeModel->getQuery($condition, '*', '');
        //关联相册表，查询门店图片
        $albumPictureModel = new AlbumPictureModel();
        foreach ($list as $k => $v) {
            $v['img_id_array'] = explode(',', $v['img_id_array']);
            foreach ($v['img_id_array'] as $key => $val) {
                $data = [
                    'pic_id' => $val
                ];
                $arr[] = getApiSrc($albumPictureModel->Query($data, 'pic_cover')[0]);
            }
            $list[$k]['store_img'] = $arr;
            unset($arr);
        }
        //查询地理位置
        $address = new Address();
        $list[0]['distance'] = $this->sphere_distance(['lat' => $list[0]['lat'], 'lng' => $list[0]['lng']], $place);
        $list[0]['province_name'] = $address->getProvinceName($list[0]['province_id']);
        $list[0]['city_name'] = $address->getCityName($list[0]['city_id']);
        $list[0]['district_name'] = $address->getDistrictName($list[0]['district_id']);

        //获取店铺名称
        $shop = new Shop();
        $list[0]['shop_name'] = $shop->getShopInfo($list[0]['shop_id'], 'shop_name')['shop_name'];

        return $list;
    }

    /*
     * 门店首页返回此门店下的所有商品的一级分类
     */
    public function getStoreGoodsCategoryList($condition)
    {
        if ($condition['store_id']) {
            //查询门店商品表获取一级分类id
            $storeGoodsModel = new VslStoreGoodsModel();
            $goodsCategoryIds = $storeGoodsModel->Query($condition, 'category_id_1');
        } else {
            //查询门店所属店铺的一级分类id
            $goodsSer = new Goods();
            $goodsCategoryIds = $goodsSer->getGoodsListByCondition($condition, 'category_id_1');
            
            if($goodsCategoryIds){
                
                $goodsCategoryIds = array_column(objToArr($goodsCategoryIds), 'category_id_1');
               
            }
        }
        
        $goodsCategoryIds = array_unique($goodsCategoryIds);
       
        //查询一级分类名称
        $goodsCategoryModel = new VslGoodsCategoryModel();
        foreach ($goodsCategoryIds as $k => $v) {
			$hascate = $goodsCategoryModel->Query(['category_id' => $v,'is_visible'=>1], 'category_name');
			if(empty($hascate)){
				continue;
			}
            $arr['category_id'] = $v;
            $arr['category_name'] = $goodsCategoryModel->Query(['category_id' => $v], 'category_name')[0];
            $arr['short_name'] = $goodsCategoryModel->Query(['category_id' => $v], 'short_name')[0];
            if (empty($arr['short_name'])) {
                $arr['short_name'] = '未分类';
            }
            $data[] = $arr;
        }
        return $data;
    }

    /*
     * 门店首页获取某个分类下的所有商品
     */
    public function getStoreGoods($page_index, $page_size, $condition, $o2o = 0, $store_id = 0, $shop_id)
    {
        //获取后台配置的库存方式 1:门店独立库存 2:店铺统一库存  默认为1
        $stock_type = (int)$this->getStoreSet($shop_id)['stock_type'] ?: 1;

        if ($condition['store_id']) {
            $storeGoodsModel = new VslStoreGoodsModel();
            $data = $storeGoodsModel->pageQuery($page_index, $page_size, $condition, '', '');
        } else {
            $goodsSer = new Goods();
            $data = $goodsSer->getPageGoodsList($page_index, $page_size, $condition);
        }

        $page_count = $data['page_count'];
        $total_count = $data['total_count'];
        $goods_info = $data['data'];
        $goodsSer = new Goods();
        if($stock_type == 2) {
            foreach ($goods_info as $k => $v) {
                $v['store_list'] = explode(',',$v['store_list']);
                if(!in_array($store_id,$v['store_list'])) {
                    unset($goods_info[$k]);
                }
            }
            $goods_info = array_values($goods_info);
        }

         //关联相册表，查出商品对应的图片
        $albumPictureModel = new AlbumPictureModel();
        if($goods_info){
            foreach ($goods_info as $key => $val) {
                $arr = $albumPictureModel->Query(['pic_id' => $val['picture']], 'pic_cover_small')[0];
                $goods_info[$key]['goods_img'] = __IMG($arr);
                unset($arr);
            }
        }
        //sku信息
        $storeGoodsSku = new VslStoreGoodsSkuModel();
        $goods_spec = new VslGoodsSpecModel();
        $goods_sku_model = new VslGoodsSkuModel();
        if($goods_info){
            foreach ($goods_info as $k => $v) {
                if ($stock_type == 1) {
                    $where = [
                        'store_id' => $v['store_id'],
                        'goods_id' => $v['goods_id'],
                    ];
                    $goods_sku_detail = $storeGoodsSku->getQuery($where, '*', '');
                } elseif ($stock_type == 2) {
                    $where = [
                        'goods_id' => $v['goods_id']
                    ];
                    $goods_sku_detail = $goods_sku_model->getQuery($where, '*', '');
                }
                $goods_info[$k]['sku_list'] = $goods_sku_detail;
                $goods_detail = $goodsSer->getGoodsDetailById($v['goods_id']);
                if(!$goods_detail) {
                    unset($goods_info[$k]);
                    continue;
                }
                $spec_list = json_decode($goods_detail['goods_spec_format'], true);
                $album = new Album();
                if (!empty($spec_list)) {
                    foreach ($spec_list as $k1 => $v1) {
                        $sort = $goods_spec->getInfo([
                            "spec_id" => $v1['spec_id']
                        ], "sort");
                        $spec_list[$k1]['sort'] = 0;
                        if (!empty($sort)) {
                            $spec_list[$k1]['sort'] = $sort['sort'];
                        }
                        foreach ($v1["value"] as $m => $t) {
                            if (empty($v1['show_type'])) {
                                $spec_list[$k1]['show_type'] = 1;
                            }
                            // 查询SKU规格主图，没有返回0
                            $spec_list[$k1]["value"][$m]["picture"] = $this->getGoodsSkuPictureBySpecId($v['goods_id'], $spec_list[$k1]["value"][$m]['spec_id'], $spec_list[$k1]["value"][$m]['spec_value_id']);
                            if (is_numeric($t["spec_value_data"]) && $v1["show_type"] == 3) {
                                $picture_detail = $album->getAlubmPictureDetail([
                                    "pic_id" => $t["spec_value_data"]
                                ]);
                                if (!empty($picture_detail)) {
                                    $spec_list[$k1]["value"][$m]["spec_value_data_src"] = __IMG($picture_detail["pic_cover_micro"]);
                                } else {
                                    $spec_list[$k1]["value"][$m]["spec_value_data_src"] = null;
                                }
                                $spec_list[$k1]["value"][$m]["spec_value_data"] = $this->getGoodsSkuPictureBySpecId($v['goods_id'], $spec_list[$k1]["value"][$m]['spec_id'], $spec_list[$k1]["value"][$m]['spec_value_id']);
                            } else {
                                $spec_list[$k1]["value"][$m]["spec_value_data_src"] = null;
                            }
                        }
                    }
                    // 排序字段
                    $sort = array(
                        'field' => 'sort'
                    );

                    $arrSort = array();
                    foreach ($spec_list as $uniqid => $row) {
                        foreach ($row as $key => $value) {
                            $arrSort[$key][$uniqid] = $value;
                        }
                    }
                    array_multisort($arrSort[$sort['field']], SORT_ASC, $spec_list);
                }
                $goods_info[$k]['spec_list'] = $spec_list;
            }
        }
        
        // 查询商品主表
        if($goods_info){
            $goods_info = array_values($goods_info);
            foreach ($goods_info as $key => $value) {
            $model = \think\Request::instance()->module();
            $condition = ['website_id' => $this->website_id, 'goods_id' => $value['goods_id']];
            if ($model == 'platform' || $model == 'admin') {
                $condition['shop_id'] = $this->instance_id;
            }

            $goods_detail = $goodsSer->getGoodsDetailByCondition($condition);
            if (!$goods_detail) {
                continue;
            }

            $discount_service = getAddons('discount', $this->website_id) ? new Discount() : '';
            $limit_discount_info = getAddons('discount', $this->website_id) ? $discount_service->getPromotionInfo($value['goods_id'], $this->instance_id, $this->website_id) : ['discount_num' => 10];

            $member_price = $value['price'];
            $member_discount = 1;
            $goods_detail['discount_choice'] = 1;
            $goods_detail['member_is_label'] = 0;
            $goods_detail['is_show_member_price'] = 0;
            $goods_detail['price_type'] = 0;
            $goods_detail['stock'] = $value['stock'];
            if ($this->uid) {
                // 查询商品是否有开启会员折扣
                $goodsDiscountInfo = $this->getGoodsInfoOfIndependentDiscount($value['goods_id'], $member_price);
                if ($goodsDiscountInfo) {
                    if ($goodsDiscountInfo['is_use'] == 1) {
                        $goods_detail['price_type'] = 1; //会员折扣
                    }
                    $member_price = $goodsDiscountInfo['member_price'];
                    $member_discount = $goodsDiscountInfo['member_discount'];
                    $goods_detail['discount_choice'] = $goodsDiscountInfo['discount_choice'];
                    $goods_detail['is_show_member_price'] = $goodsDiscountInfo['is_show_member_price'];
                    $goods_detail['member_is_label'] = $goodsDiscountInfo['member_is_label'];
                }
            }
            $goods_detail['member_price'] = $member_price;
            $goods_detail['member_discount'] = $member_discount;

            //处理sku的价格
            foreach ($value['sku_list'] as $k => $goods_sku) {
                $pprice = $value['sku_list'][$k]['price'];
                $value['sku_list'][$k]['member_price'] = $member_price;
                if ($goods_detail['discount_choice'] == 2) {
                    $goods_detail['price_type'] = 1; //会员折扣
                    $value['sku_list'][$k]['price'] = $member_price;
                }
                if ($goods_detail['discount_choice'] == 1) {
                    $goods_detail['price_type'] = 1; //会员折扣
                    $value['sku_list'][$k]['price'] = $pprice * ($goodsDiscountInfo['member_discount'] ?: 1);
                }
                if ($limit_discount_info['discount_type'] == 1) {
                    $goods_detail['price_type'] = 2; //限时折扣
                    $value['sku_list'][$k]['price'] = $pprice * $limit_discount_info['discount_num'] / 10;
                }
                if ($limit_discount_info['discount_type'] == 2) {
                    $goods_detail['price_type'] = 2; //限时折扣
                    $value['sku_list'][$k]['price'] = $limit_discount_info['discount_num'];
                }
                if ($o2o) {
                    $value['sku_list'][$k]['price'] = $pprice;
                }

            }
            if ($limit_discount_info['discount_num'] == 10) {
                $limit_discount_info = (object)[];
            }
            $goods_info[$key]['limit_discount_info'] = $limit_discount_info;
            $goods_info[$key]['sku_list'] = $value['sku_list'];

            // 查询商品满减送活动
            $goods_detail['mansong_name'] = '';
            if(getAddons('fullcut', $this->website_id)){
                // 查询商品满减送活动
                $goods_mansong = new Fullcut();
                $goods_detail['mansong_name'] = $goods_mansong->getGoodsMansongName($value['goods_id']);
            }
            // 查询商品的已购数量
            $orderGoods = new VslOrderGoodsModel();
            $num = $orderGoods->getSum([
                "goods_id" => $value['goods_id'],
                "buyer_id" => $this->uid,
                "order_status" => array(
                    "neq",
                    5
                )
            ], "num");
            $goods_detail["purchase_num"] = $num;

            $goods_info[$key]['goods_detail'] = $goods_detail;
        }
        }
        return [
            'goods_list' => $goods_info,
            'page_count' => $page_count,
            'total_count' => $total_count,
        ];
    }

    /*
     * 根据sku_id获取库存
     * **/
    public function getSkuBySkuid($sku_id)
    {
        $storeGoodsSkuModel = new VslStoreGoodsSkuModel();
        $stock = $storeGoodsSkuModel->getSum(['sku_id' => $sku_id], 'stock');
        return $stock;
    }

    public function getGoodsShopid($goods_id)
    {
        $goodsSer = new \data\service\Goods();
        $goods_info = $goodsSer->getGoodsDetailById($goods_id, 'goods_id,goods_name,price,shop_id');
        return $goods_info['shop_id'];
    }

    /*
     * 添加购物车
     */
    public function addCart($uid, $shop_id, $goods_id, $goods_name, $sku_id, $sku_name, $price, $num, $picture, $bl_id, $seckill_id = 0, $store_id)
    {
        if (getAddons('seckill', $this->website_id, $this->instance_id)) {
            //判断是否有seckill_id并且是否已经开始
            $sec_server = new SeckillServer();
            //判断当前商品是否为秒杀商品并且已经开始未结束
            $condition_seckill['s.seckill_id'] = $seckill_id;
            $condition_seckill['nsg.goods_id'] = $goods_id;
            $is_seckill = $sec_server->isSeckillGoods($condition_seckill);
        }
        $stock = $this->getSkuBySkuid($sku_id);//获取规格库存
        if ($is_seckill) {
            //获取限购数量
            $seckill_sku_list = $sec_server->getSeckillSkuInfo(['seckill_id' => $seckill_id, 'sku_id' => $sku_id]);
            $limit_buy = $seckill_sku_list->seckill_limit_buy;
            $seckill_id = $seckill_id;
            if ($limit_buy != 0) {
                if ($num > $limit_buy) {
                    $num = $limit_buy;
                }
            }
            //如果库存不足了
            $redis = connectRedis();
            $redis_goods_sku_store_key = 'store_' . $seckill_id . '_' . $goods_id . '_' . $sku_id;
            $is_index = $redis->llen($redis_goods_sku_store_key);
            if (!$is_index) {
                return -2;
            }
        } else {
            $seckill_id = 0;
        }

        //根据store_id获取store_name
        $storeModel = new VslStoreModel();
        $store_name = $storeModel->getInfo(['store_id' => $store_id], 'store_name');

        // 检测当前购物车中是否存在产品
        if ($uid > 0) {
            $cart = new VslStoreCartModel();
            $condition = array(
                'buyer_id' => $uid,
                'sku_id' => $sku_id
            );
            //多用户shopid重新获取
            $shop_id = $this->getGoodsShopid($goods_id);
            if (getAddons('shop', $this->website_id)) {
                //获取店铺名称
                $shop_model = new VslShopModel();
                $shop_info = $shop_model::get(['shop_id' => $shop_id, 'website_id' => $this->website_id]);
                $shop_name = $shop_info['shop_name'] ? : $this->mall_name;
            } else {
                $shop_name = $this->mall_name;
            }
            $count = $cart->where($condition)->count();

            if ($count == 0 || empty($count)) {
                $data = array(
                    'buyer_id' => $uid,
                    'shop_id' => $shop_id,
                    'shop_name' => $shop_name,
                    'goods_id' => $goods_id,
                    'goods_name' => $goods_name,
                    'sku_id' => $sku_id,
                    'sku_name' => $sku_name,
                    'price' => $price,
                    'num' => $num,
                    'goods_picture' => $picture,
                    'bl_id' => $bl_id,
                    'website_id' => $this->website_id,
                    'seckill_id' => $seckill_id,
                    'store_id' => $store_id,
                    'store_name' => $store_name['store_name'],
                );
                $cart->save($data);
                $retval = $cart->cart_id;
            } else {
                $cart = new VslStoreCartModel();
                // 查询商品限购
                $get_num = $cart->getInfo($condition, 'cart_id,num');
                $goodsSer = new \data\service\Goods();
                $max_buy = $goodsSer->getGoodsDetailById($goods_id, 'goods_id,goods_name,price,max_buy');
                $new_num = $num + $get_num['num'];
                if ($new_num > $stock) {
                    return -2;
                }
                if ($is_seckill) {
                    $price = $seckill_sku_list->seckill_price;
                    if ($limit_buy != 0) {
                        if ($new_num > $limit_buy) {
                            $new_num = $limit_buy;
                        }
                    }
                    $data['seckill_id'] = $seckill_id;
                    $data['num'] = $new_num;
                    $data['price'] = $price;
                } else {
                    if ($max_buy['max_buy'] != 0) {

                        if ($new_num > $max_buy['max_buy']) {

                            $new_num = $max_buy['max_buy'];
                        }
                    }
//                    $data['seckill_id'] = $seckill_id;
                    $data = array(
                        'num' => $new_num
                    );
                }
                $retval = $cart->save($data, $condition);
                if ($retval) {
                    $retval = $get_num['cart_id'];
                }
            }
        } else {
            $cart_array = cookie('cart_array' . $this->website_id);
            $shop_id = $this->getGoodsShopid($goods_id);
            if (getAddons('shop', $this->website_id) && $shop_id) {
                //获取店铺名称
                $shop_model = new VslShopModel();
                $shop_info = $shop_model::get(['shop_id' => $shop_id, 'website_id' => $this->website_id]);
                $shop_name = $shop_info['shop_name'] ? : $this->mall_name;
            } else {
                $shop_name = $this->mall_name;
            }
            $data = array(
                'shop_id' => $shop_id,
                'shop_name' => $shop_name,
                'goods_id' => $goods_id,
                'sku_id' => $sku_id,
                'num' => $num,
                'goods_picture' => $picture
            );
            if ($is_seckill) {
                $data['seckill_id'] = $seckill_id;
            }
            $cart_array = json_decode($cart_array, true);
            if (!empty($cart_array)) {
                $tmp_array = array();
                foreach ($cart_array as $k => $v) {
                    $tmp_array[] = $v['cart_id'];
                }
                $cart_id = max($tmp_array) + 1;
                $is_have = true;
                foreach ($cart_array as $k => $v) {
                    if ($v["goods_id"] == $goods_id && $v["sku_id"] == $sku_id) {
                        $is_have = false;
                        if (($data["num"] + $v["num"]) > $stock) {
                            return -2;
                        }
                        $cart_array[$k]["num"] = $data["num"] + $v["num"];
                    }
                }
                if ($is_have) {
                    $data["cart_id"] = $cart_id;
                    $cart_array[] = $data;
                }
            } else {
                $data["cart_id"] = 1;
                $cart_array[] = $data;
            }
            $cart_array_string = json_encode($cart_array);
            try {
                cookie('cart_array' . $this->website_id, $cart_array_string, 3600);
                return 1;
            } catch (\Exception $e) {
                recordErrorLog($e);
                return 0;
            }
            $retval = 1;
        }
        return $retval;
    }

    /*
     * 购物车页面
     */
    public function cart($uid, &$msg = '', $store_id, $page_size, $page_index)
    {
        if ($uid > 0) {
            $cart = new VslStoreCartModel();
            $cart_goods_list = null;
            $condition = [
                'buyer_id' => $uid,
                'store_id' => $store_id,
                'website_id' => $this->website_id,
            ];
            $data = $cart->pageQuery($page_index, $page_size, $condition, '', '*');
            $cart_goods_list = $data['data'];
            $total_count = $data['total_count'];
            $page_count = $data['page_count'];
        } else {
            $cart_goods_list = cookie('cart_array' . $this->website_id);
            if (empty($cart_goods_list)) {
                $cart_goods_list = array();
            } else {
                $cart_goods_list = json_decode($cart_goods_list, true);
            }
        }

        if (!empty($cart_goods_list)) {
            //获取后台配置的库存方式 1:门店独立库存 2:店铺统一库存  默认为1
            $store_model = new VslStoreModel();
            $shop_id = $store_model->Query(['store_id' => $store_id, 'website_id' => $this->website_id], 'shop_id')[0];
            $stock_type = (int)$this->getStoreSet($shop_id)['stock_type'] ?: 1;
            $goodsSer = new \data\service\Goods();
            foreach ($cart_goods_list as $k => $v) {
                $goods_info = $goodsSer->getGoodsDetailById($v['goods_id']);
                //获取当前商品是否在什么活动中
                $promotion_type = $goods_info['promotion_type'];
                $cart_goods_list[$k]['promotion_type'] = $promotion_type;
                // 获取商品sku信息
                if($stock_type == 1) {
                    $goods_sku = new VslStoreGoodsSkuModel();
                    $sku_info = $goods_sku->getInfo([
                        'sku_id' => $v['sku_id'],
                        'store_id' => $store_id,
                    ], 'stock, price, sku_name');
                }else{
                    $goods_sku = new VslGoodsSkuModel();
                    $sku_info = $goods_sku->getInfo([
                        'sku_id' => $v['sku_id']
                    ], 'stock, price, sku_name');
                }

                $goods_name = $goods_info->goods_name;
                if (mb_strlen($goods_info->goods_name) > 10) {
                    $goods_name = mb_substr($v->goods->goods_name, 0, 10) . '...';
                }
                // 验证商品或sku是否存在,不存在则从购物车移除
                if ($uid > 0) {
                    if (empty($goods_info)) {
                        $cart->destroy([
                            'goods_id' => $v['goods_id'],
                            'buyer_id' => $uid
                        ]);
                        unset($cart_goods_list[$k]);
                        $msg .= "购物车内商品发生变化，已重置购物车" . PHP_EOL;
                        continue;
                    }
                    if (empty($sku_info)) {
                        $cart->destroy([
                            'buyer_id' => $uid,
                            'sku_id' => $v['sku_id'],
                            'store_id' => $store_id
                        ]);
                        unset($cart_goods_list[$k]);
                        $msg .= $goods_name . "商品无sku规格信息，已移除" . PHP_EOL;
                        continue;
                    }
                } else {
                    if (empty($goods_info)) {
                        unset($cart_goods_list[$k]);
                        $this->cartDelete($v['cart_id']);
                        $msg .= "购物车内商品发生变化，已重置购物车" . PHP_EOL;
                        continue;
                    }
                    if (empty($sku_info)) {
                        unset($cart_goods_list[$k]);
                        $this->cartDelete($v['cart_id']);
                        $msg .= $goods_name . "商品无sku规格信息，已移除" . PHP_EOL;
                        continue;
                    }
                }
                if ($goods_info['state'] != 1) {
                    $msg .= $goods_name . "商品该sku规格已下架" . PHP_EOL;
                    continue;
                }
                $num = $v['num'];
//                if ($goods_info['max_buy'] != 0 && $goods_info['max_buy'] < $v['num']) {// todo... 优化，这样判断不准确，因为购物车显示时候前端判断可能存在库存已经没有了
//                    $num = $goods_info['max_buy'];
//                    $msg .= $goods_name . "商品该sku规格购买数量大于最大购买量，已修改购物数量" . PHP_EOL;
//                }
                if ($sku_info['stock'] < $num) {
                    $num = $sku_info['stock'];
                }
                // 商品最小购买数大于现购买数
                if ($goods_info['min_buy'] > 0 && $num < $goods_info['min_buy']) {
                    $num = $goods_info['min_buy'];
                    $msg .= $goods_name . "商品该sku规格现购买数小于最小购买数，已修改购物数量" . PHP_EOL;
                }
                // 商品最小购买数大于现有库存
                if ($goods_info['min_buy'] > $sku_info['stock']) {
                    $msg .= $goods_name . "商品该sku规格最小购买数大于现有库存，已修改购物数量" . PHP_EOL;
                    continue;
                }
                if ($num != $v['num']) {
                    // 更新购物车
                    $cart_goods_list[$k]['num'] = $num;
                    $this->cartAdjustNum($v['cart_id'], $num);
                }
                // 为cookie信息完善商品和sku信息
                if ($uid > 0) {
                    // 查看用户会员价
//                    if (!empty($this->uid)) {
//                        $member_model = new VslMemberModel();
//                        $member_level_info = $member_model->getInfo(['uid'=>$uid])['member_level'];
//                        $member_level = new VslMemberLevelModel();
//                        $member_info = $member_level->getInfo(['level_id'=>$member_level_info]);
//                        $member_discount = $member_info['goods_discount'] / 10;
//                        $member_is_label = $member_info['is_label'];
//                    } else {
//                        $member_discount = 1;
//                    }
//                    if($member_is_label){
//                        $member_price = round($member_discount * $sku_info['price']);
//                    }else{
//                        $member_price = round($member_discount * $sku_info['price'], 2);
//                    }

//                    // todo... 会员折扣
//                    $member_price = $v['price'];    // by sgw 直接从购物车取就行
                    // todo... 会员折扣 by sgw商品价格计算
                    $goodsDiscountInfo = $this->getGoodsInfoOfIndependentDiscount($v->goods_id, $sku_info['price']);//计算会员折扣价
                    if ($goodsDiscountInfo) {
                        $member_price = $goodsDiscountInfo['member_price'];
                    }
                    if (getAddons('seckill', $this->website_id, $this->instance_id)) {
                        //判断是否有秒杀的商品并且是否过期，若有直接取秒杀价
                        $sec_server = new SeckillServer();
                        if (!empty($v['seckill_id'])) {
                            $condition_seckill['s.seckill_id'] = $v['seckill_id'];
                            $condition_seckill['nsg.sku_id'] = $v['sku_id'];
                            $is_seckill = $sec_server->isSeckillGoods($condition_seckill);
                        } else {
                            $condition_seckill['nsg.sku_id'] = $v['sku_id'];
                            $is_seckill = $sec_server->isSkuStartSeckill($condition_seckill);
                            if ($is_seckill) {
                                $v['seckill_id'] = $is_seckill['seckill_id'];
                                $seckill_data['cart_id'] = $v["cart_id"];
                                $seckill_data['seckill_id'] = $is_seckill['seckill_id'];
                                $cart->data($seckill_data, true)->isupdate(true)->save();
                            }
                        }
                    }

                    if ($is_seckill) {
                        //取该商品该用户购买了多少
                        $sku_id = $v['sku_id'];
                        $uid = $this->uid;
                        $website_id = $this->website_id;
                        $buy_num = $this->getActivityOrderSku($uid, $sku_id, $website_id, $v['seckill_id']);
                        $sec_sku_info_list = $sec_server->getSeckillSkuInfo(['seckill_id' => $v->seckill_id, 'sku_id' => $sku_id]);
//                        $sku_info['stock'] = $sec_sku_info_list->remain_num;
                        $goods_info['max_buy'] = (($sec_sku_info_list->seckill_limit_buy - $buy_num) < 0) ? $sec_sku_info_list->seckill_limit_buy : $sec_sku_info_list->seckill_limit_buy - $buy_num;
                        $goods_info['max_buy'] = $goods_info['max_buy'] > $sku_info['stock'] ? $sku_info['stock'] : $goods_info['max_buy'];
                        //如果最大购买数小于购物车的数量并且不等于0
                        if ($goods_info['max_buy'] != 0 && $goods_info['max_buy'] < $v['num']) {
                            // 更新购物车
                            $cart_goods_list[$k]['num'] = $goods_info['max_buy'];
                            $this->cartAdjustNum($v['cart_id'], $goods_info['max_buy']);
                        }
                        if ($goods_info['max_buy'] == 0) {
                            unset($cart_goods_list[$k]);
                            $this->cartDelete($v['cart_id']);
                            $msg .= $goods_name . "商品已达上限" . PHP_EOL;
                            continue;
                        }
                        $sku_info['stock'] = $goods_info['max_buy'];
                        $price = (float)$sec_sku_info_list->seckill_price;
                    } else {
                        $price = $member_price;
                    }

                    $update_data = array(
                        "goods_name" => $goods_info["goods_name"],
                        "sku_name" => $sku_info["sku_name"],
                        "goods_picture" => $v['goods_picture'], // $goods_info["picture"],
                        "price" => $price
                    );
                    // 更新数据
                    $cart->save($update_data, [
                        "cart_id" => $v["cart_id"]
                    ]);
                    $cart_goods_list[$k]["price"] = $price;
                    $cart_goods_list[$k]["goods_name"] = $goods_info["goods_name"];
                    $cart_goods_list[$k]["sku_name"] = $sku_info["sku_name"];
                    $cart_goods_list[$k]["goods_picture"] = $v['goods_picture']; // $goods_info["picture"];
                    $cart_goods_list[$k]['stock'] = $sku_info['stock'];
                    $cart_goods_list[$k]['max_buy'] = $goods_info['max_buy'];
                } else {
                    if (!empty($v['seckill_id']) && getAddons('seckill', $this->website_id, $this->instance_id)) {
                        //判断是否有秒杀的商品并且是否过期，若有直接取秒杀价
                        $condition_seckill['s.seckill_id'] = $v['seckill_id'];
                        $condition_seckill['nsg.sku_id'] = $v['sku_id'];
                        $sec_server = new SeckillServer();
                        $is_seckill = $sec_server->isSeckillGoods($condition_seckill);
                        if ($is_seckill) {
                            $cart_goods_list[$k]["price"] = $is_seckill['seckill_price'];
                            $remain_num = $is_seckill['remain_num'];
                            $limit_buy = $is_seckill['seckill_limit_buy'];
                            $cart_goods_list[$k]['stock'] = $remain_num;
                            $cart_goods_list[$k]['max_buy'] = $limit_buy;
                        } else {
                            $cart_goods_list[$k]["price"] = $sku_info["price"];
                            $cart_goods_list[$k]['stock'] = $sku_info['stock'];
                            $cart_goods_list[$k]['max_buy'] = $goods_info['max_buy'];
                        }
                    } else {
                        $cart_goods_list[$k]["price"] = $sku_info["price"];
                        $cart_goods_list[$k]['stock'] = $sku_info['stock'];
                        $cart_goods_list[$k]['max_buy'] = $goods_info['max_buy'];
                    }
                    $cart_goods_list[$k]["goods_name"] = $goods_info["goods_name"];
                    $cart_goods_list[$k]["sku_name"] = $sku_info["sku_name"];
                    $cart_goods_list[$k]["goods_picture"] = $v['goods_picture']; // $goods_info["picture"];
                }
                $cart_goods_list[$k]['sku_name_arr'] = array_filter(explode(' ', $sku_info["sku_name"]));
                $cart_goods_list[$k]['min_buy'] = $goods_info['min_buy'];
                $cart_goods_list[$k]['point_exchange_type'] = $goods_info['point_exchange_type'];
                $cart_goods_list[$k]['point_exchange'] = $goods_info['point_exchange'];

            }
            // 购物车商品图片
            $picture = new AlbumPictureModel();
            foreach ($cart_goods_list as $k => $v) {
                $picture_info = $picture->Query(['pic_id' => $v['goods_picture']], 'pic_cover');
                $cart_goods_list[$k]['goods_img'] = $picture_info[0];
            }
            sort($cart_goods_list);
        }
        return [
            'cart_list' => $cart_goods_list,
            'total_count' => $total_count,
            'page_count' => $page_count,
        ];
    }

    /**
     * 购物车项目删除(non-PHPdoc)
     *
     * @see \data\api\IGoods::cartDelete()
     */
    public function cartDelete($cart_id_array)
    {
        if ($this->uid > 0) {
            $cart = new VslStoreCartModel();
            $retval = $cart->destroy($cart_id_array);
            return $retval;
        } else {
            $result = $this->deleteCookieCart($cart_id_array);
            return $result;
        }
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IGoods::deleteCookieCart()删除cookie购物车
     */
    private function deleteCookieCart($cart_id_array)
    {
        // TODO Auto-generated method stub
        // 获取删除条件拼装
        $cart_id_array = trim($cart_id_array);
        if (empty($cart_id_array) && $cart_id_array != 0) {
            return 0;
        }
        // 获取购物车
        $cart_goods_list = cookie('cart_array' . $this->website_id);
        if (empty($cart_goods_list)) {
            $cart_goods_list = array();
        } else {
            $cart_goods_list = json_decode($cart_goods_list, true);
        }
        foreach ($cart_goods_list as $k => $v) {
            if (strpos((string)$cart_id_array, (string)$v["cart_id"]) !== false) {
                unset($cart_goods_list[$k]);
            }
        }
        if (empty($cart_goods_list)) {
            cookie('cart_array' . $this->website_id, null);
            return 1;
        } else {
            sort($cart_goods_list);
            try {
                cookie('cart_array' . $this->website_id, json_encode($cart_goods_list), 3600);
                return 1;
            } catch (\Exception $e) {
                recordErrorLog($e);
                return 0;
            }
        }
    }


    /**
     * 购物车数量修改(non-PHPdoc)
     *
     * @see \data\api\IGoods::cartAdjustNum()
     */
    public function cartAdjustNum($cart_id, $num)
    {
        if ($this->uid > 0) {
            $cart = new VslStoreCartModel();
            $data = array(
                'num' => $num
            );
            $retval = $cart->save($data, [
                'cart_id' => $cart_id
            ]);
            return $retval;
        } else {
            $result = $this->updateCookieCartNum($cart_id, $num);
            return $result;
        }
    }

    /**
     * 修改cookie购物车的数量
     *
     * @param unknown $cart_id
     * @param unknown $num
     * @return number
     */
    private function updateCookieCartNum($cart_id, $num)
    {
        // 获取购物车
        $cart_goods_list = cookie('cart_array' . $this->website_id);
        if (empty($cart_goods_list)) {
            $cart_goods_list = array();
        } else {
            $cart_goods_list = json_decode($cart_goods_list, true);
        }
        foreach ($cart_goods_list as $k => $v) {
            if ($v["cart_id"] == $cart_id) {
                $cart_goods_list[$k]["num"] = $num;
            }
        }
        sort($cart_goods_list);
        try {
            cookie('cart_array' . $this->website_id, json_encode($cart_goods_list), 3600);
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            return 0;
        }
    }

    /**
     * 获取商品会员折扣以及会员独立后的信息
     * @param $goods_id
     * @param $goods_price 商品原价格
     * @return member_price     // 折扣后价格
     * @return member_discount   // 折扣率 例如 1就是没有折扣 0.9折扣90%
     * @return is_show_member_price //是否显示折后价
     * @return member_is_label   // 是否取整
     * @return discount_choice   // 折扣方式选择 1：折扣  2：固定金额（折扣率为1）
     */
    public function getGoodsInfoOfIndependentDiscount($goods_id, $goods_price = 0)
    {
        // 查询商品是否有开启会员折扣
        $goodsPower = $this->getGoodsPowerDiscount($goods_id);
        // 默认会员价（不管是分销商还是会员）
        $member_model = new VslMemberModel();
        $member_info = $member_model::get($this->uid)->level;
        $goods_detail['discount_choice'] = 1;
        $goods_detail['member_discount'] = $member_info['goods_discount'] / 10 > 0 ? $member_info['goods_discount'] / 10 : 1;
        $goods_detail['member_is_label'] = $member_info['is_label'] ?: 0;
        if ($member_info['is_label'] == 1) {
            $goods_detail['member_price'] = round($goods_detail['member_discount'] * $goods_price);
        } else {
            $goods_detail['member_price'] = round($goods_detail['member_discount'] * $goods_price, 2);
        }
        if ($goods_detail['member_discount'] == 1) {
            $goods_detail['is_show_member_price'] = 0;
        } else {
            $goods_detail['is_show_member_price'] = 1;
        }

        if ($goodsPower && $goodsPower['is_use'] == 0) { //关闭会员折扣，则商品不参与折扣
            $goods_detail['member_price'] = $goods_price;
            $goods_detail['member_discount'] = 1;
            $goods_detail['member_is_label'] = 0;
            $goods_detail['is_show_member_price'] = 0;
        } else if ($goodsPower && $goodsPower['is_use'] == 1) {//开启会员折扣
            $goods_detail['is_show_member_price'] = 1;
            // 查询会员的等级
            $userService = new User();
            $userLevle = $userService->getUserLevelAndGroupLevel($this->uid);//distributor_level分销商; user_level会员
            $value = json_decode($goodsPower['value'], TRUE);

            // 用户，分销商折扣关闭就使用原会员折扣
            if ($value['is_user_obj_open'] == 2 && $value['is_distributor_obj_open'] == 2) {
                // 没开启默认使用原来的会员等级
//                $member_model = new VslMemberModel();
//                $member_info = $member_model::get($this->uid)->level;
//                $goods_detail['member_discount'] = $member_info['goods_discount'] / 10 ?: 1;
//                $goods_detail['member_is_label'] = $member_info['is_label'];
//                if($member_info['is_label'] == 1){
//                    $goods_detail['member_price'] = round($goods_detail['member_discount'] * $goods_price);
//                }else{
//                    $goods_detail['member_price'] = round($goods_detail['member_discount'] * $goods_price,2);
//                }
//                if ($goods_detail['member_discount'] == 1) {
//                    $goods_detail['is_show_member_price'] = 0;
//                } else {
//                    $goods_detail['is_show_member_price'] = 1;
//                }
            } else if ($userLevle['distributor_level']) {// 客户是分销商且开启分销商独立折扣
                if ($value['is_distributor_obj_open'] == 1 && $value['distributor_obj']) {
                    $id = $userLevle['distributor_level'];
                    $is_label = $value['distributor_obj']['d_is_label'];//是否取整1取，0不取
                    $discount_choice = $value['distributor_obj']['d_discount_choice'];//折扣方式选择
                    if ($discount_choice == 1) {//折扣
                        $member_discount_val = $value['distributor_obj']['d_level_data'][$id]['val'];
                        $goods_detail['member_discount'] = $member_discount_val / 10 ?: 1;

                        if ($is_label == 1) {
                            $goods_detail['member_price'] = round($goods_detail['member_discount'] * $goods_price);
                        } else {
                            $goods_detail['member_price'] = round($goods_detail['member_discount'] * $goods_price, 2);
                        }

                        $goods_detail['member_is_label'] = $is_label ?: 0;
                        $goods_detail['discount_choice'] = 1;
                    }
                    if ($discount_choice == 2) {//固定金额
                        $goods_detail['member_price'] = $value['distributor_obj']['d_level_data'][$id]['val'] ?: $goods_price;
                        $goods_detail['member_discount'] = 1;
                        $goods_detail['member_is_label'] = 0;
                        $goods_detail['discount_choice'] = 2;
                    }
                } else if ($value['is_distributor_obj_open'] == 2 && $value['is_user_obj_open'] == 1 && $value['user_obj']) {// 关闭取会员独立折扣
                    $id = $userLevle['user_level']; //分销商取（会员等级）
                    $is_label = $value['user_obj']['u_is_label'];//是否取整1取，0不取
                    $discount_choice = $value['user_obj']['u_discount_choice'];//折扣方式选择
                    if ($discount_choice == 1) {//折扣
                        $member_discount_val = $value['user_obj']['u_level_data'][$id]['val'];//折扣
                        $goods_detail['member_discount'] = $member_discount_val / 10 ?: 1;
                        if ($is_label == 1) {
                            $goods_detail['member_price'] = round($goods_detail['member_discount'] * $goods_price);
                        } else {
                            $goods_detail['member_price'] = round($goods_detail['member_discount'] * $goods_price, 2);
                        }
                        $goods_detail['member_is_label'] = $is_label ?: 0;
                        $goods_detail['discount_choice'] = 1;
                    }
                    if ($discount_choice == 2) {//固定金额
                        $goods_detail['member_price'] = $value['user_obj']['u_level_data'][$id]['val'] ?: $goods_price;
                        $goods_detail['member_discount'] = 1;
                        $goods_detail['member_is_label'] = 0;
                        $goods_detail['discount_choice'] = 2;
                    }
                }
            } else if ($userLevle['user_level'] && $value['is_user_obj_open'] == 1 && $value['user_obj']) {// 客户是会员且开启会员折扣
                $id = $userLevle['user_level'];
                $is_label = $value['user_obj']['u_is_label'];//是否取整1取，0不取
                $discount_choice = $value['user_obj']['u_discount_choice'];//折扣方式选择
                if ($discount_choice == 1) {//折扣
                    $member_discount_val = $value['user_obj']['u_level_data'][$id]['val'];
                    $goods_detail['member_discount'] = $member_discount_val / 10 ?: 1;
                    if ($is_label == 1) {
                        $goods_detail['member_price'] = round($goods_detail['member_discount'] * $goods_price);
                    } else {
                        $goods_detail['member_price'] = round($goods_detail['member_discount'] * $goods_price, 2);
                    }
                    $goods_detail['member_is_label'] = $is_label ?: 0;
                    $goods_detail['discount_choice'] = 1;
                }
                if ($discount_choice == 2) {//固定金额
                    $goods_detail['member_price'] = $value['user_obj']['u_level_data'][$id]['val'] ?: $goods_price;
                    $goods_detail['member_discount'] = 1;
                    $goods_detail['member_is_label'] = 0;
                    $goods_detail['discount_choice'] = 2;
                }
            }
        }

        return $goods_detail;
    }

    /**
     * 查询商品拥有的权限折扣
     * @param $goods_id
     */
    public function getGoodsPowerDiscount($goods_id)
    {
        $goodsDiscount = new VslGoodsDiscountModel();
        $condition = [
            'type' => 1,
            'goods_id' => $goods_id,
            'website_id' => $this->website_id,
        ];
        $res = $goodsDiscount->getInfo($condition);
        return $res;
    }

    /*
     * 获取商品sku最大购买量
     * **/
    public function getActivityOrderSku($uid, $sku_id, $website_id, $seckill_id)
    {
        $redis = connectRedis();
        $user_buy_sku_num_key = 'buy_' . $seckill_id . '_' . $uid . '_' . $sku_id . '_num';
        $buy_num = $redis->get($user_buy_sku_num_key);
        if (!$buy_num) {
            $activity_os_mdl = new VslActivityOrderSkuRecordModel();
            $activity_os_info = $activity_os_mdl->where(['uid' => $uid, 'sku_id' => $sku_id, 'website_id' => $website_id, 'buy_type' => 1, 'activity_id' => $seckill_id])->find();
//                    echo $activity_os_mdl->getLastSql();exit;
            $buy_num = $activity_os_info['num'];
            $redis->set($user_buy_sku_num_key, $buy_num);
        }
        return $buy_num;
    }

    /**
     * 获取购物车中项目
     *
     * @param array $cart_id_array
     *
     * return array $cart_lists
     */
    public function getCartList($cart_id, &$msg = '')
    {
        $cart = new VslStoreCartModel();
        $storeGoods = new VslStoreGoodsModel();
        $goodsSer = new Goods();
        $storeGoodsSku = new VslStoreGoodsSkuModel();
        $cart_lists = $cart->getQuery(['cart_id' => $cart_id], '*', '');
        $where = [
            'website_id' => $cart_lists[0]['website_id'],
            'shop_id' => $cart_lists[0]['shop_id'],
            'store_id' => $cart_lists[0]['store_id'],
            'goods_id' => $cart_lists[0]['goods_id'],
        ];
        $cart_lists[0]['goods'] = $storeGoods->getQuery($where, '*', '')[0];
        $goodsInfo = $goodsSer->getGoodsDetailById($cart_lists[0]['goods_id'], 'goods_id,point_exchange,max_buy,point_exchange_type');
        $cart_lists[0]['goods']['point_exchange_type'] = $goodsInfo['point_exchange_type'];
        $cart_lists[0]['goods']['max_buy'] = $goodsInfo['max_buy'];
        $cart_lists[0]['goods']['point_exchange'] = $goodsInfo['point_exchange'];
        $cart_lists[0]['sku'] = $storeGoodsSku->getQuery(['sku_id' => $cart_lists[0]['sku_id']], '*', '')[0];

        foreach ($cart_lists as $k => $v) {
            $goods_name = $v->goods_name;
            if (mb_strlen($v->goods_name) > 10) {
                $goods_name = mb_substr($v->goods_name, 0, 10) . '...';
            }
            if (empty($v->sku)) {
                $cart->destroy(['cart_id' => $v->cart_id]);
                unset($cart_lists[$k]);
                $msg .= $goods_name . "商品该sku规格不存在，已移除" . PHP_EOL;
                continue;
            }
            if ($v->sku->stock <= 0) {
                $msg .= $goods_name . "商品该sku规格库存不足" . PHP_EOL;
                continue;
            }
            if ($v->goods->state != 1) {
                $msg .= $goods_name . "商品该sku规格已下架" . PHP_EOL;
                continue;
            }
            $num = $v->num;
            if ($v->goods->max_buy != 0 && $v->goods->max_buy < $v->num) {
                $num = $v->goods->max_buy;
                $msg .= $goods_name . "商品该sku规格购买量大于最大购买量，购买数量已更改" . PHP_EOL;
            }

            if ($v->sku->stock < $num) {
                $num = $v->sku->stock;
            }
            if ($num != $v->num) {
                // 更新购物车
                $this->cartAdjustNum($v->cart_id, $v->sku->stock);
                $v->num = $num;
                $msg .= $goods_name . "商品该sku规格购买量大于库存，购买数量已更改" . PHP_EOL;
            }
            $v->stock = $v->sku->stock;
            $v->max_buy = $v->goods->max_buy;
            $v->point_exchange_type = $v->goods->point_exchange_type;
            $v->point_exchange = $v->goods->point_exchange;
            $v->picture_info = $v->goods_picture;
            //如果是秒杀商品并且没有结束，则取秒杀价
            if (!$v->seckill_id && getAddons('seckill', $this->website_id, $this->instance_id)) {
                $sec_server = new SeckillServer();
                $sku_id = $v->sku->sku_id;
                $condition_seckill['nsg.sku_id'] = $sku_id;
                $seckill_info = $sec_server->isSkuStartSeckill($condition_seckill);
                if ($seckill_info) {
                    $v->seckill_id = $seckill_info['seckill_id'];
                }
            }
            if (!empty($v->seckill_id) && getAddons('seckill', $this->website_id, $this->instance_id)) {
                $sec_server = new SeckillServer();
                //判断当前秒杀活动的商品是否已经开始并且没有结束
                $condition_seckill['s.website_id'] = $this->website_id;
                $condition_seckill['s.seckill_id'] = $v->seckill_id;
                $condition_seckill['nsg.sku_id'] = $v->sku->sku_id;
                $is_seckill = $sec_server->isSeckillGoods($condition_seckill);
                if (!$is_seckill) {
                    $v->price = $v->sku->price;
                    $v->seckill_id = 0;
                    $this->cartAdjustSec($v->cart_id, 0);
                    $msg .= $goods_name . "商品该sku规格秒杀活动已经结束，已更改为正常状态商品价格" . PHP_EOL;
                } else {
                    //取该商品该用户购买了多少
                    $sku_id = $v->sku->sku_id;
                    $uid = $this->uid;
                    $website_id = $this->website_id;
                    $buy_num = $this->getActivityOrderSku($uid, $sku_id, $website_id, $v->seckill_id);
                    $sec_sku_info_list = $sec_server->getSeckillSkuInfo(['seckill_id' => $v->seckill_id, 'sku_id' => $v->sku->sku_id]);
                    $v->stock = $sec_sku_info_list->remain_num;
                    $v->max_buy = (($sec_sku_info_list->seckill_limit_buy - $buy_num) < 0) ? 0 : $sec_sku_info_list->seckill_limit_buy - $buy_num;
                    $v->price = $sec_sku_info_list->seckill_price;
                }
            } else {
                $v->price = $v->sku->price;
            }
            unset($cart_lists[$k]->good, $cart_lists[$k]->sku, $cart_lists[$k]->goods_picture);
        }
        return $cart_lists;
    }

    /**
     * 购物车秒杀商品修改(non-PHPdoc)
     *
     * @see \data\api\IGoods::cartAdjustNum()
     */
    public function cartAdjustSec($cart_id, $seckill_id)
    {
        if ($this->uid > 0) {
            $cart = new VslStoreCartModel();
            $data = array(
                'seckill_id' => $seckill_id
            );
            $retval = $cart->save($data, [
                'cart_id' => $cart_id
            ]);
            return $retval;
        }
    }

    /**
     * 门店购物车秒杀商品修改(non-PHPdoc)
     *
     * @see \data\api\IGoods::cartAdjustNum()
     */
    public function storeCartAdjustSec($cart_id, $seckill_id)
    {
        if ($this->uid > 0) {
            $cart = new VslStoreCartModel();
            $data = array(
                'seckill_id' => $seckill_id
            );
            $retval = $cart->save($data, [
                'cart_id' => $cart_id
            ]);
            return $retval;
        }
    }

    /**
     * 暂时是移动端/app结算页面的数据获取/计算
     * @param array $sku_list
     * @param string $msg
     * @return array $return_data
     */
    public function paymentData(array $sku_list, $record_id = '', $group_id = '', $presell_id = '', $un_order = 0, $website_id = 0, $uid = 0,$luckyspell_id=0)
    {
        $uid = $this->uid ? $this->uid : $uid;
        // 获取非秒杀,团购商品,各个类型所需的数据结构
        // $promotion_sku_list 需要计算折扣,满减,优惠券的商品,即非秒杀,团购商品
        // $return_data 全部数据
        // $return_data[$shop_id]['total_amount'] 店铺应付金额
        // $return_data[$shop_id]['goods_list'] 店铺商品
        // $return_data[$shop_id]['full_cut'] 店铺满减
        // $return_data[$shop_id]['coupon_list'] 店铺优惠券列表
        // $return_data[$shop_id]['member_promotion'] 店铺会员优惠总金额
        // $return_data[$shop_id]['discount_promotion'] 店铺限时折扣优惠总金额
        // $return_data[$shop_id]['full_cut_promotion'] 店铺满减送优惠总金额
        // $promotion_sku_list 获取满减送，优惠券信息的商品数据
       
        $new_sku_list = $return_data = $sku_id_array = $seckill_sku = $shipping_sku = $record_sku = $promotion_sku_list = [];
        $flag_receive_goods_code_arr = [];
        $redis = connectRedis();
        foreach ($sku_list as $k => $v) {
            //获取后台配置的库存方式 1:门店独立库存 2:店铺统一库存  默认为1
            $stock_type = (int)$this->getStoreSet($v['shop_id'])['stock_type'] ?: 1;
            $new_sku_list[$v['sku_id']] = $v;
            $sku_id_array[] = $v['sku_id'];
            if ($v['store_id'] && $stock_type == 1) {
                $sku_model = new VslstoreGoodsSkuModel();
                $sku_detail[$k] = $sku_model::get(['sku_id' => $v['sku_id'], 'store_id' => $v['store_id']], ['goods']);
                $store_redis_key = 'store_goods_'.$sku_detail[$k]->goods_id.'_'.$v['sku_id'];
                //判断是否redis中存在库存
                $is_store_stock = $redis->get($store_redis_key);
                if(!$is_store_stock){
                    $redis->set($store_redis_key, $sku_detail[$k]->stock);
                }
            } else {
                $sku_model = new VslGoodsSkuModel();
                $sku_detail[$k] = $sku_model::get(['sku_id' => $v['sku_id']], ['goods']);
            }
            $sku_detail[$k]['channel_id'] = $v['channel_id'] ?: 0;
            $sku_detail[$k]['bargain_id'] = $v['bargain_id'] ?: 0;
            //主播id
            $sku_detail[$k]['anchor_id'] = $v['anchor_id'] ?: 0;
            $sku_detail[$k]['coupon_id'] = empty($v['coupon_id']) ? 0 : $v['coupon_id'];
            $sku_detail[$k]['num'] = $v['num'] ?: 0;
            $sku_detail[$k]['store_id'] = $v['store_id'] ?: 0;
            if (isset($v['receive_goods_code'])){
                if (count($v['receive_goods_code']) != count(array_unique($v['receive_goods_code']))) {
                    //领货码重复
                    $sku_detail[$k]['receive_goods_code_temp'] = $v['receive_goods_code'];
                }
            }else{
                $sku_detail[$k]['receive_goods_code_temp'] = [];
            }
        }

        $discount_service = getAddons('discount', $this->website_id) ? new Discount() : '';
        $full_cut_service = getAddons('fullcut', $this->website_id) ? new Fullcut() : '';
        $shop = getAddons('shop', $this->website_id) ? new VslShopModel() : '';
        $order_goods_service = new OrderGoods();
        $album_picture_model = new AlbumPictureModel();
        $sec_server = getAddons('seckill', $this->website_id, $this->instance_id) ? new SeckillServer() : '';
        $goods_service = new Goods();
        $group_server = getAddons('groupshopping', $this->website_id, $this->instance_id) ? new GroupShoppingServer() : '';
        $addonsConfigSer = new AddonsConfigService();
        $total_account = 0;
        $temp_goods_ids = array_column(objToArr($sku_detail),'goods_id');
        foreach ($sku_detail as $k => $v) {
            $presell_shop_id = $v->goods->shop_id;
            //砍价活动id
            $bargain_id = $new_sku_list[$v->sku_id]['bargain_id'] ?: 0;
            $channel_id = $new_sku_list[$v->sku_id]['channel_id'] ?: 0;
            $temp_sku = [];
            $temp_sku['goods_name'] = $v->goods->goods_name;
            $temp_sku['anchor_id'] = $v['anchor_id'] ?: 0;
            if (getAddons('presell', $this->website_id, $this->instance_id) && !$un_order) {
                $presell = new PresellService();
                $is_presell = $presell->getPresellInfoByGoodsIdIng($v->goods_id);
                $presell_arr = objToArr($is_presell);
                $presell_id = $presell_arr[0]['id'];  //预售
            }

            $is_group = getAddons('groupshopping', $this->website_id, $this->instance_id) && $group_server->isGroupGoods($v->goods_id);
            // 活动影响的内容 是 价格、限购、库存
            //判断当前秒杀活动的商品是否已经开始并且没有结束
            if (!empty($new_sku_list[$v->sku_id]['seckill_id']) && getAddons('seckill', $this->website_id, $this->instance_id) && !$un_order) {
                $seckill_id = $new_sku_list[$v->sku_id]['seckill_id'];
                //判断当前秒杀活动的商品是否已经开始并且没有结束
                $condition_seckill['s.website_id'] = $this->website_id;
                $condition_seckill['nsg.sku_id'] = $v->sku_id;
                $condition_seckill['s.seckill_id'] = $seckill_id;
                $is_seckill = $sec_server->isSeckillGoods($condition_seckill);
                if (!$is_seckill && !$un_order) {
                    $temp_sku['price'] = $v->price;
                    $temp_sku['seckill_id'] = 0;
                    if (!empty($new_sku_list[$v->sku_id]['cart_id'])) {
                        $this->storeCartAdjustSec($new_sku_list[$v->sku_id]['cart_id'], 0);
                    }
                } else {
                    //取该商品该用户购买了多少
                    $sku_id = $v->sku_id;
                    $uid = $this->uid;
                    $website_id = $this->website_id;
                    $buy_num = $this->getActivityOrderSku($uid, $sku_id, $website_id, $new_sku_list[$v->sku_id]['seckill_id']);
                    $sec_sku_info_list = $sec_server->getSeckillSkuInfo(['seckill_id' => $seckill_id, 'sku_id' => $v->sku_id]);
                    $temp_sku['stock'] = $sec_sku_info_list->remain_num;
//                    $temp_sku['max_buy'] = (($sec_sku_info_list->seckill_limit_buy - $buy_num) < 0) ? 0 : (($sec_sku_info_list->seckill_limit_buy - $buy_num) > $temp_sku['stock'] ? $temp_sku['stock'] : $sec_sku_info_list->seckill_limit_buy - $buy_num);
//                    $new_sku_list[$v->sku_id]['num'] = $new_sku_list[$v->sku_id]['num'] > $temp_sku['max_buy'] ? $temp_sku['max_buy'] : $new_sku_list[$v->sku_id]['num'];
                    $temp_sku['price'] = $sec_sku_info_list->seckill_price;
                    $temp_sku['member_price'] = $sec_sku_info_list->seckill_price;
                    $temp_sku['discount_price'] = $sec_sku_info_list->seckill_price;
                }
            } elseif ((!empty($group_id) || !empty($record_id))) {
                if (!$un_order) {
                    if ($is_group) {
                        $group_sku_info = $group_server->getGroupSkuInfo(['sku_id' => $v->sku_id, 'goods_id' => $v->goods_id, 'group_id' => $group_id]);
                        $uid = $this->uid;
                        $website_id = $this->website_id;
                        $buy_num = $goods_service->getActivityOrderSkuForGroup($uid, $v->sku_id, $website_id, $group_id);

                    $temp_sku['price'] = $group_sku_info->group_price;
//                    $temp_sku['max_buy'] = $group_sku_info->group_limit_buy - $buy_num; // 限购数量
//                    if ($temp_sku['max_buy'] < 0) {
//                        $temp_sku['max_buy'] = 0;
//                    }
                        $temp_sku['member_price'] = $group_sku_info->group_price;
                        $temp_sku['discount_price'] = $group_sku_info->group_price;
                        $temp_sku['stock'] = $v->stock;
                    }
                }
            } elseif (!empty($luckyspell_id) && $luckyspell_id>0) {
                if (!$un_order) {
                    $luckyspell_server = getAddons('luckyspell', $this->website_id, $this->instance_id) ? new luckySpellServer() : '';
                    $is_luckyspell = getAddons('luckyspell', $this->website_id, $this->instance_id) && $luckyspell_server->isGroupGoods($v->goods_id);
                    if ($is_luckyspell) {
                        $group_sku_info = $luckyspell_server->getGroupSkuInfo(['sku_id' => $v->sku_id, 'goods_id' => $v->goods_id, 'group_id' => $luckyspell_id]);
                        $buy_num = $goods_service->getActivityOrderSkuForGroup($uid, $v->sku_id, $this->website_id, $luckyspell_id);

                        $temp_sku['price'] = $group_sku_info->group_price;
                        $temp_sku['member_price'] = $group_sku_info->group_price;
                        $temp_sku['discount_price'] = $group_sku_info->group_price;
                        if($channel_id) {
                            //如果有上级渠道商，库存显示平台库存+直属上级渠道商的库存
                            $sku_id = $v->sku_id;
                            $channel_sku_mdl = new VslChannelGoodsSkuModel();
                            $channel_cond['channel_id'] = $channel_id;
                            $channel_cond['sku_id'] = $sku_id;
                            $channel_cond['website_id'] = $this->website_id;
                            $channel_stock = $channel_sku_mdl->getInfo($channel_cond, 'stock')['stock'];
                            $temp_sku['stock'] = $v->stock + $channel_stock;
                        }else{
                            $temp_sku['stock'] = $v->stock;
                        }
                        $temp_sku['channel_id'] = $channel_id?:0;
                    }
                }
            } elseif (getAddons('presell', $this->website_id, $this->instance_id) && !empty($presell_id) && !$un_order) {
                $temp_sku['presell_id'] = $presell_id;
            } elseif (!empty($bargain_id) && getAddons('bargain', $this->website_id, $this->instance_id) && !$un_order) {//砍价活动
                $bargain_server = new Bargain();
                $condition_bargain['bargain_id'] = $bargain_id;
                $condition_bargain['website_id'] = $this->website_id;
                $sku_id = $v->sku_id;
                $uid = $this->uid;
                $website_id = $this->website_id;
                $is_bargain = $bargain_server->isBargain($condition_bargain, $uid);
                if ($is_bargain && !$un_order) {
                    $orderService = new orderServer();
                    $buy_num = $orderService->getActivityOrderSkuNum($uid, $sku_id, $website_id, 3, $bargain_id);
                    $bargain_stock = $is_bargain['bargain_stock'];
//                    $max_buy = $is_bargain['limit_buy'] - $buy_num;
//                    $temp_sku['max_buy'] = ($max_buy > 0) ? ($max_buy > $bargain_stock ? $bargain_stock : $max_buy) : 0; // 限购数量
                    $temp_sku['price'] = $is_bargain['my_bargain']['now_bargain_money'];
                    $temp_sku['discount_price'] = $is_bargain['my_bargain']['now_bargain_money'];
                    $temp_sku['stock'] = $bargain_stock;
                    $temp_sku['bargain_id'] = $bargain_id;
                }
            } else {
                if ($v->goods->state != 1) {
                    throw new Exception('商品为不可购买状态');
//                    return ['code' => -2, 'message' => $v->goods->goods_name . '商品为不可购买状态' . PHP_EOL];
                }
                if ($v->goods->max_buy != 0 && $v->goods->max_buy < $v->num && empty($presell_id) && empty($channel_id)) {
                    $temp_sku['num'] = $v->goods->max_buy;
                }
                if ($v->stock < $new_sku_list[$v->sku_id]['num'] && empty($presell_id) && empty($channel_id)) {
                    $temp_sku['num'] = $v->stock;
                }

//                // todo... by sgw返回max_buy
//                $max_buy = $this->getGoodsMaxBuyNums($v['goods_id'], $v['sku_id']);
//                $temp_sku['max_buy'] = ($max_buy - $new_sku_list[$v->sku_id]['num']) > 0 ? $max_buy - $new_sku_list[$v->sku_id]['num'] : 0;
                $temp_sku['stock'] = $v->stock;
                if (!empty($channel_id) && getAddons('channel', $this->website_id) && !$un_order) {
                    $sku_id = $v->sku_id;
                    $channel_sku_mdl = new VslChannelGoodsSkuModel();
                    $channel_cond['channel_id'] = $channel_id;
                    $channel_cond['sku_id'] = $sku_id;
                    $channel_cond['website_id'] = $this->website_id;
                    $channel_stock = $channel_sku_mdl->getInfo($channel_cond, 'stock')['stock'];
//                    $temp_sku['max_buy'] = $channel_stock;
                    $temp_sku['stock'] = $channel_stock;
                    $temp_sku['channel_id'] = $channel_id;
                }
                $temp_sku['price'] = $v->price;   // todo....

                //限时折扣
                $limit_discount_info = getAddons('discount', $this->website_id) ? $discount_service->getPromotionInfo($v->goods_id, $v->goods->shop_id, $v->goods->website_id) : ['discount_num' => 10];
                //查询商品是否开启PLUS会员价
                $is_member_discount = 1;
                $goodsPower = $this->getGoodsPowerDiscount($v->goods_id);
                $goodsPower = json_decode($goodsPower['value'],true);
                $plus_member = $goodsPower['plus_member'] ? : 0;
                if($plus_member == 1) {
                    if(getAddons('membercard',$this->website_id)) {
                        $membercard = new \addons\membercard\server\Membercard();
                        $membercard_data = $membercard->checkMembercardStatus($this->uid);
                        if($membercard_data['status'] && $membercard_data['membercard_info']['is_member_discount']) {
                            //有会员折扣权益
                            $temp_sku['member_price'] = $temp_sku['price'] * $membercard_data['membercard_info']['member_discount'] / 10;
                            $shop_member_price = $v->price - $temp_sku['member_price'];
                            $platform_member_price = $v->price - $temp_sku['member_price'];
                            //如果存在限时折扣 则会员价为原价
                            if($limit_discount_info['discount_id']){
                                $temp_sku['member_price'] = $temp_sku['price'];
                            }else{
                                $return_data[$v->goods->shop_id]['shop_member_price'] += $shop_member_price * $new_sku_list[$v->sku_id]['num'];
                                $return_data[$v->goods->shop_id]['platform_member_price'] += $platform_member_price * $new_sku_list[$v->sku_id]['num'];
                            }
                            $is_member_discount = 0;
                        }
                    }
                }

                if($is_member_discount) {
                    // todo... 会员折扣 by sgw商品价格计算
                    $goodsDiscountInfo = $this->getGoodsInfoOfIndependentDiscount($v->goods_id, $v->price);//计算会员折扣价
                    //如果是限时折扣是店铺设置的 需要店铺负责
                    //会员折扣后的价格
                    if ($goodsDiscountInfo) {
                        $temp_sku['member_price'] = $goodsDiscountInfo['member_price'];
                        //如果存在限时折扣 则会员价为原价
                        if ($limit_discount_info['discount_id']) {
                            $temp_sku['member_price'] = $temp_sku['price'];
                        } else {
                            $return_data[$v->goods->shop_id]['shop_member_price'] += $goodsDiscountInfo['shop_member_price'] * $new_sku_list[$v->sku_id]['num'];
                            $return_data[$v->goods->shop_id]['platform_member_price'] += $goodsDiscountInfo['platform_member_price'] * $new_sku_list[$v->sku_id]['num'];
                        }
                    }
                }

                //限时折扣处理
                if ($limit_discount_info['integer_type'] == 1) {
                    $temp_sku['discount_price'] = round($temp_sku['member_price'] * $limit_discount_info['discount_num'] / 10);
                } else {
                    $temp_sku['discount_price'] = round($temp_sku['member_price'] * $limit_discount_info['discount_num'] / 10, 2);
                }
                if ($limit_discount_info['discount_type'] == 2) {
                    $temp_sku['discount_price'] = $limit_discount_info['discount_num'];
                }
                if ($limit_discount_info['shop_id'] > 0) {
                    $return_data[$v->goods->shop_id]['shop_member_price'] += ($temp_sku['member_price'] - $temp_sku['discount_price']) * $new_sku_list[$v->sku_id]['num'];
                } else {
                    $return_data[$v->goods->shop_id]['platform_member_price'] += ($temp_sku['member_price'] - $temp_sku['discount_price']) * $new_sku_list[$v->sku_id]['num'];
                }
            }
            # 领货码
            if(isset($v['receive_goods_code_temp']) && is_array($v['receive_goods_code_temp'])){

                $v['receive_goods_code_temp'] = array_diff($v['receive_goods_code_temp'],$flag_receive_goods_code_arr);
                $temp_sku['receive_goods_code_num_ids'] = [];
                foreach ($v['receive_goods_code_temp'] as $receive_goods_code){
                    $checkRes = $goods_service->getReceiveGoodsCodeConfigInfo(decryptBase64HaveKey($receive_goods_code),$this->website_id,$v['goods']['shop_id']);
                    if (!in_array($checkRes['data']['goods_id'],$temp_goods_ids)){throw new Exception('请绑定对应的商品领货码');}
                    if ($checkRes['code'] < 0){return $checkRes;}
                    //查询码的信息
                    if ($checkRes['data']['goods_id'] == $v['goods_id']){
                        $receiveGoodsCodeSer = new ReceiveGoodsCodeSer();
                        $code_id = $receiveGoodsCodeSer->getReceiveGoodsCodeIdByBase64CodeNo($receive_goods_code,$v['goods']['shop_id']);//code_id
                        if($code_id){
                            $flag_receive_goods_code_arr[] = $receive_goods_code;
                            $temp_sku['receive_goods_code_ids'][] = $code_id;
                            $temp_sku['receive_goods_code_num_ids'][]= $code_id;
                        }
                        $checkRes['data']['code_id'] = $code_id;
                        $temp_sku['receive_goods_code_data'][] = $checkRes['data'];//店铺中，商品对应码对应的数据
                    }
                }
                $temp_sku['receive_goods_code_used'] = $v['receive_goods_code_temp'];
            }
            $temp_sku['min_buy'] = 1;
            $return_data[$v->goods->shop_id]['shop_id'] = $v->goods->shop_id;
            if (empty($return_data[$v->goods->shop_id]['shop_name'])) {
                if (getAddons('shop', $this->website_id)) {
                    $return_data[$v->goods->shop_id]['shop_name'] = $shop->getInfo(['shop_id' => $v->goods->shop_id, 'website_id' => $v->goods->website_id])['shop_name'];
                } else {
                    $return_data[$v->goods->shop_id]['shop_name'] = $this->mall_name;
                }
            }
            $temp_sku['sku_id'] = $v->sku_id;
            $temp_sku['num'] = $new_sku_list[$v->sku_id]['num'];
            $temp_sku['goods_id'] = $v->goods_id;
            $temp_sku['channel_id'] = $v['channel_id'];
            $temp_sku['shop_id'] = $v->goods->shop_id;
            $temp_sku['goods_type'] = $v->goods->goods_type;
            $temp_sku['point_deduction_max'] = $v->goods->point_deduction_max;
            $temp_sku['point_return_max'] = $v->goods->point_return_max;
            $temp_sku['shipping_fee_type'] = $v->goods->shipping_fee_type;
            //暂时取商品的图片，不取规格图
            $picture_info = $album_picture_model->get($v->goods->picture);
            $temp_sku['goods_pic'] = $picture_info ? getApiSrc($picture_info->pic_cover) : '';

            $temp_sku['discount_id'] = $limit_discount_info['discount_id'] ?: '';
            $temp_sku['seckill_id'] = $new_sku_list[$v->sku_id]['seckill_id'] ?: '';

            if (empty($is_bargain) && empty($temp_sku['seckill_id']) && (empty($group_id) && empty($record_id)) && empty($presell_id) && !$un_order && empty($luckyspell_id)) {

                //普通商品进入 、、 各类金额汇总-》总价 等等 待处理
                $promotion_sku_list[$v->goods->shop_id][$v->sku_id] = $temp_sku;
                $return_data[$v->goods->shop_id]['total_amount'] += $temp_sku['discount_price'] * $temp_sku['num'];
                // 用于计算 折扣 类型优惠券的总额
                $return_data[$v->goods->shop_id]['amount_for_coupon_discount'] += $temp_sku['discount_price'] * $temp_sku['num'];
                // 店铺会员优惠总金额   会员折扣不写入优惠金额 -- 待定
                $return_data[$v->goods->shop_id]['member_promotion'] += ($temp_sku['price'] - $temp_sku['member_price']) * $temp_sku['num'];
                // 店铺限时折扣优惠总金额
                $return_data[$v->goods->shop_id]['discount_promotion'] += ($temp_sku['member_price'] - $temp_sku['discount_price']) * $temp_sku['num'];
            } else {

                $return_data[$v->goods->shop_id]['total_amount'] += $temp_sku['price'] * $temp_sku['num'];
                // 将显示的价格全部设置为discount_price
                $temp_sku['discount_price'] = $temp_sku['price'];
                // 店铺会员优惠总金额
                $return_data[$v->goods->shop_id]['member_promotion'] += 0;
                // 店铺限时折扣优惠总金额
                $return_data[$v->goods->shop_id]['discount_promotion'] += 0;
            }

            // 规格
            $spec_info = [];
            if ($v['attr_value_items']) {
                $sku_spec_info = explode(';', $v['attr_value_items']);
                foreach ($sku_spec_info as $k_spec => $v_spec) {
                    $spec_value_id = explode(':', $v_spec)[1];
                    $spec_info[$k_spec] = $order_goods_service->getSpecInfo($spec_value_id, $temp_sku['goods_id']);
                }
            }
            $temp_sku['spec'] = $spec_info;
            //判断是否有传预售ID
            if ($presell_id) {
                $return_data[$presell_shop_id]['presell_info'] = null;
                if (getAddons('presell', $this->website_id, $this->instance_id) && !$un_order) {
                    //从SKUID和预售ID找到相关信息
                    $presell = new PresellService();
                    $sku_id = $sku_list[0]['sku_id'];
                    $info = $presell->getPresellBySku($presell_id, $sku_id);
                    if ($info) {
                        //判断当前用户购买了多少件该活动商品
                        $uid = $this->uid;
                        $p_cond['activity_id'] = $presell_id;
                        $p_cond['uid'] = $uid;
                        $p_cond['sku_id'] = $v->sku_id;
                        $p_cond['buy_type'] = 4;
                        $p_cond['website_id'] = $this->website_id;
                        $aosr_mdl = new VslActivityOrderSkuRecordModel();
                        $user_already_buy = $aosr_mdl->getInfo($p_cond, 'num')['num'];
                        $return_data[$presell_shop_id]['presell_info']['maxbuy'] = ($info['maxbuy'] - $user_already_buy) > 0 ? ($info['maxbuy'] - $user_already_buy) : 0;
                        $return_data[$presell_shop_id]['presell_info']['firstmoney'] = $info['firstmoney'] ?: 0;
                        $return_data[$presell_shop_id]['presell_info']['allmoney'] = $info['allmoney'] ?: 0;
                        $return_data[$presell_shop_id]['presell_info']['presellnum'] = $info['presellnum'] ?: 0;
                        $return_data[$presell_shop_id]['presell_info']['vrnum'] = $info['vrnum'] ?: 0;
                        $return_data[$presell_shop_id]['presell_info']['pay_start_time'] = $info['pay_start_time'] ?: 0;
                        $return_data[$presell_shop_id]['presell_info']['pay_end_time'] = $info['pay_end_time'] ?: 0;
                        $return_data[$presell_shop_id]['presell_info']['goods_num'] = $sku_list[0]['num'] ?: 0;
                        $return_data[$presell_shop_id]['total_amount'] = $info['firstmoney'] * $sku_list[0]['num'] ?: 0;
                        #领货码
                        $return_data[$presell_shop_id]['presell_info']['receive_goods_code_data'] = $temp_sku['receive_goods_code_data'] ?:[];
                        $have_buy = $presell->getPresellSkuNum($presell_id, $temp_sku['sku_id']);
                        $return_data[$presell_shop_id]['presell_info']['over_num'] = $info['presellnum'] - $have_buy;  //已购买人数
                        $total_account = $info['firstmoney'] * $new_sku_list[$v->sku_id]['num'];
                        $temp_sku['price'] = $info['firstmoney'];
                    }
                }
            }

            //优惠券
            if (getAddons('coupontype', $this->website_id) && !$un_order) {
                $temp_sku['coupon_id'] = $v->coupon_id;
            }
            $return_data[$v->goods->shop_id]['goods_list'][] = $temp_sku;
            // 下面的满减送和优惠券可能不进去循环，先初始化一些数据
            $return_data[$v->goods->shop_id]['full_cut'] = (object)[];
            $return_data[$v->goods->shop_id]['coupon_list'] = [];
            $return_data[$v->goods->shop_id]['coupon_num'] = 0;

            //卡券核销门店
            if (getAddons('store', $this->website_id, $this->instance_id) && $v['goods']['goods_type'] == 0 && !$un_order) {
                $store = new Store();
                //判断是否开启了门店自提
                $storeSet = $store->getStoreSet($v->goods->shop_id)['is_use'];
                if($storeSet) {
                    $store_list = $v['goods']['store_list'];
                    if (empty($store_list)) {
                        $return_data[$v->goods->shop_id]['store_list'] = [];
                    } else {
                        $store_id = explode(',', $store_list); //适用的门店ID
                        $condition = [];
                        $condition['website_id'] = $v['website_id'];
                        $condition['store_id'] = ['IN', $store_id];
                        $lng = input('lng', 0);
                        $lat = input('lat', 0);
                        $place = ['lng' => $lng, 'lat' => $lat];
                        $store_list = $store->storeListForFront(1, 20, $condition, $place);
                        if (empty($store_list)) {
                            $return_data[$v->goods->shop_id]['store_list'] = [];
                        } else {
                            $return_data[$v->goods->shop_id]['store_list'] = $store_list['store_list'];
                        }
                    }
                }else{
                    $return_data[$v->goods->shop_id]['store_list'] = [];
                }
            }
        }

        // 满减送
        if (getAddons('fullcut', $this->website_id) && !$un_order) {
            $full_cut_lists = $full_cut_service->getPaymentFullCut($promotion_sku_list); //异常点
            foreach ($full_cut_lists as $kk => $vv) {
                if (empty($vv['man_song_id'])) {
                    unset($full_cut_lists[$kk]);
                }
            }

            $full_cut_limit = [];
            foreach ($full_cut_lists as $shop_id => $full_cut_info) {
                if ($full_cut_info['discount_percent']) {
                    foreach ($full_cut_info['discount_percent'] as $sku_id => $discount_percent) {
                        if (!empty($full_cut_info) && $full_cut_info['discount'] > 0) {
                            // 计算优惠券需要的信息
                            $promotion_sku_list[$shop_id][$sku_id]['full_cut_amount'] = $full_cut_info['discount'];
                            $promotion_sku_list[$shop_id][$sku_id]['full_cut_percent'] = $full_cut_info['discount_percent'][$sku_id];
                            $promotion_sku_list[$shop_id][$sku_id]['full_cut_percent_amount'] = round($full_cut_info['discount_percent'][$sku_id] * $full_cut_info['discount'], 2);
                        }
                    }
                }
                $return_data[$shop_id]['total_amount'] -= $full_cut_info['discount'];
                $return_data[$shop_id]['amount_for_coupon_discount'] -= $full_cut_info['discount'];
                $full_cut_limit[$shop_id] = $full_cut_info['goods_limit'];
                unset($full_cut_info['discount_percent']);
                //重新赋值discount 原规则是rule_discount,是需要展示的值，由于前端更新字段需要重新发布小程序等 所以在后台循环重新赋值 同时把真实值保留 需要展示总的优惠额
                $full_cut_info['real_discount'] = $full_cut_info['discount'];
                $full_cut_info['discount'] = $full_cut_info['rule_discount'];
                $return_data[$shop_id]['full_cut'] = $full_cut_info ?: (object)[];
                if (!empty($presell_id)) {
                    $return_data[$shop_id]['full_cut'] = (object)[];
                }
            }

            if (empty($presell_id)) {
                $full_cut_compute = [];
                foreach ($promotion_sku_list as $k => $v) {
                    foreach ($v as $k2 => $v2) {
                        $full_cut_compute[$k2]['full_cut_amount'] = $v2['full_cut_amount'];
                        $full_cut_compute[$k2]['full_cut_percent'] = $v2['full_cut_percent'];
                        $full_cut_compute[$k2]['full_cut_percent_amount'] = $v2['full_cut_percent_amount'];
                    }
                }
                foreach ($return_data as $k => $v) {
                    $full_cut_goods = [];

                    if (!empty($full_cut_limit[$k])) {
                        foreach ($full_cut_limit[$k] as $k3 => $v3) {
                            $full_cut_goods[$v3] = 1;
                        }
                        if ($v['goods_list']) {
                            foreach ($v['goods_list'] as $k2 => $v2) {
                                if ($full_cut_goods[$v2['goods_id']] == 1) {
                                    $return_data[$k]['goods_list'][$k2]['full_cut_sku_amount'] = $full_cut_compute[$v2['sku_id']]['full_cut_amount'];
                                    $return_data[$k]['goods_list'][$k2]['full_cut_sku_percent'] = $full_cut_compute[$v2['sku_id']]['full_cut_percent'];
                                    $return_data[$k]['goods_list'][$k2]['full_cut_sku_percent_amount'] = $full_cut_compute[$v2['sku_id']]['full_cut_percent_amount'];
                                }
                            }
                        }
                    } else {
                        if ($v['goods_list']) {
                            foreach ($v['goods_list'] as $k2 => $v2) {
                                $return_data[$k]['goods_list'][$k2]['full_cut_sku_amount'] = $full_cut_compute[$v2['sku_id']]['full_cut_amount'];
                                $return_data[$k]['goods_list'][$k2]['full_cut_sku_percent'] = $full_cut_compute[$v2['sku_id']]['full_cut_percent'];
                                $return_data[$k]['goods_list'][$k2]['full_cut_sku_percent_amount'] = $full_cut_compute[$v2['sku_id']]['full_cut_percent_amount'];
                            }
                        }
                    }
                }
            }
        }
        //end 满减送

        // 优惠券
        if (getAddons('coupontype', $this->website_id) && !$un_order) {
            $coupon_service = new Coupon();
            $coupon_list = $coupon_service->getMemberCouponListNew($promotion_sku_list); // 获取优惠券
            $coupon_compute = [];
            foreach ($coupon_list as $shop_id => $v) {
                foreach ($v['coupon_info'] as $coupon_id => $c) {
                    $temp_coupon = [];
                    $temp_coupon['coupon_id'] = $c['coupon_id'];
                    $temp_coupon['coupon_name'] = $c['coupon_type']['coupon_name'];
                    $temp_coupon['coupon_genre'] = $c['coupon_type']['coupon_genre'];
                    $temp_coupon['shop_range_type'] = $c['coupon_type']['shop_range_type'];
                    $temp_coupon['at_least'] = $c['coupon_type']['at_least'];
                    $temp_coupon['money'] = $c['coupon_type']['money'];
                    $temp_coupon['discount'] = $c['coupon_type']['discount'];
                    $temp_coupon['start_time'] = $c['coupon_type']['start_time'];
                    $temp_coupon['end_time'] = $c['coupon_type']['end_time'];
                    $temp_coupon['shop_id'] = $c['coupon_type']['shop_id'];
                    $return_data[$shop_id]['coupon_list'][] = $temp_coupon;
                }
                $return_data[$shop_id]['coupon_num'] = count($v['coupon_info']);
                $coupon_compute[$shop_id] = $v['sku_percent'];
                //有预售则清空
                if (!empty($presell_id)) {
                    $return_data[$shop_id]['coupon_list'][] = [];
                    $return_data[$shop_id]['coupon_num'] = 0;
                }
            }

            if (empty($presell_id)) {
                foreach ($return_data as $k => $v) {
                    $return_data[$k]['coupon_promotion'] = 0;
                    if ($v['goods_list']) {
                        foreach ($v['goods_list'] as $k2 => $v2) {
                            if ($v2['coupon_id'] > 0) {
                                $return_data[$k]['goods_list'][$k2]['coupon_sku_percent'] = $coupon_compute[$k][$v2['coupon_id']][$v2['sku_id']]['coupon_percent'];
                                $return_data[$k]['goods_list'][$k2]['coupon_sku_percent_amount'] = $coupon_compute[$k][$v2['coupon_id']][$v2['sku_id']]['coupon_percent_amount'];
                                $return_data[$k]['coupon_promotion'] += $return_data[$k]['goods_list'][$k2]['coupon_sku_percent_amount'];
                            }
                        }
                        $return_data[$k]['total_amount'] -= $return_data[$k]['coupon_promotion'];
                    }

                }
            }
        }
        #领货码
        $codeFlag = $addonsConfigSer->isAddonsIsLeastOne('receivegoodscode',$this->website_id);
        if ($codeFlag){
            $codeSer = new ReceiveGoodsCodeSer();
            $codeList = $codeSer->getUserOfCodeList($this->uid,2);//用户所有店铺的已绑定且有效领货码
//            $copyRes = $codeSer->getReceiveGoodsCodeOfSetting($this->instance_id);
//            $copy_writing = $copyRes['data']['copy_writing'] ?: '领货码';
        }
        $err_msg = '';
        foreach ($return_data as $k => &$v) {
            if ($total_account != 0) {
                $v['total_amount'] = $total_account;
            } else {
                $v['total_amount'] = ($v['total_amount'] > 0) ? $v['total_amount'] : 0;
            }
            $v['amount_for_coupon_discount'] = ($v['amount_for_coupon_discount'] > 0) ? $v['amount_for_coupon_discount'] : 0;
    
            #领货码
            if ($codeFlag){
                $copyRes = $codeSer->getReceiveGoodsCodeOfSetting($v['shop_id']);
                if ($copyRes['data']['is_use'] == 1){
                    $copy_writing = $copyRes['data']['copy_writing'] ?: '领货码';
                    $temp_receive_goods_code = [];
                    $shop_goods_ids = array_column($v['goods_list'],'goods_id');
                    foreach($codeList as $k1 => $code){
                        if ($code['shop_id'] == $v['shop_id'] && in_array($code['goods_id'],$shop_goods_ids)){
                            $temp_receive_goods_code[] = [
                                'code_id'       => $code['code_id'],
                                'code'          => encryptBase64HaveKey($code['code']['code']),
                                'discount_type' => $code['config_info']['discount_type'],
                                'discount_price'=> $code['config_info']['discount_price'],
                                'validity_type' => $code['code']['validity_type'],
                                'start_time'    => $code['code']['start_time'],
                                'end_time'      => $code['code']['end_time'],
                                'goods_info'    => $code['goods_info'],
                            ];
                        }
                    }
                    $v['receive_goods_code'] = [
                        'copy_writing' => $copy_writing,
                        'count' => count($temp_receive_goods_code),
                        'data' => $temp_receive_goods_code,
                    ];
                    unset($temp_receive_goods_code);
                    $temp_receive_goods_code_useds = reset($v['goods_list'])['receive_goods_code_used']?:'';
                    if ($temp_receive_goods_code_useds) {
                    $code_goods_info = [];
                    foreach ($temp_receive_goods_code_useds as $temp_receive_goods_code_used){
                        $code_goods_info[] = $codeSer->getReceiveCodeAndGoodsInfoByBase64CodeNo($temp_receive_goods_code_used,$v['shop_id']);;
                    }
                    $v['receive_goods_code_used'] = $code_goods_info;
                    }
                }
            }
            //获取领货码配置，是否可以使用多张
            $is_use_many = 1;
            $v['is_use_coupon'] = 1;
            if(getAddons('receivegoodscode',$this->website_id,$k)) {
                $addonsRes = $addonsConfigSer->getAddonsConfig('receivegoodscode', $this->website_id, $k);
                if($addonsRes) {
                    if(isset($addonsRes['value']['is_use_many']) && $addonsRes['value']['is_use_many'] == 0) {
                        $is_use_many = 0;
                    }
                    if(isset($addonsRes['value']['is_use_coupon']) && $addonsRes['value']['is_use_coupon'] == 0) {
                        $v['is_use_coupon'] = 0;
                    }
                }
            }
            if(empty($is_use_many)) {
                foreach ($v['goods_list'] as $k1 => $v1) {
                    if($v1['receive_goods_code_num_ids'] && count($v1['receive_goods_code_num_ids']) > $v1['num']) {
                        array_pop($v['receive_goods_code_used']);
                        array_pop($v['goods_list'][$k1]['receive_goods_code_used']);
                        array_pop($v['goods_list'][$k1]['receive_goods_code_data']);
                        $err_msg = $v1['goods_name'].'只能使用'.$v1['num'].'张领货码';
                    }
                }
            }
        }
        if($err_msg) {
            $return_data['msg'] = $err_msg;
        }
        unset($v);
        unset($temp_goods_ids);
        return $return_data;
    }

    /**
     * 该商品用户目前最大可购买数量
     * @param $goods_id
     * @param int $sku_id
     * @return int|mixed  -1:不能购买/不能增加数量, 0:无限购, 数字表示最大限购数
     */
    public function getGoodsMaxBuyNums($goods_id, $sku_id = 0)
    {
        // 查询商品库存
        $goods = new VslStoreGoodsModel();
        $good_sku = new VslStoreGoodsSkuModel();
        $g_condition = [
            'goods_id' => $goods_id,
            'website_id' => $this->website_id
        ];
        $goods = $goods->getInfo($g_condition, 'stock');
        if ($sku_id) {
            $sku_condition = [
                'sku_id' => $sku_id
            ];
            $goods_sku = $good_sku->getInfo($sku_condition, 'stock');
            if ($goods_sku) {
                $goods['stock'] = $goods_sku['stock'];
            }
        }
        if ($goods['max_buy'] == 0) {
            return 0;
        }
        return $goods['stock'];// todo... 暂时这样处理，后面修改

//
//        if (!$this->uid) {// 未登录就取商品最低限购
//            return $max_buy = min($goods['max_buy'], $goods['stock']);
//        }
//        // 可购商品数量判断
//        $max_buy = 0;
//        if ($goods['stock'] <= 0) {
//            return $max_buy = -1;
//        } else {
//            // 查询用户该商品购物车数量
//            $cart = new VslCartModel();
//            $c_condition = [
//                'buyer_id' => $this->uid,
//                'goods_id' => $goods_id
//            ];
//            $cartCount = $cart->getSum($c_condition, 'num');
//            // 查询用户该商品订单数量(order中statue=5为关闭订单)
//            $orderGood = new VslOrderGoodsModel();//todo... 判断订单状态
//            $og_condition = [
//                'goods_id' => $goods_id,
//                'buyer_id' => $this->uid,
//            ];
//            $orderCount = 0;
//            $orderGoodRes = $orderGood->getQuery($og_condition, 'num, order_id', 'order_goods_id');
//            if ($orderGoodRes) {
//                $orderModel = new VslOrderModel();
//                foreach ($orderGoodRes as $k => $order) {
//                    $order_status = $orderModel->getInfo(['order_id' => $order['order_id']], 'order_status')['order_status'];
//                    if ($order_status != 5) {
//                        $orderCount += $order['num'];
//                    }
//                }
//            }
//            $hasBuyCount = $cartCount + $orderCount;//已购数量
//
//            if ($goods['max_buy'] > 0) { //限购
//                $surplusGoods = $goods['max_buy'] - $hasBuyCount;//最大限购 - 已购数量 = 剩余可购数量
//                if ($surplusGoods <= 0 || $goods['stock'] == 0) {//不能再购买
//                    return $max_buy = -1;
//                }
//                $max_buy = min($surplusGoods, $goods['stock']);
//                if ($goods['single_limit_buy'] > 0) {//单次限购
//                    $max_buy = min($max_buy, $goods['single_limit_buy']);
//                }
//            }
//
//            if ($goods['max_buy'] == 0) {
//                $max_buy = $goods['stock'];
//            }
//        }
//
//        return $max_buy;
    }

    /**
     * 根据商品id、规格id、规格值id查询
     * {@inheritdoc}
     *
     * @see \data\api\IGoods::getGoodsSkuPictureBySpecId()
     */
    public function getGoodsSkuPictureBySpecId($goods_id, $spec_id, $spec_value_id)
    {
        $picture = 0;

        $goods_sku = new VslGoodsSkuPictureModel();
        $sku_img_array = $goods_sku->getInfo([
            "goods_id" => $goods_id,
            "spec_id" => $spec_id,
            "spec_value_id" => $spec_value_id
        ], "sku_img_array");
        if (!empty($sku_img_array)) {
            $array = explode(",", $sku_img_array['sku_img_array']);
            $picture = $array[0];
        }
        return $picture;
    }

    /**
     * 添加购物车(non-PHPdoc)
     *
     * @see \data\api\IGoods::addCart()
     */
    public function addCartForStore($shop_id, $goods_id, $sku_id, $num, $picture, $store_id, $stock_type)
    {

        // 检测当前购物车中是否存在产品
        $cart_array_cookie = Session::get('store_cart_array' . $store_id);
        $data = array(
            'shop_id' => $shop_id,
            'goods_id' => $goods_id,
            'sku_id' => $sku_id,
            'num' => $num,
            'goods_picture' => $picture,
            'store_id' => $store_id,
        );
        if($stock_type == 1) {
            //门店独立库存
            $store_goods_sku_mdl = new VslStoreGoodsSkuModel();
            $stock = $store_goods_sku_mdl->Query(['store_id' => $store_id,'sku_id' => $sku_id],'stock')[0];
        }elseif ($stock_type == 2) {
            //店铺统一库存
            $goods_sku_mdl = new VslGoodsSkuModel();
            $stock = $goods_sku_mdl->Query(['sku_id' => $sku_id],'stock')[0];
        }

        $cart_array = json_decode($cart_array_cookie, true);
        if (!empty($cart_array)) {
            $tmp_array = array();
            foreach ($cart_array as $k => $v) {
                $tmp_array[] = $v['cart_id'];
            }
            $cart_id = max($tmp_array) + 1;
            $is_have = true;
            foreach ($cart_array as $k => $v) {
                if ($v["goods_id"] == $goods_id && $v["sku_id"] == $sku_id) {
                    $is_have = false;
                    if (($data["num"] + $v["num"]) > $stock) {
                        return -2;
                    }
                    $cart_array[$k]["num"] = $data["num"] + $v["num"];
                }
            }
            if ($is_have) {
                $data["cart_id"] = $cart_id;
                $cart_array[] = $data;
            }
        } else {
            $data["cart_id"] = 1;
            $cart_array[] = $data;
        }
        $cart_array_string = json_encode($cart_array);
        try {
            Session::set('store_cart_array' . $store_id, $cart_array_string);
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            return 0;
        }
        return 0;
    }

    /*
     * 获取门店客户购物车
     */

    public function getCart($store_id = 0, $is_deduction = 0, $discount = 0, $manual_amout = 0, $shop_id, $is_membercard_deduction = 0)
    {
        //获取后台配置的库存方式 1:门店独立库存 2:店铺统一库存  默认为1
        $stock_type = (int)$this->getStoreSet($shop_id)['stock_type'] ?: 1;

        $cart_goods_list = Session::get('store_cart_array' . $store_id);
        if (empty($cart_goods_list)) {
            $cart_goods_list = array();
        } else {
            $cart_goods_list = json_decode($cart_goods_list, true);
        }
        $goodsMount = 0; //商品小计
        $amout = 0; //合计
        $returnData = [];
        $msg = '';
        $goodsSer = new \data\service\Goods();
        foreach ($cart_goods_list as $k => $v) {
            $goodsInfo = $goodsSer->getGoodsDetailById($v['goods_id']);
            if($stock_type == 1) {
                $storeGoods = new VslStoreGoodsModel();
                $goods_sku = new VslStoreGoodsSkuModel();
                $storeGoodsInfo = $storeGoods->getInfo([
                    'goods_id' => $v['goods_id'],
                    'store_id' => $store_id,
                ], 'state,goods_name,price, picture');
                // 获取商品sku信息

                $sku_info = $goods_sku->getInfo([
                    'sku_id' => $v['sku_id'],
                    'store_id' => $store_id,
                ], 'stock, price, sku_name');
            }elseif ($stock_type == 2) {
                $goods_sku = new VslGoodsSkuModel();
                $storeGoodsInfo = $goodsSer->getGoodsDetailById($v['goods_id']);
                // 获取商品sku信息

                $sku_info = $goods_sku->getInfo([
                    'sku_id' => $v['sku_id']
                ], 'stock, price, sku_name');
            }

            $goods_name = $storeGoodsInfo['goods_name'];
            if (mb_strlen($storeGoodsInfo['goods_name']) > 10) {
                $goods_name = mb_substr($storeGoodsInfo['goods_name'], 0, 10) . '...';
            }
            // 验证商品或sku是否存在,不存在则从购物车移除

            if (empty($storeGoodsInfo)) {
                unset($cart_goods_list[$k]);
                $this->deleteStoreCookieCart($v['cart_id'], $store_id);
                $msg .= "购物车内商品发上变化，已重置购物车" . PHP_EOL;
                continue;
            }
            if (empty($sku_info)) {
                unset($cart_goods_list[$k]);
                $this->deleteStoreCookieCart($v['cart_id'], $store_id);
                $msg .= $goods_name . "商品无sku规格信息，已移除" . PHP_EOL;
                continue;
            }
            if ($storeGoodsInfo['state'] != 1) {
                unset($cart_goods_list[$k]);
                // 更新cookie购物车
                $this->deleteStoreCookieCart($v['cart_id'], $store_id);
                $msg .= $goods_name . "商品该sku规格已下架，已移除" . PHP_EOL;
                continue;
            }
            $num = $v['num'];

            if ($sku_info['stock'] < $num) {
                $num = $sku_info['stock'];
            }
            // 商品最小购买数大于现购买数
            if ($goodsInfo['min_buy'] > 0 && $num < $goodsInfo['min_buy']) {
                $num = $goodsInfo['min_buy'];
                $msg .= $goods_name . "商品该sku规格现购买数小于最小购买数，已修改购物数量" . PHP_EOL;
            }
            // 商品最小购买数大于现有库存
            if ($goodsInfo['min_buy'] > $sku_info['stock']) {
                unset($cart_goods_list[$k]);
                // 更新cookie购物车
                $this->cartDelete($v['cart_id']);
                $msg .= $goods_name . "商品该sku规格最小购买数大于现有库存，已修改购物数量" . PHP_EOL;
                continue;
            }
            if ($num != $v['num']) {
                // 更新购物车
                $cart_goods_list[$k]['num'] = $num;
                $this->updateStoreCookieCartNum($v['cart_id'], $num, $store_id);
            }

            if($this->uid) {
                $goods_service = new Goods();
                //查询商品是否开启PLUS会员价
                $is_member_discount = 1;
                $goodsPower = $goods_service->getGoodsPowerDiscount($v['goods_id']);
                $goodsPower = json_decode($goodsPower['value'],true);
                $plus_member = $goodsPower['plus_member'] ? : 0;
                if($plus_member == 1) {
                    if(getAddons('membercard',$this->website_id)) {
                        $membercard = new \addons\membercard\server\Membercard();
                        $membercard_data = $membercard->checkMembercardStatus($this->uid);
                        if($membercard_data['status'] && $membercard_data['membercard_info']['is_member_discount']) {
                            //有会员折扣权益
                            $discount_price = $sku_info['price'] * $membercard_data['membercard_info']['member_discount'] / 10;
                            $is_member_discount = 0;
                        }
                    }
                }

                if($is_member_discount) {
                    // 查看用户会员价
                    $goodsDiscountInfo = $goods_service->getGoodsInfoOfIndependentDiscount($v['goods_id'], $sku_info['price'], ['price' => $goodsInfo['price'], 'shop_id' => $goodsInfo['shop_id']]);//计算会员折扣价
                    if ($goodsDiscountInfo) {
                        $discount_price = $goodsDiscountInfo['member_price'];
                    }else{
                        $discount_price = $sku_info["price"];
                    }
                }
            }else{
                $discount_price = $sku_info["price"];
            }

            // 为cookie信息完善商品和sku信息
            $goodsMount += $sku_info["price"] * $num;//商品小计
            $amout += $discount_price * $num;//实付金额
            $cart_goods_list[$k]["price"] = $sku_info["price"];
            $cart_goods_list[$k]["discount_price"] = $discount_price;
            $cart_goods_list[$k]['stock'] = $sku_info['stock'];
            $cart_goods_list[$k]["goods_name"] = $storeGoodsInfo["goods_name"];
            $cart_goods_list[$k]["sku_name"] = $sku_info["sku_name"];
            $cart_goods_list[$k]['sku_name_arr'] = array_filter(explode(' ', $sku_info["sku_name"]));
            $cart_goods_list[$k]['point_exchange_type'] = $storeGoodsInfo['point_exchange_type'];
            $cart_goods_list[$k]['point_exchange'] = $storeGoodsInfo['point_exchange'];
            $cart_goods_list[$k]['max_buy'] = $goodsInfo['max_buy'];
            $cart_goods_list[$k]['min_buy'] = $goodsInfo['min_buy'];
            $cart_goods_list[$k]['point_deduction_max'] = $goodsInfo['point_deduction_max'];
            $cart_goods_list[$k]['point_return_max'] = $goodsInfo['point_return_max'];
            $picture = new AlbumPictureModel();
            $picture_info = $picture->getInfo(['pic_id' => $storeGoodsInfo['picture']], 'pic_cover');
            $cart_goods_list[$k]['picture_info'] = $picture_info['pic_cover'] ? __IMG($picture_info['pic_cover']) : '';
        }
        unset($v);
        sort($cart_goods_list);
        if($manual_amout || $discount){
            //先换算减金额是打多少折,再平摊给各个商品
            $finalAmount = $manual_amout ? $amout - $manual_amout : $amout * $discount / 10;
            $reduce = round($finalAmount/$amout,2);
            $count = count($cart_goods_list);
            $amout = 0;
            $plus = 0;
            foreach($cart_goods_list as $key => $val){
                if($count == 1){
                    $cart_goods_list[$key]["discount_price"] = $manual_amout ? round($val['discount_price'] - $manual_amout/$val['num'],2) : round($val['discount_price'] * $discount/10, 2);
                    if($manual_amout){
                        $amout = $finalAmount;
                    }else{
                        $amout += $cart_goods_list[$key]["discount_price"] * $val['num'];//实付金额
                    }
                    break;
                }
                if($key < $count-1){
                    $cart_goods_list[$key]["discount_price"] = $manual_amout ? round($val['discount_price'] * $reduce, 2) : round($val['discount_price'] * $discount/10, 2);
                    $plus += $cart_goods_list[$key]["discount_price"] * $val['num'];
                }else{
                    $cart_goods_list[$key]["discount_price"] = round(($finalAmount - $plus)/$val['num'], 2);
                    $plus += $cart_goods_list[$key]["discount_price"];
                }
                $amout += $cart_goods_list[$key]["discount_price"] * $val['num'];//实付金额
            }
        }
        $returnData['goods_amount'] = $goodsMount;
        $returnData['amount'] = $amout;
        $returnData['promotion_amount'] = $returnData['goods_amount'] - $returnData['amount'];//优惠金额
//        if ($manual_amout) {
//            $manual_amout = $manual_amout > $returnData['amount'] ? $returnData['amount'] : $manual_amout;
//            $returnData['amount'] = round($returnData['amount'] - $manual_amout, 2);
//            $returnData['promotion_amount'] = $returnData['promotion_amount'] + $manual_amout;
//        } elseif ($discount) {
//            $oldAmount = $returnData['amount'];
//            $returnData['amount'] = round($returnData['amount'] * ($discount / 10), 2);
//            $returnData['promotion_amount'] = $returnData['promotion_amount'] + round($oldAmount - $returnData['amount'], 2);
//        }
        $returnData['cart_list'] = $cart_goods_list;
        $returnData['msg'] = $msg;
        $order_business = new OrderBusiness();
        $member_service = new Member();
        $deduction_data = [];
        $return_point_data = [];
        $point_deductio = $order_business->pointDeductionOrder($returnData['cart_list'], $is_deduction, 2, $this->website_id, $this->uid, 0);
        $deduction_data[] = $point_deductio;
        //返积分
        $point_return = $order_business->pointReturnOrder($point_deductio['sku_info'], 2);
        //判断会员有没有额外返积分的权益
        if(getAddons('membercard',$this->website_id)) {
            $membercard = new \addons\membercard\server\Membercard();
            $membercard_data = $membercard->checkMembercardStatus($this->uid);
            if($membercard_data['status'] && $membercard_data['membercard_info']['is_give_point']) {
                //有额外返积分的权益
                $total_return_point = 0;
                foreach ($point_return['sku_info'] as $k => $p) {
                    if($p['return_point']) {
                        if($membercard_data['membercard_info']['point_type'] == 1) {
                            //倍数类型返积分
                            $point_return['sku_info'][$k]['return_point'] = $p['return_point'] + $p['return_point'] * $membercard_data['membercard_info']['point_num'];
                        }elseif ($membercard_data['membercard_info']['point_type'] == 2) {
                            //固定类型返积分
                            $point_return['sku_info'][$k]['return_point'] = $p['return_point'] + $membercard_data['membercard_info']['point_num'];
                        }
                        $total_return_point += $point_return['sku_info'][$k]['return_point'];
                    }
                }
                $point_return['total_return_point'] = $total_return_point;
            }
        }
        $return_point_data[] = $point_return;
        $returnData['cart_list'] = $point_return['sku_info'];
        //返积分
        $returnData['total_give_point'] = 0;
        if ($return_point_data) {
            foreach ($return_point_data as $k => $v) {
                if ($v['total_return_point'] > 0) {
                    $returnData['total_give_point'] += $v['total_return_point'];
                }
            }
        }
        //积分抵扣
        $returnData['deduction_point'] = [];
        if ($this->uid) {
            $member_info = $member_service->getMemberAccount($this->uid);
            $returnData['deduction_point']['point'] = $member_info['point'];
        } else {
            $returnData['deduction_point']['point'] = 0;
        }

        $returnData['deduction_point']['total_deduction_money'] = 0;
        $returnData['deduction_point']['total_deduction_point'] = 0;

        if ($deduction_data) {
            $points = 1;
            foreach ($deduction_data as $k => $v) {
                if ($v['total_deduction_money'] > 0 && $points == 1) {
                    $returnData['deduction_point']['total_deduction_money'] += $v['total_deduction_money'];
                    $returnData['deduction_point']['total_deduction_point'] += $v['total_deduction_point'];
                }
                if ($v['total_deduction_point'] >= $member_info['point'])
                    $points = 0;
            }
            if ($returnData['deduction_point']['total_deduction_money'] > 0 && $is_deduction == 1) {
                $returnData['amount'] = round("{$returnData['amount']}" - "{$returnData['deduction_point']['total_deduction_money']}", 2);
            }
        }

        //会员卡抵扣
        if($is_membercard_deduction) {
            if(getAddons('membercard',$this->website_id)) {
                $membercard = new MembercardSer();
                $membercard_data = $membercard->checkMembercardStatus($this->uid);
                if($membercard_data['status']) {
                    $temp_membercard_balance = $membercard_data['membercard_info']['membercard_balance'];
                    if($temp_membercard_balance > 0) {
                        if($returnData['amount'] > 0) {
                            $temp_amount = $returnData['amount'];
                            $returnData['amount'] = $returnData['amount'] - $temp_membercard_balance >= 0 ? round("{$returnData['amount']}" - "{$temp_membercard_balance}", 2) : 0;
                            if($returnData['amount'] > 0) {
                                $returnData['total_memebrcard_deduction'] = $temp_membercard_balance;
                            }else{
                                $returnData['total_memebrcard_deduction'] = $temp_amount;
                            }
                        }
                    }
                }
            }
        }

        //如果有会员卡并且是在有效期内的，返回会员卡余额，用于抵扣
        $returnData['membercard_info'] = [];
        if(getAddons('membercard',$this->website_id)) {
            $membercard = new MembercardSer();
            $membercard_data = $membercard->checkMembercardStatus($this->uid);
            if($membercard_data['status']) {
                $returnData['membercard_info']['membercard_name'] = $membercard_data['membercard_info']['membercard_name'];
                $returnData['membercard_info']['membercard_balance'] = $membercard_data['membercard_info']['membercard_balance'];
            }
        }
        $config = new WebConfig();
        $point_deduction = $config->getShopConfig(0, $this->website_id);
        $returnData['is_point_deduction'] = $point_deduction['is_point_deduction'];
        $returnData['is_point'] = $point_deduction['is_point'];
        return $returnData;
    }

    /*
     * 删除门店客户购物车
     */

    public function deleteStoreCookieCart($cart_id_array, $store_id = 0)
    {
        // TODO Auto-generated method stub
        // 获取删除条件拼装
        $cart_id_array = trim($cart_id_array);
        if (empty($cart_id_array) && $cart_id_array != 0) {
            return 0;
        }
        // 获取购物车
        $cart_goods_list = Session::get('store_cart_array' . $store_id);
        if (empty($cart_goods_list)) {
            $cart_goods_list = array();
        } else {
            $cart_goods_list = json_decode($cart_goods_list, true);
        }
        foreach ($cart_goods_list as $k => $v) {
            if (strpos((string)$cart_id_array, (string)$v["cart_id"]) !== false) {
                unset($cart_goods_list[$k]);
            }
        }
        if (empty($cart_goods_list)) {
            Session::set('store_cart_array' . $store_id, null);
            return 1;
        } else {
            sort($cart_goods_list);
            try {
                Session::set('store_cart_array' . $store_id, json_encode($cart_goods_list));
                return 1;
            } catch (\Exception $e) {
                recordErrorLog($e);
                return 0;
            }
        }
    }

    /**
     * 修改门店cookie购物车的数量
     *
     * @param unknown $cart_id
     * @param unknown $num
     * @return number
     */
    public function updateStoreCookieCartNum($cart_id, $num, $store_id = 0)
    {
        // 获取购物车
        $cart_goods_list = Session::get('store_cart_array' . $store_id);
        if (empty($cart_goods_list)) {
            $cart_goods_list = array();
        } else {
            $cart_goods_list = json_decode($cart_goods_list, true);
        }
        foreach ($cart_goods_list as $k => $v) {
            if ($v["cart_id"] == $cart_id) {
                $cart_goods_list[$k]["num"] = $num;
            }
        }
        sort($cart_goods_list);
        try {
            Session::set('store_cart_array' . $store_id, json_encode($cart_goods_list));
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            return 0;
        }
    }

    /*
     * 验证提交订单数据
     * * */

    public function validateData($order_data)
    {
        $cartList = $this->getCart($order_data['store_id'], $order_data['is_deduction'], $order_data['discount'], $order_data['manual_amout'],$order_data['shop_id'],$order_data['is_membercard_deduction']);
        if (empty($cartList['cart_list'])) {
            return ['code' => -1, 'message' => '购物车没有商品'];
        }
        if ((string)$cartList['amount'] !=  $order_data['total_amount']) {
            return ['code' => -1, 'message' => '订单价格有误'.$cartList['amount'].'-'.$order_data['total_amount']];
        }
        $order_data['total_deduction_money'] = $cartList['deduction_point']['total_deduction_money'];
        $order_data['total_deduction_point'] = $cartList['deduction_point']['total_deduction_point'];
        $order_data['goods_list'] = $cartList['cart_list'];
        $order_data['goods_amount'] = $cartList['goods_amount'];
        $order_data['total_memebrcard_deduction'] = $cartList['total_memebrcard_deduction'] ?: 0;
        return ['code' => 1, 'data' => $order_data];
    }

    /*
     * 创建门店订单
     * * */

    public function createStoreOrder($order_data)
    {
        //下单数据
        try {
            $is_order_key = md5(json_encode($order_data));
            $order_info['order_no'] = $order_data['order_no']; //订单编号
            $order_info['out_trade_no'] = $order_data['out_trade_no']; //外部交易号
            $order_info['order_type'] = 12; //订单类型订单类型1为普通2成为微店店主3为微店店主续费4为微店店主升级，5拼团订单，6秒杀订单，7预售订单，8砍价订单，9奖品订单，10兑换订单, 12门店订单
            $order_info['pay_type'] = $order_data['pay_type'] ?: 10; //支付类型
            $order_info['shipping_type'] = 2; //订单配送方式
            $order_info['order_from'] = $order_data['type'] ?: 1; //订单来源
            $order_info['buyer_id'] = $order_data['uid'];
            $user_model = new UserModel();
            $buyer_info = $user_model->getInfo(['uid' => $order_data['uid']],'nick_name');
            $order_info['nick_name'] = $buyer_info['nick_name'];
            $order_info['ip'] = $order_data['ip'];
            $order_info['leave_message'] = $order_data['leave_message'] ?: ''; //买家附言
            $member = new Member();
            if ($order_data['address_id']) {
                $address = $member->getMemberExpressAddressDetail($order_data['address_id'], $order_data['uid']);
                $order_info['receiver_mobile'] = $address['mobile'] ?: '';
                $order_info['receiver_province'] = $address['province'] ?: 0; //省id
                $order_info['receiver_city'] = $address['city'] ?: 0;
                $order_info['receiver_district'] = $address['district'] ?: 0;
                $order_info['receiver_address'] = $address['address'] ?: '';
                $order_info['receiver_zip'] = $address['zip_code'] ?: 0;
                $order_info['receiver_name'] = $address['consigner'] ?: '';
            }else{
                $order_info['receiver_province'] = $order_data['receiver_province'] ?: 0; //省id
                $order_info['receiver_city'] = $order_data['receiver_city'] ?: 0;
            }
            $order_info['shop_id'] = $order_data['shop_id'];
            $order_info['assistant_id'] = $order_data['assistant_id'];
            $shop_condition['shop_id'] = $order_data['shop_id'];
            $shop_condition['website_id'] = $order_data['website_id'];
            $shop_mdl = new VslShopModel();
            $shop_name = $shop_mdl->getInfo($shop_condition, 'shop_name')['shop_name'];
            $order_info['shop_name'] = $shop_name;
            $order_info['shop_total_amount'] = $order_data['goods_amount'];
            $order_info['order_money'] = $order_data['total_amount'];
            $order_info['shop_order_money'] = $order_data['total_amount'];
            $order_info['normal_money'] = $order_data['total_amount'];
            $order_info['shop_should_paid_amount'] = $order_data['total_amount'];
            $order_info['member_money'] = $order_data['total_amount']; //会员价总额
            $order_info['point'] = 0; //订单消耗积分
            $order_info['point_money'] = 0; //订单消耗积分抵多少钱
            $order_info['user_money'] = 0; //订单余额支付金额
            //用户平台余额支付
            if ($order_data['pay_type'] == 5) {  //余额支付
                $order_info['pay_money'] = $order_data['total_amount'];
                $order_info['user_platform_money'] = $order_data['total_amount'];
            } else {
                $order_info['pay_money'] = $order_data['total_amount'];
                $order_info['user_platform_money'] = 0;
            }
            $order_info['payment_type'] = $order_data['pay_type']; 
            $order_info['platform_promotion_money'] = 0; //平台优惠总额
            $order_info['platform_promotion_money'] += $order_data['goods_amount'] - $order_data['total_amount']; //平台优惠总额
            $order_info['shop_promotion_money'] = 0;  //店铺优惠总额
            $order_info['shop_promotion_money'] += $order_data['goods_amount'] - $order_data['total_amount'];  //店铺优惠总额
            $order_info['shipping_fee'] = 0; //订单运费
            $order_info['order_status'] = 3; //订单状态 0->未支付，1->已付款，2->已发货，3->确认收货,4->已完成,5->已关闭
            $order_info['pay_status'] = 2; //订单付款状态,0->待支付，1->支付中，2->已支付
            $order_info['pay_time'] = time();
            $order_info['create_time'] = time();
            $order_info['sign_time'] = time();
            $order_info['website_id'] = $order_data['website_id'];
            $order_info['order_sn'] = $order_data['out_trade_no'];
            $order_info['custom_order'] = $order_data['custom_order'] ?: ''; //自定义订单内容
            $order_info['store_id'] = $order_data['store_id'] ?: 0;
            $order_info['deduction_money'] = $order_data['total_deduction_money'] ?: 0;
            $order_info['deduction_point'] = $order_data['total_deduction_point'] ?: 0;
            $order_info['sku_info'] = $order_data['goods_list'];
            $order_info['membercard_deduction_money'] = $order_data['total_memebrcard_deduction'];
            $order_business = new OrderBusiness();
            $order_id = $order_business->orderCreateNew($order_info);

            if ($order_id < 0) {
                //订单创建失败，将订单金额返回给用户
                Db::startTrans();
                $payment_type = $order_data['pay_type'];
                $refund_fee = $order_data['total_amount'];
                $refund_trade_no = date("YmdHis") . rand(100000, 999999);
                if ($payment_type == 5) {
                    // 退还会员的账户余额
                    $order_server = new Order();
                    $retval = $order_server->updateMemberAccount($order_id, $order_data['uid'], $refund_fee);
                    if (!is_numeric($retval)) {
                        Db::rollback();
                    } else {
                        Db::commit();
                    }
                } else {
                    if ($payment_type == 1) {
                        // 微信退款
                        $weixin_pay = new WeiXinPay();
                        $retval = $weixin_pay->setWeiXinRefund($refund_trade_no, $order_data['out_trade_no'], $refund_fee * 100, $order_data['total_amount'] * 100, $order_data['website_id']);
                    } elseif ($payment_type == 2) {
                        // 支付宝退款
                        $ali_pay = new UnifyPay();
                        $retval = $ali_pay->aliPayNewRefund($refund_trade_no, $order_data['out_trade_no'], $refund_fee);
                        $result = json_decode(json_encode($retval), TRUE);
                        if ($result['code'] == '10000' && $result['msg'] == 'Success') {

                        } else {
                            $retval = array(
                                "is_success" => 0,
                                'msg' => $result['msg']
                            );
                        }
                    }
                    if ($retval['is_success'] != 0) {
                        Db::commit();
                    } else {
                        Db::rollback();
                    }
                }
                return 0;
            } else {
                $cookie_set_data['create_time'] = time();
                $cookie_set_data['out_trade_no'] = $order_data['out_trade_no'];
                $cookie_set_data['order_id'] = $order_id;
                $cookie_set_data = serialize($cookie_set_data);
                Cookie::set($is_order_key, $cookie_set_data, 15);
                return $order_id;
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * 减少商品库存(购买使用)
     * @param unknown $goods_id //商品id
     * @param unknown $sku_id //商品sku
     * @param unknown $num //商品数量
     */
    public function subStoreGoodsStock($goods_id, $sku_id, $num)
    {
        $goods_model = new VslStoreGoodsModel();
        $stock = $goods_model->getInfo(['goods_id' => $goods_id], 'stock');
        if ($stock['stock'] < $num) {
            return LOW_STOCKS;
            exit();
        }
        $goods_sku_model = new VslStoreGoodsSkuModel();
        $sku_stock = $goods_sku_model->getInfo(['sku_id' => $sku_id], 'stock');
        if ($sku_stock['stock'] < $num) {
            return LOW_STOCKS;
            exit();
        }
        $goods_model->save(['stock' => $stock['stock'] - $num], ['goods_id' => $goods_id]);
        $retval = $goods_sku_model->save(['stock' => $sku_stock['stock'] - $num], ['sku_id' => $sku_id]);
        return $retval;

    }

    /**
     * 有新订单或者有售后订单时推送到店员端消息列表
     */
    public function orderMessagePushToClerk($order_no, $order_money, $message_type, $store_id, $shop_id, $website_id)
    {
        //给下单门店的所有店员推送订单信息
        $store_assistant_mdl = new VslStoreAssistantModel();
        $assistant_ids = $store_assistant_mdl -> getQuery(['shop_id' => $shop_id, 'website_id' => $website_id],'assistant_id,store_id','');

        if($assistant_ids) {
            $data = [];
            foreach ($assistant_ids as $k => $v) {
                $v['store_id'] = explode(',',$v['store_id']);
                if(in_array($store_id,$v['store_id'])) {
                    $data[] = [
                        'assistant_id' => $v['assistant_id'],
                        'store_id' => $store_id,
                        'shop_id' => $shop_id,
                        'website_id' => $website_id,
                        'message_type' => $message_type,
                        'message_status' => 0,
                        'order_no' => $order_no,
                        'order_money' => $order_money,
                        'create_time' => time()
                    ];
                }
            }
            if($data) {
                $store_message_mdl = new VslStoreMessageModel();
                $store_message_mdl -> saveAll($data,true);
            }
        }
    }

    /**
     * 店员端获取消息列表
     */
    public function clerkGetMessageList($page_index, $page_size, $condition, $order, $is_loop)
    {
        $store_message_mdl = new VslStoreMessageModel();
        if(empty($is_loop)) {
            //每次请求先把所有未读的改为已读
            $condition['message_status'] = 0;
            $store_message_mdl->isUpdate(true)->save(['message_status' => 1],$condition);
        }

        //获取消息列表
        unset($condition['message_status']);
        $list = $store_message_mdl -> pageQuery($page_index, $page_size, $condition, $order, '*');
        if($list['data']) {
            foreach ($list['data'] as $k => $v) {
                $list['data'][$k]['create_time'] = date('Y-m-d H:i:s',$list['data'][$k]['create_time']);
            }
        }

        return $list;
    }
    /**
     * 通过门店id获取门店列表
     * @param int|string $page_index
     * @param int|string $page_size
     * @param array $condition
     *
     * @return array $list
     */
    public function distanceStoreList(array $condition = [], array $place = [])
    {
        $storeMdl = new VslStoreModel();
        $list = $storeMdl->getQuery($condition, 'store_id, shop_id, website_id, store_name, province_id, city_id, district_id, address, status, start_time, finish_time, lat, lng', 'create_time desc');
        if ($list) {
            //重新组装数组
            $newList = []; 
            $address = new Address();
            foreach ($list as $key => $val) {
                $newList[$key]['distance'] = $this->sphere_distance(['lat' => $val['lat'], 'lng' => $val['lng']], $place);
                $newList[$key]['store_id'] = $val['store_id'];
                $newList[$key]['shop_id'] = $val['shop_id'];
                $newList[$key]['website_id'] = $val['website_id'];
                $newList[$key]['store_name'] = $val['store_name'];
                $newList[$key]['province_name'] = $address->getProvinceName($val['province_id']);
                $newList[$key]['city_name'] = $address->getCityName($val['city_id']);
                $newList[$key]['dictrict_name'] = $address->getDistrictName($val['district_id']);
                $newList[$key]['address'] = $val['address'];
                $newList[$key]['status'] = $val['status'];
                $newList[$key]['start_time'] = $val['start_time'];
                $newList[$key]['finish_time'] = $val['finish_time'];
            }
            array_multisort(array_column($newList, 'distance'), SORT_ASC, $newList);
            unset($list);
        } else {
            $newList = [];
        }
        return $newList;
    }
    /***************************** 门店业绩奖金计算 *********************************/
    /**
     * 提货成功 计算业绩
     * order_id 是唯一的 所以不需要先查再关联
     * type 1门店收银 2核销
     */
    public function calculateStoreCommission ($order_id = 0, $website_id = '', $type = 1)
    {
        if ($order_id == 0 || $website_id == '') {
            return;
        }
        $storeCommissionRecordsModel = new VslStoreCommissionRecordsModel();
        $check = $storeCommissionRecordsModel->getInfo(['order_id' => $order_id], 'id');
        if ($check) {
            return;//已存在 不再重复处理
        }
        #查看是否使用队列 是则放到延迟队列 否则直接继续执行
        if (config('is_high_powered')) {
            #加入
            $this->pushRabbit($order_id,$website_id,$type);
            return;
        }
        $this->payStoreCommission($order_id, $type);
        return;
    }
    public function pushRabbit ($order_id, $website_id = '', $type)
    {
        $url = config('rabbit_interface_url.url');
        $back_url = $url . '/rabbitTask/payStoreCommission';
        $website_id = $website_id ? $website_id : $this->website_id;
        //订单完成延时队列
        $exchange_name = 'delay_storecommission';
        $queue_name = 'delay_storecommission_queue';
        $routing_key = 'delay_storecommission_routing';
        //查询出订单配置过期自动关闭时间
        $delay_time = 1000;//毫秒
        $data = [
            'order_id'   => $order_id,
            'type'       => $type,
            'website_id' => $website_id
        ];
        $request_data = json_encode($data);
        $push_data = [
            "customType"    => "calculate_store_commission",//标识什么业务场景
            "data"          => $request_data,//请求数据
            "requestMethod" => "POST",
            "limitReq"      => 1000,
            "timeOut"       => 20,
            "url"           => $back_url,
            "websiteId"     => $website_id,
            "sign"          => API_KEY,
        ];
        $push_res = pushData($push_data, $exchange_name, $queue_name, $routing_key, 0, 'DIRECT');
        $push_arr = json_decode($push_res, true);
        if ($push_arr['code'] == 103) {//未创建队列
            $create_res = createQueue($exchange_name, $queue_name, $routing_key);
            $create_arr = json_decode($create_res, true);
            if ($create_arr['code'] != 200) {
                debugLog($create_arr, $order_id . '==calculateStoreCommission>pushRabbit1<==');
            } elseif ($create_arr['code'] == 200) {
                $push_res = pushData($push_data, $exchange_name, $queue_name, $routing_key, 0, 'DIRECT');
                $push_arr = json_decode($push_res, true);
            }
        }
    }
    /**
     * 获取门店的平台规则 店铺规则
     */
    public function getStoreSetTask ($website_id, $shop_id = 0)
    {
        $storeSetModel = new VslStoreSetModel();
        $info = $storeSetModel->getInfo([
            'shop_id'    => $shop_id,
            'website_id' => $website_id
        ],
            '*');
        $info['value'] = json_decode(str_replace("&quot;", "\"", $info['value']), true);
        $storeSet = $info['value'];
        return $storeSet;
    }
    /**
     * 开始计算业绩奖金
     * type 1门店收银 2核销
     */
    public function payStoreCommission ($order_id, $type)
    {
        $orderSer = new Order();
        $order = $orderSer->getOrderInfoById($order_id, '*');
        $store_set = $this->getStoreSetTask($order['website_id'], $order['shop_id']);
        if (!isset($store_set['is_commission']) || !isset($store_set['settlement_type'])) {
            return;
        }
        if ($store_set['is_commission'] != 1) {
            return;
        }
        if (!in_array($store_set['settlement_type'], [1, 2])) {
            return;
        }
        if (!in_array($type, [1, 2])) {
            return;
        }
        //需要先循环商品 查看商品独立设置
        $order_item = new VslOrderGoodsModel();
        $order_item_list = $order_item->where([
            'order_id' => $order_id
        ])->select();
        $goodsSer = new GoodsServer();
        $all_money = $pay_money = $order['pay_money']; //订单支付金额
        $money = 0; //奖金
        $arr = [];//规则 留作备用校验
        foreach ($order_item_list as $key_item => $v_item) {
            $goods_detail = $goodsSer->getGoodsDetailById($v_item['goods_id']);
            $goods_commisson = 0;
            if ($goods_detail['is_commission'] == 1) {//开启商品独立
                $all_money -= $v_item['real_money'];
                switch ($type) {
                    case 1:
                        # 门店收银
                        if ($v_item['cash_reward'] == 1) { //比例
                            $goods_commisson = round($v_item['real_money'] * $v_item['cash_reward'] / 100, 2);
                        }
                        if ($v_item['cash_reward'] == 2) { //固定
                            $goods_commisson = $v_item['cash_reward'];
                        }
                        break;
                    case 2:
                        # 核销
                        if ($v_item['write_off_method'] == 1) { //比例
                            $goods_commisson = round($v_item['real_money'] * $v_item['write_off_reward'] / 100, 2);
                        }
                        if ($v_item['write_off_method'] == 2) { //固定
                            $goods_commisson = $v_item['write_off_reward'];
                        }
                        break;
                }
                $money += $goods_commisson;
                $arr[] = [
                    'pay_money'        => $pay_money,
                    'type'             => $type,
                    'goods_money'      => $v_item['real_money'],
                    'text'             => 'one_goods',
                    'goods_id'         => $v_item['goods_id'],
                    'goods_commisson'  => $goods_commisson,
                    'is_commission'    => $v_item['is_commission'],
                    'cash_method'      => $v_item['cash_method'],
                    'write_off_method' => $v_item['write_off_method'],
                    'cash_reward'      => $v_item['cash_reward'],
                    'write_off_reward' => $v_item['write_off_reward']
                ];
            }
        }
        if ($all_money > 0) {//商品独立规则外的剩余金额
            #查询门店独立或者店员独立
            $rules = [];
            switch ($store_set['settlement_type']) {
                case 1:
                    # 员工
                    $assistantModel = new VslStoreAssistantModel();
                    $rules_info = $assistantModel->getInfo(['assistant_id' => $order['assistant_id'], 'website_id' => $order['website_id'], 'shop_id' => $order['shop_id']],
                        'is_commission,cash_method,write_off_method,cash_reward,write_off_reward');
                    if ($rules_info['is_commission'] == 1) {
                        $rules = $rules_info;
                    }
                    break;
                case 2:
                    # 门店
                    $storeMdl = new VslStoreModel();
                    $rules_info = $storeMdl->getInfo(['store_id' => $order['store_id'], 'website_id' => $order['website_id'], 'shop_id' => $order['shop_id']],
                        'is_commission,cash_method,write_off_method,cash_reward,write_off_reward');
                    if ($rules_info['is_commission'] == 1) {
                        $rules = $rules_info;
                    }
                    break;
            }
            if (empty($rules)) {
                $rules = [
                    'is_commission'    => $store_set['is_commission'],
                    'cash_method'      => $store_set['cash_method'],
                    'write_off_method' => $store_set['write_off_method'],
                    'cash_reward'      => $store_set['cash_reward'],
                    'write_off_reward' => $store_set['write_off_reward']
                ];
            }
            #最后处理
            $last_commisson = 0;
            switch ($type) {
                case 1:
                    # 门店收银
                    if ($rules['cash_method'] == 1) { //比例
                        $last_commisson = round($all_money * $rules['cash_reward'] / 100, 2);
                    }
                    if ($rules['cash_method'] == 2) { //固定
                        $last_commisson = $rules['cash_reward'];
                    }
                    break;
                case 2:
                    # 核销
                    if ($rules['write_off_method'] == 1) { //比例
                        $last_commisson = round($all_money * $rules['write_off_reward'] / 100, 2);
                    }
                    if ($rules['write_off_method'] == 2) { //固定
                        $last_commisson = $rules['write_off_reward'];
                    }
                    break;
            }
            $money += $last_commisson;
            $arr[] = [
                'pay_money'        => $pay_money,
                'type'             => $type,
                'goods_money'      => $all_money,
                'text'             => 'last_all',
                'goods_id'         => 0,
                'goods_commisson'  => $last_commisson,
                'is_commission'    => $rules['is_commission'],
                'cash_method'      => $rules['cash_method'],
                'write_off_method' => $rules['write_off_method'],
                'cash_reward'      => $rules['cash_reward'],
                'write_off_reward' => $rules['write_off_reward']
            ];
        }
        $data = [
            'rules'           => json_encode($arr),
            'type'            => $type,
            'assistant_id'    => $order['assistant_id'],
            'store_id'        => $order['store_id'],
            'order_id'        => $order_id,
            'settlement_type' => $store_set['settlement_type'],
            'money'           => $money,
            'pay_money'       => $order['pay_money'],
            'website_id'      => $order['website_id'],
            'shop_id'         => $order['shop_id'],
            'order_no'        => $order['order_no']
        ];
        $this->addStoreCommissionRecords($data);
        return;
    }
    /**
     * 获取奖金的记录
     */
    public function addStoreCommissionRecords ($data)
    {
        if ($data['money'] <= 0) {
            return;
        }
        $assistantModel = new VslStoreAssistantModel();
        $assistant_info = $assistantModel->getInfo(['assistant_id' => $data['assistant_id'], 'website_id' => $data['website_id'], 'shop_id' => $data['shop_id']], 'assistant_name');
        $text = '获得奖金';
        if ($data['type'] == 1) {
            $text = '门店收银';
        }
        if ($data['type'] == 2) {
            $text = '订单核销';
        }
        $storeCommissionRecordsModel = new VslStoreCommissionRecordsModel();
        $storeCommissionRecordsModel->startTrans();
        try {
            //写入账户
            $res = $this->updateAccount($data);
            //添加流水
            $record_data = [
                'text'            => $text,
                'rules'           => $data['rules'],
                'order_id'        => $data['order_id'],
                'order_no'        => $data['order_no'],
                'records_no'      => 'SC' . date('YmdHis') . mt_rand(100000, 999999),
                'pay_money'       => $data['pay_money'],
                'account_type'    => 1,
                'type'            => $data['type'],
                'status'          => 1,
                'money'           => $data['money'],
                'balance'         => $res,
                'shop_id'         => $data['shop_id'],
                'website_id'      => $data['website_id'],
                'assistant_id'    => $data['assistant_id'],
                'store_id'        => $data['store_id'],
                'settlement_type' => $data['settlement_type'],
                'assistant_name'  => $assistant_info['assistant_name'],
                'add_time'        => time(),
                'add_time_str'    => date('Y-m-d H:i:s', time())
            ];
            $storeCommissionRecordsModel->save($record_data);
            $storeCommissionRecordsModel->commit();
            return;
        } catch (\Throwable $e) {
            debugFile($e->getLine() . '__' . $e->getMessage(), 'addStoreCommissionRecords-1', 1111112);
            $storeCommissionRecordsModel->rollback();
        }
    }
    /**
     * 更新账户信息
     */
    public function updateAccount ($data)
    {
        $storeCommissionModel = new VslStoreCommissionModel();
        $account_id = $data['settlement_type'] == 1 ? $data['assistant_id'] : $data['store_id'];
        $account_type = $data['settlement_type'];
        $info = $storeCommissionModel->getInfo(['account_type' => $account_type, 'account_id' => $account_id, 'website_id' => $data['website_id']]);
        if (empty($info)) {
            $insert_data = [
                'account_type' => $account_type,
                'account_id'   => $account_id,
                'commission'   => $data['money'],
                'shop_id'      => $data['shop_id'],
                'website_id'   => $data['website_id']
            ];
            $storeCommissionModel->save($insert_data);
            return $data['money'];
        } else {
            $new_money = $info['commission'] + $data['money'];
            $update_data = [
                'commission' => $new_money
            ];
            $storeCommissionModel->save($update_data, ['id' => $info['id']]);
            return $new_money;
        }
        return 0;
    }
    /**
     * 奖金详情
     */
    public function storeCommission ($assistant_id, $store_id = 0)
    {
        if (!$assistant_id) {
            return [];
        }
        $result = [];
        $assistantModel = new VslStoreAssistantModel();
        $info = $assistantModel->getInfo(['assistant_id' => $assistant_id],
            'assistant_id,store_id,jobs_id,shop_id,website_id,assistant_name,assistant_tel,status,assistant_headimg,is_commission,cash_method,cash_reward,write_off_method,write_off_reward,wx_openid');
        $apl_commission = 0;//提现权限
        $info['jobs_info'] = $this->jobDetail($info['jobs_id']);
        if ($info['jobs_info']['module_id_array']) {
            $act_rules = explode(',', $info['jobs_info']['module_id_array']);
            if (in_array(12, $act_rules)) {
                $apl_commission = 1;
            }
        }
        $result['wx_openid'] = $info['wx_openid'];
        $result['apl_commission'] = $apl_commission;
        $result['commission'] = 0; //可提现
        $result['total_commission'] = 0; //累计
        $result['freezing_commission'] = 0; //冻结
        $result['settlement_type'] = 1;
        // 或者基础设置 查看是店员或门店奖金模式
        $store_set = $this->getStoreSetTask($info['website_id'], $info['shop_id']);
        if (in_array($store_set['settlement_type'], [1, 2])) {
            $result['settlement_type'] = $store_set['settlement_type'];
            $account_id = $store_set['settlement_type'] == 1 ? $info['assistant_id'] : $store_id;
            $account_type = $store_set['settlement_type'];
            $storeCommissionModel = new VslStoreCommissionModel();
            $commission_info = $storeCommissionModel->getInfo(['account_type' => $account_type, 'account_id' => $account_id, 'website_id' => $info['website_id']]);
            if ($commission_info) {
                $result['commission'] = floatval($commission_info['commission']); //可提现
                $result['total_commission'] = round($commission_info['commission'] + $commission_info['freezing_commission'] + $commission_info['withdrawals'], 2); //累计
                $result['freezing_commission'] = floatval($commission_info['freezing_commission']); //冻结
            }
        }
        return $result;
    }
    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::memberBankAccount()
     */
    public function getMemberBankAccount ($assistant_id, $is_default = 0, $condition = [])
    {
        $member_bank_account = new VslMemberBankAccountModel();
        $uid = -$assistant_id;
        $bank_account_list = '';
        if (!empty($uid)) {
            $condition['uid'] = $uid;
            $condition['assistant_id'] = $assistant_id;
            if ($is_default) {
                $condition['is_default'] = 1;
            }
            $bank_account_list = $member_bank_account->getQuery($condition, '*', '');
        }
        return $bank_account_list;
    }
    /**
     * 前台接口余额流水
     *
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param string $order
     * @param string $field
     * @return multitype:number unknown
     */
    public function getAccountLists ($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $store_set = $this->getStoreSetTask($this->website_id, $this->instance_id);
        if ($store_set['settlement_type'] == 1) {
            unset($condition['store_id']);
        }
        if ($store_set['settlement_type'] == 2) {
            unset($condition['assistant_id']);
        }
        $condition['settlement_type'] = $store_set['settlement_type'];
        $member_account = new VslStoreCommissionRecordsModel();
        $storeAssistantModel = new VslStoreAssistantModel();
        $list = $member_account->pageQuery($page_index, $page_size, $condition, 'add_time desc', $field);
        if (!empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                switch ($v['status']) {
                    case 1:
                        $status_name = '待审核';
                        break;
                    case 2:
                        $status_name = '已审核待打款';
                        break;
                    case 3:
                        $status_name = '处理中';
                        break;
                    case 4:
                        $status_name = '已打款';
                        break;
                    case -1:
                        $status_name = '拒绝审核';
                        break;
                    case -2:
                        $status_name = '拒绝打款';
                        break;
                    case -3:
                        $status_name = '打款失败';
                        break;
                    default:
                        $status_name = '';
                        break;
                }
                if($v['account_type'] == 2){
                    $list['data'][$k]['text'] .= '('.$status_name.')';
                    // $list['data'][$k]['money'] = '-'.$v['money'];
                }else{
                    $list['data'][$k]['money'] = '+'.$v['money'];
                }
                unset($v);
            }
        }
        return $list;
    }
    public function getAccountDetail ($id)
    {
        $member_account = new VslStoreCommissionRecordsModel();
        $list = $member_account->getInfo(['id' => $id],
            'id,balance,add_time_str,text,money,website_id,shop_id,order_no,records_no,assistant_name,pay_money,charge,account_type,meno,account_number,realname,pay_type,payment_time,status,service_charge');
        if (!$list) {
            return ['code' => -1, 'message' => '记录不存在'];
        }
        if ($list['website_id'] != $this->website_id || $list['shop_id'] != $this->instance_id) {
            return ['code' => -1, 'message' => '非法操作'];
        }
        if ($list['account_type'] == 2) {
            // $list['money'] = '-'.$list['money'];
        } else {
            $list['money'] = '+' . $list['money'];
        }
        $list['payment_date'] = $list['payment_time'] ? date('Y-m-d H:i:s', $list['payment_time']) : '未到账';
        $list['bank_name'] = '';
        if ($list['pay_type'] == 1 || $list['pay_type'] == 5) {
            //获取银行名称
            $memberBankAccountModel = new VslMemberBankAccountModel();
            $bank_info = $memberBankAccountModel->getInfo(['account_number' => $list['account_number']], 'open_bank');
            $list['bank_name'] = $bank_info['open_bank'] ? $bank_info['open_bank'] : '';
        }
        # 1银行卡（自动）2微信3支付宝4银行卡（手动）
        switch ($list['pay_type']) {
            case 1:
                $type_name = '银行卡';
                break;
            case 2:
                $type_name = '微信';
                break;
            case 3:
                $type_name = '支付宝';
                break;
            case 4:
                $type_name = '银行卡';
                break;
            default:
                $type_name = '';
                break;
        }
        $list['type_name'] = $type_name;
        switch ($list['status']) {
            case 1:
                $status_name = '待审核';
                break;
            case 2:
                $status_name = '已审核待打款';
                break;
            case 3:
                $status_name = '处理中';
                break;
            case 4:
                $status_name = '已打款';
                break;
            case -1:
                $status_name = '拒绝审核';
                break;
            case -2:
                $status_name = '拒绝打款';
                break;
            case -3:
                $status_name = '打款失败';
                break;
            default:
                $status_name = '';
                break;
        }
        $list['status_name'] = $status_name;
        return ['code' => 1, 'message' => 'success', 'data' => $list];
    }
    /*-------------------------  提现过程  -------------------------*/
    /**
     * 申请提现 
     */
    public function addMemberBalanceWithdraw ($shop_id, $withdraw_no, $uid, $bank_account_id, $cash, $type, $assistant_id, $store_id)
    {
        $website_id = $this->website_id;
        $shop_id = $this->instance_id;
        // 得到本店的提现设置
        $config = new WebConfig();
        $withdraw_info = $config->getConfig($shop_id, 'WITHDRAW_BALANCE');
        // 判断是否余额提现设置是否为空 是否启用
        if (empty($withdraw_info) || $withdraw_info['is_use'] == 0) {
            return USER_WITHDRAW_NO_USE;
        }
        // 最小提现额判断
        if ($cash < $withdraw_info['value']["withdraw_cash_min"]) {
            return USER_WITHDRAW_MIN;
        }
        // 判断会员当前余额
        $store_set = $this->getStoreSetTask($website_id, $shop_id);
        $account_id = $store_set['settlement_type'] == 1 ? $assistant_id : $store_id;
        $account_type = $store_set['settlement_type'];
        $storeCommissionModel = new VslStoreCommissionModel();
        $commission_info = $storeCommissionModel->getInfo(['account_type' => $account_type, 'account_id' => $account_id, 'website_id' => $website_id]);
        if (!$commission_info) {
            return ORDER_CREATE_LOW_PLATFORM_MONEY;
        }
        $balance = $commission_info['commission'];
        if ($balance <= 0) {
            return ORDER_CREATE_LOW_PLATFORM_MONEY;
        }
        if ($balance < $cash || $cash <= 0) {
            return ORDER_CREATE_LOW_PLATFORM_MONEY;
        }
        $charge = 0;
        //目前 业绩奖金是没有手续费 .... 所以先去掉
        //提现手续费
        // if($withdraw_info['value']['member_withdraw_poundage']) {
        //     $charge = abs($cash) * $withdraw_info['value']['member_withdraw_poundage']/100;//手续费
        //     if($withdraw_info['value']['withdrawals_end'] && $withdraw_info['value']['withdrawals_begin']){
        //         if (abs($cash) <= $withdraw_info['value']['withdrawals_end'] && abs($cash) >=  $withdraw_info['value']['withdrawals_begin']) {
        //             $charge = 0;//免手续费区间
        //         }
        //     }
        // }
        if ($cash + $charge <= $balance) {
            $service_charge = $cash;
        } else {
            if ($cash - $charge >= 0) {
                $service_charge = $cash - $charge;
            } else {
                return ORDER_CREATE_LOW_PLATFORM_MONEY;
            }
        }
        // 获取 提现账户
        $member_bank_account = new VslMemberBankAccountModel();
        $bank_account_info = $member_bank_account->getInfo([
            'id' => $bank_account_id
        ],
            '*');
        // 查询是否是自动打款不用审核
        $is_examine = $withdraw_info['value']['is_examine'];
        $make_money = $withdraw_info['value']['make_money'];
        // if($type==2){ //微信？
        //     $bank_account_info['account_number'] = $member_info['user_tel'];
        //     $bank_account_info['realname'] = $member_info['real_name'];
        //     $bank_account_info['type'] = 2;
        // }
        if ($is_examine == 1) {//自动审核
            $status = 2;
        }
        if ($is_examine != 1) {//手动审核
            $status = 1;
        }
        if ($type == 1 || $type == 4) {
            if ($withdraw_info['value']['withdraw_message']) {
                $withdraw_message = explode(',', $withdraw_info['value']['withdraw_message']);
                if (in_array(4, $withdraw_message)) {
                    $bank_account_info['type'] = 4;
                }
            }
        }
        //更新账户
        $update['commission'] = $commission_info['commission'] - $cash;
        $update['freezing_commission'] = $commission_info['freezing_commission'] + $cash;
        // 添加提现记录
        $assistantModel = new VslStoreAssistantModel();
        $assistant_info = $assistantModel->getInfo(['assistant_id' => $assistant_id, 'website_id' => $this->website_id, 'shop_id' => $shop_id], 'assistant_name');
        $storeCommissionRecordsModel = new VslStoreCommissionRecordsModel();
        $data = [
            'records_no'      => 'TX' . date('YmdHis') . mt_rand(100000, 999999),
            'shop_id'         => $shop_id,
            'website_id'      => $this->website_id,
            'assistant_id'    => $assistant_id,
            'store_id'        => $store_id,
            'commission_id'   => $commission_info['id'],
            'account_number'  => $bank_account_info['account_number'],
            'realname'        => $bank_account_info['realname'],
            'bank_account_id' => $bank_account_id,
            'settlement_type' => $account_type,
            'money'           => $cash * (-1),
            'balance'         => $update['commission'],
            'service_charge'  => abs($service_charge),
            'charge'          => abs($charge) * (-1),
            'status'          => 1,
            'account_type'    => 2,
            'pay_type'        => $bank_account_info['type'],
            'text'            => '奖金提现',
            'http_from'       => requestForm(),
            'assistant_name'  => $assistant_info['assistant_name'],
            'add_time'        => time(),
            'add_time_str'    => date('Y-m-d H:i:s', time())
        ];
        $storeCommissionRecordsModel->startTrans();
        try {
            $storeCommissionModel->save($update, ['id' => $commission_info['id']]);
            $id = $storeCommissionRecordsModel->save($data);
            $storeCommissionRecordsModel->commit();
            return $id;
        } catch (\Throwable $e) {
            debugFile($e->getLine() . '__' . $e->getMessage(), 'addStoreCommissionRecords-1', 1111112);
            $storeCommissionRecordsModel->rollback();
            return -1;
        }
    }
    public function getMemberWithdrawalCount ($condition)
    {
        $condition['account_type'] = 2;
        $storeCommissionRecordsModel = new VslStoreCommissionRecordsModel();
        $user_sum = $storeCommissionRecordsModel->where($condition)->count();
        if ($user_sum) {
            return $user_sum;
        } else {
            return 0;
        }
    }
    /**
     * 佣金提现列表
     */
    public function getCommissionWithdrawList ($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $condition['account_type'] = 2;
        $commission_withdraw = new VslStoreCommissionRecordsModel();
        $list = $commission_withdraw->pageQuery($page_index, $page_size, $condition, 'add_time desc', $field);
        if (!empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                switch ($v['status']) {
                    case 1:
                        $status_name = '待审核';
                        break;
                    case 2:
                        $status_name = '已审核待打款';
                        break;
                    case 3:
                        $status_name = '处理中';
                        break;
                    case 4:
                        $status_name = '已打款';
                        break;
                    case -1:
                        $status_name = '拒绝审核';
                        break;
                    case -2:
                        $status_name = '拒绝打款';
                        break;
                    case -3:
                        $status_name = '打款失败';
                        break;
                    default:
                        $status_name = '待处理';
                        break;
                }
                $list['data'][$k]['status_name'] = $status_name;
                $list['data'][$k]['payment_date'] = $v['payment_time'] ? date('Y-m-d H:i:s', $v['payment_time']) : '未到账';
                unset($v);
            }
        }
        return $list;
    }
    /**
     * 处理提现
     * status是前端请求的操作 并非数据表的状态值
     */
    public function commissionWithdrawAudit ($id, $status, $memo)
    {
        try {
            switch ($status) {
                case 2:
                    # 通过审核
                    $res = $this->updateCommissionRecords($id, 2);
                    break;
                case 3:
                    # 同意打款(线上)(扣除冻结奖金)
                    $res = $this->audoUpdateCommissionRecords($id, 3, $memo);
                    break;
                case 5:
                    # 同意打款(线下)(扣除冻结奖金)
                    $res = $this->updateCommissionRecords($id, 4, $memo);
                    break;
                case 4:
                    # 拒绝打款(退回冻结奖金)
                    $res = $this->updateCommissionRecords($id, -2, $memo);
                    break;
                case -1:
                    # 拒绝审核(退回冻结奖金)
                    $res = $this->updateCommissionRecords($id, -1, $memo);
                    break;
            }
            return $res;
        } catch (\Throwable $e) {
            return ['code' => -1, 'message' => $e->getMessage()];
        }
    }
    /**
     * 线上打款处理
     */
    public function audoUpdateCommissionRecords ($id, $status, $memo = '')
    {
        $storeCommissionRecordsModel = new VslStoreCommissionRecordsModel();
        $records = $storeCommissionRecordsModel->getInfo(['id' => $id],
            'status,commission_id,account_type,service_charge,money,assistant_id,records_no,account_number,realname,pay_type,charge,shop_id,website_id');
        if (empty($records)) {
            return ['code' => -1, 'message' => '记录不存在！'];
        }
        if ($records['account_type'] != 2 || intval($records['commission_id']) == 0) {
            return ['code' => -1, 'message' => '错误记录！'];
        }
        if ($status == $records['status']) {
            return ['code' => -1, 'message' => '请勿重复操作！'];
        }
        if ($records['pay_type'] == 4) {
            $res = $this->updateCommissionRecords($id, 4, $memo);
            return $res;
        }
        $wx_openid = '';
        if ($records['pay_type'] == 2) {
            #微信提现 需要当前店员使用了微信登陆
            $assistantModel = new VslStoreAssistantModel();
            $assistant_info = $assistantModel->getInfo(['assistant_id' => $records['assistant_id'], 'website_id' => $this->website_id], 'wx_openid');
            if (empty($assistant_info)) {
                return ['code' => -1, 'message' => '店员信息不存在！'];
            }
            if (empty($assistant_info['wx_openid'])) {
                return ['code' => -1, 'message' => '该店员需要使用微信授权登陆门店才可以使用微信打款！'];
            }
            $wx_openid = $assistant_info['wx_openid'];
        }
        $storeCommissionRecordsModel->startTrans();
        try {
            #1银行卡（自动）2微信3支付宝4银行卡（手动）
            $res = ['code' => 1, 'message' => 'success'];
            # 先处理系统账号 处理成功再打款？
            # 重试 4次 
            # 先打款 再处理系统账号？
            # ....................
            $pay_name = '';
            $type = '';
            switch ($records['pay_type']) {
                case 1:
                    # 银行卡（自动）
                    $pay_name = '银行卡';
                    $type = 81;
                    $webConfig = new Config();
                    $joinPay = $webConfig->getConfig($this->instance_id, 'JOINPAY', $this->website_id);
                    if ($joinPay['value']['joinpaytw_is_use'] == 1) {
                        $joinpay = new Joinpay();
                        $retval = $joinpay->jpWithdraw($records['records_no'], $records['uid'], $records['bank_account_id'], abs($records['service_charge']));
                    } else {
                        $weixin_pay = new tlPay();
                        $retval = $weixin_pay->tlWithdraw($records['records_no'], $records['uid'], $records['bank_account_id'], abs($records['service_charge']));
                    }
                    break;
                case 2:
                    # 微信  
                    $pay_name = '微信';
                    $type = 82;
                    $weixin_pay = new WeiXinPay();
                    $retval = $weixin_pay->EnterprisePayment($wx_openid, $records['records_no'], '', abs($records['service_charge']), '奖金微信提现', $this->website_id);
                    break;
                case 3:
                    # 支付宝
                    $pay_name = '支付宝';
                    $type = 83;
                    $alipay_pay = new AliPay();
                    $retval = $alipay_pay->aliPayTransferNew($records['records_no'], $records['account_number'], abs($records['service_charge']), $records['realname']);
                    break;
            }
            if ($retval['is_success'] == 1) { //自动打款成功
                $res = $storeCommissionRecordsModel->where(array("id" => $id))->update(array("payment_date" => time(), "status" => 4, "meno" => '打款成功'));
                $this->updateCommission($records, $status);
                switch ($records['pay_type']) {
                    case 1:
                        $pay_type = 3;
                        break;
                    case 2:
                        $pay_type = 1;
                        break;
                    case 3:
                        $pay_type =2;
                        break;
                }
                $arr = [
                    'order_no' => $records['records_no'],
                    'shop_id' => $records['shop_id'],
                    'website_id' => $records['website_id'],
                    'serial_no' => 'PTN'.getSerialNo(),
                    'out_trade_no' => $records['records_no'],
                    'user_id' => $records['uid'],
                    'money' => abs($records['money']),
                    'charge' => abs($records['charge']),
                    'income_tax' => abs($records['service_charge']),
                    'pay_type' => $pay_type,
                    'account_type' => 16,
                    'offline_pay' => 2,
                    'create_time' => time(),
                    'title' => '店员奖金提现',
                    'type' => 2
                ];
                $onlinePayRecordsModel = new VslOnlinePayRecordsModel();
                $onlinePayRecordsModel->save($arr);
                $storeCommissionRecordsModel->commit();
                return ['code'=>$res,'message'=>'操作成功'];
            } else { //自动打款失败
                $storeCommissionRecordsModel->where(array("id" => $id))->update(array("status" => -3, "meno" => $retval['msg']));
                $storeCommissionRecordsModel->commit();
                return ['code' => -9000, 'message' => '打款失败！' . $retval['msg']];
            }
        } catch (\Throwable $e) {
            $storeCommissionRecordsModel->rollback();
            return ['code' => -1, 'message' => $e->getMessage()];
        }
    }
    /**
     * 更新提现记录  退回
     * 1待审核 2已审核待打款 3处理中 4已打款 -1拒绝审核 -2拒绝打款 -3打款失败
     */
    public function updateCommissionRecords ($id, $status, $memo = '')
    {
        $storeCommissionRecordsModel = new VslStoreCommissionRecordsModel();
        $storeCommissionRecordsModel->startTrans();
        try {
            $records = $storeCommissionRecordsModel->getInfo(['id' => $id], 'status,commission_id,account_type,service_charge,money,charge,shop_id,website_id,records_no');
            if (empty($records)) {
                return ['code' => -1, 'message' => '记录不存在！'];
            }
            if ($records['account_type'] != 2 || intval($records['commission_id']) == 0) {
                return ['code' => -1, 'message' => '错误记录！'];
            }
            if ($status == $records['status']) {
                return ['code' => -1, 'message' => '请勿重复操作！'];
            }
            if ($status == -2 || $status == -1 || $status == 4) {
                $result = $this->updateCommission($records, $status);
                if ($result <= 0) {
                    return ['code' => -1, 'message' => '账户更新失败,请稍后重试！'];
                }
            }
            $data = [
                'status'     => $status,
                'ajust_time' => time(),
                'meno'       => $memo
            ];
            if ($status == 4) {
                $data['payment_time'] = time();
            }
            $res = $storeCommissionRecordsModel->save($data, ['id' => $id]);
            //手动打款记录
            switch ($records['pay_type']) {
                case 1:
                    $pay_type = 3;
                    break;
                case 2:
                    $pay_type = 1;
                    break;
                case 3:
                    $pay_type =2;
                    break;
            }
            $arr = [
                'order_no' => $records['records_no'],
                'shop_id' => $records['shop_id'],
                'website_id' => $records['website_id'],
                'serial_no' => 'PTN'.getSerialNo(),
                'out_trade_no' => $records['records_no'],
                'user_id' => $records['uid'],
                'money' => abs($records['money']),
                'charge' => abs($records['charge']),
                'income_tax' => abs($records['service_charge']),
                'pay_type' => $pay_type,
                'account_type' => 16,
                'offline_pay' => 2,
                'create_time' => time(),
                'title' => '店员奖金提现',
                'type' => 2
            ];
            if ($status == 4) {
                $onlinePayRecordsModel = new VslOnlinePayRecordsModel();
                $onlinePayRecordsModel->save($arr);
            }
            $storeCommissionRecordsModel->commit();
            return ['code' => 1, 'message' => '操作成功！'];
        } catch (\Throwable $e) {
            $storeCommissionRecordsModel->rollback();
            return ['code' => -1, 'message' => $e->getMessage()];
        }
    }
    /**
     * 更新账户 指定账户id更新
     * 重试4次
     */
    public function updateCommission ($records, $status, $num = 1)
    {
        $storeCommissionModel = new VslStoreCommissionModel();
        $commission_info = $storeCommissionModel->getInfo(['id' => $records['commission_id']], 'freezing_commission,commission,withdrawals');
        if ($status == -2 || $status == -1) { //拒绝 退回冻结奖金
            $commission = $commission_info['commission'];
            $id = $records['commission_id'];
            $freezing_commission = $commission_info['freezing_commission'];
            $new_commission = $commission + abs($records['money']);
            $new_freezing_commission = $freezing_commission - abs($records['money']);
            $sql = "update vsl_store_commission set commission=$new_commission, freezing_commission=$new_freezing_commission where id=$id and commission=$commission and freezing_commission=$freezing_commission";
            $results = Db::execute($sql);
            if ($results <= 0 && $num < 5) {
                #失败重试
                $num += 1;
                return $this->updateCommission($records, $status, $num);
            }
            return $results; //成功返回>0
        }
        if ($status == 4) { //同意打款(线下)(扣除冻结奖金)
            $withdrawals = $commission_info['withdrawals'];
            $id = $records['commission_id'];
            $freezing_commission = $commission_info['freezing_commission'];
            $new_withdrawals = $withdrawals + abs($records['money']);
            $new_freezing_commission = $freezing_commission - abs($records['money']);
            $sql = "update vsl_store_commission set withdrawals=$new_withdrawals, freezing_commission=$new_freezing_commission where id=$id and withdrawals=$withdrawals and freezing_commission=$freezing_commission";
            $results = Db::execute($sql);
            if ($results <= 0 && $num < 5) {
                #失败重试
                $num += 1;
                return $this->updateCommission($records, $status, $num);
            }
            return $results; //成功返回>0
        }
        return 0; //成功返回>0
    }
    /**
     * 删除提现账户
     */
    public function delMemberBankAccount ($account_id, $uid, $assistant_id)
    {
        $member_bank_account = new VslMemberBankAccountModel();
        $retval = $member_bank_account->destroy([
            'uid'          => $uid,
            'assistant_id' => $assistant_id,
            'id'           => $account_id
        ]);
        return $retval;
    }
    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::updateMemberBankAccount()
     */
    public function updateMemberBankAccount (
        $account_id,
        $type,
        $account_number,
        $bank_code,
        $bank_type,
        $bank_username,
        $bank_card,
        $bank_name,
        $mobile,
        $validdate,
        $cvv2,
        $assistant_id
    )
    {
        $member_bank_account = new VslMemberBankAccountModel();
        $member_bank_account->startTrans();
        try {
            if ($bank_code) {
                $bank = new VslBankModel();
                $open_bank = $bank->getInfo(['bank_code' => $bank_code], 'bank_name')['bank_name'];
            } else {
                $open_bank = $bank_name;
            }
            $data = array(
                'realname'       => $bank_username,
                'account_number' => $account_number,
                'type'           => $type,
                'open_bank'      => $open_bank,
                'bank_code'      => $bank_code,
                'bank_card'      => $bank_card,
                'bank_type'      => $bank_type,
                'mobile'         => $mobile,
                'valid_date'     => $validdate,
                'cvv2'           => $cvv2,
                'modify_date'    => time()
            );
            $member_bank_account->save($data,
                [
                    'id' => $account_id
                ]);
            $this->setMemberBankAccountDefault($assistant_id, $account_id);
            $member_bank_account->commit();
            return $account_id;
        } catch (\Exception $e) {
            $member_bank_account->rollback();
            return $e->getMessage();
        }
    }
    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::setMemberBankAccountDefault()
     */
    public function setMemberBankAccountDefault ($assistant_id, $account_id)
    {
        $member_bank_account = new VslMemberBankAccountModel();
        $member_bank_account->update([
            'is_default' => 0
        ],
            [
                'assistant_id' => $assistant_id,
                'is_default'   => 1
            ]);
        $member_bank_account->update([
            'is_default' => 1
        ],
            [
                'assistant_id' => $assistant_id,
                'id'           => $account_id
            ]);
        return $account_id;
    }
}
