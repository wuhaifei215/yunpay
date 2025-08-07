<?php

namespace Pay\Controller;

class CeShiController extends PayController
{
    private $code = '';
    private $query_url='';

    public function __construct()
    {
        $matches = [];
        preg_match('/([\da-zA-Z\_]+)Controller$/', __CLASS__, $matches);
        $this->code = $matches[1];
    }

    //支付
    public function Pay($array)
    {
        // $pay_amount = I('request.pay_amount');
        // if ($pay_amount < 100 || $pay_amount > 500000) {
        //     die("金额不匹配，仅支持100~50000元充值");
        // }
        $orderid = I("request.pay_orderid", '');
        $body = I('request.pay_productname', '');
        $pay_callbackurl = I('request.pay_callbackurl', '');
        $parameter = [
            'code' => $this->code,
            'title' => '测试通道',
            'exchange' => 1, // 金额比例
            'gateway' => "",
            'orderid' => '',
            'out_trade_id' => $orderid, //外部订单号
            'channel' => $array,
            'body' => $body,
        ];
        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);
        //如果生成错误，自动跳转错误页面
        $return["status"] == "error" && $this->showmessage($return["errorcontent"]);
        
        $return_arr = [
            'status' => 'success',
            'H5_url' => $this->_site . '/Pay_CeShi_Success.html',
            'pay_orderid' => $orderid,
            'out_trade_id' => $return['orderid'],
            'amount' => $return['amount'],
        ];
        echo json_encode($return_arr);
        // if($array['userid'] == 2){
            $return['gateway'] = $this->_site . '/Pay_CeShi_Success.html';
            try{
                
                logApiAddReceipt('下游商户提交YunPay', $array['userid'], __METHOD__, $return['orderid'], $return['out_trade_id'], $return['gateway'], $return, $return_arr);
            }catch (\Exception $e) {
                var_dump($e);
            }
        // }
    }
    
    public function Success(){
        
        echo '对接成功！请联系运营切换正式通道';
    }
}
