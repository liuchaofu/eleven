<?php
namespace app\admin\controller;

class Role extends Common 
{
	public function index()
    {
		$result = db('admin_role')->order('role_id asc')->paginate(10);//5条数据分页
		return $this->fetch('',[
            'result' => $result,
        ]);
    }
	public function add(){
		if(!empty($_POST)){
			if(empty($_POST['role_name'])){
				$this->ajaxReturn(array('status'=>false,'msg'=>'请填写角色名'));
			}
			if(empty($_POST['act_list'])){//角色未赋予权限
				$this -> ajaxReturn(array('status'=>false,'msg'=>'请为该角色赋予权限'));
			}else{
				$_POST['act_list'] = json_encode($_POST['act_list']);//将权限数组转成json格式
			}
			$_POST['add_time'] = time();
			if(db('admin_role')->insert($_POST)){
				$this->ajaxReturn(array('msg'=>'添加成功'));
			}else{
				$this->ajaxReturn(array('status'=>false,'msg'=>'添加失败'));
			}
		}
		$result = $this -> getPriList();
		return $this->fetch('add',[
            'list' => $result,
        ]);
	}
	public function edit(){
		$id = input('param.id');
		$result = db('admin_role')->where('role_id',$id)->find();
		$result['act_list'] = json_decode($result['act_list']);
		$list = $this -> getPriList();
		if(!empty($_POST)){
			if(empty($_POST['role_name'])){
				$this->ajaxReturn(array('status'=>false,'msg'=>'请填写角色名'));
			}
			if(empty($_POST['act_list'])){//角色未赋予权限
				$this -> ajaxReturn(array('status'=>false,'msg'=>'请为该角色赋予权限'));
			}else{
				$_POST['act_list'] = json_encode($_POST['act_list']);//将权限数组转成json格式
			}
			$result = db('admin_role')->where('role_id',$_POST['role_id'])->update($_POST);
			if($result){
				$this->ajaxReturn(array('msg'=>'编辑成功'));
			}else{
				$this->ajaxReturn(array('msg'=>'未做任何修改'));
			}
		}
		return $this->fetch('edit',[
            'result' => $result,
			'list' => $list,
        ]);
	}
}
