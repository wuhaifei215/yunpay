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

        $order_info = $m_Order->table($m_Order->getRealTableName($date))->where(['pay_orderid' => $trans_id])->find(); //获取订单信息
        
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
            $res = $m_Order->table($m_Order->getRealTableName($date))->where(['pay_orderid' => $trans_id, 'pay_status' => 0])->save(['pay_status' => 1, 'pay_successdate' => $time]);
            if (!$res) {
                M()->rollback();
                return false;
            }
            //-----------------------------------------修改用户数据 商户余额、冻结余额start-----------------------------------
            //要给用户增加的实际金额（扣除投诉保证金）
            $actualAmount = $order_info['pay_actualamount'];

            //判断用结算方式
            switch ($order_info['t']) {
                case '0':
                    //t+0结算
                case '7':
                    //t+7 只限制提款和代付时间，每周一允许提款
                case '30':
                    //t+30 只限制提款和代付时间，每月第一天允许提款
                    if(getPaytypeCurrency($order_info['paytype']) ==='PHP'){        //菲律宾余额
                        $ymoney = $member_info['balance_php']; //改动前的金额
                        $gmoney = bcadd($member_info['balance_php'], $actualAmount, 4); //改动后的金额
                        $member_data['balance_php'] = ['exp', 'balance_php+' . $actualAmount]; //防止数据库并发脏读
                    }
                    if(getPaytypeCurrency($order_info['paytype']) ==='INR'){        //越南余额
                        $ymoney = $member_info['balance_inr']; //改动前的金额
                        $gmoney = bcadd($member_info['balance_inr'], $actualAmount, 4); //改动后的金额
                        $member_data['balance_inr'] = ['exp', 'balance_inr+' . $actualAmount]; //防止数据库并发脏读
                    }
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
                    if(getPaytypeCurrency($order_info['paytype']) ==='PHP'){        //菲律宾冻结余额
                        $ymoney = $member_info['blockedbalance_php']; //原冻结资金
                        $gmoney = bcadd($member_info['blockedbalance_php'], $actualAmount, 4); //改动后的冻结资金
                        $member_data['blockedbalance_php'] = ['exp', 'blockedbalance_php+' . $actualAmount]; //防止数据库并发脏读
                    }
                    if(getPaytypeCurrency($order_info['paytype']) ==='INR'){        //越南冻结余额
                        $ymoney = $member_info['blockedbalance_inr']; //改动前的金额
                        $gmoney = bcadd($member_info['blockedbalance_inr'], $actualAmount, 4); //改动后的金额
                        $member_data['blockedbalance_inr'] = ['exp', 'blockedbalance_inr+' . $actualAmount]; //防止数据库并发脏读
                    }
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
                'paytype' => $order_info['paytype'],
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
                'paytype' => $order_info['paytype'],
            ];
            $this->bianliticheng($bianliticheng_data); // 提成处理

            M()->commit();
            //订单信息存入缓存
            $order_info['pay_status'] = 1;
            $redis->set($order_info['pay_orderid'],json_encode($order_info),3600 * 2);
        }

        switch ($returntype) {
            case '0':
                
                // 在列表尾部插入元素
                $redis->rPush('notifyList', $order_info['pay_orderid']);

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
        $userid = $arrayStr["userid"];
        $tongdaoid = $arrayStr["tongdao"];
        $trans_id = $arrayStr["transid"];
        $paytype = $arrayStr['paytype'];
        $feilvfind = $this->huoqufeilv($userid, $tongdaoid, $trans_id);

        if ($feilvfind["status"] == "error") {
            return false;
        } else {
            //商户费率（下级）
            $x_feilv = $feilvfind["feilv"];
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
                $s_feilv = $parentRate["feilv"];
                $s_fengding = $parentRate["fengding"];

                //费率差
                $ratediff = (($x_feilv * 1000) - ($s_feilv * 1000)) / 1000;
                if ($ratediff <= 0) {
                    return false;
                } else {
                    $parent = M('Member')->where(['id' => $parentid])->field('id,balance_php,balance_inr')->find();
                    if (empty($parent)) {
                        return false;
                    }
                    $brokerage = $arrayStr['money'] * $ratediff;
                    //代理佣金
                    if(getPaytypeCurrency($arrayStr['paytype']) ==='PHP'){        //菲律宾余额
                        $ymoney = $parent['balance_php'];
                        $rows = ['balance_php' => array('exp', "balance_php+{$brokerage}")];
                    }
                    if(getPaytypeCurrency($arrayStr['paytype']) ==='INR'){        //越南余额
                        $ymoney = $parent['balance_inr'];
                        $rows = ['balance_inr' => array('exp', "balance_inr+{$brokerage}")];
                    }
                    M('Member')->where(['id' => $parentid])->save($rows);

                    //代理商资金变动记录
                    $arrayField = array(
                        "userid" => $parentid,
                        "ymoney" => $ymoney,
                        "money" => $brokerage,
                        "gmoney" => $ymoney + $brokerage,
                        "datetime" => date("Y-m-d H:i:s"),
                        "tongdao" => $tongdaoid,
                        "transid" => $arrayStr["transid"],
                        "orderid" => "tx" . date("YmdHis"),
                        "tcuserid" => $userid,
                        "tcdengji" => $tcjb,
                        'paytype' => $arrayStr['paytype'],
                        "lx" => 9,
                    );
                    $this->MoenyChange($arrayField); // 资金变动记录
                    $num = $num - 1;
                    $tcjb = $tcjb + 1;
                    $arrayStr["userid"] = $parentid;
                    $this->bianliticheng($arrayStr, $num, $tcjb);
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