<?php
namespace addons\distribution\model;

use data\model\BaseModel as BaseModel;
use tests\thinkphp\library\think\dbTest;
use think\Db;

/**
 * 分销商账户流水表
 * @author  www.vslai.com
 *
 */
class VslDistributorAccountRecordsViewModel extends BaseModel {
    protected $table = 'vsl_distributor_account_records';
 /**
     * 获取列表返回数据格式
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     * @return unknown
     */
    public function getViewList($page_index, $page_size, $condition, $order){
        $queryList = $this->getViewQuery($page_index, $page_size, $condition, $order);
        $queryCount = $this->getViewCount($condition);
        $list = $this->setReturnList($queryList, $queryCount, $page_size);
        return $list;
    }
    /**
     * 获取列表
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     * @return \data\model\multitype:number
     */
    public function getViewQuery($page_index, $page_size, $condition, $order)
    {
        //设置查询视图
        $viewObj = $this->alias('nmar')
        ->join('sys_user su','nmar.uid = su.uid','left')
        ->field('nmar.*, su.nick_name, su.user_name, su.user_tel, su.user_email, su.user_headimg');
        $list = $this->viewPageQuery($viewObj, $page_index, $page_size, $condition, $order);
        return $list;
    }
    /**
     * 获取列表数量
     * @param unknown $condition
     * @return \data\model\unknown
     */
    public function getViewCount($condition)
    {
         $viewObj = $this->alias('nmar')
        ->join('sys_user su','nmar.uid = su.uid','left')
        ->field('nmar.id');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }

    /**
     * 获取列表返回数据格式
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     * @return unknown
     */
    public function getViewList1($page_index, $page_size, $condition){
        $queryList = $this->getViewQuery1($page_index, $page_size, $condition);
        list($queryCount, $total_commission) = $this->getViewCount1($condition);
        $list = $this->setReturnList($queryList, $queryCount, $page_size);
        $list['total_commission'] = $total_commission;
        return $list;
    }
    /**
     * 获取列表
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     * @return \data\model\multitype:number
     */
    public function getViewQuery1($page_index, $page_size, $condition)
    {

        $viewObj = Db::field('uid,data_id,text,create_time,commission,from_type,records_no')
            ->name('vsl_distributor_account_records')
            ->where('from_type', 1)
            ->where('commission', '>', 0)
            ->where('uid', $condition['uid'])
            ->union('SELECT uid,data_id,text,create_time,bonus as commission,from_type,records_no FROM vsl_agent_account_records where from_type=1 and bonus>0 and uid='.$condition['uid'])
            ->buildSql();

        $sql = substr($viewObj, 1);
        $sql = substr($sql, 0, strlen($sql) - 1);
        if ($page_size == 0) {
            $sql = 'select uid,data_id,text,create_time,commission,from_type,records_no from ('.$sql.') as a ORDER BY create_time desc';
        } else {
            $start_row = $page_size * ($page_index - 1);
            $sql = 'select uid,data_id,text,create_time,commission,from_type,records_no from ('.$sql.') as a ORDER BY create_time desc limit '. $start_row .','.$page_size;
        }
        $list = Db::query($sql);
        return $list;
    }
    /**
     * 获取列表数量
     * @param unknown $condition
     * @return \data\model\unknown
     */
    public function getViewCount1($condition)
    {
        $list = Db::field('uid,data_id,text,create_time,commission,from_type,records_no')
            ->name('vsl_distributor_account_records')
            ->where('from_type', 1)
            ->where('commission', '>', 0)
            ->where('uid', $condition['uid'])
            ->union('SELECT uid,data_id,text,create_time,bonus as commission,from_type,records_no FROM vsl_agent_account_records where from_type=1 and bonus>0 and uid='.$condition['uid'])
            ->select();

        $total_commission = 0;
        $num = 0;
        foreach ($list as $item){
            $num++;
            $total_commission += $item['commission'];
        }

        return [$num, $total_commission];
    }

}
