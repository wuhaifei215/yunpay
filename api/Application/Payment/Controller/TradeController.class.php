<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-10-30
 * Time: 21:24
 */
namespace Payment\Controller;

class TradeController extends CreateDFController
{
    public function __construct()
    {
        parent::__construct();
    }
    
    //代付查询
    public function query()
    {
        $out_trade_no = I('request.out_trade_no', '', 'string,strip_tags,htmlspecialchars');
        if (!$out_trade_no) {
            $this->showmessage("out_trade_no error");
        }
        $datetime = I('request.datetime', '', 'string,strip_tags,htmlspecialchars');
        if (!$datetime) {
            $this->showmessage("datetime error");
        }
        $sign = I('request.pay_md5sign', '', 'string');
        if (!$sign) {
            $this->showmessage("pay_md5sign error");
        }
        $user_id = $this->memberid - 10000;
        //用户信息
        $this->merchants = D('Member')->where(array('id' => $user_id))->find();
        if (empty($this->merchants)) {
            $this->showmessage('Merchant does not exist');
        }
        if (!$this->merchants['df_api']) {
            $this->showmessage('The merchant has not enabled the payment function');
        }
        $referer = getHttpReferer();
        if ($this->merchants['df_domain'] != '') {
            if (!checkDfDomain($referer, $this->merchants['df_domain'])) {
                $this->showmessage('The request source domain name is inconsistent with the reported domain name');
            }
        }
        if ($this->merchants['df_ip'] != '') {
            if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']) {
                $ip = $_SERVER['REMOTE_ADDR'];
            } else {
                $ip = getRealIp();
            }
            if (!checkDfIp($ip, $this->merchants['df_ip'])) {
            $this->showmessage('The submitted IP address has not been reported!');
            }
            // $hostname = getHost($referer);//请求来源域名
            // $domainIp = gethostbyname($hostname);//域名IP
            // if (!checkDfIp($domainIp, $this->merchants['df_ip'])) {
            //     $this->showmessage('The IP address is inconsistent with the reported IP!');
            // }
        }
        $request = [
            'mchid' => $this->memberid,
            'out_trade_no' => $out_trade_no,
            'datetime' => $datetime
        ];

        $signature = $this->createSign($this->merchants['apikey'], $request);
        if ($signature != $sign) {
            ksort($_POST);
            $md5str = "";
            foreach ($_POST as $key => $val) {
                if (!empty($val) && $key != 'pay_md5sign') {
                    $md5str = $md5str . $key . "=" . $val . "&";
                }
            }
            // $sign = strtoupper(md5($md5str . "key=" . $this->merchants['apikey']));
            
            $result = [
                'mgs' => 'Please compare the splicing order and signature',
                'PostData' => $requestarray,
                'Field concatenation order' => substr($md5str, 0, strlen($md5str) - 1),
                // 'sign' => $sign,
            ];
            $this->showmessage('sign error', $result);
        }
        $where = [
            'userid' => $user_id,
            'out_trade_no' => $out_trade_no,
            'sqdatetime'=>['between',[date('Y-m-d H:i:s',strtotime($datetime . ' 00:00:00')),date('Y-m-d H:i:s',strtotime($datetime . ' 23:59:59'))]],
        ];
        $Wttklist = D('Wttklist');
        $order = $Wttklist->table($Wttklist->getRealTableName($datetime))->where($where)->find();
        if(!$order || empty($order)){
            $where = [
                'userid' => $user_id,
                'out_trade_no' => $out_trade_no,
                'sqdatetime'=>['between',[date('Y-m-d H:i:s',strtotime($datetime . ' 00:00:00')),date('Y-m-d H:i:s',strtotime($datetime . ' 23:59:59') + 86400)]],
            ];
            $t_date = date('Y-m-d',strtotime($datetime . ' 23:59:59') + 86400);
            $Wttklist = D('Wttklist');
            try {
                $order = $Wttklist->table($Wttklist->getRealTableName($t_date))->where($where)->find();
            } catch (\Exception $e) {
                
            }
        }
        // echo $Wttklist->table($Wttklist->getRealTableName($datetime))->getLastSql();
        // var_dump($order);
        if (!$order) {
            $return = [
                'status' => 'error',
                'msg' => 'Error',
                'refCode' => '7',
                'refMsg' => 'Transaction does not exist',
            ];
        }else {
            if ($order['status'] == 0) {
                $refCode = '4';
                $refMsg = "Pending";
            } elseif ($order['status'] == 1) {
                $refCode = '3';
                $refMsg = "Processing";
            } elseif ($order['status'] == 2 || $order['status'] == 3) {
                $refCode = '1';
                $refMsg = "Success";
            } elseif ($order['status'] == 4 || $order['status'] == 5) {
                $refCode = '2';
                $refMsg = "Fail";
            } elseif ($order['status'] == 6) {
                $refCode = '5';
                $refMsg = "reject";
            } else {
                $refCode = '8';
                $refMsg = "unknown status";
            }
            $return = [
                'status' => 'success',
                'msg' => 'Success',
                'orderType' => 'df',
                'mchid' => $this->memberid,
                'out_trade_no' => $order['out_trade_no'],
                'amount' => $order['money'],
                'transaction_id' => $order['orderid'],
                'refCode' => $refCode,
                'refMsg' => $refMsg,
            ];
            if ($refCode == 1) {
                $return['success_time'] = $order['cldatetime'];
            }
            $return['sign'] = $this->createSign($this->merchants['apikey'], $return);
        }
        echo json_encode($return);
        exit;
    }

    //余额查询
    public function balance()
    {
        $sign = I('request.pay_md5sign', '', 'string');
        if (!$sign) {
            $this->showmessage("pay_md5sign error");
        }
        $user_id = $this->memberid - 10000;
        //用户信息
        $this->merchants = D('Member')->where(array('id' => $user_id))->find();
        if (empty($this->merchants)) {
            $this->showmessage('Merchant does not exist');
        }
        if (!$this->merchants['df_api']) {
            $this->showmessage('The merchant has not enabled the payment function');
        }
        
        //用户国家信息
        if($this->merchants['country_id']!=1){
            $this->showmessage("The merchant's country is incorrect!");
        }
        
        $referer = getHttpReferer();
        if ($this->merchants['df_domain'] != '') {
            if (!checkDfDomain($referer, $this->merchants['df_domain'])) {
                $this->showmessage('The request source domain name is inconsistent with the reported domain name');
            }
        }
        if ($this->merchants['df_ip'] != '') {
            if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']) {
                $ip = $_SERVER['REMOTE_ADDR'];
            } else {
                $ip = get_client_ip();
            }
            if (!checkDfIp($ip, $this->merchants['df_ip'])) {
            $this->showmessage('The submitted IP address has not been reported!');
            }
            // $hostname = getHost($referer);//请求来源域名
            // $domainIp = gethostbyname($hostname);//域名IP
            // if (!checkDfIp($domainIp, $this->merchants['df_ip'])) {
            //     $this->showmessage('The IP address is inconsistent with the reported IP!');
            // }
        }

        $request = [
            'mchid' => $this->memberid
        ];

        $signature = $this->createSign($this->merchants['apikey'], $request);
        if ($signature != $sign) {
            ksort($_POST);
            $md5str = "";
            foreach ($_POST as $key => $val) {
                if (!empty($val) && $key != 'pay_md5sign') {
                    $md5str = $md5str . $key . "=" . $val . "&";
                }
            }
            // $sign = strtoupper(md5($md5str . "key=" . $this->merchants['apikey']));
            
            $result = [
                'mgs' => 'Please compare the splicing order and signature',
                'POST Data' => $_POST,
                'Field concatenation order' => substr($md5str, 0, strlen($md5str) - 1),
                // 'sign' => $sign,
            ];
            $this->showmessage('sign error', $result);
        }
        $return = [
            'status' => 'success',
            'msg' => 'Successed',
            'mchid' => $this->memberid,
            'data' => [
                [
                    'currency' => 'PHP',
                    'balance' => $this->merchants['balance_php'],//菲律宾可提现余额
                    'blockedbalance' => $this->merchants['blockedbalance_php'],//菲律宾冻结余额
                ],
                // [
                //     'currency' => 'INR',
                //     'balanceINR' => $this->merchants['balance_inr'],//越南可提现余额
                //     'blockedbalanceINR' => $this->merchants['blockedbalance_inr'],//越南冻结余额
                // ],
            ],
        ];
        $return['sign'] = $this->createSign($this->merchants['apikey'], $return);
        echo json_encode($return);
    }
    
    //余额查询
    public function balanceMXN()
    {
        $currency = I('request.currency', '', 'string');
        if (!$currency || $currency!= 'MXN') {
            $this->showmessage("currency error");
        }
        $sign = I('request.pay_md5sign', '', 'string');
        if (!$sign) {
            $this->showmessage("pay_md5sign error");
        }
        $user_id = $this->memberid - 10000;
        //用户信息
        $this->merchants = D('Member')->where(array('id' => $user_id))->find();
        if (empty($this->merchants)) {
            $this->showmessage('Merchant does not exist');
        }
        if (!$this->merchants['df_api']) {
            $this->showmessage('The merchant has not enabled the payment function');
        }
        
        //用户国家信息
        if($this->merchants['country_id']!=2){
            $this->showmessage("The merchant's country is incorrect!");
        }
        
        $referer = getHttpReferer();
        if ($this->merchants['df_domain'] != '') {
            if (!checkDfDomain($referer, $this->merchants['df_domain'])) {
                $this->showmessage('The request source domain name is inconsistent with the reported domain name');
            }
        }
        if ($this->merchants['df_ip'] != '') {
            if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']) {
                $ip = $_SERVER['REMOTE_ADDR'];
            } else {
                $ip = get_client_ip();
            }
            if (!checkDfIp($ip, $this->merchants['df_ip'])) {
            $this->showmessage('The submitted IP address has not been reported!');
            }
            // $hostname = getHost($referer);//请求来源域名
            // $domainIp = gethostbyname($hostname);//域名IP
            // if (!checkDfIp($domainIp, $this->merchants['df_ip'])) {
            //     $this->showmessage('The IP address is inconsistent with the reported IP!');
            // }
        }

        $request = [
            'mchid' => $this->memberid
        ];

        $signature = $this->createSign($this->merchants['apikey'], $request);
        if ($signature != $sign) {
            ksort($_POST);
            $md5str = "";
            foreach ($_POST as $key => $val) {
                if (!empty($val) && $key != 'pay_md5sign') {
                    $md5str = $md5str . $key . "=" . $val . "&";
                }
            }
            // $sign = strtoupper(md5($md5str . "key=" . $this->merchants['apikey']));
            
            $result = [
                'mgs' => 'Please compare the splicing order and signature',
                'POST Data' => $_POST,
                'Field concatenation order' => substr($md5str, 0, strlen($md5str) - 1),
                // 'sign' => $sign,
            ];
            $this->showmessage('sign error', $result);
        }
        $return = [
            'status' => 'success',
            'msg' => 'Successed',
            'mchid' => $this->memberid,
            'data' => [
                [
                    'currency' => 'MXN',
                    'balance' => $this->merchants['balance_php'],//菲律宾可提现余额
                    'blockedbalance' => $this->merchants['blockedbalance_php'],//菲律宾冻结余额
                ],
                // [
                //     'currency' => 'INR',
                //     'balanceINR' => $this->merchants['balance_inr'],//越南可提现余额
                //     'blockedbalanceINR' => $this->merchants['blockedbalance_inr'],//越南冻结余额
                // ],
            ],
        ];
        $return['sign'] = $this->createSign($this->merchants['apikey'], $return);
        echo json_encode($return);
    }
    
    //余额查询
    public function balancePAK()
    {
        $currency = I('request.currency', '', 'string');
        if (!$currency || $currency!= 'PKR') {
            $this->showmessage("currency error");
        }
        $sign = I('request.pay_md5sign', '', 'string');
        if (!$sign) {
            $this->showmessage("pay_md5sign error");
        }
        $user_id = $this->memberid - 10000;
        //用户信息
        $this->merchants = D('Member')->where(array('id' => $user_id))->find();
        if (empty($this->merchants)) {
            $this->showmessage('Merchant does not exist');
        }
        if (!$this->merchants['df_api']) {
            $this->showmessage('The merchant has not enabled the payment function');
        }
        
        //用户国家信息
        if($this->merchants['country_id']!=3){
            $this->showmessage("The merchant's country is incorrect!");
        }
        
        
        $referer = getHttpReferer();
        if ($this->merchants['df_domain'] != '') {
            if (!checkDfDomain($referer, $this->merchants['df_domain'])) {
                $this->showmessage('The request source domain name is inconsistent with the reported domain name');
            }
        }
        if ($this->merchants['df_ip'] != '') {
            if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']) {
                $ip = $_SERVER['REMOTE_ADDR'];
            } else {
                $ip = get_client_ip();
            }
            if (!checkDfIp($ip, $this->merchants['df_ip'])) {
            $this->showmessage('The submitted IP address has not been reported!');
            }
            // $hostname = getHost($referer);//请求来源域名
            // $domainIp = gethostbyname($hostname);//域名IP
            // if (!checkDfIp($domainIp, $this->merchants['df_ip'])) {
            //     $this->showmessage('The IP address is inconsistent with the reported IP!');
            // }
        }

        $request = [
            'mchid' => $this->memberid
        ];

        $signature = $this->createSign($this->merchants['apikey'], $request);
        if ($signature != $sign) {
            ksort($_POST);
            $md5str = "";
            foreach ($_POST as $key => $val) {
                if (!empty($val) && $key != 'pay_md5sign') {
                    $md5str = $md5str . $key . "=" . $val . "&";
                }
            }
            // $sign = strtoupper(md5($md5str . "key=" . $this->merchants['apikey']));
            
            $result = [
                'mgs' => 'Please compare the splicing order and signature',
                'POST Data' => $_POST,
                'Field concatenation order' => substr($md5str, 0, strlen($md5str) - 1),
                // 'sign' => $sign,
            ];
            $this->showmessage('sign error', $result);
        }
        $return = [
            'status' => 'success',
            'msg' => 'Successed',
            'mchid' => $this->memberid,
            'data' => [
                [
                    'currency' => 'PKR',
                    'balance' => $this->merchants['balance_php'],//菲律宾可提现余额
                    'blockedbalance' => $this->merchants['blockedbalance_php'],//菲律宾冻结余额
                ],
                // [
                //     'currency' => 'INR',
                //     'balanceINR' => $this->merchants['balance_inr'],//越南可提现余额
                //     'blockedbalanceINR' => $this->merchants['blockedbalance_inr'],//越南冻结余额
                // ],
            ],
        ];
        $return['sign'] = $this->createSign($this->merchants['apikey'], $return);
        echo json_encode($return);
    }
    
    //余额查询
    public function balanceIND()
    {
        $currency = I('request.currency', '', 'string');
        if (!$currency || $currency!= 'INR') {
            $this->showmessage("currency error");
        }
        $sign = I('request.pay_md5sign', '', 'string');
        if (!$sign) {
            $this->showmessage("pay_md5sign error");
        }
        $user_id = $this->memberid - 10000;
        //用户信息
        $this->merchants = D('Member')->where(array('id' => $user_id))->find();
        if (empty($this->merchants)) {
            $this->showmessage('Merchant does not exist');
        }
        if (!$this->merchants['df_api']) {
            $this->showmessage('The merchant has not enabled the payment function');
        }
        
        //用户国家信息
        if($this->merchants['country_id']!=4){
            $this->showmessage("The merchant's country is incorrect!");
        }
        
        $referer = getHttpReferer();
        if ($this->merchants['df_domain'] != '') {
            if (!checkDfDomain($referer, $this->merchants['df_domain'])) {
                $this->showmessage('The request source domain name is inconsistent with the reported domain name');
            }
        }
        if ($this->merchants['df_ip'] != '') {
            if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']) {
                $ip = $_SERVER['REMOTE_ADDR'];
            } else {
                $ip = get_client_ip();
            }
            if (!checkDfIp($ip, $this->merchants['df_ip'])) {
            $this->showmessage('The submitted IP address has not been reported!');
            }
            // $hostname = getHost($referer);//请求来源域名
            // $domainIp = gethostbyname($hostname);//域名IP
            // if (!checkDfIp($domainIp, $this->merchants['df_ip'])) {
            //     $this->showmessage('The IP address is inconsistent with the reported IP!');
            // }
        }

        $request = [
            'mchid' => $this->memberid
        ];

        $signature = $this->createSign($this->merchants['apikey'], $request);
        if ($signature != $sign) {
            ksort($_POST);
            $md5str = "";
            foreach ($_POST as $key => $val) {
                if (!empty($val) && $key != 'pay_md5sign') {
                    $md5str = $md5str . $key . "=" . $val . "&";
                }
            }
            // $sign = strtoupper(md5($md5str . "key=" . $this->merchants['apikey']));
            
            $result = [
                'mgs' => 'Please compare the splicing order and signature',
                'POST Data' => $_POST,
                'Field concatenation order' => substr($md5str, 0, strlen($md5str) - 1),
                // 'sign' => $sign,
            ];
            $this->showmessage('sign error', $result);
        }
        $return = [
            'status' => 'success',
            'msg' => 'Successed',
            'mchid' => $this->memberid,
            'data' => [
                [
                    'currency' => 'INR',
                    'balance' => $this->merchants['balance_php'],//菲律宾可提现余额
                    'blockedbalance' => $this->merchants['blockedbalance_php'],//菲律宾冻结余额
                ],
                // [
                //     'currency' => 'INR',
                //     'balanceINR' => $this->merchants['balance_inr'],//越南可提现余额
                //     'blockedbalanceINR' => $this->merchants['blockedbalance_inr'],//越南冻结余额
                // ],
            ],
        ];
        $return['sign'] = $this->createSign($this->merchants['apikey'], $return);
        echo json_encode($return);
    }
    
    //越南余额查询
    public function balanceVN()
    {
        $currency = I('request.currency', '', 'string');
        if (!$currency || $currency!= 'VND') {
            $this->showmessage("currency error");
        }
        $sign = I('request.pay_md5sign', '', 'string');
        if (!$sign) {
            $this->showmessage("pay_md5sign error");
        }
        $user_id = $this->memberid - 10000;
        //用户信息
        $this->merchants = D('Member')->where(array('id' => $user_id))->find();
        if (empty($this->merchants)) {
            $this->showmessage('Merchant does not exist');
        }
        if (!$this->merchants['df_api']) {
            $this->showmessage('The merchant has not enabled the payment function');
        }
        
        //用户国家信息
        if($this->merchants['country_id']!=5){
            $this->showmessage("The merchant's country is incorrect!");
        }
        
        $referer = getHttpReferer();
        if ($this->merchants['df_domain'] != '') {
            if (!checkDfDomain($referer, $this->merchants['df_domain'])) {
                $this->showmessage('The request source domain name is inconsistent with the reported domain name');
            }
        }
        if ($this->merchants['df_ip'] != '') {
            if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']) {
                $ip = $_SERVER['REMOTE_ADDR'];
            } else {
                $ip = get_client_ip();
            }
            if (!checkDfIp($ip, $this->merchants['df_ip'])) {
            $this->showmessage('The submitted IP address has not been reported!');
            }
            // $hostname = getHost($referer);//请求来源域名
            // $domainIp = gethostbyname($hostname);//域名IP
            // if (!checkDfIp($domainIp, $this->merchants['df_ip'])) {
            //     $this->showmessage('The IP address is inconsistent with the reported IP!');
            // }
        }

        $request = [
            'mchid' => $this->memberid
        ];

        $signature = $this->createSign($this->merchants['apikey'], $request);
        if ($signature != $sign) {
            ksort($_POST);
            $md5str = "";
            foreach ($_POST as $key => $val) {
                if (!empty($val) && $key != 'pay_md5sign') {
                    $md5str = $md5str . $key . "=" . $val . "&";
                }
            }
            // $sign = strtoupper(md5($md5str . "key=" . $this->merchants['apikey']));
            
            $result = [
                'mgs' => 'Please compare the splicing order and signature',
                'POST Data' => $_POST,
                'Field concatenation order' => substr($md5str, 0, strlen($md5str) - 1),
                // 'sign' => $sign,
            ];
            $this->showmessage('sign error', $result);
        }
        $return = [
            'status' => 'success',
            'msg' => 'Successed',
            'mchid' => $this->memberid,
            'data' => [
                [
                    'currency' => 'VND',
                    'balance' => $this->merchants['balance_php'],//菲律宾可提现余额
                    'blockedbalance' => $this->merchants['blockedbalance_php'],//菲律宾冻结余额
                ],
                // [
                //     'currency' => 'INR',
                //     'balanceINR' => $this->merchants['balance_inr'],//越南可提现余额
                //     'blockedbalanceINR' => $this->merchants['blockedbalance_inr'],//越南冻结余额
                // ],
            ],
        ];
        $return['sign'] = $this->createSign($this->merchants['apikey'], $return);
        echo json_encode($return);
    }
    
    //孟加拉余额查询
    public function balanceBD()
    {
        $currency = I('request.currency', '', 'string');
        if (!$currency || $currency!= 'BDT') {
            $this->showmessage("currency error");
        }
        $sign = I('request.pay_md5sign', '', 'string');
        if (!$sign) {
            $this->showmessage("pay_md5sign error");
        }
        $user_id = $this->memberid - 10000;
        //用户信息
        $this->merchants = D('Member')->where(array('id' => $user_id))->find();
        if (empty($this->merchants)) {
            $this->showmessage('Merchant does not exist');
        }
        if (!$this->merchants['df_api']) {
            $this->showmessage('The merchant has not enabled the payment function');
        }
        
        //用户国家信息
        if($this->merchants['country_id']!=6){
            $this->showmessage("The merchant's country is incorrect!");
        }
        
        $referer = getHttpReferer();
        if ($this->merchants['df_domain'] != '') {
            if (!checkDfDomain($referer, $this->merchants['df_domain'])) {
                $this->showmessage('The request source domain name is inconsistent with the reported domain name');
            }
        }
        if ($this->merchants['df_ip'] != '') {
            if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']) {
                $ip = $_SERVER['REMOTE_ADDR'];
            } else {
                $ip = get_client_ip();
            }
            if (!checkDfIp($ip, $this->merchants['df_ip'])) {
            $this->showmessage('The submitted IP address has not been reported!');
            }
            // $hostname = getHost($referer);//请求来源域名
            // $domainIp = gethostbyname($hostname);//域名IP
            // if (!checkDfIp($domainIp, $this->merchants['df_ip'])) {
            //     $this->showmessage('The IP address is inconsistent with the reported IP!');
            // }
        }

        $request = [
            'mchid' => $this->memberid
        ];

        $signature = $this->createSign($this->merchants['apikey'], $request);
        if ($signature != $sign) {
            ksort($_POST);
            $md5str = "";
            foreach ($_POST as $key => $val) {
                if (!empty($val) && $key != 'pay_md5sign') {
                    $md5str = $md5str . $key . "=" . $val . "&";
                }
            }
            // $sign = strtoupper(md5($md5str . "key=" . $this->merchants['apikey']));
            
            $result = [
                'mgs' => 'Please compare the splicing order and signature',
                'POST Data' => $_POST,
                'Field concatenation order' => substr($md5str, 0, strlen($md5str) - 1),
                // 'sign' => $sign,
            ];
            $this->showmessage('sign error', $result);
        }
        $return = [
            'status' => 'success',
            'msg' => 'Successed',
            'mchid' => $this->memberid,
            'data' => [
                [
                    'currency' => 'BDT',
                    'balance' => $this->merchants['balance_php'],//菲律宾可提现余额
                    'blockedbalance' => $this->merchants['blockedbalance_php'],//菲律宾冻结余额
                ],
                // [
                //     'currency' => 'INR',
                //     'balanceINR' => $this->merchants['balance_inr'],//越南可提现余额
                //     'blockedbalanceINR' => $this->merchants['blockedbalance_inr'],//越南冻结余额
                // ],
            ],
        ];
        $return['sign'] = $this->createSign($this->merchants['apikey'], $return);
        echo json_encode($return);
    }

    //获取凭证
    public function voucher(){
        $out_trade_no = I('request.out_trade_no', '', 'string,strip_tags,htmlspecialchars');
        if (!$out_trade_no) {
            $this->showmessage("out_trade_no error");
        }
        $datetime = I('request.datetime', '', 'string,strip_tags,htmlspecialchars');
        if (!$datetime) {
            $this->showmessage("datetime error");
        }
        $sign = I('request.pay_md5sign', '', 'string');
        if (!$sign) {
            $this->showmessage("pay_md5sign error");
        }
        $user_id = $this->memberid - 10000;
        //用户信息
        $this->merchants = D('Member')->where(array('id' => $user_id))->find();
        if (empty($this->merchants)) {
            $this->showmessage('Merchant does not exist');
        }
        if (!$this->merchants['df_api']) {
            $this->showmessage('The merchant has not enabled the payment function');
        }
        $referer = getHttpReferer();
        if ($this->merchants['df_domain'] != '') {
            if (!checkDfDomain($referer, $this->merchants['df_domain'])) {
                $this->showmessage('The request source domain name is inconsistent with the reported domain name');
            }
        }
        if ($this->merchants['df_ip'] != '') {
            if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']) {
                $ip = $_SERVER['REMOTE_ADDR'];
            } else {
                $ip = get_client_ip();
            }
            if (!checkDfIp($ip, $this->merchants['df_ip'])) {
                $this->showmessage('The IP address is inconsistent with the reported IP');
            }
            // $hostname = getHost($referer);//请求来源域名
            // $domainIp = gethostbyname($hostname);//域名IP
            // if (!checkDfIp($domainIp, $this->merchants['df_ip'])) {
            //     $this->showmessage('The source domain name IP address is inconsistent with the reported IP');
            // }
        }
        $request = [
            'mchid' => $this->memberid,
            'out_trade_no' => $out_trade_no,
            'datetime' => $datetime
        ];

        $signature = $this->createSign($this->merchants['apikey'], $request);
        if ($signature != $sign) {
            ksort($_POST);
            $md5str = "";
            foreach ($_POST as $key => $val) {
                if (!empty($val) && $key != 'pay_md5sign') {
                    $md5str = $md5str . $key . "=" . $val . "&";
                }
            }
            // $sign = strtoupper(md5($md5str . "key=" . $this->merchants['apikey']));
            
            $result = [
                'mgs' => 'Please compare the splicing order and signature',
                'POST Data' => $_POST,
                'Field concatenation order' => substr($md5str, 0, strlen($md5str) - 1),
                // 'sign' => $sign,
            ];
            $this->showmessage('sign error', $result);
        }
        $where = [
            'userid' => $user_id,
            'out_trade_no' => $out_trade_no,
            'sqdatetime'=>['between',[date('Y-m-d H:i:s',strtotime($datetime . ' 00:00:00')),date('Y-m-d H:i:s',strtotime($datetime . ' 23:59:59'))]],
        ];
        $Wttklist = D('Wttklist');
        $order = $Wttklist->table($Wttklist->getRealTableName($datetime))->where($where)->find();
        if(!$order || empty($order)){
            $where = [
                'userid' => $user_id,
                'out_trade_no' => $out_trade_no,
                'sqdatetime'=>['between',[date('Y-m-d H:i:s',strtotime($datetime . ' 00:00:00')),date('Y-m-d H:i:s',strtotime($datetime . ' 23:59:59') + 86400)]],
            ];
            $t_date = date('Y-m-d',strtotime($datetime . ' 23:59:59') + 86400);
            $Wttklist = D('Wttklist');
            try {
                $order = $Wttklist->table($Wttklist->getRealTableName($t_date))->where($where)->find();
            } catch (\Exception $e) {
                
            }
        }
        if (!$order) {
            $return = [
                'status' => 'error',
                'msg' => 'Error',
                'refCode' => '7',
                'refMsg' => 'Transaction does not exist',
            ];
        }else {
            if ($order['status'] == 2 || $order['status'] == 3) {
                $return = [
                    'status' => 'success',
                    'msg' => 'Successed',
                    'mchid' => $this->memberid,
                    'out_trade_no' => $order['out_trade_no'],
                    'transaction_id' => $order['orderid'],
                    'refCode' => '1',
                    'refMsg' => 'Payment successful',
                    'voucherUrl' => 'https://' . C('DOMAIN') . '/Payment_Index_voucher.html?casOrdNo=' . $order['orderid'],
                ];
            }else{
                $return = [
                    'status' => 'error',
                    'msg' => 'Error',
                    'refCode' => '8',
                    'refMsg' => 'Unknown status',
                ];
            }
            $return['sign'] = $this->createSign($this->merchants['apikey'], $return);
        }
        echo json_encode($return);
        exit;
    }
}