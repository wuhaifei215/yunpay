<?php

namespace Payment\Controller;

class SKPayDFController extends PaymentController
{
    private $code = '';

    public function __construct()
    {
        $matches = [];
        preg_match('/([\da-zA-Z\_]+)Controller$/', __CLASS__, $matches);
        $this->code = $matches[1];
    }

    //代付提交
    public function PaymentExec($data, $config)
    {
        $post_data = array(
            "merchantOrderNo" => $data['orderid'], //订单号
            "beneficiary" => $data['bankfullname'],  //户名
            "bankName" => $data['bankname'],  
            "bankAccount" => $data['banknumber'],    //账号
            "ifsc" => $data['bankzhiname'],
            "currency" => "inr",
            "channelCode" => $config['appid'],
            "amount" =>sprintf('%.2f', $data['money']),  //提现金额
            "notifyUrl" => 'https://' . C('NOTIFY_DOMAIN') . "/Payment_" . $this->code . "_notifyurl.html",      //异步通知地址
        );
        
        $head_arr = [
            "method" => 'POST',
            "url" => '/mcapi/send/create',
            "accessKey" => $config['mch_id'],
            "timestamp" => time(),
            "nonce" => randpw(6,'NUMBER'),
        ];
        // log_place_order($this->code, $data['orderid'] . "----签名串", implode('&',$head_arr));    //日志
        $head_arr['sign'] = base64_encode(hash_hmac('sha256', implode('&',$head_arr), $config['signkey'],true));

        log_place_order($this->code, $data['orderid'] . "----提交", json_encode($post_data, JSON_UNESCAPED_UNICODE));    //日志
        log_place_order($this->code, $data['orderid'] . "----提交地址", $config['exec_gateway']);    //日志
        
        // 记录初始执行时间
        $beginTime = microtime(TRUE);
        
        $returnContent = $this->http_post_json($config['exec_gateway'], $post_data, $head_arr);
        $result = json_decode($returnContent, true);
        // if($data['userid'] == 2){
            try{
                
                $redis = $this->redis_connect();
                $userdfpost = $redis->get('userdfpost_' . $data['out_trade_no']);
                $userdfpost = json_decode($userdfpost,true);
                
                logApiAddPayment('商户提交', __METHOD__, $data['orderid'], $data['out_trade_no'], '/', $userdfpost, [], '0', '0', '1', '2');
                
                // 结束并输出执行时间
                $endTime = microtime(TRUE);
                $doTime = floor(($endTime-$beginTime)*1000);
                logApiAddPayment('订单提交三方', __METHOD__, $data['orderid'], $data['out_trade_no'], $config['exec_gateway'], $post_data, $result, $doTime, '0', '1', '1');
            }catch (\Exception $e) {
                // var_dump($e);
            }
        // }
        log_place_order($this->code, $data['orderid'] . "----返回", json_encode($result, JSON_UNESCAPED_UNICODE));    //日志

        // log_place_order($this->code, $data['orderid'] . "----状态：", $result['status']);    //日志
        if($result['code'] === 200000){
            //保存第三方订单号
            // $orderid = $data['orderid'];
            // $Wttklistmodel = D('Wttklist');
            // $date = date('Ymd',strtotime(substr($orderid, 1, 8)));  //获取订单日期
            // $tableName = $Wttklistmodel->getRealTableName($date);
            // $re_save = $Wttklistmodel->table($tableName)->where(['orderid' => $orderid])->save(['three_orderid'=>$result['order']]);
            if($result['data']['status'] === "created"){
                $return = ['status' => 1, 'msg' => '申请正常'];
            }elseif($result['data']['status'] === "failure"){
                $return = ['status' => 3, 'msg' => '申请失败'];
            }else{
                $return = ['status' => 0, 'msg' => '异常状态' . $result['data']['status']];
            }
        }else{
            $return = ['status' => 0, 'msg' => '提交超时'];
        }
        return $return;
    }

    public function notifyurl()
    {
        $re_data = json_decode(file_get_contents("php://input"), true);
        //获取报文信息
        $orderid = $re_data['merchantOrderNo'];
        //log_place_order($this->code . '_notifyserver', $orderid . "----异步回调报文头", json_encode($_SERVER));    //日志
        log_place_order($this->code . '_notifyurl', $orderid . "----异步回调", json_encode($re_data, JSON_UNESCAPED_UNICODE));    //日志
        
        $tableName ='';
        $Wttklistmodel = D('Wttklist');
        $date = date('Ymd',strtotime(substr($orderid, 1, 8)));  //获取订单日期
        $tableName = $Wttklistmodel->getRealTableName($date);
        $Order = $Wttklistmodel->table($tableName)->where(['orderid' => $orderid])->find();
        
        // $Order = $this->selectOrder(['orderid' => $orderid]);
        if (!$Order) {
            log_place_order($this->code . '_notifyurl', $orderid . '----没有查询到Order！ ', $orderid);
            exit;
        }
        
        $config = M('pay_for_another')->where(['code' => $this->code,'id'=>$Order['df_id']])->find();
        //验证IP白名单
        if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = getRealIp();
        }
        $check_re = checkNotifyurlIp($ip, $config['notifyip']);
        if ($check_re !== true) {
            log_place_order($this->code . '_notifyurl', $orderid . "----IP异常", $ip.'==='.$config['notifyip']);    //日志
            return;
        }
        
        $head_arr = [
            "method" => $_SERVER['REQUEST_METHOD'],
            "url" => $_SERVER['REQUEST_URI'],
            "accessKey" => $_SERVER['HTTP_ACCESSKEY'],
            "timestamp" => $_SERVER['HTTP_TIMESTAMP'],
            "nonce" => $_SERVER['HTTP_NONCE'],
        ];
        // log_place_order($this->code . '_notifyurl', $orderid . "----head_arr", json_encode($head_arr));    //日志
        $sign = base64_encode(hash_hmac('sha256', implode('&',$head_arr), $config['signkey'], true));
        if ($sign === $_SERVER["HTTP_SIGN"]) {
            if ($re_data['status'] === "success") {
                //代付成功 更改代付状态 完善代付逻辑
                $data = [
                    'memo' => '代付成功',
                ];
                $this->changeStatus($Order['id'], 2, $data, $tableName);
                log_place_order($this->code . '_notifyurl', $orderid, "----代付成功");    //日志
            } elseif ($re_data['status'] === "failure") {
                //代付失败
                $data = [
                    'memo' => '代付失败',
                ];
                $this->changeStatus($Order['id'], 3, $data, $tableName);
                log_place_order($this->code . '_notifyurl', $orderid, "----代付失败");    //日志
            } elseif ($re_data['status'] === "overrule") {
                //代付失败
                $data = [
                    'memo' => '代付驳回',
                ];
                $this->changeStatus($Order['id'], 3, $data, $tableName);
                log_place_order($this->code . '_notifyurl', $orderid, "----代付失败");    //日志
            } else {
                log_place_order($this->code . '_notifyurl', $orderid, "----代付状态异常" . $re_data['status']);    //日志
            }
            $json_result = "success";
        } else {
            log_place_order($this->code . '_notifyurl', $orderid . '----签名错误: ', $sign);
            $json_result = "fail";
        }
        echo $json_result;
        try{
            logApiAddNotify($orderid, 1, $re_data, $json_result);
        }catch (\Exception $e) {
            // var_dump($e);
        }
    }
    
    //账户余额查询
    public function queryBalance()
    {
        if (IS_AJAX) {
            $id = I('post.id', 1);
            $config = M('pay_for_another')->where(['id' => $id])->find();
            $head_arr = [
                "method" => 'GET',
                "url" => '/mcapi/quota',
                "accessKey" => $config['mch_id'],
                "timestamp" => time(),
                "nonce" => randpw(6,'NUMBER'),
            ];
            // log_place_order($this->code . '_queryBalance', "签名串", implode('&',$head_arr));    //日志
            $head_arr['sign'] = base64_encode(hash_hmac('sha256', implode('&',$head_arr), $config['signkey'],true));

            $returnContent = $this->http_get_json($config['serverreturn'], $head_arr);
            log_place_order($this->code . '_queryBalance', "返回", $returnContent);    //日志
            $result = json_decode($returnContent,true);
            if($result['code'] === 200000){
                foreach ($result['data'] as $k => $v){
                    if($v['currency'] === 'inr'){
                        $balance = $v['balance'];  //可用金额
                    }
                }
                
                $html = <<<AAA
<!-- CSS goes in the document HEAD or added to your external stylesheet -->
<style type="text/css">
table.hovertable {width: 200px;font-family: verdana,arial,sans-serif;font-size:11px;color:#333333;border-width: 1px;border-color: #999999;border-collapse: collapse;}
table.hovertable th {background-color:#c3dde0;border-width: 1px;padding: 8px;border-style: solid;border-color: #a9c6c9;}
table.hovertable tr {background-color:#f5f5f5;}
table.hovertable td {border-width: 1px;padding: 8px;border-style: solid;border-color: #a9c6c9;}
</style>
<table class="hovertable">
<tr><th>说明</th><th>值</th></tr>
<tr onmouseout="this.style.backgroundColor='#f5f5f5';" onmouseover="this.style.backgroundColor='#009688';"><td>可用金额</td><td><b>$balance </b></td></tr>
</table>
AAA;
                $this->ajaxReturn(['status' => 1, 'msg' => '成功', 'data' => $html]);
            }else{
                $this->ajaxReturn(['status' => 0, 'msg' => '失败', 'data' => '查询异常']);
            }
        }
    }
    
    //账户余额查询
    public function queryBalance2($config)
    {
        $head_arr = [
            "method" => 'GET',
            "url" => '/mcapi/quota',
            "accessKey" => $config['mch_id'],
            "timestamp" => time(),
            "nonce" => randpw(6,'NUMBER'),
        ];
        // log_place_order($this->code . '_queryBalance2', "签名串", implode('&',$head_arr));    //日志
        $head_arr['sign'] = base64_encode(hash_hmac('sha256', implode('&',$head_arr), $config['signkey'],true));

        $returnContent = $this->http_get_json($config['serverreturn'], $head_arr);
        log_place_order($this->code . '_queryBalance2', "返回", $returnContent);    //日志
        $result = json_decode($returnContent,true);
        if($result['code'] === 200000){
            $result_data['resultCode'] = "0";
            foreach ($result['data'] as $k => $v){
                if($v['currency'] === 'inr'){
                    $result_data['balance'] = $v['balance'];  //可用金额
                }
            }
        }
        return $result_data;
    }
    
    //代付订单查询
    public function PaymentQuery($data, $config)
    {
        $post_data = [
            'merchantOrderNo' => $data['orderid'],
        ];
        $head_arr = [
            "method" => 'POST',
            "url" => '/mcapi/send/query',
            "accessKey" => $config['mch_id'],
            "timestamp" => time(),
            "nonce" => randpw(6,'NUMBER'),
        ];
        // log_place_order($this->code, $data['orderid'] . "----签名串", implode('&',$head_arr));    //日志
        $head_arr['sign'] = base64_encode(hash_hmac('sha256', implode('&',$head_arr), $config['signkey'],true));
    
        log_place_order($this->code . '_PaymentQuery', $data['orderid'] . "----提交", json_encode($post_data, JSON_UNESCAPED_UNICODE));    //日志
        $returnContent = $this->http_post_json($config['query_gateway'], $post_data, $head_arr);
        log_place_order($this->code . '_PaymentQuery', $data['orderid'] . "----返回", $returnContent);    //日志
        $result = json_decode($returnContent, true);
        if ($result['code'] === 200000) {
            switch ($result['data']['status']) {       //订单状态 created：已創建,paying：支付中,timeout：超時,success：成功,failure：失敗,cancel：取消支付,exception：異常
                case 'created':
                case 'paying':
                    $return = ['status' => 1, 'msg' => '处理中'];
                    break;
                case 'success':
                    $return = ['status' => 2, 'msg' => '成功'];
                    break;
                case 'failure':
                case 'cancel':
                    $return = ['status' => 3, 'msg' => '失败','remark' => $result['message']];
                    break;
            }
        } else {
            $return = ['status' => 7, 'msg' => "查询接口失败:".$result['code']];
        }
        return $return;
    }
        
    // public function PaymentVoucher($data, $config){
    //     $post_data = [
    //         'uorderid' => $data['orderid'],
    //     ];
    //     $post_data["sign"] = $this->sign($post_data, $config['signkey']);
    //     log_place_order($this->code . '_PaymentVoucher', $data['orderid'] . "----提交", json_encode($post_data, JSON_UNESCAPED_UNICODE));    //日志
    //     $returnContent = $this->http_post_json('https://yunpay.win0066.com/credentials', $post_data);
    //     log_place_order($this->code . '_PaymentVoucher', $data['orderid'] . "----返回", $returnContent);    //日志
    //     $result = json_decode($returnContent, true);
    //     if($result['code'] === 200){
    //         $result_data['resultCode'] = "0";
    //         $result_data['balance'] = $result['param']['url'];
    //         return  $result_data;
    //     }else{
    //         return false;
    //     }
    // }

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
        
    //发送get请求，提交json字符串
    private function http_get_json($url, $head_arr)
    {
        $data = '';
        if (!empty($url)) {
            $headers = array(
                'accessKey:' . $head_arr['accessKey'],
                'timestamp:' . $head_arr['timestamp'],
                'nonce:' . $head_arr['nonce'],
                'sign:' . $head_arr['sign'],
            );
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10); //30秒超时
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                $data = curl_exec($ch);
                curl_close($ch);
            } catch (Exception $e) {
                $data = '';
            }
        }
        return $data;
    }
}
