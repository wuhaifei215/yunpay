<?php

namespace Pay\Controller;

class UPISKPayController extends PayController
{
    private $code = '';
    private $query_url='https://api.skpay.app/mcapi/receive/query';

    public function __construct()
    {
        parent::__construct();
        $matches = [];
        preg_match('/([\da-zA-Z\_]+)Controller$/', __CLASS__, $matches);
        $this->code = $matches[1];
    }

    //支付
    public function Pay($array)
    {
        $orderid = I("request.pay_orderid", '');
        $body = I('request.pay_productname', '');
        $pay_callbackurl = I('request.pay_callbackurl', '');
        $parameter = [
            'code' => $this->code,
            'title' => 'UPI-SKPay',
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
        //跳转页面，优先取数据库中的跳转页面
        $_site = ((is_https()) ? 'https' : 'http') . '://' . C("DOMAIN") . '/';
        $site = trim($return['unlockdomain']) ? $return['unlockdomain'] . '/' : $_site;
        
        $head_arr = [
            "method" => 'POST',
            "accessKey" => $return['mch_id'],
            "timestamp" => $this->getMillisecond(),
            "nonce" => randpw(8,'ALL'),
            ];
        $head_arr['sign'] = base64_encode(hash_hmac('sha256', implode('&',$head_arr), $return['signkey'], true));
        
        $native = array(
            'merchantOrderNo' => $return['orderid'],
            'channelCode' => 'ch00379',       //通道代码 UPI: ch00379,通道代码原生：ch00380,通道代码唤醒：ch00381
            'amount' => intval($return['amount'] * 100), 
            'currency' =>'inr',
            'notifyUrl' => $return["notifyurl"],
            'jumpUrl' =>$pay_callbackurl,        //支付后重定向地址

        );

        log_place_order($this->code, $return['orderid'] . "----提交", json_encode($native, JSON_UNESCAPED_UNICODE));    //日志
        log_place_order($this->code, $return['orderid'] . "----提交地址", $return['gateway']);    //日志
                
        // 记录初始执行时间
        $beginTime = microtime(TRUE);
        
        $returnContent = $this->http_post_json($return['gateway'], $native, $head_arr);
        log_place_order($this->code, $return['orderid'] . "----返回", $returnContent);    //日志
        print_r($returnContent);
        // $ans = json_decode($returnContent, true);
        // if($ans['code'] === 200000){
        //     if($array['userid'] == 2){
        //         echo '<script type="text/javascript">window.location.href="' . $ans['data']['payUrl'] . '"</script>';
        //     }else{
        //         $return_arr = [
        //             'status' => 'success',
        //             'H5_url' => $ans['data']['payUrl'],
        //             'QRcode' => $ans['msg'],
        //             'pay_orderid' => $orderid,
        //             'out_trade_id' => $return['orderid'],
        //             'amount' => $return['amount'],
        //             'datetime' => date('Y-m-d')
        //         ];
        //     }
        // }else{
        //     $return_arr = [
        //         'status' => 'error',
        //         'msg' => $ans['msg'], 
        //     ];
        // }
        // echo json_encode($return_arr);
        
        // if($array['userid'] == 2){
            try{
                $redis = $this->redis_connect();
                $userpost = $redis->get('userpost_' . $return['out_trade_id']);
                $userpost = json_decode($userpost,true);
                
                logApiAddReceipt('下游商户提交YunPay', __METHOD__, $return['orderid'], $return['out_trade_id'], '/', $userpost, $return_arr, '0', '0', '1', '2');
                
                // 结束并输出执行时间
                $endTime = microtime(TRUE);
                $doTime = floor(($endTime-$beginTime)*1000);
                logApiAddReceipt('YunPay订单提交上游SKPay', __METHOD__, $return['orderid'], $return['out_trade_id'], $return['gateway'], $native, $ans, $doTime, '0', '1', '2');
            }catch (\Exception $e) {
                // var_dump($e);
            }
        // }
        exit;
    }

    //异步通知
    public function notifyurl()
    {
        //获取报文信息
        $orderid = $_REQUEST['merchantOrderNo'];
        log_place_order($this->code . '_notifyserver', $orderid . "----异步回调报文头", json_encode($_SERVER));    //日志
        log_place_order($this->code . '_notifyurl', $orderid . "----异步回调", json_encode($_REQUEST, JSON_UNESCAPED_UNICODE));    //日志
        if (!$orderid) return;
        
        
        $result = $_REQUEST;
        //过滤数据，防SQL注入
        // $check_data = sqlInj($result);
        // if ($check_data === false) return;
        $OrderModel = D('Order');
        $date = date('Ymd',strtotime(substr($orderid, 0, 8)));  //获取订单日期
        $tablename = $OrderModel->getRealTableName($date);

        $orderList = $OrderModel->table($tablename)->where(['pay_orderid' => $orderid])->find();
        if (!$orderList) return;
        
        //验证IP白名单
        if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = getRealIp();
        }
        $check_re = check_IP($orderList['channel_id'], $ip, $orderid);
        if ($check_re !== true) return;

        $head_arr = [
            "method" => $_SERVER['HTTP_METHOD'],
            "accessKey" => $_SERVER['HTTP_ACCESSKEY'],
            "timestamp" => $_SERVER['HTTP_TIMESTAMP'],
            "nonce" => $_SERVER['HTTP_NONCE'],
            ];
        $sign = base64_encode(hash_hmac('sha256', implode('&',$head_arr), $orderList['key'], true));
        if ($sign == $_SERVER["HTTP_SIGN"]) {
            if($result['status'] === 'success'){     //订单状态 created：已創建,paying：支付中,timeout：超時,success：成功,failure：失敗,cancel：取消支付,exception：異常
                $re = $this->EditMoney($orderList['pay_orderid'], $this->code, 0);
                if ($re !== false) {
                    log_place_order($this->code . '_notifyurl', $orderid . "----回调上游", "成功");    //日志
                }else{
                    log_place_order($this->code . '_notifyurl', $orderid . "----回调上游", "失败");    //日志
                }
                $json_result = "success";
            }else{
                $json_result = "status error";
                log_place_order($this->code . '_notifyurl', $orderid . "----订单状态异常", $result['status']);    //日志
            }
        } else {
            log_place_order($this->code . '_notifyurl', $orderid . "----签名错误，加密后", $sign);    //日志
            $json_result = "fail";
        }
        echo $json_result;
        try{
            logApiAddNotify($orderid, 0, $_REQUEST, $json_result);
        }catch (\Exception $e) {
            // var_dump($e);
        }
    }

    //订单查询
    public function queryOrder($orderid, $memberid, $key)
    {
        $head_arr = [
            "method" => 'POST',
            "accessKey" => $return['mch_id'],
            "timestamp" => $this->getMillisecond(),
            "nonce" => randpw(8,'ALL'),
            ];
        $head_arr['sign'] = base64_encode(hash_hmac('sha256', implode('&',$head_arr), $return['signkey'], true));
        
        $native = [
            'merchantOrderNo' => $orderid,
        ];
        $native['sign'] = $this->sign($native, $key);
        // log_place_order($this->code. '_queryOrder', $orderid . "----查单提交", json_encode($native));    //日志
        $returnContent = $this->http_post_json($this->query_url, $native, $head_arr);
        log_place_order($this->code. '_queryOrder', $orderid . "----查单返回", $returnContent);    //日志
        $ans = json_decode($returnContent, true);
        if ($ans['code'] === 200000 && $ans['data']['status']==='success') {
            return 1;
        } else {
            return $returnContent;
        }
    }

    //发送post请求，提交json字符串
    private function http_post_json($url, $postData, $head_arr)
    {
        $json = json_encode($postData, JSON_UNESCAPED_UNICODE);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'accessKey:' . $head_arr['accessKey'],
                'timestamp:' . $head_arr['timestamp'],
                'nonce:' . $head_arr['nonce'],
                'sign:' . $head_arr['sign'],
                // 'Content-Type: application/json',
                // 'Content-Length:'.strlen($json)
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
    
    /**
     * 获取毫秒级别的时间戳
     */
    private static function getMillisecond()
    {
        //获取毫秒的时间戳
        $time = microtime(true);
        $milliseconds = round($time * 1000);
        
        return $milliseconds;
    }
}
