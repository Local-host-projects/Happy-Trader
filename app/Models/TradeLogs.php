<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradeLogs extends Model
{
        public $timestamps = false;

    protected $fillable = [
        'user_id',
        'direction',
        'lot_size',
        'entry_price',
        'sl_price',
        'tp1_price',
        'tp2_price',
        'close_price',
        'pnl',
        'status',
        'ref_candle_open_at',
        'ref_candle_high',
        'ref_candle_low',
        'atr_at_entry',
        'opened_at',
        'closed_at',
        'created_at',
    ];

    protected $casts = [
        'lot_size'           => 'decimal:4',
        'entry_price'        => 'decimal:5',
        'sl_price'           => 'decimal:5',
        'tp1_price'          => 'decimal:5',
        'tp2_price'          => 'decimal:5',
        'close_price'        => 'decimal:5',
        'pnl'                => 'decimal:4',
        'atr_at_entry'       => 'decimal:5',
        'ref_candle_high'    => 'decimal:5',
        'ref_candle_low'     => 'decimal:5',
        'ref_candle_open_at' => 'datetime',
        'opened_at'          => 'datetime',
        'closed_at'          => 'datetime',
        'created_at'         => 'datetime',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRangeAttribute(): float
    {
        return $this->ref_candle_high - $this->ref_candle_low;
    }

    public function getBodyRatioAttribute(): float
    {
        $range = $this->range;
        if ($range == 0) return 0;
        return abs($this->entry_price - $this->close_price) / $range * 100;
    }

    public function getSlDistanceInAtrAttribute(): float|null
    {
        if (! $this->atr_at_entry) return null;
        return abs($this->entry_price - $this->sl_price) / $this->atr_at_entry;
    }

    public function getRMultipleAttribute(): float|null
    {
        if (! $this->pnl || ! $this->lot_size) return null;
        $risk = abs($this->entry_price - $this->sl_price) * $this->lot_size;
        if ($risk == 0) return null;
        return $this->pnl / $risk;
    }

    public function isWinner(): bool
    {
        return in_array($this->status, ['tp1', 'tp2']) && $this->pnl > 0;
    }

    public function isLoser(): bool
    {
        return $this->status === 'sl' && $this->pnl < 0;
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeClosed($query)
    {
        return $query->whereNotNull('closed_at');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}
