<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-04-02
 * Time: 23:01
 */

namespace Admin\Controller;

use Org\Net\UserLogService;
use Think\Page;

/**
 * 订单管理控制器
 * Class OrderController
 * @package Admin\Controller
 */
class OrderController extends BaseController
{
    const TMT = 7776000; //三个月的总秒数

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 订单列表
     */
    public function index()
    {
        //银行
        $tongdaolist = M("Channel")->field('id,code,title')->select();
        $this->assign("tongdaolist", $tongdaolist);

        //通道
        $banklist = M("Product")->field('id,name,code')->select();
        $this->assign("banklist", $banklist);

        $where = array();
        $memberid = I("request.memberid", 0, 'intval');
        if ($memberid) {
            $where['pay_memberid'] = array('eq', $memberid);
            $todaysumMap['pay_memberid'] = $monthsumMap['pay_memberid'] = $nopaidsumMap['pay_memberid'] = $monthNopaidsumMap['pay_memberid'] = array('eq', $memberid);
            $profitSumMap['userid'] = $profitMap['userid'] = $profitSumMap['userid'] = $CorrectSumMap['userid'] = $cjsxfMap['userid'] = $cjlrMap['userid'] = $memberid - 10000;
        }
        $this->assign('memberid', $memberid);
        $orderid = I("request.orderid", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($orderid) {
            $where['out_trade_id'] = $orderid;
        }
        $this->assign('orderid', $orderid);
        $ddlx = I("request.ddlx", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($ddlx != "") {
            $where['ddlx'] = array('eq', $ddlx);
        }
        $this->assign('ddlx', $ddlx);
        $tongdao = I("request.tongdao", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($tongdao) {
            $where['channel_id'] = array('eq', $tongdao);
            $accountlist = M('channel_account')->where(['channel_id' => $tongdao])->select();
            $this->assign('accountlist', $accountlist);
        }
        $this->assign('tongdao', $tongdao);
        $account = I("request.account", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($account) {
            $where['account_id'] = array('eq', $account);

        }
        $this->assign('account', $account);
        $bank = I("request.bank", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($bank) {
            $where['pay_bankcode'] = array('eq', $bank);
        }
        $this->assign('bank', $bank);
        $payOrderid = I('get.payorderid', '', 'trim,string,strip_tags,htmlspecialchars');

        if ($payOrderid) {
            $where['pay_orderid'] = array('eq', $payOrderid);
            $profitMap['transid'] = $payOrderid;
        }
        $this->assign('payOrderid', $payOrderid);
        $body = I("request.body", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($body) {
            $where['pay_productname'] = array('eq', $body);
        }
        $this->assign('body', $body);
        $status = I("request.status", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($status != "") {
            if ($status == '1or2') {
                $where['pay_status'] = array('between', array('1', '2'));
            } else {
                $where['pay_status'] = array('eq', $status);
            }
        }
        $this->assign('status', $status);
        $payamount = I("request.payamount", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($payamount) {
            $where['pay_amount'] = array('eq', $payamount);
        }
        $this->assign('payamount', $payamount);

        $createtime = urldecode(I("request.createtime", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($createtime) {
            list($cstime, $cetime) = explode('|', $createtime);
            $where['pay_applydate'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
            $where['lock_status'] = ['neq', '1'];
            $profitSumMap['datetime'] = $profitMap['datetime'] = $cjsxfMap['datetime'] = $cjlrMap['cldatetime'] = ['between', [$cstime, $cetime ? $cetime : date('Y-m-d H:i:s')]];
        }
        $this->assign('createtime', $createtime);
        $successtime = urldecode(I("request.successtime", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($successtime) {
            list($sstime, $setime) = explode('|', $successtime);
            $where['pay_successdate'] = ['between', [strtotime($sstime), strtotime($setime) ? strtotime($setime) : time()]];
            $profitSumMap['datetime'] = $profitMap['datetime'] = $cjsxfMap['datetime'] = $cjlrMap['cldatetime'] = ['between', [$sstime, $setime ? $setime : date('Y-m-d H:i:s')]];
        }
        $this->assign('successtime', $successtime);
        $where['lock_status'] = ['neq', '1'];
        $count = M('Order')->alias('as O')->where($where)->count();
        $size = 100;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }

        $page = new Page($count, $rows);
        
        $field = 'id,pay_amount,pay_poundage,pay_actualamount,pay_bankname,pay_zh_tongdao,pay_channel_account,pay_memberid,pay_orderid,out_trade_id,pay_applydate,pay_successdate,pay_productname,pay_status,ddlx,three_orderid';
        
        $list = M('Order')
            ->field($field)
            // ->alias('as O')
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('id desc')
            ->select();

        $this->assign('rows', $rows);
        $this->assign("list", $list);
        $this->assign('page', $page->show());
        $this->assign("isrootadmin", is_rootAdministrator());
        C('TOKEN_ON', false);
        $this->display();
    }

    /**
     * 订单列表
     */
    public function indexCount()
    {
        //银行
        $tongdaolist = M("Channel")->field('id,code,title')->order('id desc')->select();
        $this->assign("tongdaolist", $tongdaolist);

        //通道
        $banklist = M("Product")->field('id,name,code')->select();
        $this->assign("banklist", $banklist);

        $where = array();
        $memberid = I("request.memberid", 0, 'intval');
        if ($memberid) {
            $where['O.pay_memberid'] = array('eq', $memberid);
            $todaysumMap['pay_memberid'] = $monthsumMap['pay_memberid'] = $nopaidsumMap['pay_memberid'] = $monthNopaidsumMap['pay_memberid'] = array('eq', $memberid);
            $profitSumMap['userid'] = $profitMap['userid'] = $profitSumMap['userid'] = $CorrectSumMap['userid'] = $cjsxfMap['userid'] = $cjlrMap['userid'] = $memberid - 10000;
        }
        $this->assign('memberid', $memberid);
        $orderid = I("request.orderid", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($orderid) {
            $where['O.out_trade_id'] = $orderid;
        }
        $this->assign('orderid', $orderid);
        $ddlx = I("request.ddlx", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($ddlx != "") {
            $where['O.ddlx'] = array('eq', $ddlx);
        }
        $this->assign('ddlx', $ddlx);
        $tongdao = I("request.tongdao", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($tongdao) {
            $where['O.channel_id'] = array('eq', $tongdao);
            $accountlist = M('channel_account')->where(['channel_id' => $tongdao])->select();
            $this->assign('accountlist', $accountlist);
        }
        $this->assign('tongdao', $tongdao);
        $account = I("request.account", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($account) {
            $where['O.account_id'] = array('eq', $account);

        }
        $this->assign('account', $account);
        $bank = I("request.bank", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($bank) {
            $where['O.pay_bankcode'] = array('eq', $bank);
        }
        $this->assign('bank', $bank);
        $payOrderid = I('get.payorderid', '', 'trim,string,strip_tags,htmlspecialchars');

        if ($payOrderid) {
            $where['O.pay_orderid'] = array('eq', $payOrderid);
            $profitMap['transid'] = $payOrderid;
        }
        $this->assign('payOrderid', $payOrderid);
        $body = I("request.body", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($body) {
            $where['O.pay_productname'] = array('eq', $body);
        }
        $this->assign('body', $body);
        $status = I("request.status", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($status != "") {
            if ($status == '1or2') {
                $where['O.pay_status'] = array('between', array('1', '2'));
            } else {
                $where['O.pay_status'] = array('eq', $status);
            }
        }
        $this->assign('status', $status);
        $payamount = I("request.payamount", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($payamount) {
            $where['O.pay_amount'] = array('eq', $payamount);
        }
        $this->assign('payamount', $payamount);

        $createtime = urldecode(I("request.createtime", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($createtime) {
            list($cstime, $cetime) = explode('|', $createtime);
            $where['O.pay_applydate'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
            $where['O.lock_status'] = ['neq', '1'];
            $profitSumMap['datetime'] = $profitMap['datetime'] = $cjsxfMap['datetime'] = $cjlrMap['cldatetime'] = ['between', [$cstime, $cetime ? $cetime : date('Y-m-d H:i:s')]];
        }
        $this->assign('createtime', $createtime);
        $successtime = urldecode(I("request.successtime", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($successtime) {
            list($sstime, $setime) = explode('|', $successtime);
            $where['O.pay_successdate'] = ['between', [strtotime($sstime), strtotime($setime) ? strtotime($setime) : time()]];
            $profitSumMap['datetime'] = $profitMap['datetime'] = $cjsxfMap['datetime'] = $cjlrMap['cldatetime'] = ['between', [$sstime, $setime ? $setime : date('Y-m-d H:i:s')]];
        }
        $this->assign('successtime', $successtime);
        $where['O.lock_status'] = ['neq', '1'];
        $count = M('Order')->alias('as O')->where($where)->count();
        $size = 100;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }

        $page = new Page($count, $rows);
        $list = M('Order')->alias('as O')
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('id desc')
            ->select();
        // echo M('Order')->getLastSql();
        
        

        //查询支付成功的订单的手续费，入金费，总额总和
        $countWhere = $where;        
        //默认获取今日成功交易总额
        $todayBegin = date('Y-m-d') . ' 00:00:00';
        $todyEnd = date('Y-m-d') . ' 23:59:59';
        if (!$createtime && !$successtime) {
            $countWhere['pay_applydate'] = ['between', [strtotime($todayBegin), strtotime($todyEnd)]];
            if($status!='' && $status!=0){
                $countWhere['pay_successdate'] = ['between', [strtotime($todayBegin), strtotime($todyEnd)]];
            }
            $profitSumMap['datetime'] = $profitMap['datetime'] = $cjsxfMap['datetime'] = $cjlrMap['cldatetime'] = ['between', [$todayBegin, $todyEnd]];
        }
        $countWhere['O.pay_status'] = ['between', [1, 2]];
        $countWhere['O.lock_status'] = ['neq', '1'];
        $field = ['sum(`pay_amount`) pay_amount', 'sum(`cost`) cost', 'sum(`pay_poundage`) pay_poundage', 'sum(`pay_actualamount`) pay_actualamount', 'count(`id`) success_count'];
        $sum = M('Order')->alias('as O')->field($field)->where($countWhere)->find();
        $countWhere['O.pay_status'] = 0;
        //失败笔数
        $sum['fail_count'] = M('Order')->alias('as O')->where($countWhere)->count('id');
        // echo M('Order')->getLastSql();
        //投诉保证金冻结金额
        // $map = $where;
        // $map['C.status'] = 0;
        // $sum['complaints_deposit_freezed'] = M('complaints_deposit')->alias('as C')->join('LEFT JOIN __ORDER__ AS O ON C.pay_orderid=O.pay_orderid')
        //     ->where($map)
        //     ->sum('freeze_money');
        // $sum['complaints_deposit_freezed'] += 0;
        // $map['C.status'] = 1;
        // $sum['complaints_deposit_unfreezed'] = M('complaints_deposit')->alias('as C')->join('LEFT JOIN __ORDER__ AS O ON C.pay_orderid=O.pay_orderid')
        //     ->where($map)
        //     ->sum('freeze_money');
        // $sum['complaints_deposit_unfreezed'] += 0;
        
        $profitMap['lx'] = 9;
        $sum['memberprofit'] = M('moneychange')->where($profitMap)->sum('money');
        // echo M('moneychange')->getLastSql();
        
        // //出金利润
        // $cjlrMap['status'] = 2;
        // $field1 = ['SUM(`tkmoney`) - SUM(`money`) AS cjlrmoney'];
        // $sum1 = M('tklist')->field($field1)->where($cjlrMap)->find();
        // $sum2 = M('wttklist')->field($field1)->where($cjlrMap)->find();
        // //代付成本
        // $cjcost = M('wttklist')->where($cjlrMap)->sum('cost');
        // $stat['cjlr'] = $sum1['cjlrmoney'] + $sum2['cjlrmoney'] - $cjcost;
        
        // //出金手续费
        // $sxf = M('moneychange')->where('lx = 14 OR lx = 16')->sum('money');
        // //退回出金手续费
        // $qxsxf = M('moneychange')->where('lx = 15 OR lx = 17')->sum('money');
        // //出金实际手续费
        // $stat['cjsxf'] = $sxf - $qxsxf;
        
        $sum['strate'] = $sum['pay_poundage'] - $sum['cost'] - $sum['memberprofit'];
        // $sum['pay_poundage'] = $sum['pay_poundage'] - $sum['cost'] - $sum['memberprofit'] + $stat['cjlr'] + $stat['cjsxf'];
        foreach ($sum as $k => $v) {
            $sum[$k] += 0;
        }

//         if ($status == '' || $status == '1or2' || $status == 1 || $status == 2) {
//             //今日成功交易总额
//             $todayBegin = date('Y-m-d') . ' 00:00:00';
//             $todyEnd = date('Y-m-d') . ' 23:59:59';
//             if ($successtime) {
//                 $todaysumMap['pay_successdate'] = ['between', [strtotime($todayBegin), strtotime($todyEnd)]];
//             } else {
//                 $todaysumMap['pay_applydate'] = ['between', [strtotime($todayBegin), strtotime($todyEnd)]];
//             }
//             $todaysumMap['pay_status'] = ['in', '1,2'];
//             $todaysumMap['lock_status'] = ['neq', '1'];
//             $stat['todaysum'] = M('Order')->where($todaysumMap)->sum('pay_amount');

//             //平台收入
//             $pay_poundage = M('Order')->where($todaysumMap)->sum('pay_poundage');
//             $profitSumMap['datetime'] = ['between', [$todayBegin, $todyEnd]];
//             $profitSumMap['lx'] = 9;
//             $profitSum = M('moneychange')->where($profitSumMap)->sum('money');
//             $order_cost = M('Order')->where($todaysumMap)->sum('cost');

//             //出金利润
//             $cjlrMap['status'] = 2;
//             $cjlrMap['cldatetime'] = ['between', [$todayBegin, $todyEnd]];
//             $tkmoney1 = M('tklist')->where($cjlrMap)->sum('tkmoney');
//             $tkmoney2 = M('wttklist')->where($cjlrMap)->sum('tkmoney');
//             $money1 = M('tklist')->where($cjlrMap)->sum('money');
//             $money2 = M('wttklist')->where($cjlrMap)->sum('money');
//             //代付成本
//             $cjcost = M('wttklist')->where($cjlrMap)->sum('cost');
//             $stat['cjlr'] = $tkmoney1 + $tkmoney2 - $money1 - $money2 - $cjcost;
//             //出金手续费
//             $cjsxfMap['datetime'] = ['between', [$todayBegin, $todyEnd]];
//             $cjsxfMap['lx'] = 14;
//             $sxf1 = M('moneychange')->where($cjsxfMap)->sum('money');
//             $cjsxfMap['lx'] = 16;
//             $sxf2 = M('moneychange')->where($cjsxfMap)->sum('money');
//             //退回出金手续费
//             $cjsxfMap['lx'] = 15;
//             $qxsxf1 = M('moneychange')->where($cjsxfMap)->sum('money');
//             $cjsxfMap['lx'] = 17;
//             $qxsxf2 = M('moneychange')->where($cjsxfMap)->sum('money');
//             //出金实际手续费
//             $stat['cjsxf'] = $sxf1 + $sxf2 - $qxsxf1 - $qxsxf2;
//             $stat['platform'] = $pay_poundage - $order_cost - $profitSum;
// //            $stat['platform'] = $pay_poundage - $order_cost - $profitSum + $stat['cjlr'] + $stat['cjsxf'];
//             //代理收入
//             $stat['agentIncome'] = $profitSum;

//             //本月成功交易总额
//             $monthBegin = date('Y-m-01') . ' 00:00:00';
//             if ($successtime) {
//                 $monthsumMap['pay_successdate'] = ['egt', strtotime($monthBegin)];
//             } else {
//                 $monthsumMap['pay_applydate'] = ['egt', strtotime($monthBegin)];
//             }
//             $monthsumMap['pay_status'] = ['in', '1,2'];
//             $monthsumMap['lock_status'] = ['neq', '1'];
//             $stat['monthsum'] = M('Order')->where($monthsumMap)->sum('pay_amount');

//             //本月平台收入
//             $pay_poundage = M('Order')->where($monthsumMap)->sum('pay_poundage');
//             $profitSumMap['datetime'] = ['egt', $monthBegin];
//             $profitSumMap['lx'] = 9;
//             $profitSum = M('moneychange')->where($profitSumMap)->sum('money');

//             if ($memberid) {
//                 $CorrectSumMap['lx'] = 7;
//                 $CorrectSum = M('moneychange')->where($CorrectSumMap)->sum('money');
//                 $profitSum = $profitSum - $CorrectSum;
//             }

//             $order_cost = M('Order')->where($monthsumMap)->sum('cost');

//             //出金利润
//             $cjlrMap['status'] = 2;
//             $cjlrMap['cldatetime'] = ['egt', $monthBegin];
//             $tkmoney1 = M('tklist')->where($cjlrMap)->sum('tkmoney');
//             $tkmoney2 = M('wttklist')->where($cjlrMap)->sum('tkmoney');
//             $money1 = M('tklist')->where($cjlrMap)->sum('money');
//             $money2 = M('wttklist')->where($cjlrMap)->sum('money');
//             //代付成本
//             $cjcost = M('wttklist')->where($cjlrMap)->sum('cost');
//             $stat['cjlr'] = $tkmoney1 + $tkmoney2 - $money1 - $money2 - $cjcost;
//             //出金手续费
//             $cjsxfMap['datetime'] = ['egt', $monthBegin];
//             $cjsxfMap['lx'] = 14;
//             $sxf1 = M('moneychange')->where($cjsxfMap)->sum('money');
//             $cjsxfMap['lx'] = 16;
//             $sxf2 = M('moneychange')->where($cjsxfMap)->sum('money');
//             //退回出金手续费
//             $cjsxfMap['lx'] = 15;
//             $qxsxf1 = M('moneychange')->where($cjsxfMap)->sum('money');
//             $cjsxfMap['lx'] = 17;
//             $qxsxf2 = M('moneychange')->where($cjsxfMap)->sum('money');
//             //出金实际手续费
//             $stat['cjsxf'] = $sxf1 + $sxf2 - $qxsxf1 - $qxsxf2;

//             $stat['monthPlatform'] = $pay_poundage - $order_cost - $profitSum;
// //            $stat['monthPlatform'] = $pay_poundage - $order_cost - $profitSum + $stat['cjlr'] + $stat['cjsxf'];
//             //代理收入
//             $stat['monthAgentIncome'] = $profitSum;

//             if ($status == 1) {
//                 $nopaidsumMap['pay_applydate'] = ['between', [strtotime($todayBegin), strtotime($todyEnd)]];
//                 $nopaidsumMap['pay_status'] = 1;
//                 //今日异常订单总额
//                 $stat['todaynopaidsum'] = M('Order')->where($nopaidsumMap)->sum('pay_amount');
//                 //今日异常订单笔数
//                 $stat['todaynopaidcount'] = M('Order')->where($nopaidsumMap)->count();

//                 $monthNopaidsumMap['pay_applydate'] = ['egt', strtotime($todayBegin)];
//                 $monthNopaidsumMap['pay_status'] = 1;
//                 //本月异常订单总额
//                 $stat['monthNopaidsum'] = M('Order')->where($monthNopaidsumMap)->sum('pay_amount');
//                 //本月异常订单笔数
//                 $stat['monthNopaidcount'] = M('Order')->where($monthNopaidsumMap)->count();
//             }
//         } elseif ($status == 0) {
//             //今日未支付订单总额
//             $todayBegin = date('Y-m-d') . ' 00:00:00';
//             $todyEnd = date('Y-m-d') . ' 23:59:59';
//             $monthBegin = date('Y-m-01') . ' 00:00:00';
//             $stat['todaynopaidsum'] = M('Order')->where(['pay_applydate' => ['between', [strtotime($todayBegin), strtotime($todyEnd)]], 'pay_status' => 0])->sum('pay_amount');
//             $stat['monthNopaidsum'] = M('Order')->where(['pay_applydate' => ['egt', strtotime($monthBegin)], 'pay_status' => 0])->sum('pay_amount');
//             $nopaidMap = $where;
//             $nopaidMap['pay_status'] = 0;
//             $stat['totalnopaidsum'] = M('Order')->alias('as O')->where($nopaidMap)->sum('pay_amount');
//         }
        // foreach ($stat as $k => $v) {
        //     $stat[$k] = $v + 0;
        // }
        $sum['success_rate'] = sprintf("%.4f", $sum['success_count'] / ($sum['success_count'] + $sum['fail_count'])) * 100;
        $this->assign('success_rate', $sum['success_rate']);
        // $this->assign('stat', $stat);
        $this->assign('rows', $rows);
        $this->assign("list", $list);
        $this->assign('stamount', $sum['pay_amount']);
        $this->assign('page', $page->show());
        $this->assign('strate', $sum['strate']);
        $this->assign('strealmoney', $sum['pay_actualamount']);
        $this->assign('success_count', $sum['success_count']);
        $this->assign('fail_count', $sum['fail_count']);
        $this->assign('memberprofit', $sum['memberprofit']);
        // $this->assign('complaints_deposit_freezed', $sum['complaints_deposit_freezed']);
        // $this->assign('complaints_deposit_unfreezed', $sum['complaints_deposit_unfreezed']);
        $this->assign("isrootadmin", is_rootAdministrator());
        C('TOKEN_ON', false);
        $this->display();
    }

    /**
     * 导出交易订单
     * */
    public function exportorder()
    {
        UserLogService::HTwrite(5, '导出交易订单', '导出交易订单');
        $memberid = I("request.memberid", 0, 'intval');
        if ($memberid) {
            $where['pay_memberid'] = array('eq', $memberid);
        }
        $orderid = I("request.orderid", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($orderid) {
            $where['out_trade_id'] = $orderid;
        }
        $ddlx = I("request.ddlx", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($ddlx != "") {
            $where['ddlx'] = array('eq', $ddlx);
        }
        $tongdao = I("request.tongdao", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($tongdao) {
            $where['channel_id'] = array('eq', $tongdao);
        }
        $bank = I("request.bank", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($bank) {
            $where['pay_bankname'] = array('eq', $bank);
        }
        $status = I("request.status", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($status != "") {
            if ($status == '1or2') {
                $where['pay_status'] = array('between', array('1', '2'));
            } else {
                $where['pay_status'] = array('eq', $status);
            }
        }
        $createtime = urldecode(I("request.createtime", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($createtime) {
            list($cstime, $cetime) = explode('|', $createtime);
            $where['pay_applydate'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
        }
        $successtime = urldecode(I("request.successtime", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($successtime) {
            list($sstime, $setime) = explode('|', $successtime);
            $where['pay_successdate'] = ['between', [strtotime($sstime), strtotime($setime) ? strtotime($setime) : time()]];
        }

        $title = [
            '下游订单号',
            '系统订单号',
            '商户编号',
            '商户用户名',
            '交易金额',
            '手续费',
            '实际金额',
            '提交时间',
            '成功时间',
            '通道',
            // '通道商户号',
            '状态',
        ];
        $numberField = ['pay_amount', 'pay_poundage', 'pay_actualamount'];
        
        $filed = 'pay_member.username,pay_order.id,pay_order.out_trade_id,pay_order.pay_orderid,pay_order.pay_memberid,pay_order.pay_amount,pay_order.pay_poundage,pay_order.pay_actualamount,pay_order.pay_applydate,pay_order.pay_successdate,pay_order.pay_zh_tongdao,pay_order.memberid,pay_order.pay_status';
        $data = M('Order')
            ->join('LEFT JOIN __MEMBER__ ON __MEMBER__.id+10000 = __ORDER__.pay_memberid')
            ->where($where)->field($filed)->order('id desc')->select();
            
//        echo M('Order')->getLastSql();
        foreach ($data as $item) {

            switch ($item['pay_status']) {
                case 0:
                    $status = '未处理';
                    break;
                case 1:
                    $status = '成功，未返回';
                    break;
                case 2:
                    $status = '成功，已返回';
                    break;
            }
            if ($item['pay_successdate']) {
                $pay_successdate = date('Y-m-d H:i:s', $item['pay_successdate']);
            } else {
                $pay_successdate = 0;
            }
            $list[] = [
                'out_trade_id' => $item['out_trade_id'],
                'pay_orderid' => $item['pay_orderid'],
                'pay_memberid' => $item['pay_memberid'],
                'username' => $item['username'],
                'pay_amount' => $item['pay_amount'],
                'pay_poundage' => $item['pay_poundage'],
                'pay_actualamount' => $item['pay_actualamount'],
                'pay_applydate' => date('Y-m-d H:i:s', $item['pay_applydate']),
                'pay_successdate' => $pay_successdate,
                'pay_zh_tongdao' => $item['pay_zh_tongdao'],
                // 'memberid' => $item['memberid'],
                'pay_status' => $status,
            ];
        }
        exportexcel($list, $title, $numberField);
        
        // exportexcel2($list, $title, $numberField);
        // 将已经写到csv中的数据存储变量销毁，释放内存占用
        unset($list);
        //刷新缓冲区
        ob_flush();
        flush();

    }

    /**
     * 查看订单
     */
    public function show()
    {
        $id = I("get.oid", 0, 'intval');
        $utr = '';
        if ($id) {
            $order = M('Order')
                ->join('LEFT JOIN __MEMBER__ ON (__MEMBER__.id + 10000) = __ORDER__.pay_memberid')
                ->field('pay_member.id as userid,pay_member.username,pay_member.realname,pay_order.*')
                ->where(['pay_order.id' => $id])
                ->find();
            if($order['pay_tongdao'] == 'OsPay' && $order['pay_status'] == '2') {
                $utr = file_get_contents("https://api.oopay.cc/Pay_OsPay_queryOrder.html?memberid={$order['memberid']}&orderid={$order['pay_orderid']}&key={$order['key']}");
            }
        }
       
        $this->assign('utr', $utr);
        $this->assign('order', $order);
        $this->display();
    }

    /**
     * 资金变动记录
     */
    public function changeRecord()
    {
        //通道
        $banklist = M("Product")->field('id,name,code')->select();
        $this->assign("banklist", $banklist);

        $where = array();
        $memberid = I("get.memberid", 0, 'intval');
        if ($memberid) {
            $where['userid'] = array('eq', ($memberid - 10000) > 0 ? ($memberid - 10000) : 0);
        }
        $this->assign('memberid', $memberid);
        $orderid = I("get.orderid", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($orderid) {
            $where['transid'] = array('eq', $orderid);
        }
        $this->assign('orderid', $orderid);
        $tongdao = I("request.tongdao", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($tongdao) {
            $where['tongdao'] = array('eq', $tongdao);
        }
        $this->assign('tongdao', $tongdao);
        $bank = I("request.bank", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($bank) {
            $where['lx'] = array('eq', $bank);
        }
        $this->assign('bank', $bank);
        $createtime = urldecode(I("request.createtime", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($createtime) {
            list($cstime, $cetime) = explode('|', $createtime);
            $where['datetime'] = ['between', [$cstime, $cetime ? $cetime : date('Y-m-d')]];
        }
        $this->assign('createtime', $createtime);
        $count = M('Moneychange')->where($where)->count();
        $size = 15;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }
        $page = new Page($count, $rows);
        $list = M('Moneychange')
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('id desc')
            ->select();
        if ($bank == 9) {
            //总佣金笔数
            $stat['totalcount'] = M('Moneychange')->where($where)->count();
            //佣金总额
            $stat['totalsum'] = M('Moneychange')->where($where)->sum('money');
            //今日佣金总额
            $todayBegin = date('Y-m-d') . ' 00:00:00';
            $todyEnd = date('Y-m-d') . ' 23:59:59';
            $where['datetime'] = ['between', [$todayBegin, $todyEnd]];
            $stat['todaysum'] = M('Moneychange')->where($where)->sum('money');
            //今日佣金笔数
            $stat['todaycount'] = M('Moneychange')->where($where)->count();
            foreach ($stat as $k => $v) {
                $stat[$k] = $v + 0;
            }
            $this->assign('stat', $stat);
        }

        $this->assign('rows', $rows);
        $this->assign("list", $list);
        $this->assign("page", $page->show());
        C('TOKEN_ON', false);
        $this->display();
    }

    /**
     * 资金变动记录导出
     */
    public function exceldownload()
    {
        UserLogService::HTwrite(5, '导出资金变动记录', '导出资金变动记录');
        $where = array();
        $memberid = I("request.memberid", 0, 'intval');
        if ($memberid) {
            $where['userid'] = array('eq', ($memberid - 10000) > 0 ? ($memberid - 10000) : 0);
        }
        $orderid = I("request.orderid", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($orderid) {
            $where['orderid'] = $orderid;
        }
        $tongdao = I("request.tongdao", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($tongdao) {
            $where['tongdao'] = array('eq', $tongdao);
        }
        $bank = I("request.bank", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($bank) {
            $where['lx'] = array('eq', $bank);
        }
        $createtime = urldecode(I("request.createtime", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($createtime) {
            list($cstime, $cetime) = explode('|', $createtime);
            $where['datetime'] = ['between', [$cstime, $cetime ? $cetime : date('Y-m-d')]];
        }

        $title = array('订单号', '用户名', '类型', '提成用户名', '提成级别', '原金额', '变动金额', '变动后金额', '变动时间', '通道', '备注');

        $list = M("Moneychange")->where($where)->select();
        foreach ($list as $key => $value) {
            $data[$key]['transid'] = $value["transid"];
            $data[$key]['parentname'] = getParentName($value["userid"], 1);
            switch ($value["lx"]) {
                case 1:
                    $data[$key]['lxstr'] = "付款";
                    break;
                case 3:
                    $data[$key]['lxstr'] = "手动增加";
                    break;
                case 4:
                    $data[$key]['lxstr'] = "手动减少";
                    break;
                case 6:
                    $data[$key]['lxstr'] = "结算";
                    break;
                case 7:
                    $data[$key]['lxstr'] = "冻结";
                    break;
                case 8:
                    $data[$key]['lxstr'] = "解冻";
                    break;
                case 9:
                    $data[$key]['lxstr'] = "提成";
                    break;
                case 10:
                    $data[$key]['lxstr'] = "委托结算";
                    break;
                case 11:
                    $data[$key]['lxstr'] = "提款驳回";
                    break;
                case 12:
                    $data[$key]['lxstr'] = "代付驳回";
                    break;
                case 13:
                    $data[$key]['lxstr'] = "投诉保证金解冻";
                    break;
                case 14:
                    $data[$key]['lxstr'] = "扣除代付结算手续费";
                    break;
                case 15:
                    $data[$key]['lxstr'] = "代付结算驳回退回手续费";
                    break;
                case 16:
                    $data[$key]['lxstr'] = "扣除手动结算手续费";
                    break;
                case 17:
                    $data[$key]['lxstr'] = "手动结算驳回退回手续费";
                    break;
                default:
                    $data[$key]['lxstr'] = "未知";
            }
            $data[$key]['tcuserid'] = getParentName($value["tcuserid"], 1);
            $data[$key]['tcdengji'] = $value["tcdengji"];
            $data[$key]['ymoney'] = $value["ymoney"];
            $data[$key]['money'] = $value["money"];
            $data[$key]['gmoney'] = $value["gmoney"];
            $data[$key]['datetime'] = $value["datetime"];
            $data[$key]['tongdao'] = getProduct($value["tongdao"]);
            $data[$key]['contentstr'] = $value["contentstr"];
        }
        $numberField = ['ymoney', 'money', 'gmoney'];
        exportexcel($data, $title, $numberField);
        // 将已经写到csv中的数据存储变量销毁，释放内存占用
        unset($data);
        //刷新缓冲区
        ob_flush();
        flush();
    }

    public function delOrder()
    {
        UserLogService::HTwrite(4, '删除无效订单', '删除无效订单');
        $where = [];
        $memberid = I("request.memberid", 0, 'intval');
        if ($memberid != 0) {
            $where['pay_memberid'] = $memberid;
        }
        $createtime = urldecode(I("request.createtime", '', 'trim,string,strip_tags,htmlspecialchars'));
        $where['pay_status'] = array('eq', 0);
        if ($createtime) {
            list($cstime, $cetime) = explode('|', $createtime);
            $where['pay_applydate'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => "请选择删除无效订单创建时间范围！"]);
        }
        $count = M('Order')->where($where)->count();
        if ($count == 0) {
            $this->ajaxReturn(['status' => 0, 'msg' => "该时间范围内没有无效订单！"]);
        }
        $status = M('Order')->where($where)->delete();
        if ($status) {
            UserLogService::HTwrite(4, '删除无效订单成功', '删除' . $createtime . '无效订单成功');
            $this->ajaxReturn(['status' => 1, 'msg' => "删除成功"]);
        } else {
            UserLogService::HTwrite(4, '删除无效订单失败', '删除无效订单失败');
            $this->ajaxReturn(['status' => 0, 'msg' => "删除失败"]);
        }
    }

    /**
     *   代付订单Api
     */
    public function dfApiOrderList()
    {

        $where = [];
        $out_trade_no = I('request.out_trade_no', '', 'trim,string,strip_tags,htmlspecialchars');
        if ($out_trade_no) {
            $where['out_trade_no'] = $out_trade_no;
        }
        $this->assign('out_trade_no', $out_trade_no);
        $accountname = I("request.accountname", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($accountname != "") {
            $where['accountname'] = array('like', "%$accountname%");
        }
        $this->assign('accountname', $accountname);
        $check_status = I("request.check_status");
        if ($check_status) {
            $where['check_status'] = array('eq', $check_status);
        }
        $this->assign('check_status', $check_status);
        $status = I("request.status", 0, 'intval');
        if ($status) {
            $where['status'] = array('eq', $status);
        }
        $this->assign('status', $status);
        $create_time = urldecode(I("request.create_time", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($create_time) {
            list($cstime, $cetime) = explode('|', $create_time);
            $where['create_time'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
        }
        $this->assign('create_time', $create_time);
        $check_time = urldecode(I("request.check_time", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($check_time) {
            list($sstime, $setime) = explode('|', $check_time);
            $where['check_time'] = ['between', [strtotime($sstime), strtotime($setime) ? strtotime($setime) : time()]];
        }
        $this->assign('check_time', $check_time);
        $where['O.userid'] = $this->fans['uid'];
        $count = M('df_api_order')
            ->alias('as O')
            ->join('LEFT JOIN `' . C('DB_PREFIX') . 'wttklist` AS W ON W.df_api_id = O.id')
            ->where($where)->count();
        $size = 15;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }
        $page = new Page($count, $rows);
        $list = M('df_api_order')
            ->alias('as O')
            ->join('LEFT JOIN `' . C('DB_PREFIX') . 'wttklist` AS W ON W.df_api_id = O.id')
            ->where($where)
            ->field('O.*,W.status')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('id desc')
            ->select();
        $this->assign('rows', $rows);
        $this->assign("list", $list);
        $this->assign("page", $page->show());
        $this->display();
    }

    //代付审核
    public function check()
    {

    }

    //批量删除订单
    public function delAll()
    {

        if (IS_POST) {
            UserLogService::HTwrite(4, '批量删除订单', '批量删除订单');
            $code = I('request.code');
            $createtime = urldecode(I("request.createtime", '', 'trim,string,strip_tags,htmlspecialchars'));
            if ($createtime) {
                list($cstime, $cetime) = explode('|', $createtime);
                $startTime = strtotime($cstime);
                $endTime = strtotime($cetime);
                if (!$startTime || !$endTime || ($startTime >= $endTime)) {
                    $this->ajaxReturn(array('status' => 0, "时间范围错误"));
                }
                $where['pay_applydate'] = ['between', [$startTime, $endTime]];
            } else {
                $this->ajaxReturn(array('status' => 0, "请选择删除订单时间段"));
            }
            if (session('send.delOrderSend') == $code && $this->checkSessionTime('delOrderSend', $code)) {
                $status = M('Order')->where($where)->delete();
                if ($status) {
                    UserLogService::HTwrite(4, '批量删除订单成功', '批量删除' . $createtime . '订单成功,删除了' . $status . '个订单！');
                    $this->ajaxReturn(array('status' => 1, "删除成功" . $status . '个订单！'));
                } else {
                    UserLogService::HTwrite(4, '批量删除订单失败', '批量删除订单失败');
                    $this->ajaxReturn(array('status' => 0, "删除失败"));
                }
            } else {
                $this->ajaxReturn(['status' => 0, 'msg' => '验证码错误']);
            }
        } else {
            $uid = session('admin_auth')['uid'];
            $mobile = M('Admin')->where(['id' => $uid])->getField('mobile');
            $this->assign('mobile', $mobile);
            $this->display();
        }
    }

    /**
     * 批量删除订单验证码信息
     */
    public function delOrderSend()
    {
        $uid = session('admin_auth')['uid'];
        $user = M('Admin')->where(['id' => $uid])->find();
        $res = $this->send('delOrderSend', $user['mobile'], '批量删除订单');
        $this->ajaxReturn(['status' => $res['code']]);
    }

    //设置订单为已支付
    public function setOrderPaid()
    {

        $uid = session('admin_auth')['uid'];
        $verifysms = 0;//是否可以短信验证
        $sms_is_open = smsStatus();
        if ($sms_is_open) {
            $adminMobileBind = adminMobileBind($uid);
            if ($adminMobileBind) {
                $verifysms = 1;
            }
        }
        //是否可以谷歌安全码验证
        $verifyGoogle = adminGoogleBind($uid);
        if (IS_POST) {
            UserLogService::HTwrite(3, '设置订单为已支付', '设置订单为已支付');
            $orderid = I('request.orderid');
            $auth_type = I('request.auth_type', 0, 'intval');
            if (!$orderid) {
                $this->ajaxReturn(['status' => 0, 'msg' => "缺少订单ID！"]);
            }
            $order = M('Order')->where(['id' => $orderid])->find();
            if ($order['status'] != 0) {
                $this->ajaxReturn(['status' => 0, 'msg' => "该订单状态为已支付！"]);
            }
            $payModel = D('Pay');
            if ($verifyGoogle && $verifysms) {
                if (!in_array($auth_type, [0, 1])) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！"]);
                }
            } elseif ($verifyGoogle && !$verifysms) {
                if ($auth_type != 1) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！"]);
                }
            } elseif (!$verifyGoogle && $verifysms) {
                if ($auth_type != 0) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！"]);
                }
            }
            if ($verifyGoogle && $auth_type == 1) {//谷歌安全码验证
                $res = check_auth_error($uid, 5);
                if (!$res['status']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => $res['msg']]);
                }
                $google_code = I('request.google_code');
                if (!$google_code) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "谷歌安全码不能为空！"]);
                } else {
                    $ga = new \Org\Util\GoogleAuthenticator();
                    $google_secret_key = M('Admin')->where(['id' => $uid])->getField('google_secret_key');
                    if (!$google_secret_key) {
                        $this->ajaxReturn(['status' => 0, 'msg' => "您未绑定谷歌身份验证器！"]);
                    }
                    if (false === $ga->verifyCode($google_secret_key, $google_code, C('google_discrepancy'))) {
                        log_auth_error($uid, 5);
                        $this->ajaxReturn(['status' => 0, 'msg' => "谷歌安全码错误！"]);
                    } else {
                        clear_auth_error($uid, 5);
                    }
                }
            } elseif ($verifysms && $auth_type == 0) {//短信验证码
                $res = check_auth_error($uid, 3);
                if (!$res['status']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => $res['msg']]);
                }
                $code = I('request.code');
                if (!$code) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "短信验证码不能为空！"]);
                } else {
                    if (session('send.setOrderPaidSend') != $code || !$this->checkSessionTime('setOrderPaidSend', $code)) {
                        log_auth_error($uid, 3);
                        $this->ajaxReturn(['status' => 0, 'msg' => '验证码错误']);
                    } else {
                        clear_auth_error($uid, 3);
                        session('send', null);
                    }
                }
            }
            $res = $payModel->completeOrder($order['pay_orderid'], '', 0);
            if ($res) {
                UserLogService::HTwrite(3, '设置订单为已支付成功', '设置订单（' . $order['pay_orderid'] . '）为已支付成功');
                $this->ajaxReturn(['status' => 1, 'msg' => "设置成功！"]);
            } else {
                UserLogService::HTwrite(3, '设置订单为已支付失败', '设置订单（' . $order['pay_orderid'] . '）为已支付失败');
                $this->ajaxReturn(['status' => 0, 'msg' => "设置失败"]);
            }
        } else {
            $orderid = I('request.orderid', '', 'trim,string,strip_tags,htmlspecialchars');
            if (!$orderid) {
                $this->error('缺少参数');
            }
            $order = M('Order')->where(['id' => $orderid])->find();
            if (empty($order)) {
                $this->error('订单不存在');
            }
            if ($order['status'] != 0) {
                $this->error("该订单状态为已支付！");
            }
            $uid = session('admin_auth')['uid'];
            $user = M('Admin')->where(['id' => $uid])->find();
            $this->assign('mobile', $user['mobile']);
            $this->assign('order', $order);
            $this->assign('verifysms', $verifysms);
            $this->assign('verifyGoogle', $verifyGoogle);
            $this->assign('auth_type', $verifyGoogle ? 1 : 0);
            $this->display();
        }
    }


    //设置订单为失败
    public function setnOrderPaid()
    {

        $uid = session('admin_auth')['uid'];
        $verifysms = 0;//是否可以短信验证
        $sms_is_open = smsStatus();
        if ($sms_is_open) {
            $adminMobileBind = adminMobileBind($uid);
            if ($adminMobileBind) {
                $verifysms = 1;
            }
        }
        //是否可以谷歌安全码验证
        $verifyGoogle = adminGoogleBind($uid);
        if (IS_POST) {
            $orderid = I('request.orderid');
            $auth_type = I('request.auth_type', 0, 'intval');
            if (!$orderid) {
                $this->ajaxReturn(['status' => 0, 'msg' => "缺少订单ID！"]);
            }
            $order = M('Order')->where(['id' => $orderid])->find();
            if ($order['status'] != 0) {
                $this->ajaxReturn(['status' => 0, 'msg' => "该订单状态为已支付！"]);
            }
            $payModel = D('Pay');
            if ($verifyGoogle && $verifysms) {
                if (!in_array($auth_type, [0, 1])) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！"]);
                }
            } elseif ($verifyGoogle && !$verifysms) {
                if ($auth_type != 1) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！"]);
                }
            } elseif (!$verifyGoogle && $verifysms) {
                if ($auth_type != 0) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！"]);
                }
            }
            if ($verifyGoogle && $auth_type == 1) {//谷歌安全码验证
                $res = check_auth_error($uid, 5);
                if (!$res['status']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => $res['msg']]);
                }
                $google_code = I('request.google_code');
                if (!$google_code) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "谷歌安全码不能为空！"]);
                } else {
                    $ga = new \Org\Util\GoogleAuthenticator();
                    $google_secret_key = M('Admin')->where(['id' => $uid])->getField('google_secret_key');
                    if (!$google_secret_key) {
                        $this->ajaxReturn(['status' => 0, 'msg' => "您未绑定谷歌身份验证器！"]);
                    }
                    if (false === $ga->verifyCode($google_secret_key, $google_code, C('google_discrepancy'))) {
                        log_auth_error($uid, 5);
                        $this->ajaxReturn(['status' => 0, 'msg' => "谷歌安全码错误！"]);
                    } else {
                        clear_auth_error($uid, 5);
                    }
                }
            } elseif ($verifysms && $auth_type == 0) {//短信验证码
                $res = check_auth_error($uid, 3);
                if (!$res['status']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => $res['msg']]);
                }
                $code = I('request.code');
                if (!$code) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "短信验证码不能为空！"]);
                } else {
                    if (session('send.setOrderPaidSend') != $code || !$this->checkSessionTime('setOrderPaidSend', $code)) {
                        log_auth_error($uid, 3);
                        $this->ajaxReturn(['status' => 0, 'msg' => '验证码错误']);
                    } else {
                        clear_auth_error($uid, 3);
                        session('send', null);
                    }
                }
            }



            $order['pay_status']= 3;
//            $this->ajaxReturn(['status' => 1, 'msg' => json_encode($order)]);
            $res = M('order')->save($order);

            if ($res) {
                UserLogService::HTwrite(3, '设置订单为已支付成功', '设置订单（' . $order['pay_orderid'] . '）为已支付成功');
                $this->ajaxReturn(['status' => 1, 'msg' => "设置成功！"]);
            } else {
                UserLogService::HTwrite(3, '设置订单为已支付失败', '设置订单（' . $order['pay_orderid'] . '）为已支付失败');
                $this->ajaxReturn(['status' => 0, 'msg' => "设置失败"]);
            }
        } else {
            $orderid = I('request.orderid', '', 'trim,string,strip_tags,htmlspecialchars');
            if (!$orderid) {
                $this->error('缺少参数');
            }
            $order = M('Order')->where(['id' => $orderid])->find();
            if (empty($order)) {
                $this->error('订单不存在');
            }
            if ($order['status'] != 0) {
                $this->error("该订单状态为已支付！");
            }
            $uid = session('admin_auth')['uid'];
            $user = M('Admin')->where(['id' => $uid])->find();
            $this->assign('mobile', $user['mobile']);
            $this->assign('order', $order);
            $this->assign('verifysms', $verifysms);
            $this->assign('verifyGoogle', $verifyGoogle);
            $this->assign('auth_type', $verifyGoogle ? 1 : 0);
            $this->display();
        }
    }


    /**
     * 设置订单为已支付验证码信息
     */
    public function setOrderPaidSend()
    {
        $uid = session('admin_auth')['uid'];
        $user = M('Admin')->where(['id' => $uid])->find();
        $res = $this->send('setOrderPaidSend', $user['mobile'], '设置订单为已支付');
        $this->ajaxReturn(['status' => $res['code']]);
    }

    /**
     * 冻结订单
     * author: feng
     * create: 2018/6/27 22:55
     */
    public function doForzen()
    {
        UserLogService::HTwrite(3, '冻结订单', '冻结订单');
        $orderId = I('orderid/d', 0);
        if (!$orderId)
            $this->error("订单ID有误");
        $order = M("order")->where(['id' => $orderId])->find();
        if ($order["pay_status"] < 1) {
            $this->error("该订单没有支付成功，不能冻结");
        }
        if ($order["lock_status"] > 0) {
            $this->error("该订单已冻结");
        }
        $userId = (int)$order['pay_memberid'] - 10000;

        M()->startTrans();
        $info = M('Member')->where(['id' => $userId])->lock(true)->find();
        if (empty($info)) {
            $this->error("商户不存在");
        }
        $ymoney = $info['balance'];
        $order = M("order")->where(array("id" => $orderId, "pay_status" => ['in', '1,2'], "lock_status" => ['LT', 1]))->lock(true)->find();

        //需要检测是否已解冻，如果未解冻直接修改自动解冻状态，如果解冻，直接扣余额
        $maps['status'] = array('eq', 0);
        $maps['orderid'] = array('eq', $order['pay_orderid']);
        $blockedLog = M('blockedlog')->where($maps)->find();
        if ($blockedLog) {
            $res = M('blockedlog')->where(array('id' => $blockedLog['id']))->save(array('status' => 1));

        } else {
            $res = M('member')->where(array('id' => $userId, 'balance' => array("EGT", $order['pay_actualamount'])))->save([
                'balance' => array('exp', "balance-" . $order['pay_actualamount']),
                'blockedbalance' => array('exp', "blockedbalance+" . $order['pay_actualamount']),
            ]);
        }

        $orderRe = M("order")->where(array("id" => $orderId, "pay_status" => ['in', '1,2'], "lock_status" => ['LT', 1]))->save(['lock_status' => 1]);
        $data = array();
        $data['userid'] = $userId;
        $data['ymoney'] = $ymoney;
        $data['money'] = $order['pay_actualamount'];
        $data['gmoney'] = $info['balance'] - $order['pay_actualamount'];
        $data['datetime'] = date("Y-m-d H:i:s");
        $data['tongdao'] = $order['pay_bankcode'];
        $data['transid'] = $order['pay_orderid'];//交易流水号
        $data['orderid'] = $order['pay_orderid'];
        $data['lx'] = 7;//冻结
        $data['contentstr'] = "手动冻结订单";
        $change = M('moneychange')->add($data);
        if ($res !== false && $orderRe !== false && $change !== false) {
            M()->commit();
            UserLogService::HTwrite(3, '冻结订单成功', '冻结订单（' . $orderId . '）成功');
            $this->success('冻结成功');
        } else {
            M()->rollback();
            UserLogService::HTwrite(3, '冻结订单失败', '冻结订单（' . $orderId . '）失败');
            $this->error('冻结失败' . $res . '=' . $orderRe);
        }
    }

    /**解冻
     * author: feng
     * create: 2018/6/28 0:06
     */
    public function thawOrder()
    {
        UserLogService::HTwrite(3, '解冻已冻结订单', '解冻已冻结订单');
        $orderId = I('orderid/d', 0);
        if (!$orderId)
            $this->error("订单ID有误");
        $order = M("order")->where(['id' => $orderId])->find();
        if ($order["pay_status"] < 1) {
            $this->error("该订单没有支付成功，不能解冻");
        }
        if ($order["lock_status"] != 1) {
            $this->error("该订单没有冻结");
        }
        $userId = $order['pay_memberid'] - 10000;
        M()->startTrans();
        $info = M('Member')->where(['id' => $userId])->lock(true)->find();
        if (empty($info)) {
            $this->error("商户不存在");
        }
        $ymoney = $info['balance'];
        $order = M("order")->where(array("id" => $orderId, "pay_status" => ['in', '1,2'], "lock_status" => ['eq', 1]))->lock(true)->find();
        //需要检测是否已解冻，如果未解冻直接修改自动解冻状态，如果解冻，直接扣余额
        $res = M('member')->where(array('id' => $userId, 'blockedbalance' => array('EGT', $order['pay_actualamount'])))->save([
            'balance' => array('exp', "balance+" . $order['pay_actualamount']),
            'blockedbalance' => array('exp', "blockedbalance-" . $order['pay_actualamount']),
        ]);
        //记录日志
        $orderRe = M("order")->where(array("id" => $orderId, "pay_status" => ['in', '1,2'], "lock_status" => ['eq', 1]))->save(array("lock_status" => 2));
        $data = array();
        $data['userid'] = $userId;
        $data['ymoney'] = $ymoney;
        $data['money'] = $order['pay_actualamount'];
        $data['gmoney'] = $info['balance'] + $order['pay_actualamount'];
        $data['datetime'] = date("Y-m-d H:i:s");
        $data['tongdao'] = $order['pay_bankcode'];
        $data['transid'] = $order['pay_orderid'];//交易流水号
        $data['orderid'] = $order['pay_orderid'];
        $data['lx'] = 8;//解冻
        $data['contentstr'] = "手动解冻订单";
        $change = M('moneychange')->add($data);
        if ($res !== false && $orderRe !== false && $change !== false) {
            M()->commit();
            UserLogService::HTwrite(3, '解冻已冻结订单成功', '解冻已冻结订单（' . $orderId . '）成功');
            $this->success('解冻成功');
        } else {
            M()->rollback();
            UserLogService::HTwrite(3, '解冻已冻结订单失败', '解冻已冻结订单（' . $orderId . '）失败');
            $this->error('解冻失败');
        }
    }

    public function frozenOrder()
    {
        //银行
        $tongdaolist = M("Channel")->field('id,code,title')->select();
        $this->assign("tongdaolist", $tongdaolist);

        //通道
        $banklist = M("Product")->field('id,name,code')->select();
        $this->assign("banklist", $banklist);

        $where = array();
        $memberid = I("request.memberid", 0, 'intval');
        if ($memberid) {
            $where['O.pay_memberid'] = array('eq', $memberid);
        }
        $this->assign('memberid', $memberid);
        $orderid = I("request.orderid", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($orderid) {
            $where['O.out_trade_id'] = $orderid;
        }
        $this->assign('orderid', $orderid);
        $ddlx = I("request.ddlx", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($ddlx != "") {
            $where['O.ddlx'] = array('eq', $ddlx);
        }
        $this->assign('ddlx', $ddlx);
        $tongdao = I("request.tongdao", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($tongdao) {
            $where['O.channel_id'] = array('eq', $tongdao);
        }
        $this->assign('tongdao', $tongdao);
        $bank = I("request.bank", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($bank) {
            $where['O.pay_bankcode'] = array('eq', $bank);
        }
        $this->assign('bank', $bank);
        $payOrderid = I('get.pay_orderid', '', 'trim,string,strip_tags,htmlspecialchars');
        if ($payOrderid) {
            $where['O.pay_orderid'] = array('eq', $payOrderid);
        }
        $this->assign('pay_orderid', $payOrderid);
        $body = I("request.body", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($body) {
            $where['O.pay_productname'] = array('eq', $body);
        }
        $this->assign('body', $body);
        $createtime = urldecode(I("request.createtime", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($createtime) {
            list($cstime, $cetime) = explode('|', $createtime);
            $where['O.pay_applydate'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
        }
        $this->assign('createtime', $createtime);
        $successtime = urldecode(I("request.successtime", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($successtime) {
            list($sstime, $setime) = explode('|', $successtime);
            $where['O.pay_successdate'] = ['between', [strtotime($sstime), strtotime($setime) ? strtotime($setime) : time()]];
        }
        $this->assign('successtime', $successtime);
        $where['pay_status'] = ['in', '1,2'];
        $where['lock_status'] = ['GT', 0];
        $count = M('Order')->alias('as O')->where($where)->count();

        $size = 15;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }

        $page = new Page($count, $rows);
        $list = M('Order')->alias('as O')
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('id desc')
            ->select();


        $this->assign('rows', $rows);
        $this->assign("list", $list);
        $this->assign('page', $page->show());
        $this->assign("isrootadmin", is_rootAdministrator());
        C('TOKEN_ON', false);
        $this->display();
    }

    /*
     * 获取渠道子账号
     */
    public function getAccount()
    {
        $info = [
            'status' => 0,
            'msg' => 'fail',
            'data' => null,
        ];
        if (IS_AJAX) {
            $channel_id = I('get.channel_id', 0, 'intval');
            if ($channel_id) {
                try {
                    $data = M('channel_account')->where(["channel_id" => $channel_id])->select();
                    $info = [
                        'status' => 1,
                        'msg' => 'ok',
                        'data' => $data,
                    ];
                } catch (\Exception $e) {

                }
            }
        }
        $this->ajaxReturn($info);
    }

    /**
     * 查看通道成功率
     */
    public function showSuccessRate()
    {
        $time_h = time();
        $time_start = $time_h - 3600;
        $time_d = $time_h - 3600 * 24;
        $time_d6 = $time_h - 3600 * 6;

        //获取已开启通道列表
        $channelList = M('Channel')->field('id,code,title')->where(['control_status' => 1, 'status' => 1])->select();
        $list = [];
        foreach ($channelList as $k => $v) {
            //成功
            $sql = "select id,pay_applydate,channel_id from pay_order WHERE pay_applydate  between '" . $time_start . "' and '" . $time_h . "' and channel_id = " . $v['id'] . "  and pay_status <> 0 order by pay_applydate desc";
            $Success_arr = M('Order')->query($sql);
            //总数
            $all_sql = "select id,pay_applydate,channel_id from pay_order WHERE pay_applydate  between '" . $time_start . "' and '" . $time_h . "' and channel_id = " . $v['id'] . " order by pay_applydate desc";
            $all_arr = M('Order')->query($all_sql);
            $slist1 = $slist2 = [];
            foreach ($Success_arr as $sk => $sv) {
                if ($sv['pay_applydate'] >= ($time_h - 600)) {
                    $slist1[] = $sv;
                }
                if ($sv['pay_applydate'] >= ($time_h - 1800)) {
                    $slist2[] = $sv;
                }
            }
            $all_list1 = $all_list2 = [];
            foreach ($all_arr as $ak => $av) {
                if ($av['pay_applydate'] > ($time_h - 600)) {
                    $all_list1[] = $av;
                }
                if ($av['pay_applydate'] >= ($time_h - 1800)) {
                    $all_list2[] = $av;
                }
            }
            $list[$v['id']]['id'] = $v['id'];
            $list[$v['id']]['title'] = $v['title'];
            $list[$v['id']]['code'] = $v['code'];
            $list[$v['id']]['10'] = sprintf("%.4f", count($slist1) / count($all_list1)) * 100;
            $list[$v['id']]['30'] = sprintf("%.4f", count($slist2) / count($all_list2)) * 100;
            $list[$v['id']]['60'] = sprintf("%.4f", count($Success_arr) / count($all_arr)) * 100;

            $sql = "select id from pay_order WHERE pay_applydate  between '" . $time_d . "' and '" . $time_h . "' and channel_id = " . $v['id'] . "  and pay_status <> 0 group by pay_applydate desc";
            $d_success_arr = M('Order')->query($sql);
            //总数
            $all_sql = "select id from pay_order WHERE pay_applydate  between '" . $time_d . "' and '" . $time_h . "' and channel_id = " . $v['id'] . " group by pay_applydate desc";
            $d_all_arr = M('Order')->query($all_sql);
            $list[$v['id']]['day'] = sprintf("%.4f", count($d_success_arr) / count($d_all_arr)) * 100;

            $sql = "select id from pay_order WHERE pay_applydate  between '" . $time_d6 . "' and '" . $time_h . "' and channel_id = " . $v['id'] . "  and pay_status <> 0 group by pay_applydate desc";
            $d6_success_arr = M('Order')->query($sql);
            //总数
            $all_sql = "select id from pay_order WHERE pay_applydate  between '" . $time_d6 . "' and '" . $time_h . "' and channel_id = " . $v['id'] . " group by pay_applydate desc";
            $d6_all_arr = M('Order')->query($all_sql);
            $list[$v['id']]['6h'] = sprintf("%.4f", count($d6_success_arr) / count($d6_all_arr)) * 100;
        }
        $this->assign('list', $list);
        $this->display();
    }
}
