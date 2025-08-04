define(['core/ajax'], function(Ajax) {
    'use strict';

    function init() {
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
                    messagesContainer.removeChild(loadingDiv);
                    
                    if (credentials.success) {
                        sendChatMessage(message, credentials.api_key);
                    } else {
                        const errorDiv = document.createElement("div");
                        errorDiv.className = "ai-message";
                        errorDiv.innerHTML = "<strong>AI Assistant:</strong> <em>Error: " + credentials.message + "</em>";
                        messagesContainer.appendChild(errorDiv);
                    }
                },
                fail: function(error) {
                    messagesContainer.removeChild(loadingDiv);
                    
                    const errorDiv = document.createElement("div");
                    errorDiv.className = "ai-message";
                    errorDiv.innerHTML = "<strong>AI Assistant:</strong> <em>Failed to get credentials: " + error.message + "</em>";
                    messagesContainer.appendChild(errorDiv);
                }
            }]);
            
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
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
        
        sendButton.addEventListener("click", sendMessage);
        
        chatInput.addEventListener("keypress", function(e) {
            if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    }

    return {
        init: init
    };
});