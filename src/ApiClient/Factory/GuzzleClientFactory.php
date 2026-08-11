<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Factory;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// @phpstan-ignore-next-line
class GuzzleClientFactory
{
    private const TOKEN_URI_SANDBOX = 'https://identity.topi-sandbox.eu/oauth2/token';
    private const TOKEN_URI_PRODUCTION = 'https://identity.topi.eu/oauth2/token';
    private const API_BASE_SANDBOX = 'https://seller-api-sandbox.topi-sandbox.eu/v1/';
    private const API_BASE_PRODUCTION = 'https://seller-api.topi.eu/v1/';

    // Neither client below had a timeout before — a stalled connection to Topi (seen
    // once in the wild: CaptureAction logged, then nothing, forever) left the
    // customer's browser waiting on /payment/capture/{token} indefinitely, since
    // that's a synchronous call blocking the checkout redirect. These bound how long
    // a single checkout/sync can hang before failing loudly instead of silently.
    private const CONNECT_TIMEOUT_SECONDS = 5.0;
    private const REQUEST_TIMEOUT_SECONDS = 30.0;
    private const TOKEN_REQUEST_TIMEOUT_SECONDS = 10.0;

    /**
     * @var array<string, GuzzleClient>
     */
    private array $clientCache = [];

    public function __construct(
        #[Autowire(env: 'TOPI_CLIENT_ID')]
        private readonly string $clientId,
        #[Autowire(env: 'TOPI_CLIENT_SECRET')]
        private readonly string $clientSecret,
        #[Autowire(env: 'bool:TOPI_ENABLE_LIVE')]
        private readonly bool $enableLive,
    ) {}

    public function make(?string $clientId = null, ?string $clientSecret = null): GuzzleClient
    {
        $cacheKey = ($clientId ?? 'default') . ':' . ($clientSecret ?? 'default');
        if (!isset($this->clientCache[$cacheKey])) {
            $this->clientCache[$cacheKey] = $this->createClientInstance($clientId, $clientSecret);
        }

        return $this->clientCache[$cacheKey];
    }

    private function createClientInstance(?string $clientId = null, ?string $clientSecret = null): GuzzleClient
    {
        $tokenUri = $this->enableLive ? self::TOKEN_URI_PRODUCTION : self::TOKEN_URI_SANDBOX;
        $resolvedClientId = $clientId ?: $this->clientId;
        $resolvedClientSecret = $clientSecret ?: $this->clientSecret;

        $accessToken = null;
        $tokenExpiresAt = 0;

        $fetchToken = function () use ($tokenUri, $resolvedClientId, $resolvedClientSecret, &$accessToken, &$tokenExpiresAt): void {
            $response = new GuzzleClient()->post($tokenUri, [
                'auth'         => [$resolvedClientId, $resolvedClientSecret],
                'form_params'  => [
                    'grant_type' => 'client_credentials',
                    'scope' => 'client',
                ],
                'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
                'timeout' => self::TOKEN_REQUEST_TIMEOUT_SECONDS,
            ]);
            $data = json_decode($response->getBody()->getContents(), true) ?? [];
            $accessToken = $data['access_token'] ?? null;
            $tokenExpiresAt = time() + max(0, ($data['expires_in'] ?? 3600) - 30);
        };

        $authMiddleware = function (callable $handler) use (&$accessToken, &$tokenExpiresAt, $fetchToken): callable {
            return function (RequestInterface $request, array $options) use ($handler, &$accessToken, &$tokenExpiresAt, $fetchToken) {
                if ($accessToken === null || time() >= $tokenExpiresAt) {
                    $fetchToken();
                }
                if ($accessToken !== null) {
                    $request = $request->withHeader('Authorization', 'Bearer ' . $accessToken);
                }

                return $handler($request, $options)->then(
                    static function ($response) use ($request, $options, $handler, $fetchToken, &$accessToken) {
                        if ($response->getStatusCode() !== 401 || $request->hasHeader('X-Guzzle-Retry')) {
                            return $response;
                        }
                        $accessToken = null;
                        $fetchToken();
                        // @phpstan-ignore-next-line ($accessToken is mutated via reference inside $fetchToken)
                        if ($accessToken === null) {
                            return $response;
                        }

                        return $handler(
                            $request->withHeader('Authorization', 'Bearer ' . $accessToken)
                                     ->withHeader('X-Guzzle-Retry', '1'),
                            $options
                        );
                    }
                );
            };
        };

        $handlerStack = HandlerStack::create();
        $handlerStack->push($authMiddleware);

        return new GuzzleClient([
            'base_uri' => $this->enableLive ? self::API_BASE_PRODUCTION : self::API_BASE_SANDBOX,
            'handler'  => $handlerStack,
            'headers'  => [
                'User-Agent' => 'TopiPayment/Sylius 1.0',
            ],
            'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
            'timeout' => self::REQUEST_TIMEOUT_SECONDS,
        ]);
    }
}
