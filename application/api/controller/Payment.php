<?php
/**
 * Created by PhpStorm
 * User: Brian
 * Date: 2019/8/22
 * Time: 16:14
 */

namespace app\api\controller;
use think\Log;

class Payment extends Common
{
    public $payUrl = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
    private $appid = 'appid';
    private $appsecret = 'appsecret';
    private $mch_id = '1549630351';
    private $key = 'key'; //api密钥

    /**
     *获取预支付订单
     * @param $orderInfo array 数据信息 必传项order_sn,openid,total_fee(int) 可选项spbill_create_ip,attach,body,detail
     * @return string 预支付订单号
    */
    public function getPrepayId($orderInfo){
        $order_sn = $orderInfo['order_sn'] ?? '';
        $openid = $orderInfo['openid'] ?? '';
        $detail = $orderInfo['detail'] ?? '';
        $body = $orderInfo['body'] ?? 'oneStyle';
        $total_fee  = (int)$orderInfo['total_fee'] ?? 0;//分为单位
        $spbill_create_ip = $orderInfo['spbill_create_ip'] ?? '';
        $attach = $orderInfo['attach'] ?? ''; //微信回调会原样返回该值
        if(!$order_sn || !$openid || !$total_fee || !$body){
            return '';
        }

        $send_data['appid'] = $this->appid;
        $send_data['mch_id'] = $this->mch_id;
        $send_data['device_info'] = $this->appid;
        $send_data['nonce_str'] = get_rand_str(6);
        $send_data['sign_type'] = 'MD5';
        $send_data['body'] = $body;
        $send_data['out_trade_no'] = $order_sn;
        $send_data['fee_type'] = 'CNY';
        $send_data['total_fee'] = $total_fee;
        $send_data['notify_url'] = 'https://www.onestyle.vip/api/Payment/Notify';
        $send_data['trade_type'] = 'JSAPI';
        $send_data['openid'] = $openid;
        //值为空不能加入验签

        if($detail){
            $send_data['detail'] = $detail;
        }
        if($spbill_create_ip){
            $send_data['spbill_create_ip'] = $spbill_create_ip;
        }
        if($attach){
            $send_data['attach'] = $attach;
        }


        //签名
        $send_data['sign'] = $this->get_sign($send_data);
        $send_data = arrayToXml($send_data);
        $url = $this->payUrl;
        try {
            $xmlstr = httpRequest($url, true, "post", $send_data);
            $reObj = simplexml_load_string($xmlstr, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($reObj->return_code == 'SUCCESS') {
                $prepay_id = (string)$reObj->prepay_id;

                return $prepay_id;
            } else {
                Log::info('getPrepayId:' . $reObj->return_msg);
                return '';
            }
        }catch (HttpException $e){
            return '';
        }

    }

    protected function get_sign($send_data){
        ksort($send_data);
        $string = urldecode(http_build_query($send_data)).'&key='.$this->key;
        return strtoupper(md5($string));
    }

    public function Notify(){
        //支付回调地址
        $origin_xml = file_get_contents("php://input");
        $xml = simplexml_load_string($origin_xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if($xml ->return_code == 'SUCCESS'){
            //通信标识SUCCESS
            if($xml->result_code != 'SUCCESS'){
                //业务结果
                $this->exit_msg('FAIL','result_codes fail');
            }
            $data=[];
            foreach ($xml as $k => $v) {
                $data[(string) $k] = (string) $v;
            }
            $dataSign = $data['sign'];
            unset($data['sign']);
            $sign = $this->get_sign($data);
            if($sign == $dataSign){
                //签名验证通过
                $result_info = [];
                $result_info['total_fee'] = $data['total_fee'] ?? 0; //以分为单位
                $result_info['transaction_id'] = $data['transaction_id'] ?? ''; //微信支付订单号
                $result_info['out_trade_no'] = $data['out_trade_no'] ?? ''; //商户订单号 也就是系统内的order_sn
                $result_info['attach'] = $data['attach'] ?? ''; //商家数据包
                $result_info['time_end'] = $data['time_end'] ?? ''; //支付完成时间格式为20141030133525
                $result_info['openid'] = $data['openid'] ?? ''; //openid
                $status = action('order/pay_back',[
                    'param'=>$result_info
                ]);
                if($status){
                    //订单处理成功后记录支付日志
                    $payment_log['origin_xml'] = $origin_xml;
                    $payment_log['total_fee'] = $data['total_fee'];
                    $payment_log['transaction_id'] = $data['transaction_id'];
                    $payment_log['out_trade_no'] = $data['out_trade_no'];
                    $payment_log['time_end'] = $data['time_end'];
                    $payment_log['openid'] = $data['openid'];
                    $payment_log['is_subscribe'] = $data['is_subscribe'];//是否关注公众账号
                    $payment_log['trade_type'] = $data['trade_type'];
                    $payment_log['bank_type'] = $data['bank_type'];
                    $payment_log['cash_fee'] = $data['cash_fee'];
                    $payment_log['pay_name'] = 'wx';
                    $payment_log['reg_time'] = time();
                    db('payment_log')->insert($payment_log);
                    $this->exit_msg('SUCCESS','OK');
                }
            }else{
                $this->exit_msg('FAIL','签名验证失败');
            }
        }

        $this->exit_msg('FAIL','return_code fail');

    }

    /**
     * 通知微信
     * @param $return_code string
     * @param $return_msg string
    */
    public function exit_msg($return_code,$return_msg){
        $msg['return_code'] = $return_code;
        $msg['return_msg'] = $return_msg;
        echo arrayToXml($msg);
        exit;
    }




}
