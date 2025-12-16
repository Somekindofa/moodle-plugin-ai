/**
 * CraftPilot Chat Interface - Complete Redesign
 * Features: Collapsible bubble, sliding animation, sidebars, conversation management
 */

import * as Ajax from 'core/ajax';

// Module-level variables
let cmId, courseId, instanceId;
let currentConversationId = null;
let currentConversationTitle = 'Chat';
let allConversations = [];
let marked = null;

// DOM elements
let chatWrapper, chatToggleButton, chatInterface, messagesArea, chatInput, sendButton;
let sidebarPanel, sidebarToggle, conversationsList;
let documentsList, videoPlayerContainer;
let conversationTitleElement;

/**
 * Main initialization function
 * @param {number} moduleCmId - Course module ID
 * @param {number} moduleCourseId - Course ID
 * @param {number} moduleInstanceId - Activity instance ID
 */
export const init = (moduleCmId, moduleCourseId, moduleInstanceId) => {
    // IMMEDIATE LOGGING - debug if this function is called
    console.log('🔨 CraftPilot init() function called!');
    console.log('Parameters:', {moduleCmId, moduleCourseId, moduleInstanceId});
    
    cmId = moduleCmId;
    courseId = moduleCourseId;
    instanceId = moduleInstanceId;

    console.log('CraftPilot: Initializing...', {cmId, courseId, instanceId});

    const initializeChat = () => {
        console.log('🔨 initializeChat() starting...');
        if (typeof window.marked === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/marked/marked.min.js';
            script.onload = () => {
                marked = window.marked;
                console.log('CraftPilot: Marked.js loaded');
            };
            document.head.appendChild(script);
        } else {
            marked = window.marked;
        }

        // Get all required DOM elements
        console.log('🔨 Getting DOM elements...');
        chatWrapper = document.getElementById("ai-chat-wrapper");
        chatToggleButton = document.getElementById("ai-chat-toggle");
        chatInterface = document.getElementById("ai-chat-interface");
        messagesArea = document.getElementById("messages-area");
        chatInput = document.getElementById("user-input");
        sendButton = document.getElementById("send-btn");
        conversationTitleElement = document.getElementById("current-conversation-title");
        sidebarPanel = document.getElementById("ai-sidebar-panel");
        sidebarToggle = document.getElementById("ai-sidebar-toggle");
        
        console.log('🔨 Element status:', {
            chatWrapper: !!chatWrapper,
            chatToggleButton: !!chatToggleButton,
            chatInterface: !!chatInterface,
            messagesArea: !!messagesArea,
            chatInput: !!chatInput,
            sendButton: !!sendButton,
            sidebarPanel: !!sidebarPanel,
            sidebarToggle: !!sidebarToggle
        });
        
        conversationsList = document.getElementById("conversations-list");
        documentsList = document.getElementById("documents-list");
        videoPlayerContainer = document.getElementById("video-player-container");

        // Validate required elements
        const missingElements = [];
        if (!chatWrapper) missingElements.push('ai-chat-wrapper');
        if (!chatToggleButton) missingElements.push('ai-chat-toggle');
        if (!chatInterface) missingElements.push('ai-chat-interface');
        if (!messagesArea) missingElements.push('messages-area');
        if (!chatInput) missingElements.push('user-input');
        if (!sendButton) missingElements.push('send-btn');
        if (!sidebarToggle) missingElements.push('ai-sidebar-toggle');

        if (missingElements.length > 0) {
            console.warn('CraftPilot: Skipping init, missing elements:', missingElements.join(', '));
            return;
        }

        console.log('CraftPilot: Elements found, setting up...');

        // Setup event listeners
        chatToggleButton.addEventListener("click", toggleChatInterface);
        if (sidebarToggle) sidebarToggle.addEventListener("click", toggleSidebarPanel);
        sendButton.addEventListener("click", sendMessage);
        
        chatInput.addEventListener("keypress", (e) => {
            if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        chatInput.addEventListener('input', autoResizeTextarea);

        // Initialize conversation for this instance
        initializeConversation();
    };

    /**
     * Initialize or load conversation for this instance
     */
    const initializeConversation = () => {
        // Check if conversation exists for this user + instance
        Ajax.call([{
            methodname: 'mod_craftpilot_manage_conversations',
            args: {
                action: 'list',
                instance_id: instanceId
            }
        }])[0]
            .then((response) => {
                const conversations = response.conversations || response.data || [];
                if (response.success && conversations.length > 0) {
                    allConversations = conversations;
                    // Load existing conversation
                    const firstConv = conversations[0];
                    currentConversationId = firstConv.conversation_id || firstConv.id;
                    currentConversationTitle = firstConv.title || 'Chat';
                    console.log('CraftPilot: Found existing conversation', currentConversationId);
                    updateConversationHeader();
                    populateConversationsList(conversations, currentConversationId);
                    loadMessagesFromDatabase(currentConversationId);
                } else {
                    // Create new conversation
                    const conversationId = generateUUID();
                    createConversationInMoodle(conversationId);
                }
            })
            .catch((error) => {
                console.error('CraftPilot: Failed to initialize conversation', error);
                if (messagesArea) {
                    messagesArea.innerHTML = '<div class="ai-message assistant-message"><em>Failed to initialize chat. Please refresh the page.</em></div>';
                }
            });
    };

    /**
     * Toggle chat interface (slide up/down from bottom)
     */
    const toggleChatInterface = () => {
        const isExpanded = chatInterface.classList.contains('expanded');
        if (isExpanded) {
            chatInterface.classList.remove('expanded');
            sidebarPanel.classList.remove('expanded');
            if (chatWrapper) chatWrapper.classList.remove('expanded');
            if (sidebarToggle) sidebarToggle.classList.remove('expanded');
            if (sidebarToggle) sidebarToggle.querySelector('.ai-sidebar-toggle-arrow').textContent = '◀';
        } else {
            chatInterface.classList.add('expanded');
            if (chatWrapper) chatWrapper.classList.add('expanded');
            chatInput.focus();
        }
    };

    /**
     * Toggle sidebar panel via handle
     */
    const toggleSidebarPanel = () => {
        // Ensure chat is open when toggling sidebar
        if (!chatInterface.classList.contains('expanded')) {
            toggleChatInterface();
        }
        const isExpanded = sidebarPanel.classList.contains('expanded');
        if (isExpanded) {
            sidebarPanel.classList.remove('expanded');
            if (sidebarToggle) sidebarToggle.classList.remove('expanded');
            if (sidebarToggle) sidebarToggle.querySelector('.ai-sidebar-toggle-arrow').textContent = '◀';
        } else {
            sidebarPanel.classList.add('expanded');
            if (sidebarToggle) sidebarToggle.classList.add('expanded');
            if (sidebarToggle) sidebarToggle.querySelector('.ai-sidebar-toggle-arrow').textContent = '▶';
        }
    };

    /**
     * Update conversation header title
     */
    const updateConversationHeader = () => {
        if (conversationTitleElement) {
            conversationTitleElement.textContent = currentConversationTitle || 'Chat';
        }
    };

    /**
     * Populate conversations list in left sidebar
     */
    const populateConversationsList = (conversations, activeId) => {
        conversationsList.innerHTML = '';
        
        conversations.forEach((conv) => {
            const convId = String(conv.conversation_id || conv.id);
            const item = document.createElement('div');
            item.className = 'conversation-item';
            item.dataset.conversationId = convId;
            if (convId === String(activeId)) {
                item.classList.add('active');
            }
            
            const meta = document.createElement('div');
            meta.className = 'conversation-meta';

            const title = document.createElement('strong');
            title.textContent = conv.title || 'Chat';
            meta.appendChild(title);
            
            const timestamp = document.createElement('div');
            timestamp.style.fontSize = '11px';
            timestamp.style.color = '#999';
            timestamp.textContent = new Date(conv.created_time || conv.created).toLocaleDateString();
            meta.appendChild(timestamp);
            
            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'conversation-delete';
            deleteBtn.title = 'Delete conversation';
            deleteBtn.setAttribute('aria-label', 'Delete conversation');
            deleteBtn.textContent = '🗑';
            deleteBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                deleteConversation(convId);
            });

            item.appendChild(meta);
            item.appendChild(deleteBtn);
            item.addEventListener('click', () => selectConversation(convId, conv.title || 'Chat'));
            conversationsList.appendChild(item);
        });
    };

    /**
     * Select a conversation and load its messages
     */
    const selectConversation = (conversationId, title) => {
        const targetId = String(conversationId);
        currentConversationId = targetId;
        currentConversationTitle = title;
        updateConversationHeader();
        
        if (conversationsList) {
            conversationsList.querySelectorAll('.conversation-item').forEach(item => {
                if (item.dataset.conversationId === targetId) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
        }
        
        messagesArea.innerHTML = '';
        loadMessagesFromDatabase(targetId);
    };

    /**
     * Delete a conversation and its messages
     */
    const deleteConversation = (conversationId) => {
        if (!conversationId) return;
        const confirmed = window.confirm('Delete this conversation and all its messages?');
        if (!confirmed) return;

        Ajax.call([{
            methodname: 'mod_craftpilot_manage_conversations',
            args: {
                action: 'delete',
                conversation_id: conversationId
            }
        }])[0]
            .then((response) => {
                if (!response.success) {
                    console.error('CraftPilot: Failed to delete conversation', response.message);
                    return;
                }

                allConversations = allConversations.filter((conv) => String(conv.conversation_id || conv.id) !== String(conversationId));

                if (String(currentConversationId) === String(conversationId)) {
                    if (allConversations.length > 0) {
                        const next = allConversations[0];
                        selectConversation(next.conversation_id || next.id, next.title || 'Chat');
                    } else {
                        const newId = generateUUID();
                        const now = Date.now();
                        allConversations = [{
                            conversation_id: newId,
                            title: 'Chat Session',
                            created_time: now
                        }];
                        populateConversationsList(allConversations, newId);
                        createConversationInMoodle(newId);
                        return;
                    }
                }

                populateConversationsList(allConversations, currentConversationId);
            })
            .catch((error) => {
                console.error('CraftPilot: Error deleting conversation', error);
            });
    };

    /**
     * Create new conversation for this instance
     */
    const createConversationInMoodle = (conversationId) => {
        Ajax.call([{
            methodname: 'mod_craftpilot_manage_conversations',
            args: {
                action: 'create',
                conversation_id: conversationId,
                title: 'Chat Session',
                instance_id: instanceId,
                metadata: JSON.stringify({
                    provider: 'fireworks',
                    created: new Date().toISOString()
                })
            }
        }])[0]
            .then((response) => {
                if (response.success) {
                    currentConversationId = conversationId;
                    currentConversationTitle = 'Chat Session';
                    updateConversationHeader();
                    console.log('CraftPilot: Created new conversation', conversationId);
                    if (messagesArea) {
                        messagesArea.innerHTML = '<div class="ai-message assistant-message"><em>Ready to assist you!</em></div>';
                    }
                } else {
                    console.error('CraftPilot: Failed to create conversation', response.message);
                }
            })
            .catch((error) => {
                console.error('CraftPilot: Error creating conversation', error);
            });
    };

    /**
     * Load messages from database
     */
    const loadMessagesFromDatabase = (conversationId) => {
        Ajax.call([{
            methodname: 'mod_craftpilot_manage_messages',
            args: {
                action: 'load',
                conversation_id: conversationId
            }
        }])[0]
            .then((response) => {
                const messages = response.messages || response.data || [];
                if (response.success) {
                    if (messages.length > 0) {
                        displayMessages(messages);
                    } else if (messagesArea) {
                        messagesArea.innerHTML = '<div class="ai-message assistant-message"><em>Ready to assist you!</em></div>';
                    }
                }
            })
            .catch((error) => {
                console.error('CraftPilot: Failed to load messages', error);
            });
    };

    /**
     * Display messages in chat container
     */
    const displayMessages = (messages) => {
        messagesArea.innerHTML = '';
        
        messages.forEach((message) => {
            const messageDiv = document.createElement('div');
            
            if (message.role === 'user') {
                messageDiv.className = 'ai-message user-message';
                messageDiv.textContent = message.content;
            } else {
                messageDiv.className = 'ai-message assistant-message';
                const content = marked ? marked.parse(message.content) : escapeHtml(message.content);
                messageDiv.innerHTML = content;
            }
            
            messagesArea.appendChild(messageDiv);
        });

        messagesArea.parentElement.scrollTop = messagesArea.parentElement.scrollHeight;
    };

    /**
     * Send message handler
     */
    const sendMessage = () => {
        const userMessage = chatInput.value.trim();
        
        if (!userMessage) {
            return;
        }

        if (!currentConversationId) {
            messagesArea.innerHTML = '<div class="ai-message assistant-message"><em>Initializing conversation...</em></div>';
            setTimeout(sendMessage, 1000); // Retry after conversation is created
            return;
        }

        // Disable input
        chatInput.disabled = true;
        sendButton.disabled = true;
        
        // Display user message
        const userDiv = document.createElement('div');
        userDiv.className = 'ai-message user-message';
        userDiv.textContent = userMessage;
        messagesArea.appendChild(userDiv);
        
        // Clear input
        chatInput.value = '';
        autoResizeTextarea();
        
        // Scroll to bottom
        messagesArea.parentElement.scrollTop = messagesArea.parentElement.scrollHeight;
        
        // Save user message
        saveMessageToDatabase(currentConversationId, 'user', userMessage);
        
        // Send to AI
        sendFireworksChatMessage(userMessage);
    };

    /**
     * Send message to Fireworks AI via RAG backend
     */
    const sendFireworksChatMessage = (userMessage) => {
        // Display loading state
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'ai-message assistant-message';
        loadingDiv.innerHTML = '<em>Thinking...</em>';
        messagesArea.appendChild(loadingDiv);
        messagesArea.parentElement.scrollTop = messagesArea.parentElement.scrollHeight;

        // Get Fireworks API key
        Ajax.call([{
            methodname: 'mod_craftpilot_get_user_credentials',
            args: {}
        }])[0]
            .then((response) => {
                if (!response.success || !response.api_key) {
                    loadingDiv.innerHTML = '<em style="color: red;">Error: Fireworks API key not configured</em>';
                    chatInput.disabled = false;
                    sendButton.disabled = false;
                    return;
                }

                const apiKey = response.api_key;
                
                // Prepare request
                const requestBody = {
                    message: userMessage,
                    conversation_thread_id: currentConversationId
                };

                // Stream response from RAG backend
                fetch('http://127.0.0.1:8000/api/chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(requestBody)
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }

                    const reader = response.body.getReader();
                    const decoder = new TextDecoder();
                    let buffer = '';
                    let assistantResponse = '';

                    // Replace loading message with empty assistant message
                    loadingDiv.innerHTML = '<span class="ai-response-content"></span>';
                    const contentSpan = loadingDiv.querySelector('.ai-response-content');

                    const readStream = () => {
                        return reader.read().then(({done, value}) => {
                            if (done) {
                                // Save complete response
                                saveMessageToDatabase(currentConversationId, 'assistant', assistantResponse);
                                
                                // Re-enable input
                                chatInput.disabled = false;
                                sendButton.disabled = false;
                                chatInput.focus();
                                return;
                            }

                            buffer += decoder.decode(value, {stream: true});
                            const lines = buffer.split('\n');
                            buffer = lines.pop(); // Keep incomplete line

                            lines.forEach(line => {
                                if (!line.trim()) return;

                                try {
                                    const parsed = JSON.parse(line);

                                    // Handle Python backend format: {event: 'message', content: [...], documents: [...]}
                                    if (parsed.event === 'message' && parsed.content) {
                                        // Extract text from content array
                                        parsed.content.forEach(msg => {
                                            if (msg.content) {
                                                assistantResponse += msg.content;
                                            }
                                        });
                                        contentSpan.innerHTML = marked ? marked.parse(assistantResponse) : escapeHtml(assistantResponse);
                                        messagesArea.parentElement.scrollTop = messagesArea.parentElement.scrollHeight;
                                        
                                        // Show documents if present
                                        if (parsed.documents && parsed.documents.length > 0) {
                                            showDocuments(parsed.documents);
                                        }
                                    } else if (parsed.content === '[DONE]') {
                                        return;
                                    } else if (parsed.type === 'video_segment') {
                                        displayVideoSegment(parsed.video_id, parsed.start_time, parsed.end_time);
                                    } else if (parsed.type === 'error' || parsed.event === 'error') {
                                        const errorMsg = parsed.message || parsed.content;
                                        contentSpan.innerHTML = '<em style="color: red;">Error: ' + escapeHtml(errorMsg) + '</em>';
                                        chatInput.disabled = false;
                                        sendButton.disabled = false;
                                        return;
                                    }
                                } catch (e) {
                                    console.error('CraftPilot: Failed to parse streaming data:', e, 'Line:', line);
                                }
                            });

                            return readStream();
                        });
                    };

                    return readStream();
                })
                .catch(error => {
                    console.error('CraftPilot: Error streaming response:', error);
                    loadingDiv.innerHTML = '<em style="color: red;">Failed to get response. Please check if the RAG backend is running.</em>';
                    chatInput.disabled = false;
                    sendButton.disabled = false;
                });
            })
            .catch((error) => {
                console.error('CraftPilot: Failed to get credentials:', error);
                loadingDiv.innerHTML = '<em style="color: red;">Failed to retrieve API credentials</em>';
                chatInput.disabled = false;
                sendButton.disabled = false;
            });
    };

    /**
     * Save message to database
     */
    const saveMessageToDatabase = (conversationId, messageRole, messageContent) => {
        const messageType = messageRole === 'assistant' ? 'ai' : 'user';

        Ajax.call([{
            methodname: 'mod_craftpilot_manage_messages',
            args: {
                action: 'save',
                conversation_id: conversationId,
                message_type: messageType,
                content: messageContent
            }
        }])[0]
            .then((response) => {
                if (response.success) {
                    console.log('CraftPilot: Message saved:', messageRole);
                } else {
                    console.error('CraftPilot: Failed to save message:', response.message);
                }
            })
            .catch((error) => {
                console.error('CraftPilot: Error saving message:', error);
            });
    };

    /**
     * Display retrieved documents in sidebar
     */
    const showDocuments = (documents) => {
        if (!documentsList) return;

        documentsList.innerHTML = '';
        
        if (documents.length === 0) {
            return;
        }

        documents.forEach((doc) => {
            const item = document.createElement('div');
            item.className = 'document-item';
            
            const title = document.createElement('strong');
            title.textContent = doc.title || 'Document';
            item.appendChild(title);
            
            const content = document.createElement('p');
            content.style.fontSize = '12px';
            content.style.marginTop = '4px';
            content.style.color = '#666';
            content.textContent = doc.content.substring(0, 150) + (doc.content.length > 150 ? '...' : '');
            item.appendChild(content);
            
            documentsList.appendChild(item);
        });
    };

    /**
     * Display video segment
     */
    const displayVideoSegment = (videoId, startTime, endTime) => {
        if (!videoPlayerContainer) return;

        // Show documents sidebar
        documentsSidebar.style.display = 'flex';

        const iframe = document.createElement('iframe');
        iframe.width = '100%';
        iframe.height = '200';
        iframe.src = 'https://www.youtube.com/embed/' + videoId + '?start=' + Math.floor(startTime) + '&end=' + Math.floor(endTime);
        iframe.frameBorder = '0';
        iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
        iframe.allowFullscreen = true;
        iframe.style.borderRadius = '4px';

        const info = document.createElement('div');
        info.style.fontSize = '12px';
        info.style.marginTop = '8px';
        info.style.color = '#666';
        info.innerHTML = '<strong>Video:</strong> ' + formatTime(startTime) + ' - ' + formatTime(endTime);

        videoPlayerContainer.innerHTML = '';
        videoPlayerContainer.appendChild(iframe);
        videoPlayerContainer.appendChild(info);
    };

    /**
     * Format timestamp as HH:MM:SS
     */
    const formatTime = (seconds) => {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);
        
        return [
            hours.toString().padStart(2, '0'),
            minutes.toString().padStart(2, '0'),
            secs.toString().padStart(2, '0')
        ].join(':');
    };

    /**
     * Generate UUID for conversation ID
     */
    const generateUUID = () => {
        if (typeof crypto !== 'undefined' && crypto.randomUUID) {
            return crypto.randomUUID();
        }
        // Fallback for older browsers
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    };

    /**
     * Auto-resize textarea as user types
     */
    const autoResizeTextarea = () => {
        chatInput.style.height = 'auto';
        const lineHeight = 24;
        const maxLines = 4;
        const maxHeight = lineHeight * maxLines;
        const newHeight = Math.min(chatInput.scrollHeight, maxHeight);
        chatInput.style.height = newHeight + 'px';
    };

    /**
     * Escape HTML to prevent XSS
     */
    const escapeHtml = (text) => {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeChat);
    } else {
        initializeChat();
    }
};
