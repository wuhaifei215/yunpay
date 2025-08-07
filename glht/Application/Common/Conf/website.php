<?php 
	return array(
		'WEB_TITLE' => 'YUN-PH支付',
		'DOMAIN' => 'pglht.yunpay.me',
		'MODULE_ALLOW_LIST'   => array('Home','User','sysadmin','Install', 'Weixin','Pay','Cashier','Agent','Payment','Cli'),
		'URL_MODULE_MAP'  => array('sysadmin'=>'admin', 'agent'=>'user', 'user'=>'user'),
		'LOGINNAME' => 'user',
		'HOUTAINAME' => 'sysadmin',
		'API_DOMAIN' => 'papi.yunpay.me',
        'NOTIFY_DOMAIN' => 'pnapi.yunpay.me',
        'LOG_API_URL' => 'http://plog.yunpay.me',
    );
?>