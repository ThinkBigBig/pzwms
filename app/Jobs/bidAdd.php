<?php

namespace App\Jobs;

use App\Handlers\CarryMeApi;
use App\Logics\BiddingAsyncLogic;
use App\Logics\BiddingLogic;
use App\Logics\BidExecute;
use App\Logics\CarrymeCallbackLogic;
use App\Logics\NoticeLogic;
use App\Logics\ProductLogic;
use App\Logics\Robot;
use App\Models\CarryMeBidding;
use App\Models\CarryMeBiddingItem;
use App\Models\StockBidding;
use App\Models\ChannelBidding;
use App\Models\StockBiddingItem;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Psy\Util\Json;

class bidAdd implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2; //任务可尝试次数
    public $timeout = 3600; //任务超时之前可运行的秒数

    protected $params = [];
    const BID_TYPE_ALL = 1; //多渠道同时出价
    const BID_TYPE_SINGLE = 2; //单渠道分别出价
    const BID_REFRESH = 3; //刷新出价

    const CANCEL_TYPE_ALL = 'all';
    const CANCEL_TYPE_SIGNLE = 'single';
    const CANCEL_TYPE_SAMPLE = 'sample';
    


    const OPTION_ADD = 'add';
    const OPTION_CANCEL = 'cancel';


    /**
     * 异步执行新增出价
     *
     * @return void
     */
    public function __construct($params)
    {
        //
        $this->params = $params;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $option = $this->params['option'] ?? 'add';
        if ($option == self::OPTION_ADD) {
            $this->_add();
        }
        if ($option == self::OPTION_CANCEL) {
            $this->_cancel();
        }
    }

    private function _add()
    {
        $bid_type = $this->params['bid_type'] ?? self::BID_TYPE_ALL;
        if ($bid_type == self::BID_TYPE_ALL) {
            $logic = new BiddingLogic();
            $res = $logic->bidV2($this->params);
            $infos = [];

            foreach ($res['info'] as $info) {
                $infos[] = [
                    'code' => $info['channel_code'], //渠道编码
                    'lowestPrice' => $info['lowest_price'], //最低价
                ];
            }
            $data = [
                'list' => [
                    [
                        'businessType' => CarryMeBidding::getCarrymeBusinessType($res['business_type']), //出价类型 0现货 1预售 2闪电直发
                        'infos' => $infos,
                        'isSuccess' => $res['success'],
                        'messageId' => $res['carryme_bid_id'],
                        'thirdPartyProductRecordId' => $res['callback_id'],
                    ]
                ]
            ];
            NoticeLogic::bidSuccess($data);
        } elseif ($bid_type == self::BID_TYPE_SINGLE) {

            // dump($this->params);
            $s = time();
            try {

                // app出价
                if ($this->params['carryme_bidding_item_id'] ?? 0) {
                    $item = CarryMeBiddingItem::where(['id' => $this->params['carryme_bidding_item_id'], 'status' => CarryMeBiddingItem::STATUS_DEFAULT])->first();
                    if (!$item) {
                        Robot::sendException(sprintf('（出价数据），params:%s', Json::encode($this->params)));
                        return 0;
                    }
                    $logic = new BiddingAsyncLogic();
                    $logic->bidSingle($item);
                }

                //库存出价
                if ($this->params['stock_bidding_item_id'] ?? 0) {
                    $item = StockBiddingItem::where(['id' => $this->params['stock_bidding_item_id'], 'status' => StockBiddingItem::STATUS_DEFAULT])->first();
                    if (!$item) {
                        Robot::sendException(sprintf('（出价数据），params:%s', Json::encode($this->params)));
                        return 0;
                    }
                    $logic = new BiddingAsyncLogic();
                    $logic->bidSingleStock($item);
                }
            } catch (Exception $e) {
                Robot::sendException('bidAdd params：' . json_encode($this->params) . $e->__toString());
            }
            // dump(time() - $s);
        }

        //刷新出价
        if ($bid_type == self::BID_REFRESH) {
            // $bidding = ChannelBidding::where(['id' => $this->params['channel_bidding_id']])->first();

            // $logic = new BiddingAsyncLogic();
            // $res = $logic->bidRefresh($bidding);

            // $msg = sprintf('刷新出价 bidding_id：%s carryme_bidding_id：%s res：%s msg：%s', $bidding->id, $bidding->carryme_bidding_id, $res ? '成功' : '失败', $logic->err_msg);
            // // dump($msg);
            // Robot::sendNotice($msg);
        }
    }

    private function _cancel()
    {
        $cancel_type = $this->params['cancel_type'] ?? self::CANCEL_TYPE_ALL;
        switch ($cancel_type) {
            case self::CANCEL_TYPE_ALL: //全渠道取消
                $logic = new BiddingAsyncLogic();
                $res = $logic->cancel($this->params);
                // dump($this->params, $res);
                //有申请中的出价，等处理完再取消
                if ($res['re_onqueue'] ?? '') {
                    $this->release(5);
                } else {
                    CarrymeCallbackLogic::bidCancel($this->params['carryme_bidding_id']);
                }
                break;
            case self::CANCEL_TYPE_SIGNLE: //单个渠道取消
                // dump($this->params);
                $s = time();
                try {
                    if($this->params['carryme_bidding_item_id']??0){
                        BidExecute::appItemCancel($this->params);
                        break;
                    }
                    if ($this->params['stock_bidding_item_id'] ?? 0) {
                        // 库存取消出价
                        BiddingAsyncLogic::stockSingleChannelCancel($this->params);
                    } else {
                        // app取消出价
                        BiddingAsyncLogic::singleChannelCancel($this->params);
                    }
                } catch (Exception $e) {
                    Robot::sendException('bidCancel params：' . json_encode($this->params) . $e->__toString());
                }
                // dump(time() - $s);
                break;
            case self::CANCEL_TYPE_SAMPLE:
                try{
                    BiddingAsyncLogic::singleSampleCancel($this->params);
                }catch(Exception $e){
                    Robot::sendException('bidCancel params：' . json_encode($this->params) . $e->__toString());
                }
                break;
        }
    }
}
