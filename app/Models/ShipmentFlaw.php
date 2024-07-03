<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;
use App\Handlers\DwApi;

class ShipmentFlaw extends BaseModel
{
    public $table = 'pms_shipment_flaw';

    const CREATEDTIME = 'createtime';
    const UPDATEDTIME = 'updatetime';

    /**
     * 导入数据
     *
     * @return array
     */
    public function import($data)
    {

        $createData = [];
        $failData   = [];
        $ks = 99999999;
        foreach($data as $k => $row){
            if($row[0] == '发货单号') continue;
            if($row[0] == NULL)
            {
                if(!empty($createData[$ks])){
                    $createData[$ks]['failed_reason_url'] = $createData[$ks]['failed_reason_url'].','.$row[10];
                }
                continue;
            }else{
                $createDataSon = [
                    'invoice_no'                =>  $row[0],//发货单号
                    'objective'                 =>  $row[1],//目的仓
                    'grouping'                  =>  $row[2],//入仓分组
                    'stock_no'                  =>  $row[3],//备货单号
                    'good_name'                 =>  $row[4],//商品名称
                    'product_sn'                =>  $row[5],//商品货号
                    'properties'                =>  $row[6],//规格
                    'bar_code'                  =>  $row[7],//条码
                    'unique_code'               =>  $row[8],//唯一码
                    'failed_reason'             =>  $row[9],//瑕疵
                    'failed_reason_url'         =>  $row[10],//瑕疵图片
                ];
                $ks = $k;
            }
            $where = [['invoice_no','=', $createDataSon['invoice_no']],
                    ['stock_no' , '=', $createDataSon['stock_no'] ],
                    ['product_sn' ,'=' , $createDataSon['product_sn']],
                    ['properties' ,'=' , $createDataSon['properties']],
            ];
            $shipmentModel = (new ShipmentFlaw);
            $shipmentData  = $shipmentModel->BaseOne($where);//查表里是否已经被导入
            if(count($shipmentData)>0) {
                $failData[]= $createDataSon; continue;
            }
            $createData[$ks] =$createDataSon;
        }
        $shipmentModel->insert($createData);
        // var_dump($failData);exit;
        // var_dump($createData);exit;
        return ['createData' => $createData, 'failData'=> $failData];
    }


}
