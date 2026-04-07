<div class="chat-widget">
    <div class="chat-button" onclick="toggleChat()" aria-label="Open Chat" role="button" tabindex="0" onkeypress="if(event.key==='Enter') toggleChat()">
        <i class="fas fa-robot"></i>
    </div>
    <div class="chat-window" id="chatWindow" role="dialog" aria-label="AI Assistant Chat">
        <div class="chat-header">
            <h3>AI Assistant</h3>
            <span class="close-chat" onclick="toggleChat()" aria-label="Close Chat" role="button" tabindex="0" onkeypress="if(event.key==='Enter') toggleChat()">
                <i class="fas fa-times"></i>
            </span>
        </div>
        <div class="chat-messages" id="chatMessages" aria-live="polite">
            <!-- Messages will be dynamically injected here -->
        </div>
        <div class="chat-input-area">
            <input type="text" id="userInput" placeholder="Type a message..." onkeypress="handleKeyPress(event)" aria-label="Message Input">
            <button id="sendBtn" onclick="sendMessage()" aria-label="Send Message"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>
</div>

<script>
    const INITIAL_MESSAGE = "Hello! I'm your virtual assistant. How can I help you today?\n\nTry asking about:\n- Booking a court\n- Pricing\n- Sports available\n- Location";
    
    // Maintain chat history locally
    let chatHistory = [];

    document.addEventListener('DOMContentLoaded', () => {
        loadChatHistory();

        // Allow users to close the modal smoothly via the Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const window = document.getElementById('chatWindow');
                if (window.classList.contains('active')) {
                    toggleChat();
                }
            }
        });
    });

    function toggleChat() {
        const chatWindow = document.getElementById('chatWindow');
        chatWindow.classList.toggle('active');
        
        // Save window state so it stays open or closed during navigation
        sessionStorage.setItem('chatActive_Stadium', chatWindow.classList.contains('active') ? 'true' : 'false');
        
        if (chatWindow.classList.contains('active')) {
            document.getElementById('userInput').focus();
            
            // Auto scroll to bottom when opening
            const messagesDiv = document.getElementById('chatMessages');
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }
    }

    function handleKeyPress(e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    }

    async function sendMessage() {
        const input = document.getElementById('userInput');
        const btn = document.getElementById('sendBtn');
        const message = input.value.trim();
        
        if (message === '') return;

        addMessage(message, 'user-message');
        saveToHistory(message, 'user-message');
        input.value = '';

        // Prevent request spamming (lock input and button)
        input.disabled = true;
        btn.disabled = true;

        // Show typing indicator
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message bot-message typing-indicator';
        typingDiv.innerHTML = '<i class="fas fa-ellipsis-h"></i>';
        document.getElementById('chatMessages').appendChild(typingDiv);
        document.getElementById('chatMessages').scrollTop = document.getElementById('chatMessages').scrollHeight;

        try {
            // Include history so backend chat_api.php can use it for context if supported
            const response = await fetch('chat_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    message: message,
                    history: chatHistory.slice(0, -1) // Provide context excluding current prompt
                })
            });

            // Handle HTTP errors cleanly
            if (!response.ok) {
                throw new Error(`Server returned HTTP ${response.status}`);
            }

            const data = await response.json();
            
            typingDiv.remove();
            
            addMessage(data.reply, 'bot-message');
            saveToHistory(data.reply, 'bot-message');
        } catch (error) {
            typingDiv.remove();
            console.error("Chat API Error:", error);
            const errorMsg = "Sorry, I'm having trouble connecting to the server. Please check your connection and try again.";
            addMessage(errorMsg, 'bot-message');
            // Deliberately not saving error messages to history so they don't clog up sessionStorage
        } finally {
            // Release the lock
            input.disabled = false;
            btn.disabled = false;
            
            // Only refocus if chat is still active
            if (document.getElementById('chatWindow').classList.contains('active')) {
                input.focus();
            }
        }
    }

    function addMessage(text, className) {
        const messagesDiv = document.getElementById('chatMessages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${className}`;
        
        // Prevents XSS vulnerabilities completely while allowing \n to format cleanly
        messageDiv.textContent = text;
        messageDiv.style.whiteSpace = 'pre-wrap';
        
        messagesDiv.appendChild(messageDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }
    
    function saveToHistory(text, className) {
        chatHistory.push({ text, className });
        sessionStorage.setItem('chatHistory_Stadium', JSON.stringify(chatHistory));
    }
    
    function loadChatHistory() {
        const savedHistory = sessionStorage.getItem('chatHistory_Stadium');
        const messagesDiv = document.getElementById('chatMessages');
        
        // Clear hardcoded HTML from previous implementations if it still exists
        messagesDiv.innerHTML = '';
        
        if (savedHistory) {
            try {
                chatHistory = JSON.parse(savedHistory);
                chatHistory.forEach(msg => addMessage(msg.text, msg.className));
            } catch (e) {
                console.error("Failed to parse chat history:", e);
                sessionStorage.removeItem('chatHistory_Stadium');
                chatHistory = [];
                addMessage(INITIAL_MESSAGE, 'bot-message');
                saveToHistory(INITIAL_MESSAGE, 'bot-message');
            }
        } else {
            addMessage(INITIAL_MESSAGE, 'bot-message');
            saveToHistory(INITIAL_MESSAGE, 'bot-message');
        }
        
        // Restore window state
        const wasActive = sessionStorage.getItem('chatActive_Stadium');
        if (wasActive === 'true') {
            document.getElementById('chatWindow').classList.add('active');
        }
    }
</script>
