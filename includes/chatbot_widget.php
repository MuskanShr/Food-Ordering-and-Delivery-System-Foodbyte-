<?php
?>
<style>
/* ---- Floating launcher button -------------------------------------- */
.fb-bot-fab {
    position: fixed;
    right: 1.5rem;
    bottom: 1.5rem;
    width: 58px;
    height: 58px;
    border-radius: 50%;
    background: var(--orange, #E8521A);
    color: #fff;
    border: none;
    cursor: pointer;
    box-shadow: 0 8px 24px rgba(232,82,26,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    z-index: 1000;
    transition: transform 0.2s, box-shadow 0.2s;
}
.fb-bot-fab:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 12px 32px rgba(232,82,26,0.5);
}
.fb-bot-fab.is-open {
    background: var(--charcoal, #1A1A1A);
    box-shadow: 0 8px 24px rgba(0,0,0,0.3);
}

/* ---- Chat panel ---------------------------------------------------- */
.fb-bot-panel {
    position: fixed;
    right: 1.5rem;
    bottom: 5.5rem;
    width: 360px;
    max-width: calc(100vw - 2rem);
    height: 520px;
    max-height: calc(100vh - 7rem);
    background: var(--warm-white, #FFFEF9);
    border: 1px solid var(--border, #DDD8D0);
    border-radius: 16px;
    box-shadow: 0 16px 48px rgba(26,26,26,0.18);
    display: none;
    flex-direction: column;
    overflow: hidden;
    z-index: 1000;
    font-family: 'DM Sans', sans-serif;
}
.fb-bot-panel.is-open { display: flex; }

.fb-bot-header {
    background: var(--orange, #E8521A);
    color: #fff;
    padding: 0.9rem 1.1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.fb-bot-header-title {
    font-family: 'Playfair Display', serif;
    font-weight: 800;
    font-size: 1.05rem;
    line-height: 1.2;
}
.fb-bot-header-sub {
    font-size: 0.72rem;
    opacity: 0.85;
    font-weight: 400;
    margin-top: 2px;
}
.fb-bot-close {
    background: rgba(255,255,255,0.2);
    border: none;
    color: #fff;
    width: 28px; height: 28px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.1rem;
    line-height: 1;
    display: flex; align-items: center; justify-content: center;
}
.fb-bot-close:hover { background: rgba(255,255,255,0.35); }

/* ---- Message list -------------------------------------------------- */
.fb-bot-messages {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
    background: var(--cream, #FAF7F2);
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.fb-bot-msg {
    max-width: 85%;
    padding: 0.6rem 0.85rem;
    border-radius: 12px;
    font-size: 0.88rem;
    line-height: 1.4;
    word-wrap: break-word;
}
.fb-bot-msg-bot {
    align-self: flex-start;
    background: #fff;
    border: 1px solid var(--border, #DDD8D0);
    color: var(--charcoal, #1A1A1A);
    border-bottom-left-radius: 4px;
}
.fb-bot-msg-user {
    align-self: flex-end;
    background: var(--orange, #E8521A);
    color: #fff;
    border-bottom-right-radius: 4px;
}
.fb-bot-msg-typing {
    align-self: flex-start;
    background: #fff;
    border: 1px solid var(--border, #DDD8D0);
    padding: 0.7rem 1rem;
    border-radius: 12px;
    border-bottom-left-radius: 4px;
}
.fb-bot-typing-dot {
    display: inline-block;
    width: 6px; height: 6px;
    border-radius: 50%;
    background: var(--gray, #6B6B6B);
    margin: 0 1px;
    animation: fbTypingBounce 1.2s infinite;
}
.fb-bot-typing-dot:nth-child(2) { animation-delay: 0.15s; }
.fb-bot-typing-dot:nth-child(3) { animation-delay: 0.3s; }
@keyframes fbTypingBounce {
    0%,60%,100% { transform: translateY(0);   opacity: 0.4; }
    30%         { transform: translateY(-4px); opacity: 1;   }
}

/* ---- Item cards inside chat --------------------------------------- */
.fb-bot-items {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-top: 0.4rem;
    align-self: stretch;
}
.fb-bot-item {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    background: #fff;
    border: 1px solid var(--border, #DDD8D0);
    border-radius: 10px;
    padding: 0.55rem;
    transition: border-color 0.15s, transform 0.15s;
}
.fb-bot-item:hover { border-color: var(--orange, #E8521A); }
.fb-bot-item-img {
    width: 52px; height: 52px;
    border-radius: 8px;
    object-fit: cover;
    background: var(--light-gray, #E8E4DF);
    flex-shrink: 0;
}
.fb-bot-item-body { flex: 1; min-width: 0; }
.fb-bot-item-name {
    font-weight: 600;
    font-size: 0.82rem;
    color: var(--charcoal, #1A1A1A);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.fb-bot-item-price {
    font-size: 0.76rem;
    color: var(--gray, #6B6B6B);
    margin-top: 1px;
}
.fb-bot-add-btn {
    background: var(--orange, #E8521A);
    color: #fff;
    border: none;
    padding: 0.4rem 0.7rem;
    border-radius: 7px;
    font-size: 0.74rem;
    font-weight: 600;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.15s;
}
.fb-bot-add-btn:hover { background: var(--orange-dark, #C94210); }
.fb-bot-add-btn:disabled { background: #2E7D32; cursor: default; }

/* ---- Suggestion chips --------------------------------------------- */
.fb-bot-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.5rem;
}
.fb-bot-chip {
    background: var(--cream, #FAF7F2);
    border: 1px solid var(--border, #DDD8D0);
    color: var(--charcoal, #1A1A1A);
    padding: 0.3rem 0.7rem;
    border-radius: 16px;
    font-size: 0.76rem;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.15s;
}
.fb-bot-chip:hover {
    background: var(--orange, #E8521A);
    color: #fff;
    border-color: var(--orange, #E8521A);
}

/* ---- Input row ----------------------------------------------------- */
.fb-bot-input-row {
    display: flex;
    gap: 0.5rem;
    padding: 0.75rem;
    border-top: 1px solid var(--border, #DDD8D0);
    background: var(--warm-white, #FFFEF9);
}
.fb-bot-input {
    flex: 1;
    border: 1px solid var(--border, #DDD8D0);
    border-radius: 10px;
    padding: 0.55rem 0.8rem;
    font-size: 0.88rem;
    font-family: inherit;
    background: var(--cream, #FAF7F2);
    color: var(--charcoal, #1A1A1A);
    outline: none;
    transition: border-color 0.15s;
}
.fb-bot-input:focus { border-color: var(--orange, #E8521A); }
.fb-bot-send {
    background: var(--orange, #E8521A);
    color: #fff;
    border: none;
    padding: 0 1rem;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
}
.fb-bot-send:hover  { background: var(--orange-dark, #C94210); }
.fb-bot-send:disabled { opacity: 0.5; cursor: not-allowed; }

/* ---- Mobile -------------------------------------------------------- */
@media (max-width: 480px) {
    .fb-bot-panel {
        right: 0.5rem;
        bottom: 5rem;
        width: calc(100vw - 1rem);
        height: calc(100vh - 6rem);
    }
    .fb-bot-fab {
        right: 1rem; bottom: 1rem;
        width: 52px; height: 52px;
        font-size: 1.4rem;
    }
}
</style>

<!-- Launcher button -->
<button id="fb-bot-fab"
        class="fb-bot-fab"
        type="button"
        aria-label="Open food recommendation chat"
        aria-expanded="false">
    💬
</button>

<!-- Chat panel -->
<div id="fb-bot-panel"
     class="fb-bot-panel"
     role="dialog"
     aria-label="Food recommendation chat"
     aria-hidden="true">
    <div class="fb-bot-header">
        <div>
            <div class="fb-bot-header-title">FoodByte Assistant</div>
            <div class="fb-bot-header-sub">Tell me what you're craving</div>
        </div>
        <button id="fb-bot-close" class="fb-bot-close" type="button" aria-label="Close chat">×</button>
    </div>

    <div id="fb-bot-messages" class="fb-bot-messages" aria-live="polite"></div>

    <div class="fb-bot-input-row">
        <input id="fb-bot-input"
               class="fb-bot-input"
               type="text"
               placeholder="e.g. something spicy and cheap"
               maxlength="200"
               autocomplete="off">
        <button id="fb-bot-send" class="fb-bot-send" type="button">Send</button>
    </div>
</div>

<script>
(function () {
    'use strict';

    // ---- Endpoints / config (adjust BASE if your install path differs)
    const BASE          = '/foodbyte';
    const CHAT_URL      = BASE + '/chatbot.php';
    const ADD_CART_URL  = BASE + '/cart.php?add=';   // matches existing menu.php pattern
    const UPLOADS_URL   = BASE + '/uploads/';

    const STARTER_CHIPS = [
        'something spicy',
        'sweet drink',
        'cheap snack',
        'filling main course',
        'vegetarian',
        'dessert under 300'
    ];

    // ---- DOM refs
    const fab      = document.getElementById('fb-bot-fab');
    const panel    = document.getElementById('fb-bot-panel');
    const closeBtn = document.getElementById('fb-bot-close');
    const msgList  = document.getElementById('fb-bot-messages');
    const input    = document.getElementById('fb-bot-input');
    const sendBtn  = document.getElementById('fb-bot-send');

    let isOpen        = false;
    let isWaiting     = false;
    let greetingShown = false;

    // ---- Open / close
    function open() {
        isOpen = true;
        panel.classList.add('is-open');
        fab.classList.add('is-open');
        fab.textContent = '×';
        fab.setAttribute('aria-expanded', 'true');
        panel.setAttribute('aria-hidden', 'false');
        if (!greetingShown) {
            showGreeting();
            greetingShown = true;
        }
        setTimeout(() => input.focus(), 100);
    }
    function close() {
        isOpen = false;
        panel.classList.remove('is-open');
        fab.classList.remove('is-open');
        fab.textContent = '💬';
        fab.setAttribute('aria-expanded', 'false');
        panel.setAttribute('aria-hidden', 'true');
    }
    fab.addEventListener('click', () => isOpen ? close() : open());
    closeBtn.addEventListener('click', close);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isOpen) close();
    });

    // ---- Greeting message + chips
    function showGreeting() {
        const wrap = document.createElement('div');
        wrap.className = 'fb-bot-msg fb-bot-msg-bot';
        wrap.appendChild(document.createTextNode(
            "Hi! I'll suggest dishes from our menu. Try one of these or type your own:"
        ));
        const chips = document.createElement('div');
        chips.className = 'fb-bot-chips';
        STARTER_CHIPS.forEach(text => {
            const b = document.createElement('button');
            b.className = 'fb-bot-chip';
            b.type = 'button';
            b.textContent = text;
            b.addEventListener('click', () => {
                input.value = text;
                handleSend();
            });
            chips.appendChild(b);
        });
        wrap.appendChild(chips);
        msgList.appendChild(wrap);
        scrollToBottom();
    }

    // ---- Render helpers
    function addUserMsg(text) {
        const el = document.createElement('div');
        el.className = 'fb-bot-msg fb-bot-msg-user';
        el.textContent = text;       // textContent => safe vs XSS
        msgList.appendChild(el);
        scrollToBottom();
    }
    function addBotMsg(text, items) {
        const el = document.createElement('div');
        el.className = 'fb-bot-msg fb-bot-msg-bot';
        el.appendChild(document.createTextNode(text));
        if (items && items.length) {
            const list = document.createElement('div');
            list.className = 'fb-bot-items';
            items.forEach(it => list.appendChild(buildItemCard(it)));
            el.appendChild(list);
        }
        msgList.appendChild(el);
        scrollToBottom();
    }
    function buildItemCard(item) {
        const card = document.createElement('div');
        card.className = 'fb-bot-item';

        const img = document.createElement('img');
        img.className = 'fb-bot-item-img';
        img.alt = '';
        img.loading = 'lazy';
        img.src = item.image ? UPLOADS_URL + item.image
                             : 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="52" height="52"/>';
        img.onerror = () => { img.style.visibility = 'hidden'; };

        const body = document.createElement('div');
        body.className = 'fb-bot-item-body';
        const name = document.createElement('div');
        name.className = 'fb-bot-item-name';
        name.textContent = item.name;
        const price = document.createElement('div');
        price.className = 'fb-bot-item-price';
        price.textContent = 'Rs. ' + Number(item.price).toFixed(2);
        body.appendChild(name);
        body.appendChild(price);

        const btn = document.createElement('button');
        btn.className = 'fb-bot-add-btn';
        btn.type = 'button';
        btn.textContent = 'Add';
        btn.addEventListener('click', () => addToCart(item.id, btn));

        card.appendChild(img);
        card.appendChild(body);
        card.appendChild(btn);
        return card;
    }
    function showTyping() {
        const el = document.createElement('div');
        el.className = 'fb-bot-msg-typing';
        el.id = 'fb-bot-typing';
        el.innerHTML = '<span class="fb-bot-typing-dot"></span>'
                     + '<span class="fb-bot-typing-dot"></span>'
                     + '<span class="fb-bot-typing-dot"></span>';
        msgList.appendChild(el);
        scrollToBottom();
    }
    function hideTyping() {
        const t = document.getElementById('fb-bot-typing');
        if (t) t.remove();
    }
    function scrollToBottom() {
        msgList.scrollTop = msgList.scrollHeight;
    }

    // ---- Send / receive
    async function handleSend() {
        if (isWaiting) return;
        const text = input.value.trim();
        if (!text) return;

        addUserMsg(text);
        input.value = '';
        isWaiting = true;
        sendBtn.disabled = true;
        showTyping();

        try {
            const res = await fetch(CHAT_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ message: text })
            });
            const data = await res.json();
            hideTyping();
            addBotMsg(data.reply || 'Hmm, no reply.', data.items || []);
        } catch (err) {
            hideTyping();
            addBotMsg('Sorry, something went wrong. Try again?', []);
            console.error('chatbot error:', err);
        } finally {
            isWaiting = false;
            sendBtn.disabled = false;
            input.focus();
        }
    }
    sendBtn.addEventListener('click', handleSend);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleSend();
        }
    });

    // ---- Add to cart (reuses the existing /foodbyte/cart.php?add= endpoint)
    function addToCart(itemId, btn) {
        const original = btn.textContent;
        btn.disabled = true;
        btn.textContent = '...';

        fetch(ADD_CART_URL + itemId, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error('cart endpoint returned !success');
            btn.textContent = '✓ Added';
            // Update header badge if present
            const cartLink = document.querySelector('.nav-right a[href*="cart"]');
            let badge = document.querySelector('.cart-badge');
            if (badge) {
                badge.textContent = data.cartCount;
            } else if (cartLink) {
                const b = document.createElement('span');
                b.className = 'cart-badge';
                b.textContent = data.cartCount;
                cartLink.appendChild(b);
            }
            setTimeout(() => {
                btn.textContent = original;
                btn.disabled = false;
            }, 1400);
        })
        .catch(() => {
            // Fall back to a real navigation, same as menu.php does
            window.location.href = ADD_CART_URL + itemId;
        });
    }
})();
</script>
