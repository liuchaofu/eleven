<?php
/**
 * Created by PhpStorm
 * User: Brian
 * Date: 2019/8/6
 * Time: 9:40
 */

namespace app\api\controller;


class Category extends Common
{
    //分类数据
    public function cateList()
    {
        //验证登录
        $data = input('post.');
        $this->verifyLogin($data);

        //查出所有分类
        $all_cate =db('goods_category')->select();

        //转换
        $resource_pre = getConfig('resource_pre');
        foreach ($all_cate as $k=>$v){
            $img_url = complete_url($v['image'],$resource_pre);
            $list[$k]['id'] = $v['id'];
            $list[$k]['name'] = $v['name'];
            $list[$k]['pid'] = $v['pid'];
            $list[$k]['img_url'] = $img_url;

        }

        //树状
        $final = $this->array2tree($list,'pid','child');

        //组合数据
        $all =['all'=>$final];
        if ($all) {
            $this->api_return($all);
        }
        $this->json_error_msg('返回失败');

    }



    public function designerList(){
        $pageNum = 10;//每页10条数据
        $data = input('post.');
        //$userid = $this->verifyLogin($data);//先不验证登录
        $this->verifySign($data);
        $page = $data['page'] ?? 1;

        $page = ($page < 1) ? 1 : (int)$page;
        $begin = ($page-1)*$pageNum;
        $list = db('users')
            ->alias('u')
            ->join('goods g','u.user_id = g.user_id')
            ->join('join_apply j','u.user_id = j.user_id','LEFT')
            ->where([
                'u.is_partner'=>1,
                'g.is_show'=>1
            ])
            ->group('u.user_id')
            ->field('u.user_id,u.head_pic,j.designer nickname,g.goods_id,g.goods_sn,g.goods_name,g.original_img,g.is_on_sale,MAX(g.last_update) as last_update')
            ->order('j.designer asc')
            ->limit($begin,$pageNum)
            ->select();

        $resource_pre = getConfig('resource_pre');
        foreach ($list as $k=>$v){
            $img_url = complete_url($v['original_img'],$resource_pre);
            $imgSize = @getimagesize($img_url);
            $list[$k]['head_pic'] = complete_url($v['head_pic'],$resource_pre);
            $list[$k]['img_url'] = $img_url;
            $list[$k]['imgWidth'] = $imgSize[0] ?? 0;
            $list[$k]['imgHeight'] = $imgSize[1] ?? 0;
        }

        return $this->api_return($list);

    }


    public function designerListTest(){
        $pageNum = 10;//每页10条数据
        $data = input('post.');

        $page = $data['page'] ?? 1;

        $page = ($page < 1) ? 1 : (int)$page;
        $begin = ($page-1)*$pageNum;
        $list = db('users')
            ->alias('u')
            ->join('goods g','u.user_id = g.user_id')
            ->where([
                'u.is_partner'=>1,
                'g.is_show'=>1
            ])
            ->group('u.user_id')
            ->field('u.user_id,u.head_pic,u.nickname,g.goods_id,g.goods_sn,g.goods_name,g.original_img,g.is_on_sale,MAX(g.last_update) as last_update')
            ->order('u.nickname asc')
            ->limit($begin,$pageNum)
            ->select();

        $resource_pre = getConfig('resource_pre');
        $data=[];
        $imgList=[
            'userImg\20190806\568acf7ecc2f2f509130ec0f39d77ddf.png',
            'userImg\20190806\0f6aed512fa8c75fbecff23bba04fb35.png',
            'userImg\20190806\aef3005ea84e490172abc03aff707a8e.png',
            'userImg\20190806\f45341c755019c47addcc2d65d0ea94a.png',
            'userImg\20190806\af41113edb7a46f5eee80b5701276374.png',
            'userImg\20190806\b09be1c5583724735e88de0e0a7386ad.png',
            'userImg\20190806\02d17459d9f8edcbf28abf8f76605e8e.png',
            'userImg\20190806\22fdf3efde8b6ea692fe485e43f40e22.png',
            'userImg\20190806\9d051cf8aad249c05d76306c581ca2ec.png',
            'userImg\20190806\8ab57177f5da7d42ce2dab3998928f73.jpg',
            'userImg\20190806\2a08838f01f4d82c2b12b6db646ce5c4.jpg',
            'userImg\20190806\248692d4fabcde8d507be13a5cb9bc50.jpg',
            'userImg\20190806\8ae1c2843cfaeca9aa7eccde5d653e81.png',
            'userImg\20190806\611a20871307e6b0e0dda62f91f1ae53.png',
            'userImg\20190806\8d2108253ca565d2a77387782b87dbc4.png',
            'userImg\20190806\23fa05a51a4aafb15cc2e439497a057e.jpg',
        ];
        for($i=0;$i<16;$i++){
            $j = (int)($i%2);
            $data[$i] = $list[$j];
            $data[$i]['original_img'] = $imgList[$i];
        }

        foreach ($data as $k=>$v){
            $img_url = complete_url($v['original_img'],$resource_pre);
            $imgSize = @getimagesize($img_url);
            $data[$k]['head_pic'] = complete_url($v['head_pic'],$resource_pre);
            $data[$k]['img_url'] = $img_url;
            $data[$k]['imgWidth'] = $imgSize[0] ?? 0;
            $data[$k]['imgHeight'] = $imgSize[1] ?? 0;
        }

        return $this->api_return($data);

    }



}