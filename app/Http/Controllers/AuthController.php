<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Parameters;
use Illuminate\Http\Request;
use App\Services\DerivService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'         => ['required', 'email'],
            'deriv_api_key' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['No account found with that email.'],
            ]);
        }

        try {
            DerivService::getAccounts($data['deriv_api_key']);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'deriv_api_key' => ['Could not authenticate with Deriv: ' . $e->getMessage()],
            ]);
        }

        if ($user->deriv_api_key !== $data['deriv_api_key']) {
            throw ValidationException::withMessages([
                'deriv_api_key' => ['API key does not match our records.'],
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()->route('dashboard');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'unique:users,email'],
            'deriv_api_key'=> ['required', 'string'],
            'account_type' => ['required', 'in:demo,real'],
        ]);

        try {
            $accounts = DerivService::getAccounts($data['deriv_api_key']);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'deriv_api_key' => ['Deriv rejected this key: ' . $e->getMessage()],
            ]);
        }

        if (empty($accounts)) {
            throw ValidationException::withMessages([
                'deriv_api_key' => ['No accounts found on this Deriv API key.'],
            ]);
        }

        $account = collect($accounts)->first(
            fn($a) => str_contains(strtolower($a['account_type'] ?? ''), $data['account_type'])
        );

        if (! $account) {
            throw ValidationException::withMessages([
                'account_type' => ["No {$data['account_type']} account found on this key."],
            ]);
        }

        $accountId = $account['account_id'] ?? $account['id'] ?? null;

        if (! $accountId) {
            throw ValidationException::withMessages([
                'deriv_api_key' => ['Could not resolve account ID from Deriv.'],
            ]);
        }

        $user = User::create([
            'name'             => $data['name'],
            'email'            => $data['email'],
            'deriv_api_key'    => $data['deriv_api_key'],
            'deriv_account_id' => $accountId,
        ]);

        // Seed default parameters
        Parameters::create(array_merge(
            ['user_id' => $user->id],
            Parameters::defaults()
        ));

        Auth::login($user, remember: true);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
