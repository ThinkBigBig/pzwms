<?php

namespace App\Http\Controllers\CarryMe;

use App\Http\Controllers\Controller;
use App\Jobs\bidAdd;
use App\Jobs\bidCancel;
use App\Logics\BiddingLogic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BidController extends Controller
{
    /**
     * 出价
     * @param Request $request
     * @return JsonResponse
     */
    public function bid(Request $request): JsonResponse
    {
        $params = $request->input();
        $this->validateParams($params,[
            'config' => 'required|array',
            'product_sn' => 'required',
            'properties' => 'required',
            'good_name' => 'required',
            'qty' => 'required|integer|min:1',
        ]);
        $logic = new BiddingLogic();
        $data = $logic->bidV2($params);
        return $this->output($logic, $data);
    }

    /**
     * 取消出价
     * @param Request $request
     * @return JsonResponse
     */
    public function cancel(Request $request): JsonResponse
    {
        $params = $request->input();
        $this->validateParams($params,[
            'carryme_bid_id' => ['required', 'exists:carryme_bidding,id']
        ]);

        $logic = new BiddingLogic();
        $params['carryme_bidding_id'] = $params['carryme_bid_id'];
        $params['option'] = bidAdd::OPTION_CANCEL;
        bidAdd::dispatch($params)->onQueue('bid-add');
        return $this->output($logic, []);
    }

    /**
     * 批量取消出价
     * 指定货号下的所有出价
     */
    public function batchCancel(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params,[
            'product_sn' => 'required'
        ]);
        $logic = new BiddingLogic();
        $data = $logic->batchCancel($params);
        return $this->output($logic, $data);
    }

    /**
     * 根据货号批量新增出价
     *
     * @param Request $request
     */
    public function batchBidByProductSn(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params,[
            'product_sn' => 'required',
            'good_name' => 'required',
            'skus' => 'required|array',
            'skus.*.config' => 'required|array',
            'skus.*.qty' => 'required|integer|min:1',
            'skus.*.sku_id' => 'required|integer',
            'skus.*.callback_id' => 'required|integer',
            'skus.*.properties' => 'required',
        ]);

        $logic = new BiddingLogic();
        foreach($params['skus'] as $sku){
            $sku['good_name'] = $params['good_name'];
            $sku['product_sn'] = $params['product_sn'];
            bidAdd::dispatch($sku)->onQueue('bid-add');
        }
        return $this->output($logic, []);
    }

    public function batchCancelByProductSn(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params,[
            'product_sn' => 'required',
            'carryme_bid_ids' => 'required|array',
        ]);

        $logic = new BiddingLogic();
        foreach($params['carryme_bid_ids'] as $carryme_bid_id){
            $item = [
                'carryme_bidding_id' => $carryme_bid_id,
            ];
            bidCancel::dispatch($item)->onQueue('bid-cancel');
        }
        return $this->output($logic, []);
    }
}