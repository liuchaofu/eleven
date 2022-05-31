<?php

namespace app\api\controller;


class Search extends Common
{
    //热门搜索   标识
    public function search_hot()
    {
        //先验证签名
        $sign = input('post.');
        $this->verifySign($sign);

        $Redis = new RedisPackage();
        $Redis_searchKey = 'search_key_words';
        $Redis_searchKey_expire = config('redis_expire.search_key_words');
        $searchKey = $Redis::get($Redis_searchKey);
        if($searchKey){
            $list = json_decode($searchKey,true);
        }else{
            //查询最新7个
            $list = db('config')->where('name', 'hot_keywords')->find();

            //查询个数 数据多少个数
            $hot_num = db('config')->where('name', 'hot_num')->find();
            $hot_num = $hot_num['value'];


            //配置为空 查search_log里面取出7条
            if (empty($list['value'])) {
                $list = db('search_log')
                    ->field('keys_word,count(*) as total')
                    ->whereTime('reg_time', '>', 'this week')
                    ->group('keys_word')
                    ->order('total desc')
                    ->limit($hot_num)
                    ->select();
            } else {
                //json转出
                $list = json_decode($list['value'], true);

                //整合结果 改名
                $arr = array();
                $i = 0;
                foreach ($list as $k => $v) {
                    $arr[$i]['keys_word'] = $v['value'];
                    $arr[$i]['sort'] = $v['listorder'];
                    $arr[$i]['total'] = $v['total'];
                    $i++;
                }
                //
                $result = db('search_log')
                    ->field('keys_word,count(*) as total')
                    ->whereTime('reg_time', '>', 'this week')
                    ->group('keys_word')
                    ->order('total desc')
                    ->limit($hot_num)
                    ->select();
                //排序
                foreach ($arr as $key => $item) {
                    //从0开始截取到最后一位
                    $array1 = array_slice($result, 0, $item['sort'] - 1);
                    $array2 = array_slice($result, $item['sort'] - 1);
                    //整合
                    $result = array_merge($array1, array($key => $item), $array2);
                }
                //从数据库读出拿多少个
                $list = array_slice($result, 0, $hot_num);

            }
            $Redis::set($Redis_searchKey,json_encode($list),$Redis_searchKey_expire);
        }


        //多个分类
        //查询数据
        $Redis_categoryKey = 'search_goods_category';
        $Redis_category_expire = config('redis_expire.goods_category');
        $categoryList = $Redis::get($Redis_categoryKey);
        if($categoryList) {
            $categoryList = json_decode($categoryList,true);
        }else{
            $cate = db('goods_category')->field('id,name,pid,sort')->where('is_show', 1)->order('sort asc')->select();
            $categoryList = $this->array2tree($cate, 'pid', 'child');
            $Redis::set($Redis_categoryKey,json_encode($categoryList),$Redis_category_expire);
        }
        $all = ['hot' => $list, 'cate' => $categoryList];

        //返回
        if ($all) {
            $this->api_return($all);
        }
        $this->json_error_msg('热搜榜显示失败');

    }

    //搜索框
    public function search_goods()
    {
        //先验证签名
        $data = input('post.');
        $user = $this->verifySign($data);

        //分页
        $pageNum = 10;//每页10条数据
        $page = $data['page'] ?? 1;
        $page = ($page < 1) ? 1 : (int)$page;
        $start = ($page-1)*$pageNum;
        $begin =isset($start) ?$start :0;

        //传来的值 可以是商品名称/商品类型名称/关键字
        $user_id = $user ? $user : 0;

        //判断是不是点击了分类id
        if (isset($data['cat_id'])) {
            $cate_id = $data['cat_id'] ? $data['cat_id'] : 0;
        } else {
            $cate_id = 0;
        }

        if (isset($data['search'])) {
            $get = trim($data['search']) ? trim($data['search']) : 0;
        } else {
            $get = 0;
        }
        $something = $this->str_filter($get);

        if (empty($data['cat_id']) && empty($data['search'])) {
            $this->json_error_msg('请输入关键词');
        }

        //去搜索
        if ($cate_id) {
            $all = db('goods')
                ->alias('g')
                ->join('goods_category c', 'g.cat_id =c.id')
                ->join('join_apply j', 'g.user_id = j.user_id')
                ->field('g.goods_id,g.goods_name,g.original_img,g.store_count,g.is_new,g.is_hot,g.price,g.sales_sum,g.last_update,g.pre_sale_num,
                g.pre_sale_start_time,g.pre_sale_end_time,g.is_complete,g.cat_id,c.name  as cat_name,j.brand,j.designer')
                ->whereIn('g.cat_id', $cate_id)
                ->order('g.goods_id desc')
                ->limit($begin,$pageNum)
                ->select();
        } else {
            //查询所有数据 按时间倒序排序
            $all = db('goods')
                ->alias('g')
                ->join('goods_category c', 'g.cat_id =c.id')
                ->join('join_apply j', 'g.user_id = j.user_id')
                ->whereor("g.goods_name", "like", "%{$something}%")
                ->whereor("g.keywords", "like", "%{$something}%")
                ->field('g.goods_id,g.goods_name,g.original_img,g.store_count,g.is_new,g.is_hot,g.price,g.sales_sum,g.last_update,g.pre_sale_num,
            g.pre_sale_start_time,g.pre_sale_end_time,g.is_complete,g.cat_id,c.name  as cat_name,j.brand,j.designer')
                ->order('g.goods_id desc')
                ->limit($begin,$pageNum)
                ->select();
        }


        foreach ($all as $k => $v) {
            $img_url = complete_url($v['original_img'], $resource_pre);


            $list[$k]['goods_id'] = $v['goods_id'];
            $list[$k]['goods_name'] = $v['goods_name'];
            $list[$k]['store_count'] = $v['store_count'];
            if ($v['is_new'] == 0) {
                $list[$k]['is_new'] = '非新品';
            } elseif ($v['is_new'] == 1) {
                $list[$k]['is_new'] = '新品';
            }

            if ($v['is_hot'] == 0) {
                $list[$k]['is_hot'] = '非热销';
            } elseif ($v['is_hot'] == 1) {
                $list[$k]['is_hot'] = '热销';
            }
            $list[$k]['price'] = $v['price'];
            $list[$k]['sales_sum'] = $v['sales_sum'];
            $list[$k]['last_update'] = $v['last_update'];
            $list[$k]['pre_sale_num'] = $v['pre_sale_num'];
            $list[$k]['is_complete'] = $v['is_complete'];

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


        //组合数据
        $all_data = ['res' => $list];
        $log['user_id'] = $user_id;
        $log['keys_word'] = $something;
        $log['reg_time'] = time();

        //添加日志进搜索日志
        $res = db('search_log')->insert($log);

        if ($res) {
            //返回
            if ($all_data) {
                $this->api_return($all_data);
            }
        }

        $this->json_error_msg('搜索失败，请稍后再试');

    }

    //点击之后加载
    public function optional_search()
    {

        //先验证签名
        $data = input('post.');
        $this->verifySign($data);

        //判断是搜索的词语 还是分类点进来的
        $cate_id = isset($data['cat_id']) ? $data['cat_id'] : "";
        $something = isset($data['search']) ? $data['search'] : "";

        //全部为空的限制前端给
        if (isset($data['brand']) || isset($data['money'])  ||isset($data['category_id'])) {
            //分页
            $pageNum = 10;//每页10条数据
            $page = $data['page'] ?? 1;
            $page = ($page < 1) ? 1 : (int)$page;
            $start = ($page-1)*$pageNum;
            $begin =isset($start) ?$start :0;

            //where
            $like['g.goods_name'] =['like',"%{$something}%"];
            $like['g.keywords'] =['like',"%{$something}%"];
            $where =[];

            //分类id
            if (isset($data['category_id'])) {
                $where['c.id'] = ['in', $data['category_id']];
            } else {

            }
            //品牌名字
            if (isset($data['brand']) ){
                $where['j.brand'] = ['in', $data['brand']];
            }else{

            }

            if(isset($data['money'])){
                //价格 1-6
                if ($data['money'] == 1) {
                    $where['g.price'] = ['between', '0,500'];
                } elseif ($data['money'] == 2) {
                    $where['g.price'] = ['between', '500,1000'];
                } elseif ($data['money'] == 3) {
                    $where['g.price'] = ['between', '1000,1500'];
                } elseif ($data['money'] == 4) {
                    $where['g.price'] = ['between', '1500,2000'];
                } elseif ($data['money'] == 5) {
                    $where['g.price'] = ['between', '2000,3000'];
                } elseif ($data['money'] == 6) {
                    $where['g.price'] = ['>', '3000'];
                }else{

                }
            }

            if ($something) {
                //查询所有数据 按时间倒序排序
                $all = db('goods')
                    ->alias('g')
                    ->join('goods_category c', 'g.cat_id =c.id')
                    ->join('join_apply j', 'g.user_id = j.user_id')
                    ->field('g.goods_id,g.goods_name,g.original_img,g.store_count,g.is_new,g.is_hot,g.price,g.sales_sum,g.last_update,g.pre_sale_num,
            g.pre_sale_start_time,g.pre_sale_end_time,g.is_complete,g.cat_id,c.name  as cat_name,j.brand,j.designer')
                    ->where($where)
                    ->where(function ($q) use($like) {
                        $q->whereOr($like);
                    })
                    ->order('g.goods_id desc')
                    ->limit($begin,$pageNum)
                    ->select();
            } else {
                //传的category_id
                $all = db('goods')
                    ->alias('g')
                    ->join('goods_category c', 'g.cat_id =c.id')
                    ->join('join_apply j', 'g.user_id = j.user_id')
                    ->field('g.goods_id,g.goods_name,g.original_img,g.store_count,g.is_new,g.is_hot,g.price,g.sales_sum,g.last_update,g.pre_sale_num,
                g.pre_sale_start_time,g.pre_sale_end_time,g.is_complete,g.cat_id,c.name  as cat_name,j.brand,j.designer')
                    ->whereIn('g.cat_id', $cate_id)
                    ->where($where)
                    ->order('g.goods_id desc')
                    ->limit($begin,$pageNum)
                    ->select();

            }

            //图片转换

            $resource_pre = getConfig('resource_pre');
            $list =[];
            foreach ($all as $k=>$v){
                $img_url = complete_url($v['original_img'],$resource_pre);
                $list[$k]['goods_id'] = $v['goods_id'];
                $list[$k]['goods_name'] = $v['goods_name'];
                $list[$k]['store_count'] = $v['store_count'];

                if ($v['is_new'] == 0) {
                    $list[$k]['is_new'] = '非新品';
                } elseif ($v['is_new'] == 1) {
                    $list[$k]['is_new'] = '新品';
                }
                if ($v['is_hot'] == 0) {
                    $list[$k]['is_hot'] = '非热销';
                } elseif ($v['is_hot'] == 1) {
                    $list[$k]['is_hot'] = '热销';
                }

                $list[$k]['price'] = $v['price'];
                $list[$k]['sales_sum'] = $v['sales_sum'];
                $list[$k]['last_update'] = $v['last_update'];
                $list[$k]['pre_sale_num'] = $v['pre_sale_num'];
                $list[$k]['nickname'] = $v['designer'];
                //判断
                $time = $v['pre_sale_end_time'] - time();
                if ($time) {
                    $list[$k]['final_days'] = ceil($time / (3600 * 24));
                    if ($list[$k]['final_days'] <= 0) {
                        $list[$k]['final_days'] = 0;
                    }
                }

                $list[$k]['is_complete'] = $v['is_complete'];
                $list[$k]['category_id'] = $v['cat_id'];
                $list[$k]['category_name'] = $v['cat_name'];
                $list[$k]['brand'] = $v['brand'];
                $list[$k]['designer'] = $v['designer'];
                $list[$k]['img_url'] = $img_url;

            }
            $all =$list;

            if(empty($all)){
                $this->json_error_msg('没有该数据，请再次选择');
            }

        }else{

            //商品类型
            $type[] = ['complete' => '成品'];

            //商品分类
            $all_cate = db('goods_category')
                ->where('is_show', '1')
                ->select();
            $cate = $this->array2tree($all_cate, 'pid', 'child');

            //设计师品牌 限制审核通过了
            $brand = db('join_apply')
                ->field('brand')
                ->where('status', 3)
                ->select();
            //价格
            $price = [['money' => '0-500'], ['money' => '500-1k'], ['money' => '1k-1.5k'], ['money' => '1.5k-2k'], ['money' => '2k-3k'], ['money' => '3k以上']];

            $all = ['type' => $type, 'cate' => $cate, 'brand' => $brand, 'price' => $price];
        }

        //返回
        if ($all) {
            $this->api_return($all);
        } else {
            $this->json_error_msg('数据加载有误，请稍后再试');
        }
    }


}
