<?php

namespace App\Models;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Http\Request;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class Roles extends BaseModel
{
    public $table = 'admin_roles';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    protected $guarded = [];

}
