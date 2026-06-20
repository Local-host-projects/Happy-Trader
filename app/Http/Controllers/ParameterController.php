<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ParameterController extends Controller
{
    public function index()
    {
        $user   = Auth::user()->load('parameters');
        $params = $user->parameters;

        return view('parameters.index', compact('user', 'params'));
    }

    public function update(Request $request)
    {

        $data = $request->validate([
            'risk_percent'          => ['sometimes', 'numeric', 'min:0.1', 'max:5'],
            'sl_atr_multiplier'     => ['sometimes', 'numeric', 'min:1.0', 'max:5'],
            'min_range_atr_pct'     => ['sometimes', 'numeric', 'min:10', 'max:200'],
            'max_range_atr_pct'     => ['sometimes', 'numeric', 'min:50', 'max:500'],
            'tp1_close_pct'         => ['sometimes', 'numeric', 'min:10', 'max:100'],
            'tp2_atr_multiplier'    => ['sometimes', 'nullable', 'numeric', 'min:0.5', 'max:5'],
            'trailing_atr_step'     => ['sometimes', 'nullable', 'numeric', 'min:0.1', 'max:3'],
            'adx_min_threshold'     => ['sometimes', 'nullable', 'numeric', 'min:10', 'max:50'],
            'trend_filter_enabled'  => ['sometimes', 'boolean'],
            'max_concurrent_trades' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'daily_loss_limit_pct'  => ['sometimes', 'numeric', 'min:0.5', 'max:20'],
            'zone_threshold'        => ['sometimes', 'numeric', 'min:0.150', 'max:0.450'],
        ]);
        dd($data);

        $params = Auth::user()->parameters;
        $params->fill($data);
        $params->updated_at = now();
        $params->save();

        return redirect()->route('parameters')->with('success', 'Parameters updated successfully.');
    }

    public function toggleTrading(Request $request)
    {
        $user = Auth::user();
        $user->is_active = ! $user->is_active;

        if ($user->is_active && ! $user->trading_started_at) {
            $user->trading_started_at = now();
        }

        $user->save();

        return response()->json([
            'is_active' => $user->is_active,
            'message'   => $user->is_active ? 'Bot activated.' : 'Bot paused.',
        ]);
    }
}
