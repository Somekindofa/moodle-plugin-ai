# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> The parent `/var/www/html/public/CLAUDE.md` covers build commands, Moodle conventions, AMD JS format, and key file paths. Read it first.

---

## Smoke Test

Run after any change to verify the plugin is fully operational:

```bash
bash /var/www/html/public/mod/craftpilot/smoke_test.sh
```

Exit 0 = all pass, exit 1 = one or more failures. Checks:
1. **AMD JS** — `amd/build/chat_interface.min.js` contains `define()` and has no raw ES6 `import`
2. **PHP syntax** — all plugin `.php` files pass `php -l`
3. **RAG backend** — `http://127.0.0.1:8000/api/health` returns `{"status":"healthy",...}`
4. **DB tables** — `mdl_craftpilot`, `mdl_craftpilot_conv`, `mdl_craftpilot_msg` exist
5. **External functions** — `mod_craftpilot_get_user_credentials`, `mod_craftpilot_manage_conversations`, `mod_craftpilot_manage_messages` registered
6. **Capabilities** — `mod/craftpilot:view`, `mod/craftpilot:addinstance` registered
7. **Assets** — `templates/chat_interface.mustache` and `styles.css` present and non-empty
8. **Cache purge** — `php purge_caches.php` exits 0

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

**State object** (`state`) holds: `cmId`, `courseId`, `instanceId`, `chatProxyUrl`, `currentConvId`, `conversations[]`, `sidebarOpen`, `chatOpen`, `streaming`, `sources[]`, `selectedDomain` (string|null).

**DOM object** (`dom`) is populated by `initDOM()` mapping element IDs. Required elements: `toggle`, `wrapper`, `chatBody`, `messages`, `input`, `sendBtn`. Optional: `domainBar` (`#cp-domain-bar`).

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

**Markdown rendering**: AI message bubbles are rendered as HTML via `marked.js` v17 (GFM + line-breaks). The library is bundled locally at `amd/build/marked.min.js` and loaded as a synchronous AMD dependency — `import * as MarkedLib from 'mod_craftpilot/marked'` — so it is guaranteed available before any message is rendered. The entry point is `renderMarkdown(text)` (bottom of `chat_interface.js`), which calls `MarkedLib.marked.parse(text)` after a one-time `setOptions({ gfm: true, breaks: true })`. Falls back to `escapeHtml(text)` (plain text, HTML-safe) if the import somehow resolves to nothing. User messages are always `textContent` (never markdown). CSS for rendered elements lives in `styles.css` under `.cp-msg--ai .cp-msg-bubble` (covers `p`, `ul/ol`, `code`, `pre`, `strong`, `a`, `h1–h3`).

> **Why bundled, not CDN**: the previous approach loaded `marked` via `$PAGE->requires->js(cdn_url, true)`. Even with `true` (head injection), the CDN network request could resolve after Moodle's RequireJS called `init()`, leaving `window.marked` undefined at first render time. Bundling it as an AMD module eliminates the race entirely.

> **Updating marked**: the source lives at `amd/src/marked.js` — a thin ES module wrapper generated from `marked.umd.js`. To update: re-run the Python extraction script embedded in the session history (or re-download `marked.umd.js`, patch `var module={exports}` → `var _marked_m={exports:{}};...var module=_marked_m;`, add ES exports at the bottom). Then run `grunt babel`. Do **not** put `marked.umd.js` directly into `amd/build/` — its internal `define("marked",f)` self-registration collides with the Moodle RequireJS module ID.

**UI structure** (Mustache template → CSS → JS):
- `.cp-wrapper` (fixed, bottom-right) contains:
  - `.cp-toggle-pill` — always visible tab at bottom of screen
  - `.cp-chat-body` — collapses via `max-height: 0 → var(--cp-panel-h)`
    - `.cp-sidebar` — conversation list, collapses via `width: 0 → var(--cp-sidebar-w)`
    - `.cp-panel` — main chat area (header, messages, sources, input)

CSS state classes: `cp-wrapper--open`, `cp-wrapper--sidebar-open`, `cp-sidebar--open`, `cp-sources--open`, `cp-sources--visible`, `cp-toggle-pill--active`, `cp-domain-btn--active`.

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

## Domain Selector

Three pill buttons above the chat input let the user focus the LLM on a specific craft domain. Clicking again deselects.

**Current domains**: `Soufflerie de verre`, `Scellerie nautique`, `Ganterie` (hardcoded in the Mustache template — add more buttons there).

**Data flow**:
1. JS stores the selection in `state.selectedDomain` and applies `cp-domain-btn--active` to the active button.
2. `streamFromBackend()` includes `selected_domain` in the POST body when set.
3. `chat_proxy.php` forwards the entire request body verbatim — no change needed there.
4. FastAPI `ChatRequest` model accepts `selected_domain: Optional[str]`.
5. `pipeline.generate_response()` passes it as part of the initial LangGraph state dict.
6. `ConversationState` carries `selected_domain: Optional[str]` through all graph nodes.
7. `rag_service.py` injects the domain at three points:
   - **`route_query()`** — hints the router that the user is focused on that domain.
   - **`generate()`** (RAG path) — fills `{specific_domain}` in the `PromptTemplate` with `"Vous vous concentrez particulièrement sur le domaine : {domain}."`.
   - **`direct_generate()`** (LLM-only path) — appends the same line to the direct prompt string.

**Adding a new domain**: add a `<button class="cp-domain-btn" data-domain="Nom du domaine">` to the `#cp-domain-bar` div in `templates/chat_interface.mustache`. No backend change needed.

**Prompt template note**: The RAG `PromptTemplate` in `rag_service.py` uses `input_variables=["history", "context", "query", "specific_domain"]`. The `{specific_domain}` placeholder must always be supplied (empty string when no domain is selected).

---

## Styles

`styles.css` is auto-included by Moodle for this module. All CSS uses `--cp-*` custom properties defined in `:root`. No build step — edit and purge caches.

Brand tokens: `--cp-blue: #0f6cbf`, `--cp-gray-1: #E8E8E8`, `--cp-gray-2: #EDEDED`.
Fonts: `Fraunces` (display/headings) + `DM Sans` (body) via Google Fonts import at top of `styles.css`.

---

## Course Content Ingestion and Retrieval — Design Notes

> This section documents the full pipeline added in version 2026022700 that extends CraftPilot's knowledge base from video annotations alone to include structured Moodle course content (Pages, Labels, PDF and DOCX file resources). It is intended to be a self-contained technical record of the design decisions, theoretical grounding, and implementation details, suitable as a reference for TEL research and future development.

### 1. Motivation and Problem Context

CraftPilot serves apprentices in niche vocational crafts — glassblowing, glove-making, nautical sealing — where instructor expertise is partially captured in two complementary modalities: (a) annotated video recordings of expert practitioners, and (b) structured written course content (Moodle Pages, uploaded PDFs) authored by the instructor.

Prior to this release, the RAG backend indexed only the video annotation corpus. Students whose questions were grounded in written course material received no retrieval support. The aim of this pipeline is to achieve **zero-friction, real-time synchronisation** between the Moodle content management lifecycle and the ChromaDB vector store, so that course content becomes immediately queryable the moment a teacher saves it, without any manual ingestion step.

A secondary, deeper problem concerns the **vocabulary gap** between novice learners and the expert corpus. This problem and its solution are described at length in §3 below.

### 2. Moodle Event Observer and Content Hash Deduplication

#### 2.1 Observer Trigger Design

The ingestion pipeline is driven by Moodle's native event system. `db/events.php` registers four observer callbacks with `'internal' => false`:

| Event | Observer method | Semantics |
|-------|----------------|-----------|
| `\core\event\course_module_created` | `course_module_created` | Module added to a course |
| `\core\event\course_module_updated` | `course_module_updated` | Module content modified and saved |
| `\core\event\course_module_deleted` | `course_module_deleted` | Module removed from a course |
| `\core\event\course_deleted` | `course_deleted` | Entire course deleted |

The `'internal' => false` flag is the correct choice for an ingestion pipeline: Moodle defers `internal => false` observers until *after* the enclosing database transaction commits. This eliminates the race condition that would arise if content were read from the database before a partially-committed write had finished — a real possibility during large page edits.

Only three module types are indexed: `mod_page`, `mod_label`, and `mod_resource` (PDF/DOCX only). These are the types that carry extractable, searchable textual content. All observer methods are wrapped in `try/catch (\Throwable $e)` with `error_log` — they never re-throw. A backend failure during ingestion must not interrupt the teacher's save operation.

#### 2.2 Content Hash Deduplication (`craftpilot_cm_index`)

The `craftpilot_cm_index` table stores one row per indexed course module:

```
cmid         INT  UNIQUE  — Moodle course module ID
course_id    INT          — Moodle course ID
content_hash CHAR(32)     — MD5 of the extracted content
last_indexed INT          — Unix timestamp of last successful ingestion
```

On `course_module_updated` events, the observer extracts the current content, computes its MD5, and compares it against the stored hash. If the hashes match, the backend call is skipped entirely. This is necessary because Moodle fires the `updated` event whenever a teacher opens and saves a module form, regardless of whether content has changed — a common occurrence when adjusting display settings or completion criteria. Avoiding redundant embedding calls is important both for latency (embedding is not free) and for collection hygiene (re-indexing unchanged content would produce duplicate chunk IDs).

### 3. Retrieval Strategy: Corpus-Grounded Pseudo-Relevance Feedback

#### 3.1 The Vocabulary Gap in Vocational Domains

Any retrieval system that maps a student query to a nearest-neighbour search in embedding space will struggle when the query vocabulary and the corpus vocabulary are systematically disjoint. For generic web-scale domains, pre-trained embedding models have seen enough synonym pairs to produce reasonable semantic overlap. For niche vocational crafts, this assumption fails. Consider:

> **Student query (novice register)**: *"pourquoi mon verre tombe quand je souffle"*
> **Expert corpus (practitioner register)**: *"perte d'axialité de la paraison liée à une rotation insuffisante de la canne"*

These two descriptions refer to the same physical event. They share no content words. A standard dense retrieval system that computes cosine similarity between the query embedding and chunk embeddings will fail to retrieve the relevant chunk, because no amount of pre-training has established a close embedding-space relationship between *"verre tombe"* and *"paraison perd son axialité"*.

#### 3.2 Why HyDE Fails in This Setting

The system's original retrieval strategy was **Hypothetical Document Embeddings (HyDE)** (Gao et al., 2022). HyDE addresses the vocabulary gap by inverting the retrieval direction: the LLM generates a *synthetic expert document* that would plausibly answer the query, and the embedding of this synthetic document is used as the search vector in place of the raw query embedding.

HyDE is an elegant solution when the LLM has sufficient parametric knowledge of the domain to generate a plausible synthetic document in the correct technical register. For generic and well-represented domains (programming, cooking, history), this assumption holds. For niche vocational crafts, it does not. The Mixtral-8x22B model's knowledge of glassblowing paraison dynamics, nautical sealant rheology, or 18th-century Vendée glove pattern construction is sparse and potentially inaccurate. When the synthesised document uses incorrect or absent technical vocabulary — or invents plausible-sounding but corpus-absent terms — its embedding drifts into a region of the space that is systematically far from the actual expert corpus, and retrieval degrades to near-random performance.

#### 3.3 Corpus-Grounded Pseudo-Relevance Feedback

The replacement strategy is a dense adaptation of **Pseudo-Relevance Feedback (PRF)**, a classical information retrieval technique with roots in the Rocchio algorithm (1971) and later probabilistic formulations (Lavrenko & Croft, 2001). In traditional sparse PRF, the top-k retrieved documents are assumed to be relevant; their term statistics are used to construct an expanded query before a second retrieval pass.

The CraftPilot adaptation replaces term-frequency expansion with **LLM-mediated reformulation grounded in the corpus vocabulary**. This can be understood as a form of *dense PRF with neural query expansion*, analogous to the methods described in Yu et al. (2021) and Wang et al. (2023), but with a critical design constraint: the reformulation prompt explicitly instructs the model to use vocabulary *extracted from the retrieved documents*, not vocabulary from its parametric weights.

The key passage from the reformulation prompt (`services/rag_service.py:refine_query_prf`):

```
"Documents récupérés (utilise leur vocabulaire technique, ne l'invente pas) :
[Document 1 — page]
...snippet from first-pass retrieval...

Instructions :
- Réécris la requête originale en une seule phrase reformulée
- Utilise UNIQUEMENT les termes techniques que tu vois dans les documents ci-dessus
- N'invente aucun terme ; si le corpus ne contient pas la réponse, dis-le"
```

This constraint transforms the LLM from a *knowledge oracle* (the role it plays in HyDE) into a **vocabulary bridge**: it reads what the corpus says and rewrites the student's question in corpus-consistent language. The safety property is that expansion terms are always attested in the retrieved documents — they cannot be hallucinated. If the first-pass retrieval returns weakly relevant documents, the reformulated query will be only weakly improved; but it cannot be made actively harmful (as HyDE's synthetic document can be when the LLM generates plausible-sounding misinformation).

**Empirically observed example (production logs)**:

```
PRF: 'pourquoi mon verre tombe quand je souffle'
  → 'Pourquoi la paraison tombe-t-elle lors du soufflage en raison
     d'une perte d'axialité liée à une rotation insuffisante ou à
     une prise trop serrée de la canne ?'
```

The reformulated query contains four corpus-attested technical terms (*paraison*, *axialité*, *rotation*, *canne*) that are absent from the original query, and exactly matches the vocabulary of the relevant expert annotation chunk.

#### 3.4 LangGraph Pipeline Specification

The active conversation graph is a four-node sequential state machine compiled by `pipeline._build_conversation_graph()`:

```
retrieve_initial → refine_query_prf → retrieve_final_dual → generate
```

**Node 1: `retrieve_initial`** (`services/rag_service.py:714`)

First-pass MaxMarginal Relevance (MMR) search using the raw user query. MMR is preferred over pure cosine similarity because it penalises redundancy within the result set, ensuring that the top-k documents used for reformulation represent diverse aspects of the corpus rather than clustering around a single sub-topic. Both collections are queried:

- `moodle_assistant_collection` — video annotations (global, domain-agnostic)
- `course_{course_id}` — course content (per-course-isolated; queried only when `course_id` is present in the graph state)

Results from both collections are merged and deduplicated by `metadata.source`. Deduplication is implemented in `_merge_dedup` using a `set` of source keys — a necessary guard because the two collections may, in principle, contain overlapping content (e.g., if a teacher uploads a video transcript as a course page).

**Node 2: `refine_query_prf`** (`services/rag_service.py:753`)

Takes up to 3 documents from `state["context"]` as corpus evidence. Constructs the grounded reformulation prompt described in §3.3. Returns `state["refined_query"]`. Falls back to the original query if `context` is empty (graceful degradation: the pipeline continues to generation without improving retrieval, rather than failing).

**Node 3: `retrieve_final_dual`** (`services/rag_service.py:809`)

Second-pass MMR search using `state["refined_query"]` instead of the raw query. Identical dual-collection architecture as `retrieve_initial`. The result set replaces `state["context"]` for the generation step.

**Node 4: `generate`**

Standard RAG generation: the LLM receives the merged context from both collections and produces a pedagogically framed response in French. The prompt instructs the model to acknowledge uncertainty when the context is insufficient, preventing confident hallucination in the absence of relevant retrieved content.

#### 3.5 Architectural Properties

- **No hallucinated expansion**: vocabulary comes from the corpus, not LLM weights.
- **Graceful degradation**: empty collection → pass-through of original query → honest uncertainty response.
- **Dual-source fusion**: a student question about glassblowing technique can receive context drawn simultaneously from annotated expert video and from the instructor's written course pages. The generation step synthesises across modalities without needing to distinguish their provenance.
- **Shared embedding model**: `CourseRAGService` reuses the `HuggingFaceEmbeddings` instance from `RAGService` (`pipeline.py:43–48`). Both collections use the same 768-dimensional MPNET vector space, making cross-collection similarity scores directly comparable at merge time.
- **Legacy HyDE methods preserved**: `generate_hypothetical_document` and `retrieve_with_hyde` are retained in `rag_service.py` but removed from the active graph, enabling easy A/B comparison without code archaeology.

### 4. Semantic Chunking for Heterogeneous Course Content

#### 4.1 Why Fixed-Size Chunking is Insufficient

Standard fixed-size chunking (e.g., `RecursiveCharacterTextSplitter` with a uniform token window) is inappropriate for structured pedagogical content. Course pages and instructional documents are organised around headings that delineate conceptually distinct sub-topics. Splitting at arbitrary character boundaries severs the heading-paragraph relationship and discards hierarchical context. A chunk containing "The blowpipe has three standard lengths" is ambiguous without knowing it falls under "Tools > Blowpipe > Variants". Worse, fixed-size chunking may cut across heading boundaries and merge content from different sections, producing chunks whose embeddings represent an incoherent mixture of topics.

The semantic chunker in `services/course_rag_service.py` addresses these issues by:

1. Treating heading boundaries as hard flush points, so each chunk belongs unambiguously to one heading hierarchy node.
2. Prepending the full heading breadcrumb path to each chunk's text before embedding, so retrieval is hierarchy-aware.
3. Using a target token budget (~400 tokens) as a soft flush point *within* sections, for sections that are long enough to warrant splitting.
4. Enforcing a minimum chunk size (50 tokens) to prevent near-empty sections from generating uninformative vectors.

#### 4.2 Heading Breadcrumb Prepend

Every chunk emitted by the chunker has the form:

```
{heading_path}\n\n{body_text}
```

where `heading_path` is the `" > "`-delimited stack of ancestor headings, e.g.:

```
Outils et matériaux > La canne à souffler > Variantes par longueur

La canne courte (90 cm) est utilisée pour les pièces de petit format.
Elle offre une meilleure précision de rotation mais réduit le temps de
travail disponible avant refroidissement.
```

This encoding means that a query for "blowpipe length variants" retrieves this chunk with higher similarity than if the same body text appeared in isolation, because the breadcrumb brings the section's semantic context into the embedding space. It also means that two identically-worded paragraphs in different sections of the same document will receive different embeddings — the correct behaviour for disambiguation.

#### 4.3 HTML Chunking (BeautifulSoup DOM Walker)

Source: Moodle Page and Label modules (store content as Moodle-formatted HTML).

```
State: heading_stack: List[str], buffer: List[str], chunks: List[Document]

for element in soup.descendants:
    if element.name in {h1..h6}:
        flush(force=True)
        level = int(tag[1])
        heading_stack[:] = heading_stack[:level-1]   # trim to parent
        heading_stack.append(element.get_text())
    elif element.name in {p, li, td, th, dd, dt, blockquote}:
        buffer.append(element.get_text())
        if approx_tokens(" ".join(buffer)) >= 400:
            flush(force=True)
flush(force=True)
```

The `heading_stack[:] = heading_stack[:level-1]` operation correctly handles non-linear heading sequences (e.g., H2 followed by H2 at the same level, or H4 followed by H2 after a sub-section closes). Tables, definition lists, and blockquotes are treated as body text, preserving their textual content without structural transformation.

#### 4.4 PDF Chunking (PyMuPDF Font-Size Heuristic)

Source: Moodle Resource modules containing PDF files.

PDFs lack semantic markup. Heading detection relies on a typographic heuristic derived from the document's own font-size distribution:

```
all_sizes = [span["size"] for all spans in all pages]
body_size = median(all_sizes)
heading_threshold = body_size * 1.15   # 15% above median

for each page → block → line:
    avg_size = mean(span sizes in line)
    is_bold  = any(span["flags"] & 0b10000)
    if avg_size >= heading_threshold OR (is_bold AND len(line) < 80):
        flush → update heading_stack
    else:
        buffer.append(line_text)
```

The 1.15× threshold is calibrated for typical instructional PDF documents (lecture slides, technical guides, assessment forms) where section titles are rendered 15–40% larger than body text. The secondary condition `is_bold AND len(line) < 80` catches headings that use bold weight at body size, a common authoring convention in documents produced with word processors.

Heading level is approximated from the font-size ratio, bounded to the range [1, 3], since PDFs rarely use more than three heading levels in practice.

#### 4.5 DOCX Chunking (python-docx Paragraph Styles)

Source: Moodle Resource modules containing DOCX files.

DOCX documents carry explicit semantic markup via paragraph styles. The chunker maps `Heading N` paragraph styles directly to heading levels — no heuristic is required:

```
for para in doc.paragraphs:
    style_name = para.style.name
    if style_name.startswith("Heading"):
        level = int(re.search(r"(\d+)", style_name).group(1))
        flush → update heading_stack
    else:
        buffer.append(para.text)

for table in doc.tables:
    for row in table.rows:
        buffer.append("Row: " + " | ".join(cell.text for cell in row.cells))
```

Tables are linearised to pipe-delimited rows. This preserves tabular content (comparison tables of tool properties, assessment rubrics, material specifications) in a form that the embedding model can process without requiring vision capabilities. The `"Row: "` prefix is added to each row as a light semantic marker that aids query matching for table-specific questions.

#### 4.6 Chunk Metadata Schema

Every chunk carries the following metadata fields in its ChromaDB document:

| Field | Value |
|-------|-------|
| `type` | `"course_content"` |
| `course_id` | Moodle course ID (string) |
| `module_id` | Moodle course module ID (string) |
| `module_type` | `"page"`, `"label"`, or `"resource"` |
| `module_name` | Human-readable module name |
| `section_name` | Moodle course section name |
| `chunk_index` | 0-based index within the module |
| `heading_path` | Full breadcrumb string |
| `source` | Globally unique: `course_{id}_module_{id}_chunk_{n}` |
| `image_pages` | (PDF only) comma-separated page numbers with images |

The `source` field is the deduplication key used by `_merge_dedup`. It is constructed to be globally unique across all courses and modules, permitting safe merging of results from multiple collections.

### 5. Per-Course ChromaDB Collection Isolation

Each Moodle course's content is indexed into a dedicated ChromaDB collection named `course_{course_id}`. All retrievals from course content are constrained to the single collection corresponding to the active course, enforcing a hard isolation boundary at the vector-store level.

**Design rationale**: In a multi-course deployment, students work within a specific course and expect answers grounded in that course's material. Cross-course contamination — where a query from a glassblowing course retrieves chunks from a carpentry course — is both educationally incorrect and potentially confusing. Per-course collections make cross-contamination structurally impossible without requiring runtime metadata filtering, which would add query complexity and is less reliable (metadata filters are a soft constraint; collection boundaries are a hard one).

**Collection lifecycle**:
- Collections are created lazily on first ingest, via `CourseRAGService._get_collection()`.
- Collection handles are cached in `_collections: Dict[str, Chroma]` to avoid repeated client initialisation.
- Course deletion fires `delete_collection`, which calls `store._client.delete_collection(name)` directly on the underlying chromadb client (the LangChain wrapper does not expose a delete-collection method).

**Coexistence with the annotation collection**: ChromaDB stores multiple collections in the same `persist_directory` (a single SQLite database at `./chroma_langchain_db/chroma.sqlite3`). Collection names `course_{int}` cannot collide with `moodle_assistant_collection`. Both collection types use the same embedding function instance, making their vector spaces directly comparable.

### 6. Frontend Integration (`course_id` Propagation)

The `course_id` is propagated from the Moodle PHP context to the Python backend via a three-link chain:

1. **`view.php`**: passes `$course->id` as the second positional argument to the AMD `init()` call. The JS module already accepted this as `moduleCourseId` and stored it in `state.courseId`.

2. **`amd/src/chat_interface.js`**: `sendMessage()` includes `course_id` in the POST body when `state.courseId` is set:
   ```javascript
   if (state.courseId) {
       payload.course_id = String(state.courseId);
   }
   ```

3. **`chat_proxy.php`**: forwards the entire validated POST body verbatim to the backend. The `course_id` field passes through without any proxy-side modification.

4. **`api/routes.py`**: `ChatRequest.course_id: Optional[str]` is passed to `pipeline.generate_response(course_id=request.course_id)`, which seeds the initial LangGraph state dict.

### 7. Operational Notes

**Moodle DB upgrade** (applied at version 2026022700):
```bash
php /var/www/html/admin/cli/upgrade.php --non-interactive
php /var/www/html/admin/cli/purge_caches.php
```

**Test ingestion directly**:
```bash
curl -X POST http://127.0.0.1:8000/api/ingest-course-module \
  -H "Content-Type: application/json" \
  -d '{"course_id":"99","module_id":"1","module_type":"page",
       "module_name":"Test","section_name":"Week 1",
       "content_html":"<h2>Outils</h2><p>La canne est centrale en verrerie soufflée.</p>"}'

curl http://127.0.0.1:8000/api/course-status/99
```

**Observe the PRF reformulation in logs**:
```bash
tail -f /tmp/craftpilot_backend.log | grep "PRF:"
```

**Python dependencies added**:
```
pymupdf         (PDF extraction via PyMuPDF / fitz)
python-docx     (DOCX paragraph and table iteration)
beautifulsoup4  (HTML DOM parsing for Page/Label content)
```
