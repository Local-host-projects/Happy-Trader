<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DerivService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function index()
    {
        return view('profile.index', ['user' => Auth::user()]);
    }

    // Returns all Deriv accounts linked to the user's API key
    // Used by the dashboard account switcher
    public function accounts()
    {
        try {
            $accounts = DerivService::getAccounts(Auth::user()->deriv_api_key);

            return response()->json(
                collect($accounts)->map(fn($a) => [
                    'account_id'   => $a['account_id'] ?? $a['id'] ?? null,
                    'account_type' => $a['account_type'] ?? 'unknown',
                    'currency'     => $a['currency'] ?? 'USD',
                    'balance'      => number_format((float)($a['balance'] ?? 0), 2),
                    'active'       => ($a['account_id'] ?? $a['id'] ?? null) === Auth::user()->deriv_account_id,
                ])->values()
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function switchAccount(Request $request)
    {
        $data = $request->validate([
            'deriv_account_id' => ['required', 'string'],
        ]);

        $user = Auth::user();

        // Verify the account ID actually belongs to this user's API key
        try {
            $accounts = DerivService::getAccounts($user->deriv_api_key);
            $ids      = collect($accounts)->map(fn($a) => $a['account_id'] ?? $a['id'] ?? null);

            if (! $ids->contains($data['deriv_account_id'])) {
                return response()->json(['error' => 'Account not found on your API key.'], 422);
            }
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Could not verify account: ' . $e->getMessage()], 500);
        }

        // Pause the bot when switching — safety measure
        $user->deriv_account_id = $data['deriv_account_id'];
        $user->is_active        = false;
        $user->save();

        return response()->json([
            'success'    => true,
            'message'    => 'Account switched. Bot paused for safety — reactivate when ready.',
            'account_id' => $data['deriv_account_id'],
        ]);
    }

    public function updateApiKey(Request $request)
    {
        $data = $request->validate([
            'deriv_api_key' => ['required', 'string'],
        ]);

        // Verify it works before saving
        try {
            $accounts = DerivService::getAccounts($data['deriv_api_key']);

            if (empty($accounts)) {
                throw ValidationException::withMessages([
                    'deriv_api_key' => ['No accounts found on this API key.'],
                ]);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'deriv_api_key' => ['Deriv rejected this key: ' . $e->getMessage()],
            ]);
        }

        $user = Auth::user();
        $user->deriv_api_key = $data['deriv_api_key'];
        $user->is_active     = false; // Pause bot on key change
        $user->save();

        return redirect()->route('settings')
            ->with('success', 'API key updated. Bot has been paused — reactivate when ready.');
    }
}