<?php
namespace Admin\Controller;

use Org\Net\UserLogService;
use Think\Page;

class ChannelController extends BaseController
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
        $count = M('Channel')->count();
        $size  = 15;
        $rows  = I('get.rows', $size, 'intval');
        if (!$rows) {
            $rows = $size;
        }
        $Page = new Page($count, $rows);
        $data = M('Channel')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->order('id DESC')
            ->select();
        $this->assign('rows', $rows);
        $this->assign('list', $data);
        $this->assign('page', $Page->show());
        $this->display();
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
            $_request['code']         = trim($papiacc['code']);
            $_request['title']        = trim($papiacc['title']);
            $_request['mch_id']       = trim($papiacc['mch_id']);
            $_request['signkey']      = trim($papiacc['signkey']);
            $_request['appid']        = trim($papiacc['appid']);
            $_request['appsecret']    = trim($papiacc['appsecret']);
            $_request['gateway']      = trim($papiacc['gateway']);
            $_request['pagereturn']   = $papiacc['pagereturn'];
            $_request['serverreturn'] = $papiacc['serverreturn'];
            $_request['defaultrate']  = $papiacc['defaultrate'] ? $papiacc['defaultrate']/100 : 0;
            $_request['fengding']     = $papiacc['fengding'] ? $papiacc['fengding'] : 0;
            $_request['rate']         = $papiacc['rate'] ? $papiacc['rate']/100 : 0;
            $_request['t0defaultrate']  = $papiacc['t0defaultrate'] ? $papiacc['t0defaultrate']/100 : 0;
            $_request['t0fengding']     = $papiacc['t0fengding'] ? $papiacc['t0fengding']/100 : 0;
            $_request['t0rate']         = $papiacc['t0rate'] ? $papiacc['t0rate']/100 : 0;
            $_request['updatetime']   = time();
            $_request['unlockdomain'] = $papiacc['unlockdomain'];
            $_request['paytype']      = $papiacc['paytype'];
            $_request['status']       = $papiacc['status'];
            $_request['notifyIP']     = trim($papiacc['notifyIP']);
            if ($id) {
                //更新
                $res = M('Channel')->where(array('id' => $id))->save($_request);
                UserLogService::HTwrite(3, '保存供应商信息成功', '保存供应商（' . $id . '）信息成功（费率：' . $_request['rate'] . '%）');
            } else {
                //添加
                $res = M('Channel')->add($_request);
                UserLogService::HTwrite(3, '添加供应商信息成功', '添加供应商（' . $res . '）信息成功（费率：' . $_request['rate'] . '%）');
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
            $res = M('Channel')->where(['id' => $pid])->save(['status' => $isopen]);
            if (I('post.isopen')) {
                UserLogService::HTwrite(3, '开启供应商接口', '开启供应商接口');
            } else {
                UserLogService::HTwrite(3, '关闭供应商接口', '关闭供应商接口');
            }
            $this->ajaxReturn(['status' => $res]);
        }
    }

    //新增供应商接口
    public function addSupplier()
    {
        $this->display();
    }

    //编辑供应商接口
    public function editSupplier()
    {
        $pid = intval($_GET['pid']);
        if ($pid) {
            $pa = M('Channel')->where(['id' => $pid])->find();
            $pa['rate'] = $pa['rate'] * 100;
        }
        $this->assign('pa', $pa);
        $this->display('addSupplier');
    }
    //删除供应商接口
    public function delSupplier()
    {
        UserLogService::HTwrite(4, '删除供应商接口', '删除供应商接口');
        $pid = I('post.pid', 0, 'intval');
        if ($pid) {
            // 删除子账号
            M('channel_account')->where(['channel_id' => $pid])->delete();
            $res = M('Channel')->where(['id' => $pid])->delete();
            UserLogService::HTwrite(4, '删除供应商接口', '删除供应商接口（' . $pid . '）');
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
        $data = M('Product') ->order('id DESC')->select();
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
     * 通道账户列表
     */
    public function account()
    {
        $channel_id = I('get.pid');
        $channel    = M('Channel')->where(['id' => $channel_id])->find();
        $accounts   = M('channel_account')->where(['channel_id' => $channel_id])->select();
        $this->assign('channel', $channel);
        $this->assign('accounts', $accounts);
        $this->display();
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

    public function showEven()
    {
        // echo "<pre>";
        $channelList = M('Channel')->where(['control_status' => 1, 'status' => 1])->select();
        $accountList = M('ChannelAccount')->where(['control_status' => 1, 'status' => 1])->select();

        $list = [];
        foreach ($channelList as $k => $v) {
            $v['offline_status'] = $v['offline_status'] ? '上线' : '下线';
            $list[$k]            = $v;
            foreach ($accountList as $k1 => $v1) {
                if ($v1['channel_id'] == $v['id']) {
                    $v1['offline_status']  = $v1['offline_status'] ? '上线' : '下线';
                    $list[$k]['account'][] = $v1;
                }
            }
        }
        $this->assign('list', $list);
        $this->display();
    }

    /**
     * 保存账户
     */
    public function saveEditAccount()
    {
        UserLogService::HTwrite(3, '保存子账户', '保存子账户');
        if (IS_POST) {
            $id                     = I('post.id', 0, 'intval');
            $papiacc                = I('post.pa/a');
            $_request['title']      = trim($papiacc['title']);
            $_request['channel_id'] = trim($papiacc['pid']);
            $_request['mch_id']     = trim($papiacc['mch_id']);
            $_request['signkey']    = trim($papiacc['signkey']);
            $_request['appid']      = trim($papiacc['appid']);
            $_request['subappid']      = trim($papiacc['subappid']);
            $_request['appsecret']  = trim($papiacc['appsecret']);
            // 默认为1
            $weight                     = trim($papiacc['weight']);
            $_request['weight']         = $weight === '' ? 1 : $weight;
            $_request['custom_rate']    = $papiacc['custom_rate'];
            $_request['defaultrate']    = $papiacc['defaultrate'] ? $papiacc['defaultrate'] : 0;
            $_request['fengding']       = $papiacc['fengding'] ? $papiacc['fengding'] : 0;
            $_request['rate']           = $papiacc['rate'] ? $papiacc['rate'] : 0;
            $_request['t0defaultrate']    = $papiacc['t0defaultrate'] ? $papiacc['t0defaultrate'] : 0;
            $_request['t0fengding']       = $papiacc['t0fengding'] ? $papiacc['t0fengding'] : 0;
            $_request['t0rate']           = $papiacc['t0rate'] ? $papiacc['t0rate'] : 0;
            $_request['updatetime']     = time();
            $_request['status']         = $papiacc['status'];
            $_request['is_defined']     = $papiacc['is_defined'];
            $_request['all_money']      = $papiacc['all_money'] == '' ? 0:$papiacc['all_money'];
            $_request['min_money']      = $papiacc['min_money'] == '' ? 0:$papiacc['min_money'];
            $_request['max_money']      = $papiacc['max_money'] == '' ? 0:$papiacc['max_money'];
            $_request['start_time']     = $papiacc['start_time'];
            $_request['end_time']       = $papiacc['end_time'];
            $_request['offline_status'] = $papiacc['offline_status'];
            $_request['control_status'] = $papiacc['control_status'];
            $_request['unlockdomain'] = $papiacc['unlockdomain'];
            if ($id) {
                //更新
                $res = M('channel_account')->where(array('id' => $id))->save($_request);
                UserLogService::HTwrite(3, '成功保存子账户', '成功保存子账户--' . $id);
            } else {
                //添加
                $res = M('channel_account')->add($_request);
                UserLogService::HTwrite(3, '成功添加子账户', '成功添加子账户--' . $res);
            }
            $this->ajaxReturn(['status' => $res]);
        }
    }

    //编辑子账户状态
    public function editAccountStatus()
    {
        UserLogService::HTwrite(3, '编辑子账户状态', '编辑子账户状态');
        if (IS_POST) {
            $aid    = intval(I('post.aid'));
            $isopen = I('post.isopen') ? I('post.isopen') : 0;
            $res = M('channel_account')->where(['id' => $aid])->save(['status' => $isopen]);
            if ($isopen) {
                UserLogService::HTwrite(3, '开启子账户', '开启子账户');
            } else {
                UserLogService::HTwrite(3, '关闭子账户', '关闭子账户');
            }
            $this->ajaxReturn(['status' => $res]);
        }
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

    //编辑供应商风控
    public function editControl()
    {
        UserLogService::HTwrite(3, '编辑供应商风控', '编辑供应商风控');
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
            $res = M('Channel')->where(['id' => $data['id']])->save($data);
            UserLogService::HTwrite(3, '成功保存供应商风控', '成功保存供应商(' . $data['id'] . ')风控');
            $this->ajaxReturn(['status' => $res]);
        } else {
            $pid  = I('get.pid', '');
            $info = M('Channel')->where(['id' => $pid])->find();
            $this->assign('info', $info);
            $this->assign('pid', $pid);
            $this->display();
        }
    }
}
