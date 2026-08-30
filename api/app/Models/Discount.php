<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $fillable = ['name', 'type', 'value', 'conditions', 'status'];

    protected $casts = [
        'value' => 'decimal:2',
        'conditions' => 'array',
    ];
}
