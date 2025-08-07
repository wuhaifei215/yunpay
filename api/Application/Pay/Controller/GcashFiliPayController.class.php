<?php

namespace Pay\Controller;

class GcashFiliPayController extends PayController
{
    private $code = '';
    private $query_url='https://gw01.ckogway.com/api/coin/pay/checkOrder';

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
            'title' => 'Gcash唤醒-FiliPay',
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
        
        $native = array(
            'clientCode' => $return['mch_id'],
            'chainName' => 'BANK',
            'coinUnit' =>'PHP',
            'clientNo' => $return['orderid'],
            'memberFlag' => randpw(8,'CHAR'),
            'requestAmount' => sprintf('%.2f', $return['amount']),
            'requestTimestamp' => $this->getMillisecond(),
            'callbackurl' => $return["notifyurl"],
            'hrefbackurl' =>$pay_callbackurl,        //支付后重定向地址
            'toPayQr' => '0',
            'dataType' =>'PAY_PAGE',
            'channel' => 'GCASH_NATIVE',        //取以下三种通道值:QRPH_SCAN,GCASH_NATIVE,MAYA_NATIVE
        );
        $native['sign'] = md5($native['clientCode']."&".$native['chainName']."&".$native['coinUnit']."&".$native['clientNo']."&".$native['requestTimestamp'].$return['signkey']);
        log_place_order($this->code, $return['orderid'] . "----提交", json_encode($native, JSON_UNESCAPED_UNICODE));    //日志
        log_place_order($this->code, $return['orderid'] . "----提交地址", $return['gateway']);    //日志
                
        // 记录初始执行时间
        $beginTime = microtime(TRUE);
        
        $returnContent = $this->http_post_json($return['gateway'], $native);
        log_place_order($this->code, $return['orderid'] . "----返回", $returnContent);    //日志
        $ans = json_decode($returnContent, true);
        if($ans['success'] === true){
            if($array['userid'] == 2){
                echo '<script type="text/javascript">window.location.href="' . $ans['data']['payUrl'] . '"</script>';
            }else{
                $return_arr = [
                    'status' => 'success',
                    'H5_url' => $ans['data']['payUrl'],
                    'QRcode' => $ans['data']['payUrl'],
                    'pay_orderid' => $orderid,
                    'out_trade_id' => $return['orderid'],
                    'amount' => $return['amount'],
                    'datetime' => date('Y-m-d')
                ];
            }
        }else{
            $return_arr = [
                'status' => 'error',
                'msg' => $ans['message'], 
            ];
        }
        echo json_encode($return_arr);
        
        // if($array['userid'] == 2){
            try{
                $redis = $this->redis_connect();
                $userpost = $redis->get('userpost_' . $return['out_trade_id']);
                $userpost = json_decode($userpost,true);
                
                logApiAddReceipt('下游商户提交YunPay', __METHOD__, $return['orderid'], $return['out_trade_id'], '/', $userpost, $return_arr, '0', '0', '1', '2');
                
                // 结束并输出执行时间
                $endTime = microtime(TRUE);
                $doTime = floor(($endTime-$beginTime)*1000);
                logApiAddReceipt('YunPay订单提交上游FiliPay', __METHOD__, $return['orderid'], $return['out_trade_id'], $return['gateway'], $native, $ans, $doTime, '0', '1', '2');
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
        $orderid = $_REQUEST['clientNo'];
        //log_place_order($this->code . '_notifyserver', $orderid . "----异步回调报文头", json_encode($_SERVER));    //日志
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
        
        $sign_arr = [
            'clientCode' => $result['clientCode'],
            'clientNo' => $result['clientNo'],
            'orderNo' => $result['orderNo'],
            'payAmount' => $result['payAmount'],
            'status' => $result['status'],
            'txid' => $result['txid'],
        ];
        $sign = $this->get_sign($sign_arr,$orderList['key']);
        
        log_place_order($this->code . '_notifyurl', $orderid . "----签名串", $sign_str);    //日志
        if ($sign == $result["sign"]) {
            if($result['status'] == 'PAID'){     // PAID 表示转账成功, FINISH 表示收到商户回调的"ok"字符串
                $re = $this->EditMoney($orderList['pay_orderid'], $this->code, 0);
                if ($re !== false) {
                    log_place_order($this->code . '_notifyurl', $orderid . "----回调上游", "成功");    //日志
                }else{
                    log_place_order($this->code . '_notifyurl', $orderid . "----回调上游", "失败");    //日志
                }
            }else{
                log_place_order($this->code . '_notifyurl', $orderid . "----订单状态异常", $result['status']);    //日志
            }
        } else {
            log_place_order($this->code . '_notifyurl', $orderid . "----签名错误，加密后", $sign);    //日志
        }
        $json_result = "ok";
        echo $json_result;
        try{
            logApiAddNotify($orderid, 0, $_REQUEST, $json_result);
        }catch (\Exception $e) {
            // var_dump($e);
        }
    }

    //订单查询
    // public function queryOrder($orderid, $memberid, $key)
    // {
    //     $native = [
    //         'clientCode' => $memberid,
    //         'clientNo' => $orderid,
    //     ];
    //     $native['sign'] = $this->sign($native, $key);
    //     // log_place_order($this->code. '_queryOrder', $orderid . "----查单提交", json_encode($native));    //日志
    //     $returnContent = $this->http_get_json($this->query_url, $native);
    //     log_place_order($this->code. '_queryOrder', $orderid . "----查单返回", $returnContent);    //日志
    //     $ans = json_decode($returnContent, true);
    //     if ($ans['code'] == "200") {
    //         return 1;
    //     } else {
    //         return $returnContent;
    //     }
    // }

    //发送post请求，提交json字符串
    private function http_post_json($url, $postData)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $response = curl_exec($ch);
//        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $response;
    }
        
    //发送get请求，提交json字符串
    private function http_get_json($url, $native=[])
    {
        if(!empty($native)){
            $url = $url . "?" . http_build_query($native);
        }
        $data = '';
        if (!empty($url)) {
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30); //30秒超时
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                //curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_jar);
                if (strstr($url, 'https://')) {
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
                }
                $data = curl_exec($ch);
                curl_close($ch);
            } catch (Exception $e) {
                $data = '';
            }
        }
        return $data;
    }
    
    /**
     * 签名算法
     * @param $data         请求数据
     * @param $md5Key       md5秘钥
     */
    private function get_sign($sign_arr, $key)
    {
        // $sign_arr = array_filter($sign_arr);
        $sign_str =implode('&',$sign_arr);
        $sign_str = $sign_str . $key;
        $sign = md5($sign_str);
        log_place_order($this->code, "----签名", $sign_str);    //日志
        return $sign; //最终的签名

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
