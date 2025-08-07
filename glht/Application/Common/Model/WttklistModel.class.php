<?php
// +----------------------------------------------------------------------
// | 订单模型
// +----------------------------------------------------------------------
namespace Common\Model;
use Think\Model;

class WttklistModel extends Model {
    protected $expire_date = 20250225;
    protected $tablePrefix = 'pay_'; // 分表前缀
    protected $tableName = 'wttklist'; // 默认表名
    protected $orderTables=[];      //订单分表表名
    protected $timeoptions = ['sqdatetime','cldatetime'];
    // 数据库表达式
    protected $exp = array('eq' => '=', 'neq' => '<>', 'gt' => '>', 'egt' => '>=', 'lt' => '<', 'elt' => '<=', 'notlike' => 'NOT LIKE', 'like' => 'LIKE', 'in' => 'IN', 'notin' => 'NOT IN', 'not in' => 'NOT IN', 'between' => 'BETWEEN', 'not between' => 'NOT BETWEEN', 'notbetween' => 'NOT BETWEEN');

    public function __construct()
    {
        parent::__construct();
        //获取所有订单分表表名
        $orderTable_arr = $this->query("SHOW TABLES LIKE '". $this->tablePrefix . $this->tableName ."%'");
        $orderTable_array=[];
        foreach($orderTable_arr as $v){
            foreach ($v as $vv){
                $orderTable_array[] = $vv;
            }
        }
        krsort($orderTable_array);
        $this->orderTables = $orderTable_array;
        $this->createTable();
        // $this->setRealTableName();
    }
    protected function createTable(){
        $tableName = $this->getRealTableName(date('Ymd',time()));
        $isTable = $this->query("SHOW TABLES LIKE '". $tableName ."'");
        if(!$isTable){
            $lastId_array = $this->getLastIds();
            array_push($this->orderTables,$tableName);
            if($lastId_array){
                $maxValue = max($lastId_array);
                $newId = ($maxValue+1);
            }else{
                $newId = 1;
            }
            $creatSql = "CREATE TABLE `" . $tableName . "` (
                  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                  `userid` int(11) NOT NULL,
                  `orderid` varchar(100) NOT NULL DEFAULT ' ' COMMENT '订单id',
                  `out_trade_no` varchar(30) DEFAULT '' COMMENT '下游订单号',
                  `bankname` varchar(300) NOT NULL,
                  `bankzhiname` varchar(300) DEFAULT NULL,
                  `banknumber` varchar(300) NOT NULL,
                  `bankfullname` varchar(300) NOT NULL,
                  `tkmoney` decimal(15,4) NOT NULL DEFAULT '0.0000',
                  `sxfmoney` decimal(15,4) unsigned NOT NULL DEFAULT '0.0000' COMMENT '手续费',
                  `money` decimal(15,4) unsigned NOT NULL DEFAULT '0.0000' COMMENT '实际到账',
                  `sqdatetime` datetime DEFAULT NULL,
                  `cldatetime` datetime DEFAULT NULL,
                  `status` enum('0','1','2','3','4','5','6') NOT NULL DEFAULT '0' COMMENT '状态:0=未处理,1=处理中,2=成功未返回,3=成功已返回,4=失败未返回,5=失败已返回,6=已驳回,',
                  `paytype` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '渠道类型: 1 Gcash直连 2 Gcash扫码 3 Maya 4 VietQR',
                  `memo` text COMMENT '备注',
                  `additional` varchar(1000) NOT NULL DEFAULT ' ' COMMENT '额外的参数',
                  `code` varchar(64) NOT NULL DEFAULT ' ' COMMENT '代码控制器名称',
                  `df_charge_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '代付API扣除手续费方式，0：从到账金额里扣，1：从商户余额里扣',
                  `bankcode` varchar(100) NOT NULL COMMENT '银行编码',
                  `df_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '代付通道id',
                  `df_name` varchar(64) NOT NULL DEFAULT ' ' COMMENT '代付名称',
                  `channel_mch_id` varchar(50) NOT NULL DEFAULT '' COMMENT '通道商户号',
                  `cost` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '成本',
                  `cost_rate` decimal(10,4) unsigned NOT NULL DEFAULT '0.0000' COMMENT '成本费率',
                  `rate_type` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '费率类型：按单笔收费0，按比例收费：1',
                  `extends` text COMMENT '扩展数据',
                  `auto_submit_try` int(10) NOT NULL DEFAULT '0' COMMENT '自动代付尝试提交次数',
                  `last_submit_time` int(11) NOT NULL DEFAULT '0' COMMENT '最后提交时间',
                  `df_lock` tinyint(1) NOT NULL DEFAULT '0' COMMENT '代付锁，防止重复提交',
                  `lock_time` int(11) NOT NULL DEFAULT '0' COMMENT '锁定时间',
                  `auto_query_num` int(10) NOT NULL DEFAULT '0' COMMENT '自动查询次数',
                  `transaction_id` varchar(50) NOT NULL DEFAULT '' COMMENT '上游订单号',
                  `billno` varchar(50) NOT NULL DEFAULT '' COMMENT '上游交易流水号',
                  `notifyurl` varchar(255) DEFAULT '' COMMENT '异步通知地址',
                  `notifycount` int(1) unsigned zerofill NOT NULL DEFAULT '0' COMMENT '回调次数',
                  `last_notify_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '上次回调时间',
                  `fail_re` tinyint(2) DEFAULT NULL,
                  `last_reissue_time` int(11) DEFAULT '0',
                  `num` tinyint(2) DEFAULT '0',
                  `is_auto` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否自动提交',
                  `type` varchar(20) DEFAULT NULL COMMENT '代付类型：CPF、PHONE、EMAIL、EVP',
                  `df_type` tinyint(1) DEFAULT '1' COMMENT '下发类型。1:普通代付，2:U下发',
                  `three_orderid` varchar(100) DEFAULT NULL COMMENT '三方订单号',
                  PRIMARY KEY (`id`) USING BTREE,
                  KEY `code` (`code`) USING BTREE,
                  KEY `df_id` (`df_id`) USING BTREE,
                  KEY `orderid` (`orderid`) USING BTREE,
                  KEY `sqdatetime` (`sqdatetime`) USING BTREE,
                  KEY `out_trade_no` (`out_trade_no`,`userid`) USING BTREE,
                  KEY `cldatetime` (`cldatetime`),
                  KEY `status` (`status`) USING BTREE
                ) ENGINE=InnoDB AUTO_INCREMENT=" . $newId . " DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;";
            $this->execute($creatSql);
        }
    }

    public function getTables($data=array()){
        if(!empty($data) && $data['sqdatetime']){
            //获取时间范围内的表
            list($startdate, $enddate) = $this->new_parseDate($data);
            $data_arr = $this->getDateRange($startdate, $enddate);
            foreach ($data_arr as $date) {
                $realTableName = $this->getRealTableName($date);
                if(in_array($realTableName,$this->orderTables)){
                    $tables[] = $realTableName;
                }
            }
        }else{
            $tables = $this->orderTables;
        }
        return $tables;
    }

    public function getLastIds($returnAllArray = false){
        foreach ($this->orderTables as $k => $v){
            $lastIds = $this->query("SELECT id FROM " . $v . ' ORDER BY id DESC LIMIT 1');
            $lastId_array[] = $lastIds[0]['id']+0;
            $lastAllArray[$k]['table']=$v;
            $lastAllArray[$k]['id']=$lastIds[0]['id']+0;
        }
        if(!$returnAllArray){
            return $lastId_array;
        }else{
            return $lastAllArray;
        }
    }

    // 获取实际表名的方法
    public function getRealTableName($date) {
        if(date('Ymd', strtotime($date)) < $this->expire_date){
            $date_md = '';
        }else{
            $date_md = date('md', strtotime($date));
        }
        return $this->tablePrefix . $this->tableName . $date_md;
    }

    // 获取实际表名的方法
    public function setRealTableName($options =array()) {
        $date = isset($options['sqdatetime']) ? $options['sqdatetime'] : date('Ymd',time());
        $realTableName = $this->getRealTableName($date);
        // 设置当前模型对应的数据表
        $this->table($realTableName);
        return $this;
    }

    public function getCount($options=array()){
        $field = 'count(id) as tp_count';
        $count_arr = $this->getOrderByDateRange($field, $options);
        $count = 0;
        foreach ($count_arr as $ck => $cv){
            $count+=$cv['tp_count'];
        }
        return $count;
    }

    public function getSum($field='', $options=array()){
        $field_arr = explode(',' , $field);
        $field_str='';
        foreach ($field_arr as $v){
            $field_str .= 'SUM(`' . $v . '`) ' . $v . ',';
        }
        $field_str = rtrim($field_str, ','); // 移除最后一个
        $sum_arr=[];
        $sum_arr = $this->getOrderByDateRange($field_str, $options);
        $sum=[];
        foreach ($sum_arr as $sk => $sv){
            if($sv){
                foreach ($sv as $skk => $svv){
                    $sum[$skk] += $svv;
                }
            }
        }
        return $sum;
    }

    // 按时间范围查询数据表
    public function getOrderByDateRange($field=array(), $options=array(), $limit='', $orderby='' , $groupby='') {
        if(!$field){
            $field = '*';
        }else{
            $field = $this->new_parseField($field);
        }
        if($orderby){
            $orderby = ' ORDER BY ' . $orderby;
        }
        if($groupby){
            $groupby = ' GROUP BY ' . $groupby;
        }
        if($limit){
            $limit = ' LIMIT ' . $limit;
        }
        
        $where = '';
        $optionstr = $this->new_parseOptions($options);
        if($optionstr){
            $where .= " WHERE {$optionstr}";
        }
        $result = [];
        list($startdate, $enddate) = $this->new_parseDate($options);
        if($startdate && $enddate){
            //获取时间段内的每一天
            $data_arr = $this->getDateRange($startdate, $enddate);        // 构建查询语句
            $break = 0;
            foreach ($data_arr as $date) {
                $realTableName = $this->getRealTableName($date);
                if(in_array($realTableName,$this->orderTables) && intval($date) >= $this->expire_date){
                    $unionSql .= "SELECT {$field} FROM `{$realTableName}`";
                    if($where){
                        $unionSql .= $where;
                    }
                    if($groupby){
                        $unionSql .= $groupby;
                    }
                    $unionSql .= " UNION All ";
                }elseif($break == 0 && intval($date) < $this->expire_date){
                    $unionSql .= "SELECT {$field} FROM `{$this->tablePrefix}{$this->tableName}`";
                    if($where){
                        $unionSql .= $where;
                    }
                    if($groupby){
                        $unionSql .= $groupby;
                    }
                    $unionSql .= " UNION All ";
                    $break = 1;
                }
                if($break===1 && intval($date) < $this->expire_date){
                    break;
                }
            }
            $unionSql = rtrim($unionSql, 'UNION  All '); // 移除最后一个UNION
            if($unionSql !=''){
                if($orderby){
                    $unionSql .= $orderby;
                }
                if($limit){
                    $unionSql .= $limit;
                }
                $do_unionSql = 'SELECT * FROM (' . $unionSql . ') AS subquery';
                // var_dump($do_unionSql);
                // 执行联合查询
                $result = $this->query($do_unionSql);
            }
        }else{
            $realTableName = $this->getRealTableName($startdate);
            if(in_array($realTableName,$this->orderTables)){
                $unionSql = "SELECT {$field} FROM `{$realTableName}`";
                if($where){
                    $unionSql .= $where;
                }
                if($groupby){
                        $unionSql .= $groupby;
                    }
                if($orderby){
                    $unionSql .= $orderby;
                }
                if($limit){
                    $unionSql .= $limit;
                }
                // 执行联合查询
                $result = $this->query($unionSql);
            }
        }
        return $result;
    }

    // 批量新增数据
    public function addAllByDate($data, $options = array()){
        // 根据创建时间计算应该使用的分表
        $date = isset($data['sqdatetime']) ? $data['sqdatetime'] : date('Ymd',time());
        $tableName = $this->getRealTableName($date);
        // 切换到对应的分表进行插入操作
        $this->table($tableName);
        return $this->addAll($data, $options);
    }

    public function saveByDate($data, $options = array()){
        // 根据创建时间计算应该使用的分表
        $date = isset($options['sqdatetime']) ? $options['sqdatetime'] : date('Ymd',time());
        $tableName = $this->getRealTableName($date);
        // 切换到对应的分表进行更新操作
        $options['where'] = $options;
        $options['table'] = $tableName;
        return $this->db->update($data, $options);
    }

    public function new_parseDate($options){
        $startdate = $enddate = '';
        if(!isset($options['sqdatetime']) && !isset($options['cldatetime'])){
            $date = date('Y-m-d',time());
            return [$date,$date];
        }
        if(isset($options['sqdatetime'])){
            if(is_array($options['sqdatetime'])){
                $startdate = $options['sqdatetime'][1][0];
                $enddate = $options['sqdatetime'][1][1];
            }else{
                $date = $options['sqdatetime']?$options['sqdatetime']:date('Y-m-d',time());
                list($startdate, $enddate) = explode('|', $date);
            }
        }elseif(isset($options['cldatetime'])){
            if(is_array($options['cldatetime'])){
                $startdate = $options['cldatetime'][1][0];
                $enddate = $options['cldatetime'][1][1];
            }else{
                $date = $options['cldatetime']?$options['cldatetime']:date('Y-m-d',time());
                list($startdate, $enddate) = explode('|', $date);
            }
        }
        return [$startdate,$enddate];
    }

    //where条件转换为语句
    public function new_parseOptions($options){
        if (is_array($options) && (count($options) > 0)) {
            foreach ($options as $key => $value){
                $where[] = $this->parseWhereItem($key, $value);
            }
            $options = implode(" AND ", $where);
        }
        return $options;
    }

    public function new_parseField($field){
        if(is_array($field) && (count($field) > 0)) {
            $field = implode(",", $field);
        }
        return $field;
    }

    //获取时间段内的每一天
    public function getDateRange($startdate, $enddate) {
        $stimestamp = is_numeric($startdate)?$startdate:strtotime($startdate);
        $etimestamp = is_numeric($enddate)?$enddate:strtotime($enddate);
        // 计算日期段内有多少天
        $days = floor(($etimestamp - $stimestamp) / 86400) + 1;
        // 保存每天日期
        $date = array();
        for($i = $days; $i >= 0; $i--){
            $date[] = date('Ymd', $stimestamp + (86400 * $i));
        }
        return $date;
    }

    // where子单元分析
    public function parseWhereItem($key, $val)
    {
        $whereStr = '';
        if (is_array($val)) {
            if (is_string($val[0])) {
                $exp = strtolower($val[0]);
                if (preg_match('/^(eq|neq|gt|egt|lt|elt)$/', $exp)) {
                    // 比较运算
                    $whereStr .= $key . ' ' . $this->exp[$exp] . ' ' . $this->parseValue($val[1]);
                } elseif (preg_match('/^(notlike|like)$/', $exp)) {
                    // 模糊查找
                    if (is_array($val[1])) {
                        $likeLogic = isset($val[2]) ? strtoupper($val[2]) : 'OR';
                        if (in_array($likeLogic, array('AND', 'OR', 'XOR'))) {
                            $like = array();
                            foreach ($val[1] as $item) {
                                $like[] = $key . ' ' . $this->exp[$exp] . ' ' . $this->parseValue($item);
                            }
                            $whereStr .= '(' . implode(' ' . $likeLogic . ' ', $like) . ')';
                        }
                    } else {
                        $whereStr .= $key . ' ' . $this->exp[$exp] . ' ' . $this->parseValue($val[1]);
                    }
                } elseif ('bind' == $exp) {
                    // 使用表达式
                    $whereStr .= $key . ' = :' . $val[1];
                } elseif ('exp' == $exp) {
                    // 使用表达式
                    $whereStr .= $key . ' ' . $val[1];
                } elseif (preg_match('/^(notin|not in|in)$/', $exp)) {
                    // IN 运算
                    if (isset($val[2]) && 'exp' == $val[2]) {
                        $whereStr .= $key . ' ' . $this->exp[$exp] . ' ' . $val[1];
                    } else {
                        if (is_string($val[1])) {
                            $val[1] = explode(',', $val[1]);
                        }
                        $zone = implode(',', $this->parseValue($val[1]));
                        $whereStr .= $key . ' ' . $this->exp[$exp] . ' (' . $zone . ')';
                    }
                } elseif (preg_match('/^(notbetween|not between|between)$/', $exp)) {
                    // BETWEEN运算
                    $data = is_string($val[1]) ? explode(',', $val[1]) : $val[1];
                    $whereStr .= $key . ' ' . $this->exp[$exp] . ' ' . $this->parseValue($data[0]) . ' AND ' . $this->parseValue($data[1]);
                } else {
                    E(L('_EXPRESS_ERROR_') . ':' . $val[0]);
                }
            } else {
                $count = count($val);
                $rule  = isset($val[$count - 1]) ? (is_array($val[$count - 1]) ? strtoupper($val[$count - 1][0]) : strtoupper($val[$count - 1])) : '';
                if (in_array($rule, array('AND', 'OR', 'XOR'))) {
                    $count = $count - 1;
                } else {
                    $rule = 'AND';
                }
                for ($i = 0; $i < $count; $i++) {
                    $data = is_array($val[$i]) ? $val[$i][1] : $val[$i];
                    if ('exp' == strtolower($val[$i][0])) {
                        $whereStr .= $key . ' ' . $data . ' ' . $rule . ' ';
                    } else {
                        $whereStr .= $this->parseWhereItem($key, $val[$i]) . ' ' . $rule . ' ';
                    }
                }
                $whereStr = '( ' . substr($whereStr, 0, -4) . ' )';
            }
        } else {
            //对字符串类型字段采用模糊匹配
            $likeFields = $this->config['db_like_fields'];
            if ($likeFields && preg_match('/^(' . $likeFields . ')$/i', $key)) {
                $whereStr .= $key . ' LIKE ' . $this->parseValue('%' . $val . '%');
            } else {
                $whereStr .= $key . ' = ' . $this->parseValue($val);
            }
        }
        return $whereStr;
    }
    /**
     * value分析
     * @access protected
     * @param mixed $value
     * @return string
     */
    public function parseValue($value)
    {
        if (is_string($value)) {
            $value = strpos($value, ':') === 0 && in_array($value, array_keys($this->bind)) ? $this->escapeString($value) : '\'' . $this->escapeString($value) . '\'';
        } elseif (isset($value[0]) && is_string($value[0]) && strtolower($value[0]) == 'exp') {
            $value = $this->escapeString($value[1]);
        } elseif (is_array($value)) {
            $value = array_map(array($this, 'parseValue'), $value);
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_null($value)) {
            $value = 'null';
        }
        return $value;
    }
    /**
     * SQL指令安全过滤
     * @access public
     * @param string $str  SQL字符串
     * @return string
     */
    public function escapeString($str)
    {
        return addslashes($str);
    }

}

?>