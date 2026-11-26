export function initializeWorkspace(root = document) {
    root.querySelectorAll('[data-workspace]').forEach((workspace) => {
        if (workspace.dataset.enhanced === 'true') return;

        const sidebar = workspace.querySelector('[data-workspace-sidebar]');
        const content = workspace.querySelector('[data-workspace-content]');
        const collapseButton = workspace.querySelector('[data-workspace-collapse]');
        const openButton = workspace.querySelector('[data-workspace-open]');
        const closeButton = workspace.querySelector('[data-workspace-close]');
        const backdrop = workspace.querySelector('[data-workspace-backdrop]');
        if (!sidebar || !content || !collapseButton || !openButton || !closeButton || !backdrop) return;

        const role = workspace.dataset.workspaceRole;
        const storageKey = `pinjemin.${role}SidebarCollapsed`;
        const desktop = window.matchMedia('(min-width: 1024px)');
        const state = { drawerOpen: false, collapsed: false };
        let returnFocus = null;
        let scrollStyles = null;
        const previousContentInert = content.inert;

        try { state.collapsed = localStorage.getItem(storageKey) === 'true'; } catch {}

        const syncCollapsed = () => {
            document.documentElement.dataset.workspaceCollapsed = String(state.collapsed);
            document.documentElement.dataset[`${role}Collapsed`] = String(state.collapsed);
            const label = state.collapsed ? 'Buka sidebar' : 'Ciutkan sidebar';
            collapseButton.setAttribute('aria-expanded', String(!state.collapsed));
            collapseButton.setAttribute('aria-label', label);
            collapseButton.title = label;
            collapseButton.querySelector('[data-collapse-icon]')?.toggleAttribute('hidden', state.collapsed);
            collapseButton.querySelector('[data-expand-icon]')?.toggleAttribute('hidden', !state.collapsed);
        };

        const focusableElements = () => [...sidebar.querySelectorAll('a[href], button, input, select, textarea, [tabindex]')]
            .filter((element) => !element.matches(':disabled, [hidden], [tabindex="-1"]')
                && !element.closest('[inert]') && element.getClientRects().length > 0
                && getComputedStyle(element).visibility !== 'hidden');

        const setDrawerOpen = (requestedOpen, restoreFocus = true) => {
            const wasOpen = state.drawerOpen;
            state.drawerOpen = requestedOpen && !desktop.matches;
            workspace.dataset.drawerOpen = String(state.drawerOpen);
            openButton.setAttribute('aria-expanded', String(state.drawerOpen));
            backdrop.hidden = !state.drawerOpen;
            sidebar.inert = !desktop.matches && !state.drawerOpen;

            if (state.drawerOpen) {
                if (!wasOpen) document.dispatchEvent(new CustomEvent('pinjemin:overlay-opening', { detail: { source: 'workspace' } }));
                sidebar.setAttribute('role', 'dialog');
                sidebar.setAttribute('aria-modal', 'true');
                if (!wasOpen) {
                    returnFocus = document.activeElement;
                    scrollStyles = {
                        bodyOverflow: document.body.style.overflow,
                        htmlOverflow: document.documentElement.style.overflow,
                        bodyPaddingRight: document.body.style.paddingRight,
                    };
                    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
                    if (scrollbarWidth > 0) {
                        document.body.style.paddingRight = `${parseFloat(getComputedStyle(document.body).paddingRight) + scrollbarWidth}px`;
                    }
                    document.body.style.overflow = 'hidden';
                    document.documentElement.style.overflow = 'hidden';
                    closeButton.focus({ preventScroll: true });
                }
                content.inert = true;
            } else {
                content.inert = previousContentInert;
                sidebar.removeAttribute('role');
                sidebar.removeAttribute('aria-modal');
                if (wasOpen && scrollStyles) {
                    document.body.style.overflow = scrollStyles.bodyOverflow;
                    document.documentElement.style.overflow = scrollStyles.htmlOverflow;
                    document.body.style.paddingRight = scrollStyles.bodyPaddingRight;
                    scrollStyles = null;
                }
                if (wasOpen && restoreFocus) {
                    const target = desktop.matches ? collapseButton : returnFocus;
                    if (target?.isConnected && target.getClientRects().length > 0 && !target.closest('[inert]')) {
                        target.focus({ preventScroll: true });
                    } else if (!desktop.matches) {
                        openButton.focus({ preventScroll: true });
                    }
                }
                returnFocus = null;
            }
        };

        collapseButton.addEventListener('click', () => {
            state.collapsed = !state.collapsed;
            try { localStorage.setItem(storageKey, String(state.collapsed)); } catch {}
            syncCollapsed();
        });
        openButton.addEventListener('click', () => setDrawerOpen(true));
        closeButton.addEventListener('click', () => setDrawerOpen(false));
        backdrop.addEventListener('click', () => setDrawerOpen(false));
        sidebar.addEventListener('click', (event) => {
            if (event.target.closest('a[href]')) setDrawerOpen(false);
        });
        if (role === 'owner') {
            workspace.addEventListener('click', (event) => {
                workspace.querySelectorAll('details[data-owner-management][open]').forEach(detail => {
                    if (!detail.contains(event.target)) detail.open = false;
                });
            });
        }
        desktop.addEventListener('change', () => {
            const focusWasInSidebar = sidebar.contains(document.activeElement);
            const focusWasOnOpener = document.activeElement === openButton;
            setDrawerOpen(false);
            if (!desktop.matches && focusWasInSidebar) openButton.focus({ preventScroll: true });
            if (desktop.matches && focusWasOnOpener) collapseButton.focus({ preventScroll: true });
        });
        document.addEventListener('keydown', (event) => {
            if (role === 'owner' && event.key === 'Escape' && !state.drawerOpen && !document.querySelector('dialog[open], [data-confirm-modal]:not(.hidden)')) {
                const detail = workspace.querySelector('details[data-owner-management][open]');
                if (detail) {
                    event.preventDefault();
                    detail.open = false;
                    detail.querySelector('summary').focus();
                }
            }
            if (!state.drawerOpen || document.querySelector('[data-tutorial-dialog][open]')) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                setDrawerOpen(false);
            } else if (event.key === 'Tab') {
                const focusable = focusableElements();
                const first = focusable[0];
                const last = focusable.at(-1);
                if (!first) {
                    event.preventDefault();
                    sidebar.focus({ preventScroll: true });
                } else if (!sidebar.contains(document.activeElement) || document.activeElement === sidebar) {
                    event.preventDefault();
                    (event.shiftKey ? last : first).focus();
                } else if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });
        document.addEventListener('focusin', (event) => {
            if (state.drawerOpen && !document.querySelector('[data-tutorial-dialog][open]') && !sidebar.contains(event.target)) {
                (focusableElements()[0] ?? sidebar).focus({ preventScroll: true });
            }
        });
        window.addEventListener('pagehide', () => setDrawerOpen(false, false));

        syncCollapsed();
        workspace.dataset.enhanced = 'true';
        setDrawerOpen(false, false);
    });
}
