<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Portfolio extends Model
{
    protected $fillable = [
        'coin_name',
        'amount',
        'buy_price',
        'current_price',
    ];

    public function getValuationAttribute()
    {
        return $this->amount * $this->current_price;
    }

    public function getProfitAttribute()
    {
        return ($this->current_price - $this->buy_price) * $this->amount;
    }
}
