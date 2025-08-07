<?php

namespace Payment\Controller;

class CeShiDFController extends PaymentController
{
    //代付提交
    public function PaymentExec($data, $config)
    {
        $return_arr =  ['status' => 1, 'msg' => '测试通道, 申请成功'];
        // $return_arr = ['status' => 3, 'msg' => '测试通道，申请失败'];
        return $return_arr;
        try{
            $redis = $this->redis_connect();
            $userdfpost = $redis->get('userdfpost_' . $data['out_trade_no']);
            $userdfpost = json_decode($userdfpost,true);
            
            logApiAddPayment('下游商户提交YunPay', __METHOD__, $data['orderid'], $data['out_trade_no'], '/', $userdfpost, $return_arr, '0', '0', '1', '2');
        }catch (\Exception $e) {
            // var_dump($e);
        }
    }
    //代付订单查询
    public function PaymentQuery($data, $config)
    {
        $return = ['status' => 2, 'msg' => 'Successed','remark' => 'https://api.yunpay.com'];
        return $return;
    }
}
