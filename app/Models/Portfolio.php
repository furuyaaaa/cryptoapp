<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Portfolio extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getValuationAttribute(): float
    {
        $value = 0.0;

        foreach ($this->transactions as $tx) {
            $price = $tx->asset?->latestPrice?->price_jpy ?? 0;
            $sign = in_array($tx->type, [Transaction::TYPE_BUY, Transaction::TYPE_TRANSFER_IN], true) ? 1 : -1;
            $value += $sign * (float) $tx->amount * (float) $price;
        }

        return $value;
    }

    public function getCostBasisAttribute(): float
    {
        $cost = 0.0;

        foreach ($this->transactions as $tx) {
            if (! in_array($tx->type, [Transaction::TYPE_BUY, Transaction::TYPE_TRANSFER_IN], true)) {
                continue;
            }
            $cost += (float) $tx->amount * (float) $tx->price_jpy + (float) $tx->fee_jpy;
        }

        return $cost;
    }

    public function getProfitAttribute(): float
    {
        return $this->valuation - $this->cost_basis;
    }
}
