<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class ExcelExport implements FromCollection
{
	// 外部调用传入数据，用来实现多次调用，也可在该文件中直接设置
    private $headers; // 表头
    private $data; // 数据
    private $column_widths; // 自定义列宽

    // 数据注入
    public function __construct($headers=[], $data=[], $column_widths = []) {
        $this->headers = $headers;
        $this->data = $data;
        $this->column_widths = $column_widths;
    }
    // 自定义表头，需实现withHeadings接口
    public function headings(): array
    {
        return $this->headers;
    }

    // 列宽，实现WithColumnWidths接口
    public function columnWidths(): array {
        return $this->column_widths;
    }

    // 数据
    public function collection()
    {
        return $this->data;
    }
}

