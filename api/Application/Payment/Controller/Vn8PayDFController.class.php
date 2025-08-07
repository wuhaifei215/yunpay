<?php

namespace Payment\Controller;

class Vn8PayDFController extends PaymentController
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
        $bank_code = M('systembank')->where(['bankcode'=> $data['bankname']])->getField('bank_code');
        $post_data = array(
            "account_name" => $data['bankfullname'],  //户名
            "account_no" => $data['banknumber'],    //账号
            "amount" =>sprintf('%.2f', $data['money']) * 100,  //提现金额
            "app_id" => $config['mch_id'], //商户号
            "bank_code" => intval($bank_code),
            "callback_url" => 'https://' . C('NOTIFY_DOMAIN') . "/Payment_" . $this->code . "_notifyurl.html",      //异步通知地址
            "order_id" => $data['orderid'], //订单号
            "pipe_id" => 110001,
            'timestamp' =>time(),
        );
        $post_data["sign"] = $this->get_sign($post_data, $config['signkey']);
        log_place_order($this->code, $data['orderid'] . "----提交", json_encode($post_data, JSON_UNESCAPED_UNICODE));    //日志
        log_place_order($this->code, $data['orderid'] . "----提交地址", $config['exec_gateway']);    //日志
        
        // 记录初始执行时间
        $beginTime = microtime(TRUE);
        
        $returnContent = $this->http_post_json($config['exec_gateway'], $post_data);
        log_place_order($this->code, $data['orderid'] . "----返回", $returnContent);    //日志
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
                logApiAddPayment('订单提交三方', __METHOD__, $data['orderid'], $data['out_trade_no'], $config['exec_gateway'], $post_data, $result, $doTime, '0', '1', '2');
            }catch (\Exception $e) {
                // var_dump($e);
            }
        // }

        // log_place_order($this->code, $data['orderid'] . "----状态：", $result['status']);    //日志
        //保存第三方订单号
        // $orderid = $data['orderid'];
        // $Wttklistmodel = D('Wttklist');
        // $date = date('Ymd',strtotime(substr($orderid, 1, 8)));  //获取订单日期
        // $tableName = $Wttklistmodel->getRealTableName($date);
        // $re_save = $Wttklistmodel->table($tableName)->where(['orderid' => $orderid])->save(['three_orderid'=>$result['order_no']]);
        // $return = ['status' => 2, 'msg' => '代付成功'];
        if($result['success'] === true){
            $return = ['status' => 1, 'msg' => '申请正常'];
        }elseif($result['success'] === false){
            $return = ['status' => 3, 'msg' => $result['data']['err_message']];
        }else{
            $return = ['status' => 0, 'msg' => $result['message']];
        }
        return $return;
    }

    public function notifyurl()
    {
        $re_data = json_decode(file_get_contents('php://input'), true);
        //获取报文信息
        $orderid = $re_data['order_id'];
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

        $sign = $this->get_sign($re_data, $config['signkey']);
        
        if ($sign === $_SERVER["HTTP_X_AUTHORIZATION_SIGNATURE"]) {
            if ($re_data['status'] === "success") {     //订单状态 
                //代付成功 更改代付状态 完善代付逻辑
                $data = [
                    'memo' => '代付成功',
                ];
                $this->changeStatus($Order['id'], 2, $data, $tableName);
                log_place_order($this->code . '_notifyurl', $orderid, "----代付成功");    //日志
            } elseif ($re_data['status'] === "fail") {
                //代付失败
                $data = [
                    'memo' => '代付失败',
                ];
                $this->changeStatus($Order['id'], 3, $data, $tableName);
                log_place_order($this->code . '_notifyurl', $orderid, "----代付失败");    //日志
            }
            $json_result = '{"success":true}';
        } else {
            log_place_order($this->code . '_notifyurl', $orderid . '----签名错误: ', $sign);
            $json_result = '{"success":false,"message":"sign error"}';
        }
        echo $json_result;
        try{
            logApiAddNotify($orderid, 1, $re_data, $json_result);
        }catch (\Exception $e) {
            // var_dump($e);
        }
    }

    //代付订单查询
    public function PaymentQuery($data, $config)
    {
        $post_data = [
            'app_id' => $config['mch_id'],
            'order_id' => $data['orderid'],
            'timestamp' => timen(),
        ];
        $post_data["sign"] = $this->get_sign($post_data, $config['signkey']);
        log_place_order($this->code . '_PaymentQuery', $data['orderid'] . "----提交", json_encode($post_data, JSON_UNESCAPED_UNICODE));    //日志
        $returnContent = $this->http_post_json($config['query_gateway'], $post_data);
        log_place_order($this->code . '_PaymentQuery', $data['orderid'] . "----返回", $returnContent);    //日志
        $result = json_decode($returnContent, true);
        if ($result['success'] === true) {
            switch ($result['data']['status']) {       //0：未支付；1：支付中；2：已支付；3：支付失败；
                case 'create':
                case 'wait':
                    $return = ['status' => 1, 'msg' => '处理中'];
                    break;
                case 'success':
                    $return = ['status' => 2, 'msg' => '成功'];
                    break;
                case 'fail':
                    $return = ['status' => 3, 'msg' => $result['data']['err_message']];
                    break;
            }
        } else {
            $return = ['status' => 7, 'msg' => "查询接口失败"];
        }
        return $return;
    }
    
    
    //账户余额查询
    public function queryBalance()
    {
        if (IS_AJAX) {
            $config = M('pay_for_another')->where(['code' => $this->code])->find();
            $post_data = array(
                "app_id" => $config['mch_id'], //商户号
                "currency" => 'VND',
                "timestamp" => time(),
            );
            $post_data["sign"] = $this->get_sign($post_data, $config['signkey']);
            log_place_order($this->code . '_queryBalance', "提交", json_encode($post_data));    //日志
            log_place_order($this->code . '_queryBalance', "url", $config['serverreturn']);    //日志
            $returnContent = $this->http_post_json($config['serverreturn'], $post_data);
            log_place_order($this->code . '_queryBalance', "返回", $returnContent);    //日志
            $result = json_decode($returnContent, true);
            if($result['success'] === true){
                $acBal = $result['data']['balance']/100;  //总金额
                $available = $result['data']['available']/100;  //可用金额
                $acT0Froz = $result['data']['freeze']/100;  //冻结金额
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
    <tr onmouseout="this.style.backgroundColor='#f5f5f5';" onmouseover="this.style.backgroundColor='#009688';"><td>总金额</td><td><b>$acBal </b></td></tr>
    <tr onmouseout="this.style.backgroundColor='#f5f5f5';" onmouseover="this.style.backgroundColor='#009688';"><td>可用金额</td><td><b>$available </b></td></tr>
    <tr onmouseout="this.style.backgroundColor='#f5f5f5';" onmouseover="this.style.backgroundColor='#009688';"><td>冻结金额</td><td><b>$acT0Froz </b></td></tr>
    </table>
AAA;
                $this->ajaxReturn(['status' => 1, 'msg' => '成功', 'data' => $html]);
            }
            
        }
    }
    
    //账户余额查询
    public function queryBalance2($config)
    {
        $post_data = array(
            "app_id" => $config['mch_id'], //商户号
            "currency" => 'VND',
            "timestamp" => time(),
        );
        $post_data["sign"] = $this->get_sign($post_data, $config['signkey']);
        log_place_order($this->code . '_queryBalance2', "提交", json_encode($post_data));    //日志
        $returnContent = $this->http_post_json($config['serverreturn'], $post_data);
        log_place_order($this->code . '_queryBalance2', "返回", $returnContent);    //日志
        $result = json_decode($returnContent, true);
        if($result['success'] === true){
            $result_data['resultCode'] = "0";
            $result_data['balance'] = $result['data']['available']/100;
        }
        return $result_data;
    }
    
    // public function PaymentVoucher($data, $config){
    //     if(isset($data['three_orderid'])){
    //         $post_data = [
    //             'custId' => $config['mch_id'],
    //             'appId' => $config['appid'],
    //             // 'order' => $data['three_orderid'],
    //             'order' => $data['orderid'],
    //         ];
    //         $post_data["sign"] = $this->get_sign($post_data, $config['signkey']);
    //         log_place_order($this->code . '_PaymentVoucher', $data['orderid'] . "----提交", json_encode($post_data, JSON_UNESCAPED_UNICODE));    //日志
    //         $returnContent = $this->http_post_json('https://phlapi.newwinpay.site/br/voucherData.json', $post_data);
    //         log_place_order($this->code . '_PaymentVoucher', $data['orderid'] . "----返回", $returnContent);    //日志
    //         $result = json_decode($returnContent, true);
        
    //         // $redata = json_decode(file_get_contents('https://api.winpay.site/payment/br/voucherData.webapp?casOrdNo=' . $data['three_orderid']),true);
    //         log_place_order($this->code . '_PaymentVoucher', $data['three_orderid'] . "----返回",  json_encode($result, JSON_UNESCAPED_UNICODE));    //日志
    //         if(!empty($result) && $result['code'] === '000000'){
    //             return  $result;
    //         }else{
    //             return false;
    //         }
    //     }else{
    //         return false;
    //     }
        
    // }

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
        // log_place_order($this->code, $orderid . "----签名", rtrim($sortedParams,'&'));    //日志
        return $signature; //最终的签名

    }
}
