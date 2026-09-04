---
paths:
  - 'app/Http/Clients/**'
---

# Clients

## ComposioClient is a raw HTTP wrapper; execute tools only through IntegrationProvider::executeTool(Team ...)
ComposioClient knows nothing about Team or Integration: it takes raw Composio ids (user_id, auth_config_id, connected_account_id), returns App\Data\Composio* payload objects and throws Illuminate HttpClientException. Composio does not enforce team boundaries, so agent code must never call it directly: use App\Integrations\Contracts\IntegrationProvider::executeTool(Team $team, string $toolSlug, array $arguments, ?Integration $integration = null), which derives the Composio user id from the team, refuses an Integration of another team, and wraps transport failures in IntegrationProviderException. Pin a connection with $team->activeIntegration($appSlug). Config stays at services.composio.key / services.composio.url (COMPOSIO_API_KEY).

## Decode Composio responses through payload() and encode path segments
Every 2xx body is read via ComposioClient::payload(), which throws ComposioException (an HttpClientException) when json() is not an array, so an HTML maintenance page or empty body degrades like a failed request instead of a TypeError escaping every catch. Ids and slugs interpolated into paths are rawurlencode()d, and StoreIntegrationRequest restricts app_id to [A-Za-z0-9_-], so user input cannot steer the authenticated request at another endpoint.
