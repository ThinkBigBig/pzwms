<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles; //造型

class Test extends DefaultStylesExport implements FromCollection
{
    private $row;
    // private $data;

    public function __construct($row, $data, $params = [])
    {
        // dd($row,$data,$params);
        parent::__construct($data,$params);
    }


}
