<?php

namespace Payment\Controller;

class U88PayDFController extends PaymentController
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
        if($data['type']=='Gcash'){
            $pay_type = 'PHP_GCASH_S';
            $bank_code = 'GCASH';
        } elseif ($data['type']=='Maya'){
            $pay_type = 'PHP_MAYA_S';
            $bank_code = 'MAYA';
        } else {
            $return = ['status' => 0, 'msg' => '支付类型错误'];
            return $return;
        }
        $post_data = array(
            "merchant_id" => $config['mch_id'], //商户号
            "merchant_order_no" => $data['orderid'], //订单号
            "pay_type" => $pay_type,
            'currency' =>'PHP',
            "amount" => $data['money'],  //提现金额（单位分）
            "notify_url" => 'https://' . C('NOTIFY_DOMAIN') . "/Payment_" . $this->code . "_notifyurl.html",      //异步通知地址
            "bank_code" => $bank_code,
            "bank_name" => $bank_code,
            "account_name" => $data['bankfullname'],  //户名
            "account_number" => $data['banknumber'],    //账号
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
                logApiAddPayment('订单提交三方', __METHOD__, $data['orderid'], $data['out_trade_no'], $config['exec_gateway'], $post_data, $result, $doTime, '0', '1', '2');
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
            // $re_save = $Wttklistmodel->table($tableName)->where(['orderid' => $orderid])->save(['three_orderid'=>$result['order_no']]);
            // $return = ['status' => 2, 'msg' => '代付成功'];
            $return = ['status' => 1, 'msg' => '申请正常'];
        }else{
            $return = ['status' => 0, 'msg' => $result['message']];
        }
        return $return;
    }

    public function notifyurl()
    {
        $re_data = json_decode(file_get_contents('php://input'), true);
        //获取报文信息
        $orderid = $re_data['merchant_order_no'];
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
        if ($sign === $re_data["sign"]) {
            if ($re_data['status'] == "2") {     //订单状态 0：未支付；1：支付中；2：已支付；3：支付失败；4：已退款/冲正（开始成功 后面银行退款了） （说明：0,1 状态后面会变成2,3成功或者失败的状态，请勿将0,1当做成功或者失败处理）
                //代付成功 更改代付状态 完善代付逻辑
                $data = [
                    'memo' => '代付成功',
                ];
                $this->changeStatus($Order['id'], 2, $data, $tableName);
                // $this->handle($Order['id'], 2, $data, $tableName);
                log_place_order($this->code . '_notifyurl', $orderid, "----代付成功");    //日志
                $json_result = "OK";
            } elseif ($re_data['status'] == "3" || $re_data['status'] == "4") {
                //代付失败
                $data = [
                    'memo' => '代付失败-' . $re_data['paid_voucher'],
                ];
                $this->changeStatus($Order['id'], 3, $data, $tableName);
                // $this->handle($Order['id'], 3, $data, $tableName);
                log_place_order($this->code . '_notifyurl', $orderid, "----代付失败");    //日志
                $json_result = "OK";
            }
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

    //代付订单查询
    public function PaymentQuery($data, $config)
    {
        $post_data = [
            'merchant_id' => $config['mch_id'],
            'merchant_order_no' => $data['orderid'],
        ];
        $post_data["sign"] = $this->get_sign($post_data, $config['signkey']);
        log_place_order($this->code . '_PaymentQuery', $data['orderid'] . "----提交", json_encode($post_data, JSON_UNESCAPED_UNICODE));    //日志
        $returnContent = $this->http_post_json($config['query_gateway'], $post_data);
        log_place_order($this->code . '_PaymentQuery', $data['orderid'] . "----返回", $returnContent);    //日志
        $result = json_decode($returnContent, true);
        if ($result['code'] === 200) {
            switch ($result['data']['status']) {       //0：未支付；1：支付中；2：已支付；3：支付失败；
                case 0:
                case 1:
                    $return = ['status' => 1, 'msg' => '处理中'];
                    break;
                case 2:
                    $return = ['status' => 2, 'msg' => '成功'];
                    break;
                case 3:
                    $return = ['status' => 3, 'msg' => $result['paid_voucher'],'remark' => $result['paid_voucher']];
                    break;
            }
        } else {
            $return = ['status' => 7, 'msg' => "查询接口失败:".$result['code']];
        }
        return $return;
    }
    
    
    //账户余额查询
    public function queryBalance()
    {
        if (IS_AJAX) {
            $config = M('pay_for_another')->where(['code' => $this->code])->find();
            $post_data = array(
                "merchant_id" => $config['mch_id'], //商户号
                "country_id" => '5',
            );
            $post_data["sign"] = $this->get_sign($post_data, $config['signkey']);
            log_place_order($this->code . '_queryBalance', "提交", json_encode($post_data));    //日志
            $returnContent = $this->http_post_json($config['serverreturn'], $post_data);
            log_place_order($this->code . '_queryBalance', "返回", $returnContent);    //日志
            $result = json_decode($returnContent, true);
            if($result['code'] === 200){
                $acBal = $result['data']['total_balance'];  //总金额
                $available = $result['data']['available_balance'];  //可用金额
                $acT0Froz = $result['data']['frozen_amount'];  //冻结金额
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
    public function PaymentVoucher($data, $config){
        if(isset($data['three_orderid'])){
            $post_data = [
                'custId' => $config['mch_id'],
                'appId' => $config['appid'],
                // 'order' => $data['three_orderid'],
                'order' => $data['orderid'],
            ];
            $post_data["sign"] = $this->get_sign($post_data, $config['signkey']);
            log_place_order($this->code . '_PaymentVoucher', $data['orderid'] . "----提交", json_encode($post_data, JSON_UNESCAPED_UNICODE));    //日志
            $returnContent = $this->http_post_json('https://phlapi.newwinpay.site/br/voucherData.json', $post_data);
            log_place_order($this->code . '_PaymentVoucher', $data['orderid'] . "----返回", $returnContent);    //日志
            $result = json_decode($returnContent, true);
        
            // $redata = json_decode(file_get_contents('https://api.winpay.site/payment/br/voucherData.webapp?casOrdNo=' . $data['three_orderid']),true);
            log_place_order($this->code . '_PaymentVoucher', $data['three_orderid'] . "----返回",  json_encode($result, JSON_UNESCAPED_UNICODE));    //日志
            if(!empty($result) && $result['code'] === '000000'){
                return  $result;
            }else{
                return false;
            }
        }else{
            return false;
        }
        
    }

    //发送post请求，提交json字符串
    private function http_post_json($url, $postData, $options = array())
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


    /**
     * 签名算法
     * @param $data         请求数据
     * @param $md5Key       md5秘钥
     */
    private function get_sign($param, $key)
    {
        $signPars = "";
        ksort($param);
        $param['key'] = $key;
        foreach ($param as $k => $v) {
            if ("" != $v && "sign" != $k && "pay_md5sign" != $k) {
                $signPars .= $k . "=" . $v . "&";
            }
        }
        $sign =strtoupper(md5(rtrim($signPars,'&')));
        // log_place_order($this->code, $orderid . "----签名", rtrim($signPars,'&'));    //日志
        return $sign; //最终的签名

    }
}
