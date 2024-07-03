<?php

namespace App\Logics\wms;

use App\Handlers\ESApiService;
use App\Logics\BaseLogic;
use App\Models\Admin\V2\Product as V2Product;
use App\Models\Admin\V2\ProductSpecAndBar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use function PHPUnit\Framework\returnSelf;

class Product extends BaseLogic
{
    function imgSync($params)
    {
        $page = 1;
        while (true) {
            $p = ['pageNum' => $page, 'pageSize' => 500, 'debug' => 1, 'mode' => 1, 'sort' => 0];
            $data = ESApiService::serach($p)['data'] ?? [];
            // Log::info('search', $p);
            if (!$data) break;
            foreach ($data as $item) {
                $pic = $item['pic'] ?? '';
                if (!$pic || !$item['product_sn']) continue;
                if ($item['product_sn'] == '-') continue;

                // echo $item['product_sn'], $pic, $item['id'], PHP_EOL;
                V2Product::where('product_sn', $item['product_sn'])
                    ->where('img', '')
                    ->update(['img' => $pic,]);
            }
            $page++;
        }
    }

    // 检查是否存在新条码
    static function hasNewBarCode($bar_codes, $type = 0)
    {
        $tenant_id = request()->header('tenant_id', 0);
        $bars = DB::table('wms_spec_and_bar as bar')
            ->join('wms_product as p', 'bar.product_id', '=', 'p.id')
            ->where(['bar.tenant_id' => $tenant_id, 'p.tenant_id' => $tenant_id, 'p.status' => 1])
            ->whereIn('bar.type', [0, $type])
            ->whereIn('bar.bar_code', $bar_codes)->selectRaw("bar.*")->get();
        $bars = objectToArray($bars);
        if (count($bars) != count($bar_codes)) return true;
        return false;
    }

    // 更新条码类型
    static function bindBarcodeType($ids, $type)
    {
        ProductSpecAndBar::where('type', 0)->whereIn('id', $ids)->update(['type' => $type]);
    }
}
