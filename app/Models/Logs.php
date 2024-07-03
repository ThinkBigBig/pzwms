<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;
use Illuminate\Support\Facades\DB;

class Logs extends BaseModel
{
    public $table = 'admin_logs';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
}
