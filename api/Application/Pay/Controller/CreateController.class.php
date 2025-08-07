<?php
namespace Pay\Controller;

class CreateController extends PayController
{
    protected $channel; //

    protected $memberid; //商户ID

    protected $pay_amount; //交易金额

    protected $bankcode; //银行码

    protected $orderid; //订单号
    
    protected $product;

    public function __construct()
    {
        parent::__construct();
        if (empty($_POST)) {
            $this->showmessage('no data!');
        }
    }
    public function index(){
        $this->showmessage("api error");
    }
    //代收接口
    public function payinPHP(){
        $this->firstCheckParams(); //初步验证参数 ，设置memberid，pay_amount，bankcode属性
        
        $this->productIsOpen(); //判断通道是否开启
        
        
        $currency = I('request.pay_currency', '', 'string,strip_tags,htmlspecialchars');
        if($currency && $currency!='PH'){
            $this->showmessage("pay_currency error");
        }
        
        // if(getPaytypeCurrency($this->product['paytype']) !=='PHP'){
        //     $this->showmessage("The currency type is incorrect");
        // }

        $this->judgeRepeatOrder(); //验证是否可以提交重复订单

        $this->userRiskcontrol(); //用户风控检测

        $this->productUserIsSet();
        
        $this->setChannelApiControl(); //判断是否开启支付渠道 ，获取并设置支付通api的id和通道风控
        
        $this->doNext();
    }
    //墨西哥代收接口
    public function payinMXN(){
        $this->firstCheckParams(); //初步验证参数 ，设置memberid，pay_amount，bankcode属性
        $currency = I('request.pay_currency', '', 'string,strip_tags,htmlspecialchars');
        if(!$currency || $currency!='MXN'){
            $this->showmessage("pay_currency error");
        }
        $this->productIsOpen(); //判断通道是否开启
        
        // if(getPaytypeCurrency($this->product['paytype']) !=='PHP'){
        //     $this->showmessage("The currency type is incorrect");
        // }

        $this->judgeRepeatOrder(); //验证是否可以提交重复订单

        $this->userRiskcontrol(); //用户风控检测

        $this->productUserIsSet();
        
        $this->setChannelApiControl(); //判断是否开启支付渠道 ，获取并设置支付通api的id和通道风控
        
        $this->doNext();
    }
    //巴基斯坦代收接口
    public function payinPAK(){
        $this->firstCheckParams(); //初步验证参数 ，设置memberid，pay_amount，bankcode属性
        
        $currency = I('request.pay_currency', '', 'string,strip_tags,htmlspecialchars');
        if(!$currency || $currency!='PKR'){
            $this->showmessage("pay_currency error");
        }
        
        $extends = I("request.pay_extends", '');
        if (!$extends) {
            $this->showmessage('pay_extends cannot be empty');
        }
        $extend_fields_array = json_decode(base64_decode($extends), true);
        $fields = ['cnic', 'phone'];    //扩展字段
        foreach($fields as $k => $v) {
            if(!isset($extend_fields_array[$v]) || $extend_fields_array[$v]=='') {
                $this->showmessage('The secondary parameter of the extends field 【' . $v . '】 cannot be empty');
            }
        }
        
        $this->productIsOpen(); //判断通道是否开启
        
        // if(getPaytypeCurrency($this->product['paytype']) !=='PHP'){
        //     $this->showmessage("The currency type is incorrect");
        // }

        $this->judgeRepeatOrder(); //验证是否可以提交重复订单

        $this->userRiskcontrol(); //用户风控检测

        $this->productUserIsSet();
        
        $this->setChannelApiControl(); //判断是否开启支付渠道 ，获取并设置支付通api的id和通道风控
        
        $this->doNext();
    }
    
    //印度代收接口
    public function payinIND(){
        $this->firstCheckParams(); //初步验证参数 ，设置memberid，pay_amount，bankcode属性
        
        $currency = I('request.pay_currency', '', 'string,strip_tags,htmlspecialchars');
        if(!$currency || $currency!='INR'){
            $this->showmessage("pay_currency error");
        }
        
        $this->productIsOpen(); //判断通道是否开启
        
        // if(getPaytypeCurrency($this->product['paytype']) !=='PHP'){
        //     $this->showmessage("The currency type is incorrect");
        // }

        $this->judgeRepeatOrder(); //验证是否可以提交重复订单

        $this->userRiskcontrol(); //用户风控检测

        $this->productUserIsSet();
        
        $this->setChannelApiControl(); //判断是否开启支付渠道 ，获取并设置支付通api的id和通道风控
        
        $this->doNext();
    }
    //越南代收接口
    public function payinVN(){
        $this->firstCheckParams(); //初步验证参数 ，设置memberid，pay_amount，bankcode属性
        
        $currency = I('request.pay_currency', '', 'string,strip_tags,htmlspecialchars');
        if(!$currency || $currency!='VND'){
            $this->showmessage("pay_currency error");
        }
        
        $this->productIsOpen(); //判断通道是否开启
        
        // if(getPaytypeCurrency($this->product['paytype']) !=='PHP'){
        //     $this->showmessage("The currency type is incorrect");
        // }

        $this->judgeRepeatOrder(); //验证是否可以提交重复订单

        $this->userRiskcontrol(); //用户风控检测

        $this->productUserIsSet();
        
        $this->setChannelApiControl(); //判断是否开启支付渠道 ，获取并设置支付通api的id和通道风控
        
        $this->doNext();
    }
    //孟加拉代收接口
    public function payinBD(){
        $this->firstCheckParams(); //初步验证参数 ，设置memberid，pay_amount，bankcode属性
        
        $currency = I('request.pay_currency', '', 'string,strip_tags,htmlspecialchars');
        if(!$currency || $currency!='BDT'){
            $this->showmessage("pay_currency error");
        }
        
        $this->productIsOpen(); //判断通道是否开启
        
        // if(getPaytypeCurrency($this->product['paytype']) !=='PHP'){
        //     $this->showmessage("The currency type is incorrect");
        // }

        $this->judgeRepeatOrder(); //验证是否可以提交重复订单

        $this->userRiskcontrol(); //用户风控检测

        $this->productUserIsSet();
        
        $this->setChannelApiControl(); //判断是否开启支付渠道 ，获取并设置支付通api的id和通道风控
        
        $this->doNext();
    }
    
    //创建代收申请
    protected function doNext()
    {
        //进入支付
        if ($this->channel['api']) {
            $redis = $this->redis_connect();
            $info_redis = $redis->get('channel_'. $this->channel['api']);
            $info = json_decode($info_redis,true);
            if(!$info_redis || empty($info)){
                $info = M('Channel')->where(['id' => $this->channel['api'], 'status' => 1])->find();
                $redis->set('channel_'. $this->channel['api'], json_encode($info, JSON_UNESCAPED_UNICODE));
                $redis->expire('channel_'. $this->channel['api'] , 60);
            }
            
            //是否存在通道文件
            if (!is_file(APP_PATH . '/' . MODULE_NAME . '/Controller/' . $info['code'] . 'Controller.class.php')) {
                $this->showmessage('channel error', ['pay_bankcode' => $this->channel['api'],'code'=>$info['code']]);
            }
            if (R($info['code'] . '/Pay', [$this->channel]) === false) {
                $this->showmessage('Server error...');
            }
        } else {
            $this->showmessage("Sorry......Server error");
        }
    }

    //======================================辅助方法===================================

    /**
     * [初步判断提交的参数是否合法并设置为属性]
     */
    protected function firstCheckParams()
    {
        
        $this->memberid = I("request.pay_memberid", 0, 'intval') - 10000;
        // if($this->memberid!=2){return;}
        
        // 商户编号不能为空
        if (empty($this->memberid) || $this->memberid <= 0) {
            $this->showmessage("pay_memberid error");
        }
            
        $this->orderid = I('post.pay_orderid', '');
        if (!$this->orderid) {
            $this->showmessage('pay_orderid error');
        }
        
        $postData = I('post.');
        $postData['get_time'] = microtime(TRUE);
        $redis = $this->redis_connect();
        $redis->set('userpost_' . $this->orderid, json_encode($postData, JSON_UNESCAPED_UNICODE), 300);
        
        // logApiAdd('商户代收提交YunPay', __METHOD__, $this->memberid, $this->orderid, 1, $postData, getRealIp());
        
        $this->pay_applydate = I('post.pay_applydate', '');
        if (!$this->pay_applydate) {
            $this->showmessage('pay_applydate error');
        }
        //银行编码
        $this->bankcode = I('request.pay_bankcode', 0, 'intval');
        if ($this->bankcode == 0) {
            $this->showmessage('pay_bankcode error', ['pay_banckcode' => $this->bankcode]);
        }
        $this->pay_notifyurl = I('post.pay_notifyurl', '');
        if (!$this->pay_notifyurl) {
            $this->showmessage('pay_notifyurl error');
        }
        $this->pay_callbackurl = I('post.pay_callbackurl', '');
        if (!$this->pay_callbackurl) {
            $this->showmessage('pay_callbackurl error');
        }
        $this->pay_amount = I('post.pay_amount', '0');
        if ($this->pay_amount == 0) {
            $this->showmessage('pay_amount error');
        }
    }

    /**
     * [用户风控]
     */
    protected function userRiskcontrol()
    {
        $l_UserRiskcontrol = new \Pay\Logic\UserRiskcontrolLogic($this->pay_amount, $this->memberid); //用户风控类
        $error_msg         = $l_UserRiskcontrol->monitoringData();
        if ($error_msg !== true) {
            $this->showmessage('Merchant：' . $error_msg);
        }
    }
    
    /**
     * [productIsOpen 判断通道是否开启，并分配]
     * @return [type] [description]
     */
    protected function productIsOpen()
    {
        $redis = $this->redis_connect();
        $product_redis = $redis->get('Product_'. $this->bankcode);
        $product = json_decode($product_redis,true);
        if(!$product_redis || empty($product)){
            $product = M('Product')->where(['id' => $this->bankcode, 'status' => 1])->field('id,paytype')->find();
            $redis->set('Product_'. $this->bankcode, json_encode($product, JSON_UNESCAPED_UNICODE));
            $redis->expire('Product_'. $this->bankcode, 60);
        }
        
        //通道关闭
        if (empty($product)) {
            $this->showmessage('Unable to connect to payment server');
        }
        $this->product = $product;
    }

    /**
     * [productIsOpen 判断通道是否开启，并分配]
     * @return [type] [description]
     */
    protected function productUserIsSet()
    {
        $redis = $this->redis_connect();
        $productUser_redis = $redis->get('ProductUser_'. $this->bankcode . '_' . $this->memberid);
        $productUser = json_decode($productUser_redis,true);
        if(!$productUser_redis || empty($productUser)){
            $productUser = M('ProductUser')->where(['pid' => $this->bankcode, 'userid' => $this->memberid, 'status' => 1])->find();
            // $productUser['paytype'] = $this->product['paytype'];
            $redis->set('ProductUser_'. $this->bankcode . '_' . $this->memberid, json_encode($productUser, JSON_UNESCAPED_UNICODE));
            $redis->expire('ProductUser_'. $this->bankcode . '_' . $this->memberid, 60);
        }
        $this->channel = $productUser;
        //用户未分配
        if (!$this->channel) {
            $this->showmessage('The channel is closed');
        }
    }

    /**
     * [判断是否开启支付渠道 ，获取并设置支付通api的id---->轮询+风控]
     */
    protected function setChannelApiControl()
    {
        $l_ChannelRiskcontrol = new \Pay\Logic\ChannelRiskcontrolLogic($this->pay_amount); //支付渠道风控类
        $m_Channel            = M('Channel');

        if (isset($this->channel['polling']) && $this->channel['polling'] == 1 && isset($this->channel['weight']) && $this->channel['weight']) {

            /***********************多渠道,轮询，权重随机*********************/
            $weight_item  = [];
            $error_msg    = ' has gone offline';
            $temp_weights = explode('|', $this->channel['weight']);
            foreach ($temp_weights as $k => $v) {

                list($pid, $weight) = explode(':', $v);
                //检查是否开通
                $temp_info = $m_Channel->where(['id' => $pid, 'status' => 1])->find();
                if(!$temp_info) continue;

                //判断通道是否开启风控并上线
                if ($temp_info['offline_status'] == 1 && $temp_info['control_status'] == 1) {

                    //判断订单金额是否在指定金额数组里，开始
//                    if ($temp_info['fix_money'] && $temp_info['fix_money'] !== null) {
//                        $fix_money = explode(',', $temp_info['fix_money']);
//                        if (in_array($this->pay_amount, $fix_money)) {
//                            //-------------------------进行风控-----------------
//                            $l_ChannelRiskcontrol->setConfigInfo($temp_info); //设置配置属性
//                            $error_msg = $l_ChannelRiskcontrol->monitoringData();
//                            if ($error_msg === true) {
//                                $weight_item[] = ['pid' => $pid, 'weight' => $weight];
//                            }
//                        }
//                    } else {
                        //-------------------------进行风控-----------------
                        $l_ChannelRiskcontrol->setConfigInfo($temp_info); //设置配置属性
                        $error_msg = $l_ChannelRiskcontrol->monitoringData();
                        if ($error_msg === true) {
                            $weight_item[] = ['pid' => $pid, 'weight' => $weight];
                        }
//                    }
                    //判断订单金额是否在指定金额数组里，结束
                } else if ($temp_info['control_status'] == 0) {
                    $weight_item[] = ['pid' => $pid, 'weight' => $weight];
                }

            }

            //如果所有通道风控，提示最后一个消息
            if ($weight_item == []) {
                $this->showmessage('Channel :' . $error_msg);
            }
            $weight_item          = getWeight($weight_item);
            $this->channel['api'] = $weight_item['pid'];

        } else {
            /***********************单渠道,没有轮询*********************/

            //查询通道信息
            $pid          = $this->channel['channel'];
            $channel_info = $m_Channel->where(['id' => $pid])->find();

            //通道风控
            $l_ChannelRiskcontrol->setConfigInfo($channel_info); //设置配置属性
            $error_msg = $l_ChannelRiskcontrol->monitoringData();

            if ($error_msg !== true) {
                $this->showmessage('Channel :' . $error_msg);
            }
            $this->channel['api'] = $pid;
        }
    }

    /**
     * 判断是否可以重复提交订单
     * @return [type] [description]
     */
    protected function judgeRepeatOrder()
    {
        $is_repeat_order = M('Websiteconfig')->getField('is_repeat_order');
        if ($is_repeat_order!==1) {
            $redis = $this->redis_connect();
            $count = $redis->get($this->orderid);
            // var_dump($count);
            if($count!==false){
                $this->showmessage('Duplicate order!');
            }
        }
    }

}
