<?php

namespace App\Http\Controllers;

use App\Models\TradeLogs;
use App\Services\DerivService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TradingController extends Controller
{
    public function manualBuy(Request $request): JsonResponse
{
    $data = $request->validate([
        'direction' => ['required', 'in:buy,sell'],
    ]);

    $user   = Auth::user();
    $params = $user->parameters;

    $service = new DerivService($user->deriv_api_key);

    try {
        $service->connect();
        $service->authorize();

        // Check live open contracts from Deriv, not stale DB rows
        $portfolio = $service->getPortfolio();

        if (count($portfolio) >= (int)($params->max_concurrent_trades ?? 2)) {
            return response()->json(['error' => 'Max concurrent trades reached (live check).'], 422);
        }

        $balance = $service->getBalance();
        $stake   = max(1.00, round(
            ((float)($params->risk_percent ?? 1) / 100) * (float) $balance['balance'], 2
        ));

        $contractType = $data['direction'] === 'buy' ? 'CALL' : 'PUT';

        $proposal = $service->getProposal([
            'amount'        => $stake,
            'basis'         => 'stake',
            'contract_type' => $contractType,
            'currency'      => $balance['currency'],
            'duration'      => (int) config('deriv.trade_duration', 60),
            'duration_unit' => 's',
            'symbol'        => config('deriv.symbol'),
        ]);

        $maxPrice = (float) $proposal['ask_price'] * 1.02;
        $buy      = $service->buy($proposal['id'], $maxPrice);

        $trade = TradeLog::create([
            'user_id'            => $user->id,
            'deriv_contract_id'  => (int) $buy['contract_id'],
            'direction'          => $data['direction'],
            'lot_size'           => $stake,
            'entry_price'        => (float) ($proposal['spot'] ?? 0),
            'sl_price'           => 0,
            'tp1_price'          => 0,
            'status'             => 'open',
            'ref_candle_open_at' => now(),
            'ref_candle_high'    => 0,
            'ref_candle_low'     => 0,
            'atr_at_entry'       => 0,
            'opened_at'          => now(),
            'created_at'         => now(),
        ]);

        Log::info("Manual trade placed by user {$user->id}", [
            'direction'   => $data['direction'],
            'contract_id' => $buy['contract_id'],
            'stake'       => $stake,
        ]);

        return response()->json([
            'success'     => true,
            'trade_id'    => $trade->id,
            'contract_id' => $buy['contract_id'],
            'direction'   => $data['direction'],
            'entry_price' => $trade->entry_price,
            'stake'       => $stake,
            'expires_at'  => now()->addSeconds((int) config('deriv.trade_duration', 60))->toIso8601String(),
        ]);

    } catch (\Throwable $e) {
        Log::error("Manual trade failed for user {$user->id}: {$e->getMessage()}");
        return response()->json(['error' => $e->getMessage()], 500);
    } finally {
        $service->disconnect();
    }
}

    // Returns open trades + trades closed in the last 2 minutes, for the live panel
    public function positions(): JsonResponse
{
    $user = Auth::user();

    if (! $user->deriv_api_key || ! $user->deriv_account_id) {
        return response()->json(['open' => [], 'closed' => [], 'error' => 'No Deriv account linked.']);
    }

    $service = new DerivService($user->deriv_api_key);

    try {
        $service->connect();
        $service->authorize();

        // Pull the live list of open contract IDs directly from Deriv
        $portfolio = $service->getPortfolio();

        $open = collect($portfolio)->map(function ($contract) use ($service) {
            try {
                // Get live profit, current spot, entry spot for this contract
                $details = $service->getOpenContract((int) $contract['contract_id']);

                return [
                    'contract_id'  => $contract['contract_id'],
                    'direction'    => str_contains($contract['contract_type'] ?? '', 'CALL') ? 'buy' : 'sell',
                    'symbol'       => $contract['symbol'] ?? config('deriv.symbol'),
                    'entry_price'  => (float) ($details['entry_spot'] ?? 0),
                    'current_spot' => (float) ($details['current_spot'] ?? 0),
                    'buy_price'    => (float) ($contract['buy_price'] ?? 0),
                    'payout'       => (float) ($contract['payout'] ?? 0),
                    'profit'       => (float) ($details['profit'] ?? 0),
                    'is_expired'   => (bool) ($details['is_expired'] ?? false),
                    'purchase_time'=> $contract['purchase_time'] ?? null,
                    'date_expiry'  => $details['date_expiry'] ?? null,
                ];
            } catch (\Throwable $e) {
                return null;
            }
        })->filter()->values();

        $service->disconnect();

        return response()->json([
            'open'   => $open,
            'source' => 'deriv_live',
        ]);

    } catch (\Throwable $e) {
        Log::error("Positions fetch failed for user {$user->id}: {$e->getMessage()}");
        return response()->json(['open' => [], 'error' => $e->getMessage()]);
    } finally {
        if (isset($service)) $service->disconnect();
    }
}
}