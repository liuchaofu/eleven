<?php

namespace app\admin\controller;

use think\Controller;
use think\Request;

class Designer extends Common
{
    //显示
    public function designer_index(Request $request)
    {
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

        if (!empty($data['began'])) {
            $end = $data['end'];
        } else {
            $end = $endDay;
        }

        if (!empty($data['something'])) {
            $something = $data['something'];
            $data = db('join_apply')
                ->alias('j')
                ->join('users u', 'j.user_id = u.user_id')
                ->whereor("brand", "like", "%{$something}%")
                ->whereor("u.nickname", "like", "%{$something}%")
                ->whereor("contact_name", "like", "%{$something}%")
                ->whereor("j.mobile", "like", "%{$something}%")
                ->whereTime('j.reg_time', 'between', [$began, $end])
                ->field('j.*,u.nickname,u.head_pic,u.oauth')
                ->order('reg_time desc')
                ->paginate(5, false, array('query' => $data));

        } else {
            $something = '';
            $data = db('join_apply')
                ->alias('j')
                ->join('users u', 'j.user_id = u.user_id')
                ->where($where)
                ->whereTime('j.reg_time', 'between', [$began, $end])
                ->field('j.*,u.nickname,u.head_pic,u.oauth')
                ->order('reg_time desc')
                ->paginate(5, false, array('query' => $data));
        }

        //转为数组
        $lists = $data->items();
        foreach ($lists as $k => $v) {
            $lists[$k]['images'] = json_decode($data[$k]['images'], true);
        }

        return $this->fetch('designer_index', [
            'lists' => $lists,
            'began' => $began,
            'end' => $end,
            'something' => $something,
            'list' => $data,
        ]);
    }

    //修改
    public function designer_edit(Request $request, $id)
    {
        if (!empty($_POST)) {
            $data = $request->post();
            $id = $data['id'];

            if (empty($_POST['brand'])) {
                $this->ajaxReturn(array('status' => false, 'msg' => '请填写品牌名'));
            }
            if (empty($_POST['designer'])) {
                $this->ajaxReturn(array('status' => false, 'msg' => '请填写设计师'));
            }
            if (empty($_POST['price_area'])) {
                $this->ajaxReturn(array('status' => false, 'msg' => '请填写价格区间'));
            }
            if (empty($_POST['customer'])) {
                $this->ajaxReturn(array('status' => false, 'msg' => '请填写目标客户'));
            }
            if (empty($_POST['contact_name'])) {
                $this->ajaxReturn(array('status' => false, 'msg' => '请填写联系人姓名'));
            }
            if (empty($_POST['mobile'])) {
                $this->ajaxReturn(array('status' => false, 'msg' => '请填写电话'));
            }
            if (empty($_POST['email'])) {
                $this->ajaxReturn(array('status' => false, 'msg' => '请填写邮箱'));
            }
            if (empty($_POST['address'])) {
                $this->ajaxReturn(array('status' => false, 'msg' => '请填写地址'));
            }
            if (empty($_POST['wechat'])) {
                $this->ajaxReturn(array('status' => false, 'msg' => '请填写微信'));
            }

            if (empty($data['images'])) {
                //用之前的图片
                $res = db('join_apply')->where('id', $id)->find();

                $old = $res['images'];
                $data['images'] = $old;
            } else {
                //拼接
                $data['images'] = 'designer/' . $data['images'];
            }


            $result = db('join_apply')->where('id', $id)->update($data);
            if ($result) {
                $this->ajaxReturn(array('msg' => '修改成功'));
            } else {
                $this->ajaxReturn(array('msg' => '管理员未做任何修改'));
            }
        }
        //原来的图片
        $pic = \db('join_apply')->where('id', $id)->find();


        return $this->fetch('designer_edit', [
            'data' => $pic,
        ]);

    }

    //删除
    public function designer_del(Request $request)
    {
        $id = $request->post('id');
        //删除
        $res = db('join_apply')->where('id', $id)->delete();
        if ($res) {
            $this->ajaxReturn(array('msg' => '删除成功'));

        }
        $this->ajaxReturn(array('msg' => '删除失败'));
    }

    //审批
    public function designer_approval(Request $request)
    {
        $id = $request->post('id');
        $status = $request->post('status');

        //通过id找到用户修改表
        $user = db('join_apply')->where('id', $id)->find();
        $user_id = $user['user_id'];

        if ($status == 0) {
            $where['status'] = 1;
        } else if ($status == 1) {
            $where['status'] = 2;
        } else if ($status == 2) {
            $where['status'] = 3;
            $change['is_partner'] = 1;
            $partner = db('users')->where('user_id', $user_id)->update($change);
        }
        //切换状态
        $res = db('join_apply')->where('id', $id)->update($where);


        if ($res) {
            $this->ajaxReturn(array('status' => 1, 'msg' => "审核成功！"));
        }
        $this->ajaxReturn(array('status' => 1, 'msg' => "审核失败！"));

    }

    //直接ban
    public function banStatus(Request $request)
    {
        $id = $request->post('id');
        $status = $request->post('status');

        if ($status == 4) {
            $data['status'] = 4;
        } else {
            $data['status'] = 0;
        }

        //删除
        $res = db('join_apply')->where('id', $id)->update($data);
        if ($res) {
            $this->ajaxReturn(array('status' => '1', 'msg' => '提交成功'));

        }
        $this->ajaxReturn(array('status' => '-1', 'msg' => '提交失败'));


    }

    //发布消息
    public function designer_publish(Request $request)
    {
        halt('11');

    }

    //上传
    public function upload(Request $request)
    {
        //接收
        $file = $request->file('file');
        $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads/designer');
        // 成功上传后 获取上传信息
        $url = $info->getSaveName();

        return json($url);

    }


}
