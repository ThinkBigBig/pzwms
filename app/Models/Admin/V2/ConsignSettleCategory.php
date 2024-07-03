<?php

namespace App\Models\Admin\V2;

use App\Logics\traits\DataPermission;
use App\Logics\wms\Purchase;
use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\V2\WmsOptionLog;
use Illuminate\Support\Facades\DB;

class ConsignSettleCategory extends wmsBaseModel
{
    use HasFactory, DataPermission;
    protected $table = 'wms_consignment_settlement_category'; //寄卖结算分类

    protected $guarded = [];

    public function rules(){
        return $this->hasMany(ConsignSettleRule::class,'category_code','code');
    }

    public  function BaseCreate($CreateData = [])
    {
        if (empty($CreateData)) return false;
        if (isset($CreateData['parent_id']) && $CreateData['parent_id'] != 0) {
            $parent_id = $CreateData['parent_id'];
            $parent = $this::where('id', $parent_id)->select('level', 'path')->first();
            if (!empty($parent)) {
                $parent = $parent->toArray();
                $CreateData['level'] = $parent['level'] + 1;
                $CreateData['path'] = $parent['path'] . $parent_id . '-';
            } else  abort(500, '父级不存在！');
        } else {
            $CreateData['level'] = 1;
            $CreateData['path'] = '-';
        }
        // $id = DB::table($this->table)->insertGetId($CreateData);
        $id = $this::insertGetId($CreateData);
        if (empty($id)) return false;
        return $id;
    }


    //获取所有子级
    public function getChildrenCodesAttribute()
    {
        return $this::query()
            ->where('path','like', $this->path.'-%')
            ->orderBy('level') // 按层级排列
            ->get()->pluck('code')->toArray();
    }

    public function BaseUpdate($where, $update)
    {
        if (empty($update)) return false;
        // $data = DB::table($this->table);
        $data = $this::select();
        foreach ($where as $v) {
            if (empty($v[0]) || empty($v[1])) continue;
            if ($v[0] == 'id') $id = $v[2];
            if ($v[1] == 'in' || $v[1] == 'IN') $data->whereIn($v[0], $v[2]);
            if (in_array($v[1], $this->whereSymbol)) $data->where($v[0], $v[1], $v[2]);
        }
        if (isset($update['parent_id'])) {
            $parent_id = $update['parent_id'];
            $res = $this->updateChildren($id, $parent_id);
            unset($update['parent_id']);
        }
        if (!empty($update['updated_at'] && count($update) == 2)) {
            if ($res) return $res;
            return false;
        }
        $reData = $data->update($update);
        // if (empty($reData) || $reData == false || $reData == NULL) return false;
        return $reData;
    }

    /**
     * 更新当前分类的所有子级
     * update  需要更新的parent_id
     */
    public function updateChildren($id, $update)
    {;
        if ($id == $update) return [];
        //当前分类数据
        $item = $this::where('id', $id)->select('id','parent_id', 'level', 'path')->first();
        if(!$item) return [];
        $path = $item->path;
        $level = $item->level;
        //无更改
        if ($item->parent_id == $update) return [];
        //需要更新的数据
        DB::beginTransaction();
        try {
            //当前更改
            if ($update == 0) {
                $item->parent_id = 0;
                $item->level = 1;
                $item->path = '-';
            } else {
                $parent = $this::where('id', $update)->select('id', 'parent_id', 'level', 'path')->first();
                //传入的parent_id 有误!
                if (empty($parent)) return [];
                if ($parent->parent_id == $id) return [];
                $item->parent_id = $update;
                $item->level = $parent->level + 1;
                $item->path = $parent->path.$update.'-';
            }
            $res = $item->save();
            //所有子级更改
            $s_path = $path.$id.'-';
            $num = $item->level - $level;
            if ($num != 0) {
                if ($num > 0) {
                    $symbol = '+';
                    // $upath = $item->path . $id . $path;
                    // $path = $path . $id;
                } else {
                    $symbol = '-';;
                    // $upath = empty($parent->path) ? '-' : $parent->path;
                }
                $data = [
                    'level' => DB::raw('level ' . $symbol . abs($num)),
                    'path' => DB::raw('REPLACE(path,"' .  $path. '","' . $item->path . '")'),
                ];
                // dd($data);
                $resChild = $this::where('path', 'like', $s_path . '%')->update($data);
            }
            DB::commit();
            return $res || $resChild;
        } catch (\Exception $th) {
            DB::rollback();
            throw $th;
        }
    }

    public function del($ids)
    {
        if(empty($ids)) return false;
        $id = explode(',',$ids);
        //当前分类和下级分类
        $codes = [];
        $query = $this->whereIn('id',$id)->get()->append('children_codes');
        foreach($query as $q){  
            $codes[]=$q->code;
            $codes=array_merge($codes,$q->children_codes);
        }
        $exists = ConsignSettleRule::whereIn('category_code',$codes)->pluck('category_code')->toArray();
        if($exists) return [false,sprintf(__('response.settle_rules_del'),implode(',',$codes))];
        $data = self::whereIn('code',$codes)->delete();
        if(empty($data)  || $data== false || $data== NULL) return [false,''];
        return [true,''];
    }
}
