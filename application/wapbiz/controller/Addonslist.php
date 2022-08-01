<?php

namespace app\wapbiz\controller;

use data\service\Addons as AddonsService;

/**
 * 扩展模块控制器
 *
 * @author  www.vslai.com
 *        
 */
class Addonslist extends BaseController
{

    protected $addons;

    public function __construct()
    {
        $this->addons = new AddonsService();
        parent::__construct();
    }

    /**
     * 插件管理
     */
    public function addonsList()
    {
        $search_text = request()->post("search_text");
        $list = $this->addons->getModuleList($search_text);
        return json(['code' => 1, 'message' => '获取成功', 'data' => $list]);
    }

}
