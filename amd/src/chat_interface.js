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
        const chatContainer = document.getElementById("ai-chat-container");
        const resizeHandle = document.getElementById("ai-resize-handle");
        const providerSelect = document.getElementById("ai-provider-select");
        const claudeModelSelect = document.getElementById("claude-model-select");
        const claudeModelSelection = document.getElementById("claude-model-selection");

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
        let selectedClaudeModel = '';

        // Load AI configuration on startup
        loadAIConfiguration();

        // Load saved dimensions from localStorage
        const savedWidth = localStorage.getItem('ai-chat-width');
        const savedHeight = localStorage.getItem('ai-chat-height');

        if (savedWidth) {
            chatContainer.style.width = savedWidth + 'px';
        }
        if (savedHeight) {
            messagesContainer.style.height = savedHeight + 'px';
        }

        // Resize functionality
        let isResizing = false;
        let startX, startY, startWidth, startHeight;

        if (resizeHandle) {
            resizeHandle.addEventListener('mousedown', function(e) {
                isResizing = true;
                chatContainer.classList.add('resizing');
                startX = e.clientX;
                startY = e.clientY;
                startWidth = parseInt(window.getComputedStyle(chatContainer).width, 10);
                startHeight = parseInt(window.getComputedStyle(messagesContainer).height, 10);
                e.preventDefault();
            });
        }

        document.addEventListener('mousemove', function(e) {
            if (!isResizing) {
                return;
            }
            const newWidth = Math.max(300, Math.min(800, startWidth + (e.clientX - startX)));
            const newHeight = Math.max(150, Math.min(600, startHeight + (e.clientY - startY)));
            chatContainer.style.width = newWidth + 'px';
            messagesContainer.style.height = newHeight + 'px';
            // Save to localStorage
            localStorage.setItem('ai-chat-width', newWidth);
            localStorage.setItem('ai-chat-height', newHeight);
        });

        document.addEventListener('mouseup', function() {
            if (isResizing) {
                isResizing = false;
                chatContainer.classList.remove('resizing');
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
                            claude_available: false,
                            fireworks_available: false,
                            claude_models: [],
                            default_claude_model: ''
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
                        claude_available: false,
                        fireworks_available: false,
                        claude_models: [],
                        default_claude_model: ''
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
                claude_available: aiConfig.claude_available,
                fireworks_available: aiConfig.fireworks_available,
                default_claude_model: aiConfig.default_claude_model,
                claude_models: aiConfig.claude_models
            });

            // Clear existing options
            providerSelect.innerHTML = '';
            if (claudeModelSelect) {
                claudeModelSelect.innerHTML = '';
            }

            let hasAvailableProvider = false;

            // Add available providers
            if (aiConfig && aiConfig.fireworks_available) {
                const fireworksOption = document.createElement('option');
                fireworksOption.value = 'fireworks';
                fireworksOption.textContent = 'Fireworks.ai';
                providerSelect.appendChild(fireworksOption);
                hasAvailableProvider = true;
            }

            if (aiConfig && aiConfig.claude_available) {
                const claudeOption = document.createElement('option');
                claudeOption.value = 'claude';
                claudeOption.textContent = 'Claude API (Under maintenance - coming soon)';
                claudeOption.disabled = true; // Temporarily disable Claude
                claudeOption.style.color = '#999'; // Grey out the option
                providerSelect.appendChild(claudeOption);
                // Don't set hasAvailableProvider = true for Claude temporarily

                // Comment out Claude model population for now
                /*
                // Populate Claude models if element exists and models are available
                if (claudeModelSelect && aiConfig.claude_models && Array.isArray(aiConfig.claude_models)) {
                    aiConfig.claude_models.forEach(model => {
                        const modelOption = document.createElement('option');
                        modelOption.value = model.key;
                        modelOption.textContent = model.name;
                        claudeModelSelect.appendChild(modelOption);
                    });

                    // Set default Claude model
                    if (aiConfig && aiConfig.default_claude_model) {
                        claudeModelSelect.value = aiConfig.default_claude_model;
                        selectedClaudeModel = aiConfig.default_claude_model;
                    }
                }
                */
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

                if (!aiConfig || !aiConfig.claude_available) {
                    const claudeOption = document.createElement('option');
                    claudeOption.value = 'claude';
                    claudeOption.textContent = 'Claude API (Not configured)';
                    claudeOption.disabled = true;
                    providerSelect.appendChild(claudeOption);
                }
                
                showConfigurationError('No AI providers are configured. Please check plugin settings.');
                return;
            }

            // Restore saved selections
            const savedProvider = localStorage.getItem('ai-chat-provider');
            const savedClaudeModel = localStorage.getItem('ai-chat-claude-model');

            if (savedProvider && document.querySelector(`option[value="${savedProvider}"]`)) {
                providerSelect.value = savedProvider;
                currentProvider = savedProvider;
            } else if (aiConfig && aiConfig.fireworks_available) {
                currentProvider = 'fireworks';
                providerSelect.value = 'fireworks';
            } else if (aiConfig && aiConfig.claude_available) {
                currentProvider = 'claude';
                providerSelect.value = 'claude';
            }

            if (savedClaudeModel && claudeModelSelect && document.querySelector(`#claude-model-select option[value="${savedClaudeModel}"]`)) {
                claudeModelSelect.value = savedClaudeModel;
                selectedClaudeModel = savedClaudeModel;
            }

            // Show/hide Claude model selection
            updateClaudeModelVisibility();
        }

        /**
         * Update Claude model selection visibility
         */
        function updateClaudeModelVisibility() {
            if (claudeModelSelection && currentProvider === 'claude' && aiConfig?.claude_available) {
                claudeModelSelection.style.display = 'flex';
            } else if (claudeModelSelection) {
                claudeModelSelection.style.display = 'none';
            }
        }

        // Provider selection change handler
        if (providerSelect) {
            providerSelect.addEventListener('change', function() {
                currentProvider = this.value;
                localStorage.setItem('ai-chat-provider', currentProvider);
                updateClaudeModelVisibility();
                
                // Clear chat messages when switching providers
                messagesContainer.innerHTML = `
                    <div class="ai-message">
                        <strong>AI Assistant:</strong> Hello! I'm now using ${currentProvider === 'claude' ? 'Claude API' : 'Fireworks.ai'}. How can I help you today?
                    </div>
                `;
            });
        }

        // Claude model selection change handler
        if (claudeModelSelect) {
            claudeModelSelect.addEventListener('change', function() {
                selectedClaudeModel = this.value;
                localStorage.setItem('ai-chat-claude-model', selectedClaudeModel);
            });
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
            if (!aiConfig || (!aiConfig.fireworks_available && !aiConfig.claude_available)) {
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
                        // Now make the actual chat request using those credentials
                        if (currentProvider === 'claude') {
                            sendClaudeChatMessage(message, credentials.api_key);
                        } else {
                            sendFireworksChatMessage(message, credentials.api_key);
                        }
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
         * Send chat message to Fireworks API with streaming support
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
                
                const url = 'https://api.fireworks.ai/inference/v1/chat/completions';
                const options = {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + apiKey,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        "model": "accounts/fireworks/models/llama-v3p1-8b-instruct",
                        "messages": [
                            {
                                "role": "user",
                                "content": message
                            }
                        ],
                        "max_tokens": 2000,
                        "prompt_truncate_len": 1500,
                        "temperature": 0.7,
                        "top_p": 1,
                        "top_k": 50,
                        "frequency_penalty": 0,
                        "perf_metrics_in_response": false,
                        "presence_penalty": 0,
                        "repetition_penalty": 1,
                        "mirostat_lr": 0.1,
                        "mirostat_target": 1.5,
                        "n": 1,
                        "ignore_eos": false,
                        "response_format": null,
                        "stream": true,
                        "context_length_exceeded_behavior": "truncate"
                    })
                };
                
                try {
                    const response = await fetch(url, options);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const reader = response.body.getReader();
                    const decoder = new TextDecoder();
                    let responseContent = '';
                    
                    while (true) {
                        const { done, value } = await reader.read();
                        
                        if (done) {
                            break;
                        }
                        
                        const chunk = decoder.decode(value);
                        const lines = chunk.split('\n');
                        
                        for (const line of lines) {
                            if (line.startsWith('data: ')) {
                                const data = line.slice(6);
                                
                                if (data === '[DONE]') {
                                    break;
                                }
                                
                                try {
                                    const parsed = JSON.parse(data);
                                    
                                    if (parsed.choices && parsed.choices[0] && parsed.choices[0].delta) {
                                        const content = parsed.choices[0].delta.content || '';
                                        responseContent += content;
                                        
                                        // Convert markdown to HTML if marked is available
                                        if (typeof marked !== 'undefined' && marked.parse) {
                                            const htmlContent = marked.parse(responseContent);
                                            responseSpan.innerHTML = htmlContent;
                                        } else {
                                            responseSpan.textContent = responseContent;
                                        }
                                        
                                        // Scroll to bottom
                                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                                    }
                                } catch (parseError) {
                                    console.log('Non-JSON data chunk:', data);
                                }
                            }
                        }
                    }
                    
                } catch (error) {
                    console.error('Fireworks API call failed:', error);
                    responseSpan.textContent = 'Sorry, there was an error processing your request: ' + error.message;
                }

                // Final scroll to bottom
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }, 1000);
        }

        /**
         * Send chat message to Claude API with streaming support
         * @param {string} message - The message to send
         * @param {string} apiKey - The API key for authentication
         */
        async function sendClaudeChatMessage(message, apiKey) {
            setTimeout(async function() {
                const aiMessageDiv = document.createElement("div");
                aiMessageDiv.className = "ai-message";
                aiMessageDiv.innerHTML = `<strong>AI Assistant (Claude):</strong> <span class='response-text'></span>`;
                messagesContainer.appendChild(aiMessageDiv);
                const responseSpan = aiMessageDiv.querySelector('.response-text');
                
                // Determine which model to use with safe fallback
                let modelToUse = selectedClaudeModel;
                if (!modelToUse && aiConfig && aiConfig.default_claude_model) {
                    modelToUse = aiConfig.default_claude_model;
                }
                if (!modelToUse) {
                    modelToUse = 'claude-sonnet-4-20250514'; // hardcoded fallback
                }

                const url = 'https://api.anthropic.com/v1/messages';
                const options = {
                    method: 'POST',
                    headers: {
                        'x-api-key': apiKey,
                        'Content-Type': 'application/json',
                        'anthropic-version': '2023-06-01'
                    },
                    body: JSON.stringify({
                        model: modelToUse,
                        max_tokens: 2000,
                        messages: [
                            {
                                role: "user",
                                content: message
                            }
                        ],
                        stream: true
                    })
                };
                
                try {
                    const response = await fetch(url, options);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const reader = response.body.getReader();
                    const decoder = new TextDecoder();
                    let responseContent = '';
                    
                    while (true) {
                        const { done, value } = await reader.read();
                        
                        if (done) {
                            break;
                        }
                        
                        const chunk = decoder.decode(value);
                        const lines = chunk.split('\n');
                        
                        for (const line of lines) {
                            if (line.startsWith('data: ')) {
                                const data = line.slice(6);
                                
                                if (data === '[DONE]') {
                                    break;
                                }
                                
                                try {
                                    const parsed = JSON.parse(data);
                                    
                                    if (parsed.type === 'content_block_delta' && parsed.delta && parsed.delta.text) {
                                        const content = parsed.delta.text;
                                        responseContent += content;
                                        
                                        // Convert markdown to HTML if marked is available
                                        if (typeof marked !== 'undefined' && marked.parse) {
                                            const htmlContent = marked.parse(responseContent);
                                            responseSpan.innerHTML = htmlContent;
                                        } else {
                                            responseSpan.textContent = responseContent;
                                        }
                                        
                                        // Scroll to bottom
                                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                                    } else if (parsed.type === 'error') {
                                        console.error('Claude API error:', parsed.error);
                                        responseSpan.textContent = 'Error: ' + parsed.error.message;
                                        break;
                                    }
                                } catch (parseError) {
                                    console.log('Non-JSON data chunk:', data);
                                }
                            }
                        }
                    }
                    
                } catch (error) {
                    console.error('Claude API call failed:', error);
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