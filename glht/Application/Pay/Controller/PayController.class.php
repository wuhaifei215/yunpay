<?php

namespace Pay\Controller;

use Think\Controller;

class PayController extends Controller
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
        $this->_site = ((is_https()) ? 'https' : 'http') . '://' . C("NOTIFY_DOMAIN") . '/';
        $deny = ['exp', 'delete', 'update', 'insert'];
        foreach ($_REQUEST as $k => $v) { 
            if (in_array(strtolower($k), $deny)) {
                die;
            }
            switch (strtolower($k)) {
                case 'pay_orderid':
                case 'pay_productname':
                case 'pay_attach':
                case 'out_trade_no':
                case 'outtradeno':
                case 'orderid':
                case 'orderno':
                case 'merorderid':
                    $_REQUEST[$k] = (string)$_REQUEST[$k];
                    if (isset($_GET[$k])) {
                        $_GET[$k] = (string)$_GET[$k];
                    }
                    if (isset($_POST[$k])) {
                        $_POST[$k] = (string)$_POST[$k];
                    }
                    break;
            }
        }
    }

    /**
     * 创建订单
     * @param $parameter
     * @return array
     */
    protected function orderadd($parameter)
    {
        $pay_amount = I("post.pay_amount", 0);

        //通道信息
        $this->channel = $parameter['channel'];
        //$this->merchants = $this->channel['userid'];
        //用户信息
        $usermodel = D('Member');
        $this->merchants = $usermodel->get_Userinfo($this->channel['userid']);
        $return = array();
        // 通道名称
        $PayName = $parameter["code"];
        // 交易金额比例
        $moneyratio = $parameter["exchange"];
        //商户编号
        $return["memberid"] = $userid = $this->merchants['id'] + 10000;
        $m_Tikuanconfig = M('Tikuanconfig');
        $tikuanconfig = $m_Tikuanconfig->where(['userid' => $this->merchants['id']])->find();
        if (!$tikuanconfig || $tikuanconfig['tkzt'] != 1 || $tikuanconfig['systemxz'] != 1) {
            $tikuanconfig = $m_Tikuanconfig->where(['issystem' => 1])->find();
        }
        //银行通道
        $syschannel = M('Channel')
            ->where(['id' => intval($this->channel['api'])])
            ->find();

        //---------------------------子账号风控start------------------------------------
        $channel_account_list = M('channel_account')->where(['channel_id' => $syschannel['id'], 'status' => '1'])->select();
//        //用户指定子商户
//        $account_ids = M('UserChannelAccount')->where(['userid' => intval($this->channel['userid']), 'status' => 1])->getField('account_ids');
//        if ($account_ids) {
//            $account_ids = explode(',', $account_ids);
//            foreach ($channel_account_list as $k => $v) {
//                //如果不在指定的子账号，将其删除
//                if (!in_array($v['id'], $account_ids)) {
//                    unset($channel_account_list[$k]);
//                }
//            }
//        }

//        $l_ChannelAccountRiskcontrol = new \Pay\Logic\ChannelAccountRiskcontrolLogic($pay_amount);
        $channel_account_item = [];
        $error_msg = '已下线';
        foreach ($channel_account_list as $k => $v) {
            if ($v['offline_status'] && $v['control_status']) {
                //判断是自定义还是继承渠道的风控
                $temp_info = $v['is_defined'] ? $v : $syschannel;
                $temp_info['account_id'] = $v['id']; //用于子账号风控类继承渠道风控机制时修改数据的id

                //修改相同商户 共享金额风控
                if ($v['is_defined']) {
                    $share_money = M('channel_account')->where(array('mch_id' => $v['mch_id']))->sum('paying_money');
                    if ($share_money) {
                        $temp_info['paying_money'] = $share_money;
                        $temp_info['share_mch_id'] = $v['mch_id'];
                    }
                }

//                //子账号风控
//                $l_ChannelAccountRiskcontrol->setConfigInfo($temp_info);
//                $error_msg = $l_ChannelAccountRiskcontrol->monitoringData();
//                if ($error_msg === true) {
//                    $channel_account_item[] = $v;
//                }
            } else if ($v['control_status'] == 0) {
                $channel_account_item[] = $v;
            }
        }
        if (empty($channel_account_item)) {
            $this->showmessage('账户:' . $error_msg);
        }
        //-------------------------子账号风控end-----------------------------------------

        // 计算权重
        if (count($channel_account_item) == 1) {
            $channel_account = current($channel_account_item);
        } else {
//            $control_order=M('channel')->field('control_order')->where(['id' =>$this->channel['channel']])->find();
//            if ($control_order['control_order'] == 3) {
//
//                //按次数匹配
//                $second_channel_account_item = [];
//                if(is_array($channel_account_item)){
//                    foreach ($channel_account_item as $cak =>$cav){
//                        if ($cav['fail_rounds'] >= 3) {
//                            M('ChannelAccount')->where(['id' => $cav["id"]])->save(['status' => 0]);
//                        }
//                        if ($cav['fail_num'] >= 5 && $cav['fail_rounds'] < 3) {
//                            M('ChannelAccount')->where(['id' => $cav["id"]])->setInc("fail_rounds");
//                            M('ChannelAccount')->where(['id' => $cav["id"]])->save(['fail_num' => 0]);
//                            $second_channel_account_item[$cak] = $cav;
//                        }
//                        if ($cav['success_num'] >= 10){
//                            $second_channel_account_item[$cak] = $cav;
//                        }
//                        if ($cav['success_num'] >= 10 || $cav['fail_num'] >= 5 || $cav['fail_rounds'] >= 3) {
//                            continue;
//                        }
//                        $channel_account = $cav;
//                    }
//                    if (empty($channel_account)) {
//                        //重置成功与失败次数
//                        $channel_account_id = array_column($channel_account_item, 'id');
//                        $last_where['id'] = array('in', $channel_account_id);
//                        $last_where['status'] = 1;
//                        $update_data['success_num'] = 0;
//                        $update_data['fail_num'] = 0;
//                        M('ChannelAccount')->where($last_where)->save($update_data);
//
//                        //Reset($second_channel_account_item);
//                        $channel_account = current($second_channel_account_item);
//                    }
//                }
//
//            } elseif ($control_order['control_order']==2) {
//                //排序最小的钱
//
//                $keysvalue = $new_account_item = array();
//                foreach ($channel_account_item as $k=>$v){
//                    $keysvalue[$k] = $v['paying_money'];
//                }
//                asort($keysvalue);
//                reset($keysvalue);
//                foreach ($keysvalue as $k=>$v){
//                    $new_account_item[$k] = $channel_account_item[$k];
//                }
//
//
//                reset($keysvalue);
//                $min_accout=current($new_account_item);
//
//                $max_accout=array_pop($new_account_item);
//
//                if ($min_accout['paying_money'] >0) {
//                    $channel_account =$min_accout;
//                } elseif ($max_accout['paying_money'] ==0) {
//                    $channel_account = getWeight($channel_account_item);
//                }else {
//                    $channel_account =$min_accout;
//                }
//
//            }elseif ($control_order['control_order']==1) {
//                //顺序
//                //查找些子账号的 上一个订单
//                $channel_account=array();
//                $channel_account_id=array_column($channel_account_item, 'id');
//                $last_where['pay_memberid']=$userid;
//                $last_where['account_id']=array('in',$channel_account_id);
//
//                $last_odrder=M('order')->where($last_where)->order('id desc')->find();
//                while (list($k, $v) = each($channel_account_item)) {
//                    if ($v['id'] ==$last_odrder['account_id']) {
//                        break;
//                    }
//
//                }
//                $channel_account=current($channel_account_item);
//                if (empty($channel_account)) {
//                    Reset($channel_account_item);
//                    $channel_account=current($channel_account_item);
//                }
//            } else{
            $channel_account = getWeight($channel_account_item);
//            }
        }

        $syschannel['mch_id'] = $channel_account['mch_id'];
        $syschannel['signkey'] = $channel_account['signkey'];
        $syschannel['appid'] = $channel_account['appid'];
        $syschannel['subappid'] = $channel_account['subappid'];
        $syschannel['appsecret'] = $channel_account['appsecret'];
        $syschannel['account'] = $channel_account['title'];

        // 定制成本费率
        if ($channel_account['custom_rate']) {
            $syschannel['rate'] = $channel_account['rate'];
        }

        //平台通道
        $platform = M('Product')->field('code,name')->where(['id' => $this->channel['pid']])->find();
        if ($channel_account['unlockdomain']) {
            $unlockdomain = $channel_account['unlockdomain'] ? $channel_account['unlockdomain'] : '';
        } else {
            $unlockdomain = $syschannel['unlockdomain'] ? $syschannel['unlockdomain'] : '';
        }

        //回调参数
        $return = [
            "mch_id" => $syschannel["mch_id"], //商户号
            "signkey" => $syschannel["signkey"], // 签名密钥
            "appid" => $syschannel["appid"], // APPID
            "subappid" => $syschannel["subappid"], // 多级代理商编号
            "appsecret" => $syschannel["appsecret"], // APPSECRET
            "gateway" => $syschannel["gateway"] ? $syschannel["gateway"] : $parameter["gateway"], // 网关
            "notifyurl" => $syschannel["serverreturn"] ? $syschannel["serverreturn"] : $this->_site . "Pay_" . $PayName . "_notifyurl.html",
            "callbackurl" => $syschannel["pagereturn"] ? $syschannel["pagereturn"] : $this->_site . "Pay_" . $PayName . "_callbackurl.html",
            'unlockdomain' => $unlockdomain, //防封域名
        ];

        //金额格式化
        if (!$pay_amount || !is_numeric($pay_amount) || $pay_amount <= 0) {
            $this->showmessage('金额错误');
        }
        $return["amount"] = floatval($pay_amount) * $moneyratio; // 交易金额

        //费率
        $_userrate = M('Userrate')->field('feilv')->where(["userid" => $this->channel['userid'], "payapiid" => intval($this->channel['pid'])])->find();
        $pay_sxfamount = $pay_amount * $_userrate['feilv']; // 手续费
        $pay_shijiamount = $pay_amount - $pay_sxfamount; // 实际到账金额
        $cost = bcmul($syschannel['rate'], $pay_amount, 4); //计算成本

        //商户订单号
        $out_trade_id = $parameter['out_trade_id'];
        //生成系统订单号
        $pay_orderid = $parameter['orderid'] ? $parameter['orderid'] : get_requestord();

        //验签
        if ($this->verify()) {
            $Order = D("Order");
            $return['bankcode'] = $this->channel['pid'];
            $return['code'] = $platform['code']; //银行英文代码
            $return['orderid'] = $pay_orderid; // 系统订单号
            $return['out_trade_id'] = $out_trade_id; // 外部订单号
            $return['subject'] = $parameter['body']; // 商品标题
            $data['pay_memberid'] = $userid;
            $data['pay_orderid'] = $return["orderid"];
            $data['pay_amount'] = $pay_amount; // 交易金额
            $data['pay_poundage'] = $pay_sxfamount; // 手续费
            $data['pay_actualamount'] = $pay_shijiamount; // 到账金额
            $data['pay_applydate'] = time();
            $data['pay_bankcode'] = intval($this->channel['pid']);
            $data['pay_bankname'] = $platform['name'];
            $data['pay_notifyurl'] = I('post.pay_notifyurl', '', 'string,strip_tags,htmlspecialchars');
            $data['pay_callbackurl'] = I('post.pay_callbackurl', '', 'string,strip_tags,htmlspecialchars');
            $data['pay_status'] = 0;
            $data['pay_tongdao'] = $syschannel['code'];
            $data['pay_zh_tongdao'] = $syschannel['title'];
            $data['pay_channel_account'] = $syschannel['account'];
            $data['pay_ytongdao'] = $parameter["code"];
            $data['pay_yzh_tongdao'] = $parameter["title"];
            $data['pay_tjurl'] = isset($_SERVER['HTTP_REFERER'])?substr((string)$_SERVER['HTTP_REFERER'], 0, 1000):'';
            $data['pay_productname'] = I("request.pay_productname");
            $data['attach'] = I("request.pay_attach");
            $data['out_trade_id'] = $out_trade_id;
            $data['paytype'] = $syschannel['paytype'];      //通道类型
            $data['memberid'] = $return["mch_id"];
            $data['key'] = $return["signkey"];
            $data['account'] = $return["appid"];
            $data['cost'] = $cost;
            $data['cost_rate'] = $tikuanconfig['t1zt'] == 0 ? $syschannel['t0rate'] : $syschannel['rate'];
            $data['channel_id'] = $this->channel['api'];
            $data['account_id'] = $channel_account['id'];
            $data['t'] = $tikuanconfig['t1zt'];
            //添加订单
            try {
                $or_add = $Order->table($Order->getRealTableName(date('Y-m-d', $data['pay_applydate'])))->add($data);
                
                //下游单号存入缓存，防止重复提交
                $redis = $this->redis_connect();
                $redis->set($out_trade_id, $userid ,7200);
                
            } catch (\Exception $e) {
                $this->showmessage('重复订单号');
            }
            if ($or_add) {
                $return['datetime'] = date('Y-m-d H:i:s', $data['pay_applydate']);
                $return["status"] = "success";
                return $return;
            } else {
                $this->showmessage('系统错误');
            }
        } else {
            $requestarray = array(
                'pay_memberid' => I('request.pay_memberid', 0, 'intval'),
                'pay_orderid' => I('request.pay_orderid', ''),
                'pay_amount' => I('request.pay_amount', ''),
                'pay_applydate' => I('request.pay_applydate', ''),
                'pay_bankcode' => I('request.pay_bankcode', ''),
                'pay_notifyurl' => I('request.pay_notifyurl', ''),
                'pay_callbackurl' => I('request.pay_callbackurl', ''),
            );
            $md5str = "";
            ksort($requestarray);
            foreach ($requestarray as $key => $val) {
                $md5str = $md5str . $key . "=" . $val . "&";
            }
            $sign = strtoupper(md5($md5str . "key=" . $this->merchants['apikey']));
            $result = [
                '提醒' => '请比对拼接顺序及签名',
                'POST数据' => $requestarray,
                '拼接顺序' => substr($md5str, 0, strlen($md5str) - 1),
                '签名' => $sign,
            ];
            $this->showmessage('签名验证失败', $result);
        }
    }

    /**
     * 回调处理订单
     * @param $TransID
     * @param $PayName
     * @param int $returntype
     */
    protected function EditMoney($trans_id, $pay_name = '', $returntype = 1, $transaction_id = '')
    {
        //设置redis标签，防止重复执行
        $redis = $this->redis_connect();
        if($redis->get('EditMoney' . $trans_id)){
            return false;
        }
        $redis->set('EditMoney' . $trans_id,'1',120);
        
        $m_Order = D("Order");
        $date = date('Ymd', strtotime(substr($trans_id, 0, 8)));  //获取订单日期

        $order_info = $m_Order->table($m_Order->getRealTableName($date))->where(['pay_orderid' => $trans_id, 'pay_tongdao' => $pay_name])->find(); //获取订单信息
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

        // $return_array = [ // 返回字段
        //     "memberid" => $order_info["pay_memberid"], // 商户ID
        //     "orderid" => $order_info['out_trade_id'], // 订单号
        //     'transaction_id' => $order_info["pay_orderid"], //支付流水号
        //     "amount" => $order_info["pay_amount"], // 交易金额
        //     "datetime" => date("YmdHis"), // 交易时间
        //     "returncode" => "00", // 交易状态
        // ];
        // $member_redis_info = $redis->get('userinfo_' . $userid);
        // $member_info = json_decode($member_redis_info,true);
        // if (!isset($member_redis_info) || !$member_info['apikey']) {
        //     $member_info = M('Member')->where(['id' => $userid])->find();
        //     $redis->set('userinfo_' . $userid, json_encode($member_info),3600);
        // }
        // $sign = $this->createSign($member_info['apikey'], $return_array);
        // $return_array["sign"] = $sign;
        // $return_array["attach"] = $order_info["attach"];
        switch ($returntype) {
            case '0':
                
                // 在列表尾部插入元素
                $redis->rPush('notifyList', $order_info['pay_orderid']);
                // $notifystr = "";
                // foreach ($return_array as $key => $val) {
                //     $notifystr = $notifystr . $key . "=" . $val . "&";
                // }
                // $notifystr = rtrim($notifystr, '&');
                // $ch = curl_init();
                // curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                // curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                // curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                // curl_setopt($ch, CURLOPT_POST, 1);
                // curl_setopt($ch, CURLOPT_URL, $order_info["pay_notifyurl"]);
                // curl_setopt($ch, CURLOPT_POSTFIELDS, $notifystr);
                // curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded", "cache-control: no-cache"));
                // $contents = curl_exec($ch);
                // $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                // curl_close($ch);

                // //记录向下游异步通知日志
                // log_server_notify($order_info["pay_orderid"], $order_info["pay_notifyurl"], $notifystr, $httpCode, $contents);

                // if (strstr(strtolower($contents), "ok") != false) {
                //     //更新交易状态
                //     $order_where = [
                //         'id' => $order_info['id'],
                //         'pay_orderid' => $order_info["pay_orderid"],
                //     ];

                //     $order_result = $m_Order->table($m_Order->getRealTableName($date))->where($order_where)->setField("pay_status", 2);
                    //订单信息存入缓存
                    // $order_info['pay_status'] = 2;
                    // $redis->set($order_info['pay_orderid'],json_encode($order_info),3600 * 2);
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
    }

    //修改渠道跟账号风控状态
//    protected function saveOfflineStatus($model, $id, $pay_amount, $info)
//    {
//        if ($info['offline_status'] && $info['control_status'] && $info['all_money'] > 0) {
//            //通道是否开启风控和支付状态为上线
//            $data['paying_money'] = bcadd($info['paying_money'], $pay_amount, 4);
//            $data['last_paying_time'] = time();
//
//            if ($data['paying_money'] >= $info['all_money']) {
//                $data['offline_status'] = 0;
//            }
//            return $model->where(['id' => $id])->save($data);
//        }
//        return true;
//    }

    /**
     *  验证签名
     * @return bool
     */
    protected function verify()
    {
        //POST参数
        $requestarray = array(
            'pay_memberid' => I('request.pay_memberid', 0, 'intval'),
            'pay_orderid' => I('request.pay_orderid', ''),
            'pay_amount' => I('request.pay_amount', ''),
            'pay_applydate' => I('request.pay_applydate', ''),
            'pay_bankcode' => I('request.pay_bankcode', ''),
            'pay_notifyurl' => I('request.pay_notifyurl', ''),
            'pay_callbackurl' => I('request.pay_callbackurl', ''),
        );
        $md5key = $this->merchants['apikey'];
        $md5keysignstr = $this->createSign($md5key, $requestarray);
        $pay_md5sign = I('request.pay_md5sign');
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

    //订单每10秒补发
    public function jiankong($orderid)
    {
        ignore_user_abort(true);
        set_time_limit(3600);
        $Order = D("Order");
        $date = date('Ymd', strtotime(substr($orderid, 0, 8)));  //获取订单日期
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
        // $result = $Moneychange->add($data);
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

    /**
     * 通道代理提成处理
     * @param $array
     */
//    private function tongdao_ticheng($array)
//    {
//        $where = [
//            "pid" => $array['tongdao'],             //产品号
//            "channel" => $array['channel'],     //渠道号
//            "status" => 1,                      //状态
//        ];
//        $useid_arr = M('ProductUser')->where($where)->field("userid")->select();
//        if (empty($useid_arr)) {
//            return false;
//        } else {
//            $useid_str = '';
//            foreach ($useid_arr as $v) {
//                $useid_str .= $v['userid'] . ",";
//            }
//            $useid_str = substr($useid_str, 0, -1);
//            $member_arr = M('Member')->where("id in ($useid_str)")->field("id,groupid")->select();
//            if (empty($member_arr)) return false;
//            foreach ($member_arr as $mk => $mv) {
//                if ($mv['groupid'] == 8) {
//                    $parentRate = $this->huoqufeilv($mv['id'], $array['tongdao'], $array['transid']);
//                    if ($parentRate["status"] == "error") {
//                        return false;
//                    } else {
//                        //代理商费率
//                        $s_feilv = $parentRate["feilv"];
//
//                        if ($s_feilv <= 0) {
//                            return false;
//                        } else {
//                            $parent = M('Member')->where(['id' => $mv['id']])->field('id,balance')->find();
//                            if (empty($parent)) {
//                                return false;
//                            }
//                            $brokerage = $array['money'] * $s_feilv;
//                            //代理佣金
//                            $rows = [
//                                'balance' => array('exp', "balance+{$brokerage}"),
//                            ];
//                            M('Member')->where(['id' => $mv['id']])->save($rows);
//
//                            //代理商资金变动记录
//                            $arrayField = array(
//                                "userid" => $mv['id'],
//                                "ymoney" => $parent['balance'],
//                                "money" => $array["money"] * $s_feilv,
//                                "gmoney" => $parent['balance'] + $brokerage,
//                                "datetime" => date("Y-m-d H:i:s"),
//                                "tongdao" => $array['tongdao'],
//                                "transid" => $array["transid"],
//                                "orderid" => "td" . date("YmdHis"),
//                                "tcuserid" => $array["userid"],
//                                "tcdengji" => 1,
//                                "lx" => 9,
//
//                            );
//                            $this->MoenyChange($arrayField); // 资金变动记录
//                        }
//                    }
//                }
//            }
//        }
//    }

    private function huoqufeilv($userid, $payapiid, $trans_id)
    {
        $return = array();
        $userrate = M("Userrate")->where(["userid" => $userid, "payapiid" => $payapiid])->find();
        $return["status"] = "ok";
        $return["feilv"] = $userrate['feilv'];
        $return["fengding"] = $userrate['feilv'];
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
//        echo $md5str . "key=" . $Md5key;
        $sign = strtoupper(md5($md5str . "key=" . $Md5key));
        return $sign;
    }

    public function bufa()
    {
        header('Content-type:text/html;charset=utf-8');
        $TransID = I("get.TransID", '', 'string,strip_tags,htmlspecialchars');
        $PayName = I("get.tongdao", '', 'string,strip_tags,htmlspecialchars');
        $Order = D("Order");
        $date = date('Ymd', strtotime(substr($TransID, 0, 8)));  //获取订单日期
        $pay_status = $Order->table($Order->getRealTableName($date))->where(array("pay_orderid" => $TransID))->getField("pay_status");
        if (intval($pay_status) == 1) {
            echo("订单号：" . $TransID . "|" . $PayName . "已补发服务器点对点通知，请稍后刷新查看结果！<a href='javascript:window.close();'>关闭</a>");
            $this->EditMoney($TransID, $PayName, 0);
        } else {
            echo "补发失败";
        }

    }

    /**
     * 扫码订单状态检查
     *
     */
    public function checkstatus()
    {
        $orderid = I("post.orderid", '', 'string,strip_tags,htmlspecialchars');
        $Order = D("Order");
        $date = date('Ymd', strtotime(substr($orderid, 0, 8)));  //获取订单日期
        $order = $Order->table($Order->getRealTableName($date))->where(array('pay_orderid' => $orderid))->find();
        if ($order['pay_status'] != 0) {
            echo json_encode(array('status' => 'ok', 'callback' => $this->_site . "Pay_" . $order['pay_tongdao'] . "_callbackurl.html?orderid="
                . $orderid . "&pay_memberid=" . $order['pay_memberid'] . '&bankcode=' . $order['pay_bankcode']));
            exit();
        } else {
            exit("no-$orderid");
        }
    }

    /**
     * 错误返回
     * @param string $msg
     * @param array $fields
     */
    protected function showmessage($msg = '', $fields = array())
    {
        header('Content-Type:application/json; charset=utf-8');
        $data = array('status' => 'error', 'msg' => $msg, 'data' => $fields);
        echo json_encode($data, 320);
        exit;
    }

    protected function getParameter($title, $channel, $className, $exchange = 1)
    {
        if (substr_count($className, 'Controller')) {
            $length = strlen($className) - 25;
            $code = substr($className, 15, $length);
        }
        $parameter = array(
            'code' => $code, // 通道名称
            'title' => $title, //通道名称
            'exchange' => $exchange, // 金额比例
            'gateway' => '',
            'orderid' => '',
            'out_trade_id' => I('request.pay_orderid', ''), //外部订单号
            'channel' => $channel,
            'body' => I('request.pay_productname', ''),
        );
        $return = $this->orderadd($parameter);
        //如果生成错误，自动跳转错误页面
        $return["status"] == "error" && $this->showmessage($return["errorcontent"]);

        //跳转页面，优先取数据库中的跳转页面
        $return["notifyurl"] || $return["notifyurl"] = $this->_site . 'Pay_' . $code . '_notifyurl.html';
        $return['callbackurl'] || $return['callbackurl'] = $this->_site . 'Pay_' . $code . '_callbackurl.html';
        return $return;
    }

    protected function showQRcode($url, $return, $view = 'weixin')
    {
        import("Vendor.phpqrcode.phpqrcode", '', ".php");
        $QR = "Uploads/codepay/" . $return["orderid"] . ".png"; //已经生成的原始二维码图
        \QRcode::png($url, $QR, "L", 20);
        $imgurl = $this->base64EncodeImage($QR);
        $this->assign("imgurl", $imgurl);
        $this->assign('params', $return);
        $this->assign('orderid', $return['orderid']);
        $this->assign('money', $return['amount']);
        $this->display("WeiXin/" . $view);
    }

    protected function base64EncodeImage($image_file)
    {
        $base64_image = '';
        $image_info = getimagesize($image_file);
        $image_data = fread(fopen($image_file, 'r'), filesize($image_file));
        $base64_image = 'data:' . $image_info['mime'] . ';base64,' . chunk_split(base64_encode($image_data));
        return $base64_image;
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
    
        
    /**
     * 发送回调信息
     * $orderList 订单信息
     */
    public function sendTelegram($orderid){
        
        if(!$orderid) return;
        $redis = $this->redis_connect();
        $redis->rPush('sendTelegramList', $orderid);
        
        // $OrderModel = D('Order');
        // $date = date('Ymd',strtotime(substr($orderid, 0, 8)));  //获取订单日期
        // $tablename = $OrderModel->getRealTableName($date);
        // $orderList = $OrderModel->table($tablename)->where(['pay_orderid' => $orderid])->find();
        
        // if(!$orderList['pay_memberid'] || !$orderList['pay_orderid'])return;
        // $TelegramApi_where = [
        //     'member_id'=>$orderList['pay_memberid'],
        //     'pay_orderid' => $orderList['pay_orderid'],
        //     'create_time' => ['between',[time() - 3600 * 72, time()]],
        // ];
        // $TelegramApi_re = M('TelegramApiOrder')->where($TelegramApi_where)->order('id DESC')->limit(1)->select();
        // // log_place_order('Telegram_notifyurl', $orderList['pay_orderid'] . "----sql", M('TelegramApiOrder')->getLastSql());    //日志
        // if($TelegramApi_re){
        //     log_place_order('Telegram_notifyurl', $orderList['pay_orderid'] . "----order", json_encode($orderList, JSON_UNESCAPED_UNICODE));    //日志
        //     log_place_order('Telegram_notifyurl', $orderList['pay_orderid'] . "----data", json_encode($TelegramApi_re, JSON_UNESCAPED_UNICODE));    //日志
        //     $order_info['status'] = 1;
        //     $order_info['info'] = $orderList;
        //     $send_re = R('Telegram/Api/doDS', [$order_info, $TelegramApi_re[0]['chat_id'], $TelegramApi_re[0]['message'], $TelegramApi_re[0]['message_id']]);
        //     log_place_order('Telegram_notifyurl', $orderList['pay_orderid'] . "----fasong", $send_re);    //日志
        // }
        // return;
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
    
    protected function getOrder($orderid){
        $OrderModel = D('Order');
        $date = date('Ymd',strtotime(substr($orderid, 0, 8)));  //获取订单日期
        $tablename = $OrderModel->getRealTableName($date);
        $orderList = $OrderModel->table($tablename)->where(['pay_orderid' => $orderid])->find();
        return $orderList;
    }
}
