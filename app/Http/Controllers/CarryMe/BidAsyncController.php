<?php

namespace App\Http\Controllers\CarryMe;

use App\Http\Controllers\Controller;
use App\Logics\BaseLogic;
use App\Logics\bid\BidCancel;
use App\Logics\BiddingAsyncLogic;
use App\Models\CarryMeBidding;
use App\Models\CarryMeBiddingItem;
use App\Models\ChannelBidding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BidAsyncController extends Controller
{
    /**
     * 出价
     * @param Request $request
     * @return JsonResponse
     */
    public function bid(Request $request): JsonResponse
    {
        $params = $request->input();
        Log::channel('daily2')->info('app出价',$params);

        $this->validateParams($params, [
            'config' => 'required|array',
            'product_sn' => 'required',
            'properties' => 'required',
            'good_name' => 'required',
            'callback_id' => 'required',
            'qty' => 'required|integer|min:1',
        ]);
        $logic = new BiddingAsyncLogic();
        $data = $logic->bidV3($params);

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
        Log::channel('daily2')->info('app取消出价',$params);

        $this->validateParams($params, [
            'carryme_bid_id' => ['required', 'exists:carryme_bidding,id']
        ]);

        $params['remark'] = '出价id取消';
        $logic = new BidCancel();
        $logic::appCancel($params);
        return $this->output($logic, []);
    }

    /**
     * 单个渠道出价取消
     * @param Request $request
     * @return JsonResponse
     */
    public function cancelSingle(Request $request): JsonResponse
    {
        $params = $request->input();
        Log::channel('daily2')->info('app单个渠道出价取消',$params);
        $this->validateParams($params, [
            'carryme_bid_item_id' => ['required', 'exists:carryme_bidding_items,id']
        ]);

        $params['remark'] = '单渠道取消出价';
        $logic = new BidCancel();
        $logic::appCancel($params);
        return $this->output($logic, []);
    }

    /**
     * 批量取消出价
     * 指定货号下的所有出价
     */
    public function batchCancel(Request $request)
    {
        $params = $request->input();
        Log::channel('daily2')->info('app批量取消出价',$params);
        $this->validateParams($params, [
            'product_sn' => 'required'
        ]);

        $params['remark'] = '按货号批量取消出价';
        $logic = new BidCancel();
        $data = $logic::appCancel($params);
        return $this->output($logic, $data);
    }

    /**
     * 批量新增出价
     * 指定货号下的指定规格
     *
     * @param Request $request
     */
    public function batchBidByProductSn(Request $request)
    {
        $params = $request->input();
        Log::channel('daily2')->info('app批量新增出价',$params);
        $this->validateParams($params, [
            'product_sn' => 'required',
            'good_name' => 'required',
            'skus' => 'required|array',
            'skus.*.config' => 'required|array',
            'skus.*.qty' => 'required|integer|min:1',
            'skus.*.sku_id' => 'required|integer',
            'skus.*.callback_id' => 'required|integer',
            'skus.*.properties' => 'required',
        ]);

        $logic = new BiddingAsyncLogic();
        $data = [];
        foreach ($params['skus'] as $sku) {
            $sku['good_name'] = $params['good_name'];
            $sku['product_sn'] = $params['product_sn'];
            $res = $logic->bidV3($sku);
            $data[] = [
                'callback_id' => $sku['callback_id'],
                'carryme_bid_id' => $res['carryme_bid_id']
            ];
        }
        return $this->output($logic, $data);
    }

    /**
     * 批量取消出价
     * 指定货号下的指定出价
     * @param Request $request
     * @return void
     */
    public function batchCancelByProductSn(Request $request)
    {
        $params = $request->input();
        Log::channel('daily2')->info('app批量取消出价',$params);
        $this->validateParams($params, [
            'product_sn' => 'required',
            'carryme_bid_ids' => 'required|array',
        ]);

        $params['remark'] = '按货号批量取消出价（指定规格）';
        $logic = new BidCancel();
        $logic->appCancel($params);
        return $this->output($logic, []);
    }


    public function search(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'carryme_bid_ids' => 'required|array',
        ]);
        $data = ChannelBidding::whereIn('carryme_bidding_id', $params['carryme_bid_ids'])
            ->where(['status' => ChannelBidding::BID_SUCCESS])
            ->select([DB::raw('carryme_bidding_item_id as carryme_bid_item_id'), DB::raw('carryme_bidding_id as carryme_bid_id')])
            ->get()->makeHidden(['business_type_txt', 'status_txt', 'carryme_business_type']);
        return $this->output(new BaseLogic(), $data);
    }

    //出价结果补偿
    public function resultCompensation(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'spu_id' => 'required',
            'ext_tag' => 'required',
        ]);
        $logic = new BiddingAsyncLogic();
        $data = $logic->refreshBidResult($params);
        return $this->output(new BaseLogic(), $data);
    }
}
