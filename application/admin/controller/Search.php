<?php

namespace app\admin\controller;


class Search extends Common
{
    //显示
    public function search_index()
    {
        //查询最新7个
        $list =\db('config')->where('name','hot_keywords')->find();

        //查询个数
        $hot_num =\db('config')->where('name','hot_num')->find();
        $hot_num =$hot_num['value'];

        //配置为空
        if(empty($list['value'])){
            $list =\db('search_log')
                ->field('keys_word,count(*) as total')
                ->whereTime('reg_time','>','this week')
                ->group('keys_word')
                ->order('total desc')
                ->limit($hot_num)
                ->select();
            $show =1;
        }else{
            //json转出
            $list = json_decode($list['value'],true);
            //整合结果 改名
            $arr = array();
            $i = 0;
            foreach ($list as $k =>$v) {
                $arr[$i]['keys_word'] = $v['value'];
                $arr[$i]['sort'] = $v['listorder'];
                $arr[$i]['total'] = $v['total'];
                $i ++;
            }
            //
            $result =\db('search_log')
                ->field('keys_word,count(*) as total')
                ->whereTime('reg_time','>','this week')
                ->group('keys_word')
                ->order('total desc')
                ->limit($hot_num)
                ->select();
            //排序
            foreach($arr as $key=>$item){
                //从0开始截取到最后一位
                $array1 = array_slice($result, 0, $item['sort']-1);
                $array2 = array_slice($result, $item['sort']-1);
                //整合
                $result = array_merge($array1, array($key=>$item), $array2);
            }
            //从数据库读出拿多少个
            $list = array_slice($result,0,$hot_num);
            $show =0;
        }


        //存在config表里面的修改了的数据
        $change_data =\db('config')->where('name','hot_keywords')->find();
        $values =json_decode($change_data['value'],true);


        //生成对应的数组
        for ($i=1;$i<=$hot_num;$i++){
            $sort[] =['sort'=>$i];
        }


        return $this->fetch('search_index',[
                'list'=>$list,
                'show'=>$show,
                'values'=>$values,
                'sort'=>$sort,
                'hot_num'=>$hot_num
            ]);
    }
    //添加热搜
    public function search_add()
    {
        //判断有没有填写
        $data =$_POST;
        if (empty($data['hot']['0'])) {
            $this->ajaxReturn(array('status'=>false,'msg'=>'请添加自定义热门'));
        }

        if(!empty($_POST)){
            $hot = $_POST['hot'];
            $sort = $_POST['sort'];

            //把数据组合
            $data = array();
            foreach ($hot as $k =>$v){
                $data[$k]['keys_word'] = $hot[$k];
                $data[$k]['sort'] = $sort[$k];
            }

            //切换顺序 如果是随机顺序 改为顺序排序 按照sort顺序来排序
            $sort = array(
                'direction' => 'SORT_ASC', //排序顺序标志 SORT_DESC 降序；SORT_ASC 升序
                'field'     => 'sort',       //排序字段
            );

            $arrSort = array();
            foreach($data AS $keys=> $row){
                foreach($row AS $key=>$value){
                    $arrSort[$key][$keys] = $value;
                }
            }
            if($sort['direction']){
                array_multisort($arrSort[$sort['field']], constant($sort['direction']), $data);
            }

            $new_arr =$data;
            $arr = array();
            //改名
            $i = 0;
            foreach ($new_arr as $k =>$v) {
                $arr[$i]['value'] = $v['keys_word'];
                $arr[$i]['listorder'] = $v['sort'];
                $arr[$i]['total'] = '自定热门';
                $i ++;
            }
            //序列化存数据库
            $config_data = array('name'=>'hot_keywords','value'=>json_encode($arr),'desc'=>'关键热词');


            //修改config数据库
            $res =\db('config')->where('name','hot_keywords')->update($config_data);
            $res =1;

            if ($res) {
                $this->ajaxReturn(array('msg'=>'修改成功'));
            }
            $this->ajaxReturn(array('status'=>false,'msg'=>'修改失败'));


        }
        return $this->fetch('search_add',[

        ]);
    }
    
    //生成
    public function search_generate()
    {
        //查询个数
        $hot_num =\db('config')->where('name','hot_num')->find();
        $hot_num =$hot_num['value'];

        $mark =input('post.mark');
        if ($mark == 1) {
            $data =\db('search_log')
                ->field('keys_word,count(*) as total')
                ->whereTime('reg_time','>','this week')
                ->group('keys_word')
                ->order('total desc')
                ->limit($hot_num)
                ->select();

            $arr = array();
            $i = 0;
            foreach ($data as $k =>$v) {
                $arr[$i]['value'] = $v['keys_word'];
                $arr[$i]['listorder'] = $i;
                $i ++;
            }

            $config_data = array('name'=>'hot_keywords','value'=>json_encode($arr),'desc'=>'关键热词');

            //判断有没有生成过
            $same =\db('config')->where('name','hot')->find();
            if($same){
                $this->ajaxReturn(array('status'=>false,'msg'=>'你好,你已经生成过了'));
            }else{
                //加入config数据库
                $res =\db('config')->insert($config_data);
                if ($res) {
                    $this->ajaxReturn(array('status'=>1,'data'=>$data,'msg'=>'生成成功'));
                }

            }

        }
        $this->ajaxReturn(array('status'=>-1,'msg'=>'失败'));

    }

    //预览
    public function search_preview()
    {
        //查询个数
        $hot_num =\db('config')->where('name','hot_num')->find();
        $hot_num =$hot_num['value'];

        //判断有没有填写  如果为空直接去seach_log找
        $data =$_POST;
        if (empty($data['hot']['0'])) {
//            $this->ajaxReturn(array('status'=>false,'msg'=>'请添加自定义热门'));
            $result =\db('search_log')
                ->field('keys_word,count(*) as total')
                ->whereTime('reg_time','>','this week')
                ->group('keys_word')
                ->order('total desc')
                ->limit($hot_num)
                ->select();
            //截取前7个
            $new_arr = array_slice($result,0,$hot_num);

            if ($new_arr) {
                $this->ajaxReturn(array('status'=>1,'data'=>$new_arr,'msg'=>'预览加载'));
            }
            $this->ajaxReturn(array('status'=>-1,'msg'=>'预览失败'));

        }

        if(!empty($_POST)){
            $hot = $_POST['hot'];
            $sort = $_POST['sort'];

            $data = array();
            foreach ($hot as $k =>$v){
                $data[$k]['keys_word'] = $hot[$k];
                $data[$k]['sort'] = $sort[$k];
            };

            //切换顺序 按照sort顺序来排序
            $sort = array(
                'direction' => 'SORT_ASC', //排序顺序标志 SORT_DESC 降序；SORT_ASC 升序
                'field'     => 'sort',       //排序字段
            );

            $arrSort = array();
            foreach($data AS $keys=> $row){
                foreach($row AS $key=>$value){
                    $arrSort[$key][$keys] = $value;
                }
            }
            if($sort['direction']){
                array_multisort($arrSort[$sort['field']], constant($sort['direction']), $data);
            }


            $result =\db('search_log')
                ->field('keys_word,count(*) as total')
                ->whereTime('reg_time','>','this week')
                ->group('keys_word')
                ->order('total desc')
                ->limit($hot_num)
                ->select();

            foreach($data as $key=>$item){
                //从0开始截取到最后一位
                $array1 = array_slice($result, 0, $item['sort']-1);
                $array2 = array_slice($result, $item['sort']-1);
                //整合
                $result = array_merge($array1, array($key=>$item), $array2);
            }
            //截取前7个
            $new_arr = array_slice($result,0,$hot_num);

            if ($new_arr) {
                $this->ajaxReturn(array('status'=>1,'data'=>$new_arr,'msg'=>'预览加载'));
            }
            $this->ajaxReturn(array('status'=>-1,'msg'=>'预览失败'));

        }else{
            $this->ajaxReturn(array('status'=>false,'msg'=>'请添加自定义热门'));
        }

    }

    //配置
    public function config_add()
    {
        if ($_POST) {
            $data =$_POST;
//            halt($data);
            $nums =$data['nums'];
            if(empty($nums)){
                $this->ajaxReturn(array('status'=>false,'msg'=>'请填写配置的条数建议7条以上'));
            }
            //判断
            $judge =$this->isPositiveInteger("$nums");
            if ($judge =='negative') {
                $this->ajaxReturn(array('status'=>false,'msg'=>'请输入正整数建议7条以上'));
            }
            $v =['value'=>$nums];

            //判断是不是没修改
            $old =\db('config')->where('name','hot_num')->find();
            $old_num =$old['value'];
            if ($nums == $old_num) {
                $this->ajaxReturn(array('msg'=>'未修改'));
            }


            //入库
            $res =\db('config')->where('name','hot_num')->update($v);
            if ($res) {
                $this->ajaxReturn(array('msg'=>'修改成功'));
            }
            $this->ajaxReturn(array('status'=>false,'msg'=>'数据有误'));
        }

        return $this->fetch('config_add');

    }


    //判断是不是正整数
    function isPositiveInteger($value, $field = '')
    {
        if (is_numeric($value) && is_int($value + 0) && ($value + 0) > 0) {
            return $value;
        }else{
            return 'negative';
        }

    }

    //回显手动热搜
    public function manual()
    {
        $data =\db('config')
                ->where('name','hot_keywords')
                ->find();

        $hot =json_decode($data['value'],true);


        //查询个数
        $hot_num =\db('config')->where('name','hot_num')->find();
        $hot_num =$hot_num['value'];
        //生成对应的数组
        for ($i=1;$i<=$hot_num;$i++){
            $sort[] =['sort'=>$i];
        }

        if ($hot) {
            $this->ajaxReturn(array('status'=>1,'data'=>['hot'=>$hot,'sort'=>$sort],'msg'=>'加载成功'));
        }
        $this->ajaxReturn(array('status'=>false,'msg'=>'数据有误'));
    }



}
