// Standard license block omitted for brevity
/**
 * @module     block_aiassistant/chat_interface
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Ajax from 'core/ajax';

/**
 * Initialize the AI chat interface
 */
export const init = () => {
    // Function to initialize the chat interface
    const initializeChat = () => {
        console.log('AI Chat: Initializing...');

        const sendButton = document.getElementById("ai-chat-send");
        const chatInput = document.getElementById("ai-chat-input");
        const messagesContainer = document.getElementById("ai-chat-messages");
        const providerSelect = document.getElementById("ai-provider-select");
        const newConversationBtn = document.getElementById("ai-new-conversation-btn");

        if (!sendButton || !chatInput || !messagesContainer || !providerSelect || !newConversationBtn) {
            console.error('AI Chat: Required elements not found');
            return;
        }

        // Global configuration storage
        let aiConfig = null;
        let currentProvider = 'fireworks';
        let currentConversationThreadId = null;

        // Load AI configuration on startup
        loadAIConfiguration();
        loadExistingConversations();

        /**
         * Displays a list of document paths in the sidepanel.
         * Populates the sidepanel with the provided document paths.
         * If no documents are provided, displays a "No documents retrieved" message.
         * 
         * @param {string[]} documentPaths - Array of document file paths to display in the sidepanel
         */
        function showDocumentSidepanel(documentPaths) {
            const content = document.getElementById('ai-sidepanel-content');

            if (documentPaths && documentPaths.length > 0) {
                const listHTML = `
                    <ul class="ai-document-list">
                        ${documentPaths.map(path => `<li>${path}</li>`).join('')}
                    </ul>
                `;
                content.innerHTML = listHTML;
            } else {
                content.innerHTML = '<p>No documents retrieved.</p>';
            }
        }

        /**
         * Load existing conversations from Moodle database
         */
        function loadExistingConversations() {
            Ajax.call([{
                methodname: 'block_aiassistant_manage_conversations',
                args: {
                    action: 'list'
                },
                done: function (result) {
                    if (result && result.success && result.conversations) {
                        console.log('Existing conversations loaded:', result.conversations);
                        populateConversationsList(result.conversations);
                    } else {
                        console.log('No existing conversations found or failed to load:', result ? result.message : 'No response received');
                    }
                },
                fail: function (error) {
                    console.error('Failed to load existing conversations:', error);
                }
            }]);
        }

        /**
         * Populate the conversations list with existing conversations
         */
        function populateConversationsList(conversations) {
            const conversationList = document.getElementById('ai-conversation-list');

            // Clear existing items
            conversationList.innerHTML = '';

            // Add each conversation to the list
            conversations.forEach(conversation => {
                const conversationItem = createConversationElement(conversation.conversation_id, conversation.title);
                conversationList.appendChild(conversationItem);

                // Add click listener to the item
                setupConversationItemListener(conversationItem);
            });

            console.log(`Populated ${conversations.length} conversations in the list`);
        }

        /**
         * Create a conversation element with title and delete button
         */
        function createConversationElement(conversationId, title) {
            const conversationItem = document.createElement('div');
            conversationItem.className = 'ai-conversation-item';
            conversationItem.setAttribute('data-conversation-id', conversationId);

            const titleSpan = document.createElement('span');
            titleSpan.className = 'ai-conversation-title';
            titleSpan.textContent = title;

            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'ai-conversation-delete-btn';
            deleteBtn.textContent = '×';
            deleteBtn.title = 'Delete conversation';
            deleteBtn.type = 'button';

            // Add click handler for delete button
            deleteBtn.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent conversation selection
                deleteConversation(conversationId, conversationItem);
            });

            conversationItem.appendChild(titleSpan);
            conversationItem.appendChild(deleteBtn);

            return conversationItem;
        }

        /**
         * Delete a conversation with confirmation
         */
        function deleteConversation(conversationId, conversationItem) {
            const conversationTitle = conversationItem.querySelector('.ai-conversation-title').textContent;
            
            // Show confirmation dialog
            if (!confirm(`Are you sure you want to delete "${conversationTitle}"? This will permanently delete the conversation and all its messages.`)) {
                return;
            }

            // Call backend to delete conversation
            Ajax.call([{
                methodname: 'block_aiassistant_manage_conversations',
                args: {
                    action: 'delete',
                    conversation_id: conversationId
                },
                done: function(result) {
                    if (result.success) {
                        console.log('Conversation deleted successfully:', conversationId);
                        
                        // Check if deleted conversation was the active one
                        const wasActive = conversationItem.classList.contains('active');
                        
                        // Remove conversation from UI
                        conversationItem.remove();
                        
                        // If this was the active conversation, create or select another one
                        if (wasActive) {
                            currentConversationThreadId = null;
                            
                            // Check if there are other conversations
                            const remainingConversations = document.querySelectorAll('.ai-conversation-item');
                            if (remainingConversations.length > 0) {
                                // Select the first remaining conversation
                                remainingConversations[0].click();
                            } else {
                                // No conversations left, clear the chat
                                messagesContainer.innerHTML = '<div class="ai-message"><strong>AI Assistant:</strong> Hello! How can I help you today?</div>';
                            }
                        }
                    } else {
                        console.error('Failed to delete conversation:', result.message);
                        alert('Failed to delete conversation: ' + result.message);
                    }
                },
                fail: function(error) {
                    console.error('Failed to delete conversation:', error);
                    alert('Failed to delete conversation. Please try again.');
                }
            }]);
        }

        /**
         * Load AI configuration from backend
         */
        function loadAIConfiguration() {
            Ajax.call([{
                methodname: 'block_aiassistant_get_ai_config',
                args: {},
                done: function (config) {
                    if (config && config.success) {
                        aiConfig = config;
                        console.log('AI Config loaded successfully.');
                        setupProviderUI();
                    } else {
                        console.error('Failed to load AI configuration:', config ? config.message : 'No response received');
                        aiConfig = {
                            success: false,
                            fireworks_available: false,
                        };
                        showConfigurationError('Failed to load AI configuration. Please check plugin settings.');
                    }
                },
                fail: function (error) {
                    console.error('Error details:', {
                        name: error.name,
                        message: error.message,
                        stack: error.stack
                    });
                    aiConfig = {
                        success: false,
                        fireworks_available: false,
                    };
                    showConfigurationError('Could not connect to AI configuration service.');
                }
            }]);
        }

        /**
         * Show configuration error in chat
         */
        function showConfigurationError(message) {
            const errorDiv = document.createElement("div");
            errorDiv.className = "ai-message";
            errorDiv.innerHTML = `<strong>AI Assistant:</strong> <em>Error: ${message}</em>`;
            messagesContainer.appendChild(errorDiv);
        }

        /**
         * Setup provider UI based on configuration
         */
        function setupProviderUI() {
            // Ensure aiConfig exists before proceeding
            if (!aiConfig) {
                console.error('AI configuration not loaded, cannot setup provider UI');
                return;
            }
            // Clear existing options
            providerSelect.innerHTML = '';

            let hasAvailableProvider = false;

            // Add available providers
            if (aiConfig && aiConfig.fireworks_available) {
                const fireworksOption = document.createElement('option');
                fireworksOption.value = 'fireworks';
                fireworksOption.textContent = 'Fireworks.ai';
                providerSelect.appendChild(fireworksOption);
                hasAvailableProvider = true;
            }

            // If no providers are available, add disabled options
            if (!hasAvailableProvider) {
                if (!aiConfig || !aiConfig.fireworks_available) {
                    const fireworksOption = document.createElement('option');
                    fireworksOption.value = 'fireworks';
                    fireworksOption.textContent = 'Fireworks.ai (Not configured)';
                    fireworksOption.disabled = true;
                    providerSelect.appendChild(fireworksOption);
                }

                showConfigurationError('No AI providers are configured. Please check plugin settings.');
                return;
            }

            // Restore saved selections
            const savedProvider = localStorage.getItem('ai-chat-provider');

            if (savedProvider && document.querySelector(`option[value="${savedProvider}"]`)) {
                providerSelect.value = savedProvider;
                currentProvider = savedProvider;
            } else if (aiConfig && aiConfig.fireworks_available) {
                currentProvider = 'fireworks';
                providerSelect.value = 'fireworks';
            }
        }

        /**
         * Send a message to the AI assistant
         */
        function sendMessage() {
            const message = chatInput.value.trim();
            if (!message) {
                return;
            }

            // Check if we have a valid configuration
            if (!aiConfig || !aiConfig.fireworks_available) {
                showConfigurationError('No AI providers are configured. Please check plugin settings.');
                return;
            }

            // If no conversation is selected, create a new one
            if (!currentConversationThreadId) {
                createNewConversation().then(() => {
                    // After creating conversation, send the message
                    sendMessageWithConversation(message, currentConversationThreadId);
                }).catch(error => {
                    console.error('Failed to create new conversation:', error);
                    showConfigurationError('Failed to create new conversation. Please try again.');
                });
                return;
            }

            sendMessageWithConversation(message, currentConversationThreadId);
        }

        /**
         * Send message with existing conversation
         */
        function sendMessageWithConversation(message, currentConversationThreadId) {
            console.log('sendMessageWithConversation called with:', {
                message: message,
                conversationId: currentConversationThreadId
            });
            
            // Add user message
            const userMessageDiv = document.createElement("div");
            userMessageDiv.className = "user-message";
            userMessageDiv.innerHTML = "<strong>You:</strong> " + message;
            messagesContainer.appendChild(userMessageDiv);

            // Save user message to database
            saveMessageToDatabase(currentConversationThreadId, 'user', message);

            // Clear input
            chatInput.value = "";

            // Show loading state
            const loadingDiv = document.createElement("div");
            loadingDiv.className = "ai-message";
            loadingDiv.innerHTML = `<strong>AI Assistant:</strong> <em>Getting your credentials for ${currentProvider}...</em>`;
            messagesContainer.appendChild(loadingDiv);

            // Get user credentials via AJAX
            Ajax.call([{
                methodname: 'block_aiassistant_get_user_credentials',
                args: { provider: currentProvider },
                done: function (credentials) {
                    // Remove loading message
                    messagesContainer.removeChild(loadingDiv);

                    if (credentials.success) {
                        sendFireworksChatMessage(message, currentConversationThreadId);
                    } else {
                        const errorDiv = document.createElement("div");
                        errorDiv.className = "ai-message";
                        errorDiv.innerHTML = "<strong>AI Assistant:</strong> <em>Error: " + credentials.message + "</em>";
                        messagesContainer.appendChild(errorDiv);
                    }
                },
                fail: function (error) {
                    // Remove loading message
                    if (messagesContainer.contains(loadingDiv)) {
                        messagesContainer.removeChild(loadingDiv);
                    }

                    const errorDiv = document.createElement("div");
                    errorDiv.className = "ai-message";
                    errorDiv.innerHTML = "<strong>AI Assistant:</strong> <em>Failed to get credentials: " +
                        (error.message || 'Unknown error') + "</em>";
                    messagesContainer.appendChild(errorDiv);
                }
            }]);

            // Scroll to bottom
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        /**
         * Send chat message to Fireworks API (Direct API approach)
         * @param {string} message - The message to send
         */
        async function sendFireworksChatMessage(message, currentConversationThreadId) {
            setTimeout(async function () {
                const aiMessageDiv = document.createElement("div");
                aiMessageDiv.className = "ai-message";
                aiMessageDiv.innerHTML = "<strong>AI Assistant (Fireworks):</strong> <span class='response-text'></span>";
                messagesContainer.appendChild(aiMessageDiv);
                const responseSpan = aiMessageDiv.querySelector('.response-text');

                const url = 'http://aimove.minesparis.psl.eu/api/chat';
                const options = {
                    method: 'POST',

                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        "message": message,
                        "conversation_thread_id": currentConversationThreadId
                    })
                };

                try {
                    const response = await fetch(url, options);

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    // Handle Server-Sent Events (SSE) response
                    let aiResponse = '';
                    let retrievedDocuments = [];
                    let documentsProcessed = false;
                    let lastAIMessageContent = "";
                    const reader = response.body.getReader();
                    const decoder = new TextDecoder();

                    while (true) {
                        const { done, value } = await reader.read();
                        if (done) break;

                        const lines = decoder.decode(value, { stream: true }).split('\n');
                        for (const line of lines) {
                            if (!line.trim()) continue;
                            try {
                                const data = JSON.parse(line);
                                if (data.content === '[DONE]') break;

                                // Handle documents (process once when available)
                                if (data.documents && Array.isArray(data.documents) && data.documents.length > 0 && !documentsProcessed) {
                                    const document_sources = data.documents.map(doc => {
                                        return doc.metadata?.source || 'Unknown source';
                                    });
                                    showDocumentSidepanel(document_sources);
                                    retrievedDocuments = document_sources;
                                    documentsProcessed = true;
                                }

                                // Handle content from stream_mode="values" (array of messages)
                                if (data.content && Array.isArray(data.content)) {
                                    // Find the latest AI message in the messages array
                                    for (const msg of data.content) {
                                        // Check for AIMessage type and extract content
                                        if (msg.content && (msg.type === 'ai' || msg.__class__ === 'AIMessage' || typeof msg.content === 'string')) {
                                            const currentAIContent = msg.content;
                                            // Only update if the AI message content has changed
                                            if (currentAIContent !== lastAIMessageContent) {
                                                aiResponse = currentAIContent; // Replace with latest complete AI message
                                                responseSpan.textContent = aiResponse;
                                                messagesContainer.scrollTop = messagesContainer.scrollHeight;

                                                // Convert markdown to HTML if marked is available
                                                if (typeof marked !== 'undefined' && marked.parse) {
                                                    const html_content = marked.parse(responseSpan.textContent);
                                                    responseSpan.innerHTML = html_content;
                                                }

                                                lastAIMessageContent = currentAIContent;
                                            }
                                        }
                                    }
                                }
                                // Fallback for stream_mode="messages" (direct string content)
                                else if (data.content && typeof data.content === 'string') {
                                    aiResponse += data.content;
                                    responseSpan.textContent = aiResponse;
                                    messagesContainer.scrollTop = messagesContainer.scrollHeight;

                                    if (typeof marked !== 'undefined' && marked.parse) {
                                        const htmlContent = marked.parse(responseSpan.textContent);
                                        responseSpan.innerHTML = htmlContent;
                                    }
                                }

                                if (data.error) throw new Error(data.error);
                            } catch (e) {
                                console.error('Parse error:', e);
                            }
                        }
                    }
                    // Save AI response to database if we have content
                    if (aiResponse && aiResponse.trim()) {
                        console.log('Attempting to save AI response:', {
                            conversationId: currentConversationThreadId,
                            responseLength: aiResponse.length,
                            responsePreview: aiResponse.substring(0, 100) + '...'
                        });
                        saveMessageToDatabase(currentConversationThreadId, 'ai', aiResponse);
                    } else {
                        console.log('No AI response to save:', {
                            aiResponse: aiResponse,
                            conversationId: currentConversationThreadId
                        });
                    }

                } catch (error) {
                    console.error('FastAPI call failed:', error);
                    responseSpan.textContent = 'Sorry, there was an error processing your request: ' + error.message;
                }

                

                // Final scroll to bottom
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }, 1000);
        }

        /**
         * Generate a UUID for new conversations
         */
        function generateUUID() {
            if (typeof crypto !== 'undefined' && crypto.randomUUID) {
                return crypto.randomUUID();
            }
            // Fallback UUID generation for older browsers
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }

        /**
         * Create a new conversation
         */
        async function createNewConversation() {
            const conversationId = generateUUID();
            const conversationTitle = `New Conversation ${new Date().toLocaleTimeString()}`;

            // Create new conversation DOM element using helper function
            const conversationList = document.getElementById('ai-conversation-list');
            const newConversationItem = createConversationElement(conversationId, conversationTitle);
            conversationList.appendChild(newConversationItem);

            // Remove active class from all items and set this one as active
            const allItems = document.querySelectorAll('.ai-conversation-item');
            allItems.forEach(item => item.classList.remove('active'));
            newConversationItem.classList.add('active');

            // Set as current conversation
            currentConversationThreadId = conversationId;

            // Clear chat messages
            messagesContainer.innerHTML = '<div class="ai-message"><strong>AI Assistant:</strong> Hello! How can I help you today?</div>';

            // Add click listener to the new item
            setupConversationItemListener(newConversationItem);

            // Create conversation in Moodle database
            try {
                await createConversationInMoodle(conversationId, conversationTitle);
                console.log('New conversation created:', conversationId);
            } catch (error) {
                console.error('Failed to create conversation in Moodle:', error);
                const errorDiv = document.createElement("div");
                errorDiv.className = "ai-message";
                errorDiv.innerHTML = `<strong>AI Assistant:</strong> <em>Warning: Could not save conversation. Messages may not be saved.</em>`;
                messagesContainer.appendChild(errorDiv);
            }
        }

        /**
         * Save a message to the database
         */
        function saveMessageToDatabase(conversationId, messageType, content, metadata = '') {
            console.log('saveMessageToDatabase called with:', {
                conversationId: conversationId,
                messageType: messageType,
                content: content.substring(0, 100) + (content.length > 100 ? '...' : ''),
                metadata: metadata
            });
            
            Ajax.call([{
                methodname: 'block_aiassistant_manage_messages',
                args: {
                    action: 'save',
                    conversation_id: conversationId,
                    message_type: messageType,
                    content: content,
                    metadata: metadata
                },
                done: function (result) {
                    console.log('AJAX response received:', result);
                    if (result.success) {
                        console.log(`Message saved successfully: ${messageType} message with ID ${result.message_id}`);
                    } else {
                        console.error('Failed to save message:', result.message);
                    }
                },
                fail: function (error) {
                    console.error('Failed to save message to database:', error);
                    console.error('Error details:', {
                        name: error.name,
                        message: error.message,
                        stack: error.stack
                    });
                }
            }]);
        }

        /**
         * Load messages for a conversation from database
         */
        function loadMessagesFromDatabase(conversationId) {
            return new Promise((resolve, reject) => {
                Ajax.call([{
                    methodname: 'block_aiassistant_manage_messages',
                    args: {
                        action: 'load',
                        conversation_id: conversationId
                    },
                    done: function (result) {
                        if (result.success) {
                            console.log(`Loaded ${result.messages.length} messages for conversation ${conversationId}`);
                            resolve(result.messages);
                        } else {
                            console.error('Failed to load messages:', result.message);
                            reject(new Error(result.message));
                        }
                    },
                    fail: function (error) {
                        console.error('Failed to load messages from database:', error);
                        reject(error);
                    }
                }]);
            });
        }

        /**
         * Create conversation in Moodle database via Ajax
         */
        async function createConversationInMoodle(conversationId, conversationTitle) {
            return new Promise((resolve, reject) => {
                Ajax.call([{
                    methodname: 'block_aiassistant_manage_conversations',
                    args: {
                        action: 'create',
                        conversation_id: conversationId,
                        title: conversationTitle
                    },
                    done: function (result) {
                        if (result.success) {
                            resolve(result);
                        } else {
                            reject(new Error(result.message || 'Failed to create conversation'));
                        }
                    },
                    fail: function (error) {
                        reject(error);
                    }
                }]);
            });
        }

        /**
         * Display messages in the chat interface
         */
        function displayMessages(messages) {
            messagesContainer.innerHTML = '';

            if (messages.length === 0) {
                messagesContainer.innerHTML = '<div class="ai-message"><strong>AI Assistant:</strong> Hello! How can I help you today?</div>';
                return;
            }

            messages.forEach(message => {
                const messageDiv = document.createElement('div');
                
                if (message.message_type === 'user') {
                    messageDiv.className = 'user-message';
                    messageDiv.innerHTML = '<strong>You:</strong> ' + message.content;
                } else if (message.message_type === 'ai') {
                    messageDiv.className = 'ai-message';
                    messageDiv.innerHTML = '<strong>AI Assistant:</strong> <span class="response-text">' + message.content + '</span>';
                    
                    // Apply markdown rendering if available
                    const responseSpan = messageDiv.querySelector('.response-text');
                    if (typeof marked !== 'undefined' && marked.parse) {
                        responseSpan.innerHTML = marked.parse(message.content);
                    }
                }
                
                messagesContainer.appendChild(messageDiv);
            });

            // Scroll to bottom
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        /**
         * Setup event listener for a single conversation item
         */
        function setupConversationItemListener(item) {
            item.addEventListener('click', function (e) {
                // Don't trigger if clicking on delete button
                if (e.target.classList.contains('ai-conversation-delete-btn')) {
                    return;
                }
                
                // Remove active class from all items
                const allItems = document.querySelectorAll('.ai-conversation-item');
                allItems.forEach(i => i.classList.remove('active'));

                // Add active class to clicked item
                this.classList.add('active');

                // Get conversation data
                const conversationId = this.getAttribute('data-conversation-id');
                const conversationTitle = this.querySelector('.ai-conversation-title').textContent;

                // Set as current conversation
                currentConversationThreadId = conversationId;

                console.log('Conversation selected:', {
                    id: conversationId,
                    title: conversationTitle
                });

                // Clear messages and show loading state
                messagesContainer.innerHTML = '<div class="ai-message"><strong>AI Assistant:</strong> <em>Loading conversation...</em></div>';

                // Load messages from database
                loadMessagesFromDatabase(conversationId)
                    .then(messages => {
                        displayMessages(messages);
                    })
                    .catch(error => {
                        console.error('Failed to load messages:', error);
                        messagesContainer.innerHTML = '<div class="ai-message"><strong>AI Assistant:</strong> <em>Failed to load conversation. Please try again.</em></div>';
                    });
            });
        }

        // Setup conversation panel event listeners
        setupConversationPanel();

        // Add event listeners
        newConversationBtn.addEventListener('click', createNewConversation);
        sendButton.addEventListener("click", sendMessage);

        /**
         * Setup conversation panel functionality
         */
        function setupConversationPanel() {
            const conversationItems = document.querySelectorAll('.ai-conversation-item');

            conversationItems.forEach(item => {
                setupConversationItemListener(item);
            });
        }
        chatInput.addEventListener("keypress", function (e) {
            if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    };

    // Try to initialize immediately if DOM is ready, otherwise wait for DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeChat);
    } else {
        // DOM is already loaded, initialize immediately
        initializeChat();
    }
};