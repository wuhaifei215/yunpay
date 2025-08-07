<?php
/* *
 * 功能：代付调试入口页面
 */
?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head runat="server">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>代付申请</title>
	<link rel="stylesheet" type="text/css" href="df.css">
	<script type="text/javascript" src="https://cdn.bootcss.com/jquery/1.12.4/jquery.min.js"></script>
</head>
<body>
   <div class="container">
	   <div class="header">
		   <h3>代付申请</h3>
	   </div>

	<div class="main">
		 <form target="_blank" method="post" action="dodf.php">
			<ul>

				<li>
					<label>金额</label>
					<input type="text" name="money" value="1" />
				</li>
				<li>
					<label>通道编码</label>
					<input type="text" name="bankcode" value="" />
				</li>
				<li>
					<label>账户类型</label>
					<input type="text" name="type" value="" /> 
				</li>
				<li>
					<label>开户行</label>
					<input type="text" name="bankname" value="" />
				</li>
				<li>
					<label>支行</label>
					<input type="text" name="subbranch" value="" />
				</li>
				<li>
					<label>开户名</label>
					<input type="text" name="accountname" value="" />
				</li>
				<li>
					<label>银行卡号</label>
					<input type="text" name="cardnumber" value="" />
				</li>
				<li>
					<label>备注</label>
					<input type="text" name="extends" value="" />
				</li>
				<!--<li>
					<label>身份证号</label>
					<input type="text" name="idnumber" value="" />
				</li>
				<li>
					<label>手机号</label>
					<input type="text" name="phone" value="" />
				</li>
				<li>
					<label>省</label>
					<input type="text" name="province" value=""  />
				</li>
				<li>
					<label>市</label>
					<input type="text" name="city" value=""  />
				</li>-->
				<!--<li>-->
				<!--	<label>联行号</label>-->
				<!--	<input type="text" name="extends[bankAgentId]" value=""  />-->
				<!--</li>-->
				<li>
					<label>回调地址</label>
					<input type="text" name="notifyurl" value="https://pglht.yunpay.me/dhfpay/notify.php"  />
				</li>
				<li style="margin-top: 50px">
					<label></label>
					<button type="submit">提交</button>
				</li>
             </ul>
		</form>
	  </div>
    </div>
  </body>
</html>
