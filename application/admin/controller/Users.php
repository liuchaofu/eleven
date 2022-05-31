<?php

namespace app\admin\controller;

use think\Controller;
use think\Db;
use think\Request;
use think\Session;

class Users extends Common
{
    //个人信息
    public function users_index()
    {
        $list =Db::name('users')
            ->order('user_id desc')
            ->paginate(5);

        return $this->fetch('users_index',[
            'list'=>$list,
        ]);

    }
    //添加
    public function users_add(Request $request)
    {
        $data =$request->post();
        if($data){
            if(empty($data['email'])){
                $this->ajaxReturn(array('status'=>false,'msg'=>'请填写邮箱'));
            }
            if(empty($data['nickname'])){
                $this->ajaxReturn(array('status'=>false,'msg'=>'请填写昵称'));
            }

            if($data['password'] == ''){
                $this->ajaxReturn(array('status'=>false,'msg'=>'请填写密码'));
            }
            if($data['birthday'] == ''){
                $this->ajaxReturn(array('status'=>false,'msg'=>'请选择生日'));
            }
            if($data['user_money'] == ''){
                $this->ajaxReturn(array('status'=>false,'msg'=>'请填写金额'));
            }
            if($data['qq'] == ''){
                $this->ajaxReturn(array('status'=>false,'msg'=>'请填写qq'));
            }
            if($data['mobile'] == ''){
                $this->ajaxReturn(array('status'=>false,'msg'=>'请填写手机'));
            }
            if($data['sex'] == ''){
                $this->ajaxReturn(array('status'=>false,'msg'=>'请选择性别'));
            }

            if($data['head_pic'] == ''){
                $this->ajaxReturn(array('status'=>false,'msg'=>'请上传头像'));
            }
            $data['password'] = md5($data['password']);
            $data['birthday'] = strtotime($data['birthday']) ;
            //拼接頭像
            $data['head_pic'] ='users/'.$data['head_pic'];

            $data = Db::name('users')->insert($data);
            if ($data) {
//                exit(json_encode(array('status'=>1,'res'=>"添加成功！",'result'=>"添加成功")));
                $this->ajaxReturn(array('msg'=>'添加成功'));
            }
                $this->ajaxReturn(array('status'=>false,'msg'=>'添加失败'));
        }
        return $this->fetch();
    }

    //上传
    public function upload(Request $request)
    {
        //接收
        $file = $request->file('file');
        $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads/users');
        // 成功上传后 获取上传信息
        $url=$info->getSaveName();

        return json($url);

    }

    //查看
    public function user_more(Request $request,$id)
    {
        //全部地址
        $user_info =Db::name('users')->where('user_id',$id)->find();
        //默认地址id
        $default_id =$user_info['address_id'];

//        默认地址
        $default_address =Db::name('user_address')->where('address_id',$default_id)->find();
//        其余地址
//        $count_address =Db::name('user_address')->where('user_id',$id)->where('is_default',0)->count();
//        if ($count_address > 1) {
//            $else_address =Db::name('user_address')->where('user_id',$id)->where('is_default',0)->select();
//        }else{
//            $else_address =Db::name('user_address')->where('user_id',$id)->where('is_default',0)->find();
//        }
        //查出全部地址去前端判断
        $list =Db::name('user_address')->where('user_id',$id)->select();

        return $this->fetch('user_more',[
           'list'=>$list ,
        ]);

    }

    //删除用户
    public function user_del(Request $request)
    {
        $id =$request->post('id');
        //删除用户表
        $res =Db::name('users')->where('user_id',$id)->delete();
        //删除地址表
        $address =Db::name('user_address')->where('user_id',$id)->delete();
        if ($res && $address) {
            exit(json_encode(array('status'=>1,'res'=>"删除成功！")));
        }
        exit(json_encode(array('status'=>-1,'res'=>"删除失败！")));
    }
    //禁用用户or冻结
    public function user_ban(Request $request)
    {
        $id =$request->post('id');
        //禁用
        $res =Db::name('users')->where('user_id',$id)->update(array('is_lock'=>'1'));
        if ($res) {
            exit(json_encode(array('status'=>1,'res'=>"禁用成功！")));
        }
        exit(json_encode(array('status'=>-1,'res'=>"禁用失败！")));

    }

    //开启用户
    public function user_open(Request $request)
    {
        $id =$request->post('id');
//        halt($id);
        //禁用
        $res =Db::name('users')->where('user_id',$id)->update(array('is_lock'=>'0'));
        if ($res) {
            exit(json_encode(array('status'=>1,'res'=>"开启成功！")));
        }
        exit(json_encode(array('status'=>-1,'res'=>"开启失败！")));

    }

    //编辑用户
    public function user_edit(Request $request,$user_id)
    {

        if(!empty($_POST)){
            $data =$request->post();
            $user_id =$data['user_id'];

            if(empty($_POST['email'])){
                $this->ajaxReturn(array('status'=>false,'msg'=>'请填写邮箱'));
            }
            if(empty($_POST['nickname'])){
                $this->ajaxReturn(array('status'=>false,'msg'=>'请填写昵称'));
            }

            if($_POST['password'] == ''){
                $this->ajaxReturn(array('status'=>false,'msg'=>'请填写密码'));
            }
            if($_POST['birthday'] == ''){
                $this->ajaxReturn(array('status'=>false,'msg'=>'请选择生日'));
            }
            if($_POST['user_money'] == ''){
                $this->ajaxReturn(array('status'=>false,'msg'=>'请填写金额'));
            }
            if($_POST['qq'] == ''){
                $this->ajaxReturn(array('status'=>false,'msg'=>'请填写qq'));
            }
            if($_POST['mobile'] == ''){
                $this->ajaxReturn(array('status'=>false,'msg'=>'请填写手机'));
            }
            if($_POST['head_pic'] == ''){
               //用之前的头像
              $res =Db::name('users')->where('user_id',$user_id)->find();
              $old =$res['head_pic'];
              $data['head_pic']   =$old;

            }
            $data['birthday'] = strtotime($data['birthday']) ;
            $data['head_pic'] ='users/'. $data['head_pic'];

            $data['password'] = md5($data['password']);

            $result =Db::name('users')->where('user_id',$user_id)->update($data);
            if($result){
                $this->ajaxReturn(array('msg'=>'修改成功'));
            }else{
                $this->ajaxReturn(array('msg'=>'管理员未做任何修改'));
            }
        }

        //查出个人信息
        $data =\db('users')->where('user_id',$user_id)->find();

        $times = date('Y-m-d ',$data['birthday']);

        return $this->fetch('users_edit',[
            'data'=>$data,
            'times'=>$times
        ]);

    }

    //获取会员
    public function send_vip()
    {
        $str = input('post.ids');
//        dump($str);
        // 第一次分割字符串
        $arr1 = explode('|', $str);

        foreach ($arr1 as $key => $value) {
            // 第二次分割字符串 组成新的数据
            $arr2 = explode(',', $value);
            $result[$key]['id'] = $arr2[0];
            $result[$key]['name'] = $arr2[1];
        }

        $this->ajaxReturn(['status'=>1,'data'=>$result,'msg'=>'成功']);

    }


    function arr2str ($arr)
    {
        foreach ($arr as $v)
        {
            $v = join(",",$v); //可以用implode将一维数组转换为用逗号连接的字符串
            $temp[] = $v;
        }
        $t="";
        foreach($temp as $v){
            $t.="'".$v."'".",";
        }
        $t=substr($t,0,-1);
        return $t;
    }


    //发送信息給用户
    public function send()
    {
        if ($_POST) {
            $data =$_POST;


            if(empty($data['content'])){
                $this->ajaxReturn(['status'=>false,'msg'=>'请填写内容']);
            }
            $msg['add_time'] =time();
            //管理员
            $msg['admin_id'] =Session::get('admin_id');
            //默认暂时只有系统消息
            $msg['category'] =0;
            $msg['is_all'] =$data['is_all'];
            $msg['content'] =$data['content'];
            $msg['title'] =$data['title'];
            $msg['img'] =$data['img'];


            //判断全部还是选择了其中几个
            //多个的
            if($data['user']['0'] != ''){
                //整合结果 改名
                $arr = array();
                $i = 0;
                foreach ($data['user'] as $k =>$v) {
                    $arr[$i]['user_id'] = $v;
                    $i ++;
                }

                $uid =$this->arr2str($arr);
                $msg['user_id'] =$uid;


                //添加
                $message_id =\db('send_message')->insertGetId($msg);

                //重新组成数据
                $user = array();
                $i = 0;
                foreach ($data['user'] as $k =>$v) {

                    $user[$i]['user_id'] = $v;
                    //用戶
                    $user[$i]['message_id'] =$message_id;
                    $user[$i]['category'] =0;//默认
                    $user[$i]['is_see'] =0;//默认
                    $user[$i]['deleted'] =0;//默认
                    $user[$i]['add_time'] =time();
                    $i ++;
                }

                //插入到用户信息表
                $res =\db('user_message')->insertAll($user);
            }else{
                //全部的只进去后台就好
                $res =\db('send_message')->insert($msg);
            }

            if ($res) {
                $this->ajaxReturn(['status'=>true,'msg'=>'发送成功']);
            }
            $this->ajaxReturn(['status'=>false,'msg'=>'发送失败']);
        }

        //查询模板消息
        $data =\db('template_message')->select();
//        halt($data);
        return $this->fetch('send',[
            'data'=>$data,
        ]);
    }


}
