---
paths:
  - 'resources/js/pages/**'
---

# Pages

## Rebuild assets after adding an Inertia page or its feature tests 500

resources/views/app.blade.php passes the page component to @vite, so a GET to a new page throws ViteException ("Unable to locate file in Vite manifest") until public/build/manifest.json includes it. public/build is gitignored, so run `./vendor/bin/sail npm run build` after creating a page before running its feature tests. CI is unaffected because `composer setup` builds first.
