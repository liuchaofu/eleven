<?php
namespace app\admin\controller;

use app\admin\tools\MenuTree;
use think\Controller;
use think\Session;
use think\Db;
use think\Request;
use think\View;
class Common extends Controller 
{
	protected $title = 'eleven';
	protected $keywords = 'eleven';
	protected $description = 'eleven';
	public function __construct(){
		parent::__construct();
		session_start();
		View::share([
			'title'        => $this->title,
			'keywords'     => $this->keywords,
			'description'  => $this->description,
			'action'       => $this->request->action(),
        ]);
	}
	public function getMenuList($pid = 0,$cat_arr=array(),$level = 0){
		$result=db('perm_menu')->where("pid=$pid")->select();
		if(!empty($result)){
			foreach($result as $v){
			$v['level'] = $level;
			$level_str = '<font color="red">';
			for($i = 0;$i<$level;$i++){
				$level_str .= '|-';
			}
			$level_str .= '</font>';
			$v['menu_name'] = $level_str.$v['menu_name'];
			$cat_arr[] = $v;
			$pid = $v['id'];
			$cat_arr = $this->getMenuList($pid,$cat_arr,$level+1);
			}	
		}
		return $cat_arr;
	}
	public function delMenuList($id){
		$result=db('perm_menu')->where("pid=$id")->select();
		if(!empty($result)){
			foreach($result as $v){
				$pid = $v['id'];
				$cat_arr = $this->delMenuList($pid);
			}	
		}
		db('perm_menu')->where("id=$id")->delete();
	}
	public function getPriList($pid = 0,$cat_arr=array(),$level = 0,$i=-1){
	    //给角色赋权限的界面显示
		$result = db('perm_menu')->where("pid=$pid")->select();
		if(!empty($result)){
			foreach($result as $v){
				if($level==0){
					$i++;
					$cat_arr[$i]=$v;
				}else{
					$cat_arr[$i]['child'][]=$v;
				}
				$pid = $v['id'];
				$cat_arr = $this->getPriList($pid,$cat_arr,$level+1,$i);
			}	
		}else{
			if($level == 1){
				$cat_arr[$i]['child'] = array();
			}
		}
		return $cat_arr;
	}
	public function AllMenu($pid = 0,$cat_arr=array(),$level = 0,$i = -1){		//得出所有的菜单和二级菜单(level<2)
		if($level<2){
			$result=db('perm_menu')->where("pid=$pid")->order('sort desc,id asc')->select();
			if(!empty($result)){
				foreach($result as $v){
					if($level==0){
						$i++;
						$cat_arr[$i]=$v;
					}else{
						$cat_arr[$i]['child'][]=$v;
					}
				$pid = $v['id'];
				$cat_arr = $this->AllMenu($pid,$cat_arr,$level+1,$i);
				}	
			}else{
				if($level == 1){
					$cat_arr[$i]['child'] = array();
				}
			}
		}
		return $cat_arr;
	}
	//单图上传处理
	public function upload_img(){
		$data = input('post.');
		$group = $data['group']? $data['group']:'common';
		$size = $data['size']? $data['size']: 2;
		$file = $_FILES['file'];
		$maxsize = $size*1024*1024;
		$type_arr = ['jpg','gif','png','jpeg'];
		$ext = strtolower(trim(substr(strrchr($file['name'], "."), 1)));//获取后缀
		if(!in_array($ext,$type_arr)){
			$this->ajaxReturn(array('status'=>false,'data'=>$file,'msg'=>'图片格式错误'));
		}
		if($file['size'] > $maxsize){
			$this->ajaxReturn(array('status'=>false,'data'=>$file,'msg'=>'请上传小于'.$maxsize.'M的文件'));
		}
		//追加一层时间目录
		$date = date('Ymd');
		// 移动到框架应用根目录/public/uploads/ 目录下
		$filePath = ROOT_PATH . 'public' . DS . 'uploads'. DS . $group . DS . $date .DS;
		if(!is_dir($filePath)){
			mkdir($filePath, 0777, true);
			chmod($filePath, 0777);
		}
		$name = time() . rand(100, 999) . '.' . $ext;
		$info = move_uploaded_file($file['tmp_name'], $filePath . $name);
		$img_url = $group . DS . $date . DS .$name;
		if($info){
			$this->ajaxReturn(array('data'=>$img_url,'msg'=>'上传成功'));
		}else{
		// 上传失败
			$this->ajaxReturn(array('status'=>false,'data'=>$info,'msg'=>'图片信息获取失败'));
		}
	}
	
	public function up_file(){
		$data = input('post.');
		$group = $data['group']? $data['group']:'common';
		$size = $data['size']? $data['size']: 2;
		$file = $_FILES['file'];
		$maxsize = 2*1024*1024;
		$ext =  strtolower(trim(substr(strrchr($file['name'], "."), 1)));//获取后缀
		if($file['size'] > $maxsize){
			$this->ajaxReturn(array('status'=>false,'data'=>$file,'msg'=>'请上传小于'.$size.'M的文件'));
		}
		//追加一层时间目录
		$date = date('Ymd');
		// 移动到框架应用根目录/public/uploads/ 目录下
		$filePath = ROOT_PATH . 'public' . DS . 'uploads'. DS . $group . DS . $date .DS;
		if(!is_dir($filePath)){
			mkdir($filePath, 0777, true);
			chmod($filePath, 0777);
		}
		$original_name = str_replace(' ','_',$file['name']);
		$name = time() . rand(100, 999) . '.' . $ext;
		$info = move_uploaded_file($file['tmp_name'], $filePath . $name);
		$file_url = $group . DS . $date . DS .$name;
		if($info){
			$this->ajaxReturn(array('data'=>$file_url,'name'=>$name,'original_name'=>$original_name,'msg'=>'上传成功'));
		}else{
		// 上传失败
			$this->ajaxReturn(array('status'=>false,'data'=>$info,'msg'=>'视频信息获取失败'));
		}
	}
	//统一图片、视频、文件删除
	public function file_del(){
		$path = input('post.path/s','');
		if($path){
			$path = ROOT_PATH . $path;
			if(file_exists($path)){
				unlink($path);
			}
			$this->ajaxReturn(array('data'=>$path,'msg'=>'删除成功'));
		}
		$this->ajaxReturn(array('status'=>false,'msg'=>'参数错误'));
	}
	//通用单条删除
	public function del(){
		$field = input('post.field/s','');
		$value = input('post.value');
		$table = input('post.table/s','');;
		$img   = input('post.img/s','');
		$path  = '';
		if($img){
			$path = ROOT_PATH . $img;
		}
		$res = db($table)->where($field,$value)->delete();
		if($res){
			if(file_exists($path)){
				unlink($path);
			}
			$this->ajaxReturn(array('msg' => '删除成功'));
		}
		$this->ajaxReturn(array('status' => false,'msg' => '删除失败'));
	}
	//通用批量删除
	public function dels(){
		$ids = input('post.ids/s');
		$ids_arr = explode(',',$ids);
		$table = input('post.table/s');
		$field = input('post.field/s');
		$img_field = input('post.img_field/s','');
        $map[$field] = array('in',$ids_arr);
		//删除图片
		if($img_field){
			$data = db($table)->where($map)->column($img_field);
			foreach($data as $key=>$val){
				$path = ROOT_PATH .'public/uploads/'. $val;
				if(file_exists($path)){
					unlink($path);
				}
			}
		}
		//删除数据库数据
		$res = db($table)->where($map)->delete();
		if($res){
			$this->ajaxReturn(array('msg' => '删除成功'));
		}
		$this->ajaxReturn(array('status' => false,'msg' => '删除失败'));
	}
	//通用改变某表某字段值
	public function changeField(){
		$key   = input('param.key',0);//表主键
        $table = input('param.table','');
        $field = input('param.field', 'sort');//需要修改字段
        $value = input('param.value');//修改字段值
        $primary_key = db($table)->getPk();// 获取主键
		$data[$field] = $value;
		$res = db($table)->where($primary_key,$key)->update($data);
		if ($res === false) {
			$this->ajaxReturn(array('status' => false,'data'=>$res,'msg' => '更改失败'));
		}
		$this->ajaxReturn(array('msg' => '更改成功'));
	}
	/** json返回ajax数据 */
    protected function ajaxReturn($value, $lg = 'zh')
    {
        header("Content-Type:application/json; charset=utf-8");
        $array = array(
            'status' => true,
            'data'   => [],
            'msg'    => '获取数据成功',
        );
        $value = array_merge($array, (array)$value);
        exit(json_encode($value,320));
    }
	/*
	 * @date   2018-12-20T15:39:03+0800
     * @author dyp
     * @param  array  $data //需要处理的分页数据
	 * @tp自带分页,查询数据处理
	 */
	function _list($data=[]){
        //分页
        $result['pages']= $data->render();
        //数据
        $result['list']= $data->toArray()['data'];
        return $result;
    }
    /**
     * 构建层级（树状）数组
     * @param array  $array          要进行处理的一维数组，经过该函数处理后，该数组自动转为树状数组
     * @param string $pid_name       父级ID的字段名
     * @param string $child_key_name 子元素键名
     * @return array|bool
     */
    function array2tree(&$array, $pid_name = 'pid', $child_key_name = 'children')
    {
        $counter = $this->array_children_count($array, $pid_name);
        if (!isset($counter[0]) || $counter[0] == 0) {
            return $array;
        }
        $tree = [];
        while (isset($counter[0]) && $counter[0] > 0) {
            $temp = array_shift($array);
            if (isset($counter[$temp['id']]) && $counter[$temp['id']] > 0) {
                array_push($array, $temp);
            } else {
                if ($temp[$pid_name] == 0) {
                    $tree[] = $temp;
                } else {
                    $array = $this->array_child_append($array, $temp[$pid_name], $temp, $child_key_name);
                }
            }
            $counter = $this->array_children_count($array, $pid_name);
        }
        return $tree;
    }
    /**
     * 把元素插入到对应的父元素$child_key_name字段
     * @param        $parent
     * @param        $pid
     * @param        $child
     * @param string $child_key_name 子元素键名
     * @return mixed
     */
    function array_child_append($parent, $pid, $child, $child_key_name)
    {
        foreach ($parent as &$item) {
            if ($item['id'] == $pid) {
                if (!isset($item[$child_key_name])) {
                    $item[$child_key_name] = [];
                }

                $item[$child_key_name][] = $child;
            }
        }
        return $parent;
    }
	
    /**
     * 子元素计数器
     * @param array $array
     * @param int   $pid
     * @return array
     */
    function array_children_count($array, $pid)
    {
        $counter = [];
        foreach ($array as $item) {
            $count = isset($counter[$item[$pid]]) ? $counter[$item[$pid]] : 0;
            $count++;
            $counter[$item[$pid]] = $count;
        }
        return $counter;
    }
}
