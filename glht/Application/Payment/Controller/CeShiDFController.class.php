<?php

namespace Payment\Controller;

class CeShiDFController extends PaymentController
{
    //代付提交
    public function PaymentExec($data, $config)
    {
        
        return ['status' => 1, 'msg' => '测试通道，申请正常'];
        // return ['status' => 3, 'msg' => '测试通道，申请失败111'];
    }
}
