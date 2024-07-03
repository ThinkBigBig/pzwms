<?php

namespace App\Http\Controllers\PDA;

use App\Logics\wms\Auth;
use App\Logics\wms\Putaway;
use App\Models\Admin\V2\WmsPutawayList;
use App\Models\Admin\V2\WmsPutawayDetail;
use App\Models\Admin\V2\Inventory;
use Illuminate\Http\Request;

class PutawayController extends BaseController
{
    // 扫描上架-唯一码
    function scan(Request $request)
    {
        $input = $request->input();
        $this->validateParams($input, [
            'uniq_code' => 'required',
            'location_code' => 'required',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $params['skus'][] = $input;
        $logic = new Putaway();
        $res = $logic->scan($params);
        return $this->output($logic, $res);
    }

    // 扫描上架-普通产品
    function scanOrdinary(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'bar_code' => 'required',
            'location_code' => 'required',
            'put_unit' => 'required',
            'is_flaw' => 'required'
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new Putaway();
        $res = $logic->scanOrdinary($params);
        return $this->output($logic, $res);
    }

    //普通产品增加上架数量
    function addOrdinary(Request $request){
        $params = $request->input();
        $this->validateParams($params, [
            'detail_id' => 'required',
            'count' => 'required',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new Putaway();
        $res = $logic->addOrdinary($params);
        return $this->output($logic, $res);
    }

    //上架检查
    function check(Request $request)
    {
        $data = [];
        //待上架的数量
        $wait_put = Inventory::where('area_code', 'ZJZCQ001')->where('warehouse_code',ADMIN_INFO['current_warehouse'])->count();
        $data['wait_put'] = $wait_put;
        $data['scan_total'] = 0;
        $data['location_count'] = 0;
        $data['details']= [];
        //检查当前账号是否存在暂存的上架单
        $item = WmsPutawayList::where('status', 0)->where('type',1)->where('create_user_id', ADMIN_INFO['user_id'])->where('warehouse_code',ADMIN_INFO['current_warehouse'])->orderBy('created_at', 'desc')->first();
        if ($item) {
            $data['id'] = $item->id;
            $info= WmsPutawayDetail::info($item->putaway_code);
            //按照位置码分组
            $detail = $info['detail'];
            $data['scan_total'] = $info['scan_total'];
            $data['location_count'] = count($detail);
            foreach ($detail as $location_code=> $item) {
                $data['details'][] = [
                    'location_code'=>$location_code,
                    'detail' => $item
                ];
            }
        }
        return $this->success($data);
    }

    // 上架完成
    function submit(Request $request)
    {  
        $params = $request->input();
        $this->validateParams($params, [
            'ids' => 'required',
        ]);
        $params['warehouse_code'] = ADMIN_INFO['current_warehouse'];
        $logic = new Putaway();
        $res = $logic->submit($params);
        return $this->output($logic, $res);
    }
}
