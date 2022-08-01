<?php

namespace app\wapbiz\controller;

\think\Loader::addNamespace('data', 'data/');

use data\service\Album as Album;
use data\service\Config as WebConfig;
use data\service\WebSite;
use think\Controller;
/**
 * 图片上传控制器     
 */
use data\service\Upload\AliOss;
use think\Config;
use \think\Session as Session;

class Upload extends Controller
{

    private $return = array();
    // 文件路径
    private $file_path = "";
    // 重新设置的文件路径
    private $reset_file_path = "";
    // 文件名称
    private $file_name = "";
    // 文件大小
    private $file_size = 0;
    // 文件类型
    private $file_type = "";
    private $upload_type = 1;
    private $instance_id = "";
    //是否开启水印设置
    private $watermark = 1;
    //水印透明度
    private $watermark_font = "";
    //水印图片位置
    private $watermark_position = "";
    //水印图片路径
    private $watermark_logo = "";
    // 允许的文件类型
    private $allow_file_ext;

    public function __construct()
    {
        $instanceid = $this->instance_id;
        $config = new WebConfig();
        $this->upload_type = $config->getConfigMaster(0, 'UPLOAD_TYPE', 0, 1) ? : 1;
        $this->allow_file_ext = [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'image/gif', 'image/png', 'image/jpeg', 'image/jpp'],
            'pem' => ['pem', 'application/octet-stream', 'application/x-x509-ca-cert'],
            'crt' => ['crt', 'application/octet-stream', 'application/x-x509-ca-cert'],
            'txt' => ['txt']
        ];
    }

    /**
     * 功能说明：文件(图片)上传(存入相册)
     */
    public function uploadImage()
    {
        $year = date('Y', time());
        $month = date('m', time());
        $day = date('d', time());
        $hour = date('H', time());
        $this->file_path = 'upload/' . Session::get(request()->module() . 'website_id') . '/' . Session::get(request()->module() . 'instance_id') . '/' . $year . '/' . $month . '/' . $day . '/' . $hour . '/';
        if ($this->file_path == "") {
            $this->return['message'] = "文件路径不能为空";
            return $this->ajaxFileReturn();
        }
        // 重新设置文件路径
        $this->resetFilePath();
        // 检测文件夹是否存在，不存在则创建文件夹
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }

        $this->file_name = $_FILES["file"]["name"]; // 文件原名
        $this->file_size = $_FILES["file"]["size"]; // 文件大小
        $this->file_type = $_FILES["file"]["type"]; // 文件类型

        if ($this->file_size == 0) {
            $this->return['message'] = "文件大小为0MB";
            return $this->ajaxFileReturn();
        }
        $validationType = 1;
        // 验证文件
        if (!$this->validationFile($validationType)) {
            return $this->ajaxFileReturn();
        }
        $guid = time() . rand(100, 999);
        $file_name_explode = explode(".", $this->file_name); // 图片名称
        $suffix = count($file_name_explode) - 1;
        $ext = "." . $file_name_explode[$suffix]; // 获取后缀名
        $newfile = $guid . $ext; // 重新命名文件
        // 特殊 判断如果是商品图
        $ok = $this->moveUploadFile($_FILES["file"]["tmp_name"], $this->reset_file_path . $newfile);
        if ($ok["code"]) {
            // 文件上传成功执行下边的操作
                @unlink($_FILES['file']);
                $image_size = getimagesize($ok["path"]); // 获取图片尺寸
                $album = new Album();
                if ($image_size) {
                    $width = $image_size[0];
                    $height = $image_size[1];
                    $name = $file_name_explode[0];
                    $type = request()->post("type", '1,2,3,4');
                    $pic_name = request()->post("pic_name", $guid);
                    $album_id = $_REQUEST['album_id'];
                    if ($album_id == '' || $album_id == 'null') {

                        $album_id = $album->getDefaultAlbumDetail()['album_id'];
                    }
                    $pic_tag = request()->post("pic_tag", $name);
                    $pic_id = intval($_REQUEST['pic_id']) > 0 ? intval($_REQUEST['pic_id']) : '';
                    if ($this->watermark == 2) {
                        $res = $this->uploadThumbFile1($this->reset_file_path . $newfile);
                    } else {
                        $res['path'] = $this->reset_file_path . $newfile;
                    }
                    if ($this->file_type == 'image/x-icon') {
                        $retval = $album->addPicture($pic_name, $pic_tag, $album_id, $ok["path"], $width . "," . $height, $width . "," . $height, $ok["path"], $width . "," . $height, $width . "," . $height, $ok["path"], $width . "," . $height, $width . "," . $height, $ok["path"], $width . "," . $height, $width . "," . $height, $ok["path"], $width . "," . $height, $width . "," . $height, $this->instance_id, $this->upload_type, $ok["domain"], $ok["bucket"]);
                        $this->return['data'] = ['src' => getApiSrc($ok['path']),'pic_id' =>$retval ];
                        $this->return['code'] = 1;
                        $this->return['message'] = "上传成功";
                        return $this->ajaxFileReturn();
                    }

                    // 上传到相册管理，生成四张大小不一的图
                    $retval = $this->photoCreate($this->reset_file_path, $res['path'], "." . $file_name_explode[$suffix], $type, $pic_name, $album_id, $width, $height, $pic_tag, $pic_id, $ok["domain"], $ok["bucket"], $ok["path"]);
                    if ($retval > 0) {
                        $this->return['data'] = ['src' => $ok["path"],'pic_id' =>$retval ];
                        $this->return['code'] = 1;
                        $this->return['message'] = "上传成功";
                    } else {
                        $this->return['message'] = "图片上传失败";
                    }
                } else {

                    // 强制将文件后缀改掉，文件流不同会导致上传文件失败
                    $this->return['message'] = "请检查您的上传参数配置或上传的文件是否有误";
                }
             
            //删除本地的图片
            if ($this->upload_type == 2) {
                @unlink($this->reset_file_path . $newfile);
            }
        } else {
            // 强制将文件后缀改掉，文件流不同会导致上传文件失败
            $this->return['message'] = "图片上传失败";
//            $this->return['message'] = "请检查您的上传参数配置或上传的文件是否有误";
        }
        return $this->ajaxFileReturn();
//        return $this->ajaxFileReturn();
    }

    public function resetFilePath($type = 0)
    {
        switch ($type) {
            case 0:
                $year = date('Y', time());
                $month = date('m', time());
                $day = date('d', time());
                $hour = date('H', time());
                $file_path = 'upload/' . Session::get(request()->module() . 'website_id') . '/' . $year . '/' . $month . '/' . $day . '/' . $hour . '/';
                if (Session::get(request()->module() . 'instance_id')) {
                    $file_path = 'upload/' . Session::get(request()->module() . 'website_id') . '/' . Session::get(request()->module() . 'instance_id') . '/' . $year . '/' . $month . '/' . $day . '/' . $hour . '/';
                }
                break;
            case 1:
                $file_path = 'upload/' . Session::get(request()->module() . 'website_id') . '/mp/';
                if (Session::get(request()->module() . 'instance_id')) {
                    $file_path = 'upload/' . Session::get(request()->module() . 'website_id') . '/' . Session::get(request()->module() . 'instance_id') . '/mp/';
                }
                break;
            case 3:
                $file_path = 'upload/' . Session::get(request()->module() . 'website_id') . '/tl/';
                if (Session::get(request()->module() . 'instance_id')) {
                    $file_path = 'upload/' . Session::get(request()->module() . 'website_id') . '/' . Session::get(request()->module() . 'instance_id') . '/tl/';
                }
                break;
            case 4:
                $file_path = 'upload/' . Session::get(request()->module() . 'website_id') . '/livegift/';
                if (Session::get(request()->module() . 'instance_id')) {
                    $file_path = 'upload/' . Session::get(request()->module() . 'website_id') . '/' . Session::get(request()->module() . 'instance_id') . '/livegift/';
                }
                break;
            case 5:
                $file_path = 'upload/' . Session::get(request()->module() . 'website_id') . '/alipay/';
                if (Session::get(request()->module() . 'instance_id')) {
                    $file_path = 'upload/' . Session::get(request()->module() . 'website_id') . '/' . Session::get(request()->module() . 'instance_id') . '/alipay/';
                }
                break;
            case 6:
                $file_path = 'upload/' . Session::get(request()->module() . 'website_id') . '/apk/';
                if (Session::get(request()->module() . 'instance_id')) {
                    $file_path = 'upload/' . Session::get(request()->module() . 'website_id') . '/' . Session::get(request()->module() . 'instance_id') . '/apk/';
                }
                break;
            default:
                $file_path = $this->file_path;
                break;
        }
        $this->reset_file_path = $file_path;
    }

    /**
     * 上传文件后，ajax返回信息
     * @param array $return            
     */
    private function ajaxFileReturn()
    {
        if (empty($this->return['code']) || null == $this->return['code'] || "" == $this->return['code']) {
            $this->return['code'] = -1; // 错误码
        }

        if (empty($this->return['message']) || null == $this->return['message'] || "" == $this->return['message']) {
            $this->return['message'] = ""; // 消息
        }

        if (empty($this->return['data']) || null == $this->return['data'] || "" == $this->return['data']) {
            $this->return['data'] = ""; // 数据
        }
        return json($this->return);
    }

    /**
     *
     * @param unknown $this->file_path
     *            文件路径
     * @param unknown $this->file_size
     *            文件大小
     * @param unknown $this->file_type
     *            文件类型
     * @return string|unknown|number|\think\false
     */
    private function validationFile($type)
    {
        $flag = true;
        switch ($type) {
            case 1:
                if (($this->file_type != "image/gif" && $this->file_type != "image/png" && $this->file_type != "image/x-icon" && $this->file_type != "image/jpeg") || $this->file_size > 10000000) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过10MB';
                    $flag = false;
                }
                // 公共
                break;
            case 2:
                if (($this->file_type != "image/gif" && $this->file_type != "image/png" && $this->file_type != "image/jpeg") || $this->file_size > 5000000) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过5MB';
                    $flag = false;
                }
                // 微信证书
                break;
            case 3:
                if (!in_array($this->file_type, $this->allow_file_ext['pem']) || $this->file_size > 5000000) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过5MB';
                    $flag = false;
                }
                // 小程序支付证书
                break;
            case 4:
                if ($this->file_type != 'video/mp4' || $this->file_size > 10000000) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过10MB';
                    $flag = false;
                }
                // 视频文件
                break;
            case 5:
                if ($this->file_type != 'application/octet-stream') {
                    $this->return['message'] = '仅支持上传SVGA格式文件';
                    $flag = false;
                }
                // 视频文件
                break;
            case 6:
                if (!in_array($this->file_type, $this->allow_file_ext['crt']) || $this->file_size > 5000000) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过5MB';
                    $flag = false;
                }
                // 小程序支付证书
                break;
        }
        return $flag;
    }


    /**
     * 各类型图片生成
     *
     * @param unknown $photoPath            
     * @param unknown $ext            
     * @param number $type            
     */
    public function photoCreate($upFilePath, $photoPath, $ext, $type, $pic_name, $album_id, $width, $height, $pic_tag, $pic_id, $domain, $bucket, $upload_img)
    {
        $width1 = 0.6 * $width;
        $width2 = 0.4 * $width;
        $width3 = 0.2 * $width;
        $width4 = 0.1 * $width;
        $height1 = 0.6 * $height;
        $height2 = 0.4 * $height;
        $height3 = 0.2 * $height;
        $height4 = 0.1 * $height;
        $photoArray = array(
            "bigPath" => array(
                "path" => '',
                "width" => $width1,
                "height" => $height1,
                'type' => '1'
            ),
            "middlePath" => array(
                "path" => '',
                "width" => $width2,
                "height" => $height2,
                'type' => '2'
            ),
            "smallPath" => array(
                "path" => '',
                "width" => $width3,
                "height" => $height3,
                'type' => '3'
            ),
            "littlePath" => array(
                "path" => '',
                "width" => $width4,
                "height" => $height4,
                'type' => '4'
            )
        );

        $photoArray["bigPath"]["path"] = $upFilePath . md5(time() . $pic_tag) . "1" . $ext;
        $photoArray["middlePath"]["path"] = $upFilePath . md5(time() . $pic_tag) . "2" . $ext;
        $photoArray["smallPath"]["path"] = $upFilePath . md5(time() . $pic_tag) . "3" . $ext;
        $photoArray["littlePath"]["path"] = $upFilePath . md5(time() . $pic_tag) . "4" . $ext;
        // 循环生成4张大小不一的图
        foreach ($photoArray as $k => $v) {
            if (stristr($type, $v['type'])) {
                $result = $this->uploadThumbFile($photoPath, $v["path"], $v["width"], $v["height"]);
                if ($result["code"]) {
                    $photoArray[$k]["path"] = $result["path"];
                } else {
                    return 0;
                }
            }
        }
        $album = new Album();
        if ($pic_id == "") {
            $retval = $album->addPicture($pic_name, $pic_tag, $album_id, $upload_img, $width . "," . $height, $width . "," . $height, $photoArray["bigPath"]["path"], $photoArray["bigPath"]["width"] . "," . $photoArray["bigPath"]["height"], $photoArray["bigPath"]["width"] . "," . $photoArray["bigPath"]["height"], $photoArray["middlePath"]["path"], $photoArray["middlePath"]["width"] . "," . $photoArray["middlePath"]["height"], $photoArray["middlePath"]["width"] . "," . $photoArray["middlePath"]["height"], $photoArray["smallPath"]["path"], $photoArray["smallPath"]["width"] . "," . $photoArray["smallPath"]["height"], $photoArray["smallPath"]["width"] . "," . $photoArray["smallPath"]["height"], $photoArray["littlePath"]["path"], $photoArray["littlePath"]["width"] . "," . $photoArray["littlePath"]["height"], $photoArray["littlePath"]["width"] . "," . $photoArray["littlePath"]["height"], $this->instance_id, $this->upload_type, $domain, $bucket);
        } else {
            $retval = $album->ModifyAlbumPicture($pic_id, $upload_img, $width . "," . $height, $width . "," . $height, $photoArray["bigPath"]["path"], $photoArray["bigPath"]["width"] . "," . $photoArray["bigPath"]["height"], $photoArray["bigPath"]["width"] . "," . $photoArray["bigPath"]["height"], $photoArray["middlePath"]["path"], $photoArray["middlePath"]["width"] . "," . $photoArray["middlePath"]["height"], $photoArray["middlePath"]["width"] . "," . $photoArray["middlePath"]["height"], $photoArray["smallPath"]["path"], $photoArray["smallPath"]["width"] . "," . $photoArray["smallPath"]["height"], $photoArray["smallPath"]["width"] . "," . $photoArray["smallPath"]["height"], $photoArray["littlePath"]["path"], $photoArray["littlePath"]["width"] . "," . $photoArray["littlePath"]["height"], $photoArray["littlePath"]["width"] . "," . $photoArray["littlePath"]["height"], $this->instance_id, $this->upload_type, $domain, $bucket);
            $retval = $pic_id;
        }
        return $retval;
    }

    /**
     * 原图上传(上传到外网的同时,也会在本地生成图片(在缩略图生成使用后会被删除))
     *
     * @param unknown $file_path            
     * @param unknown $key            
     */
    public function moveUploadFile($file_path, $key)
    {
        $ok = @move_uploaded_file($file_path, $key);
        $result = [
            "code" => $ok,
            "path" => $key,
            "domain" => '',
            "bucket" => ''
        ];
        if ($ok) {
            if (strpos($this->file_type, 'image') !== false && $this->file_type != "image/gif" && $this->file_type != "image/png" && $this->file_type != "image/x-icon") {
                (new \data\service\ImgCompress($key, 1))->compressImg($key);
            }

            if ($this->upload_type == 2) {
                $alioss = new AliOss();
                $result = $alioss->setAliOssUplaod($key, $key);
            }
        }
        return $result;
    }

    /**
     * 用户缩略图上传
     * @param unknown $file_path
     * @param unknown $key
     */
    public function uploadThumbFile($photoPath, $key, $width, $height)
    {
        try {
            $image = \think\Image::open($photoPath);
            $image->thumb($width, $height, 1);
            $image->save($key, "jpg");
            unset($image);
            $result = array("code" => true, "path" => $key);
            if (file_exists($key)) {
                (new \data\service\ImgCompress($key, 1))->compressImg($key);
            }
            if ($this->upload_type == 2) {
                $alioss = new AliOss();
                $result = $alioss->setAliOssUplaod($key, $key);
                @unlink($key);
            }
            return $result;
        } catch (\Exception $e) {
            return array("code" => false);
        }
    }

    /**
     * 用户上传水印处理
     * @param unknown $file_path
     * @param unknown $key
     */
    public function uploadThumbFile1($source)
    {
        try {
            $image = \think\Image::open($source);
            $data = $image->water(substr($this->watermark_logo, 1), $this->watermark_position, $this->watermark_font);
            $image->save($source, "png");
            unset($image);
            $result = array("code" => true, "path" => $source);
            if ($this->upload_type == 2) {
                $alioss = new AliOss();
                $result = $alioss->setAliOssUplaod($source, $source);
            }
            return $result;
        } catch (\Exception $e) {
            return array("code" => false);
        }
    }

}
