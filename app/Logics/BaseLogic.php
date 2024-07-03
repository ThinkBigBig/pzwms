<?php

namespace App\Logics;

use App\Handlers\HttpService;
use App\Models\Admin\V2\Inventory;
use App\Models\Admin\V2\ProductSpecAndBar;
use App\Models\Admin\V2\Warehouse;
use App\Models\Admin\V2\WmsLogisticsCompany;
use App\Models\AdminUser;
use App\Models\BaseModel;
use Illuminate\Support\Facades\Log;

class BaseLogic
{
    const PRICE_UNIT_FEN = 1;
    const PRICE_UNIT_YUNA = 2;
    const CURRENCY_RMB = 'CNY';
    const CURRENCY_JP = 'JPY';
    const CURRENCY_US = 'USD';

    public $channel_code = '';

    public $success = true;
    public $err_msg = '';
    public $tenant_id = 0;
    public $user_id = 0;

    static $currency_maps = [
        self::CURRENCY_RMB => '人民币（分）',
        self::CURRENCY_JP => '日元',
        self::CURRENCY_US => '美分',
    ];

    public function __construct()
    {
        $this->channel_code = self::getChannelCode();
        $this->tenant_id = request()->header('tenant_id', 0);
        $this->user_id = request()->header('user_id', 0);
    }

    public function setChannelCode($code)
    {
        $this->channel_code = strtoupper($code);
    }

    //获取渠道编码
    static function getChannelCode(): string
    {
        return 'DW';
    }

    static function isTest(): bool
    {
        if (in_array(env('ENV_NAME', ''), ['dev', 'test'])) return true;
        return false;
    }

    //为方便测试
    static function testStr($str)
    {
        if (!self::isTest()) return $str;
        return $str . date('ymdHis');
    }

    //100外币兑人民币金额
    static public function exchangeRate($currency)
    {
        $url = 'http://web.juhe.cn:8080/finance/exchange/rmbquot?type=0&bank=0&key=3fc24342a063da4921305d08390f6b42';
        // $data = HttpService::get($url);
        $data = '{"error_code":0,"resultcode":"200","reason":"SUCCESSED!","result":[{"data1":{"bankConversionPri":"687.7100","date":"2023-03-29","fBuyPri":"686.8600","fSellPri":"689.7700","mBuyPri":"681.2700","mSellPri":"689.7700","name":"美元","time":"09:58:02"},"data2":{"bankConversionPri":"745.5100","date":"2023-03-29","fBuyPri":"743.7300","fSellPri":"749.2200","mBuyPri":"720.6200","mSellPri":"751.6300","name":"欧元","time":"09:58:02"},"data3":{"bankConversionPri":"87.6100","date":"2023-03-29","fBuyPri":"87.4900","fSellPri":"87.8400","mBuyPri":"86.7900","mSellPri":"87.8400","name":"港币","time":"09:58:02"},"data4":{"bankConversionPri":"5.2484","date":"2023-03-29","fBuyPri":"5.2151","fSellPri":"5.2535","mBuyPri":"5.0531","mSellPri":"5.2616","name":"日元","time":"09:58:02"},"data5":{"bankConversionPri":"848.0400","date":"2023-03-29","fBuyPri":"845.6500","fSellPri":"851.8700","mBuyPri":"819.3700","mSellPri":"855.6400","name":"英镑","time":"09:58:02"},"data6":{"bankConversionPri":"461.0100","date":"2023-03-29","fBuyPri":"459.1700","fSellPri":"462.5500","mBuyPri":"444.9000","mSellPri":"464.6000","name":"澳大利亚元","time":"09:58:02"},"data7":{"bankConversionPri":"505.8200","date":"2023-03-29","fBuyPri":"504.4400","fSellPri":"508.1600","mBuyPri":"488.5100","mSellPri":"510.4000","name":"加拿大元","time":"09:58:02"},"data8":{"bankConversionPri":"20.0800","date":"2023-03-29","fBuyPri":"20.0000","fSellPri":"20.1600","mBuyPri":"19.3800","mSellPri":"20.8000","name":"泰国铢","time":"09:58:02"},"data9":{"bankConversionPri":"517.9200","date":"2023-03-29","fBuyPri":"516.4500","fSellPri":"520.0700","mBuyPri":"500.5100","mSellPri":"522.6700","name":"新加坡元","time":"09:58:02"},"data10":{"bankConversionPri":"746.9900","date":"2023-03-29","fBuyPri":"744.9900","fSellPri":"750.2300","mBuyPri":"722.0000","mSellPri":"753.4400","name":"瑞士法郎","time":"09:58:02"},"data11":{"bankConversionPri":"100.0700","date":"2023-03-29","fBuyPri":"99.7600","fSellPri":"100.5600","mBuyPri":"96.6800","mSellPri":"101.0400","name":"丹麦克朗","time":"09:58:02"},"data12":{"bankConversionPri":"85.1500","date":"2023-03-29","fBuyPri":"85.0300","fSellPri":"85.3700","mBuyPri":"82.1800","mSellPri":"88.2100","name":"澳门元","time":"09:58:02"},"data13":{"bankConversionPri":"156.4500","date":"2023-03-29","fBuyPri":"155.9200","fSellPri":"157.3300","mBuyPri":null,"mSellPri":null,"name":"林吉特","time":"09:58:02"},"data14":{"bankConversionPri":"66.3700","date":"2023-03-29","fBuyPri":"66.1900","fSellPri":"66.7300","mBuyPri":"64.1500","mSellPri":"67.0400","name":"挪威克朗","time":"09:58:02"},"data15":{"bankConversionPri":"429.8900","date":"2023-03-29","fBuyPri":"429.1300","fSellPri":"432.1500","mBuyPri":"415.8900","mSellPri":"438.0900","name":"新西兰元","time":"09:58:02"},"data16":{"bankConversionPri":"12.6500","date":"2023-03-29","fBuyPri":"12.5700","fSellPri":"12.7300","mBuyPri":"12.1400","mSellPri":"13.2900","name":"菲律宾比索","time":"09:58:02"},"data17":{"bankConversionPri":"8.9500","date":"2023-03-29","fBuyPri":"8.8200","fSellPri":"9.1800","mBuyPri":"8.4200","mSellPri":"9.5900","name":"卢布","time":"09:58:02"},"data18":{"bankConversionPri":"66.4100","date":"2023-03-29","fBuyPri":"66.1800","fSellPri":"66.7200","mBuyPri":"64.1400","mSellPri":"67.0300","name":"瑞典克朗","time":"09:58:02"},"data19":{"bankConversionPri":"22.6500","date":"2023-03-29","fBuyPri":null,"fSellPri":null,"mBuyPri":"21.8600","mSellPri":"23.6900","name":"新台币","time":"09:58:02"},"data20":{"bankConversionPri":"0.5299","date":"2023-03-29","fBuyPri":"0.5275","fSellPri":"0.5317","mBuyPri":"0.5089","mSellPri":"0.5512","name":"韩国元","time":"09:58:02"},"data21":{"bankConversionPri":"37.9100","date":"2023-03-29","fBuyPri":"37.7200","fSellPri":"37.9800","mBuyPri":"34.8300","mSellPri":"40.9400","name":"南非兰特","time":"09:58:02"}}]}';

        $data = $data ? json_decode($data, true) : [];
        if ($data['resultcode'] == '200') {
            $data = $data['result'][0];
            $maps = [
                self::CURRENCY_JP => '日元',
                self::CURRENCY_US => '美元',
            ];
            $name = $maps[$currency];
            foreach ($data as $item) {
                if ($item['name'] == $name) {
                    return $item['fBuyPri'];
                }
            }
        }
        return 0;
    }

    public function html2pdf($html)
    {
        $mpdf = new \Mpdf\Mpdf(['tempDir' => storage_path('tempdir')]);
        //设置中文字体
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        //水印
        $mpdf->showWatermarkText = false;
        $mpdf->WriteHTML($html);
        // I 在线预览、 D 下载模式、 F 生成后保存服务器、 S 返回字符串，此模式下$filename会被忽略
        $data = $mpdf->Output('a.pdf', 'S');
        return $data;
    }

    static function dw_code()
    {
        return 'DW';
    }

    static function goat_code()
    {
        return 'GOAT';
    }

    static function stockx_code()
    {
        return 'STOCKX';
    }

    static function carryme_code()
    {
        return 'CARRYME';
    }

    static function log($tag, $info)
    {
        Log::channel('daily2')->info($tag, $info);
    }

    static function requestLog($tag, $info)
    {
        Log::channel('request_log')->info($tag, $info);
    }

    function setErrorMsg($msg)
    {
        $this->err_msg = $msg;
        $this->success = false;
        return false;
    }

    function commonWhere($params, $model)
    {
        if ($params['WHERE'] ?? []) {
            if (is_string($params['WHERE'])) $params['WHERE'] = json_decode($params['WHERE'], true);
            $raw = $model->jsonWhere($params['WHERE']);
            if ($raw) {
                $model = $model->whereRaw($raw);
            }
            return $model;
        }
        return $model;
    }

    // 格式化展示仓库，操作人、操作时间
    static function infoFormat(&$info)
    {
        if (isset($info['warehouse'])) {
            if (is_string($info['warehouse'])) {
                $info['warehouse_name'] = $info['warehouse'];
            } else {
                $info['warehouse_name'] = $info['warehouse']['warehouse_name'] ?? '';
                $info['warehouse'] = $info['warehouse']['warehouse_name'] ?? '';
            }
        }
        if (isset($info['order_user']))
            $info['order_user'] = is_string($info['order_user']) ? $info['order_user'] : ($info['order_user']['username'] ?? '');
        if (isset($info['check_user']))
            $info['check_user'] = is_string($info['check_user']) ? $info['check_user'] : ($info['check_user']['username'] ?? '');
        if (isset($info['manager']))
            $info['manager'] = is_string($info['manager']) ? $info['manager'] : ($info['manager']['username'] ?? '');
        if (isset($info['settled_user']))
            $info['settled_user'] = is_string($info['settled_user']) ? $info['settled_user'] : ($info['settled_user']['username'] ?? '');

        if (isset($info['brand'])) {
            $info['brand_name'] = is_string($info['brand']) ? $info['brand'] : ($info['brand']['name'] ?? '');
            unset($info['brand']);
        }

        $info['create_user'] = $info['create_user']['username'] ?? '';
        $info['admin_user'] = $info['admin_user']['username'] ?? '';
        $info['created_at'] = date('Y-m-d H:i:s', strtotime($info['created_at']));

        if (isset($info['updated_at']))
            $info['updated_at'] = date('Y-m-d H:i:s', strtotime($info['updated_at']));

        if (isset($info['source'])) {
            $info['source_txt'] = '手工创建';
        }
    }


    // 格式化展示商品信息
    static function sepBarFormat(&$item)
    {
        if (isset($item['quality_type'])) {
            $item['quality_type_txt'] = $item['quality_level'] == 'A' ? '正品' : '瑕疵';
        }
        if (isset($item['supplier'])) {
            $item['supplier'] = $item['supplier']['name'] ?? '';
        }
        if (isset($item['warehouse']) && is_array($item['warehouse'])) {
            $item['warehouse_name'] = $item['warehouse']['warehouse_name'] ?? '';
            unset($item['warehouse']);
        }

        $item['sku'] = $item['spec_bar']['sku'] ?? '';
        $item['sku_code'] = $item['spec_bar']['code'] ?? '';
        $item['spec_one'] = $item['spec_bar']['spec_one'] ?? '';
        $item['barcode_type'] = $item['spec_bar']['type'] ?? 0;
        if (empty($item['spec_bar']['product']['id'])) {
            $item['name'] = '';
            $item['product_sn'] = '';
            $item['img'] = '';
            $item['category_id'] = 0;
        } else {
            $item['name'] = $item['spec_bar']['product']['name'];
            $item['product_sn'] = $item['spec_bar']['product']['product_sn'];
            $item['category_id'] = $item['spec_bar']['product']['category_id'];
            $item['img'] = $item['spec_bar']['product']['img'];
        }

        unset($item['spec_bar']);
    }

    static function exportFormat(&$item, $array)
    {
        foreach ($array as $key) {
            $item[$key] = $item[$key] . "\t";
        }
    }


    // 过滤为空的参数
    static function filterEmptyData($arr, $fields)
    {
        $res = [];
        foreach ($fields as $key) {
            if ($arr[$key] ?? '') $res[$key] = $arr[$key];
        }
        return $res;
    }

    static function cloumnOptions($maps): array
    {
        $data = [];
        foreach ($maps as $value => $label) {
            $data[] = [
                'label' => $label, 'value' => $value
            ];
        }
        return $data;
    }

    static function companyOptions(): array
    {
        return WmsLogisticsCompany::selectRaw('company_code as value,company_name as label')->get()->toArray();
    }

    static function warehouseOptions(): array
    {
        return Warehouse::selectRaw('warehouse_code as value,warehouse_name as label')->get()->toArray();
    }

    static function adminUsers($key = '')
    {
        $model = AdminUser::where('tenant_id', ADMIN_INFO['tenant_id']);
        if ($key) {
            return $model->selectRaw('id as value,username as label')->get()->keyBy($key)->toArray();
        }
        return $model->selectRaw('id as value,username as label')->get()->toArray();
    }

    static function serachFormat(&$list, $export, $model)
    {
        $list = json_decode(json_encode($list), true);
        foreach ($list['data'] as &$item) {
            self::infoFormat($item);
        }

        if ($export) {
            $list['export_config'] = $model->exportConfig();
        } else {
            $list['column'] = $model->showColumns();
        }
    }

    function _search($params, $model, $export = false, $callback = null)
    {
        $export_config = [];
        $column = [];
        if ($export) {
            $export_config = $model->exportConfig($export, $params['export_type'] ?? 2);
        } else {
            $column = $model->showColumns();
        }
        $size = $params['size'] ?? 10;
        $cur_page = $params['cur_page'] ?? 1;
        $select = ['*'];
        $model = $this->commonWhere($params, $model);
        if ($params['ids'] ?? '') {
            $ids = explode(',', $params['ids']);
            $model = $model->whereIn('id', $ids);
        }
        if ($callback) $model = call_user_func($callback, $model, $params);
        $list = $model->paginate($size, $select, 'page', $cur_page);

        $list = json_decode(json_encode($list), true);
        foreach ($list['data'] as &$item) {
            self::infoFormat($item);
        }
        $list['export_config'] = $export_config;
        $list['column'] = $column;
        return $list;
    }

    // 条形码是普通产品
    static function barcodeIsNormal($barcode)
    {
        $find = ProductSpecAndBar::where('bar_code', $barcode)->first();
        if ($find && $find->type == 2) return true;
        return false;
    }

    // 条形码是唯一码商品
    static function barcodeIsUnique($barcode)
    {
        $find = ProductSpecAndBar::where('bar_code', $barcode)->first();
        if ($find && $find->type == 1) return true;
        return false;
    }

    // 
    /**
     * 验证输入的产品是否跟传入的商品类型一致
     *
     * @param array $params
     * @return bool
     */
    function productTypeVerify($params)
    {
        // 唯一码产品
        if ($params['type'] == 1) {
            $where = self::filterEmptyData($params, ['warehouse_code', 'uniq_code']);
            $good = Inventory::where($where)->orderBy('id', 'desc')->first();
            if (!$good) {
                $this->setErrorMsg(__('tips.uniq_not_exist'));
                return false;
            }
            if ($good->product && $good->product->type == 1) return true;
            $this->setErrorMsg(__('tips.not_uniq'));
            return false;
        }

        // 普通产品
        if ($params['type'] == 2) {
            $find = ProductSpecAndBar::where('bar_code', $params['bar_code'])->first();
            if ($find && $find->type == 2) return true;
            $this->setErrorMsg(__('tips.not_normal'));
            return false;
        }

        return false;
    }

    // 给搜索信息追加字段类型和操作符信息
    static function searchColumnAppend(&$item)
    {
        if ($item['statusOPtion'] ?? []) $item['type'] = 'select';
        if (!($item['type'] ?? '')) $item['type'] = 'string';
        // eq等于 notEq不等于 leftContain左包含 rightContain右包含 contain包含 in包括 notIn不包括 isNull为空 isNotNull不为空
        // gt大于 egt大于等于 lt小于 elt小于等于 activeTime动态时间
        $arr = [];
        switch ($item['type']) {
            case 'string':
                $arr = [ 'contain', 'eq', 'neq', 'leftContain', 'rightContain', 'isNull', 'isNotNull'];
                break;
            case 'date':
                $arr = ['gt', 'egt', 'lt', 'elt', 'activeTime'];
                break;
            case 'select':
                $arr = ['eq', 'in', 'notIn'];
                break;
            case 'text':
                $arr = ['eq', 'in', 'notIn'];
                break;
        }
        foreach ($arr as $key) {
            $item['operators'][] = ['value' => $key, 'label' => __('admin.operator.' . $key)];
        }

        $item['activeTimeOptions'] = [];
        if ($item['type'] == 'date') {
            $tmp = ['yesterday', 'today', 'thisWeek', 'thisMonth'];
            foreach ($tmp as $key) {
                $item['activeTimeOptions'][] = ['value' => $key, 'label' => __('admin.operator.' . $key)];
            }
        }
    }
}
