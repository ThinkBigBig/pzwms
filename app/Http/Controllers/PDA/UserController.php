<?php

namespace App\Http\Controllers\PDA;

use App\Handlers\OSSUtil;
use App\Logics\Common;
use Illuminate\Http\Request;

class UserController extends BaseController
{
    // 上传文件
    function upload(Request $request)
    {
        $params = $request->input();
        $this->validateParams($params, [
            'type' => 'required|in:1,2,3',
        ]);
        $files = $request->file('files', null);
        if (empty($files)) return $this->error('请上传文件');
        $map = [
            1 => 'qc', //质检图片
            2 => 'avatar', //用户头像
            3 => 'product', //商品图片
        ];

        $fail = 0;
        $success = [];
        foreach ($files as $file) {
            $oss = new OSSUtil();
            $ext = $file->getClientOriginalExtension();
            $name = sprintf('%s/%s/%s.%s', $map[$params['type']], ADMIN_INFO['tenant_id'], time() . rand(100, 999), $ext);
            $res = $oss->addFileByData($name, file_get_contents($file));
            if (!$res) $fail++;
            $success[] = $res;
        }
        if ($success) {
            $msg = $fail > 0 ? sprintf('fail num:%d', $fail) : 'success';
            return $this->success($success, $msg);
        }
        return $this->error('Upload failed');
    }
}
