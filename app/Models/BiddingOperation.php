<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BiddingOperation extends Model
{
    use HasFactory;
    protected $casts = [
        'input' => 'array',
    ];
    protected $guarded = [];
}
