<?php

namespace App\Http\Controllers\Admin\V1;

use App\Logics\ChannelPurchaseLogic;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;

class PurchaseProductController extends BaseController
{
    // 新增/删除可进行空卖出价的商品货号
    function update(Request $request)
    {
        $params = $request->all();
        $params['admin_user_id'] = $request->header('user_id');
        $this->validateParams($params, [
            'product_sns' => 'required',
            'status' => ['required', Rule::in([0, 1])],
        ]);

        $res = [];
        $product_sns = explode(',', $request->get('product_sns'));
        foreach ($product_sns as $product_sn) {
            $item = ChannelPurchaseLogic::product([
                'status' => $request->get('status'),
                'product_sn' => $product_sn
            ]);
            $res[$product_sn] = $item;
        }
        return $this->success($res);
    }

    // 增加价格计算公式
    function addPriceConfig(Request $request)
    {
        $params = $request->all();
        $params['admin_user_id'] = $request->header('user_id');
        $this->validateParams($params, [
            'min' => 'required',
            'max' => 'required',
            'formula' => 'required',
        ]);

        $logic = new ChannelPurchaseLogic();
        $product_sns = $params['product_sns'] ?? '';
        if (!$product_sns) {
            $logic->addPriceConfig($params);
            return $this->output($logic, []);
        }

        $arr = explode(',', $product_sns);
        $res = [];
        foreach ($arr as $product_sn) {
            $params['product_sn'] = $product_sn;
            $logic->addPriceConfig($params);
            $res[$product_sn]= [$logic->success,$logic->err_msg]; 
        }

        return $this->output($logic, $res);
    }

    // 删除价格计算公式
    function delPriceConfig(Request $request)
    {
        $params = $request->all();
        $params['admin_user_id'] = $request->header('user_id');
        $product_sns = $params['product_sns'] ?? '';
        $arr = explode(',', $product_sns);
        $this->validateParams($params, [
            'min' => 'required',
            'max' => 'required',
        ]);
        $logic = new ChannelPurchaseLogic();
        if(!$arr){
            $logic->delPriceConfig($params);
            return $this->output($logic, []);
        }

        foreach($arr as $product_sn){
            $params['product_sn'] = $product_sn;
            $logic->delPriceConfig($params);
        }
        return $this->output($logic, []);
    }
}
