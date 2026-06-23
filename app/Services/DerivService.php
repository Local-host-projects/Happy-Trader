<?php

namespace App\Services;

use RuntimeException;
use WebSocket\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DerivService
{
    private Client $client;

    public function __construct(private readonly ?string $apiKey) {}

    // ── Connection ────────────────────────────────────────────────────────

    public function connect(): void
    {
        $appId = config('deriv.app_id');
        $endpoint = rtrim(config('deriv.endpoint', 'wss://ws.binaryws.com/websockets/v3'), '/');

        $url = "{$endpoint}?app_id={$appId}";

        $this->client = new Client(
            $url,
            ['timeout' => 30]
        );
    }

    public function connectPublic(): void
    {
        // Public endpoint — market data only, no auth needed
        $appId = config('deriv.app_id');
        $endpoint = rtrim(config('deriv.endpoint', 'wss://ws.binaryws.com/websockets/v3'), '/');
        $url = "{$endpoint}?app_id={$appId}";

        $this->client = new Client(
            $url,
            ['timeout' => 30]
        );
    }

    public function disconnect(): void
    {
        try {
            $this->client->close();
        } catch (\Throwable $e) {
            // best-effort close
            Log::debug('DerivService: error while disconnecting: ' . $e->getMessage());
            error_log('[DerivService] disconnect error: ' . $e->getMessage());
        }
    }

    // ── Auth ────────────────────────────────────────────────────────────

    public function authorize(): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('Missing Deriv API key for authorize call.');
        }

        return $this->send(['authorize' => $this->apiKey])['authorize'];
    }

    // ── REST — fetch all accounts (used on registration) ─────────────────

    public static function getAccounts(string $patToken): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$patToken}",
            'Deriv-App-ID'  => config('deriv.app_id'),
            'Content-Type'  => 'application/json',
        ])->get('https://api.derivws.com/trading/v1/options/accounts');

        if ($response->failed()) {
            throw new RuntimeException(
                "Could not fetch accounts [{$response->status()}]: {$response->body()}"
            );
        }

        return $response->json('data') ?? [];
    }

    // ── WebSocket send/receive ────────────────────────────────────────────

    private function send(array $payload): array
    {
        $this->client->text(json_encode($payload));
        $raw      = $this->client->receive();

        // Log raw response to both laravel log and stderr for easy debugging
        Log::debug('Deriv WS RAW RESPONSE: ' . $raw);
        error_log('[DerivService] RAW RESPONSE: ' . $raw);

        $response = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Deriv WS returned non-JSON.');
        }

        if (isset($response['error'])) {
            throw new RuntimeException(
                "[{$response['error']['code']}] {$response['error']['message']}"
            );
        }

        return $response;
    }

    // ── Market data ───────────────────────────────────────────────────────

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

    // ── Account ─────────────────────────────────────────────────────────

    public function getBalance(): array
    {
        return $this->send(['balance' => 1])['balance'];
    }

    // ── Trading ─────────────────────────────────────────────────────────

    public function getProposal(array $params): array
    {
        $resp = $this->send(
            array_merge(['proposal' => 1], $params)
        );

        return $resp['proposal'];
    }

    public function buy(string $proposalId, float $price): array
    {
        $resp = $this->send([
            'buy'   => $proposalId,
            'price' => $price,
        ]);

        return $resp['buy'];
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
}
