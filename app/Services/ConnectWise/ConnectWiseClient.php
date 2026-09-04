<?php

namespace App\Services\ConnectWise;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the ConnectWise Manage REST API (v3) for an
 * on-premise install, built on Laravel's HTTP client (so it's fakeable in
 * tests with Http::fake() — see tests/Feature).
 *
 * Auth: HTTP Basic, username "{companyId}+{publicKey}", password
 * "{privateKey}", plus a required "clientId" header (a GUID you register at
 * developer.connectwise.com — see docs/CONNECTWISE_SETUP.md). This mirrors
 * ConnectWise's own published "API member" auth scheme.
 *
 * Base URL: on-premise installs are versioned per-instance (the "codebase",
 * e.g. v2025_3/). Rather than hard-code it, we discover it once via the
 * public /login/companyinfo/{companyId} endpoint and cache it — or you can
 * skip discovery entirely by setting CONNECTWISE_CODEBASE.
 *
 * NOTE: exact field names on write endpoints (company/contact approval
 * fields, invoice line item shape, the property path used to scope the
 * product catalog to a single warehouse) vary slightly by ConnectWise
 * Manage version. Confirm each against your instance's own REST API
 * reference (https://{server}/{codebase}apis/3.0/) before go-live — the
 * service classes built on top of this client isolate those specifics so
 * adjusting them is a small, contained change.
 */
class ConnectWiseClient
{
    public function __construct(protected array $config = [])
    {
        $this->config = $config ?: config('connectwise');
    }

    /**
     * Resolve the versioned API base URI, e.g.
     * https://cw.codebluetechnology.com/v2025_3/apis/3.0/
     */
    public function baseUri(): string
    {
        $server = rtrim((string) $this->config['server'], '/');
        $codebase = $this->config['codebase'] ?? null;

        if (! $codebase) {
            $codebase = Cache::remember('connectwise.codebase', now()->addDay(), function () use ($server) {
                return $this->discoverCodebase($server);
            });
        }

        $codebase = trim((string) $codebase, '/');

        return "https://{$server}/{$codebase}/apis/3.0/";
    }

    protected function discoverCodebase(string $server): string
    {
        $companyId = $this->config['company_id'];

        try {
            $response = Http::timeout($this->config['timeout'] ?? 8)
                ->get("https://{$server}/login/companyinfo/{$companyId}");

            // ConnectWise returns something like {"Codebase": "v2025_3/", ...}
            return $response->json('Codebase') ?? 'v4_6_release';
        } catch (ConnectionException $e) {
            Log::warning('ConnectWise codebase discovery failed, falling back to v4_6_release', [
                'error' => $e->getMessage(),
            ]);

            return 'v4_6_release';
        }
    }

    protected function client(): PendingRequest
    {
        $companyId = $this->config['company_id'];
        $publicKey = $this->config['public_key'];
        $privateKey = $this->config['private_key'];

        return Http::baseUrl($this->baseUri())
            ->timeout($this->config['timeout'] ?? 8)
            ->withBasicAuth("{$companyId}+{$publicKey}", (string) $privateKey)
            ->withHeaders([
                'clientId' => $this->config['client_id'],
                'Accept' => 'application/json',
            ])
            ->asJson();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>|array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, fn (PendingRequest $c) => $c->get(ltrim($path, '/'), array_filter($query, fn ($v) => $v !== null)));
    }

    public function post(string $path, array $json): array
    {
        return $this->send('POST', $path, fn (PendingRequest $c) => $c->post(ltrim($path, '/'), $json));
    }

    /**
     * ConnectWise Manage's REST API uses JSON Patch (RFC 6902) for partial
     * updates, e.g. [['op' => 'replace', 'path' => '/closedFlag', 'value' => true]].
     */
    public function patch(string $path, array $operations): array
    {
        return $this->send('PATCH', $path, fn (PendingRequest $c) => $c->patch(ltrim($path, '/'), $operations));
    }

    protected function send(string $method, string $path, \Closure $call): array
    {
        try {
            $response = $call($this->client());
        } catch (ConnectionException $e) {
            throw new ConnectWiseException("ConnectWise API {$method} {$path} failed: ".$e->getMessage());
        }

        if ($response->failed()) {
            Log::error('ConnectWise API request failed', [
                'method' => $method,
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new ConnectWiseException(
                "ConnectWise API {$method} {$path} failed with status {$response->status()}",
                $response->status(),
                $response->json(),
            );
        }

        return $response->json() ?? [];
    }
}
