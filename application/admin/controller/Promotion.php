<?php
/**
 * Promotion.php
 * 微商来 - 专业移动应用开发商!
 * =========================================================
 * Copyright (c) 2014 广州领客信息科技有限公司, 保留所有权利。
 * ----------------------------------------------
 * 官方网址: http://www.vslai.com
 * 
 * 任何企业和个人不允许对程序代码以任何形式任何目的再发布。
 * =========================================================



 */
namespace app\admin\controller;

use data\service\Addons;
use data\service\Address;
use data\service\Promotion as PromotionService;

/**
 * 营销控制器
 *
 * @author  www.vslai.com
 *        
 */
class Promotion extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }
    /**
     * 营销列表
     */
    public function promotionList()
    {
        if (request()->isAjax()) {
            $search_text = request()->post("search_text");
            $addons = new Addons();
            $list = $addons->getModuleList($search_text);
            return $list;
        }
        return view($this->style . "Promotion/promotionList");
    }
}