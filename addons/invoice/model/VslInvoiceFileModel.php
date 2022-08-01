<?php

namespace addons\invoice\model;

use data\model\BaseModel as BaseModel;

/**
 * 发票批量上传表
 * @author  www.vslai.com
 *
 */
class VslInvoiceFileModel extends BaseModel
{

    public $table = 'vsl_invoice_file';
    protected $rule = [
    ];
    protected $msg = [
    ];
    protected $autoWriteTimestamp = true;
}