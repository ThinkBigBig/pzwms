<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithTitle; //sheet名称
use Maatwebsite\Excel\Concerns\WithStyles; //造型
use NunoMaduro\Collision\Provider;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Style;
use Maatwebsite\Excel\Concerns\WithDefaultStyles;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;


class DefaultStylesExport extends DefaultValueBinder implements WithHeadings, FromCollection, WithTitle, WithStyles, WithDefaultStyles, WithCustomValueBinder
{
    use Exportable;

    protected $data; //订单数据
    protected $title = 'Sheet1'; //sheet名称
    protected $params = [];
    protected $head = [];
    // protected $column = 0; //当前行
    // protected $field = 0;//当前列
    protected $field_count = 0; //列数量
    protected $detail = []; //商品详情
    protected $headers = [];

    public function __construct($data, $params)
    {
        $this->data = $data;
        $this->params = $params;
        $this->title = $params['name'];
        if (!empty($params['detail']['field'])) {
            $this->field_count = count($params['field']) + count($params['detail']['field']);
            $this->head = array_merge(array_values($params['field']), array_values($params['detail']['field']));
        } else {
            $this->head = array_values($params['field']);
            $this->field_count = count($this->head);
        }
    }


    /**
     * 表头
     * @return string[]
     */
    public function headings(): array
    {
        $count = $this->field_count;
        $heads[] = array_pad([$this->title], $count, '');
        if (!empty($this->params['detail']['name'])) {
            if (!empty($this->params['detail']['order_name'])) {
                $heads[] = array_merge(
                    array_pad([$this->params['detail']['order_name']], count($this->params['field']), ''),
                    array_pad([$this->params['detail']['name']], count($this->params['detail']['field']), '')
                );
            } else {
                $heads[] = array_merge(
                    array_pad([], count($this->params['field']), ''),
                    array_pad([$this->params['detail']['name']], count($this->params['detail']['field']), '')
                );
            }
        }
        $heads[] = $this->head;
        $this->headers = $heads;
        return $heads;
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

            'font' => [
                'name' => '宋体 (正文)',
                'size' => '11',
                'bold' => false,
                'italic' => false,
                // 'underline' => Font::UNDERLINE_DOUBLE,
                'strikethrough' => false,
                'color' => [
                    'rgb' => '000000'
                ]
            ],
            'width' => 20,
        ];
    }

    /**
     * 导出
     * @return \Illuminate\Support\Collection|\Tightenco\Collect\Support\Collection
     */
    public function collection()
    {
        $data = $this->data;
        $list = [];
        $temp = [];
        $order = [];
        foreach ($data as $value) {
            //存在明细数据
            if (!empty($this->params['detail']['field'])) {
                $details = $value[$this->params['detail']['with_as']];
                $num = count($details);
                if ($num == 0) {
                    //不存在明细
                    $temp = [];
                    foreach (array_keys($this->params['field']) as $field) {
                        $this->relation($field, $value, $temp);
                        // $temp[] = empty($value[$field]) ? '' : $value[$field];
                    }
                    $list[] = $temp;
                    continue;
                }
                //列表数据
                foreach ($this->params['field'] as $ok => $ov) {
                    $this->relation($ok, $value, $o_temp);
                }
                $order[$value['id']] = $o_temp;
                $o_temp = [];
                $count = 0;
                foreach ($details as   $item) {
                    //详情数据
                    $temp = [];
                    $d_temp = [];
                    // dd($this->params['detail']['field'], $value);
                    foreach ($this->params['detail']['field'] as $dk => $dv) {
                        $this->relation($dk, $item, $d_temp);
                    }
                    if ($count == 0) {
                        //首条详情
                        $temp = array_merge($order[$value['id']], $d_temp);
                    } else {
                        //非首条
                        $temp = array_merge(array_fill(0, count($this->params['field']), ''), $d_temp);
                    }
                    $count += 1;
                    $list[] = $temp;
                    // dd($d_temp);
                }
            } else {
                //不存在明细
                $temp = [];
                foreach (array_keys($this->params['field']) as $field) {
                    $this->relation($field, $value, $temp);
                    // $temp[] = empty($value[$field]) ? '' : $value[$field];
                }
                $list[] = $temp;
            }
            // $this->column += count($list);

        }
        // dd($list);
        return collect($list);
    }

    /**
     * sheet名称
     * @return string
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * 样式设置
     * @param Worksheet $sheet
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function styles(Worksheet $sheet)
    {
        $sheet->getDefaultRowDimension()->setRowHeight(22); //设置行高
        $header_count = count($this->headers);
        foreach ($this->headers as $key => $value) {
            $row_start = $key + 1;
            //首行
            if ($key == 0) {
                $sheet->mergeCellsByColumnAndRow(1, $row_start, $this->field_count, $row_start);
                $sheet->getStyleByColumnAndRow(1, $row_start, $this->field_count, $row_start)->applyFromArray([
                    'font' => ['bold' => true]
                ]);
                continue;
            }

            $field_starts = [];
            $fields = [];

            foreach ($value as $f => $v) {
                $field = $f + 1;
                //最后一行
                if ($header_count == $row_start) {
                    if (!empty(($this->params['format'][$v]))) {
                        $format = $this->params['format'][$v];
                        if (isset($format['color']) && $format['color'] == 'red') {;
                            $sheet->getStyleByColumnAndRow($field, $row_start)->applyFromArray([
                                'font' => ['size' => 12, 'color' => ['rgb' => 'ff0000']]
                            ]);
                        }
                    }
                }
                $sheet->getStyleByColumnAndRow($field, $row_start)->applyFromArray([
                    'font' => ['color' => ['size' => '12']]
                ]);
                if ($v == '') {
                    $field_starts[] = $f;
                } else {
                    if (!empty($field_starts)) $fields[] = $field_starts;
                    $field_starts = [];
                }
                //最后一列
                if ($f == count($this->head) - 1) {
                    if (!empty($field_starts)) $fields[] = $field_starts;
                }
            }
            //表头合并单元格
            if ($fields) {
                foreach ($fields as $v) {
                    if ($v[0] == 0) {
                        foreach ($v as $merg_top) {
                            //向上合并
                            $coord = $sheet->getCellByColumnAndRow($merg_top + 1, $row_start + 1)->getCoordinate();
                            $c_value = $sheet->getCell($coord)->getValue();
                            // $c_style = $sheet->getStyle($coord)->getFont()->exportArray();
                            $t_coord = $sheet->getCellByColumnAndRow($merg_top + 1, $row_start)->getCoordinate();
                            $sheet->getCell($t_coord)->setValue($c_value);
                            // $sheet->getStyle($t_coord)->applyFromArray($c_style);
                            $sheet->mergeCells($coord . ':' . $t_coord);
                            if (!empty(($this->params['format'][$c_value]))) {
                                $format = $this->params['format'][$c_value];
                                if (isset($format['color']) && $format['color'] == 'red') {;
                                    $sheet->getStyle($t_coord . ':' . $coord)->applyFromArray([
                                        'font' => ['size' => 12, 'color' => ['rgb' => 'ff0000']]
                                    ]);
                                }
                            }
                        }
                    } else {
                        //向右合并
                        $end = array_pop($v);
                        $sheet->mergeCellsByColumnAndRow($v[0], $row_start, $end + 1, $row_start);
                    }
                }
            }
        }
    }


    protected function relation($key, $value, &$return)
    {
        if (strpos($key, '.') !== false) {
            $relation = explode('.', $key);
            $r_count = count($relation);
            $rd_key = '';
            $r_value = $value;
            foreach ($relation as $r_k => $r_name) {
                if (!empty($r_value[$r_name])) {
                    $rd_key = $r_name;
                    if ($r_count == $r_k + 1) {
                        $return[] = (string) $r_value[$r_name];
                        break;
                    } else {
                        $r_value = $r_value[$r_name];
                        continue;
                    }
                } else {
                    $return[] = '';
                    break;
                }
            }
        } else {
            $return[] = isset($value[$key]) && $value[$key] !== null ?  (string)$value[$key] : '';
        }
    }
}
