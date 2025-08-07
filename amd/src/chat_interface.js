define(['core/ajax'], function(Ajax) {
    return {
        init: function() {
            console.log("Minimal AMD test works!");
            alert("AMD module loaded successfully!");
        }
    };
});


// define(['core/ajax'], function(Ajax) {
//     return {
//         init: function() {
//             document.addEventListener("DOMContentLoaded", function() {
//                 const sendButton = document.getElementById("ai-chat-send");
//                 const chatInput = document.getElementById("ai-chat-input");
//                 const messagesContainer = document.getElementById("ai-chat-messages");
//                 const chatContainer = document.getElementById("ai-chat-container");
//                 const resizeHandle = document.getElementById("ai-resize-handle");
                
//                 // Load saved dimensions from localStorage
//                 const savedWidth = localStorage.getItem('ai-chat-width');
//                 const savedHeight = localStorage.getItem('ai-chat-height');
                
//                 if (savedWidth) {
//                     chatContainer.style.width = savedWidth + 'px';
//                 }
//                 if (savedHeight) {
//                     messagesContainer.style.height = savedHeight + 'px';
//                 }
                
//                 // Resize functionality
//                 let isResizing = false;
//                 let startX, startY, startWidth, startHeight;
                
//                 resizeHandle.addEventListener('mousedown', function(e) {
//                     isResizing = true;
//                     chatContainer.classList.add('resizing');
                    
//                     startX = e.clientX;
//                     startY = e.clientY;
//                     startWidth = parseInt(window.getComputedStyle(chatContainer).width, 10);
//                     startHeight = parseInt(window.getComputedStyle(messagesContainer).height, 10);
                    
//                     e.preventDefault();
//                 });
                
//                 document.addEventListener('mousemove', function(e) {
//                     if (!isResizing) return;
                    
//                     const newWidth = Math.max(300, Math.min(800, startWidth + (e.clientX - startX)));
//                     const newHeight = Math.max(150, Math.min(600, startHeight + (e.clientY - startY)));
                    
//                     chatContainer.style.width = newWidth + 'px';
//                     messagesContainer.style.height = newHeight + 'px';
                    
//                     // Save to localStorage
//                     localStorage.setItem('ai-chat-width', newWidth);
//                     localStorage.setItem('ai-chat-height', newHeight);
//                 });
                
//                 document.addEventListener('mouseup', function() {
//                     if (isResizing) {
//                         isResizing = false;
//                         chatContainer.classList.remove('resizing');
//                     }
//                 });
                
//                 function sendMessage() {
//                     const message = chatInput.value.trim();
//                     if (!message) return;
                    
//                     // Add user message
//                     const userMessageDiv = document.createElement("div");
//                     userMessageDiv.className = "user-message";
//                     userMessageDiv.innerHTML = "<strong>You:</strong> " + message;
//                     messagesContainer.appendChild(userMessageDiv);
                    
//                     // Clear input
//                     chatInput.value = "";
                    
//                     // Show loading state
//                     const loadingDiv = document.createElement("div");
//                     loadingDiv.className = "ai-message";
//                     loadingDiv.innerHTML = "<strong>AI Assistant:</strong> <em>Getting your credentials...</em>";
//                     messagesContainer.appendChild(loadingDiv);
                    
//                     // Get user credentials via AJAX
//                     Ajax.call([{
//                         methodname: 'block_aiassistant_get_user_credentials',
//                         args: {},
//                         done: function(credentials) {
//                             // Remove loading message
//                             messagesContainer.removeChild(loadingDiv);
                            
//                             if (credentials.success) {
//                                 // Now make the actual chat request using those credentials
//                                 sendChatMessage(message, credentials.api_key);
//                             } else {
//                                 const errorDiv = document.createElement("div");
//                                 errorDiv.className = "ai-message";
//                                 errorDiv.innerHTML = "<strong>AI Assistant:</strong> <em>Error: " + credentials.message + "</em>";
//                                 messagesContainer.appendChild(errorDiv);
//                             }
//                         },
//                         fail: function(error) {
//                             // Remove loading message
//                             messagesContainer.removeChild(loadingDiv);
                            
//                             const errorDiv = document.createElement("div");
//                             errorDiv.className = "ai-message";
//                             errorDiv.innerHTML = "<strong>AI Assistant:</strong> <em>Failed to get credentials: " + error.message + "</em>";
//                             messagesContainer.appendChild(errorDiv);
//                         }
//                     }]);
                    
//                     // Scroll to bottom
//                     messagesContainer.scrollTop = messagesContainer.scrollHeight;
//                 }
                
//                 async function sendChatMessage(message, apiKey) {
//                     setTimeout(async function() {
//                         const aiMessageDiv = document.createElement("div");
//                         aiMessageDiv.className = "ai-message";
//                         aiMessageDiv.innerHTML = "<strong>AI Assistant:</strong> <span class='response-text'>Thinking...</span>";
//                         messagesContainer.appendChild(aiMessageDiv);

//                         const responseSpan = aiMessageDiv.querySelector('.response-text');

//                         const url = 'https://api.fireworks.ai/inference/v1/chat/completions';
//                         const options = {
//                             method: 'POST',
//                             headers: {
//                                 Authorization: 'Bearer ' + apiKey,
//                                 'Content-Type': 'application/json'
//                             },
//                             body: JSON.stringify({
//                                 "max_tokens": 2000,
//                                 "prompt_truncate_len": 1500,
//                                 "temperature": 1,
//                                 "top_p": 1,
//                                 "frequency_penalty": 0,
//                                 "perf_metrics_in_response": false,
//                                 "presence_penalty": 0,
//                                 "repetition_penalty": 1,
//                                 "mirostat_lr": 0.1,
//                                 "mirostat_target": 1.5,
//                                 "n": 1,
//                                 "ignore_eos": false,
//                                 "response_format": {"type": "text"},
//                                 "stream": false,
//                                 "messages": [{"role": "user", "content": message}],
//                                 "model": "accounts/fireworks/models/qwen3-235b-a22b-thinking-2507"
//                             })
//                         };

//                         try {
//                             const response = await fetch(url, options);
//                             const data = await response.json();
//                             console.log(data);
                            
//                             if (data.choices && data.choices[0] && data.choices[0].message) {
//                                 let content = data.choices[0].message.content;
                                
//                                 // Extract content after thinking tags if they exist
//                                 const thinkPatterns = ['</think>', '</thinking>'];
//                                 for (const pattern of thinkPatterns) {
//                                     const endIndex = content.indexOf(pattern);
//                                     if (endIndex !== -1) {
//                                         content = content.substring(endIndex + pattern.length).trim();
//                                         break;
//                                     }
//                                 }
//                                 // Convert markdown to HTML
//                                 const htmlContent = marked.parse(content);
//                                 responseSpan.innerHTML = htmlContent;
//                             } else {
//                                 responseSpan.textContent = 'Sorry, I could not process your request.';
//                             }
//                         } catch (error) {
//                             console.error(error);
//                             responseSpan.textContent = 'Sorry, there was an error processing your request.';
//                         }
                            
//                         // Scroll to bottom
//                         messagesContainer.scrollTop = messagesContainer.scrollHeight;
//                     }, 1000);
//                 }
                
//                 sendButton.addEventListener("click", sendMessage);
                
//                 chatInput.addEventListener("keypress", function(e) {
//                     if (e.key === "Enter" && !e.shiftKey) {
//                         e.preventDefault();
//                         sendMessage();
//                     }
//                 });
//             });
//         }
//     };
// });