<?php

namespace App\Http\Controllers\PDA;

use App\Logics\wms\Auth;
use App\Logics\wms\Putaway;
use App\Logics\wms\QualityControl;
use App\Models\Admin\V2\WmsPutawayList;
use App\Models\Admin\V2\WmsPutawayDetail;
use App\Models\Admin\V2\RecvOrder;
use App\Models\Admin\V2\WmsQualityList;
use App\Models\Admin\V2\WmsStockMoveList;
use App\Models\Admin\V2\WmsStockCheckList;
use App\Models\Admin\V2\Version;
use App\Models\Admin\V2\Inventory;
use App\Logics\wms\Warehouse;
use App\Models\Admin\V2\WmsAllocationTask;
use App\Models\AdminUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeController extends BaseController
{

    protected $warehouse_code;
    protected $tenant_id;
    protected $user_id;
    protected $start_time;
    protected $end_time;

    // 个人信息
    function info(Request $request)
    {
        $user = AdminUsers::find(ADMIN_INFO['user_id']);
        if (!$user) return $this->error('');
        $data = [
            'id' => $user->id,
            'username' => $user->username,
            'mobile' => $user->mobile ?? '',
            'email' => $user->email ?? '',
            'remark' => $user->remark ?? '',
        ];
        return $this->success($data);
    }

    //获取版本信息
    function version(Request $request){
        $data = [];
        $version =  Version::where('status',1)->orderBy('created_at','desc')->first();
        if($version)$data[]=$version;
        return $this->success($data);
    }


    public function init($request)
    {
        $input = $request->input();
        $this->validateParams($input, [
            'start_time' => 'required|date_format:"Y-m-d H:i:s"',
            'end_time' => 'required|date_format:"Y-m-d H:i:s"|after:start_time',
        ]);
        $this->warehouse_code = ADMIN_INFO['current_warehouse'];
        $this->tenant_id = ADMIN_INFO['tenant_id'];
        $this->user_id = ADMIN_INFO['user_id'];
        $this->start_time = $input['start_time'];
        $this->end_time = $input['end_time'];
    }

    //我的任务
    function task(Request $request)
    {
        $this->init($request);

        //收货
        $recv_count = DB::table('wms_recv_order')->where('created_user', $this->user_id)->where('tenant_id', $this->tenant_id)->where('warehouse_code', $this->warehouse_code)
            ->where('doc_status', 2)->whereBetween('done_at', [$this->start_time, $this->end_time])->sum('recv_num');
        //质检
        $qc_count = DB::table('wms_quality_list')->where('submit_user_id', $this->user_id)->where('tenant_id', $this->tenant_id)->where('warehouse_code', $this->warehouse_code)
            ->where('qc_status', 1)->whereBetween('completed_at', [$this->start_time, $this->end_time])->sum('total_num');

        //配货任务单
        // $allocate_count = DB::table('wms_pre_allocation_detail')->where('receiver_id', $this->user_id)
        //     ->where('tenant_id', $this->tenant_id)->where('warehouse_code', $this->warehouse_code)
        //     // ->where('cancel_status',0)
        //     ->whereBetween('allocated_at', [$this->start_time, $this->end_time])->sum('actual_num');


        $allocate_count =   WmsAllocationTask::where('receiver_id', $this->user_id)
        ->where('tenant_id', $this->tenant_id)->where('warehouse_code', $this->warehouse_code)
        ->where('status',2)
        ->whereBetween('created_at', [$this->start_time, $this->end_time])->sum('total_num');

        //盘点

        $stock_check_codes = DB::table('wms_stock_check_list')->where('check_user_id', $this->user_id)
        ->where('tenant_id', $this->tenant_id)->where('warehouse_code', $this->warehouse_code)
        ->where('status', 2)->whereBetween('start_at', [$this->start_time, $this->end_time])
        ->get()->pluck('code')->toArray();

        $stock_check_count  =  DB::table('wms_stock_check_details')->whereIn('origin_code', $stock_check_codes)
        ->where('tenant_id', $this->tenant_id)->sum('check_num');

        //入库上架
        $ib_putaway = DB::table('wms_putaway_list')->where('submitter_id', $this->user_id)->where('tenant_id', $this->tenant_id)->where('warehouse_code', $this->warehouse_code)
        ->where('putaway_status', 1)->where('type',1)->whereBetween('completed_at', [$this->start_time, $this->end_time])->sum('total_num');

        //取消单上架
        $cancel_putaway = DB::table('wms_putaway_list')->where('submitter_id', $this->user_id)->where('tenant_id', $this->tenant_id)->where('warehouse_code', $this->warehouse_code)
            ->where('putaway_status',1)->where('type',3)->whereBetween('completed_at', [$this->start_time, $this->end_time])->sum('total_num');

        // //移位上架
        $move_up_count = DB::table('wms_stock_move_list')->where('shelf_user_id', $this->user_id)->where('tenant_id', $this->tenant_id)->where('warehouse_code', $this->warehouse_code)
            ->where('status', 2)->where('shelf_status',2)
            ->whereBetween('created_at', [$this->start_time, $this->end_time])->sum('shelf_num');

        // //移位下架
        $move_down_count = DB::table('wms_stock_move_list')->where('down_user_id', $this->user_id)->where('tenant_id', $this->tenant_id)->where('warehouse_code', $this->warehouse_code)
            ->where('status', 2)->where('down_status',2)
            ->whereBetween('created_at', [$this->start_time, $this->end_time])->sum('down_num');
            
        $data = [
            'recv_count' => (int)$recv_count,
            'qc_count' =>(int) $qc_count,
            'allocate_count' => (int)$allocate_count,
            'stock_check_count' => (int)$stock_check_count,
            'ib_putaway' =>(int) $ib_putaway,
            'cancel_putaway' =>(int) $cancel_putaway,
            'move_up_count' => (int)$move_up_count,
            'move_down_count' => (int)$move_down_count,
        ];

        return $this->success($data);
    }

    // 收货- 列表
    function recv(Request $request)
    {
        $this->init($request);
        $list = RecvOrder::where('created_user', $this->user_id)->where('warehouse_code', $this->warehouse_code)
            ->where('doc_status', 2)->whereBetween('done_at', [$this->start_time, $this->end_time])->orderBy('done_at','desc')->get();
        $data = [];
        $data['order_total'] = $list->count();
        $data['recv_total'] = 0;
        $data['un_qc_order_total'] = 0;
        $data['un_qc_total'] = 0;
        $data['list'] = [];
        $data['un_qc_list'] = [];
        foreach ($list as $li) {
            $temp = [
                'id' => $li->id,
                'recv_code' => $li->recv_code,
                'recv_type_txt' => $li->recv_type_txt,
                'recv_num' => $li->recv_num,
                'done_at' => $li->done_at,
                'detail' => [
                    'sku_count'=>0,
                    'total'=>0,
                    'list'=>[],
                ],
            ];
            $qc_count = 0;
            if ($li->details) {
                $details = $li->details()->selectRaw('bar_code,quality_type,quality_level,count(*) as recv_num ,sum(is_qc) as qc_count')->groupByRaw('bar_code,quality_type,quality_level')->get();
                foreach ($details as $detail) {
                    $d_temp = [
                        'product_sn' => $detail->product->product->product_sn ?? '',
                        'product_spec' => $detail->product->spec_one ?? '',
                        'recv_num' => $detail->recv_num,
                        'quality_level' => $detail->quality_level,
                        'quality_type_txt' => $detail->quality_type,
                    ];
                    $temp['detail']['list'][] = $d_temp;
                    $temp['detail']['total'] += $d_temp['recv_num'];
                    $temp['detail']['sku_count'] += 1;
                    $qc_count += $detail->qc_count;
                }
            }

            $data['recv_total'] += $li->recv_num;
            $data['list'][] = $temp;

            //整单未质检
            if($qc_count == 0){
                $data['un_qc_order_total']+=1;
                $data['un_qc_total']+=$li->recv_num;
                $data['un_qc_list'][] = $temp;
            }
       
        }

        return $this->success($data);
    }

    // 质检
    function qc(Request $request){
        $this->init($request);
        $list = WmsQualityList::where('submit_user_id', $this->user_id)->where('tenant_id', $this->tenant_id)->where('warehouse_code', $this->warehouse_code)
        ->where('qc_status', 1)->whereBetween('completed_at', [$this->start_time, $this->end_time])->orderBy('completed_at','desc')->get();
        $data['qc_total'] = 0;
        $data['list'] = [];
        foreach ($list as $li) {
            $temp = [
                'id' => $li->id,
                'qc_code' => $li->qc_code,
                'qc_num' => $li->total_num,
                'completed_at' => $li->completed_at,
                'detail' => [
                    'sku_count'=>0,
                    'total'=>0,
                    'list'=>[],
                ],
            ];
            if ($li->details) {
                $details = $li->details()->selectRaw('bar_code,quality_type,quality_level,count(*) as qc_num ')->groupByRaw('bar_code,quality_type,quality_level')->get();
                foreach ($details as $detail) {
                    $d_temp = [
                        'product_sn' => $detail->specBar->product->product_sn ?? '',
                        'product_spec' => $detail->specBar->spec_one ?? '',
                        'qc_num' => $detail->qc_num,
                        'quality_level' => $detail->quality_level,
                        'quality_type_txt' => $detail->quality_type,
                    ];
                    $temp['detail']['list'][] = $d_temp;
                    $temp['detail']['total'] += $d_temp['qc_num'];
                    $temp['detail']['sku_count'] += 1;
                }
            }

            $data['qc_total'] += $li->total_num;
            $data['list'][] = $temp;
       
        }

        return $this->success($data);
    }


    private function _putaway($type=1){

        $list = WmsPutawayList::where('submitter_id', $this->user_id)->where('warehouse_code', $this->warehouse_code)
        ->where('putaway_status', 1)->where('type',$type)->whereBetween('completed_at', [$this->start_time, $this->end_time])->orderBy('completed_at', 'desc')->get();
        $data['putaway_total'] = 0;
        $data['list'] = [];

        foreach($list as $li){
            $temp = [
                'id' => $li->id,
                'putaway_code' => $li->putaway_code,
                'total_num' => $li->total_num,
                'completed_at' => $li->completed_at,
                'detail' => [
                    'location_count'=>0,
                    'total'=>0,
                    'list'=>[],
                ],
            ];
            $info= WmsPutawayDetail::info($li->putaway_code);
            //按照位置码分组
            $details = $info['detail'];
            $temp['detail']['location_count'] = count($details);
            $temp['detail']['total']=$info['scan_total'];
            foreach ($details as $location_code=> $detail) {
                $temp['detail']['list'][] = [
                    'location_code'=>$location_code,
                    'detail' => $detail
                ];
            }
            $data['putaway_total'] += $li->total_num;
            $data['list'][] = $temp;
        }
        return $data;
    }

    //入库上架单
    function putaway(Request $request){
        $this->init($request);
        $data = $this->_putaway(1);
        return $this->success($data);
    }

    //取消单上架
    function cancelPutaway(Request $request){
        $this->init($request);
        $data = $this->_putaway(3);
        return $this->success($data);
    }

    //配货
    function   allocate(Request $request)
    {
        $this->init($request);
        $list =  WmsAllocationTask::where('receiver_id', $this->user_id)
        ->where('tenant_id', $this->tenant_id)->where('warehouse_code', $this->warehouse_code)
        ->where('status',2)
        ->whereBetween('created_at', [$this->start_time, $this->end_time])
        ->orderBy('confirm_at', 'desc')
        ->get();


        $data['order_total'] = 0;
        $data['pre_total'] = 0; //应配数
        $data['actual_total'] = 0; //实配数
        $data['list'] = [];
        foreach ($list as $li) {
            $temp = [
                'id' => $li->id,
                'code' => $li->code,
                'pre_num' => $li->total_num,
                'actual_num' => 0,
                'confirm_at' => $li->confirm_at,
                'detail' => [
                    'location_count'=>0,
                    'list'=>[],
                ],
            ];
            $location = [];
            if ($li->activeDetail) {
                $details = $li->activeDetail()->selectRaw('bar_code,quality_type,quality_level,if(quality_level!= "A",uniq_code,batch_no) batch_no,location_code,sum(pre_num) as pre_num,sum(actual_num) as actual_num ')->groupByRaw('bar_code,quality_type,quality_level,batch_no,location_code')->get();
                foreach ($details as $detail) {
     
                    $d_temp = [
                        'product_sn' => $detail->product->product->product_sn ?? '',
                        'product_spec' => $detail->product->spec_one ?? '',
                        'pre_num' => $detail->pre_num,
                        'actual_num' => $detail->actual_num,
                        'batch_no' => $detail->batch_no,
                        'quality_level' => $detail->quality_level,
                        'quality_type_txt' => $detail->quality_type,
                    ];

                    if(isset($location[$detail->location_code])){
                        $location[$detail->location_code][] = $d_temp;
                    }else{
                        $location[$detail->location_code] = [$d_temp];
                    }
                   
                    $temp['actual_num'] += $d_temp['actual_num'];
                }
            }

            foreach($location as $code=>$item){
                $temp['detail']['location_count'] +=1;
                $temp['detail']['list'][]=
                [
                    'location_code'=>$code,
                    'detail'=>$item,
                ];
            }


            $data['order_total'] += 1;
            $data['pre_total'] += $temp['pre_num'];
            $data['actual_total'] += $temp['actual_num'];
            $data['list'][] = $temp;

        }


        return $this->success($data);

    }

    //盘点单
    function  stockCheck(Request $request){
        $this->init($request);
        $list =  WmsStockCheckList::where('check_user_id', $this->user_id)
        ->where('tenant_id', $this->tenant_id)->where('warehouse_code', $this->warehouse_code)
        ->where('status',2)
        ->whereBetween('start_at', [$this->start_time, $this->end_time])
        ->orderBy('completed_at', 'desc')
        ->get();


        // $data['order_total'] = 0;
        $data['pre_num'] = 0; //应盘
        $data['actual_num'] = 0; //总盘点
        $data['list'] = [];
        foreach ($list as $li) {
            $temp = [
                'id' => $li->id,
                'code' => $li->code,
                'actual_num' => 0,
                'pre_num' => 0,
                'completed_at' => $li->end_at,
                'detail' => [
                    'pre_num' => 0,//架上
                    'actual_num' => 0, //实盘
                    'diff'=>0,//总差异
                    'location_count'=>0,
                    'list'=>[],
                ],
            ];
            $location = [];
            if ($li->details) {
                $details = $li->details()->selectRaw('bar_code,quality_type,quality_level, location_code,sum(stock_num) as pre_num,sum(check_num) as actual_num ')->groupByRaw('bar_code,quality_type,quality_level, location_code')->get();
                foreach ($details as $detail) {
                    $d_temp = [
                        'product_sn' => $detail->specBar->product->product_sn ?? '',
                        'product_spec' => $detail->specBar->spec_one ?? '',
                        'pre_num' => $detail->pre_num,//架上
                        'actual_num' => $detail->actual_num, //实盘
                        'quality_level' => $detail->quality_level,
                        'quality_type_txt' => $detail->quality_type,
                    ];
                    if(isset($location[$detail->location_code])){
                        $location[$detail->location_code]['data'][] = $d_temp;
                        $location[$detail->location_code]['pre_num'] += $d_temp['pre_num'];
                        $location[$detail->location_code]['actual_num'] += $d_temp['actual_num'];
                    }else{
                        $location[$detail->location_code]['data'] = [$d_temp];
                        $location[$detail->location_code]['pre_num'] = $d_temp['pre_num'];
                        $location[$detail->location_code]['actual_num'] = $d_temp['actual_num'];
                    }
                    $temp['actual_num'] += $d_temp['actual_num'];
                    $temp['pre_num'] += $d_temp['pre_num'];
                    $temp['detail']['pre_num'] += $d_temp['pre_num'];
                    $temp['detail']['actual_num'] += $d_temp['actual_num'];
                    $temp['detail']['diff'] = $temp['detail']['pre_num'] -  $temp['detail']['actual_num'];

                }
            }

            foreach($location as $code=>$item){

                $temp['detail']['location_count'] +=1;
                $temp['detail']['list'][]=
                [
                    'location_code'=>$code,
                    'pre_num'=>$item['pre_num'], //架上
                    'actual_num'=>$item['actual_num'],//实盘
                    'detail'=>$item['data'],
                ];
            }


            $data['actual_num'] += $temp['actual_num'];
            $data['pre_num'] += $temp['pre_num'];
            $data['list'][] = $temp;

        }

        return $this->success($data);
    }

    //移位上架
    function  moveUp(Request $request){
        $this->init($request);
        $list = WmsStockMoveList::where('shelf_user_id', $this->user_id)
        ->where('tenant_id', $this->tenant_id)->where('warehouse_code', $this->warehouse_code)
        ->where('status',2)->where('shelf_status',2)
        ->whereBetween('created_at', [$this->start_time, $this->end_time])
        ->orderBy('created_at', 'desc')
        ->get();
        $data['total'] = 0;
        $data['list'] = [];
        foreach ($list as $li) {
            $temp = [
                'id' => $li->id,
                'code' => $li->code,
                'type_txt' => $li->type_txt,
                'num' => $li->num,
                'created_at' => $li->end_at,
                'detail' => [
                    'location_count'=>0,
                    'total'=>0,
                    'list'=>[],
                ],
            ];
            if ($li->details) {
                $details = $li->details()->selectRaw('bar_code,quality_type,quality_level,location_code,sum(shelf_num) as shelf_num ')->groupByRaw('bar_code,quality_type,quality_level,location_code')->get();
                foreach ($details as $detail) {
     
                    $d_temp = [
                        'product_sn' => $detail->specBar->product->product_sn ?? '',
                        'product_spec' => $detail->specBar->spec_one ?? '',
                        'shelf_num' => $detail->shelf_num,
                        'location_code'=>$detail->location_code,
                        'quality_level' => $detail->quality_level,
                        'quality_type_txt' => $detail->quality_type,
                    ];

                    if(isset($location[$detail->location_code])){
                        $location[$detail->location_code][] = $d_temp;
                    }else{
                        $location[$detail->location_code] = [$d_temp];
                    }

                    $temp['detail']['total'] += $d_temp['shelf_num'];
                }
            }

            foreach($location as $code=>$item){
                $temp['detail']['location_count'] +=1;
                $temp['detail']['list'][]=
                [
                    'location_code'=>$code,
                    'detail'=>$item,
                ];
            }
            $data['total'] += $temp['num'];
            $data['list'][] = $temp;
        }

        return $this->success($data);


    }


    //移位下架
    function  moveDown(Request $request){
        $this->init($request);
        $list = WmsStockMoveList::where('down_user_id', $this->user_id)
        ->where('tenant_id', $this->tenant_id)->where('warehouse_code', $this->warehouse_code)
        ->where('status',2)->where('down_status',2)
        ->whereBetween('created_at', [$this->start_time, $this->end_time])
        ->orderBy('created_at', 'desc')
        ->get();
        $data['total'] = 0;
        $data['list'] = [];
        foreach ($list as $li) {
            $temp = [
                'id' => $li->id,
                'code' => $li->code,
                'type_txt' => $li->type_txt,
                'num' => $li->num,
                'created_at' => $li->created_at->format('Y-m-d H:i:s'),
                'detail' => [
                    'location_count'=>0,
                    'total'=>0,
                    'list'=>[],
                ],
            ];
            if ($li->details) {
                $details = $li->details()->selectRaw('bar_code,quality_type,quality_level,location_code,sum(down_num) as down_num ')->groupByRaw('bar_code,quality_type,quality_level,location_code')->get();
                foreach ($details as $detail) {
                    $d_temp = [
                        'product_sn' => $detail->specBar->product->product_sn ?? '',
                        'product_spec' => $detail->specBar->spec_one ?? '',
                        'down_num' => $detail->down_num,
                        'location_code'=>$detail->location_code,
                        'quality_level' => $detail->quality_level,
                        'quality_type_txt' => $detail->quality_type,
                    ];

                    if(isset($location[$detail->location_code])){
                        $location[$detail->location_code][] = $d_temp;
                    }else{
                        $location[$detail->location_code] = [$d_temp];
                    }

                    $temp['detail']['total'] += $d_temp['down_num'];
                }
            }

            foreach($location as $code=>$item){
                $temp['detail']['location_count'] +=1;
                $temp['detail']['list'][]=
                [
                    'location_code'=>$code,
                    'detail'=>$item,
                ];
            }
            $data['total'] += $temp['num'];
            $data['list'][] = $temp;
        }

        return $this->success($data);
    }

}
