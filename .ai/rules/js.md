---
paths:
  - 'resources/js/**'
---

# Js

## npm run check:fix also reformats agent docs and config

`vp check --fix` (npm run check:fix) formats every file the fmt ignorePatterns in vite.config.ts do not exclude, including .ai/rules/*.md, .claude/skills/**, CLAUDE.md, .mcp.json and boost.json. After running it, revert that churn with `git checkout -- .ai .claude CLAUDE.md .mcp.json boost.json` (or run `vp check --fix` on specific paths) so unrelated files do not land in the diff.
