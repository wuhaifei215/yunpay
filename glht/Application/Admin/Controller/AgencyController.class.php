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
 * 包网机构 管理控制
 * Class UserController
 * @package Admin\Controller
 */
class AgencyController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 包网机构列表
     */
    public function index()
    {
        UserLogService::HTwrite(1, '查看包网机构列表', '查看包网机构列表');

        $username = I("get.username", '', 'trim');
        
        $this->assign('username', $username);
        $status = I("get.status");
        $regdatetime = I('get.regdatetime');
        if (!empty($username) && !is_numeric($username)) {
            $where['username'] = ['like', "%" . $username . "%"];
        }

        if ($status != '') {
            $where['status'] = $status;
        }
        $this->assign('status', $status);
  
        $count = M('Agency')->where($where)->count();
        $size = 50;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }

        $page = new Page($count, $rows);
        $list = M('Agency')
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('id desc')
            ->select();

        $this->assign('rows', $rows);
        $this->assign("list", $list);
        $this->assign('page', $page->show());
        //取消令牌
        C('TOKEN_ON', false);
        $this->display();
    }
    
    /**
     * 删除包网机构
     */
    public function delUser()
    {
        if (IS_POST) {
            UserLogService::HTwrite(4, '删除包网机构', '删除包网机构');
            $id = I('post.uid', 0, 'intval');
            $res = M('Agency')->where(['id' => $id])->delete();
            if ($res) UserLogService::HTwrite(4, '删除包网机构成功', '删除包网机构（' . $id . '）成功');
            $this->ajaxReturn(['status' => $res]);
        }
    }

    //编辑包网机构级别
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
        // $verifyGoogle = adminGoogleBind($uid);
        
        $userid = I('get.uid', 0, 'intval');
        if ($userid) {
            $data = M('Agency')->where(['id' => $userid])->find();
            $this->assign('u', $data);

        }
        // $this->assign('verifysms', $verifysms);
        // $this->assign('verifyGoogle', $verifyGoogle);
        // $this->assign('auth_type', $verifyGoogle ? 1 : 0);
        $this->display();
    }

    //保存编辑包网机构级别
    public function saveUser()
    {
        if (IS_POST) {
            UserLogService::HTwrite(3, '保存编辑包网机构级别', '保存编辑包网机构级别');
            
            $uid = session('admin_auth')['uid'];
            // //是否可以谷歌安全码验证
            // $verifyGoogle = adminGoogleBind($uid);
            // $auth_type = I('request.auth_type', 0, 'intval');
            // if ($verifyGoogle && $verifysms) {
            //     if (!in_array($auth_type, [0, 1])) {
            //         $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！"]);
            //     }
            // } elseif ($verifyGoogle && !$verifysms) {
            //     if ($auth_type != 1) {
            //         $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！"]);
            //     }
            // } elseif (!$verifyGoogle && $verifysms) {
            //     if ($auth_type != 0) {
            //         $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！"]);
            //     }
            // }
            // if ($verifyGoogle && $auth_type == 1) {//谷歌安全码验证
            //     $res = check_auth_error($uid, 5);
            //     if (!$res['status']) {
            //         $this->ajaxReturn(['status' => 0, 'msg' => $res['msg']]);
            //     }
            //     $google_code = I('request.google_code');
            //     if (!$google_code) {
            //         $this->ajaxReturn(['status' => 0, 'msg' => "谷歌安全码不能为空！"]);
            //     } else {
            //         $ga = new \Org\Util\GoogleAuthenticator();
            //         $google_secret_key = M('Admin')->where(['id' => $uid])->getField('google_secret_key');
            //         if (!$google_secret_key) {
            //             $this->ajaxReturn(['status' => 0, 'msg' => "您未绑定谷歌身份验证器！"]);
            //         }
            //         if (false === $ga->verifyCode($google_secret_key, $google_code, C('google_discrepancy'))) {
            //             log_auth_error($uid, 5);
            //             $this->ajaxReturn(['status' => 0, 'msg' => "谷歌安全码错误！"]);
            //         } else {
            //             clear_auth_error($uid, 5);
            //         }
            //     }
            // } elseif ($verifysms && $auth_type == 0) {//短信验证码
            //     $res = check_auth_error($uid, 3);
            //     if (!$res['status']) {
            //         $this->ajaxReturn(['status' => 0, 'msg' => $res['msg']]);
            //     }
            //     $code = I('request.code');
            //     if (!$code) {
            //         $this->ajaxReturn(['status' => 0, 'msg' => "短信验证码不能为空！"]);
            //     } else {
            //         if (session('send.setOrderPaidSend') != $code || !$this->checkSessionTime('setOrderPaidSend', $code)) {
            //             log_auth_error($uid, 3);
            //             $this->ajaxReturn(['status' => 0, 'msg' => '验证码错误']);
            //         } else {
            //             clear_auth_error($uid, 3);
            //             session('send', null);
            //         }
            //     }
            // }
            
            $userid = I('post.userid', 0, 'intval');
            $u = I('post.u/a');
            
            if ($userid) {
                $res = M('Agency')->where(['id' => $userid])->save($u);
                UserLogService::HTwrite(3, '编辑包网机构级别成功', '编辑包网机构（' . $userid . '）级别成功');
            } else {
                $has_user = M('Agency')->where(['username' => $u['username']])->find();
                if ($has_user) {
                    if ($has_user['username'] == $u['username']) {
                        $this->ajaxReturn(array("status" => 0, "msg" => '包网机构名已存在'));
                    }
                }
                // 创建包网机构
                $res = M('Agency')->add($u);
                UserLogService::HTwrite(2, '创建包网机构成功', '创建包网机构（' . $res . '）成功');
            }

            if ($res !== false) {
                $this->ajaxReturn(['status' => 1]);
            } else {
                UserLogService::HTwrite(3, '编辑包网机构级别失败', '编辑包网机构（' . $userid . '）级别失败');
                $this->ajaxReturn(['status' => 0]);
            }
        }
    }

        
    //批量编辑用户默认代付通道
    public function editUserAgency()
    {
        $where = [
            'status' => 1,
            ];
        $userList = M('Member')->where($where)->order('id desc')->select();
        $this->assign('userList', $userList);
        $this->display();
    }
    
    //保存编辑默认用户代付通道
    public function saveUserAgency()
    {
        UserLogService::HTwrite(3, '分配包网的用户', '分配包网的用户');
        if (IS_POST) {
            $ids = I('post.ids');
            $agency_id = I('post.agency_id');
            
            $up_agency = M('Member')->where(['agency_id' => $agency_id])->save(['agency_id' => '']);
            $ids_arr = array_filter(explode(',', $ids));
            if(!empty($ids_arr)){
                foreach ($ids_arr as $v){
                    $data_update = ['agency_id' => $agency_id];
                    $product = M('Member')->where(['id' => $v])->find();
                    if($product){
                        $up_arr = M('Member')->where(['id' => $v])->save($data_update);
                        if ($up_arr) UserLogService::HTwrite(3, '分配包网的用户', '修改包网（' . $agency_id . '）的用户：' . $v);
                    }
                }
                $this->ajaxReturn(['status' => 1]);
            }
            $this->ajaxReturn(['status' => 0]);
        }
    }
}
