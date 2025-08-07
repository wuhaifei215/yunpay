<?php
namespace User\Controller;
use Think\Page;

class DownloadController extends BaseController
{
    public $expire_time = 3600 * 8; //1天的秒数
    public function __construct()
    {
        parent::__construct();
    }
    
    public function redis_connect(){
        //创建一个redis对象
        $redis = new \Redis();
        //连接 Redis 服务
        $redis->connect(C('REDIS_HOST'), C('REDIS_PORT'));
        //密码验证
        $redis->auth(C('REDIS_PWD'));
        //设置key-value
        // $redis->set('downloadList','111');
        // 设置键的过期时间为3600秒
        // $redis->expire('downloadList',3600);
        //获取value
        // $list = $redis->get('downloadList');
        
        // 在列表头部插入元素
        // $redis->lPush('list', 'value1');
        // 从列表左侧弹出元素
        // $redis->lPop('list');
        // 在列表尾部插入元素
        // $redis->rPush('list', 'value2');
        // 获取列表的长度
        // $length = $redis->lLen('list');
        // 获取列表的所有元素
        // $list = $redis->lRange('list', 0, -1);
        // 使用Redis的LRANGE命令获取分页数据
        // $list = $redis->lRange('list', $start, $end);
        // 使用SORT命令进行排序，这里以按value字典序升序为例
        // BY参数指定了使用哪个key的value作为sort的依据
        // GET参数指定了使用哪个key的value的哪部分作为排序的值
        // $redis->sort($key, ['by' => $key.*.*, 'get' => '#']));
        return $redis;
    }

    //首页
    public function index()
    {
        $redis = $this->redis_connect();
        $uid = 10000+session("user_auth.uid");
        $key = 'downList_user_' . $uid;
        //获取列表的所有元素
        $downList = $redis->lRange($key, 0, -1);
        
        //删除列表
        // $redis->delete($key);
        $list = [];
        foreach ($downList as $v){
            $list_msg = $redis->get($v);
            if(!empty($list_msg)){
                $list[] =  json_decode($list_msg,true);
                // $redis->rPush($key, $v);
            }
        }
        $list = array_reverse($list);
        
        $this->assign('uid', session("user_auth.uid"));
        $this->assign("list", $list);
        C('TOKEN_ON', false);
        $this->display();
    }
    public function setlist(){
        $uid = I("request.uid", '', 'trim,string,strip_tags,htmlspecialchars');
        $down_url = I("request.down_url", '', 'trim,string,strip_tags,htmlspecialchars');
        $where = I("request.where", '');
        $where = array_filter($where);
        $down_name = I("request.down_name", '', 'trim,string,strip_tags,htmlspecialchars');
        $type = I("request.type", '', 'trim,string,strip_tags,htmlspecialchars');
        $key = 'download_user_' . $uid . '_' . date('YmdHis');
        $down_array = [
            'uid' => $uid,
            'type' => $type,
            'name' => $down_name,
            'down_url' => $down_url,
            'where' => $where,
            'status' => 0,
            'time' => time(),
        ];
        
        $redis = $this->redis_connect();
        //设置key-value
        $redis->set($key , json_encode($down_array, JSON_UNESCAPED_UNICODE));
        // 设置键的过期时间
        $redis->expire($key , $this->expire_time);
        // 在列表尾部插入元素
        $redis->rPush('downList_user_' . $uid, $key);
        $redis->rPush('downList', $key);
        $this->success('添加成功');
    }

}
