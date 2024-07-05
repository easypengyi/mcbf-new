<?php

namespace app\wapapi\controller;

use data\model\MaterialModel;
use data\model\VslMemberCheckLogModel;
use data\service\Config as WebConfig;
use think\Session;

class Home extends BaseController
{

    public function __construct()
    {
        parent::__construct();
        if (!$this->uid) {
            $data['code'] = -1000;
            $data['message'] = '登录信息已过期，请重新登录!';
            if (request()->get('app_test')) {
                $data['user_token'] = $_SERVER['HTTP_USER_TOKEN'];
                $data['session'] = Session::get();
            }

            echo json_encode($data, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function material(){
        $Config = new WebConfig();
        $value = $Config->getConfig(0, 'MATERIAL_SHARE', $this->website_id, 1);
        $album = new MaterialModel();
        $list = $album->getPictureList(0, 0, []);

        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => [
                'title'=> $value['share_title'],
                'name' => $value['share_words'],
                'update_time' => $value['update_time'],
                'lists' => $list
            ]
        ]);
    }

    /**
     * 提交打卡截图
     *
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function saveMemberMaterial(){
        $image_url = request()->post('image_url');
//        $image_url = 'https://mxlmsenna.oss-cn-qingdao.aliyuncs.com/upload/1/avator/17170489944104.png';
        if (empty($image_url)) {
            return json(['code' => 0, 'message' => '截图凭证不能为空']);
        }
        $start_time = strtotime(date('Y-m-d'));
        $info = VslMemberCheckLogModel::where('uid', $this->uid)
            ->where('create_time', '>', $start_time)
            ->find();
        if(!is_null($info)){
            return json(['code' => 0, 'message' => '今日已打卡']);
        }

        $Config = new WebConfig();
        $value = $Config->getConfig(0, 'MATERIAL_SHARE', $this->website_id, 1);
        $data = [
            'image_url'=> $image_url,
            'uid'=> $this->uid,
            'score'=> $value['share_score'],
            'create_time'=> time()
        ];

        VslMemberCheckLogModel::create($data);
        $data['code'] = 1;
        $data['message'] = "提交成功，请耐心等待审核";
        return json($data);
    }

    public function memberMaterial(){
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size') ?: PAGESIZE;
        $model = new VslMemberCheckLogModel();
        $condition = [];
        $condition['uid'] = $this->uid;
        $list = $model->getViewList($page_index, $page_size, $condition, 'create_time DESC');

        $data = [];
        foreach ($list['data'] as $item){
            $status_name = '审核中';
            if($item['status'] == 0){
                $status_name = '审核中';
            }else if($item['status'] == 1){
                $status_name = '审核成功';
            }else if($item['status'] == 2){
                $status_name = '审核失败';
            }
            $item['name'] = '打卡任务';
            $item['status_name'] = $status_name;
            $item['create_time'] = date('Y-m-d H:i:s', $item['create_time']);
            $data[] = $item;
        }

        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => [
                'order_list' => $data,
                'page_count' => $list['page_count'],
                'total_count' => $list['total_count']
            ]
        ]);
    }
}