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
use Common\Model\OrderModel;

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
        // $this->display();
        // $OrderModel = D('Order');
        // $OrderModel->getOrderByDateRange('id,pay_memberid','pay_memberid=240755606 or pay_orderid=2024080100000056489857576');
        // $OrderModel->getOrderByDateRange('id,pay_memberid',['pay_applydate'=>['between',['2024-08-23','2024-08-26']],'pay_successdate'=>['between',['2024-08-24','2024-08-26']]]);
        // $re = $OrderModel->getOrderByDateRange('id',['pay_memberid'=>'240755606','pay_successdate'=>['between',['2024-08-23','2024-08-28']]]);
        // $OrderModel->getOrderByDateRange(['id','pay_memberid'],['pay_memberid'=>'10002','pay_applydate'=>'2024-08-26 02:30:10']);
        // $OrderModel->addAllByDate(['pay_memberid'=>'1113','pay_memberid'=>'1114']);
        // $re = $OrderModel->table($OrderModel->getRealTableName('2024-08-26 02:30:10'))->where(['id'=>1])->save(['pay_memberid'=>'10002','pay_amount'=>101]);

        // $re = $OrderModel->table($OrderModel->getRealTableName('2024-08-26 02:30:10'))->where(['id'=>1])->save(['pay_memberid'=>'240755606','pay_amount'=>100]);
        // $re = $OrderModel->where(['id'=>1])->select();
        // $re = $OrderModel->table($OrderModel->getRealTableName(date('Ymd',strtotime('20240826'))))->where(['id'=>1])->find();
        // $re = $OrderModel->where(['id'=>1])->getField('pay_memberid');
        // $re = $OrderModel->where(['id'=>1])->setField('pay_memberid',10001);
        // var_dump($re);
        // echo $OrderModel->getLastSql();


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
        $out_trade_id = I("request.out_trade_id", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($out_trade_id) {
            $where['out_trade_id'] = $out_trade_id;
        }
        $this->assign('out_trade_id', $out_trade_id);
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
        $orderid = I('get.orderid', '', 'trim,string,strip_tags,htmlspecialchars');

        if ($orderid) {
            $where['pay_orderid'] = array('eq', $orderid);
            $profitMap['transid'] = $orderid;
        }
        $this->assign('orderid', $orderid);
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

        $successtime = urldecode(I("request.successtime", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($successtime) {
            list($sstime, $setime) = explode('|', $successtime);
            $where['pay_successdate'] = ['between', [strtotime($sstime), strtotime($setime) ? strtotime($setime) : time()]];
            $profitSumMap['datetime'] = $profitMap['datetime'] = $cjsxfMap['datetime'] = $cjlrMap['cldatetime'] = ['between', [$sstime, $setime ? $setime : date('Y-m-d H:i:s')]];
        }
        $this->assign('successtime', $successtime);
        $createtime = urldecode(I("request.createtime", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($createtime) {
            list($cstime, $cetime) = explode('|', $createtime);
            $where['pay_applydate'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
            $where['lock_status'] = ['neq', '1'];
            $profitSumMap['datetime'] = $profitMap['datetime'] = $cjsxfMap['datetime'] = $cjlrMap['cldatetime'] = ['between', [$cstime, $cetime ? $cetime : date('Y-m-d H:i:s')]];
        }
        //没有搜索条件，默认显示当前
        if (!$createtime && !$successtime && !$payOrderid && !$orderid) {
            $todayBegin = date('Y-m-d') . ' 00:00:00';
            $todyEnd = date('Y-m-d') . ' 23:59:59';
            if (!$createtime && !$successtime) {
                $where['pay_applydate'] = ['between', [strtotime($todayBegin), strtotime($todyEnd)]];
            }
            $where['lock_status'] = ['neq', '1'];
            $createtime = $todayBegin . ' | ' . $todyEnd;
        }
        $this->assign('createtime', $createtime);

        $where['lock_status'] = ['neq', '1'];

        $field = 'id,pay_amount,pay_poundage,pay_actualamount,pay_bankname,pay_zh_tongdao,pay_channel_account,pay_memberid,pay_orderid,out_trade_id,pay_applydate,pay_successdate,pay_productname,pay_status,ddlx,three_orderid,pay_ytongdao';
        $size = 50;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }
        $OrderModel = D('Order');

        $count = $OrderModel->getCount($where);
        $page = new Page($count, $rows);
        $list = $OrderModel->getOrderByDateRange($field, $where, $page->firstRow . ',' . $page->listRows, 'id desc');
        
        $redis = $this->redis_connect();
        $redis->select(1);
        foreach($list as $k => $v){
            if($redis->get('pay_' . $v['pay_orderid'])){
                $list[$k]['no_read']='<t style="color:red;">未唤醒</t>';
            }else{
                $list[$k]['no_read']='';
            }
        }

        $this->assign('uid', session("admin_auth.uid"));
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
        $tongdaolist = M("Channel")->field('id,code,title')->where($where)->select();
        $this->assign("tongdaolist", $tongdaolist);
        //通道
        $banklist = M("Product")->field('id,name,code')->where($where)->select();
        $this->assign("banklist", $banklist);

        $where = array();
        $currency = I("request.currency");
        if($currency ==='PHP'){
            $where['paytype'] = ['between', [1,3]];
            $profitMap['paytype'] = ['between', [1,3]];
        }
        if($currency ==='INR'){
            $where['paytype'] = ['eq', 4];
            $profitMap['paytype'] = ['eq', 4];
        }
        // $this->assign('currency', $currency);

        $memberid = I("request.memberid", 0, 'intval');
        if ($memberid) {
            $where['pay_memberid'] = array('eq', $memberid);
            $profitMap['userid'] = $memberid - 10000;
        }
        $this->assign('memberid', $memberid);
        
        $tongdao = I("request.tongdao", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($tongdao) {
            $where['channel_id'] = array('eq', $tongdao);
            $accountlist = M('channel_account')->where(['channel_id' => $tongdao])->select();
            $this->assign('accountlist', $accountlist);
        }
        $this->assign('tongdao', $tongdao);
        $bank = I("request.bank", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($bank) {
            $where['pay_bankcode'] = array('eq', $bank);
        }
        $this->assign('bank', $bank);

        $successtime = urldecode(I("request.successtime", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($successtime) {
            list($sstime, $setime) = explode('|', $successtime);
        }else{
            //没有搜索条件，默认显示当前
            $sstime = date('Y-m-d') . ' 00:00:00';
            $setime = date('Y-m-d') . ' 23:59:59';
            $successtime = $sstime . ' | ' . $setime;
        }
        $profitMap['datetime'] = ['between', [$sstime, $setime]];
        
        $sstime = strtotime($sstime);
        $setime = strtotime($setime);
        $cstime = $sstime- 3600 * 24 * 7;
        $cetime = $setime;
        $this->assign('successtime', $successtime);

        $where['pay_applydate'] = ['between', [$cstime, $cetime]];
        $where['pay_successdate'] = ['between', [$sstime, $setime]];
        $where['pay_status'] = ['between', [1, 2]];
        $where['lock_status'] = ['eq', 0];
        
        $field = ['sum(`pay_amount`) as pay_amount, sum(`cost`) as cost, sum(`pay_poundage`) as pay_poundage, sum(`pay_actualamount`) as pay_actualamount, count(`id`) as success_count'];
        $OrderModel = D('Order');
        $sum_arr = $OrderModel->getOrderByDateRange($field, $where);
        $sum=[];
        foreach ($sum_arr as $sk => $sv){
            foreach ($sv as $skk => $svv){
                $sum[$skk] +=$svv;
            }
        }

        //资金变动过里的提成
        $profitMap['lx'] = 9;
        if(!empty($memberid)) {
            $profitMap['userid'] = $memberid - 10000;
        }
        $MoneychangeModel = D('Moneychange');
        $sum_memberprofit = $MoneychangeModel->getSum('money',$profitMap);
        $sum['memberprofit'] = $sum_memberprofit['money'];
        // var_dump($profitMap);
        // $sum['memberprofit'] = M('moneychange')->where($profitMap)->sum('money');


        $sum['strate'] = $sum['pay_poundage'] - $sum['cost'] - $sum['memberprofit'];
        // $i=0;
        foreach ($sum as $k => $v) {
            // $sum[$i] = $v;
            // $i++;
            $sum[$k] = $v + 0;
        }

        // $sum['success_rate'] = sprintf("%.4f", $sum['success_count'] / ($sum['success_count'] + $sum['fail_count'])) * 100;
        $this->assign('success_rate', $sum['success_rate']);

        $this->assign('stamount', $sum['pay_amount']);
        $this->assign('strate', $sum['strate']);
        $this->assign('strealmoney', $sum['pay_actualamount']);
        $this->assign('success_count', $sum['success_count']);
        // $this->assign('fail_count', $sum['fail_count']);
        $this->assign('memberprofit', $sum['memberprofit']);
        $this->assign('poundage', $sum['pay_poundage']);
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
        $payOrderid = I('get.payorderid', '', 'trim,string,strip_tags,htmlspecialchars');
        if ($payOrderid) {
            $where['pay_orderid'] = array('eq', $payOrderid);
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
            if(diffBetweenTwoDays($cstime, $cetime) > 7){
                $this->ajaxReturn(['status' => 0, 'msg' => "请下载一周范围内的数据！"]);
            }
            $where['pay_applydate'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
        }
        $successtime = urldecode(I("request.successtime", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($successtime) {
            list($sstime, $setime) = explode('|', $successtime);
            $where['pay_successdate'] = ['between', [strtotime($sstime), strtotime($setime) ? strtotime($setime) : time()]];
        }
        if (!$createtime && !$successtime && !$payOrderid && !$orderid) {
            $todayBegin = date('Y-m-d') . ' 00:00:00';
            $todyEnd = date('Y-m-d') . ' 23:59:59';
            if (!$createtime && !$successtime) {
                $where['pay_applydate'] = ['between', [strtotime($todayBegin), strtotime($todyEnd)]];
            }
        }
        $file_path = "./Uploads/download/". date('Ymd') ."/" ;
        @mkdirs($file_path);
        $uid = session('admin_auth')['uid'];
        $fileName = $file_path . $uid . '_order' .time();
        $fileNameArr = array();

        $title = ['外部订单号', '系统订单号', '商户编号', '商户用户名', '交易金额', '手续费', '实际金额', '提交时间', '成功时间', '通道', '状态'];
        $filed = "pay_member.username,pay_order.id,pay_order.out_trade_id,pay_order.pay_orderid,pay_order.pay_memberid,pay_order.pay_amount,pay_order.pay_poundage,pay_order.pay_actualamount,pay_order.pay_applydate,pay_order.pay_successdate,pay_order.pay_zh_tongdao,pay_order.memberid,pay_order.pay_status";

        $OrderModel = D('Order');
        $tables = $OrderModel->getTables($where);
        foreach ($tables as $table) {
            $sqlCount = $OrderModel->table($table)
                ->alias('as pay_order')
                ->join('LEFT JOIN pay_member ON pay_member.id+10000 = pay_order.pay_memberid')
                ->where($where)
                ->count();
            if($sqlCount < 1){
                continue;
            }

            $mark = "order" . str_replace("pay_", "", $table);
            $sqlLimit = 100000;//每次只从数据库取100000条以防变量缓存太大
            // 每隔$limit行，刷新一下输出buffer，不要太大，也不要太小
            $limit = 100000;
            // buffer计数器
            $cnt = 0;
            for ($i = 0; $i < ceil($sqlCount / $sqlLimit); $i++) {
                $fp = fopen($mark . '_' . $i . '.csv', 'w'); //生成临时文件
                //     chmod('attack_ip_info_' . $i . '.csv',777);//修改可执行权限
                $fileNameArr[] = $mark . '_' .  $i . '.csv';
                // 将数据通过fputcsv写到文件句柄
                fputcsv($fp, $title);

                $dataArr = $OrderModel->table($table)
                    ->alias('as pay_order')
                    ->join('LEFT JOIN pay_member ON pay_member.id+10000 = pay_order.pay_memberid')
                    ->field($filed)->where($where)->order('id desc')
                    ->limit($i * $sqlLimit,$sqlLimit)->select();
                // var_dump($dataArr);die;
                foreach ($dataArr as $item) {
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
                        $pay_successdate = '---';
                    }
                    $list = [
                        'out_trade_id' => "\t" .$item['out_trade_id'],
                        'pay_orderid' =>"\t" .$item['pay_orderid'],
                        'pay_memberid' => "\t" . $item['pay_memberid'],
                        'username' => "\t" .$item['username'],
                        'pay_amount' => $item['pay_amount'],
                        'pay_poundage' => $item['pay_poundage'],
                        'pay_actualamount' => $item['pay_actualamount'],
                        'pay_applydate' => "\t" .date('Y-m-d H:i:s', $item['pay_applydate']),
                        'pay_successdate' => "\t" .$pay_successdate,
                        'pay_zh_tongdao' => "\t" .$item['pay_zh_tongdao'],
                        'pay_status' => "\t" .$status,
                    ];
                    $cnt++;
                    if ($limit == $cnt) {
                        //刷新一下输出buffer，防止由于数据过多造成问题
                        ob_flush();
                        flush();
                        $cnt = 0;
                    }
                    fputcsv($fp, $list);
                }
                fclose($fp);  //每生成一个文件关闭
            }
        }
        //进行多个文件压缩
        $zip = new \ZipArchive();
        $filename = $fileName . ".zip";
        // log_place_order('down', "文件地址", $filename);
        $zip->open($filename, \ZipArchive::CREATE);   //打开压缩包
        foreach ($fileNameArr as $file) {
            $zip->addFile($file, basename($file));   //向压缩包中添加文件
        }
        $zip->close();  //关闭压缩包
        foreach ($fileNameArr as $file) {
            unlink($file); //删除csv临时文件
        }
    }

    /**
     * 查看订单
     */
    public function show()
    {
        $id = I("get.oid", 0, 'intval');
        if ($id) {
            // $order = D('Order')
            //     ->join('LEFT JOIN __MEMBER__ ON (__MEMBER__.id + 10000) = __ORDER__.pay_memberid')
            //     ->field('pay_member.id as userid,pay_member.username,pay_member.realname,pay_order.*')
            //     ->where(['pay_order.id' => $id])
            //     ->find();
            //     echo M('Order')->getLastSql();die;
            $OrderModel = D('Order');
            $tables = $OrderModel->getTables();
            foreach ($tables as $v){
                $order = $OrderModel->table($v)->alias('as pay_order')
                    ->join('LEFT JOIN __MEMBER__ ON (__MEMBER__.id + 10000) = __ORDER__.pay_memberid')
                    ->field('pay_member.id as userid,pay_member.username,pay_member.realname,pay_order.*')
                    ->where(['pay_order.id' => $id])
                    ->find();
                //  echo $OrderModel->getLastSql();
                if(!empty($order)) break;
            }
        }
        $this->assign('order', $order);
        $this->display();
    }
    
    /**
     * 查看订单日志
     */
    public function showLog(){
        $orderid = I("get.orderid", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($orderid) {
            $data = json_decode(getOrderLog($orderid,'1'),true);
        }
        // var_dump($data['getNotifyLog']);
        $this->assign('order', $data);
        $this->display();
    }
    
    /**
     * 代收提交日志
     */
    public function getAddLog()
    {
        $list = '请输入查询条件';
        $memberid = I("request.memberid", '', 'trim,string,strip_tags,htmlspecialchars');
        $createtime = I("request.createtime", '', 'trim,string,strip_tags,htmlspecialchars');
        $orderid = I("request.orderid", '', 'trim,string,strip_tags,htmlspecialchars');
        $oper_ip = I("request.oper_ip", '', 'trim,string,strip_tags,htmlspecialchars');
        if (!$createtime) {
            $todayBegin = date('Y-m-d') . ' 00:00:00';
            $todyEnd = date('Y-m-d') . ' 23:59:59';
            $createtime = $todayBegin . ' | ' . $todyEnd;
        }
        if($memberid)$user_id = $memberid-10000;
        $data = getAddLogOrders($user_id, $orderid,1,$oper_ip, $createtime);
        $LogData = json_decode($data,true);
        if($LogData['status'] == 'success'){
            $list = $LogData['getAddLog'];
        }else{
             $list = $LogData['msg'];
        }
        
        $this->assign("orderid", $orderid);
        $this->assign("memberid", $memberid);
        $this->assign("oper_ip", $oper_ip);
        $this->assign("status", $LogData['status']);
        $this->assign("createtime", $createtime);
        $this->assign("list", $list);
        $this->display();
    }

    /**
     * 资金变动记录
     */
    public function changeRecord()
    {
        $where = array();
        $currency = I("request.currency");
        if($currency ==='PHP'){
            $where['paytype'] = ['between', [1,3]];
        }
        if($currency ==='INR'){
            $where['paytype'] = ['eq', 4];
        }
        $this->assign('currency', $currency);
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
        } else {
            $todayBegin = date('Y-m-d') . ' 00:00:00';
            $todyEnd = date('Y-m-d') . ' 23:59:59';
            $where['datetime'] = ['between', [($todayBegin), ($todyEnd)]];
            $createtime = $todayBegin . ' | ' . $todyEnd;
        }
        $this->assign('createtime', $createtime);
        $size = 15;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }

        $MoneychangeModel = D('Moneychange');
        $count = $MoneychangeModel->getCount($where);
        $page = new Page($count, $rows);
        $list = $MoneychangeModel->getOrderByDateRange('*', $where, $page->firstRow . ',' . $page->listRows, 'id desc');

        if ($bank == 9) {
            //总佣金笔数
            $stat['totalcount'] = $MoneychangeModel->getCount($where);
            // $stat['totalcount'] = M('Moneychange')->where($where)->count();
            //佣金总额
            $stat_totalsum = $MoneychangeModel->getSum('money',$where);
            $stat['totalsum'] = $stat_totalsum['money'];
            // $stat['totalsum'] = M('Moneychange')->where($where)->sum('money');
            //今日佣金总额
            $todayBegin = date('Y-m-d') . ' 00:00:00';
            $todyEnd = date('Y-m-d') . ' 23:59:59';
            $where['datetime'] = ['between', [$todayBegin, $todyEnd]];
            $stat_totalsum = $MoneychangeModel->getSum('money',$where);
            $stat['todaysum'] = $stat_totalsum['money'];
            // $stat['todaysum'] = M('Moneychange')->where($where)->sum('money');
            //今日佣金笔数
            $stat['totalcount'] = $MoneychangeModel->getCount($where);
            // $stat['todaycount'] = M('Moneychange')->where($where)->count();
            foreach ($stat as $k => $v) {
                $stat[$k] = $v + 0;
            }
            $this->assign('stat', $stat);
        }
        $this->assign('uid', session("admin_auth.uid"));
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

        $file_path = "./Uploads/download/". date('Ymd') ."/" ;
        @mkdirs($file_path);
        $uid = session('admin_auth')['uid'];
        $fileName = $file_path . $uid . '_change' .time();
        $fileNameArr = array();

        $title = array('订单号', '用户名', '类型', '原金额', '变动金额', '变动后金额', '变动时间', '备注');
        $filed = 'transid,userid,lx,ymoney,money,gmoney,datetime,contentstr';
        // $title = array('订单号', '用户名', '类型', '提成用户名', '提成级别', '原金额', '变动金额', '变动后金额', '变动时间', '通道', '备注');
        // $filed = 'transid,userid,lx,tcuserid,tcdengji,ymoney,money,gmoney,datetime,tongdao,contentstr';

        $MoneychangeModel = D('Moneychange');
        $tables = $MoneychangeModel->getTables($where);
        $datas =[];
        foreach ($tables as $table) {
            $sqlCount = $MoneychangeModel->table($table)->field($filed)->where($where)->count();
            if($sqlCount < 1){
                continue;
            }

            $mark = "change" . str_replace("pay_moneychange", "", $table);
            $sqlLimit = 100000;//每次只从数据库取100000条以防变量缓存太大
            // 每隔$limit行，刷新一下输出buffer，不要太大，也不要太小
            $limit = 100000;
            // buffer计数器
            $cnt = 0;
            for ($i = 0; $i < ceil($sqlCount / $sqlLimit); $i++) {
                $fp = fopen($mark . '_' . $i . '.csv', 'w'); //生成临时文件
                //     chmod('attack_ip_info_' . $i . '.csv',777);//修改可执行权限
                $fileNameArr[] = $mark . '_' .  $i . '.csv';
                // 将数据通过fputcsv写到文件句柄
                fputcsv($fp, $title);

                $data = $MoneychangeModel->table($table)->field($filed)->where($where)->order('id desc')->limit($i * $sqlLimit,$sqlLimit)->select();

                foreach ($data as $value) {
                    $list['transid'] = "\t" .$value["transid"];
                    $list['parentname'] = "\t" .getParentName($value["userid"], 1);
                    switch ($value["lx"]) {
                        case 1:
                            $list['lxstr'] = "付款";
                            break;
                        case 3:
                            $list['lxstr'] = "手动增加";
                            break;
                        case 4:
                            $list['lxstr'] = "手动减少";
                            break;
                        case 6:
                            $list['lxstr'] = "结算";
                            break;
                        case 7:
                            $list['lxstr'] = "冻结";
                            break;
                        case 8:
                            $list['lxstr'] = "解冻";
                            break;
                        case 9:
                            $list['lxstr'] = "提成";
                            break;
                        case 10:
                            $list['lxstr'] = "委托结算";
                            break;
                        case 11:
                            $list['lxstr'] = "提款驳回";
                            break;
                        case 12:
                            $list['lxstr'] = "代付驳回";
                            break;
                        case 13:
                            $list['lxstr'] = "投诉保证金解冻";
                            break;
                        case 14:
                            $list['lxstr'] = "扣除代付结算手续费";
                            break;
                        case 15:
                            $list['lxstr'] = "代付结算驳回退回手续费";
                            break;
                        case 16:
                            $list['lxstr'] = "扣除手动结算手续费";
                            break;
                        case 17:
                            $list['lxstr'] = "手动结算驳回退回手续费";
                            break;
                        default:
                            $list['lxstr'] = "未知";
                    }
                    // $list['tcuserid'] = "\t" .getParentName($value["tcuserid,"], 1);
                    // $list['tcdengji'] = "\t" .$value["tcdengji"];
                    $list['ymoney'] = $value["ymoney"];
                    $list['money'] = $value["money"];
                    $list['gmoney'] = $value["gmoney"];
                    $list['datetime'] = "\t" .$value["datetime"];
                    // $list['tongdao'] = "\t" .getProduct($value["tongdao"]);
                    $list['contentstr'] = "\t" .$value["contentstr"];
                    $cnt++;
                    if ($limit == $cnt) {
                        //刷新一下输出buffer，防止由于数据过多造成问题
                        ob_flush();
                        flush();
                        $cnt = 0;
                    }
                    fputcsv($fp, $list);
                }
                fclose($fp);  //每生成一个文件关闭
            }
            //进行多个文件压缩
            $zip = new \ZipArchive();
            $filename = $fileName . ".zip";
            // log_place_order('down', "文件地址", $filename);
            $zip->open($filename, \ZipArchive::CREATE);   //打开压缩包
            foreach ($fileNameArr as $file) {
                $zip->addFile($file, basename($file));   //向压缩包中添加文件
            }
            $zip->close();  //关闭压缩包
            foreach ($fileNameArr as $file) {
                unlink($file); //删除csv临时文件
            }
        }
        // $list = M("Moneychange")->field($field)->where($where);

        // putCsv($list,$field,$where,$title);
        // foreach ($list as $key => $value) {
        //     $data[$key]['transid'] = $value["transid"];
        //     $data[$key]['parentname'] = getParentName($value["userid"], 1);
        //     switch ($value["lx"]) {
        //         case 1:
        //             $data[$key]['lxstr'] = "付款";
        //             break;
        //         case 3:
        //             $data[$key]['lxstr'] = "手动增加";
        //             break;
        //         case 4:
        //             $data[$key]['lxstr'] = "手动减少";
        //             break;
        //         case 6:
        //             $data[$key]['lxstr'] = "结算";
        //             break;
        //         case 7:
        //             $data[$key]['lxstr'] = "冻结";
        //             break;
        //         case 8:
        //             $data[$key]['lxstr'] = "解冻";
        //             break;
        //         case 9:
        //             $data[$key]['lxstr'] = "提成";
        //             break;
        //         case 10:
        //             $data[$key]['lxstr'] = "委托结算";
        //             break;
        //         case 11:
        //             $data[$key]['lxstr'] = "提款驳回";
        //             break;
        //         case 12:
        //             $data[$key]['lxstr'] = "代付驳回";
        //             break;
        //         case 13:
        //             $data[$key]['lxstr'] = "投诉保证金解冻";
        //             break;
        //         case 14:
        //             $data[$key]['lxstr'] = "扣除代付结算手续费";
        //             break;
        //         case 15:
        //             $data[$key]['lxstr'] = "代付结算驳回退回手续费";
        //             break;
        //         case 16:
        //             $data[$key]['lxstr'] = "扣除手动结算手续费";
        //             break;
        //         case 17:
        //             $data[$key]['lxstr'] = "手动结算驳回退回手续费";
        //             break;
        //         default:
        //             $data[$key]['lxstr'] = "未知";
        //     }
        //     $data[$key]['tcuserid'] = getParentName($value["tcuserid,"], 1);
        //     $data[$key]['tcdengji'] = $value["tcdengji"];
        //     $data[$key]['ymoney'] = $value["ymoney"];
        //     $data[$key]['money'] = $value["money"];
        //     $data[$key]['gmoney'] = $value["gmoney"];
        //     $data[$key]['datetime'] = $value["datetime"];
        //     $data[$key]['tongdao'] = getProduct($value["tongdao"]);
        //     $data[$key]['contentstr'] = $value["contentstr"];
        // }
        // $numberField = ['ymoney', 'money', 'gmoney'];
        // // exportexcel($data, $title, $numberField);
        // exportCsv($data, $title);
        // // 将已经写到csv中的数据存储变量销毁，释放内存占用
        // unset($data);
        // //刷新缓冲区
        // ob_flush();
        // flush();
    }


    // /**
    //  * 资金变动记录导出
    //  */
    // public function exceldownload()
    // {
    //     UserLogService::HTwrite(5, '导出资金变动记录', '导出资金变动记录');
    //     $where = array();
    //     $memberid = I("request.memberid", 0, 'intval');
    //     if ($memberid) {
    //         $where['userid'] = array('eq', ($memberid - 10000) > 0 ? ($memberid - 10000) : 0);
    //     }
    //     $orderid = I("request.orderid", '', 'trim,string,strip_tags,htmlspecialchars');
    //     if ($orderid) {
    //         $where['orderid'] = $orderid;
    //     }
    //     $tongdao = I("request.tongdao", '', 'trim,string,strip_tags,htmlspecialchars');
    //     if ($tongdao) {
    //         $where['tongdao'] = array('eq', $tongdao);
    //     }
    //     $bank = I("request.bank", '', 'trim,string,strip_tags,htmlspecialchars');
    //     if ($bank) {
    //         $where['lx'] = array('eq', $bank);
    //     }
    //     $createtime = urldecode(I("request.createtime", '', 'trim,string,strip_tags,htmlspecialchars'));
    //     if ($createtime) {
    //         list($cstime, $cetime) = explode('|', $createtime);
    //         $where['datetime'] = ['between', [$cstime, $cetime ? $cetime : date('Y-m-d')]];
    //     }

    //     $title = array('订单号', '用户名', '类型', '提成用户名', '提成级别', '原金额', '变动金额', '变动后金额', '变动时间', '通道', '备注');

    //     $list = M("Moneychange")->field('transid,userid,lx,tcuserid,tcdengji,ymoney,money,gmoney,datetime,tongdao,contentstr')->where($where)->select();
    //     foreach ($list as $key => $value) {
    //         $data[$key]['transid'] = $value["transid"];
    //         $data[$key]['parentname'] = getParentName($value["userid"], 1);
    //         switch ($value["lx"]) {
    //             case 1:
    //                 $data[$key]['lxstr'] = "付款";
    //                 break;
    //             case 3:
    //                 $data[$key]['lxstr'] = "手动增加";
    //                 break;
    //             case 4:
    //                 $data[$key]['lxstr'] = "手动减少";
    //                 break;
    //             case 6:
    //                 $data[$key]['lxstr'] = "结算";
    //                 break;
    //             case 7:
    //                 $data[$key]['lxstr'] = "冻结";
    //                 break;
    //             case 8:
    //                 $data[$key]['lxstr'] = "解冻";
    //                 break;
    //             case 9:
    //                 $data[$key]['lxstr'] = "提成";
    //                 break;
    //             case 10:
    //                 $data[$key]['lxstr'] = "委托结算";
    //                 break;
    //             case 11:
    //                 $data[$key]['lxstr'] = "提款驳回";
    //                 break;
    //             case 12:
    //                 $data[$key]['lxstr'] = "代付驳回";
    //                 break;
    //             case 13:
    //                 $data[$key]['lxstr'] = "投诉保证金解冻";
    //                 break;
    //             case 14:
    //                 $data[$key]['lxstr'] = "扣除代付结算手续费";
    //                 break;
    //             case 15:
    //                 $data[$key]['lxstr'] = "代付结算驳回退回手续费";
    //                 break;
    //             case 16:
    //                 $data[$key]['lxstr'] = "扣除手动结算手续费";
    //                 break;
    //             case 17:
    //                 $data[$key]['lxstr'] = "手动结算驳回退回手续费";
    //                 break;
    //             default:
    //                 $data[$key]['lxstr'] = "未知";
    //         }
    //         $data[$key]['tcuserid'] = getParentName($value["tcuserid,"], 1);
    //         $data[$key]['tcdengji'] = $value["tcdengji"];
    //         $data[$key]['ymoney'] = $value["ymoney"];
    //         $data[$key]['money'] = $value["money"];
    //         $data[$key]['gmoney'] = $value["gmoney"];
    //         $data[$key]['datetime'] = $value["datetime"];
    //         $data[$key]['tongdao'] = getProduct($value["tongdao"]);
    //         $data[$key]['contentstr'] = $value["contentstr"];
    //     }
    //     $numberField = ['ymoney', 'money', 'gmoney'];
    //     // exportexcel($data, $title, $numberField);
    //     exportCsv($data, $title);
    //     // 将已经写到csv中的数据存储变量销毁，释放内存占用
    //     unset($data);
    //     //刷新缓冲区
    //     ob_flush();
    //     flush();
    // }

    // public function delOrder()
    // {
    //     UserLogService::HTwrite(4, '删除无效订单', '删除无效订单');
    //     $where = [];
    //     $memberid = I("request.memberid", 0, 'intval');
    //     if ($memberid != 0) {
    //         $where['pay_memberid'] = $memberid;
    //     }
    //     $createtime = urldecode(I("request.createtime", '', 'trim,string,strip_tags,htmlspecialchars'));
    //     $where['pay_status'] = array('eq', 0);
    //     if ($createtime) {
    //         list($cstime, $cetime) = explode('|', $createtime);
    //         $where['pay_applydate'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
    //     } else {
    //         $this->ajaxReturn(['status' => 0, 'msg' => "请选择删除无效订单创建时间范围！"]);
    //     }
    //     $OrderModel = D('Order');
    //     $count = $OrderModel->getCount($where);
    //     var_dump($count);die;
    //     // $OrderModel->table($OrderModel->getRealTableName('2024-08-26 02:30:10'))
    //     // $count = M('Order')->where($where)->count();
    //     if ($count == 0) {
    //         $this->ajaxReturn(['status' => 0, 'msg' => "该时间范围内没有无效订单！"]);
    //     }
    //     $status = M('Order')->where($where)->delete();
    //     if ($status) {
    //         UserLogService::HTwrite(4, '删除无效订单成功', '删除' . $createtime . '无效订单成功');
    //         $this->ajaxReturn(['status' => 1, 'msg' => "删除成功"]);
    //     } else {
    //         UserLogService::HTwrite(4, '删除无效订单失败', '删除无效订单失败');
    //         $this->ajaxReturn(['status' => 0, 'msg' => "删除失败"]);
    //     }
    // }

    // //批量删除订单
    // public function delAll()
    // {

    //     if (IS_POST) {
    //         UserLogService::HTwrite(4, '批量删除订单', '批量删除订单');
    //         $code = I('request.code');
    //         $createtime = urldecode(I("request.createtime", '', 'trim,string,strip_tags,htmlspecialchars'));
    //         if ($createtime) {
    //             list($cstime, $cetime) = explode('|', $createtime);
    //             $startTime = strtotime($cstime);
    //             $endTime = strtotime($cetime);
    //             if (!$startTime || !$endTime || ($startTime >= $endTime)) {
    //                 $this->ajaxReturn(array('status' => 0, "时间范围错误"));
    //             }
    //             $where['pay_applydate'] = ['between', [$startTime, $endTime]];
    //         } else {
    //             $this->ajaxReturn(array('status' => 0, "请选择删除订单时间段"));
    //         }
    //         if (session('send.delOrderSend') == $code && $this->checkSessionTime('delOrderSend', $code)) {
    //             $status = M('Order')->where($where)->delete();
    //             if ($status) {
    //                 UserLogService::HTwrite(4, '批量删除订单成功', '批量删除' . $createtime . '订单成功,删除了' . $status . '个订单！');
    //                 $this->ajaxReturn(array('status' => 1, "删除成功" . $status . '个订单！'));
    //             } else {
    //                 UserLogService::HTwrite(4, '批量删除订单失败', '批量删除订单失败');
    //                 $this->ajaxReturn(array('status' => 0, "删除失败"));
    //             }
    //         } else {
    //             $this->ajaxReturn(['status' => 0, 'msg' => '验证码错误']);
    //         }
    //     } else {
    //         $uid = session('admin_auth')['uid'];
    //         $mobile = M('Admin')->where(['id' => $uid])->getField('mobile');
    //         $this->assign('mobile', $mobile);
    //         $this->display();
    //     }
    // }

    /**
     * 批量删除订单验证码信息
     */
    // public function delOrderSend()
    // {
    //     $uid = session('admin_auth')['uid'];
    //     $user = M('Admin')->where(['id' => $uid])->find();
    //     $res = $this->send('delOrderSend', $user['mobile'], '批量删除订单');
    //     $this->ajaxReturn(['status' => $res['code']]);
    // }

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

        $orderid = I('request.orderid');
        if (!$orderid) {
            $this->ajaxReturn(['status' => 0, 'msg' => "缺少订单ID！"]);
        }

        $OrderModel = D('Order');
        $tables = $OrderModel->getTables();
        foreach ($tables as $v){
            $order = $OrderModel->table($v)->where(['id' => $orderid])->find();
            if(!empty($order)) break;
        }
        if (IS_POST) {
            //设置redis标签，防止重复执行
            $redis = $this->redis_connect();
            // if($redis->get('setOrderPaid' . $orderid)){
            //     $this->ajaxReturn(['status' => 0, 'msg' => '重复操作']);
            // }
            $redis->set('setOrderPaid' . $orderid,'1',120);

            UserLogService::HTwrite(3, '设置订单为已支付', '设置订单为已支付');

            if ($order['status'] != 0) {
                $this->ajaxReturn(['status' => 0, 'msg' => "该订单状态为已支付！"]);
            }
            $payModel = D('Pay');
            $auth_type = I('request.auth_type', 0, 'intval');
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
        $orderid = I('request.orderid');
        if (!$orderid) {
            $this->ajaxReturn(['status' => 0, 'msg' => "缺少订单ID！"]);
        }
        $OrderModel = D('Order');
        $tables = $OrderModel->getTables();
        foreach ($tables as $v){
            $order = $OrderModel->table($v)->where(['id' => $orderid])->find();
            if(!empty($order)){
                $realTableName = $v;
                break;
            }
        }

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
            //设置redis标签，防止重复执行
            $redis = $this->redis_connect();
            if($redis->get('setnOrderPaid' . $orderid)){
                $this->ajaxReturn(['status' => 0, 'msg' => '重复操作']);
            }
            $redis->set('setnOrderPaid' . $orderid,'1',120);

            if ($order['status'] != 0) {
                $this->ajaxReturn(['status' => 0, 'msg' => "该订单状态为已支付！"]);
            }
            $payModel = D('Pay');
            $auth_type = I('request.auth_type', 0, 'intval');
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
            $res = $OrderModel->table($realTableName)->save($order);
            // $res = M('order')->save($order);

            if ($res) {
                UserLogService::HTwrite(3, '设置订单为已支付成功', '设置订单（' . $order['pay_orderid'] . '）为已支付成功');
                $this->ajaxReturn(['status' => 1, 'msg' => "设置成功！"]);
            } else {
                UserLogService::HTwrite(3, '设置订单为已支付失败', '设置订单（' . $order['pay_orderid'] . '）为已支付失败');
                $this->ajaxReturn(['status' => 0, 'msg' => "设置失败"]);
            }
        } else {
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
        if(getPaytypeCurrency($order['paytype']) ==='PHP'){        //菲律宾余额
            $ymoney = $info['balance_php']; //改动前的金额
            $gmoney = $info['balance_php'] - $order['pay_actualamount']; //改动后的金额
            $member_data['balance_php'] = ['exp', 'balance_php-' . $order['pay_actualamount']]; //防止数据库并发脏读
            $member_data['blockedbalance_php'] = ['exp', "blockedbalance_php+" . $order['pay_actualamount']];
        }
        if(getPaytypeCurrency($order['paytype']) ==='INR'){        //越南余额
            $ymoney = $info['balance_inr']; //改动前的金额
            $gmoney = $info['balance_inr'] - $order['pay_actualamount']; //改动后的金额
            $member_data['balance_inr'] = ['exp', 'balance_inr-' . $order['pay_actualamount']]; //防止数据库并发脏读
            $member_data['blockedbalance_inr'] = ['exp', "blockedbalance_inr+" . $order['pay_actualamount']];
        }
        $order = M("order")->where(array("id" => $orderId, "pay_status" => ['in', '1,2'], "lock_status" => ['LT', 1]))->lock(true)->find();

        //需要检测是否已解冻，如果未解冻直接修改自动解冻状态，如果解冻，直接扣余额
        $maps['status'] = array('eq', 0);
        $maps['orderid'] = array('eq', $order['pay_orderid']);
        $blockedLog = M('blockedlog')->where($maps)->find();
        if ($blockedLog) {
            $res = M('blockedlog')->where(array('id' => $blockedLog['id']))->save(array('status' => 1));
        } else {
            $res = M('member')->where(['id' => $userId])->save($member_data);
        }

        $orderRe = M("order")->where(array("id" => $orderId, "pay_status" => ['in', '1,2'], "lock_status" => ['LT', 1]))->save(['lock_status' => 1]);
        $data = array();
        $data['userid'] = $userId;
        $data['ymoney'] = $ymoney;
        $data['money'] = $order['pay_actualamount'];
        $data['gmoney'] = $gmoney;
        $data['datetime'] = date("Y-m-d H:i:s");
        $data['tongdao'] = $order['pay_bankcode'];
        $data['transid'] = $order['pay_orderid'];//交易流水号
        $data['orderid'] = $order['pay_orderid'];
        $data['paytype'] = $order['paytype'];
        $data['lx'] = 7;//冻结
        $data['contentstr'] = "手动冻结订单";

        $Moneychange = D("Moneychange");
        $tablename = $Moneychange -> getRealTableName($data['datetime']);
        $change = $Moneychange->table($tablename)->add($data);
        // $change = M('moneychange')->add($data);
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
        if(getPaytypeCurrency($order['paytype']) ==='PHP'){        //菲律宾余额
            $ymoney = $info['balance_php']; //改动前的金额
            $gmoney = $info['balance_php'] + $order['pay_actualamount']; //改动后的金额
            $member_data['balance_php'] = ['exp', 'balance_php+' . $order['pay_actualamount']]; //防止数据库并发脏读
            $member_data['blockedbalance_php'] = ['exp', "blockedbalance_php-" . $order['pay_actualamount']];
        }
        if(getPaytypeCurrency($order['paytype']) ==='INR'){        //越南余额
            $ymoney = $info['balance_inr']; //改动前的金额
            $gmoney = $info['balance_inr'] + $order['pay_actualamount']; //改动后的金额
            $member_data['balance_inr'] = ['exp', 'balance_inr+' . $order['pay_actualamount']]; //防止数据库并发脏读
            $member_data['blockedbalance_inr'] = ['exp', "blockedbalance_inr-" . $order['pay_actualamount']];
        }
        $order = M("order")->where(array("id" => $orderId, "pay_status" => ['in', '1,2'], "lock_status" => ['eq', 1]))->lock(true)->find();
        //需要检测是否已解冻，如果未解冻直接修改自动解冻状态，如果解冻，直接扣余额
        $res = M('member')->where(array('id' => $userId))->save($member_data);
        //记录日志
        $orderRe = M("order")->where(array("id" => $orderId, "pay_status" => ['in', '1,2'], "lock_status" => ['eq', 1]))->save(array("lock_status" => 2));
        $data = array();
        $data['userid'] = $userId;
        $data['ymoney'] = $ymoney;
        $data['money'] = $order['pay_actualamount'];
        $data['gmoney'] = $gmoney;
        $data['datetime'] = date("Y-m-d H:i:s");
        $data['tongdao'] = $order['pay_bankcode'];
        $data['transid'] = $order['pay_orderid'];//交易流水号
        $data['orderid'] = $order['pay_orderid'];
        $data['lx'] = 8;//解冻
        $data['contentstr'] = "手动解冻订单";

        $Moneychange = D("Moneychange");
        $tablename = $Moneychange -> getRealTableName($data['datetime']);
        $change = $Moneychange->table($tablename)->add($data);
        // $change = M('moneychange')->add($data);
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
            $OrderModel = D('Order');
            $Where = [
                'pay_applydate'=>['between', [$time_h - 600, $time_h]],
                'channel_id' => $v['id'],
            ];
            $all_list1 = $OrderModel->getCount($Where);
            $Where['pay_status'] = ['neq',0];
            $slist1 = $OrderModel->getCount($Where);

            $Where = [
                'pay_applydate'=>['between', [$time_h - 1800, $time_h]],
                'channel_id' => $v['id'],
            ];
            $all_list2 = $OrderModel->getCount($Where);
            $Where['pay_status'] = ['neq',0];
            $slist2 = $OrderModel->getCount($Where);

            $Where = [
                'pay_applydate'=>['between', [$time_h - 3600, $time_h]],
                'channel_id' => $v['id'],
            ];
            $all_list3 = $OrderModel->getCount($Where);
            $Where['pay_status'] = ['neq',0];
            $slist3 = $OrderModel->getCount($Where);

            $Where = [
                'pay_applydate'=>['between', [$time_h- (6 * 3600), $time_h]],
                'channel_id' => $v['id'],
            ];
            $all_list4 = $OrderModel->getCount($Where);
            $Where['pay_status'] = ['neq',0];
            $slist4 = $OrderModel->getCount($Where);

            $Where = [
                'pay_applydate'=>['between', [$time_h- (24 * 3600), $time_h]],
                'channel_id' => $v['id'],
            ];
            $all_list5 = $OrderModel->getCount($Where);
            $Where['pay_status'] = ['neq',0];
            $slist5 = $OrderModel->getCount($Where);

            $list[$v['id']]['id'] = $v['id'];
            $list[$v['id']]['title'] = $v['title'];
            $list[$v['id']]['code'] = $v['code'];
            $list[$v['id']]['10'] = sprintf("%.4f", $slist1 / $all_list1) * 100;
            $list[$v['id']]['30'] = sprintf("%.4f", $slist2 / $all_list2) * 100;
            $list[$v['id']]['60'] = sprintf("%.4f", $slist3 / $all_list3) * 100;
            $list[$v['id']]['6h'] = sprintf("%.4f", $slist4 / $all_list4) * 100;
            $list[$v['id']]['day'] = sprintf("%.4f", $slist5 / $all_list5) * 100;
        }
        $this->assign('list', $list);
        $this->display();
    }

    public function utrOrder(){
        $where = array();
        $memberid = I("request.memberid", 0, 'intval');
        if ($memberid) {
            $where['pay_memberid'] = array('eq', $memberid-10000);
        }
        $this->assign('memberid', $memberid);
        $pay_orderid = I("request.pay_orderid", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($pay_orderid) {
            $where['pay_orderid'] = $pay_orderid;
        }
        $this->assign('pay_orderid', $pay_orderid);
        $out_trade_id = I("request.out_trade_id", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($out_trade_id) {
            $where['out_trade_id'] = $out_trade_id;
        }
        $this->assign('out_trade_id', $out_trade_id);
        $utr = I("request.utr", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($utr) {
            $where['utr'] = $utr;
        }
        $this->assign('utr', $utr);
        $datetime = urldecode(I("request.datetime", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($datetime) {
            list($cstime, $cetime) = explode('|', $datetime);
            $where['datetime'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
        }else{
            $todayBegin = date('Y-m-d') . ' 00:00:00';
            $todyEnd = date('Y-m-d') . ' 23:59:59';
            $where['datetime'] = ['between', [strtotime($todayBegin), strtotime($todyEnd)]];
            $datetime = $todayBegin . ' | ' . $todyEnd;
        }
        $this->assign('datetime', $datetime);
        $status = I("request.status", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($status != "") {
            if ($status == '1or2') {
                $where['pay_status'] = array('between', array('1', '2'));
            } else {
                $where['pay_status'] = array('eq', $status);
            }
        }
        $this->assign('status', $status);

        $count = M('Utr')->where($where)->count();
        $size = 50;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }

        $page = new Page($count, $rows);
        $list = M('Utr')
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('id desc')
            ->select();
        $this->assign('uid', session("admin_auth.uid"));
        $this->assign('rows', $rows);
        $this->assign("list", $list);
        $this->assign('page', $page->show());
        $this->display();
    }

    /**
     * 导出交易订单
     * */
    public function exportUtrOrder()
    {
        UserLogService::HTwrite(5, '导出UTR查询记录', '导出UTR查询记录');
        $where = array();
        $memberid = I("request.memberid", 0, 'intval');
        if ($memberid) {
            $where['pay_memberid'] = array('eq', $memberid);
        }
        $pay_orderid = I("request.pay_orderid", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($pay_orderid) {
            $where['pay_orderid'] = $pay_orderid;
        }
        $out_trade_id = I("request.out_trade_id", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($out_trade_id) {
            $where['out_trade_id'] = $out_trade_id;
        }
        $utr = I("request.utr", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($utr) {
            $where['utr'] = $utr;
        }
        $datetime = urldecode(I("request.datetime", '', 'trim,string,strip_tags,htmlspecialchars'));
        if ($datetime) {
            list($cstime, $cetime) = explode('|', $datetime);
            $where['datetime'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
        }
        $status = I("request.status", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($status != "") {
            if ($status == '1or2') {
                $where['pay_status'] = array('between', array('1', '2'));
            } else {
                $where['pay_status'] = array('eq', $status);
            }
        }

        $data = M('Utr')->where($where)->order('id desc')->select();

//        echo M('Utr')->getLastSql();
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
            switch ($item['check_status']) {
                case 0:
                    $check_status = '不存在';
                    break;
                case 1:
                    $check_status = '不存在';
                    break;
                case 2:
                    $check_status = '成功';
                    break;
                case 3:
                    $check_status = '失败';
                    break;
                case 4:
                    $check_status = '超时';
                    break;
                case 5:
                    $check_status = '未查询';
                    break;
            }
            if ($item['datetime']) {
                $datetime = date('Y-m-d H:i:s', $item['datetime']);
            } else {
                $datetime = 0;
            }
            $list[] = [
                'pay_memberid' => $item['pay_memberid']+10000,
                'pay_orderid' => $item['pay_orderid'],
                'out_trade_id' => $item['out_trade_id'],
                'utr' => $item['utr'],
                'pay_tongdao' => $item['pay_tongdao'],
                'pay_status' => $status,
                'check_status' => $check_status,
                'datetime' => $datetime,
            ];
        }

        $title = [
            '商户编号',
            '系统订单号',
            '下游订单号',
            'UTR',
            '支付通道',
            '订单状态',
            '查询状态',
            '创建时间',
        ];
        exportCsv($list, $title);

        // exportexcel2($list, $title, $numberField);
        // 将已经写到csv中的数据存储变量销毁，释放内存占用
        unset($list);
        //刷新缓冲区
        ob_flush();
        flush();

    }
}
