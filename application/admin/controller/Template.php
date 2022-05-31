<?php

namespace app\admin\controller;

use think\Request;

class Template extends Common
{
    //展示模板
    public function template_index()
    {
        $list =db('template_message')
            ->order('add_time desc')
            ->select();
//            ->paginate(5);
        return $this->fetch('',[
            'list'=>$list,
        ]);
    }
    //配置模板
    public function template_add()
    {
        if (!empty($_POST)) {
            $data =$_POST;

            //判断
            if(!$data['content']){
                $this->ajaxReturn(['status'=>false,'msg'=>'请填写模板内容']);
            }
            $data['add_time'] =time();
            $res =db('template_message')->insert($data);
            if ($res) {
                $this->ajaxReturn(array('status'=>true,'msg'=>'添加成功'));
            }
            $this->ajaxReturn(['status'=>false,'msg'=>'添加失败']);

        }
        return $this->fetch('template_add',[

        ]);

    }


    //編輯模板
    public function template_edit(Request $request ,$id)
    {
        if (!empty($_POST)){
            $data =$_POST;
            if(!$data['content']){
                $this->ajaxReturn(['status'=>false,'msg'=>'请填写模板内容']);
            }
            $res =db('template_message')->where('id',$id)->update($data);
            if ($res) {

                $this->ajaxReturn(array('msg'=>'修改成功'));
            }else{

                $this->ajaxReturn(array('msg'=>'未作修改'));
            }
        }
        //回显
        $data =db('template_message')->where('id',$id)->find();
        return $this->fetch('template_edit',[
            'data'=>$data,
            'id'=>$id,
        ]);

    }
}
