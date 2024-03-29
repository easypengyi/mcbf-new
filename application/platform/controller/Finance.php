<?php
namespace app\platform\controller;
use data\service\ExcelsExport;
use data\service\ShopAccount;
use data\service\Member as MemberService;
use data\model\VslExcelsModel;
/**
 * 系统模块控制器
 *
 * @author  www.vslai.com
 *
 */
class Finance extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }
    /**
     * 财务对账
     */
    public function financialReconciliation()
    {
        $shopAccount = new ShopAccount();
        if (request()->isPost()) {
            $start_date = request()->post('start_date','');
            $end_date = request()->post('end_date','');
            if($start_date==''){
                $start_date = '2019-1-1';
                $end_date =  '2038-1-1';
            }
            $account_count = $shopAccount->getFinanceCount($start_date,$end_date);
            return $account_count;
        }else{
            $start_date = '2019-1-1';
            $end_date =  '2038-1-1';
            $account_count = $shopAccount->getFinanceCount($start_date,$end_date);
            $this->assign('accountCount', $account_count);
            return view($this->style . 'Finance/financialReconciliation');
        }
    }
    /**
     * 会员积分流水
     */
    public function pointList()
    {
        if (request()->isAjax()) {
            $page_index = request()->post("page_index", 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $search_text = request()->post('search_text', '');
            $records_no = request()->post('records_no','');
            $form_type = request()->post('form_type','');
            $start_date = request()->post('start_date') == "" ? '2010-1-1' : request()->post('start_date');
            $end_date = request()->post('end_date') == "" ? '2038-1-1' : request()->post('end_date');
            $condition['nmar.account_type'] = 1;
            if ($form_type != '') {
                $condition['nmar.from_type'] = $form_type;
            }
            if ($records_no != '') {
                $condition['nmar.records_no'] = $records_no;
            }
            $condition["nmar.create_time"] = [
                [
                    ">",
                    strtotime($start_date)
                ],
                [
                    "<",
                    strtotime($end_date)
                ]
            ];
            $condition['su.nick_name|su.user_tel|su.user_name|su.uid'] = [
                'like',
                '%' . $search_text . '%'
            ];
            $condition['nmar.website_id'] = $this->website_id;
            $member = new MemberService();
            $list = $member->getPointList($page_index, $page_size, $condition, $order = '', $field = '*');
            return $list;
        }
        return view($this->style . 'Finance/pointBill');
    }

    /**
     * 会员余额明细
     */
    public function accountDetail()
    {
        $member = new MemberService();
        $id = request()->get('id','');
        $condition['nmar.id'] = $id;
        $condition['nmar.account_type'] = 2;
        $list = $member->getAccountList(1,0, $condition, $order = '', $field = '*');
        $this->assign('list',$list['data']) ;
        return view($this->style . 'Finance/accountDetail');
    }
    /**
     * 会员积分明细
     */
    public function pointDetail()
    {
        $member = new MemberService();
        $id = request()->get('id','');
        $condition['nmar.id'] = $id;
        $condition['nmar.account_type'] = 1;
        $list = $member->getAccountList(1,0, $condition, $order = '', $field = '*');
        $list['data'][0]['nick_name'] = $list['data'][0]['nick_name']?:($list['data'][0]['user_name']?:$list['data'][0]['user_tel']);
        $this->assign('list',$list['data']) ;
        return view($this->style . 'Finance/pointDetail');
    }
    /**
     * 会员余额流水
     */
    public function accountList()
    {
        if (request()->isAjax()) {
            $member = new MemberService();
            $page_index = request()->post("page_index",1);
            $records_no = request()->post('records_no','');
            $page_size = request()->post('page_size', PAGESIZE);
            $search_text = request()->post('search_text', '');
            $form_type = request()->post('form_type','');
            $start_date = request()->post('start_date') == "" ? '2010-1-1' : request()->post('start_date');
            $end_date = request()->post('end_date') == "" ? '2038-1-1' : request()->post('end_date');
            $condition['nmar.website_id'] = $this->website_id;
            $condition['nmar.account_type'] = 2;
            if ($records_no != '') {
                $condition['nmar.records_no'] = $records_no;
            }
            $condition['su.nick_name|su.user_tel|su.user_name|su.uid'] = [
                'like',
                '%' . $search_text . '%'
            ];
            $condition["nmar.create_time"] = [
                [
                    ">",
                    strtotime($start_date)
                ],
                [
                    "<",
                    strtotime($end_date)
                ]
            ];
            if ($form_type != '') {
                $condition['nmar.from_type'] = $form_type;
            }
            $list = $member->getAccountList($page_index, $page_size, $condition, $order = '', $field = '*');
            return $list;
        }
        return view($this->style . 'Finance/accountList');
    }
    /**
     * 用户积分数据excel导出
     */
    public function pointDataExcel()
    {
        $xlsName = "积分流水列表";
        $xlsCell = [
            0=>['records_no','流水号'],
            1=>['nick_name','会员信息'],
            2=>['type_name','变动类型'],
            3=>['number','变动积分'],
            4=>['create_time','变动时间'],
            5=>['text','备注']
        ];
        $search_text = request()->get('search_text', '');
        $records_no = request()->get('records_no','');
        $form_type = request()->get('form_type','');
        $start_date = request()->get('start_date') == "" ? '2010-1-1' : request()->get('start_date');
        $end_date = request()->get('end_date') == "" ? '2038-1-1' : request()->get('end_date');
        $condition['nmar.account_type'] = 1;
        if ($records_no != '') {
            $condition['nmar.records_no'] = $records_no;
        }
        if ($form_type != '') {
            $condition['nmar.from_type'] = $form_type;
        }
        if($search_text){
            $condition['su.nick_name|su.user_tel|su.user_name'] = [
                'like',
                '%' . $search_text . '%'
            ];
        }
        $condition["nmar.create_time"] = [
            [
                ">",
                strtotime($start_date)
            ],
            [
                "<",
                strtotime($end_date)
            ]
        ];
        $condition['nmar.website_id'] = $this->website_id;
         //edit for 2020/04/26 导出操作移到到计划任务统一执行
         $insert_data = array(
            'type' => 6,
            'status' => 0,
            'exname' => $xlsName,
            'website_id' => $this->website_id,
            'addtime' => time(),
            'ids' => serialize($xlsCell),
            'conditions' => serialize($condition),
        );
        $excels_export = new ExcelsExport();
        $res = $excels_export->insertData($insert_data);
        return $res;
    }
    /**
     * 用户余额数据excel导出
     */
    public function balanceDataExcel()
    {
        $xlsName = "余额流水列表";
        $xlsCell = [
            0=>['records_no','流水号'],
            1=>['nick_name','会员信息'],
            2=>['type_name','变动类型'],
            3=>['number','变动金额'],
            4=>['charge','变动手续费'],
            5=>['create_time','变动时间'],
            6=>['msg','备注']
        ];
        $member = new MemberService();
        $records_no = request()->get('records_no','');
        $search_text = request()->get('search_text', '');
        $form_type = request()->get('form_type');
        $start_date = request()->get('start_date') == "" ? '2010-1-1' : request()->get('start_date');
        $end_date = request()->get('end_date') == "" ? '2038-1-1' : request()->get('end_date');
        if ($records_no != '') {
            $condition['nmar.records_no'] = $records_no;
        }
        $condition['nmar.account_type'] = 2;
        $condition['nmar.website_id'] = $this->website_id;
        if($search_text){
            $condition['su.nick_name|su.user_tel|su.user_name'] = [
                'like',
                '%' . $search_text . '%'
            ];
        }
        $condition["nmar.create_time"] = [
            [
                ">",
                strtotime($start_date)
            ],
            [
                "<",
                strtotime($end_date)
            ]
        ];
        if ($form_type != '') {
            $condition['nmar.from_type'] = $form_type;
        }
        //edit for 2020/04/26 导出操作移到到计划任务统一执行
        $insert_data = array(
            'type' => 5,
            'status' => 0,
            'exname' => $xlsName,
            'website_id' => $this->website_id,
            'addtime' => time(),
            'ids' => serialize($xlsCell),
            'conditions' => serialize($condition),
        );
        $excels_export = new ExcelsExport();
        $res = $excels_export->insertData($insert_data);
        return $res;
    }
    /**
     * 会员分红流水
     */
    public function bonusRecordList()
    {
        $member = new MemberService();
        if (request()->isAjax()) {
            $page_index = request()->post("page_index",1);
            $page_size = request()->post('page_size', PAGESIZE);
            $search_text = request()->post('search_text', '');
            $start_date = request()->post('start_date') == "" ? '2010-1-1' : request()->post('start_date');
            $end_date = request()->post('end_date') == "" ? '2038-1-1' : request()->post('end_date');
            $condition['nmar.website_id'] = $this->website_id;
            $from_type = request()->post('from_type','');
            $bonus_type = request()->post('bonus_type','');
            $records_no = request()->post('records_no','');
            if ($from_type != '') {
                $condition['nmar.from_type'] = $from_type;
            }
            if ($bonus_type != '') {
                $condition['nmar.bonus_type'] = $bonus_type;
            }
            if ($records_no != '') {
                $condition['nmar.records_no'] = $records_no;
            }
            if($search_text){
                $condition['su.nick_name|su.user_tel|su.user_name|su.uid'] = [
                    'like',
                    '%' . $search_text . '%'
                ];
            }
            if($start_date){
                $condition["nmar.create_time"] = ['>', strtotime($start_date)];
            }
            if($end_date){
                $condition["nmar.create_time"] = ['<', strtotime($end_date)];
            }
            $condition['nmar.bonus'] = ['>',0];
            $list = $member->getBonusRecordList($page_index, $page_size, $condition, $order = '', $field = '*');
            return $list;

        }
        $config = $member->getBonusConfig();
        $global_bonus = $config['global_bonus']['globalbonus_status'];
        $area_bonus = $config['area_bonus']['areabonus_status'];
        $team_bonus = $config['team_bonus']['teambonus_status'];
        $this->assign('global_bonus',$global_bonus);
        $this->assign('area_bonus',$area_bonus);
        $this->assign('team_bonus',$team_bonus);
        return view($this->style . 'Finance/bonusRecordList');
    }
    /**
     * 会员分红明细
     */
    public function bonusDetail()
    {
        $member = new MemberService();
        $id = request()->get('id');
        $condition['nmar.id'] = $id;
        $list = $member->getBonusRecordList(1,0, $condition, $order = '', $field = '*');
        $this->assign('list',$list['data']);
        return view($this->style . 'Finance/bonusDetail');
    }
    /**
     * 用户分红数据excel导出
     */
    public function bonusDataExcel()
    {
        $xlsName = "分红流水列表";
        $xlsCell = array(
            array(
                'records_no',
                '流水号'
            ),
            array(
                'data_id',
                '外部交易号'
            ),
            array(
                'nick_name',
                '会员信息'
            ),
            array(
                'bonus_type',
                '分红类型'
            ),
            array(
                'from_type',
                '变动类型'
            ),
            array(
                'bonus',
                '变动金额'
            ),
            array(
                'create_time',
                '变动时间'
            ),
            array(
                'text',
                '备注'
            )
        );
        $search_text = request()->get('search_text', '');
        $records_no = request()->post('records_no','');
        $start_date = request()->get('start_date') == "" ? '2010-1-1' : request()->get('start_date');
        $end_date = request()->get('end_date') == "" ? '2038-1-1' : request()->get('end_date');
        $from_type = request()->get('from_type');
        if ($from_type != '') {
            $condition['nmar.from_type'] = $from_type;
        }
        $bonus_type = request()->get('bonus_type');
        if ($bonus_type != '') {
            $condition['nmar.bonus_type'] = $bonus_type;
        }
        if ($records_no != '') {
            $condition['nmar.records_no'] = $records_no;
        }
        $condition['nmar.website_id'] = $this->website_id;
        if($search_text){
            $condition['su.nick_name|su.user_tel|su.user_name'] = [
                'like',
                '%' . $search_text . '%'
            ];
        }
        $condition["nmar.create_time"] = [
            [
                ">",
                strtotime($start_date)
            ],
            [
                "<",
                strtotime($end_date)
            ]
        ];

        //edit for 2020/04/26 导出操作移到到计划任务统一执行
        $insert_data = array(
            'type' => 11,
            'status' => 0,
            'exname' => $xlsName,
            'website_id' => $this->website_id,
            'addtime' => time(),
            'ids' => serialize($xlsCell),
            'conditions' => serialize($condition),
        );
        $excels_export = new ExcelsExport();
        $res = $excels_export->insertData($insert_data);
        return $res;
    }
    /**
     * 会员美丽分流水
     */
    public function beautifulPointList()
    {
        if (request()->isAjax()) {
            $page_index = request()->post("page_index", 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $search_text = request()->post('search_text', '');
            $records_no = request()->post('records_no','');
            $form_type = request()->post('form_type','');
            $start_date = request()->post('start_date') == "" ? '2010-1-1' : request()->post('start_date');
            $end_date = request()->post('end_date') == "" ? '2038-1-1' : request()->post('end_date');
            $condition['nmar.account_type'] = 3;
            if ($form_type != '') {
                $condition['nmar.from_type'] = $form_type;
            }
            if ($records_no != '') {
                $condition['nmar.records_no'] = $records_no;
            }
            $condition["nmar.create_time"] = [
                [
                    ">",
                    strtotime($start_date)
                ],
                [
                    "<",
                    strtotime($end_date)
                ]
            ];
            $condition['su.nick_name|su.user_tel|su.user_name|su.uid'] = [
                'like',
                '%' . $search_text . '%'
            ];
            $condition['nmar.website_id'] = $this->website_id;
            $member = new MemberService();
            $list = $member->getPointList($page_index, $page_size, $condition, $order = '', $field = '*');
            return $list;
        }
    }
}
