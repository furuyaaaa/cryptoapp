<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'name',
        'coingecko_id',
        'icon_url',
    ];

    public function prices(): HasMany
    {
        return $this->hasMany(AssetPrice::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function latestPrice()
    {
        return $this->hasOne(AssetPrice::class)->latestOfMany('recorded_at');
    }
}
