<?php

/**
 * 错误的定义参数都要改成加上 ‘-’ 负号！
 */
//基础变量定义
define('SUCCESS', 1);
define('SUCCESS_WX', 0);
define('FAIL', -1);
# 微信公众号
define('ACCESS_TOKEN_INVALID', -40001);
define('USER_OPENID_NOT_FIND', -40003);
define('INVALID_APPID', -40013);
define('INVALID_TEMPLATE_ID', -40037);
define('INVALID_APPSECRET', -40125);
define('ACCESS_TOKEN_MISSING', -41001);
define('INVALID_IP', -40164);
define('ACCESS_TOKEN_EXPIRED', -42001);
define('USER_REFUSE_TO_ACCEPT_THE_MSG', -43101);
define('REACH_MAX_API_DAILY_QUOTA_LIMIT', -45009);
define('ARGUMENT_INVALID', -47003);
define('ACCESS_CLIENTIP_IS_NOT_REGISTERED', -61004);
define('API_IS_UNAUTHORIZED_TO_COMPONENT', -61007);
define('INVOICE_PDF_ERROR', -72040);
define('INVOICE_ORDER_NEVER_AUTH', -72038);
define('INVALID_INVOICE_TITLE', -72017);
define('INVOICE_STATUS_ERROR', -72024);
define('INVOICE_TOKEN_ERROR', -72025);
define('INVOICE_NEVER_SET_PAY_MCH_INFO', -72028);
define('INVALID_PARAMS', -72031);
define('BIZ_REJECT_INSERT', -72035);
define('BILLING_CODE_AND_BILLING_NO_REPEATED', -72042);
define('BIZ_CONTACT_IS_EMPTY', -72063);
define('PDF_BILLING_CODE_OR_PDF_BILLING_NO_IS_ERROR', -72047);
define('MINIPROGRAM_HAS_NO_PERMISSION_TO_PLUGIN', -80082);
define('USER_ALREADY_BIND_TOO_MANY_WEAPPS', -85003);
define('TESTER_USER_ALREADY_BIND', -85004);
define('THIS_NOT_MP_ACCOUNT', -85015);
define('NUMBER_OF_DOMAIN_NAMES_EXCEEDS_THE_LIMIT', -85016);
define('NO_DOMAIN_TO_MODIFY', -85017);
define('DOMAIN_NOT_SET_IN_THE_THIRD_PARTY', -85018);
define('PATH_IN_EXT_JSON_NOT_EXIST', -85045);
define('CANT_FIND_TEMPLATE_DRAFTLIST', -85064);
define('SUBMIT_AUDIT_REACH_LIMIT', -85085);
define('NO_QUOTA_TO_UNDO_CODE', -87013);
define('NOT_SET_MINIPROGRAM_WEBVIEW_DOMAIN', -89020);
define('REQUEST_DOMAIN_IS_NOT_OPENS_WEBVIEW_DOMAIN', -89021);
define('WEBVIEW_DOMAIN_COUNT_OVER_LIMIT', -89029);
define('MP_CONCURRENT_LIMIT', -9402202);
/**
 * 微信错误信息匹配返回
 * @param string $error_code [错误码]
 * @param string $error_info [使用的错误信息]
 * @return mixed|string
 */
function getWXErrorInfo($error_code, $error_info = '')
{
    $system_error_arr = array(
        /*基础变量*/
        SUCCESS => '成功',
        SUCCESS_WX => '成功',
        FAIL => '失败',
        /*微信公众号*/
        ACCESS_TOKEN_INVALID  => 'access_token无效！',
        INVALID_APPSECRET  => '小程序appsecret无效！',
        ACCESS_TOKEN_MISSING  => '缺少 access_token 参数！',
        INVALID_IP  => request()->ip() .'不在白名单内，无效IP',
        ACCESS_CLIENTIP_IS_NOT_REGISTERED  => request()->ip() .'不在白名单内，无效IP',
        API_IS_UNAUTHORIZED_TO_COMPONENT  => '当前小程序未授权给对应的第三方开放平台',
        ACCESS_TOKEN_EXPIRED  => 'access_token已过期!',
        INVOICE_PDF_ERROR  => 'Pdf无效,请提供真实有效的pdf！',
        INVOICE_ORDER_NEVER_AUTH  => '订单没有授权,可能是开票平台appid、商户appid、订单order_id不匹配！',
        INVALID_INVOICE_TITLE  => '发票抬头不一致！',
        INVOICE_STATUS_ERROR  => '发票状态错误！',
        INVOICE_TOKEN_ERROR  => 'wx_invoice_token无效！',
        INVOICE_NEVER_SET_PAY_MCH_INFO  => '未设置微信支付商户信息！',
        INVALID_PARAMS  => '参数错误。可能为请求中包括无效的参数名称或包含不通过后台校验的参数值！',
        BIZ_REJECT_INSERT  => '发票已经被拒绝开票。若order_id被用作参数调用过拒绝开票接口，再使用此order_id插卡机会报此错误！',
        BILLING_CODE_AND_BILLING_NO_REPEATED  => '发票号码和发票代码重复！',
        BIZ_CONTACT_IS_EMPTY  => '商户联系方式未空，请先调用接口设置商户联系方式！',
        USER_REFUSE_TO_ACCEPT_THE_MSG  => '用户拒绝接受消息，如果用户之前曾经订阅过，则表示用户取消了订阅关系！',
        PDF_BILLING_CODE_OR_PDF_BILLING_NO_IS_ERROR  => '发票号码或发票代码错误！',

        /*小程序*/
        MINIPROGRAM_HAS_NO_PERMISSION_TO_PLUGIN => '没有权限使用该插件',
        USER_ALREADY_BIND_TOO_MANY_WEAPPS => '该用户绑定了太多端口！',
        TESTER_USER_ALREADY_BIND => '该用户已经绑定！',
        INVALID_TEMPLATE_ID  => '订阅模板id为空不正确！',
        USER_OPENID_NOT_FIND  => 'openid为空或者不正确！',
        INVALID_APPID  => 'Appid无效！',
        ARGUMENT_INVALID  => '模板参数不准确，可能为空或者不满足规则！',
        THIS_NOT_MP_ACCOUNT  => '该账号不是小程序账号！',
        NUMBER_OF_DOMAIN_NAMES_EXCEEDS_THE_LIMIT  => '域名数量超过限制！',
        NO_DOMAIN_TO_MODIFY  => '请确认（‘客服’或‘素材存储’）域名是否添加到第三方平台的“小程序服务器域名”！',
        DOMAIN_NOT_SET_IN_THE_THIRD_PARTY  => '域名没有在第三方平台设置！',
        PATH_IN_EXT_JSON_NOT_EXIST  => '审核配置路径不正确！',
        CANT_FIND_TEMPLATE_DRAFTLIST  => '找不到模板！',
        SUBMIT_AUDIT_REACH_LIMIT  => '小程序审核次数达到上限, 稍后再试!',
        NO_QUOTA_TO_UNDO_CODE  => '撤回次数达到上限（每天一次，每个月 10 次）！',
        NOT_SET_MINIPROGRAM_WEBVIEW_DOMAIN  => '尚未设置小程序业务域名，请先在第三方平台中设置小程序业务域名后在调用本接口',
        REQUEST_DOMAIN_IS_NOT_OPENS_WEBVIEW_DOMAIN  => '请求保存的域名不是第三方平台中已设置的小程序业务域名或子域名！',
        WEBVIEW_DOMAIN_COUNT_OVER_LIMIT  => '业务域名数量超过限制，最多可以添加100个业务域名！',
        REACH_MAX_API_DAILY_QUOTA_LIMIT  => '接口调用超过限制！',
        MP_CONCURRENT_LIMIT  => '请勿频繁提交，待上一次操作完成后再提交！',
    );

    if (array_key_exists($error_code, $system_error_arr) && !$error_info) {
        return $system_error_arr[$error_code];
    } elseif($error_info) {
        return $error_info;
    }elseif ($error_code >= 0) {
        return '操作成功';
    } else {
        return '操作失败';
    }
}
