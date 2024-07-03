<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;

class Article extends BaseModel
{
    public $table = 'article';

    const CREATEDTIME = 'createtime';
    const UPDATEDTIME = 'updatetime';
}
