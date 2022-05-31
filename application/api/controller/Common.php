<?php


namespace app\api\controller;
use think\Controller;
use think\Request;

class Common extends Controller
{

    private $sign_secret = "7ca5606d9847a7c7c329f01c739fde98";
    private $sign_key = "guangshutou";
    private $appid = "wx8613ca6ee3d7ad0e";
    private $secret = "3f4e954272d0aeac62e7876d6eafa313";


    /**
     * getUser
     * 获取用户信息
     * @param $userid string
     * @param $field string 查询字段
     * @return array
    */
    protected function getUser($userid,$field){
        if(!$userid){
            $this->json_error_msg('userid未知');
        }
        if($field){
            $user = db('users')->where('user_id',$userid)->field("{$field}")->find();
        }else{
            $user = db('users')->where('user_id',$userid)->find();
        }
        if(empty($user)){
            $this->json_error_msg('未知用户');
        }
        return $user;
    }

    protected function getUsers($userid,$filed)
    {
        if (!$userid) {
            $this->json_error_msg('userid不存在');
        }
        if ($filed) {
            $user =db('users')->where('user_id',$userid)->field("{$filed}")->find();
        }else{
            $user =db('users')->where('user_id',$userid)->find();
        }
        if(empty($user)){
            $this->json_error_msg('未知用户');
        }
        return $user;

    }


    /**
     * 修改用户数据
     * @param $userid int
     * @param $data array
     * @return bool
    */
    protected function editUser($userid,$data){
        if(!$userid || empty($data)){
            return false;
        }
        if(db('users')->where('user_id',$userid)->update($data)){
            return true;
        }
        return false;
    }
    /**
     *str_filter 过滤字符串，防止sql注入
     * @param $str string
     * @return string
    */
    public function str_filter($str){
        $str = trim($str);
        if (!get_magic_quotes_gpc()) {
            $str = addslashes($str);
        }
        $str = htmlspecialchars($str);
        return $str;
    }
    /**
     * api_return api消息返回
     * @param $data mixed 返回的data数据
     * @param $status int 返回状态，默认1
     * @param $msg string 返回消息
    */
    protected function api_return($data,$status=1,$msg=''){
        header('Content-Type:application/json; charset=utf-8');
        /* 前端占不需要验签
        $alldata = [
            'status'=>$status,
            'msg'=>$msg,
            'key'=>$this->sign_key,
            'timestamp'=>time(),
            'data'=>json_encode($data)  //必须转为json字符串
        ];
        $data = $this->getSign($alldata);
        */
        $data=[
            'status'=>$status,
            'data'=>$data,
            'msg'=>$msg,
        ];
        //JSON_UNESCAPED_UNICODE（中文不转为unicode ，对应的数字 256）
        //JSON_UNESCAPED_SLASHES （不转义反斜杠，对应的数字 64）
        echo json_encode($data,320);
        die();
    }

    /**
     * json_error_msg 快捷返回错误提示
     * @param $txt string 提示文本
     */
    protected function json_error_msg($txt){
        $this->api_return('',0,$txt);
    }
    /**
     * json_success_msg 快捷返回成功提示
     * @param  $txt string  提示文本
     */
    protected function json_success_msg($txt){
        $this->api_return('',1,$txt);
    }
    /**
     * code2openId 调用微信接口获取openid
     * @param $code string  code
     * @return string  openid
    */

    protected function code2openId($code){
        if($code){
            $url="https://api.weixin.qq.com/sns/jscode2session?appid={$this->appid}&secret={$this->secret}&js_code={$code}&grant_type=authorization_code";
            $result = httpRequest($url);
            if(isset($result['openid'])){
                return $result['openid'];
            }
        }
        return '';
    }


    /**
     * 发送的数据加上sign
     * @param $data array 发送的数据
     * @return array 加了sign之后的数据
    */
    public function getSign($data)
    {
        if(!isset($data['key'])){
            $data['key'] = $this->sign_key;
        }
        // 对数组的值按key排序
        ksort($data);
        // 生成url的形式
        $params = http_build_query($data);

        // 生成sign
        $sign = md5($data['key'].$params . $this->sign_secret);
        $data['sign'] = $sign;
        return $data;
    }


    /**
     * 后台验证sign是否合法
     * @param  $data  array 接受的数据
     * @return mixed  验证结果
     */
    public function verifySign($data)
    {
        // 验证参数中是否有签名

        if (!isset($data['sign']) || !$data['sign']) {
            $this->json_error_msg('发送的数据签名不存在');

        }
        if (!isset($data['key']) || !$data['key']) {
            $this->json_error_msg('发送的数据参数不合法');
        }
        if (!isset($data['timestamp']) || !$data['timestamp']) {
            $this->json_error_msg('发送的数据参数不合法22');
        }
        // 验证请求， 10分钟失效
        if (time() - $data['timestamp'] > 600) {
            $this->json_error_msg('验证超时');

        }
        $sign = $data['sign'];
        unset($data['sign']);
        ksort($data);
        $params = http_build_query($data);

        $sign2 = md5($data['key'].$params . $this->sign_secret);

        if (strtoupper($sign) == strtoupper($sign2)) {
            return true;
        } else {
            $this->json_error_msg('验证不通过');
        }
    }

    protected function verifyLogin($data){
        $this->verifySign($data);
        $userid = $data['userid'] ?? '';
        if(!$userid){
            return $this->json_error_msg('请先登录');
        }else{
            $user = db('users')->where('user_id',$userid)->field('user_id,is_lock')->find();
            if(!$user){
                return $this->json_error_msg('未知用户');
            }else{
                if($user['is_lock']){
                    return $this->json_error_msg('用户被锁定，请联系管理员');
                }
            }
        }
        return trim($userid);
    }

    /**
     * 构建层级（树状）数组
     * @param array  $array          要进行处理的一维数组，经过该函数处理后，该数组自动转为树状数组
     * @param string $pid_name       父级ID的字段名
     * @param string $child_key_name 子元素键名
     * @return array|bool
     */
    function array2tree(&$array, $pid_name = 'pid', $child_key_name = 'children')
    {
        $counter = $this->array_children_count($array, $pid_name);
        if (!isset($counter[0]) || $counter[0] == 0) {
            return $array;
        }
        $tree = [];
        while (isset($counter[0]) && $counter[0] > 0) {
            $temp = array_shift($array);
            if (isset($counter[$temp['id']]) && $counter[$temp['id']] > 0) {
                array_push($array, $temp);
            } else {
                if ($temp[$pid_name] == 0) {
                    $tree[] = $temp;
                } else {
                    $array = $this->array_child_append($array, $temp[$pid_name], $temp, $child_key_name);
                }
            }
            $counter = $this->array_children_count($array, $pid_name);
        }
        return $tree;
    }
    /**
     * 把元素插入到对应的父元素$child_key_name字段
     * @param        $parent
     * @param        $pid
     * @param        $child
     * @param string $child_key_name 子元素键名
     * @return mixed
     */
    function array_child_append($parent, $pid, $child, $child_key_name)
    {
        foreach ($parent as &$item) {
            if ($item['id'] == $pid) {
                if (!isset($item[$child_key_name])) {
                    $item[$child_key_name] = [];
                }

                $item[$child_key_name][] = $child;
            }
        }
        return $parent;
    }

    /**
     * 子元素计数器
     * @param array $array
     * @param int   $pid
     * @return array
     */
    function array_children_count($array, $pid)
    {
        $counter = [];
        foreach ($array as $item) {
            $count = isset($counter[$item[$pid]]) ? $counter[$item[$pid]] : 0;
            $count++;
            $counter[$item[$pid]] = $count;
        }
        return $counter;
    }

    /**
     * 二维数组按照某个键值从新分组为一个新的二维数组
     */
    public  function array_group_by($arr, $key)
    {
        $grouped = [];
        foreach ($arr as $value) {
            $grouped[$value[$key]][] = $value;
        }
        // Recursively build a nested grouping if more parameters are supplied
        // Each grouped array value is grouped according to the next sequential key
        if (func_num_args() > 2) {
            $args = func_get_args();
            foreach ($grouped as $key => $value) {
                $parms = array_merge([$value], array_slice($args, 2, func_num_args()));
                $grouped[$key] = call_user_func_array('array_group_by', $parms);
            }
        }
        return $grouped;
    }

    /**
     * order message
     * @param $type string
     * @param $user_id int
     * @param $order_id int
     * @return bool
    */
    public function sendOrderMessage($type,$user_id,$order_id)
    {

        if($order_id) {
            $order_item = db('order_goods')->where(['order_id' => $order_id])->field('item_id')->find();
            $item_id = $order_item['item_id'] ?? 0;
            $goods_key = db('spec_goods_price')->where('item_id', $item_id)->field('key,goods_id')->find();
            if (!empty($goods_key)) {
                $goods_key_arr = explode('_', $goods_key['key']);
                $img = db('spec_image')->where(['goods_id' => $goods_key['goods_id'], 'spec_item_id' => ['IN', $goods_key_arr]])->field('src')->find();
                if ($img) {
                    $this->message_template($type,$user_id,'','',0,'订单消息',$img['src']);
                    return true;
                }
            }
        }

        return false;

    }

    /**
     * 消息模板发送
     * @param string $type 类型
     * @param string $content 自定义内容
     * @param array/int  $user_id 用户id 一个或者多个一维数组
     * @param  int $is_all 是不是全部
     * @param  string $category 类型
     * @param  string $title 标题
     * @param  string $img
     * @return bool;
     */

    public function message_template($type,$user_id,$content='',$category='system',$is_all = 0,$title ='',$img ='')
    {
        //判断 unpaid未支付 cancel取消订单 garment1-2成衣流程 semi_custom1-4半定制  customize1-4定制流程 myself自定义模板
        if($type =='myself'){
            $data =$content;
        }else{
            $data =db('template_message')
                ->where('type',$type)
                ->field('content')
                ->find();
            $data =$data['content'];
        }
        //发送信息
        $msg['admin_id'] =0;
        $msg['content'] =$data;
        $msg['is_all'] =isset($is_all)?$is_all:0;

        if ($category ='system'){
            $msg['category'] =0;
        }elseif($category='order'){
            $msg['category'] =1;
        }else{
            $this->json_error_msg('请填写正确的消息类型');
        }

        $msg['add_time'] =time();
        $msg['title'] =isset($title)?$title:'';
        $msg['img'] =isset($img)?$img:'';

        //判断是单个 还是数组
        if(is_numeric($user_id)){
            $msg['user_id'] =$user_id;
            //单个is_all只有0 自动转为 is_all为0
            $msg['is_all'] =0;
            $message_id =db('send_message')->insertGetId($msg);

            $own['user_id'] =$user_id;
            $own['message_id'] =$message_id;
            $own['category'] =$msg['category'];
            $own['is_see'] =0;
            $own['deleted'] =0;
            $own['add_time'] =time();

            $res =db('user_message')->insert($own);
        }else{
            //发送
            if($is_all ==0){
                $str = implode(",", $user_id);
                $msg['user_id'] =$str;
                //不是全部
                //添加
                $message_id =db('send_message')->insertGetId($msg);

                //重新组成数据
                $info = array();
                $i = 0;
                foreach ($user_id as $k =>$v) {
                    $info[$i]['user_id'] = $v;
                    //用戶
                    $info[$i]['message_id'] =$message_id;
                    $info[$i]['category'] =0;//默认
                    $info[$i]['is_see'] =0;//默认
                    $info[$i]['deleted'] =0;//默认
                    $info[$i]['add_time'] =time();
                    $i ++;
                }

                //插入到用户信息表
                $res =db('user_message')->insertAll($info);
            }elseif($is_all ==1){
                //全部消息不存用户id
                $msg['user_id'] ='';
                //全部的只进去后台就好
                $res =db('send_message')->insert($msg);
            }else{
                $res =[];
            }
        }

        if ($res) {
            return true;
        }else{
            return false;
        }

    }

    /**
     * 公用返回方法
     * @param $data
     */
    public function returns($data)
    {
        if ($data) {
            $this->api_return($data);
        } else {
            $this->json_success_msg('没有商品了');
        }

    }


}