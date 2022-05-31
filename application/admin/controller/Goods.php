<?php
/**
 * [商品管理]
 * @date   2019-08-05T10:21:46+0800
 * @author dyp
 */
namespace app\admin\controller;
use app\admin\tools\MenuTree;
use think\Db;
class Goods extends Common
{
    //商品列表
	public function index(){
		$map = array();
		$param['cat_id']  = input('post.cat_id',0);
		$param['keyword'] = trim(input('post.keyword',''));
		$param['goods_sn'] = trim(input('post.goods_sn',''));
		if($param['cat_id']){
			$map['cat_id'] = $param['cat_id'];
		}
		if($param['keyword']){
			$map['goods_name'] = array('like', '%' . $param['keyword'] . '%');
		}
		if($param['goods_sn']){
			$map['goods_sn'] = $param['goods_sn'];
		}
        $list = db('goods')->where($map)->order('sort desc,goods_id desc')->paginate(10);
		$result = $this->_list($list);
        foreach ($result['list'] as $key => $val) {
            $result['list'][$key]['cate_name'] = db('goods_category')->where('id', $val['cat_id'])->value('name');
        }
		return $this->fetch('index',[
			'pages' => $result['pages'],//分页
            'list' => $result['list'],//品牌数据
            'cates' => $this->treeList(),//商品分类
			'param' => $param,
		]);
	}
	//商品添加
	public function add(){
	    if(!empty($_POST)){
			$data = input('post.');
			//var_dump($data);exit;
			/**商品基础信息处理**/
			if(!$data['goods_name']){
				$this->ajaxReturn(['status'=>false,'msg'=>'请填写商品名']);
			}
			if(!$data['cat_id']){
				$this->ajaxReturn(['status'=>false,'msg'=>'请选择商品类别']);
			}
			if(!$data['nav_id']){
				$this->ajaxReturn(['status'=>false,'msg'=>'请选择商品所属导航栏']);
			}
			if(!$data['brand_id']){
				$this->ajaxReturn(['status'=>false,'msg'=>'请选择商品所属品牌']);
			}
			if(!$data['user_id']){
				$this->ajaxReturn(['status'=>false,'msg'=>'请选择商品设计师']);
			}
			if(!$data['price']){
				$this->ajaxReturn(['status'=>false,'msg'=>'请填写商品价格']);
			}
			if(!$data['original_img']){
				$this->ajaxReturn(['status'=>false,'msg'=>'请上传商品图片']);
			}
			if(!$data['goods_content']){
				$this->ajaxReturn(['status'=>false,'msg'=>'请填写商品详情']);
			}
			$data['last_update'] = time();
			//如果是纯成衣
			if($data['is_complete'] && $data['item']){
				$store_count = 0;
				foreach($data['item'] as $key=>$val){
					$store_count += $val['store_count'];
				}
				$data['store_count'] = $store_count;
			}
			//如果开启商品预售,进行信息检测
			if(isset($data['is_pre_sale'])&& $data['is_pre_sale']!=0){
				if(!$data['pre_sale_num']){
					$this->ajaxReturn(['status'=>false,'msg'=>'请填写预售件数']);
				}
				$data['pre_sale_start_time'] = strtotime($data['pre_sale_start_time']);
				$data['pre_sale_end_time'] = strtotime($data['pre_sale_end_time']);
			}else{
				unset($data['pre_sale_start_time'],$data['pre_sale_end_time']);
			}
			//披露材料处理
			if(isset($data['material'])){
				foreach($data['material'] as $key=>$val){
					$arr[$key]['name'] = $data['original_name'][$key];
					$arr[$key]['url'] = $val;
				}
				$data['material'] = $arr;
				$data['material'] = json_encode($data['material']);
				unset($data['original_name']);
			}
			//风格搭配处理
			if(isset($data['style_match'])){
				$data['style_match'] = json_encode($data['style_match']);
			}
			//获取当前goods表信息
			$goodsTableDetail = Db::query("SHOW TABLE STATUS WHERE NAME = 'el_goods'");
			//生成8位0补齐货号
			$data['goods_sn'] = 'EL'.sprintf("%08d", $goodsTableDetail[0]['Auto_increment']);
			//unset掉相册及模型信息
			unset($data['img_url'],$data['images'],$data['spec_item_image'],$data['item']);
			//开启事务
			Db::startTrans();
			try{
				$goods_id = db('goods')->insertGetId($data);
				/**商品相册处理**/
				$pic = input('post.img_url/a');
				if(isset($pic)){
					foreach($pic as $key=>$val){
						$pic_data[] = [
							'goods_id' => $goods_id,
							'image_url'  => $val
						];
					}
					
					db('goods_images')->insertAll($pic_data);
					//$this->ajaxReturn(['status'=>false,'msg'=>'请上传商品相册图']);
				}
				/**商品模型处理**/
				$model_id = input('post.model_id',0);
				if($model_id){
					$spec_item_image = input('post.spec_item_image/a');
					//规格开启上传图片时,进行入库
					if(isset($spec_item_image)){
						foreach($spec_item_image as $key=>$val){
							$spec_item_image_data[] = [
								'goods_id' => $goods_id,
								'spec_item_id'  => $key,
								'src' => $val
							];
						}
						db('spec_image')->insertAll($spec_item_image_data);
					}
					$item = input('post.item/a');
					//选中不同规格产生的笛卡尔积入库
					if(isset($item)){
						foreach($item as $key=>$val){
							$item_data[] = [
								'goods_id' => $goods_id,
								'key' => $key,
								'key_name' => $val['key_name'],
								'price' => $val['price'],
								'cost_price' => $val['cost_price'],
								'store_count' => $val['store_count']
							];
						}
						db('spec_goods_price')->insertAll($item_data);
					}
				}
				// 提交事务
				Db::commit();
			} catch (\Exception $e) {
				// 回滚事务
				Db::rollback();
			}
			if($goods_id){
				$this->ajaxReturn(['msg'=>'添加成功']);
			}
	        $this->ajaxReturn(['status'=>false,'msg'=>'添加失败']);
        }
		//商品模型
		$goods_model = db('goods_model')->where('status',1)->order('sort desc')->select();
		//商品品牌
		$brand = db('brand')->field('id,name,logo,is_show')->where('is_show',1)->order('sort desc')->select();
		//商品设计师
		$designer = db('users')->field('user_id,nickname,head_pic')->where('is_partner',1)->select();
		//商品所属导航栏
		$goods_nav = db('nav_list')->where('show',1)->select();
	    return $this->fetch('add',[
			'cates' => $this->treeList(),//商品分类
			'brand' => $brand,
			'designer' => $designer,
			'goods_model' => $goods_model,
			'goods_nav' => $goods_nav,
		]);
    }

    //商品编辑
    public function edit()
    {
		if(!empty($_POST)){
			$data = input('post.');
			//var_dump($data);exit;
			/**商品基础信息处理**/
			if(!$data['goods_name']){
				$this->ajaxReturn(['status'=>false,'msg'=>'请填写商品名']);
			}
			if(!$data['cat_id']){
				$this->ajaxReturn(['status'=>false,'msg'=>'请选择商品类别']);
			}
			if(!$data['nav_id']){
				$this->ajaxReturn(['status'=>false,'msg'=>'请选择商品所属导航栏']);
			}
			if(!$data['brand_id']){
				$this->ajaxReturn(['status'=>false,'msg'=>'请选择商品所属品牌']);
			}
			if(!$data['user_id']){
				$this->ajaxReturn(['status'=>false,'msg'=>'请选择商品设计师']);
			}
			if(!$data['price']){
				$this->ajaxReturn(['status'=>false,'msg'=>'请填写商品价格']);
			}
			if(!$data['original_img']){
				$this->ajaxReturn(['status'=>false,'msg'=>'请上传商品图片']);
			}
			if(!$data['goods_content']){
				$this->ajaxReturn(['status'=>false,'msg'=>'请填写商品详情']);
			}
			if($data['old_model_id']!=0 && $data['model_id'] != $data['old_model_id']){
				$this->ajaxReturn(['status'=>false,'msg'=>'原有模型改变,编辑失败']);
			}
			$data['last_update'] = time();
			//如果是纯成衣
			if($data['is_complete'] && $data['item']){
				$store_count = 0;
				foreach($data['item'] as $key=>$val){
					$store_count += $val['store_count'];
				}
				$data['store_count'] = $store_count;
			}
			//如果开启商品预售,进行信息检测
			if(isset($data['is_pre_sale'])&& $data['is_pre_sale']!=0){
				if(!$data['pre_sale_num']){
					$this->ajaxReturn(['status'=>false,'msg'=>'请填写预售件数']);
				}
				$data['pre_sale_start_time'] = strtotime($data['pre_sale_start_time']);
				$data['pre_sale_end_time'] = strtotime($data['pre_sale_end_time']);
			}else{
				unset($data['pre_sale_start_time'],$data['pre_sale_end_time']);
			}
			//披露材料处理
			if(isset($data['material'])){
				foreach($data['material'] as $key=>$val){
					$arr[$key]['name'] = $data['original_name'][$key];
					$arr[$key]['url'] = $val;
				}
				$data['material'] = $arr;
				$data['material'] = json_encode($data['material']);
				unset($data['original_name']);
			}
			//var_dump($data);exit;
			//风格搭配处理
			if(isset($data['style_match'])){
				$data['style_match'] = json_encode($data['style_match']);
			}
			//unset掉相册及模型信息
			unset($data['img_url'],$data['images'],$data['spec_item_image'],$data['item'],$data['old_model_id']);
			//开启事务
			Db::startTrans();
			try{
				//基础信息修改
				$res = db('goods')->where('goods_id',$data['goods_id'])->update($data);
				/**商品相册暂不做处理**/
				/*$pic = input('post.img_url/a');
				if(isset($pic)){
					foreach($pic as $key=>$val){
						$pic_data = [
							'goods_id' => $goods_id,
							'image_url'  => $val
						];
					}
					
					db('goods_images')->update($pic_data);
				}*/
				if($data['model_id']){
					$spec_item_image = input('post.spec_item_image/a');
					//规格开启上传图片时,进行修改入库
					if(isset($spec_item_image)){
						$spec_item_id = db('spec_image')->where('goods_id',$data['goods_id'])->column('spec_item_id,id');
						foreach($spec_item_image as $key=>$val){
							$spec_item_image_data = [
								'goods_id' => $data['goods_id'],
								'spec_item_id'  => $key,
								'src' => $val
							];
							//原有存在模型图修改,不存在则天级
							if(isset($spec_item_id[$key])){
								db('spec_image')->where(['goods_id'=>$data['goods_id'],'spec_item_id'=>$key])->update($spec_item_image_data);
							}else{
								db('spec_image')->insert($spec_item_image_data);
							}
							unset($spec_item_id[$key]);
						}
						//若数据库还存在多余模型图则删除
						if(count($spec_item_id)){
							foreach($spec_item_id as $key=>$val){
								db('spec_image')->where('id',$val)->delete();
							}
						}
					}
					$item = input('post.item/a');
					//选中不同规格产生的笛卡尔积入库/修改/删除
					if(isset($item)){
						$keySpecGoodsPrice = db('SpecGoodsPrice')->where('goods_id',$data['goods_id'])->column('key,item_id');
						foreach($item as $key=>$val){
							$item_data = [
								'goods_id' => $data['goods_id'],
								'key' => $key,
								'key_name' => $val['key_name'],
								'price' => $val['price'],
								'cost_price' => $val['cost_price'],
								'store_count' => $val['store_count']
							];
							//原有存在模型图修改,不存在则添加
							if(isset($keySpecGoodsPrice[$key])){
								db('SpecGoodsPrice')->where(['goods_id'=>$data['goods_id'],'key'=>$key])->update($item_data);
							}else{
								db('SpecGoodsPrice')->insert($item_data);
							}
							unset($keySpecGoodsPrice[$key]);
						}
						//原有库中选中的笛卡尔积存在多余,则删除
						if(count($keySpecGoodsPrice)){
							foreach($keySpecGoodsPrice as $key=>$val){
								db('SpecGoodsPrice')->where('item_id',$val)->delete();
							}
						}
					}
				}
			// 提交事务
				Db::commit();
			} catch (\Exception $e) {
				// 回滚事务
				Db::rollback();
			}
			if($res === false){
				$this->ajaxReturn(['status'=>false,'msg'=>'商品编辑失败']);
			}
	        $this->ajaxReturn(['msg'=>'商品编辑成功']);
        }
		$id = input('param.id',0);//商品id
		if(!$id){
			$this->ajaxReturn(['status'=>false,'msg'=>'参数有误']);
		}
		//商品模型
		$goods_model = db('goods_model')->where('status',1)->order('sort desc')->select();
		//商品品牌
		$brand = db('brand')->field('id,name,logo,is_show')->where('is_show',1)->order('sort desc')->select();
		//商品设计师
		$designer = db('users')->field('user_id,nickname,head_pic')->where('is_partner',1)->select();
		//商品所属导航栏
		$goods_nav = db('nav_list')->where('show',1)->select();
		$data = db('goods')->where('goods_id',$id)->find();
		$data['match'] = array();
		if($data['style_match']){
			$match = json_decode($data['style_match'],320);
			foreach($match as $key=>$val){
				$data['match'][$key]['value'] = $val;
				$data['match'][$key]['name'] = db('goods')->where('goods_id',$val)->value('goods_name');
			}
		}
		if($data['material']){
			$material = json_decode($data['material'],320);
			$data['material_url'] = '';
			$data['material_name'] = '';
			foreach($material as $key=>$val){
				$data['material_url'].= $val['url'].',';
				$data['material_name'].= $val['name'].',';
			}
			$data['material_url'] = substr($data['material_url'],0,strlen($data['material_url'])-1);
			$data['material_name'] = substr($data['material_name'],0,strlen($data['material_name'])-1);
		}
	
        return $this->fetch('edit', [
			'data' => $data,
            'cates' => $this->treeList(),//商品分类
			'brand' => $brand,
			'designer' => $designer,
			'goods_model' => $goods_model,
			'goods_nav' => $goods_nav,
        ]);
    }
	//商品预览
	public function preview(){
		$goods_id = input('param.id',0);
		if(!$goods_id){
			$this->ajaxReturn(['status'=>false,'msg'=>'参数有误']);
		}
		$data = db('goods')->field('goods_id,goods_name,goods_sn,price,original_img')->where('goods_id',$goods_id)->find();
		return $this->fetch('preview',['data'=>$data]);
	}
	//风格搭配ajax请求
	public function search_style(){
		$words = input('post.value','');
		$goods_id = input('post.goods_id','');
		$map = array();
		$map['is_show'] = 1;
		if($words){
			$map['goods_name'] = array('like', '%' . $words . '%');
		}
		if($goods_id){
			$goods_id_arr = explode(',',$goods_id);
			$map['goods_id'] = array('not in',$goods_id_arr);
		}
		$data = db('goods')->field('goods_id,goods_name,sort')->where($map)->limit(10)->order('sort desc')->select();
		$this->ajaxReturn(['data' => $data]);
	}
    public function ajaxSpecSelect()
    {
        $goods_id = input('goods_id/d', 0);
        $model_id = input('model_id/d', 0);
		$action   = input('action/s','');
        $specList = db('spec')->where("model_id", $model_id)->order('order desc')->select();
        foreach ($specList as $k => $v) {
            $specList[$k]['spec_item'] = db('SpecItem')->field('id,item')->where('spec_id', $v['id'])->select(); // 获取规格项
        }
        $items_id = db('SpecGoodsPrice')->field("GROUP_CONCAT(`key` SEPARATOR '_') AS items_id")->where('goods_id', $goods_id)->select();
		$items_ids = explode('_', $items_id[0]['items_id']);
        // 获取商品规格图片
        if ($goods_id) {
            $specImageList = db('spec_image')->where("goods_id", $goods_id)->column('spec_item_id,src');
            $this->assign('specImageList', $specImageList);
        }
		$this->assign('action', $action);
        $this->assign('items_ids', $items_ids);
        $this->assign('specList', $specList);
        return $this->fetch('ajax_spec_select');
	}

    public function ajaxGetSpecInput()
    {
        $spec_arr = input('post.spec_arr/a', array());
        $goods_id = input('post.goods_id/d', 0);
        if (!$spec_arr) {
            return false;
        }
        foreach ($spec_arr as $k => $v) {
            $spec_arr_sort[$k] = count($v);
        }
        asort($spec_arr_sort);  //按键值进行排序
        foreach ($spec_arr_sort as $key => $val) {
            $spec_arr2[$key] = $spec_arr[$key];
        }
        $clo_name = array_keys($spec_arr2);
        $spec_arr2 = $this->combineDika($spec_arr2); //  获取 规格的 笛卡尔积 
        $spec = db('Spec')->column('id,name'); // 规格表
        $specItem = db('SpecItem')->column('id,item,spec_id');//规格项
        $keySpecGoodsPrice = db('SpecGoodsPrice')->where('goods_id', $goods_id)->column('key,key_name,price,store_count,cost_price');//规格项
		$str = "<table class='table table-bordered' id='spec_input_tab'>";
        $str .= "<tr>";
        $str_fill = "<tr>";
        // 显示第一行的数据
        foreach ($clo_name as $k => $v) {
            $str .= " <td><b>{$spec[$v]}</b></td>";
            $str_fill .= " <td><b></b></td>";
        }
        $str .= "<td><b>购买价</b></td>
               <td><b>成本价</b></td>
               <td><b>库存</b></td>
               <td><b>操作</b></td>
             </tr>";
        if (count($spec_arr2) > 0) {
            $str_fill .= '<td><input id="item_price" class="form-control" value="0" onkeyup="this.value=this.value.replace(/[^\d.]/g,&quot;&quot;)"></td>
               <td><input id="item_cost_price" class="form-control" value="0" onkeyup="this.value=this.value.replace(/[^\d.]/g,&quot;&quot;)" onpaste="this.value=this.value.replace(/[^\d.]/g,&quot;&quot;)"></td>
               <td><input id="item_store_count" class="form-control" value="0" onkeyup="this.value=this.value.replace(/[^\d.]/g,&quot;&quot;)" onpaste="this.value=this.value.replace(/[^\d.]/g,&quot;&quot;)"></td>
               <td><button id="item_fill" type="button" class="btn btn-primary">批量填充</button></td>
             </tr>';
            $str .= $str_fill;
        }
		//126 41 22_26_31
        // 显示第二行开始
        foreach ($spec_arr2 as $k => $v) {
            $str .= "<tr>";
            $item_key_name = array();
            foreach ($v as $k2 => $v2) {
                $str .= "<td>{$specItem[$v2]['item']}</td>";
                $item_key_name[$v2] = $spec[$specItem[$v2]['spec_id']] . ':' . $specItem[$v2]['item'];
            }
            ksort($item_key_name);
            $item_key = implode('_', array_keys($item_key_name));
            $item_name = implode(',', $item_key_name);
			if($keySpecGoodsPrice){
				if(!isset($keySpecGoodsPrice[$item_key])){
					$str .= "<td><input name='item[$item_key][price]' class='form-control' value='' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")'  disabled='disabled'></td>";
					$str .= "<td><input name='item[$item_key][cost_price]' class='form-control' value='' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' disabled='disabled'></td>";
					$str .= "<td><input name='item[$item_key][store_count]' class='form-control' value='' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")'disabled='disabled'><input type='hidden' name='item[$item_key][key_name]' value='$item_name' disabled='disabled'></td>";
					$str .= "<td><button type='button' class='btn delete_item'>有效</button></td>";
				}else{
					$keySpecGoodsPrice[$item_key]['price'] ? false : $keySpecGoodsPrice[$item_key]['price'] = 0; // 价格默认为0
					$keySpecGoodsPrice[$item_key]['store_count'] ? false : $keySpecGoodsPrice[$item_key]['store_count'] = 0; //库存默认为0
					$keySpecGoodsPrice[$item_key]['cost_price'] ? false : $keySpecGoodsPrice[$item_key]['cost_price'] = 0; //成本价默认为0
					$str .= "<td><input name='item[$item_key][price]' class='form-control' value='{$keySpecGoodsPrice[$item_key]['price']}' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' / ></td>";
					$str .= "<td><input name='item[$item_key][cost_price]' class='form-control' value='{$keySpecGoodsPrice[$item_key]['cost_price']}' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' /></td>";
					$str .= "<td><input name='item[$item_key][store_count]' class='form-control' value='{$keySpecGoodsPrice[$item_key]['store_count']}' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")'/><input type='hidden' name='item[$item_key][key_name]' value='$item_name' /></td>";
					$str .= "<td><button type='button' class='btn delete_item'>无效</button></td>";
				}
			}else{
				$str .= "<td><input name='item[$item_key][price]' class='form-control' value='' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' / ></td>";
				$str .= "<td><input name='item[$item_key][cost_price]' class='form-control' value='' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' /></td>";
				$str .= "<td><input name='item[$item_key][store_count]' class='form-control' value='' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")'/><input type='hidden' name='item[$item_key][key_name]' value='$item_name' /></td>";
				$str .= "<td><button type='button' class='btn delete_item'>无效</button></td>";
			}
            $str .= "</tr>";
        }
        $str .= "</table>";
        return $str;
    }

    //商品模型列表
    public function model()
    {
		$param['keyword'] = trim(input('post.keyword',''));
		$map = array();
		if($param['keyword']){
			$map['name'] = array('like', '%' . $param['keyword'] . '%');
		}
        $list = db('goods_model')->where($map)->order('sort desc')->paginate(10);
        return $this->fetch('model', [
            'list' => $list,
			'param' => $param
        ]);
    }

    //商品模型添加
    public function model_add()
    {
        if (!empty($_POST)) {
            $data = input('post.');
            if (empty($data['name'])) {
                $this->ajaxReturn(['status' => false, 'msg' => '请填写模型名称']);
            }
            if (count($data['info']) <= 3) {
                $this->ajaxReturn(['status' => false, 'msg' => '请填写规格名并附上规格值']);
            }
            $num = db('goods_model')->where('name', $data['name'])->count();
            if ($num) {
                $this->ajaxReturn(['status' => false, 'msg' => '该模型已添加']);
            }
            $model['name'] = $data['name'];
            $model['sort'] = $data['sort'] ?: 0;
            $model['status'] = $data['status'] ?: 1;
            $model_id = db('goods_model')->insertGetId($model);
            foreach ($data['info']['name'] as $key => $val) {
                $spec['model_id'] = $model_id;
                $spec['name'] = $val;
                $spec['order'] = $data['info']['order'][$key];
                $spec['is_upload_image'] = $data['info']['is_upload_image'][$key];
                $spec_id = db('spec')->insertGetId($spec);
                if ($spec_id) {
                    foreach ($data['info']['spec'][$key] as $k => $v) {
                        $spec_item['spec_id'] = $spec_id;
                        $spec_item['item'] = $v;
                        db('spec_item')->insert($spec_item);
                    }
                } else {
                    $this->ajaxReturn(['status' => false, 'data' => $data, 'msg' => 'error connection']);
                }
            }
            $this->ajaxReturn(['msg' => '添加成功']);
        }
        return $this->fetch();
    }

    //商品模型修改
    public function model_edit()
    {
        $id = input('param.id');
        if (!empty($_POST)) {
            $data = input('post.');
            if (empty($data['name'])) {
                $this->ajaxReturn(['status' => false, 'msg' => '请填写模型名称']);
            }
            $model['name'] = $data['name'];
            $model['sort'] = $data['sort'] ?: 0;
            $model['status'] = $data['status'] ?: 1;
            db('goods_model')->where('id', $data['id'])->update($model);//修改模型内容
            $info = input('post.info/s', []);//获取新添加的数据
            if ($info) {
                if (count($data['info']) <= 3) {
                    $this->ajaxReturn(['status' => false, 'msg' => '请填写规格名对应规格值']);
                }
                foreach ($data['info']['name'] as $key => $val) {
                    $spec['model_id'] = $id;
                    $spec['name'] = $val;
                    $spec['order'] = $data['info']['order'][$key];
                    $spec['is_upload_image'] = $data['info']['is_upload_image'][$key];
                    $spec_id = db('spec')->insertGetId($spec);
                    if ($spec_id) {
                        $key = $data['info']['key'][$key];//修改key值获取规格值
                        foreach ($data['info']['spec'][$key] as $k => $v) {
                            $spec_item['spec_id'] = $spec_id;
                            $spec_item['item'] = $v;
                            db('spec_item')->insert($spec_item);
                        }
                    } else {
                        $this->ajaxReturn(['status' => false, 'data' => $data['info'], 'msg' => 'error connection']);
                    }
                }
                $this->ajaxReturn(['msg' => '添加编辑成功']);
            }
            $this->ajaxReturn(['msg' => '编辑成功']);
        }
        //获取页面编辑数据
        $model = db('goods_model')->where('id', $id)->find();
        $spec = db('spec')->where('model_id', $id)->select();
        foreach ($spec as $key => $val) {
            $spec[$key]['item'] = db('spec_item')->where('spec_id', $val['id'])->select();
        }
        return $this->fetch('model_edit', [
            'model' => $model,
            'spec' => $spec,
        ]);
    }
	//模型删除
	public function model_del(){
		$id = input('post.id/d',0);
		$spec_num = count(db('spec')->field('id')->where('model_id',$id)->select());
		if($spec_num >0){
			$this->ajaxReturn(['status' => false,'data' => $spec_num,'msg' => '该模型下存在规格,无法删除']);
		}
		$res = db('goods_model')->where('id',$id)->delete();
		if($res){
			$this->ajaxReturn(['msg' => '模型删除成功']);
		}
		$this->ajaxReturn(['status'=>false,'msg' => '模型删除失败']);
	}
    public function model_spec_ajax()
    {
        $id = input('post.id/d', 0);
        if ($id) {
            $count = db('specItem')->where('spec_id', $id)->count();
            if ($count >= 1) {
                $this->ajaxReturn(['status' => false, 'msg' => '请先删除该规格下的规格值']);
            }
            db('spec')->where('id', $id)->delete();
            $this->ajaxReturn(['msg' => '删除成功']);
        }
        $this->ajaxReturn(['status' => false, 'msg' => '参数有误']);
    }

    public function model_item_ajax()
    {
        $id = input('post.id/d', 0);
        $spec_id = input('post.spec_id/d', 0);
        if ($id) {
            $map = array();
            $map['key'] = array('like', '%' . $id . '%');
            $count = db('spec_goods_price')->where($map)->count();
            if ($count >= 1) {
                $this->ajaxReturn(['status' => false, 'msg' => '该规格值存在商品无法删除']);
            }
            db('specItem')->where('id', $id)->delete();
            $this->ajaxReturn(['msg' => '删除成功']);
        }
        if ($spec_id) {
            $data['spec_id'] = $spec_id;
            $data['item'] = input('post.item', '');
            $spec_item_id = db('specItem')->insertGetId($data);
            if ($spec_item_id) {
                $this->ajaxReturn(['data' => $spec_item_id, 'msg' => '添加成功']);
            }
            $this->ajaxReturn(['status' => false, 'msg' => '添加失败']);
        }
        $this->ajaxReturn(['status' => false, 'msg' => '参数有误']);
    }

    //商品分类
    public function cates()
    {
        if (!empty($_POST)) {
            $data = db('goods_category')->field('id,name as text,pid,sort,image')->where('is_show', 1)->order('sort asc')->select();
            foreach ($data as $k => $v) {
                $data[$k]['after_html'] = '<span class="button_z">
				<button type="button" class="btn btn-info btn-xs" onclick="edit(' . $v['id'] . ')">编辑</button>
				<button type="button" class="btn btn-danger btn-xs btn-del"  onclick="del(' . $v['id'] . ')">删除</button>
				</span>';
            }
            $data = $this->array2tree($data, 'pid', 'nodes');
            exit(json_encode($data));
        }
        return $this->fetch();
    }

    //添加分类
    public function cate_add()
    {
        if (!empty($_POST)) {
            if (empty($_POST['name'])) {
                return $this->ajaxReturn(['status' => false, 'msg' => '请填写商品分类名']);
            }
            $_POST['sort'] = $_POST['sort'] ? : 0;
            $res = db('goods_category')->insert($_POST);
            if ($res) {
                return $this->ajaxReturn(['msg' => '添加成功']);
            } else {
                return $this->ajaxReturn(['status' => false, 'msg' => '数据有误']);
            }
        }
        return $this->fetch('cate_add', [
            'cates' => $this->treeList(),//商品分类
        ]);
    }

    //编辑分类
    public function cate_edit()
    {
        $id = input('param.id');
        if (!empty($_POST)) {
            $data = input('post.');
			if (!$data['name']) {
                return $this->ajaxReturn(['status' => false, 'msg' => '请填写商品分类名']);
            }
			if($data['id'] == $data['pid']){
				$this->ajaxReturn(['status' => false,'msg' => '无法选取自己作为父类']);
			}
            $next_cate = db('goods_category')->where('pid', $id)->select();
			if ($next_cate) {
				$this->ajaxReturn(['status' => false,'msg' => '该类下面存在子类,无法移动']);
			}
            $data['sort'] = $data['sort'] ?: 0;
            $data['is_show'] = $data['is_show'] ?: 0;
            $res = db('goods_category')->where('id', $data['id'])->update($data);
            if ($res === false) {
				$this->ajaxReturn(['status'=> false,'msg' => "编辑失败"]); 
            } 
			$this->ajaxReturn(['msg'=>"编辑成功"]);
        }

        //回显
        $data = db('goods_category')->where('id', $id)->find();
        return $this->fetch('cate_edit', [
            'cates' => $this->treeList(),//商品分类
            'data' => $data,
        ]);
    }
    //删除分类
    public function cate_del($id)
    {
		$id = input('post.id',0);
		if(!$id){
			$this->ajaxReturn(array('status' => false, 'msg' => '参数有误'));
		}
        //下面还有无下级分类
        $res = db('goods_category')->where('pid', $id)->select();
        if ($res) {
            $this->ajaxReturn(array('status' => false, 'msg' => '该分类下还有商品'));
        } else {
            $result = db('goods_category')->where('id', $id)->delete();
            if ($result) {
                $this->ajaxReturn(array('msg' => '删除成功'));
            }
			$this->ajaxReturn(array('status' => false, 'msg' => '删除失败'));
        }
    }
	//即将上线列表
	public function online(){
		$map = array();
		$param['keyword'] = trim(input('post.keyword',''));
		if($param['keyword']){
			$map['desciption'] = array('like', '%' . $param['keyword'] . '%');
		}
		$list = db('goods_online')->where($map)->order('sort desc')->paginate(10);
		$result = $this->_list($list);
        foreach ($result['list'] as $key => $val) {
            $result['list'][$key]['nav_name'] = db('nav_list')->where('id', $val['nav_id'])->value('name');
        }
		return $this->fetch('online',[
			'list' => $result['list'],
			'pages' => $result['pages'],//分页
			'param' => $param,
		]);
	}
	//即将上线添加
	public function online_add(){
		if (!empty($_POST)) {
			$data = input('post.');
			if(!$data['desciption']){
				$this->ajaxReturn(['status' => false, 'msg' => '请填写上线描述']);
			}
			if(!$data['nav_id']){
				$this->ajaxReturn(['status' => false, 'msg' => '请选择所属导航栏']);
			}
			if(!$data['img_url']){
				$this->ajaxReturn(['status' => false, 'msg' => '请上传图片']);
			}
			$data['online_time'] = strtotime($data['online_time']);
			$data['add_time'] = time();
			$res = db('goods_online')->insert($data);
			if($res){
				$this->ajaxReturn(['msg' => '添加成功']);
			}
			$this->ajaxReturn(['status' => false, 'msg' => '添加失败']);
		}
		$goods_nav = db('nav_list')->where('show',1)->order('sort desc')->select();
		return $this->fetch('online_add',['goods_nav' => $goods_nav]);
	}
	//即将上线编辑
	public function online_edit(){
		if (!empty($_POST)) {
			$data = input('post.');
			$id = $data['id'];
			if(!$data['desciption']){
				$this->ajaxReturn(['status' => false, 'msg' => '请填写上线描述']);
			}
			if(!$data['nav_id']){
				$this->ajaxReturn(['status' => false, 'msg' => '请选择所属导航栏']);
			}
			$data['online_time'] = strtotime($data['online_time']);
			$res = db('goods_online')->where('id',$id)->update($data);
			if($res === false){
				$this->ajaxReturn(['status' => false, 'msg' => '编辑失败']);
			}
			$this->ajaxReturn(['msg' => '编辑成功']);
		}
		$id = input('param.id');
		$data = db('goods_online')->where('id',$id)->find();
		$goods_nav = db('nav_list')->where('show',1)->order('sort desc')->select();
		return $this->fetch('online_edit',['data' => $data,'goods_nav' => $goods_nav]);
	}
    /**
     * [获取树状菜单列表]
     * @date   2016-09-05T10:21:46+0800
     * @author dyp
     */
    public function treeList()
    {
        $map = [];
        $list = [];
        $map['is_show'] = 1;
        //格式化菜单
        $result = db('goods_category')->where($map)->field('id,pid,name,sort,image')->order('sort asc')->select();
        if ($result) {
            $tree = new MenuTree();
            $tree->setConfig('id', 'pid');
            $list = $tree->getLevelTreeArray($result);
            if (isset($list) && $list) {
                foreach ($list as $key => $value) {
                    $list[$key]['htmlname'] = @$value['delimiter'] . $value['name'];
                }
            }
        }
        return $list;
    }

    /**
     * 多个数组的笛卡尔积
     *
     * @param unknown_type $data
     */
    function combineDika()
    {
        $data = func_get_args();
        $data = current($data);
        $cnt = count($data);
        $result = array();
        $arr1 = array_shift($data);
        foreach ($arr1 as $key => $item) {
            $result[] = array($item);
        }

        foreach ($data as $key => $item) {
            $result = $this->combineArray($result, $item);
        }
        return $result;
    }

    /**
     * 两个数组的笛卡尔积
     * @param unknown_type $arr1
     * @param unknown_type $arr2
     */
    function combineArray($arr1, $arr2)
    {
        $result = array();
        foreach ($arr1 as $item1) {
            foreach ($arr2 as $item2) {
                $temp = $item1;
                $temp[] = $item2;
                $result[] = $temp;
            }
        }
        return $result;
    }

    /**
     * 获取 规格的 笛卡尔积
     * @param $goods_id 商品 id
     * @param $spec_arr 笛卡尔积
     * @return string 返回表格字符串
     */
    public function getSpecInput($goods_id, $spec_arr)
    {
        // <input name="item[2_4_7][price]" value="100" /><input name="item[2_4_7][name]" value="蓝色_S_长袖" />        
        /*$spec_arr = array(         
            20 => array('7','8','9'),
            10=>array('1','2'),
            1 => array('3','4'),
            
        );  */
        // 排序
        foreach ($spec_arr as $k => $v) {
            $spec_arr_sort[$k] = count($v);
        }
        asort($spec_arr_sort);
        foreach ($spec_arr_sort as $key => $val) {
            $spec_arr2[$key] = $spec_arr[$key];
        }
        $clo_name = array_keys($spec_arr2);
        $spec_arr2 = combineDika($spec_arr2); //  获取 规格的 笛卡尔积                             
        $spec = M('Spec')->getField('id,name'); // 规格表
        $specItem = M('SpecItem')->getField('id,item,spec_id');//规格项
        $keySpecGoodsPrice = M('SpecGoodsPrice')->where('goods_id = ' . $goods_id)->getField('key,key_name,price,store_count,bar_code,sku,cost_price,commission');//规格项

        $str = "<table class='table table-bordered' id='spec_input_tab'>";
        $str .= "<tr>";
        $str_fill = "<tr>";
        // 显示第一行的数据
        foreach ($clo_name as $k => $v) {
            $str .= " <td><b>{$spec[$v]}</b></td>";
            $str_fill .= " <td><b></b></td>";
        }
        $str .= "<td><b>购买价</b></td>
               <td><b>成本价</b></td>
               <td><b>佣金</b></td>
               <td><b>库存</b></td>
               <td><b>SKU</b></td>
               <td><b>操作</b></td>
             </tr>";
        if (count($spec_arr2) > 0) {
            $str_fill .= '<td><input id="item_price" value="0" onkeyup="this.value=this.value.replace(/[^\d.]/g,&quot;&quot;)" onpaste="this.value=this.value.replace(/[^\d.]/g,&quot;&quot;)"></td>
               <td><input id="item_cost_price" value="0" onkeyup="this.value=this.value.replace(/[^\d.]/g,&quot;&quot;)" onpaste="this.value=this.value.replace(/[^\d.]/g,&quot;&quot;)"></td>
               <td><input id="item_commission" value="0" onkeyup="this.value=this.value.replace(/[^\d.]/g,&quot;&quot;)" onpaste="this.value=this.value.replace(/[^\d.]/g,&quot;&quot;)"></td>
               <td><input id="item_store_count" value="0" onkeyup="this.value=this.value.replace(/[^\d.]/g,&quot;&quot;)" onpaste="this.value=this.value.replace(/[^\d.]/g,&quot;&quot;)"></td>
               <td><input id="item_sku" value="" onkeyup="this.value=this.value.replace(/[^\d.]/g,&quot;&quot;)" onpaste="this.value=this.value.replace(/[^\d.]/g,&quot;&quot;)"></td>
               <td><button id="item_fill" type="button" class="btn btn-success">批量填充</button></td>
             </tr>';
            $str .= $str_fill;
        }
        // 显示第二行开始
        foreach ($spec_arr2 as $k => $v) {
            $str .= "<tr>";
            $item_key_name = array();
            foreach ($v as $k2 => $v2) {
                $str .= "<td>{$specItem[$v2][item]}</td>";
                $item_key_name[$v2] = $spec[$specItem[$v2]['spec_id']] . ':' . $specItem[$v2]['item'];
            }
            ksort($item_key_name);
            $item_key = implode('_', array_keys($item_key_name));
            $item_name = implode(' ', $item_key_name);

            $keySpecGoodsPrice[$item_key][price] ? false : $keySpecGoodsPrice[$item_key][price] = 0; // 价格默认为0
            $keySpecGoodsPrice[$item_key][store_count] ? false : $keySpecGoodsPrice[$item_key][store_count] = 0; //库存默认为0
            $keySpecGoodsPrice[$item_key][cost_price] ? false : $keySpecGoodsPrice[$item_key][cost_price] = 0; //成本价默认为0
            $keySpecGoodsPrice[$item_key][commission] ? false : $keySpecGoodsPrice[$item_key][commission] = 0; //佣金默认为0
            $str .= "<td><input name='item[$item_key][price]' value='{$keySpecGoodsPrice[$item_key][price]}' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' /></td>";
            $str .= "<td><input name='item[$item_key][cost_price]' value='{$keySpecGoodsPrice[$item_key][cost_price]}' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' /></td>";
            $str .= "<td><input name='item[$item_key][commission]' value='{$keySpecGoodsPrice[$item_key][commission]}' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' /></td>";
            $str .= "<td><input name='item[$item_key][store_count]' value='{$keySpecGoodsPrice[$item_key][store_count]}' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")'/></td>";
            $str .= "<td><input name='item[$item_key][sku]' value='{$keySpecGoodsPrice[$item_key][sku]}' /><input type='hidden' name='item[$item_key][key_name]' value='$item_name' /></td>";
            $str .= "<td><button type='button' class='btn btn-default delete_item'>无效</button></td>";
            $str .= "</tr>";
        }
        $str .= "</table>";
        return $str;
    }
	public function upload_video(){
		$data = input('post.');
		$group = $data['group']? $data['group']:'common';
		$size = $data['size']? $data['size']: 5;
		$file = $_FILES['file'];
		$upload_max = @ini_get('upload_max_filesize');//获取系统配置中允许上传的文件大小
		$upload_max_filesize = substr($upload_max,0,strlen($upload_max)-1);
		$post_max = @ini_get('post_max_size');
		$post_max_size = substr($post_max,0,strlen($post_max)-1);//获取系统配置中post文件大小
		$size = min($size,$upload_max_filesize,$post_max_size);
		$maxsize = $size*1024*1024;
		$ext =  strtolower(trim(substr(strrchr($file['name'], "."), 1)));//获取后缀
		$type_arr = ['mp4','3gp','flv','avi','wmv','mov','rmvb','mpeg','mkv','vob'];
		if(!in_array($ext,$type_arr)){
			$this->ajaxReturn(array('status'=>false,'data'=>$file,'msg'=>'视频格式错误'));
		}
		if($file['size'] > $maxsize){
			$this->ajaxReturn(array('status'=>false,'data'=>$file,'msg'=>'请上传小于'.$size.'M的文件'));
		}
		//追加一层时间目录
		$date = date('Ymd');
		// 移动到框架应用根目录/public/uploads/ 目录下
		$filePath = ROOT_PATH . 'public' . DS . 'uploads'. DS . $group . DS . $date .DS;
		if(!is_dir($filePath)){
			mkdir($filePath, 0777, true);
			chmod($filePath, 0777);
		}
		$name = time() . rand(100, 999) . '.' . $ext;
		$info = move_uploaded_file($file['tmp_name'], $filePath . $name);
		$video_url = $group . DS . $date . DS .$name;
		if($info){
			$this->ajaxReturn(array('data'=>$video_url,'msg'=>'上传成功'));
		}else{
		// 上传失败
			$this->ajaxReturn(array('status'=>false,'data'=>$info,'msg'=>'视频信息获取失败'));
		}
	}
}
