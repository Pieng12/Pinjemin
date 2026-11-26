import { matchQuestion } from './assistant-matcher';
import { initializeTutorial } from './tutorial';
import { initializeGuides } from './assistant-guides';

export function initializeAssistant() {
    const root = document.querySelector('[data-assistant]');
    const dialog = root?.querySelector('[data-assistant-dialog]');
    if (!dialog || typeof dialog.showModal !== 'function') return;

    let config;
    try { config = JSON.parse(root.querySelector('[data-assistant-config]').textContent); } catch { return; }
    const topics = new Map(config.topics.map(topic => [topic.id, topic]));
    const launcherArea = root.querySelector('[data-assistant-launch-area]');
    const launcher = root.querySelector('[data-assistant-launcher]');
    const conversation = root.querySelector('[data-assistant-conversation]');
    const input = root.querySelector('[data-assistant-input]');
    const form = root.querySelector('[data-assistant-form]');
    const status = root.querySelector('[data-assistant-status]');
    const error = root.querySelector('[data-assistant-error]');
    const greeting = root.querySelector('[data-assistant-greeting]');
    const newMessage = root.querySelector('[data-assistant-new-message]');
    const topicBrowser = root.querySelector('[data-assistant-topics]');
    const mobile = window.matchMedia('(max-width: 639px)');
    const historyKey = 'pinjemin.assistant.history';
    const greetingKey = 'pinjemin.assistant.greeted';
    const lifetime = 30 * 60 * 1000;
    let history = [];
    let historyExpiresAt = 0;
    let scrollStyles = null;
    let composing = false;
    let greetingTimer = null;
    let statusTimer = null;
    let frame = null;
    let activeView = 'guides';
    let guides = null;

    const storage = {
        get(key) { try { return sessionStorage.getItem(key); } catch { return null; } },
        set(key, value) { try { sessionStorage.setItem(key, value); } catch {} },
        remove(key) { try { sessionStorage.removeItem(key); } catch {} },
    };
    const announce = message => {
        clearTimeout(statusTimer);
        status.textContent = '';
        statusTimer = setTimeout(() => { status.textContent = message; }, 30);
    };
    const selectView = view => {
        const changed = activeView !== view;
        activeView = view;
        root.querySelectorAll('[data-assistant-tab]').forEach(tab => {
            const selected = tab.dataset.assistantTab === view;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
        });
        root.querySelector('[data-assistant-guides]').hidden = view !== 'guides';
        root.querySelector('[data-assistant-chat]').hidden = view !== 'chat';
        newMessage.hidden = true;
        if (view === 'guides') guides?.refresh();
        else if (changed) requestAnimationFrame(() => { if (dialog.open) bottom(); });
    };
    const initialTopics = config.initial.map(id => topics.get(id)).filter(Boolean);
    const nearBottom = () => conversation.scrollHeight - conversation.clientHeight - conversation.scrollTop < 64;
    const bottom = () => {
        conversation.scrollTop = conversation.scrollHeight;
        newMessage.hidden = true;
    };
    const appendMessage = (text, user = false) => {
        const follow = nearBottom();
        if (user) {
            const welcomeSuggestions = conversation.querySelector('[data-assistant-welcome] .assistant-suggestions');
            if (welcomeSuggestions) welcomeSuggestions.hidden = true;
        }
        const message = document.createElement('div');
        message.className = `assistant-message${user ? ' is-user' : ''}`;
        const label = document.createElement('p');
        label.className = 'assistant-message-label';
        label.textContent = user ? 'Anda' : 'Layanan Pinjemin';
        const bubble = document.createElement('div');
        bubble.className = 'assistant-bubble';
        bubble.textContent = text;
        message.append(label, bubble);
        conversation.append(message);
        while (conversation.children.length > 61) conversation.firstElementChild.remove();
        if (follow) bottom();
        else newMessage.hidden = false;
        return { message, bubble, follow };
    };
    const appendLinks = (message, actions) => {
        if (!actions.length) return;
        const links = document.createElement('div');
        links.className = 'assistant-links';
        actions.forEach(action => {
            const url = new URL(action.url, window.location.href);
            if (!['http:', 'https:', 'mailto:'].includes(url.protocol)) return;
            const link = document.createElement('a');
            link.href = url.href;
            link.textContent = action.label;
            if (url.protocol !== 'mailto:' && url.origin !== window.location.origin) {
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
            }
            const legal = document.querySelector('[data-legal-modal]');
            if (legal && [legal.dataset.termsUrl, legal.dataset.privacyUrl].some(address => {
                const documentUrl = new URL(address, window.location.href);
                return documentUrl.origin === url.origin && documentUrl.pathname === url.pathname;
            })) {
                link.setAttribute('aria-haspopup', 'dialog');
                link.setAttribute('aria-controls', legal.id);
            }
            links.append(link);
        });
        message.append(links);
    };
    const topicButtons = (container, choices) => {
        choices.forEach(topic => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'assistant-topic-button';
            button.dataset.assistantTopic = topic.id;
            button.textContent = topic.question;
            container.append(button);
        });
    };
    const suggest = (message, choices) => {
        const group = document.createElement('div');
        group.className = 'assistant-suggestions';
        topicButtons(group, choices);
        message.append(group);
    };
    const setTopicsVisible = visible => {
        topicBrowser.hidden = !visible;
        conversation.hidden = visible;
        newMessage.hidden = true;
        root.querySelector('[data-assistant-show-topics]').setAttribute('aria-expanded', String(visible));
    };
    const saveHistory = () => {
        historyExpiresAt = Date.now() + lifetime;
        storage.set(historyKey, JSON.stringify({ version: config.version, scope: config.scope, expiresAt: historyExpiresAt, ids: history.slice(-20) }));
    };
    const greetingMessage = () => {
        const entry = appendMessage('Silakan pilih topik atau masukkan pertanyaan mengenai layanan Pinjemin.');
        entry.message.dataset.assistantWelcome = '';
        suggest(entry.message, initialTopics.slice(0, 3));
    };
    const displayAnswer = (topic, restore = false) => {
        const entry = appendMessage(topic.answer);
        appendLinks(entry.message, topic.actions);
        if (entry.follow) bottom();
        if (!restore) {
            history.push(topic.id);
            history = history.slice(-20);
            saveHistory();
            announce(topic.answer);
        }
    };
    const clearHistory = () => {
        history = [];
        historyExpiresAt = 0;
        storage.remove(historyKey);
        conversation.replaceChildren();
        greetingMessage();
        setTopicsVisible(false);
        input.value = '';
        error.hidden = true;
        input.removeAttribute('aria-invalid');
    };
    const restoreHistory = () => {
        history = [];
        historyExpiresAt = 0;
        conversation.replaceChildren();
        setTopicsVisible(false);
        error.hidden = true;
        input.removeAttribute('aria-invalid');
        try {
            const saved = JSON.parse(storage.get(historyKey));
            if (saved?.version === config.version && saved.scope === config.scope && Number.isFinite(saved.expiresAt) && saved.expiresAt > Date.now() && saved.expiresAt <= Date.now() + lifetime && Array.isArray(saved.ids)) {
                history = saved.ids.filter(id => typeof id === 'string' && topics.has(id)).slice(-20);
                historyExpiresAt = saved.expiresAt;
            } else storage.remove(historyKey);
        } catch { storage.remove(historyKey); }
        greetingMessage();
        history.forEach(id => {
            appendMessage(topics.get(id).question, true);
            displayAnswer(topics.get(id), true);
        });
    };
    restoreHistory();
    topicButtons(root.querySelector('[data-assistant-topic-list]'), [...new Set([...config.initial, ...topics.keys()])].map(id => topics.get(id)).filter(Boolean));

    const dismissGreeting = () => {
        greeting.hidden = true;
        clearTimeout(greetingTimer);
        launcher.classList.remove('is-greeting');
        storage.set(greetingKey, 'true');
    };
    const otherOverlayOpen = () => !!document.querySelector('dialog[open]:not([data-assistant-dialog]), [data-workspace][data-drawer-open="true"], [data-site-header].has-open-menu, [data-dropdown-trigger][aria-expanded="true"], [data-confirm-modal]:not(.hidden)');
    const syncLauncher = () => { launcherArea.hidden = dialog.open || otherOverlayOpen(); };
    const updateViewport = () => {
        frame = null;
        const viewport = window.visualViewport;
        dialog.style.setProperty('--assistant-viewport-height', `${viewport?.height ?? window.innerHeight}px`);
        dialog.style.setProperty('--assistant-viewport-top', `${viewport?.offsetTop ?? 0}px`);
    };
    const scheduleViewport = () => { if (frame === null) frame = requestAnimationFrame(updateViewport); };
    const releaseScroll = () => {
        if (!scrollStyles) return;
        document.documentElement.style.overflow = scrollStyles.overflow;
        document.documentElement.style.scrollbarGutter = scrollStyles.scrollbarGutter;
        scrollStyles = null;
    };
    const close = (restoreFocus = true) => {
        if (!dialog.open) return;
        dialog.close();
        launcher.setAttribute('aria-expanded', 'false');
        releaseScroll();
        syncLauncher();
        if (restoreFocus && !launcherArea.hidden) launcher.focus({ preventScroll: true });
    };
    const open = () => {
        if (dialog.open || otherOverlayOpen()) return;
        if (historyExpiresAt && historyExpiresAt <= Date.now()) clearHistory();
        document.dispatchEvent(new CustomEvent('pinjemin:overlay-opening', { detail: { source: 'assistant' } }));
        dismissGreeting();
        updateViewport();
        if (mobile.matches) {
            scrollStyles = { overflow: document.documentElement.style.overflow, scrollbarGutter: document.documentElement.style.scrollbarGutter };
            if (window.innerWidth > document.documentElement.clientWidth) document.documentElement.style.scrollbarGutter = 'stable';
            document.documentElement.style.overflow = 'hidden';
            dialog.showModal();
        } else dialog.show();
        launcher.setAttribute('aria-expanded', 'true');
        syncLauncher();
        guides?.refresh();
        if (activeView === 'chat') bottom();
        if (mobile.matches) root.querySelector('[data-assistant-close]').focus({ preventScroll: true });
        else (activeView === 'chat' ? input : root.querySelector('[data-assistant-tab="guides"]')).focus({ preventScroll: true });
    };
    const answerTopic = id => {
        const topic = topics.get(id);
        if (!topic) return;
        if (historyExpiresAt && historyExpiresAt <= Date.now()) clearHistory();
        setTopicsVisible(false);
        appendMessage(topic.question, true);
        displayAnswer(topic);
        input.focus({ preventScroll: true });
    };
    const tutorial = initializeTutorial({ root, config, onFinish: (completed, goal) => {
        open();
        selectView('guides');
        guides.completed(completed, goal);
        root.querySelector('[data-assistant-tab="guides"]').focus({ preventScroll: true });
    } });
    guides = initializeGuides({ root, config, startTour: goal => tutorial.start(goal), closeAssistant: () => close(false), showGuides: () => selectView('guides'), announce });
    root.querySelector('[data-assistant-tutorial]').addEventListener('click', () => tutorial.start('page'));
    root.querySelectorAll('[data-assistant-tab]').forEach((tab, index, tabs) => {
        tab.addEventListener('click', () => selectView(tab.dataset.assistantTab));
        tab.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + 1) % tabs.length;
            selectView(tabs[next].dataset.assistantTab);
            tabs[next].focus({ preventScroll: true });
        });
    });
    launcher.addEventListener('click', open);
    root.querySelector('[data-assistant-close]').addEventListener('click', () => close());
    root.querySelector('[data-assistant-dismiss-greeting]').addEventListener('click', dismissGreeting);
    root.querySelector('[data-assistant-reset]').addEventListener('click', () => {
        clearHistory();
        announce('Percakapan sudah dihapus. Pilih topik atau ketik pertanyaan baru.');
        input.focus({ preventScroll: true });
    });
    root.querySelector('[data-assistant-show-topics]').addEventListener('click', () => {
        setTopicsVisible(true);
        topicBrowser.querySelector('button').focus({ preventScroll: true });
    });
    root.querySelector('[data-assistant-hide-topics]').addEventListener('click', () => {
        setTopicsVisible(false);
        input.focus({ preventScroll: true });
    });
    root.addEventListener('click', event => {
        const button = event.target.closest('[data-assistant-topic]');
        if (button) answerTopic(button.dataset.assistantTopic);
    });
    form.addEventListener('submit', event => {
        event.preventDefault();
        if (composing) return;
        const question = input.value.trim();
        if (!question || question.length > 300) {
            error.textContent = question ? 'Pertanyaan maksimal 300 karakter.' : 'Ketik pertanyaan atau pilih salah satu topik.';
            error.hidden = false;
            input.setAttribute('aria-invalid', 'true');
            announce(error.textContent);
            input.focus();
            return;
        }
        if (historyExpiresAt && historyExpiresAt <= Date.now()) clearHistory();
        error.hidden = true;
        input.removeAttribute('aria-invalid');
        setTopicsVisible(false);
        appendMessage(question, true);
        const result = matchQuestion(question, config.topics);
        if (result.kind === 'answer') displayAnswer(result.topics[0]);
        else {
            const text = result.kind === 'choices'
                ? 'Terdapat beberapa topik yang relevan. Pilih topik yang ingin ditinjau.'
                : 'Pilih topik bantuan yang relevan atau hubungi Pinjemin. Untuk masalah transaksi, buka detail pemesanan dan gunakan pengaduan jika tersedia.';
            const entry = appendMessage(text);
            suggest(entry.message, result.kind === 'choices' ? result.topics : initialTopics.slice(0, 3));
            if (result.kind !== 'choices') appendLinks(entry.message, config.contacts);
            if (entry.follow) bottom();
            announce(text);
        }
        input.value = '';
        input.focus({ preventScroll: true });
    });
    input.addEventListener('compositionstart', () => { composing = true; });
    input.addEventListener('compositionend', () => { composing = false; });
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter' && (event.isComposing || composing || event.keyCode === 229)) event.preventDefault();
    });
    conversation.addEventListener('scroll', () => { if (nearBottom()) newMessage.hidden = true; }, { passive: true });
    newMessage.addEventListener('click', () => { bottom(); conversation.focus({ preventScroll: true }); });
    dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
    dialog.addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(); }
        if (event.key === 'Tab' && mobile.matches) {
            const controls = [...dialog.querySelectorAll('button, a[href], input, [tabindex]')]
                .filter(node => !node.matches(':disabled, [tabindex="-1"]') && !node.closest('[hidden]') && node.getClientRects().length > 0);
            const first = controls[0];
            const last = controls.at(-1);
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus({ preventScroll: true });
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus({ preventScroll: true });
            }
        }
    });
    dialog.addEventListener('click', event => {
        if (event.target !== dialog || !mobile.matches) return;
        const rect = dialog.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) close();
    });
    dialog.addEventListener('close', () => {
        if (!dialog.open) { releaseScroll(); launcher.setAttribute('aria-expanded', 'false'); syncLauncher(); }
    });
    document.addEventListener('pinjemin:overlay-opening', event => {
        if (event.detail?.source !== 'assistant') close(false);
    });
    document.addEventListener('submit', event => {
        if (event.target.action === config.logoutUrl) {
            clearHistory();
            guides.reset();
            storage.remove(greetingKey);
        }
    }, true);
    mobile.addEventListener('change', () => { close(); updateViewport(); });
    window.visualViewport?.addEventListener('resize', scheduleViewport, { passive: true });
    window.visualViewport?.addEventListener('scroll', scheduleViewport, { passive: true });
    window.addEventListener('resize', scheduleViewport, { passive: true });
    window.addEventListener('pagehide', () => { close(false); input.value = ''; });
    window.addEventListener('pageshow', event => {
        if (event.persisted) { close(false); restoreHistory(); guides.refresh(); input.value = ''; }
        syncLauncher();
    });
    const overlayObserver = new MutationObserver(() => {
        if (otherOverlayOpen()) { close(false); dismissGreeting(); }
        syncLauncher();
    });
    document.querySelectorAll('[data-legal-modal], [data-tutorial-dialog], [data-confirm-modal], [data-workspace], [data-site-header], [data-dropdown-trigger]').forEach(node => {
        overlayObserver.observe(node, { attributes: true, attributeFilter: ['open', 'class', 'data-drawer-open', 'aria-expanded'] });
    });
    root.querySelectorAll('[data-assistant-mascot]').forEach(image => {
        const fallback = () => { image.hidden = true; image.closest('.assistant-launcher')?.classList.add('has-image-error'); };
        image.addEventListener('error', fallback);
        if (image.complete && image.naturalWidth === 0) fallback();
    });
    syncLauncher();
    if (storage.get(greetingKey) !== 'true' && !launcherArea.hidden) {
        greeting.hidden = false;
        launcher.classList.add('is-greeting');
        storage.set(greetingKey, 'true');
        greetingTimer = setTimeout(dismissGreeting, 8000);
    }
}
