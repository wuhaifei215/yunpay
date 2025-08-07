<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-04-02
 * Time: 23:01
 */

namespace Admin\Controller;

use Org\Net\UserLogService;
use Pay\Model\ComplaintsDepositModel;
use Think\Page;

/**
 * 用户管理控制
 * Class UserController
 * @package Admin\Controller
 */
class UserController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        //通道
        $channels = M('Channel')
            ->where(['status' => 1])
            ->field('id,code,title,paytype,status')
            ->select();
        $this->assign('channels', json_encode($channels));
        $this->assign('channellist', $channels);
    }

    /**
     * 用户列表
     */
    public function index()
    {
        UserLogService::HTwrite(1, '查看用户列表', '查看用户列表');
        $groupid = I('get.groupid', '');
        $username = I("get.username", '', 'trim');
        $status = I("get.status");
        $authorized = I("get.authorized");
        $parentid = I('get.parentid');
        $regdatetime = I('get.regdatetime');
        $agency_id = I('get.agency_id');
        $country_id = I('get.country_id');
        

        if ($groupid != '') {
            $where['groupid'] = $groupid != 1 ? $groupid : ['neq', '4'];
        }
        $this->assign('groupid', $groupid);
        if (!empty($username) && !is_numeric($username)) {
            $where['username'] = ['like', "%" . $username . "%"];
        } elseif (intval($username) - 10000 > 0) {
            $where['id'] = intval($username) - 10000;
        }
        if ($country_id != '') {
            $where['country_id'] = $country_id;
        }
        $this->assign('country_id', $country_id);
        
        if ($agency_id != '') {
            $where['agency_id'] = $agency_id;
        }
        $this->assign('agency_id', $agency_id);
        
        if ($status != '') {
            $where['status'] = $status;
        }
        $this->assign('status', $status);
        if ($authorized != '') {
            $where['authorized'] = $authorized;
        }
        $this->assign('authorized', $authorized);
        if (!empty($parentid) && !is_numeric($parentid)) {
            $User = M("Member");
            $pid = $User->where(['username' => $parentid])->getField("id");
            $where['parentid'] = $pid;
        } elseif ($parentid) {
            $where['parentid'] = $parentid;
        }
        $this->assign('parentid', $parentid);
        if ($regdatetime) {
            list($starttime, $endtime) = explode('|', $regdatetime);
            $where['regdatetime'] = ["between", [strtotime($starttime), strtotime($endtime)]];
        }
        $this->assign('regdatetime', $regdatetime);

        $count = M('Member')->where($where)->count();
        $size = 50;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }

        $page = new Page($count, $rows);
        $list = M('Member')
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('regdatetime desc')
            ->select();

        foreach ($list as $k => $v) {
            $list[$k]['groupname'] = $this->groupId[$v['groupid']];
            $deposit = ComplaintsDepositModel::getComplaintsDeposit($v['id']);
            $list[$k]['complaintsDeposit'] = number_format((double)$deposit['complaintsDeposit'], 2, '.', '');
            $list[$k]['complaintsDepositPaused'] = number_format((double)$deposit['complaintsDepositPaused'], 2, '.', '');

            $pay = M('Channel')->alias('a')->join('pay_product_user ON a.id=pay_product_user.channel')->field('a.title')->where(['pay_product_user.userid' => $v['id'],['pay_product_user.status' => 1]])->select();
            $list[$k]['tongdao'] = $pay;
            $dfpay = M('UserPayForAnother')->alias('a')->join('pay_pay_for_another ON a.channel=pay_pay_for_another.id')->field('pay_pay_for_another.title')->where(['a.userid' => $v['id'],'pay_pay_for_another.status'=>1])->select();
            $list[$k]['dftongdao'] = $dfpay;
            $agency = M('Agency')->where(['id' => $v['agency_id']])->find();
            $list[$k]['agency'] = $agency['username'];
        }

        $df_list = M('PayForAnother')->select();
        foreach ($df_list as $k => $v) {
            if($v['open_query']==1){
                $channel_lists[] = $v;
            }
        }
        $this->assign("channel_lists", $channel_lists);
        
        $agency = M('Agency')->select();
        $this->assign('agency', $agency);

        $country = M('Country') ->where(['status'=>1])->select();
        $this->assign('country', $country);
        $this->assign('rows', $rows);
        $this->assign("list", $list);
        $this->assign('page', $page->show());
        //取消令牌
        C('TOKEN_ON', false);
        $this->display();
    }

    /**
     * 邀请码
     */
    public function invitecode()
    {
        UserLogService::HTwrite(1, '查看邀请码列表', '查看邀请码列表');
        $invitecode = I("get.invitecode");
        $fbusername = I("get.fbusername");
        $syusername = I("get.syusername");
        $regtype = I("get.groupid");
        $status = I("get.status");
        if (!empty($invitecode)) {
            $where['invitecode'] = ["like", "%" . $invitecode . "%"];
        }
        $this->assign('invitecode', $invitecode);
        if (!empty($fbusername)) {
            $fbusernameid = M("Member")->where("username = '" . $fbusername . "'")->getField("id");
            $where['fmusernameid'] = $fbusernameid;
        }
        $this->assign('fbusername', $fbusername);
        if (!empty($syusername)) {
            $syusernameid = M("Member")->where("username = '" . $syusername . "'")->getField("id");
            $where['syusernameid'] = $syusernameid;
        }
        $this->assign('syusername', $syusername);
        if (!empty($regtype)) {
            $where['regtype'] = $regtype;
        }
        $this->assign('groupid', $regtype);
        $regdatetime = urldecode(I("request.regdatetime"));
        if ($regdatetime) {
            list($cstime, $cetime) = explode('|', $regdatetime);
            $where['fbdatetime'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
        }
        $this->assign('regdatetime', $regdatetime);
        if (!empty($status)) {
            $where['status'] = $status;
        }
        $this->assign('status', $status);
        $count = M('Invitecode')->where($where)->count();
        $size = 15;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }
        $page = new Page($count, $rows);
        $list = M('Invitecode')
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('id desc')
            ->select();

        $Admin = M('Admin');
        foreach ($list as $k => $v) {
            if ($v['is_admin']) {
                $username = $Admin->where(['id' => $v['fmusernameid']])->getField('username');
                $list[$k]['fmusernameid'] = $username;
            } else {
                $list[$k]['fmusernameid'] = getusername($v['fmusernameid']);
            }
            $list[$k]['is_admin'] = $v['is_admin'] ? '管理员' : '代理商';
            $list[$k]['groupname'] = $this->groupId[$v['regtype']];
        }
        $this->assign('rows', $rows);
        $this->assign("list", $list);
        $this->assign('page', $page->show());
        //取消令牌
        C('TOKEN_ON', false);
        $this->display();
    }

    public function setInvite()
    {
        $data = M("Inviteconfig")->find();
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 保存邀请码设置
     */
    public function saveInviteConfig()
    {
        if (IS_POST) {
            UserLogService::HTwrite(3, '保存邀请码设置', '保存邀请码设置');
            $Inviteconfig = M("Inviteconfig");
            $_formdata['invitezt'] = I('post.invitezt');
            $_formdata['invitetype2number'] = I('post.invitetype2number');
            $_formdata['invitetype2ff'] = I('post.invitetype2ff');
            $_formdata['invitetype5number'] = I('post.invitetype5number');
            $_formdata['invitetype5ff'] = I('post.invitetype5ff');
            $_formdata['invitetype6number'] = I('post.invitetype6number');
            $_formdata['invitetype6ff'] = I('post.invitetype6ff');
            $result = $Inviteconfig->where(array('id' => I('post.id')))->save($_formdata);
            if ($result) UserLogService::HTwrite(3, '保存邀请码设置成功', '保存邀请码设置成功');
            $this->ajaxReturn(['status' => $result]);
        }
    }

    /**
     * 添加邀请码
     */
    public function addInvite()
    {
        $invitecode = $this->createInvitecode();
        $this->assign('invitecode', $invitecode);
        $this->assign('datetime', date('Y-m-d H:i:s', time() + 86400));
        $this->display();
    }

    /**
     * 邀请码
     * @return string
     */
    private function createInvitecode()
    {
        $invitecodestr = random_str(C('INVITECODE')); //生成邀请码的长度在Application/Commom/Conf/config.php中修改
        $Invitecode = M("Invitecode");
        $id = $Invitecode->where("invitecode = '" . $invitecodestr . "'")->getField("id");
        if (!$id) {
            return $invitecodestr;
        } else {
            $this->createInvitecode();
        }
    }

    /**
     * 添加邀请码
     */
    public function addInvitecode()
    {
        if (IS_POST) {
            UserLogService::HTwrite(2, '添加邀请码', '添加邀请码');
            $invitecode = I('post.invitecode');
            $yxdatetime = I('post.yxdatetime');
            $regtype = I('post.regtype');
            $Invitecode = M("Invitecode");

            $_formdata = array(
                'invitecode' => $invitecode,
                'yxdatetime' => strtotime($yxdatetime),
                'regtype' => $regtype,
                'fmusernameid' => session('admin_auth.uid'),
                'inviteconfigzt' => 1,
                'fbdatetime' => time(),
                'is_admin' => 1,
            );
            $result = $Invitecode->add($_formdata);
            if ($result) UserLogService::HTwrite(2, '添加邀请码成功', '添加邀请码成功');
            $this->ajaxReturn(['status' => $result]);
        }
    }

    /**
     * 删除邀请码
     */
    public function delInvitecode()
    {
        if (IS_POST) {
            UserLogService::HTwrite(4, '删除邀请码', '删除邀请码');
            $id = I('post.id', 0, 'intval');
            $res = M('Invitecode')->where(['id' => $id])->delete();
            if ($res) UserLogService::HTwrite(4, '删除邀请码成功', '删除邀请码成功');
            $this->ajaxReturn(['status' => $res]);
        }
    }

    public function getRandstr()
    {
        echo random_str();
    }

    /**
     * 删除用户
     */
    public function delUser()
    {
        if (IS_POST) {
            UserLogService::HTwrite(4, '删除用户', '删除用户');
            $id = I('post.uid', 0, 'intval');
            $res = M('Member')->where(['id' => $id])->delete();
            if ($res) UserLogService::HTwrite(4, '删除用户成功', '删除用户（' . $id . '）成功');
            $this->ajaxReturn(['status' => $res]);
        }
    }

    public function renzheng()
    {
        $userid = I("post.userid", 0, 'intval');
        $Userverifyinfo = M("Userverifyinfo");
        $list = $Userverifyinfo->where(["userid" => $userid])->find();
        $this->ajaxReturn($list, "json");
    }

    /**
     * 保存认证
     */
    public function editAuthoize()
    {
        if (IS_POST) {
            UserLogService::HTwrite(3, '保存用户认证', '保存用户认证');
            $rows = I('post.u');
            $userid = intval($rows['userid']);
            unset($rows['userid']);
            $res = M('Member')->where(['id' => $userid])->save($rows);
            if ($res) UserLogService::HTwrite(3, '保存用户认证成功', '保存用户(' . $userid . ')认证成功');
            $this->ajaxReturn(['status' => $res]);
        }
    }

    /**
     * 修改密码
     */
    public function editPassword()
    {
        if (IS_POST) {
            UserLogService::HTwrite(3, '修改用户密码', '修改用户密码');
            $userid = I("post.userid", 0, 'intval');
            $salt = I("post.salt");
            $groupid = I('post.groupid');
            $u = I('post.u');
            if ($u['password']) {
                $data['password'] = md5($u['password'] . ($groupid < 4 ? C('DATA_AUTH_KEY') : $salt));
            }
            if ($u['paypassword']) {
                $data['paypassword'] = md5($u['paypassword']);
            }
            $res = M('Member')->where(["id" => $userid])->save($data);
            if ($res) UserLogService::HTwrite(3, '修改用户密码成功', '修改用户(' . $userid . ')密码成功');
            $this->ajaxReturn(['status' => $res]);
        } else {
            $userid = I('get.uid', 0, 'intval');
            if ($userid) {
                $data = M('Member')
                    ->where(['id' => $userid])->find();
                $this->assign('u', $data);
            }

            $this->display();
        }
    }

    /**
     * 用户资金操作（菲律宾）
     */
    public function usermoneyPHP()
    {
        UserLogService::HTwrite(1, '查看用户资金操作', '查看用户资金操作');
        $userid = I("get.userid", 0, 'intval');
        $info = M("Member")->where(["id" => $userid])->find();
        $this->assign('info', $info);
        $this->display();
    }

    /**
     * 用户资金操作（越南）
     */
    public function usermoneyINR()
    {
        UserLogService::HTwrite(1, '查看用户资金操作', '查看用户资金操作');
        $userid = I("get.userid", 0, 'intval');
        $info = M("Member")->where(["id" => $userid])->find();
        $this->assign('info', $info);
        $this->display();
    }

    /**
     * 增加、减少余额
     */
    public function incrMoney()
    {
        UserLogService::HTwrite(3, '修改用户资金', '修改用户资金操作');
        $uid = session('admin_auth')['uid'];
        $verifysms = 0; //是否可以短信验证
        $sms_is_open = smsStatus();
        if ($sms_is_open) {
            $adminMobileBind = adminMobileBind($uid);
            if ($adminMobileBind) {
                $verifysms = 1;
            }
        }
        $currency = I("request.currency");
        $this->assign('currency', $currency);
        //是否可以谷歌安全码验证
        $verifyGoogle = adminGoogleBind($uid);
        if (IS_POST) {
            //开启事物
            M()->startTrans();
            $userid = I("post.uid", 0, 'intval');
            $cztype = I("post.cztype");
            $bgmoney = I("post.bgmoney", 0, 'float');
            $contentstr = I("post.memo", "");
            $auth_type = I('post.auth_type', 0, 'intval');
            if ($bgmoney <= 0) {
                $this->ajaxReturn(['status' => 0, 'msg' => "变动金额必须是正数！"]);
            }
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
            if ($verifyGoogle && $auth_type == 1) {
                $res = check_auth_error($uid, 5);
                if (!$res['status']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => $res['msg']]);
                }
                //谷歌安全码验证
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
            } elseif ($verifysms && $auth_type == 0) {
                $res = check_auth_error($uid, 3);
                if (!$res['status']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => $res['msg']]);
                }
                //短信验证码
                $code = I('post.code');
                if (!$code) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "短信验证码不能为空！"]);
                } else {
                    if (session('send.adjustUserMoneySend') != $code || !$this->checkSessionTime('adjustUserMoneySend', $code)) {
                        log_auth_error($uid, 3);
                        $this->ajaxReturn(['status' => 0, 'msg' => '验证码错误']);
                    } else {
                        clear_auth_error($uid, 3);
                        session('send', null);
                    }
                }
            }
            $date = I("post.date");
            if (!$date) {
                $date = date('Y-m-d');
            }
            if (strtotime($date) > time()) {
                $this->ajaxReturn(['status' => 0, 'msg' => '冲正日期不正确']);
            }
            $info = M("Member")->where(["id" => $userid])->lock(true)->find();
            if (empty($info)) {
                $this->ajaxReturn(['status' => 0, 'msg' => '用户不存在']);
            }
            if($currency === 'PHP'){
                if (($info['balance_php'] - $bgmoney) < 0 && $cztype == 4) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "账上余额不足" . $bgmoney . "元，不能完成减金操作"]);
                }
                if ($cztype == 3) {
                    $data["balance_php"] = array('exp', "balance_php+" . $bgmoney);
                    $gmoney = $info['balance_php'] + $bgmoney;
                    UserLogService::HTwrite(3, '增加用户资金', '增加用户(' . $userid . ')菲律宾可用余额');
                } elseif ($cztype == 4) {
                    $data["balance_php"] = array('exp', "balance_php-" . $bgmoney);
                    $where['balance_php'] = array('egt', $bgmoney);
                    $gmoney = $info['balance_php'] - $bgmoney;
                    UserLogService::HTwrite(3, '减少用户资金', '减少用户(' . $userid . ')菲律宾可用余额');
                }
                $ymoney = $info['balance_php'];
                $paytype = 1;
            }
            if($currency === 'INR'){
                if (($info['balance_inr'] - $bgmoney) < 0 && $cztype == 4) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "账上余额不足" . $bgmoney . "元，不能完成减金操作"]);
                }
                if ($cztype == 3) {
                    $data["balance_inr"] = array('exp', "balance_inr+" . $bgmoney);
                    $gmoney = $info['balance_inr'] + $bgmoney;
                    UserLogService::HTwrite(3, '增加用户资金', '增加用户(' . $userid . ')越南可用余额');
                } elseif ($cztype == 4) {
                    $data["balance_inr"] = array('exp', "balance_inr-" . $bgmoney);
                    $where['balance_inr'] = array('egt', $bgmoney);
                    $gmoney = $info['balance_inr'] - $bgmoney;
                    UserLogService::HTwrite(3, '减少用户资金', '减少用户(' . $userid . ')越南可用余额');
                }
                $ymoney = $info['balance_inr'];
                $paytype = 4;
            }

            $where['id'] = $userid;
            $res1 = M('Member')->where($where)->save($data);
            $orderid = 'SD' . date("Y-m-d H:i:s");
            $arrayField = array(
                "userid" => $userid,
                'ymoney' => $ymoney,
                "money" => $bgmoney,
                "gmoney" => $gmoney,
                "datetime" => date("Y-m-d H:i:s"),
                "tongdao" => '',
                "transid" => $orderid,
                "orderid" => $orderid,
                "paytype" => $paytype,
                "lx" => $cztype, // 增减类型
                "contentstr" => $contentstr . '【冲正周期:' . $date . '】',
            );
            $res2 = moneychangeadd($arrayField);
            //冲正订单
            $arrayRedo = array(
                'user_id' => $userid,
                'admin_id' => session('admin_auth')['uid'],
                'money' => $bgmoney,
                'type' => $cztype == 3 ? 1 : 2,
                'remark' => $arrayField['contentstr'],
                'date' => $date,
                'ctime' => time(),
                "paytype" => $paytype,
            );
            $res3 = M('redo_order')->add($arrayRedo);
            if ($res1 && $res2 && $res3) {
                M()->commit();
                UserLogService::HTwrite(3, '修改用户资金成功', '修改用户(' . $userid . ')资金操作成功');
                $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
            } else {
                M()->rollback();
                UserLogService::HTwrite(3, '修改用户资金失败', '修改用户(' . $userid . ')资金操作失败');
                $this->ajaxReturn(['status' => 0, 'msg' => '操作失败']);
            }
        } else {
            $userid = I("request.uid");
            $date = I("request.date");
            $info = M("Member")->where(["id" => $userid])->find();
            if($currency === 'PHP'){
                $info['balance'] = $info['balance_php'];
                $info['blockedbalance'] = $info['blockedbalance_php'];
            }
            if($currency === 'INR'){
                $info['balance'] = $info['balance_inr'];
                $info['blockedbalance'] = $info['blockedbalance_inr'];
            }
            $uid = session('admin_auth')['uid'];
            $user = M('Admin')->where(['id' => $uid])->find();
            $this->assign('mobile', $user['mobile']);
            $this->assign('info', $info);
            $this->assign('date', $date);
            $this->assign('verifysms', $verifysms);
            $this->assign('verifyGoogle', $verifyGoogle);
            $this->assign('auth_type', $verifyGoogle ? 1 : 0);
            $this->display();
        }
    }

    /**
     * 冻结、解冻余额
     */
    public function frozenMoney()
    {
        if (IS_POST) {
            UserLogService::HTwrite(3, '冻结用户余额', '冻结用户余额');
            //开启事物
            M()->startTrans();
            $userid = I("post.uid", 0, 'intval');
            $currency = I("post.currency");
            $cztype = I("post.cztype", 0, 'intval');
            $bgmoney = I("post.bgmoney", 0, 'float');
            $contentstr = I("post.memo", "", 'string,strip_tags,htmlspecialchars');
            $unfreeze_time = I("post.unfreeze_time", "");
            $info = M("Member")->where(["id" => $userid])->lock(true)->find();
            if (empty($info)) {
                $this->ajaxReturn(['status' => 0, 'msg' => '用户不存在']);
            }
            if ($bgmoney <= 0) {
                $this->ajaxReturn(['status' => 0, 'msg' => "金额需要大于0"]);
            }
            if (($info['blockedbalance'] - $bgmoney) < 0 && $cztype == 8) {
                $this->ajaxReturn(['status' => 0, 'msg' => "账上冻结余额不足" . $bgmoney . "元，不能完成减金操作"]);
            }
            //冻结
            if ($cztype == 7 && ($info['balance'] - $bgmoney) < 0) {
                $this->ajaxReturn(['status' => 0, 'msg' => "账上余额不足" . $bgmoney . "元，不能完成冻结操作"]);
            }
            if ($unfreeze_time != '') {
                $unfreeze_time = strtotime($unfreeze_time);
                if ($unfreeze_time <= time()) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "解冻时间无效"]);
                }
            }
            if ($cztype == 7) {
                $data["balance"] = array('exp', "balance-" . $bgmoney);
                $data["blockedbalance"] = array('exp', "blockedbalance+" . $bgmoney);
                $where['balance'] = ['egt', $bgmoney];
                $gmoney = $info['balance'] - $bgmoney;
            } elseif ($cztype == 8) {
                $data["balance"] = array('exp', "balance+" . $bgmoney);
                $data["blockedbalance"] = array('exp', "blockedbalance-" . $bgmoney);
                $where['blockedbalance'] = ['egt', $bgmoney];
                $gmoney = $info['balance'] + $bgmoney;
            }
            $where['id'] = $userid;
            $res1 = M('Member')->where($where)->save($data);
            if ($cztype == 7) {
                //加入解冻订单
                $autoUnfreezeArray = array(
                    'user_id' => $userid,
                    'freeze_money' => $bgmoney,
                    'unfreeze_time' => $unfreeze_time,
                    'real_unfreeze_time' => 0,
                    'is_pause' => 0,
                    'status' => 0,
                    'create_at' => time(),
                    'update_at' => time(),
                );
                $res2 = M('auto_unfrozen_order')->add($autoUnfreezeArray);
            } else {
                $res2 = true;
            }
            $arrayField = array(
                "userid" => $userid,
                "ymoney" => $info['balance'],
                "money" => $bgmoney,
                "gmoney" => $gmoney,
                "datetime" => date("Y-m-d H:i:s"),
                "tongdao" => '',
                "transid" => "",
                "lx" => $cztype, // 增减类型
                "contentstr" => $contentstr,
            );
            if ($cztype == 7 && $res2 > 0) {
                $arrayField['transid'] = $res2;
            } else {
                $arrayField['transid'] = '';
            }
            $res3 = moneychangeadd($arrayField);
            if ($res1 && $res2 && $res3) {
                M()->commit();
                UserLogService::HTwrite(3, '冻结用户余额操作成功', '冻结用户(' . $userid . ')余额操作成功');
                $this->ajaxReturn(['status' => 1, 'msg' => "操作成功！"]);
            } else {
                M()->rollback();
                UserLogService::HTwrite(3, '冻结用户余额操作失败', '冻结用户(' . $userid . ')余额操作失败');
                $this->ajaxReturn(['status' => 0, 'msg' => "操作失败！"]);
            }
        } else {
            $userid = I("request.uid");
            $info = M("Member")->where(["id" => $userid])->find();
            $this->assign('info', $info);
            $this->display();
        }
    }

    /**
     * 手动去管理定时解冻任务
     * author: feng
     * create: 2017/10/21 15:43
     */
    public function frozenTiming()
    {
        //通道
        UserLogService::HTwrite(1, '查看定时解冻任务列表', '查看定时解冻任务列表');
        $where = array();
        $currency = I("post.currency");
        $memberid = I("get.uid", 0, 'intval');
        if ($memberid) {
            $where['userid'] = array('eq', $memberid);
        } else {
            return;
        }
        $this->assign('uid', $memberid);
        $orderid = I("get.orderid");
        if ($orderid) {
            $where['orderid'] = array('eq', $orderid);
        }
        $this->assign('orderid', $orderid);
        $createtime = urldecode(I("request.createtime"));
        if ($createtime) {
            list($cstime, $cetime) = explode('|', $createtime);
            $where['createtime'] = ['between', [strtotime($cstime), strtotime($cetime ? $cetime : date('Y-m-d'))]];
        }
        $this->assign('createtime', $createtime);
        $count = M('blockedlog')->where($where)->count();
        $page = new Page($count, 15);
        $list = M('blockedlog')
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('status asc,id desc')
            ->select();
        $this->assign("list", $list);
        $this->assign("page", $page->show());
        C('TOKEN_ON', false);

        $this->display();
    }

    /**
     * 管理手动冻结资金
     * author: mapeijian
     * create: 2018/06/09 12:22
     */
    public function frozenOrder()
    {
        //通道
        UserLogService::HTwrite(1, '查看冻结资金列表', '查看管理手动冻结资金');
        $where = array();
        $currency = I("post.currency");
        $memberid = I("get.uid", 0, 'intval');
        if ($memberid) {
            $where['user_id'] = array('eq', $memberid);
        } else {
            return;
        }
        $this->assign("uid", $memberid);
        $createtime = urldecode(I("request.createtime"));
        if ($createtime) {
            list($cstime, $cetime) = explode('|', $createtime);
            $where['create_at'] = ['between', [strtotime($cstime), strtotime($cetime ? $cetime : date('Y-m-d'))]];
        }
        $this->assign("createtime", $createtime);
        $count = M('autoUnfrozenOrder')->where($where)->count();
        $page = new Page($count, 15);
        $list = M('autoUnfrozenOrder')
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('status asc,id desc')
            ->select();
        $this->assign("list", $list);
        $this->assign("page", $page->show());
        C('TOKEN_ON', false);

        $this->display();
    }

    /**
     * 解冻
     * author: feng
     * create: 2017/10/21 17:15
     */
    public function frozenHandle()
    {
        if (IS_POST) {
            UserLogService::HTwrite(3, '解冻资金', '解冻资金');
            $id = I('post.id', 0, 'intval');
            if (!$id) {
                $this->ajaxReturn(['status' => 0]);
            }

            $maps['status'] = array('eq', 0);
            $maps["id"] = $id;
            $blockData = M('blockedlog')->where($maps)->order('id asc')->find();
            if (!$blockData) {
                $this->ajaxReturn(['status' => 0, 'msg' => '不存在或已解冻']);
            }
            //开启事务
            $Model = M();
            $Model->startTrans();
            $info = M('Member')->where(['id' => $blockData['userid']])->lock(true)->find();

            if ($info['blockedbalance'] < $blockData["amount"]) {
                $this->ajaxReturn(['status' => 0, 'msg' => '冻结金额不足']);
            }
            $rows = array();
            $rows['balance'] = array('exp', "balance+{$blockData['amount']}");
            $rows['blockedbalance'] = array('exp', "blockedbalance-{$blockData['amount']}");
            //更新资金
            $upRes = $Model->table('pay_member')->where(['id' => $blockData['userid']])->save($rows);
            //更新状态
            $uplog = $Model->table('pay_blockedlog')->where(array('id' => $blockData['id'], 'status' => 0))->save(array('status' => 1));
            //增加记录
            $data = array();
            $data['userid'] = $blockData['userid'];
            $data['ymoney'] = $info['balance'];
            $data['money'] = $blockData['amount'];
            $data['gmoney'] = $info['balance'] + $blockData['amount'];
            $data['datetime'] = date("Y-m-d H:i:s");
            $data['tongdao'] = $blockData['pid'];
            $data['transid'] = $blockData['orderid']; //交易流水号
            $data['orderid'] = $blockData['orderid'];
            $data['lx'] = 8; //解冻
            $data['contentstr'] = "订单金额解冻";
            $change = $Model->table('pay_moneychange')->add($data);

            //提交事务
            if ($upRes && $uplog && $change) {
                $Model->commit();
                UserLogService::HTwrite(3, '解冻资金成功', '解冻用户（' . $blockData['userid'] . '）资金成功');
                $this->ajaxReturn(['status' => 1]);
            } else {
                $Model->rollback();
                UserLogService::HTwrite(3, '解冻资金失败', '解冻用户（' . $blockData['userid'] . '）资金失败');
            }
            $this->ajaxReturn(['status' => 0]);

        }
    }

    /**
     * 手动冻结金额解冻
     * author: mapeijian
     * create: 2018/06/09 13:45
     */
    public function unfreeze()
    {
        if (IS_POST) {
            UserLogService::HTwrite(3, '手动冻结金额解冻', '手动冻结金额解冻');
            $id = I('post.id', 0, 'intval');
            if (!$id) {
                $this->ajaxReturn(['status' => 0]);
            }

            $maps['status'] = array('eq', 0);
            $maps["id"] = $id;
            $blockData = M('autoUnfrozenOrder')->where($maps)->find();
            if (!$blockData) {
                $this->ajaxReturn(['status' => 0, 'msg' => '不存在或已解冻']);
            }
            //开启事务
            $Model = M();
            $Model->startTrans();
            $info = M('Member')->where(['id' => $blockData['user_id']])->lock(true)->find();

            if ($info['blockedbalance'] < $blockData["freeze_money"]) {
                $this->ajaxReturn(['status' => 0, 'msg' => '冻结金额不足']);
            }
            $rows = array();
            $rows['balance'] = array('exp', "balance+{$blockData['freeze_money']}");
            $rows['blockedbalance'] = array('exp', "blockedbalance-{$blockData['freeze_money']}");
            //更新资金
            $upRes = $Model->table('pay_member')->where(['id' => $blockData['user_id']])->save($rows);
            //更新状态
            $uplog = $Model->table('pay_auto_unfrozen_order')->where(array('id' => $blockData['id'], 'status' => 0))->save(array('status' => 1, 'real_unfreeze_time' => time()));
            //增加记录
            $data = array();
            $data['userid'] = $blockData['user_id'];
            $data['ymoney'] = $info['balance'];
            $data['money'] = $blockData['freeze_money'];
            $data['gmoney'] = $info['balance'] + $blockData['freeze_money'];
            $data['datetime'] = date("Y-m-d H:i:s");
            $data['tongdao'] = $blockData['pid'];
            $data['transid'] = $blockData['orderid']; //交易流水号
            $data['orderid'] = $blockData['orderid'];
            $data['lx'] = 8; //解冻
            $data['contentstr'] = "手动冻结金额解冻";
            $change = $Model->table('pay_moneychange')->add($data);

            //提交事务
            if ($upRes && $uplog && $change) {
                $Model->commit();
                UserLogService::HTwrite(3, '解冻 手动冻结金额成功', '解冻 用户（' . $blockData['user_id'] . '）手动冻结金额资金成功');
                $this->ajaxReturn(['status' => 1, 'msg' => '解冻成功']);
            } else {
                $Model->rollback();
                UserLogService::HTwrite(3, '解冻 手动冻结金额失败', '解冻 用户（' . $blockData['user_id'] . '）手动冻结金额资金失败');
            }
            $this->ajaxReturn(['status' => 0, 'msg' => '解冻失败']);
        }
    }

    /**
     * 手动冻结金额自动解冻任务开关
     * author: mapeijian
     * create: 2018/06/09 13:45
     */
    public function autoUnfreezeSwitch()
    {
        if (IS_POST) {
            UserLogService::HTwrite(3, '手动冻结金额自动解冻任务开关', '手动冻结金额自动解冻任务开关');
            $id = I('post.id', 0, 'intval');
            if (!$id) {
                $this->ajaxReturn(['status' => 0]);
            }
            $status = I('post.status', 0, 'intval');
            $maps["id"] = $id;
            $blockData = M('autoUnfrozenOrder')->where($maps)->find();
            if (!$blockData) {
                $this->ajaxReturn(['status' => 0, 'msg' => '不存在该冻结金额订单！']);
            }
            if ($blockData['status']) {
                $this->ajaxReturn(['status' => 0, 'msg' => '已解冻，不能进行此操作！']);
            }
            if (!$blockData['unfreeze_time']) {
                $this->ajaxReturn(['status' => 0, 'msg' => '改冻结订单未开启自动解冻！']);
            }
            if ($blockData['is_pause'] == $status) {
                if ($status == 0) {
                    $this->ajaxReturn(['status' => 0, 'msg' => '改解冻任务正常运行中，无需重复操作！']);
                } else {
                    $this->ajaxReturn(['status' => 0, 'msg' => '改解冻任务已暂停，无需重复操作！']);
                }
            }
            $maps['status'] = 0;
            $res = M('autoUnfrozenOrder')->where($maps)->setField('is_pause', $status);
            if ($res) {
                UserLogService::HTwrite(3, '手动冻结金额自动解冻任务开关修改成功', '手动冻结金额自动解冻任务开关修改成功');
                $this->ajaxReturn(['status' => 0, 'msg' => $status == 1 ? '暂停成功' : '开启成功']);
            } else {
                UserLogService::HTwrite(3, '手动冻结金额自动解冻任务开关修改失败', '手动冻结金额自动解冻任务开关修改失败');
                $this->ajaxReturn(['status' => 0, 'msg' => '操作失败！']);
            }
        }
    }

    /**
     * 批量处理
     * author: feng
     * create: 2017/10/21 18:22
     */
    public function frozenHandles()
    {
        if (IS_POST) {
            UserLogService::HTwrite(3, '批量处理解冻', '批量处理解冻');
            $ids = I('post.ids');
            if (!$ids) {
                $this->ajaxReturn(['status' => 0]);
            }

            $idsArr = explode(",", $ids);
            $sucCount = 0;
            $msg = "";
            foreach ($idsArr as $k => $id) {
                $maps['status'] = array('eq', 0);
                $maps["id"] = $id;
                $blockData = M('blockedlog')->where($maps)->order('id asc')->find();
                if (!$blockData) {
                    continue;
                }
                $blockedbalance = M('member')->where(['id' => $blockData['userid']])->field("blockedbalance");
                if ($blockedbalance < $blockData["amount"]) {
                    $msg = '冻结金额不足';
                    break;
                }
                $rows = array();
                $rows['balance'] = array('exp', "balance+{$blockData['amount']}");
                $rows['blockedbalance'] = array('exp', "blockedbalance-{$blockData['amount']}");
                //开启事务
                $Model = M();
                $Model->startTrans();
                $info = $Model->table('pay_member')->where(['id' => $blockData['userid']])->lock(true)->find();
                //更新资金
                $upRes = $Model->table('pay_member')->where(['id' => $blockData['userid']])->save($rows);
                //更新状态
                $uplog = $Model->table('pay_blockedlog')->where(array('id' => $blockData['id'], 'status' => 0))->save(array('status' => 1));
                //增加记录
                $data = array();
                $data['userid'] = $blockData['userid'];
                $data['ymoney'] = $info['balance'];
                $data['money'] = $blockData['amount'];
                $data['gmoney'] = $info['balance'] + $blockData['amount'];
                $data['datetime'] = date("Y-m-d H:i:s");
                $data['tongdao'] = $blockData['pid'];
                $data['transid'] = $blockData['orderid']; //交易流水号
                $data['orderid'] = $blockData['orderid'];
                $data['lx'] = 8; //解冻
                $data['contentstr'] = "手动冻结金额解冻";
                $change = $Model->table('pay_moneychange')->add($data);

                //提交事务
                if ($upRes && $uplog && $change) {
                    $Model->commit();
                    $sucCount++;
                } else {
                    $Model->rollback();
                }

            }
            UserLogService::HTwrite(3, '批量处理解冻', '批量处理解冻---' . $ids);
            $this->ajaxReturn(array("status" => $sucCount == count($idsArr) ? 1 : 0, "count" => $sucCount, "msg" => $msg));

        }
    }

    //切换身份
    public function changeuser()
    {
        UserLogService::HTwrite(3, '商户登陆', '商户登陆');
        $userid = I('get.userid', 0, 'intval');
        $info = M('Member')->where(['id' => $userid])->find();
        if ($info) {
            $user_auth = [
                'uid' => $info['id'],
                'username' => $info['username'],
                'groupid' => $info['groupid'],
                'password' => $info['password'],
                'session_random' => $info['session_random'],
                'expire_time' => time() + 1800,
            ];
            if ($info['google_secret_key']) {
                $ga = new \Org\Util\GoogleAuthenticator();
                $oneCode = $ga->getCode($info['google_secret_key']);
                session('user_google_auth', $oneCode);
            } else {
                session('user_google_auth', null);
            }
            session('user_auth', $user_auth);
            ksort($user_auth); //排序
            $code = http_build_query($user_auth); //url编码并生成query字符串
            $sign = sha1($code);
            session('user_auth_sign', $sign);
            $module['4'] = C('user');
            foreach ($this->groupId as $k => $v) {
                if ($k != 4) {
                    $module[$k] = C('agent');
                }
            }
            UserLogService::HTwrite(3, '商户登陆', '(' . $info['username'] . ')商户登陆');
            header('Location:' . $this->_site . $module[$info['groupid']] . '.html');
        }
    }

    //用户状态切换
    public function editStatus()
    {
        if (IS_POST) {
            UserLogService::HTwrite(3, '用户状态切换', '用户状态切换');
            $userid = intval(I('post.uid'));
            $isstatus = I('post.isopen') ? I('post.isopen') : 0;
            $res = M('Member')->where(['id' => $userid])->save(['status' => $isstatus]);
            UserLogService::HTwrite(3, '用户状态切换', '切换(' . $userid . ')用户状态');
            $this->ajaxReturn(['status' => $res]);
        }
    }

    /**
     * 用户认证
     */
    public function authorize()
    {
        $userid = I('get.uid', 0, 'intval');
        if ($userid) {
            $data = M('Member')->where(['id' => $userid])->find();
            //上传图片
            $images = M('Attachment')
                ->where(['userid' => $userid])
                ->limit(6)
                ->field('path')
                ->order('id desc')
                ->select();
            $data['images'] = $images;
            $this->assign('u', $data);
        }
        $this->display();
    }

    //编辑用户级别
    public function editUser()
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
        
        $userid = I('get.uid', 0, 'intval');
        if ($userid) {
            $data = M('Member') ->where(['id' => $userid])->find();
            $this->assign('u', $data);
            //用户组
            //$groups = M('AuthGroup')->field('id,title')->select();
        }
        /**
         * 升级，用户组不再与用户组关联
         * author: feng
         * create: 2017/10/19 15:03
         */
        $agentCateSel = [];
        $agentCateList = M('member_agent_cate')->select();
        foreach ($agentCateList as $k => $v) {
            $agentCateSel[$v['id']] = $v['cate_name'];
        }
        $agent_where['groupid'] = ['gt', '4'];
        $agent = M('Member') ->where($agent_where)->select();
        
        $country = M('Country') ->where(['status'=>1])->select();
        $this->assign('agent', $agent);
        $this->assign('country', $country);
        $this->assign('agentCateSel', $agentCateSel);
        $this->assign('merchants', C('MERCHANTS'));
        
        $this->assign('verifysms', $verifysms);
        $this->assign('verifyGoogle', $verifyGoogle);
        $this->assign('auth_type', $verifyGoogle ? 1 : 0);
        $this->display();
    }

    //保存编辑用户级别
    public function saveUser()
    {
        if (IS_POST) {
            UserLogService::HTwrite(3, '保存编辑用户级别', '保存编辑用户级别');
            
            $uid = session('admin_auth')['uid'];
            //是否可以谷歌安全码验证
            $verifyGoogle = adminGoogleBind($uid);
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
            
            $userid = I('post.userid', 0, 'intval');
            $u = I('post.u/a');
            $u['birthday'] = strtotime($u['birthday']);
            if ($u['password']) {
                if ($userid) {
                    $salt = M('Member')->where(['id' => $userid])->getField('salt');
                    $u['password'] = md5($u['password'] . $salt);
                }
            } else {
                unset($u['password']);
            }
            if (isset($u['balance_php'])) {
                unset($u['balance_php']);
            }
            if (isset($u['blockedbalance_php'])) {
                unset($u['blockedbalance_php']);
            }
            if (isset($u['balance_inr'])) {
                unset($u['balance_inr']);
            }
            if (isset($u['blockedbalance_inr'])) {
                unset($u['blockedbalance_inr']);
            }
            if ($userid) {
                $res = M('Member')->where(['id' => $userid])->save($u);
                UserLogService::HTwrite(3, '编辑用户级别成功', '编辑用户（' . $userid . '）级别成功');
            } else {
                if (!isset($u['password']) || !$u['password']) {
                    $this->ajaxReturn(array("status" => 0, "msg" => '请输入登录密码'));
                }
                $has_user = M('member')->where(['username' => $u['username'], 'email' => $u['email'], '_logic' => 'or'])->find();
                if ($has_user) {
                    if ($has_user['username'] == $u['username']) {
                        $this->ajaxReturn(array("status" => 0, "msg" => '用户名已存在'));
                    }
                    if ($has_user['email'] == $u['email']) {
                        $this->ajaxReturn(array("status" => 0, "msg" => '邮箱已存在'));
                    }
                }

                $siteconfig = M("Websiteconfig")->find();

                foreach ($this->groupId as $k => $v) {
                    if ($u['groupid'] == $k && $u['groupid'] != 4) {
                        $u['verifycode']['regtype'] = $k;
                    }

                }
                $u = generateUser($u, $siteconfig);
                $u['activatedatetime'] = date("Y-m-d H:i:s");
                $u['agent_cate'] = $u['groupid'];
                // 创建用户
                $res = M('Member')->add($u);
                UserLogService::HTwrite(2, '创建用户成功', '创建用户（' . $res . '）成功');
                // 发邮件通知用户密码
                // sendPasswordEmail($u['username'], $u['email'], $u['origin_password'], $siteconfig);
            }

            //编辑用户组
            /*if($res){
            M('AuthGroupAccess')->where(['uid'=>$userid])->save(['group_id'=>$u['groupid']]);
            }*/
            if ($res !== false) {
                $this->ajaxReturn(['status' => 1]);
            } else {
                UserLogService::HTwrite(3, '编辑用户级别失败', '编辑用户（' . $userid . '）级别失败');
                $this->ajaxReturn(['status' => 0]);
            }
        }
    }

    //编辑用户费率
    public function userRateEdit()
    {
        $userid = I('get.uid', 0, 'intval');
        //系统产品列表
        $products = M('Product')
            ->where(['status' => 1, 'isdisplay' => 1])
            ->field('id,name')
            ->select();
        //用户产品列表
        $userprods = M('Userrate')->where(['userid' => $userid])->select();
        if ($userprods) {
            foreach ($userprods as $item) {
                $_tmpData[$item['payapiid']] = $item;
            }
        }
        //重组产品列表
        $list = [];
        if ($products) {
            foreach ($products as $key => $item) {
                $products[$key]['feilv'] = $_tmpData[$item['id']]['feilv'] ? $_tmpData[$item['id']]['feilv'] * 100 : '0.0000';
            }
        }
        $this->assign('userid', $userid);
        $this->assign('products', $products);
        $this->display();
    }

    //保存费率
    public function saveUserRate()
    {
        if (IS_POST) {
            UserLogService::HTwrite(3, '保存用户费率', '保存用户费率');
            $userid = intval(I('post.userid'));
            $rows = I('post.u/a');
            foreach ($rows as $key => $item) {
                $product = M('Product')->where(['id' => $key])->find();
                if (empty($product)) {
                    $this->ajaxReturn(['status' => 0, 'msg' => '支付产品不存在']);
                }
                $rates = M('Userrate')->where(['userid' => $userid, 'payapiid' => $key])->find();
                $productUser = M('Product_user')->where(['userid' => $userid, 'pid' => $key])->find();
                if (!empty($productUser)) {
                    if ($productUser['polling'] == 0 && $productUser['channel'] > 0) {//单独渠道的情况
                        $channel = M('Channel')
                            ->where(['id' => $productUser['channel'], 'status' => 1])
                            ->find();
                        $channel_account_list = M('channel_account')->where(['channel_id' => $productUser['channel'], 'status' => '1', 'custom_rate' => 1])->select();
                        if (!empty($channel)) {
                            if (!empty($channel_account_list)) {
                                foreach ($channel_account_list as $k => $v) {
                                    if ($item['feilv'] < $v['rate']) {
                                        $this->ajaxReturn(['status' => 0, 'msg' => '【' . $channel['title'] . '】T+1运营费率不得低于渠道子账号【' . $v['title'] . '】成本费率：' . ($v['rate'] * 100) . '%']);
                                    }
                                }
                            } else {
                                if ($item['feilv'] < $channel['rate']) {
                                    $this->ajaxReturn(['status' => 0, 'msg' => '【' . $channel['title'] . '】T+0运营费率不得低于渠道成本费率：' . ($channel['rate'] * 100) . '%']);
                                }
                            }
                        }
                    }
                    if ($productUser['polling'] == 1 && $productUser['weight'] != '') {//渠道轮询的情况
                        $temp_weights = explode('|', $productUser['weight']);
                        if (!empty($temp_weights)) {
                            foreach ($temp_weights as $k => $v) {
                                list($pid, $weight) = explode(':', $v);
                                $channel = M('channel')->where(['id' => $pid, 'status' => 1])->find();
                                $channel_account_list = M('channel_account')->where(['channel_id' => $pid, 'status' => '1', 'custom_rate' => 1])->select();
                                if (!empty($channel)) {
                                    if (!empty($channel_account_list)) {
                                        foreach ($channel_account_list as $k => $v) {
                                            if ($item['feilv'] > 0 && $item['feilv'] < $v['rate']) {
                                                $this->ajaxReturn(['status' => 0, 'msg' => '【' . $channel['title'] . '】T+1运营费率不得低于渠道子账号【' . $v['title'] . '】成本费率：' . ($v['rate'] * 100) . '%']);
                                            }
                                        }
                                    } else {
                                        if ($item['feilv'] > 0 && $item['feilv'] < $channel['rate']) {
                                            $this->ajaxReturn(['status' => 0, 'msg' => '【' . $channel['title'] . '】T+1运营费率不得低于渠道成本费率：' . ($channel['rate'] * 100) . '%']);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                if ($rates) {
                    $data_insert[] = ['id' => $rates['id'], 'userid' => $userid, 'payapiid' => $key, 'feilv' => $item['feilv']/100];
                } else {
                    $data_update[] = ['userid' => $userid, 'payapiid' => $key, 'feilv' => $item['feilv']/100];
                }
            }
            $ins_arr = M('Userrate')->addAll($data_insert, [], true);
            if ($ins_arr) UserLogService::HTwrite(2, '添加用户费率', '添加用户（' . $userid . '）费率');
            $up_arr = M('Userrate')->addAll($data_update, [], true);
            if ($up_arr) UserLogService::HTwrite(3, '修改用户费率', '修改用户（' . $userid . '）费率');
            $this->ajaxReturn(['status' => 1]);
        }
    }
    
    
    //批量编辑用户费率
    public function userAllRateEdit()
    {
        //系统产品列表
        $products = M('Product')
            ->where(['status' => 1, 'isdisplay' => 1])
            ->field('id,name')
            ->select();
        // //用户产品列表
        // $userprods = M('Userrate')->select();
        // if ($userprods) {
        //     foreach ($userprods as $item) {
        //         $_tmpData[$item['payapiid']] = $item;
        //     }
        // }
        // //重组产品列表
        // $list = [];
        // if ($products) {
        //     foreach ($products as $key => $item) {
        //         $products[$key]['feilv'] = $_tmpData[$item['id']]['feilv'] ? $_tmpData[$item['id']]['feilv'] * 100 : '0.0000';
        //     }
        // }
        $this->assign('products', $products);
        $this->display();
    }

    //批量保存费率
    public function saveAllUserRate()
    {
        if (IS_POST) {
            UserLogService::HTwrite(3, '批量保存用户费率', '批量保存用户费率');
            $rows = I('post.u/a');
            foreach ($rows as $key => $item) {
                if($item['feilv'] <= 0){
                    continue;
                }
                $product = M('Product')->where(['id' => $key])->find();
                if (empty($product)) {
                    $this->ajaxReturn(['status' => 0, 'msg' => '支付产品不存在']);
                }
                $rates = M('Userrate')->where(['payapiid' => $key])->delete();     //清楚所有设置
                
                $Member_arr = M('Member')->field('id')->select();    //查询所有用户
                foreach ($Member_arr as $mv){
                    $productUser = M('Product_user')->where(['userid' => $mv['id'], 'pid' => $key])->find();
                    if (!empty($productUser)) {
                        if ($productUser['polling'] == 0 && $productUser['channel'] > 0) {//单独渠道的情况
                            $channel = M('Channel') ->where(['id' => $productUser['channel'], 'status' => 1]) ->find();
                            $channel_account_list = M('channel_account')->where(['channel_id' => $productUser['channel'], 'status' => '1', 'custom_rate' => 1])->select();
                            if (!empty($channel)) {
                                if (!empty($channel_account_list)) {
                                    foreach ($channel_account_list as $k => $v) {
                                        if ($item['feilv'] < $v['rate']) {
                                            $this->ajaxReturn(['status' => 0, 'msg' => '【' . $channel['title'] . '】T+1运营费率不得低于渠道子账号【' . $v['title'] . '】成本费率：' . ($v['rate'] * 100) . '%']);
                                        }
                                    }
                                } else {
                                    if ($item['feilv'] < $channel['rate']) {
                                        $this->ajaxReturn(['status' => 0, 'msg' => '【' . $channel['title'] . '】T+0运营费率不得低于渠道成本费率：' . ($channel['rate'] * 100) . '%']);
                                    }
                                }
                            }
                        }
                        if ($productUser['polling'] == 1 && $productUser['weight'] != '') {//渠道轮询的情况
                            $temp_weights = explode('|', $productUser['weight']);
                            if (!empty($temp_weights)) {
                                foreach ($temp_weights as $k => $v) {
                                    list($pid, $weight) = explode(':', $v);
                                    $channel = M('channel')->where(['id' => $pid, 'status' => 1])->find();
                                    $channel_account_list = M('channel_account')->where(['channel_id' => $pid, 'status' => '1', 'custom_rate' => 1])->select();
                                    if (!empty($channel)) {
                                        if (!empty($channel_account_list)) {
                                            foreach ($channel_account_list as $k => $v) {
                                                if ($item['feilv'] > 0 && $item['feilv'] < $v['rate']) {
                                                    $this->ajaxReturn(['status' => 0, 'msg' => '【' . $channel['title'] . '】T+1运营费率不得低于渠道子账号【' . $v['title'] . '】成本费率：' . ($v['rate'] * 100) . '%']);
                                                }
                                            }
                                        } else {
                                            if ($item['feilv'] > 0 && $item['feilv'] < $channel['rate']) {
                                                $this->ajaxReturn(['status' => 0, 'msg' => '【' . $channel['title'] . '】T+1运营费率不得低于渠道成本费率：' . ($channel['rate'] * 100) . '%']);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $data_update[] = ['userid' => $mv['id'], 'payapiid' => $key, 'feilv' => $item['feilv']/100];
                }
            }
            // var_dump($data_update);die;
            $up_arr = M('Userrate')->addAll($data_update, [], true);
            if ($up_arr) UserLogService::HTwrite(3, '批量保存用户费率', '批量保存用户费率成功');
            $this->ajaxReturn(['status' => 1]);
        }
    }

    //编辑用户通道
    public function editUserProduct()
    {
        $userid = I('get.uid', 0, 'intval');
        //系统产品列表
        $products = M('Product')
            ->where(['isdisplay' => 1])
            ->field('id,name,status,paytype')
            ->select();
        //用户产品列表
        $userprods = M('Product_user')->where(['userid' => $userid])->select();
        if ($userprods) {
            foreach ($userprods as $key => $item) {
                $_tmpData[$item['pid']] = $item;
            }
        }
        //重组产品列表
        $list = [];
        if ($products) {
            foreach ($products as $key => $item) {
                $products[$key]['status'] = $_tmpData[$item['id']]['status'];
                $products[$key]['channel'] = $_tmpData[$item['id']]['channel'];
                $products[$key]['polling'] = $_tmpData[$item['id']]['polling'];
                //权重
                $weights = [];
                $weights = explode('|', $_tmpData[$item['id']]['weight']);
                $_tmpWeight = [];
                if (is_array($weights)) {
                    foreach ($weights as $value) {
                        list($pid, $weight) = explode(':', $value);
                        if ($pid) {
                            $_tmpWeight[$pid] = ['pid' => $pid, 'weight' => $weight];
                        }
                    }
                } else {
                    list($pid, $weight) = explode(':', $_tmpData[$item['id']]['weight']);
                    if ($pid) {
                        $_tmpWeight[$pid] = ['pid' => $pid, 'weight' => $weight];
                    }
                }
                $products[$key]['weight'] = $_tmpWeight;
            }
        }
        $this->assign('products', $products);
        $this->display();
    }

    //保存编辑用户通道
    public function saveUserProduct()
    {
        UserLogService::HTwrite(3, '保存编辑用户通道', '保存编辑用户通道');
        if (IS_POST) {
            $userid = I('post.userid', 0, 'intval');
            $u = I('post.u/a');
            foreach ($u as $key => $item) {
                $weightStr = '';
                $status = $item['status'] ? $item['status'] : 0;
                if (is_array($item['w'])) {
                    foreach ($item['w'] as $weigths) {
                        if ($weigths['pid']) {
                            $weightStr .= $weigths['pid'] . ':' . $weigths['weight'] . "|";
                        }
                    }
                }
                $product = M('Product_user')->where(['userid' => $userid, 'pid' => $key])->find();
                if ($product) {
                    $data_insert[] = ['id' => $product['id'], 'userid' => $userid, 'pid' => $key, 'status' => $status, 'polling' => $item['polling'], 'channel' => $item['channel'], 'weight' => trim($weightStr, '|')];
                } else {
                    $data_update[] = ['userid' => $userid, 'pid' => $key, 'status' => $status, 'polling' => $item['polling'], 'channel' => $item['channel'], 'weight' => trim($weightStr, '|')];
                }
            }
            $ins_arr = M('Product_user')->addAll($data_insert, [], true);
            if ($ins_arr) UserLogService::HTwrite(2, '添加用户通道', '添加用户（' . $userid . '）的通道');
            $up_arr = M('Product_user')->addAll($data_update, [], true);
            if ($up_arr) UserLogService::HTwrite(3, '修改用户通道', '修改用户（' . $userid . '）的通道');
            $this->ajaxReturn(['status' => 1]);
        }
    }

    //编辑用户默认代付通道
    public function editUserChannel()
    {
        $userid = I('get.uid', 0, 'intval');
        //系统产品列表
        $products = M('Product')
            ->where(['isdisplay' => 1])
            ->field('id,name,status,paytype')
            ->select();
        //用户产品列表
        $userprods = M('User_pay_for_another')->where(['userid' => $userid])->select();
        if ($userprods) {
            foreach ($userprods as $key => $item) {
                $_tmpData[$item['pid']] = $item;
            }
        }
        $PayForAnother = M('PayForAnother')
            ->where(['status' => 1])
            ->field('id,title,status,paytype')
            ->select();
        $this->assign('channellist', $PayForAnother);
        //重组产品列表
        $list = [];
        if ($products) {
            foreach ($products as $key => $item) {
                $products[$key]['status'] = $_tmpData[$item['id']]['status'];
                $products[$key]['channel'] = $_tmpData[$item['id']]['channel'];
                $products[$key]['polling'] = $_tmpData[$item['id']]['polling'];
            }
        }
        $this->assign('products', $products);
        $this->display();

        // $userid = I('get.uid', 0, 'intval');
        // //系统产品列表
        // $products = M('PayForAnother')
        //     ->where(['status' => 1])
        //     ->field('id,title,status,appid')
        //     ->select();
        // //用户产品列表
        // $userprods = M('UserPayForAnother')->where(['userid' => $userid])->find();
        // $this->assign('products', $products);
        // $this->assign('userprods', $userprods);
        // $this->display();
    }

    //保存编辑默认用户代付通道
    public function saveUserChannel()
    {
        UserLogService::HTwrite(3, '保存编辑用户代付通道', '保存编辑用户代付通道');
        if (IS_POST) {
            $userid = I('post.userid', 0, 'intval');
            $u = I('post.u/a');
            foreach ($u as $key => $item) {
                $weightStr = '';
                $status = $item['status'] ? $item['status'] : 0;
                $product = M('User_pay_for_another')->where(['userid' => $userid, 'pid' => $key])->find();
                if ($product) {
                    $data_insert[] = ['id' => $product['id'], 'userid' => $userid, 'pid' => $key, 'status' => $status, 'channel' => $item['channel']];
                } else {
                    $data_update[] = ['userid' => $userid, 'status' => $status, 'pid' => $key, 'channel' => $item['channel']];
                }
            }
            $ins_arr = M('User_pay_for_another')->addAll($data_insert, [], true);
            if ($ins_arr) UserLogService::HTwrite(2, '添加用户通道', '添加用户（' . $userid . '）的通道');
            $up_arr = M('User_pay_for_another')->addAll($data_update, [], true);
            if ($up_arr) UserLogService::HTwrite(3, '修改用户通道', '修改用户（' . $userid . '）的通道');
            $this->ajaxReturn(['status' => 1]);
        }
    }
    
    //按国家批量编辑用户默认代收通道
    public function editAllUserProductCountry()
    {
        //系统产品列表
        $product = M('Product')
            ->where(['isdisplay' => 1])
            ->field('id,name,status,paytype')
            ->select();
        $country = M('Country')->select();
        
        $this->assign('product', $product);
        $this->assign('country', $country);
        $this->display();
    }
    //按包网批量编辑用户默认代收通道
    public function editAllUserProductType()
    {
        //系统产品列表
        $product = M('Product')
            ->where(['isdisplay' => 1])
            ->field('id,name,status,paytype')
            ->select();
        $agency = M('Agency')->select();
        
        $this->assign('product', $product);
        $this->assign('agency', $agency);
        $this->display();
    }
    
    //批量编辑用户默认代收通道
    public function editAllUserProduct()
    {
        //系统产品列表
        $product = M('Product')
            ->where(['isdisplay' => 1])
            ->field('id,name,status,paytype')
            ->select();
        $userList = M('Member')->where(['status' => 1])->order('id desc')->select();
        
        $this->assign('product', $product);
        $this->assign('userList', $userList);
        $this->display();
    }
    
        
    //按类型查询代付通道
    public function getChannel()
    {
        $productid = I('post.productid');
        //系统产品列表
        $paytype = M('Product')
            ->where(['id' => $productid])
            ->getField("paytype");
        //系统产品列表
        $channel = M('Channel')
            ->where(['status' => 1,'paytype' => $paytype])
            ->field('id,title')
            ->select();
        $this->ajaxReturn($channel);
    }
    
    //保存编辑默认用户代收通道
    public function saveAllUserProduct()
    {
        UserLogService::HTwrite(3, '批量保存编辑用户默认代收通道', '批量保存编辑用户代收通道');
        if (IS_POST) {
            $channel = I('post.channel');
            $product = I('post.product');
            $checktype = I('post.checktype');
            $ids = I('post.ids');
            if($checktype==1){
                $agencys_arr = array_filter(explode(',', $ids));
                $ids_array = M('Member')->field('id')->where(['agency_id' => ['in',$agencys_arr]])->order('id desc')->select();
                $ids_arr=[];
                foreach ($ids_array as $v){
                    $ids_arr[]=$v['id'];
                }
                // echo M('Member')->getLastSql();
            }elseif($checktype==2){
                $agencys_arr = array_filter(explode(',', $ids));
                $ids_array = M('Member')->field('id')->where(['country_id' => ['in',$agencys_arr]])->order('id desc')->select();
                $ids_arr=[];
                foreach ($ids_array as $v){
                    $ids_arr[]=$v['id'];
                }
                // var_dump($ids_arr);die;
                // echo M('Member')->getLastSql();
            }else{
                if($ids!=''){
                    $ids_arr = array_filter(explode(',', $ids));
                }
            }
            
            if(!empty($ids_arr)){
                foreach ($ids_arr as $v){
                    $data_update = ['userid' => $v, 'channel' => $channel,'pid' => $product];
                    $productU = M('ProductUser')->where(['userid' => $v,'pid' => $product])->find();
                    if($productU){
                        $up_arr = M('ProductUser')->where(['userid' => $v,'pid' => $product])->save($data_update);
                        if ($up_arr) UserLogService::HTwrite(3, '批量修改用户默认代收通道', '修改用户（' . $v . '）的默认代收通道为：' . $channel);
                    }else{
                        $ins_arr = M('ProductUser')->add($data_update);
                        if ($ins_arr) UserLogService::HTwrite(2, '批量添加用户默认代收通道', '添加用户（' . $v . '）的默认代收通道为：' . $channel);
                    }
                }
                $this->ajaxReturn(['status' => 1]);
            }
            $this->ajaxReturn(['status' => 0]);
        }
    }
    //按国家批量编辑用户默认代付通道
    public function editAllUserWithdrawalCountry()
    {
        //系统产品列表
        $product = M('Product')
            ->where(['isdisplay' => 1])
            ->field('id,name,status,paytype')
            ->select();
        $country = M('Country')->select();
        
        $this->assign('product', $product);
        $this->assign('country', $country);
        $this->display();
    }
    
    //按包网批量编辑用户默认代付通道
    public function editAllUserWithdrawalType()
    {
        //系统产品列表
        $product = M('Product')
            ->where(['isdisplay' => 1])
            ->field('id,name,status,paytype')
            ->select();
        $agency = M('Agency')->select();
        
        $this->assign('product', $product);
        $this->assign('agency', $agency);
        $this->display();
    }
    
    //批量编辑用户默认代付通道
    public function editAllUserWithdrawal()
    {
        //系统产品列表
        $product = M('Product')
            ->where(['isdisplay' => 1])
            ->field('id,name,status,paytype')
            ->select();
        $userList = M('Member')->where(['status' => 1])->order('id desc')->select();
        
        $this->assign('product', $product);
        $this->assign('userList', $userList);
        $this->display();
    }
    
    //按类型查询代付通道
    public function getWithdrawal()
    {
        $productid = I('post.productid');
        //系统产品列表
        $product = M('Product')
            ->where(['id' => $productid,'isdisplay' => 1])
            ->field('id,name,status,paytype')
            ->find();
        //系统产品列表
        $channel = M('PayForAnother')
            ->where(['status' => 1,'paytype' => $product['paytype']])
            ->field('id,title')
            ->select();
        $this->ajaxReturn($channel);
    }
    
    //保存编辑默认用户代付通道
    public function saveAllUserWithdrawal()
    {
        UserLogService::HTwrite(3, '批量保存编辑用户默认代付通道', '批量保存编辑用户代付通道');
        if (IS_POST) {
            $channel = I('post.channel');
            $product = I('post.product');
            $checktype = I('post.checktype');
            $ids = I('post.ids');
            if($checktype==1){
                $agencys_arr = array_filter(explode(',', $ids));
                $ids_array = M('Member')->field('id')->where(['agency_id' => ['in',$agencys_arr]])->order('id desc')->select();
                $ids_arr=[];
                foreach ($ids_array as $v){
                    $ids_arr[]=$v['id'];
                }
                // echo M('Member')->getLastSql();
            }elseif($checktype==2){
                $country_arr = array_filter(explode(',', $ids));
                $ids_array = M('Member')->field('id')->where(['country_id' => ['in',$country_arr]])->order('id desc')->select();
                $ids_arr=[];
                foreach ($ids_array as $v){
                    $ids_arr[]=$v['id'];
                }
                // echo M('Member')->getLastSql();
            }else{
                if($ids!=''){
                    $ids_arr = array_filter(explode(',', $ids));
                }
            }
            if(!empty($ids_arr)){
                foreach ($ids_arr as $v){
                    $data_update = ['userid' => $v, 'channel' => $channel, 'pid' => $product];
                    $productU = M('UserPayForAnother')->where(['userid' => $v, 'pid' => $product])->find();
                    if($productU){
                        $up_arr = M('UserPayForAnother')->where(['userid' => $v, 'pid' => $product])->save($data_update);
                        if ($up_arr) UserLogService::HTwrite(3, '批量修改用户默认代付通道', '修改用户（' . $v . '）的产品：' . $product . ',代付通道为：' . $channel);
                    }else{
                        $ins_arr = M('UserPayForAnother')->add($data_update);
                        if ($ins_arr) UserLogService::HTwrite(2, '批量添加用户默认代付通道', '添加用户（' . $v . '）的产品：' . $product . ',代付通道为：' . $channel);
                    }
                }
                $this->ajaxReturn(['status' => 1]);
            }
            $this->ajaxReturn(['status' => 0]);
        }
    }

    //提现
    public function userWithdrawal()
    {
        $userid = I('get.uid', 0, 'intval');
        $data = M('Tikuanconfig')->where(['userid' => $userid])->find();
        $this->assign('u', $data);
        $this->display();
    }

    //保存提现规则
    public function saveWithdrawal()
    {
        UserLogService::HTwrite(3, '保存提现规则', '保存提现规则');
        if (IS_POST) {
            $userid = I('post.userid', 0, 'intval');
            $id = I('post.id', 0, 'intval');
            if ((int)$_POST['u']['systemxz']) {
                $rows = I('post.u');
            } else {
                $rows['systemxz'] = 0;
            }
            if ($userid == 1) {
                $this->ajaxReturn(['status' => 0, 'msg' => '参数错误']);
            }
            $rows['issystem'] = 0;
            if ($id) {
                $res = M('Tikuanconfig')->where(['id' => $id, 'userid' => $userid])->save($rows);
                UserLogService::HTwrite(3, '修改提现规则成功', '修改用户（' . $userid . '）提现规则成功');
            } else {
                $rows['userid'] = $userid;
                $res = M('Tikuanconfig')->add($rows);
                UserLogService::HTwrite(2, '添加提现规则成功', '添加用户（' . $userid . '）提现规则成功');
            }
            if (FALSE !== $res) {
                $this->ajaxReturn(['status' => 1, 'msg' => '设置成功']);
            } else {
                UserLogService::HTwrite(3, '修改提现规则失败', '修改用户（' . $userid . '）提现规则失败');
                $this->ajaxReturn(['status' => 0, 'msg' => '设置失败']);
            }
        }
    }

    /**
     * 用户代理分类管理
     */
    public function agentCateList()
    {
        $m = M("member_agent_cate");
        $count = $m->count();
        $page = new Page($count, 15);
        $list = $m
            ->order('id desc')
            ->select();
        $this->assign('list', $list);
        $this->assign('page', $page->show());
        $this->display();
    }

    /**
     * 添加代理分类
     */
    public function addAgentCate()
    {
        $this->display();
    }

    /**
     * 编辑代理分类
     */
    public function editAgentCate()
    {
        $id = I("id", 0, "intval");
        if (!$id) {
            return;
        }

        $this->assign("cache", M("member_agent_cate")->where(array("id" => $id))->find());
        $this->display();
    }

    /**
     * 编辑代理分类
     */
    public function saveAgentCate()
    {
        UserLogService::HTwrite(3, '编辑代理分类', '编辑代理分类');
        if (IS_POST) {
            $id = I('post.id', 0, 'intval');
            $rows = I('post.item/a');

            //保存
            if ($id) {
                $res = M('member_agent_cate')->where(['id' => $id])->save($rows);
                UserLogService::HTwrite(3, '修改代理分类', '修改代理(' . $id . ')分类' . $id);
            } else {
                $rows["ctime"] = time();
                $res = M('member_agent_cate')->add($rows);
                UserLogService::HTwrite(2, '添加代理分类', '添加代理(' . $res . ')分类' . $res);
            }
            $this->ajaxReturn(['status' => $res]);
        }
    }

    /**
     * 删除代理分类
     */
    public function deleteAgentCate()
    {
        UserLogService::HTwrite(3, '删除代理分类', '删除代理分类');
        if (IS_POST) {
            $id = I('post.id', 0, 'intval');
            $res = M('member_agent_cate')->where(['id' => $id])->delete();
            UserLogService::HTwrite(4, '删除代理分类', '删除代理(' . $id . ')分类' . $id);
            $this->ajaxReturn(['status' => $res]);
        }
    }

    /**
     * 代理列表
     */
    public function agentList()
    {

        $username = I('get.username', '');
        $status = I('get.status', '');
        $authorized = I('get.authorized', '');
        $parentid = I('get.parentid', '');
        $regdatetime = I('get.regdatetime', '');
        $groupid = I('get.groupid', '');

        $where['groupid'] = ['gt', '4'];
        if ($groupid != '') {
            $where['groupid'] = $groupid;
        }
        $this->assign('groupid', $groupid);

        if (!empty($username) && !is_numeric($username)) {
            $where['username'] = ['like', "%" . $username . "%"];
        } elseif (intval($username) - 10000 > 0) {
            $where['id'] = intval($username) - 10000;
        }
        $this->assign('username', $username);
        if ($status != '') {
            $where['status'] = $status;
        }
        $this->assign('status', $status);
        if ($authorized != '') {
            $where['authorized'] = $authorized;
        }
        $this->assign('authorized', $authorized);
        if (!empty($parentid) && !is_numeric($parentid)) {
            $User = M("Member");
            $pid = $User->where(['username' => $parentid])->getField("id");
            $where['parentid'] = $pid;
        } elseif ($parentid) {
            $where['parentid'] = $parentid;
        }
        $this->assign('parentid', $parentid);
        if ($regdatetime) {
            list($starttime, $endtime) = explode('|', $regdatetime);
            $where['regdatetime'] = ["between", [strtotime($starttime), strtotime($endtime)]];
        }
        $this->assign('regdatetime', $regdatetime);
        $count = M('Member')->where($where)->count();
        $size = 15;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }
        $page = new Page($count, $rows);
        $list = M('Member')
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('id desc')
            ->select();

        $agentCateSel = [];
        $agentCateList = M('member_agent_cate')->select();
        foreach ($agentCateList as $k => $v) {
            if ($v['id'] != 4) {
                $agentCateSel[$v['id']] = $v['cate_name'];
            }
        }
        foreach ($list as $k => $v) {
            $list[$k]['groupname'] = $this->groupId[$v['groupid']];
        }
        $this->assign('agentCateSel', $agentCateSel);
        $this->assign('rows', $rows);
        $this->assign("list", $list);
        $this->assign('page', $page->show());
        //取消令牌
        C('TOKEN_ON', false);
        $this->display();
    }

    public function loginrecord()
    {

        $type = I('get.type', '');
        if ($type != '') {
            $where['type'] = $type;
        }
        $this->assign("type", $type);

        if ($userid = I('get.userid', '')) {
            if ($$userid < 10000) {
                $where['userid'] = $userid;

            } else {
                $where['userid'] = $userid - 10000;
            }

        }
        $this->assign("userid", $userid);


        if ($loginip = I('get.loginip', '')) {
            $where['loginip'] = $loginip;
        }
        $this->assign("loginip", $loginip);
        $logindatetime = urldecode(I("request.logindatetime"));
        if ($logindatetime) {
            list($cstime, $cetime) = explode('|', $logindatetime);
            $where['logindatetime'] = ['between', [$cstime, $cetime ? $cetime : date('Y-m-d H:i:s')]];
        }
        $this->assign("logindatetime", $logindatetime);
        $count = M('Loginrecord')->where($where)->count();
        $size = 15;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }

        $page = new Page($count, $rows);
        $list = M('Loginrecord')
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('id desc')
            ->select();
        foreach ($list as $k => $v) {
            if ($v['type'] == 0) {
                $list[$k]['userid'] += 10000;
            }
        }
        $this->assign("list", $list);
        $this->assign("rows", $rows);
        $this->assign('page', $page->show());
        $this->display();
    }

    //导出登录记录
    public function exportloginrecord()
    {
        UserLogService::HTwrite(5, '导出登录记录', '导出登录记录');
        if ($userid = I('get.userid', 0, 'intval')) {
            $where['userid'] = $userid - 10000;
        }
        $type = I('get.type', '');
        if ($type != '') {
            $where['type'] = $type;
        }

        if ($loginip = I('get.loginip', '')) {
            $where['loginip'] = $loginip;
        }
        $logindatetime = urldecode(I("request.logindatetime"));
        if ($logindatetime) {
            list($cstime, $cetime) = explode('|', $logindatetime);
            $where['logindatetime'] = ['between', [$cstime, $cetime ? $cetime : date('Y-m-d H:i:s')]];
        }

        $title = array('ID', '用户类型', '用户编号', '登录时间', '地点', 'IP');
        $data = M('Loginrecord')
            ->where($where)
            ->order('id desc')
            ->select();
        foreach ($data as $item) {

            switch ($item['type']) {
                case 0:
                    $type = '商户';
                    $userid = $item['userid'] + 10000;
                    break;
                case 1:
                    $type = '后台管理员';
                    $userid = $item['userid'];
                    break;
            }
            $list[] = array(
                'id' => $item['id'],
                'type' => $type,
                'userid' => $userid,
                'logindatetime' => $item['logindatetime'],
                'loginip' => $item['loginip'],
                'loginaddress' => $item['loginaddress'],
            );
        }
        UserLogService::HTwrite(5, '导出登录记录', '导出（' . $userid . '）登录记录');
        exportCsv($list, $title);
    }

    //用户充值开关
    public function editCharge()
    {
        UserLogService::HTwrite(3, '编辑用户充值开关', '编辑用户充值开关');
        if (IS_POST) {
            $userid = I('post.uid', 0, 'intval');
            $isstatus = I('post.isopen') ? I('post.isopen') : 0;
            $res = M('Member')->where(['id' => $userid])->save(['open_charge' => $isstatus]);
            if ($isstatus) {
                UserLogService::HTwrite(3, '打开用户充值开关', '打开编辑用户(' . $userid . ')充值开关');
            } else {
                UserLogService::HTwrite(3, '关闭用户充值开关', '关闭用户(' . $userid . ')充值开关');
            }
            $this->ajaxReturn(['status' => $res]);
        }
    }

    /**
     * 发送冲正交易验证码信息
     */
    public function adjustUserMoney()
    {
        $mobile = I('request.mobile');
        $res = $this->send('adjustUserMoneySend', $mobile, '冲正交易');
        $this->ajaxReturn(['status' => $res['code']]);
    }

    /**
     * 根据渠道ID获取渠道账号列表
     */
    public function getChannelAccount()
    {

        $uid = I('request.uid', 0, 'intval');
        $productUserList = M('ProductUser')->where(['userid' => $uid, 'status' => 1])->select();
        //因为有些渠道是轮询的，要将所有渠道id处理出来
        $channelIds = [];
        foreach ($productUserList as $k => $v) {
            if ($v['polling']) {
                $channls = explode('|', $v['weight']);
                foreach ($channls as $k1 => $v1) {
                    $channelIds[] = rtrim($v1, ':');
                }
            } else {
                $channelIds[] = $v['channel'];
            }
        }
        $channelAccountList = $channelList = [];
        if ($channelIds) {
            //查询所有的子账号
            $channelAccountList = M('channelAccount')->field('id,channel_id,title')->where(['channel_id' => ['in', $channelIds], 'status' => 1])->select();
            //查询所有的通道
            $channelList = M('channel')->field('id,title')->where(['id' => ['in', $channelIds], 'status' => 1])->select();
        }
        //查询已自定的子账号
        $userChannelAccountInfo = M('UserChannelAccount')->field('status,account_ids')->where(['userid' => $uid])->find();
        $accountIds = $userChannelAccountInfo['account_ids'];
        $status = $userChannelAccountInfo['status'];
        if ($accountIds) {
            $accountIds = explode(',', $accountIds);
            foreach ($channelAccountList as $k => $v) {
                if (in_array($v['id'], $accountIds)) {
                    $channelAccountList[$k]['checked'] = true;
                } else {
                    $channelAccountList[$k]['checked'] = false;
                }
            }
        }

        //获取可以用的子账号和通道
        $list = [];
        foreach ($channelList as $k => $v) {
            foreach ($channelAccountList as $k1 => $v1) {
                if ($v1['channel_id'] == $v['id']) {
                    $list[$v['title']][] = $v1;
                }
            }
        }
        $this->assign('status', $status);
        $this->assign('list', $list);
        $this->assign('uid', $uid);
        $this->display();
    }

    public function saveChannelAccout()
    {
        UserLogService::HTwrite(3, '保存用户指定账户', '保存用户指定账户');
        $uid = I('request.userid', 0, 'intval');
        $account = I('request.account');
        $status = I('request.status', 0, 'intval');
        if (empty($account)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '没有选择子账号']);
        }
        $count = M('UserChannelAccount')->where(['userid' => $uid])->count();
        $data['account_ids'] = implode(',', $account);
        $data['status'] = $status;
        if ($count) {
            $res = M('UserChannelAccount')->where(['userid' => $uid])->save($data);
            UserLogService::HTwrite(3, '修改用户指定账户', '修改用户（' . $uid . '）指定账户' . $data['account_ids']);
        } else {
            $data['userid'] = $uid;
            $res = M('UserChannelAccount')->add($data);
            UserLogService::HTwrite(2, '添加用户指定账户', '添加用户（' . $uid . '）指定账户' . $data['account_ids']);
        }
        $this->ajaxReturn(['status' => $res]);
    }

    /**
     * 解绑谷歌验证器
     */
    public function unbindGoogle()
    {
        if (IS_POST) {
            $id = I('post.uid', 0, 'intval');
            $info = M('Member')->where(['id' => $id])->find();
            if (empty($info)) {
                $this->ajaxReturn(['status' => 0, 'msg' => '用户不存在']);
            }
            if ($info['google_secret_key'] == '') {
                $this->ajaxReturn(['status' => 0, 'msg' => '用户未绑定谷歌验证器']);
            }
            $res = M('Member')->where(['id' => $id])->setField('google_secret_key', '');
            if ($res) {
                $this->ajaxReturn(['status' => 1, 'msg' => '解绑成功']);
            } else {
                $this->ajaxReturn(['status' => 0, 'msg' => '解绑失败']);
            }
        }
    }
    
        /**
     * 解绑机器人
     */
    public function unbindtelegram()
    {
        if (IS_POST) {
            $id = I('post.uid', 0, 'intval');
            $info = M('Member')->where(['id' => $id])->find();
            if (empty($info)) {
                $this->ajaxReturn(['status' => 0, 'msg' => '用户不存在']);
            }
            if ($info['telegram_id'] == '') {
                $this->ajaxReturn(['status' => 0, 'msg' => '用户未绑定谷歌验证器']);
            }
            $res = M('Member')->where(['id' => $id])->setField('telegram_id', '');
            if ($res) {
                $this->ajaxReturn(['status' => 1, 'msg' => '解绑成功']);
            } else {
                $this->ajaxReturn(['status' => 0, 'msg' => '解绑失败']);
            }
        }
    }

    //编辑用户通道费率
    public function userChannelRateEdit()
    {
        $userid = I('get.uid', 0, 'intval');
        //系统通道列表
        $channel = M('Channel')->where(['status' => 1])->field('id,title')->order('id asc')->select();
        //重组产品列表
        if ($channel) {
            //用户通道列表
            $userprods = M('Userchannelrate')->where(['userid' => $userid])->order('id asc')->select();
            if ($userprods) {
                foreach ($userprods as $item) {
                    $_tmpData[$item['amount']][$item['channelid']] = $item;
                }
                foreach ($channel as $key => $item) {
                    foreach ($_tmpData as $_tk => $_tv) {
                        if ($_tv[$item['id']]['amount'] == $_tk) {
                            $new_channel[$key][$_tk]['id'] = $item['id'];
                            $new_channel[$key][$_tk]['title'] = $item['title'];
                            $new_channel[$key][$_tk]['feilv'] = $_tv[$item['id']]['feilv'] ? $_tv[$item['id']]['feilv'] : '0.0000';
                            $new_channel[$key][$_tk]['amount'] = $_tv[$item['id']]['amount'] ? $_tv[$item['id']]['amount'] : '默认';
                        }
                    }
                }
            } else {
                foreach ($channel as $key => $item) {
                    $new_channel[$key][0]['id'] = $item['id'];
                    $new_channel[$key][0]['title'] = $item['title'];
                    $new_channel[$key][0]['feilv'] = '0.0000';
                    $new_channel[$key][0]['amount'] = '默认';
                }
            }
        }
        $u_id = $userid + 10000;
        $this->assign('u_id', $u_id);
        $this->assign('userid', $userid);
        $this->assign('channel', $channel);
        $this->assign('new_channel', $new_channel);
        $this->display();
    }

    //保存用户通道费率
    public function saveUserChannelRate()
    {
        UserLogService::HTwrite(3, '保存用户通道费率', '保存用户通道费率');
        if (IS_POST) {
            $userid = intval(I('post.userid'));
            $rows = I('post.u/a');
//            echo "<pre>";
//            var_dump($rows);
            foreach ($rows as $key => $item) {
                foreach ($item as $ik => $iv) {
                    if (!$iv['amount']) $iv['amount'] = 0;
                    $data_insert[] = [
                        'userid' => $userid,
                        'channelid' => $key,
                        'amount' => $iv['amount'],
                        'feilv' => $iv['feilv'],
                    ];
                }
                M('Userchannelrate')->where(['userid' => $userid, 'channelid' => $key])->delete();
            }
            M('Userchannelrate')->addAll($data_insert, [], true);
            UserLogService::HTwrite(3, '保存用户通道费率成功', '保存用户（' . $userid . '）通道费率成功');
            $this->ajaxReturn(['status' => 1]);
        }
    }
}
