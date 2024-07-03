<?php

namespace App\Http\Controllers\Admin\V2\Common;

use App\Http\Controllers\Admin\V2\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HomeController extends BaseController
{
    protected $BaseModels = 'App\Models\Admin\V2\Home';
    private function dateArea($type)
    {
        $time = time();
        $end_time = date('Y-m-d H:i:s', $time);
        switch ($type) {
            case 'day':
                $start_time =  date('Y-m-d 00:00:00');
                break;
            case 'week':
                $start_time = date('Y-m-d H:i:s', strtotime('-1 week'));
                break;
            case 'month':
                $start_time = date('Y-m-01 00:00:00');
                break;
            case 'year':
                $start_time = date('Y-01-01 00:00:00');
                break;
            case 'all':
                $start_time = [
                    'day' => date('Y-m-d 00:00:00'),
                    'week' => date('Y-m-d H:i:s', strtotime('-1 week')),
                    'month' => date('Y-m-01 00:00:00'),
                    'year' => date('Y-01-01 00:00:00'),
                ];
                break;
            default:
                $start_time = date('Y-m-d 00:00:00');
                break;
        }
        return ['start_time' => $start_time, 'end_time' => $end_time];
    }

    private function dateASearch($name, $params = [], $time,$time_area='all')
    {
        $data = [];
        $start_time = $time['start_time'];
        $end_time = $time['end_time'];
        if (is_string($name)) $name = explode(',', $name);
        foreach ($name as $n) {
            $model_func = Str::camel($n);
            if($time_area == 'all'){
                $data[$n]['day'] = $this->model->$model_func(...array_merge($params, [$start_time['day'], $end_time]));
                $data[$n]['week'] = $this->model->$model_func(...array_merge($params, [$start_time['week'], $end_time]));
                $data[$n]['month'] = $this->model->$model_func(...array_merge($params, [$start_time['month'], $end_time]));
                $data[$n]['year'] = $this->model->$model_func(...array_merge($params, [$start_time['year'], $end_time]));
            }else{
                $data[$n][$time_area]=$this->model->$model_func(...array_merge($params, [$start_time, $end_time]));
            }

        }
        return $data;
    }

    //店铺面板
    public function shop(Request $request)
    {
        $shop_code = $request->get('shop_code', '');
        $shop_todo_list = $this->model->shopToDoList($shop_code);
        $time_area = $request->get('time_area','day');
        $time = $this->dateArea($time_area);
        // $all_data = $this->dateAll(['shop_amount', 'shop_buyer', 'shop_top', 'sale_top', 'return_top'], [$shop_code], $time);
        $all_data = $this->dateASearch(['shop_amount', 'shop_buyer', 'shop_top', 'sale_top', 'return_top'], [$shop_code], $time,$time_area);
        $res = array_merge([
            'shop_todo_list' => $shop_todo_list,

        ], $all_data);
        return $this->success($res);
    }

    //仓库面板
    public function warehouse(Request $request)
    {
        $warehouse_code = $request->get('warehouse_code', '');
        $wh_todo_list = $this->model->whToDoList($warehouse_code);
        $wh_out_in = $this->model->whOutIn($warehouse_code);
        $res =  [
            'wh_todo_list' => $wh_todo_list,
            'wh_out_in' => $wh_out_in,

        ];
        return $this->success($res);
    }

    //店铺面板-代办事项
    public function shopToDoList(Request $request)
    {
        $shop_code = $request->get('shop_code', '');
        $res = $this->model->shopToDoList($shop_code);
        return $this->success($res);
    }

    //店铺面板-订单金额&退款金额
    public function shopAmount(Request $request)
    {
        $shop_code = $request->get('shop_code', '');
        $date_type = $request->get('time_area', 'day');
        $time = $this->dateArea($date_type);
        $res = $this->model->shopAmount($shop_code, $time['start_time'], $time['end_time']);
        return $this->success($res);
    }

    //店铺面板-平均客单&客户人数
    public function shopBuyer(Request $request)
    {
        $shop_code = $request->get('shop_code', '');
        $date_type = $request->get('time_area', 'day');
        $time = $this->dateArea($date_type);
        $res = $this->model->shopBuyer($shop_code, $time['start_time'], $time['end_time']);
        return $this->success($res);
    }

     public function top(Request $request)
    {
        $shop_code = $request->get('shop_code', '');
        $date_type = $request->get('time_area', 'day');
        $type = $request->get('type', 'shop_top');
        $time = $this->dateArea($date_type);
        $model_func = Str::camel($type);
        $res = $this->model->$model_func($shop_code, $time['start_time'], $time['end_time']);
        return $this->success($res);
    }

}
