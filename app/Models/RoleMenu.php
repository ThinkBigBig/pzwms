<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;

class RoleMenu extends BaseModel
{
    public $table = 'admin_role_menu';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

}
