<?php

namespace addons\invoice\model;

use data\model\BaseModel as BaseModel;

/**
 * 商品导入token表
 * @author  www.vslai.com
 *
 */
class VslInvoiceConfigModel extends BaseModel
{
    public $table = 'vsl_invoice_config';
    protected $rule = [
    ];
    protected $msg = [
    ];
    protected $autoWriteTimestamp = true;

    public function invoice()
    {
        return $this->hasMany('VslInvoiceModel');
    }
}