<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-08-22
 * Time: 14:34
 */
namespace Payment\Controller;

/**
 * 用户中心首页控制器
 * Class IndexController
 * @package User\Controller
 */
use Think\Controller;

class PaymentController extends Controller
{
    protected $verify_data_ = [
        'code'=>'请选择代付方式！',
        'id'=>'请选择代付订单！',
        'opt' => '操作方式错误！',
    ];

    public function __construct(){
        parent::__construct();
        if(PHP_SAPI !== 'cli' && isset($_REQUEST['opt']) && $_REQUEST['opt'] == 'exec' && !session('admin_submit_df')) {
            showError('没有权限');
        }
        if(PHP_SAPI !== 'cli' && ACTION_NAME == 'PaymentExec' && !session('admin_submit_df')) {
            showError('没有权限');
        }
        
    }

    protected function findPaymentType($code='default'){
        $where['status'] = 1;
        if($code == 'default'){
            $where['is_default'] = 1;
        }else{
            $where['id'] = $code;
        }
        $list = M('PayForAnother')->where($where)->find();
        $list || showError('支付方式错误');


// 		//---------------------------子账号风控start------------------------------------
//         $channel_account_list = M('channel_account')->where(['channel_id' => $syschannel['id'], 'status' => '1'])->select();
//         $account_ids = M('UserChannelAccount')->where(['userid' => intval($this->channel['userid']), 'status' => 1])->getField('account_ids');
//         if ($account_ids) {
//             $account_ids = explode(',', $account_ids);
//             foreach ($channel_account_list as $k => $v) {
//                 //如果不在指定的子账号，将其删除
//                 if (!in_array($v['id'], $account_ids)) {
//                     unset($channel_account_list[$k]);
//                 }
//             }
//         }

//         $l_ChannelAccountRiskcontrol = new \Pay\Logic\ChannelAccountRiskcontrolLogic($pay_amount);
//         $channel_account_item = [];
//         $error_msg = '已下线';
//         foreach ($channel_account_list as $k => $v) {
//             if ($v['offline_status'] && $v['control_status']) {
//                 //判断是自定义还是继承渠道的风控
//                 $temp_info = $v['is_defined'] ? $v : $syschannel;
//                 $temp_info['account_id'] = $v['id']; //用于子账号风控类继承渠道风控机制时修改数据的id

//                           //修改相同商户 共享金额风控
//               if ($v['is_defined']) {
//                   $share_money=M('channel_account')->where(array('mch_id'=>$v['mch_id']))->sum('paying_money');
//                   if ($share_money) {
//                      $temp_info['paying_money']               =$share_money ;
//                      $temp_info['share_mch_id']               =$v['mch_id'] ;
//                   }       
//               }

//                 //子账号风控
//                 $l_ChannelAccountRiskcontrol->setConfigInfo($temp_info);
//                 $error_msg = $l_ChannelAccountRiskcontrol->monitoringData();
//                 if ($error_msg === true) {
//                     $channel_account_item[] = $v;
//                 }
//             } else if ($v['control_status'] == 0) {
//                 $channel_account_item[] = $v;
//             }
//         }
//         if (empty($channel_account_item)) {
//             showError('账户:' . $error_msg);
//         }

//         //-------------------------子账号风控end-----------------------------------------

//         //-------------------------计算权重start-----------------------------------------
//         if (count($channel_account_item) == 1) {
//             $channel_account = current($channel_account_item);
//         } else {
//             //$channel_account = getWeight($channel_account_item);
//             //
//             $control_order=M('channel')->field('control_order')->where(['id' =>$this->channel['channel']])->find();     
//             if ($control_order['control_order']==2) {
//                 //排序最小的钱
//                 //

//                 $keysvalue = $new_account_item = array();
//                 foreach ($channel_account_item as $k=>$v){
//                 $keysvalue[$k] = $v['paying_money'];
//                 }
//                 asort($keysvalue);
//                 reset($keysvalue);
//                 foreach ($keysvalue as $k=>$v){
//                 $new_account_item[$k] = $channel_account_item[$k];
//                 }


//                 reset($keysvalue);
//                 $min_accout=current($new_account_item);

//                 $max_accout=array_pop($new_account_item);

//                 if ($min_accout['paying_money'] >0) {
//                     $channel_account =$min_accout;
//                 } elseif ($max_accout['paying_money'] ==0) {
//                   $channel_account = getWeight($channel_account_item);
//                 }else {
//                  $channel_account =$min_accout;
//                 }

//             }elseif ($control_order['control_order']==1) {
//               //顺序 
//               //查找些子账号的 上一个订单
//               $channel_account=array();
//                  $channel_account_id=array_column($channel_account_item, 'id');
//                 $last_where['pay_memberid']=$userid;  
//                 $last_where['account_id']=array('in',$channel_account_id);

//               $last_odrder=M('order')->where($last_where)->order('id desc')->find();
//             while (list($k, $v) = each($channel_account_item)) {
//                 if ($v['id'] ==$last_odrder['account_id']) {
//                     break;
//                 }

//             }
//                  $channel_account=current($channel_account_item);
//                  if (empty($channel_account)) {
//                     Reset($channel_account_item);
//                      $channel_account=current($channel_account_item);
//                  }             
//             } else{
//                 $channel_account = getWeight($channel_account_item);
//             }
//         }

//         $list['mch_id'] = $channel_account['mch_id'];
//         $list['signkey'] = $channel_account['signkey'];
//         $list['appid'] = $channel_account['appid'];
//         $list['appsecret'] = $channel_account['appsecret'];
//         //-------------------------计算权重end-----------------------------------------

        return $list;
    }

    protected function selectOrder($where, $tableName=''){
        $Wttklistmodel = D('Wttklist');
        if($tableName == ''){
            $tables = $Wttklistmodel->getTables();
            foreach ($tables as $v){
                $lists = $Wttklistmodel->table($v)->where($where)->find();
                if(!empty($lists)){
                    $tableName = $v;
                    break;
                }
            }
        }else{
            $lists = $Wttklistmodel->table($tableName)->where($where)->find();
        }
        
        $lists || showError('无该代付订单或订单当前状态不允许该操作！');
        foreach($lists as $k => $v){
            $lists[$k]['additional'] = json_decode($v['additional'],true);
        }
        return $lists;
    }



    protected function checkMoney($uid,$money){
        $where = ['id' => $uid];
        $balance = M('Member')->where($where)->getField('balance');
        $balance < $money && showError('支付金额错误');
    }

    protected function changeStatus($id, $status = 1, $return,$table='Wttklist'){
        $redis = $this->redis_connect();
        if($redis->get('changestatus' . $status . $id)){
            return;
        }
        $redis->set('changestatus' . $status . $id,'1',120);
        
        $where = ['id'=>$id, 'status'=>['in', '0,1']];
	    //处理成功返回的数据
        $Wttklist = D('Wttklist');
        $withdraw = $Wttklist->table($table)->where($where)->find();
        if(!$withdraw || empty($withdraw)){
            return;
        }
        $memo = $withdraw['memo'];
        $data = array();
		switch($status){
			case 1:
				//提交代付成功
			   $data['status'] = 1;
			   $data['memo']  = $return['memo']. date('Y-m-d H:i:s');
			   break;
			case 2:
				 //支付成功
			   $data['status'] = 2;
			   $data['cldatetime'] = date('Y-m-d H:i:s', time());
			   $data['memo']  = '代付成功';
			   break;
			case 3:
                    // log_place_order( 'DFadd_error', $withdraw['orderid'],  json_encode($withdraw, JSON_UNESCAPED_UNICODE));    //日志
			     try {
			        $contentstr = '失败';
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
                    // log_place_order( 'DFadd_error', $withdraw['orderid'],   $Moneychange->table($tablename)->getLastSql());    //日志
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
    				$message = isset($return['memo'])?$return['memo']:'代付失败';
    				
    			    $data['status'] = 4;
    			    $data['cldatetime'] = date('Y-m-d H:i:s', time());
    			    $data['memo']  = $message;
    			 //   log_place_order( 'DFadd_error', $withdraw['orderid'], '$res1:' . $res1);    //日志
    			 //   log_place_order( 'DFadd_error', $withdraw['orderid'], '$res2:' . $res2);    //日志
    			 //   log_place_order( 'DFadd_error', $withdraw['orderid'], '$sxf_re:' . $sxf_re);    //日志
    			    
                    if($res1 && $res2 && $sxf_re === true){
                    // log_place_order( 'DFadd_error', $withdraw['orderid'],   11111);    //日志
            		    M()->commit();
            		}else{
                        log_place_order( 'DFadd_error', $withdraw['orderid'],   '数据回滚了');    //日志
            		    M()->rollback();
            		}
			    } catch (\Exception $e) {
                    log_place_order( 'DFadd_error', $withdraw['orderid'] . '$e',  $e);    //日志
                }
			    break;
			default:
				 //订单状态不改变
				 $sta = $Wttklist->table($table)->where(['id' => $id])->getField('status');
				 $data['status'] = $sta;
				 $data['memo']  = '状态不改变！ - '. $return['memo']. date('Y-m-d H:i:s');
				 break;
		}
        $ad = $Wttklist->table($table)->where($where)->save($data);
        if($status == 2 || $status == 3){
            $withdraw['memo'] = $data['memo'];
            $withdraw['status'] = $data['status'];
            $redis->set($withdraw['orderid'],json_encode($withdraw),3600 * 2);
            // $redis->rPush('notifyList_DF', $withdraw['orderid']);
            Automatic_Notify($withdraw['orderid']);
        }
        return;
	}
    // protected function handle($id, $status = 1, $return, $tableName = ''){
    //     $redis = $redis_connect();
    //     if($redis->get('handle' . $id)){
    //         return;
    //     }
    //     $redis->set('handle' . $id,'1',120);
    //     //处理成功返回的数据
    //     $Wttklistmodel = D('Wttklist');
    //     if($tableName==''){
    //         $tables = $Wttklistmodel->getTables();
    //         foreach ($tables as $v){
    //             $Wttklist = $Wttklistmodel->table($v)->where(['id' => $id])->find();
    //             if(!empty($Wttklist)){
    //                 $tableName = $v;
    //                 break;
    //             }
    //         }
    //     }else{
    //         $Wttklist = $Wttklistmodel->table($tableName)->where(['id' => $id])->find();
    //     }
    //     if($Wttklist["status"] > 1){return;}
    //     $memo =$Wttklist['memo'];
    //     $data = array();
    //     switch($status){
    //         case 1:
    //             //提交代付成功
    //             $data['status'] = 1;
    //             $data['memo']  = '申请成功！ - '. $return['memo']. date('Y-m-d H:i:s').$memo;
    //             break;
    //         case 2:
    //             //支付成功
    //             //  if($Wttklist["status"] != 0 && $Wttklist["status"] != 1){return;}
    //             $data['status'] = 2;
    //             $data['cldatetime'] = date('Y-m-d H:i:s', time());
    //             $data['memo']  = '代付成功！ - '. $return['memo']. date('Y-m-d H:i:s').$memo;
    //             break;
    //         case 3:
    //             // if($Wttklist["status"] != 0 && $Wttklist["status"] != 1){return;}
    //             //各种失败未返回 并退回金额
    //             $message = isset($return['memo'])?$return['memo']:'代付失败！';
    //             $message = $message .' - '.date('Y-m-d H:i:s').$memo;
    //             M()->startTrans();
				// $Reject_re = Reject(['id' => $id, 'status' => '4','message'=> $message],$return);
				// if($Reject_re ===false){
				//     M()->rollback();
				// }else{
				//     M()->commit();
				// }
    //             //异步通知下游
    //             Automatic_Notify($id);
    //             return;
    //         default:
    //             //订单状态不改变
    //             $sta = $Wttklistmodel->table($tableName)->where(['id' => $id])->getField('status');
    //             $data['status'] = $sta;
    //             $data['memo']  = '状态不改变！ - '. $return['memo']. date('Y-m-d H:i:s').$memo;
    //     }
    //     if(in_array($status, [0,1,2])){
    //         $data = array_merge($data, $return);
    //         $where = ['id'=>$id, 'status'=>['in', '0,1']];
    //         $res = $Wttklistmodel->table($tableName)->where($where)->save($data);
    //     }
    //     //异步通知下游
    //     Automatic_Notify($id);

    // }
    
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