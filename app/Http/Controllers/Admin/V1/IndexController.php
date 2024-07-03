<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\BaseController;
use App\Logics\FreeTaxLogic;
use Illuminate\Http\Request;
use App\Transformers\AuthorizationTransformer;
use Illuminate\Support\Facades\Hash;
use Dingo\Api\Exception\StoreResourceFailedException;
use App\Handlers\DwApi;
use App\Logics\RedisKey;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class IndexController extends BaseController
{
    protected $BaseModels = 'App\Models\Slide';
    protected $BaseAllVat = []; //获取全部验证
    protected $BAWhere  = []; //获取全部Where条件
    protected $BA  = ['*']; //获取全部选取字段 *是全部
    protected $BAOrder  = [['id', 'desc']]; //获取全部字段排序

    protected $BaseLVat = []; //获取分页全部验证
    protected $BLWhere  = []; //获取全部分页Where条件
    protected $BL  = ['*']; //获取全部分页选取字段 *是全部
    protected $BLOrder  = [['sort', 'desc']]; //获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',]; //单个处理验证
    protected $BOWhere = ['id' => ['=', '']]; //单个查询验证
    protected $BO = ['*']; //单个选取字段；*是全部
    protected $BOOrder = [['id', 'desc']]; //单个选取字段排序

    protected $BaseCreateVat = [
        'title' => 'required', //标题
        'content' => 'required', //描述
        'imagepath' => 'required', //图片地址
        'line_url' => 'required', //链接地址
        // 'listorder' => 'required',//排序
        'status' => 'required', //是否禁用（1：正常； 2：禁用）',
        'type' => 'required', //图片类型:1=首页轮播,2=会员轮播',
    ]; //新增验证
    protected $BaseCreate = [
        'title' => '', 'content' => '', 'imagepath' => '',
        'line_url' => '', 'listorder' => '', 'status' => '',
        'type' => '',
        'createtime' => ['type', 'time'],
    ]; //新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
    ]; //新增验证
    protected $BaseUpdate = [
        'id' => '', 'title' => '', 'content' => '',
        'imagepath' => '', 'line_url' => '', 'listorder' => '',
        'status' => '', 'type' => '',
        'updatetime' => ['type', 'time'],
    ]; //新增数据
    protected $BUWhere = ['id' => ['=', '']]; //新增数据

    //得物api测试
    public function dwApi(Request $request)
    {
        // $method = '30,3,apiUrl';
        // $request = [];
        // $data =  (new DwApi($request))->uniformRequest($method,$request);
        // return $data;
        $article_numbers = $request->article_numbers;
        $method = '2,2,apiUrl';
        // var_dump($request->all());exit;
        // $requestArr['sku_id'] = $request->sku_id;
        // var_dump($article_numbers);exit;
        $requestArr['article_numbers'] = [$article_numbers];
        // $requestArr['inventory_list'] = [
        //     'wh_inv_no' => $request->wh_inv_no,
        //     'qty' => $request->qty,
        // ];
        // var_dump($method);
        // var_dump($requestArr);exit;
        $data =  (new DwApi($requestArr))->uniformRequest($method, $requestArr);
        // var_dump($data);exit;
        $arr  = json_decode($data, true);
        return  $arr;
        // var_dump($arr);
    }

    //得物api测试
    public function dwApiMethod(Request $request)
    {
        $method = '';
        $request = [];
        // $data =  DwApi::dwApiMethod($method,$request);
        // return $data;
    }


    public function callback(Request $request)
    {
        $data = [
            'title' => 'dw callback',
            'url' => $request->fullUrl(),
            'data' => $request->all(),
            'ips' => $request->ips(),
            'user-agent' => $request->userAgent(),
        ];
        Log::channel('daily')->info(json_encode($data, true));

        $state = $request->get('state');
        if ($state == 'the1sneakercarryme4567') {
            $code = $request->get('code');
            Redis::set(RedisKey::STOCKX_AUTHORIZATION_CODE, $code);
            return $code;
        }
        return '';
    }

    public function redisToken(Request $request): string
    {
        $key = 'DW_TOKEN';
        if ($request->get('refresh', 0)) {
            Redis::del($key);
            return '已删除';
        }
        $token = Redis::get($key);
        return $token ?? '暂无数据';
    }
}
