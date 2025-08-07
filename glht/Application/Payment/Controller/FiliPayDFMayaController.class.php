<?php

namespace Payment\Controller;

class FiliPayDFMayaController extends PaymentController
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
            "clientCode" => $config['mch_id'], //商户号
            "chainName" =>"BANK",
            "coinUnit" =>"PHP",
            "bankCardNum" => $data['banknumber'],    //账号
            "bankUserName" => $data['bankfullname'],  //户名
            "ifsc" => "IFSC",
            "bankName" => "MYW",
            "amount" =>sprintf('%.2f', $data['money']),  //提现金额
            "clientNo" => $data['orderid'], //订单号
            "requestTimestamp" => $this->getMillisecond(),
            "callbackurl" => 'https://' . C('NOTIFY_DOMAIN') . "/Payment_" . $this->code . "_notifyurl.html",      //异步通知地址
        );
        $post_data["sign"] = md5($post_data['clientCode']."&".$post_data['chainName']."&".$post_data['coinUnit']."&".$post_data['clientNo']."&".$post_data['requestTimestamp'].$config['signkey']);
        log_place_order($this->code, $data['orderid'] . "----提交", json_encode($post_data, JSON_UNESCAPED_UNICODE));    //日志
        log_place_order($this->code, $data['orderid'] . "----提交地址", $config['exec_gateway']);    //日志
        
        // 记录初始执行时间
        $beginTime = microtime(TRUE);
        
        $returnContent = $this->http_post_json($config['exec_gateway'], $post_data);
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
        if($result['success'] === true){
            //保存第三方订单号
            // $orderid = $data['orderid'];
            // $Wttklistmodel = D('Wttklist');
            // $date = date('Ymd',strtotime(substr($orderid, 1, 8)));  //获取订单日期
            // $tableName = $Wttklistmodel->getRealTableName($date);
            // $re_save = $Wttklistmodel->table($tableName)->where(['orderid' => $orderid])->save(['three_orderid'=>$result['order']]);
            switch ($result['data']['status']) {      //1. **PAYING**：提现进行中。2. **PAID**：提现成功并已确认。3. **FINISH**：回调已处理并接收到确认响应。4. **CANCEL**：提现失败，订单被拒绝。5. **REVERT**：银行在成功提现后撤销了交易
                case 'PAYING':
                    $return = ['status' => 1, 'msg' => '申请正常'];
                    break;
            }
        }elseif($result['success'] === false){
            $return = ['status' => 3, 'msg' => '申请失败--'.$result['message']];
        }else{
            $return = ['status' => 0, 'msg' => $result['message']];
        }
        return $return;
    }

    public function notifyurl()
    {
        unset($_REQUEST['PHPSESSID']);
        $re_data = $_REQUEST;
        //获取报文信息
        $orderid = $re_data['clientNo'];
        //log_place_order($this->code . '_notifyserver', $orderid . "----异步回调报文头", json_encode($_SERVER));    //日志
        log_place_order($this->code . '_notifyurl', $orderid . "----异步回调", json_encode($_REQUEST, JSON_UNESCAPED_UNICODE));    //日志
        
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
        
        $sign_arr = [
            'clientCode' => $re_data['clientCode'],
            'clientNo' => $re_data['clientNo'],
            'orderNo' => $re_data['orderNo'],
            'payAmount' => $re_data['payAmount'],
            'status' => $re_data['status'],
            'txid' => $re_data['txid'],
        ];
        $sign = $this->get_sign($sign_arr,$config['signkey']);
        
        
        if ($sign === $re_data["sign"]) {
            if ($re_data['status'] === "PAID" || $re_data['status'] === "FINISH") {     //1. **PAYING**：提现进行中。2. **PAID**：提现成功并已确认。3. **FINISH**：回调已处理并接收到确认响应。4. **CANCEL**：提现失败，订单被拒绝。5. **REVERT**：银行在成功提现后撤销了交易
            
                //代付成功 更改代付状态 完善代付逻辑
                $data = [
                    'memo' => '代付成功',
                ];
                $this->changeStatus($Order['id'], 2, $data, $tableName);
                // $this->handle($Order['id'], 2, $data, $tableName);
                log_place_order($this->code . '_notifyurl', $orderid, "----代付成功");    //日志
            } elseif ($re_data['status'] === "CANCEL") {
                //代付失败
                $data = [
                    'memo' => '代付失败-' . $re_data['remark'],
                ];
                $this->changeStatus($Order['id'], 3, $data, $tableName);
                // $this->handle($Order['id'], 3, $data, $tableName);
                log_place_order($this->code . '_notifyurl', $orderid, "----代付失败");    //日志
            }
            $json_result = "ok";
        } else {
            log_place_order($this->code . '_notifyurl', $orderid . '----签名错误: ', $sign);
            // $data = [
            //     'memo' => '签名错误',
            // ];
            
            // $this->changeStatus($Order['id'], 0, $data, $tableName);
            // $this->handle($Order['id'], 0, $data, $tableName);
            $json_result = "fail";
        }
        echo $json_result;
        try{
            logApiAddNotify($orderid, 1, $_REQUEST, $json_result);
        }catch (\Exception $e) {
            // var_dump($e);
        }
    }
    
    //账户余额查询
    public function queryBalance()
    {
        if (IS_AJAX) {
            $config = M('pay_for_another')->where(['code' => $this->code])->find();
            $post_data = array(
                "clientCode" => $config['mch_id'], //商户号
                "coinChain" =>"BANK",
                "coinUnit" =>"PHP",
                "requestTimestamp" => $this->getMillisecond(),
            );
            $post_data["sign"] = $this->get_sign($post_data, $config['signkey']);
            log_place_order($this->code . '_queryBalance', "提交", json_encode($post_data));    //日志
            $returnContent = $this->http_get_json($config['serverreturn'], $post_data);
            log_place_order($this->code . '_queryBalance', "返回", $returnContent);    //日志
            $returnContent = json_decode($returnContent,true);
            $result = $returnContent['data'];
            
            $balance = $result['outBalance']['balance'];  //可用金额
            $freeze = $result['outBalance']['freeze'];  //在途余额
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
<tr onmouseout="this.style.backgroundColor='#f5f5f5';" onmouseover="this.style.backgroundColor='#009688';"><td>冻结金额</td><td><b>$freeze </b></td></tr>
</table>
AAA;
            $this->ajaxReturn(['status' => 1, 'msg' => '成功', 'data' => $html]);
        }
    }
    
    //账户余额查询
    public function queryBalance2($config)
    {
        $post_data = array(
                "clientCode" => $config['mch_id'], //商户号
                "coinChain" =>"BANK",
                "coinUnit" =>"PHP",
                "requestTimestamp" => $this->getMillisecond(),
            );
        $post_data["sign"] = $this->get_sign($post_data, $config['signkey']);
        log_place_order($this->code . '_queryBalance2', "提交", json_encode($post_data));    //日志
        $returnContent = $this->http_get_json($config['serverreturn'], $post_data);
        log_place_order($this->code . '_queryBalance2', "返回", $returnContent);    //日志
        $returnContent = json_decode($returnContent,true);
        $result = $returnContent['data'];
        if($returnContent['code'] === 200){
            $result_data['resultCode'] = "0";
            $result_data['balance'] = $result['outBalance']['balance'];
        }
        return $result_data;
    }

    //代付订单查询
    public function PaymentQuery($data, $config)
    {
        $post_data = [
            "clientCode" => $config['mch_id'], //商户号
            'clientNo' => $data['orderid'],
        ];
        $post_data["sign"] = $this->get_sign($post_data, $config['signkey']);
        log_place_order($this->code . '_PaymentQuery', $data['orderid'] . "----提交", json_encode($post_data, JSON_UNESCAPED_UNICODE));    //日志
        $returnContent = $this->http_get_json($config['query_gateway'], $post_data);
        log_place_order($this->code . '_PaymentQuery', $data['orderid'] . "----返回", $returnContent);    //日志
        $result = json_decode($returnContent, true);
        if ($result['code'] === "000000") {
            switch ($result['orderStatus']) {       //01:待结算06:清算中07:清算完成08:清算失败09:清算撤销
                case '01':
                case '06':
                    $return = ['status' => 1, 'msg' => '处理中'];
                    break;
                case '07':
                    $return = ['status' => 2, 'msg' => '成功'];
                    break;
                case '08':
                case '09':
                    $return = ['status' => 3, 'msg' => '失败','remark' => $result['remark']];
                    break;
            }
        } else {
            $return = ['status' => 7, 'msg' => "查询接口失败:".$result['code']];
        }
        return $return;
    }
        
    // public function PaymentVoucher($data, $config){
    //     if(isset($data['three_orderid'])){
    //         $post_data = [
    //             'custId' => $config['mch_id'],
    //             'appId' => $config['appid'],
    //             'order' => $data['orderid'],
    //         ];
    //         $post_data["sign"] = $this->get_sign($post_data, $config['signkey']);
    //         log_place_order($this->code . '_PaymentVoucher', $data['orderid'] . "----提交", json_encode($post_data, JSON_UNESCAPED_UNICODE));    //日志
    //         $returnContent = $this->http_post_json('https://api.winpay.site/br/voucherData.json', $post_data);
    //         log_place_order($this->code . '_PaymentVoucher', $data['orderid'] . "----返回", $returnContent);    //日志
    //         $result = json_decode($returnContent, true);

    //         if(!empty($result)){
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
            
            log_place_order($this->code, "----url", $url);    //日志
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
