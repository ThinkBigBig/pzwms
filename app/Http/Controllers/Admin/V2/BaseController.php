<?php

namespace App\Http\Controllers\Admin\V2;

use App\Logics\FreeTaxLogic;
use App\Models\AdminUser;
use Dingo\Api\Routing\Helpers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Auth;
use App\Http\Service\ExcelService;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ExportView;
use App\Handlers\Export as HandlersExport;
use App\Imports\Import;
use App\Http\Service\TenantCodeService;
use App\Logics\BaseLogic;
use App\Models\Admin\V2\Warehouse;
use App\Models\Admin\V2\WmsShop;

class BaseController extends Controller
{
    // 接口帮助调用
    use Helpers;
    protected  $importName = '\App\Imports\Import';
    protected  $exportName = '\App\Exports\DefaultStylesExport';

    public  $model;
    protected  $required = [];
    protected $exportAll = null;
    protected $exportField;

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

    // 错误播放提示音方法
    public function playError($msg = '', $data = [])
    {
        return response()->json([
            'code' => self::PLAY_ERR,
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

    // public  function getModelIns(){
    //     if(!$this->model){
    //         dump('cre');
    //         $this->model = new $this->BaseModels();
    //     }
    //     return $this->model;
    // }

    public function __construct(Request $request)
    {
        $this->model = new $this->BaseModels();
        parent::__construct($request);
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
        //修改参数
        if (method_exists($this, '_listBefore')) $res_request = $this->_listBefore($request);
        if (isset($res_request)) $request =  $res_request;

        $data = $request->all();
        $cur_page   = !empty($data['cur_page']) ? $data['cur_page'] : 1;
        $size       = !empty($data['size']) ? $data['size'] : 10;

        //修改参数  request要存在或者BUWhere存在

        if (!empty($data['WHERE'])) {
            $data['WHERE'] = json_decode($data['WHERE'], true);
        }
        if (!empty($data['ORDER'])) {
            $this->BLOrder = array_merge(json_decode($data['ORDER'], true), $this->BLOrder);
        }
        // if (!empty($data['SHOW'])) {
        //     $this->BL=json_decode($data['SHOW'],true);

        // }

        foreach ($this->BLWhere as $B_k => $B_v) {
            // var_dump($B_k[1]);exit;
            if ($B_k == 'NUMBER') {
                $number = $request->get('number');
                if ($number) {
                    if ($number) {
                        $where_data[] = [$B_k, $B_v[0], $number];
                    }
                }
            }
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
        if (!$request->header('export')) $RData['column'] = $this->getColumn();
        return  $this->success($RData, __('base.success'));
    }

    /**
     *基础取单个值方法
     *
     * @param Request $request
     * @return  \Illuminate\Http\JsonRespons;
     */
    public function BaseOne(Request $request)
    {
        $where_data = [];
        if ($request->get('code')) $this->BaseOneVat = ['id' => 'required_without:code', 'code' => 'required_without:id'];
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
        if (!empty($data['code'])) {
            $code_field  =  isset($this->code_field) ? $this->code_field : 'code';
            $this->BOWhere[$code_field] = ['=', ''];
            $where_data[] = [
                $code_field,
                '=',
                $data['code']
            ];
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
        $create_data = [];
        $msg = !empty($this->BaseCreateVatMsg) ? $this->BaseCreateVatMsg : [];
        $validator = Validator::make($request->all(), $this->BaseCreateVat, $msg);
        if ($validator->fails()) return $this->errorBadRequest($validator);
        $data = $request->all();
        $repeatRes = $this->setRepeat($request);
        if (is_object($repeatRes)) return $repeatRes;

        //根据配置传入参数
        foreach ($this->BaseCreate as $k => $v) {
            if (isset($data[$k]) && $data[$k] !== '') {
                $create_data[$k] = $data[$k];
            }
            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type' && $v[1] == 'time') {
                $create_data[$k] = time();
            }
            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type' && $v[1] == 'date') {
                $create_data[$k] = date('Y-m-d H:i:s');
            }
            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type' && $v[1] == 'user_id') {
                $create_data[$k] = $request->header('user_id');
            }
            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type' && $v[1] == 'user_name') {
                $create_data[$k] = $request->header('user_name');
            }
        }
        //修改参数  request要存在或者BUWhere存在
        if (method_exists($this, '_createFrom')) $create_data = $this->_createFrom($create_data);
        if (is_object($create_data)) return $create_data;

        $id = (new $this->BaseModels)->BaseCreate($create_data);
        if (is_array($id)) {
            if (!$id[0]) return $this->error($id[1]);
            else return $this->success([], $id[1]);
        }
        if ($id) {
            $create_data['id'] = $id;
            //创建完成后钩子函数
            if (method_exists($this, '_createAfter')) $create_after = $this->_createAfter($create_data);
            if (isset($create_after) && is_object($create_after)) return $create_after;
            $admin_id = Auth::id();
            DB::table('admin_logs')->insert([
                'admin_id' => $admin_id ?? ADMIN_INFO['user_id'],
                'log_url' => empty($data['s']) ? '' : $data['s'],
                'log_ip' => get_ip(),
                'log_info' => '新增内容',
                'log_time' => date('Y-m-d H:i:s'),
                'log_info_details' => json_encode($create_data, true)
            ]);
            return  $this->success($create_data, __('base.success'));
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
        if (method_exists($this, '_updateBefore')) $update_before = $this->_updateBefore($data);
        if (isset($update_before) && is_object($update_before)) return $update_before;
        $repeatRes = $this->setRepeat($request, 1);
        if (is_object($repeatRes)) return $repeatRes;
        //根据配置传入参数
        foreach ($this->BaseUpdate as $k => $v) {
            if (isset($data[$k]) && $data[$k] !== '') {
                $update_data[$k] = $data[$k];
            }
            if (!empty($v) && empty($v[1])) {
                $update_data[$k] = $v;
            }
            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type' && $v[1] == 'time') {
                $update_data[$k] = time();
            }
            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type' && $v[1] == 'date') {
                $update_data[$k] = date('Y-m-d H:i:s');
            }
            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type' && $v[1] == 'user_id') {
                $update_data[$k] = $request->header('user_id');
            }
            if (!empty($v[0]) && !empty($v[1]) && $v[0] == 'type' && $v[1] == 'user_name') {
                $update_data[$k] = $request->header('user_name');
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
        if (is_object($update_data)) return $update_data;
        $RData = (new $this->BaseModels)->BaseUpdate($where_data, $update_data);
        if ($RData) {
            $admin_id = Auth::id();
            $update_data['where'] = $where_data;
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
            return  $this->error('无更新');
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
            $admin_id = Auth::id();
            DB::table('admin_logs')->insert([
                'admin_id' => $admin_id,
                'log_url' => empty($data['s']) ? $request->getUri() : $data['s'],
                'log_ip' => get_ip(),
                'log_info' => '删除内容',
                'log_time' => date('Y-m-d H:i:s'),
                'log_info_details' => json_encode(['id'=>$ids,'d_data'=>$request->get('d_data')], true)
            ]);
            return $this->success();
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

    public function checkRepeat($field, $value)
    {
        return (new $this->BaseModels)->where($field, $value)->get()->isEmpty();
    }

    public function setRepeat(Request $request, $is_update = false)
    {
        $data = $request->all();
        if (empty($this->repeat)) return true;
        $fname = array_keys($this->BUWhere)[0];
        foreach ($this->repeat as $field) {
            if (!empty($data[$field])) {
                if ($is_update) {
                    $isEmpty = (new $this->BaseModels)->where($field, $data[$field])->where($fname, '<>', $data[$fname])->get()->isEmpty();
                    if (!$isEmpty) {
                        return $this->vdtError($field . ' exists');
                    }
                } else {
                    if (!$this->checkRepeat($field, $data[$field])) {
                        return $this->vdtError($field . ' exists');
                    }
                }
            }
        }
        return true;
    }


    public static function getErpCode($pre = 'WZ', $len = 10, $date = true)
    {
        if ($date) $pre =  $pre . date('ymd');
        $id = TenantCodeService::createOnlyId();
        $code = TenantCodeService::generateNumber($id, $pre, $len);
        return $code;
    }

    protected function setExcelField()
    {
        return [];
    }
    protected function _beforeExport($request, $field = 'setExcelField', $update = [])
    {
        $request->headers->set('export', 1);
        if (empty($field)) $field = 'setExcelField';
        $this->$field();

        if (!$update) {
            if (method_exists($this, '_exportFiledEdit')) $update = $this->_exportFiledEdit();
        }
        if ($update) {
            $rData = [];
            foreach ($this->exportField as $u_k => $u_v) {
                if (isset($update[$u_v])) {
                    $rData[$update[$u_v]] = $u_v;
                    unset($update[$u_v]);
                } else $rData[$u_k] = $u_v;
            }
            if ($update) {
                foreach ($update as $a_k => $a_v) {
                    $rData[$a_k] = $a_v;
                }
            }
            $this->exportField = $rData;
        }
        $params = $this->exportFormat();

        $ids = $request->get('ids');
        if (!empty($params['detail']['with_as'])) {
            $request->merge(['with_as' => isset($params['detail']['with'])?$params['detail']['with']:$params['detail']['with_as']]);
        }
        if ($ids) {
            if (is_string($ids)) $ids = explode(',', $ids);
            // $where_data[]=['id','in',$ids];
            if (is_array($this->BLWhere)) $this->BLWhere['id'] = ['in', $ids];
            $size = count($ids);
            $request->merge(['size' => $size]);
        } else {
            if ($ids !== null) {
                $request->merge(['size' => 15000]);
                // $this->exportAll = 'all';
            }
        }
        return $params;
    }

    //导出excel
    public function Export(Request $request)
    {
        $this->_export($request, function ($request) {
            $params = $this->_beforeExport($request);
            return  $this->BaseLimit($request)->getData(1);
        });

        // $params = $this->_beforeExport($request);
        // $res = $this->BaseLimit($request)->getData(1);
        // if ($res['code'] != 200) return $this->error($res['msg']);
        // $data = $res['data']['data'];
        // ob_end_clean();
        // ob_start();
        // return  Excel::download((new $this->exportName($data, $params)), $this->NAME . date('YmdHis') . mt_rand(100, 999) . '.xlsx');
    }


    public function ExportByMaatwebsite(Request $request)
    {
        
        $params = $this->_beforeExport($request);
        $res = $this->BaseLimit($request)->getData(1);
        if ($res['code'] != 200) return $this->error($res['msg']);
        $data = $res['data']['data'];
        ob_end_clean();
        ob_start();
        return  Excel::download((new $this->exportName($data, $params)), $this->NAME . date('YmdHis') . mt_rand(100, 999) . '.xlsx');

        // $res = Excel::download((new $this->exportName($data, $params)), $this->NAME . date('YmdHis') . mt_rand(100, 999) . '.xlsx');
        // $buf = base64_encode($res->getFile()->getContent());
        // // 设置响应头
        // $response = response($res->getFile()->getContent(), 200);
        // $response->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        // $response->header('Content-Disposition', 'attachment; filename="' .  $this->NAME . date('YmdHis') . mt_rand(100, 999) . '.xlsx');
        // return $response;
    }

    //导出模版
    public function oExample(Request $request)
    {
        $this->setExcelField();
        ob_end_clean();
        ob_start();
        return  Excel::download((new $this->exportName([], $this->exportFormat())), $this->NAME . '-导出模板文件' . '.xlsx');
    }

    //导入模版
    public function iExample(Request $request)
    {
        $this->setExcelField();
        ob_end_clean();
        ob_start();
        return  Excel::download((new $this->exportName([], $this->importFormat())), $this->NAME . '-导入模板文件' . '.xlsx');
    }


    public static function obClear()
    {
        ob_end_clean();
        ob_start();
    }


    public function exportFormat()
    {
        $name = $this->NAME;
        $format = [
            // '分类名称'=>['color'=>'red']
        ];
        return [
            'name' => $name,
            'format' => $format,
            'field' => $this->exportField,
        ];
    }

    //导出模版
    public function Example(Request $request)
    {
        $this->setExcelField();
        // return  Excel::download((new Export([$this->exportField], [])), $this->NAME . '-模板文件.xlsx');
        ob_end_clean();
        ob_start();

        return Excel::download(new ExportView('exports.common', [
            'headers' => [$this->importFields],
            'data' => [],
            'title' => $this->NAME,
        ], $this->NAME, []), $this->NAME . '-模板文件.xlsx');
    }

    public function importFormat()
    {
        $count = 2;
        $field = $this->exportField ?? [];
        $rule = [
            'required' => [],
            'auto' => [
                'created_user' => request()->header('user_id'),
            ],
            'uniq' => '',

        ];
        return [
            'count' => $count,
            'rule' => $rule,
            'field' => $field,
        ];
    }
    //导入
    public function Import(Request $request)
    {
        // $request->validate([
        //     'file' => 'required|mimes:csv,xlsx',
        // ]);
        // $path = $request->file('file')->getRealPath();
        $file = $request->file('file');
        $destinationPath = '/uploads/admin/excel/'; // public文件夹下面uploads/xxxx-xx-xx 建文件夹
        $extension = $file->getClientOriginalExtension();   // 上传文件后缀
        // $fileName = date('YmdHis').mt_rand(100,999).'.'.$extension; // 重命名
        $fileName = $file->getClientOriginalName();
        $status = $file->move(public_path() . $destinationPath, $fileName); // 保存图片
        if (!$status) {
            $this->error();
        }
        // var_dump(public_path().$destinationPath.$fileName);exit;
        $path = public_path() . $destinationPath . $fileName;
        // $relationMap = isset($this->relationMap) ? $this->relationMap : null;
        if (method_exists($this, 'setExcelField')) $this->setExcelField();
        $import = new $this->importName(new $this->BaseModels, $this->importFormat());
        Excel::import($import, $path);
        // \Maatwebsite\Excel\Excel 
        if ($import->code != 200) return $this->error($import->error);
        if (method_exists($this, 'afterImport')) $this->afterImport();
        return  $this->success([], '导入成功');
    }

    //获取字段信息
    public function getColumn()
    {
        $model = new $this->BaseModels();
        $map = $model->excelSetAttr();
        $setExcelField = request()->get('setExcelField', 'setExcelField');
        if (method_exists($this, $setExcelField)) $this->$setExcelField();
        $col = empty($this->exportField) ? [] : $this->exportField;
        $column = [];
        foreach ($col as $k => $v) {
            $temp = [];
            //时间返回类型
            if (substr($k, -3) == '_at' || substr($k, -5) == '_time' || substr($k, -8) == 'deadline') $temp['type'] = 'date';
            if (substr($k, -4) == '_txt') $k = substr($k, 0, -4);
            $temp['value'] = $k;
            $temp['label'] = $v;
            if (!empty($map[$k])) {
                // $temp['map']=$map[$k];
                foreach ($map[$k] as $k => $v) {
                    $temp['statusOPtion'][] = [
                        'label' => $k,
                        'value' => $v,
                    ];
                }
            }
            if (substr($k, -14) == 'warehouse_name' || substr($k, -9) == 'warehouse') {
                $warehouse = $this->model::_getRedisMap('warehouse_map');
                foreach ($warehouse as $warehouse_code => $warehouse_name) {
                    $temp['statusOPtion'][] = [
                        'label' => $warehouse_name,
                        'value' => $warehouse_name,
                        //  'label' => $item['warehouse_name'],
                        //  'value' => $item['warehouse_name'],
                    ];
                }
            }
            if (substr($k, -14) == 'warehouse_code') {
                $warehouse = $this->model::_getRedisMap('warehouse_map');
                foreach ($warehouse as  $warehouse_code => $warehouse_name) {
                    $temp['statusOPtion'][] = [
                        'label' => $warehouse_name,
                        'value' => $warehouse_code,
                    ];
                }
            }
            BaseLogic::searchColumnAppend($temp);
            // if(substr($k, -14) == 'shop_code'){
            //     $warehouse = WmsShop::where('status',1)->select('code','name')->get();
            //     foreach ($warehouse as $item) {
            //         $temp['statusOPtion'][] = [
            //             'label' =>$item['name'],
            //             'value' => $item['code'],
            //         ];
            //     }
            // }

            $column[] = $temp;
        }
        return $column;
    }

    protected function modelReturn($method, $params, $error = 'error')
    {
        if (method_exists($this->model, $method)) {
            list($res, $msg) = $this->model->$method(...$params);
            if (!$res) return $this->$error($msg);
            if (is_array($msg)) return $this->success($msg);
            return $this->success();
        }
    }

    public function vatReturn($request, $funcVat, $msg = [])
    {
        $validator = Validator::make($request->all(), $funcVat, $msg);
        if ($validator->fails()) abort($this->errorBadRequest($validator));
        return $request->all();
    }

    //修改旗帜
    public function editFlag(Request $request)
    {
        $vat = [
            'ids' => 'required',
            'flag' => 'required|integer|min:0|max:7',
        ];
        $data = $this->vatReturn(request(), $vat);
        if (!is_array($data['ids'])) $data['ids'] = explode(',', $data['ids']);
        $this->model->whereIn('id', $data['ids'])->update(['flag' => $data['flag']]);
        return $this->success();
    }

    function _export($request, $callback)
    {
        $page = 1;
        $name = '';
        $export = null;
        while (true) {
            $request->merge(['cur_page' => $page, 'size' => 50000]);
            $res = $callback($request);
            if ($res['code'] != 200) return $this->error($res['msg']);
            $headers = [];
            $current_page = $data['current_page'] ?? 1;
            $data = $res['data']['data'] ?? [];
            if (!$name) {
                $name = sprintf('%s_%s', $this->NAME, date('YmdHis'));
                $export = new HandlersExport(1, $name);
                $headers[0] = [$this->NAME];
                $headers[1] = array_values($this->exportField);
            }
            $keys = array_keys($this->exportField);
            // 数据转存成临时文件
            $export->data2Tmp($data, $headers, $keys, $current_page);

            if (!($res['data']['total'] ?? 0) || $page >= 10) break;
            $page++;
        }

        // 临时文件导出
        if ($export) $export->tmp2File();
    }

    //修改备注
    public function editRemark(Request $request)
    {
        $vat = [
            'ids' => 'required',
            // 'remark' => 'required',
        ];
        $remark = $request->get('remark', '');
        if (empty($remark)) return $this->success();
        $data = $this->vatReturn(request(), $vat);
        if (!is_array($data['ids'])) $data['ids'] = explode(',', $data['ids']);
        $this->model->whereIn('id', $data['ids'])->update(['remark' => $remark]);
        return $this->success();
    }
}
