<?php

namespace App\Imports;

use App\Models\SizeStandard;
use Maatwebsite\Excel\Concerns\ToModel;

class SizeStandardImport implements ToModel
{
    private $params;
    public function __construct($params = [])
    {
        $this->params = $params;
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        if ($row[0] == '品牌') return;

        if ($row[0]) {
            $where = ['brand' => $row[0], 'product_sn' => '', 'size_us' => $row[4],];
        }
        if ($row[1]) {
            $where = ['brand' => '', 'product_sn' => $row[1], 'size_us' => $row[4],];
        }
        $maps = [
            '男款' => 1,
            '女款' => 2,
            '幼童' => 3,
            '婴童' => 4,
        ];
        if ($row[2]) {
            $where['gender'] = $maps[$row[2]];
        }

        $update = [
            'size_fr' => $row[5],
            'cm' => $row[6] ? $row[6] : 0,
            'size_eu' => $row[3],
        ];
        SizeStandard::updateOrCreate($where, $update);
    }
}
