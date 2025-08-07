<?php
error_reporting(0);
header("Content-type: text/html; charset=utf-8");
$pay_memberid = "10002";//商户ID
$pay_orderid = $_POST["orderid"];    //订单号
$pay_amount =  $_POST["amount"];    //交易金额
$pay_bankcode = $_POST["channel"];   //银行编码
if(empty($pay_memberid)||empty($pay_amount)||empty($pay_bankcode)){
    die("信息不完整！");
}
$pay_applydate = date("Y-m-d H:i:s");  //订单时间
$http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://'; 
$pay_notifyurl = $http_type . $_SERVER['HTTP_HOST'] . "/gmtest/server.php";   //服务端返回地址
$pay_callbackurl = $http_type . "pglht.yunpay.me/gmtest/page.php";  //页面跳转返回地址
$Md5key = "sy016baa755zjmp6dnxl0ezr2asxio8z";   //密钥
if(in_array($pay_bankcode,['918','919','920','921','922'])){
    $tjurl = $http_type . "papi.yunpay.me/Pay_Create_payinVN.html";   //提交地址
    $pay_currency = 'VND';
}elseif(in_array($pay_bankcode,['913','914','915'])){
    $tjurl = $http_type . "papi.yunpay.me/Pay_Create_payinIND.html";   //提交地址
    $pay_currency = 'INR';
}elseif(in_array($pay_bankcode,['912','916','917'])){
    $tjurl = $http_type . "papi.yunpay.me/Pay_Create_payinPAK.html";   //提交地址
    $pay_currency = 'PKR';
}elseif(in_array($pay_bankcode,['924','925','926'])){
    $tjurl = $http_type . "papi.yunpay.me/Pay_Create_payinBD.html";   //提交地址
    $pay_currency = 'BDT';
}else{
    $tjurl = $http_type . "papi.yunpay.me/Pay_Create_payinPHP.html";   //提交地址
    $pay_currency = 'PH';
}



//扫码
$native = array(
    "pay_memberid" => $pay_memberid,
    "pay_orderid" => $pay_orderid,
    "pay_amount" => $pay_amount,
    "pay_applydate" => $pay_applydate,
    "pay_bankcode" => $pay_bankcode,
    "pay_notifyurl" => $pay_notifyurl,
    "pay_callbackurl" => $pay_callbackurl,
);
ksort($native);
$md5str = "";
foreach ($native as $key => $val) {
    $md5str = $md5str . $key . "=" . $val . "&";
}
//echo($md5str . "key=" . $Md5key);
$sign = strtoupper(md5($md5str . "key=" . $Md5key));
$native["pay_md5sign"] = $sign;
$native['pay_productname'] ='Vip基础服务';
$native['pay_ip'] =$_SERVER['REMOTE_ADDR'];
$native["pay_currency"] = $pay_currency;

if(in_array($pay_bankcode,['912','916', '917'])){
    $native['pay_extends'] = base64_encode(json_encode([
        'cnic' => '42101-1234567-8',
        'phone' => '13095342424',
    ]));
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title></title>

</head>
<body>
<div class="container">
    <div class="row" style="margin:15px;0;">
        <div class="col-md-12">
            <form class="form-inline" id="payform" method="post" action="<?php echo $tjurl; ?>">
                <?php
                foreach ($native as $key => $val) {
                    echo '<input type="hidden" name="' . $key . '" value="' . $val . '">';
                }
                ?>
                <button type="submit" style='display:none;' ></button>
            </form>
        </div>
    </div>
</div>
<script>
    document.forms['payform'].submit();
</script>
</body>
</html>
