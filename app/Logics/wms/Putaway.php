<?php

namespace App\Logics\wms;

use App\Logics\BaseLogic;
use App\Models\Admin\V2\ArrivalRegist;
use App\Models\Admin\V2\Inventory;
use App\Models\Admin\V2\RecvDetail;
use App\Models\Admin\V2\Warehouse;
use App\Models\Admin\V2\WarehouseLocation;
use App\Models\Admin\V2\WmsOptionLog;
use App\Models\Admin\V2\WmsPutawayDetail;
use App\Models\Admin\V2\WmsPutawayList;
use App\Models\Admin\V2\WmsStockLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class Putaway extends BaseLogic
{
    // 提交上架单
    function scan($params)
    {
        $warehouse_name = Warehouse::getWarehouseName($params['warehouse_code']);
        if (!$warehouse_name) {
            $this->setErrorMsg(__('tips.warehouse_error'));
            return [];
        }

        // 查位置码是否存在
        $location_codes = array_unique(array_column($params['skus'], 'location_code'));
        $locations = WarehouseLocation::where('warehouse_code', $params['warehouse_code'])->whereIn('location_code', $location_codes)->get()->keyBy('location_code')->toArray();
        $real = [];
        foreach ($locations as $item) {
            if(in_array($item['area_code'],['XJZCQ001','SHZCQ001','ZJZCQ001'])){
                $this->setErrorMsg(sprintf(__('tips.temp_not_putaway')));
                return [];
            }
            if ($item['status'] == 1 && $item['warehouse_code'] == $params['warehouse_code']) {
                $real[] = $item['location_code'];
            }
        }
        $diff = array_diff($location_codes, $real);
        if ($diff) {
            $this->setErrorMsg(sprintf(__('tips.location_error_info'), implode(',', $diff)));
            return [];
        }

        // 查唯一码是否存在且可上架
        $uniq_codes = array_unique(array_column($params['skus'], 'uniq_code'));
        $goods = Inventory::whereIn('uniq_code', $uniq_codes)->where('warehouse_code', $params['warehouse_code'])->where('in_wh_status', Inventory::QC)->get()->keyBy('uniq_code');
        $real = array_keys($goods->toArray());
        $diff = array_diff($uniq_codes, $real);
        if ($diff) {
            $this->setErrorMsg(sprintf(__('tips.uniq_not_wait_shelf'), implode(',', $diff)));
            return [];
        }

        try {
            DB::beginTransaction();

            $type = $params['type'] ?? WmsPutawayList::TYPE_STORE_IN;
            $putaway = WmsPutawayList::where('create_user_id', ADMIN_INFO['user_id'])->where('status', WmsPutawayList::STATUS_STAGE)->where('type', $type)->first();
            if (!$putaway) {
                $putaway = WmsPutawayList::create([
                    'type' => $type,
                    'putaway_code' => WmsPutawayList::code(),
                    'create_user_id' => ADMIN_INFO['user_id'],
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                    'warehouse_code' => $params['warehouse_code'],
                    'warehouse_name' => $warehouse_name,
                ]);
                WmsOptionLog::add(WmsOptionLog::PUTAWAY, $putaway->putaway_code, '创建', '扫描上架开始', []);
            }

            // 查商品是否质检通过
            foreach ($params['skus'] as $sku) {

                // 查上架记录
                $find = WmsPutawayDetail::where('uniq_code', $sku['uniq_code'])->where('type', $type)->where('putaway_code', $putaway->putaway_code)->first();
                if ($find){
                    //重复上架
                    $this->setErrorMsg(__('tips.option_repeat'));
                    return;
                    // continue;
                }

                WmsPutawayDetail::create([
                    'type' => $type,
                    'putaway_code' => $putaway->putaway_code,
                    'bar_code' => $goods[$sku['uniq_code']]['bar_code'],
                    'uniq_code' => $sku['uniq_code'],
                    'location_code' => $sku['location_code'],
                    'area_code' => $locations[$sku['location_code']]['area_code'],
                    'quality_type' => $goods[$sku['uniq_code']]->getRawOriginal('quality_type'),
                    'quality_level' => $goods[$sku['uniq_code']]['quality_level'],
                    'admin_user_id' => ADMIN_INFO['user_id'],
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                ]);

                //修改库存库区编码为上架暂存区SJZCQ001
                Inventory::where('uniq_code', $sku['uniq_code'])->where('in_wh_status', Inventory::QC)->update(['area_code' => 'SJZCQ001']);
            }
            DB::commit();
            $data = $this->info(['id' => $putaway->id]);
            return $data;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return [];
        }
    }

    // 提交上架单-普通产品上架
    function scanOrdinary($params)
    {
        $warehouse_name = Warehouse::getWarehouseName($params['warehouse_code']);
        if (!$warehouse_name) {
            $this->setErrorMsg(__('tips.warehouse_error'));
            return [];
        }

        // 查位置码是否存在
        $location_code = $params['location_code'];
        $location = WarehouseLocation::where('warehouse_code', $params['warehouse_code'])->where('location_code', $location_code)->where('status', 1)->orderBy('created_at', 'desc')->first();
        if (!$location) {
            $this->setErrorMsg(__('tips.location_error'));
            return [];
        }

        // 查找收货单中存在存在的未上架的条码商品
        $bar_code = $params['bar_code'];
        $quality_type = $params['is_flaw'] == 0 ? 1 : 2;
        $goods = Inventory::where('recv_unit', $params['put_unit'])->where('bar_code', $bar_code)->where('quality_type', $quality_type)
            ->where('area_code', 'ZJZCQ001')->where('is_qc', 1)->where('is_putway', 0)->selectRaw('arr_id,recv_id,bar_code,uniq_code,quality_type,quality_level')->groupByRaw('arr_id,bar_code,quality_type,quality_level')->get();
        if ($goods->isEmpty()) {
            $this->setErrorMsg(__('tips.good_not_wait_shelf'));
            return [];
        }
        // dd($goods->toArray());
        if ($goods->count() > 1) {
            //存在多个登记单
            if (!empty($params['arr_id'])) {
                $goods = Inventory::where('arr_id', $params['arr_id'])->where('recv_unit', $params['put_unit'])->where('bar_code', $bar_code)->where('quality_type', $quality_type)
                    ->where('area_code', 'ZJZCQ001')->where('is_qc', 1)->where('is_putway', 0)->selectRaw('arr_id,recv_id,bar_code,uniq_code,quality_type,quality_level')->groupByRaw('arr_id,bar_code,quality_type,quality_level')->get();
            } else {
                $arr_item = ArrivalRegist::whereIn('id', $goods->pluck('arr_id'))
                    ->selectRaw('id,arr_type,arr_status,log_number,third_doc_code,arr_name,arr_code,arr_num,recv_num')
                    ->get()->makeHidden(['ibOrder']);
                return ['code' => 0, 'msg' => __('status.scan_ordinary_more'), 'arr_list' => $arr_item->toArray()];
            }
        }
        $goods =  $goods->first();
        try {
            DB::beginTransaction();
            $type = $params['type'] ?? WmsPutawayList::TYPE_STORE_IN;
            $putaway = WmsPutawayList::where('create_user_id', ADMIN_INFO['user_id'])->where('status', WmsPutawayList::STATUS_STAGE)->where('type', $type)->first();
            if (!$putaway) {
                $putaway = WmsPutawayList::create([
                    'type' => $type,
                    'putaway_code' => WmsPutawayList::code(),
                    'create_user_id' => ADMIN_INFO['user_id'],
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                    'warehouse_code' => $params['warehouse_code'],
                    'warehouse_name' => $warehouse_name,
                ]);
                WmsOptionLog::add(WmsOptionLog::PUTAWAY, $putaway->putaway_code, '创建', '扫描上架开始', []);
            }

            // 查上架记录
            $find = WmsPutawayDetail::where('uniq_code', $goods['uniq_code'])->where('type', $type)->where('putaway_code', $putaway->putaway_code)->first();
            if (!$find) {
                WmsPutawayDetail::create([
                    'type' => $type,
                    'putaway_code' => $putaway->putaway_code,
                    'bar_code' => $params['bar_code'],
                    'put_unit' => $params['put_unit'],
                    'uniq_code' => $goods['uniq_code'],
                    'location_code' => $params['location_code'],
                    'area_code' => $location['area_code'],
                    'quality_type' => $goods->getRawOriginal('quality_type'),
                    'quality_level' => $goods['quality_level'],
                    'admin_user_id' => ADMIN_INFO['user_id'],
                    'tenant_id' => ADMIN_INFO['tenant_id'],
                ]);
                //修改库存库区编码为上架暂存区SJZCQ001
                Inventory::where('uniq_code', $goods['uniq_code'])->where('in_wh_status', Inventory::QC)->update(['area_code' => 'SJZCQ001']);
            } else {
                $this->setErrorMsg(__('tips.good_not_wait_shelf'));
                DB::rollBack();
                return [];
            }
            DB::commit();
            // $data = $this->info(['id' => $putaway->id]);
            $data = ['code' => 1];
            return $data;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return [];
        }
    }

    //提交上架单-普通产品上架-增加
    function  addOrdinary($params)
    {
        $detail_id = $params['detail_id'];

        $detail = WmsPutawayDetail::find($detail_id);
        if (!$detail) {
            $this->setErrorMsg(sprintf('product not exists'));
            return [];
        }
        //判断上架单是否是暂存状态
        $putaway_code = $detail->putaway_code;
        $list = WmsPutawayList::where('putaway_code', $putaway_code)->where('warehouse_code', $params['warehouse_code'])->first();
        if ($list->status != 0) {
            $this->setErrorMsg(sprintf('putaway order status cant not add'));
            return [];
        }
        $item = Inventory::where('uniq_code', $detail->uniq_code)->first();
        if (!$item) {
            $this->setErrorMsg(sprintf('product not exists'));
            return [];
        }
        $count = $params['count'];
        $goods = Inventory::where('arr_id', $item->arr_id)->where('recv_unit', $item->recv_unit)->where('bar_code', $item->bar_code)->where('quality_type', $item->getRawOriginal('quality_type'))->where('quality_level', $item->quality_level)
            ->where('area_code', 'ZJZCQ001')->where('is_qc', 1)->where('is_putway', 0)->limit($count);
        if ($goods->get()->count() < $count) {
            $this->setErrorMsg(sprintf('The quantity on the shelves exceeds the quantity received'));
            return [];
        }
        $add_data = [];
        foreach ($goods->get() as $good) {
            $temp = [
                'type' => $detail->type,
                'putaway_code' => $putaway_code,
                'bar_code' => $good['bar_code'],
                'uniq_code' => $good['uniq_code'],
                'location_code' => $detail['location_code'],
                'put_unit' => $detail->put_unit,
                'area_code' => $detail['area_code'],
                'quality_type' => $good->getRawOriginal('quality_type'),
                'quality_level' => $good['quality_level'],
                'admin_user_id' => ADMIN_INFO['user_id'],
                'tenant_id' => ADMIN_INFO['tenant_id'],
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $add_data[] = $temp;
        }
        try {
            Db::beginTransaction();
            WmsPutawayDetail::insert($add_data);
            $goods->update(['area_code' => 'SJZCQ001']);
            DB::commit();
            return  ['code' => 1];
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return [];
        }
    }

    // 上架完成
    function submit($params)
    {
        try {
            DB::beginTransaction();
            $ids = $params['ids'] ?? [];
            if ($ids) {
                if (!is_array($ids)) $ids = [$ids];
                $putaways = WmsPutawayList::whereIn('id', $ids)->where('status', WmsPutawayList::STATUS_STAGE)->get();
            } else {
                $where = ['create_user_id' => ADMIN_INFO['user_id'], 'status' => WmsPutawayList::STATUS_STAGE];
                $where2 = self::filterEmptyData($params, ['warehouse_code', 'type']);
                $putaway = WmsPutawayList::where(array_merge($where, $where2))->first();
                $putaways = [$putaway];
            }

            foreach ($putaways as $putaway) {
                if (!$putaway) continue;
                $putaway->update([
                    'status' => WmsPutawayList::STATUS_AUDIT,
                    'putaway_status' => WmsPutawayList::PUTAWAY_DONE,
                    'total_num' => WmsPutawayDetail::where('putaway_code', $putaway->putaway_code)->count(),
                    'completed_at' => date('Y-m-d H:i:s'),
                    'submitter_id' => ADMIN_INFO['user_id'],
                    'admin_user_id' => ADMIN_INFO['user_id'],
                ]);
                WmsOptionLog::add(WmsOptionLog::PUTAWAY, $putaway->putaway_code, '上架完成', '上架完成', []);
                $details = WmsPutawayDetail::where('putaway_code', $putaway->putaway_code)->get();
                //位置码更新为不空闲
                $location_codes = $details->pluck('location_code')->toArray();
                WarehouseLocation::where('warehouse_code', $putaway->warehouse_code)->whereIn('location_code', $location_codes)->update(['is_able' => 0]);
                foreach ($details as $detail) {
                    $res = RecvDetail::locationUpdate($detail->uniq_code, $detail->area_code, $detail->location_code, false);
                    if (!($res[0] ?? false)) {
                        throw new Exception('位置码更新失败');
                    }
                    if ($putaway->type == 1) WmsStockLog::add(WmsStockLog::ORDER_PUTAWAY, $detail->uniq_code, $putaway->putaway_code);
                }
            }
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            $this->setErrorMsg($e->getMessage());
            return false;
        }
    }

    function search($params, $export = false)
    {

        $model = new WmsPutawayList();
        $list = $this->_search($params, $model, $export, function ($model, $params) {
            return $model->with(['submitUser'])->orderBy('created_at', 'desc');
        });

        foreach ($list['data'] as &$item) {
            $item['submit_user'] = $item['submit_user']['username'] ?? '';
            $item['created_at'] = date('Y-m-d H:i:s', strtotime($item['created_at']));
        }
        return $list;
    }

    function info($params)
    {
        if(!empty($params['id']))$info = WmsPutawayList::where('id', $params['id'])->with(['adminUser', 'createUser', 'submitUser'])->first();
        if(!empty($params['code']))$info = WmsPutawayList::where('putaway_code', $params['code'])->with(['adminUser', 'createUser', 'submitUser'])->first();
        if(!$info)return[];
        $info = $info->toArray();
        $info['create_user'] = $info['create_user'] ? $info['create_user']['username'] : '';
        $info['submit_user'] = $info['submit_user'] ? $info['submit_user']['username'] : '';
        $info['admin_user'] = $info['admin_user'] ? $info['admin_user']['username'] : '';

        $sql = "SELECT a.*,wupl.sku,wupl.spec_one,wupl.spec_two,wupl.spec_three,wp.img,wp.name,product_sn from (
            SELECT bar_code,location_code,quality_type,quality_level,COUNT(id) as num FROM wms_putaway_detail 
            WHERE putaway_code=?  and tenant_id = ?
            GROUP BY bar_code,location_code,quality_type,quality_level) as a 
            left JOIN wms_spec_and_bar wupl ON a.bar_code = wupl.bar_code
            LEFT JOIN wms_product wp ON wupl.product_id = wp.id
            WHERE  wupl.tenant_id = ?";
        $detail = DB::select($sql, [$info['putaway_code'], ADMIN_INFO['tenant_id'], ADMIN_INFO['tenant_id']]);

        $total = 0;
        $id = 1;
        foreach ($detail as &$item) {
            $item->quality_type = QualityControl::getQualityTypeTxt($item->quality_level);
            $item->id = $id;
            $total += $item->num;
            $id++;
        }

        $logs = WmsOptionLog::where('type', WmsOptionLog::PUTAWAY)->where('doc_code', $info['putaway_code'])->orderBy('id', 'desc')->get();

        return compact('info', 'detail', 'total', 'logs');
    }

    function detail($params)
    {
        $model = WmsPutawayDetail::where('putaway_code', $params['putaway_code'])
            ->where('bar_code', $params['bar_code'])
            ->where('location_code', $params['location_code'])
            ->where('quality_level', $params['quality_level']);
        if ($params['WHERE'] ?? []) {
            $model->addListWhere($params['WHERE']);
        }
        $list = $model->with(['specBar', 'arrivalRegist', 'adminUser'])->get();
        $res = [];
        foreach ($list as $item) {
            $sku = $item->specBar;
            $product = $sku ? $sku->product : null;
            $res[] = [
                'id' => $item->id,
                'merchant_name' => '',
                'product_sn' => $product ? $product->product_sn : '',
                'name' => $product ? $product->name : '',
                'spec_one' => $sku ? $sku->spec_one : '',
                'quality_type_txt' => $item->quality_type_txt,
                'quality_level' => $item->quality_level,
                'lot_num' => $item->arrivalRegist ? $item->arrivalRegist->lot_num : '',
                'uniq_code' => $item->uniq_code,
                'num' => 1,
                'admin_user' => $item->adminUser ? $item->adminUser->username : '',
                'created_at' => Carbon::parse($item->created_at)->toDatetimeString(),
            ];
        }
        return $res;
    }

    function save($params)
    {
        WmsPutawayList::where('id', $params['id'])->update([
            'remark' => $params['remark'],
            'admin_user_id' => ADMIN_INFO['user_id'],
        ]);
        return true;
    }

    // 创建一个已完成的上架单
    static function createEndList($data, $details)
    {
        if (empty($data['putaway_code'])) $data['putaway_code'] = WmsPutawayList::code();
        $putaway = WmsPutawayList::create($data);
        foreach ($details as $detail) {
            $detail['putaway_code'] = $putaway->putaway_code;
            WmsPutawayDetail::create($detail);
            //更新总库存
            // Inventory::totalInvUpdate($data['warehouse_code'], $detail['bar_code']);
            Inventory::invAsyncAdd(0, 2, $data['warehouse_code'], $detail['bar_code']);
        }
        return $putaway;
    }
}
