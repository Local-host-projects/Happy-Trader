<?php

$user   = App\Models\User::first();
$params = $user->parameters;

echo "=== USER ===\n";
echo "Active: " . ($user->is_active ? 'yes' : 'no') . "\n";
echo "Account ID: {$user->deriv_account_id}\n";

try {
    $key = $user->deriv_api_key;
    echo "Key OK: " . substr($key, 0, 6) . "...\n";
} catch (\Throwable $e) {
    echo "KEY DECRYPT FAILED: " . $e->getMessage() . "\n";
    return;
}

echo "\n=== CONNECT ===\n";
try {
    $service = new App\Services\DerivService($user->deriv_api_key);
    $service->connect();
    echo "Connected\n";
} catch (\Throwable $e) {
    echo "CONNECT FAILED: " . $e->getMessage() . "\n";
    return;
}

echo "\n=== AUTHORIZE ===\n";
try {
    $auth = $service->authorize();
    echo "Authorized as: " . ($auth['loginid'] ?? 'unknown') . "\n";
} catch (\Throwable $e) {
    echo "AUTHORIZE FAILED: " . $e->getMessage() . "\n";
    $service->disconnect();
    return;
}

echo "\n=== PORTFOLIO ===\n";
try {
    if (! method_exists($service, 'getPortfolio')) {
        echo "getPortfolio() METHOD DOES NOT EXIST — add it to DerivService\n";
    } else {
        $portfolio = $service->getPortfolio();
        echo "Open contracts: " . count($portfolio) . "\n";
    }
} catch (\Throwable $e) {
    echo "PORTFOLIO FAILED: " . $e->getMessage() . "\n";
}

echo "\n=== BALANCE ===\n";
try {
    $balance = $service->getBalance();
    echo "Balance: {$balance['balance']} {$balance['currency']}\n";
} catch (\Throwable $e) {
    echo "BALANCE FAILED: " . $e->getMessage() . "\n";
    $service->disconnect();
    return;
}

echo "\n=== PROPOSAL ===\n";
try {
    $stake = max(1.00, round(1 / 100 * (float)$balance['balance'], 2));
    echo "Stake: $stake\n";

    $proposal = $service->getProposal([
        'amount'        => $stake,
        'basis'         => 'stake',
        'contract_type' => 'CALL',
        'currency'      => $balance['currency'],
        'duration'      => 60,
        'duration_unit' => 's',
        'symbol'        => 'R_25',
    ]);

    echo "Proposal ID: {$proposal['id']}\n";
    echo "Ask price: {$proposal['ask_price']}\n";
} catch (\Throwable $e) {
    echo "PROPOSAL FAILED: " . $e->getMessage() . "\n";
    $service->disconnect();
    return;
}

echo "\n=== BUY ===\n";
try {
    $buy = $service->buy($proposal['id'], (float)$proposal['ask_price'] * 1.02);
    echo "TRADE PLACED! Contract ID: {$buy['contract_id']}\n";
    echo "Buy price: {$buy['buy_price']}\n";
} catch (\Throwable $e) {
    echo "BUY FAILED: " . $e->getMessage() . "\n";
}

$service->disconnect();
echo "\n=== DONE ===\n";