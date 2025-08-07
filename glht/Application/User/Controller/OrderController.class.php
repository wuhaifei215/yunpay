<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-08-22
 * Time: 14:34
 */
namespace User\Controller;

use Think\Page;
use Org\Net\UserLogService;

/**
 * 订单管理控制器
 * Class OrderController
 * @package User\Controller
 */
class OrderController extends UserController
{

    public function __construct()
    {
        parent::__construct();
        $this->assign("Public", MODULE_NAME); // 模块名称
    }

    public function index()
    {
        $status = I("request.status", '');
        if ($status != '') {
            $status = intval($status);
        }
        switch ($status) {
            case '0':
                $title = '未支付订单';
                break;
            case '1':
                $title = '手工补发订单';
                break;
            case '2':
                $title = '成功订单';
                break;
            default:
                $title = '所有订单';
                break;
        }
        UserLogService::write(1, '访问' . $title . '列表页面', '访问' . $title . '订单列表页面');

        //通道
        $banklist = M("Product")->field('id,name,code')->select();
        $this->assign("banklist", $banklist);
        $where = array();

        $orderid = I("request.orderid", '', 'string,strip_tags,htmlspecialchars');
        if ($orderid) {
            $where['pay_orderid'] = $orderid;
        }
        $this->assign("orderid", $orderid);
        $out_trade_id = I("request.out_trade_id", '', 'string,strip_tags,htmlspecialchars');
        if ($out_trade_id) {
            $where['out_trade_id'] = $out_trade_id;
        }
        $this->assign("out_trade_id", $out_trade_id);
        $bank = I("request.bank", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($bank) {
            $where['pay_bankcode'] = array('eq', $bank);
        }
        $this->assign('bank', $bank);
        $body = I("request.body", '', 'string,strip_tags,htmlspecialchars');
        if ($body) {
            $where['pay_productname'] = array('like', '%' . $body . '%');
        }
        $this->assign("body", $body);
        if ($status != '' || $status === 0) {
            $where['pay_status'] = array('eq', $status);
        }
        $this->assign("status", $status);
        $successtime = urldecode(I("request.successtime", '', 'string,strip_tags,htmlspecialchars'));
        if ($successtime) {
            list($sstime, $setime) = explode('|', $successtime);
            $where['pay_successdate'] = $sumMap['pay_successdate'] = $failMap['pay_successdate'] = $map['create_at'] = ['between', [strtotime($sstime), strtotime($setime) ? strtotime($setime) : time()]];
        }
        $this->assign("successtime", $successtime);
        $createtime = urldecode(I("request.createtime", '', 'string,strip_tags,htmlspecialchars'));
        if ($createtime) {
            list($cstime, $cetime) = explode('|', $createtime);
            $where['pay_applydate'] = $sumMap['pay_applydate'] = $failMap['pay_applydate'] = $map['create_at'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
        }
        if (!$createtime && !$successtime && !$orderid) {
            $todayBegin = date('Y-m-d') . ' 00:00:00';
            $todyEnd = date('Y-m-d') . ' 23:59:59';
            $where['pay_applydate'] = ['between', [strtotime($todayBegin), strtotime($todyEnd)]];
            $createtime = $todayBegin . ' | ' . $todyEnd;
        }
        $this->assign("createtime", $createtime);
        
        $where['isdel'] = 0;

        $OrderModel = D('Order');

        //通道代理商户
        if ($this->fans['groupid'] == 8) {
            $where['channel_id'] = $products[0]['channel'];
            $where['pay_status'] = ['between', [1, 2]];

            $statistic = $OrderModel->getSum('pay_amount,pay_actualamount',$where);
            // var_dump($statistic);die;
//            $statistic = M('Order')->field(['sum(`pay_amount`) pay_amount,  sum(`pay_actualamount`) pay_actualamount'])->where($where)->find();

            $tkconfig = M('Tikuanconfig')->where(['userid' => $this->fans['uid']])->field('t1zt,tkzt,systemxz')->find();
            if (!$tkconfig || $tkconfig['tkzt'] != 1 || $tkconfig['systemxz'] != 1) {
                $tkconfig = M('Tikuanconfig')->where(['issystem' => 1])->field('t1zt,tkzt,systemxz')->find();
            }
            $list = M('ProductUser')
                ->join('LEFT JOIN __PRODUCT__ ON __PRODUCT__.id = __PRODUCT_USER__.pid')
                ->where(['pay_product_user.userid' => $this->fans['uid'], 'pay_product_user.status' => 1, 'pay_product.isdisplay' => 1])
                ->field('pay_product.name,pay_product.id,pay_product.t0defaultrate,pay_product.t0fengding,pay_product.defaultrate,pay_product.fengding')
                ->select();
            if (!empty($list)) {
                foreach ($list as $key => $item) {
                    $_userrate = M('Userrate')->where(['userid' => $this->fans['uid'], 'payapiid' => $item['id']])->find();
                    if ($tkconfig['t1zt'] == 0) { //T+0费率
                        $feilv = $_userrate['t0feilv'] ? $_userrate['t0feilv'] : $item['t0defaultrate']; // 交易费率
                    } else { //T+1费率
                        $feilv = $_userrate['feilv'] ? $_userrate['feilv'] : $item['defaultrate']; // 交易费率
                    }
                    $list[$key]['feilv'] = $feilv;
                }
            }
            $pay_poundage = $statistic['pay_amount'] * $feilv;
            //平台分润
            $this->assign('pay_amount', number_format($statistic['pay_amount'], 2));
            $this->assign('pay_actualamount', number_format($statistic['pay_actualamount'], 2));
            $this->assign('pay_poundage', number_format($pay_poundage, 2));
        } else {
            $where['pay_memberid'] = 10000 + $this->fans['uid'];
        }

        $count = $OrderModel->getCount($where);
        $size = 15;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }
        $page = new Page($count, $rows);

        $list = $OrderModel->getOrderByDateRange('*', $where, $page->firstRow . ',' . $page->listRows, 'id desc');
        //统计今日交易数据
        // if ($status == '2') {
        //今日成功交易总额
        $todayBegin = date('Y-m-d') . ' 00:00:00';
        $todyEnd = date('Y-m-d') . ' 23:59:59';

        //今日失败笔数
        $t_where['pay_status']=['eq',0];
        $t_where['pay_memberid']= 10000 + $this->fans['uid'];
        $stat['todayfailcount'] = $OrderModel->getCount($t_where);

        //今日实际到账总额
        $t_where = [
            'pay_memberid' => 10000 + $this->fans['uid'],
            'pay_successdate' => ['between', [strtotime($todayBegin), strtotime($todyEnd)]],
            'pay_status' => ['between', [1, 2]]
        ];
        $taodaypay_amount = $OrderModel->getSum('pay_amount,pay_actualamount', $t_where);
        $stat['todaysum'] = $taodaypay_amount['pay_amount'];

        //今日实际到账笔数
        $stat['taodayactualamount'] = $taodaypay_amount['pay_actualamount'];

        //今日成功笔数
        $stat['todaysuccesscount'] = $OrderModel->getCount($t_where);


        foreach ($stat as $k => $v) {
            $stat[$k] = $v + 0;
        }
        $this->assign('stat', $stat);
        // }

        //如果指定时间范围则按搜索条件做统计
        if ($createtime || $successtime) {
            $sumMap = $failMap = $where;
            //成功
            $sumMap['pay_status'] = ['between', [1, 2]];
            $sum = $OrderModel->getSum('pay_amount,pay_actualamount',$sumMap);
            $sum['success_count'] = $OrderModel->getCount($sumMap);
            //失败
            $failMap['pay_status'] = ['eq',0];
            $sum['fail_count'] = $OrderModel->getCount($failMap);
            
            $this->assign('sum', $sum);
        }
        $this->assign('uid', 10000+session("user_auth.uid"));
        $this->assign('rows', $rows);
        $this->assign("list", $list);
        $this->assign('page', $page->show());
        C('TOKEN_ON', false);
        $this->display();
    }

    /**
     * 导出交易订单
     * */
    public function exportorder()
    {
        UserLogService::write(5, '导出交易订单', '导出交易订单');

        //通道
        $products = M('ProductUser')
            ->join('LEFT JOIN __PRODUCT__ ON __PRODUCT__.id = __PRODUCT_USER__.pid')
            ->where(['pay_product_user.status' => 1, 'pay_product_user.userid' => $this->fans['uid']])
            ->field('pay_product.name,pay_product.id,pay_product.code,pay_product_user.channel')
            ->select();

        $orderid = I("request.orderid", '', 'string,strip_tags,htmlspecialchars');
        if ($orderid) {
            $where['out_trade_id'] = $orderid;
        }
        $ddlx = I("request.ddlx", '', 'string,strip_tags,htmlspecialchars');
        if ($ddlx != "") {
            $where['ddlx'] = array('eq', $ddlx);
        }
        $bankcode = I("request.bankcode", '', 'string,strip_tags,htmlspecialchars');
        if ($bankcode) {
            $where['pay_bankcode'] = array('eq', $bankcode);
        }
        $bank = I("request.bank", '', 'string,strip_tags,htmlspecialchars');
        if ($bank) {
            $where['pay_bankname'] = array('eq', $bank);
        }

        $status = I("request.status", 0, 'intval');
        if ($status) {
            $where['pay_status'] = array('eq', $status);
        }
        $successtime = urldecode(I("request.successtime", '', 'string,strip_tags,htmlspecialchars'));
        if ($successtime) {
            list($sstime, $setime) = explode('|', $successtime);
            $where['pay_successdate'] = ['between', [strtotime($sstime), strtotime($setime) ? strtotime($setime) : time()]];
        }
        $createtime = urldecode(I("request.createtime", '', 'string,strip_tags,htmlspecialchars'));
        if ($createtime) {
            list($cstime, $cetime) = explode('|', $createtime);
            if(diffBetweenTwoDays($cstime, $cetime) > 7){
                $this->ajaxReturn(['status' => 0, 'msg' => "请下载一周范围内的数据！"]);
            }
            $where['pay_applydate'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
        }
        if (!$createtime && !$successtime && !$orderid) {
            $todayBegin = date('Y-m-d') . ' 00:00:00';
            $todyEnd = date('Y-m-d') . ' 23:59:59';
            $where['pay_applydate'] = ['between', [strtotime($todayBegin), strtotime($todyEnd)]];
        }
        $where['isdel'] = 0;
        //通道代理商户
        if ($this->fans['groupid'] == 8) {
            $where['channel_id'] = $products[0]['channel'];
            $where['pay_status'] = ['in', '1,2'];
        } else {
            $where['pay_memberid'] = $this->fans['memberid'];
        }
        
        $file_path = "./Uploads/download/". date('Ymd') ."/" ;
        @mkdirs($file_path);
        $uid = $this->fans['memberid'];
        $fileName = $file_path . $uid . '_order' .time();
        $fileNameArr = array();

        $title = ['外部订单号', '系统订单号', '商户编号', '交易金额', '手续费', '实际金额', '提交时间', '成功时间', '通道', '状态'];
        $field = 'out_trade_id,pay_orderid,pay_memberid,pay_amount,pay_poundage,pay_actualamount,pay_applydate,pay_successdate,pay_bankname,pay_status';
        
        $OrderModel = D('Order');
        $tables = $OrderModel->getTables($where);
        foreach ($tables as $table) {
            $sqlCount = $OrderModel->table($table)->where($where)->count();
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
                    $list = array(
                        'out_trade_id'      => "\t" .$item['out_trade_id'],
                        'pay_orderid'       =>"\t" .$item['pay_orderid'],
                        'pay_memberid'      => "\t" .$item['pay_memberid'],
                        'pay_amount'        => $item['pay_amount'],
                        'pay_poundage'      => $item['pay_poundage'],
                        'pay_actualamount'  => $item['pay_actualamount'],
                        'pay_applydate'     => "\t" .date('Y-m-d H:i:s', $item['pay_applydate']),
                        'pay_successdate'   =>  $item['pay_successdate']?"\t" .date('Y-m-d H:i:s', $item['pay_successdate']):"\t" .'---',
                        'pay_bankname'      => "\t" .$item['pay_bankname'],
                        'pay_status'        => "\t" .$status,
                    );
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
        
        $OrderModel = D('Order');
        $data = $OrderModel->getOrderByDateRange($field, $where);
        // $data = M('Order')->where($where)->order('id desc')->select();
        return true;
    }

    /**
     * 查看订单
     */
    public function show()
    {
        $orderid = I("get.oid", '');
        if ($orderid) {
            $m_Order    = D("Order");
            $date = date('Ymd',strtotime(substr($orderid, 0, 8)));  //获取订单日期
            $order = $m_Order->table($m_Order->getRealTableName($date))
                ->alias('as pay_order')
                ->join('LEFT JOIN pay_member ON (pay_member.id + 10000) = pay_order.pay_memberid')
                ->field('pay_member.id as userid,pay_member.username,pay_member.realname,pay_order.*')
                ->where(['pay_order.pay_orderid' => $orderid])
                ->find();

            // $order = M('Order')
            //     ->where(['id' => $id])
            //     ->find();
            // $OrderModel = D('Order');
            // $tables = $OrderModel->getTables();
            // foreach ($tables as $v){
            //     $order = $OrderModel->table($v)->alias('as pay_order')
            //     ->join('LEFT JOIN __MEMBER__ ON (__MEMBER__.id + 10000) = __ORDER__.pay_memberid')
            //     ->field('pay_member.id as userid,pay_member.username,pay_member.realname,pay_order.*')
            //     ->where(['pay_order.id' => $id])
            //     ->find();
            //     //  echo $OrderModel->getLastSql();
            //     if(!empty($order)) break;
            // }
        }

        UserLogService::write(1, '查看订单', '查看订单，orderID：' . $orderid);
        $this->assign('order', $order);
        $this->display();
    }

    /**
     *  伪删除订单
     */
    /*
    public function delOrder()
    {
        if(IS_POST){
            $id = I('post.id',0,'intval');
            if($id){
                $res = M('Order')->where(['id'=>$id,'pay_memberid'=>$this->fans['memberid']])->setField('isdel',1);
            }
            $this->ajaxReturn(['status'=>$res]);
        }
    }
    */
}

?>
