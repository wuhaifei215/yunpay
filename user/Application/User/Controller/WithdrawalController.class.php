<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-08-22
 * Time: 14:34
 */
namespace User\Controller;

use Think\Page;
use Think\Upload;
use Org\Net\UserLogService;

/**
 * 商家结算控制器
 * Class WithdrawalController
 * @package User\Controller
 */

class WithdrawalController extends UserController
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     *  委托结算记录
     */
    public function payment()
    {
        UserLogService::write(1, '访问代付记录', '访问代付记录');
        //通道
        $banklist = M("Product")->select();
        $this->assign("banklist", $banklist);

        $where        = array();
        $type = I("request.type",  0, 'intval');
        if($type === 2){
            $where['df_type'] = ['eq', 2];
        }else{
            $where['df_type'] = ['neq', 2];
        }
        $this->assign('type', $type);
        $bankcode = I("request.bankcode");
        if ($bankcode) {
            $where['bankcode'] = array('eq', $bankcode);
        }
        $this->assign("bankcode", $bankcode);
        
        $orderid = I("request.orderid", '', 'string,strip_tags,htmlspecialchars');
        if ($orderid) {
            $where['orderid'] = array('eq', $orderid);
        }
        $this->assign("orderid", $orderid);
        
        $out_trade_no = I("request.out_trade_no", '', 'string,strip_tags,htmlspecialchars');
        if ($out_trade_no) {
            $where['out_trade_no'] = array('eq', $out_trade_no);
        }
        $this->assign("out_trade_no", $out_trade_no);
        
        $bankfullname = I("request.bankfullname", '', 'string,strip_tags,htmlspecialchars');
        if ($bankfullname) {
            $where['bankfullname'] = array('eq', $bankfullname);
        }
        $this->assign("bankfullname", $bankfullname);
        $tongdao = I("request.tongdao", '', 'string,strip_tags,htmlspecialchars');
        if ($tongdao) {
            $where['payapiid'] = array('eq', $tongdao);
        }
        $this->assign("tongdao", $tongdao);
        $status = I("request.status", '', 'string,strip_tags,htmlspecialchars');
        if ($status != "") {
            $where['status'] = array('eq', $status);
        }
        $this->assign("status", $status);
        $successtime = urldecode(I("request.successtime", '', 'string,strip_tags,htmlspecialchars'));
        if ($successtime) {
            list($sstime, $setime) = explode('|', $successtime);
            $where['cldatetime']   = ['between', [$sstime, $setime ? $setime : date('Y-m-d')]];
        }
        $this->assign("successtime", $successtime);
        $createtime = urldecode(I("request.createtime", '', 'string,strip_tags,htmlspecialchars'));
        if ($createtime) {
            list($cstime, $cetime) = explode('|', $createtime);
            $where['sqdatetime']   = ['between', [$cstime, $cetime ? $cetime : date('Y-m-d')]];
        }
        //没有搜索条件，默认显示当前
        if (!$createtime && !$successtime && !$out_trade_no && !$orderid) {
            $todayBegin = date('Y-m-d') . ' 00:00:00';
            $todyEnd = date('Y-m-d') . ' 23:59:59';
            if (!$createtime && !$successtime) {
                $where['sqdatetime'] = ['between', [$todayBegin, $todyEnd]];
            }
            $createtime = $todayBegin . ' | ' . $todyEnd;
        }
        $this->assign("createtime", $createtime);
        $where['userid'] = $this->fans['uid'];
        $size  = 15;
        $rows  = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }
        $field = 'id,orderid,out_trade_no,userid,tkmoney,sxfmoney,money,bankfullname,banknumber,bankname,sqdatetime,cldatetime,status,memo,bankcode';
        $Wttklist = D('Wttklist');
        $count = $Wttklist->getCount($where);
        $page = new Page($count, $rows);
        $list = $Wttklist->getOrderByDateRange($field, $where, $page->firstRow . ',' . $page->listRows, 'id desc');
        
        //统计汇总信息
        //代付总金额
        $totalMap = $where;
		$totalMap['userid'] = $this->fans['uid'];
        $totalMap['status'] = ['between',['2','3']];
        $stat_arr = $Wttklist->getSum('money',$totalMap);
        $stat['total'] = $stat_arr['money'];
//        $stat['total'] = round(M('Wttklist')->where($totalMap)->sum('money'), 4);
        //提款成功总笔数
        $totalMap['status'] = ['between',['2','3']];
        $stat['total_success_count'] = $Wttklist->getCount($totalMap);
//        $stat['total_success_count'] = M('Wttklist')->where($totalMap)->count();
        //提款失败总笔数
        $totalMap['status'] = ['between',['4','6']];
        $stat['total_fail_count'] = $Wttklist->getCount($totalMap);
//        $stat['total_fail_count'] = M('Wttklist')->where($totalMap)->count();

        //统计今日代付信息
        $beginToday    = date("Y-m-d").' 00:00:00';
        $endToday      = date("Y-m-d").' 23:59:59';
        $map['userid'] = $this->fans['uid'];
        //今日代付总金额
        $map['cldatetime']   = array('between', array($beginToday, $endToday));
        $map['status']       = ['between',['2','3']];
        $stat_arr = $Wttklist->getSum('money',$map);
        $stat['totay_total'] = $stat_arr['money'];
//        $stat['totay_total'] = round(M('Wttklist')->where($map)->sum('money'), 4);
        //今日代付成功笔数
        $stat['totay_success_count'] = $Wttklist->getCount($map);
//        $stat['totay_success_count'] = M('Wttklist')->where($map)->count();
        //今日代付待结算
        unset($map['cldatetime']);
        //今日代付失败笔数
        $map['status']            = ['between',['4','6']];
        $stat['totay_fail_count'] = $Wttklist->getCount($map);
//        $stat['totay_fail_count'] = M('Wttklist')->where($map)->count();

        foreach ($list as $k => $v){
            foreach ($banklist as $kk => $vv){
                if($v['bankcode'] === $vv['id']){
                    $list[$k]['bankcode'] = $vv['name'];
                }
            }
        }
        // echo "<pre>";
        // var_dump($list);
        $this->assign('uid', 10000+session("user_auth.uid"));
        $this->assign('stat', $stat);
        $this->assign("list", $list);
        $this->assign("page", $page->show());
        $this->assign("rows", $rows);
        if($type === 2){
            $this->display('paymentU');
        }else{
            $this->display();
        }
    }

    //导出委托提款记录
    public function exportweituo()
    {
        UserLogService::write(5, '导出代付记录', '导出代付记录');
        $where = array();

        $orderid = I("request.orderid", '', 'string,strip_tags,htmlspecialchars');
        if ($orderid) {
            $where['orderid'] = array('eq', $orderid);
        }
        
        $out_trade_no = I("request.out_trade_no", '', 'string,strip_tags,htmlspecialchars');
        if ($out_trade_no) {
            $where['out_trade_no'] = array('eq', $out_trade_no);
        }
        
        $tongdao = I("request.tongdao", '', 'string,strip_tags,htmlspecialchars');
        if ($tongdao) {
            $where['payapiid'] = array('eq', $tongdao);
        }
        $T = I("request.T", '', 'string,strip_tags,htmlspecialchars');
        if ($T != "") {
            $where['t'] = array('eq', $T);
        }
        $status = I("request.status");
        if ($status) {
            $where['status'] = array('eq', $status);
        }
        $createtime = urldecode(I("request.createtime", '', 'string,strip_tags,htmlspecialchars'));
        if ($createtime) {
            list($cstime, $cetime) = explode('|', $createtime);
            $where['sqdatetime']   = ['between', [$cstime, $cetime ? $cetime : date('Y-m-d')]];
        }
        $successtime = urldecode(I("request.successtime", '', 'string,strip_tags,htmlspecialchars'));
        if ($successtime) {
            list($sstime, $setime) = explode('|', $successtime);
            $where['cldatetime']   = ['between', [$sstime, $setime ? $setime : date('Y-m-d')]];
        }
        $where['userid'] = $this->fans['uid'];
        
        $file_path = "./Uploads/download/". date('Ymd') ."/" ;
        @mkdirs($file_path);
        $uid = 10000+$this->fans['uid'];
        $fileName = $file_path . $uid . '_dforder' .time();
        $fileNameArr = array();
        
        $title = array('商户编号', '系统订单号', '外部订单号', '结算金额', '手续费', '到账金额', '银行名称', '支行名称', '银行卡号', '开户名', '申请时间', '处理时间', '状态', "备注");
        $filed = 'userid,df_name,channel_mch_id,orderid,out_trade_no,tkmoney,sxfmoney,money,bankname,bankzhiname,banknumber,bankfullname,sqdatetime,cldatetime,status,memo';
        
        $Wttklist = D('Wttklist');
        $tables = $Wttklist->getTables($where);
        $datas =[];
        foreach ($tables as $table) {
            $sqlCount = $Wttklist->table($table)->field($filed)->where($where)->count();
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
        return true;
    }

    /**
     *  委托结算
     */
    public function entrusted()
    {
        UserLogService::write(1, '访问代付申请页面', 'EXCEL导入方式');
        $verifysms = 0;
        //查询是否开启短信验证
        $sms_is_open = smsStatus();
        if ($sms_is_open) {
            $verifysms = 1;
            $this->assign('sendUrl', U('User/Withdrawal/entrustedSend'));
        }
        $verifyGoogle = 0;
        if($this->fans['google_secret_key'] && $this->fans['google_auth']) {
            $verifyGoogle = 1;
        }
        $this->assign('sms_is_open', $sms_is_open);
        $this->assign('verifysms', $verifysms);
        $this->assign('verifyGoogle', $verifyGoogle);
        $this->assign('auth_type', $verifyGoogle ? 1 : 0);
        //结算方式：
        $tkconfig = M('Tikuanconfig')->where(['userid' => $this->fans['uid']])->find();
        if (!$tkconfig || $tkconfig['tkzt'] != 1) {
            $tkconfig = M('Tikuanconfig')->where(['issystem' => 1])->find();
        }
        //可用余额
        $info = M('Member')->where(['id' => $this->fans['uid']])->find();

        $this->assign('tkconfig', $tkconfig);
        $this->assign('info', $info);
        $this->display();
    }
    /**
     * 发送委托结算验证码信息
     */
    public function entrustedSend()
    {
        $res = $this->send('entrusted', $this->fans['mobile'], '委托结算');
        $this->ajaxReturn(['status' => $res['code']]);
    }

    /**
     *  保存委托申请
     */
    public function saveEntrusted()
    {
        if (IS_POST) {
            $userid   = session('user_auth.uid');
            //查询是否开启短信验证
            $sms_is_open = smsStatus();
            $verifysms = 0;
            if ($sms_is_open) {
                $verifysms = 1;
            }
            //查询是否开通谷歌验证
            $verifyGoogle = 0;
            if($this->fans['google_secret_key'] && $this->fans['google_auth']) {
                $verifyGoogle = 1;
            }
            $auth_type = I('post.auth_type', 0, 'intval');
            if($verifyGoogle && $verifysms) {
                if(!in_array($auth_type,[0,1])) {
                    UserLogService::write(2, '代付申请提交失败', 'EXCEL导入方式，失败原因：参数错误');
                    $this->error("参数错误！");
                }
            } elseif($verifyGoogle && !$verifysms) {
                if($auth_type != 1) {
                    UserLogService::write(2, '代付申请提交失败', 'EXCEL导入方式，失败原因：参数错误');
                    $this->error("参数错误！");
                }
            } elseif(!$verifyGoogle && $verifysms) {
                if($auth_type != 0) {
                    UserLogService::write(2, '代付申请提交失败', 'EXCEL导入方式，失败原因：参数错误');
                    $this->error("参数错误！");
                }
            }
            if ($verifyGoogle && $auth_type == 1) {//谷歌安全码验证
                $res = check_auth_error($userid, 4);
                if(!$res['status']) {
                    UserLogService::write(2, '代付申请提交失败', 'EXCEL导入方式，失败原因：谷歌安全码输入错误次数超限');
                    $this->error($res['msg']);
                }
                $google_code   = I('post.google_code', '', 'string,strip_tags,htmlspecialchars');
                if(!$google_code) {
                    UserLogService::write(2, '代付申请提交失败', 'EXCEL导入方式，失败原因：谷歌安全码不能为空');
                    $this->error("谷歌安全码不能为空！");
                } else {
                    $ga = new \Org\Util\GoogleAuthenticator();
                    if(false === $ga->verifyCode($this->fans['google_secret_key'], $google_code, C('google_discrepancy'))) {
                        log_auth_error($userid,4);
                        UserLogService::write(2, '代付申请提交失败', 'EXCEL导入方式，失败原因：谷歌安全码错误');
                        $this->error("谷歌安全码错误！");
                    } else {
                        clear_auth_error($userid,4);
                    }
                }
            } elseif($verifysms && $auth_type == 0){//短信验证码
                $res = check_auth_error($userid, 2);
                if(!$res['status']) {
                    UserLogService::write(2, '代付申请提交失败', 'EXCEL导入方式，失败原因：短信验证码输入错误次数超限');
                    $this->error($res['msg']);
                }
                $code   = I('post.code', '', 'string,strip_tags,htmlspecialchars');
                if(!$code) {
                    UserLogService::write(2, '代付申请提交失败', 'EXCEL导入方式，失败原因：短信验证码不能为空');
                    $this->error("短信验证码不能为空！");
                } else {
                    if (session('send.entrusted') != $code || !$this->checkSessionTime('entrusted', $code)) {
                        log_auth_error($userid,2);
                        UserLogService::write(2, '代付申请提交失败', 'EXCEL导入方式，失败原因：短信验证码错误');
                        $this->error('短信验证码错误');
                    } else {
                        clear_auth_error($userid,2);
                        session('send', null);
                    }
                }
            }

            $password = I('post.password', '', 'string,strip_tags,htmlspecialchars');
            $u        = I('post.u');
            if (!$password) {
                UserLogService::write(2, '代付申请提交失败', 'EXCEL导入方式，失败原因：支付密码不能为空');
                $this->error('支付密码不能为空！');
            }
            //上传文件
            $upload           = new Upload();
            $upload->maxSize  = 3145728;
            $upload->exts     = array('xls', 'xlsx');
            $upload->savePath = '/wtjsupload/' . $this->fans['memberid'] . "/" . date("Ymd", time()) . "/";
            $upload->saveName = array();
            if (empty($_FILES["file"])) {
                $this->error('请上传文件！');
            }

            if (file_exists($upload->savePath . $_FILES["file"]['name'])) {
                $this->error('不能上传同名文件！');
            }

            $info = $upload->uploadOne($_FILES["file"]);
            if (!$info) // 上传错误提示错误信息
            {
                $this->error($upload->getError());
            }

            $file  = './Uploads/wtjsupload/' . $this->fans['memberid'] . "/" . date("Ymd", time()) . "/" . $info['savename'];
            $excel = $this->importExcel($file);

            if (!$excel) {
                UserLogService::write(2, '代付申请提交失败', 'EXCEL导入方式，失败原因：没有数据');
                $this->error("没有数据！");
            }
            M()->startTrans();
            //查询用户数据
            $Member = M('Member');
            $info   = $Member->where(['id' => $userid])->lock(true)->find();
            //支付密码
            $res = check_auth_error($userid, 6);
            if(!$res['status']) {
                UserLogService::write(2, '代付申请提交失败', 'EXCEL导入方式，失败原因：支付密码输入错误次数超限');
                $this->error($res['msg']);
            }
            if(md5($password) != $info['paypassword']) {
                log_auth_error($userid,6);
                M()->commit();
                UserLogService::write(2, '代付申请提交失败', 'EXCEL导入方式，失败原因：支付密码有误');
                $this->error('支付密码有误!');
            } else {
                clear_auth_error($userid,6);
            }
            //判断是否设置了节假日不能提现
            $tkHolidayList = M('Tikuanholiday')->limit(366)->getField('datetime', true);
            if ($tkHolidayList) {
                $today = date('Ymd');
                foreach ($tkHolidayList as $k => $v) {
                    if ($today == date('Ymd', $v)) {
                        UserLogService::write(2, '代付申请提交失败', 'EXCEL导入方式，失败原因：节假日无法提款');
                        $this->error('节假日暂时无法提款！');
                    }
                }
            }
            //结算方式：
            $Tikuanconfig = M('Tikuanconfig');
            $tkConfig     = $Tikuanconfig->where(['userid' => $userid, 'tkzt' => 1])->find();

            $defaultConfig = $Tikuanconfig->where(['issystem' => 1, 'tkzt' => 1])->find();

            //判断是否开启提款设置
            if (!$defaultConfig) {
                UserLogService::write(2, '代付申请提交失败', 'EXCEL导入方式，失败原因：提款已关闭');
                $this->error('提款已关闭！');
            }

            //判断是否设置个人规则
            if (!$tkConfig || $tkConfig['tkzt'] != 1 || $tkConfig['systemxz'] != 1) {
                $tkConfig = $defaultConfig;
            } else {
                //个人规则，但是提现时间规则要按照系统规则
                $tkConfig['allowstart'] = $defaultConfig['allowstart'];
                $tkConfig['allowend']   = $defaultConfig['allowend'];
            }

            //判断结算方式
            $t = $tkConfig['t1zt'] > 0 ? $tkConfig['t1zt'] : 0;
            //判断是否T+7,T+30
            if ($t == 7) {
            //T+7每周一结算
                if (date('w') != 1) {
                    UserLogService::write(2, '代付申请提交失败', 'EXCEL导入方式，失败原因：请在周一申请结算');
                    $this->error('请在周一申请结算!');
                }
            } elseif ($t == 30) {
                //月结
                if (date('j') != 1) {
                    UserLogService::write(2, '代付申请提交失败', 'EXCEL导入方式，失败原因：请在每月1日申请结算');
                    $this->error('请在每月1日申请结算!');
                }
            }
            //是否在许可的提现时间
            $hour = date('H');
            //判断提现时间是否合法
            if ($tkConfig['allowend'] != 0) {
                if ($tkConfig['allowstart'] > $hour || $tkConfig['allowend'] <= $hour) {
                    UserLogService::write(2, '代付申请提交失败', 'EXCEL导入方式，失败原因：不在结算时间内');
                    $this->error('不在结算时间内，算时间段为' . $tkConfig['allowstart'] . ':00 - ' . $tkConfig['allowend'] . ':00');
                }
            }

            //单笔最小提款金额
            $tkzxmoney = $tkConfig['tkzxmoney'];
            //单笔最大提款金额
            $tkzdmoney = $tkConfig['tkzdmoney'];

            //查询代付表跟提现表的条件
            $map['userid']     = $userid;
            $map['sqdatetime'] = ['between', [date('Y-m-d') . ' 00:00:00', date('Y-m-d') . ' 23:59:59']];

            //统计提现表的数据
//            $Tklist = M('Tklist');
            $tkNum  = 0;
            $tkSum  = 0;

            //统计代付表的数据
            $Wttklist = D('Wttklist');
            $wttkNum  = $Wttklist->getCount($map);
            $wttkSum  = $Wttklist->getSum('tkmoney',$map);
//            $Wttklist = M('Wttklist');
//            $wttkNum  = $Wttklist->where($map)->count();
//            $wttkSum  = $Wttklist->where($map)->sum('tkmoney');

            //判断是否超过当天次数
            $count    = count($excel);
            $dayzdnum = $tkNum + $wttkNum + $count;
            if ($dayzdnum >= $tkConfig['dayzdnum']) {
                $errorTxt = "超出当日提款次数！";
            }

            //判断提款额度
            $dayzdmoney = bcadd($wttkSum, $tkSum, 4);
            if ($dayzdmoney >= $tkConfig['dayzdmoney']) {
                $errorTxt = "超出当日提款额度！";
            }

            $balance    = $info['balance'];
            $tkmoneysum = 0;
            $cardsum    = [];
            foreach ($excel as $k => $v) {
                $v['tkmoney'] = trim($v['tkmoney']);
                if(!is_numeric($v['tkmoney'])) {
                    $errorTxt = "金额错误！";
                    break;
                }
                if (!isset($errorTxt)) {
                    $tkmoneysum += $v['tkmoney'];
                    //个人信息
                    if ($balance < $v['tkmoney']) {
                        $errorTxt = '金额错误，可用余额不足!';
                        break;
                    }

                    if ($v['tkmoney'] < $tkzxmoney || $v['tkmoney'] > $tkzdmoney) {
                        $errorTxt = '提款金额不符合提款额度要求!';
                        break;
                    }

                    $dayzdmoney = bcadd($v['tkmoney'], $dayzdmoney, 4);
                    if ($dayzdmoney >= $tkConfig['dayzdmoney']) {
                        $errorTxt = "超出当日提款额度！";
                        break;
                    }
                    if (bcadd($tkmoneysum, $dayzdmoney, 4) >= $tkConfig['dayzdmoney']) {
                        $errorTxt = "提现额度不足！您今日剩余提现额度：" . ($tkConfig['dayzdmoney'] - $dayzdmoney) . "元";
                        break;
                    }
                    if (!isset($cardsum[$v['banknumber']])) {
                        $cardsum[$v['banknumber']] = 0; //本次银行卡提现总额
                    }
                    $cardsum[$v['banknumber']] += $v['tkmoney'];
                    //单人单卡最高提现额度检查
                    if ($tkConfig['daycardzdmoney'] > 0) {
                        $map['banknumber'] = $v["banknumber"];
                        $tkCardSum         = 0;
                        $wttkCardSum  = $Wttklist->getSum('tkmoney',$map);
//                        $wttkCardSum       = $Wttklist->where($map)->sum('tkmoney');
                        $todayCardSum      = bcadd($tkCardSum, $wttkCardSum, 4);
                        if ($todayCardSum >= $tkConfig['daycardzdmoney']) {
                            $errorTxt = "该银行卡今日提现已超额！";
                            break;
                        }
                        if (($todayCardSum + $cardsum[$v['banknumber']]) > $tkConfig['daycardzdmoney']) {
                            $errorTxt = "尾号" . substr($v["banknumber"], -4) . "的银行卡提现额度不足！该银行卡今日剩余提现额度：" . ($tkConfig['daycardzdmoney'] - $todayCardSum) . "元";
                            break;
                        }
                    }
                    //计算手续费
                    $sxfmoney = $tkConfig['tktype'] ? $tkConfig['sxffixed'] : bcdiv(bcmul($v['tkmoney'], $tkConfig['sxfrate'], 4), 100, 4);
                    if($tkConfig['tk_charge_type']) {
                        //实际提现的金额
                        $money = $v['tkmoney'];
                    } else {
                        //实际提现的金额
                        $money = bcsub($v['tkmoney'], $sxfmoney, 4);
                    }

                    //获取订单号
                    $orderid = $this->getOrderId();
                    $out_trade_no = $v['out_trade_no']?trim($v['out_trade_no']):'';

                    //提现时间
                    $time = date("Y-m-d H:i:s");

                    //提现记录
                    $wttkData[] = [
                        'orderid'      => $orderid,
                        "bankname"     => trim($v["bankname"]),
                        "bankzhiname"  => trim($v["bankzhiname"]),
                        "banknumber"   => trim($v["banknumber"]),
                        "bankfullname" => trim($v['bankfullname']),
                        "sheng"        => trim($v["sheng"]),
                        "shi"          => trim($v["shi"]),
                        "userid"       => $userid,
                        "sqdatetime"   => $time,
                        "status"       => 0,
                        "t"            => $t,
                        'tkmoney'      => $v['tkmoney'],
                        'sxfmoney'     => $sxfmoney,
                        "money"        => $money,
                        "additional"   => trim($v['additional']),
                        "df_charge_type" => $tkConfig['tk_charge_type']

                    ];

                    $tkmoney = abs(floatval($v['tkmoney']));
                    $ymoney  = $balance;
                    $balance = bcsub($balance, $tkmoney, 4);

                    $mcData[] = [
                        "userid"     => $userid,
                        'ymoney'     => $ymoney,
                        "money"      => $v['tkmoney'],
                        'gmoney'     => $balance,
                        "datetime"   => $time,
                        "transid"    => $orderid,
                        "orderid"    => $out_trade_no,
                        "lx"         => 10,
                        'contentstr' => date("Y-m-d H:i:s") . '委托提现操作',
                    ];
                    if($tkConfig['tk_charge_type'] && $sxfmoney>0) {
                        $balance = bcsub($balance, $sxfmoney, 4);
                        if($balance<0) {
                            $errorTxt = '余额不足以扣除手续费！';
                            break;
                        }
                        $chargeData[] = [
                            "userid"     => $userid,
                            'ymoney'     => $ymoney-$tkmoney,
                            "money"      => $sxfmoney,
                            'gmoney'     => $balance,
                            "datetime"   => $time,
                            "transid"    => $orderid,
                            "orderid"    => $out_trade_no,
                            "lx"         => 14,
                            'contentstr' => date("Y-m-d H:i:s") . '委托提现扣除手续费',
                        ];
                    }
                } else {
                    break;
                }
            }

            if (!isset($errorTxt)) {
                $res = $Member->where(['id' => $userid])->save(['balance' => $balance]);
                if ($res) {
                    $result1 = M('Moneychange')->addAll($mcData);
                    //获取数据表名称
                    $table = $Wttklist->getRealTableName($time);
                    $result2 = $Wttklist->table($table)->add($wttkData);

//                    $result2 = $Wttklist->addAll($wttkData);
                    if($tkConfig['tk_charge_type'] && $sxfmoney>0) {
                        $result3 = M('Moneychange')->addAll($chargeData);
                    } else {
                        $result3 = true;
                    }
                    if ($result1 && $result2 && $result3) {
                        M()->commit();
                        UserLogService::write(2, '代付申请提交成功', 'EXCEL导入方式');
                        $this->success('委托结算提交成功！');
                        exit;
                    }
                }
                M()->rollback();
                UserLogService::write(2, '代付申请提交失败', 'EXCEL导入方式');
                $this->error('委托结算提交失败！');
            } else {
                UserLogService::write(2, '代付申请提交失败', 'EXCEL导入方式，失败原因：'.$errorTxt);
                $this->error($errorTxt);
            }
        }
    }

    /**
     * 导入EXCEL
     */
    public function importExcel($file)
    {
        header("Content-type: text/html; charset=utf-8");
        vendor("PHPExcel.PHPExcel");
        $objReader = \PHPExcel_IOFactory::createReader('Excel5');
        $objReader->setReadDataOnly(true);
        $objPHPExcel   = $objReader->load($file, $encode = 'utf-8');
        $sheet         = $objPHPExcel->getSheet(0);
        $highestRow    = $sheet->getHighestRow(); // 取得总行数
        $highestColumn = $sheet->getHighestColumn(); // 取得总列数
        for ($i = 2; $i <= $highestRow; $i++) {
            $data[$i]['bankname']     = $objPHPExcel->getActiveSheet()->getCell("A" . $i)->getValue();
            $data[$i]['bankzhiname']  = $objPHPExcel->getActiveSheet()->getCell("B" . $i)->getValue();
            $data[$i]['bankfullname'] = $objPHPExcel->getActiveSheet()->getCell("C" . $i)->getValue();
            $data[$i]['banknumber']   = $objPHPExcel->getActiveSheet()->getCell("D" . $i)->getValue();
            $data[$i]['sheng']        = $objPHPExcel->getActiveSheet()->getCell("E" . $i)->getValue();
            $data[$i]['shi']          = $objPHPExcel->getActiveSheet()->getCell("F" . $i)->getValue();
            $data[$i]['tkmoney']      = $objPHPExcel->getActiveSheet()->getCell("G" . $i)->getValue();

            /**
             *User:chen
             */
            //获取模板的额外参数
            $additional = [];
            $k          = 7;
            $num        = ord($highestColumn) - 65;

            while ($k <= $num) {

                $letter = chr(65 + $k);

                $res = $objPHPExcel->getActiveSheet()->getCell($letter . $i)->getValue();
                if ($res) {
                    $additional[] = $res;
                } else {
                    break;
                }

                $k++;
            }

            $data[$i]['additional'] = json_encode($additional, JSON_UNESCAPED_UNICODE);
        }
        return $data;
    }

    /**
     * 获得订单号
     *
     * @return string
     */
    public function getOrderId()
    {
        $year_code = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');
        $i = intval(date('Y')) - 2010;

        return $year_code[$i] . date('YmdHis') . substr(time(), -5) . str_pad(mt_rand(1, 99), 2, '0', STR_PAD_LEFT) . rand(1000,9999);
    }


    /**
     *  代付申请
     */
    public function dfapply()
    {
        UserLogService::write(1, '访问代付申请页面', '表单提交方式');
        $verifysms = 0;
        //查询是否开启短信验证
        $sms_is_open = smsStatus();
        if ($sms_is_open) {
            $verifysms = 1;
            $this->assign('sendUrl', U('User/Withdrawal/entrustedSend'));
        }
        $verifyGoogle = 0;
        if($this->fans['google_secret_key'] && $this->fans['google_auth']) {
            $verifyGoogle = 1;
        }
        $this->assign('sms_is_open', $sms_is_open);
        $this->assign('verifysms', $verifysms);
        $this->assign('verifyGoogle', $verifyGoogle);
        $this->assign('auth_type', $verifyGoogle ? 1 : 0);
        //结算方式：
        $tkconfig = M('Tikuanconfig')->where(['userid' => $this->fans['uid']])->find();
        if (!$tkconfig  || $tkconfig['systemxz'] != 1 || $tkconfig['tkzt'] != 1) {
            $tkconfig = M('Tikuanconfig')->where(['issystem' => 1])->find();
        }
        //可用余额
        $info = M('Member')->where(['id' => $this->fans['uid']])->find();
        //银行卡
        $bankcards = M('Bankcard')->where(['userid' => $this->fans['uid']])->order('id desc')->select();
        //当前可用代付渠道
        $channel_ids = M('pay_for_another')->where(['status' => 1])->getField('id', true);
        //获取渠道扩展字段
        $extend_fields = [];
        if ($channel_ids) {
            $fields = M('pay_channel_extend_fields')->where(['channel_id' => ['in', $channel_ids]])->select();
            foreach ($fields as $k => $v) {
                if (!isset($extend_fields[$v['name']])) {
                    $extend_fields[$v['name']] = $v['alias'];
                }
            }
        }
        $this->assign('tkconfig', $tkconfig);
        $this->assign('bankcards', $bankcards);
        $this->assign('extend_fields', $extend_fields);
        $this->assign('info', $info);
        $this->display();
    }

    public function dfsave()
    {
        if (IS_POST) {
            $userid   = session('user_auth.uid');
            //查询是否开启短信验证
            $sms_is_open = smsStatus();
            $verifysms = 0;
            if ($sms_is_open) {
                $verifysms = 1;
            }
            //查询是否开通谷歌验证
            $verifyGoogle = 0;
            if($this->fans['google_secret_key'] && $this->fans['google_auth']) {
                $verifyGoogle = 1;
            }
            $auth_type = I('post.auth_type', 0, 'intval');
            if($verifyGoogle && $verifysms) {
                if(!in_array($auth_type,[0,1])) {
                    UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：参数错误');
                    $this->error("参数错误！");
                }
            } elseif($verifyGoogle && !$verifysms) {
                if($auth_type != 1) {
                    UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：参数错误');
                    $this->error("参数错误！");
                }
            } elseif(!$verifyGoogle && $verifysms) {
                if($auth_type != 0) {
                    UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：参数错误');
                    $this->error("参数错误！");
                }
            }
            if ($verifyGoogle && $auth_type == 1) {//谷歌安全码验证
                $res = check_auth_error($userid, 4);
                if(!$res['status']) {
                    UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：谷歌安全码输入错误次数超限');
                    $this->error($res['msg']);
                }
                $google_code   = I('post.google_code', '', 'string,strip_tags,htmlspecialchars');
                if(!$google_code) {
                    UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：谷歌安全码不能为空');
                    $this->error("谷歌安全码不能为空！");
                } else {
                    $ga = new \Org\Util\GoogleAuthenticator();
                    if(false === $ga->verifyCode($this->fans['google_secret_key'], $google_code, C('google_discrepancy'))) {
                        log_auth_error($userid,4);
                        UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：谷歌安全码错误');
                        $this->error("谷歌安全码错误！");
                    } else {
                        clear_auth_error($userid,4);
                    }
                }
            } elseif($verifysms && $auth_type == 0){//短信验证码
                $res = check_auth_error($userid, 2);
                if(!$res['status']) {
                    UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：短信验证码输入错误次数超限');
                    $this->error($res['msg']);
                }
                $code   = I('post.code', '', 'string,strip_tags,htmlspecialchars');
                if(!$code) {
                    UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：短信验证码不能为空');
                    $this->error("短信验证码不能为空！");
                } else {
                    if (session('send.entrusted') != $code || !$this->checkSessionTime('entrusted', $code)) {
                        log_auth_error($userid,2);
                        UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：短信验证码错误');
                        $this->error('短信验证码错误');
                    } else {
                        clear_auth_error($userid,2);
                        session('send', null);
                    }
                }
            }
            $password = I('post.password', '', 'string,strip_tags,htmlspecialchars');

            if (!$password) {
                UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：支付密码不能为空');
                $this->error('支付密码不能为空！');
            }

            $data = I('post.item');
            if (empty($data)) {
                UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：代付申请数据不能为空');
                $this->error('代付申请数据不能为空！');
            }
            M()->startTrans();
            //查询用户数据
            $Member = M('Member');
            $info   = $Member->where(['id' => $userid])->lock(true)->find();
            //支付密码
            $res = check_auth_error($userid, 6);
            if(!$res['status']) {
                UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：支付密码输入错误次数超限');
                $this->error($res['msg']);
            }
            if(md5($password) != $info['paypassword']) {
                log_auth_error($userid,6);
                M()->commit();
                UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：支付密码错误');
                $this->error('支付密码有误!');
            } else {
                clear_auth_error($userid,6);
            }
            //判断是否设置了节假日不能提现
            $tkHolidayList = M('Tikuanholiday')->limit(366)->getField('datetime', true);
            if ($tkHolidayList) {
                $today = date('Ymd');
                foreach ($tkHolidayList as $k => $v) {
                    if ($today == date('Ymd', $v)) {
                        UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：节假日无法提款');
                        $this->error('节假日暂时无法提款！');
                    }
                }
            }
            //结算方式：
            $Tikuanconfig = M('Tikuanconfig');
            $tkConfig     = $Tikuanconfig->where(['userid' => $userid, 'tkzt' => 1])->find();

            $defaultConfig = $Tikuanconfig->where(['issystem' => 1, 'tkzt' => 1])->find();

            //判断是否开启提款设置
            if (!$defaultConfig) {
                UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：提款已关闭');
                $this->error('提款已关闭！');
            }

            //判断是否设置个人规则
            if (!$tkConfig || $tkConfig['tkzt'] != 1 || $tkConfig['systemxz'] != 1) {
                $tkConfig = $defaultConfig;
            } else {
                //个人规则，但是提现时间规则要按照系统规则
                $tkConfig['allowstart'] = $defaultConfig['allowstart'];
                $tkConfig['allowend']   = $defaultConfig['allowend'];
            }

            //判断结算方式
            $t = $tkConfig['t1zt'] > 0 ? $tkConfig['t1zt'] : 0;
            //判断是否T+7,T+30
            if ($t == 7) {
            //T+7每周一结算
                if (date('w') != 1) {
                    UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：请在周一申请结算');
                    $this->error('请在周一申请结算!');
                }
            } elseif ($t == 30) {
                //月结
                if (date('j') != 1) {
                    UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：请在每月1日申请结算');
                    $this->error('请在每月1日申请结算!');
                }
            }

            //是否在许可的提现时间
            $hour = date('H');
            //判断提现时间是否合法
            if ($tkConfig['allowend'] != 0) {
                if ($tkConfig['allowstart'] > $hour || $tkConfig['allowend'] <= $hour) {
                    UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：不在结算时间内');
                    $this->error('不在结算时间内，结算时间段为' . $tkConfig['allowstart'] . ':00 - ' . $tkConfig['allowend'] . ':00');
                }

            }

            //单笔最小提款金额
            $tkzxmoney = $tkConfig['tkzxmoney'];
            //单笔最大提款金额
            $tkzdmoney = $tkConfig['tkzdmoney'];

            //查询代付表跟提现表的条件
            $map['userid']     = $userid;
            $map['sqdatetime'] = ['between', [date('Y-m-d') . ' 00:00:00', date('Y-m-d') . ' 23:59:59']];

            //统计提现表的数据
//            $Tklist = M('Tklist');
            $tkNum  = 0;
            $tkSum  = 0;

            //统计代付表的数据
            $Wttklist = D('Wttklist');
            $wttkNum  = $Wttklist->getCount($map);
            $wttkSum  = $Wttklist->getSum('tkmoney',$map);
//            $Wttklist = M('Wttklist');
//            $wttkNum  = $Wttklist->where($map)->count();
//            $wttkSum  = $Wttklist->where($map)->sum('tkmoney');

            //判断是否超过当天次数
            $count    = count($data);
            $dayzdnum = $tkNum + $wttkNum + $count;
            if ($dayzdnum >= $tkConfig['dayzdnum']) {
                $errorTxt = "超出当日提款次数！";
            }

            //判断提款额度
            $dayzdmoney = bcadd($wttkSum, $tkSum, 4);
            if ($dayzdmoney >= $tkConfig['dayzdmoney']) {
                $errorTxt = "超出当日提款额度！";
            }

            $balance    = $info['balance'];
            $tkmoneysum = 0;
            $cardsum    = [];
            foreach ($data as $k => $v) {
                if (!isset($errorTxt)) {
                    $v['tkmoney'] = trim($v['tkmoney']);
                    if(!is_numeric($v['tkmoney'])) {
                        $errorTxt = "金额错误！";
                        break;
                    }
                    $tkmoneysum += $v['tkmoney'];
                    $bankCard = M('bankcard')->where(['id' => $v['bank'], 'userid' => $userid])->find();
                    if (empty($bankCard)) {
                        $errorTxt = "银行卡不存在！";
                        break;
                    }
                    if (!isset($cardsum[$v['bank']])) {
                        $cardsum[$v['bank']] = 0; //本次银行卡提现总额
                    }
                    $cardsum[$v['bank']] += $v['tkmoney'];
                    //个人信息
                    if ($balance < $v['tkmoney']) {
                        $errorTxt = '金额错误，可用余额不足!';
                        break;
                    }

                    if ($v['tkmoney'] < $tkzxmoney || $v['tkmoney'] > $tkzdmoney) {
                        $errorTxt = '提款金额不符合提款额度要求!';
                        break;
                    }
                    if (bcadd($tkmoneysum, $dayzdmoney, 4) >= $tkConfig['dayzdmoney']) {
                        $errorTxt = "提现额度不足！您今日剩余提现额度：" . ($tkConfig['dayzdmoney'] - $dayzdmoney) . "元";
                        break;
                    }
                    //单人单卡最高提现额度检查
                    if ($tkConfig['daycardzdmoney'] > 0) {
                        $map['banknumber'] = $bankCard['cardnumber'];
                        $tkCardSum         = 0;
                        $wttkCardSum  = $Wttklist->getSum('tkmoney',$map);
//                        $wttkCardSum       = $Wttklist->where($map)->sum('tkmoney');
                        $todayCardSum      = bcadd($tkCardSum, $wttkCardSum, 4);
                        if ($todayCardSum >= $tkConfig['daycardzdmoney']) {
                            $errorTxt = "该银行卡今日提现已超额！";
                            break;
                        }
                        if (($todayCardSum + $cardsum[$v['bank']]) > $tkConfig['daycardzdmoney']) {
                            $errorTxt = "尾号" . substr($bankCard['cardnumber'], -4) . "的银行卡提现额度不足！该银行卡今日剩余提现额度：" . ($tkConfig['daycardzdmoney'] - $todayCardSum) . "元";
                            break;
                        }
                    }
                    //计算手续费
                    $sxfmoney = $tkConfig['tktype'] ? $tkConfig['sxffixed'] : bcdiv(bcmul($v['tkmoney'], $tkConfig['sxfrate'], 4), 100, 4);
                    if($tkConfig['tk_charge_type']) {
                        //实际提现的金额
                        $money = $v['tkmoney'];
                    } else {
                        //实际提现的金额
                        $money = bcsub($v['tkmoney'], $sxfmoney, 4);
                    }

                    //获取订单号
                    $orderid = $this->getOrderId();

                    //提现时间
                    $time = date("Y-m-d H:i:s");
                    $out_trade_no = $v['out_trade_no']?trim($v['out_trade_no']):'';

                    //扩展字段
                    $extends = '';
                    if (isset($v['extend'])) {
                        $extends = json_encode($v['extend']);
                    }
                    //提现记录
                    $wttkData[] = [
                        'orderid'      => $orderid,
                        "bankname"     => trim($bankCard["bankname"]),
                        "bankzhiname"  => trim($bankCard["subbranch"]),
                        "banknumber"   => trim($bankCard["cardnumber"]),
                        "bankfullname" => trim($bankCard['accountname']),
                        "sheng"        => trim($bankCard["province"]),
                        "shi"          => trim($bankCard["city"]),
                        "phone"        => $bankCard['phone'],
                        "idnumber"     => $bankCard['idnumber'],
                        "userid"       => $userid,
                        "sqdatetime"   => $time,
                        "status"       => 0,
                        "t"            => $t,
                        'tkmoney'      => $v['tkmoney'],
                        'sxfmoney'     => $sxfmoney,
                        "money"        => $money,
                        "additional"   => trim($v['additional']),
                        "extends"      => $extends,
                        "df_charge_type" => $tkConfig['tk_charge_type'],
                    ];

                    $tkmoney = abs(floatval($v['tkmoney']));
                    $ymoney  = $balance;
                    $balance = bcsub($balance, $tkmoney, 4);

                    $mcData[] = [
                        "userid"     => $userid,
                        'ymoney'     => $ymoney,
                        "money"      => $v['tkmoney'],
                        'gmoney'     => $balance,
                        "datetime"   => $time,
                        "transid"    => $orderid,
                        "orderid"    => $out_trade_no,
                        "lx"         => 10,
                        'contentstr' => date("Y-m-d H:i:s") . '委托提现操作',
                    ];
                    if($tkConfig['tk_charge_type'] && $sxfmoney>0) {
                        $balance = bcsub($balance, $sxfmoney, 4);
                        if($balance<0) {
                            $errorTxt ='余额不足以扣除手续费！';
                            break;
                        }
                        $chargeData[] = [
                            "userid"     => $userid,
                            'ymoney'     => $ymoney-$v['tkmoney'],
                            "money"      => $sxfmoney,
                            'gmoney'     => $balance,
                            "datetime"   => $time,
                            "transid"    => $orderid,
                            "orderid"    => $out_trade_no,
                            "lx"         => 14,
                            'contentstr' => date("Y-m-d H:i:s") . '代付结算扣除手续费',
                        ];
                    }
                } else {
                    break;
                }
            }

            if (!isset($errorTxt)) {
                $res1 = $Member->where(['id' => $userid])->save(['balance' => $balance]);
                $res2 = M('Moneychange')->addAll($mcData);
                if($tkConfig['tk_charge_type'] && $sxfmoney>0) {
                    $res3 = M('Moneychange')->addAll($chargeData);
                } else {
                    $res3 = true;
                }
                if ($res1 && $res2 && $res3) {
                    //获取数据表名称
                    $table = $Wttklist->getRealTableName($time);
                    $result = $Wttklist->table($table)->add($wttkData);
//                    $result = $Wttklist->addAll($wttkData);
                    if ($result) {
                        UserLogService::write(2, '代付申请提交成功', '表单提交方式');
                        M()->commit();
                        $this->success('委托结算提交成功！');
                    }
                }
                M()->rollback();
                UserLogService::write(2, '代付申请提交失败', '表单提交方式');
                $this->error('委托结算提交失败！');
            } else {
                UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：'.$errorTxt);
                $this->error($errorTxt);
            }

        }
    }
    
//
//    /**
//     *  保存代付申请
//     */
//    public function gCashdfsave()
//    {
//        if (IS_POST) {
//            $userid   = session('user_auth.uid');
//            //查询是否开启短信验证
//            $sms_is_open = smsStatus();
//            $verifysms = 0;
//            if ($sms_is_open) {
//                $verifysms = 1;
//            }
//            //查询是否开通谷歌验证
//            $verifyGoogle = 0;
//            if($this->fans['google_secret_key'] && $this->fans['google_auth']) {
//                $verifyGoogle = 1;
//            }
//            $auth_type = I('post.auth_type', 0, 'intval');
//            if($verifyGoogle && $verifysms) {
//                if(!in_array($auth_type,[0,1])) {
//                    UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：参数错误');
//                    $this->error("参数错误！");
//                }
//            } elseif($verifyGoogle && !$verifysms) {
//                if($auth_type != 1) {
//                    UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：参数错误');
//                    $this->error("参数错误！");
//                }
//            } elseif(!$verifyGoogle && $verifysms) {
//                if($auth_type != 0) {
//                    UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：参数错误');
//                    $this->error("参数错误！");
//                }
//            }
//            if ($verifyGoogle && $auth_type == 1) {//谷歌安全码验证
//                $res = check_auth_error($userid, 4);
//                if(!$res['status']) {
//                    UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：谷歌安全码输入错误次数超限');
//                    $this->error($res['msg']);
//                }
//                $google_code   = I('post.google_code', '', 'string,strip_tags,htmlspecialchars');
//                if(!$google_code) {
//                    UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：谷歌安全码不能为空');
//                    $this->error("谷歌安全码不能为空！");
//                } else {
//                    $ga = new \Org\Util\GoogleAuthenticator();
//                    if(false === $ga->verifyCode($this->fans['google_secret_key'], $google_code, C('google_discrepancy'))) {
//                        log_auth_error($userid,4);
//                        UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：谷歌安全码错误');
//                        $this->error("谷歌安全码错误！");
//                    } else {
//                        clear_auth_error($userid,4);
//                    }
//                }
//            } elseif($verifysms && $auth_type == 0){//短信验证码
//                $res = check_auth_error($userid, 2);
//                if(!$res['status']) {
//                    UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：短信验证码输入错误次数超限');
//                    $this->error($res['msg']);
//                }
//                $code   = I('post.code', '', 'string,strip_tags,htmlspecialchars');
//                if(!$code) {
//                    UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：短信验证码不能为空');
//                    $this->error("短信验证码不能为空！");
//                } else {
//                    if (session('send.entrusted') != $code || !$this->checkSessionTime('entrusted', $code)) {
//                        log_auth_error($userid,2);
//                        UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：短信验证码错误');
//                        $this->error('短信验证码错误');
//                    } else {
//                        clear_auth_error($userid,2);
//                        session('send', null);
//                    }
//                }
//            }
//            $password = I('post.password', '', 'string,strip_tags,htmlspecialchars');
//
//            if (!$password) {
//                UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：支付密码不能为空');
//                $this->error('支付密码不能为空！');
//            }
//
//            $data = I('post.item');
//            if (empty($data)) {
//                UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：代付申请数据不能为空');
//                $this->error('代付申请数据不能为空！');
//            }
//            M()->startTrans();
//            //查询用户数据
//            $Member = M('Member');
//            $info   = $Member->where(['id' => $userid])->lock(true)->find();
//            //支付密码
//            $res = check_auth_error($userid, 6);
//            if(!$res['status']) {
//                UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：支付密码输入错误次数超限');
//                $this->error($res['msg']);
//            }
//            if(md5($password) != $info['paypassword']) {
//                log_auth_error($userid,6);
//                M()->commit();
//                UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：支付密码错误');
//                $this->error('支付密码有误!');
//            } else {
//                clear_auth_error($userid,6);
//            }
//            //判断是否设置了节假日不能提现
//            $tkHolidayList = M('Tikuanholiday')->limit(366)->getField('datetime', true);
//            if ($tkHolidayList) {
//                $today = date('Ymd');
//                foreach ($tkHolidayList as $k => $v) {
//                    if ($today == date('Ymd', $v)) {
//                        UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：节假日无法提款');
//                        $this->error('节假日暂时无法提款！');
//                    }
//                }
//            }
//
//            //结算方式：
//            $Tikuanconfig = M('Tikuanconfig');
//            $tkConfig     = $Tikuanconfig->where(['userid' => $userid, 'tkzt' => 1])->find();
//
//            $defaultConfig = $Tikuanconfig->where(['issystem' => 1, 'tkzt' => 1])->find();
//
//            //判断是否开启提款设置
//            if (!$defaultConfig) {
//                UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：提款已关闭');
//                $this->error('提款已关闭！');
//            }
//
//            //根据银行名称来判断 哪种类型的 默认通道
//            $paytype = 1;
//            $user_channel = M('user_pay_for_another')->where(['userid' =>  $userid])->find();
//            if(!$user_channel){
//                $this->error('用户未配置代付通道！');
//            }
//            $channel = M('pay_for_another')->where(['id' => $user_channel['channel']])->find();
//            $channelConfig = $Tikuanconfig->where(['channel'=> $channel['id'] , 'userid' => 1 , 'tkzt' => 1])->find();       //通道规则
//            //判断是否设置个人规则
//            if (!$tkConfig || $tkConfig['tkzt'] != 1 || $tkConfig['systemxz'] != 1) {
//                $tkConfig = $defaultConfig;
//            }elseif (!$channelConfig || $channelConfig['tkzt'] != 1 || $channelConfig['systemxz'] != 2) {
//                $tkConfig = $channelConfig;
//                //通道规则，但是提现时间规则要按照系统规则
//                $tkConfig['allowstart'] = $defaultConfig['allowstart'];
//                $tkConfig['allowend'] = $defaultConfig['allowend'];
//            } else {
//                //个人规则，但是提现时间规则要按照系统规则
//                $tkConfig['allowstart'] = $defaultConfig['allowstart'];
//                $tkConfig['allowend'] = $defaultConfig['allowend'];
//            }
//
//            //判断结算方式
//            $t = $tkConfig['t1zt'] > 0 ? $tkConfig['t1zt'] : 0;
//            //判断是否T+7,T+30
//            if ($t == 7) {
//            //T+7每周一结算
//                if (date('w') != 1) {
//                    UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：请在周一申请结算');
//                    $this->error('请在周一申请结算!');
//                }
//            } elseif ($t == 30) {
//                //月结
//                if (date('j') != 1) {
//                    UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：请在每月1日申请结算');
//                    $this->error('请在每月1日申请结算!');
//                }
//            }
//
//            //是否在许可的提现时间
//            $hour = date('H');
//            //判断提现时间是否合法
//            if ($tkConfig['allowend'] != 0) {
//                if ($tkConfig['allowstart'] > $hour || $tkConfig['allowend'] <= $hour) {
//                    UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：不在结算时间内');
//                    $this->error('不在结算时间内，结算时间段为' . $tkConfig['allowstart'] . ':00 - ' . $tkConfig['allowend'] . ':00');
//                }
//
//            }
//
//            //单笔最小提款金额
//            $tkzxmoney = $tkConfig['tkzxmoney'];
//            //单笔最大提款金额
//            $tkzdmoney = $tkConfig['tkzdmoney'];
//
//            //查询代付表跟提现表的条件
//            $map['userid']     = $userid;
//            $map['sqdatetime'] = ['between', [date('Y-m-d') . ' 00:00:00', date('Y-m-d') . ' 23:59:59']];
//
//            //统计提现表的数据
////            $Tklist = M('Tklist');
//            $tkNum  = 0;
//            $tkSum  = 0;
//
//            //统计代付表的数据
//            $Wttklist = M('Wttklist');
//            $wttkNum  = $Wttklist->where($map)->count();
//            $wttkSum  = $Wttklist->where($map)->sum('tkmoney');
//
//            //判断是否超过当天次数
//            $count    = count($data);
//            $dayzdnum = $tkNum + $wttkNum + $count;
//            if ($dayzdnum >= $tkConfig['dayzdnum']) {
//                $errorTxt = "超出当日提款次数！";
//            }
//
//            //判断提款额度
//            $dayzdmoney = bcadd($wttkSum, $tkSum, 4);
//            if ($dayzdmoney >= $tkConfig['dayzdmoney']) {
//                $errorTxt = "超出当日提款额度！";
//            }
//
//            $balance    = $info['balance'];
//            $tkmoneysum = 0;
//            $cardsum    = [];
//            foreach ($data as $k => $v) {
//                if (!isset($errorTxt)) {
//                    $v['tkmoney'] = trim($v['tkmoney']);
//                    if(!is_numeric($v['tkmoney'])) {
//                        $errorTxt = "金额错误！";
//                        break;
//                    }
//                    $tkmoneysum += $v['tkmoney'];
//                    if (!isset($cardsum[$v['bank']])) {
//                        $cardsum[$v['bank']] = 0; //本次银行卡提现总额
//                    }
//                    $cardsum[$v['bank']] += $v['tkmoney'];
//                    //个人信息
//                    if ($balance < $v['tkmoney']) {
//                        $errorTxt = '金额错误，可用余额不足!';
//                        break;
//                    }
//
//                    if (!$v['bankname']) {
//                        $errorTxt = '银行名称不能为空';
//                        break;
//                    }
//
//                    if (!$v['subbranch']) {
//                        $errorTxt = 'IFSC不能为空';
//                        break;
//                    }
//
//                    if ($v['tkmoney'] < $tkzxmoney || $v['tkmoney'] > $tkzdmoney) {
//                        $errorTxt = '提款金额不符合提款额度要求!';
//                        break;
//                    }
//                    if (bcadd($tkmoneysum, $dayzdmoney, 4) >= $tkConfig['dayzdmoney']) {
//                        $errorTxt = "提现额度不足！您今日剩余提现额度：" . ($tkConfig['dayzdmoney'] - $dayzdmoney) . "元";
//                        break;
//                    }
//                    //单人单卡最高提现额度检查
//                    if ($tkConfig['daycardzdmoney'] > 0) {
//                        $map['banknumber'] = $v['cardnumber'];
//                        $tkCardSum         = $Tklist->where($map)->sum('tkmoney');
//                        $wttkCardSum       = $Wttklist->where($map)->sum('tkmoney');
//                        $todayCardSum      = bcadd($tkCardSum, $wttkCardSum, 4);
//                        if ($todayCardSum >= $tkConfig['daycardzdmoney']) {
//                            $errorTxt = "该银行卡今日提现已超额！";
//                            break;
//                        }
//                        if (($todayCardSum + $cardsum[$v['bank']]) > $tkConfig['daycardzdmoney']) {
//                            $errorTxt = "尾号" . substr($v['cardnumber'], -4) . "的支付宝提现额度不足！该支付宝今日剩余提现额度：" . ($tkConfig['daycardzdmoney'] - $todayCardSum) . "元";
//                            break;
//                        }
//                    }
//                    //计算手续费
//                    // $sxfmoney = $tkConfig['tktype'] ? $tkConfig['sxffixed'] : bcdiv(bcmul($v['tkmoney'], $tkConfig['sxfrate'], 4), 100, 4);
//                    if ($tkConfig['tktype'] == 1) { //按比例计算
//                        $sxfmoney = $tkConfig['sxffixed'];
//                    } elseif ($tkConfig['tktype'] == 2) {   //按单笔加比例计算
//                        $sxfmoney = $tkConfig['sxffixed'] + bcdiv(bcmul($v['tkmoney'], $tkConfig['sxfrate'], 4), 100, 4);
//                    } else {    //按单笔计算
//                        $sxfmoney = bcdiv(bcmul($v['tkmoney'], $tkConfig['sxfrate'], 4), 100, 4);
//                    }
//
//                    if($tkConfig['tk_charge_type']) {
//                        //实际提现的金额
//                        $money = $v['tkmoney'];
//                    } else {
//                        //实际提现的金额
//                        $money = bcsub($v['tkmoney'], $sxfmoney, 4);
//                    }
//
//                    //获取订单号
//                    $orderid = $this->getOrderId();
//                    $out_trade_no = $v['out_trade_no']?trim($v['out_trade_no']):'';
//
//                    //提现时间
//                    $time = date("Y-m-d H:i:s");
//
//                    //扩展字段
//                    $extends = '';
//                    if (isset($v['extend'])) {
//                        $extends = json_encode($v['extend']);
//                    }
//                    //提现记录
//                    $wttkData[] = [
//                        'orderid'      => $orderid,
//                        "bankname"     => $v['bankname'],
//                        "bankzhiname"  => $v['subbranch'],
//                        "banknumber"   => trim($v["cardnumber"]),
//                        "bankfullname" => trim($v['accountname']),
//                        "sheng"        => '',
//                        "shi"          => '',
//                        "phone"        => $bankCard['phone'],
//                        "idnumber"     => $bankCard['idnumber'],
//                        "type"         => '',
//                        "userid"       => $userid,
//                        "sqdatetime"   => $time,
//                        "status"       => 0,
//                        "t"            => $t,
//                        'tkmoney'      => $v['tkmoney'],
//                        'sxfmoney'     => $sxfmoney,
//                        "money"        => $money,
//                        "additional"   => trim($v['additional']),
//                        "extends"      => $extends,
//                        "df_charge_type" => $tkConfig['tk_charge_type'],
//                    ];
//
//                    $tkmoney = abs(floatval($v['tkmoney']));
//                    $ymoney  = $balance;
//                    $balance = bcsub($balance, $tkmoney, 4);
//
//                    $mcData[] = [
//                        "userid"     => $userid,
//                        'ymoney'     => $ymoney,
//                        "money"      => $v['tkmoney'],
//                        'gmoney'     => $balance,
//                        "datetime"   => $time,
//                        "transid"    => $orderid,
//                        "orderid"    => $out_trade_no,
//                        "lx"         => 10,
//                        'contentstr' => date("Y-m-d H:i:s") . '委托提现操作',
//                    ];
//                    if($tkConfig['tk_charge_type'] && $sxfmoney>0) {
//                        $balance = bcsub($balance, $sxfmoney, 4);
//                        if($balance<0) {
//                            $errorTxt ='余额不足以扣除手续费！';
//                            break;
//                        }
//                        $chargeData[] = [
//                            "userid"     => $userid,
//                            'ymoney'     => $ymoney-$v['tkmoney'],
//                            "money"      => $sxfmoney,
//                            'gmoney'     => $balance,
//                            "datetime"   => $time,
//                            "transid"    => $orderid,
//                            "orderid"    => $out_trade_no,
//                            "lx"         => 14,
//                            'contentstr' => date("Y-m-d H:i:s") . '代付结算扣除手续费',
//                        ];
//                    }
//                } else {
//                    break;
//                }
//            }
//
//            if (!isset($errorTxt)) {
//                $res1 = $Member->where(['id' => $userid])->save(['balance' => $balance]);
//                $res2 = M('Moneychange')->addAll($mcData);
//                if($tkConfig['tk_charge_type'] && $sxfmoney>0) {
//                    $res3 = M('Moneychange')->addAll($chargeData);
//                } else {
//                    $res3 = true;
//                }
//                if ($res1 && $res2 && $res3) {
//                    $result = $Wttklist->addAll($wttkData);
//                    if ($result) {
//                        UserLogService::write(2, '代付申请提交成功', '表单提交方式');
//                        M()->commit();
//                        $this->success('委托结算提交成功！');
//                    }
//                }
//                M()->rollback();
//                UserLogService::write(2, '代付申请提交失败', '表单提交方式');
//                $this->error('委托结算提交失败！');
//            } else {
//                UserLogService::write(2, '代付申请提交失败', '表单提交方式，错误原因：'.$errorTxt);
//                $this->error($errorTxt);
//            }
//
//        }
//    }
//

    /**
     *  代付申请
     */
    public function dfapplyU()
    {
        $currency = I('request.currency');
        UserLogService::write(1, '访问代付申请页面' . $currency, '表单提交方式');
        $verifysms = 0;
        //查询是否开启短信验证
        $sms_is_open = smsStatus();
        if ($sms_is_open) {
            $verifysms = 1;
            $this->assign('sendUrl', U('User/Withdrawal/entrustedSend'));
        }
        $verifyGoogle = 0;
        if($this->fans['google_secret_key'] && $this->fans['google_auth']) {
            $verifyGoogle = 1;
        }
        $this->assign('sms_is_open', $sms_is_open);
        $this->assign('verifysms', $verifysms);
        $this->assign('verifyGoogle', $verifyGoogle);
        $this->assign('auth_type', $verifyGoogle ? 1 : 0);

        //可用余额
        $info = M('Member')->where(['id' => $this->fans['uid']])->find();

        $this->assign('info', $info);
        $this->assign('currency', $currency);
        $this->display();
    }

        /**
     *  保存U代付申请
     */
    public function dfsaveUphp()
    {
        if (IS_POST) {
            $userid   = session('user_auth.uid');
            //查询是否开启短信验证
            $sms_is_open = smsStatus();
            $verifysms = 0;
            if ($sms_is_open) {
                $verifysms = 1;
            }
            //查询是否开通谷歌验证
            $verifyGoogle = 0;
            if($this->fans['google_secret_key'] && $this->fans['google_auth']) {
                $verifyGoogle = 1;
            }
            $auth_type = I('post.auth_type', 0, 'intval');
            if($verifyGoogle && $verifysms) {
                if(!in_array($auth_type,[0,1])) {
                    UserLogService::write(2, 'U代付申请失败', '表单提交方式，错误原因：参数错误');
                    $this->error("参数错误！");
                }
            } elseif($verifyGoogle && !$verifysms) {
                if($auth_type != 1) {
                    UserLogService::write(2, 'U代付申请失败', '表单提交方式，错误原因：参数错误');
                    $this->error("参数错误！");
                }
            } elseif(!$verifyGoogle && $verifysms) {
                if($auth_type != 0) {
                    UserLogService::write(2, 'U代付申请交失败', '表单提交方式，错误原因：参数错误');
                    $this->error("参数错误！");
                }
            }
            if ($verifyGoogle && $auth_type == 1) {//谷歌安全码验证
                $res = check_auth_error($userid, 4);
                if(!$res['status']) {
                    UserLogService::write(2, 'U代付申请提交失败', '表单提交方式，错误原因：谷歌安全码输入错误次数超限');
                    $this->error($res['msg']);
                }
                $google_code   = I('post.google_code', '', 'string,strip_tags,htmlspecialchars');
                if(!$google_code) {
                    UserLogService::write(2, 'U代付申请提交失败', '表单提交方式，错误原因：谷歌安全码不能为空');
                    $this->error("谷歌安全码不能为空！");
                } else {
                    $ga = new \Org\Util\GoogleAuthenticator();
                    if(false === $ga->verifyCode($this->fans['google_secret_key'], $google_code, C('google_discrepancy'))) {
                        log_auth_error($userid,4);
                        UserLogService::write(2, 'U代付申请提交失败', '表单提交方式，错误原因：谷歌安全码错误');
                        $this->error("谷歌安全码错误！");
                    } else {
                        clear_auth_error($userid,4);
                    }
                }
            } elseif($verifysms && $auth_type == 0){//短信验证码
                $res = check_auth_error($userid, 2);
                if(!$res['status']) {
                    UserLogService::write(2, 'U代付申请提交失败', '表单提交方式，错误原因：短信验证码输入错误次数超限');
                    $this->error($res['msg']);
                }
                $code   = I('post.code', '', 'string,strip_tags,htmlspecialchars');
                if(!$code) {
                    UserLogService::write(2, 'U代付申请提交失败', '表单提交方式，错误原因：短信验证码不能为空');
                    $this->error("短信验证码不能为空！");
                } else {
                    if (session('send.entrusted') != $code || !$this->checkSessionTime('entrusted', $code)) {
                        log_auth_error($userid,2);
                        UserLogService::write(2, 'U代付申请请提交失败', '表单提交方式，错误原因：短信验证码错误');
                        $this->error('短信验证码错误');
                    } else {
                        clear_auth_error($userid,2);
                        session('send', null);
                    }
                }
            }
            $password = I('post.password', '', 'string,strip_tags,htmlspecialchars');

            if (!$password) {
                UserLogService::write(2, 'U代付申请提交失败', '表单提交方式，错误原因：支付密码不能为空');
                $this->error('支付密码不能为空！');
            }

            $data = I('post.item');
            if (empty($data)) {
                UserLogService::write(2, 'U代付申请提交失败', '表单提交方式，错误原因：代付申请数据不能为空');
                $this->error('代付申请数据不能为空！');
            }
            M()->startTrans();
            //查询用户数据
            $Member = M('Member');
            $info   = $Member->where(['id' => $userid])->lock(true)->find();
            //支付密码
            $res = check_auth_error($userid, 6);
            if(!$res['status']) {
                UserLogService::write(2, 'U代付申请提交失败', '表单提交方式，错误原因：支付密码输入错误次数超限');
                $this->error($res['msg']);
            }
            if(md5($password) != $info['paypassword']) {
                log_auth_error($userid,6);
                M()->commit();
                UserLogService::write(2, 'U代付申请提交失败', '表单提交方式，错误原因：支付密码错误');
                $this->error('支付密码有误!');
            } else {
                clear_auth_error($userid,6);
            }

            $balance    = $info['balance_php'];
            $tkmoneysum = 0;
            foreach ($data as $k => $v) {
                if (!isset($errorTxt)) {
                    $v['tkmoney'] = trim($v['tkmoney']);
                    if(!is_numeric($v['tkmoney'])) {
                        $errorTxt = "金额错误！";
                        break;
                    }
                    //个人信息
                    if ($balance < $v['tkmoney']) {
                        $errorTxt = '金额错误，可用余额不足!';
                        break;
                    }
                    
                    $tkmoneysum += $v['tkmoney'];
                    //实际提现的金额
                    $money = $v['tkmoney']/$v['sxfmoney'];
                    //获取订单号
                    $orderid = $this->getOrderId();
                    $out_trade_no = $v['out_trade_no']?trim($v['out_trade_no']):'';

                    //提现时间
                    $time = date("Y-m-d H:i:s");

                    //扩展字段
                    $extends = '';
                    if (isset($v['extend'])) {
                        $extends = json_encode($v['extend']);
                    }
                    //提现记录
                    $wttkData[] = [
                        'orderid'      => $orderid,
                        "bankname"     => 'USDT',
                        "bankfullname" => 'USDT',
                        "memo" => 'USDT',
                        "banknumber"   => trim($v["cardnumber"]),
                        "bankcode" =>'910',
                        'paytype' => 1,
                        "userid"       => $userid,
                        "sqdatetime"   => $time,
                        "status"       => 1,
                        'tkmoney'      => $v['tkmoney'],
                        'sxfmoney'     => $v['sxfmoney'],
                        "money"        => sprintf("%.4f", $money),
                        "df_type"         => 2,
                    ];
                    $tkmoney = abs(floatval($v['tkmoney']));
                    $ymoney  = $balance;
                    $balance = bcsub($balance, $tkmoney, 4);

                    $mcData[] = [
                        "userid"     => $userid,
                        'ymoney'     => $ymoney,
                        "money"      => $v['tkmoney'],
                        'gmoney'     => $balance,
                        "datetime"   => $time,
                        "transid"    => $orderid,
                        "orderid"    => $out_trade_no,
                        'paytype' => 1,
                        "lx"         => 10,
                        'contentstr' => date("Y-m-d H:i:s") . '委托U提现操作',
                    ];
                } else {
                    break;
                }
            }

            if (!isset($errorTxt)) {
                $res1 = $Member->where(['id' => $userid])->save(['balance_php' => $balance]);
                $MoneychangeModel = D('Moneychange');
                $Moneychangetable = $MoneychangeModel->getRealTableName($time);
                $res2 = $MoneychangeModel->table($Moneychangetable)->addAll($mcData);

                if ($res1 && $res2) {
                    $Wttklistmodel = D('Wttklist');
                    $table = $Wttklistmodel->getRealTableName($time);
                    $result = $Wttklistmodel->table($table)->addAll($wttkData);
                    if ($result) {
                        UserLogService::write(2, 'U代付申请提交成功', '表单提交方式');
                        M()->commit();
                        
                        $message = '';
                        $message .= "\r\n \*【账户USDT下发提交】\*\r\n\r\n";
                        $message .= "\*商户名称\*：" . $info['username'] . "\r\n";
                        $message .= "\*下发金额\*：`" . sprintf("%.4f", $v['tkmoney']) . "`\r\n";
                        $message .= "\*下发汇率\*：`" . sprintf("%.4f", $v['sxfmoney']) . "`\r\n";
                        $message .= "\*USDT数额\*：`" . sprintf("%.4f", $money) . "`\r\n";
                        $message .= "\*钱包地址\*：`" . trim($v["cardnumber"]) . "`\r\n";
                        $message .= "\*【请商户提交人员确认订单信息】\*\r\n";
                        $result1 = R('Telegram/Api/send', [$info['telegram_id'], $message, '', 'Markdown']);
                        
                        $this->success('委托结算提交成功！');
                    }
                }
                M()->rollback();
                UserLogService::write(2, 'U代付申请提交失败', '表单提交方式');
                $this->error('委托结算提交失败！');
            } else {
                UserLogService::write(2, 'U代付申请提交失败', '表单提交方式，错误原因：'.$errorTxt);
                $this->error($errorTxt);
            }
        }
    }

       /**
     *  保存U代付申请(国2)
     */
    public function dfsaveUinr()
    {
        if (IS_POST) {
            $userid   = session('user_auth.uid');
            //查询是否开启短信验证
            $sms_is_open = smsStatus();
            $verifysms = 0;
            if ($sms_is_open) {
                $verifysms = 1;
            }
            //查询是否开通谷歌验证
            $verifyGoogle = 0;
            if($this->fans['google_secret_key'] && $this->fans['google_auth']) {
                $verifyGoogle = 1;
            }
            $auth_type = I('post.auth_type', 0, 'intval');
            if($verifyGoogle && $verifysms) {
                if(!in_array($auth_type,[0,1])) {
                    UserLogService::write(2, 'U代付申请(国2)失败', '表单提交方式，错误原因：参数错误');
                    $this->error("参数错误！");
                }
            } elseif($verifyGoogle && !$verifysms) {
                if($auth_type != 1) {
                    UserLogService::write(2, 'U代付申请(国2)失败', '表单提交方式，错误原因：参数错误');
                    $this->error("参数错误！");
                }
            } elseif(!$verifyGoogle && $verifysms) {
                if($auth_type != 0) {
                    UserLogService::write(2, 'U代付申请(国2)交失败', '表单提交方式，错误原因：参数错误');
                    $this->error("参数错误！");
                }
            }
            if ($verifyGoogle && $auth_type == 1) {//谷歌安全码验证
                $res = check_auth_error($userid, 4);
                if(!$res['status']) {
                    UserLogService::write(2, 'U代付申请(国2)提交失败', '表单提交方式，错误原因：谷歌安全码输入错误次数超限');
                    $this->error($res['msg']);
                }
                $google_code   = I('post.google_code', '', 'string,strip_tags,htmlspecialchars');
                if(!$google_code) {
                    UserLogService::write(2, 'U代付申请(国2)提交失败', '表单提交方式，错误原因：谷歌安全码不能为空');
                    $this->error("谷歌安全码不能为空！");
                } else {
                    $ga = new \Org\Util\GoogleAuthenticator();
                    if(false === $ga->verifyCode($this->fans['google_secret_key'], $google_code, C('google_discrepancy'))) {
                        log_auth_error($userid,4);
                        UserLogService::write(2, 'U代付申请(国2)提交失败', '表单提交方式，错误原因：谷歌安全码错误');
                        $this->error("谷歌安全码错误！");
                    } else {
                        clear_auth_error($userid,4);
                    }
                }
            } elseif($verifysms && $auth_type == 0){//短信验证码
                $res = check_auth_error($userid, 2);
                if(!$res['status']) {
                    UserLogService::write(2, 'U代付申请(国2)提交失败', '表单提交方式，错误原因：短信验证码输入错误次数超限');
                    $this->error($res['msg']);
                }
                $code   = I('post.code', '', 'string,strip_tags,htmlspecialchars');
                if(!$code) {
                    UserLogService::write(2, 'U代付申请(国2)提交失败', '表单提交方式，错误原因：短信验证码不能为空');
                    $this->error("短信验证码不能为空！");
                } else {
                    if (session('send.entrusted') != $code || !$this->checkSessionTime('entrusted', $code)) {
                        log_auth_error($userid,2);
                        UserLogService::write(2, 'U代付申请(国2)请提交失败', '表单提交方式，错误原因：短信验证码错误');
                        $this->error('短信验证码错误');
                    } else {
                        clear_auth_error($userid,2);
                        session('send', null);
                    }
                }
            }
            $password = I('post.password', '', 'string,strip_tags,htmlspecialchars');

            if (!$password) {
                UserLogService::write(2, 'U代付申请(国2)提交失败', '表单提交方式，错误原因：支付密码不能为空');
                $this->error('支付密码不能为空！');
            }

            $data = I('post.item');
            if (empty($data)) {
                UserLogService::write(2, 'U代付申请(国2)提交失败', '表单提交方式，错误原因：代付申请数据不能为空');
                $this->error('代付申请数据不能为空！');
            }
            M()->startTrans();
            //查询用户数据
            $Member = M('Member');
            $info   = $Member->where(['id' => $userid])->lock(true)->find();
            //支付密码
            $res = check_auth_error($userid, 6);
            if(!$res['status']) {
                UserLogService::write(2, 'U代付申请(国2)提交失败', '表单提交方式，错误原因：支付密码输入错误次数超限');
                $this->error($res['msg']);
            }
            if(md5($password) != $info['paypassword']) {
                log_auth_error($userid,6);
                M()->commit();
                UserLogService::write(2, 'U代付申请(国2)提交失败', '表单提交方式，错误原因：支付密码错误');
                $this->error('支付密码有误!');
            } else {
                clear_auth_error($userid,6);
            }

            $balance    = $info['balance_inr'];
            $tkmoneysum = 0;
            foreach ($data as $k => $v) {
                if (!isset($errorTxt)) {
                    $v['tkmoney'] = trim($v['tkmoney']);
                    if(!is_numeric($v['tkmoney'])) {
                        $errorTxt = "金额错误！";
                        break;
                    }
                    //个人信息
                    if ($balance < $v['tkmoney']) {
                        $errorTxt = '金额错误，可用余额不足!';
                        break;
                    }
                    
                    $tkmoneysum += $v['tkmoney'];
                    //实际提现的金额
                    $money = $v['tkmoney']/$v['sxfmoney'];
                    //获取订单号
                    $orderid = $this->getOrderId();
                    $out_trade_no = $v['out_trade_no']?trim($v['out_trade_no']):'';

                    //提现时间
                    $time = date("Y-m-d H:i:s");

                    //扩展字段
                    $extends = '';
                    if (isset($v['extend'])) {
                        $extends = json_encode($v['extend']);
                    }
                    //提现记录
                    $wttkData[] = [
                        'orderid'      => $orderid,
                        "bankname"     => 'USDT',
                        "bankfullname" => 'USDT',
                        "memo" => 'USDT(国2)',
                        "banknumber"   => trim($v["cardnumber"]),
                        "bankcode" =>'909',
                        'paytype' => 4,
                        "userid"       => $userid,
                        "sqdatetime"   => $time,
                        "status"       => 1,
                        'tkmoney'      => $v['tkmoney'],
                        'sxfmoney'     => $v['sxfmoney'],
                        "money"        => sprintf("%.4f", $money),
                        "df_type"         => 2,
                    ];
                    $tkmoney = abs(floatval($v['tkmoney']));
                    $ymoney  = $balance;
                    $balance = bcsub($balance, $tkmoney, 4);

                    $mcData[] = [
                        "userid"     => $userid,
                        'ymoney'     => $ymoney,
                        "money"      => $v['tkmoney'],
                        'gmoney'     => $balance,
                        "datetime"   => $time,
                        "transid"    => $orderid,
                        "orderid"    => $out_trade_no,
                        'paytype' => 4,
                        "lx"         => 10,
                        'contentstr' => date("Y-m-d H:i:s") . '委托U提现(国2)操作',
                    ];
                } else {
                    break;
                }
            }

            if (!isset($errorTxt)) {
                $res1 = $Member->where(['id' => $userid])->save(['balance_inr' => $balance]);
                $MoneychangeModel = D('Moneychange');
                $Moneychangetable = $MoneychangeModel->getRealTableName($time);
                $res2 = $MoneychangeModel->table($Moneychangetable)->addAll($mcData);

                if ($res1 && $res2) {
                    $Wttklistmodel = D('Wttklist');
                    $table = $Wttklistmodel->getRealTableName($time);
                    $result = $Wttklistmodel->table($table)->addAll($wttkData);
                    if ($result) {
                        UserLogService::write(2, 'U代付申请(国2)提交成功', '表单提交方式');
                        M()->commit();
                        
                        $message = '';
                        $message .= "\r\n \*【账户USDT下发提交】\*\r\n\r\n";
                        $message .= "\*商户名称\*：" . $info['username'] . "\r\n";
                        $message .= "\*下发金额\*：`" . sprintf("%.4f", $v['tkmoney']) . "`\r\n";
                        $message .= "\*下发汇率\*：`" . sprintf("%.4f", $v['sxfmoney']) . "`\r\n";
                        $message .= "\*USDT数额\*：`" . sprintf("%.4f", $money) . "`\r\n";
                        $message .= "\*钱包地址\*：`" . trim($v["cardnumber"]) . "`\r\n";
                        $message .= "\*【请商户提交人员确认订单信息】\*\r\n";
                        $result1 = R('Telegram/Api/send', [$info['telegram_id'], $message, '', 'Markdown']);
                        
                        $this->success('委托结算提交成功！');
                    }
                }
                M()->rollback();
                UserLogService::write(2, 'U代付申请(国2)提交失败', '表单提交方式');
                $this->error('委托结算提交失败！');
            } else {
                UserLogService::write(2, 'U代付申请(国2)提交失败', '表单提交方式，错误原因：'.$errorTxt);
                $this->error($errorTxt);
            }
        }
    }

    //代付审核
    public function check()
    {
        UserLogService::write(1, '访问商户代付页面', '访问商户代付页面');
        $df_api = M("Websiteconfig")->getField('df_api');
        if (!$df_api) {
            $this->ajaxReturn(['status' => 0, 'msg' => '该功能尚未开启']);
        }
        $Member = M('Member');
        $info   = $Member->where(['id' => $this->fans['uid']])->find();
        if (!$info['df_api']) {
            $this->ajaxReturn(['status' => 0, 'msg' => '商户未开启此功能!']);
        }
        $where        = array();
        $out_trade_no = I("request.out_trade_no", '', 'string,strip_tags,htmlspecialchars');
        if ($out_trade_no) {
            $where['O.out_trade_no'] = $out_trade_no;
        }
        $this->assign('out_trade_no', $out_trade_no);
        $accountname = I("request.accountname", '', 'string,strip_tags,htmlspecialchars');
        if ($accountname != "") {
            $where['accountname'] = array('like', "%$accountname%");
        }
        $this->assign('accountname', $accountname);
        $check_status = I("request.check_status", '', 'string,strip_tags,htmlspecialchars');
        if ($check_status != '') {
            $where['check_status'] = array('eq', intval($check_status));
        }
        $this->assign('check_status', $check_status);
        $status = I("request.status", '', 'string,strip_tags,htmlspecialchars');
        if ($status != '') {
            $where['status'] = array('eq', intval($status));
        }
        $this->assign('status', $status);
        $create_time = urldecode(I("request.create_time"));
        if ($create_time) {
            list($cstime, $cetime) = explode('|', $create_time);
            $where['create_time']  = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
        }
        $this->assign('create_time', $create_time);
        $check_time = urldecode(I("request.check_time", '', 'string,strip_tags,htmlspecialchars'));
        if ($check_time) {
            list($sstime, $setime) = explode('|', $check_time);
            $where['check_time']   = ['between', [strtotime($sstime), strtotime($setime) ? strtotime($setime) : time()]];
        }
        $this->assign('check_time', $check_time);
        $where['O.userid'] = $this->fans['uid'];
        $count             = M('df_api_order')
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
            ->field('O.*,W.status,W.memo')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('id desc')
            ->select();
        //统计今日下游商户代付信息
        $beginToday = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $endToday   = mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')) - 1;
        //今日代付总金额
        $map['O.userid']      = session('user_auth.uid');
        $map['O.create_time'] = array('between', array($beginToday, $endToday));
        $stat['totay_total']  = M('df_api_order')
            ->alias('as O')
            ->join('LEFT JOIN `' . C('DB_PREFIX') . 'wttklist` AS W ON W.df_api_id = O.id')
            ->where($map)
            ->sum('O.money');
        //今日代付待审核总金额
        $map['O.check_status'] = 0;
        $stat['totay_wait']    = M('df_api_order')
            ->alias('as O')
            ->join('LEFT JOIN `' . C('DB_PREFIX') . 'wttklist` AS W ON W.df_api_id = O.id')
            ->where($map)
            ->sum('O.money');
        //今日代付待审核笔数
        $map['O.check_status']    = 0;
        $stat['totay_wait_count'] = M('df_api_order')
            ->alias('as O')
            ->join('LEFT JOIN `' . C('DB_PREFIX') . 'wttklist` AS W ON W.df_api_id = O.id')
            ->where($map)
            ->count();
        unset($map['W.check_status']);
        //今日代付待平台审核总金额
        $map['W.status']             = ['in', '0,1'];
        $stat['totay_platform_wait'] = M('df_api_order')
            ->alias('as O')
            ->join('LEFT JOIN `' . C('DB_PREFIX') . 'wttklist` AS W ON W.df_api_id = O.id')
            ->where($map)
            ->sum('O.money');
        //今日代付待平台审核总笔数
        $map['W.status']             = ['in', '0,1'];
        $stat['totay_success_count'] = M('df_api_order')
            ->alias('as O')
            ->join('LEFT JOIN `' . C('DB_PREFIX') . 'wttklist` AS W ON W.df_api_id = O.id')
            ->where($map)
            ->count();
        //今日代付成功总金额
        $map['W.status']           = 2;
        $stat['totay_success_sum'] = M('df_api_order')
            ->alias('as O')
            ->join('LEFT JOIN `' . C('DB_PREFIX') . 'wttklist` AS W ON W.df_api_id = O.id')
            ->where($map)
            ->sum('O.money');
        //今日代付成功总笔数
        $map['W.status']             = 2;
        $stat['totay_success_count'] = M('df_api_order')
            ->alias('as O')
            ->join('LEFT JOIN `' . C('DB_PREFIX') . 'wttklist` AS W ON W.df_api_id = O.id')
            ->where($map)
            ->count();
        //今日代付失败笔数
        $map['W.status']          = ['in', '3,4'];
        $stat['totay_fail_count'] = M('df_api_order')
            ->alias('as O')
            ->join('LEFT JOIN `' . C('DB_PREFIX') . 'wttklist` AS W ON W.df_api_id = O.id')
            ->where($map)
            ->count();
        foreach ($stat as $k => $v) {
            $stat[$k] += 0;
        }
        $this->assign('stat', $stat);
        $this->assign('rows', $rows);
        $this->assign("list", $list);
        $this->assign("page", $page->show());
        $this->display();
    }

    //查看代付详情
    public function showDf()
    {
        $id = I("get.id", 0, 'intval');
        UserLogService::write(1, '访问商户代付详情', 'ID：'.$id);
        $df_api = M("Websiteconfig")->getField('df_api');
        if (!$df_api) {
            $this->ajaxReturn(['status' => 0, 'msg' => '该功能尚未开启']);
        }
        $Member = M('Member');
        $info   = $Member->where(['id' => $this->fans['uid']])->find();
        if (!$info['df_api']) {
            $this->ajaxReturn(['status' => 0, 'msg' => '商户未开启此功能!']);
        }
        if ($id) {
            $order = M('df_api_order')
                ->alias('as O')
                ->join('LEFT JOIN `' . C('DB_PREFIX') . 'wttklist` AS W ON W.df_api_id = O.id')
                ->where(['O.id' => $id, 'O.userid' => $this->fans['uid']])
                ->field('O.*,W.status')
                ->find();
        }
        $this->assign('order', $order);
        $this->display();
    }

    //审核通过代付
    public function dfPass()
    {
        $id = I("request.id", 0, 'intval');
        $df_api = M("Websiteconfig")->getField('df_api');
        if (!$df_api) {
            UserLogService::write(3, '审核通过商户代付失败', 'ID：'.$id.'，原因：该功能尚未开启');
            $this->ajaxReturn(['status' => 0, 'msg' => '该功能尚未开启']);
        }
        $Member = M('Member');
        $info   = $Member->where(['id' => $this->fans['uid']])->find();
        if (!$info['df_api']) {
            UserLogService::write(3, '审核通过商户代付失败', 'ID：'.$id.'，原因：商户未开启此功能');
            $this->ajaxReturn(['status' => 0, 'msg' => '商户未开启此功能!']);
        }
        if (IS_POST) {
            if (!$id) {
                UserLogService::write(3, '审核通过商户代付失败', 'ID：'.$id.'，原因：缺少参数ID');
                $this->ajaxReturn(['status' => 0, 'msg' => '操作失败']);
            }
            $userid   = session('user_auth.uid');
            $password = I('post.password', '');
            //开启事务
            M()->startTrans();
            //查询用户数据
            $Member = M('Member');
            $info   = $Member->where(['id' => $userid])->lock(true)->find();
            if (!$password) {
                UserLogService::write(3, '审核通过商户代付失败', 'ID：'.$id.'，原因：缺少支付密码');
                $this->ajaxReturn(['status' => 0, 'msg' => '请输入支付密码!']);
            }
            //支付密码
            $res = check_auth_error($userid, 6);
            if(!$res['status']) {
                UserLogService::write(3, '审核通过商户代付失败', 'ID：'.$id.'，原因：支付密码输入错误次数超限');
                $this->ajaxReturn(['status' => 0, 'msg' => $res['msg']]);
            }
            if(md5($password) != $info['paypassword']) {
                log_auth_error($userid,6);
                M()->commit();
                UserLogService::write(3, '审核通过商户代付失败', 'ID：'.$id.'，原因：支付密码有误');
                $this->ajaxReturn(['status' => 0, 'msg' => '支付密码有误!']);
            } else {
                clear_auth_error($userid,6);
            }
            $where['id']     = $id;
            $where['userid'] = $this->fans['uid'];
            $withdraw        = M("df_api_order")->where($where)->lock(true)->find();
            if (empty($withdraw)) {
                UserLogService::write(3, '审核通过商户代付失败', 'ID：'.$id.'，原因：代付申请不存在');
                $this->ajaxReturn(['status' => 0, 'msg' => '代付申请不存在']);
            }
            if ($withdraw['check_status'] == 1) {
                UserLogService::write(3, '审核通过商户代付失败', 'ID：'.$id.'，原因：代付已通过审核');
                $this->ajaxReturn(['status' => 0, 'msg' => '代付已通过审核，请不要重复提交']);
            } elseif ($withdraw['check_status'] == 2) {
                UserLogService::write(3, '审核通过商户代付失败', 'ID：'.$id.'，原因：该代付已驳回');
                $this->ajaxReturn(['status' => 0, 'msg' => '该代付已驳回，不能审核通过']);
            } else {
                //判断是否设置了节假日不能提现
                $tkHolidayList = M('Tikuanholiday')->limit(366)->getField('datetime', true);
                if ($tkHolidayList) {
                    $today = date('Ymd');
                    foreach ($tkHolidayList as $k => $v) {
                        if ($today == date('Ymd', $v)) {
                            UserLogService::write(3, '审核通过商户代付失败', 'ID：'.$id.'，原因：节假日无法提款');
                            $this->ajaxReturn(['status' => 0, 'msg' => '节假日暂时无法提款！']);
                        }
                    }
                }
                //结算方式：
                $Tikuanconfig = M('Tikuanconfig');
                $tkConfig     = $Tikuanconfig->where(['userid' => $userid, 'tkzt' => 1])->find();

                $defaultConfig = $Tikuanconfig->where(['issystem' => 1, 'tkzt' => 1])->find();

                //判断是否开启提款设置
                if (!$defaultConfig) {
                    UserLogService::write(3, '审核通过商户代付失败', 'ID：'.$id.'，原因：提款已关闭');
                    $this->ajaxReturn(['status' => 0, 'msg' => '提款已关闭！']);
                }

                //判断是否设置个人规则
                if (!$tkConfig || $tkConfig['tkzt'] != 1 || $tkConfig['systemxz'] != 1) {
                    $tkConfig = $defaultConfig;
                } else {
                    //个人规则，但是提现时间规则要按照系统规则
                    $tkConfig['allowstart'] = $defaultConfig['allowstart'];
                    $tkConfig['allowend']   = $defaultConfig['allowend'];
                }

                //判断结算方式
                $t = $tkConfig['t1zt'] > 0 ? $tkConfig['t1zt'] : 0;
                //判断是否T+7,T+30
                if ($t == 7) {
                //T+7每周一结算
                    if (date('w') != 1) {
                        UserLogService::write(3, '审核通过商户代付失败', 'ID：'.$id.'，原因：请在周一申请结算');
                        $this->ajaxReturn(['status' => 0, 'msg' => '请在周一申请结算!']);
                    }
                } elseif ($t == 30) {
                    //月结
                    if (date('j') != 1) {
                        UserLogService::write(3, '审核通过商户代付失败', 'ID：'.$id.'，原因：请在每月1日申请结算');
                        $this->ajaxReturn(['status' => 0, 'msg' => '请在每月1日申请结算!']);
                    }
                }

                //是否在许可的提现时间
                $hour = date('H');
                //判断提现时间是否合法
                if ($tkConfig['allowend'] != 0) {
                    if ($tkConfig['allowstart'] > $hour || $tkConfig['allowend'] <= $hour) {
                        UserLogService::write(3, '审核通过商户代付失败', 'ID：'.$id.'，原因：不在结算时间内');
                        $this->ajaxReturn(['status' => 0, 'msg' => '不在结算时间内，算时间段为' . $tkConfig['allowstart'] . ':00 - ' . $tkConfig['allowend'] . ':00']);
                    }
                }

                //单笔最小提款金额
                $tkzxmoney = $tkConfig['tkzxmoney'];
                //单笔最大提款金额
                $tkzdmoney = $tkConfig['tkzdmoney'];

                //查询代付表跟提现表的条件
                $map['userid']     = $userid;
                $map['sqdatetime'] = ['between', [date('Y-m-d') . ' 00:00:00', date('Y-m-d') . ' 23:59:59']];

                //统计提现表的数据
//            $Tklist = M('Tklist');
                $tkNum  = 0;
                $tkSum  = 0;

                //统计代付表的数据
                $Wttklist = D('Wttklist');
                $wttkNum  = $Wttklist->getCount($map);
                $wttkSum  = $Wttklist->getSum('tkmoney',$map);
//                $Wttklist = M('Wttklist');
//                $wttkNum  = $Wttklist->where($map)->count();
//                $wttkSum  = $Wttklist->where($map)->sum('tkmoney');

                //判断是否超过当天次数
                $dayzdnum = $tkNum + $wttkNum + 1;
                if ($dayzdnum >= $tkConfig['dayzdnum']) {
                    $errorTxt = "超出当日提款次数！";
                }

                //判断提款额度
                $dayzdmoney = bcadd($wttkSum, $tkSum, 4);
                if ($dayzdmoney >= $tkConfig['dayzdmoney']) {
                    $errorTxt = "超出当日提款额度！";
                }
                $balance = $info['balance'];
                if (!isset($errorTxt)) {
                    if ($balance < $withdraw['money']) {
                        $errorTxt = '金额错误，可用余额不足!';
                    }
                    if ($withdraw['money'] < $tkzxmoney || $withdraw['money'] > $tkzdmoney) {
                        $errorTxt = '提款金额不符合提款额度要求!';
                    }
                    $dayzdmoney = bcadd($withdraw['money'], $dayzdmoney, 4);
                    if ($dayzdmoney >= $tkConfig['dayzdmoney']) {
                        $errorTxt = "超出当日提款额度！";
                    }
                    //计算手续费
                    $sxfmoney = $tkConfig['tktype'] ? $tkConfig['sxffixed'] : bcdiv(bcmul($withdraw['money'], $tkConfig['sxfrate'], 4), 100, 4);
                    if($tkConfig['tk_charge_type']) {
                        //实际提现的金额
                        $money = $withdraw['money'];
                    } else {
                        //实际提现的金额
                        $money = bcsub($withdraw['money'], $sxfmoney, 4);
                    }
                    //获取订单号
                    $orderid = $this->getOrderId();
                    $out_trade_no = trim($withdraw['out_trade_no']);

                    //提现时间
                    $time = date("Y-m-d H:i:s");

                    //提现记录
                    $wttkData = [
                        'orderid'      => $orderid,
                        "bankname"     => trim($withdraw["bankname"]),
                        "bankzhiname"  => trim($withdraw["subbranch"]),
                        "banknumber"   => trim($withdraw["cardnumber"]),
                        "bankfullname" => trim($withdraw['accountname']),
                        "sheng"        => trim($withdraw["province"]),
                        "shi"          => trim($withdraw["city"]),
                        "userid"       => $userid,
                        "sqdatetime"   => $time,
                        "status"       => 0,
                        "t"            => $t,
                        'tkmoney'      => $withdraw['money'],
                        'sxfmoney'     => $sxfmoney,
                        "money"        => $money,
                        "additional"   => '',
                        "out_trade_no" => $withdraw['out_trade_no'],
                        "df_api_id"    => $withdraw['id'],
                        "extends"      => $withdraw['extends'],
                        "df_charge_type" => $tkConfig['tk_charge_type']
                    ];

                    $tkmoney = abs(floatval($withdraw['money']));
                    $ymoney  = $balance;
                    $balance = bcsub($balance, $tkmoney, 4);
                    $mcData  = [
                        "userid"     => $userid,
                        'ymoney'     => $ymoney,
                        "money"      => $withdraw['money'],
                        'gmoney'     => $balance,
                        "datetime"   => $time,
                        "transid"    => $orderid,
                        "orderid"    => $out_trade_no,
                        "lx"         => 10,
                        'contentstr' => date("Y-m-d H:i:s") . '委托提现操作',
                    ];
                    if($tkConfig['tk_charge_type'] && $sxfmoney>0) {
                        $balance = bcsub($balance, $sxfmoney, 4);
                        $chargeData = [
                            "userid"     => $userid,
                            'ymoney'     => $ymoney-$withdraw['money'],
                            "money"      => $sxfmoney,
                            'gmoney'     => $balance,
                            "datetime"   => $time,
                            "transid"    => $orderid,
                            "orderid"    => $out_trade_no,
                            "lx"         => 14,
                            'contentstr' => date("Y-m-d H:i:s") . '委托提现扣除手续费',
                        ];
                    }
                }
                if (!isset($errorTxt)) {
                    $res1 = $Member->where(['id' => $userid])->save(['balance' => $balance]);

                    $table = $Wttklist->getRealTableName($time);
                    $res2 = $Wttklist->table($table)->add($wttkData);
//                    $res2 = $Wttklist->add($wttkData);
                    $res3 = M("df_api_order")->where(['check_status' => 0, 'userid' => $userid, 'id' => $id])->save(['df_id' => $res2, 'check_status' => 1, 'check_time' => time()]);
                    $res4 = M('Moneychange')->add($mcData);
                    if($tkConfig['tk_charge_type'] && $sxfmoney>0) {
                        $res5 = M('Moneychange')->add($chargeData);
                    } else {
                        $res5 = true;
                    }
                    if ($res1 && $res2 && $res3 && $res4 && $res5) {
                        M()->commit();
                        UserLogService::write(3, '审核通过商户代付成功', 'ID：'.$id);
                        $this->ajaxReturn(['status' => 1, 'msg' => '代付审核通过成功！']);
                    }
                    M()->rollback();
                    UserLogService::write(3, '审核通过商户代付失败', 'ID：'.$id);
                    $this->ajaxReturn(['status' => 0]);
                } else {
                    UserLogService::write(3, '审核通过商户代付失败', 'ID：'.$id.'，原因：'.$errorTxt);
                    $this->ajaxReturn(['status' => 0, 'msg' => $errorTxt]);
                }
            }
        } else {
            $id = I("get.id", 0, 'intval');
            UserLogService::write(1, '访问审核商户代付详情页面', 'ID：'.$id);
            $info = M('df_api_order')->where(['id' => $id])->find();
            $this->assign('info', $info);
            $this->display();
        }
    }

    //驳回代付
    public function dfReject()
    {
        $id = I("request.id", 0, 'intval');
        $df_api = M("Websiteconfig")->getField('df_api');
        if (!$df_api) {
            UserLogService::write(3, '驳回商户代付失败', 'ID：'.$id.'，原因：该功能尚未开启');
            $this->ajaxReturn(['status' => 0, 'msg' => '该功能尚未开启']);
        }
        if (IS_POST) {
            if (!$id) {
                UserLogService::write(3, '驳回商户代付失败', 'ID：'.$id.'，原因：缺少参数ID');
                $this->ajaxReturn(['status' => 0, 'msg' => '操作失败']);
            }
            $Member = M('Member');
            $info   = $Member->where(['id' => $this->fans['uid']])->find();
            if (!$info['df_api']) {
                UserLogService::write(3, '驳回商户代付失败', 'ID：'.$id.'，原因：商户未开启此功能');
                $this->ajaxReturn(['status' => 0, 'msg' => '商户未开启此功能!']);
            }
            $reject_reason = I('post.reject_reason');
            if (!$reject_reason) {
                UserLogService::write(3, '驳回商户代付失败', 'ID：'.$id.'，原因：未填写驳回理由');
                $this->ajaxReturn(['status' => 0, 'msg' => '请填写驳回理由']);
            }
            //开启事务
            M()->startTrans();
            $withdraw = M("df_api_order")->where(['id' => $id, 'userid' => $this->fans['uid']])->lock(true)->find();
            if (empty($withdraw)) {
                UserLogService::write(3, '驳回商户代付失败', 'ID：'.$id.'，原因：商户代付申请不存在');
                $this->ajaxReturn(['status' => 0, 'msg' => '代付申请不存在']);
            }
            $data           = [];
            $data["status"] = 3;
            if ($withdraw['check_status'] == 1 && $withdraw['df_id'] > 0) {
                $df_order = M('wttklist')->where(['id' => $withdraw['df_id'], 'userid' => $this->fans['uid']])->lock(true)->find();
                if (!empty($df_order)) {
                    if ($df_order['status'] != 0) {
                        UserLogService::write(3, '驳回商户代付失败', 'ID：'.$id.'，原因：后台已处理代付，不能驳回');
                        $this->ajaxReturn(['status' => 0, 'msg' => '后台已处理代付，不能驳回']);
                    } else {
                        //将金额返回给商户
                        $Member     = M('Member');
                        $memberInfo = $Member->where(['id' => $this->fans['uid']])->lock(true)->find();
                        $res        = $Member->where(['id' => $this->fans['uid']])->save(['balance' => array('exp', "balance+{$df_order['tkmoney']}")]);
                        if (!$res) {
                            M()->rollback();
                            $this->ajaxReturn(['status' => 0]);
                        }
                        //2,记录流水订单号
                        $arrayField = array(
                            "userid"     => $this->fans['uid'],
                            "ymoney"     => $memberInfo['balance'],
                            "money"      => $df_order['tkmoney'],
                            "gmoney"     => $memberInfo['balance'] + $df_order['tkmoney'],
                            "datetime"   => date("Y-m-d H:i:s"),
                            "tongdao"    => 0,
                            "transid"    => $df_order['orderid'],
                            "orderid"    => $df_order['out_trade_no'],
                            "lx"         => 12,
                            'contentstr' => '商户代付驳回',
                        );
                        $res = M('Moneychange')->add($arrayField);
                        if (!$res) {
                            M()->rollback();
                            $this->ajaxReturn(['status' => 0]);
                        }
                        $res = M("df_api_order")->where(['check_status' => 1, 'userid' => $this->fans['uid'], 'id' => $id])->save(['check_status' => 2, 'reject_reason' => $reject_reason, 'check_time' => time()]);
                        if (!$res) {
                            M()->rollback();
                            $this->ajaxReturn(['status' => 0]);
                        }
                        $data["cldatetime"] = date("Y-m-d H:i:s");
                        $data["memo"]       = $reject_reason;
                        //修改代付的数据
                        $res = M('wttklist')->where(['id' => $withdraw['df_id'], 'status' => 0])->save($data);
                        if ($res) {
                            M()->commit();
                            UserLogService::write(3, '驳回商户代付成功', 'ID：'.$id);
                            $this->ajaxReturn(['status' => $res, 'msg' => '驳回成功']);
                        }
                        M()->rollback();
                        UserLogService::write(3, '驳回商户代付失败', 'ID：'.$id);
                        $this->ajaxReturn(['status' => 0]);
                    }
                } else {
                    UserLogService::write(3, '驳回商户代付失败', 'ID：'.$id.'，原因：未找到后台代付信息');
                    $this->ajaxReturn(['status' => 0, 'msg' => '驳回失败']);
                }
            } elseif ($withdraw['check_status'] == 2) {
                UserLogService::write(3, '驳回商户代付失败', 'ID：'.$id.'，原因：该代付已驳回');
                $this->ajaxReturn(['status' => 0, 'msg' => '该代付已驳回，请不要重复提交']);
            } else {
                $res = M("df_api_order")->where(['id' => $id, 'userid' => $this->fans['uid'], 'check_status' => 0])->save(['check_status' => 2, 'reject_reason' => $reject_reason, 'check_time' => time()]);
                if ($res) {
                    M()->commit();
                    UserLogService::write(3, '驳回商户代付成功', 'ID：'.$id);
                    $this->ajaxReturn(['status' => $res, 'msg' => '驳回成功']);
                } else {
                    M()->rollback();
                    UserLogService::write(3, '驳回商户代付失败', 'ID：'.$id);
                    $this->ajaxReturn(['status' => 0]);
                }
            }
        } else {
            UserLogService::write(1, '访问驳回商户代付页面', 'ID：'.$id);
            $info = M('df_api_order')->where(['id' => $id])->find();
            $this->assign('info', $info);
            $this->display();
        }
    }

    //批量通过代付审核
    public function dfPassBatch()
    {
        if (IS_POST) {
            $id = I('post.id', '', 'string,strip_tags,htmlspecialchars');
            if (!$id) {
                $this->ajaxReturn(['status' => 0, 'msg' => '请选择代付申请']);
            }
            $id_array = explode('_', $id);
            if (empty($id_array)) {
                $this->ajaxReturn(['status' => 0, 'msg' => '参数错误']);
            }
            $df_api = M("Websiteconfig")->getField('df_api');
            if (!$df_api) {
                UserLogService::write(3, '批量通过商户代付失败', 'ID：'.$id.'，原因：该功能尚未开启');
                $this->ajaxReturn(['status' => 0, 'msg' => '该功能尚未开启']);
            }
            M()->startTrans();
            $userid = $this->fans['uid'];
            $Member = M('Member');
            $info   = $Member->where(['id' => $userid])->lock(true)->find();
            if (!$info['df_api']) {
                UserLogService::write(3, '批量通过商户代付失败', 'ID：'.$id.'，原因：商户未开启此功能');
                $this->ajaxReturn(['status' => 0, 'msg' => '商户未开启此功能!']);
            }
            $password = I('post.password', '', 'string,strip_tags,htmlspecialchars');
            if (!$password) {
                UserLogService::write(3, '批量通过商户代付失败', 'ID：'.$id.'，原因：未输入支付密码');
                $this->ajaxReturn(['status' => 0, 'msg' => '请输入支付密码!']);
            }
            //支付密码
            $res = check_auth_error($userid, 6);
            if(!$res['status']) {
                UserLogService::write(3, '批量通过商户代付失败', 'ID：'.$id.'，原因：输入支付密码错误次数超限');
                $this->ajaxReturn(['status' => 0, 'msg' => $res['msg']]);
            }
            if(md5($password) != $info['paypassword']) {
                log_auth_error($userid,6);
                M()->commit();
                UserLogService::write(3, '批量通过商户代付失败', 'ID：'.$id.'，原因：支付密码错误');
                $this->ajaxReturn(['status' => 0, 'msg' => '支付密码有误!']);
            } else {
                clear_auth_error($userid,6);
            }
            //判断是否设置了节假日不能提现
            $tkHolidayList = M('Tikuanholiday')->limit(366)->getField('datetime', true);
            if ($tkHolidayList) {
                $today = date('Ymd');
                foreach ($tkHolidayList as $k => $v) {
                    if ($today == date('Ymd', $v)) {
                        UserLogService::write(3, '批量通过商户代付失败', 'ID：'.$id.'，原因：节假日无法提款');
                        $this->ajaxReturn(['status' => 0, 'msg' => '节假日暂时无法提款！']);
                    }
                }
            }
            //结算方式：
            $Tikuanconfig = M('Tikuanconfig');
            $tkConfig     = $Tikuanconfig->where(['userid' => $userid, 'tkzt' => 1])->find();

            $defaultConfig = $Tikuanconfig->where(['issystem' => 1, 'tkzt' => 1])->find();

            //判断是否开启提款设置
            if (!$defaultConfig) {
                UserLogService::write(3, '批量通过商户代付失败', 'ID：'.$id.'，原因：提款已关闭');
                $this->ajaxReturn(['status' => 0, 'msg' => '提款已关闭！']);
            }

            //判断是否设置个人规则
            if (!$tkConfig || $tkConfig['tkzt'] != 1 || $tkConfig['systemxz'] != 1) {
                $tkConfig = $defaultConfig;
            } else {
                //个人规则，但是提现时间规则要按照系统规则
                $tkConfig['allowstart'] = $defaultConfig['allowstart'];
                $tkConfig['allowend']   = $defaultConfig['allowend'];
            }

            //判断结算方式
            $t = $tkConfig['t1zt'] > 0 ? $tkConfig['t1zt'] : 0;
            //判断是否T+7,T+30
            if ($t == 7) {
            //T+7每周一结算
                if (date('w') != 1) {
                    UserLogService::write(3, '批量通过商户代付失败', 'ID：'.$id.'，原因：请在周一申请结算');
                    $this->ajaxReturn(['status' => 0, 'msg' => '请在周一申请结算!']);
                }
            } elseif ($t == 30) {
                //月结
                if (date('j') != 1) {
                    UserLogService::write(3, '批量通过商户代付失败', 'ID：'.$id.'，原因：请在每月1日申请结算');
                    $this->ajaxReturn(['status' => 0, 'msg' => '请在每月1日申请结算!']);
                }
            }

            //是否在许可的提现时间
            $hour = date('H');
            //判断提现时间是否合法
            if ($tkConfig['allowend'] != 0) {
                if ($tkConfig['allowstart'] > $hour || $tkConfig['allowend'] <= $hour) {
                    UserLogService::write(3, '批量通过商户代付失败', 'ID：'.$id.'，原因：不在提现时间');
                    $this->ajaxReturn(['status' => 0, 'msg' => '不在提现时间，请换个时间再来!']);
                }

            }
            //单笔最小提款金额
            $tkzxmoney = $tkConfig['tkzxmoney'];
            //单笔最大提款金额
            $tkzdmoney = $tkConfig['tkzdmoney'];
            $success   = $fail   = 0;
            foreach ($id_array as $v) {
                if (!$v) {
                    continue;
                }
                $where['id']     = $v;
                $where['userid'] = $this->fans['uid'];
                $withdraw        = M("df_api_order")->where($where)->lock(true)->find();
                if (empty($withdraw)) {
                    $fail++;
                    continue;
                }
                if ($withdraw['check_status'] == 1) {
                    $fail++;
                    continue;
                } elseif ($withdraw['check_status'] == 2) {
                    $fail++;
                    continue;
                } else {
                    //查询代付表跟提现表的条件
                    $map['userid']     = $userid;
                    $map['sqdatetime'] = ['between', [date('Y-m-d') . ' 00:00:00', date('Y-m-d') . ' 23:59:59']];

                    //统计提现表的数据
//            $Tklist = M('Tklist');
                    $tkNum  = 0;
                    $tkSum  = 0;

                    //统计代付表的数据
                    $Wttklist = D('Wttklist');
                    $wttkNum  = $Wttklist->getCount($map);
                    $wttkSum  = $Wttklist->getSum('tkmoney',$map);
//                    $Wttklist = M('Wttklist');
//                    $wttkNum  = $Wttklist->where($map)->count();
//                    $wttkSum  = $Wttklist->where($map)->sum('tkmoney');

                    //判断是否超过当天次数
                    $dayzdnum = $tkNum + $wttkNum + 1;
                    if ($dayzdnum >= $tkConfig['dayzdnum']) {
                        $fail++;
                        continue;
                    }

                    //判断提款额度
                    $dayzdmoney = bcadd($wttkSum, $tkSum, 4);
                    if ($dayzdmoney >= $tkConfig['dayzdmoney']) {
                        $fail++;
                        continue;
                    }
                    $balance = $Member->where(['id' => $userid])->getField('balance');

                    if ($balance <= $withdraw['money']) {
                        $fail++;
                        continue;
                    }
                    if ($withdraw['money'] < $tkzxmoney || $withdraw['money'] > $tkzdmoney) {
                        $fail++;
                        continue;
                    }
                    $dayzdmoney = bcadd($withdraw['money'], $dayzdmoney, 4);
                    if ($dayzdmoney >= $tkConfig['dayzdmoney']) {
                        $fail++;
                        continue;
                    }
                    //计算手续费
                    $sxfmoney = $tkConfig['tktype'] ? $tkConfig['sxffixed'] : bcdiv(bcmul($withdraw['money'], $tkConfig['sxfrate'], 4), 100, 4);
                    if($tkConfig['tk_charge_type']) {
                        //实际提现的金额
                        $money = $withdraw['money'];
                    } else {
                        //实际提现的金额
                        $money = bcsub($withdraw['money'], $sxfmoney, 4);
                    }
                    //获取订单号
                    $orderid = $this->getOrderId();
                    $out_trade_no = trim($withdraw['out_trade_no']);

                    //提现时间
                    $time = date("Y-m-d H:i:s");

                    //提现记录
                    $wttkData = [
                        'orderid'      => $orderid,
                        "bankname"     => trim($withdraw["bankname"]),
                        "bankzhiname"  => trim($withdraw["subbranch"]),
                        "banknumber"   => trim($withdraw["cardnumber"]),
                        "bankfullname" => trim($withdraw['accountname']),
                        "sheng"        => trim($withdraw["province"]),
                        "shi"          => trim($withdraw["city"]),
                        "userid"       => $userid,
                        "sqdatetime"   => $time,
                        "status"       => 0,
                        "t"            => $t,
                        'tkmoney'      => $withdraw['money'],
                        'sxfmoney'     => $sxfmoney,
                        "money"        => $money,
                        "additional"   => '',
                        "out_trade_no" => $withdraw['out_trade_no'],
                        "df_api_id"    => $withdraw['id'],
                        "extends"      => $withdraw['extends'],
                        "df_charge_type" => $tkConfig['tk_charge_type']
                    ];

                    $tkmoney = abs(floatval($withdraw['money']));
                    $ymoney  = $balance;
                    $balance = bcsub($balance, $tkmoney, 4);
                    $mcData  = [
                        "userid"     => $userid,
                        'ymoney'     => $ymoney,
                        "money"      => $withdraw['money'],
                        'gmoney'     => $balance,
                        "datetime"   => $time,
                        "transid"    => $orderid,
                        "orderid"    => $out_trade_no,
                        "lx"         => 10,
                        'contentstr' => date("Y-m-d H:i:s") . '委托提现操作',
                    ];
                    if($tkConfig['tk_charge_type'] && $sxfmoney>0) {
                        $balance = bcsub($balance, $sxfmoney, 4);
                        if($balance-$sxfmoney<0) {
                            M()->rollback();
                            $fail++;
							continue;
                        }
                        $chargeData = [
                            "userid"     => $userid,
                            'ymoney'     => $ymoney-$withdraw['money'],
                            "money"      => $sxfmoney,
                            'gmoney'     => $balance,
                            "datetime"   => $time,
                            "transid"    => $orderid,
                            "orderid"    => $out_trade_no,
                            "lx"         => 14,
                            'contentstr' => date("Y-m-d H:i:s") . '代付结算扣除手续费',
                        ];
                    }
                    $res1 = $Member->where(['id' => $userid])->save(['balance' => $balance]);
                    $table = $Wttklist->getRealTableName($time);
                    $res2 = $Wttklist->table($table)->add($wttkData);
//                    $res2 = $Wttklist->add($wttkData);
                    $res3 = M("df_api_order")->where(['check_status' => 0, 'userid' => $userid, 'id' => $v])->save(['df_id' => $res2, 'check_status' => 1, 'check_time' => time()]);
                    $res4 = M('Moneychange')->add($mcData);
                    if($tkConfig['tk_charge_type'] && $sxfmoney>0) {
                        $res5 = M('Moneychange')->add($chargeData);
                    } else {
                        $res5 = true;
                    }
                    if ($res1 && $res2 && $res3 && $res4 && $res5) {
                        M()->commit();
                        $success++;
                    } else {
                        M()->rollback();
                        $fail++;
                    }
                }
            }
            if ($success > 0 && $fail == 0) {
                UserLogService::write(3, '批量通过商户代付成功', 'ID：'.$id);
                $this->ajaxReturn(['status' => 1, 'msg' => '审核成功！']);
            } elseif ($success > 0 && $fail > 0) {
                UserLogService::write(3, '批量通过商户代付部分成功，成功:' . $success . '条，失败：' . $fail . '条', 'ID：'.$id);
                $this->ajaxReturn(['status' => 1, 'msg' => '部分成功，成功:' . $success . '条，失败：' . $fail . '条']);
            } else {
                UserLogService::write(3, '批量通过商户代付失败', 'ID：'.$id);
                $this->ajaxReturn(['status' => 0, 'msg' => '审核失败！']);
            }
        } else {
            $id = I('get.id', '', 'string,strip_tags,htmlspecialchars');
            UserLogService::write(1, '访问批量通过商户代付页面', 'ID：'.$id);
            $this->assign('id', $id);
            $this->display();
        }
    }
//
//    //批量驳回代付审核
//    public function dfRejectBatch()
//    {
//
//        if (IS_POST) {
//            $id = I('post.id', '', 'string,strip_tags,htmlspecialchars');
//            if (!$id) {
//                $this->ajaxReturn(['status' => 0, 'msg' => '请选择代付申请']);
//            }
//            $id_array = explode('_', $id);
//            if (empty($id_array)) {
//                $this->ajaxReturn(['status' => 0, 'msg' => '参数错误']);
//            }
//            $df_api = M("Websiteconfig")->getField('df_api');
//            if (!$df_api) {
//                UserLogService::write(3, '批量驳回商户代付失败', 'ID：'.$id.'，原因：该功能尚未开启');
//                $this->ajaxReturn(['status' => 0, 'msg' => '该功能尚未开启']);
//            }
//            $userid = $this->fans['uid'];
//            $Member = M('Member');
//            $info   = $Member->where(['id' => $userid])->find();
//            if (!$info['df_api']) {
//                UserLogService::write(3, '批量驳回商户代付失败', 'ID：'.$id.'，原因：商户未开启此功能');
//                $this->ajaxReturn(['status' => 0, 'msg' => '商户未开启此功能!']);
//            }
//            $password = I('post.password', '');
//            if (!$password) {
//                UserLogService::write(3, '批量驳回商户代付失败', 'ID：'.$id.'，原因：未输入支付密码');
//                $this->ajaxReturn(['status' => 0, 'msg' => '请输入支付密码!']);
//            }
//            //支付密码
//            $res = check_auth_error($userid, 6);
//            if(!$res['status']) {
//                UserLogService::write(3, '批量驳回商户代付失败', 'ID：'.$id.'，原因：支付密码输入错误次数超限');
//                $this->ajaxReturn(['status' => 0, 'msg' => $res['msg']]);
//            }
//            if(md5($password) != $info['paypassword']) {
//                log_auth_error($userid,6);
//                M()->commit();
//                UserLogService::write(3, '批量驳回商户代付失败', 'ID：'.$id.'，原因：支付密码错误');
//                $this->ajaxReturn(['status' => 0, 'msg' => '支付密码有误!']);
//            } else {
//                clear_auth_error($userid,6);
//            }
//            $success = $fail = 0;
//            foreach ($id_array as $v) {
//                try {
//                    if (!$v) {
//                        $fail++;
//                        continue;
//                    }
//                    M()->startTrans();
//                    $withdraw = M("df_api_order")->where(['id' => $v, 'userid' => $this->fans['uid']])->lock(true)->find();
//                    if (empty($withdraw)) {
//                        $fail++;
//                        continue;
//                    }
//                    $data           = [];
//                    $data["status"] = 3;
//                    if ($withdraw['check_status'] == 1 && $withdraw['df_id'] > 0) {
//                        $where =
//                        $Wttklist = D('Wttklist');
//                        $order = $Wttklist->getOrderByDateRange('*',$where);
//                        $df_order = M('wttklist')->where(['id' => $withdraw['df_id'], 'userid' => $this->fans['uid']])->lock(true)->find();
//                        if (!empty($df_order)) {
//                            if ($df_order['status'] != 0) {
//                                M()->rollback();
//                                continue;
//                            } else {
//                                //将金额返回给商户
//                                $Member     = M('Member');
//                                $memberInfo = $Member->where(['id' => $this->fans['uid']])->lock(true)->find();
//                                $res        = $Member->where(['id' => $this->fans['uid']])->save(['balance' => array('exp', "balance+{$df_order['tkmoney']}")]);
//                                if (!$res) {
//                                    M()->rollback();
//                                    $fail++;
//                                    continue;
//                                }
//                                //2,记录流水订单号
//                                $arrayField = array(
//                                    "userid"     => $this->fans['uid'],
//                                    "ymoney"     => $memberInfo['balance'],
//                                    "money"      => $df_order['tkmoney'],
//                                    "gmoney"     => $memberInfo['balance'] + $df_order['tkmoney'],
//                                    "datetime"   => date("Y-m-d H:i:s"),
//                                    "tongdao"    => 0,
//                                    "transid"    => $df_order['orderid'],
//                                    "orderid"    => $df_order['out_trade_no'],
//                                    "lx"         => 12,
//                                    'contentstr' => '商户代付驳回',
//                                );
//                                $res = M('Moneychange')->add($arrayField);
//                                if (!$res) {
//                                    M()->rollback();
//                                    $fail++;
//                                    continue;
//                                }
//                                $res = M("df_api_order")->where(['check_status' => 1, 'userid' => $this->fans['uid'], 'id' => $v])->save(['check_status' => 2, 'reject_reason' => '', 'check_time' => time()]);
//                                if (!$res) {
//                                    M()->rollback();
//                                    $fail++;
//                                    continue;
//                                }
//                                $data["cldatetime"] = date("Y-m-d H:i:s");
//                                $data["memo"]       = '';
//                                //修改代付的数据
//                                $res = M('wttklist')->where(['id' => $withdraw['df_id'], 'status' => 0])->save($data);
//                                if ($res) {
//                                    M()->commit();
//                                    $success++;
//                                    continue;
//                                }
//                                M()->rollback();
//                                $fail++;
//                                continue;
//                            }
//                        } else {
//                            M()->rollback();
//                            $fail++;
//                            continue;
//                        }
//                    } elseif ($withdraw['check_status'] == 2) {
//                        M()->rollback();
//                        $fail++;
//                        continue;
//                    } else {
//                        $res = M("df_api_order")->where(['id' => $v, 'userid' => $this->fans['uid'], 'check_status' => 0])->save(['check_status' => 2, 'check_time' => time()]);
//                        if ($res) {
//                            M()->commit();
//                            $success++;
//                            continue;
//                        } else {
//                            M()->rollback();
//                            $fail++;
//                            continue;
//                        }
//                    }
//                } catch (\Exception $e) {
//                    M()->rollback();
//                    $fail++;
//                    continue;
//                }
//            }
//            if ($success > 0 && $fail == 0) {
//                UserLogService::write(3, '批量驳回商户代付成功', 'ID：'.$id);
//                $this->ajaxReturn(['status' => 1, 'msg' => '驳回成功！']);
//            } elseif ($success > 0 && $fail > 0) {
//                UserLogService::write(3, '批量驳回商户代付部分驳回成功，成功:' . $success . '条，失败：' . $fail . '条', 'ID：'.$id);
//                $this->ajaxReturn(['status' => 1, 'msg' => '部分驳回成功，成功:' . $success . '条，失败：' . $fail . '条']);
//            } else {
//                UserLogService::write(3, '批量驳回商户代付失败', 'ID：'.$id);
//                $this->ajaxReturn(['status' => 0, 'msg' => '驳回失败！']);
//            }
//        } else {
//            $id = I('get.id', '', 'string,strip_tags,htmlspecialchars');
//            UserLogService::write(1, '访问批量驳回商户代付页面', 'ID：'.$id);
//            $this->assign('id', $id);
//            $this->display();
//        }
//    }
//    
//    /**
//     *  手动修改订单状态
//     */
//    public function editwtStatus()
//    {
//        $id = I("request.id", 0, 'intval');
//        if (IS_POST) {
//            UserLogService::write(3, '保存委托提现', '保存委托提现');
//            $status = I("post.status", 0, 'intval');
//            $userid = I('post.userid', 0, 'intval');
//            $tkmoney = I('post.tkmoney');
//
//            if (!$id) {
//                $this->ajaxReturn(['status' => 0, 'msg' => '操作失败']);
//            }
//            //开启事物
//            M()->startTrans();
//            $Wttklist  = M("Wttklist");
//            $map['id'] = $id;
//            $withdraw  = $Wttklist->where($map)->lock(true)->find();
//            if (empty($withdraw)) {
//                $this->ajaxReturn(['status' => 0, 'msg' => '提款申请不存在']);
//            }
//            $data           = [];
//            $data["status"] = $status;
//            $wtStatus       = $Wttklist->where(['id' => $id])->getField('status');
//            if ($wtStatus >= 1) {
//                M()->rollback();
//                $this->ajaxReturn(['status' => 0,'msg' => '订单当前状态不允许修改!']);
//
//            }
//            //判断状态
//            switch ($status) {
//                case '2':
//                    $data["cldatetime"] = date("Y-m-d H:i:s");
//                    break;
//                case '4':
//                    //手动设置失败
//                    $memo = I('post.memo') . ' - ' . date('Y-m-d H:i:s') . '<br>' . $withdraw['memo'];
//                    $Rejsct = Reject(['id' => $id, 'status' => '4','message'=> $memo]);
//
//                    if($Rejsct){
//                        M()->commit();
//                        $this->ajaxReturn(['status' => 1,'msg' => '订单状态设置失败成功!']);
//                        break;
//                    }
//                    M()->rollback();
//                    $this->ajaxReturn(['status' => 0,'msg' => '订单状态设置失败!']);
//                    break;
//                case '6':
//                    //手动设置驳回
//                    $memo = I('post.memo') . ' - ' . date('Y-m-d H:i:s') . '<br>' . $withdraw['memo'];
//                    $Rejsct = Reject(['id' => $id, 'status' => '6','message'=> $memo]);
//                    if($Rejsct){
//                        M()->commit();
//                        $this->ajaxReturn(['status' => 1,'msg' => '订单状态设置驳回成功!']);
//                    }
//                    M()->rollback();
//                    $this->ajaxReturn(['status' => 0,'msg' => '订单状态设置失败!']);
//                    break;
//                default:
//                    # code...
//                    break;
//            }
//            if(I('post.memo')) {
//                $data["memo"] = I('post.memo') . ' - ' . date('Y-m-d H:i:s') . '<br>' . $withdraw['memo'];
//            }
//            $res = $Wttklist->where($map)->save($data);
//
//            if ($res) {
//                M()->commit();
//                UserLogService::write(3, '保存委托提现成功', '保存委托提现成功（' . $res . '）');
//                $this->ajaxReturn(['status' => $res]);
//            }
//
//            M()->rollback();
//            $this->ajaxReturn(['status' => 0]);
//
//        } else {
//            $info = M('Wttklist')->where(['id' => $id])->find();
//            $this->assign('info', $info);
//            $this->display();
//        }
//    }
    
    //提交代申请
    public function submitDf()
    {
        UserLogService::write(3, '提交代申请', '提交代申请');
        $uid = $this->fans['uid'];
        $verifysms = 0; //是否可以短信验证
        $sms_is_open = smsStatus();
        if ($sms_is_open) {
            $adminMobileBind = adminMobileBind($uid);
            if ($adminMobileBind) {
                $verifysms = 1;
            }
        }
        //是否可以谷歌安全码验证
        $verifyGoogle = 0;
        $googleAuth   = M('Websiteconfig')->getField('google_auth');
        if ($googleAuth) {
            $verifyGoogle = adminGoogleBind($uid);
        }

        if (IS_POST) {
            $uid               = $this->fans['uid'];
            $ids = I('request.ids');
            if (!$ids) {
                $this->ajaxReturn(['status' => 0, 'msg' => "请选择代付申请！"]);
            }
            $ids_array = explode(',', $ids);
            if (empty($ids_array)) {
                $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！"]);
            } else {
                if (count($ids_array) > 1) {
                    $channe_code = 'default'; //默认代付渠道;
                } else {
                    $channe_code = I('request.channe_code', '');
                }
            }
            if (!$channe_code) {
                $channe_code = 'default';
            }
            $auth_type = I('request.auth_type', 0, 'intval');

            if($verifyGoogle && $verifysms) {
                if(!in_array($auth_type,[0,1])) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！"]);
                }
            } elseif($verifyGoogle && !$verifysms) {
                if($auth_type != 1) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！"]);
                }
            } elseif(!$verifyGoogle && $verifysms) {
                if($auth_type != 0) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！"]);
                }
            }
            if ($verifyGoogle && $auth_type == 1) {
                $res = check_auth_error($uid, 5);
                if(!$res['status']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => $res['msg']]);
                }
                //谷歌安全码验证
                $google_code = I('request.google_code');
                if (!$google_code) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "谷歌安全码不能为空！"]);
                } else {
                    $ga                = new \Org\Util\GoogleAuthenticator();
                    $google_secret_key = M('Member')->where(['id' => $uid])->getField('google_secret_key');
                    if (!$google_secret_key) {
                        $this->ajaxReturn(['status' => 0, 'msg' => "您未绑定谷歌身份验证器！"]);
                    }
                    if(false === $ga->verifyCode($google_secret_key, $google_code, C('google_discrepancy'))) {
                        log_auth_error($uid,5);
                        $this->ajaxReturn(['status' => 0, 'msg' => "谷歌安全码错误！"]);
                    } else {
                        clear_auth_error($uid,5);
                    }
                }
            } elseif ($verifysms && $auth_type == 0) {
                $res = check_auth_error($uid, 3);
                if(!$res['status']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => $res['msg']]);
                }
                //短信验证码
                $code = I('request.code');
                if (!$code) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "短信验证码不能为空！"]);
                } else {
                    if (session('send.submitDfSend') != $code || !$this->checkSessionTime('submitDfSend', $code)) {
                        log_auth_error($uid,3);
                        $this->ajaxReturn(['status' => 0, 'msg' => '短信验证码错误']);
                    } else {
                        clear_auth_error($uid,3);
                    }
                }
            }
            session('user_submit_df', 1);
            $_REQUEST = [
                'code' => $channe_code,
                'id'   => $ids,
                'opt'  => 'exec',
            ];
            UserLogService::write(3, '提交代申请', '提交代申请' . json_encode($_REQUEST));
            return R('Payment/Index/index');
        } else {
            $ids = I('request.ids');
            if (!$ids) {
                $this->error('缺少参数');
            }
            $channe_code = I('request.channe_code', '');
            $uid         = $this->fans['uid'];
            $user        = M('Member')->where(['id' => $uid])->find();
            $this->assign('mobile', $user['mobile']);
            $this->assign('ids', $ids);
            $this->assign('channe_code', $channe_code);
            $this->assign('verifysms', $verifysms);
            $this->assign('verifyGoogle', $verifyGoogle);
            $this->assign('auth_type', $verifyGoogle ? 1 : 0);
            $this->display();
        }
    }
}
