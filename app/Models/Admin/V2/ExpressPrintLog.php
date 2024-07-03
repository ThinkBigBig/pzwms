<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use  App\Models\Admin\V2\ArrivalRegist;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;


class ExpressPrintLog extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_express_print_log';

    protected $fillable = ['print_count','updated_user','upd_user_name'];

    public static  function add($ship_code,$deliver_no){
        $item = self::where('ship_code',$ship_code)->first();
        $user_id = request()->header('user_id');
        $user_name = request()->header('username');
        $time = date('Y-m-d H:i:s');
        if(empty($item)){
            //首次打印
            $create_data = [
                'ship_code'=>$ship_code,
                'deliver_no' => $deliver_no,
                'print_count' => 1,
                'created_user' => $user_id,
                'cre_user_name' => $user_name,
                'created_at' => $time,
                'updated_user' => $user_id,
                'upd_user_name' => $user_name,
                'updated_at' => $time,
            ];
            return self::insert($create_data);
        }else{
            $update_date = [
                'print_count' => $item->print_count + 1,
                'deliver_no' => $deliver_no,
                'updated_user' => $user_id,
                'upd_user_name' => $user_name,
            ];
            return $item->update($update_date);

        }

    }

}
