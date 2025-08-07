<?php

namespace Pay\Controller;

class BDbKashACEPayController extends PayController
{
    private $code = '';

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
        $userId = I('request.userId', '');
        $parameter = [
            'code' => $this->code,
            'title' => 'BKash-ACEPay',
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
            'Amount' => $return['amount'],
            'CurrencyId' => 11,
            'IsTest' => false,
            'PayerKey' => $userId?:randpw(8,'CHAR'),         //付款人唯一识别值
            'PaymentChannelId' => 34,
            'ShopInformUrl' => $return["notifyurl"],
            'ShopOrderId' => $return['orderid'],
            'ShopReturnUrl' =>$pay_callbackurl,        //支付后重定向地址
            "ShopUserLongId" => $return['mch_id'],

        );
        $native['EncryptValue'] = $this->get_sign($native, $return['signkey']);
        log_place_order($this->code, $return['orderid'] . "----提交", json_encode($native, JSON_UNESCAPED_UNICODE));    //日志
        // log_place_order($this->code, $return['orderid'] . "----header", json_encode($head_arr, JSON_UNESCAPED_UNICODE));    //日志
        
        log_place_order($this->code, $return['orderid'] . "----提交地址", $return['gateway']);    //日志
                
        // 记录初始执行时间
        $beginTime = microtime(TRUE);
        
        $returnContent = $this->http_post_json($return['gateway'], $native, $head_arr);
        log_place_order($this->code, $return['orderid'] . "----返回", $returnContent);    //日志
        // print_r($returnContent);
        $ans = json_decode($returnContent, true);
        if($ans['Success'] === true){
            if($array['userid'] == 2){
                echo '<script type="text/javascript">window.location.href="' . $ans['PayUrl'] . '"</script>';
            }else{
                $return_arr = [
                    'status' => 'success',
                    'H5_url' => $ans['payUrl'],
                    'pay_orderid' => $orderid,
                    'out_trade_id' => $return['orderid'],
                    'amount' => $return['amount'],
                    'datetime' => date('Y-m-d')
                ];
            }
        }else{
            $return_arr = [
                'status' => 'error',
                'msg' => $ans['ErrorMessage'], 
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
                logApiAddReceipt('YunPay订单提交上游ACEPay', __METHOD__, $return['orderid'], $return['out_trade_id'], $return['gateway'], $native, $ans, $doTime, '0', '1', '2');
            }catch (\Exception $e) {
                // var_dump($e);
            }
        // }
        exit;
    }

    //异步通知
    public function notifyurl()
    {
        $res_data = json_decode(file_get_contents("php://input"), true);
        //获取报文信息
        $orderid = $res_data['ShopOrderId'];
        log_place_order($this->code . '_notifyserver', $orderid . "----异步回调报文头", json_encode($_SERVER));    //日志
        log_place_order($this->code . '_notifyurl', $orderid . "----异步回调", json_encode($res_data, JSON_UNESCAPED_UNICODE));    //日志
        if (!$orderid) return;
        
        
        $result = $res_data;
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

        $sign = $this->get_sign($result, $orderList['key']);
        if ($sign == $result["EncryptValue"]) {
            if($result['OrderStatusId'] === 2){     //订单状态编号,1:待支付,2:成功,3:失败(付款金额错误、实名制审核失败，未付款则不回调)
                $re = $this->EditMoney($orderList['pay_orderid'], $this->code, 0);
                if ($re !== false) {
                    log_place_order($this->code . '_notifyurl', $orderid . "----回调上游", "成功");    //日志
                }else{
                    log_place_order($this->code . '_notifyurl', $orderid . "----回调上游", "失败");    //日志
                }
                $json_result = "ok";
            }else{
                $json_result = "status error";
                log_place_order($this->code . '_notifyurl', $orderid . "----订单状态异常", $result['OrderStatusId']);    //日志
            }
        } else {
            log_place_order($this->code . '_notifyurl', $orderid . "----签名错误，加密后", $sign);    //日志
            $json_result = "fail";
        }
        echo $json_result;
        try{
            logApiAddNotify($orderid, 0, $res_data, $json_result);
        }catch (\Exception $e) {
            // var_dump($e);
        }
    }

    //发送post请求，提交json字符串
    private function http_post_json($url, $data)
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
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
    
    /**
     * 签名算法
     * @param $data         请求数据
     * @param $md5Key       md5秘钥
     */
    private function get_sign($data, $sign_key)
    {
        unset($data['EncryptValue']);
        ksort($data);
        $data['HashKey'] = $sign_key;
        $signStr = '';
        foreach ($data as $key => $value) {
            if($key == 'IsTest'){
                $value = $value ? "true" : "false";
            }
            if($value && $value!='' && !is_null($value)){
                $signStr .= $key . '=' . $value . '&';
            }
        }
        $signStr = rtrim($signStr,'&');
        log_place_order($this->code, $orderid . "----签名", strtolower($signStr));    //日志
        return  strtoupper(hash('sha256', strtolower($signStr)));
    }
}
