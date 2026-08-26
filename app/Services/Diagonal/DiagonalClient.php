<?php

namespace App\Services\Diagonal;

use App\Models\Gym;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for the DIAGONAL Inkasso API (OpenAPI v1.2).
 *
 * The API is asynchronous: every write returns a GUID whose processing state
 * has to be polled separately through the matching GetState endpoint.
 */
class DiagonalClient
{
    protected const API_PREFIX = '/api/v1.2';

    /** Token lifetime fallback when the API does not return an expiration. */
    protected const TOKEN_TTL_MINUTES = 30;

    /**
     * Authenticate and return a bearer token. Without an explicit base URL the
     * production host is used.
     *
     * @throws DiagonalApiException
     */
    public function login(string $username, string $password, ?string $baseUrl = null): string
    {
        $response = $this->http($baseUrl)->post(self::API_PREFIX.'/Authenticate/login', [
            'username' => $username,
            'password' => $password,
        ]);

        $this->assertSuccessful($response, 'Anmeldung bei DIAGONAL fehlgeschlagen.');

        $token = $response->json('token');

        if (! $token) {
            throw new DiagonalApiException('DIAGONAL hat kein Token zurückgegeben.', $response->status());
        }

        return $token;
    }

    /**
     * A cached token for the gym, logging in again when it expired.
     *
     * @throws DiagonalApiException
     */
    public function tokenFor(Gym $gym): string
    {
        $settings = $gym->inkasso_settings;
        $username = $settings['username'] ?? null;
        $password = $gym->getInkassoPassword();

        if (! $username || ! $password) {
            throw new DiagonalApiException('Es sind keine DIAGONAL-Zugangsdaten hinterlegt.');
        }

        $baseUrl = $this->baseUrlFor($gym);

        return Cache::remember(
            $this->tokenCacheKey($gym),
            now()->addMinutes(self::TOKEN_TTL_MINUTES),
            fn () => $this->login($username, $password, $baseUrl)
        );
    }

    /**
     * Verify the stored credentials without changing any state.
     *
     * The token is never cached here: the check may run against credentials
     * that are not saved yet, which must not become the cached token.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(Gym $gym): array
    {
        try {
            $settings = $gym->inkasso_settings;
            $username = $settings['username'] ?? null;
            $password = $gym->getInkassoPassword();

            if (! $username || ! $password) {
                throw new DiagonalApiException('Es sind keine DIAGONAL-Zugangsdaten hinterlegt.');
            }

            $this->login($username, $password, $this->baseUrlFor($gym));

            $message = $gym->usesInkassoSandbox()
                ? 'Verbindung zur DIAGONAL-Testumgebung erfolgreich hergestellt.'
                : 'Verbindung zu DIAGONAL erfolgreich hergestellt.';

            return ['success' => true, 'message' => $message];
        } catch (DiagonalApiException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Transmit one case file.
     *
     * @param  array<string, mixed>  $file
     * @return string the GUID assigned by DIAGONAL
     *
     * @throws DiagonalApiException
     */
    public function addFile(Gym $gym, array $file): string
    {
        $response = $this->authenticated($gym)
            ->post($this->clientPath($gym, '/FileData/AddItem'), $file);

        $this->assertSuccessful($response, 'Die Akte konnte nicht an DIAGONAL übertragen werden.');

        return $this->extractGuid($response);
    }

    /**
     * Transmit multiple case files at once.
     *
     * @param  array<int, array<string, mixed>>  $files
     * @return array<int, string> the returned GUIDs
     *
     * @throws DiagonalApiException
     */
    public function addFiles(Gym $gym, array $files): array
    {
        $response = $this->authenticated($gym)
            ->post($this->clientPath($gym, '/FileData/AddItems'), $files);

        $this->assertSuccessful($response, 'Die Akten konnten nicht an DIAGONAL übertragen werden.');

        return $this->extractGuids($response);
    }

    /**
     * Current processing state of a transmitted case file.
     *
     * @throws DiagonalApiException
     */
    public function getFileState(Gym $gym, string $guid): ?string
    {
        $response = $this->authenticated($gym)
            ->get($this->clientPath($gym, '/FileData/GetStateByGuid/'.rawurlencode($guid)));

        $this->assertSuccessful($response, 'Der Status der Akte konnte nicht abgerufen werden.');

        return $response->json('status') ?? $response->json('data.state');
    }

    /**
     * Report a payment for a transmitted case file.
     *
     * @param  array<string, mixed>  $payment
     *
     * @throws DiagonalApiException
     */
    public function addPayment(Gym $gym, array $payment): string
    {
        $response = $this->authenticated($gym)
            ->post($this->clientPath($gym, '/PaymentData/AddItem'), $payment);

        $this->assertSuccessful($response, 'Die Zahlung konnte nicht an DIAGONAL übertragen werden.');

        return $this->extractGuid($response);
    }

    /**
     * Request the cancellation of a case file.
     *
     * @param  array<string, mixed>  $cancellation
     *
     * @throws DiagonalApiException
     */
    public function cancelFile(Gym $gym, array $cancellation): string
    {
        $response = $this->authenticated($gym)
            ->post($this->clientPath($gym, '/FileCancellation/AddItem'), $cancellation);

        $this->assertSuccessful($response, 'Die Stornierung konnte nicht an DIAGONAL übertragen werden.');

        return $this->extractGuid($response);
    }

    /**
     * Request a dunning break for a case file.
     *
     * @param  array<string, mixed>  $item
     *
     * @throws DiagonalApiException
     */
    public function addDunningBreak(Gym $gym, array $item): string
    {
        $response = $this->authenticated($gym)
            ->post($this->clientPath($gym, '/DunningBreak/AddItem'), $item);

        $this->assertSuccessful($response, 'Der Mahnstopp konnte nicht übertragen werden.');

        return $this->extractGuid($response);
    }

    /**
     * Query client specific information about a file.
     *
     * @return array<string, mixed>
     *
     * @throws DiagonalApiException
     */
    public function getGlobalDataPool(Gym $gym, ?string $searchedId = null): array
    {
        $clientName = $this->clientName($gym);
        $path = $searchedId
            ? self::API_PREFIX."/GlobalDataPool/GetItem/{$clientName}/{$searchedId}"
            : self::API_PREFIX."/GlobalDataPool/GetItems/{$clientName}";

        $response = $this->authenticated($gym)->get($path);

        $this->assertSuccessful($response, 'Die Sachstandsabfrage bei DIAGONAL ist fehlgeschlagen.');

        return $response->json() ?? [];
    }

    /**
     * Drop the cached tokens of both environments, so a switch between them
     * cannot reuse a token issued by the other host.
     */
    public function forgetToken(Gym $gym): void
    {
        Cache::forget("diagonal.token.{$gym->id}");
        Cache::forget("diagonal.token.{$gym->id}.sandbox");
    }

    protected function authenticated(Gym $gym): PendingRequest
    {
        return $this->http($this->baseUrlFor($gym))->withToken($this->tokenFor($gym));
    }

    protected function http(?string $baseUrl = null): PendingRequest
    {
        $baseUrl ??= (string) config('services.diagonal.base_url');

        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->timeout((int) config('services.diagonal.timeout', 30))
            ->acceptJson()
            ->asJson();
    }

    /**
     * The API host of the gym: the test environment when sandbox mode is on.
     */
    protected function baseUrlFor(Gym $gym): string
    {
        return (string) ($gym->usesInkassoSandbox()
            ? config('services.diagonal.sandbox_base_url')
            : config('services.diagonal.base_url'));
    }

    /**
     * Tokens are cached per environment; they are not interchangeable.
     */
    protected function tokenCacheKey(Gym $gym): string
    {
        return "diagonal.token.{$gym->id}".($gym->usesInkassoSandbox() ? '.sandbox' : '');
    }

    /**
     * Build a path carrying the client name, which is the tenant id configured
     * for the gym.
     */
    protected function clientPath(Gym $gym, string $path): string
    {
        return self::API_PREFIX.$path.'/'.$this->clientName($gym);
    }

    protected function clientName(Gym $gym): string
    {
        $tenantId = $gym->inkasso_settings['tenant_id'] ?? null;

        if (! $tenantId) {
            throw new DiagonalApiException('Es ist keine Mandanten-ID hinterlegt.');
        }

        return rawurlencode((string) $tenantId);
    }

    /**
     * @throws DiagonalApiException
     */
    protected function assertSuccessful(Response $response, string $fallbackMessage): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('message')
            ?? $response->json('description')
            ?? $fallbackMessage;

        if ($response->status() === 401) {
            $message = 'Die DIAGONAL-Zugangsdaten wurden abgelehnt.';
        }

        Log::warning('DIAGONAL API request failed', [
            'status' => $response->status(),
            'error_code' => $response->json('errorCode'),
            'message' => $message,
        ]);

        throw new DiagonalApiException($message, $response->status(), $response->json('errorCode'));
    }

    /**
     * @throws DiagonalApiException
     */
    protected function extractGuid(Response $response): string
    {
        $guid = $response->json('data.guid')
            ?? $response->json('guid')
            ?? $response->json('data');

        if (! is_string($guid) || $guid === '') {
            throw new DiagonalApiException('DIAGONAL hat keine GUID zurückgegeben.', $response->status());
        }

        return $guid;
    }

    /**
     * @return array<int, string>
     */
    protected function extractGuids(Response $response): array
    {
        $data = $response->json('data') ?? [];

        if (is_string($data)) {
            return [$data];
        }

        $guids = [];

        foreach ((array) $data as $entry) {
            if (is_string($entry)) {
                $guids[] = $entry;
            } elseif (is_array($entry) && isset($entry['guid'])) {
                $guids[] = (string) $entry['guid'];
            }
        }

        return $guids;
    }
}
