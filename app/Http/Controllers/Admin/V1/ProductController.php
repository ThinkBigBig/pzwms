<?php
    
namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\BaseController;
use Illuminate\Http\Request;
use App\Transformers\AuthorizationTransformer;
use Illuminate\Support\Facades\Hash;
use Dingo\Api\Exception\StoreResourceFailedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\SkuStock;
use App\Handlers\HttpService;
use App\Imports\ProductImport;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends BaseController
{
    protected $BaseModels = 'App\Models\Product';
    protected $BaseAllVat = [];//获取全部验证
    protected $BAWhere  = [];//获取全部Where条件
    protected $BA  = ['*'];//获取全部选取字段 *是全部
    protected $BAOrder  = [['id','desc']];//获取全部字段排序

    protected $BaseLVat = [];//获取分页全部验证
    protected $BLWhere  = [
        'name'=> ['like',''],
        'name_cn'=> ['like',''],
        'product_sn'=> ['like',''],
        'brand_id' => ['=',''],
        'product_category_id' => ['=',''],
        'series_id'=>['=',''],
        'recommand_status'=>['=',''],
        'publish_status'=>['=',''],
        // 'delete_status'=>['=',0]
    ];//获取全部分页Where条件
    protected $BL  = ['id','pic','name','name_cn','brand_name','publish_status','recommand_status','product_sn','sale','sort','series_id','recommand_status_sort','carryme_id'];//获取全部分页选取字段 *是全部
    protected $BLOrder  = [['publish_status','desc'],['sort','desc'],['id','desc']];//获取全部分页字段排序

    protected $BaseOneVat = ['id' => 'required',];//单个处理验证
    protected $BOWhere = ['id'=>['=','']];//单个查询验证
    protected $BO = ['*'];//单个选取字段；*是全部
    protected $BOOrder = [['id','desc']];//单个选取字段排序

    protected $BaseCreateVat = [
        'name' => 'required',//系列名称
        'name_cn' => 'required',//系列名称（中文）
        'brand_id' => 'required',//品牌的id
        'series_id' => 'required',//品牌的id
        // 'category_id' => 'required',//产品类型的id,
    ];//新增验证
    protected $BaseCreate =[
        'brand_id'=>'',
        'product_category_id'=>'',
        'feight_template_id'=>'',
        'product_attribute_category_id'=>'',
        'name'=>'','name_cn'=>'',
        'pic'=>'','product_sn'=>'','delete_status'=>'',
        'publish_status'=>'','new_status'=>'','recommand_status'=>'',
        'verify_status'=>'','sort'=>'','sale'=>'',
        'price'=>'',
        'promotion_price'=>'',
        'gift_growth' =>'',
        'gift_point'=>'',
        'use_point_limit' =>'',
        'sub_title' =>'',
        'description' =>'',
        'original_price'=>'',
        'stock' =>'',
        'low_stock' =>'',
        'unit' =>'',
        'weight' =>'',
        'preview_status'=>'',
        'service_ids'=>'',
        'keywords'=>'',
        'keywords_cn' =>'',
        'note'=>'',
        'album_pics'=>'',
        'album_pics_exhibition' =>'',
        'album_pics_wear' =>'',
        'album_pics_details' =>'',
        'detail_desc' =>'',
        'detail_desc_cn' =>'',
        'promotion_start_time'=>'',
        'promotion_end_time' =>'',
        'promotion_per_limit' =>'',
        'promotion_type' =>'',
        'brand_name'=>'',
        'product_category_name' =>'',
        'parent_series_id' =>'',
        'series_id' =>'',
        'postage' =>'',
        'eligibility' =>'',
        'release_time'=>'',
        'date_type' =>'',
        'advance_charge' =>'',
        'interview_num_week' =>'',
        'interview_num' =>'',
        'water_sale' =>'',
        'ding_stock' =>'',
        'new_sale_img' =>'',
        'new_sale_img_cn'=>'',
        'active_img' =>'',
        'active_img_cn' =>'',
        'create_date' => ['type','date'],
    ];//新增数据

    protected $BaseUpdateVat = [
        'id' =>        'required',
    ];//新增验证
    protected $BaseUpdate =[
        'id'=>'',
        'brand_id'=>'',
        'product_category_id'=>'',
        'feight_template_id'=>'',
        'product_attribute_category_id'=>'',
        'name'=>'','name_cn'=>'',
        'pic'=>'','product_sn'=>'','delete_status'=>'',
        'publish_status'=>'','new_status'=>'','recommand_status'=>'',
        'verify_status'=>'','sort'=>'','sale'=>'',
        'price'=>'',
        'promotion_price'=>'',
        'gift_growth' =>'',
        'gift_point'=>'',
        'use_point_limit' =>'',
        'sub_title' =>'',
        'description' =>'',
        'original_price'=>'',
        'stock' =>'',
        'low_stock' =>'',
        'unit' =>'',
        'weight' =>'',
        'preview_status'=>'',
        'service_ids'=>'',
        'keywords'=>'',
        'keywords_cn' =>'',
        'note'=>'',
        'recommand_status_sort' => '',
        'album_pics'=>'',
        'album_pics_exhibition' =>'',
        'album_pics_wear' =>'',
        'album_pics_details' =>'',
        'detail_desc' =>'',
        'detail_desc_cn' =>'',
        'promotion_start_time'=>'',
        'promotion_end_time' =>'',
        'promotion_per_limit' =>'',
        'promotion_type' =>'',
        'brand_name'=>'',
        'product_category_name' =>'',
        'parent_series_id' =>'',
        'series_id' =>'',
        'postage' =>'',
        'eligibility' =>'',
        'release_time'=>'',
        'date_type' =>'',
        'advance_charge' =>'',
        'interview_num_week' =>'',
        'interview_num' =>'',
        'water_sale' =>'',
        'ding_stock' =>'',
        'new_sale_img' =>'',
        'new_sale_img_cn'=>'',
        'active_img' =>'',
        'active_img_cn' =>'',
        'modify_date' => ['type','date'],
    ];//新增数据
    protected $BUWhere= ['id'=>['=','']];//新增数据


    public function BaseLimit(Request $request){
        $where_data= [];
        $msg = !empty($this->BaseLVatMsg) ? $this->BaseLVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseLVat,$msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        $cur_page= !empty($data['cur_page'])? $data['cur_page']: 1;
        $size= !empty($data['size'])? $data['size']: 10;
        $recommand_status =0;
        $product_id2 = 0;
        $product_id1 = 0;
        //修改参数  request要存在或者BUWhere存在
        foreach($this->BLWhere as $B_k => $B_v){
            // var_dump($B_k[1]);exit;
            if(!empty($B_v) && empty($B_v[1]) && isset($data[$B_k])){
                if($B_k == 'recommand_status'){
                    $recommand_status = 1;
                }

                // if(($B_k == 'brand_id' || $B_k == 'series_id')&& !empty($data['brand_id']) && !empty($data['series_id']) ){
                //     $product_id1 =  DB::table('pms_joint')->where('code','=',$data[$B_k])->where('type_class','=',1)->pluck('product_id');
                //     $product_id2 =  DB::table('pms_joint')->where('code','=',$data[$B_k])->where('type_class','=',2)->pluck('product_id');
                //     if(!empty($product_id1) && $product_id1 != NULL) $product_id1=json_decode($product_id1,true) ; else $product_id1 = '';
                //     if(!empty($product_id2) && $product_id2 != NULL) $product_id2=json_decode($product_id2,true); else $product_id2 = '';
                //     // $where_data[] = ['carryme_id','orwhere',['product_id1' => $product_id1, 'product_id2' => $product_id2 ]];
                //     continue;
                // }
                // if($B_k == 'brand_id' && !empty($data[$B_k]) ){
                //     $product_id1 =  DB::table('pms_joint')->where('code','=',$data[$B_k])->where('type','=',1)->where('type_class','=',1)->pluck('product_id');
                //     $product_id2 =  DB::table('pms_joint')->where('code','=',$data[$B_k])->where('type','=',1)->where('type_class','=',2)->pluck('product_id');
                //     if(!empty($product_id1) && $product_id1 != NULL) $product_id1=json_decode($product_id1,true) ; else $product_id1 = '';
                //     if(!empty($product_id2) && $product_id2 != NULL) $product_id2=json_decode($product_id2,true); else $product_id2 = '';
                //     // $where_data[] = ['carryme_id','orwhere',['product_id1' => $product_id1, 'product_id2' => $product_id2 ]];
                //     continue;
                // }

                // if($B_k == 'series_id' && !empty($data[$B_k])){

                //     $product_id1 =  DB::table('pms_joint')->where('code','=',$data[$B_k])->where('type','=',2)->where('type_class','=',1)->pluck('product_id');
                //     $product_id2 =  DB::table('pms_joint')->where('code','=',$data[$B_k])->where('type','=',2)->where('type_class','=',2)->pluck('product_id');
                //     if(!empty($product_id1) && $product_id1 != NULL) $product_id1=json_decode($product_id1,true) ; else $product_id1 = '';
                //     if(!empty($product_id2) && $product_id2 != NULL) $product_id2=json_decode($product_id2,true); else $product_id2 = '';
                //     // $where_data[] = ['carryme_id','orwhere',['product_id1' => $product_id1, 'product_id2' => $product_id2 ]];
                //     continue;
                // }

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
        if(!empty($recommand_status))
        {
            $this->BLOrder= [['recommand_status_sort','desc'],['id','desc']];
        }
        $RData = (new $this->BaseModels)->BaseLimits($where_data,$this->BL,$this->BLOrder,$cur_page,$size,$product_id1,$product_id2);
        if(method_exists($this,'_limitFrom')) $RData = $this->_limitFrom($RData);
        return  $this->success($RData,__('base.success'));
    }
    /**
     *基础新增方法
     *
     * @param Request $request
     * @return void
     */
    public function BaseCreate(Request $request)
    {
        $create_data = [];
        $msg = !empty($this->BaseCreateVatMsg) ? $this->BaseCreateVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseCreateVat,$msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        if(!empty($request['name'])){
            $name = (new $this->BaseModels)->where('delete_status','=',0)->where('name','=',$request['name'])->pluck('name');
            if(!empty($name[0]) ) return  $this->error(__('admin.product.name.being'));
        }
        if(!empty($request['product_sn'])){
            $product_sn = (new $this->BaseModels)->where('delete_status','=',0)->where('product_sn','=',$request['product_sn'])->pluck('product_sn');
            if(!empty($product_sn[0]) ) return  $this->error(__('admin.product.product_sn.being'));
        }
        $data = $request->all();
        $joint_data =[];
        //根据配置传入参数
        foreach($this->BaseCreate as $k => $v){
            if(isset($data[$k]) && $data[$k] !=''){
                $create_data[$k] = $data[$k];
            }
            if(!empty($v[0]) && !empty($v[1]) && $v[0]== 'type' && $v[1]== 'time'){
                $create_data[$k] = time();
            }
            if(!empty($v[0]) && !empty($v[1]) && $v[0]== 'type' && $v[1]== 'date'){
                $create_data[$k] = date('Y-m-d H:i:s');
            }
        }
        // if(!empty($data['brand_id'])){
        //     foreach($data['brand_id'] as $v){
        //         $joint_data[] = [
        //             'type' => 1,
        //             'type_class' => 2,
        //             'code' =>$v ,
        //         ];
        //     }
        // }
        // if(!empty($data['brand_id'])){
        //     foreach($data['series_id'] as $v){
        //         $joint_data[] = [
        //             'type' => 2,
        //             'code' =>$v,
        //             'type_class' => 2
        //         ];
        //     }
        // }
        // if(count($joint_data)>0){
        //     DB::table('pms_joint')->insert($joint_data);
        // }
        $create_data['delete_status'] =  0;
        //修改参数  request要存在或者BUWhere存在
        $id = (new $this->BaseModels)->BaseCreate($create_data);
        if($id){
            $data['id'] = $id;
            return  $this->success($data,__('base.success'));
        }else{
            return  $this->error();
        }
    }

    /**
     *基础修改方法
     *
     * @param Request $request
     * @return void
     */
    public function BaseUpdate(Request $request)
    {
        $where_data= [];
        $update_data = [];
        $msg = !empty($this->BaseUpdateVatMsg) ? $this->BaseUpdateVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseUpdateVat,$msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        //根据配置传入参数
        foreach($this->BaseUpdate as $k => $v){
            if(isset($data[$k]) && $data[$k] !==''){
                $update_data[$k] = $data[$k];
            }
            if(!empty($v) && empty($v[1])){
                $update_data[$k] = $v;
            }
            if(!empty($v[0]) && !empty($v[1]) && $v[0]== 'type' && $v[1]== 'time'){
                $update_data[$k] = time();
            }
            if(!empty($v[0]) && !empty($v[1]) && $v[0]== 'type' && $v[1]== 'date'){
                $update_data[$k] = date('Y-m-d H:i:s');
            }
        }
        //修改参数  request要存在或者BUWhere存在
        foreach($this->BUWhere as $B_k => $B_v){
            if(isset($data[$B_k]) && empty($B_v[1]) && $data[$B_k] !=''){
                $where_data[] = [$B_k,$B_v[0],$data[$B_k]];
            }
            if(!empty($B_v) && !empty($B_v[1])){
                $where_data[] = [$B_k,$B_v[0],$B_v[1]];
            }
        }
        // if(!empty($data['brand_id'])){
        //     foreach($data['brand_id'] as $v){
        //         $joint_data[] = [
        //             'type' => 1,
        //             'type_class' => $type_class,
        //             'code' =>$v ,
        //         ];
        //     }
        // }
        // if(!empty($data['brand_id'])){
        //     foreach($data['series_id'] as $v){
        //         $joint_data[] = [
        //             'type' => 2,
        //             'code' =>$v,
        //             'type_class' => $type_class,
        //         ];
        //     }
        // }
        // if(count($joint_data)>0){
        //     DB::table('pms_joint')->insert($joint_data);
        // }
        $RData = (new $this->BaseModels)->BaseUpdate($where_data,$update_data);
        if($RData)
            return  $this->success($data,__('base.success'));
        else
            return  $this->error();
    }

    public function delete(Request $request){

        $validator = Validator::make($request->all(), [
            'ids' => 'required',
        ]);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        // $ids = $request->ids;
        $ids = explode(',',$request->ids);
        $update_data = ['delete_status'=>1];
        $RData = (new $this->BaseModels)->BaseUpdate([['id','in',$ids]],$update_data);
        if($RData)
            return  $this->success([],__('base.success'));
        else
            return  $this->error();
    }

    public function _limitFrom($RData)
    {
        if(!empty($RData)){
            if(!empty($RData['data'])){
                foreach($RData['data'] as &$v){
                    if(!empty($v['pic'])) $v['pic'] = cdnurl($v['pic']);
                    // if(empty($v['carryme_id'])){
                        // $code = DB::table('pms_joint')->where('product_id','=',$v['id'])->where('type',2)->pluck('code');
                    // }
                    // else{
                    //     $code = DB::table('pms_joint')->where('product_id','=',$v['carryme_id'])->where('type',2)->where('type_class',1)->pluck('code');
                    // }
                    // $v['series_name'] = '';
                    // $v['series_name'] = '';
                }
            }
        }
        return $RData;
    }

    public function _oneFrom($RData)
    {
        if(!empty($RData)){
            if(!empty($RData['pic'])) $RData['pic'] = cdnurl($RData['pic']);
            if(!empty($RData['album_pics_wear'])) $RData['album_pics_wear'] = cdnurl($RData['album_pics_wear']);
            if(!empty($RData['album_pics_exhibition'])) $RData['album_pics_exhibition'] = cdnurl($RData['album_pics_exhibition']);
            if(!empty($RData['album_pics'])){
                $album_pics = explode(',',$RData['album_pics']);
                foreach($album_pics as &$album_pics_v){
                    $album_pics_v =  cdnurl($album_pics_v);
                }
                $RData['album_pics'] = implode(',',$album_pics);
            }
            $RData['brand_id'] = [];
            $RData['series_id'] = [];
            // if(empty($v['carryme_id'])){
                //$brand_id = DB::table('pms_joint')->where('product_id','=',$RData['id'])->where('type',1)->pluck('code');
                //$series_id = DB::table('pms_joint')->where('product_id','=',$RData['id'])->where('type',2)->pluck('code');
            // }else{
            //     $brand_id = DB::table('pms_joint')->where('product_id','=',$RData['carryme_id'])->where('type',1)->where('type_class',1)->pluck('code');
            //     $series_id = DB::table('pms_joint')->where('product_id','=',$RData['carryme_id'])->where('type',2)->where('type_class',1)->pluck('code');
            // }
            if(!empty($brand_id) && $brand_id != NULL){
                $RData['brand_id']=json_decode($brand_id,true) ;
            }
            $v['series_name'] = '';
            if(!empty($series_id) && $series_id != NULL){
                $series_id=json_decode($series_id,true) ;
                $RData['series_id'] = $series_id;
                $series_name = DB::table('pms_product_series')->where('id','=',$series_id)->pluck('name');
                $v['series_name'] = $series_name[0] ?? '';
            }
            $RData['list'] = DB::table('pms_sku_stock')
                            ->where('product_id','=',$RData['id'])
                            ->where('is_delete','=',0)
                            ->orderBy('sort','desc')
                            ->get();
            if(!empty($RData['list'])){
                // if(!empty($RData['id'])){
                //     $get_sku_price = self::get_sku_price($RData['id']);
                // }
                // $carryme_price ='暂无参考';
                foreach($RData['list'] as &$v){
                    if(!empty($v->web_price) && $v->web_price > 0){
                        $v->web_price = floatval($v->web_price);
                    }

                    // if(!empty($get_sku_price[$v->carryme_product_sku_id]['price'])){
                    //     $v->carryme_price = $get_sku_price[$v->carryme_product_sku_id]['price'];
                    // }else{
                    //     $v->carryme_price = $carryme_price;
                    // }
                }
            }
        }
        return $RData;
    }

    /**
        *基础修改方法
        *
        * @param Request $request
        * @return void
        */
    public function skuUpdate(Request $request)
    {
        $where_data= [];
        $update_data = [
        ];
        $BaseUpdate = [
            'id'=>'',
            // 'sku_code',
            'price'=>'',
            'stock'=>'',
            'web_price'=>'',
            // 'low_stock',
            // 'pic',
            // 'promotion_price',
            // 'sale',
            // 'lock_stock',
            'publish_status'=>'',
            'is_delete'=>'',
            'sort'=>'',
        ];
        $v_array = ['id' => 'required'];
        // $validator = Validator::make($request->all(), $v_array ,
        // [
        //     'price.required' => __('admin.product.price.required'),
        //     'stock.required' => __('admin.product.stock.required')
        // ]);
        // if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        $where_data[] = ['id','=',$data['id']];
        //根据配置传入参数
        foreach($BaseUpdate as $k => $v){
            if(isset($data[$k]) && $data[$k] !==''){
                $update_data[$k] = $data[$k];
            }
            if(!empty($v) && empty($v[1])){
                $update_data[$k] = $v;
            }
            if(!empty($v[0]) && !empty($v[1]) && $v[0]== 'type' && $v[1]== 'time'){
                $update_data[$k] = time();
            }
            if(!empty($v[0]) && !empty($v[1]) && $v[0]== 'type' && $v[1]== 'date'){
                $update_data[$k] = date('Y-m-d H:i:s');
            }
        }

        $RData = (new SkuStock)->BaseUpdate($where_data,$update_data);
        if($RData)
            return  $this->success($data,__('base.success'));
        else
            return  $this->error();
    }

    /**
    *基础修改方法
    *
    * @param Request $request
    * @return void
    */
    public function skuUpdateAll(Request $request)
    {
        // $data = $request->all();
        $data = json_decode(file_get_contents('php://input'), true);
        $update_data = [];
        foreach($data as $k => $v){
            if(empty($v['id']) && !empty($v['product_id']) ) {
                $sku_array = [
                    'product_id' => $data[$k]['product_id'],
                    'sku_code' => date('YmdHis'),
                    'price' =>  $v['price'],
                    'web_price' =>  $v['web_price'],
                    'stock' => $v['stock'],
                    'is_delete' => $v['is_delete'],
                    'sp_data' =>  $v['sp_data'],
                    'sort' => $v['sort'],
                    'publish_status' => $v['publish_status'],
                ];
                $skuAdd[] =  $sku_array;
                continue;
            }
            if(empty($v['id']))  continue;
            $update_data_son['id'] = $v['id'];
            $update_data_son['price']  = $v['price'];
            $update_data_son['web_price']  =  $v['web_price'];
            $update_data_son['stock']  = $v['stock'];
            $update_data_son['sort']  = $v['sort'];
            $update_data_son['is_delete']  = $v['is_delete'];
            $update_data_son['sp_data'] = $v['sp_data'];
            $update_data_son['publish_status'] = $v['publish_status'];
            $update_data[] = $update_data_son;
        }
        if(!empty($skuAdd))
        {
            DB::table('pms_sku_stock')->insert($skuAdd);
        }
        $RData = (new SkuStock)->updateBatch($update_data);
        return  $this->success($data,__('base.success'));
    }

    /**
    *基础修改方法
    *
    * @param Request $request
    * @return void
    */
    public function skuAdd(Request $request)
    {
        $data = $request->all();
        $array = [];
        // $array['id'] = $data['id'];
        // $array['price']  = $data['price'];
        // $array['stock']  = $data['stock'];
        // $array['sort']  = $data['sort'];
        // $array['is_delete']  = $data['is_delete'];
        $RData = (new SkuStock)->BaseCreate($array);
        if($RData)
            return  $this->success($data,__('base.success'));
        else
            return  $this->error();
    }

    /**
     *删除尺码
     *
     * @param Request $request
     * @return void
     */
    public function skuDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required',
        ],['ids.required'=>__('base.vdt')]);
        if ($validator->fails()) return $this->errorBadRequest($validator);

        $ids = explode(',',$request->ids);
        $update_data = ['is_delete'=> 1];
        $RData = (new SkuStock)->BaseUpdate([['id','in',$ids]],$update_data);
        if($RData)
            return  $this->success([],__('base.success'));
        else
            return  $this->error();
    }

    protected static function get_sku_price($id){
        $CARRYME_URL = env('CARRYME_URL','https://test-carryme2-appapi.paizhoukeji.com');
        $url = $CARRYME_URL."/buy/getProductStandardList?productId={$id}&lang=cn";
        $data = HttpService::get($url);
        $json = json_decode($data,true);
        if($json && !empty($json['data']) && $json != NULL){
            // $array = [];
            // foreach($json['data']['productStandardList'] as $v){
            //     $array[$v['id']] = $v;
            // }
            $array =array_column( $json['data']['productStandardList'], NULL, 'id');
            return $array;
        }else{
            log_arr(['查询价格失败', $url,$json],'get_sku_price');
            return false;
        }
    }

    //导入商品基本数据
    public function import(Request $request)
    {
        $file = $request->file('file');
        $destinationPath = '/uploads/admin/excel/'; // public文件夹下面uploads/xxxx-xx-xx 建文件夹
        $extension = $file->getClientOriginalExtension();   // 上传文件后缀
        $fileName = date('YmdHis').mt_rand(100,999).'.'.$extension; // 重命名
        $status = $file->move(public_path().$destinationPath, $fileName); // 保存图片
        if( !$status){ $this->error(); }
        // var_dump(public_path().$destinationPath.$fileName);exit;
        $path = public_path().$destinationPath.$fileName;
        Excel::import(new ProductImport, $path);
        return  $this->success([],__('base.success'));
    }

    //商品列表
    public function productList(Request $request)
    {
        $where_data= [];
        $msg = !empty($this->BaseLVatMsg) ? $this->BaseLVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseLVat,$msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        $cur_page= !empty($data['cur_page'])? $data['cur_page']: 1;
        $size= !empty($data['size'])? $data['size']: 10;
        $recommand_status =0;
        $product_id2 = 0;
        $product_id1 = 0;
        //修改参数  request要存在或者BUWhere存在
        foreach($this->BLWhere as $B_k => $B_v){
            // var_dump($B_k[1]);exit;
            if(!empty($B_v) && empty($B_v[1]) && isset($data[$B_k])){
                if($B_k == 'recommand_status'){
                    $recommand_status = 1;
                }

                // if(($B_k == 'brand_id' || $B_k == 'series_id')&& !empty($data['brand_id']) && !empty($data['series_id']) ){
                //     $product_id1 =  DB::table('pms_joint')->where('code','=',$data[$B_k])->where('type_class','=',1)->pluck('product_id');
                //     $product_id2 =  DB::table('pms_joint')->where('code','=',$data[$B_k])->where('type_class','=',2)->pluck('product_id');
                //     if(!empty($product_id1) && $product_id1 != NULL) $product_id1=json_decode($product_id1,true) ; else $product_id1 = '';
                //     if(!empty($product_id2) && $product_id2 != NULL) $product_id2=json_decode($product_id2,true); else $product_id2 = '';
                //     // $where_data[] = ['carryme_id','orwhere',['product_id1' => $product_id1, 'product_id2' => $product_id2 ]];
                //     continue;
                // }
                // if($B_k == 'brand_id' && !empty($data[$B_k]) ){
                //     $product_id1 =  DB::table('pms_joint')->where('code','=',$data[$B_k])->where('type','=',1)->where('type_class','=',1)->pluck('product_id');
                //     $product_id2 =  DB::table('pms_joint')->where('code','=',$data[$B_k])->where('type','=',1)->where('type_class','=',2)->pluck('product_id');
                //     if(!empty($product_id1) && $product_id1 != NULL) $product_id1=json_decode($product_id1,true) ; else $product_id1 = '';
                //     if(!empty($product_id2) && $product_id2 != NULL) $product_id2=json_decode($product_id2,true); else $product_id2 = '';
                //     // $where_data[] = ['carryme_id','orwhere',['product_id1' => $product_id1, 'product_id2' => $product_id2 ]];
                //     continue;
                // }

                // if($B_k == 'series_id' && !empty($data[$B_k])){

                //     $product_id1 =  DB::table('pms_joint')->where('code','=',$data[$B_k])->where('type','=',2)->where('type_class','=',1)->pluck('product_id');
                //     $product_id2 =  DB::table('pms_joint')->where('code','=',$data[$B_k])->where('type','=',2)->where('type_class','=',2)->pluck('product_id');
                //     if(!empty($product_id1) && $product_id1 != NULL) $product_id1=json_decode($product_id1,true) ; else $product_id1 = '';
                //     if(!empty($product_id2) && $product_id2 != NULL) $product_id2=json_decode($product_id2,true); else $product_id2 = '';
                //     // $where_data[] = ['carryme_id','orwhere',['product_id1' => $product_id1, 'product_id2' => $product_id2 ]];
                //     continue;
                // }

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
        if(!empty($recommand_status))
        {
            $this->BLOrder= [['recommand_status_sort','desc'],['id','desc']];
        }
        $RData = (new $this->BaseModels)->BaseLimits($where_data,$this->BL,$this->BLOrder,$cur_page,$size,$product_id1,$product_id2);
        if(method_exists($this,'_limitFrom')) $RData = $this->_limitFrom($RData);
        return  $this->success($RData,__('base.success'));

    }

    //商品详情
    public function productInfo(Request $request)
    {
        $BaseOneVat = ['product_sn' => 'required',];//单个处理验证
        $BOWhere = ['product_sn'=>['=','']];//单个查询验证
        $where_data= [];
        $msg = !empty($this->BaseOneVatMsg) ? $this->BaseOneVatMsg : ['product_sn.required' =>__('base.vdt')];
        $validator = Validator::make($request->all(), $BaseOneVat,$msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        //修改参数  request要存在或者BUWhere存在
        foreach($BOWhere as $B_k => $B_v){
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
        $RData = (new $this->BaseModels)->BaseOne($where_data,$this->BO,$this->BOOrder);
        if(!empty($RData['id'])){
            $RData['sku_list'] = (new SkuStock)->BaseAll([['product_id','=',$RData['id']]],['*'],[['id','desc']]);
            // $RData['sku_list'] = objectToArray($RData['sku_list']);
            foreach($RData['sku_list'] as $k=> $_v){
                $RData['sku_list'][$k]['tagPrice']      = $_v['tagPrice']*100;
                $RData['sku_list'][$k]['retailPrice']   = $_v['retailPrice']*100;
                $RData['sku_list'][$k]['costPrice']     = $_v['costPrice']*100;
                $RData['sku_list'][$k]['purchasePrice'] = $_v['purchasePrice']*100;
            }
        }
        return  $this->success($RData,__('base.success'));
    }


    public function synchro(Request $request)
    {
        // 'id-表名-操作'
        //验证
        if(empty($request->key))  return $this->error();
        try {
            $key = $request->key;
            $key = mb_substr(mb_substr(base64_decode($key), 7), 0, -7);
            $data = explode('-',$key);
            if(empty($data[0])|| empty($data[1])|| empty($data[2])||empty($data[3]))  return $this->error();
            (int)$time = time()-30;
            // if((int)$data[3] < $time)  return $this->error();
            // Redis::rpush('synchro_queue',$key);
            return $this->success($request);
        } catch (\Throwable $th) {
            return $this->error();
        }
    }

    public function pass(){
        $token = 'B36387A53E3387E15C57A493EB213A1B';
        $app_key='shendu2022-12';
        $str1 = mb_substr($token,0,5);
        $str2 = mb_substr($app_key,0,2);
        $str3 = mb_substr($app_key,-2);
        $str4 = mb_substr($token,-5);
        $sing = base64_encode($str1.$str2.$token.$str3.$str4);
        // var_dump($ret);
    }

    /**
     * 验签方法
     *
     * @param Request $request
     * @return void
     */
    public function sssssss(Request $request)
    {
        $paramArr['app_key'] = 123;
        $paramArr['appSecret'] = 12333;
        // $paramArr['time'] = time();
        return $this->createSign($paramArr);
    }

    /**
     * 生成签名
     * @param $paramArr
     * @return string
     */
    private function createSign($paramArr) {
        ksort($paramArr);
        foreach($paramArr as $key => $val) { //过滤空数据项
            if(is_array($val)) {
                $paramArr[$key] = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        $sign_str = http_build_query($paramArr, '&');
        // $sign_str .= $this->appSecret;
        // var_dump(strtoupper(md5($sign_str)));exit;
        return strtoupper(md5($sign_str));
    }

    /**
     * 验签方法
     *
     * @param Request $request
     * @return void
     */
    public function checkSignature(Request $request)
    {
        $paramArr['app_key'] = $request->app_key;
        $paramArr['appSecret'] = $request->appSecret;
        $signs = $request->sign;
        $sign = $this->createSigns($paramArr);
        if($sign !=$signs){
            return '签名错误';
        }
        return '签名正确';
    }


}


