<?php

namespace Cli\Controller;

use \think\Controller;
use Think\Log;

/**
 * 机器人代付审核后提交程序
 * @author mapeijian
 * @date   2018-06-06
 */
class AutodfTelController extends Controller
{
    protected $redis;
    public function __construct()
    {
        parent::__construct();
        //自动创建日志文件
        $filePath = './Runtime/Logs/Cli/';
        if(@mkdirs($filePath)) {
            @chmod($filePath, 0777);
            $destination = $filePath.'cli_autoTELsubmitdf.log';
            if(!file_exists($destination)) {
                $handle = @fopen($destination,   'wb ');
                @chmod($destination, 0777);
                @fclose($handle);
            }
        }
        $this->redis = $this->redis_connect();
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
        
        $where['status'] = 0;
        $lists = M('TelegramApiDforderAutodf')->where($where)->order('id asc')->limit(0,10)->select();
        
        echo "[" . date('Y-m-d H:i:s'). "] 要处理 " . count($lists)  . " 条订单\n";
        if($lists){
            $this->doDf2($lists , $config);
        }
        echo "[" . date('Y-m-d H:i:s'). "] 自动重新提交任务结束\n";
    }
    //提交
    protected function doDf2($lists , $config){
        foreach($lists as $k => $v) {
            if($this->redis->get('autodfTEL' . $v['callback'] . $v['orderid'])){
                return;
            }
            $this->redis->set('autodfTEL' . $v['callback'] . $v['orderid'],'1',120);
            
            log_place_order('autodfTEL', '订单号：' ,$v['orderid']);    //日志
            //把记录设置为已处理状态
            M('TelegramApiDforderAutodf')->where(['id'=> $v['id']])->setField('status', 1);
            
            $Wttklist = D('Wttklist');
            $date = date('Ymd', strtotime(substr($v['orderid'], 1, 8)));  //获取订单日期
            $table = $Wttklist->getRealTableName($date);
            //确认该订单为未处理订单
            $wttkData = $Wttklist->table($table)->where(['orderid'=>$v['orderid'], 'df_lock'=>0, 'lock_time'=>0])->find();
            if(!$wttkData){
                log_place_order('autodfTEL', '订单已加锁' , $v['orderid']);    //日志
                continue;
            }
            if($wttkData['status'] != '0'){
                log_place_order('autodfTEL', '订单状态异常' , $wttkData['status']);    //日志
                continue;
            }
            
            $id = $wttkData['id'];
            //加锁防止重复提交
            $lock_res = $Wttklist->table($table)->where(['id'=>$id, 'df_lock'=>0])->save(['df_lock' => 1, 'lock_time' => time()]);
            if(!$lock_res) {
            }
            if($v['callback'] == 1){
                try {
                    $channel = M('pay_for_another')->where(['id' => $wttkData['df_id'],'status' => 1])->find();
                    if(!$channel || empty($channel)){
                        continue;
                    }
                    $wttkData['money'] = round($wttkData['money'],2);
                    $result = R('Payment/' . $channel['code'] . '/PaymentExec', [$wttkData, $channel]);
                    if (FALSE === $result) {
                        $Wttklist->table($table)->where(['id' => $id])->save(['last_submit_time' => time(), 'auto_submit_try' => ['exp', 'auto_submit_try+1'], 'df_lock' => 0]);
                    } else {
                        if (is_array($result)) {
                            $data = [
                                'memo' => $result['msg'],
                            ];
                            $this->changeStatus($id, $result['status'], $data, $table);
                            $Wttklist->table($table)->where(['id' => $id])->save(['is_auto' => 1, 'last_submit_time' => time(), 'auto_submit_try' => ['exp', 'auto_submit_try+1'], 'df_lock' => 0]);
                        }
                    }
                } catch (\Exception $e) {
                    $Wttklist->table($table)->where(['id' => $id])->setField('df_lock', 0);
                    log_place_order('autodfTEL', '订单号：' . $v['orderid'], "捕获异常：" . $e->getMessage());    //日志
                }
            }elseif($v['callback'] == 2){
                $data = [
                    'memo' => '审核驳回',
                ];
                $this->changeStatus($id, 3, $data, $table);
            }
        }
    }

    protected function changeStatus($id, $status = 1, $return,$table='Wttklist'){
        if($this->redis->get('changestatus' . $status . $id)){
            return;
        }
        $this->redis->set('changestatus' . $status . $id,'1',120);
	    //处理成功返回的数据
        $Wttklist = D('Wttklist');
        $withdraw = $Wttklist->table($table)->where(['id' => $id])->find();
        $memo = $withdraw['memo'];
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
                    // log_place_order( 'autodfTEL_error', $withdraw['orderid'],  json_encode($withdraw, JSON_UNESCAPED_UNICODE));    //日志
			     try {
			        $contentstr = '申请失败';
                    M()->startTrans();
    				 //各种失败未返回 并退回金额
                    $Member     = M('Member');
                    $memberInfo = $Member->where(['id' => $withdraw['userid']])->lock(true)->find();
                    if(getPaytypeCurrency($withdraw['paytype']) ==='PHP'){        //菲律宾余额
                        $res1 = $Member->where(['id' => $withdraw['userid']])->save(['balance_php' => array('exp', "balance_php+{$withdraw['tkmoney']}")]);
                        $ymoney = $memberInfo['balance_php'];
                    }
                    if(getPaytypeCurrency($withdraw['paytype']) ==='INR'){        //菲律宾余额
                        $res1 = $Member->where(['id' => $withdraw['userid']])->save(['balance_inr' => array('exp', "balance_inr+{$withdraw['tkmoney']}")]);
                        $ymoney = $memberInfo['balance_inr'];
                    }
                    //2,记录流水订单号
                    $arrayField = array(
                        "userid"     => $withdraw['userid'],
                        "ymoney"     => $ymoney,
                        "money"      => $withdraw['tkmoney'],
                        "gmoney"     => $ymoney + $withdraw['tkmoney'],
                        "datetime"   => date("Y-m-d H:i:s"),
                        "tongdao"    => 0,
                        "transid"    => $withdraw['orderid'],
                        "orderid"    => $withdraw['out_trade_no'],
                        "paytype"    => $withdraw['paytype'],
                        "lx"         => 18,
                        'contentstr' => $contentstr.': '.$withdraw['orderid'],
                    );
                    $Moneychange = D("Moneychange");
                    $tablename = $Moneychange -> getRealTableName($arrayField['datetime']);
                    $res2 = $Moneychange->table($tablename)->add($arrayField);
                    // log_place_order( 'autodfTEL_error', $withdraw['orderid'],   $Moneychange->table($tablename)->getLastSql());    //日志
                    $sxf_re = true;
                    //代付驳回退回手续费
                    if ($withdraw['df_charge_type'] && $withdraw['sxfmoney']>0) {
                        if(getPaytypeCurrency($withdraw['paytype']) ==='PHP'){        //菲律宾余额
                            $res3 = $Member->where(['id' => $withdraw['userid']])->save(['balance_php' => array('exp', "balance_php+{$withdraw['sxfmoney']}")]);
                        }
                        if(getPaytypeCurrency($withdraw['paytype']) ==='INR'){        //菲律宾余额
                            $res3 = $Member->where(['id' => $withdraw['userid']])->save(['balance_inr' => array('exp', "balance_inr+{$withdraw['sxfmoney']}")]);
                        }
                        if (!$res3) {
                            $sxf_re = false;
                        }
                        $chargeField = array(
                            "userid"     => $withdraw['userid'],
                            "ymoney"     => $ymoney + $withdraw['tkmoney'],
                            "money"      => $withdraw['sxfmoney'],
                            "gmoney"     => $ymoney + $withdraw['tkmoney'] + $withdraw['sxfmoney'],
                            "datetime"   => date("Y-m-d H:i:s"),
                            "tongdao"    => 0,
                            "transid"    => $withdraw['orderid'],
                            "orderid"    => $withdraw['out_trade_no'],
                            "paytype"    => $withdraw['paytype'],
                            "lx"         => 19,
                            'contentstr' => $contentstr.' 结算退回手续费',
                        );
                        $res4 = $Moneychange->table($tablename)->add($chargeField);
                        // $res = M('Moneychange')->add($chargeField);
                        if (!$res4) {
                            $sxf_re = false;
                        }
                    }
    				$message = isset($return['memo'])?$return['memo']:'代付失败！';
    				$message = $message .' - '.date('Y-m-d H:i:s').'<br>'.$memo;
    				
    			    $data['status'] = 4;
    			    $data['cldatetime'] = date('Y-m-d H:i:s', time());
    			    $data['memo']  = $message;
    			 //   log_place_order( 'autodfTEL_error', $withdraw['orderid'], '$res1:' . $res1);    //日志
    			 //   log_place_order( 'autodfTEL_error', $withdraw['orderid'], '$res2:' . $res2);    //日志
    			 //   log_place_order( 'autodfTEL_error', $withdraw['orderid'], '$sxf_re:' . $sxf_re);    //日志
    			    
                    if($res1 && $res2 && $sxf_re === true){
                    // log_place_order( 'autodfTEL_error', $withdraw['orderid'],   11111);    //日志
            		    M()->commit();
            		}else{
                        log_place_order( 'autodfTEL_error', $withdraw['orderid'],   '数据回滚了');    //日志
            		    M()->rollback();
            		}
			    } catch (\Exception $e) {
                    log_place_order( 'autodfTEL_error', $withdraw['orderid'] . '$e',  $e);    //日志
                }
			    break;
			default:
				 //订单状态不改变
				 $sta = $Wttklist->table($table)->where(['id' => $id])->getField('status');
				 $data['status'] = $sta;
				 $data['memo']  = '状态不改变！ - '. $return['memo']. date('Y-m-d H:i:s').'<br>'.$memo;
				 break;
		}
        $where = ['id'=>$id, 'status'=>['in', '0,1']];
        $ad = $Wttklist->table($table)->where($where)->save($data);
        if($status == 2 || $status == 3){
            $withdraw['status'] = $data['status'];
            $this->redis->set($withdraw['orderid'],json_encode($withdraw),3600 * 2);
            $this->redis->rPush('notifyList_DF', $withdraw['orderid']);
            Automatic_Notify($withdraw['orderid']);
        }
        return;
	}

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