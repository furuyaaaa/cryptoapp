<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    public const TYPE_BUY = 'buy';
    public const TYPE_SELL = 'sell';
    public const TYPE_TRANSFER_IN = 'transfer_in';
    public const TYPE_TRANSFER_OUT = 'transfer_out';

    protected $fillable = [
        'portfolio_id',
        'asset_id',
        'exchange_id',
        'type',
        'amount',
        'price_jpy',
        'fee_jpy',
        'executed_at',
        'note',
    ];

    protected $casts = [
        'executed_at' => 'datetime',
        'amount' => 'decimal:8',
        'price_jpy' => 'decimal:8',
        'fee_jpy' => 'decimal:8',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(Exchange::class);
    }
}
