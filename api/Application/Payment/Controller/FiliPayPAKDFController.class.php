<?php

namespace Payment\Controller;

class FiliPayPAKDFController extends PaymentController
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
        if($data['type'] === 'wallet'){
            $cardType = 'WALLET';
        }else{
            $cardType = $data['type'];
        }
        $attach = json_decode($data['extends'],true);
        $post_data = array(
            "custId" => $config['mch_id'], //商户号
            "amount" =>intval(sprintf('%.2f', $data['money']) * 100),  //提现金额
            "backUrl" => 'https://' . C('NOTIFY_DOMAIN') . "/Payment_" . $this->code . "_notifyurl.html",      //异步通知地址
            "merchantOrderId" => $data['orderid'], //订单号
            "userName" => $data['bankfullname'],  //户名
            "userPhone" => $attach['phone'],
            "userEmail" => "925@gmail.com",
            "userCart" => $attach['cnic'],
            "countryCode" =>"PAK",
            "currencyCode" =>"PKR",
            "cardType" =>$cardType,        //cardType 代付类型     BANK = 银行 WALLET = 钱包
            "walletBank" => $data['bankname'],  //银行名称  为WALLET，walletBank填写 JAZZCASH 或 EASYPAISA
            "walletId" => $data['banknumber'],    //账号
        );
        $post_data["sign"] = $this->get_sign($post_data, $config['signkey']);
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
        if($result['code'] === 200){
            //保存第三方订单号
            // $orderid = $data['orderid'];
            // $Wttklistmodel = D('Wttklist');
            // $date = date('Ymd',strtotime(substr($orderid, 1, 8)));  //获取订单日期
            // $tableName = $Wttklistmodel->getRealTableName($date);
            // $re_save = $Wttklistmodel->table($tableName)->where(['orderid' => $orderid])->save(['three_orderid'=>$result['order']]);
            switch ($result['orderStatus']) {      //订单状态:0:等待付款,1付款成功,2付款失败,3付款被拒绝,4付款待审核
                case 0:
                case 4:
                    $return = ['status' => 1, 'msg' => '申请正常'];
                    break;
            }
        }else{
            $return = ['status' => 0, 'msg' => $result['msg']];
        }
        return $return;
    }

    public function notifyurl()
    {
        $re_data = json_decode(file_get_contents("php://input"), true);
        //获取报文信息
        $orderid = $re_data['merchantOrderId'];
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
        
        $sign = $this->get_sign($re_data,$config['signkey']);
        
        if ($sign === $re_data["sign"]) {
            if ($re_data['orderStatus'] === 1) {     //订单状态:0:等待付款,1付款成功,2付款失败,3付款被拒绝,4付款待审核
                //代付成功 更改代付状态 完善代付逻辑
                $data = [
                    'memo' => '代付成功',
                ];
                $this->changeStatus($Order['id'], 2, $data, $tableName);
                log_place_order($this->code . '_notifyurl', $orderid, "----代付成功");    //日志
            } elseif ($re_data['orderStatus'] === 2) {
                //代付失败
                $data = [
                    'memo' => '代付失败-' . $re_data['msg'],
                ];
                $this->changeStatus($Order['id'], 3, $data, $tableName);
                log_place_order($this->code . '_notifyurl', $orderid, "----代付失败");    //日志
            }
            $json_result = "success";
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
                "custId" => $config['mch_id'], //商户号
            );
            $post_data["sign"] = $this->get_sign($post_data, $config['signkey']);
            log_place_order($this->code . '_queryBalance', "提交", json_encode($post_data));    //日志
            $returnContent = $this->http_post_json($config['serverreturn'], $post_data);
            log_place_order($this->code . '_queryBalance', "url", $config['serverreturn']);    //日志
            log_place_order($this->code . '_queryBalance', "返回", $returnContent);    //日志
            $result = json_decode($returnContent,true);
            
            if($result['code'] === 200){
                $balance = $result['balance'];  //可用金额
                $not_settlement_balance = $result['not_settlement_balance'];  //在途余额
                $freeze = $result['frozen_balance'];  //冻结余额
                $msg = <<<MSG
                <tr><th>说明</th><th>值</th></tr>
<tr onmouseout="this.style.backgroundColor='#f5f5f5';" onmouseover="this.style.backgroundColor='#009688';"><td>可用金额</td><td><b>$balance </b></td></tr>
<tr onmouseout="this.style.backgroundColor='#f5f5f5';" onmouseover="this.style.backgroundColor='#009688';"><td>未结算余额
</td><td><b>$not_settlement_balance </b></td></tr>
<tr onmouseout="this.style.backgroundColor='#f5f5f5';" onmouseover="this.style.backgroundColor='#009688';"><td>冻结金额</td><td><b>$freeze </b></td></tr>
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
                "custId" => $config['mch_id'], //商户号
            );
        $post_data["sign"] = $this->get_sign($post_data, $config['signkey']);
        log_place_order($this->code . '_queryBalance2', "提交", json_encode($post_data));    //日志
        $returnContent = $this->http_post_json($config['serverreturn'], $post_data);
        log_place_order($this->code . '_queryBalance2', "返回", $returnContent);    //日志
        $result = json_decode($returnContent,true);
        if($returnContent['code'] === 200){
            $result_data['resultCode'] = "0";
            $result_data['balance'] = $result['balance'];
        }
        return $result_data;
    }

    //代付订单查询
    public function PaymentQuery($data, $config)
    {
        $post_data = [
            "custId" => $config['mch_id'], //商户号
            'merchantOrderId' => $data['orderid'],
        ];
        $post_data["sign"] = $this->get_sign($post_data, $config['signkey']);
        log_place_order($this->code . '_PaymentQuery', $data['orderid'] . "----提交", json_encode($post_data, JSON_UNESCAPED_UNICODE));    //日志
        $returnContent = $this->http_post_json($config['query_gateway'], $post_data);
        log_place_order($this->code . '_PaymentQuery', $data['orderid'] . "----返回", $returnContent);    //日志
        $result = json_decode($returnContent, true);
        if ($result['code'] === 200 ) {
            switch ($result['orderStatus']) {       //订单状态:0:等待付款,1付款成功,2付款失败,3付款被拒绝,4付款待审核
                case '0':
                case '4':
                    $return = ['status' => 1, 'msg' => '处理中'];
                    break;
                case '1':
                    $return = ['status' => 2, 'msg' => '成功'];
                    break;
                case '2':
                case '3':
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
        $ch = curl_init($url);
        $payload = json_encode($postData);
    
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
    
    // //发送get请求，提交json字符串
    // private function http_get_json($url, $native=[])
    // {
    //     if(!empty($native)){
    //         $url = $url . "?" . http_build_query($native);
            
    //         log_place_order($this->code, "----url", $url);    //日志
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
    private function get_sign($data, $key)
    {

        if(isset($data['sign'])) {
          unset($data['sign']);
        }
       if(isset($data['other'])) {
          unset($data['other']);
        } 
        ksort($data);

        $r1 = '';
        foreach($data as $k => $v) {
            if($v !== '') { //空字符串不参与md5
                $r1 .= $k . '=' . $v . '&';
            }
        }
        $r1 .= 'key=' . $key; //需要md5的字符串
        
        $sign = '';
        $sign = md5(strtolower(trim($r1, '&'))); //全部转小写然后md5
        //echo "md5前字符串：$r1\nmd5后字符串：$ret";
        log_place_order($this->code, $orderid . "----签名", strtolower(trim($r1, '&')));    //日志
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
