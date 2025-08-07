<?php
header('Content-type:text/html;charset=utf-8');
   $ReturnArray = array( // 返回字段
            "memberid" => $_REQUEST["memberid"], // 商户ID
            "orderid" =>  $_REQUEST["orderid"], // 订单号
            "amount" =>  $_REQUEST["amount"], // 交易金额
            "datetime" =>  $_REQUEST["datetime"], // 交易时间
            "transaction_id" =>  $_REQUEST["transaction_id"], // 流水号
            "returncode" => $_REQUEST["returncode"]
        );
      
        $Md5key = "t4ig5acnpx4fet4zapshjacjd9o4bhbi";

        ksort($ReturnArray);
        reset($ReturnArray);
        $md5str = "";
        foreach ($ReturnArray as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $Md5key)); 

        if ($sign == $_REQUEST["sign"]) {
            if ($_REQUEST["returncode"] == "00") {
                   $str = "交易成功！订单号：".$_REQUEST["orderid"];
                  
                   // exit($str);
            }else{
                exit();
            }
        }else{
             exit();
        }


?>

<!DOCTYPE html>
<html>
<head>

    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=0" name="viewport">
    <title>支付成功</title>
    <link href="./demo/css/Reset.css" rel="stylesheet" type="text/css">
    <script src="./demo/js/jquery-1.11.3.min.js"></script>
    <link href="./demo/css/main12.css" rel="stylesheet" type="text/css">
    <style>
        .pay_li input{
            display: none;
        }
        .immediate_pay{
            border:none;
        }
        .PayMethod12
        {
            min-height: 150px;
        }
        @media screen and (max-width: 700px) {
            .PayMethod12{
                padding-top:0;
            }
            .order-amount12{
                margin-bottom: 0;
            }
            .order-amount12,.PayMethod12{
                padding-left: 15px;padding-right: 15px;
            }
        }
        .order-amount12-right input{
            border:1px solid #efefef;
            width:6em;
            padding:5px 20px;
            font-size: 15px;
            text-indent: 0.5em;
            line-height: 1.8em;
        }



    </style>


    <script>
        if(/Android|webOS|iPhone|iPod|BlackBerry/i.test(navigator.userAgent)) {
            //window.location.href = "mobile.php";
        } else {

        }
    </script>
</head>
<body style="background-color:#f9f9f9">
<form action="" method="post" autocomplete="off">
<!--弹窗开始-->

<!--弹窗结束-->
<!--导航-->
<div class="w100 navBD12">
    <div class="w1080 nav12">
        <div class="nav12-left">
            <a href="/"><img src="/image/logo2.png" alt="好啊支付"title="好啊支付" style="max-height: 38px;"></a>
            <span class="shouyintai"></span>
        </div>
        <div class="nav12-right">
        </div>
    </div>
</div>
<!--订单金额-->

<!--支付方式-->

    <input type="hidden" name="orderid" value="<?php echo $pay_orderid;?>">
   
<div class="w1080 PayMethod12" style="border-radius: 1em;">


    <div class="row" style="text-align: center;">
       <img src="/image/zfb_logo.png">
        <ul>
                <span style="font-size:30px">支付成功</span>
        </ul>
    </div>


  
</div>

<!--立即支付-->

</form>
<div class="w100 navBD12">
    
      <button style="width: 100%;height: 40px;background-color:#00aaee;color: #fff"  onclick="too()">返回</button>
   
</div>

<script type="text/javascript">
    function too() {
        window.location.href='/';

    }
</script>
</body>
</html>




