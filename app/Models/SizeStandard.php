<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SizeStandard extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $table = 'size_standard';

    const GENDER_MEN = 1; //男性
    const GENDER_WOMEN = 2; //女性
    const GENDER_YOUTH = 3; //青少年
    const GENDER_INFANT = 4; //婴儿


    static public function getGender($gender_name)
    {
        $maps = [
            'men' => self::GENDER_MEN,
            'women' => self::GENDER_WOMEN,
            'youth' => self::GENDER_YOUTH,
            'infant' => self::GENDER_INFANT,
        ];
        return $maps[$gender_name] ?? 0;
    }


    /**
     * 根据美码获取对应的欧码
     *
     * @param string $size_us
     * @param string $gender
     * @param string $brand
     * @param string $product_sn
     */
    static function sizeEu($size_us, $gender, $brand, $product_sn = '')
    {
        if ($product_sn) {
            $where = [
                'product_sn' => $product_sn,
                'size_us' => $size_us
            ];
            $size = SizeStandard::where($where)->value('size_eu');
            if ($size) return $size;

            // 有自定义的尺码规则，但是没找到对应的尺码
            $exist = SizeStandard::where(['product_sn' => $product_sn])->first();
            if ($exist && !$size) return '';
        }

        $gender = self::getGender($gender);
        $where = [
            'gender' => $gender,
            'brand' => $brand,
            'size_us' => $size_us
        ];

        $size = SizeStandard::where($where)->value('size_eu');
        if (!$size) {
            $where['brand'] = 'default';
            return SizeStandard::where($where)->value('size_eu');
        }
        return $size;
    }

    /**
     * 根据美码获取对应的欧码
     *
     * @param string $size_us
     * @param string $gender
     * @param string $brand
     * @param string $product_sn
     */
    static function sizeInfo($size_us, $gender, $brand, $product_sn = '')
    {
        if ($product_sn) {
            $where = [
                'product_sn' => $product_sn,
                'size_us' => $size_us
            ];
            $size = SizeStandard::where($where)->first();
            if ($size) {
                if (!$size->size_fr) $size->size_fr = $size->size_eu;
                return $size;
            }

            // 有自定义的尺码规则，但是没找到对应的尺码
            $exist = SizeStandard::where(['product_sn' => $product_sn])->first();
            if ($exist && !$size) return null;
        }

        $gender = self::getGender($gender);
        $where = [
            'gender' => $gender,
            'brand' => $brand,
            'size_us' => $size_us
        ];

        $size = SizeStandard::where($where)->first();
        if (!$size) {
            $where['brand'] = 'default';
            $size = SizeStandard::where($where)->first();
        }

        if ($size && (!$size->size_fr)) $size->size_fr = $size->size_eu;
        return $size;
    }

    /**
     * 根据欧码获取对应的法码
     *
     * @param string $size_eu
     * @param string $gender
     * @param string $brand
     * @param string $product_sn
     */
    static function getSizeFr($size_eu, $gender, $brand, $product_sn = '')
    {
        $maps = [
            'adidas originals' => 'adidas',
            'adidas neo' => 'adidas',
        ];
        $brand = $maps[$brand] ?? $brand;
        $sub_where = function ($query) use ($size_eu) {
            $query->where('size_eu', $size_eu)->orWhere('size_fr', $size_eu);
        };
        if ($product_sn) {
            // 传入的尺码是38.5 或 38⅔ 形式的
            $where = ['product_sn' => $product_sn, 'size_eu' => $size_eu];
            $size = SizeStandard::where($where)->where($sub_where)->first();
            if ($size) {
                if (!$size->size_fr) $size->size_fr = $size->size_eu;
                return $size;
            }

            // 有自定义的尺码规则，但是没找到对应的尺码
            $exist = SizeStandard::where(['product_sn' => $product_sn])->first();
            if ($exist && !$size) return null;
        }

        $gender = self::getGender($gender);
        $where = ['gender' => $gender, 'brand' => $brand];

        $size = SizeStandard::where($where)->where($sub_where)->first();
        if (!$size) {
            $where['brand'] = 'default';
            $size = SizeStandard::where($where)->where($sub_where)->first();
        }

        if ($size && (!$size->size_fr)) $size->size_fr = $size->size_eu;
        return $size;
    }
}
