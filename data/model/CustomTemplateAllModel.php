<?php
/**
 * CustomTemplateAllModel.php
 *
 * 微商来 - 专业移动应用开发商!
 * =========================================================
 * Copyright (c) 2014 广州领客信息科技有限公司, 保留所有权利。
 * ----------------------------------------------
 * 官方网址: http://www.vslai.com
 * 
 * 任何企业和个人不允许对程序代码以任何形式任何目的再发布。
 * =========================================================



 */
namespace data\model;

use data\model\BaseModel as BaseModel;
use think\Db;

/**
 * 手机端自定义模板表
 */
class CustomTemplateAllModel extends BaseModel
{

    protected $table = 'sys_custom_template_all';

    protected $rule = [
        'id' => '',
        'template_data' => 'no_html_parse'
    ];
    protected $msg = [
        'id' => '',
        'template_data' => ''
    ];
    protected $autoWriteTimestamp = true;
    
    /**
     * 查询装修主题数据
     */
    public function getThemeInfo ($condition, $field="*")
    {
        return Db::name('sys_custom_template_theme')->where($condition)->field($field)->find();
    }
    
    public function saveThemeInfo ($data, $condition=[])
    {
        
        if ($condition) {
            $info = Db::name('sys_custom_template_theme')->where($condition)->find();
            if ($info) {
                return Db::name('sys_custom_template_theme')->where($condition)->update($data);
            }
        }
        $data = array_merge($data, $condition);
        return Db::name('sys_custom_template_theme')->insert($data);
    }
}