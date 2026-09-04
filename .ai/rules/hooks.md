---
paths:
  - 'resources/js/hooks/**'
---

# Hooks

## Inertia v3 useHttp: transform runs at submit time, 422 goes only to onError, submit always rethrows
useHttp applies http.transform() synchronously inside patch()/post(), so a ref cleared before the call is already empty when the body is built (use-knowledge-page-autosave.ts keeps the outgoing payload in sendingRef for that reason). A 422 is routed exclusively to onError (onHttpException/onNetworkError are never called for it), and submit rethrows after every non-422 failure regardless of what the callbacks return, so chain .catch() on the promise. router.reload() forces preserveState: true and never remounts the page component; to reset component state (refs, useState seeds, an editor's initialContent) visit window.location.href with preserveState: false.
