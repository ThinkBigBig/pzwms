<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErpLog extends BaseModel
{
    use HasFactory;
    protected $table = 'erp_logs';
    public $guarded = [];
}
