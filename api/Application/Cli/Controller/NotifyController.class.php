<?php

namespace Cli\Controller;

use \think\Controller;
use Think\Log;

/**
 * @author mapeijian
 * @date   2018-06-06
 */
class NotifyController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        //自动创建日志文件
        $filePath = './Runtime/Logs/Cli/';
        if(@mkdirs($filePath, 777)) {
            $destination = $filePath.'cli_notify.log';
            if(!file_exists($destination)) {
                $handle = @fopen($destination,   'wb ');
                @fclose($handle);
            }
        }
    }
    public function index(){
        for ($i=1; $i<=5; $i++)
        {
            $this->do_index();
        }
    }
    public function do_index(){
        $time = time();
        // echo "[" . date('Y-m-d H:i:s'). "] 自动代付任务触发\n";
        $redis = $this->redis_connect();
        //取出第一个
        $orderKey = $redis->lPop('notifyList');
        // $orderKey = '2024102518170448535232369';
        if($orderKey ===false){
            // echo "[" . date('Y-m-d H:i:s'). "] 没有需要处理\n";
            return;
        }
        echo $time . " orderId_" . $orderKey . "\n";
        $list_msg = $redis->get($orderKey);
        
        $m_Order = D("Order");
        $date = date('Ymd', strtotime(substr($orderKey, 0, 8)));  //获取订单日期
        $talbe = $m_Order->getRealTableName($date);
        if(!empty($list_msg)){
            $order_info =  json_decode($list_msg,true);
        }else{
            $order_info = $m_Order->table($talbe)->where(['pay_orderid' => $orderKey])->find();
        }
        if(!$order_info || empty($order_info)){
            echo $time . " [" . date('Y-m-d H:i:s'). "] 不需要处理\n";
        }
        $return_array = [ // 返回字段
            "memberid" => $order_info["pay_memberid"], // 商户ID
            "orderid" => $order_info['out_trade_id'], // 商户订单号
            'transaction_id' => $order_info["pay_orderid"], //平台订单号
            "amount" => $order_info["pay_amount"], // 交易金额
            "datetime" => date("YmdHis"), // 交易时间
            "returncode" => "00", // 交易状态
            "msg" => "支付成功"
        ];
        $userid = $order_info["pay_memberid"] - 10000;
        $member_redis_info = $redis->get('userinfo_' . $userid);
        $member_info = json_decode($member_redis_info,true);
        if (!isset($member_redis_info) || !$member_info['apikey']) {
            $member_info = M('Member')->where(['id' => $userid])->find();
            $redis->set('userinfo_' . $userid, json_encode($member_info),3600);
        }
        // echo $member_info['apikey'];
        $sign = $this->createSign($member_info['apikey'], $return_array);
        $return_array["sign"] = $sign;
        // $return_array["attach"] = $order_info["attach"];
        $notifystr = "";
        foreach ($return_array as $key => $val) {
            $notifystr = $notifystr . $key . "=" . $val . "&";
        }
        $notifystr = rtrim($notifystr, '&');
        
        // 记录初始执行时间
        $beginTime = microtime(TRUE);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $order_info["pay_notifyurl"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $notifystr);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded", "cache-control: no-cache"));
        $contents = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // if($order_info['pay_memberid'] == '10002'){
            // 结束并输出执行时间
            $endTime = microtime(TRUE);
            $doTime = floor(($endTime-$beginTime)*1000);
            logApiAddReceipt('YunPay回调下游商户信息', __METHOD__, $order_info['pay_orderid'], $order_info['out_trade_id'], $order_info["pay_notifyurl"], $notifystr, substr($contents,0,500), $doTime, $httpCode, '2', '2');
        // }
        //记录向下游异步通知日志
        log_server_notify($order_info["pay_orderid"], $order_info["pay_notifyurl"], $notifystr, $httpCode, $contents);

        if ($contents == 'OK') {
            //更新交易状态
            $order_where = [
                'id' => $order_info['id'],
                'pay_orderid' => $order_info["pay_orderid"],
            ];
            $order_result = $m_Order->table($talbe)->where($order_where)->setField("pay_status", 2);
            
            // log_server_notify($order_info["pay_orderid"], $order_info["pay_notifyurl"], 'sql', $m_Order->getLastSql(), '');
            
            // 订单信息存入缓存
            $order_info['pay_status'] = 2;
            $redis->set($order_info['pay_orderid'],json_encode($order_info),3600 * 2);
        } else {
//                    $this->jiankong($order_info['pay_orderid']);
        }
        // echo "[" . date('Y-m-d H:i:s'). "] 自动代付任务结束\n\n";
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
    
    public function redis_connect(){
        //创建一个redis对象
        $redis = new \Redis();
        //连接 Redis 服务
        $redis->connect(C('REDIS_HOST'), C('REDIS_PORT'));
        //密码验证
        $redis->auth(C('REDIS_PWD'));
        //设置key-value
        // $redis->set('downloadList','111');
        // 设置键的过期时间为3600秒
        // $redis->expire('downloadList',3600);
        //获取value
        // $list = $redis->get('downloadList');
        
        // 在列表头部插入元素
        // $redis->lPush('list', 'value1');
        // 从列表左侧弹出元素
        // $redis->lPop('list');
        // 在列表尾部插入元素
        // $redis->rPush('list', 'value2');
        // 获取列表的长度
        // $length = $redis->lLen('list');
        // 获取列表的所有元素
        // $list = $redis->lRange('list', 0, -1);
        // 使用Redis的LRANGE命令获取分页数据
        // $list = $redis->lRange('list', $start, $end);
        // 使用SORT命令进行排序，这里以按value字典序升序为例
        // BY参数指定了使用哪个key的value作为sort的依据
        // GET参数指定了使用哪个key的value的哪部分作为排序的值
        // $redis->sort($key, ['by' => $key.*.*, 'get' => '#']));
        return $redis;
    }

}