<?php

namespace App\Services;

use RuntimeException;
use WebSocket\Client;
use Illuminate\Support\Facades\Http;

class DerivService
{
    private Client $client;

    public function __construct(
        private readonly string $patToken,
        private readonly string $accountId,
    ) {}

    // ── Authenticated connection via OTP ──────────────────────────────────

    public function connect(): void
    {
        $wsUrl = $this->getOtpUrl();

        $this->client = new Client($wsUrl, ['timeout' => 30]);
    }

    private function getOtpUrl(): string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->patToken}",
            'Deriv-App-ID'  => config('deriv.app_id'),
            'Content-Type'  => 'application/json',
        ])->post(
            config('deriv.endpoint')
            . "/trading/v1/options/accounts/{$this->accountId}/otp"
        );

        if ($response->failed()) {
            throw new RuntimeException(
                "OTP failed [{$response->status()}]: {$response->body()}"
            );
        }

        $url = $response->json('data.url');

        if (! $url) {
            throw new RuntimeException(
                'OTP response missing data.url — response: ' . $response->body()
            );
        }

        return $url;
    }

    public function disconnect(): void
    {
        $this->client->close();
    }

    // ── Core send/receive ─────────────────────────────────────────────────

    private function send(array $payload): array
{
    $this->client->text(json_encode($payload));
    $raw      = $this->client->receive();
    $response = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Deriv returned non-JSON: ' . substr($raw, 0, 300));
    }

    if (isset($response['error'])) {
        // Surface the EXACT Deriv error — code + message + what we sent
        throw new RuntimeException(sprintf(
            'DERIV_ERROR [%s]: %s | Request: %s',
            $response['error']['code'] ?? 'unknown',
            $response['error']['message'] ?? 'no message',
            json_encode($payload)
        ));
    }

    return $response;
}

    // ── Account ───────────────────────────────────────────────────────────

    public function getBalance(): array
    {
        return $this->send(['balance' => 1])['balance'];
    }

    // ── Market data — works on authenticated connection ───────────────────

    public function getCandles(string $symbol, int $granularity, int $count = 20): array
    {
        return $this->send([
            'ticks_history' => $symbol,
            'end'           => 'latest',
            'count'         => $count,
            'granularity'   => $granularity,
            'style'         => 'candles',
        ])['candles'];
    }

    // ── Trading ───────────────────────────────────────────────────────────

    public function getProposal(array $params): array
    {
        return $this->send(
            array_merge(['proposal' => 1], $params)
        )['proposal'];
    }
    public function getPortfolio(): array
{
    return $this->send(['portfolio' => 1])['portfolio']['contracts'] ?? [];
}
    public function buy(string $proposalId, float $price): array
{
    return $this->send([
        'buy'   => $proposalId,
        'price' => $price,
    ])['buy'];
}

    public function getOpenContract(int $contractId): array
    {
        return $this->send([
            'proposal_open_contract' => 1,
            'contract_id'            => $contractId,
        ])['proposal_open_contract'];
    }

    public function sell(int $contractId, float $price = 0): array
    {
        return $this->send([
            'sell'  => $contractId,
            'price' => $price,
        ])['sell'];
    }

    // ── REST — get all linked accounts (used on registration) ────────────

    public static function getAccounts(string $patToken): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$patToken}",
            'Deriv-App-ID'  => config('deriv.app_id'),
            'Content-Type'  => 'application/json',
        ])->get(config('deriv.endpoint') . '/trading/v1/options/accounts');

        if ($response->failed()) {
            throw new RuntimeException(
                "getAccounts failed [{$response->status()}]: {$response->body()}"
            );
        }

        return $response->json('data') ?? [];
    }
    public function getTicks(string $symbol, int $count = 60): array
{
    $response = $this->send([
        'ticks_history' => $symbol,
        'end'           => 'latest',
        'count'         => $count,
        'style'         => 'ticks',  // raw ticks, not candles
    ]);

    return $response['history']['prices']
        ? array_map(fn($p, $t) => ['price' => $p, 'time' => $t],
            $response['history']['prices'],
            $response['history']['times'])
        : [];
}
}