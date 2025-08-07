<?php

namespace Pay\Controller;

class Vn8VNBankScanController extends PayController
{
    private $code = '';

    public function __construct()
    {
        parent::__construct();
        $matches = [];
        preg_match('/([\da-zA-Z\_]+)Controller$/', __CLASS__, $matches);
        $this->code = $matches[1];
    }

    //jazzcash支付
    public function Pay($array)
    {
        $orderid = I("request.pay_orderid", '');
        $body = I('request.pay_productname', '');
        $pay_callbackurl = I('request.pay_callbackurl', '');
        $pay_userId = I('request.pay_userId', '');
        $parameter = [
            'code' => $this->code,
            'title' => '银行扫码--Vn8Pay',
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
            'amount' => sprintf('%.2f', $return['amount']) * 100,
            'app_id' => $return['mch_id'],
            'callback_url' => $return["notifyurl"],
            'order_id' => $return['orderid'],
            'pipe_id' => 110001,
            'timestamp' => time(),
            'user_id' => $pay_userId?:'U'.time(),
        );
        $native['sign'] = $this->get_sign($native, $return['signkey']);
        
        log_place_order($this->code, $return['orderid'] . "----提交", json_encode($native, JSON_UNESCAPED_UNICODE));    //日志
        log_place_order($this->code, $return['orderid'] . "----提交地址", $return['gateway']);    //日志
                
        // 记录初始执行时间
        $beginTime = microtime(TRUE);
        
        $returnContent = $this->http_post_json($return['gateway'], $native);
        log_place_order($this->code, $return['orderid'] . "----返回", $returnContent);    //日志
        $ans = json_decode($returnContent, true);
        if($ans['success'] === true){
            // $re_data = json_decode($ans['data'], true);
            // var_dump($re_data);
            // var_dump($ans['data']);
            // var_dump($ans['data'][0]);
            if($array['userid'] == 2){
                echo '<script type="text/javascript">window.location.href="' . $ans['data']['pay_url'] . '"</script>';
            }else{
                $return_arr = [
                    'status' => 'success',
                    'H5_url' => $ans['data']['pay_url'],
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
        echo json_encode($return_arr,301);
        
        // if($array['userid'] == 2){
            try{
                $redis = $this->redis_connect();
                $userpost = $redis->get('userpost_' . $return['out_trade_id']);
                $userpost = json_decode($userpost,true);
                
                logApiAddReceipt('下游商户提交YunPay', __METHOD__, $return['orderid'], $return['out_trade_id'], '/', $userpost, $return_arr, '0', '0', '1', '2');
                
                // 结束并输出执行时间
                $endTime = microtime(TRUE);
                $doTime = floor(($endTime-$beginTime)*1000);
                logApiAddReceipt('YunPay订单提交上游Vn8Pay', __METHOD__, $return['orderid'], $return['out_trade_id'], $return['gateway'], $native, $ans, $doTime, '0', '1', '2');
            }catch (\Exception $e) {
                // var_dump($e);
            }
        // }
        exit;
    }

    //异步通知
    public function notifyurl()
    {
        $arrayData = json_decode(file_get_contents('php://input'), true);
        //获取报文信息
        $orderid = $arrayData['order_id'];
        // log_place_order($this->code . '_notifyserver', $orderid . "----异步回调报文头", json_encode($_SERVER));    //日志
        log_place_order($this->code . '_notifyurl', $orderid . "----异步回调", json_encode($arrayData, JSON_UNESCAPED_UNICODE));    //日志
        if (!$orderid) return;
        
        
        $result = $arrayData;
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
        if ($check_re !== true) {
            log_place_order($this->code . '_notifyurl', $orderid . "----IP异常", $ip);    //日志
            return;
        }
        
        $sign = $this->get_sign($result,$orderList['key']);
        
        if ($sign === $_SERVER["HTTP_X_AUTHORIZATION_SIGNATURE"]) {
            if($result['status'] === "success"){     //订单状态
                $re = $this->EditMoney($orderList['pay_orderid'], $this->code, 0);
                if ($re !== false) {
                    log_place_order($this->code . '_notifyurl', $orderid . "----回调上游", "成功");    //日志
                }else{
                    log_place_order($this->code . '_notifyurl', $orderid . "----回调上游", "失败");    //日志
                }
            }else{
                log_place_order($this->code . '_notifyurl', $orderid . "----订单状态异常", $result['status']);    //日志
            }
            $json_result = '{"success":true}';
        } else {
            log_place_order($this->code . '_notifyurl', $orderid . "----签名错误，加密后", $sign);    //日志
            
            $json_result = '{"success":false}';
        }
        echo $json_result;
        try{
            logApiAddNotify($orderid, 0, $result, $json_result);
        }catch (\Exception $e) {
            // var_dump($e);
        }
    }

    //发送post请求，提交json字符串
    private function http_post_json($url, $postData)
    {
        $sign = $postData['sign'];
        unset($postData['sign']);
        
        $json = json_encode($postData, JSON_UNESCAPED_UNICODE);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array(
                'X-Authorization-Signature:' . $sign,
                'Content-Type: application/json',
                'Content-Length:'.strlen($json)
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
        
    
    /**
     * 签名算法
     * @param $data         请求数据
     * @param $md5Key       md5秘钥
     */
    private function get_sign($params, $key)
    {
        // 将参数按键排序并拼接成字符串
        ksort($params);
        $sortedParams = implode('&', array_map(
            function($key, $value) {
                return $key . '=' . $value;
            },
            array_keys($params),
            $params
        ));
        
        // 创建 HMAC-SHA256 签名
        $signature = hash_hmac('sha256', $sortedParams, $key);
        // log_place_order($this->code, $orderid . "----签名", $sortedParams);    //日志
        // log_place_order($this->code, $orderid . "----key", $key);    //日志
        return $signature; //最终的签名
    }
}
