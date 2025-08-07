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
            $this->showmessage("缺少订单号");
        }
        $datetime = I('request.datetime', '', 'string,strip_tags,htmlspecialchars');
        if (!$datetime) {
            $this->showmessage("缺少订单交易时间");
        }
        $sign = I('request.pay_md5sign', '', 'string');
        if (!$sign) {
            $this->showmessage("缺少签名参数");
        }
        $user_id = $this->memberid - 10000;
        //用户信息
        $this->merchants = D('Member')->where(array('id' => $user_id))->find();
        if (empty($this->merchants)) {
            $this->showmessage('商户不存在！');
        }
        if (!$this->merchants['df_api']) {
            $this->showmessage('商户未开启此功能！');
        }
        if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = get_client_ip();
        }
        $referer = getHttpReferer();
        if ($this->merchants['df_domain'] != '') {
            if (!checkDfDomain($referer, $this->merchants['df_domain'])) {
                $this->showmessage('请求来源域名与报备域名不一致！');
            }
        }
        if ($this->merchants['df_ip'] != '') {
            if (!checkDfIp($ip, $this->merchants['df_ip'])) {
                $this->showmessage('IP地址与报备IP不一致！');
            }
            $hostname = getHost($referer);//请求来源域名
            $domainIp = gethostbyname($hostname);//域名IP
            if (!checkDfIp($domainIp, $this->merchants['df_ip'])) {
                $this->showmessage('来源域名IP地址与报备IP不一致！');
            }
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
                '提醒' => '请比对拼接顺序及签名',
                'POST数据' => $_POST,
                '拼接顺序' => substr($md5str, 0, strlen($md5str) - 1),
                // '签名' => $sign,
            ];
            $this->showmessage('签名验证失败', $result);
        }
        $where = [
            'userid' => $user_id,
            'out_trade_no' => $out_trade_no,
            'sqdatetime'=>['between',[date('Y-m-d H:i:s',strtotime($datetime . ' 00:00:00')),date('Y-m-d H:i:s',strtotime($datetime . ' 23:59:59'))]],
        ];
        $Wttklist = D('Wttklist');
        $order = $Wttklist->table($Wttklist->getRealTableName($datetime))->where($where)->find();
        // echo $Wttklist->table($Wttklist->getRealTableName($datetime))->getLastSql();
        // var_dump($order);
        if (!$order) {
            $return = [
                'status' => 'error',
                'msg' => '请求成功',
                'refCode' => '7',
                'refMsg' => '交易不存在',
            ];
        }else {
            if ($order['status'] == 0) {
                $refCode = '4';
                $refMsg = "待处理";
            } elseif ($order['status'] == 1) {
                $refCode = '3';
                $refMsg = "处理中";
            } elseif ($order['status'] == 2 || $order['status'] == 3) {
                $refCode = '1';
                $refMsg = "成功";
            } elseif ($order['status'] == 4 || $order['status'] == 5) {
                $refCode = '2';
                $refMsg = "失败";
            } elseif ($order['status'] == 6) {
                $refCode = '5';
                $refMsg = "驳回";
            } else {
                $refCode = '8';
                $refMsg = "未知状态";
            }
            $return = [
                'status' => 'success',
                'msg' => '请求成功',
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
            $this->showmessage("缺少签名参数");
        }
        $user_id = $this->memberid - 10000;
        //用户信息
        $this->merchants = D('Member')->where(array('id' => $user_id))->find();
        if (empty($this->merchants)) {
            $this->showmessage('商户不存在！');
        }
        if (!$this->merchants['df_api']) {
            $this->showmessage('商户未开启此功能！');
        }
        if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = get_client_ip();
        }
        $referer = getHttpReferer();
        if ($this->merchants['df_domain'] != '') {
            if (!checkDfDomain($referer, $this->merchants['df_domain'])) {
                $this->showmessage('请求来源域名与报备域名不一致！');
            }
        }
        if ($this->merchants['df_ip'] != '') {
            if (!checkDfIp($ip, $this->merchants['df_ip'])) {
                $this->showmessage('IP地址与报备IP不一致！');
            }
            $hostname = getHost($referer);//请求来源域名
            $domainIp = gethostbyname($hostname);//域名IP
            if (!checkDfIp($domainIp, $this->merchants['df_ip'])) {
                $this->showmessage('来源域名IP地址与报备IP不一致！');
            }
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
                '提醒' => '请比对拼接顺序及签名',
                'POST数据' => $_POST,
                '拼接顺序' => substr($md5str, 0, strlen($md5str) - 1),
                // '签名' => $sign,
            ];
            $this->showmessage('签名验证失败', $result);
        }
        $return = [
            'status' => 'success',
            'msg' => '请求成功',
            'mchid' => $this->memberid,
            'data' => [
                [
                    'currency' => 'PHP',
                    'balancePHP' => $this->merchants['balance_php'],//菲律宾可提现余额
                    'blockedbalancePHP' => $this->merchants['blockedbalance_php'],//菲律宾冻结余额
                ],[
                    'currency' => 'INR',
                    'balanceINR' => $this->merchants['balance_inr'],//越南可提现余额
                    'blockedbalanceINR' => $this->merchants['blockedbalance_inr'],//越南冻结余额
                ],
            ],
        ];
        $return['sign'] = $this->createSign($this->merchants['apikey'], $return);
        echo json_encode($return);
    }


}