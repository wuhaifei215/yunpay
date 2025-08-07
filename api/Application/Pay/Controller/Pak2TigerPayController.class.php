<?php

namespace Pay\Controller;

class Pak2TigerPayController extends PayController
{
    private $code = '';

    public function __construct()
    {
        parent::__construct();
        $matches = [];
        preg_match('/([\da-zA-Z\_]+)Controller$/', __CLASS__, $matches);
        $this->code = $matches[1];
    }

    //easypaisa支付
    public function Pay($array)
    {
        $orderid = I("request.pay_orderid", '');
        $body = I('request.pay_productname', '');
        $pay_callbackurl = I('request.pay_callbackurl', '');
        $extends = json_decode(base64_decode(I("request.pay_extends", '')),true);
        $parameter = [
            'code' => $this->code,
            'title' => 'Pak2-TigerPay',
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
            'outTradeId' => $return['orderid'],
            'amount' => sprintf('%.2f', $return['amount']),
            'productCode' => '7002',
            'merchantId' => $return['mch_id'],
            'ip' => '127.0.0.1',
            'notifyUrl' => $return["notifyurl"],
        );
        $native['sign'] = $this->sign($native, $return['signkey']);
        $native['phone'] = $extends['phone']?:'13095342424';
        $native['attach'] = json_encode(['cnic'=>$extends['cnic']]);
        
        log_place_order($this->code, $return['orderid'] . "----提交", json_encode($native, JSON_UNESCAPED_UNICODE));    //日志
        log_place_order($this->code, $return['orderid'] . "----提交地址", $return['gateway']);    //日志
                
        // 记录初始执行时间
        $beginTime = microtime(TRUE);
        
        $returnContent = $this->http_post_json($return['gateway'], $native);
        log_place_order($this->code, $return['orderid'] . "----返回", $returnContent);    //日志
        $ans = json_decode($returnContent, true);
        if($ans['code'] === 200){
            if($array['userid'] == 2){
                echo '<script type="text/javascript">window.location.href="' . $ans['data']['payUrl'] . '"</script>';
            }else{
                $return_arr = [
                    'status' => 'success',
                    'H5_url' => $ans['data']['payUrl'],
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
                logApiAddReceipt('YunPay订单提交上游TigerPay', __METHOD__, $return['orderid'], $return['out_trade_id'], $return['gateway'], $native, $ans, $doTime, '0', '1', '2');
            }catch (\Exception $e) {
                // var_dump($e);
            }
        // }
        exit;
    }

    //异步通知
    public function notifyurl()
    {
        unset($_REQUEST['PHPSESSID']);
        //获取报文信息
        $orderid = $_REQUEST['outTradeId'];
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
        
        $sign = $this->sign($result,$orderList['key']);
        
        if ($sign == $result["sign"]) {
            if($result['orderStatus'] == "1"){     //订单状态 0 未支付 1 已支付 2订单超时 3下单异常
                $re = $this->EditMoney($orderList['pay_orderid'], $this->code, 0);
                if ($re !== false) {
                    log_place_order($this->code . '_notifyurl', $orderid . "----回调上游", "成功");    //日志
                }else{
                    log_place_order($this->code . '_notifyurl', $orderid . "----回调上游", "失败");    //日志
                }
            }else{
                log_place_order($this->code . '_notifyurl', $orderid . "----订单状态异常", $result['opstate']);    //日志
            }
            $json_result = "success";
        } else {
            log_place_order($this->code . '_notifyurl', $orderid . "----签名错误，加密后", $sign);    //日志
            $json_result = "sign error";
        }
        echo $json_result;
        try{
            logApiAddNotify($orderid, 0, $_REQUEST, $json_result);
        }catch (\Exception $e) {
            // var_dump($e);
        }
    }

    //发送post请求，提交json字符串
    private function http_post_json($url, $postData)
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
                'Content-Type: application/json',
                'Content-Length:'.strlen($json)
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
        
    // //发送get请求，提交json字符串
    // private function http_get_json($url, $native=[])
    // {
    //     if(!empty($native)){
    //         $url = $url . "?" . http_build_query($native);
    //     }
    //     $data = '';
    //     if (!empty($url)) {
    //         try {
    //             $ch = curl_init();
    //             curl_setopt($ch, CURLOPT_URL, $url);
    //             curl_setopt($ch, CURLOPT_HEADER, false);
    //             curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //             curl_setopt($ch, CURLOPT_TIMEOUT, 30); //30秒超时
    //             curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    //             //curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_jar);
    //             if (strstr($url, 'https://')) {
    //                 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
    //                 curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    //             }
    //             $data = curl_exec($ch);
    //             curl_close($ch);
    //         } catch (Exception $e) {
    //             $data = '';
    //         }
    //     }
    //     return $data;
    // }
    
    /**
     * 签名算法
     * @param $data         请求数据
     * @param $md5Key       md5秘钥
     */
    private function sign($param, $key)
    {
        $signPars = "";
        ksort($param);
        $param['key'] = $key;
        foreach ($param as $k => $v) {
            if ("" != $v && "sign" != $k) {
                $signPars .= $k . "=" . $v . "&";
            }
        }
        $sign =strtoupper(md5(rtrim($signPars,'&')));
        // log_place_order($this->code, $orderid . "----签名", rtrim($signPars,'&'));    //日志
        return $sign; //最终的签名
    }
}
