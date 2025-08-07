<?php
namespace Admin\Controller;

use Org\Net\UserLogService;
use Think\Page;

class SystemBankController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->assign("Public", MODULE_NAME); // 模块名称
        $this->assign('paytypes', C('PAYTYPES'));

        //通道
        $channels = M('Channel')
            ->where(['status' => 1])
            ->field('id,code,title,paytype,status')
            ->select();
        $this->assign('channels', $channels);
        $this->assign('channellist', json_encode($channels));
    }

    //供应商接口列表
    public function index()
    {
        $count = M('bankcard')->count();
        $size  = 15;
        $rows  = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }
        $Page = new Page($count, $rows);
        $data = M('bankcard')->where('userid=0')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->order('id DESC')
            ->select();
        $this->assign('rows', $rows);
        $this->assign('list', $data);
        $this->assign('page', $Page->show());
        $this->display();
    }

    /*
     * 获取银行卡的信息
     */
    public function getBankinfos()
    {
        if (IS_POST) {
            $systembank = M('systembank');
            if (!$_POST['cardnumber']) $this->ajaxReturn(['status' => 0, 'msg' => '卡号不能为空']);
            $res = $systembank->where(['cardnumber' => $_POST['cardnumber']])->field('bankname,accountname,operator')->find();
            $operator = M('Member')->where(['id' => $res['operator']])->field('username')->find();
            $res['operatorname'] = $operator['username'];
            if (FALSE !== $res) {
                $this->ajaxReturn(['status' => 1, 'data' => $res, 'msg' => '获取信息成功']);
            } else {
                $this->ajaxReturn(['status' => 0, 'msg' => '获取信息失败']);
            }
        }
    }


    /**
     * 保存编辑供应商
     */
    public function saveEditSupplier()
    {
        UserLogService::HTwrite(3, '保存编辑供应商', '保存编辑供应商');
        if (IS_POST) {
            $id                       = I('post.id', 0, 'intval');
            $papiacc                  = I('post.pa/a');
            $_request['bankname']         = trim($papiacc['bankname']);
            $_request['accountname']        = trim($papiacc['accountname']);
            $_request['cardnumber']       = trim($papiacc['cardnumber']);
            $_request['userid']      = 0;
            $_request['status']       = $papiacc['status'];
            if ($id) {
                //更新
                $res = M('bankcard')->where(array('id' => $id))->save($_request);
                UserLogService::HTwrite(3, '保存供应商信息成功', '保存供应商（' . $id . '）信息成功（费率：' . $_request['rate'] . '）');
            } else {
                //添加
                $res = M('bankcard')->add($_request);
                UserLogService::HTwrite(3, '添加供应商信息成功', '添加供应商（' . $res . '）信息成功（费率：' . $_request['rate'] . '）');
            }
            $this->ajaxReturn(['status' => $res]);
        }
    }

    //开启供应商接口
    public function editStatus()
    {
        if (IS_POST) {
            UserLogService::HTwrite(3, '开启供应商接口', '开启供应商接口');
            $pid = intval(I('post.pid'));
            $isopen = I('post.isopen') ? I('post.isopen') : 0;
            $res = M('bankcard')->where(['id' => $pid])->save(['status' => $isopen]);
            if (I('post.isopen')) {
                UserLogService::HTwrite(3, '开启供应商接口', '开启供应商接口');
            } else {
                UserLogService::HTwrite(3, '关闭供应商接口', '关闭供应商接口');
            }
            if($res){
                $this->ajaxReturn(['status' => $isopen]);
            }

        }
    }

    //新增供应商接口
    public function addSupplier()
    {
        $bankcard = M('systembank')->select();
        $this->assign("bankcard", $bankcard);
        $this->display();
    }

    //编辑供应商接口
    public function editSupplier()
    {
        $pid = intval($_GET['pid']);
        if ($pid) {
            $pa = M('bankcard')->where(['id' => $pid])->find();
        }
        $bankcard = M('systembank')->select();
        $this->assign("bankcard", $bankcard);
        $this->assign('pa', $pa);
        $this->display('addSupplier');
    }
    //删除供应商接口
    public function delSupplier()
    {
        /*UserLogService::HTwrite(4, '删除供应商接口', '删除供应商接口');*/
        $pid = I('post.pid', 0, 'intval');
        if ($pid) {
            // 删除子账号
            $res = M('bankcard')->where(['id' => $pid])->delete();
/*            UserLogService::HTwrite(4, '删除供应商接口', '删除供应商接口（' . $pid . '）');*/
            $this->ajaxReturn(['status' => $res]);
        }
    }

    //编辑费率
    public function editRate()
    {
        UserLogService::HTwrite(3, '编辑费率', '编辑费率');
        if (IS_POST) {
            $pa = I('post.pa/a');
            $pid = I('post.pid', 0, 'intval');
            if ($pid) {
                $res = M('Channel')->where(['id' => $pid])->save($pa);
                UserLogService::HTwrite(3, '编辑费率成功', '成功编辑费率（' . $pid . '）');
                $pa['pid'] = $pid;
                $this->ajaxReturn(['status' => $res, 'data' => $pa]);
            }
        } else {
            $pid = intval(I('get.pid'));
            if ($pid) {
                $data = M('Channel')->where(['id' => $pid])->find();
            }

            $this->assign('pid', $pid);
            $this->assign('pa', $data);
            $this->display();
        }
    }

    //产品列表
    public function product()
    {
        $data = M('Product')->select();
        $this->assign('list', $data);
        $this->display();
    }

    //切换产品状态
    public function prodStatus()
    {
        UserLogService::HTwrite(3, '切换产品状态', '切换产品状态');
        if (IS_POST) {
            $id    = I('post.id', 0, 'intval');
            $colum = I('post.k');
            $value = I('post.v');
            $res = M('Product')->where(['id' => $id])->save([$colum => $value]);
            if ($id == 1) {
                UserLogService::HTwrite(3, '打开产品状态', '打开产品状态');
            } else {
                UserLogService::HTwrite(3, '关闭产品状态', '关闭产品状态');
            }
            $this->ajaxReturn(['status' => $res]);
        }
    }

    //切换用户显示状态
    public function prodDisplay()
    {
        UserLogService::HTwrite(3, '切换用户显示状态', '切换用户显示状态');
        if (IS_POST) {
            $id    = I('post.id', 0, 'intval');
            $colum = I('post.k');
            $value = I('post.v');
            $res = M('Product')->where(['id' => $id])->save([$colum => $value]);
            if ($id == 1) {
                UserLogService::HTwrite(3, '打开用户显示', '打开用户显示');
            } else {
                UserLogService::HTwrite(3, '关闭用户显示', '关闭用户显示');
            }
            $this->ajaxReturn(['status' => $res]);
        }
    }
    //添加产品
    public function addProduct()
    {
        $this->display();
    }

    //编辑产品
    public function editProduct()
    {
        $id   = I('get.pid', 0, 'intval');
        $data = M('Product')->where(['id' => $id])->find();

        //权重
        $weights    = [];
        $weights    = explode('|', $data['weight']);
        $_tmpWeight = '';
        if (is_array($weights)) {
            foreach ($weights as $value) {
                list($pid, $weight) = explode(':', $value);
                if ($pid) {
                    $_tmpWeight[$pid] = ['pid' => $pid, 'weight' => $weight];
                }
            }
        } else {
            list($pid, $weight) = explode(':', $data['weight']);
            if ($pid) {
                $_tmpWeight[$pid] = ['pid' => $pid, 'weight' => $weight];
            }
        }
        $data['weight'] = $_tmpWeight;
        //通道
        $channels = M('Channel')->where(["paytype" => $data['paytype'], "status" => 1])->select();
        $this->assign('channels', $channels);
        $this->assign('pd', $data);
        $this->display('addProduct');
    }

    //保存更改
    public function saveProduct()
    {
        UserLogService::HTwrite(3, '保存产品', '保存产品');
        if (IS_POST) {
            $id     = intval(I('post.id'));
            $rows   = I('post.pd/a');
            $weight = I('post.w/a');
            //权重
            $weightStr = '';
            if (is_array($weight)) {
                foreach ($weight as $weigths) {
                    if ($weigths['pid']) {
                        $weightStr .= $weigths['pid'] . ':' . $weigths['weight'] . "|";
                    }
                }
            }
            $rows['weight'] = trim($weightStr, '|');
            //检查费率合法性

            if($rows['t0defaultrate']>0 && $rows['t0defaultrate'] > $rows['t0fengding']) {
                $this->ajaxReturn(['status' => 0, 'msg' => 'T+0封顶费率不得低于T+0运营费率']);
            }
            if($rows['defaultrate']>0 && $rows['defaultrate'] > $rows['fengding']) {
                $this->ajaxReturn(['status' => 0, 'msg' => 'T+1封顶费率不得低于T+1运营费率']);
            }
            if($rows['polling'] == 0 && $rows['channel'] > 0) {//单独渠道的情况
                $channel = M('Channel')
                    ->where(['id' => $rows['channel'], 'status' => 1])
                    ->find();
                $channel_account_list = M('channel_account')->where(['channel_id' => $rows['channel'], 'status' => '1', 'custom_rate' => 1])->select();
                if (!empty($channel)) {
                    if (!empty($channel_account_list)) {
                        foreach ($channel_account_list as $k => $v) {
                            if ($rows['t0defaultrate'] > 0 && $rows['t0defaultrate'] < $v['rate']) {
                                $this->ajaxReturn(['status' => 0, 'msg' => '【' . $channel['title'] . '】T+0运营费率不得低于渠道子账号成本费率']);
                            }
                            if ($rows['defaultrate'] > 0 && $rows['defaultrate'] < $v['rate']) {
                                $this->ajaxReturn(['status' => 0, 'msg' => '【' . $channel['title'] . '】T+1运营费率不得低于渠道子账号成本费率']);
                            }
                        }
                    } else {
                        if ($rows['t0defaultrate'] > 0 && $rows['t0defaultrate'] < $channel['rate']) {
                            $this->ajaxReturn(['status' => 0, 'msg' => '【' . $channel['title'] . '】T+0运营费率不得低于渠道成本费率']);
                        }
                        if ($rows['defaultrate'] > 0 && $rows['defaultrate'] < $channel['rate']) {
                            $this->ajaxReturn(['status' => 0, 'msg' => '【' . $channel['title'] . '】T+1运营费率不得低于渠道成本费率']);
                        }
                    }
                }
            }
            if($rows['polling'] == 1 && $rows['weight'] != '') {//渠道轮询的情况
                $temp_weights = explode('|', $rows['weight']);
                if(!empty($temp_weights)) {
                    foreach ($temp_weights as $k => $v) {
                        list($pid, $weight) = explode(':', $v);
                        $channel = M('channel')->where(['id' => $pid, 'status' => 1])->find();
                        $channel_account_list = M('channel_account')->where(['channel_id' => $pid, 'status' => '1', 'custom_rate' => 1])->select();
                        if (!empty($channel)) {
                            if (!empty($channel_account_list)) {
                                foreach ($channel_account_list as $k => $v) {
                                    if ($rows['t0defaultrate'] > 0 && $rows['t0defaultrate'] < $v['rate']) {
                                        $this->ajaxReturn(['status' => 0, 'msg' => '【' . $channel['title'] . '】T+0运营费率不得低于渠道子账号成本费率']);
                                    }
                                    if ($rows['defaultrate'] > 0 && $rows['defaultrate'] < $v['rate']) {
                                        $this->ajaxReturn(['status' => 0, 'msg' => '【' . $channel['title'] . '】T+1运营费率不得低于渠道子账号成本费率']);
                                    }
                                }
                            } else {
                                if ($rows['t0defaultrate'] > 0 && $rows['t0defaultrate'] < $channel['rate']) {
                                    $this->ajaxReturn(['status' => 0, 'msg' => '【' . $channel['title'] . '】T+0运营费率不得低于渠道成本费率']);
                                }
                                if ($rows['defaultrate'] > 0 && $rows['defaultrate'] < $channel['rate']) {
                                    $this->ajaxReturn(['status' => 0, 'msg' => '【' . $channel['title'] . '】T+1运营费率不得低于渠道成本费率']);
                                }
                            }
                        }
                    }
                }
            }
            //保存
            if ($id) {
                $res = M('Product')->where(['id' => $id])->save($rows);
                UserLogService::HTwrite(3, '修改产品成功', '成功保存产品--' . $id);
            } else {
                $res = M('Product')->add($rows);
                UserLogService::HTwrite(3, '添加产品成功', '成功添加产品--' . $res);
            }
            if(FALSE !== $res) {
                $this->ajaxReturn(['status' => 1]);
            } else {
                $this->ajaxReturn(['status' => 0]);
            }
        }
    }

    //删除产品
    public function delProduct()
    {
        UserLogService::HTwrite(3, '删除产品', '删除产品');
        if (IS_POST) {
            $id  = I('post.pid', 0, 'intval');
            $res = M('Product')->where(['id' => $id])->delete();
            UserLogService::HTwrite(3, '成功删除产品', '成功删除产品--' . $id);
            $this->ajaxReturn(['status' => $res]);
        }
    }

    //接口模式
    public function selProduct()
    {
        if (IS_POST) {
            $paytyep = I('post.paytype', 0, 'intval');
            //通道
            $data = M('Channel')->where(["paytype" => $paytyep, "status" => 1])->select();
            $this->ajaxReturn(['status' => 0, 'data' => $data]);
        }
    }


    /**
     * 编辑账户
     */
    public function editAccountControl()
    {
        UserLogService::HTwrite(3, '编辑子账户', '编辑子账户');
        if (IS_POST) {
            $data = I('post.data', '');

            // if ($data['start_time'] != 0 || $data['end_time'] != 0) {
            //     if ($data['start_time'] >= $data['end_time']) {
            //         $this->ajaxReturn(['status' => 0, 'msg' => '交易结束时间不能小于开始时间！']);
            //     }
            // }
            if ($data['max_money'] != 0 && $data['min_money'] != 0) {
                if ($data['min_money'] >= $data['max_money']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => '最大交易金额不能小于或等于最小金额！']);
                }
            }
            if ($data['is_defined'] == 0) {
                $channel_id = M('ChannelAccount')->where(['id' => $data['id']])->getField('channel_id');
                $channelInfo = M('Channel')->where(['id' => $channel_id])->find();
                $data['offline_status'] = $channelInfo['offline_status'];
                $data['control_status'] = $channelInfo['control_status'];
            }
            $res = M('ChannelAccount')->where(['id' => $data['id']])->save($data);
            UserLogService::HTwrite(3, '成功保存子账户', '编辑子账户--' . $data['id']);
            $this->ajaxReturn(['status' => $res]);
        } else {
            $aid  = I('get.aid', '', 'intval');
            $info = M('ChannelAccount')->where(['id' => $aid])->find();

            $this->assign('info', $info);
            $this->assign('aid', $aid);
            $this->display();
        }

    }

    /**
     * 编辑账户
     */
    public function editAccount()
    {
        $aid = intval($_GET['aid']);
        if ($aid) {
            $pa = M('channel_account')->where(['id' => $aid])->find();
        }
        $this->assign('pa', $pa);
        $this->assign('pid', $pa['channel_id']);
        $this->display('addAccount');
    }

    /**
     * 新增账户
     */
    public function addAccount()
    {
        $pid = intval($_GET['pid']);
        $this->assign('pid', $pid);
        $this->display('addAccount');
    }




    //删除子账户
    public function delAccount()
    {
        UserLogService::HTwrite(3, '删除子账户', '删除子账户');
        $aid = I('post.aid', 0, 'intval');
        if ($aid) {
            $res = M('channel_account')->where(['id' => $aid])->delete();
            UserLogService::HTwrite(3, '成功删除子账户', '成功删除子账户--' . $aid);
            $this->ajaxReturn(['status' => $res]);
        }
    }

    //编辑子账户费率
    public function editAccountRate()
    {
        UserLogService::HTwrite(3, '编辑子账户费率', '编辑子账户费率');
        if (IS_POST) {
            $pa = I('post.pa');
            $accountId = I('post.aid');
            if ($accountId) {
                $res = M('channel_account')->where(['id' => $accountId])->save($pa);
                UserLogService::HTwrite(3, '保存子账户费率', '保存子账户（' . $accountId . '）费率');
                $pa['aid'] = $accountId;
                $this->ajaxReturn(['status' => $res, 'data' => $pa]);
            }
        } else {
            $aid = intval(I('get.aid'));
            if ($aid) {
                $data = M('channel_account')->where(['id' => $aid])->find();
            }

            $this->assign('aid', $aid);
            $this->assign('pa', $data);
            $this->display();
        }
    }


}
