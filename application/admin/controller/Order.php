<?php

namespace app\admin\controller;

use think\Request;
use think\Session;

class Order extends Common
{
    //查看
    public function order_index(Request $request)
    {
        //拼接
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
        if (!empty($data['something'])) {
            $something = $data['something'];
            $list = db('order')
                ->alias('o')
                ->join('join_apply d', 'o.designer_id =d.user_id')
                ->where($where)
                ->whereor("o.mobile", "like", "%{$something}%")
                ->whereor("consignee", "like", "%{$something}%")
                ->whereor("d.designer", "like", "%{$something}%")
                ->whereTime('add_time', 'between', [$began, $end])
                ->field('o.*,d.designer')
                ->order('add_time desc')
                ->paginate(5, false, array('query' => $data));
        } else {
            $something = '';
            //查出订单
            $list = db('order')
                ->alias('o')
                ->join('join_apply d', 'o.designer_id =d.user_id')
                ->where($where)
                ->whereTime('add_time', 'between', [$began, $end])
                ->field('o.*,d.designer')
                ->order('add_time desc')
                ->paginate(5, false, array('query' => $data));
        }


        return $this->fetch('order_index', [
            'list' => $list,
            'began' => $began,
            'end' => $end,
            'something' => $something,
        ]);
    }

    //操作
    public function order_changes(Request $request, $order_id)
    {
        //去order_action里面添加记录
        $data = input('post.');
        if ($data) {
            $where['shipping_status'] = 1;
            //订单更改状态
            $order = db('order')->where('order_id', $order_id)->update($where);
            //插入到order_action中
            $info = db('order')->where('order_id', $order_id)->find();
            $act['order_id'] = $order_id;
            $admin_id = Session::get('admin_id');
            if ($admin_id == '1') {
                $act['action_user'] = $admin_id;
            } else {
                $act['action_user'] = 0;
            }
            $act['order_status'] = $info['order_status'];
            $act['shipping_status'] = $info['shipping_status'];
            $act['pay_status'] = $info['pay_status'];
            $act['log_time'] = time();
            $act['status_desc'] = $data['status_desc'];
            $act['action_note'] = $data['action_note'];
            $order_action = db('order_action')->insert($act);
            //修改order_goods的发送状态
            $change['is_send'] = 1;
            $order_gooods = db('order_goods')->where('order_id', $order_id)->update($change);

            if ($order && $order_action && $order_gooods) {
                $this->ajaxReturn(array('msg' => '修改成功'));
            }
            $this->ajaxReturn(array('status' => false, 'msg' => '修改失败'));
        }
        return $this->fetch('order_changes', [
            'order_id' => $order_id,
        ]);
    }


    //详情
    public function order_detail(Request $request, $order_id)
    {

        $detail = db('order')
            ->alias('o')
            ->join('users u', 'o.user_id =u.user_id')
            ->join('region r1', 'o.province =r1.id')
            ->join('region r2', 'o.city =r2.id')
            ->join('region r3', 'o.district =r3.id')
            ->where('o.order_id', $order_id)
            ->field('o.*,u.nickname,u.email,u.mobile as tel,r1.name as pro_name,r2.name as city_name,r3.name as area_name')
            ->find();

        //总价
        $final_price = $detail['order_amount'];
        $detail = [$detail];
        //查出商品
        $goods = db('order_goods')
            ->where('order_id', $order_id)
            ->select();

        /*拿最终价格
         * $goods = db('order_goods')
            ->alias('o')
            ->join('spec_goods_price s','u.item_id =s.item_id')
            ->where('order_id', $order_id)
            ->filed('o.*,s.price as factPrice')
            ->select();*/

        //操作者
        $operator = db('order_action')->where('order_id', $order_id)->select();
        return $this->fetch('order_detail', [
            'detail' => $detail,
            'operator' => $operator,
            'order_id' => $order_id,
            'goods' => $goods,
            'final_price' => $final_price
        ]);
    }
    //打印
    public function order_print(Request $request, $order_id)
    {
        //查出收货人和订单详情
        $more = db('order')
            ->alias('o')
            ->join('users u', 'o.user_id =u.user_id')
            ->join('region r1', 'o.province =r1.id')
            ->join('region r2', 'o.city =r2.id')
            ->join('region r3', 'o.district =r3.id')
            ->where('order_id', $order_id)
            ->field('o.*,r1.name as pro_name,r2.name as city_name,r3.name as area_name')
            ->find();
//        halt($more);
        //最终应付金额
        $final_price = $more['order_amount'];
        $more = [$more];

        //商品详情
       /* $list =db('order_goods')
            ->alias('o')
            ->join('spec_goods_price s','o.item_id =s.item_id')
            ->where('o.order_id', $order_id)
            ->field('o.*,s.price')
            ->select();
        halt($list);*/

        $list = db('order')
            ->alias('o')
            ->join('order_goods og', 'o.order_id =og.order_id')
            ->field('o.*,og.goods_name,og.goods_sn,og.goods_num,og.goods_price,og.member_goods_price,og.final_price,og.order_id as og_id')
            ->where('o.order_id', $order_id)
            ->select();
//        halt($list);

        return $this->fetch('order_print', [
            'more' => $more,
            'list' => $list,
            'final_price' => $final_price,
        ]);
    }

    //订单页面全部的商品展示
    public function order_goods()
    {
        $order_id = input('order_id');
        /*$data =db('order_goods')
            ->alias('o')
            ->join('goods g', 'o.goods_id =g.goods_id')
            ->join('spec_goods_price s','g.item_id =s.item_id')
            ->field('o.*,g.original_img as old_img,g.market_price,g.price,g.cost_price,g.store_count,g.collect_sum,s.fatePrice,s.key')
            ->where('order_id', $order_id)
            ->select();
        foreach ($data as $k=>$v){
            $item =$v['key'];
            //值
            $key = explode("_", $item);
            $key['0'] = isset($key['0']) ? $key['0'] : 0;
            $key['1'] = isset($key['1']) ? $key['1'] : 0;
            $key['2'] = isset($key['2']) ? $key['2'] : 0;
            $arr =[$key['0'],$key['1'],$key['2']];

            $where['spec_item_id'] = array('in',$arr);
            $where['goods_id'] = $v['goods_id'];

            $data[$k]['img'] = db('spec_image')
                ->where($where)
                ->field('src')
                ->find();
            if (empty($cart[$k]['img'])) {
                $data[$k]['img'] = $v['old_img'];
            }
        }*/


        //查出订单下面的商品
        $data = db('order_goods')
            ->alias('o')
            ->join('goods g', 'o.goods_id =g.goods_id')
            ->field('o.*,g.original_img,g.market_price,g.price,g.cost_price,g.store_count,g.collect_sum')
            ->where('order_id', $order_id)
            ->select();

        return $this->fetch('order_goods', [
            'data' => $data,
        ]);
    }

    //详情页面单独的商品展示
    public function order_more()
    {
        /*当前订单的商品去拿相对应的图片
         * $rec_id =input('rec_id');
        $data =db('order_goods')
            ->alias('g')
            ->join('spec_goods_price s','g.item_id =s.item_id')
            ->join('goods d','g.goods_id =d.goods_id')
            ->where('g.rec_id',$rec_id)
            ->filed('g.*,s.key,d.original_img as old_img')
            ->select();
        foreach ($data as $k=>$v){
            $item =$v['key'];
            //值
            $key = explode("_", $item);
            $key['0'] = isset($key['0']) ? $key['0'] : 0;
            $key['1'] = isset($key['1']) ? $key['1'] : 0;
            $key['2'] = isset($key['2']) ? $key['2'] : 0;
            $arr =[$key['0'],$key['1'],$key['2']];

            $where['spec_item_id'] = array('in',$arr);
            $where['goods_id'] = $v['goods_id'];

            $data[$k]['img'] = db('spec_image')
                ->where($where)
                ->field('src')
                ->find();
            if (empty($cart[$k]['img'])) {
                $data[$k]['img'] = $v['old_img'];
            }
        }
        halt($data);*/
        $goods_id = input('goods_id');
        $data = db('goods')->where('goods_id', $goods_id)->find();

        return $this->fetch('order_more', [
            'data' => $data,
        ]);
    }
}
