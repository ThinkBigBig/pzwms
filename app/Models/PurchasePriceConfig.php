<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchasePriceConfig extends Model
{
    public $table = 'purchase_price_config';

    use HasFactory;
    protected $guarded = [];

    const INACTIVE = 0;
    const ACTIVE = 1;

    static function getFormula($price, $product_sn = '')
    {
        $config = PurchasePriceConfig::where('status', PurchasePriceConfig::ACTIVE)
            ->where('product_sn', $product_sn)
            ->where('minimum', '<', $price)
            ->where('maximum', '>=', $price)->first();

        if ($product_sn && (!$config)) {
            return self::getFormula($price, '');
        }
        return $config ? $config->formula : '%d*1.2';
    }

    static function calculate($origin, $formula = '', $product_sn = '')
    {
        if (!$formula) {
            $formula = self::getFormula($origin, $product_sn);
        }
        $f = sprintf($formula, $origin);
        $num = eval("return $f;");
        // 防止出现10010 12001之类的后两位不是00的数据，这里要处理下
        return intval($num / 100) * 100;
    }
}
