<?php
    
namespace App\Http\Controllers\Admin\V2\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\V2\WmsOptionLog;

class CommonController extends Controller{

    protected $BaseModels = 'App\Models\Admin\V2\WmsOptionLog';
    
    public function success( $data = [],$msg='操作成功'): \Illuminate\Http\JsonResponse
    {
        $code = self::SUCCESS;
        return response()->json([
            'code' => $code,
            'msg' => $msg,
            'time' => time(),
            'data' => $data,
        ]);
    }
    public function error($msg='', $data = []): \Illuminate\Http\JsonResponse
    {
        $code = self::ERROR;
        return response()->json([
            'code' => $code,
            'msg' => $msg,
            'time' => time(),
            'data' => $data,
        ]);
    }

    public function optionLog(Request $request){
            //操作日志
        $vat = [
            'type'=>'required|digits_between:1,18',
            'code'=>'required',
        ];
        $this->validateParams($request->all(),$vat);
        $type = $request->get('type');
        $code = $request->get('code');

        $res =  WmsOptionLog::list($type,$code);
        return $this->success($res);

    }
}