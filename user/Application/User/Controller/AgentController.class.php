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

/** 商家代理控制器
 * Class DailiController
 * @package User\Controller
 */
class AgentController extends UserController
{

    public function __construct()
    {
        parent::__construct();
        if ($this->fans['groupid'] == 4) {
            $this->error('没有权限！');
        }
    }

    /**
     * 邀请码
     */
    public function invitecode()
    {
        if (!$this->siteconfig['invitecode']) {
            $this->error('邀请码功能已关闭');
        }
        UserLogService::write(1, '访问邀请码列表', '访问邀请码列表');
        $invitecode = I("get.invitecode", '', 'string,strip_tags,htmlspecialchars');
        $syusername = I("get.syusername", '', 'string,strip_tags,htmlspecialchars');
        $status = I("get.status", '', 'string,strip_tags,htmlspecialchars');
        if (!empty($invitecode)) {
            $where['invitecode'] = ["like", "%" . $invitecode . "%"];
        }
        if (!empty($syusername)) {
            $syusernameid = M("Member")->where(['username' => $syusername])->getField("id");
            $where['syusernameid'] = $syusernameid;
        }
        $regdatetime = urldecode(I("request.regdatetime", '', 'string,strip_tags,htmlspecialchars'));
        if ($regdatetime) {
            list($cstime, $cetime) = explode('|', $regdatetime);
            $where['fbdatetime'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
        }
        if (!empty($status)) {
            $where['status'] = $status;
        }
        $where['fmusernameid'] = $this->fans['uid'];
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
        foreach ($list as $k => $v) {
            $list[$k]['groupname'] = $this->groupId[$v['regtype']];
        }

        $this->assign("list", $list);
        $this->assign('page', $page->show());
        //取消令牌
        C('TOKEN_ON', false);
        $this->display();
    }

    /**
     * 添加邀请码
     */
    public function addInvite()
    {
        if (!$this->siteconfig['invitecode']) {
            $this->error('邀请码功能已关闭');
        }
        UserLogService::write(1, '访问添加邀请码页面', '访问添加邀请码页面');
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
        if (!$this->siteconfig['invitecode']) {
            $this->error('邀请码功能已关闭');
        }
        $invitecodestr = random_str(C('INVITECODE')); //生成邀请码的长度在Application/Commom/Conf/config.php中修改
        $Invitecode = M("Invitecode");
        $id = $Invitecode->where(['invitecode' => $invitecodestr])->getField("id");
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
            if (!$this->siteconfig['invitecode']) {
                UserLogService::write(2, '添加邀请码失败', '原因：邀请码功能已关闭');
                $this->ajaxReturn(['status' => 0, 'msg' => '邀请码功能已关闭']);
            }
            $invitecode = I('post.invitecode');
            $yxdatetime = I('post.yxdatetime');
            $regtype = I('post.regtype');
            $Invitecode = M("Invitecode");

            //只能添加比自己等级低的商户
            if ($regtype >= $this->fans['groupid']) {
                UserLogService::write(2, '添加邀请码失败', '原因：注册类型错误');
                $this->error('没有权限');
            }

            $_formdata = array(
                'invitecode' => $invitecode,
                'yxdatetime' => strtotime($yxdatetime),
                'regtype' => $regtype,
                'fmusernameid' => $this->fans['uid'],
                'inviteconfigzt' => 1,
                'fbdatetime' => time(),
            );
            $result = $Invitecode->add($_formdata);
            if (FALSE !== $result) {
                UserLogService::write(2, '添加邀请码成功', 'ID：' . $result);
                $this->ajaxReturn(['status' => 1]);
            } else {
                UserLogService::write(2, '添加邀请码失败', '添加邀请码失败');
                $this->ajaxReturn(['status' => 0]);
            }

        }
    }

    /**
     * 删除邀请码
     */
    public function delInvitecode()
    {
        if (IS_POST) {
            $id = I('post.id', 0, 'intval');
            $res = M('Invitecode')->where(['id' => $id, 'fmusernameid' => $this->fans['uid'], 'is_admin' => 0])->delete();
            if (FALSE !== $res) {
                UserLogService::write(4, '删除邀请码成功', 'ID：' . $id);
                $this->ajaxReturn(['status' => 1]);
            } else {
                UserLogService::write(4, '删除邀请码失败', 'ID：' . $id);
                $this->ajaxReturn(['status' => 0]);
            }
        }
    }

    /**
     * 下级会员
     */
    public function member()
    {
        UserLogService::write(1, '访问下级商户列表', '访问下级商户列表');
        $where['groupid'] = ['neq', 1];
        $username = I("get.username", '', 'string,strip_tags,htmlspecialchars');
        $status = I("get.status", '', 'string,strip_tags,htmlspecialchars');
        $authorized = I("get.authorized", '', 'string,strip_tags,htmlspecialchars');
        $regdatetime = I('get.regdatetime', '', 'string,strip_tags,htmlspecialchars');
        if (!empty($username) && !is_numeric($username)) {
            $where['username'] = ['like', "%" . $username . "%"];
        } elseif (intval($username) - 10000 > 0) {
            $where['id'] = intval($username) - 10000;
        }
        if (!empty($status)) {
            $where['status'] = $status;
        }
        if (!empty($authorized)) {
            $where['authorized'] = $authorized;
        }
        $where['parentid'] = $this->fans['uid'];
        if ($regdatetime) {
            list($starttime, $endtime) = explode('|', $regdatetime);
            $where['regdatetime'] = ["between", [strtotime($starttime), strtotime($endtime)]];
        }

        //查询下级
        $pwhere['parentid'] = $this->fans['uid'];

        $userlist = M('Member')->where($pwhere)->field('id')->select();
        foreach ($userlist as $key => $val) {
            $where_zj[] = $val['id'];
        }
        $where_zj[] = $this->fans['uid'];

        $where['parentid'] = array('in', $where_zj);


        //$where['parentid'] = $this->fans['uid'];
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
        foreach ($list as $key => $value) {
            $list[$key]['pname'] = M('member')->where(array('id' => $value['parentid']))->getField('username');
            $product_list = M('ProductUser')
            ->join('LEFT JOIN __PRODUCT__ ON __PRODUCT__.id = __PRODUCT_USER__.pid')
            ->where(['pay_product_user.userid'=>$value['id'],'pay_product_user.status'=>1,'pay_product.isdisplay'=>1])
            ->field('pay_product.name,pay_product.id,pay_product.t0defaultrate,pay_product.t0fengding,pay_product.defaultrate,pay_product.fengding,pay_product_user.status')
            ->select();
            if(!empty($product_list)) {
                foreach ($product_list as $k => $item) {
                    $_userrate = M('Userrate')->where(['userid' => $value['id'], 'payapiid' => $item['id']])->find();
                    $list[$key]['rate'][$item['id']] = $item;
                    
                    $feilv = $_userrate['feilv'];
                    if ($this->fans['groupid'] != 4) {
                        $list[$key]['rate'][$item['id']]['feilv'] = $_userrate['feilv'] ? $_userrate['feilv']*100 : $item['defaultrate']*100;
                    } else {
                        $list[$key]['rate'][$item['id']]['feilv'] = $feilv*100;
                    }
                }
            }
        }
        //已开通通道
        $plist = M('ProductUser')
            ->join('LEFT JOIN __PRODUCT__ ON __PRODUCT__.id = __PRODUCT_USER__.pid')
            ->where(['pay_product_user.userid'=>$this->fans['uid'],'pay_product.isdisplay'=>1])
            ->field('pay_product.name,pay_product.id,pay_product.t0defaultrate,pay_product.t0fengding,pay_product.defaultrate,pay_product.fengding,pay_product_user.status')
            ->select();
        if(!empty($plist)) {
            foreach ($plist as $key => $item) {
                $_userrate = M('Userrate')->where(['userid' => $this->fans['uid'], 'payapiid' => $item['id']])->find();
                $feilv = $_userrate['feilv'];
                if ($this->fans['groupid'] != 4) {
                    $plist[$key]['feilv'] = $_userrate['feilv'] ? $_userrate['feilv']*100 : $item['defaultrate']*100;
                } else {
                    $plist[$key]['feilv'] = $feilv*100;
                }
            }
        }
        $this->assign("rows", $rows);
        $this->assign("list", $list);
        $this->assign("plist", $plist);
        $this->assign('page', $page->show());
        //取消令牌
        C('TOKEN_ON', false);
        $this->display();
    }

    //导出用户
    public function exportuser()
    {
        UserLogService::write(5, '导出下级商户列表', '导出下级商户列表');
        $username = I("get.username", '', 'string,strip_tags,htmlspecialchars');
        $status = I("get.status", '', 'string,strip_tags,htmlspecialchars');
        $authorized = I("get.authorized", '', 'string,strip_tags,htmlspecialchars');
        $groupid = I("get.groupid", '', 'string,strip_tags,htmlspecialchars');

        if (is_numeric($username)) {
            $map['id'] = array('eq', intval($username) - 10000);
        } else {
            $map['username'] = array('like', '%' . $username . '%');
        }
        if ($status) {
            $map['status'] = array('eq', $status);
        }
        if ($authorized) {
            $map['authorized'] = array("eq", $authorized);
        }
        $map['parentid'] = array('eq', session('user_auth.uid'));
        $regdatetime = urldecode(I("request.regdatetime", '', 'string,strip_tags,htmlspecialchars'));
        if ($regdatetime) {
            list($cstime, $cetime) = explode('|', $regdatetime);
            $map['regdatetime'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
        }

        $map['groupid'] = $groupid ? array('eq', $groupid) : array('neq', 0);

        $title = array('用户名', '商户号', '用户类型', '上级用户名', '状态', '认证', '可用余额', '冻结余额', '注册时间');
        $data = M('Member')
            ->where($map)
            ->select();
        foreach ($data as $item) {
            switch ($item['groupid']) {
                case 4:
                    $usertypestr = '商户';
                    break;
                case 5:
                    $usertypestr = '代理商';
                    break;
            }
            switch ($item['status']) {
                case 0:
                    $userstatus = '未激活';
                    break;
                case 1:
                    $userstatus = '正常';
                    break;
                case 2:
                    $userstatus = '已禁用';
                    break;
            }
            switch ($item['authorized']) {
                case 1:
                    $rzstauts = '已认证';
                    break;
                case 0:
                    $rzstauts = '未认证';
                    break;
                case 2:
                    $rzstauts = '等待审核';
                    break;
            }
            $list[] = array(
                'username' => $item['username'],
                'userid' => $item['id'] + 10000,
                'groupid' => $usertypestr,
                'parentid' => getParentName($item['parentid'], 1),
                'status' => $userstatus,
                'authorized' => $rzstauts,
                'total' => $item['balance'],
                'block' => $item['blockedbalance'],
                'regdatetime' => date('Y-m-d H:i:s', $item['regdatetime']),
            );
        }

        $numberField = ['total'];
        exportCsv($list, $title);
        // exportexcel($list, $title, $numberField);
    }

    //用户状态切换
    public function editStatus()
    {
        if (IS_POST) {
            $userid = I('post.uid', 0, 'intval');
            $member = M('Member')->where(['id' => $userid])->find();
            if (empty($member)) {
                UserLogService::write(3, '下级商户状态切换失败', '原因：用户不存在');
                $this->error('用户不存在！');
            }
            if ($member['parentid'] != $this->fans['uid']) {
                UserLogService::write(3, '下级商户状态切换失败', '原因：没有权限查');
                $this->error('您没有权限查切换该用户状态！');
            }

            $isstatus = I('post.isopen', 0, 'intval');
            $res = M('Member')->where(['id' => $userid])->save(['status' => $isstatus]);
            if (FALSE !== $res) {
                if ($isstatus > 0) {
                    $status_text = '激活';
                } else {
                    $status_text = '未激活';
                }
                UserLogService::write(3, '下级商户状态切换成功', '用户ID：' . $userid . '，状态：' . $status_text);
                $this->ajaxReturn(['status' => 1]);
            } else {
                UserLogService::write(3, '下级商户状态切换失败', '下级商户状态切换失败');
                $this->ajaxReturn(['status' => 0]);
            }
        }
    }

    /**
     * 下级费率设置
     */
    public function userRateEdit()
    {
        //需要加载代理所有开放
        //$this->fans['uid'];
        $userid = I('get.uid', 0, 'intval');
        $member = M('Member')->where(['id' => $userid])->find();
        if (empty($member)) {
            $this->error('用户不存在！');
        }
        if ($member['parentid'] != $this->fans['uid']) {
            $this->error('您没有权限查对该用户进行费率设置！');
        }
        UserLogService::write(1, '访问下级商户费率设置页面', 'ID：' . $userid);
        //系统产品列表
        $products = M('Product')
            ->join('LEFT JOIN __PRODUCT_USER__ ON __PRODUCT_USER__.pid = __PRODUCT__.id')
            ->where(['pay_product.status' => 1, 'pay_product.isdisplay' => 1, 'pay_product_user.userid' => $userid, 'pay_product_user.status' => 1])
            ->field('pay_product.id,pay_product.name,pay_product_user.status')
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
                $products[$key]['t0feilv'] = $_tmpData[$item['id']]['t0feilv'] ? $_tmpData[$item['id']]['t0feilv'] : '0.000';
                $products[$key]['t0fengding'] = $_tmpData[$item['id']]['t0fengding'] ? $_tmpData[$item['id']]['t0fengding'] : '0.000';
                $products[$key]['feilv'] = $_tmpData[$item['id']]['feilv'] ? $_tmpData[$item['id']]['feilv'] : '0.000';
                $products[$key]['fengding'] = $_tmpData[$item['id']]['fengding'] ? $_tmpData[$item['id']]['fengding'] : '0.000';
            }
        }

        $this->assign('products', $products);
        $this->display();
    }

    //保存费率
    public function saveUserRate()
    {
        if (IS_POST) {
            $userid = I('post.userid', 0, 'intval');
            $member = M('Member')->where(['id' => $userid])->find();
            if (empty($member)) {
                UserLogService::write(3, '保存下级商户费率失败', '原因：用户不存在');
                $this->error('用户不存在！');
            }
            if ($member['parentid'] != $this->fans['uid']) {
                UserLogService::write(3, '保存下级商户费率失败', '原因：没有权限');
                $this->error('您没有权限对该用户进行费率设置！');
            }
            $rows = I('post.u/a');
            $datalist = [];
            foreach ($rows as $key => $item) {
                $agent_rate = M('Userrate')->where(['userid' => $this->fans['uid'], 'payapiid' => $key])->find();
                $product = M('Product')->where(['id' => $key])->find();
                if (!$agent_rate['feilv']) {
                    $agent_rate['feilv'] = $product['defaultrate'];
                    $agent_rate['fengding'] = $product['fengding'];
                }
                if (!$agent_rate['t0feilv']) {
                    $agent_rate['t0feilv'] = $product['t0defaultrate'];
                    $agent_rate['t0fengding'] = $product['t0fengding'];
                }
                if ($item['feilv'] >= 1 || $item['feilv'] == 0 || $item['t0feilv'] >= 1 || $item['t0feilv'] == 0 || $item['t0fengding'] >= 1 || $item['t0fengding'] == 0 || $item['fengding'] >= 1 || $item['fengding'] == 0) {
                    UserLogService::write(3, '保存下级商户费率失败', '原因：存在无效费率');
                    $this->ajaxReturn(['status' => 0, 'msg' => '存在无效费率！']);
                }
                if ($item['feilv'] < $agent_rate['feilv']) {
                    UserLogService::write(3, '保存下级商户费率失败', '原因：T+1费率不能低于代理成本');
                    $this->ajaxReturn(['status' => 0, 'msg' => 'T+1费率不能低于代理成本！']);
                }
                if ($item['t0feilv'] < $agent_rate['t0feilv']) {
                    UserLogService::write(3, '保存下级商户费率失败', '原因：T+0费率不能低于代理成本');
                    $this->ajaxReturn(['status' => 0, 'msg' => 'T+0费率不能低于代理成本！']);
                }
                if ($item['fengding'] > $agent_rate['fengding']) {
                    UserLogService::write(3, '保存下级商户费率失败', '原因：T+1封顶费率不能高于代理封顶费率');
                    $this->ajaxReturn(['status' => 0, 'msg' => 'T+1封顶费率不能高于代理封顶费率！']);
                }
                if ($item['t0fengding'] > $agent_rate['t0fengding']) {
                    UserLogService::write(3, '保存下级商户费率失败', '原因：T+0封顶费率不能高于代理封顶费率');
                    $this->ajaxReturn(['status' => 0, 'msg' => 'T+0封顶费率不能高于代理封顶费率！']);
                }
                $rates = M('Userrate')->where(['userid' => $userid, 'payapiid' => $key])->find();
                if ($rates) {
                    $datalist[] = ['id' => $rates['id'], 'userid' => $userid, 'payapiid' => $key, 'feilv' => $item['feilv'], 'fengding' => $item['fengding'], 't0feilv' => $item['t0feilv'], 't0fengding' => $item['t0fengding']];
                } else {
                    $datalist[] = ['userid' => $userid, 'payapiid' => $key, 'feilv' => $item['feilv'], 'fengding' => $item['fengding'], 't0feilv' => $item['t0feilv'], 't0fengding' => $item['t0fengding']];
                }
            }
            M('Userrate')->addAll($datalist, [], true);
            UserLogService::write(3, '保存下级商户费率成功', '用户ID：' . $userid);
            $this->ajaxReturn(['status' => 1]);
        }
    }

    public function checkUserrate()
    {
        if (IS_POST) {
            $pid = I('post.pid', 0, 'intval');
            $rate = I('post.feilv');
            $t = I('post.t', 1);
            if ($pid) {
                $field = $t == 0 ? 't0feilv' : 'feilv';
                $selffeilv = M('Userrate')->where(['userid' => $this->fans['uid'], 'payapiid' => $pid])->getField($field);
                if (($selffeilv * 1000) >= ($rate * 1000)) {
                    $this->ajaxReturn(['status' => 1]);
                }
            }
        }
    }

    //下级流水
    public function childord()
    {
        $userid = I('get.userid', 0, 'intval');
        if (!$userid) {
            $this->error('缺少参数！');
        }
        //绑定的产品和通道
        $Product_user = M('Product_user')->join('LEFT JOIN __PRODUCT__ ON __PRODUCT__.id = __PRODUCT_USER__.pid')->where(['pay_product_user.userid' => $userid, 'pay_product.isdisplay' => 1])->select();
        $this->assign("banklist", $Product_user);

        //银行
        $tongdaolist = M("Channel")->field('id,code,title')->select();
        $this->assign("tongdaolist", $tongdaolist);


        $member = M('Member')->where(['id' => $userid])->find();
        if (empty($member)) {
            $this->error('用户不存在！');
        }
        if ($member['parentid'] != $this->fans['uid']) {
            $this->error('您没有权限查看该用户信息！');
        }
        UserLogService::write(1, '访问下级商户流水页面', 'ID：' . $userid);
        $userid = $userid + 10000;
        $data = array();

        $where = array('pay_memberid' => $userid);
        //商户号
        $memberid = I("request.memberid");
        if ($memberid) {
            $where['pay_memberid'] = $memberid;
        }

        $tongdao = I("request.tongdao", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($tongdao) {
            $where['channel_id'] = $tongdao;
            $poundageMap['channel_id'] = $tongdao;
        }

        $bank = I("request.bank", '', 'trim,string,strip_tags,htmlspecialchars');
        if ($bank) {
            $where['pay_bankcode'] = $bank;
            $poundageMap['tongdao'] = $bank;
        }


        //提交时间
        $createtime = urldecode(I("request.createtime"));
        if ($createtime) {
            list($cstime, $cetime) = explode('|', $createtime);
            $where['pay_applydate'] = $poundageMap['datetime'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];

            $poundageMap['datetime'] = ['between', [$cstime, $cetime ? $cetime : time()]];
        }
        //成功时间
        $successtime = urldecode(I("request.successtime"));
        if ($successtime) {
            list($sstime, $setime) = explode('|', $successtime);
            $where['pay_successdate'] = $poundageMap['datetime'] = ['between', [strtotime($sstime), strtotime($setime) ? strtotime($setime) : time()]];
            $poundageMap['datetime'] = ['between', [$sstime, $setime ? $setime : time()]];
        }
        //查询下级数据
        $where['pay_status'] = ['in', '1,2'];
        $statistic = M('Order')->field(['sum(`pay_amount`) pay_amount, sum(`pay_poundage`) pay_poundage, sum(`pay_actualamount`) pay_actualamount, sum(`cost`) cost'])->where($where)->find();

        //代理分润
        $poundageMap['tcuserid'] = $userid - 10000;
        $poundageMap['userid'] = $this->fans['uid'];
        $poundageMap['lx'] = 9;
        $pay_poundage = M('moneychange')->join('LEFT JOIN __ORDER__ ON __ORDER__.pay_orderid = __MONEYCHANGE__.transid')->where($poundageMap)->sum('money');
        //平台分润
        $paltform_amount = $statistic['pay_amount'] - $statistic['pay_actualamount'] - $statistic['cost'] - $pay_poundage;
//        $poundage_cost = $pay_poundage + $statistic['cost'];
        $this->assign('pay_amount', number_format($statistic['pay_amount'], 2));
         $this->assign('pay_poundage', number_format($pay_poundage, 2));
//        $this->assign('pay_poundage', number_format($poundage_cost, 2));
        $this->assign('pay_actualamount', number_format($statistic['pay_actualamount'], 2));
        $this->assign('paltform_amount', number_format($paltform_amount, 2));
        //分页
        $count = M('Order')->where($where)->count();
        //成功率
        $where_lv = $where;
        unset($where_lv['pay_status']);
        $all_count = M('Order')->where($where_lv)->count();
        $sucees_lv = sprintf("%.2f", ($count / $all_count));

        $Page = new Page($count, 10);
        $data = M('Order')->join('LEFT JOIN __MEMBER__ ON __MEMBER__.id+10000 = __ORDER__.pay_memberid')->where($where)->field('pay_order.*, pay_member.username')->limit($Page->firstRow . ',' . $Page->listRows)->order(['id' => 'desc'])->select();
        $show = $Page->show();
        $this->assign('sucees_lv', $sucees_lv);
        $this->assign('list', $data);
        $this->assign('page', $show);
        $this->display();
    }

    // public function addUser()
    // {
    //     $this->display();
    // }

    // /**
    //  * 生成用户
    //  */
    // public function saveUser()
    // {
    //     $u = I('post.u/a');
    //     $u['username'] = trim($u['username']);
    //     $u['email'] = trim($u['email']);
    //     $u['birthday'] = strtotime($u['birthday']);

    //     $has_user = M('member')->where(['username' => $u['username'], 'email' => $u['email'], '_logic' => 'or'])->find();
    //     if ($has_user) {
    //         if ($has_user['username'] == $u['username']) {
    //             UserLogService::write(2, '添加用户失败', '原因：用户名已存在');
    //             $this->ajaxReturn(array("status" => 0, "msg" => '用户名已存在'));
    //         }
    //         if ($has_user['email'] == $u['email']) {
    //             UserLogService::write(2, '添加用户失败', '原因：邮箱已存在');
    //             $this->ajaxReturn(array("status" => 0, "msg" => '邮箱已存在'));
    //         }
    //     }
    //     $current_user = session('user_auth');
    //     $siteconfig = M("Websiteconfig")->find();
    //     $u = generateUser($u, $siteconfig);

    //     $s['activatedatetime'] = date("Y-m-d H:i:s");
    //     $u['parentid'] = $current_user['uid'];
    //     // $u['groupid'] = $current_user['groupid'];

    //     // 创建用户
    //     $res = M('Member')->add($u);

    //     // 发邮件通知用户密码
    //     // sendPasswordEmail($u['username'], $u['email'], $u['origin_password'], $siteconfig);
    //     if (FALSE !== $res) {
    //         UserLogService::write(2, '添加用户成功', 'ID：' . $res);
    //         $this->ajaxReturn(['status' => 1]);
    //     } else {
    //         UserLogService::write(2, '添加用户失败', '添加用户失败');
    //         $this->ajaxReturn(['status' => 0]);
    //     }
    // }

    /**
     * 下级商户订单
     */
    public function order()
    {
        UserLogService::write(1, '访问下级商户订单页面', '访问下级商户订单页面');
        $createtime = urldecode(I('get.createtime'));
        $successtime = urldecode(I("request.successtime"));
        $memberid = I("request.memberid");
        $body = I("request.body");
        $orderid = I("request.orderid");
        if ($memberid) {
            $where['pay_memberid'] = array('eq', $memberid);
        }
        $this->assign('memberid', $memberid);
        if ($orderid) {
            $where['out_trade_id'] = $orderid;
        }
        $this->assign('orderid', $orderid);
        if ($createtime) {
            list($starttime, $endtime) = explode('|', $createtime);
            $where['pay_applydate'] = ["between", [strtotime($starttime), strtotime($endtime)]];
        }
        $this->assign('createtime', $createtime);
        if ($successtime) {
            list($starttime, $endtime) = explode('|', $successtime);
            $where['pay_successdate'] = ["between", [strtotime($starttime), strtotime($endtime)]];
        }
        $this->assign('successtime', $successtime);
        if ($body) {
            $where['pay_productname'] = array('eq', $body);
        }
        $this->assign('body', $body);
        /*
        $status = I("request.status",0,'intval');
        if ($status) {
            $where['pay_status'] = array('eq',$status);
        }
        */
        $where['pay_status'] = array('in', '1,2');
        $pay_memberid = [];
        $user_id = M('Member')->where(['parentid' => $this->fans['uid']])->getField('id', true);
        $size = 15;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }
        if ($user_id) {
            foreach ($user_id as $k => $v) {
                array_push($pay_memberid, $v + 10000);
            }
            if (!$createtime and !$successtime) {
                //今日成功交易总额
                $todayBegin = date('Y-m-d') . ' 00:00:00';
                $todyEnd = date('Y-m-d') . ' 23:59:59';
                $stat['todaysum'] = M('Order')->where(['pay_memberid' => ['in', $pay_memberid], 'pay_successdate' => ['between', [strtotime($todayBegin), strtotime($todyEnd)]], 'pay_status' => ['in', '1,2']])->sum('pay_amount');
                echo M('Order')->getLastSql();
                //今日成功笔数
                $stat['todaysuccesscount'] = M('Order')->where(['pay_memberid' => ['in', $pay_memberid], 'pay_successdate' => ['between', [strtotime($todayBegin), strtotime($todyEnd)]], 'pay_status' => ['in', '1,2']])->count();
                //总成功交易总额
                $totalMap['pay_memberid'] = ['in', $pay_memberid];
                $totalMap['pay_status'] = ['in', '1,2'];
                $stat['totalsum'] = M('Order')->where($totalMap)->sum('pay_amount');
                //总成功笔数
                $stat['totalsuccesscount'] = M('Order')->where($totalMap)->count();
                foreach ($stat as $k => $v) {
                    $stat[$k] = $v + 0;
                }
                $this->assign('stat', $stat);
            }
            if ($memberid) {
                if (in_array($memberid, $pay_memberid)) {
                    $where['pay_memberid'] = $memberid;
                } else {
                    $where['pay_memberid'] = 1;
                }
            } else {
                $where['pay_memberid'] = ['in', $pay_memberid];
            }
            //如果指定时间范围则按搜索条件做统计
            if ($createtime || $successtime) {
                $sumMap = $where;
                $field = ['sum(`pay_amount`) pay_amount', 'sum(`pay_actualamount`) pay_actualamount', 'count(`id`) success_count'];
                $sum = M('Order')->field($field)->where($sumMap)->find();
                foreach ($sum as $k => $v) {
                    $sum[$k] += 0;
                }
                $this->assign('sum', $sum);
            }
            //分页
            $count = M('Order')->where($where)->count();
            $Page = new Page($count, $rows);
            $data = M('Order')->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->order(['id' => 'desc'])->select();
        } else {
            $stat['todaysum'] = $stat['todaysuccesscount'] = $stat['totalsum'] = $stat['totalsuccesscount'] = 0;
            $count = 0;
            $Page = new Page($count, $rows);
            $data = [];
        }
        $show = $Page->show();
        $this->assign('list', $data);
        $this->assign('page', $show);
        //取消令牌
        C('TOKEN_ON', false);
        $this->display();
    }

    /**
     * 导出交易订单
     * */
    public function exportorder()
    {
        UserLogService::write(5, '导出下级商户订单', '导出下级商户订单');
        $createtime = urldecode(I('get.createtime'));
        $successtime = urldecode(I("request.successtime"));
        $memberid = I("request.memberid");
        $body = I("request.body", '', 'strip_tags');
        $orderid = I("request.orderid");
        if ($memberid) {
            $where['pay_memberid'] = array('eq', $memberid);
        }
        if ($orderid) {
            $where['out_trade_id'] = $orderid;
        }
        if ($createtime) {
            list($starttime, $endtime) = explode('|', $createtime);
            $where['pay_applydate'] = ["between", [strtotime($starttime), strtotime($endtime)]];
        }
        if ($successtime) {
            list($starttime, $endtime) = explode('|', $successtime);
            $where['pay_successdate'] = ["between", [strtotime($starttime), strtotime($endtime)]];
        }
        if ($body) {
            $where['pay_productname'] = array('eq', $body);
        }
        $status = I("request.status", 0, 'intval');
        if ($status) {
            $where['pay_status'] = array('eq', $status);
        }
        $where['pay_status'] = array('in', '1,2');
        $pay_memberid = [];
        $user_id = M('Member')->where(['parentid' => $this->fans['uid']])->getField('id', true);
        if ($user_id) {
            foreach ($user_id as $k => $v) {
                array_push($pay_memberid, $v + 10000);
            }
            if ($memberid) {
                if (in_array($memberid, $pay_memberid)) {
                    $where['pay_memberid'] = $memberid;
                } else {
                    $where['pay_memberid'] = 1;
                }
            } else {
                $where['pay_memberid'] = ['in', $pay_memberid];
            }
            $data = M('Order')->where($where)->order(['id' => 'desc'])->select();
        } else {
            $data = [];
        }
        $title = array('订单号', '商户编号', '交易金额', '手续费', '实际金额', '提交时间', '成功时间', '通道', '状态');
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
            $list[] = array(
                'pay_orderid' => $item['out_trade_id'] ? $item['out_trade_id'] : $item['pay_orderid'],
                'pay_memberid' => $item['pay_memberid'],
                'pay_amount' => $item['pay_amount'],
                'pay_poundage' => $item['pay_poundage'],
                'pay_actualamount' => $item['pay_actualamount'],
                'pay_applydate' => date('Y-m-d H:i:s', $item['pay_applydate']),
                'pay_successdate' => date('Y-m-d H:i:s', $item['pay_successdate']),
                'pay_zh_tongdao' => $item['pay_zh_tongdao'],
                'pay_status' => $status,
            );
        }
        $numberField = ['pay_amount', 'pay_poundage', 'pay_actualamount'];
        exportCsv($list, $title);
        // exportexcel($list, $title, $numberField);
    }

    public function withdrawal()
    {
        UserLogService::write(1, '子商户代付统计', '子商户代付统计');
        //查询下级
        $where = array();

         //查询下级
        $pwhere['parentid'] = $this->fans['uid'];

        $userlist = M('Member')->where($pwhere)->field('id')->select();
        foreach ($userlist as $key => $value) {
            $where_zj[] = $value['id'];
        }
        $currency = I("request.currency");
        if($currency ==='INR'){
            $where['paytype'] = ['eq', 4];
        }else{
            $currency ='PHP';
            $where['paytype'] = ['between', [1,3]];
        }
        $this->assign('currency', $currency);
        
        $memberid = I("request.memberid", '', 'string,strip_tags,htmlspecialchars');
        $this->assign("memberid", $memberid);
        if ($memberid) {
            $memberid = $memberid-10000;
            if (in_array($memberid, $where_zj)) {
                $where['userid'] = $memberid;
            } else {
                $where['userid'] = 1;
            }
        } else {
            $where['userid'] = ['in', $where_zj];
        }
        
        //U下发
        $type = I("request.type",  0, 'intval');
        if($type === 2){
            $where['df_type'] = ['eq', 2];
        }else{
            $where['df_type'] = ['neq', 2];
        }
        $this->assign('type', $type);
        
        // $dfid = I("get.dfid", '', 'string,strip_tags,htmlspecialchars');
        // if ($dfid != '') {
        //     $where['df_id'] = array('eq', $dfid);
        // }
        // $this->assign("dfid", $dfid);
        
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
        // $totalMap['status']   = array('between', ['2','3']);
        // $stat_total_profit = $Wttklist->getSum('sxfmoney,cost',$totalMap);
        // $stat['total_profit'] = round($stat_total_profit['sxfmoney'] - $stat_total_profit['cost'], 2);

        foreach ($stat as $k => $v) {
            $stat[$k] += 0;
        }
        
        $this->assign('stat', $stat);
        $this->assign("df_list", $df_list);
        $this->display();
        
        


        // $Wttklist = D('Wttklist');

        // $map['userid'] = array('in', $userarray);
        // //统计今日提款信息
        // $beginToday = date("Y-m-d") . ' 00:00:00';
        // $endToday = date("Y-m-d") . ' 23:59:59';
        // //今日提款总金额
        // $map['cldatetime'] = array('between', array($beginToday, $endToday));
        // $map['status'] = 3;
        // $stat_total_wait = $Wttklist->getSum('money',$map);
        // $stat['total_wait'] = round($stat_total_wait['money'], 4);
        // //今日提款成功笔数
        // $stat['total_success_count'] = $Wttklist->getCount($map);
        // //今日提款待结算
        // unset($map['cldatetime']);
        // $map['sqdatetime'] = array('between', array($beginToday, $endToday));
        // $map['status'] = ['in', '0,1'];
        // $stat_total_wait = $Wttklist->getSum('money',$map);
        // $stat['total_wait'] = round($stat_total_wait['money'], 4);
        // //今日提款失败笔数
        // $map['status'] = 5;
        // $stat['totay_fail_count'] = $Wttklist->getCount($map);
        // //统计汇总信息
        // //代付总金额
        // $totalMap = $where;
        // $totalMap['userid'] = array('in', $userarray);
        // $totalMap['status'] = 3;
        // $stat_total = $Wttklist->getSum('money',$totalMap);
        // $stat['total'] = round($stat_total['money'], 4);
        // //提款总待结算
        // $totalMap['status'] = ['in', '0,1'];
        // $stat_total_wait = $Wttklist->getSum('money',$totalMap);
        // $stat['total_wait'] = round($stat_total_wait['money'], 4);
        // //提款成功总笔数
        // $totalMap['status'] = 3;
        // $stat['total_success_count'] = $Wttklist->getCount($totalMap);
        // //提款失败总笔数
        // $totalMap['status'] = 5;
        // $stat['total_fail_count'] = $Wttklist->getCount($totalMap);

        // $this->assign('stat', $stat);
        // $this->assign("list", $list);
        // $this->assign("pfa_lists", $pfa_lists);
        // $this->assign("page", $page->show());
        // $this->assign("rows", $rows);
        // $this->display();
    }


    // public function withdrawal()
    // {
    //     UserLogService::write(1, '访问下级结算记录', '访问下级结算记录');
    //     //查询下级


    //     //通道
    //     $products = M('ProductUser')
    //         ->join('LEFT JOIN __PRODUCT__ ON __PRODUCT__.id = __PRODUCT_USER__.pid')
    //         ->where(['pay_product_user.status' => 1, 'pay_product_user.userid' => $this->fans['uid']])
    //         ->field('pay_product.name,pay_product.id,pay_product.code')
    //         ->select();
    //     $this->assign("banklist", $products);

    //     $where = array();
    //     $tongdao = I("request.tongdao", '', 'string,strip_tags,htmlspecialchars');
    //     if ($tongdao) {
    //         $where['payapiid'] = array('eq', $tongdao);
    //     }
    //     $this->assign("tongdao", $tongdao);
    //     $T = I("request.T", '', 'string,strip_tags,htmlspecialchars');
    //     if ($T != "") {
    //         $where['t'] = array('eq', $T);
    //     }
    //     $this->assign("T", $T);
    //     $bankfullname = I("request.bankfullname", '', 'string,strip_tags,htmlspecialchars');
    //     if ($bankfullname) {
    //         $where['bankfullname'] = array('eq', $bankfullname);
    //     }
    //     $this->assign("bankfullname", $bankfullname);
    //     $status = I("request.status", '', 'string,strip_tags,htmlspecialchars');
    //     if ($status != "") {
    //         $where['status'] = array('eq', $status);
    //     }
    //     $this->assign("status", $status);
    //     $createtime = urldecode(I("request.createtime", '', 'string,strip_tags,htmlspecialchars'));
    //     if ($createtime) {
    //         list($cstime, $cetime) = explode('|', $createtime);
    //         $where['sqdatetime'] = ['between', [$cstime, $cetime ? $cetime : date('Y-m-d')]];
    //     }
    //     $this->assign("createtime", $createtime);
    //     $successtime = urldecode(I("request.successtime", '', 'string,strip_tags,htmlspecialchars'));
    //     if ($successtime) {
    //         list($sstime, $setime) = explode('|', $successtime);
    //         $where['cldatetime'] = ['between', [$sstime, $setime ? $setime : date('Y-m-d')]];
    //     }
    //     $this->assign("successtime", $successtime);

    //     //获取高级商户的代付通道
    //     $pfa_list = M('PayForAnother')->where(['status' => 1])->select();
    //     $pfa_channel = M('Member')->where(['id' => $this->fans['uid']])->field('pfa_channel')->find();
    //     $pfa_channel_arr = explode(",", $pfa_channel['pfa_channel']);
    //     foreach ($pfa_channel_arr as $key => $value) {
    //         if ($value) {
    //             $pfa_where[] = $value;
    //         }
    //     }
    //     foreach ($pfa_list as $pfa_key => $pfa_value) {
    //         if (in_array($pfa_value['id'], $pfa_where)) {
    //             $pfa_lists[] = $pfa_value;
    //         }
    //     }


    //      //查询下级
    //     $pwhere['parentid'] = $this->fans['uid'];

    //     $userlist = M('Member')->where($pwhere)->field('id')->select();
    //     if($userlist){
    //         foreach ($userlist as $key => $value) {
    //             $where_zj[] = $value['id'];
    //         }

    //         //查询下级--下下级
    //         $pwhere1['parentid'] = array('in', $where_zj);
    //         $zj_userlist = M('Member')->where($pwhere1)->field('id')->select();
    //         $all_userlist = array_merge($userlist, $zj_userlist);

    //         foreach ($all_userlist as $key => $value) {
    //             $userarray[] = $value['id'];
    //         }
    //     }else{
    //         $userarray[0] = 1;
    //     }
    //     $where['userid'] = array('in', $userarray);
        
    //     $size = 15;
    //     if (!$rows) {
    //         $rows = $size;
    //     }
    //     $rows = I('get.rows', $size, 'intval');
        
    //     $Wttklist = D('Wttklist');
    //     $count = $Wttklist->getCount($where);
    //     $page = new Page($count, $rows);
    //     $list = $Wttklist->getOrderByDateRange('*', $where, $page->firstRow . ',' . $page->listRows, 'id desc');
        
    //     // $wttklist = M('wttklist');
    //     // $count = $wttklist->where($where)->count();
    //     // $page = new Page($count, $rows);
    //     // $list = $wttklist
    //     //     ->where($where)
    //     //     ->limit($page->firstRow . ',' . $page->listRows)
    //     //     ->order('id desc')
    //     //     ->select();
    //     $map['userid'] = array('in', $userarray);
    //     //统计今日提款信息
    //     $beginToday = date("Y-m-d") . ' 00:00:00';
    //     $endToday = date("Y-m-d") . ' 23:59:59';
    //     //今日提款总金额
    //     $map['cldatetime'] = array('between', array($beginToday, $endToday));
    //     $map['status'] = 3;
    //     $stat_total_wait = $Wttklist->getSum('money',$map);
    //     $stat['total_wait'] = round($stat_total_wait['money'], 4);
    //     // $stat['totay_total'] = round($wttklist->where($map)->sum('money'), 4);
    //     //今日提款成功笔数
    //     $stat['total_success_count'] = $Wttklist->getCount($map);
    //     // $stat['totay_success_count'] = $wttklist->where($map)->count();
    //     //今日提款待结算
    //     unset($map['cldatetime']);
    //     $map['sqdatetime'] = array('between', array($beginToday, $endToday));
    //     $map['status'] = ['in', '0,1'];
    //     $stat_total_wait = $Wttklist->getSum('money',$map);
    //     $stat['total_wait'] = round($stat_total_wait['money'], 4);
    //     // $stat['totay_wait'] = round($wttklist->where($map)->sum('money'), 4);
    //     //今日提款失败笔数
    //     $map['status'] = 5;
    //     $stat['totay_fail_count'] = $Wttklist->getCount($map);
    //     // $stat['totay_fail_count'] = $wttklist->where($map)->count();
    //     //统计汇总信息
    //     //代付总金额
    //     $totalMap = $where;
    //     $totalMap['userid'] = array('in', $userarray);
    //     $totalMap['status'] = 3;
    //     $stat_total = $Wttklist->getSum('money',$totalMap);
    //     $stat['total'] = round($stat_total['money'], 4);
    //     // $stat['total'] = round($wttklist->where($totalMap)->sum('money'), 4);
    //     //提款总待结算
    //     $totalMap['status'] = ['in', '0,1'];
    //     $stat_total_wait = $Wttklist->getSum('money',$totalMap);
    //     $stat['total_wait'] = round($stat_total_wait['money'], 4);
    //     // $stat['total_wait'] = round($wttklist->where($totalMap)->sum('money'), 4);
    //     //提款成功总笔数
    //     $totalMap['status'] = 3;
    //     $stat['total_success_count'] = $Wttklist->getCount($totalMap);
    //     // $stat['total_success_count'] = $wttklist->where($totalMap)->count();
    //     //提款失败总笔数
    //     $totalMap['status'] = 5;
    //     $stat['total_fail_count'] = $Wttklist->getCount($totalMap);
    //     // $stat['total_fail_count'] = $wttklist->where($totalMap)->count();

    //     $this->assign('stat', $stat);
    //     $this->assign("list", $list);
    //     $this->assign("pfa_lists", $pfa_lists);
    //     $this->assign("page", $page->show());
    //     $this->assign("rows", $rows);
    //     $this->display();
    // }


    /**
     *  批量委托提现
     */
    public function editwtAllStatus()
    {

        $ids = I('post.id', '');
        $ids = explode(',', trim($ids, ','));
        $status = I('post.status', 0, 'intval');
        if ($status != 3 && $status != 2) {
            $this->ajaxReturn(['status' => 0, 'msg' => '参数错误']);
        }
        $Tklist = M("wttklist");
        $success = $fail = 0;
        if ($status == 3) {
//一键驳回
            foreach ($ids as $k => $v) {
                try {
                    M()->startTrans();
                    if (intval($v)) {
                        $withdraw = $Tklist->where(['id' => $v])->find();
                        if (empty($withdraw)) {
                            M()->rollback();
                            $fail++;
                            continue;
                        }
                        if ($withdraw['status'] == 1) {
//提款申请处理中，不能驳回
                            M()->rollback();
                            $fail++;
                            continue;
                        } elseif ($withdraw['status'] == 2) {
//提款申请已打款，不能驳回
                            M()->rollback();
                            $fail++;
                            continue;
                        } elseif ($withdraw['status'] == 3) {
//提款申请已驳回，不能驳回
                            M()->rollback();
                            $fail++;
                            continue;
                        }
                        $map['status'] = 0;
                        //驳回操作
                        //1,将金额返回给商户
                        $Member = M('Member');
                        $memberInfo = $Member->where(['id' => $withdraw['userid']])->lock(true)->find();
                        $res = $Member->where(['id' => $withdraw['userid']])->save(['balance' => array('exp', "balance+{$withdraw['tkmoney']}")]);
                        if (!$res) {
                            M()->rollback();
                            $fail++;
                            continue;
                        }
                        //2,记录流水订单号
                        $arrayField = array(
                            "userid" => $withdraw['userid'],
                            "ymoney" => $memberInfo['balance'],
                            "money" => $withdraw['tkmoney'],
                            "gmoney" => $memberInfo['balance'] + $withdraw['tkmoney'],
                            "datetime" => date("Y-m-d H:i:s"),
                            "tongdao" => 0,
                            "transid" => $v,
                            "orderid" => $v,
                            "lx" => 11,
                            'contentstr' => '结算驳回',
                        );
                        $res = M('Moneychange')->add($arrayField);
                        if (!$res) {
                            M()->rollback();
                            $fail++;
                            continue;
                        }
                        //结算驳回退回手续费
                        if ($withdraw['tk_charge_type'] && $withdraw['sxfmoney'] > 0) {
                            $res = $Member->where(['id' => $withdraw['userid']])->save(['balance' => array('exp', "balance+{$withdraw['sxfmoney']}")]);
                            if (!$res) {
                                M()->rollback();
                                $fail++;
                                continue;
                            }
                            $chargeField = array(
                                "userid" => $withdraw['userid'],
                                "ymoney" => $memberInfo['balance'] + $withdraw['tkmoney'],
                                "money" => $withdraw['sxfmoney'],
                                "gmoney" => $memberInfo['balance'] + $withdraw['tkmoney'] + $withdraw['sxfmoney'],
                                "datetime" => date("Y-m-d H:i:s"),
                                "tongdao" => 0,
                                "transid" => $v,
                                "orderid" => $v,
                                "lx" => 17,
                                'contentstr' => '手动结算驳回退回手续费',
                            );
                            $res = M('Moneychange')->add($chargeField);
                            if (!$res) {
                                M()->rollback();
                                $fail++;
                                continue;
                            }
                        }
                        $data['status'] = 3;
                        $data["cldatetime"] = date("Y-m-d H:i:s");
                        $res = $Tklist->where(['id' => $v, 'status' => 0])->save($data);
                        if ($res === false) {
                            M()->rollback();
                            $fail++;
                            continue;
                        } else {
                            M()->commit();
                            $success++;
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
                $this->ajaxReturn(['status' => 1, 'msg' => '成功驳回：' . $success . '，失败：' . $fail]);
            } else {
                $this->ajaxReturn(['status' => 0, 'msg' => '驳回失败!']);
            }
        } else {
            foreach ($ids as $k => $v) {
                try {
                    M()->startTrans();
                    if (intval($v)) {
                        $withdraw = $Tklist->where(['id' => $v])->find();
                        if (empty($withdraw)) {
                            M()->rollback();
                            $fail++;
                            continue;
                        }
                        if ($withdraw['status'] == 3) {
                            M()->rollback();
                            $fail++;
                            continue;
                        }
                        $data = [
                            "status" => $status,
                            'cldatetime' => date("Y-m-d H:i:s"),
                        ];

                        $res = $Tklist->where(['id' => $v, 'status' => ['neq', 3]])->save($data);
                        if ($res === false) {
                            M()->rollback();
                            $fail++;
                            continue;
                        } else {
                            M()->commit();
                            $success++;
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
                $this->ajaxReturn(['status' => 1, 'msg' => '成功完成：' . $success . '，失败：' . $fail]);
            } else {
                $this->ajaxReturn(['status' => 0, 'msg' => '完成操作失败!']);
            }
        }
    }


    public function agorder()
    {
        UserLogService::write(1, '子商户代收统计', '子商户代收统计');
        //查询下级
        $where = array();

         //查询下级
        $pwhere['parentid'] = $this->fans['uid'];

        $userlist = M('Member')->where($pwhere)->field('id')->select();
        foreach ($userlist as $key => $value) {
            $where_zj[] = $value['id'] + 10000;
        }
        $currency = I("request.currency");
        if($currency ==='INR'){
            $where['paytype'] = ['eq', 4];
        }else{
            $currency ='PHP';
            $where['paytype'] = ['between', [1,3]];
        }
        $this->assign('currency', $currency);
        
        $memberid = I("request.memberid", '', 'string,strip_tags,htmlspecialchars');
        $this->assign("memberid", $memberid);
        if ($memberid) {
            if (in_array($memberid, $where_zj)) {
                $where['pay_memberid'] = $memberid;
            } else {
                $where['pay_memberid'] = 1;
            }
        } else {
            $where['pay_memberid'] = ['in', $where_zj];
        }
        
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

        $sstime = strtotime($sstime);
        $setime = strtotime($setime);
        $cstime = $sstime- 3600 * 24 * 7;
        $cetime = $setime;
        $this->assign('successtime', $successtime);

        $where['pay_applydate'] = ['between', [$cstime, $cetime]];
        $where['pay_successdate'] = ['between', [$sstime, $setime]];
        $where['pay_status'] = ['between', [1, 2]];
        $where['lock_status'] = ['eq', 0];
        $OrderModel = D('Order');
        $stat_total = $OrderModel->getSum('pay_amount',$where);
        $sum['success_count'] = $OrderModel->getCount($where);
        // echo $OrderModel->getLastSql();
        $sum['pay_amount'] = round($stat_total['pay_amount'], 2);


        //资金变动过里的提成
        $profitMap['lx'] = 9;
        if(!empty($memberid)) {
            $profitMap['userid'] = $memberid - 10000;
        }
        $MoneychangeModel = D('Moneychange');
        $sum_memberprofit = $MoneychangeModel->getSum('money',$profitMap);
        $sum['memberprofit'] = $sum_memberprofit['money'];

        $this->assign('sum', $sum);
        C('TOKEN_ON', false);
        $this->display();
    }

    // public function agorder()
    // {
    //     // $status = I("request.status", '');
    //     // if ($status != '') {
    //     //     $status = intval($status);
    //     // }
        
    //     $memberid = I("request.memberid", '', 'intval');
    //     $this->assign("memberid", $memberid);
        
    //     $down_user = M('member')->where(array('parentid' => $this->fans['uid']))->field('id')->select();

    //     if($down_user){
    //         foreach ($down_user as $key => $value) {
    //             $where_zj[] = $value['id'];
    //         }
    //         //查询下级--下下级
    //         $pwhere1['parentid'] = array('in', $where_zj);
    //         $zj_userlist = M('Member')->where($pwhere1)->field('id')->select();
    //         $all_userlist = array_merge($down_user, $zj_userlist);

    //         $down_array1='';
    //         if ($memberid) {
                
    //             foreach ($all_userlist as $key => $value) {
    //                 $all_userlist1[] =  $value['id'];
    //             }
    //             if (in_array($memberid - 10000, $all_userlist1)) {
    //                 $down_array[] = $memberid;
    //                 $down_array1 .= $memberid . ',';
    //             } else {
    //                 $down_array[]= 1;
    //                 $down_array1 .= '1,';
    //             }
    //         } else {
    //             foreach ($all_userlist as $key => $value) {
    //                 $value['id'] = 10000 + $value['id'];
    //                 $down_array[] = $value['id'];
    //                 $down_array1 .= $value['id'] . ',';
    //             }
    //         }
    //     }else{
    //         $down_array[0]=1;
    //         $down_array1 = 1;
    //     }

    //     // switch ($status) {
    //     //     case '0':
    //     //         $title = '未支付订单';
    //     //         break;
    //     //     case '1':
    //     //         $title = '手工补发订单';
    //     //         break;
    //     //     case '2':
    //     //         $title = '成功订单';
    //     //         break;
    //     //     default:
    //     //         $title = '所有订单';
    //     //         break;
    //     // }
    //     UserLogService::write(1, '访问' . $title . '列表页面', '访问' . $title . '订单列表页面');
    //     // //通道
    //     // $products = M('ProductUser')
    //     //     ->join('LEFT JOIN __PRODUCT__ ON __PRODUCT__.id = __PRODUCT_USER__.pid')
    //     //     ->where(['pay_product_user.status' => 1, 'pay_product_user.userid' => $this->fans['uid']])
    //     //     ->field('pay_product.name,pay_product.id,pay_product.code')
    //     //     ->select();
    //     // $this->assign("banklist", $products);


    //     $where = array();
    //     // $orderid = I("request.orderid", '', 'string,strip_tags,htmlspecialchars');
    //     // if ($orderid) {
    //     //     $where['out_trade_id'] = $orderid;
    //     //     $where1['O.out_trade_id'] = $orderid;
    //     // }
    //     // $this->assign("orderid", $orderid);
    //     // $ddlx = I("request.ddlx", '', 'string,strip_tags,htmlspecialchars');
    //     // if ($ddlx != "") {
    //     //     $where['ddlx'] = array('eq', $ddlx);
    //     //     $where1['O.ddlx'] = array('eq', $ddlx);
    //     // }
    //     // $this->assign("ddlx", $ddlx);


    //     // $tongdaolist = M("Channel")->field('id,code,title')->select();
    //     // $this->assign("tongdaolist", $tongdaolist);
    //     // $tongdao = I("request.tongdao", '', 'trim,string,strip_tags,htmlspecialchars');
    //     // if ($tongdao) {
    //     //     $where['channel_id'] = array('eq', $tongdao);
    //     //     $where1['O.channel_id'] = array('eq', $tongdao);

    //     //     $accountlist = M('channel_account')->where(['channel_id' => $tongdao])->select();
    //     //     $this->assign('accountlist', $accountlist);
    //     // }
    //     // $this->assign('tongdao', $tongdao);
    //     // $account = I("request.account", '', 'trim,string,strip_tags,htmlspecialchars');
    //     // if ($account) {
    //     //     $where['account_id'] = array('eq', $account);
    //     //     $where1['O.account_id'] = array('eq', $account);


    //     // }
    //     // $this->assign('account', $account);
    //     // $bank = I("request.bank", '', 'trim,string,strip_tags,htmlspecialchars');
    //     // if ($bank) {
    //     //     $where['pay_bankcode'] = array('eq', $bank);
    //     //     $where1['O.pay_bankcode'] = array('eq', $bank);
    //     // }
    //     // $this->assign('bank', $bank);


    //     // $tongdao = I("request.tongdao", '', 'string,strip_tags,htmlspecialchars');
    //     // if ($tongdao) {
    //     //     $where['pay_bankcode'] = array('eq', $tongdao);
    //     // }
    //     // $this->assign("tongdao", $tongdao);
    //     // $body = I("request.body", '', 'string,strip_tags,htmlspecialchars');
    //     // if ($body) {
    //     //     $where['pay_productname'] = array('like', '%' . $body . '%');
    //     //     $where1['O.pay_productname'] = array('like', '%' . $body . '%');
    //     // }
    //     // $this->assign("body", $body);
    //     // if ($status != '') {
    //     //     $where['pay_status'] = array('eq', $status);
    //     //     $where1['O.pay_status'] = array('eq', $status);
    //     // }
    //     // $this->assign("status", $status);
    //     $successtime = urldecode(I("request.successtime", '', 'string,strip_tags,htmlspecialchars'));
    //     if ($successtime) {
    //         list($sstime, $setime) = explode('|', $successtime);
    //         $where['pay_successdate'] = $sumMap['pay_successdate'] = $failMap['pay_successdate'] = $map['create_at'] = ['between', [strtotime($sstime), strtotime($setime) ? strtotime($setime) : time()]];
    //         $where1['O.pay_successdate'] = $sumMap['pay_successdate'] = $failMap['pay_successdate'] = $map['create_at'] = ['between', [strtotime($sstime), strtotime($setime) ? strtotime($setime) : time()]];
    //     }
    //     $this->assign("successtime", $successtime);
    //     $createtime = urldecode(I("request.createtime", '', 'string,strip_tags,htmlspecialchars'));
    //     if ($createtime) {
    //         list($cstime, $cetime) = explode('|', $createtime);
    //         $where['pay_applydate'] = $sumMap['pay_applydate'] = $failMap['pay_applydate'] = $map['create_at'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
    //         $where1['O.pay_applydate'] = $sumMap['pay_applydate'] = $failMap['pay_applydate'] = $map['create_at'] = ['between', [strtotime($cstime), strtotime($cetime) ? strtotime($cetime) : time()]];
    //     }
    //     if (!$createtime && !$successtime && !$payOrderid && !$orderid) {
    //         $todayBegin = date('Y-m-d') . ' 00:00:00';
    //         $todyEnd = date('Y-m-d') . ' 23:59:59';
    //         $where['pay_applydate'] = $sumMap['pay_applydate'] = $failMap['pay_applydate'] = $map['create_at'] = ['between', [strtotime($todayBegin), strtotime($todyEnd)]];
    //         $where1['O.pay_applydate'] = $sumMap['pay_applydate'] = $failMap['pay_applydate'] = $map['create_at'] = ['between', [strtotime($todayBegin), strtotime($todyEnd)]];
    //         $createtime = $todayBegin . ' | ' . $todyEnd;
    //     }
    //     $this->assign("createtime", $createtime);
        
    //     $where['isdel'] = 0;
    //     // $where1['O.isdel'] = 0;
    //     $where['pay_memberid'] = array('in', $down_array);
    //     $where1['O.pay_memberid'] = array('in', $down_array1 . '0');
                
    //     // $size = 15;
    //     // $rows = I('get.rows', $size, 'intval');
    //     // if (!$rows) {
    //     //     $rows = $size;
    //     // }
    //     $OrderModel = D('Order');
    //     // $count = $OrderModel->getCount($where);
    //     // $page = new Page($count, $rows);
    //     // $list = $OrderModel->getOrderByDateRange($field, $where, $page->firstRow . ',' . $page->listRows, 'id desc');
            
    //     // //统计今日交易数据
    //     // if ($status == '2') {
    //     //     //今日成功交易总额
    //     //     $todayBegin = date('Y-m-d') . ' 00:00:00';
    //     //     $todyEnd = date('Y-m-d') . ' 23:59:59';
    //     //     $t_where = [
    //     //         'pay_memberid' => 10000 + $this->fans['uid'], 
    //     //         'pay_successdate' => ['between', [strtotime($todayBegin), strtotime($todyEnd)]], 
    //     //         'pay_status' => ['between', ["1", "2"]],
    //     //         ];
    //     //     $taodaypay_amount = $OrderModel->getSum('pay_amount,pay_actualamount', $t_where);
    //     //     $stat['todaysum'] = $taodaypay_amount['pay_amount'];
    //     //     //今日实际到账笔数
    //     //     $stat['taodayactualamount'] = $taodaypay_amount['pay_actualamount'];
    //     //     //今日成功笔数
    //     //     $stat['todaysuccesscount'] = $OrderModel->getCount($t_where);
    //     //     //今日失败笔数
    //     //     $t_where['pay_status'] = ['eq',0];
    //     //     $stat['todayfailcount'] = $OrderModel->getCount($t_where);
    //     //     foreach ($stat as $k => $v) {
    //     //         $stat[$k] = $v + 0;
    //     //     }
    //     //     $this->assign('stat', $stat);
    //     // }

    //     //如果指定时间范围则按搜索条件做统计
    //     // if ($createtime || $successtime) {
    //     $sumMap = $failMap = $where;
    //     $all_count = $OrderModel->getCount($sumMap);
    //     $field = 'sum(`pay_amount`) pay_amount, sum(`pay_actualamount`) pay_actualamount, sum(`cost`) cost, count(`id`) success_count';
    //     if (empty($where['pay_status'])) {
    //         $sumMap['pay_status'] = ['in', '1, 2'];
    //         $where1['O.pay_status'] = ['in', '1, 2'];
    //     }
    //     $sum_arr = $OrderModel->getOrderByDateRange($field, $sumMap);
    //     $sum = $sum_arr[0];
    //     foreach ($sum as $k => $v) {
    //         $sum[$k] += 0;
    //     }
    //     $where1['C.lx'] = 9;
    //     $sum['memberprofit'] = M('moneychange')->alias('as C')->join('LEFT JOIN __ORDER__ AS O ON C.transid=O.pay_orderid')
    //         ->where($where1)->sum('money');

    //     // $sum['pay_feilv'] = sprintf("%.3f", $sum['pay_amount'] - $sum['pay_actualamount']);
    //     $sum['memberprofit'] = sprintf("%.3f", $sum['memberprofit']);
    //     // $sum['pay_poundage'] = sprintf("%.3f", $sum['pay_feilv'] - $sum['memberprofit'] - $sum['costcost']);
    //     $sum['success_lv'] = sprintf("%.3f", $sum['success_count'] / $all_count)*100;

    //     $this->assign('sum', $sum);
    //     // $this->assign('rows', $rows);
    //     // $this->assign("list", $list);
    //     // $this->assign('page', $page->show());
    //     C('TOKEN_ON', false);
    //     $this->display();
    // }

    //设置订单为已支付
    public function setOrderPaid()
    {
        if ($this->fans['groupid'] != 7) {
            $this->error('没有权限！');
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
                UserLogService::write(1, '修改订单状态', '订单号' . $order['pay_orderid'] . '操作成功;IP:' . get_client_ip());
                $this->ajaxReturn(['status' => 1, 'msg' => "设置成功！"]);
            } else {
                UserLogService::write(1, '修改订单状态', '订单号' . $order['pay_orderid'] . '操作失败;IP:' . get_client_ip());
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
            $this->assign('order', $order);
        }
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


}
