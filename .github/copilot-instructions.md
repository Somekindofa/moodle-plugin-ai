# AI Assistant Block for Moodle - Copilot Instructions

## Architecture Overview

This is a **Moodle block plugin** (`block_aiassistant`) providing AI chat functionality with **dual provider support** (Fireworks AI and Claude) and **RAG capabilities**. The plugin follows Moodle's standard plugin architecture with secure API key management and modern ES6/AMD JavaScript.

### Key Components

- **Block Class** (`block_aiassistant.php`): Main Moodle block that renders chat interface HTML with conversation panel and sidepanel for retrieved documents
- **External APIs** (`classes/external/`): Three AJAX endpoints for credentials, config, and Claude proxy
- **Credential Service** (`classes/credential_service.php`): Unified service managing both Fireworks per-user keys and shared Claude keys
- **Frontend** (`amd/src/chat_interface.js`): ES6 module handling provider selection, streaming responses, and RAG document display
- **RAG Backend** (`test/app.py`): Standalone Python service using LangGraph/LangChain for document retrieval

## Development Workflow

### JavaScript Development
- **Source**: Edit `amd/src/chat_interface.js` in ES6 syntax
- **Build**: Run `npx grunt dev` to start file watching - auto-transpiles ES6→AMD and minifies  
- **Output**: Transpiled AMD modules go to `amd/build/` (what Moodle actually loads)
- **Loading**: Moodle loads via `$PAGE->requires->js_call_amd('block_aiassistant/chat_interface', 'init')`

### RAG Development
- **Backend**: Python FastAPI service at `http://127.0.0.1:8000/api/chat` with SSE streaming
- **Testing**: Run `test/app.py` directly for Gradio interface with file upload and database management
- **Integration**: Frontend calls external Python service, displays retrieved docs in sidepanel

## Provider Architecture

**Dual Provider Model**: Plugin supports both Fireworks (per-user keys) and Claude (shared admin key)

**Configuration Detection**: `get_ai_config` endpoint checks which providers are configured and returns available models

**Frontend Adaptation**: UI dynamically shows/hides providers based on backend configuration

**API Approaches**:
- Fireworks: Direct frontend calls to Fireworks API (requires per-user key generation via `firectl`)
- Claude: Server-side proxy via `send_claude_message` endpoint (uses shared admin key)

## Key Patterns

**AJAX Communication**: Frontend calls multiple endpoints - `get_ai_config` for provider detection, `get_user_credentials` for Fireworks keys, `send_claude_message` for Claude proxy

**Error Handling**: All external API methods return structured responses with `success`, `message`, and optional data fields. Always check response.success before proceeding.

**Database Schema**: Single table `block_aiassistant_keys` with fields:
- `userid`, `fireworks_api_key`, `fireworks_key_id` (per-user Fireworks credentials)  
- `claude_api_key` (shared admin key, tracked per user for usage)
- `display_name`, `created_time`, `last_used`, `is_active`

**Streaming Responses**: Frontend handles Server-Sent Events from RAG backend, displays documents in sidepanel and streams AI responses with markdown rendering

**Naming Convention**: Use snake_case for all identifiers (functions, variables, config keys) - e.g., `claude_default_model`, `send_claude_message`

## Configuration Requirements

**Fireworks Setup** (Optional): `fireworks_api_key` admin setting OR legacy per-user approach requiring:
- `fireworks_account_id`, `fireworks_service_account_id` with `firectl` binary at `/usr/local/bin/firectl`

**Claude Setup** (Optional): `claude_api_key` admin setting for shared Claude access

**RAG Setup**: Python backend service must run separately for document retrieval functionality

## Security Model

**Fireworks**: Either shared admin key OR individual per-user keys generated via `firectl` command-line tool
**Claude**: Shared admin API key, usage tracked per user in database  
**API Endpoints**: All require `loginrequired => true` and proper context validation
**External Service**: RAG backend assumed to be on localhost:8000 (no authentication currently)

## UI Architecture

**Three-Panel Layout**:
- **Left**: Conversation history panel (static mockup, no backend integration yet)
- **Center**: Chat interface with provider selection dropdown and message history
- **Right**: Retrieved documents sidepanel (populated by RAG backend during chat)

**Provider Selection**: Dropdown dynamically populated based on `get_ai_config` response, shows "Not configured" for unavailable providers

**Markdown Rendering**: Uses `marked.js` library loaded via CDN to render AI responses with proper formatting

## File Structure Conventions

```
block_aiassistant.php          # Main block class
classes/external/              # AJAX API endpoints
classes/credential_service.php # API key management
db/install.xml                # Database schema
db/services.php               # AJAX service definitions
amd/src/                      # ES6 source files
amd/build/                    # Transpiled AMD outputs (auto-generated)
lang/en/                      # Language strings
test/app.py                   # Standalone RAG backend service
```

## Common Tasks

**Add new AJAX endpoint**: Add to `db/services.php` + create `classes/external/` class extending `external_api`

**Debug JavaScript**: Check browser console for "AI Chat: Initializing..." and element detection logs

**Update dependencies**: Modify `package.json`, run `npm install`, restart `npx grunt dev`

**Test RAG functionality**: Run `python test/app.py` to start Gradio interface with file upload and database management

**Add new AI provider**: Extend `get_ai_config` endpoint, add provider detection logic, update frontend provider selection
