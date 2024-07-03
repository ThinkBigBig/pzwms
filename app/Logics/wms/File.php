<?php

namespace App\Logics\wms;

use App\Handlers\OSSUtil;
use App\Logics\BaseLogic;
use App\Models\Admin\V2\WmsFile;
use App\Models\Admin\V2\WmsOrder as V2WmsOrder;
use App\Models\Admin\V2\WmsShippingRequest;
use Illuminate\Support\Facades\File as FacadesFile;

/**
 * 文件
 */
class File extends BaseLogic
{
    // 新建文件夹
    function newDir($params)
    {
        $parent_id = $params['parent_id'] ?? 0;
        $parent = $parent_id ? WmsFile::find($parent_id) : null;
        $data = [
            'parent_id' => $parent ? $parent->id : 0,
            'type' => WmsFile::TYPE_DIR,
            'level' => $parent ? $parent->level + 1 : 0,
            'name' => $params['name'],
            'tenant_id' => ADMIN_INFO['tenant_id'],
        ];
        if(!empty($params['created_at']))$data['created_at']=$params['created_at'];
        $dir = WmsFile::firstOrCreate($data, [
            'created_user' => ADMIN_INFO['user_id'],
        ]);
        $path = $parent ? ($parent->path . '-' . $dir->id) : $dir->id;
        if (!$dir->path) $dir->update(['path' => $path]);
        return [['id'=>$dir->id]];
    }

    static function parentDir($params)
    {
        if ($params['parent_id']) {
            $parent = WmsFile::find($params['parent_id']);
            return $parent;
        }

        $parent = WmsFile::where('name', date('Y-m-d'))->where('parent_id', 0)->first();
        if ($parent) return $parent;

        $name = date('Y-m-d');
        $parent = WmsFile::firstOrCreate([
            'parent_id' => 0,
            'type' => WmsFile::TYPE_DIR,
            'level' => 0,
            'name' => $name,
            'tenant_id' => ADMIN_INFO['tenant_id'],
        ], [
            'created_user' => ADMIN_INFO['user_id'],
        ]);
        if (!$parent->path) $parent->update(['path' => $parent->id,]);
        return $parent;
    }

    // 上传文件
    function upload($params)
    {
        $parent = self::parentDir($params);

        $oss = new OSSUtil();
        foreach ($params['files'] as $file) {
            $file_size = FacadesFile::size($file);
            $name = $file->getClientOriginalName();

            $names = WmsFile::whereIn('id', explode('-', $parent->path ?? ''))->pluck('name')->toArray();
            if ($names) $file_path = 'wms/' . ADMIN_INFO['tenant_id'] . '/' . implode('/', $names) . '/' . $name;
            else $file_path = 'wms/' . ADMIN_INFO['tenant_id'] . '/' . $name;

            $res = $oss->addFileByData($file_path, file_get_contents($file));
            $file = WmsFile::create([
                'parent_id' => $parent->id ?? 0,
                'type' => WmsFile::TYPE_FILE,
                'level' => ($parent->level ?? 0) + 1,
                'name' => $name,
                'tenant_id' => ADMIN_INFO['tenant_id'],
                'created_user' => ADMIN_INFO['user_id'],
                'file_size' => $file_size,
                'file_path' => $res['file_path'],
            ]);
            $file->update(['path' => $parent->path . '-' . $file->id,]);
            $order_no = str_replace('.pdf', '', $name);
            V2WmsOrder::where('third_no', $order_no)->update([
                'deliver_no' => $name,
                'deliver_path' => $res['file_path'],
            ]);
            WmsShippingRequest::where(['third_no' => $order_no, 'deliver_no' => ''])->update(['deliver_no' => $name,]);
        }
        return true;
    }

    // 目录结构
    function dirs($params)
    {
        $where = ['type' => WmsFile::TYPE_DIR,];
        $model = WmsFile::where($where);
        if ($params['name'] ?? '') {
            $path = WmsFile::where($where)->where('name', $params['name'])->value('path');
            $ids = $path ? explode('-', $path) : [];
            $model->whereIn('id', $ids);
        }

        $res = $model->select(['id', 'parent_id', 'name'])->orderBy('created_at', 'desc')->get();
        $arr = $this->buildTree($res);
        return $arr;
    }

    // 定义构建树形结构的函数  
    private function buildTree($files, $parentId = 0)
    {
        $tree = [];
        foreach ($files as $file) {
            if ($file['parent_id'] == $parentId) {
                $children = $this->buildTree($files, $file['id']);
                if (!empty($children)) {
                    $file['children'] = $children;
                }
                $tree[] = $file;
            }
        }
        return $tree;
    }

    // 文件列表
    function list($params)
    {
        $model = WmsFile::where('parent_id', $params['parent_id']);
        if (defined('ADMIN_INFO') && ADMIN_INFO['username'] == '王夏阳') {
            $model = $model->where('created_user', ADMIN_INFO['user_id']);
        }
        $res = $model->orderBy('id', 'desc')->get()->each(function ($item) {
            $item['file_size'] = sprintf('%dk', bcdiv($item['file_size'], 1024, 0));
        });
        return $res;
    }

    // 删除文件
    function del($params)
    {
        $list = WmsFile::whereIn('id', $params['ids'])->get();
        foreach ($list as $item) {
            $item->delete();
            if ($item->type == WmsFile::TYPE_FILE) continue;
            // 删除文件夹下的所有文件
            WmsFile::where('path', 'like', $item->path . '-%')->delete();
        }

        return true;
    }
}
