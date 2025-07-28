<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

class block_aiassistant extends block_base {
    
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
        
        $html = '
        <div class="ai-chat-container">
            <div class="ai-chat-messages" id="ai-chat-messages">
                <div class="ai-message">
                    <strong>AI Assistant:</strong> Hello! How can I help you today?
                </div>
            </div>
            <div class="ai-chat-input">
                <textarea id="ai-chat-input" placeholder="Type your message here..." rows="3"></textarea>
                <button id="ai-chat-send" type="button">Send</button>
            </div>
        </div>
        
        <style>
        .ai-chat-container {
            max-width: 100%;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .ai-chat-messages {
            height: 200px;
            overflow-y: auto;
            padding: 10px;
            background: #f9f9f9;
            border-bottom: 1px solid #ddd;
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
        require(['core/ajax', 'core/notification'], function(Ajax, Notification) {
            document.addEventListener("DOMContentLoaded", function() {
                const sendButton = document.getElementById("ai-chat-send");
                const chatInput = document.getElementById("ai-chat-input");
                const messagesContainer = document.getElementById("ai-chat-messages");
                
                function sendMessage() {
                    const message = chatInput.value.trim();
                    if (!message) return;
                    
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
                    loadingDiv.innerHTML = "<strong>AI Assistant:</strong> <em>Getting your credentials...</em>";
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
                                const errorDiv = document.createElement("div");
                                errorDiv.className = "ai-message";
                                errorDiv.innerHTML = "<strong>AI Assistant:</strong> <em>Error: " + credentials.message + "</em>";
                                messagesContainer.appendChild(errorDiv);
                            }
                        },
                        fail: function(error) {
                            // Remove loading message
                            messagesContainer.removeChild(loadingDiv);
                            
                            const errorDiv = document.createElement("div");
                            errorDiv.className = "ai-message";
                            errorDiv.innerHTML = "<strong>AI Assistant:</strong> <em>Failed to get credentials: " + error.message + "</em>";
                            messagesContainer.appendChild(errorDiv);
                        }
                    }]);
                    
                    // Scroll to bottom
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
                
                function sendChatMessage(message, apiKey) {
                    // TODO: Implement actual chat with AI using the apiKey
                    // For now, just simulate a response
                    setTimeout(function() {
                        const aiMessageDiv = document.createElement("div");
                        aiMessageDiv.className = "ai-message";
                        aiMessageDiv.innerHTML = "<strong>AI Assistant:</strong> I received your message: \"" + message + "\". API key ready: " + apiKey.substring(0, 10) + "...";
                        messagesContainer.appendChild(aiMessageDiv);
                        
                        // Scroll to bottom
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
            });
        });
        </script>';
        
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
