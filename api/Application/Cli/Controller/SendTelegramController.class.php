<?php

namespace Cli\Controller;

use \think\Controller;
use Think\Log;

/**
 * @author mapeijian
 * @date   2018-06-06
 */
class SendTelegramController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        //自动创建日志文件
        $filePath = './Runtime/Logs/Cli/';
        if(@mkdirs($filePath, 777)) {
            $destination = $filePath.'cli_sendTelegram.log';
            if(!file_exists($destination)) {
                $handle = @fopen($destination,   'wb ');
                @fclose($handle);
            }
        }
    }
    public function index(){
        // echo "[" . date('Y-m-d H:i:s'). "] 自动代付任务触发\n";
        $redis = $this->redis_connect();
        //取出第一个
        $orderKey = $redis->lPop('sendTelegramList');
        // $orderKey = '2024100417030650501012819';
        $orderid = $redis->get($orderKey);
        if(!empty($orderid)){
            $OrderModel = D('Order');
            $date = date('Ymd',strtotime(substr($orderid, 0, 8)));  //获取订单日期
            $tablename = $OrderModel->getRealTableName($date);
            $orderList = $OrderModel->table($tablename)->where(['pay_orderid' => $orderid])->find();
            
            if(!$orderList['pay_memberid'] || !$orderList['pay_orderid'])return;
            $TelegramApi_where = [
                'member_id'=>$orderList['pay_memberid'],
                'pay_orderid' => $orderList['pay_orderid'],
                'create_time' => ['between',[time() - 3600 * 72, time()]],
            ];
            $TelegramApi_re = M('TelegramApiOrder')->where($TelegramApi_where)->order('id DESC')->limit(1)->select();
            // log_place_order('Telegram_notifyurl', $orderList['pay_orderid'] . "----sql", M('TelegramApiOrder')->getLastSql());    //日志
            if($TelegramApi_re){
                log_place_order('Telegram_notifyurl', $orderList['pay_orderid'] . "----order", json_encode($orderList, JSON_UNESCAPED_UNICODE));    //日志
                log_place_order('Telegram_notifyurl', $orderList['pay_orderid'] . "----data", json_encode($TelegramApi_re, JSON_UNESCAPED_UNICODE));    //日志
                $order_info['status'] = 1;
                $order_info['info'] = $orderList;
                $send_re = R('Telegram/Api/doDS', [$order_info, $TelegramApi_re[0]['chat_id'], $TelegramApi_re[0]['message'], $TelegramApi_re[0]['message_id']]);
                log_place_order('Telegram_notifyurl', $orderList['pay_orderid'] . "----fasong", $send_re);    //日志
            }
            return;
        }else{
            // echo "[" . date('Y-m-d H:i:s'). "] 不需要处理\n";
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