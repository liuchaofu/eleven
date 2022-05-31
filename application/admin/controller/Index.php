<?php
namespace app\admin\controller;
use think\Session;
use think\Request;
class Index extends Common 
{
    public function index()
    {	
		if(empty(Session::get('admin_id'))){
			$this->redirect('login/login');
		}else{
			$id = $_SESSION['admin_id'];
			$find = db('admin')->where("admin_id",$id)->find();//查询所登录管理员的信息
			$role_id = $find['role_id'];
			$role = db('admin_role')->where('role_id',$role_id)->find();
			$find['role_name'] = $role['role_name'];
			$_SESSION['perm_arr'] = json_decode($role['act_list']);//查询管理员的角色权限数组
			$menu_arr = $this -> AllMenu(); //得出所有菜单列表
//            halt($menu_arr);
			//print_r($menu_arr);exit;
			return view('/index/index',[
				'menu_arr' => $menu_arr,
				'find' => $find, 
			]);
		}
    }
}
