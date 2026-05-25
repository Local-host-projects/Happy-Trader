<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TradeController extends Controller
{
    public function index()
    {
        return view('trades.index');
    }

    public function data(Request $request)
    {
        $query = Auth::user()->tradeLogs()->latest('created_at');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('direction') && $request->direction !== 'all') {
            $query->where('direction', $request->direction);
        }

        $trades = $query->paginate(15);

        $trades->getCollection()->transform(fn($t) => array_merge($t->toArray(), [
            'sl_distance_in_atr' => $t->sl_distance_in_atr,
            'r_multiple'         => $t->r_multiple,
            'body_ratio'         => round($t->body_ratio, 1),
        ]));

        return response()->json($trades);
    }
}