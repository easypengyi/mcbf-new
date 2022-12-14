<?php
namespace data\service;
/**
 * 商品分类服务层
 */
use data\service\BaseService as BaseService;
use data\model\VslGoodsGroupModel as VslGoodsGroupModel;
use data\model\AlbumPictureModel as AlbumPictureModel;
class GoodsGroup extends BaseService{
    
    private $goods_group;
    function __construct(){
        parent:: __construct();
        $this->goods_group = new VslGoodsGroupModel();
    }
	/* (non-PHPdoc)
     * @see \data\api\IGoodsGroup::getGoodsGroupList()
     */
    public function getGoodsGroupList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $list = $this->goods_group->pageQuery($page_index, $page_size, $condition, $order, $field);
        foreach ($list['data'] as $k=>$v){
            $picture = new AlbumPictureModel();
            $pic_info = array();
            $pic_info['pic_cover'] = '';
            if( !empty($v['group_pic'])){
                $pic_info = $picture->getInfo(['pic_id' =>$v['group_pic']],'pic_cover,pic_cover_mid,pic_cover_micro');
            }
            $list['data'][$k]['picture'] = $pic_info;
        }
        unset($v);
        return $list;
        // TODO Auto-generated method stub
        
    }

	/* (non-PHPdoc)
     * @see \data\api\IGoodsGroup::addOrEditGoodsGroup()
     */
    public function addOrEditGoodsGroup($group_id, $shop_id, $group_name, $pid, $is_visible, $sort, $group_pic)
    {
    	if($pid == 0){
    		$level = 1;
    	}else{
    		$level = $this->getGoodsGroupDetail($pid)['level'] + 1;
    	}
        $data = array(
            'shop_id'	=> $shop_id,
            'website_id'	=> $this->website_id,
            'group_name'   	=> $group_name,
            'pid'             => $pid,
            'level'           => $level,
            'is_visible'      => $is_visible,
            'sort'            => $sort,
            'group_pic'       => $group_pic
        );
		if($group_id == 0){
			$this->goods_group->save($data);
			$data['group_id'] = $this->goods_group->group_id;
			hook("goodsGroupSaveSuccess", $data);
			$res = $this->goods_group->group_id;
		}else{
			$res = $this->goods_group->save($data,['group_id'=>$group_id]);
			$data['group_id'] = $group_id;
			hook("goodsGroupSaveSuccess", $data);
		}
        // TODO Auto-generated method stub
        return $res;
    }

	/* (non-PHPdoc)
     * @see \data\api\IGoodsGroup::deleteGoodsGroup()
     */
    public function deleteGoodsGroup($goods_group_id_array, $shop_id)
    {
    	$sub_list = $this->getGoodsGroupListByParentId($goods_group_id_array, $shop_id);
        if (! empty($sub_list)) {
            $res = SYSTEM_DELETE_FAIL;
        } else {
            $shop_id = $this->instance_id;
            $condition = array(
                'shop_id' => $this->instance_id,
                'group_id' => array('in', $goods_group_id_array)
            );
            $res = $this->goods_group->destroy($condition);
            hook("goodsGroupDeleteSuccess", ['group_id' => $goods_group_id_array]);
        }
        return $res;
        // TODO Auto-generated method stub
        
    }
    /**
     * 返回 二级的列表 
     */
    public function getGoodsGroupQuery($shop_id){
        //一级
        $list = $this->getGoodsGroupListByParentId($shop_id, 0);
        foreach ($list as $k=>$v){
            $child_list = array();
            $child_list = $this->getGoodsGroupListByParentId($shop_id, $v['group_id']);
            $v['child_list'] = $child_list;
        }
        unset($v);
        return $list;
    }
    /**
     * (non-PHPdoc)
     * @see \data\api\IGoodsGroup::getGoodsGroupListByParentId()
     */
	public function getGoodsGroupListByParentId($shop_id, $pid)
    {
        $condition = array(
            'website_id' => $this->website_id,
            'shop_id' => $shop_id,
            'pid'     => $pid
        );
       $list = $this->getGoodsGroupList(1, 0, $condition, 'pid,sort');
       foreach ($list['data'] as $k=>$v){
           $picture = new AlbumPictureModel();
           $pic_info = array();
           $pic_info['pic_cover'] = '';
           if( !empty($v['group_pic'])){
               $pic_info = $picture->get($v['group_pic']);
           }
           $v['picture'] = $pic_info;
       }
       unset($v);
       return $list['data'];
    }
    /**
     * 
     * @param unknown $group_id
     * @return Ambigous <\think\static, multitype:, \think\db\false, PDOStatement, string, \think\Model, \PDOStatement, \think\db\mixed, multitype:a r y s t i n g Q u e \ C l o , \think\db\Query, NULL>
     */
	public function getGoodsGroupDetail($group_id)
    {
        $info = $this->goods_group->get($group_id);
        $picture = new AlbumPictureModel();
        $pic_info = array();
        $pic_info['pic_cover'] = '';
        if( !empty($info['group_pic'])){
            $pic_info = $picture->get($info['group_pic']);
        }
        $info['picture'] = $pic_info;
        return $info;
        // TODO Auto-generated method stub
        
    }
    /**
     * (non-PHPdoc)
     * @see \data\api\IGoodsGroup::getGroupGoodsTree()
     */
    public function getGroupGoodsTree($shop_id)
    {
        $goodsSer = new Goods();
        $list = $this->goods_group->getQuery(['shop_id' => $shop_id,'website_id' => $this->website_id], '*', '');
        $goods_list = $goodsSer->getGoodsListByCondition(['shop_id' => $shop_id,'website_id' => $this->website_id], '*');
        foreach ($list as $k => $v)
        {
            $group_goods_list = array();
            foreach ($goods_list as $k_goods => $v_goods)
            {
            $group_id_array = explode(',', $v_goods['group_id_array']);
                if (in_array($v['group_id'], $group_id_array) || $v['group_id'] == 0) {
                    $v_goods['picture_info'] = $v_goods['album_picture'];
                    $group_goods_list[] = $v_goods;
                }
            }
            unset($v_goods);
            $list[$k]['goods_list'] = $group_goods_list;
            $list[$k]['goods_list_count'] = count($group_goods_list);
        }
        unset($v);
        return $list;
    }
	/* (non-PHPdoc)
     * @see \data\api\IGoodsGroup::getGoodsGroupQueryList()
     */
    public function getGoodsGroupQueryList($condition)
    {
        // TODO Auto-generated method stub
        $res = $this->goods_group->getQuery($condition, "*", "sort");
        return $res;
    }

    
}