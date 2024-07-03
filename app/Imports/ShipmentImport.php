<?php

namespace App\Imports;

use App\Models\Shipment;
use Maatwebsite\Excel\Concerns\ToModel;
// use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Facades\Hash;

class ShipmentImport implements ToModel
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
        // return $data;
    }
}
