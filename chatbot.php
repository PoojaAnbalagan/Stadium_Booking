<div class="chat-widget">
    <div class="chat-button" onclick="toggleChat()">
        <i class="fas fa-robot"></i>
    </div>
    <div class="chat-window" id="chatWindow">
        <div class="chat-header">
            <h3>Arena Assistant</h3>
            <span class="close-chat" onclick="toggleChat()"><i class="fas fa-times"></i></span>
        </div>
        <div class="chat-messages" id="chatMessages">
            <div class="message bot-message">
                Hello! I'm your virtual assistant. How can I help you today?
                <br><br>
                Try asking about:<br>
                - Booking a court<br>
                - Pricing<br>
                - Sports available<br>
                - Location
            </div>
        </div>
        <div class="chat-input-area">
            <input type="text" id="userInput" placeholder="Type a message..." onkeypress="handleKeyPress(event)">
            <button onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>
</div>

<script>
    console.log("Chatbot script loaded");
    document.addEventListener('DOMContentLoaded', () => {
        console.log("Chatbot DOM ready");
    });

    function toggleChat() {
        const chatWindow = document.getElementById('chatWindow');
        chatWindow.classList.toggle('active');
        if (chatWindow.classList.contains('active')) {
            document.getElementById('userInput').focus();
        }
    }

    function handleKeyPress(e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    }

    async function sendMessage() {
        const input = document.getElementById('userInput');
        const message = input.value.trim();
        if (message === '') return;

        addMessage(message, 'user-message');
        input.value = '';

        // Show typing indicator
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message bot-message typing-indicator';
        typingDiv.innerHTML = '<i class="fas fa-ellipsis-h"></i>';
        document.getElementById('chatMessages').appendChild(typingDiv);
        document.getElementById('chatMessages').scrollTop = document.getElementById('chatMessages').scrollHeight;

        try {
            const response = await fetch('chat_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ message: message })
            });

            const data = await response.json();
            
            // Remove typing indicator
            typingDiv.remove();
            
            addMessage(data.reply, 'bot-message');
        } catch (error) {
            typingDiv.remove();
            addMessage("Sorry, I'm having trouble connecting. Please try again.", 'bot-message');
        }
    }

    function addMessage(text, className) {
        const messagesDiv = document.getElementById('chatMessages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${className}`;
        messageDiv.innerHTML = text;
        messagesDiv.appendChild(messageDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }
</script>
