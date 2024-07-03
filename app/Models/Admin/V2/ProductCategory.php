<?php

namespace App\Models\Admin\V2;

use App\Logics\RedisKey;
use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use PhpParser\Node\Stmt\TryCatch;

class ProductCategory extends wmsBaseModel
{
    use HasFactory;
    protected $table = 'wms_product_category';
    public $unique_field = ['name', 'code'];
    protected $appends = ['status_txt', 'full_name', 'path_ids', 'final_state'];
    protected $map = [
        'status' => [1 => '启用', 0 => '禁用'],
    ];

    protected $with = ['parent:id,name,status,level'];

    // protected static function booted()
    // {
    //     parent::booted();
    //     static::addGlobalScope('final_state', function (Builder $builder)  {
    //         $builder->where('final_state',true);
    //     });
    // }

    public function getStatusTxtAttribute()
    {
        $status = isset($this->status) ? $this->map['status'][$this->status] : '';
        return $status;
    }

    public function product()
    {
        return $this->hasMany(Product::class, 'category_id', 'id');
    }

    public function parent()
    {
        return $this->belongsTo(ProductCategory::class, 'pid');
    }

    /**
     * 获取当前分类的所有子级
     */
    public function getChildren($id)
    {
        $item = $this::where('id', $id)->select('status', 'level', 'path')->first();
        if (empty($item)) return [];
        $item_children = $this::where('path', 'like', $item->path . $id . '%')->get();
        if (empty($item_children)) return [];
        return $item_children->toArray();
    }

    /**
     * 更新当前分类的所有子级
     * update  需要更新的pid
     */
    public function updateChildren($id, $update)
    {
        if ($id == $update) return [];
        //当前分类数据
        $item = $this::where('id', $id)->select('id', 'pid', 'level', 'path', 'status')->first();
        if (!$item) return [];
        $path = $item->path;
        $level = $item->level;
        //无更改
        if ($item->pid == $update) return [];
        //需要更新的数据
        DB::beginTransaction();
        try {
            //当前更改
            if ($update == 0) {
                $item->pid = 0;
                $item->level = 1;
                $item->path = '-';
            } else {
                $parent = $this::where('id', $update)->select('id', 'pid', 'level', 'path')->first();
                //传入的pid 有误!
                if (empty($parent)) return [];
                if ($parent->pid == $id) return [];
                $item->pid = $update;
                $item->level = $parent->level + 1;
                $item->path = $parent->path . $update . '-';
            }
            $res = $item->save();
            //所有子级更改
            $s_path = $path . $id . '-';
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
                    'path' => DB::raw('REPLACE(path,"' .  $path . '","' . $item->path . '")'),
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




    /**
     * 获取所有祖先分类id
     */
    public function getPathIdsAttribute()
    {
        $path = trim($this->path, '-'); // 过滤两端的 -
        $path = explode('-', $path); // 以 - 为分隔符切割为数组
        $path = array_filter($path); // 过滤空值元素
        return $path;
    }

    /**
     * 获取所有祖先分类且按层级正序排列
     */
    public function getAncestorsAttribute()
    {
        return $this::query()
            ->whereIn('id', $this->path_ids) // 调用 getPathIdsAttribute 获取祖先类目id
            ->orderBy('level') // 按层级排列
            ->get();
    }


    /**
     * 获取所有祖先分类且状态是均开启的
     */
    public function getFinalStateAttribute()
    {
        if (empty($this->path_ids)) return true;
        $deny = $this::query()
            ->whereIn('id', $this->path_ids) // 调用 getPathIdsAttribute 获取祖先类目id
            ->where('status', 0) // 按层级排列
            ->get()->isEmpty();
        return $deny;
    }

    /**
     * 获取所有祖先类目名称以及当前类目的名称
     */
    public function getFullNameAttribute()
    {
        return $this->ancestors // 调用 getAncestorsAttribute 获取祖先类目
            ->pluck('name') // 将所有祖先类目的 name 字段作为一个数组
            ->push($this->name) // 追加当前类目的name字段到数组末尾
            ->implode(' - '); // 用 - 符号将数组的值组装成一个字符串
    }


    // public function children1($id,$level=0){
    //     $childIds = $this::where('pid',$id)->get()->modelKeys();
    //     if(!empty($childIds)){
    //         $this::whereIn('id',$childIds)->update(["level"=>$level+1]);

    //         foreach($childIds as $childId){
    //             $this->children1($childId,$level+1);
    //         }

    //     }
    // }


    // protected function editLevel($pid,$id=false){
    //     $p_level = $this::where('id',$pid)->value('level');
    //     $level = $p_level+1;
    //     if($id){;
    //         $this->children1($id,$level);
    //     }
    //     return $level;
    // }

    public  function BaseCreate($CreateData = [])
    {
        if (empty($CreateData)) return false;
        if (isset($CreateData['pid']) && ($CreateData['pid'] ?? 0)) {
            $pid = $CreateData['pid'];
            $parent = $this::where('id', $pid)->select('status', 'level', 'path')->first();
            if (!empty($parent)) {
                $parent = $parent->toArray();
                $CreateData['level'] = $parent['level'] + 1;
                $CreateData['path'] = $parent['path'] . $pid . '-';
            } else  abort(500, '父级不存在！');
        } else {
            $CreateData['level'] = 1;
            $CreateData['path'] = '-';
        }
        // $id = DB::table($this->table)->insertGetId($CreateData);
        $id = $this::insertGetId($CreateData);
        if (empty($id)) return false;
        Redis::hset(RedisKey::WMS_CATEGORY, $id, $CreateData['name']);
        return $id;
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
        if (isset($update['pid'])) {
            $pid = $update['pid'];
            $res = $this->updateChildren($id, $pid);
            unset($update['pid']);
        }
        if (!empty($update['updated_at'] && count($update) == 1)) {
            if ($res) return $res;
            return false;
        }
        $reData = $data->update($update);
        if (empty($reData) || $reData == false || $reData == NULL) return false;
        self::cacheAll();
        return $reData;
    }

    public function BaseDelete($ids, $name = 'id')
    {
        if (empty($ids)) return false;
        $id = explode(',', $ids);;
        // dd($this::with('product:id,category_id,name')->doesntHave('product')->whereIn($name,$id)->delete());
        $del_ids = $this::with('product:id,category_id,name')->doesntHave('product')->whereIn($name, $id)->select('id')->get()->modelKeys();
        $ndel_ids = array_values(array_diff($id, $del_ids));
        $data = $this::whereIn($name, $del_ids)->delete();
        if ($ndel_ids) {
            return ['ndel_ids' => $ndel_ids, 'del_ids' => $del_ids];
        }
        if (empty($data)  || $data == false || $data == NULL) return false;

        foreach($del_ids as $id){
            Redis::hdel(RedisKey::WMS_CATEGORY, $id);
        }
        return $data;
    }

    public function withSearch($select)
    {
        //只展示上级状态是启用的
        return $this::whereDoesntHave('parent', function ($query) {
            $query->where('status', 0);
        })->select($select);
    }

    public function levelFormat($value)
    {
        $v = mb_substr($value, 0, -1);
        $map = array('零', '一', '二', '三', '四', '五', '六', '七', '八', '九');
        if (in_array($v, $map)) {
            return array_search($v, $map);
        }
        return '';
    }

    static function cacheAll()
    {
        Redis::del(RedisKey::WMS_CATEGORY);
        $list = DB::select("select * from wms_product_category");
        foreach ($list as $item) {
            Redis::hset(RedisKey::WMS_CATEGORY, $item->id, $item->name);
        }
    }

    static function getName($id)
    {
        $name = Redis::hget(RedisKey::WMS_CATEGORY, $id);
        if ($name !== null) return $name;
        $find = self::find($id);
        Redis::hset(RedisKey::WMS_CATEGORY, $id, $find->name);
        return $find->name;
    }
}
