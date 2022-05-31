<?php

namespace app\api\controller;



use think\Config;

class Message extends Common
{
    /**
     * 分类调用
     * @param $arr
     * @return string
     */
    public function sum($arr)
    {
        //数组和
        $arr_sum =count($arr);

        $sum = 0;
        foreach($arr as $item){
            $sum += (int) $item['is_see'];
        }

        if($arr_sum !=$sum){
            return 'has';
        }elseif($arr_sum ==$sum){
            return 'over';
        }

    }

    //查出分类
    public function cateList()
    {
        //验证登录
        $data = input('post.');
        $user_id =$this->verifyLogin($data);
        //消息
        $all = db('user_message')
            ->field('category')
            ->group('category')
            ->select();
        //系统
        $see =db('user_message')
            ->where('user_id',$user_id)
            ->where('category',0)
            ->field('is_see')
            ->select();
        $sum =$this->sum($see);
        //分类
        $cate =db('user_message')
            ->where('user_id',$user_id)
            ->where('category',1)
            ->field('is_see')
            ->select();
        $cates =$this->sum($cate);

        //打印出当前用户最新的消息 系统
        $self =db('user_message')
            ->alias('u')
            ->join('send_message s','u.message_id =s.id')
            ->where('u.user_id',$user_id)
            ->where('u.category',0)
            ->field('u.add_time,s.content')
            ->order('u.add_time desc')
            ->find();

        //活动
        $own =db('user_message')
            ->alias('u')
            ->join('send_message s','u.message_id =s.id')
            ->where('u.user_id',$user_id)
            ->where('u.category',1)
            ->field('u.add_time,s.content')
            ->order('u.add_time desc')
            ->find();
        $all_cate = array();
        $i = 0;
        //相关消息(涉及维护)
        $resource_pre = getConfig('resource_pre');
        foreach ($all as $k => $v) {
            if ($v['category'] == 0) {
                $all_cate[$i]['category'] = '系统消息';
                $all_cate[$i]['english'] = 'system_information';
                $all_cate[$i]['category_id'] = 0;
                //默认图片
                $img = config('apiImg.system');
                $img_url = complete_url($img,$resource_pre);
                $all_cate[$i]['img'] =$img_url;
                $all_cate[$i]['content'] = $self['content'];
                if($sum =='has'){
                    $all_cate[$i]['is_see'] = 0;
                }else{
                    $all_cate[$i]['is_see'] = 1;
                }
                if($self['add_time']){
                    //天数
                    $old_day =date('Y/m/d',$self['add_time']);
                    $now_day =date('Y/m/d');
                    //年
                    $old_year =date('Y',$self['add_time']);
                    $now_year =date('Y');

                    if($old_day ==$now_day){
                        $all_cate[$i]['add_time'] =date('H:i',$self['add_time']);
                    }elseif($old_year==$now_year){
                        //是今年的
                        $all_cate[$i]['add_time'] =date('m/d H:i',$self['add_time']);
                    }else{
                        $all_cate[$i]['add_time'] =date('Y/m/d H:i',$self['add_time']);
                    }

                }else{
                    $all_cate[$i]['add_time'] =null;
                }

            } elseif ($v['category'] == 1) {
                $all_cate[$i]['category'] = '活动精选';
                $all_cate[$i]['english'] = 'activity';
                $all_cate[$i]['category_id'] = 1;
                $all_cate[$i]['content'] = $own['content'];
                $img = config('apiImg.activity');
                $img_url = complete_url($img,$resource_pre);
                $all_cate[$i]['img'] =$img_url;
                if($cates =='has'){
                    $all_cate[$i]['is_see'] = 0;
                }else{
                    $all_cate[$i]['is_see'] = 1;
                }
                if($own['add_time']){
                    //天数
                    $old_day =date('Y/m/d',$own['add_time']);
                    $now_day =date('Y/m/d');
                    //年
                    $old_year =date('Y',$own['add_time']);
                    $now_year =date('Y');

                    if($old_day ==$now_day){
                        $all_cate[$i]['add_time'] =date('H:i',$own['add_time']);
                    }elseif($old_year==$now_year){
                        //是今年的
                        $all_cate[$i]['add_time'] =date('m/d H:i',$own['add_time']);
                    }else{
                        $all_cate[$i]['add_time'] =date('Y/m/d H:i',$own['add_time']);
                    }

                }else{
                    $all_cate[$i]['add_time'] =null;
                }
            }
            $i++;
        }
        //默认图片

        $img = config('apiImg.service');
        $img_url = complete_url($img,$resource_pre);
        $client[] =['category'=>'客户','img'=>$img_url];
        $final =['service'=>$client,'main'=>$all_cate];
        if ($final) {
            $this->api_return($final);
        }
        $this->json_error_msg('返回失败，请稍后再试');

    }

    //查出所有消息 加上全部消息
    public function messageInfo()
    {
        //id 然后用id去查
        $data = input('post.');
        $id = $this->verifyLogin($data);
        //传来的分类
        $cate = trim($data['category_id']) ? $data['category_id']:0;

        //群体消息
        $all = db('send_message')
            ->where('is_all', 1)
            ->limit(50)
            ->select();

        //然后查看该用户有没有这条消息，没有就再发一次消息给他然后查出来

        //查出全部消息的id
        $arr = array();
        foreach ($all as $k => $v) {
            $arr[]['id'] = $v['id'];

        }

        //全部的消息id转为一维数组
        $all_id = array_reduce($arr, function ($result, $value) {
            return array_merge($result, array_values($value));
        }, array());


        //得到存在于数据表的用户消息
        $info = db('user_message')
            ->alias('u')
            ->where('user_id', $id)
            ->field('u.message_id')
            ->whereIn('u.message_id', $all_id)
            ->limit(100)
            ->select();
        //用户的二维转为一维
        $sign = [];
        foreach ($info as $k => $v) {
            $sign[] = $v['message_id'];
        }
        //拿到差别的那个那些id 去send_message 查出来
        $lost = array_diff_key($arr, $sign);

        $lost_ids = array_reduce($lost, function ($result, $value) {
            return array_merge($result, array_values($value));
        }, array());

        $lost_news = db('send_message')
            ->alias('s')
            ->where('is_all', 1)
            ->whereIn('s.id', $lost_ids)
            ->limit(50)
            ->select();
        //添加到那个表中
        $all_new = array();
        $i = 0;
        foreach ($lost_news as $k => $v) {
            $all_new[$i]['user_id'] = $id;
            $all_new[$i]['message_id'] = $v['id'];
            $all_new[$i]['category'] = $v['category'];
            $all_new[$i]['is_see'] = 0;
            $all_new[$i]['deleted'] = 0;
            $all_new[$i]['add_time'] = time();
            $i++;
        }
        //组合成功插入数据库
        db('user_message')->insertAll($all_new);

        //存入后链表查询返给前端
        $total_info = db('user_message')
            ->alias('u')
            ->join('send_message s', 'u.message_id =s.id')
            ->where('u.user_id', $id)
            ->where('u.category', $cate)
            ->field('u.*,s.title,s.img,s.content')
            ->order('add_time desc')
            ->select();

        $resource_pre = getConfig('resource_pre');
        foreach ($total_info as $k=>$v){
            $img_url = complete_url($v['img'],$resource_pre);
            $list[$k]['rec_id'] = $v['rec_id'];
            $list[$k]['user_id'] = $v['user_id'];
            $list[$k]['message_id'] = $v['message_id'];
            $list[$k]['category'] = $v['category'];
            $list[$k]['is_see'] = $v['is_see'];
            if($v['add_time']){
                //天数
                $old_day =date('Y/m/d',$v['add_time']);
                $now_day =date('Y/m/d');
                //年
                $old_year =date('Y',$v['add_time']);
                $now_year =date('Y');
                if($old_day == $now_day){
                    $list[$k]['add_time'] =date('H:i',$v['add_time']);
                }elseif($old_year == $now_year){
                    //是今年的
                    $list[$k]['add_time'] =date('m/d H:i',$v['add_time']);
                }else{
                    $list[$k]['add_time'] =date('Y/m/d H:i',$v['add_time']);
                }
            }else{
                $list[$k]['add_time'] =null;
            }
            $list[$k]['title'] = $v['title'];
            $list[$k]['content'] = $v['content'];
            $list[$k]['img_url'] = $img_url;

        }

        //组合成新的数据
        $news = ['all' => $list];
        //返回
        if ($news) {
            $this->api_return($news);
        }

        $this->json_error_msg('数据查找失败，请稍后再试');

    }

    //改变查看状态
    public function changeStatus()
    {
        $data = input('post.');
        $user_id =$this->verifyLogin($data);
        $category =$data['category_id'];
        //改变查看状态
        $date['is_see'] = 1;
        $where['user_id'] =$user_id;
        $where['category'] =$category;

        $res = db('user_message')->where($where)->update($date);
        if ($res) {
            $this->json_success_msg('更改成功');
        }
        $this->json_error_msg('更改失败，请稍后再试');
    }


}
