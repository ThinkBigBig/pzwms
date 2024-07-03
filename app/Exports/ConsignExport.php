<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithTitle; //sheet名称
use Maatwebsite\Excel\Concerns\WithStyles; //造型
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Style;
use Maatwebsite\Excel\Concerns\WithDefaultStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;


class ConsignExport extends DefaultStylesExport
{
    use Exportable;

    protected $data; //订单数据
    private $status = '寄卖单'; //sheet名称
    private $goodsNum = []; //一个订单的商品数量
    private $column; //总行数

    /**
     * 导出
     * @return \Illuminate\Support\Collection|\Tightenco\Collect\Support\Collection
     */
    public function collection()
    {
       ;
        $data = $this->data;
        $all = [];
        foreach ($data as $value) {
            $list = [];
            $goods_details = $value['details_group'];
            $count = count($goods_details);
            $this->goodsNum[] = $count;
            $sup_list = [];
            foreach ($goods_details as   $item) {
                if (in_array($item['sup_id'], $sup_list)) {
                    $temp = [
                        //基本信息
                        'code' => '',
                        'supplier' => '',
                        'supplier.id_card' => '',
                        'warehouse' => '',
                        'estimate_receive_time' => '',
                        'order_user' => '',
                        'order_at' => '',
                        'remark' => '',
                        'log_prod_code' => '',
                        'deliver_no' => '',
                        'send_at' => '',
                        'third_code' => '',
                        //明细
                        'product_sn'        => $item['product']['product']['product_sn'],
                        'spec'       => $item['product']['spec_one'],
                        'num'      => $item['count'],
                        'buy_price'         => '',
                        'd_remark'      => '',
                    ];
                } else {
                    $temp = [
                        //基本信息
                        'code' => '',
                        'supplier' => $item['supplier']['name'],
                        'supplier.id_card' => $item['supplier']['id_card'],
                        'warehouse' => $value['warehouse'],
                        'estimate_receive_time' => '',
                        'order_user' => '',
                        'order_at' => '',
                        'remark' => $value['remark'],
                        'log_prod_code' => $value['log_product_code'],
                        'deliver_no' => $value['log_number'],
                        'send_at' => '',
                        'third_code' => $value['arr_code'],
                        //明细
                        'product_sn'        => $item['product']['product']['product_sn'],
                        'spec'       => $item['product']['spec_one'],
                        'num'      => $item['count'],
                        'buy_price'         => '',
                        'd_remark'      => '',
                    ];
                    $sup_list[] = $item['sup_id'];
                }
                $list[] = $temp;
            }
            $this->column = count($list);
            $all[] = $list;
        }


        return collect($all);
    }


    /**
     * sheet名称
     * @return string
     */
    public function title(): string
    {
        return $this->status;
    }

    /**
     * 样式设置
     * @param Worksheet $sheet
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $cellRange = 'A:Z'; // 假设列B需要设置成文本格式
                $event->sheet->getDelegate()->getStyle($cellRange)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            },
        ];
    }
}
