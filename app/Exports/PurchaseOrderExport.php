<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class PurchaseOrderExport implements WithStyles,WithHeadings
{
    private $row;
    private $data;

    public function __construct($row, $data, $params = [])
    {
        $this->row = $row;
        $this->data = $data;
        // $this->title = $params['title'] ?? '';
    }
    public function headings(): array
    {
        return [
            array_pad(array_pad(['采购单'],-17,''),34,''),
            array_merge(array_values($this->row[0]),array_pad(array_pad(['产品明细'],-7,''),13,'')),
            array_merge(array_values($this->row[0]),['sku编码','货号','品名','规格','数量','采购价','采购额','已收总数','已收正品','已收瑕疵','待收总数','备注']),
        ];
    }

    /**
     * 样式设置
     * @param Worksheet $sheet
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function styles(Worksheet $sheet)
    {
        //合并单元格
        $sheet->mergeCells('A1:AG1');
        $sheet->getStyle('A1:AG1')->getAlignment()->setVertical('center');//垂直居中
        $sheet->getStyle('A1:AG1')->applyFromArray(['alignment' => ['horizontal' => 'center']]);//设置水平居中
        $sheet->getStyle('A1:AG3')->applyFromArray(['font' => ['bold' => true]]);//字体设置

        $sheet->mergeCells('U2:AG2');
        $sheet->mergeCells('A2:A3');
        $sheet->mergeCells('A2:A3');
        $sheet->mergeCells('A2:A3');
        $sheet->mergeCells('A2:A3');
        // $sheet->mergeCells('J1:L1');
        // $sheet->mergeCells('M1:N1');
        // $sheet->mergeCells('O1:Q1');
        $sheet->getDefaultRowDimension()->setRowHeight(22);//设置默认行高
        $sheet->getRowDimension('1')->setRowHeight(25);//设置指定行高
        $sheet->getRowDimension('2')->setRowHeight(50);//设置指定行高
        // $sheet->getCell('B2');//设置换行
        // $sheet->getStyle('B2:Q2')->getAlignment()->setWrapText(true);
    }

    // function title(): string
    // {
    //     return $this->title;
    // }
    
}
