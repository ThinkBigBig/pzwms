<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;
use App\Handlers\DwApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class Shipment extends BaseModel
{
    public $table = 'pms_shipment';

    const CREATEDTIME = 'createtime';
    const UPDATEDTIME = 'updatetime';

    protected $guarded = [];
    public $timestamps = false;

    /**
     * 导入数据
     *
     * @return array
     */
    public function import($data)
    {
        $createData = [];
        $failData   = [];
        foreach($data as $row){
            //   var_dump($row[0][]);
            //   $row =  $v[0];
            //   var_dump($row[0]);
            if($row[0] == '发货单号'){
                continue;
            }
            $createDataSon = [
                  'invoice_no'                =>  $row[0],//发货单号
                  'objective'                 =>  $row[1],//目的仓
                  'grouping'                  =>  $row[2],//入仓分组
                  'stock_no'                  =>  $row[3],//备货单号
                  'good_name'                 =>  $row[4],//商品名称
                  'product_sn'                =>  $row[5],//商品货号
                  'properties'                =>  $row[6],//规格
                  'bar_code'                  =>  $row[7],//条码
                  'appoint_qty'               =>  $row[8],//预约数量
                  'shipment_qty'              =>  $row[9],//发货数量
                  'warehouse_qty'             =>  $row[10],//仓库实收数量
                  'variance_qty'              =>  $row[11],//差异数量
                  'identification_passed_qty' =>  $row[12],//鉴别通过数量
                  'identification_passed_qty2'=>  $row[12],//鉴别通过数量(做库存使用)
                  'identification_failed_qty' =>  $row[13],//鉴别不通过数量
                  'inspection_failed_qty'     =>  $row[14],//质检未通过数量
                  'no_identified_qty'         =>  $row[15],//未鉴定数
            ];
            $where = [
                ['product_sn' ,'=' , $createDataSon['product_sn']],
                ['properties' ,'=' , $createDataSon['properties']],
                ['sku_id' ,'>', 0],
            ];
            $where2 = [['invoice_no','=', $createDataSon['invoice_no']],
                    ['stock_no' , '=', $createDataSon['stock_no'] ],
                    // ['product_sn' ,'=' , $createDataSon['product_sn']],
                    // ['properties' ,'=' , $createDataSon['properties']],
            ];
            $shipmentModel = (new Shipment);
            $shipmentData  =  $shipmentModel->BaseOne($where2);//查表里是否已经被导入

            if(count($shipmentData)>0) {
                $failData[]= $createDataSon; continue;
            }

            $shipmentDataSku = $shipmentModel->BaseOne($where);//查表里是否已经有sku
            // var_dump($shipmentDataSku);exit;
            if(count($shipmentDataSku)>0){
                //   var_dump($shipmentDataSku);exit;
                  $sku_id = $shipmentDataSku['sku_id'];
                  $spu_id = $shipmentDataSku['spu_id'];
            }else{
                // var_dump($createDataSon['product_sn']);
                $product = self::batch_article_number($createDataSon['product_sn']);
                if(!$product){
                    $failData[] = $createDataSon;
                    continue;
                }

                // var_dump($product );exit;
                if($product['code'] != 200) {
                    $failData[]= $createDataSon; continue;
                }
                $data2 = [];
                foreach ($product['data'] as $pp){
                    if($pp['article_number'] == $createDataSon['product_sn']){
                        $data2 = $pp;
                    }
                }
                if (!$data2) {
                    $failData[] = $createDataSon;
                    continue;
                }
//                $data2 = $product['data'][0];
                $spu_id = $data2['spu_id'];
                $sku_id = self::seek_v($data2['skus'],$createDataSon['properties']);
                if(empty($sku_id ))
                {
                    $failData[]= $createDataSon; continue;
                }
            }
            $createDataSon['sku_id'] = $sku_id;
            $createDataSon['spu_id'] = $spu_id;
            // var_dump($createDataSon);
            $createData[] =$createDataSon;
            // exit;
        }
        $shipmentModel->insert($createData);
        // var_dump($createData);exit;
        return ['createData' => $createData, 'failData'=> $failData];
        // log_arr([$row,$e->getMessage()] ,'shipment');
    }

    /**
     *请求得物接口
     *
     * @param [type] $article_numbers
     */
    public function batch_article_number($article_numbers)
    {
        if(!$article_numbers) return [];
        //避免多次请求，加个缓存
        $key = 'dw:product_info:'.$article_numbers;
        $data = Redis::get($key);
        if($data){
            return json_decode($data,true);
        }
        $method = '2,2,apiUrl';
        $requestArr['article_numbers'] = [$article_numbers];
        $data =  (new DwApi($requestArr))->uniformRequest($method,$requestArr);
        Redis::setex($key,3600,$data);
        $arr  = json_decode($data,true);
        return  $arr;
    }

    /**
     * 数组找规格
     * @param [type] $data
     * @param [type] $v
     * @return void
     */
    public function seek_v($data,$v)
    {
        $arr2 = explode('-',$v);
        foreach($data as $_v){
            // $_v['properties'];
            $properties =json_decode($_v['properties'],true);
            $arr = array_values($properties);
            if(!array_diff($arr,$arr2) && !array_diff($arr2,$arr)){
                return $_v['sku_id'];
            }
            // var_dump($v);
            // var_dump( $properties);exit;
            if($properties['尺码'] == $v)
            {
                return $_v['sku_id'];
            }
        }
        return 0;
    }


}
