<?php

namespace app\admin\controller;

use app\admin\tools\MenuTree;

//品牌管理
class Brand extends Common
{
    //品牌列表
    public function index()
    {
		$param['keyword'] = trim(input('post.keyword',''));
		$map = array();
		if($param['keyword']){
			$map['name'] = array('like', '%' . $param['keyword'] . '%');
		}
        $list = db('brand')->where($map)->order('sort desc id asc')->paginate(10);
        $result = $this->_list($list);
        foreach ($result['list'] as $key => $val) {
            $result['list'][$key]['cate_name'] = db('goods_category')->where('id', $val['cat_id'])->value('name');
        }
        return $this->fetch('index', [
            'pages' => $result['pages'],//分页
            'list' => $result['list'],//品牌数据
			'param' => $param,
        ]);
    }

    //品牌添加
    public function add()
    {
        if (!empty($_POST)) {
            if (empty($_POST['name'])) {
                $this->ajaxReturn(['status' => false, 'msg' => '请填写品牌名']);
            }
            if (empty($_POST['cat_id'])) {
                $this->ajaxReturn(['status' => false, 'msg' => '请选择品牌分类']);
            }
            if (empty($_POST['logo'])) {
                $this->ajaxReturn(['status' => false, 'msg' => '请上传logo图片']);
            }
            $_POST['add_time'] = time();
            $res = db('brand')->insert($_POST);
            if (!$res) {
                $this->ajaxReturn(['status' => false, 'msg' => '添加失败']);
            }
            $this->ajaxReturn(['msg' => '添加成功']);
        }
        return $this->fetch('add', [
            'cates' => $this->treeList(),//商品分类
        ]);
    }

    //品牌编辑
    public function edit()
    {
        $id = input('param.id');
        $data = db('brand')->where('id', $id)->find();
        if (!empty($_POST)) {
            if (empty($_POST['name'])) {
                $this->ajaxReturn(['status' => false, 'msg' => '请填写品牌名']);
            }
            if (empty($_POST['cat_id'])) {
                $this->ajaxReturn(['status' => false, 'msg' => '请选择品牌分类']);
            }
            $res = db('brand')->where('id', $id)->update($_POST);
            if ($res === false) {
                $this->ajaxReturn(['msg' => '品牌修改失败']);
            }
            $this->ajaxReturn(['msg' => '品牌修改成功']);
        }
        return $this->fetch('edit', [
            'data' => $data,
            'cates' => $this->treeList(),//商品分类
        ]);
    }

    /**
     * [获取树状菜单列表]
     * @date   2016-09-05T10:21:46+0800
     * @author dyp
     */
    public function treeList()
    {
        $map = [];
        $map['is_show'] = 1;
        //格式化菜单
        $result = db('goods_category')->where($map)->field('id,pid,name,sort,image')->order('sort asc')->select();
        if ($result) {
            $tree = new MenuTree();
            $tree->setConfig('id', 'pid');
            $list = $tree->getLevelTreeArray($result);
            if (isset($list) && $list) {
                foreach ($list as $key => $value) {
                    $list[$key]['htmlname'] = @$value['delimiter'] . $value['name'];
                }
            }
        }
        return $list;
    }
}
