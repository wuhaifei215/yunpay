<?php
namespace Admin\Controller;
use Org\Net\UserLogService;
use Think\Page;
class PayForAnotherController extends BaseController{
    public function __construct()
    {
        parent::__construct();
        //分类 类型
        $paytypes = C('PAYTYPES');
        $this->assign('paytypes', $paytypes);
    }


	public function index(){
		$pfa_model = D('PayForAnother');
		$count = $pfa_model->count();
		$page = new Page($count, 15);
		$where = [];
		$lists = $pfa_model->where($where)->limit($page->firstRow . ',' . $page->listRows) ->order('id DESC')->select();
		foreach($lists as $k => $v){
			$lists[$k]['updatetime'] = date('Y-m-d H:i:s', $v['updatetime']);
		}
		$this->assign('lists', $lists);
		$this->assign('page',$page->show());
		$this->display();
	}

	public function operationSupplier(){
		$id = I('get.id',0,'intval');

		if($id){
			$pfa_model = D('PayForAnother');
			$where = ['id'=>$id];
			$list = $pfa_model->where($where)->find();
			$this->assign('list', $list);
		}
		$this->display();
	}

	public function saveEditSupplier(){
		if(IS_POST){
			
			$pfa_model = D('PayForAnother');
            if(!$_POST['cost_rate']) {
                $_POST['cost_rate'] = 0;
            }
			$data = $pfa_model->create();
			$pfa_model->startTrans();
			//判断是修改数据还是添加数据
			if(!$data['id']){
				$result = $pfa_model->add();
				$data['id'] = $result;
			}else{
				$result = $pfa_model->save();
			}
			
			//如果该代付通道是默认选择的通道，将其他通道改为不默认
			if($result){
				if($data['is_default'] == 1){
					if($pfa_model->editAllDefault($data['id'])){
						$pfa_model->commit();
						$this->ajaxReturn(['status'=>1]);
					}
				}else{
					$pfa_model->commit();
					$this->ajaxReturn(['status'=>1]);
				}
			}

			$pfa_model->rollback();
			$this->ajaxReturn(['status'=>0]);
		}
	}

	public function delSupplier(){
		$id = I('post.id','intval');
		if($id){
			$pfa_model = D('PayForAnother');
            $res = $pfa_model->where(['id'=>$id])->delete();
            $this->ajaxReturn(['status'=>$res]);
        }
	}

	//修改单一字段
	public function editStatus(){
		$id = I('post.id',0,'intval');
		$isopen = I('post.isopen','intval');
		if($isopen===1){
	        UserLogService::HTwrite(3, '设置通道(' . $id . ')开启', '设置通道(' . $id . ')开启');
		}else{
	        UserLogService::HTwrite(3, '设置通道(' . $id . ')关闭', '设置通道(' . $id . ')关闭');
		}
		if($id && $isopen!='' ){
			$pfa_model = D('PayForAnother');
            $reslut = $pfa_model->where(['id'=>$id])->save(['status'=>$isopen]);
            if($reslut){
                UserLogService::HTwrite(3, '设置通道(' . $id . ')成功', '设置通道(' . $id . ')成功');
            }else{
                UserLogService::HTwrite(3, '设置通道(' . $id . ')失败', '设置通道(' . $id . ')失败');
            }
            $this->ajaxReturn(['status'=>$reslut]);
        }
	}

	public function editDefault(){
		$id = I('post.id',0,'intval');
		$isopen = I('post.isopen',0,'intval');

		$pfa_model = D('PayForAnother');
		$pfa_model->startTrans();

		if($id && $isopen==1 )
			$reslut = $pfa_model->editAllDefault($id);
       	else
        	$reslut = $pfa_model->where(['id'=>$id])->save(['is_default'=>0]);
        

        if($reslut){
			$pfa_model->commit();
	        $this->ajaxReturn(['status'=>1]);
	    }else{
			$pfa_model->rollback();
			$this->ajaxReturn(['status'=>0]);
		}
	}

    /**
     * 扩展字段列表
     */
	public function extendFields(){
        $channel_id = I('get.id',0,'intval');
        $channel    = M('pay_for_another')->where(['id' => $channel_id])->find();
        $data   = M('pay_channel_extend_fields')->where(['channel_id' => $channel_id])->select();
        $this->assign('channel', $channel);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 编辑扩展字段
     */
    public function editExtendFields()
    {
        if(IS_POST) {
            $data = I('post.', '');
            if(!$data['name'] || !$data['alias']) {
                $this->ajaxReturn(['status' => 0, 'msg' => '扩展字段名和别名不能为空']);
            }
            $count = M('pay_channel_extend_fields')->where(array('name'=>$data['name'], 'channel_id' => $data['channel_id'], 'id'=>array('neq', $data['id'])))->count();
            if($count>0) {
                $this->ajaxReturn(['status' => 0, 'msg' => '该扩展字段名已存在']);
            }
            $res = M('pay_channel_extend_fields')->where(['id' => $data['id']])->save($data);
            if(FALSE !== $res) {
                $this->ajaxReturn(['status' => 1, 'msg'=>'编辑成功']);
            } else {
                $this->ajaxReturn(['status' => 0, 'msg'=>'编辑失败']);
            }
        } else {
            $id = I('id', 0, 'intval');
            if ($id) {
                $data = M('pay_channel_extend_fields')->where(['id' => $id])->find();
            }
            $this->assign('data', $data);
            $this->display('extendFieldsForm');
        }
    }

    /**
     * 新增扩展字段
     */
    public function addExtendFields()
    {
        if(IS_POST) {
            $data = I('post.', '');
            if(!$data['name'] || !$data['alias']) {
                $this->ajaxReturn(['status' => 0, 'msg' => '扩展字段名和别名不能为空']);
            }
            $count = M('pay_channel_extend_fields')->where(array('name'=>$data['name'], 'channel_id' => $data['channel_id']))->count();
            if($count>0) {
                $this->ajaxReturn(['status' => 0, 'msg' => '该扩展字段名已存在']);
            }
            $data['code']  = M('pay_for_another')->where(array('id' => $data['channel_id']))->getField('code');
            $data['ctime'] = $data['etime'] = time();
            $res = M('pay_channel_extend_fields')->add($data);
            if(FALSE !== $res) {
                $this->ajaxReturn(['status' => 1, 'msg'=>'添加成功']);
            } else {
                $this->ajaxReturn(['status' => 0, 'msg'=>'添加失败']);
            }
        } else {
            $id = I('id', 0, 'intval');
            $data['channel_id'] = $id;
            $this->assign('data', $data);
            $this->display('extendFieldsForm');
        }
    }

    //删除扩展字段
    public function delExtendFields()
    {
        $id = I('id', 0, 'intval');
        if ($id) {
            $res = M('pay_channel_extend_fields')->where(['id' => $id])->delete();
            $this->ajaxReturn(['status' => $res]);
        }
    }
    
    public function editControl(){
        $uid = session('admin_auth')['uid'];
        $verifysms = 0;//是否可以短信验证
        $sms_is_open = smsStatus();
        if ($sms_is_open) {
            $adminMobileBind = adminMobileBind($uid);
            if ($adminMobileBind) {
                $verifysms = 1;
            }
        }
        //是否可以谷歌安全码验证
        $verifyGoogle = adminGoogleBind($uid);
        if (IS_POST) {
            UserLogService::HTwrite(3, '保存通道提款设置', '保存通道提款设置');
            $id  = I('post.id', 0, 'intval') ? I('post.id', 0, 'intval') : 0;
            $tab = I('tab', 1, 'intval');

            $_rows           = I('post.u');
            $_rows['userid'] = 1;
            $_rows['systemxz'] = 2;
            $_rows['channel'] = $id;
            $auth_type = I('request.auth_type',0,'intval');
            if($verifyGoogle && $verifysms) {
                if(!in_array($auth_type,[0,1])) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！", 'tab' => $tab]);
                }
            } elseif($verifyGoogle && !$verifysms) {
                if($auth_type != 1) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！", 'tab' => $tab]);
                }
            } elseif(!$verifyGoogle && $verifysms) {
                if($auth_type != 0) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "参数错误！", 'tab' => $tab]);
                }
            }
            if ($verifyGoogle && $auth_type == 1) {//谷歌安全码验证
                $google_code   = I('request.google_code');
                if(!$google_code) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "谷歌安全码不能为空！", 'tab' => $tab]);
                } else {
                    $res = check_auth_error($uid, 5);
                    if(!$res['status']) {
                        $this->ajaxReturn(['status' => 0, 'msg' => $res['msg'], 'tab' => $tab]);
                    }
                    $ga = new \Org\Util\GoogleAuthenticator();
                    $google_secret_key = M('Admin')->where(['id'=>$uid])->getField('google_secret_key');
                    if(!$google_secret_key) {
                        $this->ajaxReturn(['status' => 0, 'msg' => "您未绑定谷歌身份验证器！", 'tab' => $tab]);
                    }
                    if(false === $ga->verifyCode($google_secret_key, $google_code, C('google_discrepancy'))) {
                        log_auth_error($uid,5);
                        $this->ajaxReturn(['status' => 0, 'msg' => "谷歌安全码错误！", 'tab' => $tab]);
                    } else {
                        clear_auth_error($uid,5);
                    }
                }
            } elseif($verifysms && $auth_type == 0) {//短信验证码
                $res = check_auth_error($uid, 3);
                if (!$res['status']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => $res['msg'], 'tab' => $tab]);
                }
                $code = I('request.code');
                if (!$code) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "短信验证码不能为空！", 'tab' => $tab]);
                } else {
                    if (session('send.tkconfig') != $code || !$this->checkSessionTime('tkconfig', $code)) {
                        log_auth_error($uid, 3);
                        $this->ajaxReturn(['status' => 0, 'msg' => '验证码错误', 'tab' => $tab]);
                    } else {
                        clear_auth_error($uid, 3);
                        session('send', null);
                    }
                }
            }
            $isEdit = M("Tikuanconfig")->where(['channel' => $id])->find();
            if ($isEdit) {
                $res = M("Tikuanconfig")->where(['channel' => $id])->save($_rows);
                UserLogService::HTwrite(3, '修改通道提款设置成功', '成功修改（' . $id . '）通道提款设置');
            } else {
                $res = M("Tikuanconfig")->add($_rows);
                UserLogService::HTwrite(3, '添加通道提款设置成功', '成功添加通道提款设置（' . $res . '）');

            }
            $this->ajaxReturn(['status' => $res,'tab' => $tab]);
        }else{
            $id = I('id', 1, 'intval');
            $configs = M("Tikuanconfig")->where("channel=" . $id)->find();
            $this->assign("configs", $configs);
            $this->assign('verifysms', $verifysms);
            $this->assign('verifyGoogle', $verifyGoogle);
            $this->assign('auth_type', $verifyGoogle ? 1 : 0);
            $this->display();
        }
    }
}
