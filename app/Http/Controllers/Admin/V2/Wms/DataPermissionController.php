<?php

namespace App\Http\Controllers\Admin\V2\Wms;

use App\Http\Controllers\Admin\V1\BaseController;
use App\Logics\wms\DataPermission;
use Illuminate\Http\Request;

/**
 * 数据权限
 */
class DataPermissionController extends BaseController
{
    // 查询
    function org(Request $request)
    {
        $logic = new DataPermission();
        $data = $logic->getOrgOptions($request->all());
        return $this->success($data);
    }

    // 查询
    function search(Request $request)
    {
        $logic = new DataPermission();
        $data = $logic->search($request->all());
        return $this->success($data);
    }

    // 导出
    function export(Request $request)
    {
        $this->_export($request, function ($params) {
            $logic = new DataPermission();
            $res = $logic->search($params, true);
            return $res;
        });
        // $params = $request->all();
        // $params['size'] = 1000;
        // $logic = new DataPermission();
        // $res = $logic->search($params, true);
        // return $this->exportOutput($res);
    }


    // 详情
    function info(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
        ]);
        $logic = new DataPermission();
        $data = $logic->getOrgInfo($params);
        return $this->output($logic, $data);
    }

    // 新增
    function add(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'name' => 'required',
            'parent_id' => 'required',
        ]);
        $logic = new DataPermission();
        $data = $logic->addOrg($request->all());
        return $this->output($logic, $data);
    }


    // 保存
    function save(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'id' => 'required',
        ]);
        $logic = new DataPermission();
        $data = $logic->saveOrg($request->all());
        return $this->output($logic, $data);
    }

    // 用户列表
    function users(Request $request)
    {
        $params = $request->all();
        $logic = new DataPermission();
        $data = $logic->users($request->all());
        return $this->output($logic, $data);
    }

    // 删除
    function del(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'ids' => 'required|array',
        ]);
        $logic = new DataPermission();
        $data = $logic->delOrgs($request->all());
        return $this->output($logic, $data);
    }

    // 用户授权
    function authorizeUser(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'org_ids' => 'required|array',
            'user_ids' => 'required|array',
        ]);
        $params['tenant_id'] = ADMIN_INFO['tenant_id'];
        $params['user_id'] = ADMIN_INFO['user_id'];
        $logic = new DataPermission();
        $data = $logic->authorize($params);
        return $this->output($logic, $data);
    }

    // 状态更新
    function statusUpdate(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'status' => 'required|in:0,1',
            'ids' => 'required',
        ]);
        $logic = new DataPermission();
        $data = $logic->statusUpdate($request->all());
        return $this->output($logic, $data);
    }

    function authDel(Request $request){
        $params = $request->all();
        $this->validateParams($params, [
            'ids' => 'required',
        ]);
        $logic = new DataPermission();
        $data = $logic->authDel($request->all());
        return $this->output($logic, $data);
    }
}
