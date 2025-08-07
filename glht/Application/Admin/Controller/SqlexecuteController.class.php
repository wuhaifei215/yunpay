<?php
namespace Admin\Controller;

class SqlexecuteController extends BaseController
{

    public function index()
    {
        if ($this->payapi() > 0) {
            echo ($this->TransCode("通道数据添加成功！<br><br>"));
            if ($this->payapiconfig() > 0) {
                echo ($this->TransCode("通道设置数据添加成功！<br><br>"));
                if ($this->systembank() > 0) {
                    echo ($this->TransCode("系统银行添加成功！<br><br>"));
                    if ($this->payapibank() > 0) {
                        echo ($this->TransCode("通道银行添加成功！<br><br>"));
                        if ($this->payapicompatibility() > 0) {
                            echo ($this->TransCode("通道兼容字段添加成功！<br><br>"));
                        } else {
                            exit($this->TransCode("通道兼容字段添加失败！<br><br>"));
                        }
                    } else {
                        exit($this->TransCode("通道银行添加失败！<br><br>"));
                    }
                } else {
                    exit($this->TransCode("系统银行添加失败！<br><br>"));
                }
            } else {
                exit($this->TransCode("通道设置数据添加失败！<br><br>"));
            }
        } else {
            exit($this->TransCode("通道数据添加失败！<br><br>"));
        }
    }

    private function payapi()
    {
        $sqlarray = array(
            array(
                'en_payname' => 'Baofoo',
                'zh_payname' => '宝付',
                'url' => 'http://www.baofoo.com/'
            ),
            array(
                'en_payname' => 'Yeepay',
                'zh_payname' => '易宝',
                'url' => 'http://www.yeepay.com/'
            ),
            array(
                'en_payname' => 'Reapay',
                'zh_payname' => '融宝',
                'url' => 'http://www.reapal.com/'
            ),
            array(
                'en_payname' => 'Dinpay',
                'zh_payname' => '智付',
                'url' => 'http://www.dinpay.com/'
            ),
            array(
                'en_payname' => 'Ips',
                'zh_payname' => '环讯IPS',
                'url' => 'http://www.ips.com/'
            ),
            array(
                'en_payname' => 'Unionpay',
                'zh_payname' => '银联在线',
                'url' => 'http://cn.unionpay.com/'
            ),
            array(
                'en_payname' => 'Yidong',
                'zh_payname' => '中国移动支付',
                'url' => 'https://cmpay.10086.cn/'
            ),
            array(
                'en_payname' => 'Liantong',
                'zh_payname' => '联通沃支付',
                'url' => 'https://epay.10010.com/'
            )
        )
        ;
        
        $sqlstr = "";
        
        $Model = M();
        
        foreach ($sqlarray as $key) {
            $sqlstr = "insert into " . C('DB_PREFIX') . "payapi(en_payname,zh_payname,url) values('" . $key["en_payname"] . "','" . $key["zh_payname"] . "','" . $key["url"] . "');";
            $returnnumber = $Model->execute($sqlstr);
            if ($returnnumber > 0) {
                echo ($this->TransCode("新增通道【" . $key["zh_payname"] . "】<br>"));
            }
        }
        
        // exit($sqlstr);
        
        return $returnnumber;
    }

    private function payapiconfig()
    {
        $Payapi = M("Payapi");
        
        $list = $Payapi->select();
        
        $sqlstr = "";
        
        $Model = M();
        
        foreach ($list as $key) {
            $sqlstr = "insert into " . C('DB_PREFIX') . "payapiconfig(payapiid,websiteid) values(" . $key["id"] . ",0);";
            $returnnumber = $Model->execute($sqlstr);
            if ($returnnumber > 0) {
                echo ($this->TransCode("新增通道【" . $key["zh_payname"] . "】配置数据<br>"));
            }
        }
        
        return $returnnumber;
    }

    private function systembank()
    {
        $sqlarray = array(
            array(
                'bankcode' => 'BOB',
                'bankname' => '北京银行'
            ),
            array(
                'bankcode' => 'CBB',
                'bankname' => '渤海银行'
            ),
            array(
                'bankcode' => 'BEA',
                'bankname' => '东亚银行'
            ),
            array(
                'bankcode' => 'ICBC',
                'bankname' => '中国工商银行'
            ),
            array(
                'bankcode' => 'CEB',
                'bankname' => '中国光大银行'
            ),
            array(
                'bankcode' => 'GDB',
                'bankname' => '广发银行'
            ),
            array(
                'bankcode' => 'HXB',
                'bankname' => '华夏银行'
            ),
            array(
                'bankcode' => 'CCB',
                'bankname' => '中国建设银行'
            ),
            array(
                'bankcode' => 'BCM',
                'bankname' => '交通银行'
            ),
            array(
                'bankcode' => 'CMSB',
                'bankname' => '中国民生银行'
            ),
            array(
                'bankcode' => 'NJCB',
                'bankname' => '南京银行'
            ),
            array(
                'bankcode' => 'NBCB',
                'bankname' => '宁波银行'
            ),
            array(
                'bankcode' => 'ABC',
                'bankname' => '中国农业银行'
            ),
            array(
                'bankcode' => 'PAB',
                'bankname' => '平安银行'
            ),
            array(
                'bankcode' => 'BOS',
                'bankname' => '上海银行'
            ),
            array(
                'bankcode' => 'SPDB',
                'bankname' => '上海浦东发展银行'
            ),
            array(
                'bankcode' => 'SDB',
                'bankname' => '深圳发展银行'
            ),
            array(
                'bankcode' => 'CIB',
                'bankname' => '兴业银行'
            ),
            array(
                'bankcode' => 'PSBC',
                'bankname' => '中国邮政储蓄银行'
            ),
            array(
                'bankcode' => 'CMBC',
                'bankname' => '招商银行'
            ),
            array(
                'bankcode' => 'CZB',
                'bankname' => '浙商银行'
            ),
            array(
                'bankcode' => 'BOC',
                'bankname' => '中国银行'
            ),
            array(
                'bankcode' => 'CNCB',
                'bankname' => '中信银行'
            )
        )
        ;
        
        $sqlstr = "";
        
        $Model = M();
        
        foreach ($sqlarray as $key) {
            $sqlstr = "insert into " . C('DB_PREFIX') . "systembank(bankcode,bankname) values('" . $key["bankcode"] . "','" . $key["bankname"] . "');";
            $returnnumber = $Model->execute($sqlstr);
            if ($returnnumber > 0) {
                echo ($this->TransCode("新增系统银行【" . $key["bankname"] . "】<br>"));
            }
        }
        return $returnnumber;
    }

    private function payapibank()
    {
        $Payapiconfig = M("Payapiconfig");
        
        $Payapi = M("Payapi");
        
        $Payapiconfiglist = $Payapiconfig->where("websiteid = 0")->select();
        
        $Systembank = M("Systembank");
        
        $Systembanklist = $Systembank->select();
        
        $sqlstr = "";
        
        $Model = M();
        
        foreach ($Payapiconfiglist as $Payapiconfigkey) {
            $zh_payname = $Payapi->where("id=" . $Payapiconfigkey["payapiid"])->getField("zh_payname");
            foreach ($Systembanklist as $Systembankkey) {
                $sqlstr = "insert into " . C('DB_PREFIX') . "payapibank(payapiconfigid,systembankid) values(" . $Payapiconfigkey["id"] . "," . $Systembankkey["id"] . ");";
                $returnnumber = $Model->execute($sqlstr);
                if ($returnnumber > 0) {
                    echo ($this->TransCode("新增通道【" . $zh_payname . "】系统银行【" . $Systembankkey["bankname"] . "】<br>"));
                }
            }
            echo ("<br>");
        }
        return $returnnumber;
    }

    private function payapicompatibility()
    {
        $array = array(
            
            'Baofoo' => array(
                'MerchantID',
                'PayID',
                'TradeDate',
                'OrderMoney',
                'ProductName',
                'Amount',
                'ProductLogo',
                'Username',
                'Email',
                'Mobile',
                'AdditionalInfo',
                'Merchant_url',
                'Return_url',
                'Md5Sign',
                'NoticeType'
            ),
            'Yeepay' => array(
                'p0_Cmd',
                'p1_MerId',
                'p2_Order',
                'p3_Amt',
                'p4_Cur',
                'p5_Pid',
                'p6_Pcat',
                'p7_Pdesc',
                'p8_Url',
                'p9_SAF',
                'pa_MP',
                'pd_FrpId',
                'pr_NeedResponse',
                'hmac'
            ),
            'Reapay' => array(
                'service',
                'merchant_ID',
                'notify_url',
                'return_url',
                'sign',
                'sign_type',
                'charset',
                'title',
                'body',
                'order_no',
                'total_fee',
                'payment_type',
                'paymethod',
                'pay_cus_no',
                'defaultbank',
                'seller_email',
                'buyer_email'
            ),
            'Dinpay' => array(
                'bank_code',
                'client_ip',
                'extend_param',
                'extra_return_param',
                'input_charset',
                'interface_version',
                'merchant_code',
                'notify_url',
                'order_amount',
                'order_no',
                'order_time',
                'product_code',
                'product_desc',
                'product_name',
                'product_num',
                'return_url',
                'service_type',
                'show_url',
                'sign'
            ),
            'Ips' => array(
                'Mer_code',
                'Billno',
                'Amount',
                'Date',
                'Currency_Type',
                'Gateway_Type',
                'Lang',
                'Merchanturl',
                'FailUrl',
                'Attach',
                'OrderEncodeType',
                'RetEncodeType',
                'Rettype',
                'ServerUrl',
                'SignMD5'
            ),
            'Unionpay' => array(
                'version',
                'charset',
                'transType',
                'merAbbr',
                'merId',
                'merCode',
                'acqCode',
                'backEndUrl',
                'frontEndUrl',
                'orderTime',
                'orderNumber',
                'commodityName',
                'commodityUrl',
                'commodityUnitPrice',
                'commodityQuantity',
                'transferFee',
                'commodityDiscount',
                'orderAmount',
                'orderCurrency',
                'customerName',
                'defaultPayType'
            ),
            'Yidong' => array(
                'characterSet',
                'callbackUrl',
                'notifyUrl',
                'ipAddress',
                'merchantId',
                'requestId',
                'signType',
                'type',
                'version',
                'merchantCert',
                'hmac',
                'amount',
                'bankAbbr',
                'currency',
                'orderDate',
                'orderId',
                'merAcDate',
                'period',
                'periodUnit',
                'merchantAbbr',
                'productDesc',
                'productId',
                'productName',
                'productNum',
                'reserved1',
                'reserved2',
                'userToken',
                'showUrl',
                'couponsFlag'
            ),
            'Liantong' => array(
                'interfaceVersion',
                'tranType',
                'bankCode',
                'payProducts',
                'merNo',
                'goodsName',
                'goodsDesc',
                'orderDate',
                'orderNo',
                'amount',
                'goodId',
                'merUserId',
                'merExtend',
                'customerName',
                'mobileNo',
                'customerEmail',
                'customerID',
                'charSet',
                'tradeMode',
                'expireTime',
                'reqTime',
                'reqIp',
                'respMode',
                'callbackUrl',
                'serverCallUrl',
                'signType',
                'signMsg'
            )
        )
        ;
        
        $Payapi = M("Payapi");
        
        $sqlstr = "";
        
        $Model = M();
        
        foreach ($array as $k => $val) {
            $payapiid = $Payapi->where("en_payname = '" . $k . "'")->getField("id");
            $zh_payname = $Payapi->where("en_payname = '" . $k . "'")->getField("zh_payname");
            foreach ($val as $key) {
                $sqlstr = "insert into " . C('DB_PREFIX') . "payapicompatibility(payapiid,field) values(" . $payapiid . ",'" . $key . "');";
                $returnnumber = $Model->execute($sqlstr);
                if ($returnnumber > 0) {
                    echo ($this->TransCode("新增通道【" . $zh_payname . "】兼容提交字段<br>"));
                }
            }
            echo ("<br>");
        }
        
        return $returnnumber;
    }
    
    
    public function VND(){
        $bank = '[
        {
            "id": 20001,
            "currency": "VND",
            "code": "ICB",
            "name": "VietinBank",
            "logo": "https://api.vietqr.io/img/ICB.png",
            "desc": "Ngân hàng TMCP Công thương Việt Nam",
            "bin": "970415",
            "status": "disable"
        },
        {
            "id": 20002,
            "currency": "VND",
            "code": "VCB",
            "name": "Vietcombank",
            "logo": "https://api.vietqr.io/img/VCB.png",
            "desc": "Ngân hàng TMCP Ngoại Thương Việt Nam",
            "bin": "970436",
            "status": "disable"
        },
        {
            "id": 20003,
            "currency": "VND",
            "code": "BIDV",
            "name": "BIDV",
            "logo": "https://api.vietqr.io/img/BIDV.png",
            "desc": "Ngân hàng TMCP Đầu tư và Phát triển Việt Nam",
            "bin": "970418",
            "status": "disable"
        },
        {
            "id": 20004,
            "currency": "VND",
            "code": "VBA",
            "name": "Agribank",
            "logo": "https://api.vietqr.io/img/VBA.png",
            "desc": "Ngân hàng Nông nghiệp và Phát triển Nông thôn Việt Nam",
            "bin": "970405",
            "status": "disable"
        },
        {
            "id": 20005,
            "currency": "VND",
            "code": "OCB",
            "name": "OCB",
            "logo": "https://api.vietqr.io/img/OCB.png",
            "desc": "Ngân hàng TMCP Phương Đông",
            "bin": "970448",
            "status": "disable"
        },
        {
            "id": 20006,
            "currency": "VND",
            "code": "MB",
            "name": "MBBank",
            "logo": "https://api.vietqr.io/img/MB.png",
            "desc": "Ngân hàng TMCP Quân đội",
            "bin": "970422",
            "status": "disable"
        },
        {
            "id": 20007,
            "currency": "VND",
            "code": "TCB",
            "name": "Techcombank",
            "logo": "https://api.vietqr.io/img/TCB.png",
            "desc": "Ngân hàng TMCP Kỹ thương Việt Nam",
            "bin": "970407",
            "status": "disable"
        },
        {
            "id": 20008,
            "currency": "VND",
            "code": "ACB",
            "name": "ACB",
            "logo": "https://api.vietqr.io/img/ACB.png",
            "desc": "Ngân hàng TMCP Á Châu",
            "bin": "970416",
            "status": "disable"
        },
        {
            "id": 20009,
            "currency": "VND",
            "code": "VPB",
            "name": "VPBank",
            "logo": "https://api.vietqr.io/img/VPB.png",
            "desc": "Ngân hàng TMCP Việt Nam Thịnh Vượng",
            "bin": "970432",
            "status": "disable"
        },
        {
            "id": 20010,
            "currency": "VND",
            "code": "TPB",
            "name": "TPBank",
            "logo": "https://api.vietqr.io/img/TPB.png",
            "desc": "Ngân hàng TMCP Tiên Phong",
            "bin": "970423",
            "status": "disable"
        },
        {
            "id": 20011,
            "currency": "VND",
            "code": "STB",
            "name": "Sacombank",
            "logo": "https://api.vietqr.io/img/STB.png",
            "desc": "Ngân hàng TMCP Sài Gòn Thương Tín",
            "bin": "970403",
            "status": "disable"
        },
        {
            "id": 20012,
            "currency": "VND",
            "code": "HDB",
            "name": "HDBank",
            "logo": "https://api.vietqr.io/img/HDB.png",
            "desc": "Ngân hàng TMCP Phát triển Thành phố Hồ Chí Minh",
            "bin": "970437",
            "status": "disable"
        },
        {
            "id": 20013,
            "currency": "VND",
            "code": "VCCB",
            "name": "VietCapitalBank",
            "logo": "https://api.vietqr.io/img/VCCB.png",
            "desc": "Ngân hàng TMCP Bản Việt",
            "bin": "970454",
            "status": "disable"
        },
        {
            "id": 20014,
            "currency": "VND",
            "code": "SCB",
            "name": "SCB",
            "logo": "https://api.vietqr.io/img/SCB.png",
            "desc": "Ngân hàng TMCP Sài Gòn",
            "bin": "970429",
            "status": "disable"
        },
        {
            "id": 20015,
            "currency": "VND",
            "code": "VIB",
            "name": "VIB",
            "logo": "https://api.vietqr.io/img/VIB.png",
            "desc": "Ngân hàng TMCP Quốc tế Việt Nam",
            "bin": "970441",
            "status": "disable"
        },
        {
            "id": 20016,
            "currency": "VND",
            "code": "SHB",
            "name": "SHB",
            "logo": "https://api.vietqr.io/img/SHB.png",
            "desc": "Ngân hàng TMCP Sài Gòn - Hà Nội",
            "bin": "970443",
            "status": "disable"
        },
        {
            "id": 20017,
            "currency": "VND",
            "code": "EIB",
            "name": "Eximbank",
            "logo": "https://api.vietqr.io/img/EIB.png",
            "desc": "Ngân hàng TMCP Xuất Nhập khẩu Việt Nam",
            "bin": "970431",
            "status": "disable"
        },
        {
            "id": 20018,
            "currency": "VND",
            "code": "MSB",
            "name": "MSB",
            "logo": "https://api.vietqr.io/img/MSB.png",
            "desc": "Ngân hàng TMCP Hàng Hải",
            "bin": "970426",
            "status": "disable"
        },
        {
            "id": 20019,
            "currency": "VND",
            "code": "CAKE",
            "name": "CAKE",
            "logo": "https://api.vietqr.io/img/CAKE.png",
            "desc": "TMCP Việt Nam Thịnh Vượng - Ngân hàng số CAKE by VPBank",
            "bin": "546034",
            "status": "disable"
        },
        {
            "id": 20020,
            "currency": "VND",
            "code": "Ubank",
            "name": "Ubank",
            "logo": "https://api.vietqr.io/img/UBANK.png",
            "desc": "TMCP Việt Nam Thịnh Vượng - Ngân hàng số Ubank by VPBank",
            "bin": "546035",
            "status": "disable"
        },
        {
            "id": 20021,
            "currency": "VND",
            "code": "TIMO",
            "name": "Timo",
            "logo": "https://vietqr.net/portal-service/resources/icons/TIMO.png",
            "desc": "Ngân hàng số Timo by Ban Viet Bank (Timo by Ban Viet Bank)",
            "bin": "963388",
            "status": "disable"
        },
        {
            "id": 20022,
            "currency": "VND",
            "code": "VTLMONEY",
            "name": "ViettelMoney",
            "logo": "https://api.vietqr.io/img/VIETTELMONEY.png",
            "desc": "Tổng Công ty Dịch vụ số Viettel - Chi nhánh tập đoàn công nghiệp viễn thông Quân Đội",
            "bin": "971005",
            "status": "disable"
        },
        {
            "id": 20023,
            "currency": "VND",
            "code": "VNPTMONEY",
            "name": "VNPTMoney",
            "logo": "https://api.vietqr.io/img/VNPTMONEY.png",
            "desc": "VNPT Money",
            "bin": "971011",
            "status": "disable"
        },
        {
            "id": 20024,
            "currency": "VND",
            "code": "SGICB",
            "name": "SaigonBank",
            "logo": "https://api.vietqr.io/img/SGICB.png",
            "desc": "Ngân hàng TMCP Sài Gòn Công Thương",
            "bin": "970400",
            "status": "disable"
        },
        {
            "id": 20025,
            "currency": "VND",
            "code": "BAB",
            "name": "BacABank",
            "logo": "https://api.vietqr.io/img/BAB.png",
            "desc": "Ngân hàng TMCP Bắc Á",
            "bin": "970409",
            "status": "disable"
        },
        {
            "id": 20026,
            "currency": "VND",
            "code": "PVCB",
            "name": "PVcomBank",
            "logo": "https://api.vietqr.io/img/PVCB.png",
            "desc": "Ngân hàng TMCP Đại Chúng Việt Nam",
            "bin": "970412",
            "status": "disable"
        },
        {
            "id": 20027,
            "currency": "VND",
            "code": "Oceanbank",
            "name": "Oceanbank",
            "logo": "https://api.vietqr.io/img/OCEANBANK.png",
            "desc": "Ngân hàng Thương mại TNHH MTV Đại Dương",
            "bin": "970414",
            "status": "disable"
        },
        {
            "id": 20028,
            "currency": "VND",
            "code": "NCB",
            "name": "NCB",
            "logo": "https://api.vietqr.io/img/NCB.png",
            "desc": "Ngân hàng TMCP Quốc Dân",
            "bin": "970419",
            "status": "disable"
        },
        {
            "id": 20029,
            "currency": "VND",
            "code": "SHBVN",
            "name": "ShinhanBank",
            "logo": "https://api.vietqr.io/img/SHBVN.png",
            "desc": "Ngân hàng TNHH MTV Shinhan Việt Nam",
            "bin": "970424",
            "status": "disable"
        },
        {
            "id": 20030,
            "currency": "VND",
            "code": "ABB",
            "name": "ABBANK",
            "logo": "https://api.vietqr.io/img/ABB.png",
            "desc": "Ngân hàng TMCP An Bình",
            "bin": "970425",
            "status": "disable"
        },
        {
            "id": 20031,
            "currency": "VND",
            "code": "VAB",
            "name": "VietABank",
            "logo": "https://api.vietqr.io/img/VAB.png",
            "desc": "Ngân hàng TMCP Việt Á",
            "bin": "970427",
            "status": "disable"
        },
        {
            "id": 20032,
            "currency": "VND",
            "code": "NAB",
            "name": "NamABank",
            "logo": "https://api.vietqr.io/img/NAB.png",
            "desc": "Ngân hàng TMCP Nam Á",
            "bin": "970428",
            "status": "disable"
        },
        {
            "id": 20033,
            "currency": "VND",
            "code": "PGB",
            "name": "PGBank",
            "logo": "https://api.vietqr.io/img/PGB.png",
            "desc": "Ngân hàng TMCP Xăng dầu Petrolimex",
            "bin": "970430",
            "status": "disable"
        },
        {
            "id": 20034,
            "currency": "VND",
            "code": "VIETBANK",
            "name": "VietBank",
            "logo": "https://api.vietqr.io/img/VIETBANK.png",
            "desc": "Ngân hàng TMCP Việt Nam Thương Tín",
            "bin": "970433",
            "status": "disable"
        },
        {
            "id": 20035,
            "currency": "VND",
            "code": "BVB",
            "name": "BaoVietBank",
            "logo": "https://api.vietqr.io/img/BVB.png",
            "desc": "Ngân hàng TMCP Bảo Việt",
            "bin": "970438",
            "status": "disable"
        },
        {
            "id": 20036,
            "currency": "VND",
            "code": "SEAB",
            "name": "SeABank",
            "logo": "https://api.vietqr.io/img/SEAB.png",
            "desc": "Ngân hàng TMCP Đông Nam Á",
            "bin": "970440",
            "status": "disable"
        },
        {
            "id": 20037,
            "currency": "VND",
            "code": "COOPBANK",
            "name": "COOPBANK",
            "logo": "https://api.vietqr.io/img/COOPBANK.png",
            "desc": "Ngân hàng Hợp tác xã Việt Nam",
            "bin": "970446",
            "status": "disable"
        },
        {
            "id": 20038,
            "currency": "VND",
            "code": "LPB",
            "name": "LienVietPostBank",
            "logo": "https://api.vietqr.io/img/LPB.png",
            "desc": "Ngân hàng TMCP Bưu Điện Liên Việt",
            "bin": "970449",
            "status": "disable"
        },
        {
            "id": 20039,
            "currency": "VND",
            "code": "KLB",
            "name": "KienLongBank",
            "logo": "https://api.vietqr.io/img/KLB.png",
            "desc": "Ngân hàng TMCP Kiên Long",
            "bin": "970452",
            "status": "disable"
        },
        {
            "id": 20040,
            "currency": "VND",
            "code": "KBank",
            "name": "KBank",
            "logo": "https://api.vietqr.io/img/KBANK.png",
            "desc": "Ngân hàng Đại chúng TNHH Kasikornbank",
            "bin": "668888",
            "status": "disable"
        },
        {
            "id": 20041,
            "currency": "VND",
            "code": "KBHN",
            "name": "KookminHN",
            "logo": "https://api.vietqr.io/img/KBHN.png",
            "desc": "Ngân hàng Kookmin - Chi nhánh Hà Nội",
            "bin": "970462",
            "status": "disable"
        },
        {
            "id": 20042,
            "currency": "VND",
            "code": "KEBHANAHCM",
            "name": "KEBHanaHCM",
            "logo": "https://api.vietqr.io/img/KEBHANAHCM.png",
            "desc": "Ngân hàng KEB Hana – Chi nhánh Thành phố Hồ Chí Minh",
            "bin": "970466",
            "status": "disable"
        },
        {
            "id": 20043,
            "currency": "VND",
            "code": "KEBHANAHN",
            "name": "KEBHANAHN",
            "logo": "https://api.vietqr.io/img/KEBHANAHN.png",
            "desc": "Ngân hàng KEB Hana – Chi nhánh Hà Nội",
            "bin": "970467",
            "status": "disable"
        },
        {
            "id": 20044,
            "currency": "VND",
            "code": "MAFC",
            "name": "MAFC",
            "logo": "https://api.vietqr.io/img/MAFC.png",
            "desc": "Công ty Tài chính TNHH MTV Mirae Asset (Việt Nam) ",
            "bin": "977777",
            "status": "disable"
        },
        {
            "id": 20045,
            "currency": "VND",
            "code": "CITIBANK",
            "name": "Citibank",
            "logo": "https://api.vietqr.io/img/CITIBANK.png",
            "desc": "Ngân hàng Citibank, N.A. - Chi nhánh Hà Nội",
            "bin": "533948",
            "status": "disable"
        },
        {
            "id": 20046,
            "currency": "VND",
            "code": "KBHCM",
            "name": "KookminHCM",
            "logo": "https://api.vietqr.io/img/KBHCM.png",
            "desc": "Ngân hàng Kookmin - Chi nhánh Thành phố Hồ Chí Minh",
            "bin": "970463",
            "status": "disable"
        },
        {
            "id": 20047,
            "currency": "VND",
            "code": "VBSP",
            "name": "VBSP",
            "logo": "https://api.vietqr.io/img/VBSP.png",
            "desc": "Ngân hàng Chính sách Xã hội",
            "bin": "999888",
            "status": "disable"
        },
        {
            "id": 20048,
            "currency": "VND",
            "code": "WVN",
            "name": "Woori",
            "logo": "https://api.vietqr.io/img/WVN.png",
            "desc": "Ngân hàng TNHH MTV Woori Việt Nam",
            "bin": "970457",
            "status": "disable"
        },
        {
            "id": 20049,
            "currency": "VND",
            "code": "VRB",
            "name": "VRB",
            "logo": "https://api.vietqr.io/img/VRB.png",
            "desc": "Ngân hàng Liên doanh Việt - Nga",
            "bin": "970421",
            "status": "disable"
        },
        {
            "id": 20050,
            "currency": "VND",
            "code": "UOB",
            "name": "UnitedOverseas",
            "logo": "https://api.vietqr.io/img/UOB.png",
            "desc": "Ngân hàng United Overseas - Chi nhánh TP. Hồ Chí Minh",
            "bin": "970458",
            "status": "disable"
        },
        {
            "id": 20051,
            "currency": "VND",
            "code": "SCVN",
            "name": "StandardChartered",
            "logo": "https://api.vietqr.io/img/SCVN.png",
            "desc": "Ngân hàng TNHH MTV Standard Chartered Bank Việt Nam",
            "bin": "970410",
            "status": "disable"
        },
        {
            "id": 20052,
            "currency": "VND",
            "code": "PBVN",
            "name": "PublicBank",
            "logo": "https://api.vietqr.io/img/PBVN.png",
            "desc": "Ngân hàng TNHH MTV Public Việt Nam",
            "bin": "970439",
            "status": "disable"
        },
        {
            "id": 20053,
            "currency": "VND",
            "code": "NHB HN",
            "name": "Nonghyup",
            "logo": "https://api.vietqr.io/img/NHB.png",
            "desc": "Ngân hàng Nonghyup - Chi nhánh Hà Nội",
            "bin": "801011",
            "status": "disable"
        },
        {
            "id": 20054,
            "currency": "VND",
            "code": "IVB",
            "name": "IndovinaBank",
            "logo": "https://api.vietqr.io/img/IVB.png",
            "desc": "Ngân hàng TNHH Indovina",
            "bin": "970434",
            "status": "disable"
        },
        {
            "id": 20055,
            "currency": "VND",
            "code": "IBK-HCM",
            "name": "IBKHCM",
            "logo": "https://api.vietqr.io/img/IBK.png",
            "desc": "Ngân hàng Công nghiệp Hàn Quốc - Chi nhánh TP. Hồ Chí Minh",
            "bin": "970456",
            "status": "disable"
        },
        {
            "id": 20056,
            "currency": "VND",
            "code": "IBK-HN",
            "name": "IBKHN",
            "logo": "https://api.vietqr.io/img/IBK.png",
            "desc": "Ngân hàng Công nghiệp Hàn Quốc - Chi nhánh Hà Nội",
            "bin": "970455",
            "status": "disable"
        },
        {
            "id": 20057,
            "currency": "VND",
            "code": "HSBC",
            "name": "HSBC",
            "logo": "https://api.vietqr.io/img/HSBC.png",
            "desc": "Ngân hàng TNHH MTV HSBC (Việt Nam)",
            "bin": "458761",
            "status": "disable"
        },
        {
            "id": 20058,
            "currency": "VND",
            "code": "HLBVN",
            "name": "HongLeong",
            "logo": "https://api.vietqr.io/img/HLBVN.png",
            "desc": "Ngân hàng TNHH MTV Hong Leong Việt Nam",
            "bin": "970442",
            "status": "disable"
        },
        {
            "id": 20059,
            "currency": "VND",
            "code": "GPB",
            "name": "GPBank",
            "logo": "https://api.vietqr.io/img/GPB.png",
            "desc": "Ngân hàng Thương mại TNHH MTV Dầu Khí Toàn Cầu",
            "bin": "970408",
            "status": "disable"
        },
        {
            "id": 20060,
            "currency": "VND",
            "code": "DOB",
            "name": "DongABank",
            "logo": "https://api.vietqr.io/img/DOB.png",
            "desc": "Ngân hàng TMCP Đông Á",
            "bin": "970406",
            "status": "disable"
        },
        {
            "id": 20061,
            "currency": "VND",
            "code": "DBS",
            "name": "DBSBank",
            "logo": "https://api.vietqr.io/img/DBS.png",
            "desc": "DBS Bank Ltd - Chi nhánh Thành phố Hồ Chí Minh",
            "bin": "796500",
            "status": "disable"
        },
        {
            "id": 20062,
            "currency": "VND",
            "code": "CIMB",
            "name": "CIMB",
            "logo": "https://api.vietqr.io/img/CIMB.png",
            "desc": "Ngân hàng TNHH MTV CIMB Việt Nam",
            "bin": "422589",
            "status": "disable"
        },
        {
            "id": 20063,
            "currency": "VND",
            "code": "CBB",
            "name": "CBBank",
            "logo": "https://api.vietqr.io/img/CBB.png",
            "desc": "Ngân hàng Thương mại TNHH MTV Xây dựng Việt Nam",
            "bin": "970444",
            "status": "disable"
        }
    ]
';
        $bank_arr = json_decode($bank,true);
        $in = [];
        $i=0;
        foreach ($bank_arr as $k => $b){
            $in[$i]['bankcode'] = $b['code'];
            $in[$i]['bankname'] = $b['name'];
            $in[$i]['images'] = $b['logo'];
            $in[$i]['bank_code'] = $b['id'];
            $in[$i]['currency'] = $b['currency'];
            $i++;
        }
        $str = '<table><th>Full Name</th><th>Bank Name（接口传bankname）</th>';
        $Model = M();
        foreach ($in as $key) {
            // $sqlstr = "insert into " . C('DB_PREFIX') . "systembank(bankcode,bankname,images,bank_code,currency) values('" . $key["bankcode"] . "','" . $key["bankname"] . "','" . $key["images"] . "','" . $key["bank_code"] . "','" . $key["currency"] . "');";
            // $returnnumber = $Model->execute($sqlstr);
            // if ($returnnumber > 0) {
            //     echo ($this->TransCode("新增系统银行【" . $key["bankname"] . "】<br>"));
            // }
            $str .= '<tr><td>' . $key["bankname"] . '</td><td>' . $key["bankcode"] . '</td></tr>';
        }
        $str .= '</table>';
        echo $str;
        // var_dump($in);
    }

    private function TransCode($Code)
    { // 中文转码
        return iconv("UTF-8", "GBK", $Code);
    }
}
?>
