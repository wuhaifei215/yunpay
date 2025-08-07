<?php

namespace Cli\Controller;

use \think\Controller;
use Think\Log;

/**
 * @author mapeijian
 * @date   2018-06-06
 */
class AutodfController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        //自动创建日志文件
        $filePath = './Runtime/Logs/Cli/';
        if(@mkdirs($filePath)) {
            @chmod($filePath, 0777);
            $destination = $filePath.'cli_autosubmitdf.log';
            if(!file_exists($destination)) {
                $handle = @fopen($destination,   'wb ');
                @chmod($destination, 0777);
                @fclose($handle);
            }
            $destination = $filePath.'cli_autoquerydf.log';
            if(!file_exists($destination)) {
                $handle = @fopen($destination,   'wb ');
                @chmod($destination, 0777);
                @fclose($handle);
            }
        }
    }

    public function index()
    {
        
        $time = time();
        echo "[" . date('Y-m-d H:i:s'). "] 自动代付任务触发\n";
        Log::record("自动代付任务触发\n", Log::INFO);
        $config = M('tikuanconfig')->where(['issystem' => 1])->find();
        if(!$config['auto_df_switch']) {
            echo "[" . date('Y-m-d H:i:s'). "] 自动代付已关闭\n";
            Log::record("自动代付已关闭\n", Log::INFO);
            exit;
        }
        $start_time = strtotime($config['auto_df_stime']);
        $end_time = strtotime($config['auto_df_etime'])+59;
        if($time < $start_time || $time > $end_time) {
            echo "[" . date('Y-m-d H:i:s'). "] 不在自动代付时间\n";
            Log::record("不在自动代付时间\n", Log::INFO);
            exit;
        }
        
        // $this->doDf($config);
        $this->doDf2($config);
        Log::record("自动代付任务结束\n", Log::INFO);
        echo "[" . date('Y-m-d H:i:s'). "] 自动代付任务结束\n";
    }
    
     //提交
    private function doDf($config)
    {
        //默认代付通道
        $channel = M('PayForAnother')->where(['status'=>1, 'is_default'=>1])->find();
        if(empty($channel)) {
            echo "[" . date('Y-m-d H:i:s'). "] 默认代付通道不存在\n";
            exit;
        }
        $file = APP_PATH .  'Payment/Controller/' . $channel['code'] . 'Controller.class.php';
        if( !file_exists($file) ) {
            echo "[" . date('Y-m-d H:i:s'). "] 默认代付通道文件不存在\n";
            exit;
        }
        $success = $fail = 0;
        //每次执行10条，尝试提交次数超过5次的不处理，优先处理申请时间较早的，尝试提交次数少的代付申请
        $map['status'] = '0';
        $map['userid'] =['neq',2];
        // $map['auto_submit_try'] = ['lt','5'];
        $map['auto_submit_try'] = '0';
        $map['df_lock'] = '0';
        $map['lock_time'] = '0';
        if($config['auto_df_maxmoney']>0) {
            $map['money'] = ['elt', $config['auto_df_maxmoney']];//单笔最大金额限制
        }
        $lists = M('Wttklist')->where($map)->order('id ASC, auto_submit_try ASC')->limit(0,100)->select();
        Log::record("本次计划任务处理".count($lists).'个订单', Log::INFO);
        echo "本次计划任务处理".count($lists).'个订单';
        foreach($lists as $k => $v) {
            if($v['additional']) {
                $v['additional'] = json_decode($v['additional'],true);
            }
            if($config['auto_df_max_count']>0) {//商户每天自动代付笔数限制
                $map['userid']     = $v['userid'];
                $map['sqdatetime'] = ['between', [date('Y-m-d') . ' 00:00:00', date('Y-m-d') . ' 23:59:59']];
                $map['is_auto'] = 1;
                $count = M('Wttklist')->where($map)->count();
                if($count>=$config['auto_df_max_count']) {
                    M('Wttklist')->where(['id'=>$v['id']])->save(['last_submit_time'=>time(),'auto_submit_try'=>['exp','auto_submit_try+1'],'df_lock'=>0]);
                    $this->logAutoDf($v['id'], 1, 0, '超过商户每天自动代付笔数限制');
                    $fail++;
                    continue;
                }
            }
            if($config['auto_df_max_sum']>0) {//自动代付商户每天最大总金额
                $map['userid']     = $v['userid'];
                $map['sqdatetime'] = ['between', [date('Y-m-d') . ' 00:00:00', date('Y-m-d') . ' 23:59:59']];
                $map['is_auto'] = 1;
                $sum = M('Wttklist')->where($map)->sum('tkmoney');
                if($sum>=$config['auto_df_max_sum']) {
                    M('Wttklist')->where(['id'=>$v['id']])->save(['last_submit_time'=>time(),'auto_submit_try'=>['exp','auto_submit_try+1'],'df_lock'=>0]);
                    $this->logAutoDf($v['id'], 1, 0, '商户每天自动代付最大总金额限制');
                    $fail++;
                    continue;
                }
            }
            $v['money'] = round($v['money'],2);
            //加锁防止重复提交
            $res = M('Wttklist')->where(['id'=>$v['id'], 'df_lock'=>0])->save(['df_lock' => 1, 'lock_time' => time()]);
            if(!$res) {
                Log::record("ID：".$v['id']."加锁失败", Log::INFO);
                continue;
            }
            try {
                $result = R('Payment/' . $channel['code'] . '/PaymentExec', [$v, $channel]);
                if (FALSE === $result) {
                    M('Wttklist')->where(['id' => $v['id']])->save(['last_submit_time' => time(), 'auto_submit_try' => ['exp', 'auto_submit_try+1'], 'df_lock' => 0]);
                    $this->logAutoDf($v['id'], 1, 0, '提交失败');
                    $fail++;
                    Log::record("ID：".$v['id']."服务器请求失败", Log::INFO);
                    continue;
                } else {
                    if (is_array($result)) {
                        $cost = $channel['rate_type'] ? bcmul($v['tkmoney'], $channel['cost_rate'], 4) : $channel['cost_rate'];
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
                        $this->handle($v['id'], $result['status'], $data);
                        $this->logAutoDf($v['id'], 1, $result['status'], $result['msg']);
                        $success++;
                        M('Wttklist')->where(['id' => $v['id']])->save(['is_auto' => 1, 'last_submit_time' => time(), 'auto_submit_try' => ['exp', 'auto_submit_try+1'], 'df_lock' => 0]);
                    } else {
                        Log::record("ID：".$v['id']."返回值异常：".$result, Log::INFO);
                    }
                }
            } catch (\Exception $e) {
                M('Wttklist')->where(['id' => $v['id']])->setField('df_lock', 0);
                Log::record("ID：".$v['id']."捕获异常：".$e->getMessage(), Log::INFO);
            }
        }
        Log::record("[" . date('Y-m-d H:i:s'). "] 成功提交：".$success."，失败：".$fail, Log::INFO);
        echo "成功提交：".$success."，失败：".$fail;
        exit;
    }

    //提交
    private function doDf2($config)
    {
        $success = $fail = 0;
        //每次执行100条，尝试提交次数超过5次的不处理，优先处理申请时间较早的，尝试提交次数少的代付申请
        $map['status'] = '0';
        $map['userid'] =['neq',2];
        // $map['auto_submit_try'] = ['lt','5'];
        $map['auto_submit_try'] = '0';
        $map['df_lock'] = '0';
        $map['lock_time'] = '0';
        if($config['auto_df_maxmoney']>0) {
            $map['money'] = ['elt', $config['auto_df_maxmoney']];//单笔最大金额限制
        }
        $lists = M('Wttklist')->where($map)->order('id ASC, auto_submit_try ASC')->limit(0,100)->select();
        log_place_order('autoDF', "===================================================", '');    //日志
        // Log::record("本次计划任务处理".count($lists).'个订单'."\n", Log::INFO);
        log_place_order('autoDF', "本次计划任务处理", count($lists).'个订单');    //日志
        echo "本次计划任务处理".count($lists).'个订单'."\n";
        foreach($lists as $k => $v) {
            log_place_order('autoDF', "ID", $v['id'].',订单号：' . $v['orderid']);    //日志
            //确认该订单为未处理订单
            $res = M('Wttklist')->where(['id'=>$v['id'], 'df_lock'=>'0', 'lock_time'=>'0'])->find();
            if(!$res){
                log_place_order('autoDF', "ID", $v['id']. ',订单号：' . $v['orderid'] . ',订单已加锁');    //日志
                // Log::record("ID：".$v['id']."订单已加锁，已处理", Log::INFO);
                continue;
            }
            
            //代付类型
            if($v['bankname']==='gcash'){
                $channel_where['paytype'] = 1;
            }elseif($v['bankname']==='Maya'){
                $channel_where['paytype'] = 2;
            }elseif($v['bankname']==='PMP'){
                $channel_where['paytype'] = 3;
            }else{
                $fail++;
                continue;
            }
            
            //默认代付通道
            $channel_where['status'] = 1;
            $channel_where['is_default']= 1;
            
            $channel = M('PayForAnother')->where($channel_where)->find();
            if(empty($channel)) {
                echo "[" . date('Y-m-d H:i:s'). "] 用户 " . $v['userid'] . " : 代付通道不存在\n";
                $this->logAutoDf($v['id'], 1, 0, '代付通道不存在');
                
                $data = ['memo'=> '代付通道不存在'];
                // $this->handle($v['id'], 3, $data);
                $fail++;
                continue;
            }
            $file = APP_PATH .  'Payment/Controller/' . $channel['code'] . 'Controller.class.php';
            if( !file_exists($file) ) {
                echo "[" . date('Y-m-d H:i:s'). "] 用户 " . $v['userid'] . " : 代付通道文件不存在\n";
                $this->logAutoDf($v['id'], 1, 0, '代付通道文件不存在');
                $data = ['memo'=> '代付通道文件不存在'];
                // $this->handle($v['id'], 3, $data);
                $fail++;
                continue;
            }
            
            
            // echo "通道". $channel['code'] ."\n";
            
            if($v['additional']) {
                $v['additional'] = json_decode($v['additional'],true);
            }
            if($config['auto_df_max_count']>0) {//商户每天自动代付笔数限制
                $map['userid']     = $v['userid'];
                $map['sqdatetime'] = ['between', [date('Y-m-d') . ' 00:00:00', date('Y-m-d') . ' 23:59:59']];
                $map['is_auto'] = 1;
                $count = M('Wttklist')->where($map)->count();
                if($count>=$config['auto_df_max_count']) {
                    M('Wttklist')->where(['id'=>$v['id']])->save(['last_submit_time'=>time(),'auto_submit_try'=>['exp','auto_submit_try+1'],'df_lock'=>0]);
                    $this->logAutoDf($v['id'], 1, 0, '超过商户每天自动代付笔数限制');
                    $fail++;
                    continue;
                }
            }
            if($config['auto_df_max_sum']>0) {//自动代付商户每天最大总金额
                $map['userid']     = $v['userid'];
                $map['sqdatetime'] = ['between', [date('Y-m-d') . ' 00:00:00', date('Y-m-d') . ' 23:59:59']];
                $map['is_auto'] = 1;
                $sum = M('Wttklist')->where($map)->sum('tkmoney');
                if($sum>=$config['auto_df_max_sum']) {
                    M('Wttklist')->where(['id'=>$v['id']])->save(['last_submit_time'=>time(),'auto_submit_try'=>['exp','auto_submit_try+1'],'df_lock'=>0]);
                    $this->logAutoDf($v['id'], 1, 0, '商户每天自动代付最大总金额限制');
                    $fail++;
                    continue;
                }
            }
            $v['money'] = round($v['money'],2);
            //加锁防止重复提交
            $res = M('Wttklist')->where(['id'=>$v['id'], 'df_lock'=>0])->save(['df_lock' => 1, 'lock_time' => time()]);
            if(!$res) {
                log_place_order('autoDF', "ID", $v['id'].',加锁失败');    //日志
                // Log::record("ID：".$v['id']."加锁失败", Log::INFO);
                continue;
            }
            try {
                $result = R('Payment/' . $channel['code'] . '/PaymentExec', [$v, $channel]);
                // var_dump($result);
                if (FALSE === $result) {
                    M('Wttklist')->where(['id' => $v['id']])->save(['last_submit_time' => time(), 'auto_submit_try' => ['exp', 'auto_submit_try+1'], 'df_lock' => 0]);
                    $this->logAutoDf($v['id'], 1, 0, '提交失败');
                    $fail++;
                    log_place_order('autoDF', "ID", $v['id'].',服务器请求失败');    //日志
                    // Log::record("ID：".$v['id']."服务器请求失败", Log::INFO);
                    continue;
                } else {
                    if (is_array($result)) {
                        $cost = $channel['rate_type'] ? bcmul($v['tkmoney'], $channel['cost_rate'], 4) : $channel['cost_rate'];
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
                        $this->handle($v['id'], $result['status'], $data);
                        $this->logAutoDf($v['id'], 1, $result['status'], $result['msg']);
                        $success++;
                        M('Wttklist')->where(['id' => $v['id']])->save(['is_auto' => 1, 'last_submit_time' => time(), 'auto_submit_try' => ['exp', 'auto_submit_try+1'], 'df_lock' => 0]);
                    } else {
                        log_place_order('autoDF', "ID", $v['id'].",返回值异常：".$result);    //日志
                        // Log::record("ID：".$v['id']."返回值异常：".$result, Log::INFO);
                    }
                }
            } catch (\Exception $e) {
                M('Wttklist')->where(['id' => $v['id']])->setField('df_lock', 0);
                log_place_order('autoDF', "ID", $v['id'].",捕获异常：".$e->getMessage());    //日志
                // Log::record("ID：".$v['id']."捕获异常：".$e->getMessage(), Log::INFO);
            }
        }
        log_place_order('autoDF', "成功提交", $success."，失败：".$fail);    //日志
        // Log::record("[" . date('Y-m-d H:i:s'). "] 成功提交：".$success."，失败：".$fail."\n", Log::INFO);
        echo "成功提交：".$success."，失败：".$fail."\n\n";
        exit;
    }

    public function dfQuery()
    {
        echo "[" . date('Y-m-d H:i:s'). "] 自动代付查询任务触发\n";
        Log::record("自动代付任务触发\n", Log::INFO);
        $time = $_SERVER['REQUEST_TIME'];
        $this->doQuery();
        Log::record("自动代付任务结束\n", Log::INFO);
        echo "[" . date('Y-m-d H:i:s'). "] 自动代付查询任务结束\n";

    }

    //查询
    private function doQuery() {

        $lists = M('Wttklist')->where(['status' => 1])->order('id asc, auto_query_num asc')->limit(0,10)->select();
        Log::record("本次计划任务查询\n".count($lists).'个订单', Log::INFO);
        echo "本次计划任务查询".count($lists).'个订单';
        $success = 0;
        foreach($lists as $k => $v){
            $file = APP_PATH . 'Payment/Controller/' . $v['code'] . 'Controller.class.php';
            if( file_exists($file) ) {
                $pfa_list = M('PayForAnother')->where(['id'=>$v['df_id']])->find();
                if(empty($pfa_list)) {
                    continue;
                }
                $result = R('Payment/'.$v['code'].'/PaymentQuery', [$v, $pfa_list]);
                if(FALSE === $result) {
                    $this->logAutoDf($v['id'], 2, 0, '查询失败');
                } else {
                    if(is_array($result)){
                        $success++;
                        $data = [
                            'memo'      => $result['msg'],
                            'df_id'     => $pfa_list['id'],
                            'code'      => $pfa_list['code'],
                            'df_name'   => $pfa_list['title'],
                        ];
                        $this->handle($v['id'], $result['status'], $data);
                        $this->logAutoDf($v['id'], 2, $result['status'], $result['msg']);
                    }
                }
                M('Wttklist')->where(['id'=>$v['id']])->setInc('auto_query_num');
            } else {
                $this->logAutoDf($v['id'], 2, 0, '代付通道文件不存在');
            }
        }
        Log::record("[" . date('Y-m-d H:i:s'). "] 成功查询：".$success."个订单\n", Log::INFO);
        echo "成功查询：".$success."个订单";
        exit;
    }


    protected function handle($id, $status = 1, $return){
	    //处理成功返回的数据
        $memo = M('Wttklist')->where(['id' => $id])->getField('memo');
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
				 $sta = M('Wttklist')->where(['id' => $id])->getField('status');
				 $data['status'] = $sta;
				 $data['memo']  = '状态不改变！ - '. $return['memo']. date('Y-m-d H:i:s').'<br>'.$memo;
		}
        if(in_array($status, [0,1,2])){
        	$data = array_merge($data, $return);
            $where = ['id'=>$id, 'status'=>['in', '0,1']];
        	M('Wttklist')->where($where)->save($data);
        }
        //异步通知下游
        Automatic_Notify($id);
	}
	
    // protected function handle($id, $status=1, $return){
    //     //处理成功返回的数据
    //     $memo = M('Wttklist')->where(['id' => $id])->getField('memo');
    //     $data = array();
    //     if($status == 1){
    //         $data['status'] = 1;
    //         if(!$memo) {
    //             $data['memo']  = '申请成功！ - '.date('Y-m-d H:i:s').'<br>';
    //         } else {
    //             $data['memo']  =  $memo;
    //         }
    //     }else if ($status == 2) {
    //         $data['status'] = 2;
    //         $data['cldatetime'] = date('Y-m-d H:i:s', time());
    //         $data['memo']  = '代付成功！ - '.date('Y-m-d H:i:s').'<br>'.$memo;
    //     }else if($status == 3){
    //         $data['status'] = 4;
    //         $data['memo'] = isset($return['memo'])?$return['memo']:'代付失败！';
    //         $data['memo']  = $data['memo'].' - '.date('Y-m-d H:i:s').'<br>'.$memo;
    //     }
    //     if(in_array($status, [1,2,3])){
    //         $data = array_merge($data, $return);
    //         $where = ['id'=>$id, 'status'=>['in', '0,1,4']];
    //         M('Wttklist')->where($where)->save($data);
    //     }
    // }
    //记录日志
    private function logAutoDf($df_id, $type, $status, $msg) {

        $log['status'] = $status;
        $log['msg'] = $msg;
        $log['df_id'] = $df_id;
        $log['type'] = $type;
        $log['ctime'] = time();
        $res = M('auto_df_log')->add($log);
        return $res;
    }

    public function query() {
        $id = I('id', 0, 'intval');
        if($id) {
            $data = M('Wttklist')->where(['id'=> $id])->find();
            $chance = M('PayForAnother')->where(['id'=>$data['df_id']])->find();
            $result = R('Payment/'.$data['code'].'/PaymentQuery', [$data, $chance]);
            var_dump($result);die;
        }
    }

}