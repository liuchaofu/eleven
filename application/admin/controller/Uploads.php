<?php

namespace app\admin\controller;

use think\Db;
use think\Request;

class Uploads extends Common
{
    /*
  * bootst多图上传
  */
    public function fileinput()
    {
        //查出分类

        $cates = Db::name('nav_list')->select();
        $this->assign('cates', $cates);
        return $this->fetch();
    }

//接收图片
    public function uploadImg()
    {

        // 获取表单上传文件
        $category = input("category");

        $file = request()->file('images');
        // 移动到框架应用根目录/public/uploads/banner 目录下
        $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . $category);

        if ($info) {
            // 成功上传后 获取上传信息
            $data['response'] = $info->getSaveName();

//            $res =Db::name('banner')->insertAll($result);
            $res = '1';
            if ($res) {
                return json_encode(array('res' => $data['response'], 'category' => $category));
            }
            //图片上传成功，以下可对数据库操作
            // ......
        } else {
            // 上传失败获取错误信息
            echo $file->getError();
        }

    }

    //添加图片
    public function add_img(Request $request)
    {
        $url = $request->post('img');
        $cat_id = $request->post('cat_id');
        $arr = array('url', $url, $cat_id);
        $data['url'] = 'banner/' . $arr['1'];
        $data['cat_id'] = $arr['2'];
        $data['is_show'] = 1;
        $data['reg_time'] = time();
        $res = Db::name('banner')->insert($data);
        if ($res) {
            $this->ajaxReturn(array('msg' => '添加成功'));
        }


    }

    //展示图片
    public function index()
    {
        $data = Db::name('banner')
            ->where('is_show', 1)
            ->order('reg_time desc')
            ->paginate('5');

        $this->assign('data', $data);
        return $this->fetch();
    }

    //修改
    public function uploads_edit(Request $request, $id)
    {

        if (!empty($_POST)) {
            $data = $request->post();
            $id = $data['id'];
            if (empty($_POST['name'])) {
                $this->ajaxReturn(array('status' => false, 'msg' => '请填写banner名'));
            }
            if (empty($_POST['desc'])) {
                $this->ajaxReturn(array('status' => false, 'msg' => '请填写描述'));
            }
            if (empty($data['url'])) {
                //用之前的图片
                $res = Db::name('banner')->where('id', $id)->find();

                $old = $res['url'];
                $data['url'] = $old;
            } else {
                $data['url'] = 'banner/' . $data['url'];
            }


            $result = Db::name('banner')->where('id', $id)->update($data);
            if ($result) {
                $this->ajaxReturn(array('msg' => '修改成功'));
            } else {
                $this->ajaxReturn(array('msg' => '管理员未做任何修改'));
            }
        }

        //查出个人信息
        $data = \db('banner')->where('id', $id)->find();
        //查出类别
        $address = \db('nav_list')->select();


        return $this->fetch('uploads_edit', [
            'data' => $data,
            'address' => $address
        ]);

    }


    //修改上传图片
    public function upload(Request $request)
    {
        //接收
        $file = $request->file('file');
        $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads/banner');
        // 成功上传后 获取上传信息
        $url = $info->getSaveName();

        return json($url);

    }

    //删除
    public function uploads_del(Request $request, $id)
    {
        //删除之前的图片
        $url = Db::name('banner')->where('id', $id)->find();
        $path = './public/uploads/banner/' . $url['url'];

        unlink($path);
        $res = Db::name('banner')->where('id', $id)->delete();
        if ($res) {
            $this->ajaxReturn(array('status' => 1, 'msg' => "删除成功！", 'result' => null));
        }
        $this->ajaxReturn(array('status' => -1, 'msg' => "删除失败！", 'result' => null));
    }
}
