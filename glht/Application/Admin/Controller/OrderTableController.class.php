<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-04-02
 * Time: 23:01
 */

namespace Admin\Controller;

use Think\Page;

/**
 * 订单管理控制器
 * Class OrderController
 * @package Admin\Controller
 */
class OrderTableController extends BaseController
{
    const TMT = 7776000; //三个月的总秒数

    public function __construct()
    {
        parent::__construct();
    }

    public function AgentTable()
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
            $todaysumMap['pay_memberid'] = $monthsumMap['pay_memberid'] = $nopaidsumMap['pay_memberid'] = $monthNopaidsumMap['pay_memberid'] = array('eq', $memberid);
            $profitSumMap['userid'] = $profitMap['userid'] = $profitSumMap['userid'] = $cjsxfMap['userid'] = $cjlrMap['userid'] = $memberid - 10000;
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

        $createtime = urldecode(I("request.createtime", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($createtime) {
            list($cstime, $cetime) = explode('|', $createtime);
            $where['O.pay_applydate'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
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


        if ($memberid) {
            $where1['id'] = $memberid - 10000;
        }
        $where1['groupid'] = array('gt', 4);
        $count = M('member')->where($where1)->count();
        $size = 15;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }

        $page = new Page($count, $rows);
        $list = M('member')
            ->where($where1)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('id desc')
            ->select();


        foreach ($list as $key => $value) {


            $list[$key]['memberid'] = $value['id'] + 10000;
            //查询下游
            //查询下游
            $under = M('member')->where(array('parentid' => $value['id']))->select();
            foreach ($under as $k => $v) {


                $where['O.pay_memberid'] = $v['id'] + 10000;

                unset($sum);
                //查询支付成功的订单的手续费，入金费，总额总和
                $countWhere = $where;
                $countWhere['O.pay_status'] = ['between', [1, 2]];
                $field = ['sum(`pay_amount`) pay_amount', 'sum(`cost`) cost', 'sum(`pay_poundage`) pay_poundage', 'sum(`pay_actualamount`) pay_actualamount', 'count(`id`) success_count'];
                $sum = M('Order')->alias('as O')->field($field)->where($countWhere)->find();
                $countWhere['O.pay_status'] = 0;
                //失败笔数
                $sum['fail_count'] = M('Order')->alias('as O')->where($countWhere)->count();
                //投诉保证金冻结金额


                //print_r($where);die();
                $profitMap = $where;
                unset($profitMap['pay_memberid']);
                unset($profitMap['O.pay_memberid']);

                $profitMap['C.lx'] = 9;
                $profitMap['C.tcuserid'] = $v['id'];

                $sum['memberprofit'] = M('moneychange')->alias('as C')->join('LEFT JOIN __ORDER__ AS O ON C.transid=O.pay_orderid')
                    ->where($profitMap)->sum('money');
                $sum['costcost'] = M('moneychange')->alias('as C')->join('LEFT JOIN __ORDER__ AS O ON C.transid=O.pay_orderid')
                    ->where($profitMap)->sum('cost');


                //构造累加商户交易额等
                $list[$key]['pay_amount'] += sprintf("%.3f", $sum['pay_amount']);
                $list[$key]['pay_actualamount'] += sprintf("%.3f", $sum['pay_actualamount']);
                $list[$key]['pay_feilv'] += sprintf("%.3f", $sum['pay_amount'] - $sum['pay_actualamount']);

                $list[$key]['memberprofit'] += sprintf("%.3f", $sum['memberprofit']);
                $list[$key]['pay_poundage'] += sprintf("%.3f", $sum['pay_amount'] - $sum['pay_actualamount'] - $sum['memberprofit'] - $sum['costcost']);

                $list[$key]['success_count'] += sprintf("%.3f", $sum['success_count']);
                $list[$key]['fail_count'] += sprintf("%.3f", $sum['fail_count']);
                $list[$key]['costcost'] += sprintf("%.3f", $sum['costcost']);

                if (!$list[$key]['fail_count']) {
                    $list[$key]['success_rate'] = 0;
                } else {
                    $list[$key]['success_rate'] = sprintf("%.3f", $sum['success_count'] / ($sum['success_count'] + $sum['fail_count']));
                }

                //构造每个下级商户
                $list[$key]['under'][] = '[' . $v['username'] . '] 交易额：' . sprintf("%.3f", $sum['pay_amount']) . ' 代理分润：' . sprintf("%.3f", $sum['memberprofit']) . ' 平台分润：' . sprintf("%.3f", $sum['pay_amount'] - $sum['pay_actualamount'] - $sum['memberprofit'] - $sum['costcost']);


            }


// print_r($where);die();
            //       <blockquote class="layui-elem-quote" style="font-size:14px;padding;8px;">成功交易总金额：<span class="label stat_success"><{$stamount}>元</span> 平台利润：<span class="label stat_success"><{$strate}>元</span>
            //   代理收入：<span class="label stat_success"><{$memberprofit}>元</span> 商户收入总金额：<span class="label stat_success"><{$strealmoney}>元</span> 成功订单数：<span class="label stat_success"><{$success_count}></span> 失败订单数：<span class="label stat_fail"><{$fail_count}></span>
            //   投诉保证金已返回金额：<span class="label stat_success"><{$complaints_deposit_unfreezed}></span> 投诉保证金冻结金额：<span class="label stat_fail"><{$complaints_deposit_freezed}></span>
            // </blockquote>


            $all['pay_amount'] += $list[$key]['pay_amount'];
            $all['pay_actualamount'] += $list[$key]['pay_actualamount'];
            $all['pay_feilv'] += $list[$key]['pay_feilv'];
            $all['memberprofit'] += $list[$key]['memberprofit'];
            $all['pay_poundage'] += $list[$key]['pay_poundage'];
            $all['success_count'] += $list[$key]['success_count'];
            $all['fail_count'] += $list[$key]['fail_count'];
            $all['costcost'] += $list[$key]['costcost'];
        }


        // foreach ($sum as $k => $v) {
        //     $sum[$k] += 0;
        // }


        // foreach($stat as $k => $v) {
        //     $stat[$k] = $v+0;
        // }
        $this->assign('stat', $stat);
        $this->assign('rows', $rows);
        $this->assign("list", $list);

        $this->assign('stamount', $all['pay_amount']);
        $this->assign('page', $page->show());
        $this->assign('strate', $all['pay_poundage']);
        $this->assign('strealmoney', $all['pay_actualamount']);
        $this->assign('success_count', $all['success_count']);
        $this->assign('fail_count', $all['fail_count']);
        $this->assign('memberprofit', $all['memberprofit']);
        $this->assign('complaints_deposit_freezed', $sum['complaints_deposit_freezed']);
        $this->assign('complaints_deposit_unfreezed', $sum['complaints_deposit_unfreezed']);
        $this->assign("isrootadmin", is_rootAdministrator());
        C('TOKEN_ON', false);
        $this->display();
    }

    /**
     * 商户报表
     */
    public function MerchantTable()
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
            $todaysumMap['pay_memberid'] = $monthsumMap['pay_memberid'] = $nopaidsumMap['pay_memberid'] = $monthNopaidsumMap['pay_memberid'] = array('eq', $memberid);
            $profitSumMap['userid'] = $profitMap['userid'] = $profitSumMap['userid'] = $cjsxfMap['userid'] = $cjlrMap['userid'] = $memberid - 10000;
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

        $createtime = urldecode(I("request.createtime", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($createtime) {
            list($cstime, $cetime) = explode('|', $createtime);
            $where['O.pay_applydate'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
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


        if ($memberid) {
            $where1['id'] = $memberid - 10000;
        }
        $where1['groupid'] = 4;
        $count = M('member')->where($where1)->count();
        $size = 15;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }

        $page = new Page($count, $rows);
        $list = M('member')
            ->where($where1)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('id desc')
            ->select();


        foreach ($list as $key => $value) {


            $where['O.pay_memberid'] = $list[$key]['memberid'] = $value['id'] + 10000;

            //查询支付成功的订单的手续费，入金费，总额总和
            $countWhere = $where;
            $countWhere['O.pay_status'] = ['between', [1, 2]];
            $field = ['sum(`pay_amount`) pay_amount', 'sum(`cost`) cost', 'sum(`pay_poundage`) pay_poundage', 'sum(`pay_actualamount`) pay_actualamount', 'count(`id`) success_count'];
            $sum = M('Order')->alias('as O')->field($field)->where($countWhere)->find();
            $countWhere['O.pay_status'] = 0;
            //失败笔数
            $sum['fail_count'] = M('Order')->alias('as O')->where($countWhere)->count();
            //投诉保证金冻结金额


            //print_r($where);die();
            $profitMap = $where;
            unset($profitMap['pay_memberid']);
            unset($profitMap['O.pay_memberid']);

            $profitMap['C.lx'] = 9;
            $profitMap['C.tcuserid'] = $value['id'];

            $sum['memberprofit'] = M('moneychange')->alias('as C')->join('LEFT JOIN __ORDER__ AS O ON C.transid=O.pay_orderid')
                ->where($profitMap)->sum('money');
            $sum['costcost'] = M('moneychange')->alias('as C')->join('LEFT JOIN __ORDER__ AS O ON C.transid=O.pay_orderid')
                ->where($profitMap)->sum('cost');
            //        $sum['memberprofit'] = M('moneychange')->alias('as C')->join('LEFT JOIN __ORDER__ AS O ON C.transid=O.pay_orderid')
            //        ->where($profitMap)->find();
            // print_r($sum['memberprofit']);die();

            //构造每个商户
            $list[$key]['pay_amount'] = sprintf("%.3f", $sum['pay_amount']);
            $list[$key]['pay_actualamount'] = sprintf("%.3f", $sum['pay_actualamount']);
            $list[$key]['pay_feilv'] = sprintf("%.3f", $sum['pay_amount'] - $sum['pay_actualamount']);

            $list[$key]['memberprofit'] = sprintf("%.3f", $sum['memberprofit']);
            $list[$key]['pay_poundage'] = sprintf("%.3f", $list[$key]['pay_feilv'] - $sum['memberprofit'] - $sum['costcost']);

            $list[$key]['success_count'] = sprintf("%.3f", $sum['success_count']);
            $list[$key]['fail_count'] = sprintf("%.3f", $sum['fail_count']);
            $list[$key]['costcost'] = sprintf("%.3f", $sum['costcost']);

            if (!$list[$key]['fail_count']) {
                $list[$key]['success_rate'] = 0;
            } else {
                $list[$key]['success_rate'] = sprintf("%.3f", $sum['success_count'] / ($sum['success_count'] + $sum['fail_count']));
            }

            $all['pay_amount'] += $list[$key]['pay_amount'];
            $all['pay_actualamount'] += $list[$key]['pay_actualamount'];
            $all['pay_feilv'] += $list[$key]['pay_feilv'];
            $all['memberprofit'] += $list[$key]['memberprofit'];
            $all['pay_poundage'] += $list[$key]['pay_poundage'];
            $all['success_count'] += $list[$key]['success_count'];
            $all['fail_count'] += $list[$key]['fail_count'];
            $all['costcost'] += $list[$key]['costcost'];
        }


        // foreach ($sum as $k => $v) {
        //     $sum[$k] += 0;
        // }


        // foreach($stat as $k => $v) {
        //     $stat[$k] = $v+0;
        // }
        $this->assign('stat', $stat);
        $this->assign('rows', $rows);
        $this->assign("list", $list);

        $this->assign('stamount', $all['pay_amount']);
        $this->assign('page', $page->show());
        $this->assign('strate', $all['pay_poundage']);
        $this->assign('strealmoney', $all['pay_actualamount']);
        $this->assign('success_count', $all['success_count']);
        $this->assign('fail_count', $all['fail_count']);
        $this->assign('memberprofit', $all['memberprofit']);
        $this->assign('complaints_deposit_freezed', $sum['complaints_deposit_freezed']);
        $this->assign('complaints_deposit_unfreezed', $sum['complaints_deposit_unfreezed']);
        $this->assign("isrootadmin", is_rootAdministrator());
        C('TOKEN_ON', false);
        $this->display();
    }

    public function SuccessRate()
    {
        $memberid = I("request.uid", 0, 'intval');
        if (!$memberid) $this->ajaxReturn(['status' => 0, 'date' => '请输入商户号']);
        $memberid = $memberid + 10000;

        $list_h = $all_h = [];
        $time_h = strtotime(date("Y-m-d H", time()) . ":00:00") + 3600;
        $time_start = strtotime(date("Y-m-d H", $time_h - 24 * 3600) . ":00:00");
        //总数
        $all_sql = "select COUNT(id) as num,DATE_FORMAT(from_unixtime(pay_applydate), '%H') as time from pay_order WHERE unix_timestamp(DATE_FORMAT(from_unixtime(pay_applydate), '%Y-%m-%d %H'))  between '" . $time_start . "' and '" . $time_h . "' and pay_memberid= " . $memberid . " group by DATE_FORMAT(from_unixtime(pay_applydate), '%Y-%m-%d %H')";
        $all_arr = M('Order')->query($all_sql);
        foreach ($all_arr as $av) {
            $all_h[$av['time']] = $av['num'];
        }
        //成功
        $sql = "select sum(`pay_amount`) amount, sum(`pay_poundage`) poundage, sum(`pay_actualamount`) actualamount,sum(`cost`) cost, count(`id`) Success_count,DATE_FORMAT(from_unixtime(pay_applydate), '%H') as time from pay_order WHERE unix_timestamp(DATE_FORMAT(from_unixtime(pay_applydate), '%Y-%m-%d %H'))  between '" . $time_start . "' and '" . $time_h . "'and pay_status between 1 and 2 and pay_memberid= " . $memberid . " group by DATE_FORMAT(from_unixtime(pay_applydate), '%Y-%m-%d %H')";
        $Success_arr = M('Order')->query($sql);
        foreach ($Success_arr as $sk => $sv) {
            $Success_h[$sv['time']] = $sv;
            $time = $Success_arr[$sk]['time'] + 1;
            if ($time == 24) {
                $time = "00";
            }
            if ($Success_arr[$sk + 1]['time'] != $time) {
                $Success_h[$sv['time'] + 1]['time'] = $time;
            }
        }
        foreach ($Success_h as $k => $data_h) {
            $list_h['amount'][$data_h['time']] = $data_h['amount'] ? $data_h['amount'] : 0;
            $list_h['actualamount'][$data_h['time']] = $data_h['actualamount'] ? $data_h['actualamount'] : 0;
            $list_h['poundage'][$data_h['time']] = $data_h['poundage'] ? $data_h['poundage'] : 0;
            $list_h['profit'][$data_h['time']] = $data_h['poundage'] - $data_h['cost'];
            if ($data_h['time'] < 10) {
                $list_h['time'][$data_h['time']] = "0" . intval($data_h['time']) . "时";
            } else {
                $list_h['time'][$data_h['time']] = $data_h['time'] . "时";
            }
            //投诉保证金冻结金额
            if (empty($data_h['success_count']) || $all_h[$data_h['time']] == "0") {
                $list_h['success_rate'][$data_h['time']] = 0;
            } else {
                $list_h['success_rate'][$data_h['time']] = sprintf("%.4f", $data_h['success_count'] / $all_h[$data_h['time']]) * 100;
            }
        }


        $list_d = [];
        $time_d = strtotime(date("Y-m-d", time() - 3600 * 24) . " 00:00:00") + 3600 * 24;
        $time_start_d = strtotime(date("Y-m-d", $time_d - 6 * 3600 * 24) . " 00:00:00");
        //总数
        $all_sql_d = "select COUNT(id) as num, DATE_FORMAT(from_unixtime(pay_applydate), '%Y-%m-%d') as time from pay_order WHERE unix_timestamp(DATE_FORMAT(from_unixtime(pay_applydate), '%Y-%m-%d'))  between '" . $time_start_d . "' and '" . $time_d . "' and pay_memberid= " . $memberid . " group by DATE_FORMAT(time, '%Y-%m-%d')";
        $all_d_arr = M('Order')->query($all_sql_d);
        foreach ($all_d_arr as $a_dv) {
            $all_d[$a_dv['time']] = $a_dv['num'];
        }
        //成功
        $sql_d = "select sum(`pay_amount`) amount, sum(`pay_poundage`) poundage, sum(`pay_actualamount`) actualamount,sum(`cost`) cost, count(`id`) Success_count,DATE_FORMAT(from_unixtime(pay_applydate), '%Y-%m-%d') as time from pay_order WHERE unix_timestamp(DATE_FORMAT(from_unixtime(pay_applydate), '%Y-%m-%d'))  between '" . $time_start_d . "' and '" . $time_d . "'and pay_status between 1 and 2 and pay_memberid= " . $memberid . " group by DATE_FORMAT(time, '%Y-%m-%d')";
        $Success_d_arr = M('Order')->query($sql_d);
        foreach ($Success_d_arr as $s_dk => $s_dv) {
            $Success_d[$s_dv['time']] = $s_dv;
        }
        foreach ($Success_d as $k_d => $data_d) {
            $list_d['amount'][$data_d['time']] = $data_d['amount'] ? $data_d['amount'] : 0;
            $list_d['actualamount'][$data_d['time']] = $data_d['actualamount'] ? $data_d['actualamount'] : 0;
            $list_d['poundage'][$data_d['time']] = $data_d['poundage'] ? $data_d['poundage'] : 0;
            $list_d['profit'][$data_d['time']] = $data_d['poundage'] - $data_d['cost'];
            $list_d['time'][$data_d['time']] = $data_d['time'];
            //投诉保证金冻结金额
            if (!$data_d['success_count'] || $all_d[$data_d['time']] == "0") {
                $list_d['success_rate'][$data_d['time']] = 0;
            } else {
                $list_d['success_rate'][$data_d['time']] = sprintf("%.4f", $data_d['success_count'] / $all_d[$data_d['time']]) * 100;
            }
        }
        $list_h_str = "'" . implode("','", $list_h['time']) . "'";
        $list_d_str = "'" . implode("','", $list_d['time']) . "'";
        $this->assign('list_h', $list_h);
        $this->assign('list_d', $list_d);
        $this->assign('list_h_str', $list_h_str);
        $this->assign('list_d_str', $list_d_str);
        C('TOKEN_ON', false);
        $this->display();
    }

    //统计
    public function Statistics()
    {
        $list_h = $all_h = [];
        $time_h = strtotime(date("Y-m-d H", time()) . ":00:00") + 3600;
        $time_start = strtotime(date("Y-m-d H", $time_h - 24 * 3600) . ":00:00");
        //总数
        $all_sql = "select COUNT(id) as num,DATE_FORMAT(from_unixtime(pay_applydate), '%H') as time from pay_order WHERE unix_timestamp(DATE_FORMAT(from_unixtime(pay_applydate), '%Y-%m-%d %H'))  between '" . $time_start . "' and '" . $time_h . "' group by DATE_FORMAT(from_unixtime(pay_applydate), '%Y-%m-%d %H')";
        $all_arr = M('Order')->query($all_sql);
        foreach ($all_arr as $av) {
            $all_h[$av['time']] = $av['num'];
        }
        //成功
        $sql = "select sum(`pay_amount`) amount, sum(`pay_poundage`) poundage, sum(`pay_actualamount`) actualamount,sum(`cost`) cost, count(`id`) Success_count,DATE_FORMAT(from_unixtime(pay_applydate), '%H') as time from pay_order WHERE unix_timestamp(DATE_FORMAT(from_unixtime(pay_applydate), '%Y-%m-%d %H'))  between '" . $time_start . "' and '" . $time_h . "'and pay_status <>0 group by DATE_FORMAT(from_unixtime(pay_applydate), '%Y-%m-%d %H')";
        $Success_arr = M('Order')->query($sql);
        foreach ($Success_arr as $sk => $sv) {
            $Success_h[$sv['time']] = $sv;
            $time = $Success_arr[$sk]['time'] + 1;
            if ($time == 24) {
                $time = "00";
            }
            if ($Success_arr[$sk + 1]['time'] != $time) {
                $Success_h[$sv['time'] + 1]['time'] = $time;
            }
        }
        foreach ($Success_h as $k => $data_h) {
            $list_h['amount'][$data_h['time']] = $data_h['amount'] ? $data_h['amount'] : 0;
            $list_h['actualamount'][$data_h['time']] = $data_h['actualamount'] ? $data_h['actualamount'] : 0;
            $list_h['poundage'][$data_h['time']] = $data_h['poundage'] ? $data_h['poundage'] : 0;
            $list_h['profit'][$data_h['time']] = $data_h['poundage'] - $data_h['cost'];
            if ($data_h['time'] < 10) {
                $list_h['time'][$data_h['time']] = "0" . intval($data_h['time']) . "时";
            } else {
                $list_h['time'][$data_h['time']] = $data_h['time'] . "时";
            }
            //投诉保证金冻结金额
            if (empty($data_h['success_count']) || $all_h[$data_h['time']] == "0") {
                $list_h['success_rate'][$data_h['time']] = 0;
            } else {
                $list_h['success_rate'][$data_h['time']] = sprintf("%.4f", $data_h['success_count'] / $all_h[$data_h['time']]) * 100;
            }
        }


        $list_d = [];
        $time_d = strtotime(date("Y-m-d", time() - 3600 * 24) . " 00:00:00") + 3600 * 24;
        $time_start_d = strtotime(date("Y-m-d", $time_d - 30 * 3600 * 24) . " 00:00:00");
        //总数
        $all_sql_d = "select COUNT(id) as num, DATE_FORMAT(from_unixtime(pay_applydate), '%Y-%m-%d') as time from pay_order WHERE unix_timestamp(DATE_FORMAT(from_unixtime(pay_applydate), '%Y-%m-%d'))  between '" . $time_start_d . "' and '" . $time_d . "' group by DATE_FORMAT(time, '%Y-%m-%d')";
        $all_d_arr = M('Order')->query($all_sql_d);
        foreach ($all_d_arr as $a_dv) {
            $all_d[$a_dv['time']] = $a_dv['num'];
        }
        //成功
        $sql_d = "select sum(`pay_amount`) amount, sum(`pay_poundage`) poundage, sum(`pay_actualamount`) actualamount,sum(`cost`) cost, count(`id`) Success_count,DATE_FORMAT(from_unixtime(pay_applydate), '%Y-%m-%d') as time from pay_order WHERE unix_timestamp(DATE_FORMAT(from_unixtime(pay_applydate), '%Y-%m-%d'))  between '" . $time_start_d . "' and '" . $time_d . "'and pay_status <>0 group by DATE_FORMAT(time, '%Y-%m-%d')";
        $Success_d_arr = M('Order')->query($sql_d);
        foreach ($Success_d_arr as $s_dk => $s_dv) {
            $Success_d[$s_dv['time']] = $s_dv;
        }
        foreach ($Success_d as $k_d => $data_d) {
            $list_d['amount'][$data_d['time']] = $data_d['amount'] ? $data_d['amount'] : 0;
            $list_d['actualamount'][$data_d['time']] = $data_d['actualamount'] ? $data_d['actualamount'] : 0;
            $list_d['poundage'][$data_d['time']] = $data_d['poundage'] ? $data_d['poundage'] : 0;
            $list_d['profit'][$data_d['time']] = $data_d['poundage'] - $data_d['cost'];
            $list_d['time'][$data_d['time']] = $data_d['time'];
            //投诉保证金冻结金额
            if (!$data_d['success_count'] || $all_d[$data_d['time']] == "0") {
                $list_d['success_rate'][$data_d['time']] = 0;
            } else {
                $list_d['success_rate'][$data_d['time']] = sprintf("%.4f", $data_d['success_count'] / $all_d[$data_d['time']]) * 100;
            }
        }
        $list_h_str = "'" . implode("','", $list_h['time']) . "'";
        $list_d_str = "'" . implode("','", $list_d['time']) . "'";

        $this->assign('list_h', $list_h);
        $this->assign('list_d', $list_d);
        $this->assign('list_h_str', $list_h_str);
        $this->assign('list_d_str', $list_d_str);
        C('TOKEN_ON', false);
        $this->display();
    }

}
