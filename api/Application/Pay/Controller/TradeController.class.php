<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-10-30
 * Time: 21:24
 */
namespace Pay\Controller;

class TradeController extends PayController
{
    private $userid;
    private $apikey;
    public function __construct()
    {
        parent::__construct();
        $memberid = I("request.pay_memberid",0,'intval') - 10000;
        if (empty($memberid) || $memberid<=0) {
            $this->showmessage("pay_memberid error");
        }
        $this->userid = $memberid;
        $fans = M('Member')->where(['id'=>$this->userid])->find();
        if(!$fans){
            $this->showmessage('The memberid does not exist');
        }
        $this->apikey = $fans['apikey'];
    }

    //订单查询
    public function query()
    {
        $out_trade_id = I('request.pay_orderid', '', 'string,strip_tags,htmlspecialchars');
        if(!$out_trade_id){
            $this->showmessage("pay_orderid error！");
        }
        $pay_applydate = I('request.pay_applydate', '', 'string,strip_tags,htmlspecialchars');
        if(!$pay_applydate){
            $this->showmessage("pay_applydate error");
        }
        $pay_memberid = I("request.pay_memberid", 0, 'intval');
        if(!$pay_memberid) {
            $this->showmessage("pay_memberid error");
        }
        $request = [
            'pay_memberid'=>$pay_memberid,
            'pay_orderid'=>$out_trade_id,
            'pay_applydate'=>$pay_applydate,
        ];
        $signature = $this->createSign($this->apikey,$request);
        // echo $signature;
        $sign = I('request.pay_md5sign');
        if($signature != $sign){
            ksort($_POST);
            $md5str = "";
            foreach ($_POST as $key => $val) {
                if (!empty($val) && $key != 'pay_md5sign') {
                    $md5str = $md5str . $key . "=" . $val . "&";
                }
            }
            $sign = strtoupper(md5($md5str . "key=" . $this->apikey));
            $result = [
                'mgs' => 'Please compare the splicing order and signature',
                'Post data' => $requestarray,
                'Field concatenation order' => substr($md5str, 0, strlen($md5str) - 1),
                'sign' => $sign,
            ];
            $this->showmessage('sign error', $result);
        }
        //获取redis里的订单信息
        // $redis = $this->redis_connect();
        // $order = $redis->get($out_trade_id);
        // if(!$order || empty($order)){
            $where = [
                'pay_memberid'=>$pay_memberid,
                'out_trade_id'=>$out_trade_id,
                'pay_applydate'=>['between',[strtotime($pay_applydate . ' 00:00:00'),strtotime($pay_applydate . ' 23:59:59')]],
            ];
            $OrderModel = D('Order'); 
            $order = $OrderModel->table($OrderModel->getRealTableName($pay_applydate))->where($where)->find();
            // echo $OrderModel->table($OrderModel->getRealTableName($pay_applydate))->getLastSql();
            if(!$order){
                $where = [
                    'pay_memberid'=>$pay_memberid,
                    'out_trade_id'=>$out_trade_id,
                    'pay_applydate'=>['between',[strtotime($pay_applydate . ' 00:00:00'),strtotime($pay_applydate . ' 23:59:59') + 86400]],
                ];
                $t_date = date('Y-m-d',strtotime($pay_applydate . ' 23:59:59') + 86400);
                $OrderModel = D('Order'); 
                try {
                    $order = $OrderModel->table($OrderModel->getRealTableName($t_date))->where($where)->find();
                } catch (\Exception $e) {
                    
                }
                
            }
        // }
            
        if(!$order){
            $this->showmessage('Non-existent transaction order.');
        }
        if($order['pay_status']==0){
            $refCode = '3';
            $refMsg = "Paying";
        }elseif ($order['pay_status'] ==1 || $order['pay_status'] == 2){
            $refCode = '1';
            $refMsg = "Success";
        }else{
            $refCode = '8';
            $refMsg = "Unknown status";
        }
        
        $return = [
            'status' => 'success',
            'msg' => 'Success',
            'orderType' => 'ds',
            'mchid' => $pay_memberid,
            'out_trade_no' => $order['out_trade_id'],
            'amount' => $order['pay_amount'],
            'transaction_id' => $order['pay_orderid'],
            'refCode' => $refCode,
            'refMsg' => $refMsg,
        ];

        $return['sign'] = $this->createSign($this->apikey,$return);
        echo json_encode($return);
    }
}