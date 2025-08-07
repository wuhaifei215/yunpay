<?php
namespace Admin\Controller;
use Think\Controller;
use Think\Page;

class JavaHTController extends BaseController
{
    protected $user = '';
    protected $merchant = '';

    public function __construct()
    {
        parent::__construct();
        $this->user = M('payUser', '', 'mysql://tatech:66paysql@150.109.127.17:3306/pay#utf8');
        $this->merchant = M('merchantUser', '', 'mysql://tatech:66paysql@150.109.127.17:3306/pay#utf8');
    }

    //列表
    public function index()
    {
        // date_default_timezone_set('PRC');               //设置北京时间为默认时区
        $day_time = strtotime(date("Y-m-d"), time());    //当天零时时间戳

        $redis = new \Redis();        //创建一个redis对象
        $redis->connect('150.109.127.17', '6379');        //连接 Redis 服务
        $redis->auth('cooljava!@#89.');        //密码验证

        $where = '1=1';
        $size = 15;
        $rows = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }
        $page = I('get.p', 1);
        $pagesize = ($page - 1) * $rows;
        $id = I('get.id');
        if ($id) {
            $where .= ' and id=' . $id;
        }
        $limit = ' limit ' . $pagesize . ',' . $rows;
        $channel_account = M('channel_account')->field('mch_id,title')->where(['channel_id' => '213'])->select();
        foreach ($channel_account as $cv) {
            $account[$cv['mch_id']] = $cv['title'];
        }
        $a = $this->user->query('SELECT count(id) as count FROM `payUser`');
        $list = $this->user->query('SELECT * FROM `payUser` WHERE ' . $where . ' ORDER BY id desc' . $limit);
        $count = $a[0]['count'];
        foreach ($list as $lk => $lv) {
            if ($account[$lv['userid']] != '') {
                $list[$lk]['online'] = $redis->get('auth_token_' . $lv['authtoken']);
                $list[$lk]['title'] = $account[$lv['userid']];
                //计算完成总额
                $sum = M('order')->where(['pay_channel_account' => $account[$lv['userid']], ['pay_status' => ['between', [1, 2]]]])->sum('pay_amount');
                if ($sum == 'null' || $sum == '') {
                    $sum = 0;
                }
                $list[$lk]['all_amount'] = sprintf('%.2f', $sum);

                //计算当日完成金额
                $day_amount = M('order')->where([
                    'pay_channel_account' => $account[$lv['userid']],
                    'pay_successdate' => ['gt', $day_time],
                    'pay_status' => ['neq', 0]
                ])->sum('pay_amount');
                if ($day_amount == 'null' || $day_amount == '') {
                    $day_amount = 0;
                }
                $list[$lk]['day_amount'] = sprintf('%.2f', $day_amount);
            }
        }
        $page = new Page($count, $rows);
        $this->assign('count', $count);
        $this->assign("list", $list);
        //取消令牌
        C('TOKEN_ON', false);
        $this->assign('page', $page->show());
        $this->display();
    }

    public function addclient()
    {
        if (IS_POST) {
            //防止跨站请求伪造
            if (parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) !== C('DOMAIN')) {
                $this->ajaxReturn(['status' => 0, 'msg' => 'Denied']);
            }
            $data = I("post.");
            if (!$data["userid"]) {
                $this->ajaxReturn(['status' => 0, 'msg' => '请输入客户端Id!']);
            }
            if (!$data["authtoken"]) {
                $this->ajaxReturn(['status' => 0, 'msg' => '请输入授权码!']);
            }
            if (!$data["expiredate"]) {
                $this->ajaxReturn(['status' => 0, 'msg' => '请输入授权码过期时间']);
            }

            if ($this->user->query('SELECT id FROM `payUser` WHERE userid=' . $data["userid"])) {
                $this->ajaxReturn(['status' => 0, 'msg' => '客户端已存在！']);
            }
            if ($this->user->query('SELECT id FROM `payUser` WHERE authtoken=' . $data["authtoken"])) {
                $this->ajaxReturn(['status' => 0, 'msg' => '授权码已存在！']);
            }

            //插入商户设备表 `payUser` 数据
            $insert_sql = 'insert into `payUser` (userid,authtoken,expiredate,notifyaddress) VALUES ("';
            $insert_sql .= $data["userid"] . '","';
            $insert_sql .= $data["authtoken"] . '","';
            $insert_sql .= $data["expiredate"] . '","';
            $insert_sql .= $data["notifyaddress"] . '")';
            $this->user->query($insert_sql);
            //插入商户表 `merchantUser` 数据
            $merchant_sql = 'insert into `merchantUser` (userid,effectivenesstype) VALUES ("' . $data["userid"] . '","1")';
            $this->merchant->query($merchant_sql);

            $this->ajaxReturn(['status' => true]);
        }
        $this->display();
    }

    public function editclient()
    {
        if (IS_POST) {
            if (parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) !== C('DOMAIN')) {
                $this->ajaxReturn(['status' => 0, 'msg' => 'Denied']);
            }
            $data = I("post.");
            if (!$data["userid"]) {
                $this->ajaxReturn(['status' => 0, 'msg' => '请输入客户端Id!']);
            }
            if (!$data["authtoken"]) {
                $this->ajaxReturn(['status' => 0, 'msg' => '请输入授权码!']);
            }
            if (!$data["expiredate"]) {
                $this->ajaxReturn(['status' => 0, 'msg' => '请输入授权码过期时间']);
            }
            //修改商户设备表 `payUser` 数据
            $update_sql = 'UPDATE `payUser` SET userid="' . $data["userid"];
            $update_sql .= '",authtoken = "' . $data["authtoken"];
            $update_sql .= '",expiredate = "' . $data["expiredate"];
            $update_sql .= '",notifyaddress = "' . $data["notifyaddress"];
            $update_sql .= '" WHERE id=' . $data["id"];
            $change_result = $this->user->execute($update_sql);
            $this->ajaxReturn(['status' => $change_result]);

        } else {
            $id = I('id', 0, 'intval');
            $admin_info = $this->user->query('SELECT * FROM `payUser` WHERE id=' . $id);
            $this->assign('admin_info', $admin_info[0]);
            $this->display();
        }
    }

    public function deleteclient()
    {
        if (parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) !== C('DOMAIN')) {
            $this->ajaxReturn(['status' => 0, 'msg' => 'Denied']);
        }
        $id = I('id', 0, 'intval');
        $admin = $this->user->query('SELECT id,userid FROM `payUser` WHERE id=' . $id);
        if (!$admin) {
            $this->ajaxReturn(['status' => 0, 'msg' => '客户端不存在!']);
        }

        //删除商户设备表 `payUser` 数据
        $del_user_sql = 'DELETE FROM `payUser` WHERE id=' . $id;
        $change_result = $this->user->execute($del_user_sql);
        if ($change_result) {
            //删除商户表 `merchantUser` 数据
            $del_merchant_sql = 'DELETE FROM `merchantUser` WHERE userid=' . $admin[0]['userid'];
            $this->merchant->execute($del_merchant_sql);
        }
        $this->ajaxReturn(['status' => $change_result]);
    }


}

?>
