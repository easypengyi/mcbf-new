<?php

namespace addons\wxmembermsg\model;

use data\model\BaseModel as BaseModel;
use think\Db;

/**
 * 微信会员群发消息模板
 * @author  www.vslai.com
 *
 */
class WxMassMsgTemplateModel extends BaseModel
{
    public $table = 'vsl_weixin_mass_msg_template';
    protected $rule = [
    ];
    protected $msg = [
    ];
    protected $autoWriteTimestamp = true;
    
    public function msg_type()
    {
        return $this->hasOne('WxMassMsgTypeModel', 'id', 'send_type_id');
    }
    
    public function msg_record()
    {
        return $this->hasMany('WxMassMsgRecordModel', 'id','msg_id');
    }
    
    
    /*
     * 群发消息模板列表
     */
    public function getMsgTemplateList($page_index, $page_size, $condition, $order='')
    {
        if (!$order) {
            $primary_key =  $this->getPk();//模型主键
            $order = $primary_key.' DESC';
        }
    
        $queryList = $this->getMsgTemplateViewQuery($page_index, $page_size, $condition, $order);
        $queryCount = $this->getMsgTemplateViewCount($condition);
        $list = $this->setReturnList($queryList, $queryCount, $page_size);
        return $list;
    }
    /*
     * 获取数据
     */
    public function getMsgTemplateViewQuery($page_index, $page_size, $condition, $order)
    {
        $viewObj = $this->alias('mt')
                        ->field('mt.*');
        $list = $this->viewPageQuery($viewObj, $page_index, $page_size, $condition, $order);
        return $list;
    }
    /*
     * 获取数量
     */
    public function getMsgTemplateViewCount($condition)
    {
        $viewObj = $this->alias('mt');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }
    
}