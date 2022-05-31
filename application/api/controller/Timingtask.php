<?php
/**
 * 定时任务专用
 * Created by PhpStorm
 * User: Brian
 * Date: 2019/8/24
 * Time: 10:21
 */

namespace app\api\controller;


class Timingtask
{
    public function checkOrder(){
        $map['order_status'] = 0;
        $map['shipping_status'] = 0;
        $map['pay_status'] = 0;
        $expire_time = time()-24*3600;
        $map['add_time'] = ['<',$expire_time];
        $order_list = db('order')->where($map)->field('order_id,user_id')->order('add_time desc')->limit(50)->select();
        if(!$order_list){
            return;
        }
        foreach ($order_list as $row){
            action('order/cancelOrderFunc',[
                'userid'=>$row['user_id'],
                'order_id'=>$row['order_id'],
                'type'=>'sys_cancel_order'
            ]);
        }
    }

}