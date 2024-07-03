<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * 支持多表格导出
 */
class ExportSheets implements WithMultipleSheets
{
    use Exportable;
    private $data;


    public function __construct($data, $params = [])
    {
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets = [];
        foreach ($this->data as $title => $val) {
            $sheets[] = new Sheet($val['headers'], $val['data'], ['title' => $title]);
        }
        return $sheets;
    }
}
