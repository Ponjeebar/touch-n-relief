(() => {
    const script = document.currentScript;
    if (!script) return;

    document.addEventListener('DOMContentLoaded', () => {
        const role = ['guest', 'customer', 'admin', 'receptionist'].includes(script.dataset.chatbotRole)
            ? script.dataset.chatbotRole : 'guest';
        const staff = role === 'admin' || role === 'receptionist';
        const root = document.createElement('div');
        root.className = 'tnr-chat' + (staff ? ' tnr-chat-staff' : '');
        root.innerHTML = '<button type="button" class="tnr-chat-launcher" aria-label="Open help chat" aria-expanded="false"><svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-8 8 9 9 0 0 1-3.4-.7L4 20l1.2-4.3A8 8 0 1 1 20 11.5Z"/><path d="M8.5 11.5h7"/></svg><span>Chat with us</span></button><section class="tnr-chat-panel" role="dialog" aria-label="Help chat" hidden><header class="tnr-chat-header"><div><strong>Touch N Relief</strong><span>Help with services and bookings</span></div><button type="button" class="tnr-chat-close" aria-label="Close help chat">×</button></header><div class="tnr-chat-messages" role="log" aria-live="polite" aria-relevant="additions text"></div><div class="tnr-chat-prompts" aria-label="Suggested questions"></div><form class="tnr-chat-form"><label class="tnr-chat-label" for="tnr-chat-input">Ask a question</label><div class="tnr-chat-compose"><input id="tnr-chat-input" type="text" maxlength="500" autocomplete="off" placeholder="Type your question…" required><button type="submit">Send</button></div></form></section>';
        document.body.append(root);

        const launcher = root.querySelector('.tnr-chat-launcher');
        const staffMobileHelp = staff ? document.querySelector('[data-staff-chat-open]') : null;
        const launcherLabel = launcher.querySelector('span');
        const headerSubtitle = root.querySelector('.tnr-chat-header span');
        const panel = root.querySelector('.tnr-chat-panel');
        const close = root.querySelector('.tnr-chat-close');
        const messages = root.querySelector('.tnr-chat-messages');
        const prompts = root.querySelector('.tnr-chat-prompts');
        const form = root.querySelector('.tnr-chat-form');
        const input = root.querySelector('#tnr-chat-input');
        const send = form.querySelector('button');
        staffMobileHelp?.addEventListener('click', () => launcher.click());
        let busy = false;

        function addMessage(value, fromBot, actions = []) {
            const item = document.createElement('div');
            item.className = 'tnr-chat-message ' + (fromBot ? 'tnr-chat-bot' : 'tnr-chat-user');
            const body = document.createElement('p');
            body.textContent = value;
            item.append(body);
            if (fromBot && Array.isArray(actions)) {
                const links = document.createElement('div');
                links.className = 'tnr-chat-actions';
                actions.forEach((action) => {
                    try {
                        const url = new URL(action.url, window.location.origin);
                        if (url.origin !== window.location.origin) return;
                        const link = document.createElement('a');
                        link.href = url.href;
                        link.textContent = action.label;
                        links.append(link);
                    } catch (_) { /* Ignore invalid links. */ }
                });
                item.append(links);
            }
            messages.append(item);
            messages.scrollTop = messages.scrollHeight;
            return item;
        }

        async function ask(question) {
            if (busy || !question.trim()) return;
            busy = true;
            send.disabled = true;
            input.disabled = true;
            addMessage(question.trim(), false);
            const pending = addMessage('Checking…', true);
            try {
                const response = await fetch(script.dataset.chatbotUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': script.dataset.chatbotCsrf },
                    body: JSON.stringify({ message: question.trim() }),
                });
                if (!response.ok) throw new Error('Request failed');
                const answer = await response.json();
                pending.remove();
                addMessage(answer.reply || 'I could not find an answer.', true, answer.actions);
            } catch (_) {
                pending.remove();
                addMessage('I could not answer right now. Please try again shortly.', true);
            } finally {
                busy = false;
                send.disabled = false;
                input.disabled = false;
                input.focus();
            }
        }

        const suggestedQuestions = role === 'admin'
            ? ["Today's appointments", 'Manage services', 'Client records', 'Reports']
            : role === 'receptionist'
                ? ["Today's appointments", 'Manage services', 'Client records', 'Ongoing sessions']
                : role === 'customer'
                    ? ['My appointments', 'Reschedule my booking', 'Services and prices', 'Business hours']
                    : ['Services and prices', 'Business hours', 'Location', 'How do I book?'];
        if (staff) {
            launcherLabel.textContent = 'Staff help';
            headerSubtitle.textContent = role === 'admin' ? 'Administrator assistance' : 'Receptionist assistance';
        } else if (role === 'customer') {
            headerSubtitle.textContent = 'Help with your bookings';
        }

        suggestedQuestions.forEach((question) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = question;
            button.addEventListener('click', () => ask(question));
            prompts.append(button);
        });
        const welcome = role === 'admin'
            ? 'I can help you find appointments, reports, services, and client records.'
            : role === 'receptionist'
                ? 'I can help you find appointments, services, client records, and ongoing sessions.'
                : role === 'customer'
                    ? 'Hello! Ask about your appointments, booking changes, services, or your account.'
                    : 'Hello! Ask about services, prices, hours, location, or how to book.';
        addMessage(welcome, true);
        launcher.addEventListener('click', () => {
            panel.hidden = !panel.hidden;
            launcher.setAttribute('aria-expanded', String(!panel.hidden));
            staffMobileHelp?.setAttribute('aria-expanded', String(!panel.hidden));
            if (!panel.hidden) input.focus();
        });
        close.addEventListener('click', () => {
            panel.hidden = true;
            launcher.setAttribute('aria-expanded', 'false');
            staffMobileHelp?.setAttribute('aria-expanded', 'false');
            if (staffMobileHelp && window.matchMedia('(max-width: 700px)').matches) {
                staffMobileHelp.focus();
            } else {
                launcher.focus();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !panel.hidden) close.click();
        });
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const question = input.value;
            input.value = '';
            ask(question);
        });
    });
})();
