<?php


namespace app\api\controller;

use think\Db;

use think\Request;

class Order extends Common
{
    /**
     * 获取用户订单列表
     */
    public function orderList()
    {
        $data = input('post.');
        $id = $this->verifyLogin($data);
        $something = isset($data['something']) ? $data['something'] : 0;

        //分页
        $pageNum = 10;//每页10条数据
        $page = $data['page'] ?? 1;
        $page = ($page < 1) ? 1 : (int)$page;
        $start = ($page - 1) * $pageNum;
        $begin = isset($start) ? $start : 0;

        //待付款 待发货 待收货 待评价
        $where['o.is_delete'] = ['=', 0];
        $where['o.user_id'] = ['=', $id];
        if ($something == 'pendingPayment') {
            $where['o.pay_status'] = ['=', 0];
            $where['o.shipping_status'] = ['=', 0];
            $cart = db('order')
                ->alias('o')
                ->join('join_apply j', 'o.designer_id =j.user_id')
                ->field('o.*,j.designer,j.user_id as designer_uid')
                ->where($where)
                ->order('add_time desc,j.designer asc')
                ->limit($begin, $pageNum)
                ->select();
            $list = $this->imgChange($cart);
        } elseif ($something == 'delivered') {
            $where['o.pay_status'] = ['=', 1];
            $where['o.shipping_status'] = ['=', 0];
            $cart = db('order')
                ->alias('o')
                ->join('join_apply j', 'o.designer_id =j.user_id')
                ->field('o.*,j.designer,j.user_id as designer_uid')
                ->where($where)
                ->order('add_time desc,j.designer asc')
                ->limit($begin, $pageNum)
                ->select();
            $list = $this->imgChange($cart);
        } elseif ($something == 'pendingReceipt') {
            $where['o.pay_status'] = ['=', 1];
            $where['o.shipping_status'] = ['=', 1];
            $where['o.order_status'] = ['=', 1];
            $cart = db('order')
                ->alias('o')
                ->join('join_apply j', 'o.designer_id =j.user_id')
                ->field('o.*,j.designer,j.user_id as designer_uid')
                ->where($where)
                ->order('add_time desc,j.designer asc')
                ->limit($begin, $pageNum)
                ->select();
            $list = $this->imgChange($cart);

        } elseif ($something == 'isComment') {
            $where['o.pay_status'] = ['=', 1];
            $where['o.shipping_status'] = ['=', 2];
            $where['o.is_comment'] = ['=', 0];
            $where['o.order_status'] = ['=', 1];
            $cart = db('order')
                ->alias('o')
                ->join('join_apply j', 'o.designer_id =j.user_id')
                ->field('o.*,j.designer,j.user_id as designer_uid')
                ->where($where)
                ->order('add_time desc,j.designer asc')
                ->limit($begin, $pageNum)
                ->select();
            $list = $this->imgChange($cart);

        } elseif($something=='all') {
            //分页
            $allpage = 5;//每页10条数据
            $pages = $data['page'] ?? 1;
            $pages = ($pages < 1) ? 1 : (int)$pages;
            $starts = ($pages - 1) * $allpage;
            $begins = isset($starts) ? $starts : 0;

            //待付款 待发货 待收货 待评价
            $pendingPayment['o.pay_status'] = ['=', 0];
            $pendingPayment['o.shipping_status'] = ['=', 0];
            $payment = db('order')
                ->alias('o')
                ->join('join_apply j', 'o.designer_id =j.user_id')
                ->field('o.*,j.designer,j.user_id as designer_uid')
                ->where($where)
                ->where($pendingPayment)
                ->order('add_time desc,j.designer asc')
                ->limit($begins, $allpage)
                ->select();
            $pay = $this->imgChange($payment);

            //待发货
            $send['o.pay_status'] = ['=', 1];
            $send['o.shipping_status'] = ['=', 0];
            $sendGoods = db('order')
                ->alias('o')
                ->join('join_apply j', 'o.designer_id =j.user_id')
                ->field('o.*,j.designer,j.user_id as designer_uid')
                ->where($where)
                ->where($send)
                ->order('add_time desc,j.designer asc')
                ->limit($begins, $allpage)
                ->select();
            $sends = $this->imgChange($sendGoods);

            //待收货
            $receipt['o.pay_status'] = ['=', 1];
            $receipt['o.shipping_status'] = ['=', 1];
            $receipt['o.order_status'] = ['=', 1];
            $receiptGoods = db('order')
                ->alias('o')
                ->join('join_apply j', 'o.designer_id =j.user_id')
                ->field('o.*,j.designer,j.user_id as designer_uid')
                ->where($where)
                ->where($receipt)
                ->order('add_time desc,j.designer asc')
                ->limit($begins, $allpage)
                ->select();
            $gets = $this->imgChange($receiptGoods);

            // 待评价
            $reason['o.pay_status'] = ['=', 1];
            $reason['o.shipping_status'] = ['=', 2];
            $reason['o.is_comment'] = ['=', 0];
            $receipt['o.order_status'] = ['=', 1];
            $comment = db('order')
                ->alias('o')
                ->join('join_apply j', 'o.designer_id =j.user_id')
                ->field('o.*,j.designer,j.user_id as designer_uid')
                ->where($where)
                ->where($reason)
                ->order('add_time desc,j.designer asc')
                ->limit($begins, $allpage)
                ->select();
            $comments = $this->imgChange($comment);

            $list = array_merge($pay,$sends,$gets,$comments);

        }else{
            $list =[];
        }

        if (empty($list)) {
            $list = [];
        }

        //返回
        if ($list) {
            $this->api_return($list);
        } else {
            $this->json_success_msg('没有数据');
        }

    }

    /**
     * 订单商品评论
     */
    public function comment()
    {

        $date = input('post.');
        $userid = $this->verifyLogin($date);
        $userInfo = $this->getUser($userid, 'user_id,nickname as username');
        $data = $date['info'];

        foreach ($data as $k => $v) {
            $data[$k]['content'] = $this->str_filter($v['content']);
            $data[$k]['user_id'] = $userid;
            $data[$k]['username'] = $userInfo['username'];
            $data[$k]['is_show'] = 1;//默认显示
            $data[$k]['add_time'] = time();
            $order[] = $v['order_id'];
        }
        $res = db('comment')->insertAll($data);
        //更改订单状态
        $changeStatus = \db('order')->where('order_id', $order[0])->update(['is_comment' => 1]);
        $orderStatus = \db('order_goods')->where('order_id', $order[0])->update(['is_comment' => 1]);
        if ($res) {
            return $this->json_success_msg('评论成功');
        } else {
            return $this->json_error_msg('评论失败');
        }
    }


    /**
     * 订单售后
     */
    public function after_sale()
    {
        $msg = input('post.');
        $id = $this->verifyLogin($msg);
        $rec_id = $msg['rec_id'];

        $msg['reason'] = isset($msg['reason']) ? $msg['reason'] : '';
        if ($msg['reason']) {
            $reason = $this->str_filter($msg['reason']);
        } else {
            $reason = '';
        }

        $msg['des'] = isset($msg['des']) ? $msg['des'] : '';
        if ($msg['des']) {
            $des = $this->str_filter($msg['des']);
        } else {
            $des = '';
        }
        $msg['img'] = isset($msg['img']) ? $msg['img'] : '';
        if ($msg['img']) {
            $str = implode(",", $msg['img']);
            $data['img'] = $str;
        } else {
            $data['img'] = '';
        }


        $back = db('order_goods')
            ->where('rec_id', $rec_id)
            ->field('goods_num,goods_price,order_id')
            ->find();
        $back_money = round($back['goods_num'] * $back['goods_price'], 2);

        $data['rec_id'] = $rec_id;
        $data['refund_amount'] = $back_money;
        $data['reason'] = $reason;
        $data['des'] = $des;
        $data['user_id'] = $id;
        //查收货人 电话 地址
        $info = db('order')
            ->alias('o')
            ->join('region r1', 'o.province =r1.id')
            ->join('region r2', 'o.city =r2.id')
            ->join('region r3', 'o.district =r3.id')
            ->where('order_id', $back['order_id'])
            ->field('o.consignee,o.mobile,r1.name as pro_name,r2.name as city_name,r3.name as area_name,o.address')
            ->find();

        $data['consignee'] = $info['consignee'];
        $data['mobile'] = $info['mobile'];
        $data['consignee_address'] = $info['pro_name'] . $info['city_name'] . $info['area_name'] . $info['address'];
        $data['add_time'] = time();

        //把数据加入反馈表中
        $res = db('after_sale')
            ->insert($data);
        if ($res) {
            $this->json_success_msg('反馈成功');
        } else {
            $this->json_error_msg('反馈失败，请稍后再试');
        }

    }

    //支付成功回调处理
    public function pay_back($param = [])
    {
        $res = false;
        $data['pay_time'] = time();
        $data['pay_name'] = 'wx';
        $data['pay_status'] = 1;
        $data['transaction_id'] = $param['transaction_id'];
        Db::startTrans();
        try {
            $res = db('order')->where('order_sn', $param['out_trade_no'])->update($data);
            $row = db('order')->where('order_sn', $param['out_trade_no'])->find();
            if ($row['is_parent_order']) {
                $order_id_arr = db('order')->where('parent_sn', $param['out_trade_no'])->column('order_id');
                db('order')->where('order_id', 'in', $order_id_arr)->update($data);
            }
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
        }
        if ($res) {
            return true;
        }
        return false;
    }

    //取消订单
    public function cancel_order()
    {
        $param = input('post.');
        $userid = $this->verifyLogin($param);
        $order_id = $param['order_id'];
        $res = $this->cancelOrderFunc($userid, $order_id, $type = "user_cancel_order");
        if ($res) {
            $log = db('order')->field('order_id,order_status,shipping_status,pay_status')->where(['order_id' => $order_id, 'user_id' => $userid])->find();
            if (!isset($param['action_note'])) {
                $this->json_error_msg('请注明取消原因');
            }
            if ($log) {
                $log['action_note'] = $param['action_note'];
                $this->order_log($log);//生成日志
            }
            $this->json_success_msg('取消成功');
        }
        $this->json_error_msg('取消失败');
    }

    //删除订单
    public function delOrder()
    {
        $data = input('post.');
        $userid = $this->verifyLogin($data);
        $row = db('order')->where(['order_id' => $data['order_id'], 'user_id' => $userid])->find();
        if (!$row) {
            $this->json_error_msg('订单不存在');
        }
        if ($row['order_status'] != 2 || ($row['is_comment'] == 0 && $row['order_status'] != 2)) {
            $this->json_error_msg('删除失败');
        }
        $res = db('order')->where(['order_id' => $data['order_id'], 'user_id' => $userid])->update(['is_delete' => 1]);
        if ($res !== false) {
            $log = db('order')->field('order_id,order_status,shipping_status,pay_status')->where(['order_id' => $data['order_id'], 'user_id' => $userid])->find();
            if ($log) {
                $log['action_note'] = '用户删除订单';
                $this->order_log($log);//生成日志
            }
            $this->json_success_msg('删除成功');
        }
        $this->json_error_msg('删除失败');
    }

    public function cancelOrderFunc($userid = 0, $order_id = 0, $type = '')
    {
        $res = false;
        $data['order_status'] = 2;
        $row = db('order')->where(['order_id' => $order_id, 'user_id' => $userid])->find();
        if (!$row) {
            $this->json_error_msg('订单不存在');
        }
        Db::startTrans();
        try {
            $res = db('order')->where(['order_id' => $order_id, 'user_id' => $userid])->update($data);//修改订单状态(取消)
            //成品返回库存
            if ($row['complete_goods']) {
                $order_goods_arr = db('order_goods')->field('item_id,goods_num,goods_id')->where('order_id', $order_id)->select();
                foreach ($order_goods_arr as $val) {
                    db('spec_goods_price')->where('item_id', $val['item_id'])->setInc('store_count', $val['goods_num']);
                    db('goods')->where('goods_id', $val['goods_id'])->setInc('store_count', $val['goods_num']);
                }
            }
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
        }
        if ($res) {
            $this->sendOrderMessage($type, $userid,$order_id);
            return true;
        }
        return false;
    }

    /**
     * 确认收货
     */
    public function confirmReceipt()
    {
        $data = input('post.');
        $this->verifyLogin($data);
        $order_id = $data['order_id'];
        $where['shipping_status'] = 2;
        $where['confirm_time'] = time();

        $res = \db('order')->where('order_id', $order_id)->update($where);
        $msg = \db('order')->where('order_id', $order_id)->find();
        $date['order_id'] = $order_id;
        $date['action_user'] = 0;//调用api默认是用户
        $date['order_status'] = $msg['order_status'];
        $date['shipping_status'] = $msg['shipping_status'];
        $date['pay_status'] = $msg['pay_status'];
        $date['log_time'] = time();

        if ($res) {
            $log = \db('order_action')->insert($date);
            if ($log) {
                $this->json_success_msg('确认成功');
            }
        }
        $this->json_error_msg('确认失败');

    }

    /**
     * 订单日志
     */
    public function order_log($param = [])
    {
        if (!$param) {
            return false;
        }
        $data['order_id'] = $param['order_id'];
        $data['order_status'] = $param['order_status'];
        $data['shipping_status'] = $param['shipping_status'];
        $data['pay_status'] = $param['pay_status'];
        $data['action_note'] = $param['action_note'];
        $data['log_time'] = time();
        $res = db('order_action')->insert($data);
        if ($res) {
            return true;
        }
        return false;
    }

    /**
     * 公用的订单转换图片相关
     * @param $cart 查询出来的数组
     * @return array 返回的数据
     */
    public function imgChange($cart)
    {
        foreach ($cart as $k => $v) {

            //设计师头像和判断
            $head = db('users')
                ->where('user_id', $v['designer_uid'])
                ->field('oauth,head_pic')
                ->find();
            $cart[$k]['oauth'] = $head['oauth'];
            $cart[$k]['head_pic'] = $head['head_pic'];
            $cart[$k]['goods_info'] = db('order_goods')
                ->alias('g')
                ->join('spec_goods_price s', 's.item_id =g.item_id')
                ->field('g.rec_id,g.goods_name,g.goods_id,g.goods_num,g.goods_price,g.final_price,g.item_id,g.is_comment,s.key_name,s.key')
                ->whereIn('order_id', $v['order_id'])
                ->select();

            $cart[$k]['service_status'] = $v['service_status'];
            $cart[$k]['info'] = json_decode($v['service_msg'], true);
        }

        //处理图片
        $resource_pre = getConfig('resource_pre');
        $list = [];
        foreach ($cart as $k => $v) {
            $list[$k]['order_id'] = $v['order_id'];
            $list[$k]['shipping_status'] = $v['shipping_status'];
            $list[$k]['pay_status'] = $v['pay_status'];
            $list[$k]['order_status'] = $v['order_status'];
            $list[$k]['is_delete'] = $v['is_delete'];
            //成衣 0非 1是
            $list[$k]['complete_goods'] = $v['complete_goods'];
            $list[$k]['info'] = isset($v['info']) ? $v['info'] : [];
            //打折后的钱
            $list[$k]['order_amount'] = $v['order_amount'];
            $list[$k]['designerName'] = $v['designer'];
            $head_img = complete_url($v['head_pic'], $resource_pre);
            $list[$k]['head_img'] = $head_img;
//            $list[$k]['goods_info'] = $v['goods_info'];
            $list[$k]['num'] = count($v['goods_info']);

            foreach ($v['goods_info'] as $king => $value) {
                $coment[] = isset($value['is_comment']) ? $value['is_comment'] : 0;
            }

            //是不是未评价
            $list[$k]['is_comment'] = $v['is_comment'];

            foreach ($v['goods_info'] as $key => $val) {
                $list[$k]['child'][$key]['item_id'] = $val['item_id'];
                $list[$k]['child'][$key]['goods_name'] = $val['goods_name'];
                $list[$k]['child'][$key]['complete_goods'] = $v['complete_goods'];
                $list[$k]['child'][$key]['service_status'] = $v['service_status'];
                $list[$k]['child'][$key]['service_time'] = isset($v['info']['service_time']) ? $v['info']['service_time'] : '';
                $list[$k]['child'][$key]['service_mobile'] = isset($v['info']['service_mobile']) ? $v['info']['service_mobile'] : '';
                $list[$k]['child'][$key]['service_addr'] = isset($v['info']['service_addr']) ? $v['info']['service_addr'] : '';

                $list[$k]['child'][$key]['goods_id'] = $val['goods_id'];
                $list[$k]['child'][$key]['rec_id'] = $val['rec_id'];
                $list[$k]['child'][$key]['goods_num'] = $val['goods_num'];
                $list[$k]['child'][$key]['price'] = $val['goods_price'];
                $list[$k]['child'][$key]['final_price'] = $val['final_price'];
//                $list[$k]['child'][$key]['item_id']=$val['item_id'];
                $list[$k]['child'][$key]['is_comment'] = $val['is_comment'];
                //好评
                $list[$k]['child'][$key]['evaluate'] = '已经收到宝贝啦，我很喜欢呢，这么棒的产品我想分享给更多喜欢的人！';
                $list[$k]['child'][$key]['is_anonymous'] = 1;

                //商品原图片
                $old_img = db('goods')
                    ->where('goods_id', $val['goods_id'])
                    ->field('original_img')
                    ->find();
                //形容词
                $adj = db('spec_goods_price')
                    ->where('item_id', $val['item_id'])
                    ->field('key_name,key')
                    ->find();
                //拿出关键词
                $keys = explode(",", $adj['key_name']);
                $list[$k]['child'][$key]['color'] = isset($keys['0']) ? $keys['0'] : '';
                $list[$k]['child'][$key]['size'] = isset($keys['1']) ? $keys['1'] : '';
                $list[$k]['child'][$key]['category'] = isset($keys['2']) ? $keys['2'] : '';
                //判断图片
                $img = explode("_", $adj['key']);
                $img['0'] = isset($img['0']) ? $img['0'] : 0;
                $spec_img = db('spec_image')
                    ->where('spec_item_id', $img['0'])
                    ->field('src')
                    ->find();
                //数据库存在的图片
                $item_img = $spec_img['src'];
                if (empty($item_img)) {
                    $item_img = $old_img['original_img'];
                }
                $list_img = complete_url($item_img, $resource_pre);
                $list[$k]['child'][$key]['img_url'] = $list_img;

            }

        }

        return $list;

    }



    /**
     * 订单支付取消发送消息通知
    */
    public function cancelPay(){
        $data = input('post.');
        $user_id = $this->verifyLogin($data);
        $order_id = $data['order_id'] ?? '';

        if($this->sendOrderMessage('unpaid',$user_id,$order_id)){
            return $this->json_success_msg('success');
        }else{
            return $this->json_error_msg('fail');
        }

    }
}