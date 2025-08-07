<?php
namespace Admin\Controller;
use Think\Controller;

class ClientController extends Controller
{
    protected $user = '';

    public function __construct()
    {
        parent::__construct();
        $this->user = M('payUser', '', 'mysql://tatech:66paysql@150.109.127.17:3306/pay#utf8');
    }

    public function index()
    {
        // date_default_timezone_set('PRC');               //设置北京时间为默认时区
        $datetime = strtotime(date("Y-m-d"), time());    //当天零时时间戳
        $redis = new \Redis();                          //创建一个redis对象
        $redis->connect('150.109.127.17', '6379');      //连接 Redis 服务
        $redis->auth('cooljava!@#89.');                 //密码验证

        $channel_account = M('channel_account')->field('mch_id,title')->where(['channel_id' => '213'])->select();
        foreach ($channel_account as $cv) {
            $account[$cv['mch_id']] = $cv['title'];
        }

        $moneycash = M('moneycash')->field('userid,gmoney')->select();
        foreach ($moneycash as $mv) {
            $cash[$mv['userid']] = $mv['gmoney'];
        }
        $a = $this->user->query('SELECT count(id) as count FROM `payUser`');
        $list = $this->user->query('SELECT * FROM `payUser` ORDER BY id desc');
        $count = $a[0]['count'];
        foreach ($list as $lk => $lv) {
            if ($account[$lv['userid']] != '') {
                $list[$lk]['online'] = $redis->get('auth_token_' . $lv['authtoken']);
                $list[$lk]['title'] = $account[$lv['userid']];
                $day_amount = M('order')->where([
                    'pay_channel_account' => $account[$lv['userid']],
                    'pay_successdate' => ['gt', $datetime],
                    'pay_status' => ['neq', 0]
                ])->sum('pay_amount');

                if($day_amount=='null' || $day_amount==''){
                    $day_amount = 0;
                }
                if($cash[$lv['userid']] != ''){
                    $money = $cash[$lv['userid']];
                }else{
                    $money = $day_amount;
                }
                $list[$lk]['day_amount'] = sprintf('%.2f', $day_amount);
                $list[$lk]['money'] = sprintf('%.2f', $money);
            }
        }

        $this->assign('count', $count);
        $this->assign("list", $list);
        $this->display();
    }
    public function change()
    {

        // date_default_timezone_set('PRC');               //设置北京时间为默认时区
        $datetime = strtotime(date("Y-m-d"), time());    //当天零时时间戳

        if (IS_POST) {
            if (parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) !== C('DOMAIN')) {
                $this->ajaxReturn(['status' => 0, 'msg' => 'Denied']);
            }
            $data = I("post.");
            unset($data["__hash__"]);

            if (!$data["userid"]) {
                $this->ajaxReturn(['status' => 0, 'msg' => '请选择客户端!']);
            }
            if (!$data["change_type"]) {
                $this->ajaxReturn(['status' => 0, 'msg' => '请选择操作方式!']);
            }
            if (!$data["change_money"]) {
                $this->ajaxReturn(['status' => 0, 'msg' => '请输入变动金额!']);
            }
            if (!empty($data)) {
                $data["datetime"] = time();
            }
            $userid = explode('_', $data["userid"]);
            $mch_id = M("moneycash")->where(array("userid"=>$userid[0]))->find();

            if($mch_id && $mch_id["datetime"] > $datetime){
                $data["ymoney"] = $mch_id["gmoney"];
                if($data["change_type"]==1){  
                    if($mch_id["gmoney"] < $data["change_money"]){
                        $this->ajaxReturn(['status' => 0, 'msg' => '出错了，减少金额大于实际金额!1']);
                    }
                    $data["gmoney"] = $mch_id["gmoney"] - $data["change_money"];
                }elseif($data["change_type"]==2){
                    $data["gmoney"] = $mch_id["gmoney"] + $data["change_money"];
                }else{
                    $this->ajaxReturn(['status' => 0, 'msg' => '操作方式有误!']);
                }
                $result = M("moneycash")->where(array("userid"=>$mch_id["userid"]))->save($data);
            }else{
                $userid = $userid[1];
                $day_amount = M('order')->where([
                        'pay_channel_account' => $userid,
                        'pay_successdate' => ['gt', $datetime],
                        'pay_status' => ['neq', 0]
                    ])->sum('pay_amount');
                if($data["change_type"]==1){  
                    if($day_amount < $data["change_money"]){
                        $this->ajaxReturn(['status' => 0, 'msg' => '出错了，减少金额大于实际金额!']);
                    }
                    $data["gmoney"] = $day_amount - $data["change_money"];
                }elseif($data["change_type"]==2){
                    $data["gmoney"] = $day_amount + $data["change_money"];
                }else{
                    $this->ajaxReturn(['status' => 0, 'msg' => '操作方式有误!']);
                }
                
                $data["ymoney"] = intval($day_amount);
                $result = M('moneycash')->add($data);
            }
            $this->ajaxReturn(['status' => $result]);

        } else {
            $channel_account = M('channel_account')->field('mch_id,title')->where(['channel_id' => '213'])->select();
            // var_dump($channel_account);
            $this->assign('channel_account', $channel_account);
            $this->display();
        }
    }
}

?>
