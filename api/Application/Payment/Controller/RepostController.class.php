<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-09-12
 * Time: 14:20
 */
namespace Payment\Controller;

use Think\Log;

class RepostController extends PaymentController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        echo json_encode(['code' => 500, 'msg' => '走错道了哦.']);
    }

    /**
     *  补单机制
     */
    public function postUrl()
    {
        Log::record("自动补发任务触发", Log::INFO);
        echo "自动补发任务触发:" . date('Y-m-d H:i:s', time()) ."\n";
        log_place_order('dfsh', "自动补发任务触发", date('Y-m-d H:i:s', time()));    //日志
        //缓存
        $configs = C('PLANNING');
        // $nums = $configs['postnum'] ? $configs['postnum'] : 3;
        $nums = 3;
        $maps['userid'] = ['neq', 2];
        $maps['notifyurl'] = ['neq', ''];
        $maps['status'] = ['in', ["2", "4"]];
        // $maps['fail_re'] = ['neq', 1];
        $maps['num'] = array('lt', $nums);
        $maps['last_reissue_time'] = array('lt', time() - 10);//距离上次补发至少10秒
        // $maps['cldatetime'] = ['between', [date('Y-m-d H:i:s', time() - 180), date('Y-m-d H:i:s', time())]];
        $Wttklistmodel = D('Wttklist');
        $tables = $Wttklistmodel->getTables();
        foreach ($tables as $v){
            $list = $Wttklistmodel->table($v)->field('id,orderid')->where($maps)->order('id asc')->limit(100)->select();
            // echo  $Wttklistmodel->table($v)->getLastSql();
            if(!empty($list)){
                $tableName = $v;
                break;
            }
        }
        // $list = M('Wttklist')->field('id')->where($maps)->order('id asc')->limit(100)->select();
        log_place_order('dfsh', "处理条数", count($list));    //日志
        if ($list) {
            log_place_order('dfsh', "自动补发任务触发", json_encode($list));    //日志
            foreach ($list as $item) {
                
                $redis = redis_connect();
                $redis->rPush('notifyList_DF_BuFa', $item['orderid']);
                
                // Automatic_Notify($item['id'],$tableName);
                // $this->paymentbufa($item['id']);
                $Wttklistmodel->table($tableName)->where(['id' => $item['id']])->save(['num' => array('exp', 'num+1'), 'last_reissue_time' => time()]);
            }
        }
        echo "自动补发任务结束:" . date('Y-m-d H:i:s', time())."\n";
        log_place_order('dfsh', "自动补发任务结束", date('Y-m-d H:i:s', time()));    //日志
    }
}