<?php

namespace App\Services;

use RuntimeException;
use WebSocket\Client;
use Illuminate\Support\Facades\Http;

class DerivService
{
    private Client $client;

    private const REST_BASE   = 'https://api.derivws.com';
    private const WS_PUBLIC   = 'wss://api.derivws.com/trading/v1/options/ws/public';

    public function __construct(
        private readonly string $patToken,
        private readonly string $accountId,
    ) {}

    // ── Connection ────────────────────────────────────────────────────────

    public function connect(): void
    {
        $wsUrl = $this->getAuthenticatedWsUrl();

        $this->client = new Client($wsUrl, ['timeout' => 30]);
    }

    public function connectPublic(): void
    {
        // For market data (ticks_history, active_symbols) — no auth needed
        $this->client = new Client(self::WS_PUBLIC, ['timeout' => 30]);
    }

    public function disconnect(): void
    {
        $this->client->close();
    }

    // ── OTP — gets authenticated WebSocket URL from REST ──────────────────

    private function getAuthenticatedWsUrl(): string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->patToken}",
            'Deriv-App-ID'  => config('deriv.app_id'),
            'Content-Type'  => 'application/json',
        ])->post(self::REST_BASE . "/trading/v1/options/accounts/{$this->accountId}/otp");

        if ($response->failed()) {
            throw new RuntimeException(
                "Deriv OTP request failed [{$response->status()}]: {$response->body()}"
            );
        }

        $url = $response->json('data.url');

        if (! $url) {
            throw new RuntimeException(
                'Deriv OTP response missing data.url — response: ' . $response->body()
            );
        }

        return $url;
    }

    // ── REST — get all accounts linked to this PAT ────────────────────────

    public static function getAccounts(string $patToken): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$patToken}",
            'Deriv-App-ID'  => config('deriv.app_id'),
            'Content-Type'  => 'application/json',
        ])->get(self::REST_BASE . '/trading/v1/options/accounts');

        if ($response->failed()) {
            throw new RuntimeException(
                "Could not fetch Deriv accounts [{$response->status()}]: {$response->body()}"
            );
        }

        return $response->json('data') ?? [];
    }

    // ── WebSocket send/receive ────────────────────────────────────────────

    private function send(array $payload): array
    {
        $this->client->text(json_encode($payload));
        $raw      = $this->client->receive();
        $response = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Deriv WS returned non-JSON response.');
        }

        if (isset($response['error'])) {
            throw new RuntimeException(
                "[{$response['error']['code']}] {$response['error']['message']}"
            );
        }

        return $response;
    }

    // ── Trading operations (authenticated WS) ────────────────────────────

    public function getBalance(): array
    {
        return $this->send(['balance' => 1])['balance'];
    }

    public function getProposal(array $params): array
    {
        return $this->send(array_merge(['proposal' => 1], $params))['proposal'];
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

    // ── Market data (public WS — no auth) ────────────────────────────────

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
}
