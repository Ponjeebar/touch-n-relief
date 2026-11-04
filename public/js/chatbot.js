(() => {
    if (window.__tnrChatbotInitialized) return;

    const script = document.currentScript;
    if (!script) return;

    window.__tnrChatbotInitialized = true;

    const endpoint = script.dataset.chatbotUrl;
    const sessionEndpoint = script.dataset.chatbotSessionUrl;
    let csrfToken = script.dataset.chatbotCsrf;

    document.addEventListener('DOMContentLoaded', () => {
        const role = ['guest', 'customer', 'admin', 'receptionist'].includes(script.dataset.chatbotRole)
            ? script.dataset.chatbotRole : 'guest';
        const staff = role === 'admin' || role === 'receptionist';
        const root = document.createElement('div');
        root.className = 'tnr-chat' + (staff ? ' tnr-chat-staff' : '');
        root.innerHTML = '<button type="button" class="tnr-chat-launcher" aria-label="Open help chat" aria-expanded="false" aria-controls="tnr-chat-panel"><svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-8 8 9 9 0 0 1-3.4-.7L4 20l1.2-4.3A8 8 0 1 1 20 11.5Z"/><path d="M8.5 11.5h7"/></svg><span>Chat with us</span></button><section id="tnr-chat-panel" class="tnr-chat-panel" role="dialog" aria-modal="false" aria-labelledby="tnr-chat-title" hidden><header class="tnr-chat-header"><div><strong id="tnr-chat-title">TouchNRelief help</strong><span>Automated spa assistant</span></div><button type="button" class="tnr-chat-close" aria-label="Close help chat">&times;</button></header><div class="tnr-chat-messages" role="log" aria-live="polite" aria-relevant="additions text"></div><div class="tnr-chat-prompts" aria-label="Suggested questions"></div><form class="tnr-chat-form"><label class="tnr-chat-label" for="tnr-chat-input">Your question</label><div class="tnr-chat-compose"><input id="tnr-chat-input" type="text" maxlength="500" autocomplete="off" placeholder="Ask about a service or booking..." required><button type="submit">Send</button></div></form></section>';
        document.body.append(root);

        const launcher = root.querySelector('.tnr-chat-launcher');
        const staffHelpButtons = staff ? Array.from(document.querySelectorAll('[data-staff-chat-open]')) : [];
        const launcherLabel = launcher.querySelector('span');
        const headerSubtitle = root.querySelector('.tnr-chat-header span');
        const panel = root.querySelector('.tnr-chat-panel');
        const close = root.querySelector('.tnr-chat-close');
        const messages = root.querySelector('.tnr-chat-messages');
        const prompts = root.querySelector('.tnr-chat-prompts');
        const form = root.querySelector('.tnr-chat-form');
        const input = root.querySelector('#tnr-chat-input');
        const send = form.querySelector('button');
        staffHelpButtons.forEach(button => button.addEventListener('click', () => openPanel(button)));
        let busy = false;
        let returnFocus = launcher;

        function renderPrompts(questions) {
            prompts.replaceChildren();
            (Array.isArray(questions) ? questions : []).slice(0, 4).forEach((question) => {
                if (typeof question !== 'string' || !question.trim()) return;
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = question;
                button.disabled = busy;
                button.addEventListener('click', () => ask(question));
                prompts.append(button);
            });
            prompts.hidden = prompts.childElementCount === 0;
        }

        function addMessage(value, fromBot, actions = [], pending = false) {
            const item = document.createElement('div');
            item.className = 'tnr-chat-message ' + (fromBot ? 'tnr-chat-bot' : 'tnr-chat-user');
            item.setAttribute('aria-label', fromBot ? 'Touch N Relief' : 'You');
            if (pending) item.classList.add('is-pending');

            const body = document.createElement('p');
            body.textContent = value;
            item.append(body);

            if (fromBot && Array.isArray(actions) && actions.length > 0) {
                const links = document.createElement('div');
                links.className = 'tnr-chat-actions';
                actions.forEach((action) => {
                    try {
                        const url = new URL(action.url, window.location.origin);
                        const socialHosts = ['facebook.com', 'fb.com', 'fb.me', 'instagram.com'];
                        const officialSocial = action.external === true && url.protocol === 'https:'
                            && !url.username && !url.password && (!url.port || url.port === '443')
                            && socialHosts.some(host => url.hostname === host || url.hostname.endsWith('.' + host));
                        if (url.origin !== window.location.origin && !officialSocial) return;
                        if (!['http:', 'https:'].includes(url.protocol)) return;
                        const link = document.createElement('a');
                        link.href = url.href;
                        link.textContent = action.label;
                        if (officialSocial) {
                            link.target = '_blank';
                            link.rel = 'noopener noreferrer';
                        }
                        links.append(link);
                    } catch (_) { /* Ignore invalid links. */ }
                });
                item.append(links);
            }

            messages.append(item);
            messages.scrollTop = messages.scrollHeight;
            return item;
        }

        async function chatFetch(url, options) {
            const controller = new AbortController();
            const timeout = window.setTimeout(() => controller.abort(), 20000);
            try {
                return await fetch(url, { ...options, signal: controller.signal });
            } finally {
                window.clearTimeout(timeout);
            }
        }

        async function refreshCsrfToken() {
            if (!sessionEndpoint) return false;

            const response = await chatFetch(sessionEndpoint, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
            });
            if (!response.ok) return false;

            const payload = await response.json();
            if (!payload.token) return false;
            csrfToken = payload.token;
            return true;
        }

        async function sendQuestion(question, allowRefresh = true) {
            const response = await chatFetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ message: question }),
            });

            if (response.status === 419 && allowRefresh && await refreshCsrfToken()) {
                return sendQuestion(question, false);
            }

            let payload = {};
            try {
                payload = await response.json();
            } catch (_) { /* The status-specific message below is enough. */ }

            if (!response.ok) {
                const error = new Error(payload.message || 'The chat request failed.');
                error.status = response.status;
                error.validation = payload.errors;
                throw error;
            }

            return payload;
        }

        async function ask(question, retryMessage = null) {
            const cleanQuestion = question.trim();
            if (busy || !cleanQuestion) return;
            if (cleanQuestion.length < 2) {
                input.setCustomValidity('Enter at least two characters.');
                input.reportValidity();
                return;
            }

            busy = true;
            send.disabled = true;
            input.disabled = true;
            form.setAttribute('aria-busy', 'true');
            prompts.querySelectorAll('button').forEach(button => { button.disabled = true; });
            if (retryMessage) retryMessage.remove();
            else addMessage(cleanQuestion, false);
            const pending = addMessage('Let me check that for you…', true, [], true);

            try {
                const answer = await sendQuestion(cleanQuestion);
                pending.remove();
                addMessage(answer.reply || 'I could not find an answer.', true, answer.actions);
                renderPrompts(answer.suggestions);
            } catch (error) {
                pending.remove();
                const validationMessage = error.validation?.message?.[0];
                const failureMessage = validationMessage
                    || (!navigator.onLine
                        ? 'You appear to be offline. Check your connection and try again.'
                        : error.name === 'AbortError'
                            ? 'The reply took too long. Try sending your question again.'
                        : error.status === 429
                            ? 'You have sent several questions. Wait a minute, then try again.'
                        : error.status === 419
                            ? 'Your session changed. Refresh this page, then try again.'
                            : 'Chat could not connect right now. Please try again in a moment.');
                const failure = addMessage(failureMessage, true);
                failure.classList.add('is-error');
                const retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'tnr-chat-retry';
                retry.textContent = 'Retry question';
                retry.addEventListener('click', () => ask(cleanQuestion, failure));
                failure.append(retry);
            } finally {
                busy = false;
                send.disabled = false;
                input.disabled = false;
                form.removeAttribute('aria-busy');
                prompts.querySelectorAll('button').forEach(button => { button.disabled = false; });
                if (!panel.hidden && !window.matchMedia('(max-width: 700px)').matches) input.focus();
            }
        }

        function setPanelOpen(open) {
            panel.hidden = !open;
            root.classList.toggle('is-open', open);
            launcher.setAttribute('aria-expanded', String(open));
            staffHelpButtons.forEach(button => button.setAttribute('aria-expanded', String(open)));
        }

        function openPanel(control) {
            returnFocus = control;
            setPanelOpen(true);
            close.focus({ preventScroll: true });
            if (!window.matchMedia('(max-width: 700px)').matches) input.focus();
        }

        const suggestedQuestions = role === 'admin'
            ? ["Today's appointments", 'Manage services', 'Client records', 'Reports']
            : role === 'receptionist'
                ? ["Today's appointments", 'Manage services', 'Client records', 'Ongoing sessions']
                : role === 'customer'
                    ? ['My appointments', 'Change my therapist', 'Services and prices', 'Business hours']
                    : ['Services and prices', 'Business hours', 'Location', 'How do I book?'];

        if (staff) {
            launcherLabel.textContent = 'Staff help';
            headerSubtitle.textContent = role === 'admin' ? 'Automated administrator help' : 'Automated front desk help';
        } else if (role === 'customer') {
            headerSubtitle.textContent = 'Help with your bookings';
        }

        renderPrompts(suggestedQuestions);

        const customerName = (script.dataset.chatbotName || '').trim().split(/\s+/)[0];
        const welcome = role === 'admin'
            ? 'I can help you find appointments, reports, services, and client records.'
            : role === 'receptionist'
                ? 'I can help you find appointments, services, client records, and ongoing sessions.'
                : role === 'customer'
                    ? 'Hi' + (customerName ? ', ' + customerName : '') + '! How can I help with your visit today? You can ask about your appointments, services, or booking changes.'
                    : 'Hi! I’m the TouchNRelief virtual assistant for Bueno’s Touché. Ask about services, booking, hours, location, the owner, or official social media.';
        addMessage(welcome, true);

        launcher.addEventListener('click', () => openPanel(launcher));
        input.addEventListener('input', () => input.setCustomValidity(''));
        close.addEventListener('click', () => {
            setPanelOpen(false);
            if (returnFocus instanceof HTMLElement && returnFocus.getClientRects().length > 0) {
                returnFocus.focus({ preventScroll: true });
            } else if (staff) {
                const helpControl = staffHelpButtons.find(button => button.getClientRects().length > 0)
                    || document.querySelector('[data-staff-mobile-menu]');
                helpControl?.focus();
            } else {
                launcher.focus();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.target instanceof Element && event.target.closest('[role="dialog"][aria-modal="true"]')) return;
            if (event.key === 'Escape' && !panel.hidden && root.getClientRects().length > 0) close.click();
        });
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const question = input.value;
            if (question.trim().length < 2 || busy) {
                if (!busy) { input.setCustomValidity('Enter at least two characters.'); input.reportValidity(); }
                return;
            }
            input.value = '';
            ask(question);
        });
    });
})();
