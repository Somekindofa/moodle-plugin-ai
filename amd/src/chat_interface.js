/**
 * AI Assistant Chat Interface - Refactored for mod_craftpilot
 * Streamlined version for per-instance conversations with Fireworks AI
 */

import * as Ajax from 'core/ajax';

// Module-level variables
let cmId, courseId, instanceId;
let currentConversationId = null;
let marked = null;

// DOM elements
let chatInput, sendButton, messagesContainer, resultsArea, documentsContainer, videoPlayerContainer;

/**
 * Main initialization function
 * @param {number} moduleCmId - Course module ID
 * @param {number} moduleCourseId - Course ID
 * @param {number} moduleInstanceId - Activity instance ID
 */
export const init = (moduleCmId, moduleCourseId, moduleInstanceId) => {
    cmId = moduleCmId;
    courseId = moduleCourseId;
    instanceId = moduleInstanceId;

    console.log('AI Chat: Initializing...', {cmId, courseId, instanceId});

    const initializeChat = () => {
        // Load marked.js for markdown rendering
        if (typeof window.marked === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/marked/marked.min.js';
            script.onload = () => {
                marked = window.marked;
                console.log('Marked.js loaded');
            };
            document.head.appendChild(script);
        } else {
            marked = window.marked;
        }

        // Get DOM elements (IDs follow view.php markup)
        chatInput = document.getElementById("user-input");
        sendButton = document.getElementById("send-btn");
        messagesContainer = document.getElementById("messages-area");
        resultsArea = document.getElementById("documents-sidepanel");
        documentsContainer = document.getElementById("documents-list");
        videoPlayerContainer = document.getElementById("video-player-container");

        // Validate required elements; if any are missing, log once and exit gracefully
        const missingElements = [];
        if (!chatInput) missingElements.push('user-input');
        if (!sendButton) missingElements.push('send-btn');
        if (!messagesContainer) missingElements.push('messages-area');

        if (missingElements.length > 0) {
            console.warn('AI Chat: Skipping init, missing elements:', missingElements.join(', '));
            return;
        }

        console.log('AI Chat: Elements found, setting up...');

        // Initialize conversation for this instance
        initializeConversation();

        // Setup event listeners
        sendButton.addEventListener("click", sendMessage);
        
        chatInput.addEventListener("keypress", (e) => {
            if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        chatInput.addEventListener('input', autoResizeTextarea);
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
                    // Load existing conversation
                    currentConversationId = conversations[0].conversation_id || conversations[0].id;
                    console.log('AI Chat: Found existing conversation', currentConversationId);
                    loadMessagesFromDatabase(currentConversationId);
                } else {
                    // Create new conversation
                    const conversationId = generateUUID();
                    createConversationInMoodle(conversationId);
                }
            })
            .catch((error) => {
                console.error('AI Chat: Failed to initialize conversation', error);
                if (messagesContainer) {
                    messagesContainer.innerHTML = '<div class="ai-message"><strong>AI Assistant:</strong> <em>Failed to initialize chat. Please refresh the page.</em></div>';
                }
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
                    console.log('AI Chat: Created new conversation', conversationId);
                    if (messagesContainer) {
                        messagesContainer.innerHTML = '<div class="ai-message"><strong>AI Assistant:</strong> <em>Ready to assist you!</em></div>';
                    }
                } else {
                    console.error('AI Chat: Failed to create conversation', response.message);
                }
            })
            .catch((error) => {
                console.error('AI Chat: Error creating conversation', error);
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
                    } else if (messagesContainer) {
                        messagesContainer.innerHTML = '<div class="ai-message"><strong>AI Assistant:</strong> <em>Ready to assist you!</em></div>';
                    }
                }
            })
            .catch((error) => {
                console.error('AI Chat: Failed to load messages', error);
            });
    };

    /**
     * Display messages in chat container
     */
    const displayMessages = (messages) => {
        messagesContainer.innerHTML = '';
        
        messages.forEach((message) => {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'ai-message';
            
            if (message.role === 'user') {
                messageDiv.innerHTML = '<strong>You:</strong> ' + escapeHtml(message.content);
            } else {
                const content = marked ? marked.parse(message.content) : escapeHtml(message.content);
                messageDiv.innerHTML = '<strong>AI Assistant:</strong> ' + content;
            }
            
            messagesContainer.appendChild(messageDiv);
        });

        messagesContainer.scrollTop = messagesContainer.scrollHeight;
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
            messagesContainer.innerHTML = '<div class="ai-message"><strong>AI Assistant:</strong> <em>Initializing conversation...</em></div>';
            setTimeout(sendMessage, 1000); // Retry after conversation is created
            return;
        }

        // Disable input
        chatInput.disabled = true;
        sendButton.disabled = true;
        
        // Display user message
        const userDiv = document.createElement('div');
        userDiv.className = 'ai-message';
        userDiv.innerHTML = '<strong>You:</strong> ' + escapeHtml(userMessage);
        messagesContainer.appendChild(userDiv);
        
        // Clear input
        chatInput.value = '';
        autoResizeTextarea();
        
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
        loadingDiv.className = 'ai-message';
        loadingDiv.innerHTML = '<strong>AI Assistant:</strong> <em>Thinking...</em>';
        messagesContainer.appendChild(loadingDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;

        // Get Fireworks API key
        Ajax.call([{
            methodname: 'mod_craftpilot_get_user_credentials',
            args: {}
        }])[0]
            .then((response) => {
                if (!response.success || !response.api_key) {
                    loadingDiv.innerHTML = '<strong>AI Assistant:</strong> <em style="color: red;">Error: Fireworks API key not configured</em>';
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
                loadingDiv.innerHTML = '<strong>AI Assistant:</strong> <span class="ai-response-content"></span>';
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
                        const lines = buffer.split('\n\n');
                        buffer = lines.pop(); // Keep incomplete line

                        lines.forEach(line => {
                            if (line.startsWith('data: ')) {
                                const data = line.substring(6);
                                
                                if (data === '[DONE]') {
                                    return;
                                }

                                try {
                                    const parsed = JSON.parse(data);

                                    if (parsed.type === 'text') {
                                        assistantResponse += parsed.content;
                                        contentSpan.innerHTML = marked ? marked.parse(assistantResponse) : escapeHtml(assistantResponse);
                                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                                    } else if (parsed.type === 'documents' && parsed.documents) {
                                        showDocuments(parsed.documents);
                                    } else if (parsed.type === 'video_segment') {
                                        displayVideoSegment(parsed.video_id, parsed.start_time, parsed.end_time);
                                    } else if (parsed.type === 'error') {
                                        contentSpan.innerHTML = '<em style="color: red;">Error: ' + escapeHtml(parsed.message) + '</em>';
                                        chatInput.disabled = false;
                                        sendButton.disabled = false;
                                        return;
                                    }
                                } catch (e) {
                                    console.error('Failed to parse SSE data:', e);
                                }
                            }
                        });

                        return readStream();
                    });
                };

                return readStream();
            })
            .catch(error => {
                console.error('Error streaming response:', error);
                loadingDiv.innerHTML = '<strong>AI Assistant:</strong> <em style="color: red;">Failed to get response. Please check if the RAG backend is running.</em>';
                chatInput.disabled = false;
                sendButton.disabled = false;
            });
        })
        .catch((error) => {
            console.error('Failed to get credentials:', error);
            loadingDiv.innerHTML = '<strong>AI Assistant:</strong> <em style="color: red;">Failed to retrieve API credentials</em>';
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
                    console.log('Message saved:', messageRole);
                } else {
                    console.error('Failed to save message:', response.message);
                }
            })
            .catch((error) => {
                console.error('Error saving message:', error);
            });
    };

    /**
     * Display retrieved documents in sidepanel
     */
    const showDocuments = (documents) => {
        if (!documentsContainer) return;

        documentsContainer.innerHTML = '';
        
        if (documents.length === 0) {
            hideResultsArea();
            return;
        }

        showResultsArea();

        const listElement = document.createElement('ul');
        listElement.className = 'ai-documents-list';

        documents.forEach((doc) => {
            const item = document.createElement('li');
            item.className = 'ai-document-item';
            
            const title = document.createElement('strong');
            title.textContent = doc.title || 'Document';
            item.appendChild(title);
            
            const content = document.createElement('p');
            content.textContent = doc.content.substring(0, 200) + (doc.content.length > 200 ? '...' : '');
            item.appendChild(content);
            
            listElement.appendChild(item);
        });

        documentsContainer.appendChild(listElement);
    };

    /**
     * Display video segment
     */
    const displayVideoSegment = (videoId, startTime, endTime) => {
        if (!videoPlayerContainer) return;

        showResultsArea();
        if (videoPlayerContainer.style.display === 'none') {
            videoPlayerContainer.style.display = 'block';
        }

        const iframe = document.createElement('iframe');
        iframe.width = '100%';
        iframe.height = '315';
        iframe.src = 'https://www.youtube.com/embed/' + videoId + '?start=' + Math.floor(startTime) + '&end=' + Math.floor(endTime);
        iframe.frameBorder = '0';
        iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
        iframe.allowFullscreen = true;

        const info = document.createElement('div');
        info.className = 'ai-video-info';
        info.innerHTML = '<strong>Video Segment:</strong> ' + formatTime(startTime) + ' - ' + formatTime(endTime);

        videoPlayerContainer.innerHTML = '';
        videoPlayerContainer.appendChild(iframe);
        videoPlayerContainer.appendChild(info);
    };

    /**
     * Clear video player
     */
    const clearVideoPlayer = () => {
        if (videoPlayerContainer) {
            videoPlayerContainer.style.display = 'none';
            videoPlayerContainer.innerHTML = '';
        }
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
     * Show results sidepanel
     */
    const showResultsArea = () => {
        if (resultsArea) {
            resultsArea.style.display = 'block';
        }
    };

    /**
     * Hide results sidepanel
     */
    const hideResultsArea = () => {
        if (resultsArea) {
            resultsArea.style.display = 'none';
        }
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
