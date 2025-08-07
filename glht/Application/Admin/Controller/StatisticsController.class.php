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
 * 统计控制器
 * Class StatisticsController
 * @package Admin\Controller
 */
class StatisticsController extends BaseController
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
        //通道
        $tongdaolist = M("Channel")->field('id,code,title')->select();
        $this->assign("tongdaolist", $tongdaolist);

        $where = array(
            'pay_status' => ['gt', 1],
        );
        $memberid = I("request.memberid");
        if ($memberid) {
            $where['O.pay_memberid'] = array('eq', $memberid);
            $profitMap['userid'] = $profitSumMap['userid'] = $cjsxfMap['userid'] = $cjlrMap['userid'] = $memberid-10000;
        }
        $this->assign("memberid", $memberid);
        $orderid = I("request.orderid");
        if ($orderid) {
            $where['O.pay_orderid'] = $orderid;
            $profitMap['transid'] = $orderid;
        }
        $this->assign("orderid", $orderid);
        $tongdao = I("request.tongdao");
        if ($tongdao) {
            $where['O.pay_tongdao'] = array('eq', $tongdao);
        }
        $this->assign("tongdao", $tongdao);
        $createtime = urldecode(I("request.createtime"));
        if ($createtime) {
            list($cstime, $cetime)  = explode('|', $createtime);
            $where['O.pay_applydate'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
            $profitMap['datetime'] = $cjsxfMap['datetime'] = $cjlrMap['cldatetime'] = ['between', [$cstime, $cetime ? $cetime : date('Y-m-d H:i:s')]];
        }
        $this->assign("createtime", $createtime);
        $successtime = urldecode(I("request.successtime"));
        if ($successtime) {
            list($sstime, $setime)    = explode('|', $successtime);
            $where['O.pay_successdate'] = ['between', [strtotime($sstime), strtotime($setime) ? strtotime($setime) : time()]];
            $profitMap['datetime'] = $cjsxfMap['datetime'] = $cjlrMap['cldatetime'] = ['between', [$cstime, $cetime ? $cetime : date('Y-m-d H:i:s')]];
        } elseif (!$successtime && !$createtime) {
            $successtime = date('Y-m-d H:i:s', strtotime(date('Y-m', time()))) . " | " . date('Y-m-d H:i:s', time());
            $where['O.pay_successdate'] = ['between', [strtotime(date('Y-m', time())), time()]];
            $profitMap['datetime'] = $cjsxfMap['datetime'] = $cjlrMap['cldatetime'] = ['between', [strtotime(date('Y-m', time())), time()]];
        }
        $this->assign("successtime", $successtime);
        $count = M('Order')->alias('as O')->where($where)->count();
        $page  = new Page($count, 15);
        $list  = M('Order')
            ->alias('as O')
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('id desc')
            ->select();

        $amount = $rate = $realmoney = 0;
        foreach ($list as $item) {
            if ($item['pay_status'] >= 1) {
                $amount += $item['pay_amount'];
                $rate += $item['pay_poundage'];
                $realmoney += $item['pay_actualamount'];
            }
        }
        //查询支付成功的订单的手续费，入金费，总额总和
        $countWhere               = $where;
        $countWhere['O.pay_status'] = ['between', [1, 2]];
        $field                    = ['sum(`pay_amount`) pay_amount','sum(`cost`) cost', 'sum(`pay_poundage`) pay_poundage', 'sum(`pay_actualamount`) pay_actualamount', 'count(`id`) success_count'];
        $sum                      = M('Order')->alias('as O')->field($field)->where($countWhere)->find();
        foreach ($sum as $k => $v) {
            $sum[$k] += 0;
        }
        $countWhere['O.pay_status'] = 0;
        //失败笔数
        $sum['fail_count'] =  M('Order')->alias('as O')->where($countWhere)->count();
        //投诉保证金冻结金额
        $map = $where;
        $map['C.status'] = 0;
        $sum['complaints_deposit_freezed'] = M('complaints_deposit')->alias('as C')->join('LEFT JOIN __ORDER__ AS O ON C.pay_orderid=O.pay_orderid')
            ->where($map)
            ->sum('freeze_money');
        $sum['complaints_deposit_freezed'] += 0;
        $map['C.status'] = 1;
        $sum['complaints_deposit_unfreezed'] = M('complaints_deposit')->alias('as C')->join('LEFT JOIN __ORDER__ AS O ON C.pay_orderid=O.pay_orderid')
            ->where($map)
            ->sum('freeze_money');
        $sum['complaints_deposit_unfreezed'] += 0;
        $profitMap['lx'] = 9;
        $sum['memberprofit'] = M('moneychange')->where($profitMap)->sum('money');

        //出金利润
        $cjlrMap['status'] = 2;
        $tkmoney1 = M('tklist')->where($cjlrMap)->sum('tkmoney');
        $tkmoney2 = M('wttklist')->where($cjlrMap)->sum('tkmoney');
        $money1 = M('tklist')->where($cjlrMap)->sum('money');
        $money2 = M('wttklist')->where($cjlrMap)->sum('money');
        //代付成本
        $cjcost = M('wttklist')->where($cjlrMap)->sum('cost');
        $sum['cjlr'] = $tkmoney1 + $tkmoney2 - $money1 - $money2 - $cjcost;
        //出金手续费
        $cjsxfMap['lx'] = 14;
        $sxf1 = M('moneychange')->where($cjsxfMap)->sum('money');
        $cjsxfMap['lx'] = 16;
        $sxf2 = M('moneychange')->where($cjsxfMap)->sum('money');
        //退回出金手续费
        $cjsxfMap['lx'] = 15;
        $qxsxf1 = M('moneychange')->where($cjsxfMap)->sum('money');
        $cjsxfMap['lx'] = 17;
        $qxsxf2 = M('moneychange')->where($cjsxfMap)->sum('money');
        //出金实际手续费
        $sum['cjsxf'] = $sxf1 + $sxf2 - $qxsxf1 - $qxsxf2;
        $sum['pay_poundage'] = $sum['pay_poundage'] + $sum['cjlr'] - $sum['cost'] - $sum['memberprofit'] - $sum['cjsxf'];
        foreach($sum as $k => $v) {
            $sum[$k] +=0;
        }
        //统计订单信息
        $is_month = true;
        //下单时间
        if ($createtime) {
            $cstartTime = strtotime($cstime);
            $cendTime   = strtotime($cetime) ? strtotime($cetime) : time();
            $is_month   = $cendTime - $cstartTime > self::TMT ? true : false;
        }
        //支付时间
        $pstartTime = strtotime($sstime);
        $pendTime   = strtotime($setime) ? strtotime($setime) : time();
        $is_month   = $pendTime - $pstartTime > self::TMT ? true : false;

        $time       = $successtime ? 'pay_successdate' : 'pay_applydate';
        $dateFormat = $is_month ? '%Y年-%m月' : '%Y年-%m月-%d日';
        $field      = "FROM_UNIXTIME(" . $time . ",'" . $dateFormat . "') AS date,SUM(pay_amount) AS amount,SUM(pay_poundage) AS rate,SUM(pay_actualamount) AS total";
        $_mdata     = M('Order')->alias('as O')->field($field)->where($where)->group('date')->select();
        $mdata      = [];
        foreach ($_mdata as $item) {
            $mdata['amount'][] = $item['amount'] ? $item['amount'] : 0;
            $mdata['mdate'][]  = "'" . $item['date'] . "'";
            $mdata['total'][]  = $item['total'] ? $item['total'] : 0;
            $mdata['rate'][]   = $item['rate'] ? $item['rate'] : 0;
        }

        $this->assign("list", $list);
        $this->assign("mdata", $mdata);
        $this->assign('page', $page->show());
        $this->assign('stamount', $sum['pay_amount']);
        $this->assign('strate', $sum['pay_poundage']);
        $this->assign('strealmoney', $sum['pay_actualamount']);
        $this->assign('success_count', $sum['success_count']);
        $this->assign('fail_count', $sum['fail_count']);
        $this->assign('memberprofit', $sum['memberprofit']);
        $this->assign('complaints_deposit_freezed', $sum['complaints_deposit_freezed']);
        $this->assign('complaints_deposit_unfreezed', $sum['complaints_deposit_unfreezed']);
        $this->assign("isrootadmin", is_rootAdministrator());
        C('TOKEN_ON', false);
        $this->display();
    }
    /**
     * 导出交易订单
     * */
    public function exportorder()
    {

        //通道
        $tongdaolist = M("Channel")->field('id,code,title')->select();
        $this->assign("tongdaolist", $tongdaolist);

        $where = array(
            'pay_status' => ['eq', 2],
        );
        $memberid = I("request.memberid");
        if ($memberid) {
            $where['pay_memberid'] = array('eq', $memberid);
        }
        $orderid = I("request.orderid");
        if ($orderid) {
            $where['out_trade_id'] = $orderid;
        }
        $tongdao = I("request.tongdao");
        if ($tongdao) {
            $where['pay_tongdao'] = array('eq', $tongdao);
        }

        $createtime = urldecode(I("request.createtime"));
        if ($createtime) {
            list($cstime, $cetime)  = explode('|', $createtime);
            $where['pay_applydate'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
        }
        $successtime = urldecode(I("request.successtime"));
        if ($successtime) {
            list($sstime, $setime)    = explode('|', $successtime);
            $where['pay_successdate'] = ['between', [strtotime($sstime), strtotime($setime) ? strtotime($setime) : time()]];
        }

        $title = array('订单号', '商户编号', '交易金额', '手续费', '实际金额', '提交时间', '成功时间', '通道', '状态');
        $data  = M('Order')->where($where)->select();

        foreach ($data as $item) {
            $list[] = array(
                'pay_orderid'      => $item['pay_orderid'],
                'pay_memberid'     => $item['pay_memberid'],
                'pay_amount'       => $item['pay_amount'],
                'pay_poundage'     => $item['pay_poundage'],
                'pay_actualamount' => $item['pay_actualamount'],
                'pay_applydate'    => date('Y-m-d H:i:s', $item['pay_applydate']),
                'pay_successdate'  => date('Y-m-d H:i:s', $item['pay_successdate']),
                'pay_zh_tongdao'   => $item['pay_zh_tongdao'],
                'pay_status'       => '成功，已返回',
            );
        }

        exportCsv($list, $title);
    }

    public function channelFinance()
    {
        $where = [];
        $createtime = urldecode(I("request.createtime", '', 'string,strip_tags,htmlspecialchars'));
        if ($createtime) {
            list($cstime,$cetime) = explode('|',$createtime);
            $where['pay_applydate'] = ['between',[strtotime($cstime),strtotime($cetime)?strtotime($cetime):time()]];
        }
        $this->assign('createtime', $createtime);
        $Product = M('Product');
        $size = 15;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }
        $count = $Product->count();
        $Page  = new Page($count, $rows);

        $productList = $Product
            ->field(['id', 'name', 'code'])
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();

        $Order      = M('Order');
        $orderCount = $Order->where($where)->count();
        $orderList  = [];
        $limit      = 100000;
        for ($i = 0; $i < $orderCount; $i += $limit) {

            $tempList = $Order
                ->where($where)
                ->field(['pay_bankcode', 'pay_amount', 'pay_poundage', 'pay_actualamount', 'pay_status'])
                ->limit($i, $limit)
                ->select();

            $orderList = array_merge($orderList, $tempList);
        }

        //处理查询的数据
        foreach ($productList as $k => $v) {

            $productList[$k]['count']            = 0;
            $productList[$k]['fail_count']       = 0;
            $productList[$k]['success_count']    = 0;
            $productList[$k]['success_rate']     = 0;
            $productList[$k]['pay_amount']       = 0.00;
            $productList[$k]['pay_poundage']     = 0.00;
            $productList[$k]['pay_actualamount'] = 0.00;

            foreach ($orderList as $k1 => $v1) {
                if ($v['id'] == $v1['pay_bankcode']) {
                    $productList[$k]['count']++;
                    if ($v1['pay_status'] != 0) {
                        $productList[$k]['success_count']++;
                        $productList[$k]['pay_amount']       = bcadd($productList[$k]['pay_amount'], $v1['pay_amount'], 4);
                        $productList[$k]['pay_poundage']     = bcadd($productList[$k]['pay_poundage'], $v1['pay_poundage'], 4);
                        $productList[$k]['pay_actualamount'] = bcadd($productList[$k]['pay_actualamount'], $v1['pay_actualamount'], 4);
                    }
                }
            }
            $productList[$k]['fail_count']   = $productList[$k]['count'] - $productList[$k]['success_count'];
            $productList[$k]['success_rate'] = bcdiv($productList[$k]['success_count'], $productList[$k]['count'], 4) * 100;
        }

        $this->assign('list', $productList);
        $this->assign('rows', $rows);
        $this->assign('page', $Page->show());
        $this->display();
    }

    public function productChannelFinance()
    {
        $createtime = urldecode(I("request.createtime", '', 'string,strip_tags,htmlspecialchars'));
        if ($createtime) {
            list($cstime,$cetime) = explode('|',$createtime);
        }else{
            $cstime = date('Y-m-d') . ' 00:00:00';
            $cetime = date('Y-m-d') . ' 23:59:59';
            $createtime = $cstime . ' | ' . $cetime;
        }
        $this->assign('createtime', $createtime);
        

        $list = M('channel')->where(['status'=>1,'id' =>['neq',6]])->select();
        $countSuccess = $countFail = 0;
        $S_where = $F_where = [];
        foreach($list as $k => $v) {
            $S_where['channel_id'] = $v['id'];
            $S_where['pay_status'] = ['between', [1, 2]];
            $S_where['pay_applydate'] = ['between',[strtotime($cstime)-86400*7,strtotime($cetime)]];
            $S_where['pay_successdate'] = ['between',[strtotime($cstime),strtotime($cetime)]];
            $OrderModel = D('Order');
            $sum = $OrderModel->getSum('pay_amount,cost,pay_poundage,pay_actualamount',$S_where);
            $countSuccess = $OrderModel->getCount($S_where);

            $list[$k]['pay_amount'] = $sum['pay_amount'] += 0;//交易笔数
            $list[$k]['pay_poundage'] = $sum['pay_poundage']+= 0;//手续费
            $list[$k]['pay_actualamount'] = $sum['pay_actualamount'] += 0;//入金总额
            
            $F_where = [
                'channel_id'=> $v['id'],
                'pay_applydate' => ['between', [strtotime($cstime), strtotime($cetime)]],
                'pay_status' =>  ['eq', 0]
            ];
            $OrderModel = D('Order');
            $countFail = $OrderModel->getCount($F_where);//交易笔数
            $list[$k]['success_count'] = $countSuccess ;//成功笔数
            $list[$k]['fail_count'] = $countFail;
            $list[$k]['count'] = $countFail + $countSuccess;//失败笔数

            $list[$k]['success_rate'] = $list[$k]['count']>0?bcdiv($list[$k]['success_count'],$list[$k]['count'], 4) * 100 : 0;//成功率
            $list[$k]['success_average'] = $list[$k]['count']>0?bcdiv($list[$k]['pay_amount'],$list[$k]['count'], 4) : 0;//成功率
        }
        $this->assign('list', $list);
//        $this->assign('data', $product);
        $this->display();
    }
    
    /**
     * 通道对账单
     */
    public function productReconciliation()
    {
        $memberid = urldecode(I("request.memberid", '', 'string,strip_tags,htmlspecialchars'));
        
        $channel = M('channel')->where(['status'=>1,'id' =>['neq',6]])->select();
        $payChannel = M('PayForAnother')->where(['status'=>1,'id' =>['neq',6]])->select();
        $max_date = strtotime(date('Y-m-d'));   //今日日期
        
        for($i=0; $i<30; $i++) {
            $start_time = $max_date-$i*86400;
            if($start_time<$time) {
                break;
            }
            $list[$i]['date'] = date('Y-m-d',$start_time);
            $begin = date('Y-m-d',$start_time).' 00:00:00';
            $end = date('Y-m-d H:i:s',strtotime(date('Y-m-d',$start_time))+86400-1);
            foreach($channel as $k => $v) {
                $list[$i]['payin'][$v['id']] = $this->getDayReconciliationPayin($begin, $end, $v['id'], $memberid);
            }
            foreach($payChannel as $pk => $pv) {
                $list[$i]['payout'][$pv['id']] = $this->getDayReconciliationPayout($begin, $end, $pv['id'], $memberid);
            }
        }
        // echo "<pre>";
        // var_dump($list);
        $this->assign('list', $list);
        $this->assign('channel', $channel);
        $this->assign('payChannel', $payChannel);
        $this->assign('memberid', $memberid);
        C('TOKEN_ON', false);
        $this->display();
    }

    //获取代收对账单
    private function getDayReconciliationPayin($begin, $end, $channelId, $memberid = 0) {
        if($memberid!=0 && $memberid > 10002){
            $memberid = $memberid-10000;
        }elseif($memberid==0){
            $memberid = $memberid;
        }else{
            return [];
        }
        $date = date('Y-m-d', strtotime($begin));
        $C_where = [
            'channel_id'=>$channelId,
            'date'=>$date,
            'userid'=>$memberid,
        ];
        $data = M('reconciliationPayin')->where($C_where)->find();
        if(empty($data)) {
            $insertFlag = true;
        } else {
            $insertFlag = false;
        }
        if(empty($data) || (!empty($data) && (diffBetweenTwoDays(date('Y-m-d'),$date)<=7) || $data['ctime'] < (strtotime($date)+86400))) {//7天内账单实时更新数据
            $data = [];
            $data['date'] = $date;
            $data['userid'] = $memberid;
            
            //代收信息
            $where = [
                'channel_id'=>$channelId,
                'pay_status' => ['between', [1, 2]],
                'pay_applydate' => ['between', [strtotime($begin)-86400*7, strtotime($end)]],
                'pay_successdate' => ['between', [strtotime($begin), strtotime($end)]],
            ];            
            $O_where = [
                'channel_id'=>$channelId,
                'pay_applydate' => ['between', [strtotime($begin), strtotime($end)]],
            ];
            
            if($memberid > 0){
                $O_where['pay_memberid'] = $where['pay_memberid'] = $memberid+10000;
            }
            
            $OrderModel = D('Order');
            $Order_datas = $OrderModel->getSum('pay_amount', $where);
            $order_success_count = $OrderModel->getCount($where);
            
            $data['order_success_amount'] = $Order_datas['pay_amount']?:'0.00';
            $data['order_success_count'] = $order_success_count?:0;
            $data['order_success_average'] = $data['order_success_count']>0 ? sprintf("%.2f", $data['order_success_amount'] / $data['order_success_count']):0;
            
            $order_count = $OrderModel->getCount($O_where);
            $data['order_success_rate'] = bcdiv($order_success_count,$order_count, 4) * 100;

            if($insertFlag) {
                $data['channel_id'] = $channelId;
                $data['ctime'] = time();
                M('reconciliationPayin')->add($data);
            } else {
                M('reconciliationPayin')->where(['id'=>$data['id']])->save($data);
            }
        }
        unset($data['ctime']);
        unset($data['id']);            
        foreach ($data as $k => $v) {
            if ($k != 'date') {
                $data[$k] += 0;
            }
        }
        return $data;
    }

    //获取代付对账单
    private function getDayReconciliationPayout($begin, $end, $channelId, $memberid = 0) {
        if($memberid!=0 && $memberid > 10002){
            $memberid = $memberid-10000;
        }elseif($memberid==0){
            $memberid = $memberid;
        }else{
            return [];
        }
        $date = date('Y-m-d', strtotime($begin));
        $C_where = [
            'channel_id'=>$channelId,
            'date'=>$date,
            'userid'=>$memberid,
        ];
        $data = M('reconciliationPayout')->where($C_where)->find();
        if(empty($data)) {
            $insertFlag = true;
        } else {
            $insertFlag = false;
        }
        if(empty($data) || (!empty($data) && (diffBetweenTwoDays(date('Y-m-d'),$date)<=7) || $data['ctime'] < (strtotime($date)+86400))) {//7天内账单实时更新数据
            $data['date'] = $date;
            $data['userid'] = $memberid;
            
            //代付信息
            $where = [
                'df_id'=>$channelId,
                'status' => ['between', ['2', '3']],
                'sqdatetime' => ['between', [date('Y-m-d',strtotime($begin)-86400*7), $end]], 
                'cldatetime' => ['between', [$begin, $end]],
            ];
            if($memberid > 0){
                $where['userid'] = $memberid;
            }
            $Wttklist = D('Wttklist');
            $Wttklist_datas = $Wttklist->getSum('tkmoney,sxfmoney', $where);
            $wttklist_success_count = $Wttklist->getCount($where);
            $data['wttklist_success_amount'] = $Wttklist_datas['tkmoney']?:'0.00';
            $data['wttklist_success_poundage'] = $Wttklist_datas['sxfmoney']?:'0.00';
            $data['wttklist_success_count'] = $wttklist_success_count?:0;

            if($insertFlag) {
                $data['channel_id'] = $channelId;
                $data['ctime'] = time();
                M('reconciliationPayout')->add($data);
            } else {
                M('reconciliationPayout')->where(['id'=>$data['id']])->save($data);
            }
        }
        unset($data['ctime']);
        unset($data['id']);            
        foreach ($data as $k => $v) {
            if ($k != 'date') {
                $data[$k] += 0;
            }
        }
        return $data;
    }


    public function channelAccountFinance()
    {
        $where = [];
        $createtime = urldecode(I("request.createtime", '', 'string,strip_tags,htmlspecialchars'));
        if ($createtime) {
            list($cstime,$cetime) = explode('|',$createtime);
            $where['pay_applydate'] = ['between',[strtotime($cstime),strtotime($cetime)?strtotime($cetime):time()]];
        }
        $this->assign('createtime', $createtime);
        $id = I('id', 0, 'intval');
        if(!$id) {
            $this->error('缺少参数');
        }
        $channel = M('Channel')->where(['id' => $id])->find();
        if(empty($channel)) {
            $this->error('支付通道不存在');
        }
        $list = M('channelAccount')->where(['channel_id' => $id])->select();
        foreach($list as $k => $v) {
            $where['account_id'] = $v['id'];
            $where['pay_status'] = ['in', '1,2'];
            $sum = M('Order')->field(['sum(`pay_amount`) pay_amount','sum(`cost`) cost', 'sum(`pay_poundage`) pay_poundage', 'sum(`pay_actualamount`) pay_actualamount', 'count(`id`) success_count'])
                ->where($where)->find();
            $list[$k]['pay_amount'] = $sum['pay_amount'];//交易笔数
            $list[$k]['pay_amount'] += 0;
            $list[$k]['pay_poundage'] = $sum['pay_poundage'];//手续费
            $list[$k]['pay_poundage'] += 0;
            $list[$k]['pay_actualamount'] = $sum['pay_actualamount'];//入金总额
            $list[$k]['pay_actualamount'] += 0;
            unset($where['pay_status']);
            $list[$k]['count'] = M('Order')->where($where)->count();//交易笔数
            $list[$k]['count'] += 0;
            $list[$k]['success_count'] = $sum['success_count'];//成功笔数
            $list[$k]['success_count'] += 0;
            $list[$k]['fail_count'] = $list[$k]['count'] - $list[$k]['success_count'];//失败笔数
            $list[$k]['success_rate'] = $list[$k]['count']>0?bcdiv($list[$k]['success_count'],$list[$k]['count'], 4) * 100 : 0;//成功率
        }
        $this->assign('list', $list);
        $this->assign('data', $channel);
        $this->display();
    }

    /*
     * 商户报表
     */
    public function merchantReport() {
        $date = urldecode(I("request.date", ''));

        $agency = M('Agency')->select();
        $this->assign('agency', $agency);
        
        $agency_id = I('get.agency_id', '');
        if ($agency_id) {
            $where['agency_id'] = $agency_id;
        }
        
        $this->assign('agency_id', $agency_id);
        if ($date) {
            list($begin, $end) = explode('|', $date);
        }else{
            //没有搜索条件，默认显示当前
            $begin = date('Y-m-d') . ' 00:00:00';
            $end = date('Y-m-d') . ' 23:59:59';
            $date = $begin . ' | ' . $end;
        }
        $this->assign('date', $date);

        $where['groupid'] = 4;
        $where['id'] = ['gt',3];
//        $where['statistics'] = 1;       //是否开启统计
        if ($memberid = I('get.memberid', '')) {
            $where['id'] = $memberid - 10000;
        }
        $this->assign('memberid', $memberid);
        $size = 50;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }

        $MemberModel     = M('Member');
        $count      = $MemberModel->where($where)->count();
        $Page       = new Page($count, $rows);
        $members =[];
        $members = $MemberModel
            ->field(['id,username'])
            ->where($where)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->order('id asc')
            ->select();
            
            // echo $MemberModel->getLastSql();
        //入金通道费率
        $channel = M('Channel')->field('id,title')->where(['status' => 1,'id' =>['neq',6]])->select();
        if ($members) {
            $OrderModel = D('Order');
            $data=[];
            $orderSum=$countSuccess=$countFail=0;
            foreach ($members as $k => $v) {
                $uid = $v['id'];
                $members[$k]['uid'] = 10000 + $uid;
                foreach ($channel as $ck => $cv){
                    $S_where = [
                        'channel_id'=> $cv['id'],
                        'pay_memberid'=>10000+$v['id'],
                        'pay_applydate' => ['between', [strtotime($begin)-86400*7, strtotime($end)]],
                        'pay_successdate' => ['between', [strtotime($begin), strtotime($end)]],
                        'pay_status' =>  ['between', [1, 2]]
                    ];
                    $orderSum = $OrderModel->getSum('pay_amount',$S_where);
                    $countSuccess = $OrderModel->getCount($S_where);
                    
                    $O_where = [
                        'channel_id'=> $cv['id'],
                        'pay_memberid'=>10000+$v['id'],
                        'pay_applydate' => ['between', [strtotime($begin), strtotime($end)]],
                        'pay_status' =>  ['eq', 0]
                    ];
                    $countFail = $OrderModel->getCount($O_where);
                    
                    $data[$uid][$cv['id']]['cid'] = $cv['id'];
                    $data[$uid][$cv['id']]['username'] = $v['username'];
                    $data[$uid][$cv['id']]['pay_amount']= $orderSum['pay_amount'];
                    $data[$uid][$cv['id']]['countSuccess'] = $countSuccess;
                    $data[$uid][$cv['id']]['countFail'] = $countFail;
                    $countAll = $countFail + $countSuccess;
                    $data[$uid][$cv['id']]['successRate']= $countSuccess>0?sprintf("%.4f", $countSuccess / $countAll) * 100:0;
                    $data[$uid][$cv['id']]['successAverage']= $countSuccess>0?sprintf("%.2f", $orderSum['pay_amount'] / $countSuccess):0;
                    
                }
            }
        }
        // echo "<pre>";
        // var_dump($data);
        $this->assign('channel', $channel);
        $this->assign('rows', $rows);
        $this->assign('page', $Page->show());
        $this->assign('members', $members);
        $this->assign('list', $data);
        $this->display();
    }

    /**
     *
     *
     */
    public function merchantSuccessRate(){
        $date = urldecode(I("request.date", ''));
        if(!$date) {//默认今日
            $date = date('Y-m-d');
        }
        $this->assign('date', $date);
        if($date>date('Y-m-d')) {
            $this->error('日期错误');
        }
        $timestamp = strtotime($date);
        //开始时间戳
        $begin = mktime(0, 0, 0, date('m',$timestamp), date('d',$timestamp), date('Y',$timestamp));
        //结束时间戳
        $end = mktime(0, 0, 0, date('m',$timestamp), date('d',$timestamp) + 1, date('Y',$timestamp)) - 1;

        $where['groupid'] = 4;
        $where['id'] = ['gt',3];
//        $where['statistics'] = 1;       //是否开启统计
        if ($memberid = I('get.memberid', '')) {
            $where['id'] = $memberid - 10000;
        }
        $this->assign('memberid', $memberid);
        $size = 50;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }

        $MemberModel     = M('Member');
        $count      = $MemberModel->where($where)->count();
        $Page       = new Page($count, $rows);
        $show       = $Page->show();
        $members = $MemberModel
            ->field(['id,username'])
            ->where($where)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->order('id asc')
            ->select();
        //入金通道费率
        $channel = M('Channel')->field('id,title')->where(['status' => 1,'id' =>['NOT BETWEEN',['311','312']]])->select();
        if ($members) {
            $OrderModel = D('Order');
            $data=[];
            foreach ($members as $k => $v) {
                foreach ($channel as $ck => $cv){
                    $data[$k]['uid'] = 10000+$v['id'];
                    $data[$k]['username'] = $v['username'];
                    $O_where = [
                        'channel_id'=> $cv['id'],
                        'pay_memberid'=>10000+$v['id'],
                        'pay_applydate' => ['between', [$begin, $end]],
                        'pay_status' =>  ['between', [1, 2]]
                    ];
                    $countSuccess = $OrderModel->getCount($O_where);
                    unset($O_where['pay_status']);
                    $countAll = $OrderModel->getCount($O_where);
                    $data[$k][$cv['id']]= sprintf("%.4f", $countSuccess / $countAll) * 100;
                }
            }
        }
        $this->assign('channel', $channel);
        $this->assign('rows', $rows);
        $this->assign('page', $show);
        $this->assign('list', $data);
        $this->display();
        
    }
    
    
    /*
     * 包网报表
     */
    public function agencyReport() {

        $date = urldecode(I("request.date", ''));
        $agency = M('Agency')->select();
        $this->assign('agency', $agency);

        if ($date) {
            list($begin, $end) = explode('|', $date);
        }else{
            //没有搜索条件，默认显示当前
            $begin = date('Y-m-d') . ' 00:00:00';
            $end = date('Y-m-d') . ' 23:59:59';
            $date = $begin . ' | ' . $end;
        }
        $this->assign('date', $date);

        $size = 50;
        $agency_id = I('get.agency_id','');
        if ($agency_id!='') {
            $where = ['id' =>$agency_id];
        }
        $this->assign('agency_id', $agency_id);
        
        $rows = I('get.rows', $size, 'intval');
        
        $AgencyModel     = M('Agency');
        $count      = $AgencyModel->where($where)->count();
        $Page       = new Page($count, $rows);
        $agencys =[];
        $agencys = $AgencyModel
            ->field(['id,username'])
            ->where($where)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->order('id asc')
            ->select();
            
            // echo $MemberModel->getLastSql();
        
        //入金通道费率
        $channel = M('Channel')->field('id,title')->where(['status' => 1,'id' =>['neq',6]])->select();
        if ($agencys) {
                $data=[];
            foreach ($agencys as $k => $av) {
                $MemberModel     = M('Member');
                $M_where = ['agency_id'=>$av['id']];
                $members = $MemberModel ->field('id') ->where($M_where) ->select();
                $uid_arr=[];
                foreach ($members as $k => $v) {
                    $uid_arr[] = 10000 + $v['id'];
                }
                $OrderModel = D('Order');
                $orderSum=$countSuccess=$countFail=0;
                foreach ($channel as $ck => $cv){
                    if(empty($uid_arr)){
                        $data[$av['id']][$cv['id']]['cid'] = $cv['id'];
                        $data[$av['id']][$cv['id']]['username'] = $av['username'];
                        $data[$av['id']][$cv['id']]['pay_amount']= 0;
                        $data[$av['id']][$cv['id']]['countSuccess'] = 0;
                        $data[$av['id']][$cv['id']]['countFail'] = 0;
                        $data[$av['id']][$cv['id']]['successRate']= 0;
                        $data[$av['id']][$cv['id']]['successAverage']= 0;
                    }else{
                        $S_where = [
                            'channel_id'=> $cv['id'],
                            'pay_memberid'=>['in',$uid_arr],
                            'pay_applydate' => ['between', [strtotime($begin)-86400*7, strtotime($end)]],
                            'pay_successdate' => ['between', [strtotime($begin), strtotime($end)]],
                            'pay_status' =>  ['between', [1, 2]]
                        ];
                        $orderSum = $OrderModel->getSum('pay_amount',$S_where);
                        $countSuccess = $OrderModel->getCount($S_where);
                        
                        $O_where = [
                            'channel_id'=> $cv['id'],
                            'pay_memberid'=>['in',$uid_arr],
                            'pay_applydate' => ['between', [strtotime($begin), strtotime($end)]],
                            'pay_status' =>  ['eq', 0]
                        ];
                        $countFail = $OrderModel->getCount($O_where);
                        
                        $data[$av['id']][$cv['id']]['cid'] = $cv['id'];
                        $data[$av['id']][$cv['id']]['username'] = $av['username'];
                        $data[$av['id']][$cv['id']]['pay_amount']= $orderSum['pay_amount'];
                        $data[$av['id']][$cv['id']]['countSuccess'] = $countSuccess;
                        $data[$av['id']][$cv['id']]['countFail'] = $countFail;
                        $countAll = $countFail + $countSuccess;
                        $data[$av['id']][$cv['id']]['successRate']= $countSuccess>0?sprintf("%.4f", $countSuccess / $countAll) * 100:0;
                        $data[$av['id']][$cv['id']]['successAverage']= $countSuccess>0?sprintf("%.2f", $orderSum['pay_amount'] / $countSuccess):0;
                    }
                }
            }
        }
        $this->assign('channel', $channel);
        $this->assign('rows', $rows);
        $this->assign('page', $Page->show());
        $this->assign('agencys', $agencys);
        $this->assign('list', $data);
        $this->display();
    }


    /*
     * 获取初期余额
     */
    private function getAllMoney($date) {

        $money = 0;
        $lists = M('Member')->field('id')->select();
        foreach($lists as $v) {
            $money += $this->getUserBalance($v['id'], $date) ;
        }
        return $money;
    }


    /*
     * 根据日期获取用户余额
     */
    private function getUserBalance($userid, $date) {

        $money = M('Moneychange')->where(['userid'=>$userid, 'datetime'=>array('elt', $date), 't'=>['neq', 1], 'lx' => ['not in', '3,4']])->order('datetime DESC')->getField('gmoney');
        if(empty($money)) {
            $money = 0;
        }
        return $money;
    }

    /*
   * 根据日期获取用户期初余额
   */
    private function getDateBalance($userid, $date) {

        $log = M('Moneychange')->where(['userid'=>$userid, 'datetime'=>array('elt', $date), 't'=>['neq', 1], 'lx' => ['not in', '3,4']])->order('datetime DESC,id DESC')->find();
        if(empty($log)) {
            $money = 0;
        } else {
            $yesterdayTime = date("Y-m-d 00:00:00",strtotime($date)-1);
            $yesterdayRedAddSum = M('redo_order')->where(['type'=>1,'user_id'=>$userid,'date'=>$yesterdayTime, 'ctime'=>['gt', strtotime($log['datetime'])]])->sum('money');
            $lastlog = M('Moneychange')->where(['userid'=>$userid, 'datetime'=>array('elt', $date), 't'=>['neq', 1]])->order('datetime DESC,id DESC')->find();
            if($lastlog['lx'] == 3 || $lastlog['lx'] == 4) {
                $money = $lastlog['gmoney'];
            } else {
                $yesterdayRedReduceSum = M('redo_order')->where(['type'=>2,'user_id'=>$userid,'date'=>$yesterdayTime, 'ctime'=>['gt', strtotime($log['datetime'])]])->sum('money');
                $money = $log['gmoney'] + $yesterdayRedAddSum - $yesterdayRedReduceSum + 0;
            }
        }
        return $money;
    }

    public function analysis() {

        $date = urldecode(I("request.date", '' , 'string,strip_tags,htmlspecialchars'));
        if(!$date) {//默认今日
            $date = date('Y-m-d');
        }
        $this->assign('date', $date);
        $startTime = strtotime($date);
        $endTime = $startTime+60*60*24-1;
        //订单金额
        $total_success_amount = M('order')->where(['pay_status'=>['in', '1,2'], 'pay_applydate' => ['between', [$startTime, $endTime]]])->sum('pay_amount');

        //订单笔数
        $total_success_count = M('order')->where(['pay_status'=>['in', '1,2'], 'pay_applydate' => ['between', [$startTime, $endTime]]])->count();

        //订单均价
        if($total_success_count>0) {
            $average = round($total_success_amount/$total_success_count,4);
        } else {
            $average = 0;
        }
        //成功率
        $total_fail_count = M('order')->where(['pay_applydate' => ['between', [$startTime, $endTime]]])->count();
        if($total_fail_count>0) {
            $success_rate = round($total_success_count/$total_fail_count,4)*100;
        } else {
            $success_rate = 0;
        }

        $where['pay_status'] = ['gt',0];
        //商户订单金额TOP10
        $amountRankTmp = M('Order')
            ->join('LEFT JOIN __MEMBER__ ON (__MEMBER__.id + 10000) = __ORDER__.pay_memberid')
            ->field('pay_member.id as userid,pay_member.username,sum(pay_amount) as total_charge')
            ->where(['pay_status'=>['in', '1,2'],'pay_applydate' => ['between', [$startTime, $endTime]]])
            ->limit(0,100)
            ->group('pay_memberid')
            ->order('total_charge desc')
            ->select();
        $i = 0;
        $userids = $amountRank = [];
        foreach($amountRankTmp as $k => $v) {
            if (!$v['userid']) {
                continue;
            }
            $i++;
            $amountRank[$v['userid']]['userid'] = $v['userid'];
            $amountRank[$v['userid']]['username'] = $v['username'];
            $amountRank[$v['userid']]['rank'] = $i;
            $amountRank[$v['userid']]['total_charge'] = $v['total_charge'];
            array_push($userids, $v['userid']);
        }
        //如果不够10个商户则补充
        $tmpwhere['status'] = 1;
        if(!empty($userids)) {
            $tmpwhere['id'] =  ['not in', $userids];
            if(count($userids)<100) $limit = 100-count($userids);
        } else {
            $limit = 100;
        }
        $tmplist = M('Member')->where($tmpwhere)->field('id as userid,username')->order('id ASC')->limit(0, $limit)->select();
        foreach($tmplist as $k => $v) {
            $i++;
            $amountRank[$v['userid']]['userid'] = $v['userid'];
            $amountRank[$v['userid']]['username'] = $v['username'];
            $amountRank[$v['userid']]['rank'] = $i;
            $amountRank[$v['userid']]['total_charge'] = 0;
        }
        //商户订单笔数TOP10
        $totalNumtRankTmp = M('Order')
            ->join('LEFT JOIN __MEMBER__ ON (__MEMBER__.id + 10000) = __ORDER__.pay_memberid')
            ->field('pay_member.id as userid,pay_member.username,count(pay_order.id) as total_num')
            ->where(['pay_status'=>['in', '1,2'],'pay_applydate' => ['between', [$startTime, $endTime]]])
            ->limit(0,100)
            ->group('pay_memberid')
            ->order('total_num desc')
            ->select();
        $i = 0;
        $userids = $totalNumtRank = [];
        foreach($totalNumtRankTmp as $k => $v) {
            if(!$v['userid']) {
                continue;
            }
            $i++;
            $totalNumtRank[$v['userid']]['userid'] = $v['userid'];
            $totalNumtRank[$v['userid']]['username'] = $v['username'];
            $totalNumtRank[$v['userid']]['rank'] = $i;
            $totalNumtRank[$v['userid']]['total_num'] = $v['total_num'];
            array_push($userids, $v['userid']);
        }
        unset($tmpwhere);
        //如果不够10个商户则补充
        $tmpwhere['status'] = 1;
        if(!empty($userids)) {
            $tmpwhere['id'] =  ['not in', $userids];
            if(count($userids)<100) $limit = 100-count($userids);
        } else {
            $limit = 100;
        }
        $tmplist = M('Member')->where($tmpwhere)->field('id as userid,username')->order('id ASC')->limit(0, $limit)->select();
        foreach($tmplist as $k => $v) {
            $i++;
            $totalNumtRank[$v['userid']] = $v;
            $totalNumtRank[$v['userid']]['rank'] = $i;
            $totalNumtRank[$v['userid']]['total_num'] = 0;
        }
        //商户成功率TOP10
        $userSuccessRate = [];
        $userList = M('Member')->field('id as userid,username')->limit(0,100)->select();
        if(!empty($userList)) {
            foreach($userList as $k => $v) {
                $success_count = M('order')->where(['pay_memberid' => $v['userid']+10000,'pay_status'=>['in', '1,2'],'pay_applydate' => ['between', [$startTime, $endTime]]])->count();
                $total_count = M('order')->where(['pay_memberid' => $v['userid']+10000,'pay_applydate' => ['between', [$startTime, $endTime]]])->count();
                if($total_count>0) {
                    $userSuccessRate[$k] = round($success_count/$total_count,4)*100;
                    $userList[$k]['success_rate'] = $userSuccessRate[$k];
                } else {
                    $userList[$k]['success_rate'] = 0;
                }
            }
            $userList = arraySort($userList, 'success_rate', SORT_DESC);

            // $userList = array_slice($userList,0,100);
            $i = 0;
            foreach($userList as $k => $v) {
                $i++;
                $userList[$k]['rank'] = $i;
            }
        }

        //30天订单曲线图
        $dateRange = urldecode(I("request.dateRange", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($dateRange) {
            list($firstday, $lastday)  = explode('|', $dateRange);
            $time_diff = strtotime($lastday) - strtotime($firstday);
            if($time_diff <=0) {
                $this->error('日期范围错误');
            }
            if($time_diff/86400>30) {
                $this->error('日期相隔天数不得大于30天');
            }
        }
        if(!isset($firstday) || !$firstday) {
            $firstday = date('Y-m-d', strtotime("-30 day"));
            $lastday = date('Y-m-d');
            $dateRange = $firstday.' | '.$lastday;
        }
        $this->assign('dateRange', $dateRange);
        $sql = "SELECT SUM( pay_actualamount ) AS total, FROM_UNIXTIME( pay_applydate,  '%Y-%m-%d' ) AS DATETIME
        FROM pay_order WHERE pay_applydate >= UNIX_TIMESTAMP(  '".$firstday."' ) AND pay_applydate <= UNIX_TIMESTAMP(  '".
            $lastday."' ) AND pay_status>=1  GROUP BY DATETIME";
        $ordertotal = M('Order')->query($sql);
        $chartData = $category ='';
        foreach($ordertotal as $k => $v) {
            $category .= date('Ymd',strtotime($v['datetime'])). ',';
            $chartData .= $v['total'].',';
        }
        $category = '['.trim($category,',').']';
        $chartData = '['.trim($chartData,',').']';
        $this->assign('total_success_amount', $total_success_amount>0?$total_success_amount:0);
        $this->assign('total_success_count', $total_success_count>0?$total_success_count:0);
        $this->assign('average', $average>0?$average:0);
        $this->assign('success_rate', $success_rate>0?$success_rate:0);
        $this->assign("amountRank", $amountRank);
        $this->assign("totalNumtRank", $totalNumtRank);
        $this->assign("userList", $userList);
        $this->assign("category", $category);
        $this->assign("chartData", $chartData);
        $this->display();

    }
        
    //安全信息监控
    public function monitor(){
        $Member = M('Member');
        $stat_total_wait = $Member->sum('balance_php');
        $last = $Member->field('id,username,balance_php')->order('id desc')->limit(5)->select();

        //查询通道剩余额
        $pfa_model = D('PayForAnother');
        $where = [
            'status'=> 1,
            'id'=>['gt',6],
            'code' =>['neq','CeShiDF'],
        ];
        $channel_lists = $pfa_model->where($where)->select();
        $total_channel_balance = 0;
        $PayForAnother=[];
        foreach($channel_lists as $k => $v){
            $PayForAnother[$k]['title'] = $v['title'];
            $file = APP_PATH . 'Payment/Controller/' . $v['code'] . 'Controller.class.php';
            $result = R('Payment/'.$v['code'].'/queryBalance2', [$v]);
            if ($result['resultCode'] === "0") {
                $PayForAnother[$k]['balance'] = sprintf('%.4f', $result['balance']);
                $total_channel_balance += sprintf('%.4f', $result['balance']);
            }else{
                $PayForAnother[$k]['balance'] = '获取失败！';
            }
            
        }
        
        $stat['lastUser'] = $last;
        $stat['total_menmber_balance'] = sprintf('%.4f', $stat_total_wait);
        $stat['total_channel_balance'] = sprintf('%.4f', $total_channel_balance);
        $stat['PayForAnother'] = $PayForAnother;
        // var_dump($stat);
        $this->assign("stat", $stat);
        
        $targetUrl = 'https://' . C('DOMAIN') . "/sysadmin_Statistics_monitor.html";
        $this->assign("targetUrl", $targetUrl);
        $this->display();
    }
}
