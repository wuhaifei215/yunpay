<?php

namespace Pay\Controller;

class WorldVNMomoController extends PayController
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
            'title' => 'VNMOMO--World',
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
            'mchId' => $return['mch_id'],
            'mchOrderId' => $return['orderid'],
            'amount' => intval($return['amount']),
            'payMethod' => 'VNMOMO',
            'notifyUrl' => $return["notifyurl"],
        );
        $native['sign'] = $this->get_sign($native, $return['signkey']);
        
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
                logApiAddReceipt('YunPay订单提交上游WorldPay', __METHOD__, $return['orderid'], $return['out_trade_id'], $return['gateway'], $native, $ans, $doTime, '0', '1', '2');
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
        $orderid = $arrayData['mchOrderId'];
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
        
        if ($sign === $result['sign']) {
            if($result['isPaid'] === 1){     //支付状态，1为已支付，0为未支付
                $re = $this->EditMoney($orderList['pay_orderid'], $this->code, 0);
                if ($re !== false) {
                    log_place_order($this->code . '_notifyurl', $orderid . "----回调上游", "成功");    //日志
                }else{
                    log_place_order($this->code . '_notifyurl', $orderid . "----回调上游", "失败");    //日志
                }
            }else{
                log_place_order($this->code . '_notifyurl', $orderid . "----订单状态异常", $result['isPaid']);    //日志
            }
            $json_result = 'success';
        } else {
            log_place_order($this->code . '_notifyurl', $orderid . "----签名错误，加密后", $sign);    //日志
            
            $json_result = 'fail';
        }
        echo $json_result;
        try{
            logApiAddNotify($orderid, 0, $result, $json_result);
        }catch (\Exception $e) {
            // var_dump($e);
        }
    }

    //发送post请求，提交json字符串
    private function http_post_json($url, $data)
    {
        $ch = curl_init($url);
        $payload = json_encode($data);
    
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch) . "\n";
            return null;
        }
        curl_close($ch);
    
        return $result;
    }
        
    
    /**
     * 签名算法
     * @param $data         请求数据
     * @param $md5Key       md5秘钥
     */
    private function get_sign($data, $sign_key)
    {
        unset($data['sign']);
        ksort($data);
        $signStr = '';
        foreach ($data as $key => $value) {
            $signStr .= $key . '=' . $value . '&';
        }
        $signStr .= 'sign=' . $sign_key;
        // log_place_order($this->code, $orderid . "----签名", $signStr);    //日志
        // log_place_order($this->code, $orderid . "----key", $sign_key);    //日志
        return md5($signStr);
    }
}
