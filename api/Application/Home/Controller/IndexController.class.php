<?php
namespace Home\Controller;
use Think\Controller;
class IndexController extends Controller {
    public function index(){
        $this->show('请使用正确的接口地址！','utf-8');
    }
}