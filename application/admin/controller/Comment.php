<?php

namespace app\admin\controller;

use think\Request;

class Comment extends Common
{
    //商品评论显示
    public function index(Request $request)
    {
        //条件
        $where = [];
        $data = $request->get();

        //当前时间的前30天
        $startDay = date('Y-m-d H:i:s', strtotime("-30 day"));
        $endDay = date('Y-m-d H:i:s', time());


        if (!empty($data['began'])) {
            $began = $data['began'];
        } else {
            $began = $startDay;
        }

        if (!empty($data['end'])) {
            $end = $data['end'];
        } else {
            $end = $endDay;
        }


        if (!empty($data['comment_user'])) {
            $user = $data['comment_user'];
            $where['nickname'] = ['like', "%$user%"];
        } else {
            $user = '';
        }
        if (!empty($data['comment_goods'])) {
            $goods = $data['comment_goods'];
            $where['goods_name'] = ['like', "%$goods%"];
        } else {
            $goods = '';
        }

        //商品表
        $list = db('comment')
            ->alias('c')
            ->join('users u', 'c.user_id = u.user_id')
            ->join('goods o', 'c.goods_id = o.goods_id')
            ->field('c.*,u.nickname,o.goods_name')
            ->where($where)
            ->whereTime('add_time', 'between', [$began, $end])
            ->order('add_time desc')
            ->paginate(5, false, array('query' => $data));

        return $this->fetch('comment/index',
            [
                'list' => $list,
                'comment_user' => $user,
                'comment_goods' => $goods,
                'began' => $began,
                'end' => $end
            ]);
    }

    //查看单条的评论
    public function comment_more(Request $request, $comment_id)
    {
        //查出单条
        $list = db('comment')->where('comment_id', $comment_id)->find();
        return $this->fetch('comment_more', [
            'list' => $list,
        ]);

    }

    //禁用
    public function ban_show(Request $request)
    {
        $id = $request->post('id');
        $status = $request->post('status');

        if ($status == 0) {
            $where['is_show'] = 1;
        } else {
            $where['is_show'] = 0;
        }
        //禁用
        $res = db('comment')->where('comment_id', $id)->update($where);

        if ($res) {
            exit(json_encode(array('status' => 1, 'res' => "操作成功！")));
        }
        exit(json_encode(array('status' => -1, 'res' => "操作失败！")));

    }

    //开启
    public function open_show(Request $request)
    {
        $id = $request->post('id');

        //禁用
        $res = db('comment')->where('comment_id', $id)->update(array('is_show' => '0'));
        if ($res) {
            exit(json_encode(array('status' => 1, 'res' => "开启成功！")));
        }
        exit(json_encode(array('status' => -1, 'res' => "开启失败！")));
    }

    //评论编辑
    public function comment_edit(Request $request, $comment_id)
    {

        if (!empty($_POST)) {
            $data = $request->post();
            $comment_id = $data['comment_id'];
            if (empty($data['content'])) {
                $this->ajaxReturn(array('status' => false, 'msg' => '请填写评论'));
            }
            $result = db('comment')->where('comment_id', $comment_id)->update($data);
            if ($result) {
                $this->ajaxReturn(array('msg' => '修改成功'));
            } else {
                $this->ajaxReturn(array('msg' => '管理员未做任何修改'));
            }
        }
        $msg = db('comment')->where('comment_id', $comment_id)->find();
        return $this->fetch('comment_edit', [
            'comment_id' => $comment_id,
            'msg' => $msg,
        ]);

    }

    public function show(Request $request)
    {
        $data =$request->post();
        $id =$data['id'];
        halt($data);
        $test =db('user')->where('id',$id)->find();
        $change =$data['color'];

    }

}
