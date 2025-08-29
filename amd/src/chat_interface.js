// Standard license block omitted for brevity
/**
 * @module     block_aiassistant/chat_interface
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Ajax from 'core/ajax';

let conversationHistory = [];

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
        const chatContainer = document.getElementById("ai-chat-container");
        const resizeHandle = document.getElementById("ai-resize-handle");
        const providerSelect = document.getElementById("ai-provider-select");

        console.log('AI Chat: Elements found:', {
            sendButton: !!sendButton,
            chatInput: !!chatInput,
            messagesContainer: !!messagesContainer,
            chatContainer: !!chatContainer,
            resizeHandle: !!resizeHandle,
            providerSelect: !!providerSelect,
            claudeModelSelect: !!claudeModelSelect
        });

        if (!sendButton || !chatInput || !messagesContainer || !providerSelect) {
            console.error('AI Chat: Required elements not found');
            return;
        }

        // Global configuration storage
        let aiConfig = null;
        let currentProvider = 'fireworks';

        // Load AI configuration on startup
        loadAIConfiguration();

        
        /**
         * Displays a sidepanel containing a list of document paths.
         * Shows the sidepanel with a smooth animation and populates it with the provided document paths.
         * If no documents are provided, displays a "No documents retrieved" message.
         * 
         * @param {string[]} documentPaths - Array of document file paths to display in the sidepanel
         */
        function showDocumentSidepanel(documentPaths) {
            const sidepanel = document.getElementById('ai-sidepanel');
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
            
            sidepanel.style.display = 'block';
            setTimeout(() => sidepanel.classList.add('active'), 10);
        }
        
        /**
         * Hides the document sidepanel by removing the 'active' class and setting display to 'none' after a delay.
         * The function first removes the 'active' class from the AI sidepanel element, then waits 300ms
         * before completely hiding the element by setting its display style to 'none'.
         * 
         * @function hideDocumentSidepanel
         * @returns {void}
         */
        function hideDocumentSidepanel() {
            const sidepanel = document.getElementById('ai-sidepanel');
            sidepanel.classList.remove('active');
            setTimeout(() => sidepanel.style.display = 'none', 300);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const closeButton = document.getElementById('ai-sidepanel-close');
            if (closeButton) {
                closeButton.addEventListener('click', hideDocumentSidepanel);
            }
        });
        
        /**
         * Load AI configuration from backend
         */
        function loadAIConfiguration() {
            console.log('DEBUG: Starting AJAX call to block_aiassistant_get_ai_config');
            
            Ajax.call([{
                methodname: 'block_aiassistant_get_ai_config',
                args: {},
                done: function(config) {
                    console.log('DEBUG: AJAX success, config received:', config);
                    
                    if (config && config.success) {
                        aiConfig = config;
                        console.log('AI Config loaded successfully:', aiConfig);
                        setupProviderUI();
                    } else {
                        console.error('Failed to load AI configuration:', config ? config.message : 'No response received');
                        console.error('Full config object:', config);
                        aiConfig = {
                            success: false,
                            fireworks_available: false,
                        };
                        showConfigurationError('Failed to load AI configuration. Please check plugin settings.');
                    }
                },
                fail: function(error) {
                    console.error('AJAX call failed:', error);
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
            console.log('DEBUG: setupProviderUI called with aiConfig:', aiConfig);
            
            // Ensure aiConfig exists before proceeding
            if (!aiConfig) {
                console.error('AI configuration not loaded, cannot setup provider UI');
                return;
            }

            console.log('DEBUG: aiConfig properties:', {
                success: aiConfig.success,
                fireworks_available: aiConfig.fireworks_available,
            });

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

            // Add user message
            const userMessageDiv = document.createElement("div");
            userMessageDiv.className = "user-message";
            userMessageDiv.innerHTML = "<strong>You:</strong> " + message;
            messagesContainer.appendChild(userMessageDiv);

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
                done: function(credentials) {
                    // Remove loading message
                    messagesContainer.removeChild(loadingDiv);

                    if (credentials.success) {
                        sendFireworksChatMessage(message, credentials.api_key);
                    } else {
                        const errorDiv = document.createElement("div");
                        errorDiv.className = "ai-message";
                        errorDiv.innerHTML = "<strong>AI Assistant:</strong> <em>Error: " + credentials.message + "</em>";
                        messagesContainer.appendChild(errorDiv);
                    }
                },
                fail: function(error) {
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
         * *WIP/ Needs to send `apiKey` from the Moodle's server to the backend,
         * not the other way around.*
         * 
         * Send chat message to Fireworks API (Direct API approach)
         * @param {string} message - The message to send
         * @param {string} apiKey - The master API key for authentication
         */
        async function sendFireworksChatMessage(message, apiKey) {
            setTimeout(async function() {
                const aiMessageDiv = document.createElement("div");
                aiMessageDiv.className = "ai-message";
                aiMessageDiv.innerHTML = "<strong>AI Assistant (Fireworks):</strong> <span class='response-text'></span>";
                messagesContainer.appendChild(aiMessageDiv);
                const responseSpan = aiMessageDiv.querySelector('.response-text');
                
                const url = 'http://127.0.0.1:8000/api/chat'; // Adjust
                const options = {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        "message": message,
                        "history": conversationHistory.map(msg => ({
                            "role": msg.role,
                            "content": msg.content
                        })),
                    })
                };
                
                try {
                    const response = await fetch(url, options);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    // Handle Server-Sent Events (SSE) response
                    let aiResponse = '';
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
                                if (data.content) {
                                    aiResponse += data.content;
                                    fullMarkdownText += data.content;
                                    renderProgressiveMarkdown(fullMarkdownText, responseSpan);
                                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                                } 
                                if (data.error) throw new Error(data.error);
                            } catch (e) {
                                console.error('Parse error:', e);
                            }
                        }
                    }
                    // Update conversation history
                conversationHistory.push(
                    { role: "user", content: message },
                    { role: "assistant", content: aiResponse }
                );
                    
                    // Convert final markdown to HTML if marked is available
                    if (typeof marked !== 'undefined' && marked.parse) {
                        const htmlContent = marked.parse(responseSpan.textContent);
                        responseSpan.innerHTML = htmlContent;
                    }
                    
                } catch (error) {
                    console.error('FastAPI call failed:', error);
                    responseSpan.textContent = 'Sorry, there was an error processing your request: ' + error.message;
                }

                // Final scroll to bottom
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }, 1000);
        }

        sendButton.addEventListener("click", sendMessage);
        chatInput.addEventListener("keypress", function(e) {
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