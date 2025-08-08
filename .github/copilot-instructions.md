# AI Assistant Block for Moodle - Copilot Instructions

## Architecture Overview

This is a **Moodle block plugin** (`block_aiassistant`) that provides AI chat functionality using the Fireworks AI API. The plugin follows Moodle's standard plugin architecture with secure API key management and modern ES6/AMD JavaScript.

### Key Components

- **Block Class** (`block_aiassistant.php`): Main Moodle block that renders chat interface HTML and loads AMD modules
- **External API** (`classes/external/get_user_credentials.php`): AJAX endpoint for secure API key retrieval
- **Credential Service** (`classes/credential_service.php`): Manages Fireworks AI API key generation per user
- **Frontend** (`amd/src/chat_interface.js`): ES6 module for chat UI, transpiled to AMD via Babel/Grunt

## Development Workflow

### JavaScript Development
- **Source**: Edit `amd/src/chat_interface.js` in ES6 syntax
- **Build**: Run `npx grunt dev` to start file watching - auto-transpiles ES6→AMD and minifies
- **Output**: Transpiled AMD modules go to `amd/build/` (what Moodle actually loads)
- **Loading**: Moodle loads via `$PAGE->requires->js_call_amd('block_aiassistant/chat_interface', 'init')`

### Key Patterns

**AJAX Communication**: Frontend calls `Ajax.call([{methodname: 'block_aiassistant_get_user_credentials', ...}])` to get API keys, then directly calls Fireworks AI API

**Error Handling**: External API methods return structured responses with `success`, `message`, and data fields. Always check `credentials.success` before proceeding.

**Database**: Single table `block_aiassistant_keys` stores per-user API keys with `userid`, `fireworks_api_key`, `is_active` fields

**Naming Convention**: Use snake_case for all identifiers (functions, variables, config keys) - e.g., `fireworks_account_id`, `get_user_credentials`

## Configuration Requirements

Plugin requires three admin settings in Site Administration:
- `fireworks_account_id`: Fireworks account identifier  
- `fireworks_service_account_id`: Service account for API key generation
- `fireworks_api_token`: Admin token for creating user API keys

## Security Model

- Each Moodle user gets individual Fireworks API key (stored in `block_aiassistant_keys`)
- Keys generated on-demand via `credential_service->generate_user_api_key()`
- Frontend makes direct calls to Fireworks API (not proxied through Moodle)
- All AJAX endpoints require `loginrequired => true`

## Future Architecture Notes

- Chat interface will evolve into full RAG system with retrieved content display
- Current implementation focuses on basic chat functionality as foundation

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
```

## Common Tasks

**Add new AJAX endpoint**: Add to `db/services.php` + create `classes/external/` class extending `external_api`

**Debug JavaScript**: Check browser console for "AI Chat: Initializing..." and element detection logs

**Update dependencies**: Modify `package.json`, run `npm install`, restart `npx grunt dev`
