<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class UniqCodePrintLog extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_unicode_print_log';

    public static function add($arr_id, $arr_code, $warehouse, $start, $end, $lot_num)
    {
        $user_id = request()->header('user_id');
        $username = request()->header('username');
        $dtime = date('Y-m-d H:i:s');
        $data = [
            'arr_id' => $arr_id,
            'arr_code' => $arr_code,
            'warehouse_name' => $warehouse,
            'uniq_num_start' => $start,
            'uniq_num_end' => $end,
            'created_at' => $dtime,
            'created_user' => $user_id,
            'cre_user_name' => $username,
            'tenant_id' => request()->header('tenant_id')
        ];

        $res = WmsOptionLog::add(1, $arr_code, '打印唯一码', '打印唯一码', $data);
        if (!$res) return false;
        unset($data['uniq_num_start'], $data['uniq_num_end']);

        for ($i = $start; $i < $end; $i++) {
            $insert[] = array_merge($data, [
                'uniq_code' => $lot_num . '-' . $i,
                'print_count' => 1,
                'updated_user' => $user_id,
                'upd_user_name' => $username,
                'updated_at' => $dtime
            ]);
            $print[] = [
                'arr_id' => $arr_id,
                'arr_code' => $arr_code,
                'warehouse_name' => $warehouse,
                'lot_num' => $lot_num,
                'uniq_code' => $lot_num . '-' . $i,
            ];
        }
        $pres = DB::table('wms_unicode_print_log')->insert($insert);
        if (!$pres) return false;
        return $print;
    }

    //再次打印
    public function rePrint($arr_id, $uniq_code)
    {
        $user_id = request()->header('user_id');
        $username = request()->header('username');
        $item = $this::where('arr_id', $arr_id)->where('uniq_code', $uniq_code)->get();
        if ($item->count() != 1) return [false, '唯一码不存在或有重复'];
        $item = $item->first();
        $item->print_count += 1;
        $item->upd_user_name = $username;
        $item->updated_user = $user_id;
        $row = $item->save();
        $print = [
            'arr_id' => $arr_id,
            'arr_code' => $item->arr_code,
            'warehouse_name' => $item->warehouse_name,
            'lot_num' => substr($item->uniq_code, 0, 9),
            'uniq_code' => $item->uniq_code,
        ];
        return [$row, $print];
    }

    //判断唯一码是否有效
    public static function isUniqCode($uniq_code, $arr_id)
    {
        $tenant_id = request()->header('tenant_id');
        return DB::table('wms_unicode_print_log')->where('tenant_id', $tenant_id)->where('arr_id', $arr_id)->where('uniq_code', $uniq_code)->where('bar_code', '')->exists();
    }

    //唯一码绑定条形码
    public static function bindBarCode($uniq_code, $arr_id, $bar_code, $retrun = false)
    {
        if ($retrun) {
            // return true;
            $uniq_item = self::where('uniq_code', $uniq_code);
            if ($uniq_item->doesntExist()) {
                return self::where('uniq_code', $uniq_code)->insert([
                    'uniq_code' => $uniq_code,
                    'bar_code' => $bar_code,
                    'arr_id' => $arr_id,
                    'created_user' => request()->header('user_id'),
                    'updated_user' => request()->header('user_id'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }else{
                return self::where('uniq_code',$uniq_code)->lockForUpdate()->update([
                    // 'bar_code'=>$bar_code,
                    'arr_id' => $arr_id,
                    'updated_user' => request()->header('user_id'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
        } else {
            return self::where('arr_id', $arr_id)->where('uniq_code', $uniq_code)->lockForUpdate()->update(['bar_code' => $bar_code, 'updated_user' => request()->header('user_id'), 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    //唯一码解绑条形码
    public static function unBbindBarCode($uniq_codes = [], $arr_id, $bar_code, $is_return = false)
    {
        if ($is_return) {
            $row =  WithdrawUniqLog::whereIn('uniq_code', $uniq_codes)->where('bar_code', $bar_code)->where('is_scan', 1)->update(['is_scan' => 0]);
        } else {
            $row = self::whereIn('uniq_code', $uniq_codes)->where('arr_id', $arr_id)->where('bar_code', $bar_code)->update(['bar_code' => '', 'updated_user' => request()->header('user_id'), 'updated_at' => date('Y-m-d H:i:s')]);
        }
        return $row;
    }

    function arrivalRegist()
    {
        return $this->hasOne(ArrivalRegist::class, 'id', 'arr_id');
    }

    public function withSearch($select)
    {
        $permission = ADMIN_INFO['data_permission'];
        $warehouse_codes = $permission['warehouse_name'] ?? [];
        if ($warehouse_codes) {
            return $this::whereIn('warehouse_name', $warehouse_codes)->select($select);
        }

        return $this::select($select);
    }
}
