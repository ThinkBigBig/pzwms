<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\BaseController;
use Illuminate\Http\Request;
use App\Transformers\AuthorizationTransformer;
use Illuminate\Support\Facades\Hash;
use Dingo\Api\Exception\StoreResourceFailedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Handlers\DwApi;
use App\Models\BondedStock;
use Illuminate\Support\Facades\Auth;
use App\Imports\ShipmentFlawImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Export;


class ShipmentFlawController extends BaseController
{
    protected $BaseModels = 'App\Models\ShipmentFlaw';
    protected $BaseAllVat = ['invoice_no' => 'required',];//获取全部验证
    protected $BAWhere  = ['invoice_no' =>['=','']];//获取全部Where条件
    protected $BA  = ['*'];//获取全部选取字段 *是全部
    protected $BAOrder  = [['id','desc']];//获取全部字段排序

    protected $BaseLVat = [];//获取分页全部验证
    protected $BLWhere  = [
        'invoice_no' =>['like',''],
        'stock_no' =>['like',''],
        'product_sn' =>['like',''],
        'good_name' =>['like',''],
    ];//获取全部分页Where条件
    protected $BL  = ['*'];//获取全部分页选取字段 *是全部
    protected $BLOrder  = [['id','desc']];//获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',];//单个处理验证
    protected $BOWhere = ['id'=>['=','']];//单个查询验证
    protected $BO = ['*'];//单个选取字段；*是全部
    protected $BOOrder = [['id','desc']];//单个选取字段排序

    protected $BaseCreateVat = [
    ];//新增验证
    protected $BaseCreate =[
        'createtime' => ['type','time'],
    ];//新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
    ];//新增验证
    protected $BaseUpdate =[
        'updatetime' => ['type','time'],
    ];//新增数据
    protected $BUWhere= ['id'=>['=','']];//新增数据
    protected $exportField = [
        'invoice_no' => '出货单号',
        'objective' => '目的仓',
        'grouping' => '入仓分组',
        'stock_no' => '备货单号',
        'good_name' => '商品名称',
        'product_sn' => '货号',
        'properties' => '规格',
        'bar_code' => '条码',
        'unique_code' => '唯一码',
        'failed_reason' => '质检未通过原因',
        'failed_reason_url' => '图片',
    ];

    /**
     * 导入出货单
     * @param Request $request
     * @return array
     */
    public function import(Request $request)
    {
        $file = $request->file('file');
        $destinationPath = '/uploads/admin/excel/'; // public文件夹下面uploads/xxxx-xx-xx 建文件夹
        $extension = $file->getClientOriginalExtension();   // 上传文件后缀
        $fileName = date('YmdHis').mt_rand(100,999).'.'.$extension; // 重命名
        $status = $file->move(public_path().$destinationPath, $fileName); // 保存图片
        if( !$status){ return $this->error();}
        // var_dump(public_path().$destinationPath.$fileName);exit;
        $path = public_path().$destinationPath.$fileName;
        try{
            $data  = Excel::toArray(new ShipmentFlawImport, $path);
            // $allCount =count($data[0]);
            $reData = (new $this->BaseModels)->import($data[0]);
        } catch (\Exception $e) {
            // var_dump( $e->getMessage());exit;
            // log_arr([$row,$e->getMessage()] ,'shipment');
            return  $this->error('',$e->getMessage());
        }
        return  $this->success([
            'allCount'=> count($reData['createData'])+count($reData['failData']),
            'success' =>[
                'number' => count($reData['createData']),
                'data'   => $reData['createData'],
                // 'data'   => [],
            ],
            'fail' =>[
                'number' => count($reData['failData']),
                'data'   => $reData['failData'],
            ]
        ],__('base.success'));
    }

    /**
     * 瑕疵列表
     *
     * @param Request $request
     * @return void
     */
    public function shipmentFlawList(Request $request)
    {
        // bill_id
        // $where_data= [];
        $user_id  = Auth::id();
        $user_ids = $this->getUserIdentity($user_id,'id');
        if(count($user_ids) == 0){
            $where_data= [];
        }else{
            $invoice_no = $this->bill_id($user_ids);
            $where_data= [['invoice_no','in',$invoice_no]];
        }
        $msg = !empty($this->BaseLVatMsg) ? $this->BaseLVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseLVat,$msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        $cur_page= !empty($data['cur_page'])? $data['cur_page']: 1;
        $size= !empty($data['size'])? $data['size']: 10;
        //修改参数  request要存在或者BUWhere存在
        foreach($this->BLWhere as $B_k => $B_v){
            // var_dump($B_k[1]);exit;
            if(!empty($B_v) && empty($B_v[1]) && isset($data[$B_k])){
                if($B_v[0] == 'allLike')
                {
                    $where_data[] = ["concat({$B_v[2]}) like ?",$B_v[0],["%{$data[$B_k]}%"]];
                    continue;
                }
                $where_data[] = [$B_k,$B_v[0],$data[$B_k]];
                continue;
            }
            if(!empty($B_v) && (!empty($B_v[1]) || $B_v[1] ===0)){
                $where_data[] = [$B_k,$B_v[0],$B_v[1]];
                continue;
            }
        }
        $RData = (new $this->BaseModels)->BaseLimit($where_data,$this->BL,$this->BLOrder,$cur_page,$size);
        // if(method_exists($this,'_limitFrom')) $RData = $this->_limitFrom($RData);
        foreach($RData['data'] as $k=>$v)
        {
            $RData['data'][$k]['failed_reason_url_list'] = explode(',',$v['failed_reason_url']);
        }
        return  $this->success($RData,__('base.success'));
    }

    /**
     * 导出
     * @param Request $request
     * @return array
     */
    public function export(Request $request)
    {
        // if(empty($request->ids) ) return $this->error(__('base.vdt'));
        $data = $request->all();
        $where=[];
        if(!empty($data['ids'])){
            $ids = explode(',',$data['ids']);
            $where = [['id','in',$ids ]];
        }
        $exportField = $this->exportField;
        $ssss = [];
        foreach($exportField as $k=>$v){
            $ssss[] = [
                'value' =>  $k,
                'label' => $v
            ];
        }
        // var_dump(json_encode($ssss,true));exit;
        $exportFieldRow=  $exportField;
        if(!empty($data['field'])){
            $exportFieldRow  = [];
            $field = explode(',',$data['field']);
            foreach($field as $field_v){
                $exportFieldRow[$field_v] = $exportField[$field_v];
            }
        }
        // $where = [['id','in',$ids ]];
        $bondedAdopt  = (new  $this->BaseModels)->BaseAll($where);
        $headers[]  = $exportFieldRow;
        $data = [];
        foreach($bondedAdopt as $v){
            $row = [];
            foreach($exportFieldRow as $e_k=>$e_sv){
                $row[$e_k] = $v[$e_k];
                // var_dump($e_k);exit;
                // var_dump( $v);exit;
            }
            $data[] = $row;
        }
        // var_dump($data);exit;
        $export = new Export($headers,$data);
        return Excel::download($export, date('YmdHis') . '.xlsx',);
    }
}