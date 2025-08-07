<?php

namespace Cli\Controller;

use \think\Controller;
use Think\Log;

/**
 * @author mapeijian
 * @date   2018-06-06
 */
class PaymentExecController extends Controller
{
    private $code = '';
    public function __construct()
    {
        parent::__construct();
        
        $matches = [];
        preg_match('/([\da-zA-Z\_]+)Controller$/', __CLASS__, $matches);
        $this->code = $matches[1];
    }
    public function index(){
        echo "[" . date('Y-m-d H:i:s'). "] 自动代付任务触发\r\n";
        for ($i=1; $i<=10; $i++)
        {
            $this->do_index();
        }
        echo "[" . date('Y-m-d H:i:s'). "] 自动代付任务结束\r\n\n";
    }
    public function do_index(){
        $time = time();
        $redis = redis_connect();
        //取出第一个
        $orderKey = $redis->lPop('PaymentExec_List');
        // $orderKey = 'P2025071617342058460527760';
        if($orderKey === false ||  $orderKey ==''){
            // echo "[" . date('Y-m-d H:i:s'). "] 没有需要处理\n";
            return false;
        }
        echo $time . "orderId_" . $orderKey . "\n";
        
        $WttklistModel = D('Wttklist');
        $date = date('Ymd', strtotime(substr($orderKey, 1, 8)));  //获取订单日期
        $talbe = $WttklistModel->getRealTableName($date);
        
        $wttklist = $WttklistModel->table($talbe)->where(['orderid' => $orderKey,'status'=>'0'])->find();
        if(!$wttklist || $wttklist=='' || empty($wttklist)){
            echo "[" . date('Y-m-d H:i:s'). "] 不需要处理\n";
            return false;
        }
        try {
            //加锁防止重复提交
            $res = $WttklistModel->table($talbe)->where(['id' => $wttklist['id'], 'df_lock'=>0])->save(['df_lock' => 1, 'lock_time' => time()]);
            if(!$res) {
                //加锁失败
                log_place_order($this->code, $wttklist['orderid'], '加锁失败');    //日志
                return false;
            }
            //默认代付通道
            $channel_where['status'] = 1;
            $channel_where['id']= $wttklist['df_id'];
            
            $channel = M('PayForAnother')->where($channel_where)->find();
            if(empty($channel) || $channel == '') {
                log_place_order($this->code, $wttklist['orderid'], '绑定的代付通道不存在');    //日志
                return false;
            }
            
            $wttklist['money'] = round($wttklist['money'],2);
            $result = R('Payment/' . $channel['code'] . '/PaymentExec', [$wttklist, $channel]);
            if (FALSE === $result) {
                $WttklistModel->table($talbe)->where(['id' => $wttklist['id']])->save(['last_submit_time' => time(), 'auto_submit_try' => ['exp', 'auto_submit_try+1'], 'df_lock' => 0]);
                log_place_order($this->code, $wttklist['orderid'], '提交失败');    //日志
                return false;
            } else {
                if (is_array($result)) {
                    $cost = $channel['rate_type'] ? bcmul($wttklist['tkmoney'], $channel['cost_rate'], 4) : $channel['cost_rate'];
                    $data = [
                        'memo' => $result['msg'],
                        'df_id' => $channel['id'],
                        'code' => $channel['code'],
                        'df_name' => $channel['title'],
                        'channel_mch_id' => $channel['mch_id'],
                        'cost_rate' => $channel['cost_rate'],
                        'cost' => $cost,
                        'rate_type' => $channel['rate_type'],
                    ];
                    $re = R('Payment/Payment/changeStatus', [$wttklist['id'], $result['status'], $data, $talbe]);
                    // var_dump($re);
                    // echo "<br>";

                    $WttklistModel->table($talbe)->where(['id' => $wttklist['id']])->save(['is_auto' => 1, 'last_submit_time' => time(), 'auto_submit_try' => ['exp', 'auto_submit_try+1'], 'df_lock' => 0]);
                } else {
                    log_place_order($this->code, $wttklist['orderid'], '返回值异常' . json_encode($result));    //日志
                    return false;
                }
            }
        } catch (\Exception $e) {
            $WttklistModel->table($talbe)->where(['id' => $wttklist['id']])->setField('df_lock', 0);
            log_place_order($this->code, $wttklist['orderid'], '捕获异常' . $e->getMessage());    //日志
            return false;
        }
    }
}