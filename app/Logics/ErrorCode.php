<?php

namespace App\Logics;

class ErrorCode{

    const SUCCESS = 200;//响应成功
    const SIGN_ERROR = 401;//签名验证未通过
    const TOKEN_EXPIRE = 407;//令牌过期

    const OK = 0;//正常
    const ORDER_STATUS_EXCEPTION = 10001;//订单状态异常

}