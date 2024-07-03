<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToModel;
use Illuminate\Support\Facades\Hash;

class ShipmentFlawImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        // var_dump($row);exit;
            // $data = Shipment::import($row);
        // var_dump($data);exit;
        //    return $data;
    }
}
