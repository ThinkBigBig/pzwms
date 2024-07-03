<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\SkuStock;
// use App\Models\Product;
use Maatwebsite\Excel\Concerns\ToModel;
// use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Facades\Hash;

class ProductImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
          $data =Product::importProduct($row);
    }
}
