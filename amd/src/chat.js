// This file is part of Moodle - http://moodle.org/
/**
 * @module     block_aiassistant/chat
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Initialize the chat interface for a specific container
 * @param {string} uniqueId The unique identifier for this chat instance
 */
export const init = (uniqueId) => {
    console.log('AI Assistant: Initializing chat with ID:', uniqueId);
    
    const sendButton = document.getElementById(uniqueId + '_send');
    const chatInput = document.getElementById(uniqueId + '_input');
    const messagesContainer = document.getElementById(uniqueId + '_messages');
    
    if (!sendButton || !chatInput || !messagesContainer) {
        console.error('AI Assistant: Elements not found for ID:', uniqueId);
        return;
    }
    
    /**
     * Add a message to the chat
     * @param {string} content The message content
     * @param {boolean} isUser Whether this is a user message
     * @returns {HTMLElement} The created message element
     */
    const addMessage = (content, isUser) => {
        const messageDiv = document.createElement('div');
        messageDiv.className = isUser ? 'user-message' : 'ai-message';
        messageDiv.innerHTML = content;
        messagesContainer.appendChild(messageDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        return messageDiv;
    };
    
    /**
     * Escape HTML to prevent XSS
     * @param {string} text The text to escape
     * @returns {string} The escaped text
     */
    const escapeHtml = (text) => {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };
    
    /**
     * Send a chat message
     */
    const sendMessage = () => {
        const message = chatInput.value.trim();
        if (!message) return;
        
        console.log('AI Assistant: Sending message:', message);
        
        // Disable send button
        sendButton.disabled = true;
        sendButton.textContent = 'Sending...';
        
        // Add user message
        addMessage('<strong>You:</strong> ' + escapeHtml(message), true);
        
        // Clear input
        chatInput.value = '';
        
        // Show loading state
        const loadingDiv = addMessage('<strong>AI Assistant:</strong> <em>Getting your credentials...</em>', false);
        
        // Make AJAX call to get credentials
        Ajax.call([{
            methodname: 'block_aiassistant_get_user_credentials',
            args: {},
            done: function(credentials) {
                // Remove loading message
                if (loadingDiv && loadingDiv.parentNode) {
                    loadingDiv.parentNode.removeChild(loadingDiv);
                }
                
                if (credentials.success) {
                    sendChatMessage(message, credentials.api_key);
                } else {
                    addMessage('<strong>AI Assistant:</strong> <em>Error: ' + escapeHtml(credentials.message) + '</em>', false);
                    // Re-enable send button
                    sendButton.disabled = false;
                    sendButton.textContent = 'Send';
                }
            },
            fail: function(error) {
                // Remove loading message
                if (loadingDiv && loadingDiv.parentNode) {
                    loadingDiv.parentNode.removeChild(loadingDiv);
                }
                
                addMessage('<strong>AI Assistant:</strong> <em>Failed to get credentials: ' + escapeHtml(error.message || 'Unknown error') + '</em>', false);
                
                // Re-enable send button
                sendButton.disabled = false;
                sendButton.textContent = 'Send';
            }
        }]);
    };
    
    /**
     * Send the actual chat message to AI
     * @param {string} message The user's message
     * @param {string} apiKey The API key to use
     */
    const sendChatMessage = (message, apiKey) => {
        // TODO: Implement actual chat with AI using the apiKey
        // For now, simulate a response
        setTimeout(() => {
            addMessage('<strong>AI Assistant:</strong> I received your message: "' + escapeHtml(message) + '". API key ready: ' + escapeHtml(apiKey.substring(0, 10)) + '...', false);
            
            // Re-enable send button
            sendButton.disabled = false;
            sendButton.textContent = 'Send';
        }, 1000);
    };
    
    // Add event listeners
    sendButton.addEventListener('click', sendMessage);
    
    chatInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    
    console.log('AI Assistant: Chat interface initialized successfully');
};