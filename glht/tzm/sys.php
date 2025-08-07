<?php
header('Content-type:text/html;charset=utf-8');
// date_default_timezone_set('America/Sao_Paulo');
$codecode1 = 'yunYUNPHP.!'.substr(date("YmdHi"), 0, 11) . 'zhxcfgg';
$codecode = substr(md5($codecode1), -6);
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=0" name="viewport">
    <title>动态码</title>
</head>
<body style="background-color:#f9f9f9">
<form action="" method="post" autocomplete="off">
    <div class="row" style="text-align: center; margin-top: 30px;">
        <span style="font-size:30px"><?php  echo $codecode; ?></span>
    </div>
</form>
</body>
</html>