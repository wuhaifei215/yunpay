<?php
/*
 *1. 以下是有关支付的方法，签名方式等可以进行参考，具体的业务逻辑实现还需要参考文档，有不懂的可以和收钱吧技术人员确认。
 *
 *2. 请求支付后，订单的状态信息通过轮询的方式获取。
 * */

if ($_REQUEST['action']=='pay')
{//支付

$terminal_sn = '100012510006301785';
$terminal_key = 'c96d8b1e9699f76c8669098247cc5cfb';
pay($terminal_sn, $terminal_key);
}

if ($_REQUEST['action'] == 'checkin') {//签到

    $terminal_sn = '';
    $terminal_key = '';
    checkin($terminal_sn, $terminal_key);
}

if ($_REQUEST['action'] == 'refund') {//退款
    $terminal_sn = '';
    $terminal_key = '';
    refund($terminal_sn, $terminal_key);
}

if ($_REQUEST['action'] == 'activate') {//激活
    $vendor_sn=$_REQUEST['vendor_sn'];
    $vendor_key =$_REQUEST['vendor_key'];// 
    $app_id =$_REQUEST['app_id'];// 
    $code =$_REQUEST['code'];//
    // print_r($vendor_sn.'-'.$vendor_key.'-'.$app_id.'-'.$code);die();
    activate($vendor_sn, $vendor_key,$app_id,$code);    
}

if ($_REQUEST['action'] == 'precreate') {// 预下单
    $terminal_sn = '100012510006301785';//"terminal_sn":"100114020002373208","terminal_key":"059c443b2e67d2c4630e218b3282887c"
    $terminal_key = 'c96d8b1e9699f76c8669098247cc5cfb';
    precreate($terminal_sn, $terminal_key);
}

if ($_REQUEST['action'] == 'cancel') {//冲正
    $terminal_sn = '';
    $terminal_key = '';
    cancel($terminal_sn, $terminal_key);
}

if ($_REQUEST['action'] == 'revoke') {// 主动撤单
    $terminal_sn = '';
    $terminal_key = '';
    revoke($terminal_sn, $terminal_key);
}

if ($_REQUEST['action'] == 'query') {//查找
    $terminal_sn = '100012510006301785';
    $terminal_key = 'c96d8b1e9699f76c8669098247cc5cfb';
    query($terminal_sn, $terminal_key);
}

if ($_REQUEST['action'] == 'wap_api_pro') {//wap api pro
    $terminal_sn = '';//
    $terminal_key = '';//
    wap_api_pro($terminal_sn, $terminal_key);
}

function  pay($terminal_sn, $terminal_key)
{
    $api_domain = 'https://api.shouqianba.com';
    $url = $api_domain . "/upay/v2/pay";

    $params['terminal_sn'] = $terminal_sn;          //终端号
    $params['client_sn'] = getClient_Sn(16); //商户系统订单号,必须在商户系统内唯一；且长度不超过64字节
    $params['total_amount'] = '1';                   //交易总金额,以分为单位
    $params['payway'] = '1';                         //支付方式,1:支付宝 3:微信 4:百付宝 5:京东钱包
    $params['dynamic_id'] = '';  //条码内容
    $params['subject'] = '披萨';                   //交易简介
    $params['operator'] = 'kay';                    //门店操作员

    $ret = pre_do_execute($params, $url, $terminal_sn, $terminal_key);

    return $ret;

}


function checkin($terminal_sn, $terminal_key)
{
    $api_domain = 'https://api.shouqianba.com';
    $url = $api_domain . '/terminal/checkin';

    $params['terminal_sn'] = $terminal_sn;              //终端号
    $params['device_id'] = '123';//设备唯一身份ID

    //    $params['os_info']='';                 //当前系统信息，如: Android5.0
    //    $params['sdk_version']='';                    //SDK版本

    $ret = pre_do_execute($params, $url, $terminal_sn, $terminal_key);

    return $ret;
//         string(189) "{"result_code":"200","biz_response":{"terminal_sn":"100114020002343785","terminal_key":"e79d5371d7dda6cfcb875ef67db33234",
//"merchant_sn":"","merchant_name":"","store_sn":"","store_name":""}}"

}

function refund($terminal_sn, $terminal_key)
{
    $api_domain = 'https://api.shouqianba.com';
    $url = $api_domain . '/upay/v2/refund';
    $params['terminal_sn'] = $terminal_sn;           //收钱吧终端ID
    $params['sn'] = '7895253810887036';              //收钱吧系统内部唯一订单号
//            $params['client_sn']='6521100263201711301108897858';//商户系统订单号,必须在商户系统内唯一；且长度不超过64字节
    $params['refund_amount'] = '1';                   //退款金额
    $params['refund_request_no'] = '001';                 //商户退款所需序列号,表明是第几次退款
    $params['operator'] = 'kay';                    //门店操作员

    $ret = pre_do_execute($params, $url, $terminal_sn, $terminal_key);

    return $ret;
}

function activate($vendor_sn, $vendor_key,$app_id,$code)
{

    // print_r($vendor_sn.' '.$vendor_key);die();
    $api_domain = 'https://api.shouqianba.com';
    $url = $api_domain . '/terminal/activate';

    $params['app_id'] = $app_id;           //app id，从服务商平台获取2017112500000439
    $params['code'] = $code;              //激活码内容11654978
    $params['device_id'] = (string)time();//设备唯一身份ID

//    $params['client_sn']='';                   //第三方终端号，必须保证在app id下唯一
//    $params['name']='';                       //终端名
//    $params['os_info']='';                 //当前系统信息，如: Android5.0
//    $params['sdk_version']='';                    //SDK版本

    $j_params = json_encode($params);
    $sign = getSign($j_params . $vendor_key);

    // $result = httpPost($url, $j_params, $sign, $vendor_sn);

    $ret = pre_do_execute($params, $url, $vendor_sn, $vendor_key);
    $array_info=json_decode($ret,true);
    if ($array_info['result_code']=='200') {
        echo "商户名字:".$array_info['biz_response']['merchant_name'];
        echo "</br>以下信息请保存在文档</br>";
        echo "terminal_sn：".$array_info['biz_response']['terminal_sn'];
        echo "</br>";
        echo "terminal_key：".$array_info['biz_response']['terminal_key'];
        echo "</br>";
    
    }else{
        echo "您填写信息有误！请返回充填，错误代码如下"."</br>";
        print_r($ret);
    }
   
 exit();
//    string(247) "{"result_code":"200","biz_response":{"terminal_sn":"100114020002373208","terminal_key":"059c443b2e67d2c4630e218b3282887c",
//"merchant_sn":"18956397746","merchant_name":"半夜鸡叫","store_sn":"00010101001200200046406","store_name":"半夜鸡叫"}}"

}

function precreate($terminal_sn, $terminal_key)
{
    $api_domain = 'https://api.shouqianba.com';
    $url = $api_domain . '/upay/v2/precreate';

    $params['terminal_sn'] = $terminal_sn;           //收钱吧终端ID
//        $params['sn']='7895253130995555';              //收钱吧系统内部唯一订单号
    $params['client_sn'] = '6521100'.time();//商户系统订单号,必须在商户系统内唯一；且长度不超过64字节
    $params['total_amount'] = '1';                   //金额
    $params['payway'] = '3';                 //内容为数字的字符串 支付方式
    $params['subject'] = 'pizza';                //本次交易的概述
    $params['operator'] = 'Obama';              //发起本次交易的操作员


       //$params['sub_payway']='3';               //内容为数字的字符串，如果要使用WAP支付，则必须传 "3", 使用小程序支付请传"4"
//        $params['payer_uid']='kay';                    //消费者在支付通道的唯一id,微信WAP支付必须传open_id,支付宝WAP支付必传用户授权的userId
//        $params['description']='';            //对商品或本次交易的描述
//        $params['longitude']='';             //经纬度必须同时出现
//        $params['latitude']='';              //经纬度必须同时出现
//        $params['extended']='';              //收钱吧与特定第三方单独约定的参数集合,json格式，最多支持24个字段，每个字段key长度不超过64字节，value长度不超过256字节
//        $params['goods_details']='';        //
//        $params['reflect']='';              //任何调用者希望原样返回的信息
//        $params['notify_url']='';            //支付回调的地址
    $ret = pre_do_execute($params, $url, $terminal_sn, $terminal_key);
    /*
     * string(44) "https://api.shouqianba.com/upay/v2/precreate"
    string(724) "{"result_code":"200","error_code":"","error_message":"","biz_response":{"result_code":"PRECREATE_SUCCESS","error_code":"","error_message":"","data":{"sn":"7895253189084906","client_sn":"6521100263201711163297047920",
    "client_tsn":"6521100263201711163297047920","trade_no":"","finish_time":"","channel_finish_time":"","status":"CREATED","order_status":"CREATED","payway":"1","payway_name":"支付宝","sub_payway":"2","payer_uid":"","payer_login":"","total_amount":"1","net_amount":"1",
    "qr_code":"https://qr.alipay.com/bax06545wtwccfvlsmxj0076","qr_code_image_url":"https://api.shouqianba.com/upay/qrcode?content=https%3A%2F%2Fqr.alipay.com%2Fbax06545wtwccfvlsmxj0076","subject":"pizza","operator":"Obama","payment_list":[]}}}"
     *
     *
     * */
    return $ret;

}

function cancel($terminal_sn, $terminal_key)
{
    $api_domain = 'https://api.shouqianba.com';
    $url = $api_domain . '/upay/v2/cancel';

    $params['terminal_sn'] = $terminal_sn;           //收钱吧终端ID
//        $params['sn']='7895253130997784';              //收钱吧系统内部唯一订单号
    $params['client_sn'] = '0006';//商户系统订单号,必须在商户系统内唯一；且长度不超过64字节

    $ret = pre_do_execute($params, $url, $terminal_sn, $terminal_key);

//        string(41) "https://api.shouqianba.com/upay/v2/revoke"
//string(220) "{"result_code":"200","error_code":"","error_message":"","biz_response":
//{"result_code":"FAIL","error_code":"UPAY_CANCEL_INVALID_ORDER_STATE","error_message":"当前的订单7895253130997784状态是REFUNDED","data":null}}"
    return $ret;

}

function revoke($terminal_sn, $terminal_key)
{
    $api_domain = 'https://api.shouqianba.com';
    $url = $api_domain . '/upay/v2/revoke';

    $params['terminal_sn'] = $terminal_sn;           //收钱吧终端ID
//        $params['sn']='7895253180902211';              //收钱吧系统内部唯一订单号
    $params['client_sn'] = '6521100263201711163297047555';//商户系统订单号,必须在商户系统内唯一；且长度不超过64字节

    $ret = pre_do_execute($params, $url, $terminal_sn, $terminal_key);

    return $ret;

}

function query($terminal_sn, $terminal_key)
{
    $api_domain = 'https://api.shouqianba.com';
    $url = $api_domain . '/upay/v2/query';

    $params['terminal_sn'] = $terminal_sn;           //收钱吧终端ID
//        $params['sn']='7895253130997784';              //收钱吧系统内部唯一订单号
    $params['client_sn'] = 'E2019011110270037943';//商户系统订单号,必须在商户系统内唯一；且长度不超过64字节

    $ret = pre_do_execute($params, $url, $terminal_sn, $terminal_key);
    /*string(40) "https://api.shouqianba.com/upay/v2/query"
    string(594) "{"result_code":"200","error_code":"","error_message":"","biz_response":
    {"result_code":"SUCCESS","error_code":"","error_message":"","data":{"sn":"7895253130997784","client_sn":"2002673090172838","client_tsn":"2002673090172838-001",
    "trade_no":"6521100263201711162107115070","finish_time":"1510803598466","channel_finish_time":"","status":"SUCCESS",
    "order_status":"REFUNDED","payway":"1","payway_name":"支付宝","sub_payway":"1","payer_uid":"","payer_login":"","total_amount":"1",
    "net_amount":"0","subject":"Pizza","operator":"kay","payment_list":[{"type":"ALIPAY_HUABEI","amount_total":"1"}]}}}"*/
    return $ret;

}

function wap_api_pro($terminal_sn, $terminal_key)
{
    $params['terminal_sn'] = $terminal_sn;           //收钱吧终端ID
    $params['client_sn'] = '005';//商户系统订单号,必须在商户系统内唯一；且长度不超过64字节
    $params['total_amount'] = '1';//以分为单位,不超过10位纯数字字符串,超过1亿元的收款请使用银行转账
    $params['subject'] = 'pizza';//本次交易的概述
    $params['notify_url'] = 'http://10.0.0.157/dashboard/test.php';
    $params['operator'] = 'Obama';//发起本次交易的操作员
    $params['return_url'] = 'http://www.baidu.com';

    ksort($params);

    $param_str = "";
    foreach ($params as $k => $v) {
        $param_str .= $k . '=' . $v . '&';
    }

    $sign = strtoupper(md5($param_str . 'key=' . $terminal_key));
    $paramsStr = $param_str . "sign=" . $sign;


    $res = "https://m.wosai.cn/qr/gateway?" . $paramsStr;
    //将这个url生成二维码扫码或在微信链接中打开可以完成测试
    file_put_contents('logs/wap_api_pro_' . date('Y-m-d') . '.txt', $res, FILE_APPEND);

    /*
     * https://m.wosai.cn/qr/gateway?client_sn=0007&notify_url=https://www.shouqianba.com/&operator=Obama&
     * return_url=http://www.baidu.com&subject=pizza&terminal_sn=100114020002444498&
     * total_amount=1&sign=40CF32733C5A8AF3FE1D175196762458
     * */
//        var_dump($res);exit;
//    header($res);

}

function pre_do_execute($params, $url, $terminal_sn, $terminal_key)
{
    $j_params = json_encode($params);
    $sign = getSign($j_params . $terminal_key);
    $result = httpPost($url, $j_params, $sign, $terminal_sn);
    return $result;
}

function getClient_Sn($codeLenth)
{
    $str_sn = '';
    for ($i = 0; $i < $codeLenth; $i++) {
        if ($i == 0)
            $str_sn .= rand(1, 9); // first field will not start with 0.
        else
            $str_sn .= rand(0, 9);
    }
    return $str_sn;

}

function getSign($signStr)
{

    $md5 = Md5($signStr);
    return $md5;

}


function httpPost($url, $body, $sign, $sn)
{

    $header = array(
        "Format:json",
        "Content-Type: application/json",
        "Authorization:$sn" . ' ' . $sign
    );


    $result = do_execute($url, $body, $header);
    return $result;

}


function do_execute($url, $postfield, $header)
{
    //    var_dump($url);echo '<br>';
    //    var_dump($postfield);echo '<br>';
    //    var_dump($header);echo '<br>';exit;

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);  // 从证书中检查SSL加密算法是否存在

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postfield);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);

    $response = curl_exec($ch);
 
    // var_dump($url);
    // echo '<br>';
    // var_dump($response);
    // exit;

    //    $httpStatusCode = curl_getinfo($ch);

    curl_close($ch);
    return $response;
}




