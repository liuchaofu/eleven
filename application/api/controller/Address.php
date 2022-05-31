<?php


namespace app\api\controller;


class Address extends Common
{
    /**
     * 用户地址列表
     */
    public function addressList(){
        $data = input('post.');
        $userid = $this->verifyLogin($data);
        $addressList = db('user_address')
            ->where(['user_id'=>$userid])
            ->order(['is_default'=>'desc','reg_time'=>'desc'])
            ->limit(20)
            ->select();

        foreach ($addressList as $k=>$v){
            $addressList[$k]['provinceName'] = $this->getAreaName($v['province']);
            $addressList[$k]['cityName'] = $this->getAreaName($v['city']);
            $addressList[$k]['districtName'] = $this->getAreaName($v['district']);
        }
        return $this->api_return($addressList);
    }

    public function test()
    {
        $data =input('post.');
        $userid =$this->verifyLogin($data);
        $address =db('user_address')
            ->where('user_id',$userid)
            ->order(['is_default'=>'desc','reg_time'=>'desc'])
            ->limit(15)
            ->select();
        foreach ($address as $k=>$v){
            $address[$k]['provinceName'] =$this->getAreaName($v['province']);
            $address[$k]['cityName'] =$this->getAreaName($v['city']);
            $address[$k]['districtName'] =$this->getAreaName($v['district']);
        }
        return $this->api_return($address);
    }

    /**
     * 编辑地址
    */
    public function editAddress(){
        $data = input('post.');
        $userid = $this->verifyLogin($data);

        $addressId = isset($data['address_id']) ? (int)$this->str_filter($data['address_id']) : 0;

        if($addressId){
            $address_data['consignee'] = isset($data['consignee']) ? $this->str_filter($data['consignee']) : '';
            $address_data['mobile'] = isset($data['mobile']) ? $this->str_filter($data['mobile']) : '';
            $address_data['province'] = isset($data['province']) ? $this->str_filter($data['province']) : 0;
            $address_data['city'] = isset($data['city']) ? $this->str_filter($data['city']) : 0;
            $address_data['district'] = isset($data['district']) ? $this->str_filter($data['district']) : 0;
            $address_data['address'] = isset($data['address']) ? $this->str_filter($data['address']) : '';
            $address_data['is_default'] = isset($data['is_default']) ? $this->str_filter($data['is_default']) : 0;
            $address_data['user_id'] = $userid;
            $address_data['reg_time'] = time();
            if(!check_mobile($address_data['mobile'])){
                return $this->json_error_msg('请输入正确联系电话');
            }
            if(!$address_data['consignee'] || !$address_data['province'] || !$address_data['address']){
                $this->json_error_msg('联系人和地址必填');
            }

            if($address_data['is_default'] == 1){
                //如果是默认地址
                db('user_address')->where('user_id',$userid)->update(['is_default'=>0]);
            }

            if(db('user_address')->where(['user_id'=>$userid,'address_id'=>$addressId])->update($address_data)){
                if($address_data['is_default'] == 1) {
                    //修改users表的默认id
                    $default_id = db('user_address')->field('address_id')->where(['user_id'=>$userid,'is_default'=>1])->find();
                    $user_address_id = $default_id['address_id'] ?? 0;
                    db('users')->where('user_id',$userid)->update(['address_id'=>$user_address_id]);
                }
                $this->autoDefault($userid);
                return $this->json_success_msg('编辑成功');
            }else{
                return $this->json_error_msg('操作失败，请稍后再试');
            }
        }
        return $this->json_error_msg('操作失败');
    }

    /**
     * getAreaName 通过id查地名
     * @param $regionId int
    */
    protected function getAreaName($regionId){
        $region = db('region')->where('id',$regionId)->field('name')->find();
        if($region){
            return $region['name'];
        }else{
            return '';
        }
    }

    /**
     *删除收货地址
    */
    public function delAddress(){
        $data = input('post.');
        $userid = $this->verifyLogin($data);

        $addressId = isset($data['address_id']) ? (int)$this->str_filter($data['address_id']) : 0;
        if(!$addressId){
            return $this->json_error_msg('未知地址');
        }else{
            $map['address_id'] = $addressId;
            $map['user_id'] = $userid;
            if(db('user_address')->where($map)->delete()){
                $this->autoDefault($userid);
                return $this->json_success_msg('删除成功');
            }
        }
        return $this->json_error_msg('操作失败');
    }

    public function del()
    {
        $data =input('post');
        $userid =$this->verifyLogin($data);
        $aid =isset($data['address_id'])?(int)$this->str_filter($data['address_id']):0;
        if ($aid){
            return $this->json_error_msg('未知地址');
        }else{
            $map['address_id'] =$aid;
            $map['user_id'] =$userid;
            if(db('user_address')->where($map)->delete()){
                $this->autoDefault($userid);
                return $this->json_success_msg('删除成功');
            }
        }
        return $this->json_error_msg('操作失败');
    }


    /**
     * 新增用户地址
     */
    public function addAddress(){
        $data = input('post.');
        $userid = $this->verifyLogin($data);

        $address_data['consignee'] = isset($data['consignee']) ? $this->str_filter($data['consignee']) : '';
        $address_data['mobile'] = isset($data['mobile']) ? $this->str_filter($data['mobile']) : '';
        $address_data['province'] = isset($data['province']) ? $this->str_filter($data['province']) : 0;
        $address_data['city'] = isset($data['city']) ? $this->str_filter($data['city']) : 0;
        $address_data['district'] = isset($data['district']) ? $this->str_filter($data['district']) : 0;
        $address_data['address'] = isset($data['address']) ? $this->str_filter($data['address']) : '';
        $address_data['is_default'] = isset($data['is_default']) ? $this->str_filter($data['is_default']) : 0;
        $address_data['user_id'] = $userid;
        $address_data['reg_time'] = time();
        if(!check_mobile($address_data['mobile'])){
            return $this->json_error_msg('请输入正确联系电话');
        }
        if(!$address_data['consignee'] || !$address_data['province'] || !$address_data['address']){
            $this->json_error_msg('联系人和地址必填');
        }

        if($address_data['is_default'] == 1){
            //如果是默认地址
            db('user_address')->where('user_id',$userid)->update(['is_default'=>0]);
        }

        if(db('user_address')->insert($address_data)){
            if($address_data['is_default'] == 1) {
                //修改users表的默认id
                $default_id = db('user_address')->field('address_id')->where(['user_id'=>$userid,'is_default'=>1])->find();
                $user_address_id = $default_id['address_id'] ?? 0;
                db('users')->where('user_id',$userid)->update(['address_id'=>$user_address_id]);
            }
            $this->autoDefault($userid);
            return $this->json_success_msg('新增地址成功');
        }else{
            return $this->json_error_msg('操作失败，请稍后再试');
        }

    }

    /**
     * 自动选取默认地址
     * @param $user_id int
    */

    protected function autoDefault($user_id){
        if(!$user_id){return;}
        if(db('user_address')->where(['user_id'=>$user_id,'is_default'=>1])->find()){
            //如果有默认地址则不做处理
            return;
        }else{
            $address = db('user_address')->where(['user_id'=>$user_id])->order('reg_time desc')->limit(1)->find();
            if($address){
                db('user_address')->where(['user_id'=>$user_id,'address_id'=>$address['address_id']])->update(['is_default'=>1]);
            }
        }
    }
}