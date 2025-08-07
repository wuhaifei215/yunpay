<?php
error_reporting(0);
header("Content-type: text/html; charset=utf-8");	
$mchid = "10002";  
$Md5key = "sy016baa755zjmp6dnxl0ezr2asxio8z";
$out_trade_no = date("YmdHis").mt_rand(1000,9999);    //订单号
$_POST['out_trade_no'] = $out_trade_no;
$money =  $_POST["money"];    //交易金额
$_POST['mchid'] = $mchid;
if(empty($mchid)||empty($_POST['money']) || empty($_POST['accountname']) || empty($_POST['cardnumber']) ){
		die("信息不完整！");
}
if(in_array($_POST['bankcode'],['913','914','915'])){
    $tjurl = "https://papi.yunpay.me/Payment_CreateDF_payoutIND.html";   //提交地址
}elseif(in_array($_POST['bankcode'],['912'])){
    if($_POST['extends']) {
    	$_POST['extends'] = base64_encode($_POST['extends']);
    }
    $tjurl = "https://papi.yunpay.me/Payment_CreateDF_payoutPAK.html";   //提交地址
}elseif(in_array($_POST['bankcode'],['918'])){
    $tjurl = "https://papi.yunpay.me/Payment_CreateDF_payoutVN.html";   //提交地址
}elseif(in_array($_POST['bankcode'],['924', '925', '926'])){
    $_POST['currency'] = 'BDT';
    $tjurl = "https://papi.yunpay.me/Payment_CreateDF_payoutBD.html";   //提交地址
}else{
    $tjurl = "https://papi.yunpay.me/Payment_CreateDF_payoutPHP.html";   //提交地址
}

ksort($_POST);
//var_dump($_POST);die;
$md5str = "";
foreach ($_POST as $key => $val) {
    $md5str = $md5str . $key . "=" . $val . "&";
	
}
$sign = strtoupper(md5($md5str . "key=" . $Md5key));
$param = $_POST;
$param["pay_md5sign"] = $sign;
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
                foreach ($param as $key => $val) {
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
