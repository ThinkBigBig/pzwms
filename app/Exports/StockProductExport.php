<?php

namespace App\Exports;

use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class StockProductExport implements FromCollection, WithEvents
{
    private $row;
    private $data;
    private $params;

    public function __construct($row, $data, $params = [])
    {
        $this->row = $row;
        $this->data = $data;
        $this->params = $params;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $row = $this->row;
        $data = $this->data;

        //设置表头
        foreach ($row[0] as $key => $value) {
            $key_arr[] = $key;
        }

        //输入数据
        foreach ($data as $key => &$value) {
            $js = [];
            for ($i = 0; $i < count($key_arr); $i++) {
                $js = array_merge($js, [$key_arr[$i] => $value[$key_arr[$i]]]);
            }
            array_push($row, $js);
            unset($val);
        }
        return collect($row);
    }

    /**
     * 注册事件
     * @return array
     */
    public function registerEvents(): array
    {
        $color = [
            'font' => [
                'name' => 'Arial',
                'bold' => true,
                'italic' => false,
                'strikethrough' => false,
                'color' => [
                    'rgb' => 'FF0000'
                ]
            ],
        ];

        return [
            AfterSheet::class => function (AfterSheet $event) use ($color) {
                $rows_line = $event->sheet->getHighestRow();
                for ($i = 1; $i <= $rows_line; $i++) {
                    if ($i == 1) {
                        continue;
                    }

                    $line = $i > 2 ? $i - 2 : 0;
                    $row =  $this->data[$line] ?? [];
                    if ($this->params['detail'] ?? 0) {
                        $dw = $row['dw_threshold_price'] ?? 0;
                        $goat = $row['goat_threshold_price'] ?? 0;
                        $stockx = $row['stockx_threshold_price'] ?? 0;
                        $carryme = $row['carryme_threshold_price'] ?? 0;
                        $finnal_price = $row['finnal_price'] ?? 0;
                        $limit = 20;

                        if (abs($goat - round($finnal_price * 1.1 + 750)) > $limit && intval($goat) > 0) {
                            //设置区域单元格字体、颜色、背景等，其他设置请查看 applyFromArray 方法，提供了注释
                            $event->sheet->getDelegate()->getStyle("J$i")->applyFromArray($color);
                        } else {
                            $event->sheet->setCellValue("J$i", "=I$i*1.1+750");
                        }

                        if (abs($dw - round($finnal_price * 1.04 + 2000)) > $limit && intval($dw) > 0) {
                            $event->sheet->getDelegate()->getStyle("L$i")->applyFromArray($color);
                        } else {
                            $event->sheet->setCellValue("L$i", "=I$i*1.04+2000");
                        }

                        if (abs($stockx - round($this->params['stockx_formula2']($finnal_price))) > $limit && intval($stockx) > 0) {
                            $event->sheet->getDelegate()->getStyle("N$i")->applyFromArray($color);
                        } else {
                            $event->sheet->setCellValue("N$i", $this->params['stockx_formula']($i));
                        }

                        if (abs($carryme - round($finnal_price * 1.05)) > $limit && intval($carryme) > 0) {
                            $event->sheet->getDelegate()->getStyle("P$i")->applyFromArray($color);
                        } else {
                            $event->sheet->setCellValue("P$i", "=I$i*1.05");
                        }

                    } else {
                        $event->sheet->setCellValue("I$i", "=H$i*1.1+750");
                        $event->sheet->setCellValue("J$i", "=H$i*1.04+2000");
                        $event->sheet->setCellValue("K$i", $this->params['stockx_formula']($i));
                        $event->sheet->setCellValue("L$i", "=H$i*1.05");
                    }
                }
            }
        ];
    }
}
