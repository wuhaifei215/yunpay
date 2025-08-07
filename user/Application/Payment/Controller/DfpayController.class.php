<?php
/*
 * 代付API
 */
namespace Payment\Controller;

use Think\Controller;

class DfpayController extends Controller
{
    //商家信息
    protected $merchants;
    //网站地址
    protected $_site;
    //通道信息
    protected $channel;

    public function __construct()
    {
        parent::__construct();
        $this->_site = ((is_https()) ? 'https' : 'http') . '://' . C("DOMAIN") . '/';
    }

    /**
     * 创建代付申请
     * @param $parameter
     * @return array
     */
    public function add()
    {
        //  PaymentLogs( 'DFpay_add',json_encode($_POST) );
        if (empty($_POST)) {
            $this->showmessage('no data!');
        }
        $siteconfig = M("Websiteconfig")->find();
        if (!$siteconfig['df_api']) {
            $this->showmessage('代付API未开启！');
        }
        $sign = I('request.pay_md5sign', '', 'string,strip_tags,htmlspecialchars');
        if (!$sign) {
            $this->showmessage("缺少签名参数");
        }

        $mchid = I("post.mchid", 0, 'intval');
        if (!$mchid) {
            $this->showmessage('商户ID不能为空！');
        }
        $user_id = $mchid - 10000;
//        PaymentLogs( 'DFpay_add_server', $mchid.':'.json_encode($_SERVER) );
        //用户信息
        $this->merchants = D('Member')->where(array('id' => $user_id))->find();
        if (empty($this->merchants)) {
            $this->showmessage('商户不存在！');
        }
        if (!$this->merchants['df_api']) {
            $this->showmessage('商户未开启此功能！');
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR']) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = get_client_ip();
        }
        $referer = getHttpReferer();//请求来源URL

        if ($this->merchants['df_domain'] != '') {
            if (!checkDfDomain($referer, $this->merchants['df_domain'])) {
                $this->showmessage('请求来源域名与报备域名不一致！');
            }
        }
        if ($this->merchants['df_ip'] == '') {
            $this->showmessage('提交IP地址未报备！');
        } elseif ($this->merchants['df_ip'] != '') {
            if (!checkDfIp($ip, $this->merchants['df_ip'])) {
                
            // PaymentLogs( 'DFpay_add_server', $user_id.':'.$ip.'=='. $this->merchants['df_ip']);
                $this->showmessage('IP地址与报备IP不一致！');
            }
        }
        //判断是否设置了节假日不能提现
        $tkHolidayList = M('Tikuanholiday')->limit(366)->getField('datetime', true);
        if ($tkHolidayList) {
            $today = date('Ymd');
            foreach ($tkHolidayList as $k => $v) {
                if ($today == date('Ymd', $v)) {
                    $this->showmessage('节假日暂时无法提款！');
                }
            }
        }
        
        $bankname = I("post.bankname", '', 'string,strip_tags,htmlspecialchars');
        if (!$bankname) {
            $this->showmessage('银行名称不能为空！');
        }
        $ifsc = I("post.subbranch", '', 'string,strip_tags,htmlspecialchars');
        if (!$ifsc) {
            $this->showmessage('银行IFSC不能为空！');
        }
        
        //结算方式：
        //结算方式：
        $Tikuanconfig = M('Tikuanconfig');
        $tkConfig = $Tikuanconfig->where(['userid' => $user_id, 'tkzt' => 1])->find();       //个人规则

        $defaultConfig = $Tikuanconfig->where(['issystem' => 1, 'tkzt' => 1])->find();      //平台规则
        //判断是否开启提款设置
        if (!$defaultConfig) {
            return ['status' => 0, 'msg' => '提款已关闭！'];
        }
        
        $channel = M('pay_for_another')->alias('as A')->field('A.*')->join('LEFT JOIN `pay_user_pay_for_another` AS B ON A.id = B.channel')->where(['B.userid' => $user_id])->find();
        $channelConfig = $Tikuanconfig->where(['channel'=> $channel['id'] , 'userid' => 1 , 'tkzt' => 1])->find();       //通道规则
        
        //判断是否设置个人规则
        if ($tkConfig && $tkConfig['tkzt'] == 1 && $tkConfig['systemxz'] == 1) {
            //个人规则，但是提现时间规则要按照系统规则
            $tkConfig['allowstart'] = $defaultConfig['allowstart'];
            $tkConfig['allowend'] = $defaultConfig['allowend'];
        }elseif ($channelConfig && $channelConfig['tkzt'] == 1 && $channelConfig['systemxz'] == 2) {
            $tkConfig = $channelConfig;
            //通道规则，但是提现时间规则要按照系统规则
            $tkConfig['allowstart'] = $defaultConfig['allowstart'];
            $tkConfig['allowend'] = $defaultConfig['allowend'];
        } else {
            $tkConfig = $defaultConfig;
        }
        //是否在许可的提现时间
        $hour = date('H');
        //判断提现时间是否合法
        if ($tkConfig['allowend'] != 0) {
            if ($tkConfig['allowstart'] > $hour || $tkConfig['allowend'] <= $hour) {
                $this->showmessage('不在提现时间，请换个时间再来!');
            }
        }
        $money = I("post.money", 0, 'intval');
        if (!is_numeric($money) || $money <= 0) {
            $this->showmessage('金额错误！');
        }
        //单笔最小提款金额
        if ($tkConfig['tkzxmoney'] > $money) {
            $this->showmessage('单笔最低提款额度：' . $tkConfig['tkzxmoney']);
        }
        //单笔最大提款金额
        if ($tkConfig['tkzdmoney'] < $money) {
            $this->showmessage('单笔最大提款额度：' . $tkConfig['tkzdmoney']);
        }

        $accountname = I("post.accountname", '', 'string,strip_tags,htmlspecialchars');
        if (!$accountname) {
            $this->showmessage('开户名不能为空！');
        }
        $cardnumber = I("post.cardnumber", '', 'string,strip_tags,htmlspecialchars');
        if (!$cardnumber) {
            $this->showmessage('银行卡号不能为空！');
        }
        $out_trade_no = I("post.out_trade_no", '', 'string,strip_tags,htmlspecialchars');
        if (!$out_trade_no) {
            $this->showmessage('订单号不能为空！');
        }

        $Wttklist = D('Wttklist');
        $where = [
            'userid' => $user_id,
            'out_trade_no' => $out_trade_no,
            'sqdatetime'=>['between',[date('Y-m-d',time()-86399) . ' 00:00:00', date('Y-m-d',time()) . ' 23:59:59']],
        ];
        
        // $count = $Wttklist->getCount($where);
        // if($count && !empty($count)){
        //     $this->showmessage('重复订单号！');
        // }
        
        $where['sqdatetime'] = ['between', [date('Y-m-d') . ' 00:00:00', date('Y-m-d') . ' 23:59:59']];
        //判断是否超过当天次数
        $wttkNum = $Wttklist->getCount($where);
        $dayzdnum = $wttkNum + 1;
        if ($dayzdnum >= $tkConfig['dayzdnum']) {
            $this->showmessage('超出商户当日提款次数！');
        }
        //判断提款额度
        $dayzdmoney = $Wttklist->getSum('tkmoney',$where)['tkmoney'];
        if ($dayzdmoney >= $tkConfig['dayzdmoney']) {
            $this->showmessage('超出商户当日提款额度！');
        }
        $balance = $this->merchants['balance'];
        if ($balance < $money) {
            $this->showmessage('金额错误，可用余额不足!');
        }
        if ($money < $tkConfig['tkzxmoney'] || $money > $tkConfig['tkzdmoney']) {
            $this->showmessage('提款金额不符合提款额度要求!');
        }
        $dayzdmoney = bcadd($money, $dayzdmoney, 4);
        if ($dayzdmoney >= $tkConfig['dayzdmoney']) {
            $this->showmessage('超出当日提款额度!');
        }
        //计算手续费
//       $sxfmoney = $tkConfig['tktype'] ? $tkConfig['sxffixed'] : bcdiv(bcmul($data['money'], $tkConfig['sxfrate'], 4), 100, 4);

        
        if ($tkConfig['tktype'] == 1) { //按比例计算
            $sxfmoney = $tkConfig['sxffixed'];
        } elseif ($tkConfig['tktype'] == 2) {   //按单笔加比例计算
            $sxfmoney = $tkConfig['sxffixed'] + bcdiv(bcmul($money, $tkConfig['sxfrate'], 4), 100, 4);
        } else {    //按单笔计算
            $sxfmoney = bcdiv(bcmul($money, $tkConfig['sxfrate'], 4), 100, 4);
        }

        if ($tkConfig['tk_charge_type']) {
            //实际提现的金额
            $money2 = $money;
        } else {
            //实际提现的金额
            $money2 = bcsub($money, $sxfmoney, 4);
        }
        $notifyurl = I("post.notifyurl", '');
        $extends = I("post.extends", '');
        //验签
        if ($this->verify($_POST)) {
            M()->startTrans();
            $time = date("Y-m-d H:i:s");
            $orderid = $this->getOrderId();
            $wttkData = [
                'orderid' => $orderid,
                "bankname" => trim($bankname),
                "bankzhiname" => trim($ifsc),
                "banknumber" => trim($cardnumber),
                "bankfullname" => trim($accountname),
                "notifyurl" =>  $notifyurl,
                "userid" => $user_id,
                "sqdatetime" => $time,
                "status" => 0,
                'tkmoney' => $money,
                'sxfmoney' => $sxfmoney,
                "money" => $money2,
                "out_trade_no" => $out_trade_no,
                "extends" => trim(base64_decode($extends)),
                "df_charge_type" => $tkConfig['tk_charge_type']
            ];

            $tkmoney = abs(floatval($money));
            $ymoney = $balance;
            $balance = bcsub($balance, $tkmoney, 4);
            $mcData = [
                "userid" => $user_id,
                'ymoney' => $ymoney,
                "money" => $money,
                'gmoney' => $balance,
                "datetime" => $time,
                "transid" => $orderid,
                "orderid" => $out_trade_no,
                "lx" => 10,
                'contentstr' => date("Y-m-d H:i:s") . '委托提现操作',
            ];
            $Member = M('Member');
            /**************************************2019年06月06日 增加的代付代理资金变动*************************************************/
//            //上级代理
//            $parentid = $Member->where(["id" => $user_id])->getField("parentid");
//            //如果存在上级代理的情况
//            if ($parentid > 1) {
//                $dl_rate = $Tikuanconfig->where(['userid' => $parentid])->getField("sxfrate");
//                $dl_sxfrate = abs(bcsub($dl_rate, $tkConfig['sxfrate'], 4));
//                $dl_info = $Member->where(['id' => $parentid])->lock(true)->find();
//                $dl_ymoney = $dl_info['balance'];
//                $dlyj_money = bcdiv(bcmul($money, $dl_sxfrate, 4), 100, 4);
//                $dl_balance = bcadd($dl_ymoney, $dlyj_money, 4);
//                $dl_mcData = [
//                    "userid" => $parentid,
//                    'ymoney' => $dl_ymoney,
//                    "money" => $dlyj_money,
//                    'gmoney' => $dl_balance,
//                    "datetime" => $time,
//                    "transid" => $orderid,
//                    "orderid" => $out_trade_no,
//                    "lx" => 6,
//                    'contentstr' => date("Y-m-d H:i:s") . '下级商户代付提现佣金',
//                ];
//            }
            /**************************************2019年06月06日 增加的代付代理资金变动*************************************************/

            if ($tkConfig['tk_charge_type'] && $sxfmoney > 0) {
                $balance = bcsub($balance, $sxfmoney, 4);
                if ($balance < 0) {
                    M()->rollback();
                    $this->showmessage('余额不足以扣除手续费！');
                }
                $chargeData = [
                    "userid" => $user_id,
                    'ymoney' => $ymoney - $money,
                    "money" => $sxfmoney,
                    'gmoney' => $balance,
                    "datetime" => $time,
                    "transid" => $orderid,
                    "orderid" => $out_trade_no,
                    "lx" => 14,
                    'contentstr' => date("Y-m-d H:i:s") . '委托提现扣除手续费',
                ];
            }
            $res1 = $Member->where(['id' => $user_id])->save(['balance' => $balance]);
            //获取数据表名称
            

            $table = $Wttklist->getRealTableName($wttkData['sqdatetime']);
            // var_dump($table);die;
            $res2 = $Wttklist->table($table)->add($wttkData);
            $res3 = M('Moneychange')->add($mcData);

            if ($tkConfig['tk_charge_type'] && $sxfmoney > 0) {
                $res4 = M('Moneychange')->add($chargeData);
            } else {
                $res4 = true;
            }
            if ($res1 && $res2 && $res3 && $res4) {
                //代付代理佣金变动
//                if($parentid > 1){
//                    $res6 = $Member->where(['id' => $parentid])->save(['balance' => $dl_balance]);
//                    $res7 = M('Moneychange')->add($dl_mcData);
//                }
                M()->commit();
            }else{
                M()->rollback();
                $this->showmessage('系统错误');
            }

            /**************************************2024年09月10日 自动向上游提交*************************************************/
            $id = $res2;
            $file = APP_PATH .  'Payment/Controller/' . $channel['code'] . 'Controller.class.php';
            if( !file_exists($file) ) {
                $data = ['memo'=> '代付通道文件不存在'];
                $this->handle($id, 3, $data, $table);
//                return ['status' => 0, 'msg' => '系统出错，请您联系管理员处理!'];
            }
            //加锁防止重复提交
            $lock_res = $Wttklist->table($table)->where(['id'=>$id, 'df_lock'=>0])->save(['df_lock' => 1, 'lock_time' => time()]);
            if(!$lock_res) {
//                return ['status' => 0, 'msg' => '系统出错，请您联系管理员处理!'];
            }
            try {
                $wttkData['money'] = round($wttkData['money'],2);
                $result = R('Payment/' . $channel['code'] . '/PaymentExec', [$wttkData, $channel]);
                if (FALSE === $result) {
                    $Wttklist->table($table)->where(['id' => $id])->save(['last_submit_time' => time(), 'auto_submit_try' => ['exp', 'auto_submit_try+1'], 'df_lock' => 0]);
                } else {
                    if (is_array($result)) {
                        $data = [
                            'memo' => $result['msg'],
                        ];
                        $this->handle($id, $result['status'], $data, $table);
                        $Wttklist->table($table)->where(['id' => $id])->save(['is_auto' => 1, 'last_submit_time' => time(), 'auto_submit_try' => ['exp', 'auto_submit_try+1'], 'df_lock' => 0]);
                    }
                }
            } catch (\Exception $e) {
                $Wttklist->table($table)->where(['id' => $id])->setField('df_lock', 0);
            }
            /**************************************2019年06月06日 自动向上游提交*************************************************/

            header('Content-Type:application/json; charset=utf-8');
            $data = array('status' => 'success', 'msg' => '代付申请成功', 'transaction_id' => $orderid);
            echo json_encode($data);
            exit;
        } else {
            ksort($_POST);
            $md5str = "";
            foreach ($_POST as $key => $val) {
                if (!empty($val) && $key != 'pay_md5sign') {
                    $md5str = $md5str . $key . "=" . $val . "&";
                }
            }
            $sign = strtoupper(md5($md5str . "key=" . $this->merchants['apikey']));
            $result = [
                '提醒' => '请比对拼接顺序及签名',
                'POST数据' => $_POST,
                '拼接顺序' => substr($md5str, 0, strlen($md5str) - 1),
                '签名' => $sign,
            ];
            $this->showmessage('签名验证失败', $result);
        }
    }

    //代付查询
    public function query()
    {
        $datetime = I('request.datetime', '', 'string,strip_tags,htmlspecialchars');
        if (!$datetime) {
        $this->showmessage("缺少订单交易时间");
    }
        $out_trade_no = I('request.out_trade_no', '', 'string,strip_tags,htmlspecialchars');
        $sign = I('request.pay_md5sign', '', 'string');
        if (!$sign) {
            $this->showmessage("缺少签名参数");
        }
        if (!$out_trade_no) {
            $this->showmessage("缺少订单号");
        }
        $mchid = I("request.mchid", 0, 'intval');
        if (!$mchid) {
            $this->showmessage("缺少商户号");
        }
        $user_id = $mchid - 10000;
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
            'mchid' => $mchid,
            'out_trade_no' => $out_trade_no
        ];

        $signature = $this->createSign($this->merchants['apikey'], $request);
        if ($signature != $sign) {
            $this->showmessage('验签失败!');
        }
        $where = [
            'mchid' => $mchid,
            'out_trade_no' => $out_trade_no,
            'sqdatetime'=>['between',[$datetime,date('Y-m-d H:i:s',strtotime($datetime)+86399)]],
        ];
        $Wttklist = D('Wttklist');
        $order = $Wttklist->getOrderByDateRange('*',$where);

        if (!$order) {
            $return = [
                'status' => 'error',
                'msg' => '请求成功',
                'refCode' => '7',
                'refMsg' => '交易不存在',
            ];
            echo json_encode($return);
            exit;
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
        }
        $return = [
            'status' => 'success',
            'msg' => '请求成功',
            'mchid' => $mchid,
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
        echo json_encode($return);
        exit;
    }

    //余额查询
    public function balance()
    {
        $mchid = I("request.mchid", 0, 'intval');
        if (!$mchid) {
            $this->showmessage("缺少商户号");
        }
        $sign = I('request.pay_md5sign', '', 'string');
        if (!$sign) {
            $this->showmessage("缺少签名参数");
        }
        $user_id = $mchid - 10000;
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
            'mchid' => $mchid
        ];

        $signature = $this->createSign($this->merchants['apikey'], $request);
        if ($signature != $sign) {
            $this->showmessage('验签失败!');
        }
        $return = [
            'status' => 'success',
            'msg' => '请求成功',
            'mchid' => $mchid,
            'balance' => $this->merchants['balance'],//可提现余额
            'blockedbalance' => $this->merchants['blockedbalance'],//冻结余额
        ];
        $return['sign'] = $this->createSign($this->merchants['apikey'], $return);
        echo json_encode($return);
    }

    /**
     * 获得订单号
     *
     * @return string
     */
    public function getOrderId()
    {
        $year_code = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');
        $i = intval(date('Y')) - 2010 - 1;

        return $year_code[$i] . date('md') . substr(time(), -5) . substr(microtime(), 2, 5) . str_pad(mt_rand(1, 99), 2, '0', STR_PAD_LEFT) . rand(1000,9999);
    }

    /**
     *  验证签名
     * @return bool
     */
    protected function verify($param)
    {
        $md5key = $this->merchants['apikey'];
        $md5keysignstr = $this->createSign($md5key, $param);
        $pay_md5sign = I('request.pay_md5sign');
        if ($pay_md5sign == $md5keysignstr) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * 创建签名
     * @param $Md5key
     * @param $list
     * @return string
     */
    protected function createSign($Md5key, $list)
    {
        ksort($list);
        $md5str = "";
        foreach ($list as $key => $val) {
            if (!empty($val) && $key != 'pay_md5sign') {
                $md5str = $md5str . $key . "=" . $val . "&";
            }
        }
        $sign = strtoupper(md5($md5str . "key=" . $Md5key));
        return $sign;
    }

    /**
     * 错误返回
     * @param string $msg
     * @param array $fields
     */
    protected function showmessage($msg = '', $fields = array())
    {
        PaymentLogs( 'DFpay_add', $msg.':'.json_encode($fields,JSON_UNESCAPED_UNICODE) );
        header('Content-Type:application/json; charset=utf-8');
        $data = array('status' => 'error', 'msg' => $msg, 'data' => $fields);
        echo json_encode($data, 320);
        exit;
    }
    
    protected function handle($id, $status = 1, $return,$table='Wttklist'){
	    //处理成功返回的数据
        $Wttklist = D('Wttklist');
        $memo = $Wttklist->table($table)->where(['id' => $id])->getField('memo');
        $data = array();
		switch($status){
			case 1:
				//提交代付成功
			   $data['status'] = 1;
			   $data['memo']  = '申请成功！ - '. $return['memo']. date('Y-m-d H:i:s').'<br>'.$memo;
			   break;
			case 2:
				 //支付成功
			   $data['status'] = 2;
			   $data['cldatetime'] = date('Y-m-d H:i:s', time());
			   $data['memo']  = '代付成功！ - '. $return['memo']. date('Y-m-d H:i:s').'<br>'.$memo;
			   break;
			case 3:
				 //各种失败未返回 并退回金额
				$message = isset($return['memo'])?$return['memo']:'代付失败！';
				$message = $message .' - '.date('Y-m-d H:i:s').'<br>'.$memo;
				Reject(['id' => $id, 'status' => '4','message'=> $message],$return);
                //异步通知下游
                // Automatic_Notify($id);
				return;
			default:
				 //订单状态不改变
				 $sta = $Wttklist->table($table)->where(['id' => $id])->getField('status');
				 $data['status'] = $sta;
				 $data['memo']  = '状态不改变！ - '. $return['memo']. date('Y-m-d H:i:s').'<br>'.$memo;
		}
        if(in_array($status, [0,1,2])){
        	$data = array_merge($data, $return);
            $where = ['id'=>$id, 'status'=>['in', '0,1']];
            $Wttklist->table($table)->where($where)->save($data);
        }
        //异步通知下游
//        $user_id = $Wttklist->table($table)->where(['id' => $id])->getField('userid');
        /*if($user_id == 4) {
            sleep(10);
        }*/
        Automatic_Notify($id);
	}
}