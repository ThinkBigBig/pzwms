<?php

namespace App\Http\Controllers\Admin\V1\Warehousing;


use App\Http\Controllers\Admin\V1\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\WarehouseLocation;
use App\Models\Warehousing;
use App\Http\Library\Jwt;
use App\Handlers\HttpService;
use Illuminate\Support\Facades\Redis;

class MobileWarehouse extends BaseController
{

    protected $BaseCreateVat = [
        'identifier' => 'required|regex:/^[0-9a-zA-Z-]*$/',//库位编号
        'consignmentCode' => 'required',//寄售码
    ];
    //移动入库
    public function inboundCheck(Request $request){
        $system = $request->get('system',1);
    //     $token = $request->header('Authorization',null);
    //     if(empty($token)){
    //         return $this->permissionError();
    //     }
    //     if($system == 1){
    //         // Jwt::verifyToken($token,);
    //     }
        $validator = Validator::make($request->all(), $this->BaseCreateVat);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $identifier = trim($request->input('identifier'));

        //判断库位编号
        if(!WarehouseLocation::checkNumber($identifier)){
            return $this->vdtError('入库号'.$identifier.'不存在/错误,请检查');
        };

        //判断寄售码  需要调用java接口
        $consignmentCode = explode(',', $request->input('consignmentCode'));
        $url = '/carryme-product/product/light/consignment/code';
        $params['data'] = $consignmentCode;
        $params['header'] = $this->javaHeaders();
        $res = HttpService::request('post',env('CARRYME_HOST') . $url, $params);
        $res ? json_decode($res, true) : [];
        if(empty($res) || !empty($res['error']) || $res['code'] != 200){
            $this->error($res['msg']);
        }
        $valid_code = [];
        $code_data = [];
        $data =  $res['data']??[];
        foreach ($data as $key => $item) {
            $valid_code[] = $item['consignmentCode'];
            $code_data[] = [
                'img'=>$item['pic'],
                'name'=>$item['name'],
                'product_sn'=>$item['productSn'],
                'consignment_code'=> $item['consignmentCode'],
                'status'=>1,
                'locator_number' => $identifier,
                'submitter' => $request->header('user-id'),
                'system' => $system ,
            ];

        }
        $diff = array_values(array_diff($consignmentCode,$valid_code));
        if(!empty($diff)){
            return $this->vdtError("寄售码 ".implode('、',$diff)." 不存在/错误,请检查");
        }else{
            $able = WarehouseLocation::getUsableVolume($identifier);
            $is_able = true;
            if(count($consignmentCode) > $able)$is_able = false;
            $inboundId = md5(json_encode($code_data));
            $result=[
                'volume'=>$able,
                'is_able'=>$is_able,
                'identifier' => $identifier,
                'consignmentCode' => $consignmentCode,
                'inboundId' => $inboundId,
            ];
            //都有效商品信息写入redis
            Redis::setex($inboundId,300,json_encode($code_data));
            return $this->success($result);

        }

    }

    public function inbound(Request $request){
        $inboundId = $request->input('inboundId');
        if(empty($inboundId)){
            return $this->vdtError('参数错误');
        }
        $data = Redis::get($inboundId);
        if(empty($data)){
            return $this->error('入库失败,信息已过期');
        }
        $create_tata = json_decode($data,true);
        array_walk($create_tata, function(&$value){$value['created_at']=date('Y-m-d H:i:s');});
        $result = Warehousing::insert($create_tata);
        if($result){
            Redis::del($inboundId);
            return $this->success([],'入库成功');
        }else{
            return $this->error('入库失败,请联系管理员');
        }


    }

}