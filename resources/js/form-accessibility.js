const initializedForms = new WeakSet();
const linkedErrors = new WeakSet();
let generatedId = 0;

const slug = (value) => value.replace(/[^a-zA-Z0-9_-]+/g, '-').replace(/^-+|-+$/g, '') || 'field';
const fieldPath = (name) => name.replace(/\[([^\]]*)\]/g, '.$1').replace(/\.$/, '');
const controlsIn = (form) => Array.from(form.elements).filter((field) =>
    field.matches('input:not([type="hidden"]), select, textarea, button[data-error-name]'));

function uniqueId(document, base) {
    let id = base;
    while (document.getElementById(id)) id = `${base}-${++generatedId}`;
    return id;
}

function describe(field, id) {
    const ids = new Set((field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
    ids.add(id);
    field.setAttribute('aria-describedby', Array.from(ids).join(' '));
}

function matchesField(field, name) {
    const path = fieldPath(field.dataset.errorName || field.dataset.errorField || field.name || '');
    if (path === name) return true;
    // A single multiple-file control receives indexed Laravel upload errors.
    return field.name?.endsWith('[]') && (name === path || name.startsWith(`${path}.`));
}

/** Find a failed field only within the supplied form; never use a document-wide name selector. */
export function findErrorField(form, name) {
    const fields = controlsIn(form).filter((field) => matchesField(field, name));
    return fields.find((field) => !field.disabled && field.checked)
        || fields.find((field) => !field.disabled)
        || fields[0]
        || null;
}

function setConditionalGroup(group, relevant) {
    if (!group) return;
    group.hidden = !relevant;
    group.querySelectorAll('input, select, textarea').forEach((field) => {
        // Retain values (including selected files) when switching types.
        field.disabled = !relevant;
    });
}

function enhanceConditions(form) {
    const type = form.querySelector('[data-payment-type]');
    if (type) {
        const provider = findErrorField(form, 'provider');
        const account = findErrorField(form, 'account_identifier');
        const image = findErrorField(form, 'qris_image');
        const sync = () => {
            const providerRequired = ['bank', 'other'].includes(type.value);
            const qris = type.value === 'qris';
            setConditionalGroup(form.querySelector('[data-payment-provider]'), providerRequired);
            setConditionalGroup(form.querySelector('[data-payment-account]'), !qris);
            setConditionalGroup(form.querySelector('[data-payment-qris]'), qris);
            if (provider) provider.required = providerRequired;
            if (account) {
                account.required = !qris;
                account.setAttribute('inputmode', ['bank', 'dana', 'gopay', 'ovo'].includes(type.value) ? 'numeric' : 'text');
            }
            if (image) image.required = qris && form.dataset.hasQris !== 'true';
        };
        type.addEventListener('change', sync);
        sync();
    }

    const documentType = form.querySelector('[data-identity-document-type]');
    const expiration = form.querySelector('[data-identity-expiration]');
    if (documentType && expiration) {
        const sync = () => { expiration.required = documentType.value === 'sim'; };
        documentType.addEventListener('change', sync);
        sync();
    }

    if (form.querySelector('[data-item-form-fields]')) {
        const active = findErrorField(form, 'is_active');
        const address = findErrorField(form, 'address');
        const photos = findErrorField(form, 'photos');
        const depositEnabled = findErrorField(form, 'allow_deposit_payment');
        const deposit = findErrorField(form, 'deposit_amount');
        const existingPhotos = Number(form.querySelector('[data-photo-upload]')?.dataset.existingCount || 0);
        const sync = () => {
            if (address) address.required = !!active?.checked;
            if (photos) photos.required = !!active?.checked && existingPhotos === 0;
            if (deposit) {
                deposit.required = !!depositEnabled?.checked;
                deposit.min = depositEnabled?.checked ? '0.01' : '0';
                deposit.dispatchEvent(new Event('currency-constraints-changed'));
            }
        };
        active?.addEventListener('change', sync);
        depositEnabled?.addEventListener('change', sync);
        sync();
    }
}

function focusField(field) {
    let parent = field.parentElement;
    while (parent) {
        if (parent.matches('details')) parent.open = true;
        parent = parent.parentElement;
    }
    const visibleCurrency = field.hasAttribute('data-currency-input') ? field.ownerDocument.getElementById(`${field.id}_display`) : null;
    (visibleCurrency || field).focus();
}

/**
 * Enhance a document, subtree, or newly inserted form. Safe to call repeatedly.
 * Forms may declare data-form-id and data-error-bag (default: "default").
 * Errors declare data-error-field or data-error-name, with matching bag/form scope.
 * Ambiguous default-bag fields remain summary text until a form scope is supplied.
 */
export function initializeFormAccessibility(root = document) {
    const document = root.ownerDocument || root;
    const forms = Array.from(root.querySelectorAll('form'));
    if (root.matches?.('form')) forms.unshift(root);
    root.querySelectorAll('[data-item-form-fields]').forEach((fields) => {
        const form = fields.closest('form');
        if (form && !form.dataset.formId) form.dataset.formId = fields.dataset.formId || 'item-form';
    });

    forms.forEach((form) => {
        const prefix = form.dataset.formId || form.id || `form-${++generatedId}`;
        form.dataset.formId = prefix;
        if (!form.id) form.id = uniqueId(document, prefix);
        controlsIn(form).forEach((field, index) => {
            const previousId = field.id;
            const duplicates = previousId ? Array.from(document.querySelectorAll('[id]')).filter((node) => node.id === previousId) : [];
            if (!previousId || duplicates.length > 1) {
                field.id = uniqueId(document, `${slug(prefix)}-${slug(field.name || String(index))}`);
                if (previousId) {
                    form.querySelectorAll('label[for]').forEach((label) => {
                        if (label.htmlFor === previousId) label.htmlFor = field.id;
                    });
                }
            }
            const wrappingLabel = field.closest('label');
            if (wrappingLabel && !wrappingLabel.htmlFor) wrappingLabel.htmlFor = field.id;
            const container = wrappingLabel || field.parentElement;
            container?.querySelectorAll('.field-help').forEach((help) => {
                if (!help.id) help.id = uniqueId(document, `${field.id}-help`);
                describe(field, help.id);
            });
        });
        if (!initializedForms.has(form)) {
            enhanceConditions(form);
            initializedForms.add(form);
        }
    });

    root.querySelectorAll('[data-error-field], [data-error-name]').forEach((error) => {
        if (error.matches('input, select, textarea, button') || !error.textContent.trim()) return;
        const name = error.dataset.errorField || error.dataset.errorName;
        const summary = error.closest('[data-validation-summary]');
        const containingForm = error.closest('form');
        const scope = error.dataset.formId || summary?.dataset.formId || containingForm?.dataset.formId;
        const bag = error.dataset.errorBag || summary?.dataset.errorBag || containingForm?.dataset.errorBag || 'default';
        const candidates = forms.filter((form) =>
            (!scope || form.dataset.formId === scope || form.id === scope)
            && (form.dataset.errorBag || 'default') === bag
            && findErrorField(form, name));
        if (candidates.length !== 1) return;
        const field = findErrorField(candidates[0], name);
        for (let ancestor = field.parentElement; ancestor; ancestor = ancestor.parentElement) {
            if (ancestor.matches('details')) ancestor.open = true;
        }
        if (!error.id) error.id = uniqueId(document, `${field.id}-error`);
        controlsIn(candidates[0]).filter((control) => matchesField(control, name)).forEach((control) => {
            control.setAttribute('aria-invalid', 'true');
            describe(control, error.id);
        });
        if (!summary) return;
        let link = error.querySelector('[data-error-link]');
        if (!link) {
            link = document.createElement('a');
            link.dataset.errorLink = '';
            link.className = 'underline underline-offset-2';
            link.textContent = error.textContent.trim();
            error.replaceChildren(link);
        }
        link.setAttribute('href', `#${field.id}`);
        if (!linkedErrors.has(link)) {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                focusField(field);
            });
            linkedErrors.add(link);
        }
    });
    const summary = root.querySelector('[data-validation-summary]');
    if (root === document && summary && !summary.dataset.focused) {
        summary.dataset.focused = 'true';
        summary.focus({ preventScroll: true });
    }
}
