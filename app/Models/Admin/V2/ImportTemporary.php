<?php

namespace App\Models\Admin\V2;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportTemporary extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'import_temporary';
    protected $casts = [
        'data' => 'array',
    ];
    const UPDATED_AT = null;
}
