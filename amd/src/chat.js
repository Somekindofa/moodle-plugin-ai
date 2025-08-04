define(['core/ajax'], function(Ajax) {
    'use strict';

    function init() {
        // Wait for DOM to be ready
        document.addEventListener('DOMContentLoaded', function() {
            initChat();
        });
        
        // Also try immediately in case DOM is already ready
        if (document.readyState === 'loading') {
            // DOM hasn't finished loading yet
            document.addEventListener('DOMContentLoaded', initChat);
        } else {
            // DOM has already loaded
            initChat();
        }
    }
    
    function initChat() {
        const sendButton = document.getElementById("ai-chat-send");
        const chatInput = document.getElementById("ai-chat-input");
        const messagesContainer = document.getElementById("ai-chat-messages");
        
        // Debug: Check if elements exist
        console.log("Send button:", sendButton);
        console.log("Chat input:", chatInput);
        console.log("Messages container:", messagesContainer);
        
        // Exit if elements don't exist
        if (!sendButton || !chatInput || !messagesContainer) {
            console.error("Chat elements not found!");
            return;
        }
        
        function sendMessage() {
            const message = chatInput.value.trim();
            console.log("Sending message:", message);
            
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
            
            // Scroll to bottom
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            
            // Get user credentials via AJAX
            Ajax.call([{
                methodname: 'block_aiassistant_get_user_credentials',
                args: {},
                done: function(credentials) {
                    messagesContainer.removeChild(loadingDiv);
                    
                    if (credentials.success) {
                        sendChatMessage(message, credentials.api_key);
                    } else {
                        const errorDiv = document.createElement("div");
                        errorDiv.className = "ai-message";
                        errorDiv.innerHTML = "<strong>AI Assistant:</strong> <em>Error: " + credentials.message + "</em>";
                        messagesContainer.appendChild(errorDiv);
                    }
                    
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                },
                fail: function(error) {
                    messagesContainer.removeChild(loadingDiv);
                    
                    const errorDiv = document.createElement("div");
                    errorDiv.className = "ai-message";
                    errorDiv.innerHTML = "<strong>AI Assistant:</strong> <em>Failed to get credentials: " + error.message + "</em>";
                    messagesContainer.appendChild(errorDiv);
                    
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
            }]);
        }
        
        function sendChatMessage(message, apiKey) {
            setTimeout(function() {
                const aiMessageDiv = document.createElement("div");
                aiMessageDiv.className = "ai-message";
                aiMessageDiv.innerHTML = "<strong>AI Assistant:</strong> I received your message: \"" + message + "\". API key ready: " + apiKey.substring(0, 10) + "...";
                messagesContainer.appendChild(aiMessageDiv);
                
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }, 1000);
        }
        
        // Add event listeners
        sendButton.addEventListener("click", function(e) {
            console.log("Send button clicked!");
            e.preventDefault();
            sendMessage();
        });
        
        chatInput.addEventListener("keypress", function(e) {
            if (e.key === "Enter" && !e.shiftKey) {
                console.log("Enter pressed!");
                e.preventDefault();
                sendMessage();
            }
        });
        
        console.log("Chat initialized successfully!");
    }

    return {
        init: init
    };
});