<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DerivService;
use App\Models\AccountSnapshots;
use Illuminate\Support\Facades\Auth;


class DashboardController extends Controller
{
    public function index()
    {
        $user        = Auth::user()->load('parameters');
        $todayTrades = $user->todaysTrades()->latest('created_at')->take(8)->get();

        $todayTrades->each->append(['sl_distance_in_atr', 'r_multiple']);

        return view('dashboard.index', [
            'user'        => $user,
            'todayTrades' => $todayTrades,
        ]);
    }

    public function stats()
    {
        $user        = Auth::user();
        $todayTrades = $user->todaysTrades()->get();

        $winners = $todayTrades->filter->isWinner();
        $losers  = $todayTrades->filter->isLoser();

        return response()->json([
            'today_trades'  => $todayTrades->count(),
            'open_trades'   => $user->openTrades()->count(),
            'win_rate'      => $todayTrades->count() > 0
                ? round($winners->count() / $todayTrades->count() * 100, 1)
                : 0,
            'today_pnl'     => round($todayTrades->sum('pnl'), 2),
            'drawdown_pct'  => round(AccountSnapshots::currentDrawdownFor($user->id), 2),
            'is_active'     => $user->is_active,
        ]);
    }

    public function snapshots()
    {
        $snapshots = Auth::user()
            ->accountSnapshots()
            ->latest('captured_at')
            ->take(30)
            ->get()
            ->reverse()
            ->values();

        return response()->json($snapshots->map(fn($s) => [
            'label'   => $s->captured_at->format('M d H:i'),
            'balance' => (float) $s->balance,
            'equity'  => (float) $s->equity,
        ]));
    }
    public function balance()
{
    $user = Auth::user();

    try {
        // getAccounts() already returns balance per account from Deriv REST
        $accounts = DerivService::getAccounts($user->deriv_api_key);

        // Find the account currently active for this user
        $active = collect($accounts)->first(
            fn($a) => ($a['account_id'] ?? $a['id'] ?? null) === $user->deriv_account_id
        );

        if ($active) {
            return response()->json([
                'balance'       => number_format((float)($active['balance'] ?? 0), 2),
                'currency'      => $active['currency'] ?? 'USD',
                'account_id'    => $user->deriv_account_id,
                'account_type'  => $active['account_type'] ?? 'unknown',
                'source'        => 'live',
            ]);
        }

        throw new \RuntimeException('Account not found in Deriv response.');

    } catch (\Throwable $e) {
        // Graceful fallback to latest snapshot
        $snapshot = $user->latestSnapshot;

        return response()->json([
            'balance'      => number_format((float)($snapshot?->balance ?? 0), 2),
            'currency'     => 'USD',
            'account_id'   => $user->deriv_account_id,
            'account_type' => 'unknown',
            'source'       => 'cached',
            'error'        => 'Live fetch unavailable — showing last known balance.',
        ]);
    }
}
}