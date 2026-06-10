<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeConnection extends Model
{
    protected $fillable = [
        'user_id',
        'exchange_id',
        'portfolio_id',
        'label',
        'api_key',
        'api_secret',
        'api_passphrase',
        'product_code',
        'sync_start_at',
        'is_active',
        'last_synced_at',
        'last_error_at',
        'last_error',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'api_secret' => 'encrypted',
        'api_passphrase' => 'encrypted',
        'sync_start_at' => 'datetime',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(Exchange::class);
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }
}
