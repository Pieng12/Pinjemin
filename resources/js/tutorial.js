import { goalTutorialSteps } from './goal-tutorial-steps';

export function initializeTutorial({ root, config, onFinish }) {
    const dialog = root.querySelector('[data-tutorial-dialog]');
    if (!dialog || typeof dialog.showModal !== 'function') return { start() {} };
    const card = dialog.querySelector('[data-tutorial-card]');
    const cutout = dialog.querySelector('[data-tutorial-cutout]');
    const highlight = dialog.querySelector('[data-tutorial-highlight]');
    const next = dialog.querySelector('[data-tutorial-next]');
    const previous = dialog.querySelector('[data-tutorial-previous]');
    let steps = [];
    let index = 0;
    let target = null;
    let restore = [];
    let original = null;
    let frame = null;
    let trackingUntil = 0;
    let preparing = false;
    let goal = 'page';

    const candidates = definition => {
        if (definition.selector) return [...document.querySelectorAll(definition.selector)];
        const url = config.navigation?.[definition.destination]?.url;
        return url ? [...document.querySelectorAll('a[href]')].filter(node => node.href === url && !root.contains(node)) : [];
    };
    const visible = node => node.isConnected && !node.matches('input[type="hidden"], [hidden]') && node.getClientRects().length > 0 && getComputedStyle(node).display !== 'none' && getComputedStyle(node).visibility !== 'hidden';
    const revealable = node => {
        if (node.matches('input[type="hidden"]') || root.contains(node)) return false;
        const container = node.closest('#mobile-menu, [data-workspace-sidebar], [data-dropdown-menu], details:not([open])');
        if (!container) return false;
        for (let current = node; current && current !== document.body; current = current.parentElement) {
            if ((current.hidden || getComputedStyle(current).display === 'none') && current !== container && !current.matches('#mobile-menu, [data-dropdown-menu]')) return false;
        }
        return true;
    };
    const find = definition => {
        const nodes = candidates(definition);
        return nodes.find(node => visible(node) && !node.closest('[inert]')) || nodes.find(revealable) || null;
    };
    const restoreReveals = () => {
        preparing = true;
        restore.reverse().forEach(callback => callback());
        restore = [];
        preparing = false;
    };
    const prepare = node => {
        preparing = true;
        const sidebar = node.closest('[data-workspace-sidebar]');
        const workspace = sidebar?.closest('[data-workspace]');
        if (workspace && window.matchMedia('(max-width: 1023px)').matches && workspace.dataset.drawerOpen !== 'true') {
            workspace.querySelector('[data-workspace-open]').click();
            restore.push(() => workspace.querySelector('[data-workspace-close]').click());
        }
        const menu = node.closest('#mobile-menu');
        const toggle = document.querySelector('[data-mobile-menu-toggle]');
        if (menu && toggle?.getAttribute('aria-expanded') !== 'true') {
            toggle?.click();
            restore.push(() => { if (toggle?.getAttribute('aria-expanded') === 'true') toggle.click(); });
        }
        const dropdown = node.closest('[data-dropdown]');
        const trigger = dropdown?.querySelector('[data-dropdown-trigger]');
        if (node.closest('[data-dropdown-menu]') && trigger?.getAttribute('aria-expanded') !== 'true') {
            trigger.click();
            restore.push(() => { if (trigger.getAttribute('aria-expanded') === 'true') trigger.click(); });
        }
        let parent = node.parentElement;
        while (parent) {
            if (parent.matches('details:not([open])') && !parent.querySelector(':scope > summary')?.contains(node)) {
                const detail = parent;
                detail.open = true;
                restore.push(() => { detail.open = false; });
            }
            parent = parent.parentElement;
        }
        preparing = false;
    };
    const place = () => {
        frame = null;
        if (!dialog.open || !target) return;
        if (!target.isConnected || !visible(target)) {
            const replacement = find(steps[index]);
            if (!replacement) { steps.splice(index, 1); move(Math.min(index, steps.length)); return; }
            target.removeAttribute('data-tutorial-active-target');
            target = replacement;
            target.setAttribute('data-tutorial-active-target', '');
            prepare(target);
        }
        const viewport = window.visualViewport;
        const width = document.documentElement.clientWidth;
        const height = viewport?.height ?? innerHeight;
        const offset = viewport?.offsetTop ?? 0;
        dialog.style.width = `${width}px`;
        dialog.style.height = `${height}px`;
        dialog.style.top = `${offset}px`;
        const bounds = target.getBoundingClientRect();
        const x = Math.max(8, Math.min(width - 8, bounds.left - 6));
        const y = Math.max(8, Math.min(height - 52, bounds.top - offset - 6));
        const w = Math.max(1, Math.min(width - x - 8, bounds.right + 6 - x));
        const h = Math.max(1, Math.min(height - y - 8, height * 0.35, bounds.bottom - offset + 6 - y));
        for (const [key, value] of Object.entries({ x, y, width: w, height: h })) cutout.setAttribute(key, String(value));
        Object.assign(highlight.style, { left: `${x}px`, top: `${y}px`, width: `${w}px`, height: `${h}px` });
        const cardWidth = Math.min(360, width - 24);
        card.style.width = `${cardWidth}px`;
        card.style.maxHeight = `${height - 24}px`;
        const cardHeight = card.getBoundingClientRect().height;
        let left = Math.max(12, Math.min(width - cardWidth - 12, x));
        let top;
        if (width - (x + w) >= cardWidth + 24) {
            left = x + w + 12;
            top = Math.max(12, Math.min(height - cardHeight - 12, y));
        } else if (x >= cardWidth + 24) {
            left = x - cardWidth - 12;
            top = Math.max(12, Math.min(height - cardHeight - 12, y));
        } else {
            const above = y - 24;
            const below = height - y - h - 24;
            if (below >= cardHeight || below >= above) {
                card.style.maxHeight = `${Math.max(120, below)}px`;
                top = y + h + 12;
            } else {
                card.style.maxHeight = `${Math.max(120, above)}px`;
                top = y - card.getBoundingClientRect().height - 12;
            }
            top = Math.max(12, Math.min(height - card.getBoundingClientRect().height - 12, top));
        }
        Object.assign(card.style, { left: `${left}px`, top: `${top}px` });
        if (performance.now() < trackingUntil) schedule();
    };
    const schedule = () => { if (dialog.open && frame === null) frame = requestAnimationFrame(place); };
    const finish = (completed = false, reopen = true) => {
        if (!dialog.open) return;
        restoreReveals();
        dialog.close();
        target?.removeAttribute('data-tutorial-active-target');
        target = null;
        if (frame !== null) cancelAnimationFrame(frame);
        frame = null;
        document.documentElement.classList.remove('has-tutorial');
        if (original) {
            document.documentElement.style.overflow = original.overflow;
            document.documentElement.style.scrollBehavior = 'auto';
            window.scrollTo(original.x, original.y);
            document.documentElement.style.scrollBehavior = original.scrollBehavior;
            original = null;
        }
        if (reopen) requestAnimationFrame(() => onFinish(completed, goal));
    };
    const move = requested => {
        if (requested >= steps.length) { finish(true); return; }
        if (requested < 0) return;
        restoreReveals();
        target?.removeAttribute('data-tutorial-active-target');
        index = requested;
        const node = find(steps[index]);
        if (!node) { steps.splice(index, 1); move(Math.min(index, steps.length)); return; }
        target = node.matches('input[type="checkbox"], input[type="radio"]') ? node.closest('label') || node : node;
        target.setAttribute('data-tutorial-active-target', '');
        prepare(target);
        target.scrollIntoView({ behavior: 'instant', block: 'center', inline: 'nearest' });
        if (!target.closest('[data-workspace-sidebar], [data-site-header]')) {
            const header = document.querySelector('[data-site-header], .owner-topbar');
            const headerBottom = header ? Math.max(0, header.getBoundingClientRect().bottom) : 0;
            window.scrollBy({ top: target.getBoundingClientRect().top - headerBottom - 24, behavior: 'instant' });
        }
        dialog.querySelector('#tutorial-title').textContent = steps[index].title;
        dialog.querySelector('#tutorial-description').textContent = steps[index].description;
        dialog.querySelector('[data-tutorial-progress]').textContent = `Langkah ${index + 1} dari ${steps.length}`;
        const progress = dialog.querySelector('[data-tutorial-progress-bar]');
        progress.max = steps.length;
        progress.value = index + 1;
        previous.disabled = index === 0;
        next.textContent = index === steps.length - 1 ? 'Selesai' : 'Berikutnya';
        dialog.querySelector('[data-tutorial-announcement]').textContent = `Langkah ${index + 1} dari ${steps.length}. ${steps[index].title}. ${steps[index].description}`;
        trackingUntil = performance.now() + 320;
        place();
        card.focus({ preventScroll: true });
    };
    const start = (requestedGoal = 'page') => {
        if (dialog.open) return;
        goal = requestedGoal;
        document.dispatchEvent(new CustomEvent('pinjemin:overlay-opening', { detail: { source: 'tutorial' } }));
        steps = goalTutorialSteps(config, goal).filter(definition => find(definition));
        if (!steps.length) { onFinish(false, goal); return; }
        original = { x: window.scrollX, y: window.scrollY, overflow: document.documentElement.style.overflow, scrollBehavior: document.documentElement.style.scrollBehavior };
        document.documentElement.classList.add('has-tutorial');
        document.documentElement.style.overflow = 'hidden';
        dialog.showModal();
        move(0);
    };
    next.addEventListener('click', () => move(index + 1));
    previous.addEventListener('click', () => move(index - 1));
    dialog.querySelector('[data-tutorial-skip]').addEventListener('click', () => finish());
    dialog.querySelector('[data-tutorial-close]').addEventListener('click', () => finish());
    dialog.addEventListener('cancel', event => { event.preventDefault(); finish(); });
    dialog.addEventListener('click', event => event.stopPropagation());
    dialog.addEventListener('keydown', event => {
        const navigationKey = ['ArrowLeft', 'ArrowRight'].includes(event.key) && !event.altKey && !event.ctrlKey && !event.metaKey && !event.shiftKey;
        if (event.key === 'Escape' || navigationKey) {
            event.preventDefault();
            event.stopPropagation();
            if (event.key === 'Escape') finish();
            else move(index + (event.key === 'ArrowRight' ? 1 : -1));
        }
        if (event.key === 'Tab') {
            const controls = [...card.querySelectorAll('button:not(:disabled)')];
            if (event.shiftKey && (document.activeElement === controls[0] || document.activeElement === card)) {
                event.preventDefault(); controls.at(-1).focus();
            } else if (!event.shiftKey && document.activeElement === controls.at(-1)) {
                event.preventDefault(); controls[0].focus();
            }
        }
    });
    document.addEventListener('pinjemin:overlay-opening', event => {
        if (dialog.open && !preparing && event.detail?.source !== 'tutorial') finish(false, false);
    });
    window.addEventListener('resize', schedule, { passive: true });
    window.visualViewport?.addEventListener('resize', schedule, { passive: true });
    window.visualViewport?.addEventListener('scroll', schedule, { passive: true });
    document.addEventListener('scroll', schedule, { passive: true, capture: true });
    window.addEventListener('pagehide', () => finish(false, false));
    return { start };
}
