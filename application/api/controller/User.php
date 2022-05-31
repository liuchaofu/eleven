<?php


namespace app\api\controller;


class User extends Common
{
    public function userCenter()
    {

        $userCenterData = [];
        $data = input('post.');
        $userid = $this->verifyLogin($data);
        $userInfo = $this->getUser($userid, 'user_id,sex,birthday,mobile,head_pic,nickname,is_partner');

        /*-------用户基础信息userInfo----------*/
        $UserInfoData = $userInfo;
        $UserInfoData['birthday'] = ($UserInfoData['birthday'] == 0) ? '' : date("Y-m-d", $UserInfoData['birthday']);

        $resource_pre = getConfig('resource_pre');
        $UserInfoData['head_pic'] = complete_url($UserInfoData['head_pic'], $resource_pre);

        $userCenterData['userInfo'] = $UserInfoData;
        /*-------用户入驻信息joinInfo---------*/
        $userCenterData['joinInfo'] = $this->checkJoin($userid, $userInfo['is_partner']);

        return $this->api_return($userCenterData);

    }

    /**
     * 编辑用户基础信息
     */
    public function editUserInfo()
    {
        $data = input('post.');
        $userid = $this->verifyLogin($data);

        $field = isset($data['field']) ? $this->str_filter($data['field']) : '';//需要编辑的字段
        $value = isset($data['value']) ? $this->str_filter($data['value']) : '';//需要编辑的值
        if (!$field || !$value) {
            return $this->json_error_msg('错误操作');
        }
        $updateData = [];
        switch ($field) {
            case 'HEAD_IMG':
                $updateData['head_pic'] = $value;
                break;
            case 'MOBILE':
                //TODO::check mobile
                $updateData['mobile'] = $value;
                $updateData['mobile_validated'] = 0;
                break;
            case 'NICKNAME':
                $updateData['nickname'] = $value;
                break;
            case 'SEX':
                $sex = (int)($value);
                if ($sex == 0 || $sex == 1 || $sex == 2) {
                    $updateData['sex'] = $value;
                } else {
                    $this->json_error_msg('性别修改错误');
                }
                break;
            case 'BIRTHDAY':
                $updateData['birthday'] = strtotime($value);
                break;
            default :
                $this->json_error_msg('未知字段类型');
                break;
        }
        if ($this->editUser($userid, $updateData)) {
            $this->json_success_msg('修改成功');
        } else {
            $this->json_error_msg('修改失败');
        }


    }


    /**
     * 查询入驻信息
     * @param $userid string
     * @param $is_partner int
     * @return array
     */
    protected function checkJoin($userid, $is_partner)
    {

        $isPartner = ($is_partner == 0) ? 0 : 1; //是否已成功入驻

        $joinInfo = db('join_apply')->where('user_id', $userid)->field('id,status')->find();
        if (!$joinInfo) {
            $applyStatus = -1;
        } else {
            $applyStatus = $joinInfo['status'];
        }

        return ['isPartner' => $isPartner, 'applyStatus' => $applyStatus];

    }

    /**
     * 获取用户openid
     */
    public function getUserId()
    {

        $user_data = input('post.');
        //print_r($user_data['nickName']);exit;
        //$user_data = json_decode($user_data,true);
        $this->verifySign($user_data);

        $code = $user_data['code'] ?? '';//微信授权code
        if ($code) {
            $openid = $this->code2openId($code);
            if ($openid) {
                $userid = $this->checkRegistered($openid, $user_data);
                if ($userid) {
                    $data = ['userid' => $userid];
                    return $this->api_return($data);
                } else {
                    return $this->json_error_msg('注册失败,请重试');
                }
            } else {
                return $this->json_error_msg('openid获取失败，code被使用或失效');
            }
        } else {
            return $this->json_error_msg('code不存在');
        }
    }

    /**
     * checkRegistered 判断是否注册
     * @param $openid string openid
     * @param $user_data array 用户信息
     * @return bool 是否注册
     */
    protected function checkRegistered($openid, $user_data)
    {
        $check = db('users')->where('openid', $openid)->field('user_id')->find();
        if ($check) {
            return $check['user_id'];//已注册
        }
        //to register
        $data = [];
        $data['openid'] = $openid;
        $data['oauth'] = 'wx';
        $data['head_pic'] = $user_data['avatarUrl'] ?? '';
        $data['wx_city'] = $user_data['city'] ?? '';
        $data['wx_province'] = $user_data['province'] ?? '';
        $data['sex'] = $user_data['gender'] ?? 0;
        $data['nickname'] = $user_data['nickName'] ?? '';
        $data['reg_time'] = time();
        if (db('users')->insert($data)) {
            $check = db('users')->where('openid', $openid)->field('user_id')->find();
            if ($check) {
                return $check['user_id'];
            }
        } else {
            return 0;
        }
    }



    /**
     * 我的收藏
     */
    public function collect()
    {
        $data = input('post.');
        $id = $this->verifyLogin($data);
        $data = db('goods_collect')
            ->where('user_id', $id)
            ->field('goods_id')
            ->select();
        $goods_id = array_column($data, 'goods_id');

        //分页
        $pageNum = 10;//每页10条数据
        $page = $data['page'] ?? 1;
        $page = ($page < 1) ? 1 : (int)$page;
        $start = ($page-1)*$pageNum;
        $begin =isset($start) ?$start :0;

        //商品
        $goods = db('goods')
            ->alias('g')
            ->join('goods_category c', 'g.cat_id =c.id')
            ->join('join_apply j', 'g.user_id = j.user_id')
            ->field('g.goods_id,g.goods_name,g.original_img,g.store_count,g.is_new,g.is_hot,g.price,g.sales_sum,g.last_update,g.pre_sale_num,
                g.pre_sale_start_time,g.pre_sale_end_time,g.is_complete,g.cat_id,c.name  as cat_name,j.brand,j.designer')
            ->whereIn('goods_id', $goods_id)
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
     *消息提醒
     */
    public function allMessage()
    {
        $data = input('post.');
        $id = $this->verifyLogin($data);
        $message =db('user_message')
                ->where('user_id',$id)
                ->where('is_see',0)
                ->select();
        $message_num =count($message);
        $cart_num =db('cart')
                    ->where('user_id',$id)
                    ->where('is_deleted',0)
                    ->select();

        $cart_num =count($cart_num);

        $all =['message_num'=>$message_num,'cart_num'=>$cart_num];
        if ($all) {
            $this->api_return($all);
        } else {
            $this->json_success_msg('没有数据');
        }
    }


}