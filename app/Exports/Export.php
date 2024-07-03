<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;

class Export implements FromCollection
{
    private $row;
    private $data;

    public function __construct($row, $data, $params = [])
    {
        $this->row = $row;
        $this->data = $data;
    }

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
                if (strpos($key_arr[$i], '.') !== false) {
                    $relation = explode('.', $key_arr[$i]);
                    if (count($relation) == 2) {
                        $field_v = isset($value[$relation[0]][$relation[1]])?$value[$relation[0]][$relation[1]]:'';
                    }
                    if (count($relation) == 3) {
                        $field_v = isset($value[$relation[0]][$relation[1]][$relation[2]])?$value[$relation[0]][$relation[1]][$relation[2]]:'';
                    }
                    if (count($relation) > 3) {
                        abort('数据有误');
                    }
                    $js = array_merge($js, [$key_arr[$i] => $field_v]);
                } else {
                    if (strpos($key_arr[$i], '#') !== false ) {
                        $js=  array_merge($js, [$key_arr[$i] => explode('#', $key_arr[$i])[1]]);
                    }else{
                        $js = array_merge($js, [$key_arr[$i] => $value[$key_arr[$i]]]);

                    }
                }
            }
            array_push($row, $js);
            unset($val);
        }
        // var_dump($row);exit;
        return collect($row);
    }
}
