<?php
namespace app\admin\controller;

class Admin extends Common
{
    public function index()
    {
		$result = db('admin')->order('admin_id desc')->paginate(10);
		return $this->fetch('index',[
            'result' => $result,
        ]);
    }
	public function add()
    {
		if(!empty($_POST)){
			if(empty($_POST['username'])){
				$this->ajaxReturn(array('status'=>false,'msg'=>'请填写管理员名'));
			}
			$count = count(db('admin')->where('username',$_POST['username'])->select());
			if($count>0){
				$this->ajaxReturn(array('status'=>false,'msg'=>'该管理员已存在'));
			}
			if(!empty($_POST['email'])){
				if(!check_email($_POST['email'])){//该方法位于tp助手函数文件
					$this->ajaxReturn(array('status'=>false,'msg'=>'邮箱格式有误'));
				}
			}
			if($_POST['password']!= $_POST['confirm_password']){
				$this->ajaxReturn(array('status'=>false,'msg'=>'两次输入密码不一致'));
			}
			unset($_POST['confirm_password']);
			if($_POST['role_id'] == 0){
				$this->ajaxReturn(array('status'=>false,'msg'=>'请为管理员设置角色'));
			}
			$id = $_POST['role_id']; //利用角色id查询角色名;
			$result = db('admin_role')->where("role_id",$id)->find();
			$_POST['role_name'] = $result['role_name'];
			$_POST['add_time'] = time();
			$_POST['password'] = md5($_POST['password']);
			if(db('admin')->insert($_POST)){
				$this->ajaxReturn(array('msg'=>'管理员添加成功'));
			}else{
				$this->ajaxReturn(array('status'=>false,'msg'=>'管理员添加失败'));
			}
		}
		$role = db('admin_role')->order('role_id desc')->select();
		return $this->fetch('add',[
            'role' => $role,
        ]);
    }
	
	public function edit()
	{
		$id = input('param.id');
		$result = db('admin')->where('admin_id',$id)->find();
		$role = db('admin_role')->order('role_id desc')->select();
		if(!empty($_POST)){
			if(empty($_POST['username'])){
				$this->ajaxReturn(array('status'=>false,'msg'=>'请填写管理员名'));
			}
			if($_POST['password']){
				$_POST['password'] = md5($_POST['password']);
			}
			if($_POST['role_id'] == 0){
				$this->ajaxReturn(array('status'=>false,'msg'=>'请为管理员设置角色'));
			}
			$id = $_POST['role_id']; //利用角色id查询角色名;
			$res = db('admin_role')->where("role_id=$id")->find();
			$_POST['role_name'] = $res['role_name'];
			$result = db('admin')->where('admin_id',$_POST["admin_id"])->update($_POST);
			if($result){
				$this->ajaxReturn(array('msg'=>'管理员修改成功'));
			}else{
				$this->ajaxReturn(array('msg'=>'管理员未做任何修改'));
			}
		}
		return $this->fetch('edit',[
            'result' => $result,
			'role'   => $role,
        ]);
	}
}
