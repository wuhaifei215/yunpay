<?php

namespace Pay\Controller;

class InrWapSKPayController extends PayController
{
    private $code = '';
    private $query_url='https://api.skpay.app/mcapi/v2/receive/query';

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
            'title' => 'InrWap-SKPay',
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
            "url" => '/mcapi/v2/receive/create',
            "accessKey" => $return['mch_id'],
            "timestamp" => time(),
            "nonce" => randpw(6,'NUMBER'),
        ];
        // log_place_order($this->code, $return['orderid'] . "----签名串", implode('&',$head_arr));    //日志
        $head_arr['sign'] = base64_encode(hash_hmac('sha256', implode('&',$head_arr), $return['signkey'],true));
        
        $native = array(
            'merchantOrderNo' => $return['orderid'],
            'channelCode' => 'ch00380',       //通道代码 UPI: ch00379,通道代码原生：ch00380,通道代码唤醒：ch00381
            'amount' => sprintf('%.2f', $return['amount']), 
            'currency' =>'inr',
            'notifyUrl' => $return["notifyurl"],
            'jumpUrl' =>$pay_callbackurl,        //支付后重定向地址

        );

        log_place_order($this->code, $return['orderid'] . "----提交", json_encode($native, JSON_UNESCAPED_UNICODE));    //日志
        // log_place_order($this->code, $return['orderid'] . "----header", json_encode($head_arr, JSON_UNESCAPED_UNICODE));    //日志
        
        log_place_order($this->code, $return['orderid'] . "----提交地址", $return['gateway']);    //日志
                
        // 记录初始执行时间
        $beginTime = microtime(TRUE);
        
        $returnContent = $this->http_post_json($return['gateway'], $native, $head_arr);
        log_place_order($this->code, $return['orderid'] . "----返回", $returnContent);    //日志
        // print_r($returnContent);
        $ans = json_decode($returnContent, true);
        if($ans['code'] === 200000){
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
        $res_data = json_decode(file_get_contents("php://input"), true);
        //获取报文信息
        $orderid = $res_data['merchantOrderNo'];
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

        $head_arr = [
            "method" => $_SERVER['REQUEST_METHOD'],
            "url" => $_SERVER['REQUEST_URI'],
            "accessKey" => $_SERVER['HTTP_ACCESSKEY'],
            "timestamp" => $_SERVER['HTTP_TIMESTAMP'],
            "nonce" => $_SERVER['HTTP_NONCE'],
            ];
        log_place_order($this->code . '_notifyurl', $orderid . "----head_arr", json_encode($head_arr));    //日志
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
            logApiAddNotify($orderid, 0, $res_data, $json_result);
        }catch (\Exception $e) {
            // var_dump($e);
        }
    }

    //订单查询
    public function queryOrder($orderid, $memberid, $key)
    {
        $head_arr = [
            "method" => 'POST',
            "url" => '/mcapi/v2/receive/query',
            "accessKey" => $return['mch_id'],
            "timestamp" => time(),
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
    private function http_post_json($url, $param, $head_arr)
    {
        $json = json_encode($param);
        $headers = array(
            'Content-Type: application/json',
            'Content-Length:' . strlen($json),
            'accessKey:' . $head_arr['accessKey'],
            'timestamp:' . $head_arr['timestamp'],
            'nonce:' . $head_arr['nonce'],
            'sign:' . $head_arr['sign'],
        );
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json); // Post提交的数据包
        curl_setopt($curl, CURLOPT_TIMEOUT, 10); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($curl); // 执行操作
        if (curl_errno($curl)) {
            echo 'Errno' . curl_error($curl);//捕抓异常
        }
        curl_close($curl); // 关闭CURL会话
        return $result;
    }
}
