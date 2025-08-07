<?php
namespace Admin\Controller;

class IndexController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    //首页
    public function index()
    {
        $Websiteconfig = D("Websiteconfig");
        $withdraw = $Websiteconfig->getField("withdraw");
        $this->assign("withdraw", $withdraw);
        $this->display();
    }

    public function main()
    {
        // $get_data = I("request.get_data", 0, 'intval');
        // $this->assign('get_data', $get_data);
        // if ($get_data) {
            // $todayBegin = date('Y-m-d') . ' 00:00:00';
            // $todyEnd = date('Y-m-d') . ' 23:59:59';
            
            // $beginThismonth = mktime(0, 0, 0, date('m'), 1, date('Y'));
            // $endThismonth = mktime(23, 59, 59, date('m'), date('t'), date('Y'));
            
            // //今日平台总入金
            // $stat = M('Order')->field(['sum(`pay_amount`) today_allordersum ,count(`id`) today_allordercount'])->where(['pay_applydate' => ['between', [strtotime($todayBegin), strtotime($todyEnd)]],'pay_status' => ['between', [1, 2]], 'lock_status' => ['neq', 1]])->find();
            
            // //本月平台总入金
            // $stat['month_allordersum'] = M('Order')->where(['pay_applydate' => ['between', [$beginThismonth, $endThismonth]],'pay_status' => ['between', [1, 2]], 'lock_status' => ['neq', 1]])->sum('pay_amount');
            
            // //本月商户总分成
            // $stat['allmemberprofit'] = M('moneychange')->where(['datetime' => ['between', [date('Y-m-d 00:00:00',$beginThismonth), date('Y-m-d 23:59:59',$endThismonth)]],'lx' => 9])->sum('money');
            // //本月平台总分成 = （本月收款手续费 - 本月收款成本） + （本月提现手续费 - 本月提现成本）
            // $month_income_profit = M('Order')
            // ->field(['(sum(`pay_poundage`) - sum(`cost`)) profit'])
            // ->where(['pay_applydate' => ['between', [$beginThismonth, $endThismonth]],'pay_status' => ['between', [1, 2]], 'lock_status' => ['neq', 1]])
            // ->find();
            
            // $month_withdraw_profit = M('Wttklist')
            // ->field(['sum(`tkmoney`) tkmoney','(sum(`sxfmoney`) - sum(`cost`)) tkprofit',])
            // ->where(['sqdatetime' => ['between', [date('Y-m-d 00:00:00',$beginThismonth), date('Y-m-d 23:59:59',$endThismonth)]], 'status' => ['between', ['2', '3']]])
            // ->find();
            // $stat['month_platform_income'] = $month_income_profit['profit'] + $month_withdraw_profit['tkprofit'] - $stat['allmemberprofit'];
            
            // //今日商户总分成
            // $stat['todaymemberprofit'] = M('moneychange')->where(['datetime' => ['between', [$todayBegin, $todyEnd]],'lx' => 9])->sum('money');
            // //今日平台总分成 = （今日收款手续费 - 今日收款成本） + （今日提现手续费 - 今日提现成本）
            // $today_income_profit = M('Order')
            // ->field(['(sum(`pay_poundage`) - sum(`cost`)) profit'])
            // ->where(['pay_applydate' => ['between', [strtotime($todayBegin), strtotime($todyEnd)]],'pay_status' => ['between', [1, 2]], 'lock_status' => ['neq', 1]])
            // ->find();
            
            // $today_withdraw_profit = M('Wttklist')
            // ->field(['sum(`tkmoney`) tkmoney','(sum(`sxfmoney`) - sum(`cost`)) tkprofit',])
            // ->where(['sqdatetime' => ['between', [$todayBegin, $todyEnd]], 'status' => ['between', ['2', '3']]])
            // ->find();
            // $stat['today_platform_income'] = $today_income_profit['profit'] + $today_withdraw_profit['tkprofit'] - $stat['todaymemberprofit'];
            // $stat['today_withdraw_money'] = $today_withdraw_profit['tkmoney'];

            
            
            // //商户总分成
            // $stat['allmemberprofit'] = M('moneychange')->where(['lx' => 9])->sum('money');
            // //平台总分成
            // $all_income_profit = M('Order')->where(['pay_status' => ['between', [1, 2]], ['lock_status' => ['neq', 1]]])->sum('pay_poundage');
    
            // $all_order_cost = M('Order')->where(['pay_status' => ['between', [1, 2]], ['lock_status' => ['neq', 1]]])->sum('cost');
            // $all_wt_profit = M('wttklist')->where(['status' => ['between', ['2', '3']]])->sum('sxfmoney');
            // $all_pay_cost = M('wttklist')->where(['status' => ['between', ['2', '3']]])->sum('cost');
            // $stat['allplatformincome'] = $all_income_profit + $all_wt_profit - $all_order_cost - $all_pay_cost - $stat['allmemberprofit'];
            
            
    
            //7天统计
            // $lastweek = time() - 7 * 86400;
            // $sql = "select COUNT(id) as num,SUM(pay_amount) AS amount,SUM(pay_poundage) AS rate,SUM(pay_actualamount) AS total from pay_order where  1=1 and pay_status>=1 and DATE_SUB(CURDATE(), INTERVAL 7 DAY) <= date(FROM_UNIXTIME(pay_successdate,'%Y-%m-%d')) and pay_successdate>=$lastweek; ";
            // $wdata = M('Order')->query($sql);
    
            //按月统计
            // $lastyear = strtotime(date('Y-1-1'));
            // $sql = "select FROM_UNIXTIME(pay_successdate,'%Y年-%m月') AS month,SUM(pay_amount) AS amount,SUM(pay_poundage) AS rate,SUM(pay_actualamount) AS total from pay_order where  1=1 and pay_status>=1 and pay_successdate>=$lastyear GROUP BY month;  ";
            // $_mdata = M('Order')->query($sql);
            // $mdata = [];
            // foreach ($_mdata as $item) {
            //     $mdata['amount'][] = $item['amount'] ? $item['amount'] : 0;
            //     $mdata['mdate'][] = "'" . $item['month'] . "'";
            //     $mdata['total'][] = $item['total'] ? $item['total'] : 0;
            //     $mdata['rate'][] = $item['rate'] ? $item['rate'] : 0;
            // }
            //商户总分成
            // $stat['allmemberprofit'] = M('moneychange')->where(['lx' => 9])->sum('money');
            // //平台总分成
            // $all_income_profit = M('Order')->where(['pay_status' => ['between', [1, 2]], ['lock_status' => ['neq', 1]]])->sum('pay_poundage');
    
            // $all_order_cost = M('Order')->where(['pay_status' => ['between', [1, 2]], ['lock_status' => ['neq', 1]]])->sum('cost');
            // $all_wt_profit = M('wttklist')->where(['status' => ['between', ['2', '3']]])->sum('sxfmoney');
            // $all_pay_cost = M('wttklist')->where(['status' => ['between', ['2', '3']]])->sum('cost');
            // $stat['allplatformincome'] = $all_income_profit + $all_wt_profit - $all_order_cost - $all_pay_cost - $stat['allmemberprofit'];
            // $todayBegin = date('Y-m-d') . ' 00:00:00';
            // $todyEnd = date('Y-m-d') . ' 23:59:59';
            // //今日平台总入金
            // $stat['todayordersum'] = M('Order')->where(['pay_applydate' => ['between', [strtotime($todayBegin), strtotime($todyEnd)]], 'pay_status' => ['between', [1, 2]]])->sum('pay_amount');
            // //今日商户总分成
            // $stat['todaymemberprofit'] = M('moneychange')->where(['datetime' => ['between', [$todayBegin, $todyEnd]], 'lx' => 9])->sum('money');
            // //今日平台总分成
            // $income_profit = M('Order')->where(['pay_successdate' => ['between', [strtotime($todayBegin), strtotime($todyEnd)]], 'pay_status' => ['between', [1, 2]]])->sum('pay_poundage');
    
            // $order_cost = M('Order')->where(['pay_successdate' => ['between', [strtotime($todayBegin), strtotime($todyEnd)]], 'pay_status' => ['between', [1, 2]]])->sum('cost');
            // $wt_profit = M('wttklist')->where(['sqdatetime' => ['between', [$todayBegin, $todyEnd]], 'status' => ['between', ['2', '3']]])->sum('sxfmoney');
            // $pay_cost = M('wttklist')->where(['sqdatetime' => ['between', [$todayBegin, $todyEnd]], 'status' =>  ['between', ['2', '3']]])->sum('cost');
            // $stat['todayplatformincome'] = $income_profit + $wt_profit - $order_cost - $pay_cost - $stat['todaymemberprofit'];
            // foreach ($stat as $k => $v) {
            //     $stat[$k] = $v + 0;
            // }
    
    
            $time_d = date("Y-m-d H", time()+3599) . ":00:00";

            for ($i = 48;  $i > 0; $i--) {
                if($i%2==0){
                    $where_d = $data_d = array();
                    $time_start_d = strtotime($time_d) - $i * 3600;
                    $time_end_d = strtotime($time_d) - ($i-1) * 3600;
                    $where_d['pay_applydate'] = ['between', [$time_start_d, $time_end_d]];
        
                    //总数
                    // $all_d = M('Order')->where($where_d)->count('id');
                    //成功
                    $where_d['pay_status'] = ['between', [1, 2]];
                    $where_d['lock_status'] = ['eq', '0'];
                    $field = ['sum(`pay_amount`) amount', 'sum(`pay_poundage`) poundage','count(`id`) Success_count'];
                    // $field = ['sum(`pay_amount`) amount', 'sum(`pay_poundage`) poundage', 'sum(`pay_actualamount`) actualamount', 'count(`id`) Success_count'];
                    
                    
                    $OrderModel = D('Order');
                    $tableName = $OrderModel->getRealTableName(date("Y-m-d H:i:s",$time_start_d));
                    $isTable = $OrderModel->query("SHOW TABLES LIKE '". $tableName ."'");
                    if($isTable){
                        $data_d = $OrderModel->table($tableName)->field($field)->where($where_d)->find();
                        $list_d['amount'][$i] = $data_d['amount'] ? $data_d['amount'] : 0;
                        $list_d['total'][$i] = $data_d['actualamount'] ? $data_d['actualamount'] : 0;
                        $list_d['rate'][$i] = $data_d['poundage'] ? $data_d['poundage'] : 0;
                        // $SuccessSum_d = $data_d['success_count'];
                    }
                    $list_d['time'][$i] = date('H', $time_start_d);
                }
            }
            $list_d_str = "'" . implode("','", $list_d['time']) . "'";

            $this->assign('stat', $stat);
            // $this->assign('wdata', $wdata[0]);
            // $this->assign('mdata', $mdata);
            $this->assign('list_d', $list_d);
            $this->assign('list_d_str', $list_d_str);
        // }
        
        $this->display();
    }

    /**
     * 清除缓存
     */
    public function clearCache()
    {
        $groupid = session('admin_auth.groupid');
        if ($groupid == 1) {
            $dir = RUNTIME_PATH;
            $this->delCache($dir);
            $this->success('缓存清除成功！');
        } else {
            $this->error('只有总管理员能操作！');
        }
    }

    /**
     * 删除缓存目录
     * @param $dirname
     * @return bool
     */
    protected function delCache($dirname)
    {
        $result = false;
        if (!is_dir($dirname)) {
            echo " $dirname is not a dir!";
            exit(0);
        }
        $handle = opendir($dirname); //打开目录
        while (($file = readdir($handle)) !== false) {
            if ($file != '.' && $file != '..') {
                //排除"."和"."
                $dir = $dirname . DIRECTORY_SEPARATOR . $file;
                is_dir($dir) ? self::delCache($dir) : unlink($dir);
            }
        }
        closedir($handle);
        $result = rmdir($dirname) ? true : false;
        return $result;
    }


    public function MP3File()
    {
        if ($_POST) {
//            $where['sqdatetime'] = ['between', ['2019-03-05 19:56:18', '2019-03-05 19:56:38']];
            $where['sqdatetime'] = ['between', [date('Y-m-d H:i:s', time() - 600), date('Y-m-d H:i:s', time())]];
            $where['status'] = ['eq', 0];
            $list = M('Wttklist')->field('id')->where($where)->order('id desc')->find();
//            $list = M('Wttklist')->where($where)->order('id desc')->select();
            if ($list) {
                $this->ajaxReturn(['status' => 1]);
            }
        }
        $this->display();
    }



    // public function myupdata(){
    //     $list = M('Order')->where(array('channel_id' => '218' ))->select();
    //     // $i=0;
    //     foreach ($list as $key => $value) {
    //         // if ($value['cost']=='0') {
    //             // $chage=$value['pay_amount']*0.0048;
    //             $list = M('Order')->where(array('id' => $value['id']))->save(array('cost' => 0));
    //             if ($list) {
    //                $i++;
    //             }
    //         // }

    //     }
    //     print_r($i);die();

    // }

}
