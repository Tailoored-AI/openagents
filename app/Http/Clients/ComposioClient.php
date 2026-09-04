<?php

namespace App\Http\Clients;

use App\Data\ComposioAuthConfig;
use App\Data\ComposioConnectedAccount;
use App\Data\ComposioConnectionLink;
use App\Data\ComposioToolkit;
use App\Data\ComposioToolResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * A thin client for the Composio REST API.
 *
 * @see https://docs.composio.dev
 */
class ComposioClient
{
    /**
     * The largest page size Composio accepts on list endpoints.
     */
    protected const int PAGE_SIZE = 50;

    /**
     * The maximum number of catalog pages fetched in one call.
     */
    protected const int MAX_PAGES = 10;

    public function __construct(
        protected readonly ?string $apiKey,
        protected readonly string $baseUrl,
    ) {
        //
    }

    /**
     * Determine if an API key has been configured.
     */
    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * Get the auth configs of the Composio project: the apps that teams may connect.
     *
     * @return Collection<int, ComposioAuthConfig>
     *
     * @throws HttpClientException
     */
    public function authConfigs(): Collection
    {
        $authConfigs = [];
        $cursor = null;

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $response = $this->request()
                ->get('auth_configs', array_filter([
                    'limit' => self::PAGE_SIZE,
                    'cursor' => $cursor,
                ]))
                ->throw();

            $payload = $this->payload($response);

            foreach ((array) Arr::get($payload, 'items', []) as $item) {
                if (is_array($item)) {
                    $authConfigs[] = ComposioAuthConfig::fromArray($item);
                }
            }

            $cursor = Arr::get($payload, 'next_cursor');

            if (blank($cursor)) {
                break;
            }
        }

        return collect($authConfigs);
    }

    /**
     * Get a single auth config, or null when Composio does not know it.
     *
     * @throws HttpClientException
     */
    public function authConfig(string $id): ?ComposioAuthConfig
    {
        $response = $this->request()->get('auth_configs/'.rawurlencode($id));

        if ($response->notFound()) {
            return null;
        }

        return ComposioAuthConfig::fromArray($this->payload($response->throw()));
    }

    /**
     * Describe a toolkit, falling back to a placeholder named after its slug.
     */
    public function toolkit(string $slug): ComposioToolkit
    {
        return $this->toolkits([$slug])[$slug] ?? ComposioToolkit::fallback($slug);
    }

    /**
     * Describe the given toolkits, asking Composio at most once a day for each.
     *
     * Toolkits Composio cannot describe fall back to a placeholder named after
     * their slug, so the catalog always renders.
     *
     * @param  list<string>  $slugs
     * @return array<string, ComposioToolkit>
     */
    public function toolkits(array $slugs): array
    {
        $slugs = array_values(array_unique($slugs));

        if ($slugs === []) {
            return [];
        }

        $cached = Cache::many(array_map($this->toolkitCacheKey(...), $slugs));

        $toolkits = [];
        $missing = [];

        foreach ($slugs as $slug) {
            $toolkit = $cached[$this->toolkitCacheKey($slug)] ?? null;

            if ($toolkit instanceof ComposioToolkit) {
                $toolkits[$slug] = $toolkit;
            } else {
                $missing[] = $slug;
            }
        }

        if ($missing === []) {
            return $toolkits;
        }

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (string $slug) => $this->configure($pool->as($slug))->get('toolkits/'.rawurlencode($slug)),
            $missing,
        ));

        $fetched = [];

        foreach ($missing as $slug) {
            $response = $responses[$slug] ?? null;

            if ($response instanceof Response && $response->successful() && is_array($response->json())) {
                $toolkits[$slug] = ComposioToolkit::fromArray($response->json());
                $fetched[$this->toolkitCacheKey($slug)] = $toolkits[$slug];

                continue;
            }

            $toolkits[$slug] = ComposioToolkit::fallback($slug);
        }

        if ($fetched !== []) {
            Cache::putMany($fetched, now()->addDay());
        }

        return $toolkits;
    }

    /**
     * Create a hosted authorization link the user must visit to connect an app.
     *
     * @throws HttpClientException
     */
    public function createConnectionLink(string $authConfigId, string $userId, string $callbackUrl): ComposioConnectionLink
    {
        $payload = $this->payload(
            $this->request()
                ->post('connected_accounts/link', [
                    'auth_config_id' => $authConfigId,
                    'user_id' => $userId,
                    'callback_url' => $callbackUrl,
                ])
                ->throw(),
        );

        $connectedAccountId = Arr::get($payload, 'connected_account_id');
        $redirectUrl = Arr::get($payload, 'redirect_url');

        if (! is_string($connectedAccountId) || $connectedAccountId === '' || ! is_string($redirectUrl) || $redirectUrl === '') {
            throw new ComposioException('Composio returned an incomplete connection link.');
        }

        return new ComposioConnectionLink($connectedAccountId, $redirectUrl);
    }

    /**
     * Get several connected accounts concurrently.
     *
     * Each entry is the account, null when Composio no longer knows it, or the
     * exception that prevented it from being fetched.
     *
     * @param  list<string>  $ids
     * @return array<string, ComposioConnectedAccount|HttpClientException|null>
     */
    public function connectedAccounts(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (string $id) => $this->configure($pool->as($id))->get('connected_accounts/'.rawurlencode($id)),
            $ids,
        ));

        $accounts = [];

        foreach ($ids as $id) {
            $response = $responses[$id] ?? null;

            try {
                if (! $response instanceof Response) {
                    throw $response instanceof HttpClientException
                        ? $response
                        : new ConnectionException(
                            "Composio did not answer for connected account [{$id}].",
                            0,
                            $response instanceof Throwable ? $response : null,
                        );
                }

                $accounts[$id] = $response->notFound()
                    ? null
                    : ComposioConnectedAccount::fromArray($this->payload($response->throw()));
            } catch (HttpClientException $exception) {
                $accounts[$id] = $exception;
            }
        }

        return $accounts;
    }

    /**
     * Delete a connected account. An account Composio no longer knows counts as deleted.
     *
     * @throws HttpClientException
     */
    public function deleteConnectedAccount(string $id): void
    {
        $response = $this->request()->delete('connected_accounts/'.rawurlencode($id));

        if ($response->notFound()) {
            return;
        }

        $response->throw();
    }

    /**
     * Execute a Composio tool with the credentials stored under the given user id.
     *
     * Pass a connected account id to pin the account when the user connected the
     * same app more than once.
     *
     * @param  array<string, mixed>  $arguments
     *
     * @throws HttpClientException
     */
    public function executeTool(string $userId, string $toolSlug, array $arguments = [], ?string $connectedAccountId = null): ComposioToolResult
    {
        $payload = [
            'user_id' => $userId,
            'arguments' => (object) $arguments,
        ];

        if ($connectedAccountId !== null) {
            $payload['connected_account_id'] = $connectedAccountId;
        }

        return ComposioToolResult::fromArray($this->payload(
            $this->request()->post('tools/execute/'.rawurlencode($toolSlug), $payload)->throw(),
        ));
    }

    /**
     * Get a pending request configured for the Composio API.
     */
    protected function request(): PendingRequest
    {
        return $this->configure(Http::baseUrl($this->baseUrl));
    }

    /**
     * Apply the Composio base URL, credentials and timeouts to the given request.
     */
    protected function configure(PendingRequest $request): PendingRequest
    {
        return $request
            ->baseUrl($this->baseUrl)
            ->withHeaders(['x-api-key' => (string) $this->apiKey])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15);
    }

    /**
     * Get the decoded JSON object of a successful response.
     *
     * A 2xx response whose body is not a JSON object (an HTML maintenance page,
     * an empty body) is as unusable as a failed one and is reported the same way.
     *
     * @return array<string, mixed>
     *
     * @throws ComposioException
     */
    protected function payload(Response $response): array
    {
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new ComposioException('Composio answered with a body that is not JSON.');
        }

        return $payload;
    }

    /**
     * Get the cache key under which a toolkit description is stored.
     */
    protected function toolkitCacheKey(string $slug): string
    {
        return "composio:toolkit:{$slug}";
    }
}
