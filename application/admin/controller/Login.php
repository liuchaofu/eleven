<?php
namespace app\admin\controller;
use think\Session;
class Login extends Common 
{
    public function login()
    {	
		if(!empty($_POST)){
			if(!empty($_POST['captcha'])){
				if(!captcha_check($_POST['captcha'])){//验证失败
					$this->ajaxReturn(array('status'=>false,'msg'=>'验证码输入错误'));
				}else{//验证成功
					if(empty($_POST['name'])||empty($_POST['password'])){
						$this->ajaxReturn(array('status'=>false,'msg'=>'用户名或密码不能为空'));
					}else{//密码验证
						$name = $_POST['name'];
						if($check = db('admin')->where('username',$name)->find()){//查询到该用户名
							if($check['password'] == md5($_POST['password'])){
								Session::set('admin_id',$check['admin_id']);
								$_SESSION['admin_id'] = $check['admin_id'];
								$this->ajaxReturn(array('msg'=>'登录成功'));
							}
							else{
								$this->ajaxReturn(array('status'=>false,'msg'=>'密码错误'));
							}
						}
						else{
							$this->ajaxReturn(array('status'=>false,'msg'=>'sorry,该用户名不存在'));
						}
					}
				}
			}else{
				$this->ajaxReturn(array('status'=>false,'msg'=>'请输入验证码'));
			}
		}
		return $this -> fetch();
    }
	public function logout(){
		Session::clear();
		unset($_SESSION['admin_id']);
		unset($_SESSION['perm_arr']);
		$this->ajaxReturn(array('msg'=>'成功退出'));
	}
}
