<?php

namespace Cli\Controller;

use \think\Controller;
use Think\Log;

/**
 * @author mapeijian
 * @date   2018-06-06
 */
class NotifyDFBuFaController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        //自动创建日志文件
        $filePath = './Runtime/Logs/Cli/';
        if(@mkdirs($filePath, 777)) {
            $destination = $filePath.'cli_notifyDF_BuFa.log';
            if(!file_exists($destination)) {
                $handle = @fopen($destination,   'wb ');
                @fclose($handle);
            }
        }
    }
    public function index(){
        echo "[" . date('Y-m-d H:i:s'). "] 自动代付任务触发\n";
        for ($i=1; $i<=5; $i++)
        {
            $this->do_index();
        }
        echo "[" . date('Y-m-d H:i:s'). "] 自动代付任务结束\n\n";
    }
    public function do_index(){
        $time = time();
        $redis = redis_connect();
        //取出第一个
        $orderKey = $redis->lPop('notifyList_DF_BuFa');
        // $orderKey = 'P2025071404125877178165056';
        if($orderKey === false ||  $orderKey =='' || $orderKey =='{}'){
            // echo "[" . date('Y-m-d H:i:s'). "] 没有需要处理\n";
            return;
        }
        echo $time . "orderId_" . $orderKey . "\n";
        $list_msg = $redis->get($orderKey);
        
        $WttklistModel = D('Wttklist');
        $date = date('Ymd', strtotime(substr($orderKey, 1, 8)));  //获取订单日期
        $talbe = $WttklistModel->getRealTableName($date);
        if(!empty($list_msg) && $list_msg !=='' && $list_msg !=='{}'){
            $wttklist =  json_decode($list_msg,true);
        }else{
            $wttklist = $WttklistModel->table($talbe)->where(['orderid' => $orderKey])->find();
        }
        if(!$wttklist || $wttklist=='' || empty($wttklist)){
            echo "[" . date('Y-m-d H:i:s'). "] 不需要处理\n";
            return false;
        }
        $return_array = [ // 返回字段
            "memberid" => $wttklist['userid'] + 10000, // 商户ID
            'orderid' => $wttklist["out_trade_no"], //支付流水号
            "transaction_id" => $wttklist['orderid'], // 订单号
            "amount" => $wttklist["money"], // 交易金额
            "datetime" => date("YmdHis"), // 交易时间
            "msg" => $wttklist['memo'], // 交易时间
        ];
        //判断状态
        if($wttklist['status'] == 2){
            $return_array['returncode'] = '00';
        }elseif ($wttklist['status'] == 4 || $wttklist['status'] == 6){
            $return_array['returncode'] = '11';
        }
    
        //预先写入数据
        $set = array(
            'notifycount' => $wttklist['notifycount'] + 1, // 回调次数+1
            'last_notify_time' => time()
        );
        $member_info = M('Member')->where(['id' => $wttklist['userid']])->find();
        if(!$member_info){
            PaymentLogs( 'Automatic_Notify_Bufa','orderid: ' . $wttklist['orderid'] . ' 未查询到用户信息！ $wttklist: '.json_encode($wttklist));
            return false;
        }
        $sign = createSign($member_info['apikey'], $return_array);
        $return_array["sign"] = $sign;
        
        // 记录初始执行时间
        $beginTime = microtime(TRUE);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $wttklist['notifyurl']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($return_array));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded", "cache-control: no-cache"));
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // $res = http_post_json($wttklist['notifyurl'], $return_array);
        
        // if($order_info['pay_memberid'] == '10002'){
            // 结束并输出执行时间
            $endTime = microtime(TRUE);
            $doTime = floor(($endTime-$beginTime)*1000);
            logApiAddPayment('YunPay回调下游商户信息(补发)', __METHOD__, $wttklist['orderid'], $wttklist["out_trade_no"], $wttklist['notifyurl'], $return_array, substr($res,0,500), $doTime, $httpCode, '2', '2');
        // }
        //日志输出
        PaymentLogs( 'Automatic_Notify_Bufa','orderid: ' . $wttklist['orderid'] . " 通知地址：". $wttklist['notifyurl']."\r\n通知参数: ". json_encode($return_array)."\r\n返回内容: ".$res);
    
        if($res == 'OK'){
            switch ($wttklist['status']){
                case 2:
                    $set['status'] = 3;
                    break;
                case 4:
                    $set['status'] = 5;
                    break;
            }
            $re = $WttklistModel->table($talbe)->where(['orderid' => $orderKey])->setField($set);
            // echo $WttklistModel->table($talbe)->getlastSql();
            return true;
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
            if (!empty($val)) {
                $md5str = $md5str . $key . "=" . $val . "&";
            }
        }
//        echo $md5str . "key=" . $Md5key;
        $sign = strtoupper(md5($md5str . "key=" . $Md5key));
        return $sign;
    }
}