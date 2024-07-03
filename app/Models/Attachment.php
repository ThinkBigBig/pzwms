<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;

class Attachment extends BaseModel
{
    public $table = 'attachment';

    const CREATEDTIME = 'createtime';
    const UPDATEDTIME = 'updatetime';
}
