<?php

namespace App\Imports;

use App\Logics\RedisKey;
use App\Models\Admin\V2\WmsTemporaryImport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;

class BigDataImport implements ToCollection, WithChunkReading
{

    public $params;
    public function __construct($params = [])
    {
        $this->params = $params;
    }

    public function collection(Collection $rows)
    {
        $this->params['params']['rows'] = $rows;
        Redis::lpush(RedisKey::QUEUE_AYSNC_HADNLE,json_encode($this->params));
        // Log::info(count($rows));
        // call_user_func($this->callback, $this->params, $rows);
    }

    //以1000条为基准切割数据
    public function chunkSize(): int
    {
        return 500;
    }
}
