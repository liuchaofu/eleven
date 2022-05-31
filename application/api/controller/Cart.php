<?php
namespace app\api\controller;

use think\Db;

class Cart extends Common
{
	public $data = [] ;
	public function __construct(){
		parent::__construct();
		$this->data = input('post.');
		$this->verifyLogin($this->data);
	}
    /**
     * 购物车 以及为你推荐
     */
    public function goods_cart()
    {
        //id 然后用id去查
         $data = $this->data;
         $id = $data['userid'];

 //        删除购物车单
       $cart_id = isset($data['cart_id'])?$data['cart_id']:0;
         if($cart_id){
             $data =['is_deleted'=>1];
             $res =db('cart')
                 ->where('id',$cart_id)
                 ->update($data);
             if ($res) {
                 $this->json_success_msg('删除成功');
             }else{
                 $this->json_error_msg('删除失败');
             }
         }

        //购物车
        $cart = db('cart')
            ->alias('c')
            ->join('goods g', 'c.goods_id =g.goods_id')
            ->join('join_apply j', 'c.designer_id =j.user_id')
            ->join('spec_goods_price s', 's.item_id =c.item_id')
            ->where('c.user_id',$id)
            ->where('is_deleted',0)
            ->field('c.*,g.goods_name,g.original_img as old_img,g.model_id,g.price,j.designer,j.user_id as designer_uid,s.key,s.key_name,s.price as catePrice')
            ->order('c.designer_id desc')
            ->select();


        foreach ($cart as $k => $v) {
            //设计师头像和判断
            $head =db('users')->where('user_id',$v['designer_uid'])->field('oauth,head_pic')->find();
            $cart[$k]['oauth'] =$head['oauth'];
            $cart[$k]['head_pic'] =$head['head_pic'];
            //属性
            $attribute = explode(",", $v['key_name']);
            $cart[$k]['color'] = isset($attribute[0]) ? $attribute[0] : '';
            $cart[$k]['size'] = isset($attribute[1]) ? $attribute[1] : '';
            $cart[$k]['server'] = isset($attribute[2]) ? $attribute[2] : '';

            //总金额
            if(isset($v['catePrice'])){
                $cart[$k]['total_price'] =round($v['goods_num'] * $v['catePrice'],2);
            }else{
                $cart[$k]['total_price'] =round($v['goods_num'] * $v['price'],2);
            }

            //值
            $key = explode("_", $v['key']);
            $key['0'] = isset($key['0']) ? $key['0'] : 0;
            $key['1'] = isset($key['1']) ? $key['1'] : 0;
            $key['2'] = isset($key['2']) ? $key['2'] : 0;
            $arr =[$key['0'],$key['1'],$key['2']];

            $where['spec_item_id'] = array('in',$arr);
            $where['goods_id'] = $v['goods_id'];

            $cart[$k]['img'] = db('spec_image')
                ->where($where)
                ->field('src')
                ->find();
            if (empty($cart[$k]['img'])) {
                $cart[$k]['img'] = $v['old_img'];
            }

        }
        //处理图片
        $resource_pre = getConfig('resource_pre');
        $list =[];
        foreach ($cart as $k=>$v){
            $img_url = complete_url($v['img']['src'],$resource_pre);
            $list[$k]['id'] = $v['id'];
            $list[$k]['designer_id'] = $v['designer_id'];
            $list[$k]['item_id'] = $v['item_id'];
            $list[$k]['goods_id'] = $v['goods_id'];
            $list[$k]['goods_num'] = $v['goods_num'];
            $list[$k]['add_time'] = $v['add_time'];
            $list[$k]['prom_type'] = $v['prom_type'];//默认只有普通订单，之后维护
            $list[$k]['is_deleted'] = $v['is_deleted'];
            $list[$k]['goods_name'] = $v['goods_name'];
            if($v['catePrice']){
                $list[$k]['price'] = $v['catePrice'];
            }else{
                $list[$k]['price'] = $v['price'];
            }
            $list[$k]['designer'] = $v['designer'];
            $list[$k]['color'] = $v['color'];
            $list[$k]['size'] = $v['size'];
            $list[$k]['server'] = $v['server'];
            $list[$k]['total_price'] = $v['total_price'];
            $list[$k]['img_url'] = $img_url;
            $head_img =complete_url($v['head_pic'],$resource_pre);
            $list[$k]['head_img'] = $head_img;
            $list[$k]['checked'] = false;
        }

        //按照设计师分组
        $list = $this->array_group_by($list,'designer');
        //重组数据
        foreach ($list as $k=>$v){
            $head_img =isset($v[0]['head_img'])?$v[0]['head_img']:'';
            $head_imgWidth =isset($v[0]['head_imgWidth'])?$v[0]['head_imgWidth']:'';
            $head_imgHeight =isset($v[0]['head_imgHeight'])?$v[0]['head_imgHeight']:'';
            $new[] =['designerName'=>$k,'checked'=>false,'hed_img'=>$head_img,'head_width'=>$head_imgWidth,'head_height'=>$head_imgHeight,'price'=> array_sum(array_column($v, 'total_price')),'num'=>count($v),'child'=>$v];
        }
        $list =isset($new)?$new:[];
        if(empty($list)){
            $list =[];
        }
        //为你推荐
        $day =db('goods')->where('is_on_sale',1)->where('is_show',1)->order('goods_id desc')->limit(3)->select();
        //转换图片
        $resource_pre = getConfig('resource_pre');
        $new =[];
        foreach ($day as $k=>$v){
            $img_url = complete_url($v['original_img'],$resource_pre);
            $new[$k]['goods_id'] = $v['goods_id'];
            $new[$k]['goods_name'] = $v['goods_name'];
            $new[$k]['price'] = $v['price'];
            $new[$k]['img_url'] = $img_url;

        }
        //组合
        $all =['cart'=>$list,'new'=>$new];
        //返回
        if ($all) {
            $this->api_return($all);
        }
        $this->json_error_msg('数据查找失败，请稍后再试');
    }

    /**
     ** [添加购物车]
	 ** @date   2019-08-20T10:21:46+0800
     ** @author dyp
    */
    public function addCart(){
		$data = $this->data;
		$param['user_id'] = $data['userid'];
		$param['goods_id'] = $data['goods_id'];
		$param['goods_num'] = $data['goods_num'];
		asort($data['item_ids']);
		$key = implode('_',$data['item_ids']);
		//查询商品库存信息
		$specGoodsPrice = $this->getStoreCount(['key'=>$key,'goods_id'=>$param['goods_id']]);
        //是否是是定制(true/false);
        $is_custom = $this->custom_goods(['key'=>$key,'goods_id'=>$data['goods_id']]);
		if(!$is_custom && $specGoodsPrice['store_count']< $param['goods_num']){
			$this->json_error_msg('商品库存不足!');
		}
        //查询商品是否已添加购物车
        $row = db('cart')->where(['user_id'=>$data['userid'],'goods_id'=>$data['goods_id'],'item_id'=>$specGoodsPrice['item_id'],'is_deleted'=>0])->find();
        if($row){
            $res = db('cart')->where('item_id',$specGoodsPrice['item_id'])->setInc('goods_num', $data['goods_num']);
            if($res){
                $this->json_success_msg('购物车添加成功');
            }
            $this->json_error_msg('购物车添加失败');
        }
		$param['item_id'] = $specGoodsPrice['item_id'];
		$param['designer_id'] = db('goods')->where('goods_id',$param['goods_id'])->value('user_id');
		$param['add_time'] = time();
		$id = db('cart')->insertGetId($param);
		if($id){
			$this->api_return($id,1,$msg='购物车添加成功');
		}
		$this->json_error_msg('购物车添加失败');
    }
	/**
     ** [详情页确认或购物车结算,判断是否存在定制商品]
	 ** @date   2019-08-21T10:21:46+0800
	 ** @author dyp
    */
	public function goods_comfirm(){
		$data = $this->data;
		if(isset($data['item_id'])){
			$map['key_name'] = array('like','%定制%');
			$map['item_id'] = array('in',$data['item_id']);
			$res = db('specGoodsPrice')->field('key_name')->where($map)->find();
		}else{
			$param['goods_id'] = $data['goods_id'];
			asort($data['spec_item_id']);
			$key = implode('_',$data['spec_item_id']);
			$param['key'] = $key;
			$res = $this->custom_goods($param);
		}
		if($res){
			return $this->api_return($data=1,$status=1,$msg='非成衣');
		}
		return $this->api_return($data=0,$status=1,$msg='成衣');
	}
	public function orderDetailBuy(){
        $data = $this->data;
        if(!isset($data['order_id'])){
            $this->json_error_msg('订单参数有误');
        }
        $row = db('order')->where(['order_id'=>$data['order_id'],'user_id'=>$data['userid']])->find();
        if(!$row){
            $this->json_error_msg('订单参数有误');
        }
        $openid = $this->getUser($data['userid'],'openid')['openid'];
        $pay_arr = ['order_sn' =>$row['order_sn'],'total_fee'=>$row['order_amount'],'openid'=> $openid,'order_id'=>$row['order_id']];
        //调用支付接口
        $payment = controller('payment');
        $result['prepayId'] = $payment->getPrepayId($pay_arr);
        $result['order_id'] = $data['order_id'];
        $this->api_return($result, $status = 1, $msg = '订单获取成功');
    }
	/**
     ** [立即购买,购物车结算]
	 ** @date   2019-08-21T10:21:46+0800
	 ** @author dyp
    */
	public function buy(){
		
		$data = $this->data;
		$userid = $data['userid'];
		$address_id = $data['address_id'];
		//$userid = 66;//当前登录会员id;
        //$address_id = 106;//收货地址id;
		$openid = $this->getUser($userid,'openid')['openid'];
		//会员收货地址信息(订单信息)
		$orderInfo = db('user_address')->field('consignee,country,province,city,district,address,mobile,email')
						->where(['user_id'=>$userid,'address_id'=>$address_id])->find();
		if(!$orderInfo){
			$this->json_error_msg('请选择收货地址');
		}
		$orderInfo['user_id'] = $userid;
		$service_msg = '';
		if(isset($data['service_time'])&& isset($data['service_mobile'])&& isset($data['service_addr'])){
			$service_msg = json_encode(['service_time'=>$data['service_time'],'service_mobile'=>$data['service_mobile'],'service_addr'=>$data['service_addr']],320);
		}
		if(isset($data['user_note'])){
			$orderInfo['user_note'] = $data['user_note'];
		}
		$orderInfo['add_time'] = time();
        //$data['spec_item_id'] = [22,26,32];
        //$data['goods_num'] = 1;
        //$data['goods_id'] = 22;
		//108 109 140 141
		/*$data['item_arr']= array(['item_id'=>102,'goods_num'=>1,'designer_id'=>66],
								['item_id'=>108,'goods_num'=>1,'designer_id'=>59],
								['item_id'=>109,'goods_num'=>1,'designer_id'=>59],
								['item_id'=>140,'goods_num'=>1,'designer_id'=>66],
								['item_id'=>141,'goods_num'=>1,'designer_id'=>66],
								['item_id'=>146,'goods_num'=>1,'designer_id'=>66],
								);*/
		//购物车提交订单,进行拆单处理
		$res = false; $designer_id = []; $goods_arr = []; $item_id_arr = []; $count_arr = []; $order_id = 0;
		if(isset($data['item_arr'])){
			$total_price = 0;
			foreach($data['item_arr'] as $key=>$val){
				$item_id_arr[] = $val['item_id'];//用于订单生成后,删除购物车
				//获取库存信息
				$specGoodsPrice = $this->getStoreCount(['item_id'=>$val['item_id']]);
				$is_custom = $this->custom_goods(['item_id'=>$val['item_id']]);//是否是是定制(true/false);
				if(!$is_custom && $specGoodsPrice['store_count']< $val['goods_num']){//成衣判断库存是否足够
					$this->api_return(['item_id'=>$val['item_id']],1,$msg='库存不足');
				}
				//将每个商品信息写入数组
				$val['goods_price'] = $specGoodsPrice['price'];
				$val['goods_id'] = $specGoodsPrice['goods_id'];
				$val['goods_name'] = db('goods')->where('goods_id',$val['goods_id'])->value('goods_name');
				$val['goods_sn'] = db('goods')->where('goods_id',$val['goods_id'])->value('goods_sn');
				if(!$is_custom){
					$count_arr[$val['designer_id']]['complete'][] = $val;
					$val['complete_goods'] = 1;
				}else{
					$count_arr[$val['designer_id']]['custom'][] = $val;
					$val['complete_goods'] = 0;
				}
				$total_price += round($val['goods_num']*$specGoodsPrice['price'],2);//商品总价格,用于存入父级订单
				//对商品进行设计师分组
				$goods_arr[$val['designer_id']][] = $val;
				$designer_id[] = $val['designer_id'];//设计师id,生成一维数组
				$designer_id = array_unique($designer_id);//去重
			}
			foreach($designer_id as $val){
				$count[] = count($count_arr[$val]);
			}
			$orderInfo['order_sn'] = $this->create_order_sn($userid);
			$orderInfo['goods_price'] = $total_price;
			$orderInfo['total_amount'] = $total_price;
			$orderInfo['order_amount'] = $total_price;
			//支付传递的数组
            $total_price = $total_price*100;
			$pay_arr = ['order_sn' =>$orderInfo['order_sn'],'total_fee'=>$total_price,'openid'=>$openid];
			//设计师>=2或者$count存在2,则进行分单操作
			if(count($designer_id)>=2 || in_array(2,$count)){
				//父级订单标识
				$orderInfo['is_parent_order'] = 1;
				//开启事务
				Db::startTrans();
				try{
					db('order')->insert($orderInfo);
					$orderInfo['parent_sn'] = $orderInfo['order_sn'];
					unset($orderInfo['is_parent_order']);
					foreach($designer_id as $val){
						$complete_amount = 0;//成衣总价
						$custom_amount = 0;//定制总价
						$goodsOrderInfoAll = [];
						foreach($goods_arr[$val] as $v){
							$goodsOrderInfoAll[$v['complete_goods']][] = [
								'goods_id' => $v['goods_id'],
								'goods_name' => $v['goods_name'],
								'goods_sn' => $v['goods_sn'],
								'goods_num' => $v['goods_num'],
								'final_price' => $v['goods_price'],
								'goods_price' => $v['goods_price'],
								'item_id' => $v['item_id']
							];
							if($v['complete_goods']){
								$complete_amount += round($v['goods_num']*$v['goods_price'],2);
							}else{
								$custom_amount += round($v['goods_num']*$v['goods_price'],2);
							}
						}
						$orderInfo['designer_id'] = $val;
						if(isset($goodsOrderInfoAll[1])){
							$orderInfo['complete_goods'] = 1;
							$orderInfo['order_sn'] = $this->create_order_sn($userid);
							$orderInfo['goods_price'] = $complete_amount;
							$orderInfo['total_amount'] = $complete_amount;
							$orderInfo['order_amount'] = $complete_amount;
							$order_id = db('order')->insertGetId($orderInfo);
							$arr = $goodsOrderInfoAll[1];
							$order_id_arr = array('order_id' => $order_id);
							array_walk($arr, function (&$v, $k, $p) {$v = array_merge($v, $p);},$order_id_arr);
							$res = db('order_goods')->insertAll($arr);
							//成衣库存处理
							$total_num = 0;
							foreach($goodsOrderInfoAll[1] as $v){
								db('specGoodsPrice')->where('item_id',$v['item_id'])->setDec('store_count', $v['goods_num']);
								$total_num += $v['goods_num'];
							}
							db('goods')->where('goods_id',$v['goods_id'])->setDec('store_count',$total_num);
						}
						if(isset($goodsOrderInfoAll[0])){
							$orderInfo['order_sn'] = $this->create_order_sn($userid);
							$orderInfo['service_msg'] = $service_msg;
							$orderInfo['goods_price'] = $custom_amount;
							$orderInfo['total_amount'] = $custom_amount;
							$orderInfo['order_amount'] = $custom_amount;
							$order_id = db('order')->insertGetId($orderInfo);
							$arr = $goodsOrderInfoAll[0];
							$order_id_arr = array('order_id' => $order_id);
							array_walk($arr, function (&$v, $k, $p) {$v = array_merge($v, $p);},$order_id_arr);
							$res = db('order_goods')->insertAll($arr);
						}
					}
					// 提交事务
					Db::commit();
				}catch(\Exception $e){
					// 回滚事务
					Db::rollback();
				}
			}else{    //不进行分单操作
				$goodsOrderInfoAll = [];
				foreach($goods_arr[$designer_id[0]] as $key=>$val){
					$orderInfo['designer_id'] = $designer_id[0];
					if($val['complete_goods']){
						$orderInfo['service_msg'] = $service_msg;
						$orderInfo['complete_goods'] = 1;
					}
					$goodsOrderInfoAll[] = [
						'goods_id' => $val['goods_id'],
						'goods_name' => $val['goods_name'],
						'goods_sn' => $val['goods_sn'],
						'goods_num' => $val['goods_num'],
						'final_price' => $val['goods_price'],
						'goods_price' => $val['goods_price'],
						'item_id' => $val['item_id']
					];
				}
				//开启事务
				Db::startTrans();
				try{
					$order_id = db('order')->insertGetId($orderInfo);
					$arr = $goodsOrderInfoAll;
					$order_id_arr = array('order_id' => $order_id);
					array_walk($arr, function (&$v, $k, $p) {$v = array_merge($v, $p);},$order_id_arr);
					$res = db('order_goods')->insertAll($arr);
					//若为成品,进行库存修改
					if(isset($orderInfo['complete_goods'])){
						$total_num = 0; 
						foreach($goods_arr[$designer_id[0]] as $v){
							db('specGoodsPrice')->where('item_id',$v['item_id'])->setDec('store_count', $v['goods_num']);
							$total_num += $v['goods_num'];
						}
						db('goods')->where('goods_id',$v['goods_id'])->setDec('store_count',$total_num);
					}
					// 提交事务
					Db::commit();
				}catch(\Exception $e){
					// 回滚事务
					Db::rollback();
				}
			}
			db('cart')->where('item_id','in',$item_id_arr)->update(['is_deleted'=>1]);//购物车删除
		}else{
			//详情页"立即购买"生成订单
            if(!isset($data['goods_id'])){
                $this->api_return('',$status=0,$msg='参数有误');
            }
			asort($data['spec_item_id']);
			$key = implode('_',$data['spec_item_id']);

            $is_custom = $this->custom_goods(['key' => $key, 'goods_id' => $data['goods_id']]);//是否是是定制(true/false);
            $specGoodsPrice = $this->getStoreCount(['key' => $key, 'goods_id' => $data['goods_id']]);//商品库存相关数据

            if (!$is_custom && $specGoodsPrice['store_count'] < $data['goods_num']) {
                return $this->json_error_msg('商品库存不足!');
            }
            //订单商品数据
            $order_goods_info = $this->getOrderGoodsInfo($data['goods_num'], $data['goods_id'],$key);
            if(!$order_goods_info){
                $this->api_return('',$status=0,$msg='商品参数有误');
            }
            $order_goods_info['item_id'] = $specGoodsPrice['item_id'];
            $total_price = round($specGoodsPrice['price'] * $data['goods_num'], 2);
            $order_goods_info['final_price'] = $specGoodsPrice['price'];
            $order_goods_info['goods_price'] = $specGoodsPrice['price'];
            //订单信息
            if (!$is_custom) {
                $orderInfo['complete_goods'] = 1;
            }
            $orderInfo['service_msg'] = $service_msg;
            $orderInfo['order_sn'] = $this->create_order_sn($userid);
            $orderInfo['designer_id'] = db('goods')->where('goods_id', $data['goods_id'])->value('user_id');
            $orderInfo['goods_price'] = $total_price;
            $orderInfo['total_amount'] = $total_price;
            $orderInfo['order_amount'] = $total_price;
            //支付传递的数组
			$total_price = $total_price*100;
            $pay_arr = ['order_sn' => $orderInfo['order_sn'], 'total_fee' => $total_price, 'openid' => $openid];
            //开启事务
            Db::startTrans();
            try {
                $order_id = db('order')->insertGetId($orderInfo);
                $order_goods_info['order_id'] = $order_id;
                $res = db('order_goods')->insert($order_goods_info);
                //非定制库存处理
                if (!$is_custom) {
                    db('specGoodsPrice')->where(['key' => $key, 'goods_id' => $data['goods_id']])->setDec('store_count', $data['goods_num']);
                    db('goods')->where('goods_id', $data['goods_id'])->setDec('store_count', $data['goods_num']);
                }
                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
            }
        }
        if ($res) {
            //订单生成,调用支付接口
            $payment = controller('payment');
            $result['prepayId'] = $payment->getPrepayId($pay_arr);
            $result['order_id'] = $order_id;
            $this->api_return($result, $status = 1, $msg = '订单已提交');
        }
        $this->api_return('', $status = 0, $msg = '订单生成失败');
    }

    /**
    ** [获取商品库存相关信息]
    ** @date   2019-08-21T10:21:46+0800
    ** @param  array ['key'=>string,'goods_id'=>(int)]
    ** @param  array ['item_id'=>(string)]
    ** @author dyp
    */
    public function getStoreCount($param = [])
    {
        if (isset($param['item_id'])) {
            $map['item_id'] = $param['item_id'];
        } else {
            $map['key'] = $param['key'];
            $map['goods_id'] = $param['goods_id'];
        }
        $data = db('specGoodsPrice')->where($map)->find();
        return $data;
    }

    /**
     ** [判断商品是否是定制]
     ** @date   2019-08-21T10:21:46+0800
     ** @author dyp
     ** @param $param
     ** @return bool
     */

    public function custom_goods($param = [])
    {
        $map['key_name'] = array('like', '%定制%');
        if (isset($param['item_id'])) {
            $map['item_id'] = $param['item_id'];
        } else {
            $map['goods_id'] = $param['goods_id'];
            $map['key'] = $param['key'];
        }
        $res = db('specGoodsPrice')->field('key_name')->where($map)->find();
        if ($res) {
            return true;
        }
        return false;
    }

    /**
     ** [获取单个订单商品信息]
     ** @date   2019-08-21T10:21:46+0800
     ** @param  $goods_num  int
     ** @param  $goods_id  int
     ** @return array
     ** @author dyp
     */
    public function getOrderGoodsInfo($goods_num = 0, $goods_id = 0)
    {
        $goodsInfo = db('goods')->where(['goods_id' => $goods_id])->find();
        //订单商品数据
        $orderGoodsInfo['goods_num'] = $goods_num;
        $orderGoodsInfo['goods_id'] = $goodsInfo['goods_id'];
        $orderGoodsInfo['goods_name'] = $goodsInfo['goods_name'];
        $orderGoodsInfo['goods_sn'] = $goodsInfo['goods_sn'];
        return $orderGoodsInfo;
    }

    /**
     ** [获取商品库存相关信息]
     ** @date   2019-08-21T10:21:46+0800
     ** @param  $user_id  int
     ** @author dyp
     */
    public function create_order_sn($user_id = 0)
    {
        $order_sn = date('YmdHis') . $user_id . rand(1000, 9999);
        return $order_sn;
    }
}