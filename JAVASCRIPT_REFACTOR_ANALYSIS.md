# JavaScript Refactoring Analysis - chat_interface.js

## Current State: 941 lines with block-specific UI management
## Target State: ~300-400 lines focused on per-instance chat functionality

---

## Function-by-Function Analysis

### 1. **init(cmId, courseId, instanceId)**
- **Purpose**: Entry point that receives Moodle context and initializes chat interface
- **Inputs**: cmId (course module ID), courseId, instanceId (aiassistant activity instance ID)
- **Outputs**: None (sets up entire chat UI)
- **Dependencies**: All other functions
- **Current Behavior**: Saves IDs globally, then calls initializeChat()
- **Recommendation**: **KEEP & EDIT** - Needs instanceId parameter, simplify initialization

---

### 2. **initializeChat()**
- **Purpose**: Main initialization function that sets up DOM references and event listeners
- **Inputs**: None (uses global variables)
- **Outputs**: None (mutates DOM)
- **Dependencies**: All DOM element queries, event setup functions
- **Current Behavior**: 
  - Queries 15+ DOM elements (conversationsPanel, providerSelect, motto, etc.)
  - Sets up sendButton, newConversationBtn click handlers
  - Calls loadAIConfiguration(), loadExistingConversations()
- **Recommendation**: **EDIT** - Remove block-specific elements (conversationsPanel, conversationsToggle, newConversationBtn, providerSelect, motto), simplify to just sendButton + chatInput

---

### 3. **showDocuments(documents)**
- **Purpose**: Displays retrieved documents in sidepanel during RAG queries
- **Inputs**: Array of document objects with {id, title, content, metadata}
- **Outputs**: None (mutates documentsContainer DOM)
- **Dependencies**: documentsContainer element
- **Current Behavior**: Creates HTML list of retrieved documents with titles and snippets
- **Recommendation**: **KEEP** - Essential for RAG functionality

---

### 4. **formatTime(timestamp)**
- **Purpose**: Converts timestamp to HH:MM:SS format for display
- **Inputs**: Timestamp number (seconds)
- **Outputs**: String like "01:23:45"
- **Dependencies**: None
- **Current Behavior**: String padding for hours/minutes/seconds
- **Recommendation**: **KEEP** - Utility function for video timestamps

---

### 5. **clearVideoPlayer()**
- **Purpose**: Resets video player state
- **Inputs**: None
- **Outputs**: None (mutates videoPlayerContainer DOM)
- **Dependencies**: videoPlayerContainer element
- **Current Behavior**: Hides video player, clears innerHTML
- **Recommendation**: **KEEP** - Video annotation feature

---

### 6. **displayVideoSegment(videoId, startTime, endTime)**
- **Purpose**: Displays video iframe with time range
- **Inputs**: videoId (string), startTime (number), endTime (number)
- **Outputs**: None (mutates videoPlayerContainer DOM)
- **Dependencies**: videoPlayerContainer element, formatTime()
- **Current Behavior**: Creates YouTube iframe with start/end parameters, shows timestamp info
- **Recommendation**: **KEEP** - Video annotation feature

---

### 7. **showResultsArea()**
- **Purpose**: Makes sidepanel visible
- **Inputs**: None
- **Outputs**: None (mutates resultsArea classList)
- **Dependencies**: resultsArea element
- **Current Behavior**: Removes 'd-none' class
- **Recommendation**: **KEEP** - RAG sidepanel control

---

### 8. **hideResultsArea()**
- **Purpose**: Hides sidepanel
- **Inputs**: None
- **Outputs**: None (mutates resultsArea classList)
- **Dependencies**: resultsArea element
- **Current Behavior**: Adds 'd-none' class
- **Recommendation**: **KEEP** - RAG sidepanel control

---

### 9. **loadExistingConversations()**
- **Purpose**: Fetches user's conversations from database via AJAX
- **Inputs**: None (uses global cmId, courseId)
- **Outputs**: Promise resolving to conversation array
- **Dependencies**: core/ajax, populateConversationsList()
- **Current Behavior**: Calls block_aiassistant_manage_conversations with 'list' action, filters by coursemodule_id and is_active=1
- **Recommendation**: **DELETE** - Not needed for per-instance chat (auto-create single conversation per user per instance)

---

### 10. **populateConversationsList(conversations)**
- **Purpose**: Renders conversation list in left panel
- **Inputs**: Array of conversation objects
- **Outputs**: None (mutates conversationsList DOM)
- **Dependencies**: conversationsList element, createConversationElement()
- **Current Behavior**: Clears list, creates conversation items, marks first as active, loads messages
- **Recommendation**: **DELETE** - No conversation list UI needed

---

### 11. **createConversationElement(conversation)**
- **Purpose**: Creates DOM element for single conversation item
- **Inputs**: Conversation object {id, title, created_time}
- **Outputs**: HTMLElement (li.ai-conversation-item)
- **Dependencies**: setupConversationItemListener()
- **Current Behavior**: Creates list item with title, timestamp, delete button
- **Recommendation**: **DELETE** - No conversation list UI needed

---

### 12. **deleteConversation(conversationId)**
- **Purpose**: Soft-deletes conversation (sets is_active=0)
- **Inputs**: conversationId (string)
- **Outputs**: Promise
- **Dependencies**: core/ajax, loadExistingConversations()
- **Current Behavior**: Confirms with user, calls manage_conversations 'delete' action, reloads conversation list
- **Recommendation**: **DELETE** - No manual conversation deletion (per-instance auto-managed)

---

### 13. **loadAIConfiguration()**
- **Purpose**: Fetches available AI providers and models
- **Inputs**: None
- **Outputs**: Promise resolving to config object
- **Dependencies**: core/ajax, setupProviderUI(), showConfigurationError()
- **Current Behavior**: Calls block_aiassistant_get_ai_config, checks providers (fireworks/claude), populates dropdown
- **Recommendation**: **DELETE** - Hardcode Fireworks provider, no config fetch needed

---

### 14. **showConfigurationError(message)**
- **Purpose**: Displays error when no providers configured
- **Inputs**: Error message string
- **Outputs**: None (mutates messagesContainer DOM)
- **Dependencies**: messagesContainer element
- **Current Behavior**: Shows error message with red styling, disables chat input
- **Recommendation**: **DELETE** - Assume Fireworks always configured, handle errors differently

---

### 15. **setupProviderUI(config)**
- **Purpose**: Populates provider dropdown based on config
- **Inputs**: Config object with {fireworks: {...}, claude: {...}}
- **Outputs**: None (mutates providerSelect DOM)
- **Dependencies**: providerSelect element
- **Current Behavior**: Creates dropdown options for available providers, sets default
- **Recommendation**: **DELETE** - No provider dropdown needed (Fireworks hardcoded)

---

### 16. **sendMessage()**
- **Purpose**: Main message sending handler (button click or Enter key)
- **Inputs**: None (reads from chatInput element)
- **Outputs**: None (orchestrates message flow)
- **Dependencies**: chatInput, sendButton, sendMessageWithConversation()
- **Current Behavior**: Validates input, disables UI, calls sendMessageWithConversation()
- **Recommendation**: **KEEP & EDIT** - Remove provider/conversation checks, always send to current conversation

---

### 17. **sendMessageWithConversation(userMessage)**
- **Purpose**: Adds user message to DOM and saves to database before sending to AI
- **Inputs**: userMessage (string)
- **Outputs**: None (orchestrates message display and AI request)
- **Dependencies**: messagesContainer, saveMessageToDatabase(), sendFireworksChatMessage()
- **Current Behavior**: Displays user message, saves to DB, calls sendFireworksChatMessage() based on provider
- **Recommendation**: **KEEP & EDIT** - Always use Fireworks, simplify conversation handling

---

### 18. **sendFireworksChatMessage(userMessage)**
- **Purpose**: Sends message to RAG backend and streams AI response
- **Inputs**: userMessage (string)
- **Outputs**: Promise resolving when complete
- **Dependencies**: core/ajax (for credentials), fetch() for SSE, showDocuments(), displayVideoSegment(), marked.js
- **Current Behavior**:
  - Fetches Fireworks API key via get_user_credentials
  - POSTs to http://localhost:8000/api/chat with SSE streaming
  - Handles data stream: text chunks, documents, video segments
  - Saves AI response to database when complete
  - Renders markdown with marked.js
- **Recommendation**: **KEEP & EDIT** - Update AJAX calls to mod_aiassistant_*, pass instanceId to backend for per-instance conversation lookup

---

### 19. **generateUUID()**
- **Purpose**: Creates unique conversation ID
- **Inputs**: None
- **Outputs**: UUID string (v4 format)
- **Dependencies**: None
- **Current Behavior**: Uses crypto.randomUUID() with fallback to timestamp-based generation
- **Recommendation**: **KEEP** - Needed for conversation creation

---

### 20. **createNewConversation()**
- **Purpose**: Creates new conversation with title prompt
- **Inputs**: None (prompts user)
- **Outputs**: None (creates conversation, updates UI)
- **Dependencies**: generateUUID(), createConversationInMoodle(), conversationsList element
- **Current Behavior**: Prompts for title, generates UUID, creates in DB, adds to list, clears messages
- **Recommendation**: **DELETE** - Auto-create conversation per instance, no manual creation

---

### 21. **saveMessageToDatabase(conversationId, messageRole, messageContent)**
- **Purpose**: Saves message to database via AJAX
- **Inputs**: conversationId (string), messageRole ('user'|'assistant'), messageContent (string)
- **Outputs**: Promise resolving to message object
- **Dependencies**: core/ajax
- **Current Behavior**: Calls block_aiassistant_manage_messages 'save' action with conversation_id, role, content, sequence_number (auto-increment)
- **Recommendation**: **KEEP & EDIT** - Update to mod_aiassistant_manage_messages, handle per-instance conversations

---

### 22. **loadMessagesFromDatabase(conversationId)**
- **Purpose**: Loads conversation messages from database
- **Inputs**: conversationId (string)
- **Outputs**: Promise resolving to message array
- **Dependencies**: core/ajax
- **Current Behavior**: Calls block_aiassistant_manage_messages 'load' action, returns ordered messages
- **Recommendation**: **KEEP & EDIT** - Update to mod_aiassistant_manage_messages, use for initial page load

---

### 23. **createConversationInMoodle(conversationId, title)**
- **Purpose**: Creates new conversation record in database
- **Inputs**: conversationId (UUID string), title (string)
- **Outputs**: Promise resolving to conversation object
- **Dependencies**: core/ajax
- **Current Behavior**: Calls block_aiassistant_manage_conversations 'create' action with id, title, coursemodule_id, metadata (provider info)
- **Recommendation**: **EDIT** - Update to mod_aiassistant_manage_conversations, auto-create with instanceId on first message, default title to activity name

---

### 24. **displayMessages(messages)**
- **Purpose**: Renders array of messages in chat container
- **Inputs**: Array of message objects {role, content}
- **Outputs**: None (mutates messagesContainer DOM)
- **Dependencies**: messagesContainer element, marked.js
- **Current Behavior**: Creates divs for each message, distinguishes user vs assistant styling, parses markdown for assistant messages
- **Recommendation**: **KEEP** - Essential for displaying chat history

---

### 25. **setupConversationItemListener(item)**
- **Purpose**: Attaches click handler to conversation list item
- **Inputs**: HTMLElement (conversation list item)
- **Outputs**: None (attaches event listener)
- **Dependencies**: loadMessagesFromDatabase(), displayMessages(), clearVideoPlayer()
- **Current Behavior**: Handles conversation selection, loads messages, updates active state
- **Recommendation**: **DELETE** - No conversation list UI

---

### 26. **setupConversationPanel()**
- **Purpose**: Initializes event listeners for all conversation items
- **Inputs**: None
- **Outputs**: None (sets up listeners)
- **Dependencies**: setupConversationItemListener()
- **Current Behavior**: Queries all conversation items, attaches listeners
- **Recommendation**: **DELETE** - No conversation panel

---

### 27. **autoResizeTextarea()**
- **Purpose**: Dynamically resizes chat input as user types
- **Inputs**: None (reads chatInput element)
- **Outputs**: None (mutates chatInput.style.height)
- **Dependencies**: chatInput element
- **Current Behavior**: Calculates height based on scrollHeight, caps at 4 lines
- **Recommendation**: **KEEP** - UX enhancement for multi-line messages

---

## Summary of Recommendations

### Functions to KEEP (11):
1. ✅ **init()** - Entry point with instanceId parameter
2. ✅ **showDocuments()** - RAG document display
3. ✅ **formatTime()** - Video timestamp utility
4. ✅ **clearVideoPlayer()** - Video reset
5. ✅ **displayVideoSegment()** - Video iframe display
6. ✅ **showResultsArea()** - Sidepanel visibility
7. ✅ **hideResultsArea()** - Sidepanel visibility
8. ✅ **sendMessage()** - Core message handler
9. ✅ **sendMessageWithConversation()** - Message display orchestrator
10. ✅ **sendFireworksChatMessage()** - RAG backend integration with SSE streaming
11. ✅ **generateUUID()** - Conversation ID generation
12. ✅ **saveMessageToDatabase()** - Message persistence
13. ✅ **loadMessagesFromDatabase()** - Message retrieval
14. ✅ **displayMessages()** - Message rendering
15. ✅ **autoResizeTextarea()** - Input UX

### Functions to EDIT (4):
- **initializeChat()** - Remove block-specific DOM queries (conversationsPanel, providerSelect, motto, newConversationBtn), keep messagesContainer, chatInput, sendButton, documentsContainer, videoPlayerContainer
- **sendMessage()** - Remove provider checks, always use Fireworks, auto-create conversation if needed
- **sendMessageWithConversation()** - Simplify conversation handling, ensure conversation exists for instance
- **sendFireworksChatMessage()** - Update AJAX calls to mod_aiassistant namespace, pass instanceId for conversation lookup
- **createConversationInMoodle()** - Auto-create on first message with instanceId FK, default title to activity name

### Functions to DELETE (12):
1. ❌ **loadExistingConversations()** - No conversation list
2. ❌ **populateConversationsList()** - No conversation list UI
3. ❌ **createConversationElement()** - No conversation list items
4. ❌ **deleteConversation()** - No manual deletion
5. ❌ **loadAIConfiguration()** - Hardcode Fireworks
6. ❌ **showConfigurationError()** - Different error handling
7. ❌ **setupProviderUI()** - No provider dropdown
8. ❌ **createNewConversation()** - Auto-create on first message
9. ❌ **setupConversationItemListener()** - No conversation list
10. ❌ **setupConversationPanel()** - No conversation panel
11. ❌ All conversation panel toggle logic
12. ❌ New conversation button event handler

---

## Refactored Architecture

### New Flow:
1. **init(cmId, courseId, instanceId)** - Entry point, saves context
2. **initializeChat()** - Sets up minimal DOM (sendButton, chatInput, messagesContainer, sidepanel), auto-creates/loads conversation
3. **Auto-create conversation** - On page load, check if user has conversation for this instance, if not create one with UUID and title = activity name
4. **Load existing messages** - Display conversation history from database
5. **sendMessage()** - Validates input, displays user message, calls RAG backend
6. **sendFireworksChatMessage()** - Fetches credentials, streams SSE response, handles documents/videos, saves to database
7. **displayMessages()** - Renders chat history with markdown support

### Removed Complexity:
- ❌ Conversation list panel (left sidebar)
- ❌ Provider dropdown (hardcoded Fireworks)
- ❌ New conversation button
- ❌ Delete conversation functionality
- ❌ Conversation selection logic
- ❌ Provider configuration loading
- ❌ Motto display

### Estimated Final Size: 350-400 lines (from 941)
