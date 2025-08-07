<?php

namespace Payment\Controller;

class ACEPayBDDFController extends PaymentController
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
        if($data['bankname'] === 'BKASH'){
            $PaymentChannelId = 34;
        }elseif($data['bankname'] === 'NAGAD'){
            $PaymentChannelId = 35;
        }else{
            $PaymentChannelId = '';
        }
        $post_data = array(
            "Amount" =>$data['money'],  //提现金额
            'CurrencyId' => 11,
            'IsTest' => false,
            "PayeeAccountName" => $data['bankfullname'],  //户名
            "PayeeAccountNumber" => $data['banknumber'],    //账号
            'PaymentChannelId' => $PaymentChannelId,
            "ShopInformUrl" => 'https://' . C('NOTIFY_DOMAIN') . "/Payment_" . $this->code . "_notifyurl.html",      //异步通知地址
            "ShopOrderId" => $data['orderid'], //订单号
            "ShopUserLongId" => $config['mch_id'], //商户号
        );
        $post_data["EncryptValue"] = $this->get_sign($post_data, $config['signkey']);
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

        // log_place_order($this->code, $data['orderid'] . "----状态：", $result['orderStatus']);    //日志
        if($result['Success'] === true){
            //保存第三方订单号
            // $orderid = $data['orderid'];
            // $Wttklistmodel = D('Wttklist');
            // $date = date('Ymd',strtotime(substr($orderid, 1, 8)));  //获取订单日期
            // $tableName = $Wttklistmodel->getRealTableName($date);
            // $re_save = $Wttklistmodel->table($tableName)->where(['orderid' => $orderid])->save(['three_orderid'=>$result['TrackingNumber']]);
            $return = ['status' => 1, 'msg' => '申请正常'];
        }else{
            $return = ['status' => 0, 'msg' => $result['ErrorMessage']];
        }
        return $return;
    }

    public function notifyurl()
    {
        $re_data = json_decode(file_get_contents("php://input"), true);
        //获取报文信息
        $orderid = $re_data['ShopOrderId'];
        //log_place_order($this->code . '_notifyserver', $orderid . "----异步回调报文头", json_encode($_SERVER));    //日志
        log_place_order($this->code . '_notifyurl', $orderid . "----异步回调", file_get_contents("php://input"));    //日志
        
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
        
        $sign = $this->get_sign($re_data,$config['signkey']);
        
        if ($sign === $re_data["EncryptValue"]) {
            if ($re_data['PaymentOrderStatusId'] === 2) {     //订单状态编号,1:待支付,2:成功,3:取消(驳回),4:待审核
                //代付成功 更改代付状态 完善代付逻辑
                $data = [
                    'memo' => '代付成功',
                ];
                $this->changeStatus($Order['id'], 2, $data, $tableName);
                log_place_order($this->code . '_notifyurl', $orderid, "----代付成功");    //日志
            } elseif ($re_data['PaymentOrderStatusId'] === 3) {
                //代付失败
                $data = [
                    'memo' => '代付失败-' . $re_data['FailedMessage'],
                ];
                $this->changeStatus($Order['id'], 3, $data, $tableName);
                log_place_order($this->code . '_notifyurl', $orderid, "----代付失败");    //日志
            }
            $json_result = "ok";
        } else {
            log_place_order($this->code . '_notifyurl', $orderid . '----签名错误: ', $sign);
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
                "ShopUserLongId" => $config['mch_id'], //商户号
                "CurrencyId" => 11,
                
            );
            $post_data["EncryptValue"] = $this->get_sign($post_data, $config['signkey']);
            log_place_order($this->code . '_queryBalance', "提交", json_encode($post_data));    //日志
            $returnContent = $this->http_post_json($config['serverreturn'], $post_data);
            log_place_order($this->code . '_queryBalance', "url", $config['serverreturn']);    //日志
            log_place_order($this->code . '_queryBalance', "返回", $returnContent);    //日志
            $result = json_decode($returnContent,true);
            
            if($result['Success'] === true){
                $balance = $result['AmountAvailable'];  //可用金额
                $msg = <<<MSG
                <tr><th>说明</th><th>值</th></tr>
<tr onmouseout="this.style.backgroundColor='#f5f5f5';" onmouseover="this.style.backgroundColor='#009688';"><td>可用金额</td><td><b>$balance </b></td></tr>
MSG;
            }else{
                $msg = <<<MSG
                <tr><th>说明</th></tr>
                <tr><td>未查询出结果</td></tr>
MSG;
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
$msg
</table>
AAA;
            $this->ajaxReturn(['status' => 1, 'msg' => '成功', 'data' => $html]);
        }
    }
    
    //账户余额查询
    public function queryBalance2($config)
    {
        $post_data = array(
                "ShopUserLongId" => $config['mch_id'], //商户号
                "CurrencyId" => 11,
            );
        $post_data["EncryptValue"] = $this->get_sign($post_data, $config['signkey']);
        log_place_order($this->code . '_queryBalance2', "提交", json_encode($post_data));    //日志
        $returnContent = $this->http_post_json($config['serverreturn'], $post_data);
        log_place_order($this->code . '_queryBalance2', "返回", $returnContent);    //日志
        $result = json_decode($returnContent,true);
        if($returnContent['Success'] === true){
            $result_data['resultCode'] = "0";
            $result_data['balance'] = $result['AmountAvailable'];
        }
        return $result_data;
    }

    //代付订单查询
    public function PaymentQuery($data, $config)
    {
        $post_data = [
            "ShopUserLongId" => $config['mch_id'], //商户号
            'ShopOrderId' => $data['orderid'],
        ];
        $post_data["EncryptValue"] = $this->get_sign($post_data, $config['signkey']);
        log_place_order($this->code . '_PaymentQuery', $data['orderid'] . "----提交", json_encode($post_data, JSON_UNESCAPED_UNICODE));    //日志
        $returnContent = $this->http_post_json($config['query_gateway'], $post_data);
        log_place_order($this->code . '_PaymentQuery', $data['orderid'] . "----返回", $returnContent);    //日志
        $result = json_decode($returnContent, true);
        if ($result['Success'] === true ) {
            switch ($result['PaymentOrder']['PaymentOrderStatusId']) {       //订单状态编号,1:待支付,2:成功,3:取消(驳回),4:待审核
                case 1:
                case 4:
                    $return = ['status' => 1, 'msg' => '处理中'];
                    break;
                case 2:
                    $return = ['status' => 2, 'msg' => '成功'];
                    break;
                case 3:
                    $return = ['status' => 3, 'msg' => '失败','remark' => $result['remark']];
                    break;
            }
        } else {
            $return = ['status' => 7, 'msg' => "查询接口失败:".$result['Success']];
        }
        return $return;
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
        // log_place_order($this->code, $orderid . "----签名", strtolower($signStr));    //日志
        return  strtoupper(hash('sha256', strtolower($signStr)));
    }

}
