<?php

namespace App\Interfaces;

interface WmsConstants
{
    //统一常量
    

    //单据状态
    const TEMP = 0; //暂存
    const TEMP_MSG = '暂存';
    const APPROVE = 1; //审核中
    const APPROVE_MSG = '审核中'; 
    const APPROVED = 2; //已审核
    const APPROVED_MSG = '已审核';
    const CONFIRM = 4; //已确认
    const CONFIRM_MSG = '已确认'; 
    const CANCEl = 5; //取消
    const CANCEl_MSG = '已取消';
    const REJECT = 6; //驳回
    const REJECT_MSG = '已驳回'; 
    const WAIT_RECEIVE = 1; //待收货
    const WAIT_RECEIVE_MSG = '待收货'; 
    const RECEIVING = 2; //收获中/部分收货
    const RECEIVING_MSG = '部分收货'; //收获中/部分收货
    const RECEIVED = 3; //已收货
    const RECEIVED_MSG = '已收货';


    //售后
    // const ONLY_REFUND = 1;
    // const ONLY_REFUND_MSG = '仅退款';

    // const REFUNDS = 2;
    // const REFUNDS_MSG = '退货退款';

    // const RECEIVE_NOT = 1;
    // const RECEIVE_NOT_MSG = '未收货';

}
