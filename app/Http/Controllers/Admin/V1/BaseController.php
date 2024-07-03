<?php

namespace App\Http\Controllers\Admin\V1;

use App\Logics\FreeTaxLogic;
use App\Models\AdminUser;
use Dingo\Api\Routing\Helpers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Dingo\Api\Exception\ValidationHttpException;
use APP\Exceptions\ApiErrDesc;
use App\Exports\ExportView;
use App\Handlers\Export;
use App\Http\Service\TenantCodeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class BaseController extends Controller
{
    // 接口帮助调用
    use Helpers;
    public $model = null;

    // 返回错误的请求
    protected function errorBadRequest($validator)
    {
        $result = [];
        $messages = $validator->errors()->toArray();
        if ($messages) {
            foreach ($messages as $field => $errors) {
                // var_dump($errors);exit;
                foreach ($errors as $error) {
                    $result[] = [
                        'field' => $field,
                        'code' => $error,
                    ];
                }
            }
        }
        $msg = !empty($result[0]['code']) ? $result[0]['code'] : '';
        return  $this->VdtError($msg);
    }
    // 验权不通过
    public function permissionError($data = [], $msg = '')
    {
        return response()->json([
            'code' => self::PERMISSION,
            'msg' => !empty($msg) ? $msg : __('base.permissionError'),
            'time' => time(),
            'data' => $data
        ]);
    }

    // 请求成功时对数据进行格式处理
    public function success($data = [], $msg = '')
    {
        return response()->json([
            'code' => self::SUCCESS,
            'msg' => !empty($msg) ? $msg : __('base.success'),
            'time' => time(),
            'data' => $data
        ]);
    }

    // 响应失败时返回自定义错误信息
    public function responseError($msg = '', $data = [])
    {
        return response()->json([
            'code' => self::ERROR_SPECIAL,
            'msg' => !empty($msg) ? $msg : __('base.error'),
            'time' => time(),
            'data' => $data,
        ]);
    }

    // 响应校验失败时返回自定义的信息
    public function vdtError($msg = '', $data = [])
    {
        return response()->json([
            'code' => self::VAlID,
            'msg' => !empty($msg) ? $msg : __('base.vdt'),
            'time' => time(),
            'data' => $data,
        ]);
    }

    // 错误提示方法
    public function error($msg = '', $data = [])
    {
        return response()->json([
            'code' => self::ERROR,
            'msg' => !empty($msg) ? $msg : __('base.error'),
            'time' => time(),
            'data' => $data,
        ]);
    }

    protected $params_error = '';
    public function validateParamsAdmin($data, $rules)
    {
        $this->params_error = '';
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            $this->params_error = $validator->errors()->first() ?: __('base.error');
            return false;
        }
        return true;
    }


    /**
     *基础取全部值方法
     *
     * @param Request $request
     * @return void
     */
    public function BaseAll(Request $request)
    {
        $where_data = [];
        $msg = !empty($this->BaseAllVatMsg) ? $this->BaseAllVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseAllVat, $msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        //修改参数  request要存在或者BUWhere存在
        foreach ($this->BAWhere as $B_k => $B_v) {
            if (!empty($B_v) && empty($B_v[1]) && isset($data[$B_k])) {
                if ($B_v[0] == 'allLike') {
                    $where_data[] = ["concat({$B_v[2]}) like ?", $B_v[0], ["%{$data[$B_k]}%"]];
                    continue;
                }
                $where_data[] = [$B_k, $B_v[0], $data[$B_k]];
                continue;
            }
            if (!empty($B_v) && (!empty($B_v[1]) || $B_v[1] === 0)) {
                $where_data[] = [$B_k, $B_v[0], $B_v[1]];
                continue;
            }
        }
        $RData = (new $this->BaseModels)->BaseAll($where_data, $this->BA, $this->BAOrder);
        if (method_exists($this, '_allFrom')) $RData = $this->_oneFrom($RData);
        return  $this->success($RData, __('base.success'));
    }

    /**
     *基础取多个值带分页方法
     *
     * @param Request $request
     * @return void
     */
    public function BaseLimit(Request $request)
    {
        $where_data = [];
        $msg = !empty($this->BaseLVatMsg) ? $this->BaseLVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseLVat, $msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        $cur_page   = !empty($data['cur_page']) ? $data['cur_page'] : 1;
        $size       = !empty($data['size']) ? $data['size'] : 10;
        //修改参数  request要存在或者BUWhere存在
        foreach ($this->BLWhere as $B_k => $B_v) {
            // var_dump($B_k[1]);exit;
            if (!empty($B_v) && empty($B_v[1]) && isset($data[$B_k])) {
                if ($B_v[0] == 'allLike') {
                    $where_data[] = ["concat({$B_v[2]}) like ?", $B_v[0], ["%{$data[$B_k]}%"]];
                    continue;
                }
                $where_data[] = [$B_k, $B_v[0], $data[$B_k]];
                continue;
            }
            if (!empty($B_v) && (!empty($B_v[1]) || $B_v[1] === 0)) {
                $where_data[] = [$B_k, $B_v[0], $B_v[1]];
                continue;
            }
        }
        $RData = (new $this->BaseModels)->BaseLimit($where_data, $this->BL, $this->BLOrder, $cur_page, $size);
        if (method_exists($this, '_limitFrom')) $RData = $this->_limitFrom($RData);
        return  $this->success($RData, __('base.success'));
    }

    /**
     *基础取单个值方法
     *
     * @param Request $request
     * @return void
     */
    public function BaseOne(Request $request)
    {
        $where_data = [];
        $msg = !empty($this->BaseOneVatMsg) ? $this->BaseOneVatMsg : ['id.required' => __('base.vdt')];
        $validator = Validator::make($request->all(), $this->BaseOneVat, $msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        //修改参数  request要存在或者BUWhere存在
        foreach ($this->BOWhere as $B_k => $B_v) {
            if (!empty($B_v) && empty($B_v[1]) && isset($data[$B_k])) {
                if ($B_v[0] == 'allLike') {
                    $where_data[] = ["concat({$B_v[2]}) like ?", $B_v[0], ["%{$data[$B_k]}%"]];
                    continue;
                }
                $where_data[] = [$B_k, $B_v[0], $data[$B_k]];
                continue;
            }
            if (!empty($B_v) && (!empty($B_v[1]) || $B_v[1] === 0)) {
                $where_data[] = [$B_k, $B_v[0], $B_v[1]];
                continue;
            }
        }
        $RData = (new $this->BaseModels)->BaseOne($where_data, $this->BO, $this->BOOrder);
        if (method_exists($this, '_oneFrom')) $RData = $this->_oneFrom($RData);
        return  $this->success($RData, __('base.success'));
    }

    /**
     *基础新增方法
     *
     * @param Request
     * @return void
     */
    public function BaseCreate(Request $request)
    {
        $checkRepeate = $request->get('checkRepeate', null);
        $create_data = [];
        $msg = !empty($this->BaseCreateVatMsg) ? $this->BaseCreateVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseCreateVat, $msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        if ($checkRepeate) {
            if (!$this->checkRepeat($checkRepeate, $data[$checkRepeate])) {
                return $this->vdtError($checkRepeate . ' exists');
            };
        }

        //根据配置传入参数
        foreach ($this->BaseCreate as $k => $v) {
            if (isset($data[$k]) && $data[$k] != '') {
                $create_data[$k] = $data[$k];
            }

            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type') {
                if (!empty($v[2])) {
                    $bf_zone = date_default_timezone_get();
                    date_default_timezone_set($v[2]);
                }
                if ($v[1] == 'time') $create_data[$k] = time();
                if ($v[1] == 'date') $create_data[$k] = date('Y-m-d H:i:s');
                if (!empty($bf_zone)) date_default_timezone_set($bf_zone);
            }
        }
        //修改参数  request要存在或者BUWhere存在
        if (method_exists($this, '_createFrom')) $create_data = $this->_createFrom($create_data);
        $id = (new $this->BaseModels)->BaseCreate($create_data);
        if ($id) {
            $data['id'] = $id;
            $admin_id = Auth::id();
            DB::table('admin_logs')->insert([
                'admin_id' => $admin_id,
                'log_url' => empty($data['s']) ? '' : $data['s'],
                'log_ip' => get_ip(),
                'log_info' => '新增内容',
                'log_time' => date('Y-m-d H:i:s'),
                'log_info_details' => json_encode($create_data, true)
            ]);
            return  $this->success($data, __('base.success'));
        } else {
            return  $this->error();
        }
    }

    /**
     *基础修改方法
     *
     * @param Request $request
     * @return void
     */
    public function BaseUpdate(Request $request)
    {
        $where_data = [];
        $update_data = [];
        $msg = !empty($this->BaseUpdateVatMsg) ? $this->BaseUpdateVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseUpdateVat, $msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        //根据配置传入参数
        foreach ($this->BaseUpdate as $k => $v) {
            if (isset($data[$k]) && $data[$k] !== '') {
                $update_data[$k] = $data[$k];
            }
            if (!empty($v) && empty($v[1])) {
                $update_data[$k] = $v;
            }

            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type') {
                if (!empty($v[2])) {
                    $bf_zone = date_default_timezone_get();
                    date_default_timezone_set($v[2]);
                }
                if ($v[1] == 'time') $update_data[$k] = time();
                if ($v[1] == 'date') $update_data[$k] = date('Y-m-d H:i:s');
                if (!empty($bf_zone)) date_default_timezone_set($bf_zone);
            }
        }
        //修改参数  request要存在或者BUWhere存在
        foreach ($this->BUWhere as $B_k => $B_v) {
            if (isset($data[$B_k]) && empty($B_v[1]) && $data[$B_k] != '') {
                $where_data[] = [$B_k, $B_v[0], $data[$B_k]];
            }
            if (!empty($B_v) && !empty($B_v[1])) {
                $where_data[] = [$B_k, $B_v[0], $B_v[1]];
            }
        }
        if (method_exists($this, '_updateFrom')) $update_data = $this->_updateFrom($update_data);
        $RData = (new $this->BaseModels)->BaseUpdate($where_data, $update_data);
        if ($RData) {
            $admin_id = Auth::id();
            DB::table('admin_logs')->insert([
                'admin_id' => $admin_id,
                'log_url' => empty($data['s']) ? '' : $data['s'],
                'log_ip' => get_ip(),
                'log_info' => '修改内容',
                'log_time' => date('Y-m-d H:i:s'),
                'log_info_details' => json_encode($update_data, true)
            ]);

            return  $this->success($data, __('base.success'));
        } else {
            return  $this->error();
        }
    }

    /**
     *基础删除方法
     *
     * @param Request $request
     * @return void
     */
    public function BaseDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required',
        ], ['ids.required' => __('base.vdt')]);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $ids = $request->ids;
        if (empty($request->name))
            $data = (new $this->BaseModels)->BaseDelete($ids);
        else
            $data = (new $this->BaseModels)->BaseDelete($ids);

        if ($data) {

            if (!empty($data['del_ids'])) $ids = $data['del_ids'];
            $admin_id = Auth::id();
            DB::table('admin_logs')->insert([
                'admin_id' => $admin_id,
                'log_url' => empty($data['s']) ? '' : $data['s'],
                'log_ip' => get_ip(),
                'log_info' => '删除内容',
                'log_time' => date('Y-m-d H:i:s'),
                'log_info_details' =>   json_encode([$ids], true)
            ]);
            if (empty($data['result'])) return $this->success();
            else if ($data['result']['code'] == 500) return $this->error($data['result']['msg']);
            else
                return $this->success([], $data['result']['msg']);
        } else {
            return  $this->error();
        }
    }

    static function isSpecial($user): bool
    {
        $special = FreeTaxLogic::specialUser();
        foreach ($special as $sp) {
            if ($user['username'] == $sp->username) return true;
        }
        return false;
    }

    /**
     * 获取用户身份
     *
     * @return array
     */
    public function getUserIdentity($id, $arr_name = 'username', $init_account = false)
    {
        // Redis::del('_get_Token');
        $token_rides_name =  $id . '-token_rides_name';
        //        $token = Redis::get($token_rides_name);
        $token = null;
        if (!empty($token)) {
            $data = json_decode($token, true);
            return $data[$arr_name];
        } else {
            $data = ['username' => [], 'id' => []];
            $user =  DB::table('admin_users')->where('id', '=', $id)->first();
            $roles = DB::table('admin_roles')->where('id', '=', $user->roles_id)->first();

            $user = objectToArray($user);
            if ($roles->type == 1) {
                $data = ['username' => [], 'id' => []];
            }
            if ($roles->type == 2) {
                $data_username =  DB::table('admin_users')->where('p_id', '=', $id)->pluck('username');
                $data_id =  DB::table('admin_users')->where('p_id', '=', $id)->pluck('id');
                $data['username'] = objectToArray($data_username);
                $data['id'] = objectToArray($data_id);
                $data['username'][] = $user['username'];
                $data['id'][]       = $user['id'];
                // var_dump( $data['id']);exit;
            }
            if ($roles->type  == 3) {
                $data['username'][] = $user['username'];
                $data['id'][]       = $user['id'];
            }
            //特殊用户的租户显示所有的在库商品信息
            if ($init_account && $roles->type == 3 && self::isSpecial($user)) {
                $data = ['username' => [], 'id' => []];
            }
            Redis::set($token_rides_name, json_encode($data, true));
            return $data[$arr_name];
        }
    }

    /**
     * 通过预约单获取出货单id
     * @param [type] $user_ids
     * @return array
     */
    public function bill_id($user_ids)
    {
        $bill_id = DB::table('pms_bonded_stock')->whereIn('admin_id', $user_ids)->pluck('bill_id');
        return  $bill_id;
    }


    /**
     * 
     */
    public function checkRepeat($field, $value)
    {
        return (new $this->BaseModels)->where($field, $value)->get()->isEmpty();
    }

    public function setRepeat(Request $request, $checkRepeate)
    {
        $request->request->add(['checkRepeate' => $checkRepeate]);
        return $request;
    }

    public function javaHeaders($ContentType = 'application/json')
    {
        $appid = env('CARRYME_APPID');
        $appSecret = env('CARRYME_APPSECRET');
        $timestamp = time();
        $sign = md5('appId=' . $appid . '&timestamp=' . $timestamp . '&key=' . $appSecret);

        $header = [
            'appId:' . $appid,
            'sign:' . $sign,
            'timestamp:' . $timestamp,
            'Content-Type:' . $ContentType,
        ];
        return $header;
    }

    public static function getErpCode($pre = 'WZ', $len = 10, $date = true)
    {
        if ($date) $pre =  $pre . date('ymd');
        $id = TenantCodeService::createOnlyId();
        $code = TenantCodeService::generateNumber($id, $pre, $len);
        return $code;
    }

    // 导入模板
    public function template(Request $request)
    {
        return $this->templateOutput($this->model);
        $name = sprintf('%s模板.xlsx', $this->filename);
        return Excel::download(new ExportView('exports.common', [
            'headers' => [$this->import_fileds],
            'data' => [],
            'title' => $this->filename,
        ], $this->filename, []), $name);
    }

    function exportOutput($res, $format_coulmns = [])
    {
        $title = $res['export_config']['title'] ?? '';
        $name = sprintf('%s_%s.xlsx', $title, date('YmdHis'));
        $type = $res['export_config']['type'] ?? 1;
        $view = $type == 1 ? 'exports.common' : 'exports.common2';
        // dd($res['data']);
        // return view($view,['data'=>[
        //     'headers' => $res['export_config']['headers'],
        //     'data' => $res['data'] ?? [],
        //     'title' => $title,
        // ]]);
        return Excel::download(new ExportView($view, [
            'headers' => $res['export_config']['headers'],
            'data' => $res['data'] ?? [],
            'title' => $title,
        ], $title, $format_coulmns), $name);
    }

    function templateOutput($model)
    {
        $res['export_config'] = $model->exportConfig(true);
        $title = $res['export_config']['title'] ?? '';
        $name = sprintf($title . '模板.xlsx', date('YmdHis'));
        return Excel::download(new ExportView('exports.common', [
            'headers' => $res['export_config']['headers'],
            'data' => [],
            'title' => $title,
        ], $title, []), $name);
    }

    function _export($request, $callback)
    {
        $export = null;
        $params = $request->all();
        $page = 1;
        $name = '';
        while (true) {
            $params['size'] = 50000;
            // 获取数据
            $res = $callback($params);
            $headers = [];
            $current_page = $data['current_page'] ?? 1;
            $data = $res['data'] ?? [];
            $config = $res['export_config'] ?? [];
            $keys = $config['keys'] ?? [];
            $headers = $config['headers'] ?? [];
            foreach ($headers as $item) {
                foreach ($item as $val) {
                    if (($val['colspan'] ?? 1) == 1) $keys[] = $val['value'];
                }
            }

            if (!$name) {
                $name = sprintf('%s_%s', $config['title'], date('YmdHis'));
                // 数据格式化
                $headers = array_merge([[[
                    "value" => "",
                    "label" => $config['title'],
                    "colspan" => count($keys),
                ]]], $headers);

                $export = new Export(2, $name);
            }
            // 数据转存
            $export->data2Tmp($data, $headers, $keys, $current_page);
            if (!($res['data']['total'] ?? 0) || $page >= 10) break;
            $page++;
        }
        
        // 转存数据导出
        if ($export) $export->tmp2File();
    }
}
