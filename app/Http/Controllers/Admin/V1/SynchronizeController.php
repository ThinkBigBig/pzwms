<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\BaseController;
use App\Logics\RedisKey;
use Illuminate\Http\Request;
use App\Models\ProductSkuStock;
use App\Models\BondedStockNumber;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SynchronizeController extends BaseController
{
    /**
     * 验签方法
     *
     * @param Request $request
     * @return void
     */
    public function createSignature(Request $request)
    {
        $data = $request->all();
        if (empty($data['appKey']) || empty($data['appSecret']) || empty($data['time'])) {
            return $this->vdtError();
        }
        if (!empty($data['item'])) {
            $paramArr['item'] = $data['item'];
        }
        $paramArr['appKey'] = $request->appKey; //请求令牌;
        $paramArr['appSecret'] = $request->appSecret; //请求令牌;
        $paramArr['time'] = $request->time;
        // var_dump( $paramArr['time']);
        $sign = $this->createSign($paramArr);
        return $this->success($sign);
    }

    public function check(Request $request)
    {
        $status = $this->checkSignature($request->all());
        if ($status) {
            return $this->success([], __('base.Signature_success'));
        } else {
            return  $this->error(__('base.Signature_error'));
        }
    }

    public function product(Request $request)
    {
        App::setLocale('zn');
        set_time_limit(900);
        // Log::channel('daily2')->info('product', $request->all());

        $status = $this->checkSignature($request->all());
        $data = $request->all();
        if (empty($data['time']) || empty($data['sign'])) {
            return  $this->_vdtError();
        }
        if (!$status) {
            return  $this->_error(__('base.Signature_error'));
        }
        if (!empty($data['item'])) {
            $arr = json_decode($data['item'], true);
            if (!empty($arr['items'])) {
                $error_arr = [];
                // 数据推入redis，从redis中获取数据
                Redis::lpush(RedisKey::PRODUCT_QUEUE, $data['item']);
            }
        }
        if (!empty($error_arr)) {
            return $this->_error($error_arr);
        }
        return $this->_success();
    }

    public function productStock(Request $request)
    {

        // Log::channel('daily2')->info('product_stock', $request->all());

        set_time_limit(900);
        App::setLocale('zn');
        $status = $this->checkSignature($request->all());
        $data = $request->all();
        if (empty($data['time']) || empty($data['sign'])) {
            return  $this->_vdtError();
        }
        if (!$status) {
            return  $this->_error(__('base.Signature_error'));
        }

        if (!empty($data['item'])) {
            // log_arr([$data],'synchronize');
            $arr2 = json_decode($data['item'], true);
            if (!empty($arr2)) {

                $arr = $arr2['remark'];
                $arr = json_decode($arr, true);
                $error_arr = [];
                Redis::lpush(RedisKey::PRODUCT_STOCK_QUEUE, $data['item']);
            }
        }
        if (!empty($error_arr)) {
            return $this->_error($error_arr);
        }
        return $this->_success();
    }

    public function productStockDetailed(Request $request)
    {
        // Log::channel('daily2')->info('product_stock_detail', $request->all());

        set_time_limit(900);
        App::setLocale('zn');
        $status = $this->checkSignature($request->all());
        $data = $request->all();
        if (empty($data['time']) || empty($data['sign'])) {
            return  $this->_vdtError();
        }
        if (!$status) {
            return  $this->_error(__('base.Signature_error'));
        }

        if (!empty($data['item'])) {
            $arr2 = json_decode($data['item'], true);
            $arr = $arr2['remark'];
            // remark
            $arr = json_decode($arr, true);
            // var_dump($arr);
            if (!empty($arr)) {
                $stock_arr = [];
                $error_arr = [];
                Redis::lpush(RedisKey::PRODUCT_STOCK_DETAIL_QUEUE, $data['item']);
            }
        }
        if (!empty($error_arr)) {
            return $this->_error($error_arr);
        }
        return $this->_success();
    }

    /**
     * 慎独在库的数量与预约单数量匹配
     *
     * @return void
     */
    public function stock($stock_v)
    {
        $ProductSkuStock =  (new ProductSkuStock)->BaseAll([
            'batchCode' => $stock_v['batchCode'],
            'itemCode'  => $stock_v['itemCode'],
            'supplierCode'  => $stock_v['supplierCode']
        ]);
        if (count($ProductSkuStock) > 0) {
            $BondedStockNumber = (new BondedStockNumber)->BaseAll(['pms_product_stock_id' => $ProductSkuStock['id']]);
            if ($stock_v['changeNum'] < 0) {
                $changeNum = abs($stock_v['changeNum']);
                foreach ($BondedStockNumber as $v) {
                    $qty = $v['qty'] - $v['sold_qty'];
                    if ($qty > 0) {
                        if ($changeNum >= $qty && $changeNum > 0) {
                            (new BondedStockNumber)->where('id', '=', $v['id'])->increment($qty);
                            $changeNum = $changeNum - $qty;
                        } else {
                            $changeNum = 0;
                            (new BondedStockNumber)->where('id', '=', $v['id'])->increment($changeNum);
                        }
                    }
                }
            }
        }
    }


    /**
     * 验签方法
     *
     * @param Request $request
     * @return void
     */
    public function checkSignature($paramArr)
    {
        $param['appKey'] = env('SHENDU_APP_KEY', ''); //请求令牌;
        $param['appSecret'] = env('SHENDU_APP_SECRET', ''); //请求令牌;
        if (!empty($paramArr['item'])) {
            $param['item'] = $paramArr['item'];
        }
        if (empty($paramArr['time']) || empty($paramArr['sign'])) {
            return false;
        }
        $param['time'] = $paramArr['time']; //请求令牌;
        $signs = $paramArr['sign'];
        $sign = $this->createSign($param);
        // var_dump($sign,$signs);
        if ($sign != $signs) {
            return false;
            // return '签名错误';
        }
        return true;
        // return '签名正确';
    }

    // 请求成功时对数据进行格式处理
    private function _success($data = [], $msg = '')
    {
        return response()->json([
            'code' => self::SUCCESS,
            'message' => !empty($msg) ? $msg : __('base.success'),
            'time' => time(),
            'flag' => 'success',
            'data' => $data
        ]);
    }

    // 错误提示方法
    private function _error($msg = '', $data = [])
    {
        return response()->json([
            'code' => self::ERROR,
            'message' => !empty($msg) ? $msg : __('base.error'),
            'time' => time(),
            'flag' => 'failure',
            'data' => $data,
        ]);
    }

    // 响应校验失败时返回自定义的信息
    private function _vdtError($msg = '', $data = [])
    {
        return response()->json([
            'code' => self::VAlID,
            'message' => !empty($msg) ? $msg : __('base.vdt'),
            'time' => time(),
            'flag' => 'failure',
            'data' => $data,
        ]);
    }

    /**
     * 生成签名
     * @param $paramArr
     * @return string
     */
    private function createSign($paramArr)
    {
        ksort($paramArr);
        // var_dump($paramArr);
        $str = '';
        foreach ($paramArr as $key => $val) { //过滤空数据项
            // if(is_array($val)) {
            //     $paramArr[$key] = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            // }
            if ($key == 'item') {
                // var_dump($val);exit;
                $str .= $key . '=' . $val . '&';
            } else {
                $str .= $key . '=' . $val . '&';
            }
        }
        $str = trim($str, '&');
        // var_dump($str);exit;
        // var_dump($paramArr);
        // http_build_query($paramArr, '&')
        // $sign_str = http_build_query($paramArr, '&');
        // $sign_str = json_encode($paramArr, true);
        // var_dump($sign_str);exit;
        return strtoupper(md5($str));
    }
}
