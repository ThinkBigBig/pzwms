<?php

namespace App\Imports;

use App\Models\StockProductLog;
use Exception;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithColumnLimit;

use function PHPUnit\Framework\callback;

class StockProductImport implements ToModel, WithCalculatedFormulas, WithColumnLimit
{
    public $admin_user_id;
    public $batch_no = '';
    public $fail_num = 0;
    public $success_num = 0;
    public $header = [];
    public $params;
    public function __construct($params)
    {
        $this->admin_user_id = $params['admin_user_id'];
        $this->batch_no = $params['batch_no'];
        $this->header = ['商品名', '货号', '规格', '在仓库存', '在售库存', '所在仓库', '加权成本', '到手价', 'goat门槛价', '得物门槛价', 'stockx门槛价', 'carryme门槛价', '商品条码', '仓库编号',    '上下架', '空卖名称', '空卖链接'];
        $this->params = $params;
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        if (count($row) != 17) {
            throw new Exception('文件内容有误1');
        }

        if ($row[0] == '商品名') {
            if (implode(',', $row) != implode(',', $this->header)) {
                throw new Exception('文件内容有误2');
            }
            return;
        }
        if (is_null($row[0]) || is_null($row[1]) || is_null($row[2]) || is_null($row[3]) || is_null($row[4]) || is_null($row[5]) || is_null($row[6]) || is_null($row[7]) || is_null($row[8]) || is_null($row[9]) || is_null($row[10]) || is_null($row[11]) || is_null($row[12]) || is_null($row[13]) || is_null($row[14])) {
            $this->fail_num++;
            return;
        }

        if ($row[4] > $row[3]) {
            $this->fail_num++;
            return;
        }
        if (is_null($row[15])) $row[15] = '';
        if (is_null($row[16])) $row[16] = '';

        $this->success_num++;
        $res =  new StockProductLog([
            'good_name' => $row[0],
            'product_sn' => $row[1],
            'properties' => $row[2],
            'store_stock' => $row[3],
            'stock' => $row[4],
            'store_house_name' => $row[5],
            'cost_price' => $row[6],
            'finnal_price' => $row[7],
            'goat_threshold_price' => round($row[8]),
            'dw_threshold_price' => round($row[9]),
            'stockx_threshold_price' => round($row[10]),
            'carryme_threshold_price' => round($row[11]),
            'bar_code' => $row[12],
            'store_house_code' => $row[13],
            'status' => intval($row[14]),
            'admin_user_id' => $this->admin_user_id,
            'batch_no' => $this->batch_no,
            'purchase_name' => $row[15],
            'purchase_url' => $row[16],
        ]);
        return $res;
    }

    //批量导入1000条
    public function batchSize()
    {
        return 1000;
    }

    //以1000条为基准切割数据
    public function chunkSize()
    {
        return 1000;
    }

    public function endColumn(): string
    {
        //从A读取到O列,后面的列不再读取
        return 'Q';
    }
}
