<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\BaseController;
use Illuminate\Http\Request;
use App\Transformers\AuthorizationTransformer;
use Illuminate\Support\Facades\Hash;
use Dingo\Api\Exception\StoreResourceFailedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BondedStockNumberController extends BaseController
{
    protected $BaseModels = 'App\Models\BondedStockNumber';
    protected $BaseAllVat = [ 'appoint_no' => 'required',];//获取全部验证
    protected $BAWhere  = ['appoint_no'=>['=',''],'article_number'=>['=','']];//获取全部Where条件
    protected $BA  = ['*'];//获取全部选取字段 *是全部
    protected $BAOrder  = [['id','desc']];//获取全部字段排序

    protected $BaseLVat = [];//获取分页全部验证
    protected $BLWhere  = [];//获取全部分页Where条件
    protected $BL  = ['*'];//获取全部分页选取字段 *是全部
    protected $BLOrder  = [['id','desc']];//获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',];//单个处理验证
    protected $BOWhere = ['id'=>['=','']];//单个查询验证
    protected $BO = ['*'];//单个选取字段；*是全部
    protected $BOOrder = [['id','desc']];//单个选取字段排序

    protected $BaseCreateVat = [
    ];//新增验证
    protected $BaseCreate =[
        'order_bonded' => '',
    ];//新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
    ];//新增验证
    protected $BaseUpdate =[
        'id'=>'',
        'order_bonded' => '',
        'updatetime' => ['type','time'],
    ];//新增数据
    protected $BUWhere= ['id'=>['=','']];//新增数据

    public function _oneFrom($RData)
    {
        // var_dump(json_encode($this->BaseCreate,true));
        // if(!empty($RData)){
        //     $brand_name  = DB::table('pms_brand')->where('id','=',$RData['brand_id'])->pluck('name');
        //     $brand_name_cn = DB::table('pms_brand')->where('id','=',$RData['brand_id'])->pluck('name_cn');
        //     $category_name =  DB::table('pms_product_category')->where('id','=',$RData['category_id'])->pluck('name');
        //     $category_name_cn = DB::table('pms_product_category')->where('id','=',$RData['category_id'])->pluck('name_cn');
        //     $RData['brand_name'] =  !empty($brand_name[0]) ? $brand_name[0]: '';
        //     $RData['brand_name_cn'] =  !empty($brand_name_cn[0]) ? $brand_name_cn[0]: '';
        //     $RData['category_name'] =  !empty($category_name[0]) ? $category_name[0]: '';
        //     $RData['category_name_cn'] = !empty($category_name_cn[0]) ? $category_name_cn[0]: '';
        // }
        return $RData;
    }

    public function _limitFrom($RData)
    {
        // if(!empty($RData['data'])){
        //     foreach($RData['data'] as &$v){
        //         if(!empty($RData))
        //         {
        //             $brand_name  = DB::table('pms_brand')->where('id','=',$v['brand_id'])->pluck('name');
        //             $brand_name_cn = DB::table('pms_brand')->where('id','=',$v['brand_id'])->pluck('name_cn');
        //             $category_name =  DB::table('pms_product_category')->where('id','=',$v['category_id'])->pluck('name');
        //             $category_name_cn = DB::table('pms_product_category')->where('id','=',$v['category_id'])->pluck('name_cn');
        //             $v['brand_name'] =  !empty($brand_name[0]) ? $brand_name[0]: '';
        //             $v['brand_name_cn'] =  !empty($brand_name_cn[0]) ? $brand_name_cn[0]: '';
        //             $v['category_name'] =  !empty($category_name[0]) ? $category_name[0]: '';
        //             $v['category_name_cn'] = !empty($category_name_cn[0]) ? $category_name_cn[0]: '';
        //         }
        //     }
        // }
        return $RData;
    }

    public function BaseLimit(Request $request){

        $where_data= [];
        $msg = !empty($this->BaseLVatMsg) ? $this->BaseLVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseLVat,$msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        $cur_page= !empty($data['cur_page'])? $data['cur_page']: 1;
        $size= !empty($data['size'])? $data['size']: 10;
        $this->BLWhere = ['appoint_no'];
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
        return  $this->success($RData,__('base.success'));
    }

    /**
     * 可售库存
     *
     * @return void
     */
    public function bondedNumberList(Request $request)
    {
        $where_data= [];
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
        return  $this->success($RData,__('base.success'));
    }

    /**
     *
     * @param Request $request
     * @return void
     */
    public function flaw(Request $request)
    {
        $where_data= [['identify_fail_list','!=',NULL]];
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
        foreach($RData['data'] as &$v)
        {
            $v['identify_fail_list_name'] = json_decode($v['identify_fail_list'],true);
        }
        return  $this->success($RData,__('base.success'));
    }
}
