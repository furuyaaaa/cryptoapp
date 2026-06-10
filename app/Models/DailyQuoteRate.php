<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyQuoteRate extends Model
{
    protected $fillable = [
        'base_currency',
        'quote_currency',
        'rate_date',
        'rate',
        'source',
        'fetched_at',
    ];

    protected $casts = [
        'rate_date' => 'date',
        'rate' => 'decimal:8',
        'fetched_at' => 'datetime',
    ];
}
