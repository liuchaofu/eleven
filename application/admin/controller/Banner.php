<?php

namespace app\admin\controller;


use think\Db;
use think\Request;

class Banner extends Common
{
    //显示类别
    public function banner_index()
    {
        $list = Db::name('nav_list')
            ->paginate(5);

        return $this->fetch('banner_index', [
            'list' => $list,
        ]);

    }

    //添加类别
    public function banner_add(Request $request)
    {
        if (!empty($_POST)) {
            $data = $request->post();

            if ($data) {
                if ($data['name'] == '') {
                    $this->ajaxReturn(array('status' => false, 'msg' => "分类名未填写！", 'result' => null));
                }
                if ($data['desc'] == '') {
                    $this->ajaxReturn(array('status' => false, 'msg' => "描述未填写！", 'result' => null));
                }
//                $data['sort'] =$data['sort']?$data['sort']:50;
                $res = Db::name('nav_list')->insert($data);
                if ($res) {
                    $this->ajaxReturn(array('msg' => '分类添加成功'));
                } else {
                    $this->ajaxReturn(array('status' => false, 'msg' => '信息有误请检查'));
                }

            }
        }
        return $this->fetch();
    }

    //修改的id
    public function banner_edit(Request $request, $id)
    {
        if (!empty($_POST)) {
            $all = $request->post();
            if ($all['name'] == '') {
                $this->ajaxReturn(array('status' => false, 'msg' => "分类名未填写！", 'result' => null));
            }
            if ($all['desc'] == '') {
                $this->ajaxReturn(array('status' => false, 'msg' => "描述未填写！", 'result' => null));
            }

            $data['name'] = $all['name'];
            $data['desc'] = $all['desc'];
            $data['sort'] = $all['sort'];
            $id = $all['id'];

            $res = Db::name('nav_list')->where('id', $id)->update($data);
            if ($res) {

                $this->ajaxReturn(array('msg' => '修改成功'));
            } else {

                $this->ajaxReturn(array('msg' => '未作修改'));
            }
        }
        //查询数据
        $data = Db::name('nav_list')->where('id', $id)->find();
        $this->assign('data', $data);
        return $this->fetch();

    }


    //删除
    public function banner_del(Request $request)
    {
        $id = $request->post('id');

        //删除
        $res = \db('nav_list')->where('id', $id)->delete();
        if ($res) {
            $this->ajaxReturn(array('msg' => '删除成功'));

        }
        $this->ajaxReturn(array('msg' => '删除失败'));

    }

    //查看分类下的图片
    public function banner_info(Request $request, $id)
    {

        //去找bannner图片
        $img = \db('banner')->where('cat_id', $id)->select();
        //轮播
        $Carousel = \db('banner')->where('cat_id', $id)->limit(0, 1)->select();

        $Carousels = \db('banner')->where('cat_id', $id)->limit(1, 2)->select();

        return $this->fetch('banner_info', [
            'list' => $img,
            'carousel' => $Carousel,
            'carousels' => $Carousels,
        ]);
    }

    //bennner 修改
    public function banner_imgedit(Request $request, $id)
    {

        if (!empty($_POST)) {
            $data = $request->post();
            $id = $data['id'];
//            if(empty($_POST['name'])){
//                $this->ajaxReturn(array('status'=>false,'msg'=>'请填写banner名'));
//            }
//            if(empty($_POST['desc'])){
//                $this->ajaxReturn(array('status'=>false,'msg'=>'请填写描述'));
//            }
            if (empty($data['url'])) {
                //用之前的图片
                $res = Db::name('banner')->where('id', $id)->find();

                $old = $res['url'];
                $data['url'] = $old;
            } else {
                //拼接
                $data['url'] = 'banner/' . $data['url'];
            }


            $result = Db::name('banner')->where('id', $id)->update($data);
            if ($result) {
                $this->ajaxReturn(array('msg' => '修改成功'));
            } else {
                $this->ajaxReturn(array('msg' => '管理员未做任何修改'));
            }
        }
        //原来的图片
        $pic = \db('banner')->where('id', $id)->find();
        $address = \db('nav_list')->select();

        return $this->fetch('banner_imgedit', [
            'data' => $pic,
            'address' => $address
        ]);

    }
}
