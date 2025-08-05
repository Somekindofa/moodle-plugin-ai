<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

class block_aiassistant extends block_base {
    /**
     * Set block to have configuration settings
     */
    public function has_config() {
        return true;
    }
    
    public function init() {
        $this->title = get_string('pluginname', 'block_aiassistant');
    }
    
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }
        
        $this->content = new stdClass;
        $this->content->text = $this->get_chat_interface(); 
        $this->content->footer = '';
        
        return $this->content;
    }
    
    private function get_chat_interface() {
        global $USER, $COURSE;
        $html = "
        <div class=\"ai-chat-container\" id=\"ai-chat-container\">
            <div class=\"ai-chat-messages\" id=\"ai-chat-messages\">
                <div class=\"ai-message\">
                    <strong>AI Assistant:</strong> Hello! How can I help you today?
                </div>
            </div>
            <div class=\"ai-chat-input\">
                <textarea id=\"ai-chat-input\" placeholder=\"Type your message here...\" rows=\"3\"></textarea>
                <button id=\"ai-chat-send\" type=\"button\">Send</button>
            </div>
            <div class=\"ai-resize-handle\" id=\"ai-resize-handle\" title=\"Drag to resize\"></div>
        </div>
        
        <style>
        .ai-chat-container {
            position: relative;
            width: 400px;
            min-width: 300px;
            max-width: 800px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            margin: 0 auto;
        }
        
        .ai-chat-messages {
            height: 200px;
            min-height: 150px;
            max-height: 600px;
            overflow-y: auto;
            padding: 10px;
            background: #f9f9f9;
            border-bottom: 1px solid #ddd;
        }
        
        .ai-resize-handle {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 20px;
            height: 20px;
            cursor: se-resize;
            background: linear-gradient(135deg, transparent 0%, transparent 30%, #999 30%, #999 40%, transparent 40%, transparent 50%, #999 50%, #999 60%, transparent 60%, transparent 70%, #999 70%, #999 80%, transparent 80%);
            border-top-left-radius: 4px;
            opacity: 0.7;
            transition: opacity 0.2s ease;
        }
        
        .ai-resize-handle:hover {
            opacity: 1;
        }
        
        .ai-chat-container.resizing {
            user-select: none;
        }
        
        .ai-message, .user-message {
            margin-bottom: 10px;
            padding: 8px;
            border-radius: 4px;
        }
        
        .ai-message {
            background: #e3f2fd;
        }
        
        .user-message {
            background: #f3e5f5;
            text-align: right;
        }
        
        .ai-chat-input {
            padding: 10px;
            display: flex;
            gap: 5px;
        }
        
        .ai-chat-input textarea {
            flex: 1;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 8px;
            resize: vertical;
        }
        
        .ai-chat-input button {
            padding: 8px 16px;
            background: #007cba;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .ai-chat-input button:hover {
            background: #005a87;
        }
        </style>
        
        <script>
        
        document.addEventListener(\"DOMContentLoaded\", function() {
            require(['core/ajax', 'core/notification'], function(Ajax) {
                const sendButton = document.getElementById(\"ai-chat-send\");
                const chatInput = document.getElementById(\"ai-chat-input\");
                const messagesContainer = document.getElementById(\"ai-chat-messages\");
                const chatContainer = document.getElementById(\"ai-chat-container\");
                const resizeHandle = document.getElementById(\"ai-resize-handle\");
                
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
                
                resizeHandle.addEventListener('mousedown', function(e) {
                    isResizing = true;
                    chatContainer.classList.add('resizing');
                    
                    startX = e.clientX;
                    startY = e.clientY;
                    startWidth = parseInt(window.getComputedStyle(chatContainer).width, 10);
                    startHeight = parseInt(window.getComputedStyle(messagesContainer).height, 10);
                    
                    e.preventDefault();
                });
                
                document.addEventListener('mousemove', function(e) {
                    if (!isResizing) return;
                    
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
                
                function sendMessage() {
                    const message = chatInput.value.trim();
                    if (!message) return;
                    
                    // Add user message
                    const userMessageDiv = document.createElement(\"div\");
                    userMessageDiv.className = \"user-message\";
                    userMessageDiv.innerHTML = \"<strong>You:</strong> \" + message;
                    messagesContainer.appendChild(userMessageDiv);
                    
                    // Clear input
                    chatInput.value = \"\";
                    
                    // Show loading state
                    const loadingDiv = document.createElement(\"div\");
                    loadingDiv.className = \"ai-message\";
                    loadingDiv.innerHTML = \"<strong>AI Assistant:</strong> <em>Getting your credentials...</em>\";
                    messagesContainer.appendChild(loadingDiv);
                    
                    // Get user credentials via AJAX
                    Ajax.call([{
                        methodname: 'block_aiassistant_get_user_credentials',
                        args: {},
                        done: function(credentials) {
                            // Remove loading message
                            messagesContainer.removeChild(loadingDiv);
                            
                            if (credentials.success) {
                                // Now make the actual chat request using those credentials
                                sendChatMessage(message, credentials.api_key);
                            } else {
                                const errorDiv = document.createElement(\"div\");
                                errorDiv.className = \"ai-message\";
                                errorDiv.innerHTML = \"<strong>AI Assistant:</strong> <em>Error: \" + credentials.message + \"</em>\";
                                messagesContainer.appendChild(errorDiv);
                            }
                        },
                        fail: function(error) {
                            // Remove loading message
                            messagesContainer.removeChild(loadingDiv);
                            
                            const errorDiv = document.createElement(\"div\");
                            errorDiv.className = \"ai-message\";
                            errorDiv.innerHTML = \"<strong>AI Assistant:</strong> <em>Failed to get credentials: \" + error.message + \"</em>\";
                            messagesContainer.appendChild(errorDiv);
                        }
                    }]);
                    
                    // Scroll to bottom
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
                
                async function sendChatMessage(message, apiKey) {
                    setTimeout(async function() {
                        const aiMessageDiv = document.createElement(\"div\");
                        aiMessageDiv.className = \"ai-message\";
                        aiMessageDiv.innerHTML = \"<strong>AI Assistant:</strong> <span class='response-text'>Thinking...</span>\";
                        messagesContainer.appendChild(aiMessageDiv);

                        const responseSpan = aiMessageDiv.querySelector('.response-text');

                        const url = 'https://api.fireworks.ai/inference/v1/chat/completions';
                        const options = {
                            method: 'POST',
                            headers: {
                                Authorization: 'Bearer fw_3ZHpzWpsvNQMVRgf772VmtHs',
                                'Content-Type': 'application/json'
                            },
                            body: '{\"max_tokens\":2000,\"prompt_truncate_len\":1500,\"temperature\":1,\"top_p\":1,\"frequency_penalty\":0,\"perf_metrics_in_response\":false,\"presence_penalty\":0,\"repetition_penalty\":1,\"mirostat_lr\":0.1,\"mirostat_target\":1.5,\"n\":1,\"ignore_eos\":false,\"response_format\":{\"type\":\"text\"},\"stream\":false,\"messages\":[{\"role\":\"user\",\"content\":\"Hello there!\"}],\"model\":\"accounts/fireworks/models/qwen3-235b-a22b-thinking-2507\"}'
                        };

                        try {
                            const response = await fetch(url, options);
                            const data = await response.json();
                            console.log(data);
                            
                            if (data.choices && data.choices[0] && data.choices[0].message) {
                                let content = data.choices[0].message.content;
                                
                                // Extract content after thinking tags if they exist (flexible for different tag formats)
                                const thinkPatterns = ['</think>', '</thinking>'];
                                for (const pattern of thinkPatterns) {
                                    const endIndex = content.indexOf(pattern);
                                    if (endIndex !== -1) {
                                        content = content.substring(endIndex + pattern.length).trim();
                                        break;
                                    }
                                }
                                
                                responseSpan.textContent = content;
                            } else {
                                responseSpan.textContent = 'Sorry, I could not process your request.';
                            }
                        } catch (error) {
                            console.error(error);
                            responseSpan.textContent = 'Sorry, there was an error processing your request.';
                        }
                            
                        // Scroll to bottom
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }, 1000);
                }
                
                sendButton.addEventListener(\"click\", sendMessage);
                
                chatInput.addEventListener(\"keypress\", function(e) {
                    if (e.key === \"Enter\" && !e.shiftKey) {
                        e.preventDefault();
                        sendMessage();
                    }
                });
            });
        });
        </script>";
        
        return $html;
    }
    
    /**
     * Defines the page formats where this block can be displayed.
     *
     * Available formats include:
     * - 'all' => true: Display on all page types
     * - 'site' => true: Site front page
     * - 'course' => true: Course pages
     * - 'course-category' => true: Course category pages
     * - 'my' => true: Dashboard/My Moodle page
     * - 'user' => true: User profile pages
     * - 'mod' => true: Activity/module pages
     * - 'tag' => true: Tag pages
     * - 'admin' => true: Admin pages
     * - 'blog' => true: Blog pages
     * - 'calendar' => true: Calendar pages
     *
     * @return array Array of applicable formats with boolean values
     */
    public function applicable_formats() {
        return array('all' => true); # if we only want it to be shown on page types "course" and "dashboard" then use: return array('course' => true, 'my' => true);
    }
    
    /**
     * Determines whether multiple instances of this block can exist on a single page.
     *
     * This method controls the block instance multiplicity behavior. When returning false,
     * only one instance of this block type can be added to any given page, preventing
     * duplicate block placements.
     *
     * @return bool False to allow only one instance per page, true to allow multiple instances
     */
    public function instance_allow_multiple() {
        return false;
    }
}