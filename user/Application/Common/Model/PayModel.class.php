<?php
// +----------------------------------------------------------------------
// | 支付模型
// +----------------------------------------------------------------------
namespace Common\Model;

use Think\Model;

class PayModel
{
    /**
     * 完成订单
     * @param $TransID
     * @param $PayName
     * @param int $returntype
     */
    public function completeOrder($trans_id, $pay_name = '', $returntype = 1, $transaction_id = '')
    {
        //设置redis标签，防止重复执行
        $redis = $this->redis_connect();
        if($redis->get('EditMoney' . $trans_id)){
            return false;
        }
        $redis->set('EditMoney' . $trans_id,'1',120);
        
        $m_Order = D("Order");
        $date = date('Ymd', strtotime(substr($trans_id, 0, 8)));  //获取订单日期
        $table = $m_Order->getRealTableName($date);
        $order_info = $m_Order->table($table)->where(['pay_orderid' => $trans_id])->find(); //获取订单信息
        if (!$order_info) {
            log_place_order('EditMoney', "处理订单异常", "查询(" . $trans_id . ")----通道：" . $pay_name);    //日志
            return false;
        }
        $userid = intval($order_info["pay_memberid"] - 10000); // 商户ID
        $time = time(); //当前时间

        //********************************************订单支付成功上游回调处理********************************************//
        if ($order_info["pay_status"] == 0) {
            //开启事物
            M()->startTrans();
            //查询用户信息
            $m_Member = M('Member');
            $member_info = $m_Member->where(['id' => $userid])->lock(true)->find();
            //更新订单状态 1 已成功未返回 2 已成功已返回
            $res = $m_Order->table($table)->where(['pay_orderid' => $trans_id, 'pay_status' => 0])->save(['pay_status' => 1, 'pay_successdate' => $time]);
            if (!$res) {
                M()->rollback();
                return false;
            }
            //-----------------------------------------修改用户数据 商户余额、冻结余额start-----------------------------------
            //要给用户增加的实际金额（扣除投诉保证金）
            $actualAmount = $order_info['pay_actualamount'];

            //创建修改用户修改信息
            $member_data = [
//                'last_paying_time' => $time,
//                'unit_paying_number' => ['exp', 'unit_paying_number+1'],
//                'unit_paying_amount' => ['exp', 'unit_paying_amount+' . $actualAmount],
//                'paying_money' => ['exp', 'paying_money+' . $actualAmount],
            ];

            //判断用结算方式
            switch ($order_info['t']) {
                case '0':
                    //t+0结算
                case '7':
                    //t+7 只限制提款和代付时间，每周一允许提款
                case '30':
                    //t+30 只限制提款和代付时间，每月第一天允许提款
                    $ymoney = $member_info['balance']; //改动前的金额
                    $gmoney = bcadd($member_info['balance'], $actualAmount, 4); //改动后的金额
                    $member_data['balance'] = ['exp', 'balance+' . $actualAmount]; //防止数据库并发脏读
                    break;
                case '1':
                    //t+1结算，记录冻结资金
                    $blockedlog_data = [
                        'userid' => $userid,
                        'orderid' => $order_info['pay_orderid'],
                        'amount' => $actualAmount,
                        'thawtime' => (strtotime('tomorrow') + rand(0, 7200)),
                        'pid' => $order_info['pay_bankcode'],
                        'createtime' => $time,
                        'status' => 0,
                    ];
                    $blockedlog_result = M('Blockedlog')->add($blockedlog_data);
                    if (!$blockedlog_result) {
                        M()->rollback();
                        return false;
                    }
                    $ymoney = $member_info['blockedbalance']; //原冻结资金
                    $gmoney = bcadd($member_info['blockedbalance'], $actualAmount, 4); //改动后的冻结资金
                    $member_data['blockedbalance'] = ['exp', 'blockedbalance+' . $actualAmount]; //防止数据库并发脏读

                    break;
                default:
                    # code...
                    break;
            }

            $member_result = $m_Member->where(['id' => $userid])->save($member_data);
            if ($member_result != 1) {
                M()->rollback();
                return false;
            }
            // 商户充值金额变动
            $moneychange_data = [
                'userid' => $userid,
                'ymoney' => $ymoney, //原金额或原冻结资金
                'money' => $actualAmount,
                'gmoney' => $gmoney, //改动后的金额或冻结资金
                'datetime' => date('Y-m-d H:i:s'),
                'tongdao' => $order_info['pay_bankcode'],
                'transid' => $trans_id,
                'orderid' => $order_info['out_trade_id'],
                'contentstr' => $order_info['out_trade_id'] . '订单充值,结算方式：t+' . $order_info['t'],
                'lx' => 1,
                't' => $order_info['t'],
            ];

            $moneychange_result = $this->MoenyChange($moneychange_data); // 资金变动记录

            if ($moneychange_result == false) {
                M()->rollback();
                return false;
            }

            // 通道ID
            $bianliticheng_data = [
                "userid" => $userid, // 用户ID
                "transid" => $trans_id, // 订单号
                "money" => $order_info["pay_amount"], // 金额
                "tongdao" => $order_info['pay_bankcode'],
            ];
            $this->bianliticheng($bianliticheng_data); // 提成处理

            /******************2018.12.26添加通道代理处理***********************/
//            $tongdao_ticheng_data = [
//                "userid" => $userid, // 用户ID
//                "transid" => $trans_id, // 订单号
//                "money" => $order_info["pay_amount"], // 金额
//                "tongdao" => $order_info['pay_bankcode'],       //产品号
//                "channel" => $order_info['channel_id'],     //渠道号
//            ];
//            $this->tongdao_ticheng($tongdao_ticheng_data);  //提成处理

            M()->commit();
            //-----------------------------------------修改用户数据 商户余额、冻结余额end-----------------------------------

            //-----------------------------------------修改通道风控支付数据start----------------------------------------------
//            $m_Channel = M('Channel');
//            $channel_where = ['id' => $order_info['channel_id']];
//            $channel_info = $m_Channel->where($channel_where)->find();
            //判断当天交易金额并修改支付状态
//            $channel_res = $this->saveOfflineStatus(
//                $m_Channel,
//                $order_info['channel_id'],
//                $order_info['pay_amount'],
//                $channel_info
//            );

            //-----------------------------------------修改通道风控支付数据end------------------------------------------------

            //-----------------------------------------修改子账号风控支付数据start--------------------------------------------
//            $m_ChannelAccount = M('ChannelAccount');
//            $channel_account_where = ['id' => $order_info['account_id']];
//            $channel_account_info = $m_ChannelAccount->where($channel_account_where)->find();
//            if ($channel_account_info['is_defined'] == 0) {
//                //继承自定义风控规则
//                $channel_info['paying_money'] = $channel_account_info['paying_money']; //当天已交易金额应该为子账号的交易金额
//                $channel_account_info = $channel_info;
//            }
            //判断当天交易金额并修改支付状态
//            $channel_account_res = $this->saveOfflineStatus(
//                $m_ChannelAccount,
//                $order_info['account_id'],
//                $order_info['pay_amount'],
//                $channel_account_info
//            );
//            if ($channel_account_info['unit_interval']) {
//                $m_ChannelAccount->where([
//                    'id' => $order_info['account_id'],
//                ])->save([
//                    'unit_paying_number' => ['exp', 'unit_paying_number+1'],
//                    'unit_paying_amount' => ['exp', 'unit_paying_amount+' . $order_info['pay_actualamount']],
//                ]);
//            }

            //-----------------------------------------修改子账号风控支付数据end----------------------------------------------
            //订单信息存入缓存
            $order_info['pay_status'] = 1;
            $redis->set($order_info['pay_orderid'],json_encode($order_info),3600 * 2);
        }

        //************************************************回调，支付跳转*******************************************//

        $return_array = [ // 返回字段
            "memberid" => $order_info["pay_memberid"], // 商户ID
            "orderid" => $order_info['out_trade_id'], // 订单号
            'transaction_id' => $order_info["pay_orderid"], //支付流水号
            "amount" => $order_info["pay_amount"], // 交易金额
            "datetime" => date("YmdHis"), // 交易时间
            "returncode" => "00", // 交易状态
        ];
        if (!isset($member_info)) {
            $member_info = M('Member')->where(['id' => $userid])->find();
        }
        $sign = $this->createSign($member_info['apikey'], $return_array);
        $return_array["sign"] = $sign;
        $return_array["attach"] = $order_info["attach"];
        switch ($returntype) {
            case '0':
                // 在列表尾部插入元素
                $redis->rPush('notifyList', $order_info['pay_orderid']);
//                 $notifystr = "";
//                 foreach ($return_array as $key => $val) {
//                     $notifystr = $notifystr . $key . "=" . $val . "&";
//                 }
//                 $notifystr = rtrim($notifystr, '&');
//                 $ch = curl_init();
//                 curl_setopt($ch, CURLOPT_TIMEOUT, 10);
//                 curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
//                 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//                 curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
//                 curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//                 curl_setopt($ch, CURLOPT_POST, 1);
//                 curl_setopt($ch, CURLOPT_URL, $order_info["pay_notifyurl"]);
//                 curl_setopt($ch, CURLOPT_POSTFIELDS, $notifystr);
//                 curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded", "cache-control: no-cache"));
//                 $contents = curl_exec($ch);
//                 $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//                 curl_close($ch);

//                 //记录向下游异步通知日志
//                 log_server_notify($order_info["pay_orderid"], $order_info["pay_notifyurl"], $notifystr, $httpCode, $contents);

//                 if (strstr(strtolower($contents), "ok") != false) {
//                     //更新交易状态
//                     $order_where = [
//                         'id' => $order_info['id'],
//                         'pay_orderid' => $order_info["pay_orderid"],
//                     ];

//                     $order_result = $m_Order->table($table)->where($order_where)->setField("pay_status", 2);
//                 } else {
// //                    $this->jiankong($order_info['pay_orderid']);
//                 }
                break;

            case '1':
                $this->setHtml($order_info["pay_callbackurl"], $return_array);
                break;

            default:
                # code...
                break;
        }
        return true;
    }

    //修改渠道跟账号风控状态
    protected function saveOfflineStatus($model, $id, $pay_amount, $info)
    {
        if ($info['offline_status'] && $info['control_status'] && $info['all_money'] > 0) {
            //通道是否开启风控和支付状态为上线
            $data['paying_money']     = bcadd($info['paying_money'], $pay_amount, 4);
            $data['last_paying_time'] = time();

            if ($data['paying_money'] >= $info['all_money']) {
                $data['offline_status'] = 0;
            }
            return $model->where(['id' => $id])->save($data);
        }
        return true;
    }

    /**
     *  验证签名
     * @return bool
     */
    protected function verify()
    {
        //POST参数
        $requestarray = array(
            'pay_memberid'    => I('request.pay_memberid', 0, 'intval'),
            'pay_orderid'     => I('request.pay_orderid', ''),
            'pay_amount'      => I('request.pay_amount', ''),
            'pay_applydate'   => I('request.pay_applydate', ''),
            'pay_bankcode'    => I('request.pay_bankcode', ''),
            'pay_notifyurl'   => I('request.pay_notifyurl', ''),
            'pay_callbackurl' => I('request.pay_callbackurl', ''),
        );
        $md5key        = $this->merchants['apikey'];
        $md5keysignstr = $this->createSign($md5key, $requestarray);
        $pay_md5sign   = I('request.pay_md5sign');
        if ($pay_md5sign == $md5keysignstr) {
            return true;
        } else {
            return false;
        }
    }

    public function setHtml($tjurl, $arraystr)
    {
        $str = '<form id="Form1" name="Form1" method="post" action="' . $tjurl . '">';
        foreach ($arraystr as $key => $val) {
            $str .= '<input type="hidden" name="' . $key . '" value="' . $val . '">';
        }
        $str .= '</form>';
        $str .= '<script>';
        $str .= 'document.Form1.submit();';
        $str .= '</script>';
        exit($str);
    }

    public function jiankong($orderid)
    {
        ignore_user_abort(true);
        set_time_limit(3600);
        $Order = D("Order");
        $date = date('Ymd',strtotime(substr($orderid, 0, 8)));  //获取订单日期
        $interval = 10;
        do {
            if ($orderid) {
                $_where['pay_status'] = 1;
                $_where['num'] = array('lt', 3);
                $_where['pay_orderid'] = $orderid;
                $find = $Order->table($Order->getRealTableName($date))->where($_where)->find();
            } else {
                $find = $Order->table($Order->getRealTableName($date))->where("pay_status = 1 and num < 3")->order("id desc")->find();
            }
            if ($find) {
                $this->EditMoney($find["pay_orderid"], $find["pay_tongdao"], 0);
                $Order->table($Order->getRealTableName($date))->where(["id" => $find["id"]])->save(['num' => ['exp', 'num+1']]);
            }

            sleep($interval);
        } while (true);
    }

    /**
     * 资金变动记录
     * @param $arrayField
     * @return bool
     */
    protected function MoenyChange($arrayField)
    {
        // 资金变动
        foreach ($arrayField as $key => $val) {
            $data[$key] = $val;
        }
        $Moneychange = D("Moneychange");
        $tablename = $Moneychange -> getRealTableName($data['datetime']);
        $result = $Moneychange->table($tablename)->add($data);
        return $result ? true : false;
    }

    /**
     * 佣金处理
     * @param $arrayStr
     * @param int $num
     * @param int $tcjb
     * @return bool
     */
    private function bianliticheng($arrayStr, $num = 3, $tcjb = 1)
    {
        if ($num <= 0) {
            return false;
        }
        $userid    = $arrayStr["userid"];
        $tongdaoid = $arrayStr["tongdao"];
        $trans_id = $arrayStr["trans_id"];
        $feilvfind = $this->huoqufeilv($userid, $tongdaoid, $trans_id);

        if ($feilvfind["status"] == "error") {
            return false;
        } else {
            //商户费率（下级）
            $x_feilv    = $feilvfind["feilv"];
            $x_fengding = $feilvfind["fengding"];

            //代理商(上级)
            $parentid = M("Member")->where(["id" => $userid])->getField("parentid");
            if ($parentid <= 1) {
                return false;
            }
            $parentRate = $this->huoqufeilv($parentid, $tongdaoid, $trans_id);

            if ($parentRate["status"] == "error") {
                return false;
            } else {

                //代理商(上级）费率
                $s_feilv    = $parentRate["feilv"];
                $s_fengding = $parentRate["fengding"];

                //费率差
                $ratediff = (($x_feilv * 1000) - ($s_feilv * 1000)) / 1000;
                if ($ratediff <= 0) {
                    return false;
                } else {
                    $parent    = M('Member')->where(['id' => $parentid])->field('id,balance')->find();
                    if(empty($parent)) {
                        return false;
                    }
                    $brokerage = $arrayStr['money'] * $ratediff;
                    //代理佣金
                    $rows = [
                        'balance' => array('exp', "balance+{$brokerage}"),
                    ];
                    M('Member')->where(['id' => $parentid])->save($rows);
                    //代理商资金变动记录
                    $arrayField = array(
                        "userid"   => $parentid,
                        "ymoney"   => $parent['balance'],
                        "money"    => $arrayStr["money"] * $ratediff,
                        "gmoney"   => $parent['balance'] + $brokerage,
                        "datetime" => date("Y-m-d H:i:s"),
                        "tongdao"  => $tongdaoid,
                        "transid"  => $arrayStr["transid"],
                        "orderid"  => "tx" . date("YmdHis"),
                        "tcuserid" => $userid,
                        "tcdengji" => $tcjb,
                        "lx"       => 9,
                    );
                    $this->MoenyChange($arrayField); // 资金变动记录
                    $num                = $num - 1;
                    $tcjb               = $tcjb + 1;
                    $arrayStr["userid"] = $parentid;
                    $this->bianliticheng($arrayStr, $num, $tcjb);
                }
            }
        }
    }

    /**
     * 通道代理提成处理
     * @param $array
     */
    private function tongdao_ticheng($array)
    {
        $where = [
            "pid" => $array['tongdao'],             //产品号
            "channel" => $array['channel'],     //渠道号
            "status" => 1,                      //状态
        ];
        $useid_arr = M('ProductUser')->where($where)->field("userid")->select();
        if (empty($useid_arr)) {
            return false;
        } else {
            $useid_str = '';
            foreach ($useid_arr as $v) {
                $useid_str .= $v['userid'] . ",";
            }
            $useid_str = substr($useid_str, 0, -1);
            $member_arr = M('Member')->where("id in ($useid_str)")->field("id,groupid")->select();
            if (empty($member_arr)) return false;
            foreach ($member_arr as $mk => $mv) {
                if ($mv['groupid'] == 8) {
                    $parentRate = $this->huoqufeilv($mv['id'], $array['tongdao'], $array['transid']);
                    if ($parentRate["status"] == "error") {
                        return false;
                    } else {
                        //代理商费率
                        $s_feilv = $parentRate["feilv"];

                        if ($s_feilv <= 0) {
                            return false;
                        } else {
                            $parent = M('Member')->where(['id' => $mv['id']])->field('id,balance')->find();
                            if (empty($parent)) {
                                return false;
                            }
                            $brokerage = $array['money'] * $s_feilv;
                            //代理佣金
                            $rows = [
                                'balance' => array('exp', "balance+{$brokerage}"),
                            ];
                            M('Member')->where(['id' => $mv['id']])->save($rows);

                            //代理商资金变动记录
                            $arrayField = array(
                                "userid" => $mv['id'],
                                "ymoney" => $parent['balance'],
                                "money" => $array["money"] * $s_feilv,
                                "gmoney" => $parent['balance'] + $brokerage,
                                "datetime" => date("Y-m-d H:i:s"),
                                "tongdao" => $array['tongdao'],
                                "transid" => $array["transid"],
                                "orderid" => "td" . date("YmdHis"),
                                "tcuserid" => $array["userid"],
                                "tcdengji" => 1,
                                "lx" => 9,

                            );
                            $this->MoenyChange($arrayField); // 资金变动记录
                        }
                    }
                }
            }
        }
    }

    private function huoqufeilv($userid, $payapiid, $trans_id)
    {
        $return = array();
        //用户费率
        $userrate = M("Userrate")->where(["userid" => $userid, "payapiid" => $payapiid])->find();
        $return["status"]   = "ok";
        $return["feilv"]    = $userrate['feilv'];
        $return["fengding"] = $userrate['fengding'];
        return $return;
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
            if (!empty($val)) {
                $md5str = $md5str . $key . "=" . $val . "&";
            }
        }
        $sign = strtoupper(md5($md5str . "key=" . $Md5key));
        return $sign;
    }

    /**
     * 获取投诉保证金金额
     * @param $userid
     * @return array
     */
    private function getComplaintsDepositRule($userid)
    {
        $complaintsDepositRule = M('ComplaintsDepositRule')->where(['user_id' => $userid])->find();
        if (!$complaintsDepositRule || $complaintsDepositRule['status'] != 1) {
            $complaintsDepositRule = M('ComplaintsDepositRule')->where(['is_system' => 1])->find();
        }
        return $complaintsDepositRule ? $complaintsDepositRule : [];
    }
    protected function redis_connect(){
        //创建一个redis对象
        $redis = new \Redis();
        //连接 Redis 服务
        $redis->connect(C('REDIS_HOST'), C('REDIS_PORT'));
        //密码验证
        $redis->auth(C('REDIS_PWD'));
        return $redis;
    }
}

?>