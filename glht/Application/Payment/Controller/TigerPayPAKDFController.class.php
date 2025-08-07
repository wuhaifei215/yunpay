<?php

namespace Payment\Controller;

class TigerPayPAKDFController extends PaymentController
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
            "outTradeId" => $data['orderid'], //订单号
            "amount" =>sprintf('%.2f', $data['money']),  //提现金额
            "productCode" => '40001',
            "merchantId" => $config['mch_id'], //商户号
            "bankType" => 'WALLET',
            "bankName" => $data['bankname'],
            "bankClearingNumber" => $data['bankname'],
            "bankCode" => $data['banknumber'],    //账号
            "realName" => $data['bankfullname'],  //户名
            "notifyUrl" => 'https://' . C('NOTIFY_DOMAIN') . "/Payment_" . $this->code . "_notifyurl.html",      //异步通知地址
        );
        $post_data["sign"] = $this->sign($post_data, $config['signkey']);
        
        $attach = json_decode($data['extends'],true);
        $post_data['phone'] = $attach['phone'];
        $post_data['attach'] = json_encode(['cnic'=>$attach['cnic']]);
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
                
                logApiAddPayment('商户提交YunPay', __METHOD__, $data['orderid'], $data['out_trade_no'], '/', $userdfpost, [], '0', '0', '1', '2');
                
                // 结束并输出执行时间
                $endTime = microtime(TRUE);
                $doTime = floor(($endTime-$beginTime)*1000);
                logApiAddPayment('订单提交三方TigerPay', __METHOD__, $data['orderid'], $data['out_trade_no'], $config['exec_gateway'], $post_data, $result, $doTime, '0', '1', '1');
            }catch (\Exception $e) {
                // var_dump($e);
            }
        // }
        log_place_order($this->code, $data['orderid'] . "----返回", json_encode($result, JSON_UNESCAPED_UNICODE));    //日志

        // log_place_order($this->code, $data['orderid'] . "----状态：", $result['status']);    //日志
        if($result['code'] === 200){
            //保存第三方订单号
            // $orderid = $data['orderid'];
            // $Wttklistmodel = D('Wttklist');
            // $date = date('Ymd',strtotime(substr($orderid, 1, 8)));  //获取订单日期
            // $tableName = $Wttklistmodel->getRealTableName($date);
            // $re_save = $Wttklistmodel->table($tableName)->where(['orderid' => $orderid])->save(['three_orderid'=>$result['order']]);
            if($result['data']['orderStatus'] == 0 || $result['data']['orderStatus'] == 1){     //0待审核 1 待支付 2已支付 3 已经驳回 4 下单异常
                $return = ['status' => 1, 'msg' => '申请正常'];
            }
        }elseif($result['code'] === 400){
                    $return = ['status' => 3, 'msg' => '申请失败:' . $result['message']];
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
        $orderid = $re_data['outTradeId'];
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
        
        $sign_data = [
            'orderId' => $re_data['orderId'],
            'orderStatus' => $re_data['orderStatus'],
            'outTradeId' => $re_data['outTradeId'],
            'amount' => $re_data['amount'],
        ];
        $sign = $this->sign($sign_data, $config['signkey']);
        if ($sign === $re_data["sign"]) {
            if ($re_data['orderStatus'] == 2) {      //0待审核 1 待支付 2已支付 3 驳回 4 下单异常
                $data = [
                    'memo' => '代付成功',
                ];
                $this->changeStatus($Order['id'], 2, $data, $tableName);
                // $this->handle($Order['id'], 2, $data, $tableName);
                log_place_order($this->code . '_notifyurl', $orderid, "----代付成功");    //日志
            } elseif ($re_data['orderStatus'] == 3 || $re_data['orderStatus'] == 4) {
                //代付失败
                $data = [
                    'memo' => '代付失败-' . $re_data['orderStatusName'],
                ];
                $this->changeStatus($Order['id'], 3, $data, $tableName);
                // $this->handle($Order['id'], 3, $data, $tableName);
                log_place_order($this->code . '_notifyurl', $orderid, "----代付失败");    //日志
            }
            $json_result = "success";
        } else {
            log_place_order($this->code . '_notifyurl', $orderid . '----签名错误: ', $sign);
            $json_result = "sign fail";
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
                "merchantId" => $config['mch_id'], //商户号
            );
            $post_data["sign"] = $this->sign($post_data, $config['signkey']);
            log_place_order($this->code . '_queryBalance', "提交", json_encode($post_data));    //日志
            $returnContent = $this->http_post_json($config['serverreturn'], $post_data);
            log_place_order($this->code . '_queryBalance', "返回", $returnContent);    //日志
            $result = json_decode($returnContent,true);
            if($result['code'] === 200){
                $merchantAmount = $result['data']['merchantAmount'];  //支付余额
                $withdrawAmount = $result['data']['withdrawAmount'];  //代付余额
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
<tr onmouseout="this.style.backgroundColor='#f5f5f5';" onmouseover="this.style.backgroundColor='#009688';"><td>支付余额</td><td><b>$merchantAmount </b></td></tr>
<tr onmouseout="this.style.backgroundColor='#f5f5f5';" onmouseover="this.style.backgroundColor='#009688';"><td>代付余额</td><td><b>$withdrawAmount </b></td></tr>
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
        $post_data = array(
            "merchantId" => $config['mch_id'], //商户号
        );
        $post_data["sign"] = $this->sign($post_data, $config['signkey']);
        log_place_order($this->code . '_queryBalance2', "提交", json_encode($post_data));    //日志
        $returnContent = $this->http_post_json($config['serverreturn'], $post_data);
        log_place_order($this->code . '_queryBalance2', "返回", $returnContent);    //日志
        $result = json_decode($returnContent,true);
        if($result['code'] === 200){
            $result_data['resultCode'] = "0";
            $result_data['balance'] = $result['data']['withdrawAmount'];
        }
        return $result_data;
    }
    
    //代付订单查询
    public function PaymentQuery($data, $config)
    {
        $post_data = [
            "merchantId" => $config['mch_id'], //商户号
            'outTradeId ' => $data['orderid'],
        ];
        $post_data["sign"] = $this->sign($post_data, $config['signkey']);
        log_place_order($this->code . '_PaymentQuery', $data['orderid'] . "----提交", json_encode($post_data, JSON_UNESCAPED_UNICODE));    //日志
        $returnContent = $this->http_post_json($config['query_gateway'], $post_data);
        log_place_order($this->code . '_PaymentQuery', $data['orderid'] . "----返回", $returnContent);    //日志
        $result = json_decode($returnContent, true);
        if ($result['code'] === 200) {
            switch ($result['data']['orderStatus']) {       //0待审核 1 待支付 2已支付 3 已经驳回 4 下单异常
                case 0:
                case 1:
                    $return = ['status' => 1, 'msg' => '处理中'];
                    break;
                case 2:
                    $return = ['status' => 2, 'msg' => '成功'];
                    break;
                case 3:
                case 4:
                    $return = ['status' => 3, 'msg' => '失败','remark' => $result['remark']];
                    break;
            }
        } else {
            $return = ['status' => 7, 'msg' => "查询接口失败:".$result['code']];
        }
        return $return;
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
    // private function http_post_json($url, $native=[])
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
            if ("" != $v && "sign" != $k && "info" != $k) {
                $signPars .= $k . "=" . $v . "&";
            }
        }
        $sign =strtoupper(md5(rtrim($signPars,'&')));
        // log_place_order($this->code, $orderid . "----签名", rtrim($signPars,'&'));    //日志
        return $sign; //最终的签名
    }
}
