<?php
namespace app\admin\controller;
use app\admin\tools\MenuTree;//引入树状类
class Privilege extends Common
{
    public function index()
    {
		$map    = [];
		$result = db('perm_menu')->where($map)->order('sort desc,id asc')->select();
		if($result){
			$tree = new MenuTree();
            $tree->setConfig('id', 'pid', '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;');
            $list = $tree->getLevelTreeArray($result);
		}
		return $this->fetch('index', [
			'list' => $list,
        ]);	
    }
	public function add()
	{	
		if(!empty($_POST)){
			if(empty($_POST['menu_name'])){
				$this->ajaxReturn(array('status'=>false,'msg'=>'请填写权限菜单名'));
			}
			if(empty($_POST['tag_name'])){
				$this->ajaxReturn(array('status'=>false,'msg'=>'请填写菜单名'));
			}
			$_POST['tag_name'] = strtolower($_POST['tag_name']);
			$_POST['reg_time'] = time();
			//判断图标
            $_POST['icon'] =$_POST['icon']?$_POST['icon']:'layui-icon-vercode';

			if(db('perm_menu')->insert($_POST)){
				$this->ajaxReturn(array('data'=>$_POST,'msg'=>'添加成功'));
			}else{
				$this->ajaxReturn(array('status'=>false,'msg'=>'添加失败'));
			}
		}
		//$result = $this -> getMenuList();
		return $this->fetch('add', [
			'result' => $this->treeList(),
        ]);	
	}
	
	public function adds (){
	 	if(!empty($_POST)){
			if(empty($_POST['menu_name'])){
				$this->ajaxReturn(array('status'=>false,'msg'=>'请填写权限菜单名'));
			}
			if(empty($_POST['test'])){
				$this->ajaxReturn(array('status'=>false,'msg'=>'请填写测试'));
			}
			
			if(empty($_POST['tag_name'])){
				$this->ajaxReturn(array('status'=>false,'msg'=>'请填写菜单名'));
			}
			$_POST['test'] =strtolower($_POST['test']);
			
			
			$_POST['tag_name'] =strtolower($_POST['tag_name']);
			$_POST['reg_time'] =time();
			$_POST['map'] = $_POST['map']?$_POST['map']:' ';
			$_POST['icon'] =$_POST['icon']?$_POST['icon']:'layui-icon-vercode';
			
			if(db('perm_menu')->insert($_POST)){
				$this->ajaxReturn(array('data'=>$_POST,'msg'=>'添加成功'));
			}else{
				$this->ajaxReturn(array('status'=>false,'msg'=>'添加失败'));
			}
		}
		
		return $this->fetch('add',[
			'result'=>$this->treeList()
		]);
		
	}
	
	//编辑
	public function edit()
	{
		$id = input('param.id');

		if(!empty($_POST['id'])){
			$id = $_POST['id'];

			if(empty($_POST['menu_name'])){
				$this->ajaxReturn(array('status'=>false,'msg'=>'请填写权限菜单名'));
			}
			if(empty($_POST['tag_name'])){
				$this->ajaxReturn(array('status'=>false,'msg'=>'请填写菜单名'));
			}

            if(empty($_POST['icon'])){
                //用之前的图标
                $data =db('perm_menu')->where('id',$id)->find();
                $old =$data['icon'];
                $_POST['icon'] =$old;
            }


			$result = db('perm_menu')->where('id',$id)->update($_POST);
			if($result){
				$this->ajaxReturn(array('status'=>true,'msg'=>'修改成功'));
			}else{
				$this->ajaxReturn(array('status'=>true,'msg'=>'未做任何修改'));
			}
		}
		$list = db('perm_menu')->where('id',$id)->find(); //查询出需要编辑的信息
//        halt($list);
		$result = $this -> getMenuList();//得出所有的栏目选项
		return $this->fetch('edit', [
			'list' => $list,
			'result' => $result,
        ]);	
	}
	
	/**
     * [获取树状菜单列表]
     * @date   2016-09-05T10:21:46+0800
     * @author dyp
     */
    public function treeList()
    {
        $map            = [];
        //格式化菜单
        $result = db('perm_menu')->where($map)->field('id,pid,tag_name,menu_name,sort')->order('sort desc,id asc')->select();
        if ($result) {
            $tree = new MenuTree();
            $tree->setConfig('id', 'pid');
            $list = $tree->getLevelTreeArray($result);
            if (isset($list) && $list) {
                foreach ($list as $key => $value) {
                    $list[$key]['htmlname'] = @$value['delimiter'] . $value['menu_name'];
                }
            }
        }
        return $list;
    }
	//权限列表循环删除
	public function del()
	{
		$id = $_POST['id'];
		$this->delMenuList("$id");
		$this ->ajaxReturn(array('msg'=>'删除成功'));
	}
}
