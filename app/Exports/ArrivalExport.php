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


class ArrivalExport implements WithHeadings, FromCollection, WithTitle, WithStyles, WithDefaultStyles, WithEvents
{
    use Exportable;

    private $data; //订单数据
    private $status = '采购单'; //sheet名称
    private $column; //总行数
    private $goodsNum = []; //一个订单的商品数量

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * 表头
     * @return string[]
     */
    public function headings(): array
    {
        return [
            array_pad(['采购单'], 18, ''),
            array_merge(array_pad(['基本信息'], 13, ''), array_pad(['产品明细'], 5, '')),
            [
                '单据编码', '付款状态', '供应商', '身份证号', '仓库', '预计到货日期', '下单人', '下单时间', '备注', '物流', '物流单号', '发货日期', '第三方单据编码',
                '货号', '规格', '数量', '采购价(¥)', '备注'
            ],
        ];
    }

    //默认样式

    public function defaultStyles(Style $defaultStyle)
    {
        return [
            'alignment' => [
                'horizontal' => 'center',
                'vertical' => 'center',
                'wrapText' => true,
            ],
        ];
    }
    /**
     * 导出
     * @return \Illuminate\Support\Collection|\Tightenco\Collect\Support\Collection
     */
    public function collection()
    {
        $data = $this->data;
        $all = [];
        $sup_list = [];
        $list = [];
        foreach ($data as $value) {
            
            $goods_details = $value['details_group'];
            $count = count($goods_details);
            $this->goodsNum[] = $count;

            foreach ($goods_details as  $item) {
                if(count($sup_list[$item['sup_id']]??[])>0){
                    $temp = [
                        //基本信息
                        'code' => '',
                        'pay_status' => '',
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
                }else{
                    $temp = [
                        //基本信息
                        'code' => '',
                        'pay_status' => '',
                        'supplier' => $item['supplier']['name'],
                        'supplier.id_card' => $item['supplier']['id_card'],
                        'warehouse' => $value['warehouse'],
                        'estimate_receive_time' => '',
                        'order_user' => '',
                        'order_at' => '',
                        'remark' => '',
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
                }
                $sup_list[$item['sup_id']][] = $temp;
            }
        }
        $list =[];
        foreach($sup_list as $sup_id=>$item){
            $list = array_merge($list,$item);
        }
        $all[] = $list;

        return collect($all);
    }
    /**
     * 创建sheet
     * @return array
     */
    // public function sheets(): array
    // {
    //     $list = $this->data;
    //     $sheets = [];
    //     foreach ($list as $key => $value) {
    //         $sheets[] = new self($value, $key);
    //     }

    //     return $sheets;
    // }

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
    public function styles(Worksheet $sheet)
    {
        $sheet->getDefaultRowDimension()->setRowHeight(22); //设置行高
        //表头合并单元格
        $sheet->mergeCells('A1:R1'); //合并单元格
        $sheet->mergeCells('A2:M2'); //合并单元格
        $sheet->mergeCells('N2:R2'); //合并单元格
        $sheet->getStyle('A1:Z1')->applyFromArray(['font' => ['bold' => true]]); //字体设置
        $sheet->getStyle('L2')->applyFromArray(['font' => ['color' => [
            'rgb' => 'ff0000',
        ]]]); //字体设置
        $sheet->getStyle('N3:Q3')->applyFromArray(['font' => ['color' => [
            'rgb' => 'ff0000',
        ]]]); //字体设置
        $sheet->getStyle('C3:E3')->applyFromArray(['font' => ['color' => [
            'rgb' => 'ff0000',
        ]]]); //字体设置



        // $cell = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K']; //需要合并的单元格
        //$sheet->mergeCells('A18:A22'); //合并单元格
        // foreach ($cell as $item) {
        //     $start = 4;
        //     foreach ($this->goodsNum as $key => $value) {
        //         $end = $start + $value -1;
        //         // dump($item . $start . ':' . $item . $end);
        //         $sheet->mergeCells($item . $start . ':' . $item . $end); //合并单元格
        //         $start = $end + 1;
        //     }
        // }
    }
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
