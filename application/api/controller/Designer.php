<?php


namespace app\api\controller;


class Designer extends Common
{
    /**
     * 申请入驻
    */
    public function joinApply(){

        $data = input('post.');
        $userid = $this->verifyLogin($data);
        //字段验证begin

        if(!check_email($data['email'])){
            return $this->json_error_msg('请输入正确邮箱');
        }
        if(!check_mobile($data['telephone']) && !check_telephone($data['telephone'])){
            return $this->json_error_msg('请输入正确联系电话');
        }
        //字段验证end
        $user = $this->getUser($userid,'user_id,is_partner');

        if($user['is_partner']){
           return $this->json_error_msg('您已入驻');
        }
        //判断是否提交过申请
        $check_apply = db('join_apply')->where('user_id',$userid)->field('id,status')->find();
        if($check_apply){
            if($check_apply['status'] == 4){
                //提交前删除之前审核失败的数据
                db('join_apply')->where('id',$check_apply['id'])->delete();
            }else{
                return $this->json_error_msg('请勿重复提交申请');
            }
        }

        $apply_data['address'] = isset($data['address']) ? $this->str_filter($data['address']) : '';
        $apply_data['brand'] = isset($data['brand']) ? $this->str_filter($data['brand']) : '';
        $apply_data['contact_name'] = isset($data['contacts']) ? $this->str_filter($data['contacts']) : '';
        $apply_data['customer'] = isset($data['customer']) ? $this->str_filter($data['customer']) : '';
        $apply_data['designer'] = isset($data['designer']) ? $this->str_filter($data['designer']) : '';
        $apply_data['email'] = isset($data['email']) ? $this->str_filter($data['email']) : '';
        $apply_data['desc'] = isset($data['explain']) ? $this->str_filter($data['explain']) : '';
        $apply_data['images'] = isset($data['imgSrc']) ? json_encode($data['imgSrc'],64) : '';
        $apply_data['price_area'] = isset($data['price']) ? $this->str_filter($data['price']) : '';
        $apply_data['mobile'] = isset($data['telephone']) ? $this->str_filter($data['telephone']) : '';
        $apply_data['wechat'] = isset($data['wx']) ? $this->str_filter($data['wx']) : '';
        $apply_data['type'] = 1;//暂定只有设计师
        $apply_data['reg_time'] = time();
        $apply_data['update_time'] = time();
        $apply_data['status'] = 0;
        $apply_data['user_id'] = $user['user_id'];

        if(db('join_apply')->insert($apply_data)){
            return $this->json_success_msg('恭喜你成功提交入驻申请');
        }else{
            return $this->json_error_msg('提交失败，请稍后再试');
        }
    }
    /**
     * 入驻进度
    */
    public function joinProgress(){
        $data = input('post.');
        $userid = $this->verifyLogin($data);
        $joinInfo = db('join_apply')->where('user_id',$userid)->field('id,status,update_time')->find();
        if(!$joinInfo){
            return $this->json_error_msg('暂无申请记录');
        }
        $status = $joinInfo['status'];
        return $this->api_return(['joinStatus'=>$status]);

    }

    /**
     * 设计师发布商品
    */
    public function publishGoods(){
        $data = input('post.');
        $userid = $this->verifyLogin($data);

        $publish['goods_name'] = isset($data['goods_name']) ? $this->str_filter($data['goods_name']) : '';
        $publish['designer_name'] = isset($data['designer_name']) ? $this->str_filter($data['designer_name']) : '';
        $publish['season'] = isset($data['season']) ? $this->str_filter($data['season']) : '';
        $publish['price'] = isset($data['price']) ? $this->str_filter($data['price']) : 0;
        $publish['goods_type'] = isset($data['goods_type']) ? $this->str_filter($data['goods_type']) : '';
        $publish['goods_story'] = isset($data['goods_story']) ? $this->str_filter($data['goods_story']) : '';
        $publish['goods_img'] = isset($data['goods_img']) ? json_encode($data['goods_img'],64) : '';
        $publish['size_chart'] = isset($data['size_chart']) ? json_encode($data['size_chart'],64) : '';
        $publish['material'] = isset($data['material']) ? $this->str_filter($data['material']) : '';
        $publish['reg_time'] = time();
        $publish['user_id'] = $userid;

        if(!$publish['goods_name'] || !$publish['price'] || !$publish['goods_story']){
            return $this->json_error_msg('资料不齐全');
        }

        if(db('designer_publish')->insert($publish)){
            return $this->json_success_msg('提交成功，等待审核发布');
        }else{
            return $this->json_error_msg('提交失败，请稍后再试');
        }
    }

    /**
     * 设计师详情
     */
    public function detail()
    {
        $data = input('post.');
        $this->verifyLogin($data);
        $designer_id =$data['designer_id'];

        //分页
        $pageNum = 10;//每页10条数据
        $page = $data['page'] ?? 1;
        $page = ($page < 1) ? 1 : (int)$page;
        $start = ($page-1)*$pageNum;
        $begin =isset($start) ?$start :0;

        //去拿设计师的头像
        $head_img =db('users')
                    ->where('user_id',$designer_id)
                    ->field('head_pic')
                    ->find();

        $nickname =db('join_apply')
                    ->alias('d')
                    ->where('d.user_id',$designer_id)
                    ->field('d.designer')
                    ->find();
        $num =db('goods')->where('user_id',$designer_id)->select();
        $nums =count($num);
        $designer[] =['head_pic'=>$head_img['head_pic'],'designerName'=>$nickname['designer'],'goods_num'=>$nums];

        $resource_pre = getConfig('resource_pre');
        $info = [];
        foreach ($designer as $k => $v) {
            $img_url = complete_url($v['head_pic'], $resource_pre);
            $info[$k]['goods_num'] = $v['goods_num'];
            $info[$k]['designerName'] = $v['designerName'];
            $info[$k]['img_url'] = $img_url;
        }

        //设计师发布的商品

        $goods = db('goods')
            ->alias('g')
            ->join('goods_category c', 'g.cat_id =c.id')
            ->join('join_apply j', 'g.user_id = j.user_id')
            ->where('g.user_id',$designer_id)
            ->field('g.goods_id,g.goods_name,g.original_img,g.store_count,g.is_new,g.is_hot,g.price,g.sales_sum,g.last_update,g.pre_sale_num,
                g.pre_sale_start_time,g.pre_sale_end_time,g.is_complete,g.cat_id,c.name  as cat_name,j.brand,j.designer')
            ->order('g.goods_id desc')
            ->limit($begin,$pageNum)
            ->select();

        //组合数据
        $resource_pre = getConfig('resource_pre');
        $list = [];
        foreach ($goods as $k => $v) {
            $img_url = complete_url($v['original_img'], $resource_pre);
            $list[$k]['goods_id'] = $v['goods_id'];
            $list[$k]['goods_name'] = $v['goods_name'];
            $list[$k]['store_count'] = $v['store_count'];
            $list[$k]['price'] = $v['price'];
            $list[$k]['sales_sum'] = $v['sales_sum'];
            $list[$k]['last_update'] = $v['last_update'];
            $list[$k]['pre_sale_num'] = $v['pre_sale_num'];
            $list[$k]['is_complete'] = $v['is_complete'];
            $list[$k]['pre_sale_start_time'] = $v['pre_sale_start_time'];
            $list[$k]['pre_sale_end_time'] = $v['pre_sale_end_time'];
            //判断
            $time = $v['pre_sale_end_time'] - time();
            if ($time) {
                $list[$k]['final_days'] = ceil($time / (3600 * 24));
                if ($list[$k]['final_days'] <= 0) {
                    $list[$k]['final_days'] = 0;
                }
            }
            $list[$k]['category_id'] = $v['cat_id'];
            $list[$k]['category_name'] = $v['cat_name'];
            $list[$k]['brand_name'] = $v['brand'];
            $list[$k]['nickname'] = $v['designer'];
            $list[$k]['img_url'] = $img_url;
        }
        $all =['info'=>$info['0'],'goods_info'=>$list];

        if ($all) {
            $this->api_return($all);
        }else{
            $this->json_success_msg('没有数据');
        }

    }


    /**
     *已发布商品
     */
    public function published_goods()
    {
        $data = input('post.');
        $user_id = $this->verifyLogin($data);

        //分页
        $pageNum = 10;//每页10条数据
        $page = $data['page'] ?? 1;
        $page = ($page < 1) ? 1 : (int)$page;
        $start = ($page-1)*$pageNum;
        $begin =isset($start) ?$start :0;

        $something =isset($data['something'])?$data['something']:0;
        $something =$this->str_filter($something);
        $where['is_show'] =['=',1];
        $where['user_id'] =['=',$user_id];
        //2次判断请求
        if($something =="worm"){
            $where['g.pre_sale_start_time'] =['>',time()];
            $where['g.is_on_sale'] =['=',1];
            $goods = db('goods')
                ->alias('g')
                ->where($where)
                ->field('g.goods_id,g.goods_name,g.original_img,g.sales_sum,g.pre_sale_start_time,g.pre_sale_end_time,
                g.is_hot,g.is_on_sale,g.price')
                ->limit($begin,$pageNum)
                ->order('goods_id desc')
                ->select();
            $goods =$this->designerChanges($goods);
            $this->returns($goods);
        }elseif ($something=="presale"){
            $where['g.pre_sale_start_time'] =['<=',time()];
            $where['g.pre_sale_end_time'] =['>=',time()];
            $where['g.is_on_sale'] =['=',1];
            $goods = db('goods')
                ->alias('g')
                ->where($where)
                ->field('g.goods_id,g.goods_name,g.original_img,g.sales_sum,g.pre_sale_start_time,g.pre_sale_end_time,
                g.is_hot,g.is_on_sale,g.price')
                ->order('goods_id desc')
                ->limit($begin,$pageNum)
                ->select();
            $goods =$this->designerChanges($goods);
            $this->returns($goods);
        }elseif ($something=="hot"){
            $where['g.is_on_sale'] =['=',1];
            $goods = db('goods')
                ->alias('g')
                ->where($where)
                ->field('g.goods_id,g.goods_name,g.original_img,g.sales_sum,g.pre_sale_start_time,g.pre_sale_end_time,
                g.is_hot,g.is_on_sale,g.price')
                ->order('sales_sum desc')
                ->limit($begin,$pageNum)
                ->select();
            $goods =$this->designerChanges($goods);
            $this->returns($goods);
        }elseif ($something=="obtained"){
            $where['g.is_on_sale'] =['=',0];
            $goods = db('goods')
                ->alias('g')
                ->where($where)
                ->field('g.goods_id,g.goods_name,g.original_img,g.sales_sum,g.pre_sale_start_time,g.pre_sale_end_time,
                g.is_hot,g.is_on_sale,g.price')
                ->order('goods_id desc')
                ->limit($begin,$pageNum)
                ->select();
            $goods =$this->designerChanges($goods);
            $this->returns($goods);
        }else{
            //预热中 预售中 热卖 已下架
            $worms['g.pre_sale_start_time'] =['>',time()];
            $worms['g.is_on_sale'] =['=',1];

            $worm = db('goods')
                ->alias('g')
                ->where($where)
                ->where($worms)
                ->field('g.goods_id,g.goods_name,g.original_img,g.sales_sum,g.pre_sale_start_time,g.pre_sale_end_time,
                g.is_hot,g.is_on_sale,g.price')
                ->limit($begin,$pageNum)
                ->order('goods_id desc')
                ->select();
            //预售中
            $presales['g.pre_sale_start_time'] =['<=',time()];
            $presales['g.pre_sale_end_time'] =['>=',time()];
            $presales['g.is_on_sale'] =['=',1];
            $presale = db('goods')
                ->alias('g')
                ->where($where)
                ->where($presales)
                ->field('g.goods_id,g.goods_name,g.original_img,g.sales_sum,g.pre_sale_start_time,g.pre_sale_end_time,
                g.is_hot,g.is_on_sale,g.price')
                ->order('goods_id desc')
                ->limit($begin,$pageNum)
                ->select();
            //热卖
            $hots['g.is_on_sale'] =['=',1];
            $hot = db('goods')
                ->alias('g')
                ->where($where)
                ->where($hots)
                ->field('g.goods_id,g.goods_name,g.original_img,g.sales_sum,g.pre_sale_start_time,g.pre_sale_end_time,
                g.is_hot,g.is_on_sale,g.price')
                ->order('sales_sum desc')
                ->limit($begin,$pageNum)
                ->select();
            //下架
            $sales['g.is_on_sale'] =['=',0];
            $sale = db('goods')
                ->alias('g')
                ->where($where)
                ->where($sales)
                ->field('g.goods_id,g.goods_name,g.original_img,g.sales_sum,g.pre_sale_start_time,g.pre_sale_end_time,
                g.is_hot,g.is_on_sale,g.price')
                ->order('goods_id desc')
                ->limit($begin,$pageNum)
                ->select();
        }

        $worm_list =$this->designerChanges($worm);
        $pre_list =$this->designerChanges($presale);
        $hot_list =$this->designerChanges($hot);
        $sale_list =$this->designerChanges($sale);
        $list =['worm'=>$worm_list,'presale'=>$pre_list,'hot'=>$hot_list,'obtained'=>$sale_list];

        //返回
        if ($list) {
            $this->api_return($list);
        } else {
            $this->json_error_msg('未发布商品');
        }

    }

    /**
     * 设计师已发布公用转换方法
     * @param $goods
     * @return array
     */
    public function designerChanges($goods)
    {

        $resource_pre = getConfig('resource_pre');
        $list = [];
        foreach ($goods as $k => $v) {
            $img_url = complete_url($v['original_img'], $resource_pre);


            $list[$k]['goods_name'] = $v['goods_name'];
            $list[$k]['sales_sum'] = $v['sales_sum'];
            $list[$k]['pre_sale_end_time'] = $v['pre_sale_end_time'];
            //判断在预热中
            if (time() < $v['pre_sale_start_time']) {
                $list[$k]['is_worm'] = 0;
            }
            //预售中
            if (time() >= $v['pre_sale_start_time'] && time() <= $v['pre_sale_end_time']) {
                $list[$k]['is_worm'] = 1;
            }
            //过期
            if (time() > $v['pre_sale_end_time']) {
                $list[$k]['is_worm'] = 2;
            }

            $list[$k]['is_hot'] = $v['is_hot'];
            $list[$k]['is_on_sale'] = $v['is_on_sale'];
            $list[$k]['price'] = $v['price'];

            $list[$k]['img_url'] = $img_url;
        }
        return $list;

    }


}