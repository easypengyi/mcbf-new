<?php

namespace addons\wxmembermsg\model;

use data\model\BaseModel as BaseModel;
use think\Db;

/**
 * 微信会员群发记录表
 * @author  www.vslai.com
 *
 */
class WxMassMsgRecordModel extends BaseModel
{
    public $table = 'vsl_weixin_mass_msg_record';
    protected $rule = [
    ];
    protected $msg = [
    ];
    protected $autoWriteTimestamp = true;
    
    public function msg_template()
    {
        return $this->belongsTo('WxMassMsgTemplateModel', 'msg_id');
    }
    
    /*
     * 群发消息模板列表
     */
    public function getMsgRecordList($page_index, $page_size, $condition, $order='')
    {
        if (!$order) {
            $primary_key =  $this->getPk();//模型主键
            $order = $primary_key.' DESC';
        }
        
        $queryList = $this->getMsgRecordViewQuery($page_index, $page_size, $condition, $order);
        $queryCount = $this->getMsgRecordViewCount($condition);
        $list = $this->setReturnList($queryList, $queryCount, $page_size);
        return $list;
    }
    /*
     * 获取群发记录数据
     */
    public function getMsgRecordViewQuery($page_index, $page_size, $condition, $order)
    {
        $viewObj = $this->alias('mr')
//                        ->join('vsl_weixin_mass_msg_template mt','mt.id=mr.msg_id','left')
//                        ->join('vsl_weixin_mass_msg_type mp','mt.send_type_id=mp.id','left')
                        ->field('mr.*');
        $list = $this->viewPageQuery($viewObj, $page_index, $page_size, $condition, $order);
        return $list;
    }
    /*
     * 获取群发数量
     */
    public function getMsgRecordViewCount($condition)
    {
        $viewObj = $this->alias('mr')
//                    ->join('vsl_weixin_mass_msg_template mt','mt.id=mr.msg_id','left')
//                    ->join('vsl_weixin_mass_msg_type mp','mt.send_type_id=mp.id','left')
                    ->field('mr.*');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }
}