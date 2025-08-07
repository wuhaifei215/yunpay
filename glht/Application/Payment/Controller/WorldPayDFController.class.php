<?php

namespace Payment\Controller;

class WorldPayDFController extends PaymentController
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
            "mchId" => $config['mch_id'], //商户号
            "mchOrderId" => $data['orderid'], //订单号
            "bankCode" => $data['bankname'],
            "bankAccount" => $data['banknumber'],    //账号
            "bankOwner" => $data['bankfullname'],  //户名
            "amount" =>intval($data['money']),  //提现金额
            "notifyUrl" => 'https://' . C('NOTIFY_DOMAIN') . "/Payment_" . $this->code . "_notifyurl.html",      //异步通知地址
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
        if($result['code']){
            if($result['code'] === 200){
                $return = ['status' => 1, 'msg' => '申请正常'];
            }else{
                $return = ['status' => 3, 'msg' => $result['message']];
            }
        }else{
            $return = ['status' => 0, 'msg' => $result['message']];
        }
        return $return;
    }

    public function notifyurl()
    {
        $re_data = json_decode(file_get_contents('php://input'), true);
        //获取报文信息
        $orderid = $re_data['mchOrderId'];
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

        $sign = $this->get_sign($re_data, $config['signkey']);
        
        if ($sign === $re_data["sign"]) {
            if ($re_data['isPaid'] === 1) {     //支付状态，0为未支付，1为已支付，2为支付失败
                //代付成功 更改代付状态 完善代付逻辑
                $data = [
                    'memo' => '代付成功',
                ];
                $this->changeStatus($Order['id'], 2, $data, $tableName);
                log_place_order($this->code . '_notifyurl', $orderid, "----代付成功");    //日志
            } elseif ($re_data['isPaid'] === 2) {
                //代付失败
                $data = [
                    'memo' => '代付失败-' . $re_data['msg'],
                ];
                $this->changeStatus($Order['id'], 3, $data, $tableName);
                log_place_order($this->code . '_notifyurl', $orderid, "----代付失败");    //日志
            }
            $json_result = 'success';
        } else {
            log_place_order($this->code . '_notifyurl', $orderid . '----签名错误: ', $sign);
            $json_result = 'fail';
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
            'mchId' => $config['mch_id'],
            'mchOrderId' => $data['orderid'],
        ];
        $post_data["sign"] = $this->get_sign($post_data, $config['signkey']);
        log_place_order($this->code . '_PaymentQuery', $data['orderid'] . "----提交", json_encode($post_data, JSON_UNESCAPED_UNICODE));    //日志
        $returnContent = $this->http_post_json($config['query_gateway'], $post_data);
        log_place_order($this->code . '_PaymentQuery', $data['orderid'] . "----返回", $returnContent);    //日志
        $result = json_decode($returnContent, true);
        if ($result['code'] === 200) {
            switch ($result['data']['isPaid']) {       //支付状态，0为未支付，1为已支付，2为支付失败
                case 0:
                    $return = ['status' => 1, 'msg' => '处理中'];
                    break;
                case 1:
                    $return = ['status' => 2, 'msg' => '成功'];
                    break;
                case 2:
                    $return = ['status' => 3, 'msg' => $result['message']];
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
                "mchId" => $config['mch_id'], //商户号
            );
            $post_data["sign"] = $this->get_sign($post_data, $config['signkey']);
            log_place_order($this->code . '_queryBalance', "提交", json_encode($post_data));    //日志
            log_place_order($this->code . '_queryBalance', "url", $config['serverreturn']);    //日志
            $returnContent = $this->http_post_json($config['serverreturn'], $post_data);
            log_place_order($this->code . '_queryBalance', "返回", $returnContent);    //日志
            $result = json_decode($returnContent, true);
            if($result['code'] === 200){
                $acBal = $result['data']['balance'];  //总金额
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
            "mchId" => $config['mch_id'], //商户号
        );
        $post_data["sign"] = $this->get_sign($post_data, $config['signkey']);
        log_place_order($this->code . '_queryBalance2', "提交", json_encode($post_data));    //日志
        $returnContent = $this->http_post_json($config['serverreturn'], $post_data);
        log_place_order($this->code . '_queryBalance2', "返回", $returnContent);    //日志
        $result = json_decode($returnContent, true);
        if($result['success'] === true){
            $result_data['resultCode'] = "0";
            $result_data['balance'] = $result['data']['balance'];
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
            if($key == 'costFee'){
                $value = number_format($value,1);
            }
            $signStr .= $key . '=' . $value . '&';
        }
        $signStr .= 'sign=' . $sign_key;
        // log_place_order($this->code, $orderid . "----签名", $signStr);    //日志
        // log_place_order($this->code, $orderid . "----key", $sign_key);    //日志
        return md5($signStr);
    }
}
