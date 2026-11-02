export function initializeLegalModal() {
    const dialog = document.querySelector('[data-legal-modal]');
    if (!dialog || typeof dialog.showModal !== 'function') return;

    const documents = ['terms', 'privacy'].map(name => ({
        name,
        url: new URL(dialog.dataset[`${name}Url`], window.location.href),
        tab: dialog.querySelector(`[data-legal-tab="${name}"]`),
        panel: dialog.querySelector(`[data-legal-panel="${name}"]`),
        loaded: false,
        request: null,
        pending: null,
    }));
    const scrollArea = dialog.querySelector('[data-legal-scroll]');
    let opener = null;
    let currentDocument = documents[0];
    let previousRootStyle = null;

    const matchDocument = link => {
        if (link.getAttribute('href')?.startsWith('#')) return null;
        try {
            const url = new URL(link.href, window.location.href);
            return documents.find(document => url.origin === document.url.origin && url.pathname === document.url.pathname);
        } catch { return null; }
    };

    const restorePage = () => {
        documents.forEach(document => document.request?.abort());
        if (previousRootStyle) {
            document.documentElement.style.overflow = previousRootStyle.overflow;
            document.documentElement.style.scrollbarGutter = previousRootStyle.scrollbarGutter;
            previousRootStyle = null;
        }
        const previousOpener = opener;
        opener = null;
        const menuControl = previousOpener?.closest('[data-dropdown]')?.querySelector('[data-dropdown-trigger]')
            || (previousOpener?.closest('#mobile-menu') ? document.querySelector('[aria-controls="mobile-menu"]') : null);
        (menuControl || previousOpener)?.focus({ preventScroll: true });
    };
    const close = () => {
        if (dialog.open) dialog.close();
        restorePage();
    };
    const jumpToSection = (entry, hash) => {
        if (!hash || !dialog.open || currentDocument !== entry) return;
        let sectionId;
        try { sectionId = decodeURIComponent(hash.slice(1)); } catch { return; }
        const section = [...entry.panel.querySelectorAll('[data-legal-section]')].find(node => node.dataset.legalSection === sectionId);
        if (section) scrollArea.scrollTop += section.getBoundingClientRect().top - scrollArea.getBoundingClientRect().top - 16;
    };

    const loadDocument = async entry => {
        if (entry.loaded) return true;
        if (entry.pending) {
            if (entry.request?.signal.aborted) {
                await entry.pending;
                return loadDocument(entry);
            }
            return entry.pending;
        }
        const controller = new AbortController();
        entry.request = controller;
        const loading = entry.panel.querySelector('[data-legal-loading]');
        const failure = entry.panel.querySelector('[data-legal-error]');
        loading.hidden = false;
        failure.hidden = true;
        entry.panel.setAttribute('aria-busy', 'true');

        entry.pending = (async () => {
            try {
                const response = await fetch(entry.url.href, { signal: controller.signal, credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'text/html' } });
                if (!response.ok) throw new Error('Legal document unavailable');
                const source = new DOMParser().parseFromString(await response.text(), 'text/html');
                const article = source.querySelector('[data-legal-content]');
                if (!article || controller.signal.aborted) throw new Error('Legal document missing');

                const content = article.cloneNode(true);
                content.className = 'min-w-0';
                content.querySelectorAll('script, iframe, object, embed, style, link').forEach(node => node.remove());
                content.querySelectorAll('[id]').forEach(node => {
                    node.dataset.legalSection = node.id;
                    node.id = `modal-${entry.name}-${node.id}`;
                });
                content.querySelectorAll('[aria-labelledby]').forEach(node => {
                    node.setAttribute('aria-labelledby', node.getAttribute('aria-labelledby').split(' ').map(id => `modal-${entry.name}-${id}`).join(' '));
                });
                content.querySelectorAll('h2').forEach(heading => {
                    const replacement = document.createElement('h4');
                    [...heading.attributes].forEach(attribute => replacement.setAttribute(attribute.name, attribute.value));
                    replacement.replaceChildren(...heading.childNodes);
                    heading.replaceWith(replacement);
                });
                entry.panel.querySelector('[data-legal-heading]').textContent = article.dataset.legalTitle;
                entry.panel.querySelector('[data-legal-meta]').textContent = `Diperbarui ${article.dataset.legalPublished} · Versi ${article.dataset.legalVersion}`;
                entry.panel.querySelector('[data-legal-summary]').textContent = source.querySelector('[data-legal-intro]')?.textContent || '';
                entry.panel.querySelector('[data-legal-body]').replaceChildren(content);
                entry.loaded = true;
                return true;
            } catch (error) {
                if (error.name !== 'AbortError' && !controller.signal.aborted) failure.hidden = false;
                return false;
            } finally {
                loading.hidden = true;
                entry.panel.setAttribute('aria-busy', 'false');
                entry.request = null;
                entry.pending = null;
            }
        })();
        return entry.pending;
    };

    const activate = async (entry, hash = '') => {
        currentDocument = entry;
        documents.forEach(document => {
            const selected = document === entry;
            document.tab.setAttribute('aria-selected', String(selected));
            document.tab.tabIndex = selected ? 0 : -1;
            document.panel.hidden = !selected;
        });
        scrollArea.scrollTop = 0;
        if (await loadDocument(entry)) jumpToSection(entry, hash);
    };

    document.querySelectorAll('a[href]').forEach(link => {
        if (!matchDocument(link) || link.hasAttribute('data-legal-full-page')) return;
        link.setAttribute('aria-haspopup', 'dialog');
        link.setAttribute('aria-controls', dialog.id);
        link.removeAttribute('target');
    });
    document.addEventListener('click', event => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a[href]');
        if (!link || link.hasAttribute('data-legal-full-page')) return;
        const entry = matchDocument(link);
        if (!entry) return;
        event.preventDefault();
        if (!dialog.open) {
            opener = link;
            previousRootStyle = { overflow: document.documentElement.style.overflow, scrollbarGutter: document.documentElement.style.scrollbarGutter };
            if (window.innerWidth > document.documentElement.clientWidth) document.documentElement.style.scrollbarGutter = 'stable';
            document.documentElement.style.overflow = 'hidden';
            dialog.showModal();
            dialog.querySelector('[data-legal-close]').focus({ preventScroll: true });
        }
        activate(entry, new URL(link.href).hash);
    });

    documents.forEach((entry, index) => {
        entry.tab.addEventListener('click', () => activate(entry));
        entry.tab.addEventListener('keydown', event => {
            const next = event.key === 'ArrowRight' ? (index + 1) % documents.length : event.key === 'ArrowLeft' ? (index - 1 + documents.length) % documents.length : event.key === 'Home' ? 0 : event.key === 'End' ? documents.length - 1 : null;
            if (next === null) return;
            event.preventDefault();
            documents[next].tab.focus();
            activate(documents[next]);
        });
        entry.panel.querySelector('[data-legal-retry]').addEventListener('click', () => activate(entry));
    });
    dialog.querySelectorAll('[data-legal-close]').forEach(button => button.addEventListener('click', close));
    dialog.addEventListener('keydown', event => {
        if (event.key !== 'Tab') return;
        const controls = [...dialog.querySelectorAll('button:not(:disabled), a[href], [tabindex="0"]')].filter(node => node.tabIndex >= 0 && node.getClientRects().length);
        const first = controls[0];
        const last = controls.at(-1);
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last?.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first?.focus();
        }
    });
    dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
    dialog.addEventListener('close', () => { if (!dialog.open) restorePage(); });
    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const rect = dialog.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) close();
    });
    window.addEventListener('pagehide', close);
    window.addEventListener('pageshow', () => { if (dialog.open) close(); });
}
