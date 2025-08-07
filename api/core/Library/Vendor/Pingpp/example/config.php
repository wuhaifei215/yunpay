<?php

// api_key 获取方式：登录 [Dashboard](https://dashboard.pingxx.com)->点击管理平台右上角公司名称->开发信息-> Secret Key
const APP_KEY = 'sk_test_e58SS8e5OyX14yzPGSSerDO4';
// app_id 获取方式：登录 [Dashboard](https://dashboard.pingxx.com)->点击你创建的应用->应用首页->应用 ID(App ID)
const APP_ID = 'app_1arT400mH4eLezLS';

/**
 * 设置请求签名密钥，密钥对需要你自己用 openssl 工具生成，如何生成可以参考帮助中心：https://help.pingxx.com/article/123161；
 * 生成密钥后，需要在代码中设置请求签名的私钥(rsa_private_key.pem)；
 * 然后登录 [Dashboard](https://dashboard.pingxx.com)->点击右上角公司名称->开发信息->商户公钥（用于商户身份验证）
 * 将你的公钥复制粘贴进去并且保存->先启用 Test 模式进行测试->测试通过后启用 Live 模式
 */
\Pingpp\Pingpp::setApiKey(APP_KEY);                                         // 设置 API Key
\Pingpp\Pingpp::setPrivateKeyPath(__DIR__ . '/your_rsa_private_key.pem');   // 设置私钥
\Pingpp\Pingpp::setAppId(APP_ID);                                           // 设置 APP ID

// 设置私钥内容方式2
// \Pingpp\Pingpp::setPrivateKey(file_get_contents(__DIR__ . '/your_rsa_private_key.pem'));