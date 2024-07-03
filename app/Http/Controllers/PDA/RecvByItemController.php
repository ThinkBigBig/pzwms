<?php

namespace App\Http\Controllers\PDA;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\Admin\v2\Inbound\RecvOrderController;
use App\Http\Controllers\Admin\v2\Inbound\RecvDetailController;
use App\Models\Admin\V2\ArrivalRegist as ArrModel;
use App\Models\Admin\V2\RecvOrder as RecvOrderModel;
use Illuminate\Support\Facades\DB;

class RecvByItemController extends BaseController
{
    //搜索列表
    public function list(Request $request)
    {
        $number = $request->get('number');
        // $builder = DB::table('wms_arrival_regist')->where('tenant_id', ADMIN_INFO['tenant_id'])->where('warehouse_code', ADMIN_INFO['current_warehouse'])->whereIn('arr_status', [2, 3]);
        $builder = ArrModel::where('tenant_id', ADMIN_INFO['tenant_id'])->where('warehouse_code', ADMIN_INFO['current_warehouse'])->where('doc_status',1)->whereIn('arr_status', [2, 3]);
        if ($number) {
            $number = '%'.$number.'%';
            $builder = $builder->where(function ($query) use ($number) {
                $query->where('arr_code','like', $number)
                    ->orWhere('log_number','like', $number)
                    ->orWhere('third_doc_code','like', $number);
            });
        }
        $list = $builder->orderBy('created_at','desc')->get();
        $data['count'] = 0;
        $data['list'] = [];
        foreach($list as $li){
            $temp = [
                'id'=>$li->id,
                'arr_type'=>$li->arr_type,
                'arr_type_txt'=>$li->arr_type_txt,
                'arr_status_txt'=>$li->arr_status_txt,
                'arr_status'=>$li->arr_status,
                'log_number'=> $li->log_number,
                'third_doc_code'=>$li->third_doc_code,
                'arr_name'=>$li->arr_name,
                'arr_code'=>$li->arr_code,
                'arr_num'=>$li->arr_num,
                'recv_num'=>$li->recv_num,
            ];
            $data['list'][] = $temp;
            $data['count'] += 1;

        }
        // $data = $builder->select('id','third_doc_code','log_number','arr_name','arr_code','arr_num','recv_num','arr_status',DB::raw('IF(arr_status=2,"待收货","收货中") as arr_status_txt '),'tenant_id')->get();
        // $data = $builder->select('id', 'third_doc_code', 'log_number', 'arr_name', 'arr_code', 'arr_num', 'recv_num', 'arr_status', 'tenant_id')->get();
        return  $this->success($data);
    }


    //收货扫描前检查是否有暂存收货单
    public function startScan(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, ['id' => 'required','scan_type'=>'required']);
        $id = $params['id'];
        $scan_type = $params['scan_type'];
        $arr_model = new ArrModel();
        list($res, $msg) = $arr_model->recvPreCheck($id);
        if (empty($res)) return $this->error($msg);
        $data = $arr_model->getRecvOrder($id,$scan_type);
        if ($data === false) return $this->error('登记单' . $id . '下的收货单出错,请联系管理员!');
        if (!empty($data['recv_detail_list'])) {
            $data['recv_detail_list'] =  $this->formatProduct($data['recv_detail_list']);
        }
        return  $this->success($data);
    }


    // //扫描收货
    // public function scanningGoods(Request $request)
    // {
    //     $controller = new RecvOrderController($request);
    //     return $controller->BaseCreate($request);
    // }
    // //删除
    // public  function delByBar(Request $request){
    //     $controller = new RecvDetailController($request);
    //     return $controller->delByBar($request);
    // }

    // //减扫
    // public  function delByUniq(Request $request){
    //     $controller = new RecvDetailController($request);
    //     return $controller->delByUniq($request);
    // }

    // //确认收货
    // public function recvDone(Request $request){
    //     $controller = new RecvOrderController($request);
    //     return $controller->recvDone($request);
    // }
}
