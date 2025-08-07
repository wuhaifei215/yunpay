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
use Think\Log;

/**
 * 提现控制器
 * Class WithdrawalController
 * @package Admin\Controller
 */
class WithdrawalController extends BaseController
{

    public function __construct()
    {
        parent::__construct();
        //分类 类型
        $paytypes = C('PAYTYPES');
        $this->assign('paytypes', $paytypes);
    }

    /**
     * 提款设置
     */
    public function setting()
    {
        $tab = I('tab', 1, 'intval');
        $configs = M("Tikuanconfig")->where("issystem=1")->find();
        $this->assign("tikuanconfiglist", $configs);
        $uid               = session('admin_auth')['uid'];
        $verifysms = 0;//是否可以短信验证
        $sms_is_open = smsStatus();
        if($sms_is_open) {
            $adminMobileBind = adminMobileBind($uid);
            if($adminMobileBind) {
                $verifysms = 1;
            }
        }
        //是否可以谷歌安全码验证
        $verifyGoogle = adminGoogleBind($uid);
        $user        = M('Admin')->where(['id' => $uid])->find();
        $this->assign('mobile', $user['mobile']);
        $this->assign('verifysms', $verifysms);
        $this->assign('verifyGoogle', $verifyGoogle);
        $this->assign('auth_type', $verifyGoogle ? 1 : 0);

        //排除日期
        $holiday = M('Tikuanholiday')->select();
        $this->assign("configs", $configs);
        $this->assign("holidays", $holiday);
        $this->assign("tab", $tab);
        $this->display();
    }

    /**
     * 保存系统提款设置
     */
    public function saveWithdrawal()
    {
        UserLogService::HTwrite(3, '保存系统提款设置', '保存系统提款设置');
        if (IS_POST) {
            $id  = I('post.id', 0, 'intval') ? I('post.id', 0, 'intval') : 0;
            $tab = I('tab', 1, 'intval');

            $_rows           = I('post.u');
            $_rows['userid'] = 1;
            $auth_type = I('request.auth_type',0,'intval');
            $uid               = session('admin_auth')['uid'];
            $verifysms = 0;//是否可以短信验证
            $sms_is_open = smsStatus();
            if($sms_is_open) {
                $adminMobileBind = adminMobileBind($uid);
                if($adminMobileBind) {
                    $verifysms = 1;
                }
            }
            //是否可以谷歌安全码验证
            $verifyGoogle = adminGoogleBind($uid);
            if($verifyGoogle && $verifysms) {
                if(!in_array($auth_type,[0,1])) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！", 'tab' => $tab]);
                }
            } elseif($verifyGoogle && !$verifysms) {
                if($auth_type != 1) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！", 'tab' => $tab]);
                }
            } elseif(!$verifyGoogle && $verifysms) {
                if($auth_type != 0) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！", 'tab' => $tab]);
                }
            }
            if ($verifyGoogle && $auth_type == 1) {//谷歌安全码验证
                $google_code   = I('request.google_code');
                if(!$google_code) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "谷歌安全码不能为空！", 'tab' => $tab]);
                } else {
                    $res = check_auth_error($uid, 5);
                    if(!$res['status']) {
                        $this->ajaxReturn(['status' => 0, 'msg' => $res['msg'], 'tab' => $tab]);
                    }
                    $ga = new \Org\Util\GoogleAuthenticator();
                    $google_secret_key = M('Admin')->where(['id'=>$uid])->getField('google_secret_key');
                    if(!$google_secret_key) {
                        $this->ajaxReturn(['status' => 0, 'msg' => "您未绑定谷歌身份验证器！", 'tab' => $tab]);
                    }
                    if(false === $ga->verifyCode($google_secret_key, $google_code, C('google_discrepancy'))) {
                        log_auth_error($uid,5);
                        $this->ajaxReturn(['status' => 0, 'msg' => "谷歌安全码错误！", 'tab' => $tab]);
                    } else {
                        clear_auth_error($uid,5);
                    }
                }
            } elseif($verifysms && $auth_type == 0) {//短信验证码
                $res = check_auth_error($uid, 3);
                if (!$res['status']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => $res['msg'], 'tab' => $tab]);
                }
                $code = I('request.code');
                if (!$code) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "短信验证码不能为空！", 'tab' => $tab]);
                } else {
                    if (session('send.tkconfig') != $code || !$this->checkSessionTime('tkconfig', $code)) {
                        log_auth_error($uid, 3);
                        $this->ajaxReturn(['status' => 0, 'msg' => '验证码错误', 'tab' => $tab]);
                    } else {
                        clear_auth_error($uid, 3);
                        session('send', null);
                    }
                }
            }
            if ($id) {
                $res = M("Tikuanconfig")->where(['id' => $id])->save($_rows);
                UserLogService::HTwrite(3, '修改系统提款设置成功', '成功修改（' . $id . '）系统提款设置');
            } else {
                $res = M("Tikuanconfig")->add($_rows);
                UserLogService::HTwrite(3, '添加系统提款设置成功', '成功添加系统提款设置（' . $res . '）');

            }
            $this->ajaxReturn(['status' => $res,'tab' => $tab]);
        }
    }

    /**
     * 编辑提款时间
     */
    public function settimeEdit()
    {
        if (IS_POST) {
            $id   = I('post.id', 0, 'intval');
            $tab = I('tab', 1, 'intval');
            $rows = I('post.u');
            $auth_type = I('request.auth_type',0,'intval');
            $uid               = session('admin_auth')['uid'];
            $verifysms = 0;//是否可以短信验证
            $sms_is_open = smsStatus();
            if($sms_is_open) {
                $adminMobileBind = adminMobileBind($uid);
                if($adminMobileBind) {
                    $verifysms = 1;
                }
            }
            //是否可以谷歌安全码验证
            $verifyGoogle = adminGoogleBind($uid);
            if($verifyGoogle && $verifysms) {
                if(!in_array($auth_type,[0,1])) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！", 'tab' => $tab]);
                }
            } elseif($verifyGoogle && !$verifysms) {
                if($auth_type != 1) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！", 'tab' => $tab]);
                }
            } elseif(!$verifyGoogle && $verifysms) {
                if($auth_type != 0) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！", 'tab' => $tab]);
                }
            }
            if ($verifyGoogle && $auth_type == 1) {//谷歌安全码验证
                $google_code   = I('request.google_code');
                if(!$google_code) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "谷歌安全码不能为空！", 'tab' => $tab]);
                } else {
                    $res = check_auth_error($uid, 5);
                    if(!$res['status']) {
                        $this->ajaxReturn(['status' => 0, 'msg' => $res['msg']]);
                    }
                    $ga = new \Org\Util\GoogleAuthenticator();
                    $google_secret_key = M('Admin')->where(['id'=>$uid])->getField('google_secret_key');
                    if(!$google_secret_key) {
                        $this->ajaxReturn(['status' => 0, 'msg' => "您未绑定谷歌身份验证器！", 'tab' => $tab]);
                    }
                    if(false === $ga->verifyCode($google_secret_key, $google_code, C('google_discrepancy'))) {
                        log_auth_error($uid,5);
                        $this->ajaxReturn(['status' => 0, 'msg' => "谷歌安全码错误！", 'tab' => $tab]);
                    } else {
                        clear_auth_error($uid,5);
                    }
                }
            } elseif($verifysms && $auth_type == 0) {//短信验证码
                $res = check_auth_error($uid, 3);
                if (!$res['status']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => $res['msg'], 'tab' => $tab]);
                }
                $code = I('request.code');
                if (!$code) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "短信验证码不能为空！", 'tab' => $tab]);
                } else {
                    if (session('send.tkconfig') != $code || !$this->checkSessionTime('tkconfig', $code)) {
                        log_auth_error($uid, 3);
                        $this->ajaxReturn(['status' => 0, 'msg' => '验证码错误', 'tab' => $tab]);
                    } else {
                        clear_auth_error($uid, 3);
                        session('send', null);
                    }
                }
            }
            if ($id) {
                $res = M('Tikuanconfig')->where(['id' => $id, 'issystem' => 1])->save($rows);
                UserLogService::HTwrite(3, '保存自动代付设置成功', '保存自动代付设置成功!');
            }
            $this->ajaxReturn(['status' => $res, 'tab'=>$tab]);
        }
    }

    public function addHoliday()
    {
        if (IS_POST) {
            $datetime = I("post.datetime");
            $tab = I('tab', 1, 'intval');
            if ($datetime) {
                $count = M('Tikuanholiday')->where(['datetime' => strtotime($datetime)])->count();
                if ($count) {
                    $this->ajaxReturn(['status' => 0, 'msg' => $datetime . '已存在!', 'tab' => $tab]);
                }
                $res = M('Tikuanholiday')->add(['datetime' => strtotime($datetime)]);
                $this->ajaxReturn(['status' => $res, 'tab' => $tab]);
            }
        }
    }

    public function delHoliday()
    {
        if (IS_POST) {
            $id = I("post.id", 0, 'intval');
            if ($id) {
                $res = M('Tikuanholiday')->where(["id" => $id])->delete();
                $this->ajaxReturn(['status' => $res]);
            }
        }
    }

    /**
     * 编辑自动代付设置
     */
    public function autoDfEdit()
    {
        if (IS_POST) {
            $id   = I('post.id', 0, 'intval');
            $tab = I('tab', 1, 'intval');
            $rows = I('post.u');
            $auth_type = I('request.auth_type',0,'intval');
            $uid               = session('admin_auth')['uid'];
            $verifysms = 0;//是否可以短信验证
            $sms_is_open = smsStatus();
            if($sms_is_open) {
                $adminMobileBind = adminMobileBind($uid);
                if($adminMobileBind) {
                    $verifysms = 1;
                }
            }
            //是否可以谷歌安全码验证
            $verifyGoogle = adminGoogleBind($uid);
            if($verifyGoogle && $verifysms) {
                if(!in_array($auth_type,[0,1])) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！", 'tab' => $tab]);
                }
            } elseif($verifyGoogle && !$verifysms) {
                if($auth_type != 1) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！", 'tab' => $tab]);
                }
            } elseif(!$verifyGoogle && $verifysms) {
                if($auth_type != 0) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！", 'tab' => $tab]);
                }
            }
            if ($verifyGoogle && $auth_type == 1) {//谷歌安全码验证
                $google_code   = I('request.google_code');
                if(!$google_code) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "谷歌安全码不能为空！", 'tab' => $tab]);
                } else {
                    $res = check_auth_error($uid, 5);
                    if(!$res['status']) {
                        $this->ajaxReturn(['status' => 0, 'msg' => $res['msg']]);
                    }
                    $ga = new \Org\Util\GoogleAuthenticator();
                    $google_secret_key = M('Admin')->where(['id'=>$uid])->getField('google_secret_key');
                    if(!$google_secret_key) {
                        $this->ajaxReturn(['status' => 0, 'msg' => "您未绑定谷歌身份验证器！", 'tab' => $tab]);
                    }
                    if(false === $ga->verifyCode($google_secret_key, $google_code, C('google_discrepancy'))) {
                        log_auth_error($uid,5);
                        $this->ajaxReturn(['status' => 0, 'msg' => "谷歌安全码错误！", 'tab' => $tab]);
                    } else {
                        clear_auth_error($uid,5);
                    }
                }
            } elseif($verifysms && $auth_type == 0) {//短信验证码
                $res = check_auth_error($uid, 3);
                if (!$res['status']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => $res['msg'], 'tab' => $tab]);
                }
                $code = I('request.code');
                if (!$code) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "短信验证码不能为空！", 'tab' => $tab]);
                } else {
                    if (session('send.tkconfig') != $code || !$this->checkSessionTime('tkconfig', $code)) {
                        log_auth_error($uid, 3);
                        $this->ajaxReturn(['status' => 0, 'msg' => '验证码错误', 'tab' => $tab]);
                    } else {
                        clear_auth_error($uid, 3);
                        session('send', null);
                    }
                }
            }
            if ($id) {
                $res = M('Tikuanconfig')->where(['id' => $id, 'issystem' => 1])->save($rows);
            }
            $this->ajaxReturn(['status' => $res, 'tab' => $tab]);
        }
    }
    
    
    /**
     * 代付记录
     */
    public function payment()
    {
        //通道
        $banklist = M("Product")->field('id,name,code')->select();
        $this->assign("banklist", $banklist);

        $where    = array();
        $currency = I("request.currency", '', 'string,strip_tags,htmlspecialchars');
        if($currency ==='PHP'){
            $where['paytype'] = ['between', [1,3]];
        }
        if($currency ==='INR'){
            $where['paytype'] = ['eq', 4];
        }
        $this->assign('currency', $currency);
        
        $type = I("request.type",  0, 'intval');
        if($type === 2){
            $where['df_type'] = ['eq', 2];
        }else{
            $where['df_type'] = ['neq', 2];
        }
        $this->assign('type', $type);
        $money = I("request.money", '', 'string,strip_tags,htmlspecialchars');
        if ($money) {
            $where['money'] = array('eq', $money);
        }
        $this->assign("money", $money);
        $memo = I("request.memo", '', 'string,strip_tags,htmlspecialchars');
        if ($memo) {
            $where['memo'] = ['like', "%" . $memo . "%"];
        }
        $this->assign("memo", $memo);
        
        $memberid = I("get.memberid", 0, 'intval');
        if ((intval($memberid) - 10000) > 0) {
            $where['userid'] = array('eq', $memberid - 10000);
        }
        $this->assign("memberid", $memberid);
        $orderid = I("request.orderid", '', 'string,strip_tags,htmlspecialchars');
        if ($orderid) {
            $where['orderid'] = array('eq', $orderid);
        }
        $this->assign("orderid", $orderid);
        $out_trade_no = I("request.outtradeno", '', 'string,strip_tags,htmlspecialchars');
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
        // $T = I("request.T", '', 'string,strip_tags,htmlspecialchars');
        // if ($T != "") {
        //     $where['t'] = array('eq', $T);
        // }
        // $this->assign("T", $T);
        
        $status = I("request.status", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($status != "") {
            if ($status == '2or3') {
                $where['status'] = array('between', array('2', '3'));
            } elseif ($status == '4or5') {
                $where['status'] = array('between', array('4', '5'));
            } else  {
                $where['status'] = array('eq', $status);
            }
        }
        $this->assign('status', $status);
        
        $bankcode = I("request.bankcode");
        if ($bankcode) {
            $where['bankcode'] = array('eq', $bankcode);
        }
        $this->assign("bankcode", $bankcode);
        
        $dfid = I("get.dfid", '', 'string,strip_tags,htmlspecialchars');
        if ($dfid != '') {
            $where['df_id'] = array('eq', $dfid);
        }
        $this->assign("dfid", $dfid);
        $createtime = urldecode(I("request.createtime", '', 'string,strip_tags,htmlspecialchars'));
        if ($createtime) {
            list($cstime, $cetime) = explode('|', $createtime);
            $where['sqdatetime']   = ['between', [$cstime, $cetime ? $cetime : date('Y-m-d')]];
        }
        $this->assign("createtime", $createtime);
        $successtime = urldecode(I("request.successtime", '', 'string,strip_tags,htmlspecialchars'));
        if ($successtime) {
            list($sstime, $setime) = explode('|', $successtime);
            $where['cldatetime']   = ['between', [$sstime, $setime ? $setime : date('Y-m-d')]];
        }
        $this->assign("successtime", $successtime);
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
        
        $size  = 50;
        $rows  = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }

        $field = '*';
        $Wttklist = D('Wttklist');
        $count = $Wttklist->getCount($where);
        $page = new Page($count, $rows);
        $list = $Wttklist->getOrderByDateRange($field, $where, $page->firstRow . ',' . $page->listRows, 'id desc');
        // echo $Wttklist->getLastSql();
        foreach ($list as $k => $v){
            foreach ($banklist as $kk => $vv){
                if($v['bankcode'] === $vv['id']){
                    $list[$k]['bankcode'] = $vv['name'];
                }
            }
        }

        $pfa_lists = M('PayForAnother')->where(['status' => 1])->select();
        $df_list = M('PayForAnother')->select();
        
        $this->assign('uid', session("admin_auth.uid"));
        $this->assign('rows', $rows);
        $this->assign("pfa_lists", $pfa_lists);
        $this->assign("df_list", $df_list);
        $this->assign("list", $list);
        $this->assign("page", $page->show());
        C('TOKEN_ON', false);
        if($type === 2){
            $this->display('paymentU');
        }else{
            $this->display();
        }
    }


    /**
     * 代付统计
     */
    public function payment1()
    {
        $df_list = M('PayForAnother')->where($where)->select();
        
        
        $where    = array();
        $currency = I("request.currency");
        if($currency ==='PHP'){
            $where['paytype'] = ['between', [1,3]];
            $all_balance = M('member')->sum('balance_php');
        }
        if($currency ==='INR'){
            $where['paytype'] = ['eq', 4];
            $all_balance = M('member')->sum('balance_inr');
        }
        $this->assign('currency', $currency);
        
        $memberid = I("request.memberid", '', 'string,strip_tags,htmlspecialchars');
        if ($memberid) {
            $where['userid'] = array('eq', $memberid-10000);
        }
        $this->assign("memberid", $memberid);

        //U下发
        $type = I("request.type",  0, 'intval');
        if($type === 2){
            $where['df_type'] = ['eq', 2];
        }else{
            $where['df_type'] = ['neq', 2];
        }
        $this->assign('type', $type);
        
        $dfid = I("get.dfid", '', 'string,strip_tags,htmlspecialchars');
        if ($dfid != '') {
            $where['df_id'] = array('eq', $dfid);
        }
        $this->assign("dfid", $dfid);
        
        $successtime = urldecode(I("request.successtime", '', 'string,strip_tags,htmlspecialchars'));
        if ($successtime) {
            list($sstime, $setime) = explode('|', $successtime);
        }else{
            //没有搜索条件，默认显示当前
            $sstime = date('Y-m-d') . ' 00:00:00';
            $setime = date('Y-m-d') . ' 23:59:59';
            $successtime = $sstime . ' | ' . $setime;
        }
        $this->assign("successtime", $successtime);

        
        $cstime =  date('Y-m-d 00:00:00' ,strtotime($sstime)- 3600 * 24 * 7);
        $cetime = $setime;
        $where['sqdatetime']   = ['between', [$cstime, $cetime]];
        $where['cldatetime']   = ['between', [$sstime, $setime]];
        $Wttklist = D('Wttklist');

        //统计总结算信息
        $totalMap           = $where;
        $totalMap['status']   = array('between', ['2','3']);
        //结算金额
        $stat_total = $Wttklist->getSum('money',$totalMap);
        $stat['total'] = round($stat_total['money'], 2);
        //完成笔数
        $totalMap['status']   = array('between', ['2','3']);
        $stat['total_success_count'] = $Wttklist->getCount($totalMap);
        //平台手续费利润
        $totalMap['status']   = array('between', ['2','3']);
        $stat_total_profit = $Wttklist->getSum('sxfmoney,cost',$totalMap);
        $stat['total_profit'] = round($stat_total_profit['sxfmoney'] - $stat_total_profit['cost'], 2);

        foreach ($stat as $k => $v) {
            $stat[$k] += 0;
        }

        $this->assign('stat', $stat);
        $this->assign("df_list", $df_list);
        $this->assign("all_balance", $all_balance);
        C('TOKEN_ON', false);
        $this->assign('uid', session("admin_auth.uid"));
        $this->display();
    }


    /**
     * 代付统计
     */
    public function payment1U()
    {
        $where    = array();
        
        $currency = I("request.currency");
        if($currency ==='PHP'){
            $where['paytype'] = ['between', [1,3]];
            $all_balance = M('member')->sum('balance_php');
        }
        if($currency ==='INR'){
            $where['paytype'] = ['eq', 4];
            $all_balance = M('member')->sum('balance_inr');
        }
        $this->assign('currency', $currency);
        $df_list = M('PayForAnother')->where($where)->select();
        
        $where['df_type'] = ['eq', 2];
        
        $memberid = I("request.memberid", '', 'string,strip_tags,htmlspecialchars');
        if ($memberid) {
            $where['userid'] = array('eq', $memberid-10000);
        }
        $this->assign("memberid", $memberid);
        
        $money = I("request.money", '', 'string,strip_tags,htmlspecialchars');
        if ($money) {
            $where['money'] = array('eq', $money);
        }
        $this->assign("money", $money);
        $memo = I("request.memo", '', 'string,strip_tags,htmlspecialchars');
        if ($memo) {
            $where['memo'] = ['like', "%" . $memo . "%"];
        }
        $this->assign("memo", $memo);
        
        //U下发
        $type = I("request.type",  0, 'intval');
        $bankfullname = I("request.bankfullname", '', 'string,strip_tags,htmlspecialchars');
        if ($bankfullname) {
            $where['bankfullname'] = array('eq', $bankfullname);
        }
        $this->assign("bankfullname", $bankfullname);
        $bankcode = I("request.bankcode", '', 'string,strip_tags,htmlspecialchars');
        if ($bankcode) {
            $where['bankcode'] = array('eq', $bankcode);
        }
        $this->assign("bankcode", $bankcode);
        $T = I("request.T", '', 'string,strip_tags,htmlspecialchars');
        if ($T != "") {
            $where['t'] = array('eq', $T);
        }
        $this->assign("T", $T);
        
        $dfid = I("get.dfid", '', 'string,strip_tags,htmlspecialchars');
        if ($dfid != '') {
            $where['df_id'] = array('eq', $dfid);
        }
        $this->assign("dfid", $dfid);
        
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
        $this->assign("successtime", $successtime);
        //没有搜索条件，默认显示当前
        if (!$createtime) {
            $todayBegin = date('Y-m-d') . ' 00:00:00';
            $todyEnd = date('Y-m-d') . ' 23:59:59';
            if (!$createtime && !$successtime) {
                $where['sqdatetime'] = ['between', [$todayBegin, $todyEnd]];
            }
            $createtime = $todayBegin . ' | ' . $todyEnd;
        }
        $this->assign("createtime", $createtime);
        
        $Wttklist = D('Wttklist');

        //统计总结算信息
        $totalMap           = $where;
        // $totalMap['status'] = ['in', '2,3'];
        $totalMap['status']   = array('between', ['2','3']);
        
        //结算金额
        $stat_total = $Wttklist->getSum('tkmoney',$totalMap);
        $stat['total_tk'] = round($stat_total['tkmoney'], 2);
        //结算U金额
        $stat_total = $Wttklist->getSum('money',$totalMap);
        $stat['total'] = round($stat_total['money'], 2);
        
        //待结算
        // $totalMap['status'] = ['in', '0,1'];
        $totalMap['status']   = array('between', ['0','1']);
        $stat_total_wait = $Wttklist->getSum('tkmoney',$totalMap);
        $stat['total_wait_tk'] = round($stat_total_wait['tkmoney'], 2);
        //待结算U
        // $totalMap['status'] = ['in', '0,1'];
        $totalMap['status']   = array('between', ['0','1']);
        $stat_total_wait = $Wttklist->getSum('money',$totalMap);
        $stat['total_wait'] = round($stat_total_wait['money'], 2);
        
        //完成笔数
        // $totalMap['status']          = ['in', '2,3'];
        $totalMap['status']   = array('between', ['2','3']);
        $stat['total_success_count'] = $Wttklist->getCount($totalMap);
        //失败笔数
        $totalMap['status']       = array('between', ['4','6']);
        $stat['total_fail_count'] = $Wttklist->getCount($totalMap);
        //平台手续费利润
        // $totalMap['status']   = ['in', '2,3'];
        $totalMap['status']   = array('between', ['2','3']);
        $stat_total_profit = $Wttklist->getSum('sxfmoney,cost',$totalMap);
        $stat['total_profit'] = round($stat_total_profit['sxfmoney'] - $stat_total_profit['cost'], 2);

        foreach ($stat as $k => $v) {
            $stat[$k] += 0;
        }

        $this->assign('stat', $stat);
        $this->assign("df_list", $df_list);
        $this->assign("all_balance", $all_balance);
        C('TOKEN_ON', false);
        $this->assign('uid', session("admin_auth.uid"));
        $this->display();
    }
    
    /**
     * 查看订单日志
     */
    public function showLog(){
        $orderid = I("get.orderid", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($orderid) {
            $data = json_decode(getOrderLog($orderid,'2'),true);
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
        $data = getAddLogOrders($user_id, $orderid,2,$oper_ip, $createtime);
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

    //导出委托提款记录
    public function exportweituo()
    {
        UserLogService::HTwrite(5, '导出委托提款记录', '导出委托提款记录!');
        $where = array();
                $money = I("request.money", '', 'string,strip_tags,htmlspecialchars');
        if ($money) {
            $where['money'] = array('eq', $money);
        }
        $memberid = I("get.memberid", 0, 'intval');
        if ($memberid) {
            $where['userid'] = array('eq', $memberid - 10000);
        }
        $tongdao = I("request.tongdao", '', 'string,strip_tags,htmlspecialchars');
        if ($tongdao) {
            $where['df_id'] = array('eq', $tongdao);
        }
        $T = I("request.T", '', 'string,strip_tags,htmlspecialchars');
        if ($T != "") {
            $where['t'] = array('eq', $T);
        }
        $status = I("request.status", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($status != "") {
            if ($status == '2or3') {
                $where['status'] = array('between', array('2', '3'));
            } elseif ($status == '4or5') {
                $where['status'] = array('between', array('4', '5'));
            } else  {
                $where['status'] = array('eq', $status);
            }
        }
        $this->assign('status', $status);
        $createtime = urldecode(I("request.createtime", '', 'string,strip_tags,htmlspecialchars'));
        if ($createtime) {
            list($cstime, $cetime) = explode('|', $createtime);
            if(diffBetweenTwoDays($cstime, $cetime) > 7){
                $this->ajaxReturn(['status' => 0, 'msg' => "请下载一周范围内的数据！"]);
            }
            $where['sqdatetime']   = ['between', [$cstime, $cetime ? $cetime : date('Y-m-d')]];
        }
        $successtime = urldecode(I("request.successtime", '', 'string,strip_tags,htmlspecialchars'));
        if ($successtime) {
            list($sstime, $setime) = explode('|', $successtime);
            $where['cldatetime']   = ['between', [$sstime, $setime ? $setime : date('Y-m-d')]];
        }
        if (!$createtime && !$successtime) {
            $todayBegin = date('Y-m-d') . ' 00:00:00';
            $todyEnd = date('Y-m-d') . ' 23:59:59';
            $where['sqdatetime'] = ['between', [$todayBegin, $todyEnd]];
        }
        
        $file_path = "./Uploads/download/". date('Ymd') ."/" ;
        @mkdirs($file_path);
        $uid = session('admin_auth')['uid'];
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
     *  手动修改订单状态
     */
    public function editwtStatus()
    {
        $uid = session('admin_auth')['uid'];
        $id = I("request.id", 0, 'intval');
        
        $verifysms = 0;//是否可以短信验证
        $sms_is_open = smsStatus();
        if($sms_is_open) {
            $adminMobileBind = adminMobileBind($uid);
            if($adminMobileBind) {
                $verifysms = 1;
            }
        }
        //是否可以谷歌安全码验证
        $verifyGoogle = adminGoogleBind($uid);
        
        if (IS_POST) {
            UserLogService::HTwrite(3, '保存委托提现', '保存委托提现');
            
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
                    $google_secret_key = M('Admin')->where(['id' => $uid])->getField('google_secret_key');
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
                        session('send', null);
                    }
                }
            }
            $status = I("post.status", 0, 'intval');
            $userid = I('post.userid', 0, 'intval');
            $tkmoney = I('post.tkmoney');

            if (!$id) {
                $this->ajaxReturn(['status' => 0, 'msg' => '操作失败']);
            }
            //开启事物
            M()->startTrans();
            $map['id'] = $id;
            $tableName = 'Wttklist';
            $Wttklist = D('Wttklist');
            $tables = $Wttklist->getTables();
            foreach ($tables as $v){
                $withdraw = $Wttklist->table($v)->where('id='.$id)->find();
                if(!empty($withdraw)){
                    $tableName = $v;
                    break;
                }
            }
            if (empty($withdraw)) {
                $this->ajaxReturn(['status' => 0, 'msg' => '提款申请不存在']);
            }
            $data           = [];
            $data["status"] = $status;
            $wtStatus       = $Wttklist->table($tableName)->where(['id' => $id])->getField('status');
            if ($wtStatus > 1) {
                M()->rollback();
                $this->ajaxReturn(['status' => 0,'msg' => '订单当前状态不允许修改!']);
            }
            if($status == $wtStatus){
                $this->ajaxReturn(['status' => 0,'msg' => '订单状态设置失败!']);
            }
            
            //设置redis标签，防止重复执行
            $redis = $this->redis_connect();
            if($redis->get('handle' . $id . $status)){
                $this->ajaxReturn(['status' => 0, 'msg' => '重复操作']);
            }
            $redis->set('handle' . $id . $status,'1',120);
            
            
            //判断状态
            switch ($status) {
                case '2':
                    $data["cldatetime"] = date("Y-m-d H:i:s");
                   
                    break;
                case '4':
                    if($wtStatus ==4 || $wtStatus ==5){
                        $this->ajaxReturn(['status' => 0,'msg' => '订单状态设置失败!']);
                    }
                    //手动设置失败
                    $memo = I('post.memo') . ' - ' . date('Y-m-d H:i:s') . ';' . $withdraw['memo'];
                    $Rejsct = Reject(['id' => $id, 'status' => '4','message'=> $memo]);
                    if($Rejsct){
                        M()->commit();
                        Automatic_Notify($withdraw['orderid']);
                        $this->ajaxReturn(['status' => 1,'msg' => '订单状态设置失败成功!']);
                        break;
                    }else{
                        M()->rollback();
                    }
                    $this->ajaxReturn(['status' => 0,'msg' => '订单状态设置失败!']);
                    break;
                case '6':
                    //手动设置驳回
                    $memo = I('post.memo') . ' - ' . date('Y-m-d H:i:s') . ';' . $withdraw['memo'];
                    $Rejsct = Reject(['id' => $id, 'status' => '6','message'=> $memo]);
                    if($Rejsct){
                        M()->commit();
                        Automatic_Notify($withdraw['orderid']);
                        $this->ajaxReturn(['status' => 1,'msg' => '订单状态设置驳回成功!']);
                    }else{
                        M()->rollback();
                    }
                    $this->ajaxReturn(['status' => 0,'msg' => '订单状态设置失败!']);
                    break;
                default:
                    # code...
                    break;
            }
            if(I('post.memo')) {
                $data["memo"] = I('post.memo') . ' - ' . date('Y-m-d H:i:s') . ';' . $withdraw['memo'];
            }
            $res = $Wttklist->table($tableName)->where($map)->save($data);

            if ($res) {
                if ($status == '2') {
                    Automatic_Notify($withdraw['orderid']);
                }
                M()->commit();
                UserLogService::HTwrite(3, '保存委托提现成功', $uid.'保存商户：('.$userid.')委托提现：（' . $id . '）成功');
                $this->ajaxReturn(['status' => $res]);
            }

            M()->rollback();
            $this->ajaxReturn(['status' => 0]);

        } else {
            $Wttklist = D('Wttklist');
            $tables = $Wttklist->getTables();
            foreach ($tables as $v){
                $withdraw = $Wttklist->table($v)->where(['id' => $id])->find();
                if(!empty($withdraw)){
                    break;
                }
            }
            
            $this->assign('verifysms', $verifysms);
            $this->assign('verifyGoogle', $verifyGoogle);
            $this->assign('auth_type', $verifyGoogle ? 1 : 0);
            
            $this->assign('info', $withdraw);
            $this->display();
        }
    }

    
    /**
     * 订单退款
     */
    public function refund(){
        $uid = session('admin_auth')['uid'];
        $verifysms = 0;//是否可以短信验证
        $sms_is_open = smsStatus();
        if($sms_is_open) {
            $adminMobileBind = adminMobileBind($uid);
            if($adminMobileBind) {
                $verifysms = 1;
            }
        }
        //是否可以谷歌安全码验证
        $verifyGoogle = adminGoogleBind($uid);
        
        $orderid = I("request.orderid", '', 'trim,string,strip_tags,htmlspecialchars');
        $Wttklistmodel = D('Wttklist');
        $date = date('Ymd',strtotime(substr($orderid, 1, 8)));  //获取订单日期
        $WttklistTableName = $Wttklistmodel->getRealTableName($date);
        $where=[
             'orderid'=> $orderid,
             'status' => ['between',['2', '3']]
         ];
        $withdraw = $Wttklistmodel->table($WttklistTableName)->where($where)->find();
        // echo $Wttklistmodel->table($WttklistTableName)->getLastSql();
        if (!$withdraw || empty($withdraw)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '提款申请不存在']);
        }
        
        if (IS_POST) {
             UserLogService::HTwrite(3, '退款操作', '退款操作');
            
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
                    $google_secret_key = M('Admin')->where(['id' => $uid])->getField('google_secret_key');
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
                        session('send', null);
                    }
                }
            }
            
            
            //设置redis标签，防止重复执行
            $redis = $this->redis_connect();
            if($redis->get('refund_' . $orderid)){
                UserLogService::HTwrite(3, '退款操作失败', '退款操作重复操作');
                $this->ajaxReturn(['status' => 0, 'msg' => '重复操作']);
            }
            $redis->set('refund_' . $orderid,'1',60);
            
            //开启事物
            M()->startTrans();
            
            $Member     = M('Member');
            $memberInfo = $Member->where(['id' => $withdraw['userid']])->lock(true)->find();
            if(getPaytypeCurrency($withdraw['paytype']) ==='PHP'){        //菲律宾余额
                $res = $Member->where(['id' => $withdraw['userid']])->save(['balance_php' => array('exp', "balance_php+{$withdraw['tkmoney']}")]);
                $ymoney = $memberInfo['balance_php'];
            }
            if(getPaytypeCurrency($withdraw['paytype']) ==='INR'){        //菲律宾余额
                $res = $Member->where(['id' => $withdraw['userid']])->save(['balance_inr' => array('exp', "balance_inr+{$withdraw['tkmoney']}")]);
                $ymoney = $memberInfo['balance_inr'];
            }
            if (!$res) {
                M()->rollback();
                UserLogService::HTwrite(3, '退款操作失败', '退款操作失败');
                $this->ajaxReturn(['status' => 0, 'msg' => '退款失败1']);
            }
    
            //2,记录流水订单号
            $arrayField = array(
                "userid"     => $withdraw['userid'],
                "ymoney"     => $ymoney,
                "money"      => $withdraw['tkmoney'],
                "gmoney"     => $ymoney + $withdraw['tkmoney'],
                "datetime"   => date("Y-m-d H:i:s"),
                "tongdao"    => 0,
                "transid"    => $withdraw['orderid'],
                "orderid"    => $withdraw['out_trade_no'],
                "lx"         => 21,
                'contentstr' => '冲正退款',
            );
            $Moneychange = D("Moneychange");
            $tablename = $Moneychange -> getRealTableName($arrayField['datetime']);
            $res = $Moneychange->table($tablename)->add($arrayField);
            if (!$res) {
                M()->rollback();
                UserLogService::HTwrite(3, '退款操作失败', '退款操作失败');
                $this->ajaxReturn(['status' => 0, 'msg' => '退款失败2']);
            }
            //退回手续费
            if ($withdraw['df_charge_type'] && $withdraw['sxfmoney']>0) {
                if(getPaytypeCurrency($withdraw['paytype']) ==='PHP'){        //菲律宾余额
                    $res1 = $Member->where(['id' => $withdraw['userid']])->save(['balance_php' => array('exp', "balance_php+{$withdraw['sxfmoney']}")]);
                    $ymoney = $memberInfo['balance_php'];
                }
                if(getPaytypeCurrency($withdraw['paytype']) ==='INR'){        //菲律宾余额
                    $res1 = $Member->where(['id' => $withdraw['userid']])->save(['balance_inr' => array('exp', "balance_inr+{$withdraw['sxfmoney']}")]);
                    $ymoney = $memberInfo['balance_inr'];
                }
                if (!$res1) {
                    M()->rollback();
                    UserLogService::HTwrite(3, '退款操作失败', '退款操作失败');
                    $this->ajaxReturn(['status' => 0, 'msg' => '退款失败3']);
                }
                $chargeField = array(
                    "userid"     => $withdraw['userid'],
                    "ymoney"     => $ymoney + $withdraw['tkmoney'],
                    "money"      => $withdraw['sxfmoney'],
                    "gmoney"     => $ymoney + $withdraw['tkmoney'] + $withdraw['sxfmoney'],
                    "datetime"   => date("Y-m-d H:i:s"),
                    "tongdao"    => 0,
                    "transid"    => $withdraw['orderid'],
                    "orderid"    => $withdraw['out_trade_no'],
                    "lx"         => 21,
                    'contentstr' => ' 冲正退款',
                );
                    
                $Moneychange = D("Moneychange");
                $tablename = $Moneychange -> getRealTableName($chargeField['datetime']);
                $res2 = $Moneychange->table($tablename)->add($chargeField);
                // $res = M('Moneychange')->add($chargeField);
                if (!$res2) {
                    M()->rollback();
                    UserLogService::HTwrite(3, '退款操作失败', '退款操作失败');
                    $this->ajaxReturn(['status' => 0, 'msg' => '退款失败4']);
                }
            }
            $data['status']     = 4;
            $data["cldatetime"] = date("Y-m-d H:i:s");
            $data["memo"] = '冲正退款 - ' . date('Y-m-d H:i:s') . ';' . $withdraw['memo'];
            $res5 = $Wttklistmodel->table($WttklistTableName)->where($where)->save($data);
            
            //更新回调信息
            $withdraw['status'] = 4;
            $redis->set($withdraw['orderid'],json_encode($withdraw));
            
            if ($res5 === false) {
                M()->rollback();
                UserLogService::HTwrite(3, '退款操作失败', '退款操作失败');
                $this->ajaxReturn(['status' => 0, 'msg' => '退款失败5']);
            } else {
                M()->commit();
                UserLogService::HTwrite(3, '退款操作成功', '退款操作成功');
                $this->ajaxReturn(['status' => 1,'msg' => '退款成功!']);
            }
        } else {
            $this->assign('verifysms', $verifysms);
            $this->assign('verifyGoogle', $verifyGoogle);
            $this->assign('auth_type', $verifyGoogle ? 1 : 0);
            
            $this->assign('info', $withdraw);
            $this->display();
        }
    }

    //一键补发通知
    public function notifyAllOrder()
    {
        UserLogService::HTwrite(3, '一键补发通知', '一键补发通知');
        if (IS_POST) {
            $ids = I('request.ids');
            if (!$ids) {
                $this->ajaxReturn(['status' => 0, 'msg' => "请选择订单！"]);
            }
            $ids_array = explode(',', $ids);
            if (empty($ids_array)) {
                $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！"]);
            } else {
                if (count($ids_array) > 1) {
                    
                    $redis = redis_connect();
                    
                    $Wttklistmodel = D('Wttklist');
                    $tables = $Wttklistmodel->getTables();
                    $maps['notifyurl'] = ['neq', ''];
                    $maps['status'] = ['in', ["2", "4"]];
                    
                    foreach ($ids_array as $idv){
                        if($idv>0){
                            $maps['id'] = ['eq', $idv];
                            foreach ($tables as $v){
                                $list = $Wttklistmodel->table($v)->field('orderid')->where($maps)->find();
                                // echo  $Wttklistmodel->table($v)->getLastSql();
                                if(!empty($list)){
                                    $re = $redis->rPush('notifyList_DF_BuFa', $list['orderid']);
                                    var_dump($re);
                                    break;
                                }
                            }
                            
                        }
                    }
                    $this->ajaxReturn(['status' => 'seccess']);
                }
            }
        }
        $this->ajaxReturn(['status' => 'error']);
    }


    //提交代申请
    public function submitDf()
    {
        UserLogService::HTwrite(3, '提交代申请', '提交代申请');
        $uid = session('admin_auth')['uid'];
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
            $uid               = session('admin_auth')['uid'];
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
                    $google_secret_key = M('Admin')->where(['id' => $uid])->getField('google_secret_key');
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
                        session('send', null);
                    }
                }
            }
            session('admin_submit_df', 1);
            $_REQUEST = [
                'code' => $channe_code,
                'id'   => $ids,
                'opt'  => 'exec',
            ];
            UserLogService::HTwrite(3, '提交代申请', '提交代申请' . json_encode($_REQUEST));
            return R('Payment/Index/index');
        } else {
            $ids = I('request.ids');
            if (!$ids) {
                $this->error('缺少参数');
            }
            $channe_code = I('request.channe_code', '');
            $uid         = session('admin_auth')['uid'];
            $user        = M('Admin')->where(['id' => $uid])->find();
            $this->assign('mobile', $user['mobile']);
            $this->assign('ids', $ids);
            $this->assign('channe_code', $channe_code);
            $this->assign('verifysms', $verifysms);
            $this->assign('verifyGoogle', $verifyGoogle);
            $this->assign('auth_type', $verifyGoogle ? 1 : 0);
            $this->display();
        }
    }

    public function submitnotify () {
        if (IS_POST) {
            $orderid = I('request.id');
            if($orderid){
                Automatic_Notify($orderid);
                $this->ajaxReturn(['status' => 'seccess']);
            }
            $this->ajaxReturn(['status' => 'error']);
        }
    }
    
    public function refundAll() {
        $uid = session('admin_auth')['uid'];
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
                    $google_secret_key = M('Admin')->where(['id' => $uid])->getField('google_secret_key');
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
                        session('send', null);
                    }
                }
            }
            
            $ids = I('post.id', '');
            UserLogService::HTwrite(3, '批量驳回代付', '批量驳回：'. $ids);
            $ids = explode(',', trim($ids, ','));
            if(empty($ids)){
                UserLogService::HTwrite(3, '批量驳回代付', '批量驳回代付,失败：缺少订单ID');
                $this->ajaxReturn(['status' => 0, 'msg' => '缺少订单ID']);
            }
            $tableName = 'Wttklist';
            $Wttklist = D('Wttklist');
            $tables = $Wttklist->getTables();
            
            $success = $fail = 0;
            foreach ($ids as $k => $iv) {
                M()->startTrans();
                try {
                    if (intval($iv)) {
                        foreach ($tables as $v){
                            $withdraw = $Wttklist->table($v)->where('id='.$iv)->find();
                            if(!empty($withdraw)){
                                $tableName = $v;
                                break;
                            }
                        }
                        $orderid = $withdraw['orderid'];
                        if($withdraw['status']!=0){
                            UserLogService::HTwrite(3, '批量驳回代付', $iv . '-驳回代付,失败。状态:' . $withdraw['status']);
                            $fail++;
                            continue;
                        }
                        
                        //设置redis标签，防止重复执行
                        $redis = $this->redis_connect();
                        if($redis->get('refund_' . $orderid)){
                            UserLogService::HTwrite(3, '批量驳回代付', $iv . '-驳回操作，重复操作');
                            $fail++;
                            continue;
                        }
                        $redis->set('refund_' . $orderid,'1',60);
                        
                        $memo = '驳回代付-' . date('Y-m-d H:i:s') . ';' . $withdraw['memo'];
                        
                        $Rejsct = Reject(['id' => $iv, 'status' => '6','message'=> '驳回代付']);
                        if($Rejsct){
                            M()->commit();
                            UserLogService::HTwrite(3, '批量驳回代付', $iv . '-驳回代付,成功。');
                            Automatic_Notify($orderid);
                            $success++;
                            continue;
                        }else{
                            M()->rollback();
                            UserLogService::HTwrite(3, '批量驳回代付', $iv . '-驳回代付,失败。数据回滚');
                            $fail++;
                            continue;
                        }
                    } else {
                        M()->rollback();
                        $fail++;
                        continue;
                    }
                } catch (\Exception $e) {
                    M()->rollback();
                    $fail++;
                    continue;
                }
            }
            if ($success > 0) {
                UserLogService::HTwrite(3, '成功批量驳回代付申请', '成功驳回：' . $success . '，失败：' . $fail);
                $this->ajaxReturn(['status' => 1, 'msg' => '成功驳回：' . $success . '，失败：' . $fail]);
            } else {
                UserLogService::HTwrite(3, '批量驳回代付失败', '成功批量驳回代付失败');
                $this->ajaxReturn(['status' => 0, 'msg' => '驳回失败!']);
            }
        } else {
            $ids = I('request.ids');
            if (!$ids) {
                $this->error('缺少参数');
            }
            
            $uid         = session('admin_auth')['uid'];
            $user        = M('Admin')->where(['id' => $uid])->find();
            $this->assign('mobile', $user['mobile']);
            $this->assign('ids', $ids);
            $this->assign('verifysms', $verifysms);
            $this->assign('verifyGoogle', $verifyGoogle);
            $this->assign('auth_type', $verifyGoogle ? 1 : 0);
            $this->display();
        }
    }

    // /**
    //  * 发送提交代付验证码信息
    //  */
    // public function submitDfSend()
    // {
    //     $uid               = session('admin_auth')['uid'];
    //     $user = M('Admin')->where(['id'=>$uid])->find();
    //     $res    = $this->send('submitDfSend', $user['mobile'], '提交代付');
    //     $this->ajaxReturn(['status' => $res['code']]);
    // }

    // /**
    //  * 提款设置验证码信息
    //  */
    // public function tkconfigSend()
    // {
    //     $uid               = session('admin_auth')['uid'];
    //     $user = M('Admin')->where(['id'=>$uid])->find();
    //     $res = $this->send('tkconfig', $user['mobile'] ,'提款设置');
    //     $this->ajaxReturn(['status' => $res['code']]);
    // }

    /**
     * 导出提款记录
     */
    public function exportpayment()
    {
        $where    = array();
        $type     = I('get.type', '');
        $ids     = I('get.id', '');
        $ids = substr($ids, 0, -1);
        if ($ids) {
            $where['id'] = array('in', $ids);
        }
        $Wttklist = D('Wttklist');
        $tables = $Wttklist->getTables();
        foreach ($tables as $v){
            $data = $Wttklist->table($v)->where($where)->select();
            if(!empty($data)){
                break;
            }
        }
//        $data  = M('Wttklist')->where($where)->select();

        if($type==1){
            $filename = '员工信息'.date("mdHis",time());
            $title = array('姓名(*必填)', '身份证(*必填)', '手机号(*必填)', '银行卡号(*必填)', '银行卡号2(*必填)');
            $numberField = ['bankfullname', 'idnumber', 'phone', 'banknumber', 'k', 'memberid', 'position', 'sqdatetime'];
            foreach ($data as $item) {
                $list[] = array(
                    'bankfullname' => $item['bankfullname'],
                    'idnumber'   => '',
                    'phone'   => $item['phone'],
                    'banknumber'   => ' ' .$item['banknumber'].' ',
                    'k'   => ' ',
                );
            }
            exportCsv($list, $title);
            // exportexcel($list, $title,$numberField,$filename,20);
        }elseif($type==2){
            $filename = '工资'.date("mdHis",time());
            $title = array('姓名', '基本工资', '工龄薪', '项目津贴', '全勤奖', '车补', '话补', '饭补', '补发项', '其他扣款', '病事假', '应发工资', '社保扣款', '公积金扣款', '代扣税', '实发工资');
            $numberField = ['bankfullname', 'money'];
            foreach ($data as $item) {
                $list[] = array(
                    'bankfullname' => $item['bankfullname'],
                    'money'        => $item['money'],
                );
            }
            exportCsv($list, $title);
            // exportexcel($list, $title,$numberField,$filename,10);
        }
    }

}
