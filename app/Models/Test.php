<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;

class Test extends BaseModel
{
    public $table = 'users';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

}
