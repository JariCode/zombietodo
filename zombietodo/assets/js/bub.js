// ========================================
// bub.js
//
// Zombie To-Do - Bub AI Assistant
// ========================================
document.addEventListener('DOMContentLoaded', () => {
    const widget = document.querySelector('#bub-widget');
    const smallButton = document.querySelector('#bub-small');
    const overlay = document.querySelector('#bub-overlay');
    const closeButton = document.querySelector('#bub-close');
    const form = document.querySelector('#bub-form');
    const input = document.querySelector('#bub-message');
    const messages = document.querySelector('#bub-messages');

    if (!widget || !smallButton || !overlay || !closeButton || !form || !input || !messages) {
        return;
    }

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');

    if (!csrfMeta) {
        console.error('Bub: CSRF token puuttuu.');
        return;
    }

    const csrfToken = csrfMeta.getAttribute('content');
    const bubSvgs = widget.querySelectorAll('.bub-svg');
    let pageScrollPosition = 0;
    let thinkingMessage = null;

    function setMouthAnimationState(state) {
        bubSvgs.forEach(svg => {
            const animation = state === 'running' ? 'bub-speaking' : 'none';
            svg.style.setProperty('--bub-mouth-animation', animation);
        });
    }

    function openBub() {
        pageScrollPosition = window.scrollY;

        document.body.style.position = 'fixed';
        document.body.style.top = `-${pageScrollPosition}px`;
        document.body.style.left = '0';
        document.body.style.right = '0';
        document.body.style.width = '100%';

        widget.classList.add('open');
        overlay.setAttribute('aria-hidden', 'false');
        smallButton.style.display = 'none';

        setTimeout(() => {
            input.focus();
        }, 200);
    }

    function closeBub() {
        widget.classList.remove('open');
        overlay.setAttribute('aria-hidden', 'true');
        smallButton.style.display = '';

        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
        document.body.style.width = '';

        window.scrollTo(0, pageScrollPosition);

        removeThinkingMessage();
        stopSpeaking();
    }

    function addMessage(text, type) {
        const message = document.createElement('div');
        message.className = `bub-message bub-message-${type}`;
        message.textContent = text;
        messages.appendChild(message);
        messages.scrollTop = messages.scrollHeight;
    }

    function showThinkingMessage() {
        removeThinkingMessage();

        thinkingMessage = document.createElement('div');
        thinkingMessage.className = 'bub-message bub-message-bub bub-thinking';

        const zombie = document.createElement('span');
        zombie.className = 'bub-thinking-zombie';
        zombie.textContent = '🧟';

        const text = document.createElement('span');
        text.textContent = 'Bub miettii';

        const dots = document.createElement('span');
        dots.className = 'bub-thinking-dots';
        dots.textContent = '...';

        thinkingMessage.appendChild(zombie);
        thinkingMessage.appendChild(text);
        thinkingMessage.appendChild(dots);

        messages.appendChild(thinkingMessage);
        messages.scrollTop = messages.scrollHeight;
    }

    function removeThinkingMessage() {
        if (thinkingMessage) {
            thinkingMessage.remove();
            thinkingMessage = null;
        }
    }

    function setLoading(loading) {
        const button = form.querySelector('button[type="submit"]');

        if (!button) {
            return;
        }

        button.disabled = loading;
        button.classList.toggle('is-loading', loading);
    }

    function startSpeaking() {
        setMouthAnimationState('running');
    }

    function stopSpeaking() {
        setMouthAnimationState('paused');
    }

    async function loadHistory() {
        try {
            const response = await fetch('app/bub.php', {
                method: 'GET',
                headers: {
                    'X-CSRF-Token': csrfToken
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(
                    data.error || 'Bubin keskusteluhistoriaa ei voitu ladata.'
                );
            }

            messages.innerHTML = '';

            if (Array.isArray(data.messages) && data.messages.length > 0) {
                data.messages.forEach(item => {
                    const type = item.role === 'assistant'
                        ? 'bub'
                        : 'user';

                    addMessage(item.message, type);
                });

                messages.scrollTop = messages.scrollHeight;
                return;
            }

            addMessage(
                'Hei. Minä olen Bub. Mitä haluat tietää?',
                'bub'
            );
        } catch (error) {
            console.error('Bub history:', error);

            if (!messages.children.length) {
                addMessage(
                    'Hei. Minä olen Bub. Mitä haluat tietää?',
                    'bub'
                );
            }
        }
    }

    smallButton.addEventListener('click', () => {
        openBub();
    });

    closeButton.addEventListener('click', () => {
        closeBub();
    });

    overlay.addEventListener('click', event => {
        if (event.target === overlay) {
            closeBub();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && widget.classList.contains('open')) {
            closeBub();
        }
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();

        const message = input.value.trim();

        if (message === '') {
            return;
        }

        addMessage(message, 'user');
        input.value = '';
        setLoading(true);
        showThinkingMessage();

        try {
            const response = await fetch('app/bub.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken
                },
                credentials: 'same-origin',
                body: new URLSearchParams({
                    message,
                    csrf_token: csrfToken
                })
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(
                    data.error || 'Bub ei vastannut.'
                );
            }

            removeThinkingMessage();
            startSpeaking();
            addMessage(data.reply, 'bub');
        } catch (error) {
            console.error('Bub:', error);

            removeThinkingMessage();

            addMessage(
                error.message || 'Bub menetti yhteyden aivoihinsa.',
                'error'
            );
        } finally {
            setTimeout(() => {
                stopSpeaking();
            }, 1000);

            setLoading(false);
            input.focus();
        }
    });

    loadHistory();
});