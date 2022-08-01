<?php

namespace app\supplier\controller;

/**
 * 首页控制器
 */
class Error extends BaseController {

    public function _empty($name) {
        $this->redirect(__URL(__URL__ . '/supplier'));
    }

}
