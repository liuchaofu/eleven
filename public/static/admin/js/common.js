/**
 * @date   2019-07-28T11:41:59+0800
 * @author dyp
 */

$(document).ready(function() {
	var btnBlock = true; //ajax提交堵塞 ture可提交, false堵塞中不可提交
	var ERROR_REPEAT_SUBMIT = '请勿重复提交',
		ERROR_AJAX_500 = '程序严重错误请求失败',
		ERROR_URL_IS_NULL = '请求URL不能为空',
		ERROR_IS_NOT_JSON = '返回结果非JSON格式',
		ERRRO_UPLOAD_IMG_MAX_NUM = 1,
		ERRRO_UPLOAD_IMG_MAX_MSG = '最多只能传' + ERRRO_UPLOAD_IMG_MAX_NUM + '张图片',
		ERROR_NOT_PARAM = '请上传参数';
	//防止重复提交
	function checkBtnBlock(msg) {
		msg = !msg ? ERROR_REPEAT_SUBMIT : msg;
		if (!btnBlock) {
			layer.msg(msg,{icon:2});
			return false;
		} else {
			btnBlock = false;
		}
		return true;
	};
	//关闭当前弹框
	function closeOpen(){
		var index = parent.layer.getFrameIndex(window.name); //获取窗口索引
		parent.layer.close(index);
	}
	//上传进度回调函数：  
	function progressHandlingFunction(e) {
		if (e.lengthComputable) {
			$('progress').attr({
				value: e.loaded,
				max: e.total
			}); //更新数据到进度条  
			var percent = parseInt(e.loaded / e.total * 100);
			$('.progress-bar').css('width', percent + '%');
			$('.progress-bar').text(percent + '%');
		}
	}
	//单一图片上传
	function dtUploadImg(event){
        //addKey(event);
		var baseImgUrl = '/public/uploads'; //图片保存路径
		//ajax数据提交处理地址;
		var url = '/admin/common/upload_img';
        var _this = event;
		//获取渲染图片信息
        var config = {};
        config.name = $(event).attr('dt-name');
        //config.maxNum = Math.max($(event).attr('dt-max'), 1);
		config.maxNum = 1;//限制单图
		config.size = $(event).attr('dt-size')? $(event).attr('dt-size'):2;
        config.group = $(event).attr('dt-group');//上传图片分组
        config.imgWidth = $(event).attr('dt-img-width');//图片展示宽度
        config.imgHeight = $(event).attr('dt-img-height');//图片展示高度
        config.content = '<div class="dt-img-list"><input type="file" style="display:none;" id="' + config.name + '" multiple="multiple" accept="image/*"><div class="img-list"><ul></ul></div></div>';
        config.value = $(event).attr('dt-value');
        if (config.value != '' && typeof(config.value) != 'undefined') {
            config.value = config.value.split(',');
        }
        config.imgWidth = config.imgWidth ? config.imgWidth : '100';
        config.imgHeight = config.imgHeight ? config.imgHeight : '100';

        //渲染上传组件
        $(_this).parent().find('.dt-img-list').remove();
        $(_this).parent().append(config.content);

        //渲染初始图片
        for (var i = 0; i < config.value.length; i++) {
            var imgContent = '<li style="float:left;width:100px;height:100px;margin-top:10px;list-style-type:none;"><img src="' + baseImgUrl + '/'+ config.value[i] + '" width="' + config.imgWidth + '" height="' + config.imgHeight + '" style="border:1px solid #ccc;"> <a style="top:-105px;left:90px;" class="btn-del-img ys-btn-close"><i class="glyphicon glyphicon-remove"></i></a></li>';
            $(_this).parent().find('.img-list ul').append(imgContent);
        }
        //上传
        $(_this).click(function() {
            $(_this).parent().find('input[id="' + config.name + '"]').trigger('click');
        })
        //转换图片url
        $(_this).parent().find('input[id="' + config.name + '"]').change(function(e) {
            var imgLength = $(_this).parent().find('.img-list ul img').length;
            var files = e.target.files || e.dataTransfer.files;
            if (config.maxNum && config.maxNum < files.length + imgLength) {
                ERRRO_UPLOAD_IMG_MAX_NUM = config.maxNum;
                return layer.msg(ERRRO_UPLOAD_IMG_MAX_MSG);
            }
			for (var i = 0; i < files.length; i++) {
				var formData = new FormData();
				formData.append('file', files[i]);
				formData.append('group', config.group);
				formData.append('size', config.size);
				$.ajax({
					url: url,
					type: "post",
					dataType: "json",
					data: formData,
					contentType: false, //必须false才会自动加上正确的Content-Type  
					processData: false,  //必须false才会避开jQuery对 formdata 的默认处理
					success: function(res) {
						if (res.status) {
							//console.log(res);return false;
							var url = baseImgUrl + '/' + res.data;
							var content = '<li style="float:left;width:100px;height:100px;margin-top:10px;list-style-type:none;"><img src="' + url + '" width="100" height="100" style="border:1px solid #ccc;"><a style="top:-105px;left:90px;" class="btn-del-img ys-btn-close"><i class="glyphicon glyphicon-remove"></i></a></li>';
							$(_this).parent().find('.img-list ul').append(content);
							bindValue(res.data);
						}
						return layer.msg(res.msg,{icon:1});
					}
				});
            }
        })
		bindValue(config.value);
        //删除照片
        $('body').on('click', '.btn-del-img', function() {
			$(this).parent().remove();
			var path = $(this).parent().find('img').attr('src');
			var url = "/admin/common/file_del";//图片删除地址
			$.ajax({
				url: url,
				type: "post",
				dataType: "json",
				data: {'path':path},
				success: function(res) {
					bindValue();
				}
			});
        })
		function bindValue(data=''){
			var content = '<input type="hidden" name="' + config.name + '" value="' + data + '" />';
            $(_this).parent().find('input[name="' + config.name + '"]').remove();
            $(_this).parent().append(content);
		}
	}
	//图片上传及渲染
    $('.btn-img').each(function() {
		//var progress = '<div class="progress" style="margin:0px; margin-top:5px;"><div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 1%;">1%</div></div>';
        //layer.alert(progress);return false;
        dtUploadImg(this);
    });
	//上传文件及渲染
    $('.btn-files').each(function() {
		var baseFileUrl = '/public/uploads'; //图片保存路径
		//ajax数据提交处理地址;
		var url = '/admin/common/up_file';
        var _this = $(this);
		//获取文件渲染信息
        var config = {};
        config.name = $(_this).attr('dt-name');
        config.maxNum = Math.max($(_this).attr('dt-max'), 1);
		config.size = $(_this).attr('dt-size')? $(_this).attr('dt-size'):2;
        config.group = $(_this).attr('dt-group');//上传图片分组
        config.content = '<div class="mail-attachment" style="border-top:0px;padding-left:0px;"><input type="file" style="display:none;" id="' + config.name + '" multiple="multiple"><div class="attachment" style="padding-left:0px;"></div></div>';
        config.progress = '<div class="progress" style="margin:0px; margin-top:5px;"><div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 1%;">1%</div></div>';
		config.value = $(_this).attr('dt-value');
		if (config.value != '' && typeof(config.value) != 'undefined') {
            config.value = config.value.split(',');
			var name = $(_this).attr('dt-material-name');
			if(name != '' && typeof(name) != 'undefined')
				name = name.split(',');
        }
		 //渲染上传组件
        $(_this).parent().find('.mail-attachment').remove();
        $(_this).parent().append(config.content);
		//渲染初始文件
		if(config.value != ''){
			for (var i = 0; i < config.value.length; i++) {
				var fileContent = '<div class="file-box" style="text-align:center;">'+
									'<div class="file" style="margin-bottom:0px;">'+
										'<span class="corner"></span>'+
										'<div class="icon"><i class="fa fa-file"></i></div>'+
										'<div class="file-name">'+name[i]+'</div>'+
									'</div>'+
									'<a href="="javascript:void(0);" class="btn-del-file">删除</a>'+
									'<input type="hidden" name="'+ config.name +'[]" value='+config.value[i]+'>'+
									'<input type="hidden" name="original_name[]" value='+name[i]+'>'+
								'</div>';
				$(_this).parent().find('.attachment').append(fileContent);
			}
		}
		//上传
        $(_this).click(function() {
            $(_this).parent().find('input[id="' + config.name + '"]').trigger('click');
        })
        //转换
        $(_this).parent().find('input[id="' + config.name + '"]').change(function(e) {
            var fileLength = $(_this).parent().find('.mail-attachment').find('.file-box').length;
            var files = e.target.files || e.dataTransfer.files;
            if (config.maxNum && config.maxNum < files.length + fileLength) {
                return layer.msg('只允许上传'+config.maxNum+'个文件');
            }
			for (var i = 0; i < files.length; i++) {
				var formData = new FormData();
				formData.append('file', files[i]);
				formData.append('group', config.group);
				formData.append('size', config.size);
				$.ajax({
					url: url,
					type: "post",
					dataType: "json",
					data: formData,
					contentType: false, //必须false才会自动加上正确的Content-Type  
					processData: false,  //必须false才会避开jQuery对 formdata 的默认处理
					success: function(res) {
						if (res.status) {
							//console.log(res);return false;
							var content = '<div class="file-box" style="text-align:center;">'+
											'<div class="file" style="margin-bottom:0px;">'+
												'<span class="corner"></span>'+
												'<div class="icon"><i class="fa fa-file"></i></div>'+
												'<div class="file-name">'+res.name+'</div>'+
											'</div>'+
											'<a href="javascript:void(0);" class="btn-del-file">删除</a>'+
											'<input type="hidden" name="'+ config.name +'[]" value='+res.data+'>'+
											'<input type="hidden" name="original_name[]" value='+ res.original_name +'>'+
										'</div>';
							$(_this).parent().find('.attachment').append(content);
						}else{
							return layer.msg(res.msg,{icon:2});
						}
					}
				});
            }
			return layer.msg('上传成功',{icon:1});
        })
        //删除文件
        $('body').on('click', '.btn-del-file', function() {
			$(this).parent().remove();
			var path = $(this).parent().find('input').val();
			var url = "/admin/common/file_del";//图片删除地址
			$.ajax({
				url: url,
				type: "post",
				dataType: "json",
				data: {'path':path},
				success: function(res) {
					$(this).parent().remove();
				}
			});
        })
	});
	//提交信息
	$('.btn-comply').click(function(){
		var form = $(this).parents('.form-horizontal');
		var url = form.attr('action');
		var trueUrl = $(this).attr('dt-true-url'); // 执行成功跳转地址
		var falseUrl = $(this).attr('dt-false-url'); // 执行失败跳转地址
		var newUrl = $(this).attr('dt-url'); // 新的提交地址
		var dtNotMsg = $(this).attr('dt-not-msg');//成功后不弹出提示
		var falseValue, trueValue, data, params = [];
		if (newUrl) {
			url = newUrl;
		}
		//序列化获取form表单值
		data = form.serializeArray();
		/*if (data.length < 1) {
			return layer.msg(ERROR_NOT_PARAM);
		}*/
		//处理通讯堵塞
		if (!checkBtnBlock()) {
			return false;
		}
		//ajax请求
		$.ajax({
			url: url,
			type: "post",
			dataType: "json",
			data: data,
			success: function(res) {
				//console.log(res);return false;
				if (res.status) {
					if(dtNotMsg !== "yes"){
						layer.msg(res.msg,{icon: 1});
					}
					setTimeout(function() {
						var index = parent.layer.getFrameIndex(window.name); //获取窗口索引
						if(index){
							closeOpen();
							parent.location.reload();
						}else{
							if(trueUrl){
								window.location.href = trueUrl;
							}else{
								parent.location.reload();
							}
						}
					}, 1000);
				} else {
					layer.msg(res.msg,{icon:2});
					btnBlock = true;
				}
			}	
		});
	})

	//打开弹框弹出
	$('.btn-open').click(function() {
		var href = $(this).attr('dt-url');
		var title = $(this).attr('dt-title');
		var width = $(this).attr('dt-width');
		var height = $(this).attr('dt-height');
		if (!title) {
			title = $(this).text();
		}
		if (!width) {
			width = '80%';
		}
		if (!height) {
			height = '80%';
		}
		if (!href) {
			layer.msg('请设置dt-url的值');
			return false;
		}
		//iframe层
		layer.open({
			type: 2,
			title: title,
			shadeClose: false,
			shade: 0.8,
			fixed: true,
			maxmin: true,
			area: [width, height],
			content: [href] //iframe的url

		});
	});

	//删除询问框
	$('.btn-del').on('click',function(){
		var url = $(this).attr('dt-url')? $(this).attr('dt-url'):'';
		var img = $(this).attr('dt-img') ? $(this).attr('dt-img'):'';
		if(img){
			img = $(this).parent().parent().find('img').attr('src');
		}
		if(url){
			var id = $(this).val();
			var data = {'id': id,'img':img};
		}else{
			url = '/admin/common/del';
			var table = $(this).attr('dt-table');
			if(!table){
				return layer.msg('请设置dt-table的值');
			}
			//删除的字段
			var field = $(this).attr('dt-field');
			if(!field){
				return layer.msg('请设置dt-field的值');
			}
			//字段对应值
			var value = $(this).attr('dt-value');
			if(!value){
				return layer.msg('请设置dt-value的值');
			}
			var data = {'table':table,'field':field,'value':value,'img':img};
		}
		layer.confirm('您确定要删除吗？', {
			btn: ['删除','取消'], //按钮
			shade: false //不显示遮罩
		}, function(){
			$.ajax({
				url: url,
				type: "post",
				dataType: "json",
				data: data,
				success: function(res) {
					//console.log(res);return false;
					if (res.status) {
						layer.msg(res.msg,{icon: 1});
						setTimeout("location.reload();", 1000);
					} else {
						layer.msg(res.msg,{icon: 2});
					}
				}
			});
		}, function(){
			return true;
		});
	});
	//批量操作(删除、修改等...)
	$('.btn-all').on('click',function(){
		var data = {};
		//获取所有选中id;
		var len = $("input[class='id']:checked").length;
		if (len < 1) {
			return layer.msg('请选择需要执行的内容');
		}
		var ids = '';
		//获取批量删除id
		$("input[class='id']:checked").each(function() {
			ids += $(this).val() + ",";
		});
		ids = ids.substring(0, ids.length - 1);//去除最后一个逗号
		data['ids'] = ids;
		var img = $(this).attr('dt-img') ? $(this).attr('dt-img'):'';
		if(img){
			var img_field = $(this).attr('dt-img-field')?$(this).attr('dt-img-field'):'';
			if(!img_field){
				return layer.msg('请设置dt-img-field的值');
			}
			data['img_field'] = img_field;
		}
		var url = $(this).attr('dt-url');//设置td-url,则请求该URL地址,不设置就表示删除
		if(!url){
			url = '/admin/common/dels';
			var table = $(this).attr('dt-table');
			if(!table){
				return layer.msg('请设置dt-table的值');
			}
			data['table'] = table;
			//删除的字段
			var field = $(this).attr('dt-field');
			if(!field){
				return layer.msg('请设置dt-field的值');
			}
			data['field'] = field;
		}
		var is_del = $(this).attr('dt-del')? $(this).attr('dt-del'):'';
		if(is_del){
			layer.confirm('您确定要删除所有选中对象吗？',{
				btn: ['删除','取消'], //按钮
				shade: false //不显示遮罩
			}, function(){
				$.ajax({
					url: url,
					type: "post",
					dataType: "json",
					data: data,
					success: function(res) {
						if (res.status) {
							layer.msg(res.msg,{icon: 1});
							setTimeout("location.reload();", 1000);
						} else {
							layer.msg(res.msg,{icon: 2});
						}
					}
				});
			}, function(){
				return true;
			});
		}else{
			$.ajax({
				url: url,
				type: "post",
				dataType: "json",
				data: data,
				success: function(res) {
					if (res.status) {
						layer.msg(res.msg,{icon: 1});
						location.reload();
					} else {
						layer.msg(res.msg,{icon: 2});
					}
				}
			});
		}
	});
	
	//关闭当前弹窗
	$('.btn-close').on('click', function() {
		closeOpen();
	});
	
	//下拉选项框渲染
    $('select').each(function() {
        var data = $(this).attr('dt-selected');
        if (data) {
            $(this).val(data);
        }
    });
	
    //单选radio渲染
    $("#radio").each(function() {
        var data = $(this).attr('dt-radio');//若添加dt-radio属性则渲染,反之则不渲染
		if(data){
			$(this).find('input[type=radio]').each(function() {
				if ($(this).attr('value') == data) {
					$(this).attr("checked", "checked");
				}
			})
		}
        return true;
    })
	//复选框渲染
	/*$(".checkbox").each(function(){
		var data = $(this).val();
        var checkedArray = $(this).attr('dt-checked');
        var checkedValue = '';
		if (typeof(checkedArray) != 'undefined' && checkedArray != '') {
            checkedValue = checkedArray.split(",");
        }

        if (checkedValue) {
            if (jQuery.inArray(data, checkedValue) >= 0) {
                $(this).attr("checked", "checked");
            }
        }
	})*/
	//渲染编辑器
	$('.ue-editor').each(function() {
		var id = $(this).attr('id');
		UE.getEditor(id,{ 
			initialFrameWidth: 820,   //初始化宽度
			initialFrameHeight: 400,   //初始化高度
		})
	});
	
	//渲染时间插件
	$('.date-time').each(function() {
		var time = $(this).val(); //int
		var min = $(this).attr('dt-min'); // string int
		var max = $(this).attr('dt-max'); // string int
		var format = $(this).attr('dt-format');
		var type = $(this).attr('dt-type'); //year month date time datetime
		var isNull = $(this).attr('dt-isnull'); //year month date time datetime
		if (!format) {
			format = 'yyyy-MM-dd';
		}
		if (!min) {
			min = '1900-1-1';
		}
		if (!max) {
			max = '2099-12-31';
		}
		if (!type) {
			type = 'date';
		}

		if (!time.indexOf('-')) {
			time = time * 1000;
			time = new Date(time)
		} else if (!time) {
			time = new Date();
		}

		if (!isNull) {
			laydate.render({
				elem: this, //指定元素
				value: time,
				format: format,
				type: type,
				min: min,
				max: max,
			});
		} else {
			laydate.render({
				elem: this, //指定元素
				format: format,
				type: type,
				min: min,
				max: max,
			});
		}
	});

	//列表页全选、非全选
	$("#check_all").click(function(){
		if (this.checked == true) {
			$("input[class='id']").each(function() {
				this.checked = true;
			});
		} else {
			$("input[class='id']").each(function() {
				this.checked = false;
			});
		}
	});
	//input框值发生改变Ajax
	$(document).on('focusout', '.ajax-input',function(){
		var val = $(this).val();
        if (val == '') return false;
        if (!$(this).attr('dt-url')) {
            layer.msg('请设置dt-url参数');
            return false;
        }
        $.post($(this).attr('dt-url'), {'value':val}, function(res) {
            layer.msg(res.msg);
        });

    });
	//layui修改表状态
	layui.use(['form'], function(){
        var form = layui.form,layer = layui.layer;
        //监听指定开关
        form.on('switch(switchStatus)', function(data){
			var checked = data.elem.checked;
			if(checked){
				var value = 1; 
			}else{
				var value = 0;
			}
			var table = $(this).attr('dt-table');
            if(!table){
				layer.msg('请设置dt-table的值');
			}
			//表主键
			var key = $(this).attr('dt-key');
			if(!key){
				layer.msg('请设置dt-key的值');
			}
			//需要修改的字段
			var field = $(this).attr('dt-field');
			if(!field){
				layer.msg('请设置dt-field的值');
			}
			$.post("/admin/common/changeField",{'table':table,'key':key,'field':field,'value':value},function(res){
				if(res.status){
					layer.msg('状态修改成功');
				}else{
					layer.msg('状态修改失败');
				}
			})
        });
    });
});