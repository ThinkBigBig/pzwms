<?php

namespace App\Imports;

use Dotenv\Loader\Value;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

class Import implements ToModel, WithHeadingRow, WithBatchInserts, WithUpserts
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */


    protected $header; //表中文名称与字段名对应关系
    protected $field;
    protected $count = 2; //表头几行
    protected $db; //模型
    protected $relation; //关系模型
    protected $relation_field = []; //关系模型
    protected $map; //修改器
    protected $map_field = []; //修改器字段
    protected $auto = []; //自动填充
    protected $uniq = '';
    protected $format = []; //格式化字段
    protected $format_field = []; //格式化字段
    protected $config; //
    protected $line;
    protected $batch_size = 1000;

    public $code = 200;

    public $error = '';


    public function __construct($db, $config)
    {
        $this->db = $db;
        $this->config = $config;
        if (!empty($config['count'])) $this->count = $config['count'];
        if (!empty($config['batch_size'])) $this->batch_size = $config['batch_size'];
        if (!empty($config['rule']['relation'])) {

            $this->relation = $config['rule']['relation'];
            $this->relation_field = array_keys($config['rule']['relation']);
        }
        if (!empty($config['rule']['auto'])) $this->auto = $config['rule']['auto'];
        if (!empty($config['rule']['uniq'])) $this->uniq = $config['rule']['uniq'];
        if (!empty($config['rule']['format'])) {
            foreach ($config['rule']['format'] as $fk => $fv) {
                $this->format_field[] = $fk;
                $this->format[$fk] = $fv;
            }
        }

        HeadingRowFormatter::default('none');
        foreach ($config['field'] as $k => $v) {
            if (substr($k, -4) == '_txt') {
                $k = substr($k, 0, -4);
            }
            if (strpos($k, '.') !== false) {
                // preg_match('/\.([\w_]*)$/', $k, $reg);
                // $k = array_pop($reg);
            }
            $this->header[$v] = $k;
        }
        $map_field = array_values($this->header);
        foreach ($db->excelSetAttr() as $k => $v) {
            if (in_array($k, $map_field)) {
                $this->map[$k] = $v;
                $this->map_field[] = $k;
            }
        }
    }

    public function uniqueBy()
    {
        return $this->uniq;
    }
    public function headingRow(): int
    {
        return $this->count;
    }
    public function model(array $row)
    {
        if($this->code != 200)return;
        if (!$this->line) $this->line = $this->count + 1;
        else $this->line += 1;
        if ($this->line > 10000) set_time_limit(300);

        $row = array_map(function($v){
            return trim($v);
        },$row);
        // dd($this->relation,$this->relation_field );
        foreach ($this->header as $name_cn => $name_en) {
            if (in_array($name_en, $this->map_field)) {
                if($name_en == 'status' && $row[$name_cn] == null)$temp[$name_en] =1;
                else $temp[$name_en] = $this->map[$name_en][$row[$name_cn]] ?? '';
            } else {
                $temp[$name_en] = $row[$name_cn] ?? '';
            }
        }
        //必填字段
        if(isset($this->config['rule']['required'])){
            if(is_array($this->config['rule']['required'])){
                foreach($this->config['rule']['required'] as $f){
                    if(empty($temp[$f])){
                        $this->error = '第' . $this->line . '行 ' . $f . '为必填数据';
                        $this->code = 500;
                        return ;
                    }
                }
            }
        }
        //格式化处理
        foreach ($this->format_field as  $f) {
            $method = $this->format[$f];
            if (isset($temp[$f])) $temp[$f] = $this->db->$method($temp[$f]);
        }
        //通用数据填充
        $temp['created_at'] = date('Y-m-d H:i:s');
        $temp['tenant_id'] = request()->header('tenant_id');
        foreach ($this->auto as $ak => $av) {
            if (empty($temp[$ak])) {
                if (is_array($av)) {
                    if ($av[0] == 'field') {
                        $temp[$ak] = $temp[$av[1]];
                    }
                    if ($av[0] == 'method') {
                        $method_name = $av[1];
                        $av_v = $this->db->$method_name(...$av[2]);
                        $temp[$ak] = $av_v;
                    }
                    if ($av[0] == 'methodField') {
                        $method_name = $av[1];
                        $params = array_map(function($v) use($temp){
                            return $temp[$v];
                        },$av[2]);
                        $av_v = $this->db->$method_name(...$params);
                        $temp[$ak] = $av_v;
                    }
                } else {
                    if (isset($temp[$ak])){
                        if($temp[$ak]==='')$temp[$ak] = $av;
                    }
                    else  $temp[$ak] = $av;
                }
            }
        }
        //关系模型
        foreach ($this->relation_field as $k) {
            if (isset($this->relation[$k]['field_default']) && empty($temp[$k])) {
                $field_default = $this->relation[$k]['field_default'];
                if ($temp[$field_default['field']] == $field_default['value']) {
                    $temp[$k] = $field_default['default'];
                }
            } else {
                list($model, $m_field, $m_key) = $this->relation[$k]['model'];
                if ($model == 'self') {
                    $item = $this->db->where($m_field, $temp[$k])->orderBy('id', 'desc')->first();
                    if (!$item) {
                        if (isset($this->relation[$k]['default'])) $temp[$k] = $this->relation[$k]['default'];
                        else {
                            list($this->code, $this->error) = [500, '第' . $this->line . '行 ' . $temp[$k] . '关联关系不存在'];
                            return;
                        };
                    } else $temp[$k] = $item->$m_key;
                } else {
                    $item = (new $model)->where($m_field, $temp[$k])->orderBy('id', 'desc')->first();

                    if (!$item) {
                        if (isset($this->relation[$k]['default'])) $temp[$k] = $this->relation[$k]['default'];
                        else {
                            list($this->code, $this->error) = [500, '第' . $this->line . '行 ' . $temp[$k] . '关联关系不存在'];
                            return;
                        };
                    } else $temp[$k] = $item->$m_key;
                }
            }
        }
        if (!empty($this->config['rule']['uniq_columns'])) {
            $columns = $this->config['rule']['uniq_columns'];
            $query = $this->db->query();
            if (is_array($columns)) {
                foreach ($columns as $column) {
                    $this->uniqField($column, $query, $temp);
                }
                $msg = implode(' / ', $columns);
            } else {
                $msg = '';
                $this->uniqField($columns, $query, $temp,$msg);
            }
            if ($query !== null && $query->exists()) { {
                    list($this->code, $this->error) = [500, '第' . $this->line . '行 ' . $msg . ' 数据有重复'];
                    return;
                };
            }
        }

        //删除多余字段
        if(!empty($this->config['rule']['delete'])){
            foreach($this->config['rule']['delete'] as $d){
                unset($temp[$d]);
            }
        }
        // return $this->createData($temp);
        // dd($this->db->insert($temp));
        return new $this->db($temp);
    }

    public function createData($row)
    {
        return  $this->db->create($row);
    }
    public function batchSize(): int
    {
        return $this->batch_size;
    }
    public function chunkSize(): int
    {
        return 1000;
    }

    private function uniqField(&$columns, &$query, &$temp, &$msg = '')
    {
        if (strpos($columns, '|') === false) {
            $query = $query->orWhere($columns, $temp[$columns]);
            $msg = $columns;
        } else {
            list($column, $u_method) = explode('|', $columns);
            list($u_method, $params_field) = explode('?', $u_method);
            $params_field = explode(',', $params_field);
            if ($params_field) {
                foreach ($params_field as $fie) {
                    $params[] = $temp[$fie];
                }
            }
            $bool = $this->db->$u_method(...$params);
            if (!$bool) { {
                    $msg = $column;
                    // list($this->code, $this->error) = [500, '第' . $this->line . '行 ' . $column . ' 数据有重复'];
                    return;
                };
            }
            $query = null;
        }
    }
}
