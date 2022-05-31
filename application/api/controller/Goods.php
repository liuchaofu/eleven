<?php
/**
 * Created by PhpStorm
 * User: Brian
 * Date: 2019/8/14
 * Time: 11:57
 */

namespace app\api\controller;
use Redis\RedisPackage;

class Goods extends Common
{


    /**
     * 商品详情
    */
    public function goodsInfo(){
        $goodsInfoData = [];
        $data = input('post.');
        $this->verifySign($data);
        //$data['goods_id']=22;//test
        $goods_id = $data['goods_id'] ?? 0;
        $user_id = $data['userid'] ?? 0;//非必传参数，用户展示用户收藏
        if(!$goods_id){
            return $this->json_error_msg('未知商品ID');
        }
        $resource_pre = getConfig('resource_pre');
        //redis缓存
        $Redis = new RedisPackage();
        $Redis_goodsKey = 'goods_info_id_'.$goods_id;
        $Redis_expire = config('redis_expire.goods_info');//缓存时间

        $Redis_goodsInfo = $Redis::get($Redis_goodsKey);
        if($Redis_goodsInfo){
            //缓存存在
            $goodsInfoData = json_decode($Redis_goodsInfo,true);
            //重新读取库存等及时信息。
            $goods_info = db('goods')
                ->where(['goods_id'=>$goods_id,'is_show'=>1])
                ->field('store_count,price,collect_sum,is_on_sale,sales_sum,pre_sale_num')
                ->find();
            if(!$goods_info){
                return $this->json_error_msg('商品已下线');
            }
            $goodsInfoData['goods_info']['store_count'] = $goods_info['store_count'];
            $goodsInfoData['goods_info']['price'] = $goods_info['price'];
            $goodsInfoData['goods_info']['collect_sum'] = $goods_info['collect_sum'];
            $goodsInfoData['goods_info']['is_on_sale'] = $goods_info['is_on_sale'];
            $goodsInfoData['goods_info']['sales_sum'] = $goods_info['sales_sum'];
            $goodsInfoData['goods_info']['pre_sale_num'] = $goods_info['pre_sale_num'];
            //商品收藏状态
            if(db('goods_collect')->where(['user_id'=>$user_id,'goods_id'=>$goods_id])->find()){
                //已收藏
                $goodsInfoData['goods_info']['collect_status'] = true;
            }else{
                $goodsInfoData['goods_info']['collect_status'] = false;
            }

            return $this->api_return($goodsInfoData);
        }else{
            //缓存不存在
            $goods_info = db('goods')
                ->alias('g')
                ->join('users u','g.user_id = u.user_id','LEFT')
                ->join('join_apply j','g.user_id = j.user_id','LEFT')
                ->join('goods_category c','g.cat_id = c.id','LEFT')
                ->where(['g.goods_id'=>$goods_id,'g.is_show'=>1])
                ->field('g.goods_id,g.cat_id,g.goods_name,g.brand_id,g.user_id,g.last_update,
            g.is_pre_sale,g.pre_sale_num,g.original_img,g.sales_sum,g.pre_sale_start_time,
            g.pre_sale_end_time,g.is_on_sale,g.model_id,g.video,g.video_img,g.goods_content,
            g.store_count,g.goods_remark,g.size_img,g.style_match,g.material,g.price,g.is_complete,
            g.collect_sum,
            j.designer nickname,u.head_pic,
            c.name as category_name,c.pid')
                ->find();
            if(!$goods_info){
                return $this->json_error_msg('商品已下线');
            }
            //查询父级分类名称
            $p_category = db('goods_category')->where(['id'=>$goods_info['pid']])->field('name')->find();
            $goods_info['p_category_name'] = $p_category['name'] ?? '';
            $goods_info['goods_content'] = preg_replace("/(<img .*?src=\")((?!https?).*?)(\".*?>)/is","\${1}$resource_pre\${2}\${3}",$goods_info['goods_content']);//完善富文本里的图片路径
            $material = json_decode($goods_info['material'],true);
            $material = $material ? : [];
            unset($goods_info['material']);
            //计算结束时间
            $end_time = (int)$goods_info['pre_sale_end_time'] - time();
            $end_time = ($end_time < 0) ? 0 : $end_time;
            $goods_info['final_days'] = ceil($end_time/(24*3600));
            //处理媒体链接
            $goods_info['video'] = complete_url($goods_info['video'],$resource_pre);
            $goods_info['head_pic'] = complete_url($goods_info['head_pic'],$resource_pre);
            $goods_info['original_img'] = complete_url($goods_info['original_img'],$resource_pre);
            $goods_info['video_img'] = complete_url($goods_info['video_img'],$resource_pre);
            $goods_info['size_img'] = complete_url($goods_info['size_img'],$resource_pre);
            $goodsInfoData['goods_info'] = $goods_info; //商品基础信息
            //商品收藏状态
            if(db('goods_collect')->where(['user_id'=>$user_id,'goods_id'=>$goods_id])->find()){
                //已收藏
                $goodsInfoData['goods_info']['collect_status'] = true;
            }else{
                $goodsInfoData['goods_info']['collect_status'] = false;
            }

            //工艺披露
            foreach ($material as $k=>$v){
                $material[$k]['url'] = complete_url($v['url'],$resource_pre);
            }
            $goodsInfoData['material'] = $material;
            //查询商品搭配
            $goods_match = [];
            $style_match = json_decode($goods_info['style_match'],true);
            if($style_match){
                $goods_match = db('goods')->where(['goods_id'=>['IN',$style_match],'is_show'=>1])->field('goods_id,goods_name,original_img,price')->select();
                foreach ($goods_match as $k=>$v){
                    $goods_match[$k]['original_img'] = complete_url($v['original_img'],$resource_pre);
                }

            }
            $goodsInfoData['goods_match']  = $goods_match;
            //查询商品模型信息
            $model_info = [];
            $model_id = $goods_info['model_id'];
            //查询商品设置过的所有属性id
            $allSpec = db('spec_goods_price')->where('goods_id',$goods_id)->field('key')->select();
            $allSpecArr = [];
            foreach ($allSpec as $row){
                $childArr = explode('_',$row['key']);
                $allSpecArr = array_merge($allSpecArr,$childArr);
            }
            $allSpecArr = array_unique($allSpecArr);//去重得到当前goods具有的所有属性
            if($model_id){
                $spec = db('spec')->where('model_id',$model_id)->order('order desc')->select();
                foreach ($spec as $k =>$v){
                    $model_info[$k]['modelName'] = $v['name'];
                    $model_info[$k]['id'] = $v['id'];
                    $model_info[$k]['hasImg'] = $v['is_upload_image'];
                    if($model_info[$k]['hasImg']) {
                        //联表查询颜色图片
                        $spec_item = db('spec_item')->alias('sItem')
                            ->join('spec_image sImg', 'sItem.id = sImg.spec_item_id AND sImg.goods_id = '.$goods_id,'LEFT')
                            ->where(['sItem.spec_id' => $v['id'],'sItem.id'=>['in',$allSpecArr]])
                            ->field('sItem.id,sItem.item,sImg.src as img')->select();

                        foreach ($spec_item as $i=>$j){
                            //如果存在未设置的图片则启用商品主图
                            $spec_img = $j['img'] ? $j['img'] : $goods_info['original_img'] ;
                            $spec_item[$i]['img'] = complete_url($spec_img,$resource_pre);
                        }
                        $model_info[$k]['spec_item'] = $spec_item;
                    }else {
                        $spec_item = db('spec_item')->where(['spec_id'=> $v['id'],'id'=>['in',$allSpecArr]])->field('id,item')->select();
                        $model_info[$k]['spec_item'] = $spec_item;
                    }

                }
            }
            $goodsInfoData['model_info']  = $model_info;
            //记录缓存
            $Redis::set($Redis_goodsKey,json_encode($goodsInfoData),$Redis_expire);
            return $this->api_return($goodsInfoData);
        }

    }

    public function goodsCollect(){
        $data = input('post.');
        $user_id = $this->verifyLogin($data);
        $goods_id = $data['goods_id'] ?? 0;
        $action = $data['action'] ?? 0; //0取消1收藏
        if(!$user_id || !$goods_id ){
            return $this->json_error_msg('请先登录');
        }
        $map = ['user_id'=>$user_id,'goods_id'=>$goods_id];
        if($action){
            if(db('goods_collect')->where($map)->find()){
                //已收藏
                return $this->json_success_msg('收藏成功');
            }else{
                $insertData=[
                    'user_id'=>$user_id,
                    'goods_id'=>$goods_id,
                    'add_time'=>time(),
                ];
                if(db('goods_collect')->insert($insertData)){
                    return $this->json_success_msg('收藏成功');
                }
            }
        }else{
            if(db('goods_collect')->where($map)->delete()){
                return $this->json_success_msg('取消成功');
            }
        }


        return $this->json_error_msg('操作失败请稍后再试');
    }


    /**
     * 查询商品规格价格信息
    */
    public function getGoodsItemInfo(){
        $data = input('post.');
        $this->verifySign($data);
        /*$data['goods_id']=22;//test
        $data['model_id']=3;//test
        $data['item_ids'] =[22,26,32];
        $data['spec_ids'] =[12,13,14];*/


        $goods_id = $data['goods_id'] ?? 0;
        $model_id = $data['model_id'] ?? 0;
        $item_ids = $data['item_ids'] ?? [];
        asort($item_ids);
        $spec_ids = $data['spec_ids'] ?? [];
        if(!$goods_id || !$model_id ){
            return $this->json_error_msg('未知商品');
        }
        //判断属性是否已全选
        $specNotIn = db('spec')->where(['model_id'=>$model_id])->whereNotIn('id',$spec_ids)->field('id,name')->select();
        if($specNotIn){
            $ermsg = '';
            foreach ($specNotIn as $row){
                $ermsg.=$row['name'].' ';
            }
            return $this->json_error_msg('请选择: '.$ermsg);
        }
        $spec_ids_str =  implode('_',$item_ids);
        $goods_price = db('spec_goods_price')->where(['goods_id'=>$goods_id,'key'=>$spec_ids_str])->field('item_id as price_item_id,price,store_count')->find();
        if($goods_price){
            return $this->api_return($goods_price);
        }else{
            return $this->json_error_msg('商品库存不足！');
        }

    }

    /**
     * 商品评论列表
    */
    public function goodsCommentList(){
        $pageNum = 3;//每页3条数据
        $data = input('post.');
        $this->verifySign($data);
        //$data['goods_id']=1;//test
        $page = $data['page'] ?? 1;
        $page = ($page < 1) ? 1 : (int)$page;
        $begin = ($page-1)*$pageNum;
        $goods_id = $data['goods_id'] ?? 0;
        if(!$goods_id){
            return $this->json_error_msg('未知商品ID');
        }
        $comment = db('comment')
            ->alias('c')
            ->join('users u','c.user_id = u.user_id')
            ->where(['c.goods_id'=>$goods_id,'c.is_show'=>1])
            ->field('c.comment_id,c.goods_id,c.is_anonymous,c.add_time,c.content,u.nickname,u.head_pic')
            ->order('c.add_time desc')
            ->limit($begin,$pageNum)
            ->select();
        $resource_pre = getConfig('resource_pre');
        foreach ($comment as $k=>$v){
            $img_url = complete_url($v['head_pic'],$resource_pre);
            $comment[$k]['head_pic'] = $img_url;
            if($v['is_anonymous']){
                $comment[$k]['nickname'] = '匿名用户';
                $comment[$k]['head_pic'] = complete_url(config('apiImg.anonymous'),$resource_pre);
            }
        }
        return $this->api_return($comment);
    }

    /**
     * 更多每日上新
    */
    public function moreDailyNew(){
        $pageNum = 10;//每页10条数据
        $data = input('post.');
        $this->verifySign($data);
        $page = $data['page'] ?? 1;
        if(!isset($data['nav_id'])){
            return $this->json_error_msg('未知导航ID');
        }
        $nav_id = $data['nav_id'];

        $page = ($page < 1) ? 1 : (int)$page;
        $begin = ($page-1)*$pageNum;
        $goodsList = $this->get_goods_list(
            ['g.is_show'=>1,'g.is_on_sale'=>1,'g.nav_id'=>$nav_id],
            $pageNum,$begin
        );

        return $this->api_return($goodsList);
    }
    /**
     * 更多推荐
     */
    public function moreRecommend(){
        $pageNum = 10;//每页10条数据
        $data = input('post.');
        $this->verifySign($data);
        $page = $data['page'] ?? 1;
        if(!isset($data['nav_id'])){
            return $this->json_error_msg('未知导航ID');
        }
        $nav_id = $data['nav_id'];

        $page = ($page < 1) ? 1 : (int)$page;
        $begin = ($page-1)*$pageNum;
        $goodsList = $this->get_goods_list(
            ['g.is_show'=>1,'g.is_on_sale'=>1,'g.nav_id'=>$nav_id,'g.is_recommend'=>1],
            $pageNum,$begin
        );

        return $this->api_return($goodsList);
    }
    /**
     * 必买好货
    */
    public function mustBuys(){
        $pageNum = 10;//每页10条数据
        $data = input('post.');
        $this->verifySign($data);
        $page = $data['page'] ?? 1;
        if(!isset($data['nav_id'])){
            return $this->json_error_msg('未知导航ID');
        }
        $nav_id = $data['nav_id'];

        $page = ($page < 1) ? 1 : (int)$page;
        $begin = ($page-1)*$pageNum;
        $goodsList = $this->get_goods_list(
            ['g.is_show'=>1,'g.is_on_sale'=>1,'g.nav_id'=>$nav_id,'g.is_hot'=>1],
            $pageNum,$begin
        );

        return $this->api_return($goodsList);
    }

    /**
     * get_goods_list商品列表展示
     * 表对应关系 goods-g    users-u     goods_category-c
     * @param $where array
     * @param $length int
     * @param $order string 排序
     * @param $offset int
     * @return array
     */
    public function get_goods_list($where,$length,$offset=0,$order='g.last_update desc'){
        if(empty($where) || empty($length)){
            return [];
        }

        $goods_list = db('goods')
            ->alias('g')
            ->join('users u','g.user_id = u.user_id','LEFT')
            ->join('join_apply j','g.user_id = j.user_id','LEFT')
            ->join('goods_category c','g.cat_id = c.id','LEFT')
            ->where($where)
            /*->where(function($query){ //非预售时间范围内不展示
                $query->where(['g.is_pre_sale'=>0]) //非预售
                    ->whereOr(function($query){
                        $query->where(['g.is_pre_sale'=>1,'g.pre_sale_start_time'=>['<',time()],'g.pre_sale_end_time'=>['>',time()]]); //预售需要查询时间
                    });
            })*/
            ->field('g.goods_id,g.price,g.cat_id,g.goods_name,g.brand_id,g.user_id,g.last_update,
            g.is_pre_sale,g.pre_sale_num,g.original_img,g.sales_sum,g.pre_sale_start_time,g.pre_sale_end_time,
            g.is_complete,g.store_count,
            j.designer nickname,c.name as category_name')
            ->order($order)
            ->limit($offset,$length)
            ->select();
        $resource_pre = getConfig('resource_pre');
        foreach ($goods_list as $k=>$v){
            $end_time = (int)$v['pre_sale_end_time'] - time();
            $end_time = ($end_time < 0) ? 0 : $end_time;
            $goods_list[$k]['final_days'] = ceil($end_time/(24*3600));
            $img_url = complete_url($v['original_img'],$resource_pre);
            $goods_list[$k]['img_url'] = $img_url;
        }
        return $goods_list;
    }


}