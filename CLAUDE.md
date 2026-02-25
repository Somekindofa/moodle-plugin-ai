# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> The parent `/var/www/html/public/CLAUDE.md` covers build commands, Moodle conventions, AMD JS format, and key file paths. Read it first.

---

## Module Overview

`mod_craftpilot` is a Moodle activity that combines a rich-content page with an AI chat panel. The chat streams responses from a RAG backend (`http://127.0.0.1:8000/api/chat` — server-local only) via a PHP streaming proxy, and surfaces retrieved media (video, BVH motion-capture, text) as interactive source cards.

**Component name**: `mod_craftpilot`
**Version**: 2025121601 (alpha)

---

## Database Schema

Three active tables (defined in `db/install.xml`):

| Table | Key columns | Notes |
|---|---|---|
| `craftpilot` | `course`, `name`, `content`, `contentformat`, `enable_promptbar` | One row per activity instance |
| `craftpilot_conv` | `conversation_id` (UUID, UNIQUE), `instanceid`, `userid`, `title`, `is_active` | Soft-deleted (`is_active=0`), not hard-deleted |
| `craftpilot_msg` | `conversation_id`, `message_type` ('user'\|'ai'), `content`, `sequence_number` | Ordered by `sequence_number`; auto-incremented on save |

`craftpilot_keys`, `craftpilot_proj`, `craftpilot_videos`, `craftpilot_annot` are defined in the schema but minimally used.

**Schema changes**: Edit `db/install.xml`, bump the version in the XMLDB root AND in `version.php`, add a guard block in `db/upgrade.php`, then visit Site Admin → Notifications.

---

## External Functions (AJAX)

Registered in `db/services.php`. All require `ajax: true` and a valid Moodle session.

### `mod_craftpilot_get_user_credentials`
Returns the site-wide Fireworks API key. Tries `mod_craftpilot` config first, then legacy `mod_aiassistant` and `block_aiassistant` configs (see `classes/credential_service.php`).

### `mod_craftpilot_manage_conversations`
CRUD for `craftpilot_conv`. Actions: `create`, `list`, `update`, `delete`.
- `delete` sets `is_active=0` and cascade-deletes all messages for that conversation.
- `list` filters by `userid` + optional `instanceid`, returns only `is_active=1` rows.

### `mod_craftpilot_manage_messages`
Read/write for `craftpilot_msg`. Actions: `save`, `load`.
- `save` auto-increments `sequence_number` (MAX + 1) and bumps `craftpilot_conv.last_updated`.
- `load` returns all messages ordered by `sequence_number ASC`.

**Adding a new external function**: create `classes/external/my_fn.php` extending `external_api`, register it in `db/services.php`, purge caches.

---

## SSE Streaming Protocol

`chat_proxy.php` forwards a JSON POST to `127.0.0.1:8000/api/chat` and streams the response back as newline-delimited JSON. The JS parser in `streamFromBackend()` handles these event shapes:

```jsonc
{"event": "message",        "content": [{"content": "text chunk"}]}
{"event": "video_metadata", "data": {"video_id": "...", "filename": "...", "video_url": "/api/video/stream/{id}"}}
{"event": "bvh_metadata",   "data": {"bvh_id": "...", "bvh_url": "...", "filename": "..."}}
[DONE]                       // literal string — signals end of stream
{"event": "error",          "message": "..."}
```

`video_metadata` events whose filename ends in `.bvh` are treated as BVH files regardless of the event name.

---

## JavaScript Architecture (`amd/src/chat_interface.js`)

Single AMD module with one public export: `init(cmId, courseId, instanceId, proxyUrl)`.

**State object** (`state`) holds: `cmId`, `courseId`, `instanceId`, `chatProxyUrl`, `currentConvId`, `conversations[]`, `sidebarOpen`, `chatOpen`, `streaming`, `sources[]`.

**DOM object** (`dom`) is populated by `initDOM()` mapping element IDs. Required elements: `toggle`, `wrapper`, `chatBody`, `messages`, `input`, `sendBtn`.

**Key call chain for sending a message:**
```
sendMessage()
  → saveMessage(convId, 'user', text)     // fire-and-forget AJAX
  → appendMessage('user', text)
  → streamFromBackend(text)
      → fetch(chatProxyUrl, {method:'POST', body: JSON})
      → parse newline-delimited JSON chunks
      → on 'message': append/update AI bubble
      → on 'video_metadata'/'bvh_metadata': addSource(item)
      → on '[DONE]': saveMessage(convId, 'ai', fullText)
```

**BVH viewer** (`BVHViewer` class): lazy-loads Three.js from CDN on first use, parses the BVH file (HIERARCHY + MOTION sections), renders skeleton with forward kinematics using `THREE.Matrix4`/`THREE.Euler`. Assumes 30 fps if no frame-time is specified in the BVH file.

**UI structure** (Mustache template → CSS → JS):
- `.cp-wrapper` (fixed, bottom-right) contains:
  - `.cp-toggle-pill` — always visible tab at bottom of screen
  - `.cp-chat-body` — collapses via `max-height: 0 → var(--cp-panel-h)`
    - `.cp-sidebar` — conversation list, collapses via `width: 0 → var(--cp-sidebar-w)`
    - `.cp-panel` — main chat area (header, messages, sources, input)

CSS state classes: `cp-wrapper--open`, `cp-wrapper--sidebar-open`, `cp-sidebar--open`, `cp-sources--open`, `cp-sources--visible`, `cp-toggle-pill--active`.

---

## Activity Form Settings

`mod_form.php` exposes three sections:

| Field | Type | Notes |
|---|---|---|
| `name` | text(255) | Required |
| `intro` | HTML editor | Standard Moodle intro |
| `content` | HTML editor (rows=20) | Rich content with file upload area |
| `enable_promptbar` | checkbox | Default: 1 (enabled); controls whether JS/template loads |

---

## Capabilities

`db/access.php` defines only two capabilities:

- `mod/craftpilot:view` — granted to all roles including Guest
- `mod/craftpilot:addinstance` — Teacher, Manager only

---

## Styles

`styles.css` is auto-included by Moodle for this module. All CSS uses `--cp-*` custom properties defined in `:root`. No build step — edit and purge caches.

Brand tokens: `--cp-blue: #0f6cbf`, `--cp-gray-1: #E8E8E8`, `--cp-gray-2: #EDEDED`.
Fonts: `Fraunces` (display/headings) + `DM Sans` (body) via Google Fonts import at top of `styles.css`.
