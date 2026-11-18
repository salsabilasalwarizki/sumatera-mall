<div id="chatbot-container" class="fixed bottom-6 right-6 z-50">
    <button id="chatbot-toggle"
            onclick="toggleChatbot()"
            class="chat-gold-gradient w-14 h-14 rounded-full shadow-2xl hover:scale-110 transition-transform flex items-center justify-center">
        <svg class="w-6 h-6 text-amber-950" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
        </svg>
    </button>

    <div id="chatbot-window" class="hidden absolute bottom-20 right-0 w-96 h-[500px] glass-strong rounded-2xl flex flex-col overflow-hidden">
        <div class="chat-gold-gradient p-4 flex justify-between items-center">
            <div>
                <div data-i18n="chat_assistant" class="font-display font-bold text-black">SumatraMall Assistant</div>
                <div data-i18n="chat_powered_by" class="text-xs text-black/70">Didukung SPARQL</div>
            </div>
            <button onclick="toggleChatbot()" class="text-amber-950 hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div id="chatbot-messages" class="flex-1 overflow-y-auto p-4 space-y-3">
            <div class="flex gap-2">
                <div class="glass p-3 rounded-2xl rounded-tl-none max-w-[80%] text-sm">
                    <span data-i18n="chat_greeting">Halo! Saya asisten virtual SumatraMall. Coba tanya:</span><br>
                    <ul class="mt-2 space-y-1 text-xs text-gray-300">
                        <li data-i18n="chat_example_cafe">- Cari cafe di Sun Plaza</li>
                        <li data-i18n="chat_example_malls">- Mall di Medan</li>
                        <li data-i18n="chat_example_open">- Toko yang masih buka</li>
                        <li data-i18n="chat_example_top">- Toko dengan rating tertinggi</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="p-3 border-t border-white/10 flex gap-2">
            <input id="chatbot-input" type="text" data-i18n-placeholder="chat_placeholder"
                   onkeypress="if(event.key==='Enter')sendMessage()"
                   placeholder="Ketik pertanyaan..."
                   class="flex-1 glass bg-transparent p-3 rounded-xl border border-white/10 focus:border-amber-400/60 focus:outline-none text-sm">
            <button onclick="sendMessage()" class="chat-gold-gradient text-amber-950 px-4 rounded-xl hover:shadow-lg transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
            </button>
        </div>
    </div>
</div>

<script>
let sessionId = 'sess_' + Math.random().toString(36).substring(2, 15);

function toggleChatbot() {
    document.getElementById('chatbot-window').classList.toggle('hidden');
}

function addMessage(text, isUser = false) {
    const container = document.getElementById('chatbot-messages');
    const div = document.createElement('div');
    div.className = 'flex ' + (isUser ? 'justify-end' : 'justify-start');
    const bubble = document.createElement('div');
    bubble.className = `${isUser ? 'chat-gold-gradient text-amber-950 rounded-tr-none' : 'glass rounded-tl-none'} p-3 rounded-2xl max-w-[80%] text-sm whitespace-pre-wrap`;
    bubble.textContent = text;
    div.append(bubble);
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

async function sendMessage() {
    const input = document.getElementById('chatbot-input');
    const message = input.value.trim();
    if (!message) return;

    addMessage(message, true);
    input.value = '';

    const loading = document.createElement('div');
    loading.id = 'loading-indicator';
    loading.className = 'flex justify-start';
    const loadingBubble = document.createElement('div');
    loadingBubble.className = 'glass p-3 rounded-2xl text-sm';
    loadingBubble.textContent = window.sumateraTranslate('chat_thinking');
    loading.appendChild(loadingBubble);
    document.getElementById('chatbot-messages').appendChild(loading);

    try {
        const res = await fetch('{{ route("chatbot.chat") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                message,
                session_id: sessionId,
                locale: document.documentElement.lang === 'id' ? 'id' : 'en'
            })
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Layanan pencarian tidak tersedia.');
        document.getElementById('loading-indicator')?.remove();
        addMessage(data.reply);
        if (data.notice) addMessage(data.notice);
    } catch (e) {
        document.getElementById('loading-indicator')?.remove();
        addMessage(e.message || 'Maaf, terjadi kesalahan koneksi.');
    }
}
</script>