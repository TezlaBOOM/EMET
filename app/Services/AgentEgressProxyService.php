<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Telemetry\TelemetryCollectorInterface;
use App\Models\Agent;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AgentEgressProxyService
{
    public function __construct(
        protected TelemetryCollectorInterface $telemetry
    ) {}

    /**
     * Weryfikuje możliwość wykonania wychodzącego połączenia sieciowego przez agenta.
     *
     * @param  array<int, string>  $allowedDomains
     * @return array{allowed: bool, reason: ?string, token: ?string, host: ?string}
     */
    public function validateRequest(
        Agent $agent,
        string $url,
        array $allowedDomains = [],
        ?string $runId = null
    ): array {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? null;
        $scheme = $parsed['scheme'] ?? null;

        if (! $host || ! in_array($scheme, ['http', 'https'], true)) {
            $reason = 'invalid_url';
            $this->telemetry->recordEvent('internet.blocked', $agent->id, $runId, [
                'url' => $url,
                'domain' => $host,
                'reason' => $reason,
            ]);

            return ['allowed' => false, 'reason' => $reason, 'token' => null, 'host' => $host];
        }

        // 1. Sprawdzenie blokady SSRF (adresy prywatne, pętla zwrotna, metadane chmury)
        if ($this->isSsrfTarget($host)) {
            $reason = 'ssrf_blocked';
            $this->telemetry->recordEvent('internet.blocked', $agent->id, $runId, [
                'url' => $url,
                'domain' => $host,
                'reason' => $reason,
            ]);

            return ['allowed' => false, 'reason' => $reason, 'token' => null, 'host' => $host];
        }

        // 2. Sprawdzenie trybu dostępu do internetu agenta
        if ($agent->internet_mode === Agent::INTERNET_OFF) {
            $reason = 'internet_disabled';
            $this->telemetry->recordEvent('internet.blocked', $agent->id, $runId, [
                'url' => $url,
                'domain' => $host,
                'reason' => $reason,
            ]);

            return ['allowed' => false, 'reason' => $reason, 'token' => null, 'host' => $host];
        }

        // 3. Sprawdzenie allowlisty dla trybu allowlist
        if ($agent->internet_mode === Agent::INTERNET_ALLOWLIST) {
            if (! $this->isHostInAllowlist($host, $allowedDomains)) {
                $reason = 'domain_not_allowlisted';
                $this->telemetry->recordEvent('internet.blocked', $agent->id, $runId, [
                    'url' => $url,
                    'domain' => $host,
                    'reason' => $reason,
                ]);

                return ['allowed' => false, 'reason' => $reason, 'token' => null, 'host' => $host];
            }
        }

        // 4. Tokenizacja żądania
        $token = 'egress_'.hash_hmac('sha256', "agent:{$agent->id}:{$url}:".microtime(), (string) config('app.key'));

        $this->telemetry->recordEvent('internet.request', $agent->id, $runId, [
            'url' => $url,
            'domain' => $host,
            'token' => $token,
        ]);

        return ['allowed' => true, 'reason' => null, 'token' => $token, 'host' => $host];
    }

    /**
     * Wykonuje zapytanie HTTP przez proxy z weryfikacją egress.
     *
     * @param  array<string, mixed>  $options
     * @param  array<int, string>  $allowedDomains
     * @return array{status: int, body: string, headers: array<string, mixed>}
     */
    public function send(
        Agent $agent,
        string $method,
        string $url,
        array $options = [],
        array $allowedDomains = [],
        ?string $runId = null
    ): array {
        $validation = $this->validateRequest($agent, $url, $allowedDomains, $runId);

        if (! $validation['allowed']) {
            throw new RuntimeException("Egress proxy blocked request to {$url}: {$validation['reason']}");
        }

        $headers = $options['headers'] ?? [];
        $headers['X-Agent-Egress-Token'] = $validation['token'];
        $headers['X-Agent-Id'] = (string) $agent->id;

        $response = Http::withHeaders($headers)
            ->timeout($options['timeout'] ?? 10)
            ->send($method, $url, $options);

        return [
            'status' => $response->status(),
            'body' => $response->body(),
            'headers' => $response->headers(),
        ];
    }

    /**
     * Wykrywa próby SSRF (localhost, IP prywatne, IP link-local).
     */
    public function isSsrfTarget(string $host): bool
    {
        $lowercaseHost = strtolower($host);

        if ($lowercaseHost === 'localhost' || str_ends_with($lowercaseHost, '.local') || str_ends_with($lowercaseHost, '.internal')) {
            return true;
        }

        // Sprawdzamy czy to adres IP
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : @gethostbyname($host);

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Blokada loopback (127.0.0.0/8, ::1)
        if (str_starts_with($ip, '127.') || $ip === '::1' || $ip === '0.0.0.0') {
            return true;
        }

        // Blokada AWS/GCP/Cloud metadata IP: 169.254.169.254
        if (str_starts_with($ip, '169.254.')) {
            return true;
        }

        // Sprawdzenie flag prywatnych i zarezerwowanych IP
        $isPublic = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        return $isPublic === false;
    }

    /**
     * Sprawdza dopasowanie hosta do allowlisty domen.
     *
     * @param  array<int, string>  $allowedDomains
     */
    protected function isHostInAllowlist(string $host, array $allowedDomains): bool
    {
        $host = strtolower($host);

        foreach ($allowedDomains as $allowed) {
            $allowed = strtolower(trim($allowed));
            if ($allowed === '') {
                continue;
            }

            if ($host === $allowed) {
                return true;
            }

            // Obsługa subdomen, np. api.github.com pasuje do github.com lub *.github.com
            $allowedRoot = ltrim($allowed, '*.');
            if (str_ends_with($host, '.'.$allowedRoot)) {
                return true;
            }
        }

        return false;
    }
}
