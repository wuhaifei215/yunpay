<?php
$pay_orderid = 'E' . date("YmdHis") . rand(10000, 99999);    //订单号
$pay_amount = "0.01";    //交易金额
$product_name = "Vip基础服务";
?>

<!DOCTYPE html>
<html>
<head>

    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=0" name="viewport">
    <title>收银台</title>
    <link href="./demo/css/Reset.css" rel="stylesheet" type="text/css">
    <script src="./demo/js/jquery-1.11.3.min.js"></script>
    <link href="./demo/css/main12.css" rel="stylesheet" type="text/css">
    <style>
        .pay_li input {
            display: none;
        }

        .immediate_pay {
            border: none;
        }

        .PayMethod12 {
            min-height: 150px;
        }

        @media screen and (max-width: 700px) {
            .PayMethod12 {
                padding-top: 0;
            }

            .order-amount12 {
                margin-bottom: 0;
            }

            .order-amount12, .PayMethod12 {
                padding-left: 15px;
                padding-right: 15px;
            }
        }

        .order-amount12-right input {
            border: 1px solid #efefef;
            width: 6em;
            padding: 5px 20px;
            font-size: 15px;
            text-indent: 0.5em;
            line-height: 1.8em;
        }
        .PayMethod12 ul li {
            margin: 20px;
        }

    </style>
    <script>
        var lastClickTime;
        var orderNo = "15248148988132090444";
        $(function () {
            // $('.PayMethod12 ul li').each(function (index, element) {
            //     $('.PayMethod12 ul li').eq(5 * index + 4).css('margin-right', '0')
            // });

            //支付方式选择
            $('.PayMethod12 ul li').click(function (e) {
                $(this).addClass('active').siblings().removeClass('active');
            });

            $(".pay_li").click(function () {
                $(".pay_li").removeClass("active");
                $(this).addClass("active");
            });
            //点击立即支付按钮
            $(".immediate_pay").click(function () {
                //判断用户是否选择了支付渠道
                if (!$(".pay_li").hasClass("active")) {
                    message_show("请选择支付功能");
                    return false;
                }
                //获取选择的支付渠道的li
                var payli = $(".pay_li[class='pay_li active']");
                if (payli[0]) {
                    prepay(payli.attr("data_power_id"), payli.attr("data_product_id"));
                } else {
                    message_show("请重新选择支付功能");
                }

            });


            $('.mt_agree').click(function (e) {
                $('.mt_agree').fadeOut(300);
            });

            $('.mt_agree_main').click(function (e) {
                return false;
            });

            //弹窗
            // 		$('.pay_sure12').click(function(e) {
            // 			$(this).fadeOut();
            // 		});

            $('.pay_sure12-main').click(function (e) {
                //e. stopPropagation();
                return false;
            });
        });

    </script>

    <script>
        if (/Android|webOS|iPhone|iPod|BlackBerry/i.test(navigator.userAgent)) {
            //window.location.href = "mobile.php";
        } else {

        }
    </script>
</head>
<body style="background-color:#f9f9f9">
<form action="index1.php" method="post" autocomplete="off">
    <!--弹窗开始-->
    <div class="pay_sure12">
        <div class="pay_sure12-main">
            <h2>支付确认</h2>
            <h3 class="h3-01">请在新打开的页面进行支付！<br><strong>支付完成前请不要关闭此窗口。</strong></h3>
            <div class="pay_sure12-btngroup">
                <a class="immediate_button immediate_payComplate" onclick="callback_pc();">已完成支付</a>
                <a class="immediate_button immediate_payChange" onclick="hide();">更换支付方式</a>
            </div>
            <p>支付遇到问题？请联系 <span class="f12 blue">支付</span> 客服获得帮助。</p>
        </div>
    </div>
    <!--弹窗结束-->
    <!--导航-->
    <div class="w100 navBD12">
        <div class="w1080 nav12">
            <div class="nav12-left">
                <span class="shouyintai"></span>
            </div>
            <div class="nav12-right">
            </div>
        </div>
    </div>
    <!--订单金额-->
    <div class="w1080 order-amount12" style="border-radius: 1em;">
        <ul class="order-amount12-left">
            <li>
                <span>商品名称：</span>
                <span><?php echo $product_name; ?></span>
            </li>
            <li>
                <span>订单编号：</span>
                <span><?php echo $pay_orderid; ?></span>
            </li>
        </ul>
        <div class="order-amount12-right">
            <span>订单金额：</span>
            <strong><input type="text" name="amount" value="100"></strong>
            <span>元</span>
        </div>
    </div>
    <!--支付方式-->

    <input type="hidden" name="orderid" value="<?php echo $pay_orderid; ?>">

    <div class="w1080 PayMethod12" style="border-radius: 1em;">
        <div class="row">
            <h2>支付方式:</h2>
            <ul style="border-bottom: 1px dashed #000; ">
                <label for="gcashys">
                    <li class="pay_li active" data_power_id="3000000001" data_product_id="3000000005">
                        <input value="910" checked="checked" name="channel" id="gcashys" type="radio">
                        <!--<i class="i1"></i>-->
                        <span>GCash原生</span>
                    </li>
                </label>
                <label for="gcashzl">
                    <li class="pay_li" data_power_id="3000000001" data_product_id="3000000001">
                        <input value="906" name="channel" id="gcashzl" type="radio">
                        <!--<i class="i1"></i>-->
                        <span>GCash伪原生</span>
                    </li>
                </label>
                <label for="gcashsm">
                    <li class="pay_li" data_power_id="3000000002" data_product_id="3000000002">
                        <input value="907" name="channel" id="gcashsm" type="radio">
                        <!--<i class="i1"></i>-->
                        <span>GCash扫码</span>
                    </li>
                </label>
                <label for="maya">
                    <li class="pay_li" data_power_id="3000000003" data_product_id="3000000003">
                        <input value="908" name="channel" id="maya" type="radio">
                        <!--<i class="i1"></i>-->
                        <span>Maya</span>
                    </li>
                </label>
            </ul>
            <ul style="border-bottom: 1px dashed #000; ">
                <label for="MXN">
                    <li class="pay_li" data_power_id="3000000005" data_product_id="3000000005">
                        <input value="911" name="channel" id="MXN" type="radio">
                        <!--<i class="i1"></i>-->
                        <span>MXN</span>
                    </li>
                </label>
                
            </ul>
            <ul style="border-bottom: 1px dashed #000; ">
                <label for="UPI">
                    <li class="pay_li" data_power_id="3000000007" data_product_id="3000000007">
                        <input value="913" name="channel" id="UPI" type="radio">
                        <!--<i class="i1"></i>-->
                        <span>印度UPI</span>
                    </li>
                </label>
                <label for="InrWap">
                    <li class="pay_li" data_power_id="3000000008" data_product_id="3000000008">
                        <input value="914" name="channel" id="InrWap" type="radio">
                        <!--<i class="i1"></i>-->
                        <span>印度原生</span>
                    </li>
                </label>
                <label for="InrWake">
                    <li class="pay_li" data_power_id="3000000009" data_product_id="3000000009">
                        <input value="915" name="channel" id="InrWake" type="radio">
                        <!--<i class="i1"></i>-->
                        <span>印度唤醒</span>
                    </li>
                </label>
                
            </ul>
            <ul style="border-bottom: 1px dashed #000; ">
                <label for="PAK">
                    <li class="pay_li" data_power_id="3000000006" data_product_id="3000000006">
                        <input value="912" name="channel" id="PAK" type="radio">
                        <!--<i class="i1"></i>-->
                        <span>PAK-收银台</span>
                    </li>
                </label>
                <label for="PAKj">
                    <li class="pay_li" data_power_id="30000000010" data_product_id="3000000010">
                        <input value="916" name="channel" id="PAKj" type="radio">
                        <!--<i class="i1"></i>-->
                        <span>PAK-jazzcash</span>
                    </li>
                </label>
                <label for="PAKe">
                    <li class="pay_li" data_power_id="3000000011" data_product_id="3000000011">
                        <input value="917" name="channel" id="PAKe" type="radio">
                        <!--<i class="i1"></i>-->
                        <span>PAK-easypaisa</span>
                    </li>
                </label>
                
            </ul>
            <ul>
                <label for="VNBANKWAP">
                    <li class="pay_li" data_power_id="3000000012" data_product_id="3000000012">
                        <input value="918" name="channel" id="VNBANKWAP" type="radio">
                        <!--<i class="i1"></i>-->
                        <span>VN-银行直连</span>
                    </li>
                </label>
                <label for="VNBANKSCAN">
                    <li class="pay_li" data_power_id="3000000013" data_product_id="3000000013">
                        <input value="919" name="channel" id="VNBANKSCAN" type="radio">
                        <!--<i class="i1"></i>-->
                        <span>VN-银行扫码</span>
                    </li>
                </label>
                <label for="VNBANKTOCARD">
                    <li class="pay_li" data_power_id="3000000014" data_product_id="3000000014">
                        <input value="920" name="channel" id="VNBANKTOCARD" type="radio">
                        <!--<i class="i1"></i>-->
                        <span>VN-银行卡转卡</span>
                    </li>
                </label>
                <label for="VNMOMO">
                    <li class="pay_li" data_power_id="3000000015" data_product_id="3000000015">
                        <input value="921" name="channel" id="VNMOMO" type="radio">
                        <!--<i class="i1"></i>-->
                        <span>VN-Momo</span>
                    </li>
                </label>
            </ul>
            <ul style="border-bottom: 1px dashed #000; ">
                <label for="VNZALO">
                    <li class="pay_li" data_power_id="3000000016" data_product_id="3000000016">
                        <input value="922" name="channel" id="VNZALO" type="radio">
                        <!--<i class="i1"></i>-->
                        <span>VN-Momo</span>
                    </li>
                </label>
                <label for="VNVTPAY">
                    <li class="pay_li" data_power_id="3000000017" data_product_id="3000000017">
                        <input value="923" name="channel" id="VNVTPAY" type="radio">
                        <!--<i class="i1"></i>-->
                        <span>VN-Momo</span>
                    </li>
                </label>
            </ul>
            <ul style="border-bottom: 1px dashed #000; ">
                <label for="BDbKash">
                    <li class="pay_li" data_power_id="3000000018" data_product_id="3000000018">
                        <input value="924" name="channel" id="BDbKash" type="radio">
                        <!--<i class="i1"></i>-->
                        <span>BD-bKash</span>
                    </li>
                </label>
                <label for="BDNagad">
                    <li class="pay_li" data_power_id="3000000019" data_product_id="3000000019">
                        <input value="925" name="channel" id="BDNagad" type="radio">
                        <!--<i class="i1"></i>-->
                        <span>BD-Nagad</span>
                    </li>
                </label>
                <label for="BDRocket">
                    <li class="pay_li" data_power_id="3000000020" data_product_id="3000000020">
                        <input value="926" name="channel" id="BDRocket" type="radio">
                        <!--<i class="i1"></i>-->
                        <span>BD-Rocket</span>
                    </li>
                </label>
            </ul>
        </div>
    </div>
    <!--立即支付-->
    <div class="w1080 immediate-pay12"
         style="border-radius: 1em; padding-top:1em; padding-bottom: 1em;padding-right: 1em;">
        <div class="immediate-pay12-right">
            <!--        <span>需支付：<strong>0.01</strong>元</span>-->

            <button type="submit" class="immediate_pay">立即支付</button>
        </div>
    </div>
    <div class="mt_agree">
        <div class="mt_agree_main">
            <h2>提示信息</h2>
            <p id="errorContent" style="text-align:center;line-height:36px;"></p>
            <a class="close_btn" onclick="message_hide()">确定</a>
        </div>
    </div>
    <!--底部-->
    <div class="w1080 footer12">
        <p>All Rights Reserved. 2017-2018</p>


    </div>


    <script type="text/javascript">
        function message_show(message) {
            $("#errorContent").html(message);
            $('.mt_agree').fadeIn(300);
        }

        function message_hide() {
            $('.mt_agree').fadeOut(300);
        }

    </script>
</form>

</body>
</html>