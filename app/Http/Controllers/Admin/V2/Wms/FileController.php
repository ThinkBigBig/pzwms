<?php

namespace App\Http\Controllers\Admin\V2\Wms;

use App\Http\Controllers\Admin\V1\BaseController;
use App\Logics\wms\File;
use Illuminate\Http\Request;

/**
 * 文件管理
 */
class FileController extends BaseController
{
    // 目录结构
    function dirs(Request $request)
    {
        $params = $request->all();
        $logic = new File();
        $data = $logic->dirs($params);
        return $this->success($data);
    }

    // 文件列表
    function list(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'parent_id' => 'required',
        ]);
        $logic = new File();
        $data = $logic->list($params);
        return $this->success($data);
    }

    // 新建文件夹
    function newDir(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'name' => 'required',
        ]);
        $logic = new File();
        $data = $logic->newDir($params);
        return $this->success($data);
    }

    // 上传文件
    function upload(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'parent_id' => 'required',
            // 'files.*' => 'required|mimes:pdf',
        ]);
        $logic = new File();
        $data = $logic->upload($params);
        return $this->success($data);
    }

    // 删除文件/文件夹
    function del(Request $request)
    {
        $params = $request->all();
        $this->validateParams($params, [
            'ids' => 'required',
        ]);
        $logic = new File();
        $data = $logic->del($params);
        return $this->success($data);
    }
}
