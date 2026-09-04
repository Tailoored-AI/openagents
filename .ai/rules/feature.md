---
paths:
  - 'tests/Feature/**'
---

# Feature

## Inertia SSR is disabled for the test suite via INERTIA_SSR_ENABLED
config/inertia.php reads ssr.enabled from INERTIA_SSR_ENABLED (default true) and phpunit.xml sets it to false. With SSR on, every non-Inertia GET dispatches Http::post() to the Vite dev server's /__inertia_ssr whenever public/hot exists, and Inertia's HttpGateway rethrows StrayRequestException, so any page test using Http::preventStrayRequests() / Http::fake() (IntegrationTest, ComposioProviderTest) fails locally while `npm run dev` is running. Do not hard-code ssr.enabled back to true.
