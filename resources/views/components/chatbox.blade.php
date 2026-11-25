<div id="chatbot-container" class="fixed bottom-6 right-6 z-50">
    <button id="chatbot-toggle"
            onclick="toggleChatbot()"
            aria-controls="chatbot-window"
            aria-expanded="false"
            aria-label="Buka asisten SumatraMall"
            class="chat-gold-gradient w-14 h-14 rounded-full shadow-2xl hover:scale-110 transition-transform flex items-center justify-center">
        <svg class="w-6 h-6 text-amber-950" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
        </svg>
    </button>

    <section id="chatbot-window" class="chatbot-window hidden glass-strong rounded-2xl flex flex-col overflow-hidden"
             role="dialog" aria-labelledby="chatbot-title" aria-modal="false">
        <div class="chat-gold-gradient p-4 flex justify-between items-center">
            <div>
                <div id="chatbot-title" data-i18n="chat_assistant" class="font-display font-bold text-black">Asisten SumatraMall</div>
                <div data-i18n="chat_powered_by" class="text-xs text-black/70">Mencari langsung dari data mall dan toko</div>
            </div>
            <button type="button" onclick="toggleChatbot()" aria-label="Tutup chat" data-i18n-aria-label="chat_close"
                    class="text-amber-950 hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div id="chatbot-messages" class="flex-1 overflow-y-auto p-4 space-y-3">
            <div class="flex gap-2">
                <div class="glass p-3 rounded-2xl rounded-tl-none max-w-[80%] text-sm">
                    <span data-i18n="chat_greeting">Hai! Saya bisa bantu mencari toko atau mall dari data SumatraMall. Pilih contoh pertanyaan, atau tulis kebutuhan Anda.</span>
                </div>
            </div>
            <div class="chatbot-prompts" role="group" aria-label="Contoh pertanyaan">
                <button type="button" data-chat-prompt="Kafe di Sun Plaza" data-i18n="chat_example_cafe">Kafe di Sun Plaza</button>
                <button type="button" data-chat-prompt="Mall paling dekat dengan saya" data-i18n="chat_example_nearest">Mall terdekat dari saya</button>
                <button type="button" data-chat-prompt="Toko fashion di Medan" data-i18n="chat_example_category">Toko fashion di Medan</button>
                <button type="button" data-chat-prompt="Alamat dan jam buka Sun Plaza" data-i18n="chat_example_info">Alamat dan jam buka Sun Plaza</button>
            </div>
        </div>

        <form id="chatbot-form" class="p-3 border-t border-white/10 flex gap-2">
            <label for="chatbot-input" class="sr-only" data-i18n="chat_placeholder">Tulis pertanyaan Anda</label>
            <input id="chatbot-input" type="text" data-i18n-placeholder="chat_placeholder"
                   placeholder="Misalnya: toko apa saja di lantai LG Sun Plaza?"
                   class="flex-1 glass bg-transparent p-3 rounded-xl border border-white/10 focus:border-amber-400/60 focus:outline-none text-sm">
            <button id="chatbot-send" type="submit" class="chat-gold-gradient text-amber-950 px-4 rounded-xl hover:shadow-lg transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
            </button>
        </form>
    </section>
</div>

<script>
let sessionId = 'sess_' + Math.random().toString(36).substring(2, 15);

function toggleChatbot() {
    const panel = document.getElementById('chatbot-window');
    const toggle = document.getElementById('chatbot-toggle');
    const isOpening = panel.classList.contains('hidden');
    panel.classList.toggle('hidden', !isOpening);
    toggle.setAttribute('aria-expanded', String(isOpening));
    if (isOpening) document.getElementById('chatbot-input').focus();
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
    if (!message || input.disabled) return;

    addMessage(message, true);
    input.value = '';
    input.disabled = true;
    document.getElementById('chatbot-send').disabled = true;

    const loading = document.createElement('div');
    loading.id = 'loading-indicator';
    loading.className = 'flex justify-start';
    const loadingBubble = document.createElement('div');
    loadingBubble.className = 'glass p-3 rounded-2xl text-sm';
    loadingBubble.textContent = window.sumateraTranslate('chat_thinking');
    loading.appendChild(loadingBubble);
    document.getElementById('chatbot-messages').appendChild(loading);

    try {
        const coordinates = {};
        const asksForNearestMall = /\b(?:mall.*(?:terdekat|dekat.*saya)|terdekat.*mall|nearest mall|mall.*near me|closest mall)\b/i.test(message);
        if (asksForNearestMall && navigator.geolocation) {
            try {
                const position = await new Promise((resolve, reject) => {
                    navigator.geolocation.getCurrentPosition(resolve, reject, {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 60000
                    });
                });
                coordinates.latitude = position.coords.latitude;
                coordinates.longitude = position.coords.longitude;
            } catch {
                // The server returns a clear location-permission prompt when coordinates are unavailable.
            }
        }

        const res = await fetch('{{ route("chatbot.chat") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                message,
                session_id: sessionId,
                locale: document.documentElement.lang === 'id' ? 'id' : 'en',
                ...coordinates
            })
        });
        const responseText = await res.text();
        let data;
        try {
            data = JSON.parse(responseText);
        } catch {
            throw new Error(window.sumateraTranslate('chat_response_invalid'));
        }
        if (!res.ok) throw new Error(data.message || window.sumateraTranslate('chat_response_invalid'));
        if (typeof data.reply !== 'string' || data.reply.trim() === '') {
            throw new Error(window.sumateraTranslate('chat_response_invalid'));
        }
        document.getElementById('loading-indicator')?.remove();
        addMessage(data.reply);
    } catch (e) {
        document.getElementById('loading-indicator')?.remove();
        addMessage(e.message || 'Maaf, terjadi kesalahan koneksi.');
    } finally {
        input.disabled = false;
        document.getElementById('chatbot-send').disabled = false;
        input.focus();
    }
}

document.getElementById('chatbot-form').addEventListener('submit', event => {
    event.preventDefault();
    sendMessage();
});
document.querySelectorAll('[data-chat-prompt]').forEach(button => button.addEventListener('click', () => {
    const input = document.getElementById('chatbot-input');
    input.value = button.dataset.chatPrompt;
    sendMessage();
}));
</script>