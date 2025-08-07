<?php

namespace Cli\Controller;

use \think\Controller;
use Think\Log;

/**
 * @author mapeijian
 * @date   2018-06-06
 */
class ExportController extends Controller
{
    public $_site='';
    public function __construct()
    {
        parent::__construct();
        //自动创建日志文件
        $filePath = './Runtime/Logs/Cli/';
        if(@mkdirs($filePath, 777)) {
            $destination = $filePath.'cli_export.log';
            if(!file_exists($destination)) {
                $handle = @fopen($destination,   'wb ');
                @fclose($handle);
            }
        }
        $this->_site= 'https://' . C("DOMAIN") . '/';
    }
    public function index(){
        echo "[" . date('Y-m-d H:i:s'). "] 自动代付任务触发\n";
        $redis = $this->redis_connect();
        
        // $count = $redis->lLen('downList_now');
        if($count >= 3){        //允许5条导出同时进行
            echo "[" . date('Y-m-d H:i:s'). "] 有执行中的任务\n";
            return;
        }
        //取出第一个
        $downKey = $redis->lPop('downList');
        // $redis->rPush('downList', $downKey);
        // $downKey = 'download_admin_54_20241018195520';
        $list_msg = $redis->get($downKey);
        if(!empty($list_msg)){
            $list =  json_decode($list_msg,true);
            if($list['status'] == 0){
                $list['status'] = 1;
                $ttl = $redis->ttl($downKey);
                if($ttl > 60){
                    $redis->set($downKey , json_encode($list, JSON_UNESCAPED_UNICODE) ,$ttl);
                }
                
                $re = false;
                //插入正在下载列表
                $redis->rPush('downList_now', $downKey);
                // 设置这个值的过期时间
                $redis->expire('downList_now', 600);
                if($list['type']==1 || $list['type']==2){
                    $re = $this->exportorder($list['where'], $list['uid'], $list['type']);
                }elseif($list['type']==3 || $list['type']==4){
                    $re = $this->exportweituo($list['where'], $list['uid'], $list['type']);
                }elseif($list['type']==5 || $list['type']==6){
                    $re = $this->exceldownload($list['where'], $list['uid'], $list['type']);
                }elseif($list['type']==7){
                    $re = $this->exportUtrOrder($list['where'], $list['uid'], $list['type']);
                }
                if($re !== false && !empty($re)){
                    if($re ==='noData'){
                        $list['url'] = 'noData';
                        $ttl = $redis->ttl($downKey);
                        if($ttl > 60){
                            $redis->set($downKey , json_encode($list, JSON_UNESCAPED_UNICODE) ,$ttl);
                        }
                    }else{
                        $list['url'] = $this->_site . $re;
                        $ttl = $redis->ttl($downKey);
                        if($ttl > 60){
                            $redis->set($downKey , json_encode($list, JSON_UNESCAPED_UNICODE) ,$ttl);
                        }
                    }
                    
                }
                // 获取队列中的所有元素
                $queueItems = $redis->lrange('downList_now', 0, -1);
                // 遍历查找并删除特定元素
                foreach ($queueItems as $index => $item) {
                    if ($item == $downKey) {
                        // 删除特定元素，其中$index是基于0的索引
                        $redis->lrem('downList_now', $downKey, $index);
                        break; // 只删除第一个匹配项
                    }
                }
            }
        }else{
            echo "[" . date('Y-m-d H:i:s'). "] 不需要处理\n";
        }
        echo "[" . date('Y-m-d H:i:s'). "] 自动代付任务结束\n\n";
    }
    
    public function redis_connect(){
        //创建一个redis对象
        $redis = new \Redis();
        //连接 Redis 服务
        $redis->connect(C('REDIS_HOST'), C('REDIS_PORT'));
        //密码验证
        $redis->auth(C('REDIS_PWD'));
        //设置key-value
        // $redis->set('downloadList','111');
        // 设置键的过期时间为3600秒
        // $redis->expire('downloadList',3600);
        //获取value
        // $list = $redis->get('downloadList');
        
        // 在列表头部插入元素
        // $redis->lPush('list', 'value1');
        // 从列表左侧弹出元素
        // $redis->lPop('list');
        // 在列表尾部插入元素
        // $redis->rPush('list', 'value2');
        // 获取列表的长度
        // $length = $redis->lLen('list');
        // 获取列表的所有元素
        // $list = $redis->lRange('list', 0, -1);
        // 使用Redis的LRANGE命令获取分页数据
        // $list = $redis->lRange('list', $start, $end);
        // 使用SORT命令进行排序，这里以按value字典序升序为例
        // BY参数指定了使用哪个key的value作为sort的依据
        // GET参数指定了使用哪个key的value的哪部分作为排序的值
        // $redis->sort($key, ['by' => $key.*.*, 'get' => '#']));
        return $redis;
    }
    
    
    /**
     * 导出交易订单
     * */
    public function exportorder($where, $uid='', $type='')
    {
        if($where['memberid']){
            $where['pay_order.pay_memberid'] = $where['memberid'];
            unset($where['memberid']);
        }
        if($where['status']){
            if ($where['status'] == '1or2') {
                $where['pay_order.pay_status'] = array('between', array('1', '2'));
            }else{
                $where['pay_order.pay_status'] = $where['status'];
            }
            unset($where['status']);
        }
        if($where['orderid']){
            $where['pay_order.pay_orderid'] = $where['orderid'];
            unset($where['orderid']);
        }
        if($where['tongdao']){
            $where['pay_order.channel_id'] = $where['tongdao'];
            unset($where['tongdao']);
        }
        if($where['bank']){
            $where['pay_order.pay_bankcode'] = $where['bank'];
            unset($where['bank']);
        }
        if ($where['createtime']) {
            list($cstime, $cetime) = explode('|', $where['createtime']);
            $where['pay_order.pay_applydate'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
            unset($where['createtime']);
        }
        if ($where['successtime']) {
            list($sstime, $setime) = explode('|', $where['successtime']);
            $where['pay_order.pay_successdate']   = ['between', [strtotime($sstime), strtotime($setime) ? strtotime($setime) : time()]];
            unset($where['successtime']);
        }
        
        $file_path = "./Uploads/download/". date('Ymd') ."/" ;
        @mkdirs($file_path, 0777);
        @chmod($file_path, 0777);
        $fileName = $file_path . $uid . '_order' .time();
        $fileNameArr = array();
        $title = ['外部订单号', '系统订单号', '商户编号', '商户用户名', '交易金额', '手续费', '实际金额', '提交时间', '成功时间', '状态', '通道'];
        $filed = "pay_member.username,pay_order.id,pay_order.out_trade_id,pay_order.pay_orderid,pay_order.pay_memberid,pay_order.pay_amount,pay_order.pay_poundage,pay_order.pay_actualamount,pay_order.pay_applydate,pay_order.pay_successdate,pay_order.pay_bankname,pay_order.pay_zh_tongdao,pay_order.memberid,pay_order.pay_status";

        $OrderModel = D('Order');
        $tables = $OrderModel->getTables($where);
        foreach ($tables as $table) {
            $sqlCount = $OrderModel->table($table)
                    ->alias('as pay_order')
                    ->join('LEFT JOIN pay_member ON pay_member.id+10000 = pay_order.pay_memberid')
                    ->where($where)
                    ->count();
                    echo $OrderModel->table($table)->getLastSql();
            if($sqlCount < 1){
                continue;
            }

            $mark = "order" . str_replace("pay_order", "", $table);
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
                        'pay_status' => "\t" .$status,
                    ];
                    if($type==1){
                        $list['pay_zh_tongdao'] = "\t" .$item['pay_zh_tongdao'];
                    }else{
                        $list['pay_bankname'] = "\t" .$item['pay_bankname'];
                    }
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
        if(!empty($fileNameArr)){
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
            return $filename;
        }else{
            return 'noData';
        }
    }
    
    
    //导出委托提款记录
    public function exportweituo($where,$uid='', $type='')
    {
        if(!$where['type'] || $where['type']!=2){
            $where['type'] = ['neq',2];
        }
        if($where['memberid']){
            $where['userid'] = $where['memberid']-10000;
            unset($where['memberid']);
        }
        if($where['tongdao']){
            $where['df_id'] = $where['tongdao'];
            unset($where['tongdao']);
        }
        if($where['memo']){
            $where['memo'] = ['like', "%" . $where['memo'] . "%"];
        }
        if ($where['status']) {
            if ($where['status'] == '2or3') {
                $where['status'] = array('between', array('2', '3'));
            } elseif ($where['status'] == '4or5') {
                $where['status'] = array('between', array('4', '5'));
            }
        }
        if ($where['createtime']) {
            list($cstime, $cetime) = explode('|', $where['createtime']);
            $where['sqdatetime'] = ['between', [$cstime, $cetime ? $cetime : date('Y-m-d')]];
            unset($where['createtime']);
        }
        if ($where['successtime']) {
            list($sstime, $setime) = explode('|', $where['successtime']);
            $where['cldatetime']   = ['between', [$sstime, $setime ? $setime : date('Y-m-d')]];
            unset($where['successtime']);
        }
        
        $file_path = "./Uploads/download/". date('Ymd') ."/" ;
        @mkdirs($file_path, 0777);
        @chmod($file_path, 0777);
        $fileName = $file_path . $uid . '_dforder' .time();
        $fileNameArr = array();

        if($type==3){
            $title = array('商户编号', '系统订单号', '外部订单号', '结算金额', '手续费', '到账金额', '银行名称', '支行名称', '银行卡号', '开户名', '申请时间', '处理时间', '状态', "备注","通道");
        }else{
            $title = array('商户编号', '系统订单号', '外部订单号', '结算金额', '手续费', '到账金额', '银行名称', '支行名称', '银行卡号', '开户名', '申请时间', '处理时间', '状态', "备注");
        }
        $filed = 'userid,df_name,channel_mch_id,orderid,out_trade_no,tkmoney,sxfmoney,money,bankname,bankzhiname,banknumber,bankfullname,sqdatetime,cldatetime,status,memo';
        $Wttklist = D('Wttklist');
        $tables = $Wttklist->getTables($where);
        
        $datas =[];
        foreach ($tables as $table) {
            $sqlCount = $Wttklist->table($table)->field($filed)->where($where)->count();
            // echo $Wttklist->table($table)->getLastSql();
            if($sqlCount < 1){
                continue;
            }

            $mark = "dforder" . str_replace("pay_wttklist", "", $table);
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
                
                $data = $Wttklist->table($table)->field($filed)->where($where)->order('id desc')->limit($i * $sqlLimit,$sqlLimit)->select();
                
                foreach ($data as $item) {
                    switch ($item['status']) {
                        case 0:
                            $status = '未处理';
                            break;
                        case 1:
                            $status = '处理中';
                            break;
                        case 2:
                            $status = '成功未返回';
                            break;
                        case 3:
                            $status = "成功已返回";
                            break;
                        case 4:
                            $status = "失败未返回";
                            break;
                        case 5:
                            $status = "失败已返回";
                            break;
                        case 6:
                            $status = "已驳回";
                            break;
                    }
        
                    
                    if($type==3){
                        $list = array(
                            'memberid'       => "\t" .$item['userid'] + 10000,
                            'orderid'        => "\t" .$item['orderid'],
                            'out_trade_no'   => "\t" .$item['out_trade_no'],
                            'tkmoney'        => $item['tkmoney'],
                            'sxfmoney'       => $item['sxfmoney'],
                            'money'          => $item['money'],
                            'bankname'       => "\t" .$item['bankname'],
                            'bankzhiname'    => "\t" .$item['bankzhiname'],
                            'banknumber'     => "\t" .$item['banknumber'],
                            'bankfullname'   => "\t" .$item['bankfullname'],
                            'sqdatetime'     => "\t" .$item['sqdatetime'],
                            'cldatetime'     => "\t" .$item['cldatetime'],
                            'status'         => "\t" .$status,
                            "memo"           => "\t" .$item["memo"],
                            "df_name"        => "\t" .$item["df_name"]
                        );
                        $title = array_push($title,'通道');
                    }else{
                        $list = array(
                        'memberid'       => "\t" .$item['userid'] + 10000,
                        'orderid'        => "\t" .$item['orderid'],
                        'out_trade_no'   => "\t" .$item['out_trade_no'],
                        'tkmoney'        => $item['tkmoney'],
                        'sxfmoney'       => $item['sxfmoney'],
                        'money'          => $item['money'],
                        'bankname'       => "\t" .$item['bankname'],
                        'bankzhiname'    => "\t" .$item['bankzhiname'],
                        'banknumber'     => "\t" .$item['banknumber'],
                        'bankfullname'   => "\t" .$item['bankfullname'],
                        'sqdatetime'     => "\t" .$item['sqdatetime'],
                        'cldatetime'     => "\t" .$item['cldatetime'],
                        'status'         => "\t" .$status,
                        "memo"           => "\t" .$item["memo"],
                    );
                    }
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
        if(!empty($fileNameArr)){
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
            return $filename;
        }else{
            return 'noData';
        }
    }


    /**
     * 资金变动记录导出
     */
    public function exceldownload($where, $uid='', $type='')
    {
        if ($where['memberid']) {
            $where['userid'] = array('eq', $where['memberid']-10000);
            unset($where['memberid']);
        }
        if ($where['orderid']) {
            $where['transid'] = array('eq', $where['orderid']);
            unset($where['orderid']);
        }
        if ($where['bank']) {
            $where['lx'] = array('eq', $where['bank']);
            unset($where['bank']);
        }
        if($where['currency'] ==='PHP'){
            $where['paytype'] = ['between', [1,3]];
            unset($where['currency']);
        }
        if($where['currency'] ==='INR'){
            $where['paytype'] = ['eq', 4];
            unset($where['currency']);
        }
        if ($where['createtime']) {
            list($cstime, $cetime) = explode('|', $where['createtime']);
            $where['datetime'] = ['between', [$cstime, $cetime ? $cetime : date('Y-m-d')]];
            unset($where['createtime']);
        }
        
        $file_path = "./Uploads/download/". date('Ymd') ."/" ;
        @mkdirs($file_path, 0777);
        @chmod($file_path, 0777);
        $fileName = $file_path . $uid . '_change' .time();
        $fileNameArr = array();

        $title = array('订单号', '外部订单号','用户名', '类型', '原金额', '变动金额', '变动后金额', '变动时间', '备注');
        $filed = 'transid,orderid,userid,lx,ymoney,money,gmoney,datetime,contentstr';
        
        $MoneychangeModel = D('Moneychange');
        $tables = $MoneychangeModel->getTables($where);
        $datas =[];
        foreach ($tables as $table) {
            $sqlCount = $MoneychangeModel->table($table)->where($where)->count();
            echo  $MoneychangeModel->table($table)->getLastSql();
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
                    $list['orderid'] = "\t" .$value["orderid"];
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
        }
        if(!empty($fileNameArr)){
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
            return $filename;
        }else{
            return 'noData';
        }
    }
    
    /**
     * 导出Utr
     * */
    public function exportUtrOrder($where, $uid='', $type='')
    {
        if ($where['memberid']) {
            $where['pay_memberid'] = array('eq', $where['memberid']-10000);
            unset($where['memberid']);
        }
        if ($where['createtime']) {
            list($cstime, $cetime) = explode('|', $where['createtime']);
            $where['datetime'] = ['between', [$cstime, $cetime ? $cetime : date('Y-m-d')]];
            unset($where['createtime']);
        }
        
        $file_path = "./Uploads/download/". date('Ymd') ."/" ;
        @mkdirs($file_path, 0777);
        @chmod($file_path, 0777);
        $fileName = $file_path . $uid . '_utrOrder' .time();
        $fileNameArr = array();
        
        $utrModel = M('Utr');
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
        $filed = '*';
        $sqlCount = $utrModel->where($where)->count();
        echo $utrModel->getLastSql();
        if($sqlCount < 1){
            return;
        }

        $mark = "utrOrder";
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
                
            $data = $utrModel->field($filed)->where($where)->order('id desc')->limit($i * $sqlLimit,$sqlLimit)->select();
                
    //        echo $utrModel->getLastSql();
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
                $list = [
                    'pay_memberid' => "\t" .$item['pay_memberid']+10000,
                    'pay_orderid' => "\t" .$item['pay_orderid'],
                    'out_trade_id' => "\t" .$item['out_trade_id'],
                    'utr' => "\t" .$item['utr'],
                    'pay_tongdao' => "\t" .$item['pay_tongdao'],
                    'pay_status' => "\t" .$status,
                    'check_status' => "\t" .$check_status,
                    'datetime' => "\t" .$datetime,
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
        if(!empty($fileNameArr)){
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
            return $filename;
        }else{
            return 'noData';
        }
    }

}