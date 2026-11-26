export function initializeTransactionUI() {
    const openTarget = (hash, focus = true) => {
        if (!hash || !hash.startsWith('#')) return;
        const target = document.getElementById(decodeURIComponent(hash.slice(1)));
        if (!target) return;
        for (let node = target; node; node = node.parentElement) {
            if (node instanceof HTMLDetailsElement) node.open = true;
        }
        const forms = [...target.querySelectorAll('form')];
        const primary = forms.find(form => form.querySelector('[name="action"][value="resolve"]'))
            || forms.find(form => form.querySelector('[name="action"][value="agree"]'))
            || forms.find(form => form.hasAttribute('data-condition-upload') && form.querySelector('button[value="send"]'));
        const form = primary || forms.find(form => !form.closest('details') && form.querySelector('button[type="submit"], button:not([type])')) || forms[0];
        if (form) for (let node = form.parentElement; node && node !== target; node = node.parentElement) {
            if (node instanceof HTMLDetailsElement) node.open = true;
        }
        requestAnimationFrame(() => {
            target.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
            if (focus) {
                const control = form && [...form.querySelectorAll('input:not([type="hidden"]), select, textarea, button')].find(control => !control.disabled && control.getClientRects().length);
                (control || target).focus({ preventScroll: true });
            }
        });
    };
    document.querySelectorAll('[data-action-link]').forEach(link => link.addEventListener('click', event => {
        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin || url.pathname !== location.pathname || !url.hash || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        history.replaceState(null, '', url.hash);
        openTarget(url.hash);
    }));
    if (location.hash.startsWith('#booking-')) openTarget(location.hash, false);
    window.addEventListener('hashchange', () => { if (location.hash.startsWith('#booking-')) openTarget(location.hash); });

    document.querySelectorAll('[data-payment-section]').forEach(section => {
        const select = section.querySelector('select[name="method_index"]');
        const destinations = section.querySelector('[data-payment-destinations]');
        if (!select || !destinations) return;
        destinations.classList.remove('sm:grid-cols-2');
        destinations.setAttribute('aria-label', 'Tujuan pembayaran terpilih');
        const sync = () => destinations.querySelectorAll('[data-payment-destination]').forEach(card => {
            card.hidden = card.dataset.paymentDestination !== select.value;
            card.classList.toggle('border-brand-300', !card.hidden);
        });
        select.addEventListener('change', sync);
        sync();
    });

    document.querySelectorAll('[data-copy-value], [data-copy-amount]').forEach(button => {
        button.hidden = false;
        const status = document.createElement('span');
        status.className = 'ml-3 text-xs font-medium text-brand-700';
        status.setAttribute('role', 'status');
        button.after(status);
        button.addEventListener('click', async () => {
            let value = button.dataset.copyValue;
            const form = button.closest('[data-payment-form]');
            if (button.hasAttribute('data-copy-amount') && form) {
                const plan = form.querySelector('input[name="payment_plan"]:checked')?.value || form.dataset.plan;
                value = String(Number(form.dataset.verified) > 0 ? Number(form.dataset.remaining) : (plan && plan !== 'full_transfer' ? Number(form.dataset.deposit) : Number(form.dataset.total)));
            }
            try {
                if (!navigator.clipboard?.writeText) throw new Error('clipboard-unavailable');
                await navigator.clipboard.writeText(value);
                status.textContent = 'Disalin';
            } catch {
                const source = button.parentElement.querySelector('[data-copy-source]');
                if (source) {
                    const range = document.createRange();
                    range.selectNodeContents(source);
                    getSelection()?.removeAllRanges();
                    getSelection()?.addRange(range);
                }
                status.textContent = 'Pilih salin dari browser atau tekan Ctrl+C.';
            }
        });
    });
}
