<?php
/**
 * AlbumPictureModel.php
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
use data\model\AlbumPictureModel as AlbumPictureModel;
use think\Db;
use data\model\BaseModel as BaseModel;
/**
 * 图片model
 */
class MaterialModel extends BaseModel {

    protected $table = 'sys_material';
    protected $rule = [
        'pic_id'  =>  '', 
        'pic_tag'  =>  'no_html_parse',
        'pic_name'  =>  'no_html_parse',
        'pic_cover'  =>  'no_html_parse',
        'pic_cover_big'  =>  'no_html_parse',
        'pic_cover_mid'  =>  'no_html_parse',
        'pic_cover_small'  =>  'no_html_parse',
        'pic_cover_micro'  =>  'no_html_parse'
    ];
    protected $msg = [
        'pic_id'  =>  '',
        'pic_tag'  =>  '',
        'pic_name'  =>  '',
        'pic_cover'  =>  '',
        'pic_cover_big'  =>  '',
        'pic_cover_mid'  =>  '',
        'pic_cover_small'  =>  '',
        'pic_cover_micro'  =>  ''
    ];

    /*
     * (non-PHPdoc)
     * @see \data\api\IAlbum::addPicture()
     */
    public function addPicture($pic_name, $pic_tag, $pic_cover, $pic_size, $pic_spec, $pic_cover_big, $pic_size_big, $pic_spec_big, $pic_cover_mid, $pic_size_mid, $pic_spec_mid, $pic_cover_small, $pic_size_small, $pic_spec_small, $pic_cover_micro, $pic_size_micro, $pic_spec_micro, $instance_id, $upload_type, $domain, $bucket, $website_id = 0,$type = 0,$supplier_id=0)
    {
        // TODO Auto-generated method stub
        $data = array(
            'shop_id' => $instance_id,
            'is_wide' => $type,
            'pic_name' => $pic_name,
            'pic_tag' => $pic_tag,
            'pic_cover' => $pic_cover,
            'pic_size' => $pic_size,
            'pic_spec' => $pic_spec,
            'pic_cover_big' => $pic_cover_big,
            'pic_size_big' => $pic_size_big,
            'pic_spec_big' => $pic_spec_big,
            'pic_cover_mid' => $pic_cover_mid,
            'pic_size_mid' => $pic_size_mid,
            'pic_spec_mid' => $pic_spec_mid,
            'pic_cover_small' => $pic_cover_small,
            'pic_size_small' => $pic_size_small,
            'pic_spec_small' => $pic_spec_small,
            'pic_cover_micro' => $pic_cover_micro,
            'pic_size_micro' => $pic_size_micro,
            'pic_spec_micro' => $pic_spec_micro,
            'upload_time' => time(),
            "upload_type" => $upload_type,
            "domain" => $domain,
            "bucket" => $bucket,
            'website_id' => 1,
            'supplier_id' => 0,
        );
        $pic = new MaterialModel();
        $res = $pic->save($data);
        if ($res) {
            return $pic->pic_id;
        } else {
            return $res;
        }
    }

    public function ModifyAlbumPicture($pic_id, $pic_cover, $pic_size, $pic_spec, $pic_cover_big, $pic_size_big, $pic_spec_big, $pic_cover_mid, $pic_size_mid, $pic_spec_mid, $pic_cover_small, $pic_size_small, $pic_spec_small, $pic_cover_micro, $pic_size_micro, $pic_spec_micro, $instance_id, $upload_type, $domain, $bucket,$website_id=0,$supplier_id = 0)
    {
        // TODO Auto-generated method stub
        $data = array(
            'pic_cover' => $pic_cover,
            'pic_size' => $pic_size,
            'pic_spec' => $pic_spec,
            'pic_cover_big' => $pic_cover_big,
            'pic_size_big' => $pic_size_big,
            'pic_spec_big' => $pic_spec_big,
            'pic_cover_mid' => $pic_cover_mid,
            'pic_size_mid' => $pic_size_mid,
            'pic_spec_mid' => $pic_spec_mid,
            'pic_cover_small' => $pic_cover_small,
            'pic_size_small' => $pic_size_small,
            'pic_spec_small' => $pic_spec_small,
            'pic_cover_micro' => $pic_cover_micro,
            'pic_size_micro' => $pic_size_micro,
            'pic_spec_micro' => $pic_spec_micro,
            'upload_time' => time(),
            'upload_type' => $upload_type,
            "domain" => $domain,
            "bucket" => $bucket,
            "website_id" => 1,
            "shop_id" => 0,
            "supplier_id" => 0,
        );
        $res = $this->album_picture->save($data, [
            "pic_id" => $pic_id
        ]);
        return $res;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IAlbum::getPictureList()
     */
    public function getPictureList($page_index = 1, $page_size = 0, $condition = '', $order = " upload_time desc", $field = '*')
    {
        $this->album_picture = new MaterialModel();
        $list = $this->album_picture->pageQuery($page_index, $page_size, $condition, $order, $field);
        return $list;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IAlbum::deletePicture()
     */
    public function deletePicture($pic_id_array)
    {
        $this->album_picture = new MaterialModel();
        // TODO Auto-generated method stub
        $shop_id = 0;
        $pic_array = explode(',', $pic_id_array);
        $res = 1;
        if (! empty($pic_array)) {

            // 判断当前图片是否在商品中使用过
            foreach ($pic_array as $pic_id) {
                $condition = array(
                    'shop_id' => $shop_id,
                    'pic_id' => $pic_id
                );
                // 得到当前图片的信息
                $picture_obj = $this->album_picture->getInfo(['pic_id' => $pic_id, 'website_id' => 1],'pic_cover,domain,pic_cover_big,pic_cover_mid,pic_cover_small,pic_cover_micro');
                if (! empty($picture_obj)) {
                    $pic_cover = $picture_obj["pic_cover"];
                    removeImageFile($pic_cover,$picture_obj['domain']);
                    $pic_cover_big = $picture_obj["pic_cover_big"];
                    removeImageFile($pic_cover_big,$picture_obj['domain']);
                    $pic_cover_mid = $picture_obj["pic_cover_mid"];
                    removeImageFile($pic_cover_mid,$picture_obj['domain']);
                    $pic_cover_small = $picture_obj["pic_cover_small"];
                    removeImageFile($pic_cover_small,$picture_obj['domain']);
                    $pic_cover_micro = $picture_obj["pic_cover_micro"];
                    removeImageFile($pic_cover_micro,$picture_obj['domain']);
                }
                $result = $this->album_picture->destroy($condition);
                if (! $result > 0) {
                    $res = - 1;
                }
            }
            unset($pic_id);
        } else {
            $res = - 1;
        }
        if ($res == 1) {
            return SUCCESS;
        } else {
            return DELETE_FAIL;
        }
    }
}