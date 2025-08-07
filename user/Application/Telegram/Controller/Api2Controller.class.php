<?php

namespace Telegram\Controller;

use \think\Controller;
use Think\Log;

/**
 * @author mapeijian
 * @date   2018-06-06
 */
class Api2Controller extends Controller
{
    protected $code = '';
    protected $bot_name = 'BF168TestBot';
    protected $token = '7554074847:AAEY7-i1_malga4SxO2iyPLyQpk4qfhDl6k';
    protected $callback = '';
    protected $title='test小管家';

    public function __construct()
    {
        parent::__construct();
        $matches = [];
        preg_match('/([\da-zA-Z\_]+)Controller$/', __CLASS__, $matches);
        $this->code = $matches[1];
        $this->callback = 'https://' . C('DOMAIN') . '/Telegram_' . $this->code . '_receive.html';
    }

    public function index()
    {
    }

    // 向telegram bot指定域名
    public function setWebhook()
    {
        $url = 'https://api.telegram.org/bot' . $this->token . '/setWebhook';
        $data = [
            'url' => $this->callback . '?token=' . md5($this->token),
            'drop_pending_updates'=> 'True'
        ];
        $result = curlPost($url, $data);
        $result = json_decode($result, true);
        if ($result['result'] === true) {
            echo "域名绑定成功！";
        }
        var_dump($result);
    }

    // 删除回调
    public function deleteWebhook()
    {
        $url = 'https://api.telegram.org/bot' . $this->token . '/deleteWebhook';
        $data = [
            'url' => $this->callback . '?token=' . md5($this->token),
        ];
        $result = curlPost($url, $data);
        log_place_order($this->code . '_deleteWebhook', "删除回调", $result);    //日志

        $result = json_decode($result, true);
        if ($result['result'] === true) {
            echo "删除回调成功！";
        }
    }

    // 查看当前回调信息
    public function getWebhookInfo()
    {
        $url = 'https://api.telegram.org/bot' . $this->token . '/getWebhookInfo';
        $data = [
            'url' => $this->callback . '?token=' . md5($this->token),
        ];
        $result = curlPost($url, $data);
        log_place_order($this->code . '_getWebhookInfo', "当前回调信息", $result);    //日志

        $result = json_decode($result, true);
        if ($result['result'] === true) {
            echo "查看当前回调信息成功！";
        }
        echo "<pre>";
        var_dump($result);
    }


    // 接收bot回调信息
    public function receive()
    {
        $input = file_get_contents('php://input');
        $input = json_decode($input, true);

        log_place_order($this->code . '_receive', "回调信息", json_encode($input, JSON_UNESCAPED_UNICODE));    //日志

        self::to_do($input);
        return 'True';
    }
    
    public function send($chatId, $text,$message_id='',$parse_mode='',$markup=''){
        return $this -> sendMessage($chatId, $text, $message_id, $parse_mode,$markup);
    }


    // 回复用户信息
    private function sendMessage($chatId, $text,$message_id='',$parse_mode='',$markup='')
    {
        $url = 'https://api.telegram.org/bot' . $this->token . '/sendMessage';
        // $param = "?chat_id=" . $chatId . "&text=" . $text;
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
        ];
        if($parse_mode!=''){
            $data['parse_mode'] = $parse_mode;
        }
        if($message_id!=''){
            $data['reply_parameters'] = json_encode([
                'message_id'=> $message_id,
                'chat_id'=>$chatId,
            ], JSON_UNESCAPED_UNICODE);
        }
        if($markup!=''){
            $data['reply_markup'] = json_encode($markup
            , JSON_UNESCAPED_UNICODE);
        }
        log_place_order($this->code . '_sendMessage', "发送", json_encode($data, JSON_UNESCAPED_UNICODE));    //日志
        $result = curlPost($url, $data);

        log_place_order($this->code . '_sendMessage', "返回", $result);    //日志
        $result = json_decode($result, true);
        if ($result['ok'] == true) {
            return $result;
        } else {
            // echo "sendError";
            return "sendError";
        }
    }
    
        // 回复用户信息
    private function sendPhoto($chatId, $photo, $text='')
    {
        $url = 'https://api.telegram.org/bot' . $this->token . '/sendPhoto';
        $data = [
            'chat_id' => $chatId,
            'photo' => $photo,
            'caption' => $text
        ];
        // log_place_order($this->code . '_sendPhoto', "发送", json_encode($data, JSON_UNESCAPED_UNICODE));    //日志
        $result = curlPost($url, $data);

        // log_place_order($this->code . '_sendPhoto', "返回", $result);    //日志
        $result = json_decode($result, true);
        if ($result['ok'] == true) {
            return true;
        } else {
            echo "sendError";
            return false;
        }
    }

    private function to_do($input)
    {
        $callback_query = $input['callback_query'];
        if(!empty($callback_query)){
            $username = substr($callback_query['from']['first_name'], 0, 50);
            $username = mb_convert_encoding($username, 'UTF-8', 'ISO-8859-1');
            $chat_id = $callback_query['message']['chat']['id'];
            $message_id = $callback_query['message']['message_id'];
            
            //存缓存。防止重复操作
            $redis = $this->redis_connect();
            if($redis->get('callback_query' . $chat_id . $message_id)){
                return;
            }
            $redis->set('callback_query' . $chat_id . $message_id ,'1',600);
            
            $text = $callback_query['data'];
            $str = explode(' ', $text);
            if($str[0] === 'pass'){       //通过
                $do = '通过';
                $callback = 1;
            }elseif($str[0] === 'reject'){     //驳回
                $do = '驳回';
                $callback = 2;
            }else{
                log_place_order($this->code . '_callback_query', "异常", json_encode($callback_query));    //日志
                return;
            }
            
            $add_Callback_data = [
                'orderid' => $str[1],
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'doUser' => $username,
                'callback' => $callback,
                'create_time'=>time(),
            ];
            $re = M('TelegramApiDforderCallback')->add($add_Callback_data);
            try {
                $add_Autodf_data = [
                    'orderid' => $str[1],
                    'callback' => $callback,
                    'status' => 0,
                    'create_time'=>time(),
                ];
                $re2 = M('TelegramApiDforderAutodf')->add($add_Autodf_data);
            } catch (\Exception $e) {
                log_place_order($this->code . '_callback_query', "重复操作,程序错误", $e);    //日志
                return;
            }
            
            $message = '订单号：' . $str[1] . "\r\n用户：" . $username . '，' . $do . '该订单，执行成功';
            $this->sendMessage($chat_id, $message, $message_id);
            return;
        }
        
        $text = $input['message']['text'];
        $chat_id = $input['message']['chat']['id'];
        $message_id = $input['message']['message_id'];
        $photo = $input['message']['photo'];
        $caption = $input['message']['caption'];
        if($photo){
            $text = $caption;
        }
        $str = explode(' ', $text);

        if (isset($input['message']['new_chat_members'])) {
            $members = $input['message']['new_chat_members'];
            foreach ($members as $v) {
                $this->sendMessage($chat_id, "欢迎 " . $v['first_name'] . " 来到本群 ！！\r\n");
            }
        }
        
        //转发 回复机器人的信息
        if (isset($input['message']['reply_to_message'])) {
            $reply_to_message = $input['message']['reply_to_message'];
            if($reply_to_message['from']['is_bot'] === true){
                $user_check = M('TelegramApiDforder')->where(['message_id2' => $reply_to_message['message_id']])->find();
                if($photo){
                    $this->sendPhoto($user_check['chat_id'], $photo[0]['file_id'],$text, $user_check['message_id']);
                }else{
                    $this->sendMessage($user_check['chat_id'], $text, $user_check['message_id']);
                }
            }
            return;
        }
         
        //查询显示群组ID
        if (strpos($text, '/show') !== false || strpos($text, '/show@' . $this->bot_name) !== false) {
            $message='';
                
            $message = "群组ID：" . $chat_id . "\r\n--------------------------------\r\n";
            $message .= $this->title . "，7*24小时服务\r\n 可以帮助您查询余额，收款订单详情、出款订单详情\r\n ";
            $message .= "输入 /show 可查看群组信息及提示，无需再添加其他参数\r\n ";
            $message .= "输入 /balance 可查询当前账户的查询余额，无需再添加其他参数\r\n ";
            $message .= "输入 /ds 可查询收款订单详情，后面+空格+订单号；例如:/ds 928271637212\r\n ";
            $message .= "输入 /df 可查询出款订单详情，后面+空格+订单号；例如:/df 928271637212\r\n ";
            $message .= "输入 /pz 可查询出款凭证，后面+空格+订单号；例如:/pz 928271637212\r\n ";
            $this->sendMessage($chat_id, $message, $message_id);
            return;
        }

        //绑定用户
        if (strpos($text, '/bind') !== false) {
            if ($str[1]) {
                $user_check = M('Member')->where(['telegram_id' => $chat_id])->getField('id');
                if ($user_check) {
                    $uid = $user_check['id'] + 10000;
                    $message = "本群已绑定商户号：" . $uid . "\r\n--------------------------------\r\n";
                    $message .= $this->title . "，7*24小时服务\r\n 可以帮助您查询余额，收款订单详情、出款订单详情\r\n ";
                    $message .= "输入 /show 可查看群组信息及提示，无需再添加其他参数\r\n ";
                    $message .= "输入 /balance 可查询当前账户的查询余额，无需再添加其他参数\r\n ";
                    $message .= "输入 /ds 可查询收款订单详情，后面+空格+订单号；例如:/ds 928271637212\r\n ";
                    $message .= "输入 /df 可查询出款订单详情，后面+空格+订单号；例如:/df 928271637212\r\n ";
                    $message .= "输入 /pz 可查询出款凭证，后面+空格+订单号；例如:/pz 928271637212\r\n ";
                    $this->sendMessage($chat_id, $message, $message_id);
                    return;
                } else {
                    $where=[];
                    $where['id'] = $str[1] - 10000;
                    $re = M('Member')->where($where)->save(['telegram_id' => $chat_id]);
                    if ($re) {
                        $message = "成功绑定商户号：" . $uid . "\r\n--------------------------------\r\n";
                        $message .= $this->title . "，7*24小时服务\r\n 可以帮助您查询余额，收款订单详情、出款订单详情\r\n ";
                        $message .= "输入 /show 可查看群组信息及提示，无需再添加其他参数\r\n ";
                        $message .= "输入 /balance 可查询当前账户的查询余额，无需再添加其他参数\r\n ";
                        $message .= "输入 /ds 可查询收款订单详情，后面+空格+订单号；例如:/ds 928271637212\r\n ";
                        $message .= "输入 /df 可查询出款订单详情，后面+空格+订单号；例如:/df 928271637212\r\n ";
                        $message .= "输入 /pz 可查询出款凭证，后面+空格+订单号；例如:/pz 928271637212\r\n ";
                        $this->sendMessage($chat_id, $message, $message_id);
                    } else {
                        $this->sendMessage($chat_id, '绑定失败', $message_id);
                    }
                }
            } else {
                $this->sendMessage($chat_id, '绑定失败', $message_id);
            }
            return;
        }

        //根据群ID 获取用户信息
        $userInfo = self::get_user($chat_id);
        if ($userInfo['status'] !== 1 || !$userInfo['info']['id']) {
            $this->sendMessage($chat_id, '该群未绑定用户！请联系值班客服', $message_id);
            return;
        }

        //查询用户余额
        if (strpos($text, '/balance@' . $this->bot_name) !== false) {
            $uid = $userInfo['info']['id'] + 10000;
            $statistics = $this->statistics($uid);
            $message = '';
            $message .= "商户数据播报\r\n--------------------------------\r\n";
            $message .= "商户号 ：`" . $uid . "`\r\n";
            $message .= "商户名称：`" . $userInfo['info']['username'] . "`\r\n";
            $message .= "可用余额：" . $userInfo['info']['balance_php'] . "\r\n";
            $message .= "冻结余额：" . $userInfo['info']['blockedbalance_php'] . "\r\n";
            // $message .= "越南可用余额：" . $userInfo['info']['balance_inr'] . "\r\n";
            // $message .= "越南冻结余额：" . $userInfo['info']['blockedbalance_inr'] . "\r\n";
            $message .= "======代收数据======\r\n";
            $message .= "订单金额：" . $statistics['all_o_sum'] . "  ";
            $message .= "笔数：" . $statistics['all_o_num'] . "\r\n";
            $message .= "成功金额：" . $statistics['o_sum'] . "  ";
            $message .= "笔数：" . $statistics['o_num'] . "\r\n";
            $message .= "成功率：" . $statistics['o_success_rate'] . "%\r\n";
            $message .= "======代付数据======\r\n";
            $message .= "订单金额：" . $statistics['all_w_amount'] . "  ";
            $message .= "笔数：" . $statistics['all_w_i'] . "\r\n";
            $message .= "成功金额：" . $statistics['w_sum_done'] . "  ";
            $message .= "笔数：" . $statistics['wi_done'] . "\r\n";
            $message .= "代付中金额：" . $statistics['w_sum_do'] . "  ";
            $message .= "笔数：" . $statistics['wi_do'] . "\r\n";
            $message .= "--------------------------------\r\n";
            $message .= "查询时间：" . date('Y-m-d H:i:s', time()) . "\r\n";
            $message .= "当前余额及时核对以此为准，如有疑问联系值班客服";
            $this->sendMessage($chat_id, $message, $message_id, $parse_mode='Markdown');
            return;
        }
                
        //开启客服补单
        if (strpos($text, '/开启客服补单') !== false) {
            $title = $input['message']['chat']['title'];
            $title = mb_convert_encoding($title, 'UTF-8', mb_detect_encoding($title, "auto"));
            $where=[];
            $where['telegram_id'] = $chat_id;
            $is_re = M('TelegramApi')->where($where)->find(); 
            if($is_re && !is_null($is_re)){
                if($is_re['status'] === 1){
                    $this->sendMessage($chat_id, "此群已开启客服补单功能\r\n"); 
                    return;
                }else{
                    $re = M('TelegramApi')->where($where)->save(['telegram_name' => $title,'telegram_id' => $chat_id,'status'=>1]);
                }
            }else{
                $re = M('TelegramApi')->add(['telegram_name' => $title,'telegram_id' => $chat_id,'status'=>1]);
            }
            
            if($re){
               $this->sendMessage($chat_id, "SUCCESS，客服补单功能开启成功\r\n"); 
            }else{
               $this->sendMessage($chat_id, "FAIL，客服补单功能开启失败\r\n");  
            }
        }
                
        //关闭客服补单
        if (strpos($text, '/关闭客服补单') !== false) {
            $where=[];
            $where['telegram_id'] = $chat_id;
            $is_re = M('TelegramApi')->where($where)->find(); 
            if($is_re){
                if($is_re['status'] === 0){
                    $this->sendMessage($chat_id, "此群已关闭客服补单功能\r\n"); 
                    return;
                }else{
                    $title = $input['message']['chat']['title'];
                    $re = M('TelegramApi')->where($where)->save(['telegram_name' => $title,'telegram_id' => $chat_id,'status'=>0]);
                }
            }else{
                $this->sendMessage($chat_id, "此群未开启客服补单功能\r\n"); 
                return;
            }
            if($re){
               $this->sendMessage($chat_id, "SUCCESS，客服补单功能关闭成功\r\n"); 
            }else{
               $this->sendMessage($chat_id, "FAIL，客服补单功能关闭失败\r\n");  
            }
        }
        
        //开启客服补单
        if (strpos($text, '/开启群发') !== false) {
            $title = $input['message']['chat']['title'];
            $where=[];
            $where['telegram_id'] = $chat_id;
            $is_re = M('TelegramApi')->where($where)->find(); 
            if($is_re){
                if($is_re['is_open'] === 1){
                    $this->sendMessage($chat_id, "此群已开启群发功能\r\n"); 
                    return;
                }else{
                    $re = M('TelegramApi')->where($where)->save(['telegram_name' => $title,'telegram_id' => $chat_id,'is_open'=>1]);
                }
            }else{
                $re = M('TelegramApi')->add(['telegram_name' => $title,'telegram_id' => $chat_id,'is_open'=>1]);
            }
            
            if($re){
               $this->sendMessage($chat_id, "SUCCESS，群发开启成功\r\n"); 
            }else{
               $this->sendMessage($chat_id, "FAIL，群发开启失败\r\n");  
            }
        }
                
        //关闭客服补单
        if (strpos($text, '/关闭群发') !== false) {
            $where=[];
            $where['telegram_id'] = $chat_id;
            $is_re = M('TelegramApi')->where($where)->find(); 
            if($is_re){
                if($is_re['is_open'] === 0){
                    $this->sendMessage($chat_id, "此群已关闭群发功能\r\n"); 
                    return;
                }else{
                    $title = $input['message']['chat']['title'];
                    $re = M('TelegramApi')->where($where)->save(['telegram_name' => $title,'telegram_id' => $chat_id,'is_open'=>0]);
                }
            }else{
                $this->sendMessage($chat_id, "此群未开启群发功能\r\n"); 
                return;
            }
            if($re){
               $this->sendMessage($chat_id, "SUCCESS，群发功能关闭成功\r\n"); 
            }else{
               $this->sendMessage($chat_id, "FAIL，群发功能关闭失败\r\n");  
            }
        }
        
        if (strpos($text, '/qunfa') !== false) {
            $where=[];
            $where['telegram_id'] = $chat_id;
            $where['is_open'] = 1;
            $is_re = M('TelegramApi')->where($where)->find(); 
            if($is_re){
                $where=[];
                $where['telegram_id']=['neq',''];
                $member_list = M('Member')->field('telegram_id')->where($where)->select();
                foreach ($member_list as $mv){
                    $text = str_replace('/qunfa', "", $text);
                    if($photo){
                        $this->sendPhoto($mv['telegram_id'], $photo[0]['file_id'],$text);
                    }else{
                        $this->sendMessage($mv['telegram_id'], $text);
                    }
                }
            }
        }

        // //查询成功率
        // if(strpos($text, '/scr') !== false){

        //         $this->sendMessage($chat_id , '查询成功率', $message_id);
        //         return;
        // }
        // //查询用户今日使用的upi
        // if(strpos($text, '/upiall') !== false){

        //         $this->sendMessage($chat_id , '查询用户今日使用的upi', $message_id);
        //         return;
        // }
        
        //查询代付凭证查询
        if (strpos($text, '/pz') !== false) {
            if(isset($str[1])){
                $order_info = self:: get_dforder($str[1], $userInfo['info']['id']);
                $this->doHZ($order_info, $chat_id, $message, $message_id);
            }
            return;
        }

        //查询代付订单
        if (strpos($text, '/df') !== false) {
            $order_info = self:: get_dforder($str[1], $userInfo['info']['id']);
            $this->doDF($order_info, $chat_id, $message, $message_id);
            return;
        }

        //查询订单
        if (strpos($text, '/ds') !== false) {
            // log_place_order($this->code . '_get_order', $message_id . "用户", $userInfo['info']['id']);    //日志
            $order_info = self:: get_order($str[1], $userInfo['info']['id']);
            // log_place_order($this->code . '_get_order', $message_id . "订单", json_encode($order_info, JSON_UNESCAPED_UNICODE));    //日志
            $this->doDS($order_info, $chat_id, $message, $message_id);
            return;
        }
        return;
    }
    
    //代付回执查询
    public function doHZ($order_info, $chat_id, $message, $message_id, $parse_mode='Markdown'){
        if ($order_info && $order_info['status'] === 1) {
            $message = "请稍等，正在为您查询凭证...\r\n";
        } else {
            $message = "没有查询到此代付订单";
        }
        $result1 = $this->sendMessage($chat_id, $message, $message_id, $parse_mode);
        
        if($order_info['info']['df_name']){
            $where=[];
            $where['realname']=['eq',$order_info['info']['df_name']];
            $Member_info = M('Member')->field('id, telegram_id')->where($where)->find();
        }
        if(!isset($Member_info) || empty($Member_info)){
            $where=[];
            $where['id']=['eq',2];
            $Member_info = M('Member')->field('id, telegram_id')->where($where)->find();
        }
        if($order_info['status'] === 1 && isset($Member_info['telegram_id'])){
            $info = $order_info['info'];
            $zf_message = '';
            if($info['status'] == 0){
                $status_str = "未处理";
            } elseif($info['status'] == 1){
                $status_str = "处理中";
            } elseif ($info['status'] == 2) {
                $status_str = "成功未回调";
            } elseif ($info['status'] == 3) {
                $status_str = "成功已回调";
            } elseif ($info['status'] == 4) {
                $status_str = "失败未回调";
            } elseif ($info['status'] == 5) {
                $status_str = "失败已回调";
            } elseif ($info['status'] == 6) {
                $status_str = "已驳回";
            }
            
            $zf_message .= "最新代付订单详情\r\n--------------------------------\r\n";
            $zf_message .= "通道名称：" . $info['df_name'] . "\r\n";
            $zf_message .= "系统订单号：`" . $info['orderid'] . "`\r\n";
            if ($info['out_trade_no'] != '') {
                $zf_message .= "外部订单号：`" . $info['out_trade_no'] . "`\r\n";
            }
            $zf_message .= "订单金额：" . $info['tkmoney'] . "\r\n";
            if ($info['sqdatetime'] != '') {
                $zf_message .= "申请时间：" . $info['sqdatetime'] . "\r\n";
            }
            if ($info['cldatetime'] != '') {
                $zf_message .= "处理时间：" . $info['cldatetime'] . "\r\n";
            }
            $zf_message .= "订单状态：" . $status_str . "\r\n";
            $zf_message .= "备注 ：" . $info['memo'] . "\r\n";
            $zf_message .= "\r\n======银行信息======\r\n";
            $zf_message .= "银行名称：" . $info['bankname'] . "\r\n";
            $zf_message .= "开户名 ：`" . $info['bankfullname'] . "`\r\n";
            $zf_message .= "账号 ：`" . $info['banknumber'] . "`\r\n";
            
            $resule2 = $this->sendMessage($Member_info['telegram_id'], $zf_message, '', 'Markdown');
            if($resule2['ok'] === true && isset($resule2['result']['message_id'])){
                $add_data = [
                        'pay_orderid' => $info['orderid'],
                        'member_id' =>$info['userid'] + 10000,
                        'chat_id' => $chat_id,
                        'chat_id2' => $Member_info['telegram_id'],
                        'message_id' => $message_id,
                        'message_id2' => $resule2['result']['message_id'],
                        'create_time'=>time(),
                    ];
                // log_place_order($this->code . '_get_dforder22', "信息", json_encode($add_data, JSON_UNESCAPED_UNICODE));    //日志
                $re = M('TelegramApiDforder')->add($add_data);
                // log_place_order($this->code . '_get_dforder22', "sql", M('TelegramApiDforder')->getLastSql());    //日志
            }
        }
        return true;
    }
   
    //代付处理
    public function doDF($order_info, $chat_id, $message, $message_id, $parse_mode='Markdown'){
        if ($order_info && $order_info['status'] === 1) {
            $info = $order_info['info'];
            $message = '';
            // if($info['status'] > 1){
                if ($info['status'] == 0) {
                    $status_str = "未处理";
                } elseif ($info['status'] == 1) {
                    $status_str = "处理中";
                } elseif ($info['status'] == 2) {
                    $status_str = "成功未回调";
                } elseif ($info['status'] == 3) {
                    $status_str = "成功已回调";
                } elseif ($info['status'] == 4) {
                    $status_str = "失败未回调";
                } elseif ($info['status'] == 5) {
                    $status_str = "失败已回调";
                } elseif ($info['status'] == 6) {
                    $status_str = "已驳回";
                }
                
                if($info['status'] == 2 || $info['status'] == 3){
                    $where['id'] = $info['df_id'];
                    $pfa_list = M('PayForAnother')->where($where)->find();
                    $file = APP_PATH . 'Payment/Controller/' . $pfa_list['code'] . 'Controller.class.php';
                    if( is_file($file) ){
                        $result = R('Payment/'.$pfa_list['code'].'/PaymentQuery', [$info, $pfa_list]);
                            log_place_order($this->code . '_get_dforder', "PaymentQuery", json_encode($result, JSON_UNESCAPED_UNICODE));    //日志
                        if($result!==FALSE && $result['status']===2 && $result['remark']!=''){
                            $remark = $result['remark'];
                        }
                    }
                }
                
                $message .= "\r\n最新代付订单详情\r\n---------------------------------------------\r\n";
                $message .= "系统订单号：`" . $info['orderid'] . "`\r\n";
                $message .= "外部订单号：`" . $info['out_trade_no'] . "`\r\n";
                $message .= "订单金额：" . $info['tkmoney'] . "\r\n";
                if ($info['sqdatetime'] != '') {
                    $message .= "申请时间：" . $info['sqdatetime'] . "\r\n";
                }
                if ($info['cldatetime'] != '') {
                    $message .= "处理时间：" . $info['cldatetime'] . "\r\n";
                }
                $message .= "订单状态：" . $status_str . "\r\n";
                $message .= "备注 ：" . $info['memo'] . "\r\n";
                // $message .= "异步通知地址：" . $info['notifyurl'] . "\r\n";
                $message .= "通知次数 ：" . $info['notifycount'] . "\r\n";
                $message .= "最后通知时间：" . date('Y-m-d H:i:s', $info['last_notify_time']) . "\r\n";
                $message .= "\r\n=========银行信息=========\r\n";
                $message .= "银行名称：" . $info['bankname'] . "\r\n";
                if($info['bankzhiname'] != '') {
                    $message .= "支行名称：" . $info['bankzhiname'] . "\r\n";
                }
                $message .= "开户名 ：" . $info['bankfullname'] . "\r\n";
                $message .= "账号 ：" . $info['banknumber'] . "\r\n";
                if(isset($remark) || $remark!=''){
                    $message .= "凭证地址 ：`" . $remark . "`\r\n";
                }
                
                
            // }else{
                
            //     $message .= "系统订单号：`" . $info['orderid'] . "`\r\n";
            //     $message .= "外部订单号：`" . $info['out_trade_no'] . "`\r\n";
            //     $message .= "订单金额：" . $info['tkmoney'] . "\r\n";
            //     $message .= "请稍等，正在为您查询...\r\n";
            //     $message .= "Please wait, we are querying for you ...";
                
            //     $add_data = [
            //         'pay_orderid' => $info['orderid'],
            //         'member_id' =>$info['userid'] + 10000,
            //         'chat_id' => $chat_id,
            //         'message_id' => $message_id,
            //         'create_time'=>time(),
            //     ];
                
            //     // log_place_order($this->code . '_get_dforder', "ApiDForder", json_encode($add_data, JSON_UNESCAPED_UNICODE));    //日志
            //     $re = M('TelegramApiDforder')->add($add_data);
            //     // log_place_order($this->code . '_get_dforder', "sql", M('TelegramApiDforder')->getLastSql());    //日志
            // }
            
        } else {
            $message = "没有查询到代付订单，三种情况\r\n--------------------------------\r\n1.单号输入不正确，请确认单号；\r\n2.不是我们的订单，请确认收付款方是不是我们；\r\n3.订单没有提交到我们这里，请联系群里运营人员。";
        }
        $this->sendMessage($chat_id, $message, $message_id, $parse_mode='Markdown');
    }

    //代收处理
    public function doDS($order_info, $chat_id, $message, $message_id, $parse_mode='Markdown'){
        
        if ($order_info && $order_info['status'] === 1) {
            $info = $order_info['info'];
            $message = '';
            // if ($info['pay_status'] == 0) {
            //     $message .= "系统订单号：`" . $info['pay_orderid'] . "`\r\n";
            //     $message .= "外部订单号：`" . $info['out_trade_id'] . "`\r\n";
            //     $message .= "订单金额：" . $info['pay_amount'] . "\r\n";
            //     $message .= "请稍等，正在为您查询...\r\n";
            //     $message .= "Please wait, we are querying for you ...";
            //     $add_data = [
            //         'pay_orderid' => $info['pay_orderid'],
            //         'member_id' =>$info['pay_memberid'],
            //         'chat_id' => $chat_id,
            //         'message_id' => $message_id,
            //         'create_time'=>time(),
            //     ];
            //     // log_place_order($this->code . '_get_order', "ApiOrder", json_encode($add_data, JSON_UNESCAPED_UNICODE));    //日志
            //     $re = M('TelegramApiOrder')->add($add_data);
            //     // log_place_order($this->code . '_get_order', "sql", M('TelegramApiOrder')->getLastSql());    //日志
            // }else{
                $message .= "\r\n最新订单详情\r\n---------------------------------------------\r\n";
                $message .= "系统订单号：`" . $info['pay_orderid'] . "`\r\n";
                $message .= "外部订单号：`" . $info['out_trade_id'] . "`\r\n";
                $message .= "订单金额：" . $info['pay_amount'] . "\r\n";
                if ($info['pay_applydate'] != '') {
                    $message .= "申请时间：" . date('Y-m-d H:i:s', $info['pay_applydate']) . "\r\n";
                }
                if ($info['pay_successdate'] != '0') {
                    $message .= "成功时间：" . date('Y-m-d H:i:s', $info['pay_successdate']) . "\r\n";
                }
                if ($info['pay_status'] == 0) {
                    $status_str = "未处理";
                } elseif ($info['pay_status'] == 1) {
                    $status_str = "成功未回调";
                } elseif ($info['pay_status'] == 2) {
                    $status_str = "成功已回调";
                }
                $message .= "订单状态：" . $status_str . "\r\n";
                
                //删除6小时前的记录
                M('TelegramApiOrder')->where(['create_time' => ['elt', (time() - 3600 * 72)]])->delete();
                // log_place_order($this->code . '_get_order', "sql", M('TelegramApiOrder')->getLastSql());    //日志
            // }
        } else {
            $message = "没有查询到订单，三种情况\r\n--------------------------------\r\n 1.单号输入不正确，请确认单号；\r\n 2.不是我们的订单，请确认收付款方是不是我们；\r\n 3.订单没有提交到我们这里，请联系群里运营人员。";
        }
        return $this->sendMessage($chat_id, $message, $message_id, $parse_mode);
    }
 

    //根据群ID 获取用户信息
    private function get_user($chat_id)
    {
        $info = [];
        if ($chat_id) {
            $where=[];
            $where['telegram_id'] = $chat_id;
            $info = M('Member')->field('id, username, balance_php, blockedbalance_php, balance_inr, blockedbalance_inr, df_api, status, df_ip')->where($where)->find();
            // log_place_order($this->code . '_get_user', "sql", M('Member')->getLastSql());    //日志
            if ($info) {
                $status = 1;
            } else {
                $status = 0;
            }
        } else {
            $status = 0;
        }
        $data = [
            'status' => $status,
            'info' => $info,
        ];
        // log_place_order($this->code . '_get_user', "返回", json_encode($data, JSON_UNESCAPED_UNICODE));    //日志
        return $data;
    }

    //获取订单信息
    private function get_order($orderid, $uid)
    {
        $info = [];
        if ($orderid && $uid) {
            $where=[];
            $map['pay_orderid']  = ['eq',$orderid];
            $map['out_trade_id']  = ['eq',$orderid];
            $map['_logic'] = 'or';
            $where['_complex'] = $map;
            $where['pay_memberid'] = ['eq', $uid + 10000];
            
            $field = 'id,pay_memberid, pay_orderid, pay_amount, pay_poundage, pay_actualamount, pay_applydate ,pay_successdate, pay_bankname, pay_status, out_trade_id';
            $OrderModel = D('Order');   
            $tables = $OrderModel->getTables();
            // log_place_order($this->code . '_get_order', "表", json_encode($tables, JSON_UNESCAPED_UNICODE));    //日志
            foreach ($tables as $v){
                $order = $OrderModel->table($v)
                ->field($field)
                ->where($where)
                ->select();
                // log_place_order($this->code . '_get_order', "sql:".$v, $OrderModel->table($v)->getLastSql());    //日志
                if(!empty($order)) break;
            }
            $info = $order[0];
            // log_place_order($this->code . '_get_order', "sql", M('Order')->getLastSql());    //日志
            if ($info) {
                $status = 1;
            } else {
                $status = 0;
            }
        } else {
            $status = 0;
        }
        $data = [
            'status' => $status,
            'info' => $info,
        ];
        // log_place_order($this->code . '_get_order', "返回", json_encode($data, JSON_UNESCAPED_UNICODE));    //日志
        return $data;
    }

    //获取订单信息
    private function get_dforder($orderid, $uid)
    {
        $info = [];
        if ($orderid && $uid) {
            $where=[];
            $map['orderid']  = ['eq', $orderid];
            $map['out_trade_no']  = ['eq', $orderid];
            $map['_logic'] = 'or';
            $where['_complex'] = $map;
            $where['userid'] =['eq', $uid];
            $field = 'id, userid, orderid, out_trade_no, tkmoney, sxfmoney, money, status, bankname, bankzhiname, banknumber, bankfullname, sqdatetime, cldatetime, memo, notifyurl, notifycount, last_notify_time,df_id, df_name';
            $Wttklist = D('Wttklist');   
            $tables = $Wttklist->getTables();
            foreach ($tables as $v){
                $order = $Wttklist->table($v)
                ->field($field)
                ->where($where)
                ->select();
                // log_place_order($this->code . '_get_dforder', "sql", $Wttklist->table($v)->getLastSql());    //日志
                if(!empty($order)) break;
            }
            $info = $order[0];
            if ($info) {
                $status = 1;
            } else {
                $status = 0;
            }
        } else {
            $status = 0;
        }
        $data = [
            'status' => $status,
            'info' => $info,
        ];
        // log_place_order($this->code . '_get_dforder', "返回", json_encode($data, JSON_UNESCAPED_UNICODE));    //日志
        return $data;
    }
    
    //用户当日统计
    private function statistics($uid){
        $data = [];
        $todayBegin = date('Y-m-d') . ' 00:00:00';
        $todyEnd = date('Y-m-d') . ' 23:59:59';
        $where=[];
        $where = [
            'pay_memberid' => $uid,
            'pay_applydate' => ['between', [strtotime($todayBegin), strtotime($todyEnd)]],
            ];
        //今日平台总入金
        $field = 'id, pay_amount, pay_status';
        $OrderModel = D('Order');   
        $order_statistics = $OrderModel->getOrderByDateRange($field, $where);
        // $order_statistics = M('Order')->field('id, pay_amount, pay_status')->where($where)->select();
        $oi = $o_sum = $all_o_sum = $all_o_i = 0;
        foreach ($order_statistics as $ok => $ov) {
            $all_o_sum += $ov['pay_amount'];
            $all_o_i++;
            if($ov['pay_status']==1 || $ov['pay_status']==2){
                $o_sum += $ov['pay_amount'];
                $oi++;
            }
        }
        $data['all_o_sum'] = $all_o_sum;
        $data['all_o_num'] = $all_o_i;
        $data['o_sum'] = $o_sum;
        $data['o_num'] = $oi;
        $data['o_success_rate'] = sprintf("%.4f", $oi / $all_o_i) * 100;
        if($data['o_success_rate'] > 10 && $data['o_success_rate'] < 90){
            $data['o_success_rate']+=5;
        }
        
        $wi_do = $w_sum_do = $wi_done = $w_sum_done = $all_w_amount = $all_w_i = 0;
        $dfwhere = [
            'sqdatetime' => ['between', [$todayBegin, $todyEnd]],
            'userid' => $uid - 10000,
            ];
        $field = 'id, tkmoney, status';
        $Wttklist = D('Wttklist');  
        
        $withdraw_statistics = $Wttklist->getOrderByDateRange($field, $dfwhere);
        foreach ($withdraw_statistics as $wk => $wv) {
            $all_w_amount += $wv['tkmoney'];
            $all_w_i++;
            if($wv['status'] == 1){
                $w_sum_do += $wv['tkmoney'];
                $wi_do++;
            }
            if($wv['status'] == 2 || $wv['status'] == 3){
                $w_sum_done += $wv['tkmoney'];
                $wi_done++;
            }
        }
        $data['all_w_amount'] = $all_w_amount;
        $data['all_w_i'] = $all_w_i;
        $data['w_sum_do'] = $w_sum_do;
        $data['wi_do'] = $wi_do;
        $data['w_sum_done'] = $w_sum_done;
        $data['wi_done'] = $wi_done;
        
        return $data;
    }

    protected function redis_connect(){
        //创建一个redis对象
        $redis = new \Redis();
        //连接 Redis 服务
        $redis->connect(C('REDIS_HOST'), C('REDIS_PORT'));
        //密码验证
        $redis->auth(C('REDIS_PWD'));
        return $redis;
    }
}