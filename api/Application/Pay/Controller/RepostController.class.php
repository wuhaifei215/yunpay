<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-09-12
 * Time: 14:20
 */
namespace Pay\Controller;
use Think\Log;

class RepostController extends PayController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        echo json_encode(['code'=>500,'msg'=>'走错道了哦.']);
    }

    /**
     *  补单机制
     */
    public function postUrl()
    {
        Log::record("自动补发任务触发", Log::INFO);
        log_place_order('sh', "自动补发任务触发", '开始');    //日志
        //缓存
        $configs = C('PLANNING');
        $nums = $configs['postnum'] ? $configs['postnum'] : 5;
        $maps['pay_status'] = array('eq',1);
        $maps['num'] = array('lt',$nums);
        $maps['last_reissue_time'] = array('lt', time()-10);//距离上次补发至少10秒
        $OrderModel = D('Order');   
        $tables = $OrderModel->getTables();
        foreach ($tables as $v){
            $list = $OrderModel->table($v)->where($maps)->field('id,pay_orderid,pay_ytongdao')->order('id asc')->limit(50)->select();
            if(!empty($list)) break;
        }

        if($list){
            log_place_order('sh', "自动补发任务触发", json_encode($list));    //日志
            foreach ($list as $item){
                $OrderModel = D('Order');  
                $date = date('Ymd',strtotime(substr($item['pay_orderid'], 0, 8)));  //获取订单日期
                $tablename = $OrderModel->getRealTableName($date);
                $OrderModel->table($tablename)->where(['id'=>$item['id']])->save(['num' => array('exp','num+1'), 'last_reissue_time' => time()]);
                
                $this->EditMoney($item['pay_orderid'],$item['pay_ytongdao'],0);
            }
        }
    }
}