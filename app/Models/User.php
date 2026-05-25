<?php

namespace App\Models;

use App\Models\TradeLog;
use App\Models\Parameter;
use App\Models\AccountSnapshot;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'deriv_api_key',
        'deriv_account_id',
        'is_active',
        'trading_started_at',
    ];

    protected $hidden = [
        'deriv_api_key',
    ];

    protected $casts = [
        'deriv_api_key'      => 'encrypted',
        'is_active'          => 'boolean',
        'trading_started_at' => 'datetime',
    ];

    public function parameters(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Parameters::class);
    }

    public function tradeLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TradeLogs::class);
    }

    public function accountSnapshots(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccountSnapshots::class);
    }

    public function latestSnapshot(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AccountSnapshots::class)->latestOfMany('captured_at');
    }

    public function openTrades(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TradeLogs::class)->where('status', 'open');
    }

    public function todaysTrades(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TradeLogs::class)->whereDate('created_at', today());
    }
}