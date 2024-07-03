<?php

namespace App\Http\Controllers\Admin\V2\Outbound;

use App\Http\Controllers\Admin\V2\BaseController;
use App\Logics\wms\Warehouse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class ReviewOneController extends BaseController
{

    protected $BaseModels = 'App\Models\Admin\V2\AllocationTaskDetail';

    public function review(Request $request)
    {
        $vat = [
            'warehouse_code' => 'required',
            'uniq_code' => 'required',
        ];
        $is_print = $request->get('is_print');
        $data = $this->vatReturn($request,$vat);
        // $res = $this->modelReturn('reviewOne',[$data['warehouse_code'],$data['uniq_code'],$is_print]);
        return  $this->modelReturn('reviewOne',[$data['warehouse_code'],$data['uniq_code'],$is_print],'playError');

    }

    public function sendGoods(Request $request){
        $vat = [
            'request_code' => 'required',
        ];
        $data = $this->vatReturn($request, $vat);
        return  $this->modelReturn('sendGoods', [$data['request_code']]);
    }

    public function reviewReset(Request $request){
        $vat = [
            'request_code' => 'required',
        ];
        $data = $this->vatReturn($request, $vat);
        return  $this->modelReturn('reviewReset', [$data['request_code']]);
    }
}
