<?php


namespace app\api\controller;

class Index extends Common
{

    public function index()
    {
        $data = input('post.');
        $this->verifySign($data);
        //$goodsController = controller('Goods');
        //$data['nav_id'] = 1;//test
        $indexData = [];
        if(!isset($data['nav_id'])){
            return $this->json_error_msg('未知导航ID');
        }
        $nav_id = $data['nav_id'];
        //check nav
        if(!db('nav_list')->where(['id'=>$nav_id,'show'=>1])->find()){
            return $this->json_error_msg('导航ID错误');
        }
        //all nav
        $all_nav = db('nav_list')->field('id,name,sort')->where('show',1)->order('sort desc')->limit(5)->select();
        $indexData['nav_list'] = $all_nav;
        //get banner
        $banner = db('banner')->where(['cat_id'=>$nav_id,'is_show'=>1])->field('id,url')->order('sort desc,reg_time desc')->limit(3)->select();
        $resource_pre = getConfig('resource_pre');
        foreach ($banner as $k=>$v){
            $img_url = complete_url($v['url'],$resource_pre);
            $banner[$k]['url'] = $img_url;
        }
        $indexData['banner'] = $banner;
        //每日上新
//        $daily_new = $goodsController->get_goods_list(['g.is_show'=>1,'g.is_on_sale'=>1,'g.nav_id'=>$nav_id],3);
        $daily_new = action('goods/get_goods_list',[
            'where'=>['g.is_show'=>1,'g.is_on_sale'=>1,'g.nav_id'=>$nav_id],
            'length'=>3
        ]);
        $indexData['daily_new'] = $daily_new;

        //即将上线
        $goods_online = db('goods_online')->where(['online_time'=>['>',time()],'status'=>1,'nav_id'=>$nav_id])->field('id,img_url,online_time')->order('online_time ASC')->limit(3)->select();
        foreach ($goods_online as $k=>$v){
            $img_url = complete_url($v['img_url'],$resource_pre);
            $goods_online[$k]['online_date'] = date("m月d日",$v['online_time']);
            $goods_online[$k]['img_url'] = $img_url;
        }

        $indexData['goods_online'] = $goods_online;

        //推荐
//        $recommend = $goodsController->get_goods_list(['g.is_show'=>1,'g.is_on_sale'=>1,'g.nav_id'=>$nav_id,'g.is_recommend'=>1],3);
        $recommend = action('goods/get_goods_list',[
            'where'=>['g.is_show'=>1,'g.is_on_sale'=>1,'g.nav_id'=>$nav_id,'g.is_recommend'=>1],
            'length'=>3
        ]);
        $indexData['recommend'] = $recommend;


        return $this->api_return($indexData);

    }




    public function userUploadImg(){
        $file = request()->file('userImg');
        $path = 'userImg';
        if(!empty($file)){
            // 移动到框架应用根目录/public/uploads/ 目录下
            $filePath = ROOT_PATH . 'public' . DS . 'uploads'.DS.$path;
            if(!is_dir($filePath)){
                mkdir($filePath, 0777, true);
            }
            $info = $file->validate(['size'=>4194304,'ext'=>'jpg,png,gif'])->move($filePath);
            if($info){
                $savename = $info->getSaveName();
                $img_url = $path.DS.$savename;
                $data = ['url'=>$img_url];
                return $this->api_return($data);
            }else{
                // 上传失败
                return $this->json_error_msg('上传失败,请上传不超过4M的图片');
            }
        }else{
            return $this->json_error_msg('图片不存在');
        }
    }


}