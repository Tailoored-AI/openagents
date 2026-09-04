---
paths:
  - 'app/**/Knowledge/**'
---

# Knowledge

## Knowledge pages: version-guarded JSON autosave and a client-only editor
KnowledgePage rows store BlockNote block JSON in jsonb `content` (validated by App\Rules\BlockNoteDocument: shape, depth, node count and byte caps, block types deliberately not enumerated). knowledge.update is a plain JSON PATCH used by the editor's autosave (useHttp, no Inertia headers): SaveKnowledgePage locks the row, compares the integer `version` sent by the client and returns 409 with {message, version, updatedBy} on mismatch; do not add Inertia::flash there. Postgres timestamps are second precision, which is why an integer version, not updated_at, is the guard. Deleting a page cascades to its subtree at the DB level (parent_id FK), no soft deletes. Policy: KnowledgePagePolicy, any member views/creates/updates, delete = author or TeamPermission::ManageKnowledge. Frontend: resources/js/components/knowledge-editor.tsx is the only runtime BlockNote import ('use no memo' for React Compiler) and is mounted via client-only-knowledge-editor.tsx (mounted flag + React.lazy) because Inertia SSR is enabled; other files may only `import type` from @blocknote/core. Tailwind scans the package via `@source '../../node_modules/@blocknote/shadcn'` in app.css. Moving/reordering pages and image uploads are intentionally out of v1.
