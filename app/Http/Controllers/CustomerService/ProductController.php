<?php

namespace App\Http\Controllers\CustomerService;

use App\Http\Controllers\Controller;
use App\Logics\customerService\Product;
use \Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // 商品信息
    function product(Request $request)
    {
        $logic = new Product();
        $data = $logic->products($request->all());
        return $this->output($logic, $data);
    }

    // 库存信息
    function stock(Request $request)
    {
        $logic = new Product();
        $data = $logic->stock($request->all());
        return $this->output($logic, $data);
    }

    // 出入库明细
    function detail(Request $request)
    {
        $logic = new Product();
        $data = $logic->detail($request->all());
        return $this->output($logic, $data);
    }
}
