<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;
use Illuminate\Support\Facades\DB;

class Product extends BaseModel
{
    public $table = 'pms_product';

    const CREATEDTIME = 'createtime';
    const UPDATEDTIME = 'updatetime';

    //状态设置
    public static function productStatus($status = 1)
    {
        $date = [
            'apply_warehousing_status'          => 100,
            'apply_warehousing_status_name'     => '申请入库',
            'reject_warehousing_status'         => 110,
            'reject_warehousing_status_name'    => '驳回入库',
            'again_warehousing_status'          => 120,
            'again_warehousing_status_name'     => '重新提交入库',

            'warehousing_passed_status'         => 200,
            'warehousing_passed_status_name'    => '提交入库通过',
            // 'warehousing_status'                => 210,//入库
            'store_warehousing_status'          => 211,
            'store_warehousing_status_name'     => '门店入库',
            'express_warehousing_status'        => 212,
            'express_warehousing_status_name'   => '快递入库',
            'arrive_status'                     => 220, //到货登记
            'arrive_status_name'                => '到货登记',
            // 'quality_inspection_failed_status' => 230,//质检不通过
            'store_return_status'               => 231, //门店退回
            'store_return_status_name'          => '门店退回',
            'express_return_status'             => 232, //快递退回
            'express_return_status_name'        => '快递退回',

            'quality_inspection_passed_status'  => 300, //质检通过
            'quality_inspection_passed_status_name' => '质检通过',
        ];
        //可大致分成三个模块状态 入仓前  100开头   厂库状态200 开头  订单操作 300
        return $date;
    }

    //修改状态
    public static function updateProduct()
    {
    }

    //
    public static function importProduct($row)
    {
        // var_dump($row);exit;
        // $data=['name' =>$row[0]];
        // $data_sku = ['name' =>$row[0]];
        // $product_sn = $row[0];
        // $size = $row[0];
        // $size_where = 'JSON_EXTRACT(sp_data,"$[0].value")='.$size;

        // $Product = DB::table('pms_product')->where('product_sn','=',$product_sn)->first();
        // if( $Product ==NULL){
        //     $product_id = DB::table('pms_product')->insertGetId($data);
        // }else{
        //     $product_id =  $Product->id;
        // }
        // $pms_sku_stock = DB::table('pms_sku_stock')
        // ->where('product_id','=',$product_id)
        // // ->where('product_sn','=',$product_sn)
        // ->whereRaw($size_where)
        // ->first();
        // if( $pms_sku_stock ==NULL){
        //     DB::table('pms_product')->insertGetId(['name' =>$row[0],'product_sn' =>'12']);
        // }else{
        //     // DB::table('pms_product')->insertGetId(['name' =>$row[0],'product_sn' =>'12']);
        //     //加库存
        //     // $Product_id =  $Product->id;
        // }
        // var_dump($Product);
        return true;
    }

    /**
     * 公共封装 总查询
     *
     * @param [array] $where
     * @param array $select
     * @param array $order
     * @param integer $page
     * @param integer $size
     * @return array
     */
    public function BaseLimits($where, $select = ['*'], $order = [['id', 'desc']], $cur_page = 1, $size = 10, $product_id1 = 0, $product_id2 = 0)
    {
        // DB::connection()->enableQueryLog();
        $data = DB::table($this->table)->select($select);
        //处理条件
        foreach ($where as $v) {
            if (empty($v[1])) continue;
            if ($v[1] == 'in' || $v[1] == 'IN') $data->whereIn($v[0], $v[2]);
            if ($v[1] == 'like') $data->where($v[0], $v[1], '%' . $v[2] . '%');
            if ($v[1] == 'allLike') $data->whereRaw($v[0], $v[2]);
            if (in_array($v[1], $this->whereSymbol)) $data->where($v[0], $v[1], $v[2]);
        }
        if (!empty($product_id1)) $data->whereIn('id', $product_id1);
        if (!empty($product_id2)) {
            $data->orwhere(function ($query) use ($where, $product_id2) {
                foreach ($where as $v) {
                    if (empty($v[1])) continue;
                    if ($v[1] == 'in' || $v[1] == 'IN') $query->whereIn($v[0], $v[2]);
                    if ($v[1] == 'like') $query->where($v[0], $v[1], '%' . $v[2] . '%');
                    if ($v[1] == 'allLike') $query->whereRaw($v[0], $v[2]);
                    if (in_array($v[1], $this->whereSymbol)) $query->where($v[0], $v[1], $v[2]);
                }
                $query->whereIn('id', $product_id2);
            });
        }
        //处理排序
        foreach ($order as $Ok => $Ov) {
            if (empty($Ov[0]) || empty($Ov[1])) continue;
            $data->orderBy($Ov[0], $Ov[1]);
        }
        $reData = $data->paginate($size, ['*'], 'page', $cur_page);
        // $logs = DB::getQueryLog();   // 获取查询日志

        // dd($logs);               // 即可查看执行的sql，传入的参数等等
        return  objectToArray($reData);
    }

    public function productBrand()
    {
        return $this->hasOne(ProductBrand::class, 'product_sn', 'product_sn');
    }

    public function skus()
    {
        return $this->hasMany(SkuStock::class, 'product_id', 'id');
    }
}
