async function requestJson(url, data) {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        ...(data ? { method: 'POST', body: data } : {}),
        cache: 'no-store',
        ...(typeof AbortSignal.timeout === 'function' ? { signal: AbortSignal.timeout(10000) } : {}),
    });
    if (response.status === 204) return null;
    if (response.status === 413) throw new Error('Pengiriman melebihi batas ukuran unggahan. Kurangi jumlah lampiran dan coba kembali.');
    if (response.status === 401 || response.status === 419 || response.redirected) {
        throw new Error('Sesi Anda telah berakhir. Masuk kembali sebelum melanjutkan.');
    }
    const payload = await response.json();
    if (!response.ok) {
        throw new Error(Object.values(payload.errors ?? {}).flat()[0] || payload.message);
    }

    return payload;
}

const isAtBottom = element => element.scrollHeight - element.scrollTop - element.clientHeight < 45;

function initializeFilePreviews(input, list, error, scroller) {
    const maxFiles = Number(input.dataset.maxFiles);
    const maxBytes = Number(input.dataset.maxBytes);
    const previewUrls = [];
    const validate = () => {
        if (input.files.length > maxFiles) return 'Maksimal 3 lampiran per pesan.';
        if ([...input.files].some(file => file.size > maxBytes)) return 'Ukuran tiap lampiran maksimal 5 MB.';
        return '';
    };
    const render = () => {
        const follow = scroller && isAtBottom(scroller);
        previewUrls.splice(0).forEach(url => URL.revokeObjectURL(url));
        list.replaceChildren();
        list.hidden = input.files.length === 0;
        [...input.files].slice(0, maxFiles).forEach((file, index) => {
            const preview = document.createElement('li');
            preview.className = 'flex min-w-0 max-w-full items-center gap-2 rounded-md border border-brand-100 bg-brand-50 px-2 py-1 text-xs';
            if (['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                const image = document.createElement('img');
                image.alt = '';
                image.className = 'size-10 shrink-0 rounded-sm bg-white object-contain';
                image.src = URL.createObjectURL(file);
                previewUrls.push(image.src);
                preview.append(image);
            }
            const name = document.createElement('span');
            name.className = 'min-w-0 truncate';
            name.textContent = file.name;
            name.title = file.name;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'min-h-9 shrink-0 px-1 font-semibold text-brand-700 hover:underline disabled:opacity-50';
            remove.textContent = 'Hapus';
            remove.setAttribute('aria-label', `Hapus ${file.name}`);
            remove.addEventListener('click', () => {
                if (typeof DataTransfer === 'function') {
                    const next = new DataTransfer();
                    [...input.files].forEach((entry, position) => {
                        if (position !== index) next.items.add(entry);
                    });
                    input.files = next.files;
                } else {
                    input.value = '';
                }
                render();
                input.focus();
            });
            preview.append(name, remove);
            list.append(preview);
        });
        error.textContent = validate();
        if (follow) scroller.scrollTop = scroller.scrollHeight;
    };
    input.addEventListener('change', render);
    return { render, validate };
}

export function initializeChat(onInboxUpdated) {
    const inbox = document.querySelector('[data-chat-inbox]');
    const thread = document.querySelector('[data-chat-thread]');
    const compose = document.querySelector('[data-chat-compose]');

    if (!inbox && !compose) return;

    let inboxLoading = false;
    let lastInboxMarkup;
    async function refreshInbox() {
        if (!inbox || inboxLoading || document.hidden || !inbox.getClientRects().length || inbox.contains(document.activeElement)) return;
        inboxLoading = true;
        try {
            const url = new URL(inbox.dataset.chatInbox, window.location.href);
            if (inbox.dataset.selectedChat) url.searchParams.set('selected', inbox.dataset.selectedChat);
            const { html } = await requestJson(url);
            if (document.hidden || inbox.contains(document.activeElement) || html === lastInboxMarkup) return;
            inbox.innerHTML = html;
            lastInboxMarkup = html;
            onInboxUpdated(inbox);
        } catch {
            // Keep the current list available when a refresh fails.
        } finally {
            inboxLoading = false;
        }
    }

    const messages = thread?.querySelector('[data-chat-messages]');
    const scroller = thread?.querySelector('[data-chat-scroll]');
    const newNotice = thread?.querySelector('[data-chat-new]');
    const readForm = thread?.querySelector('[data-chat-read]');
    const readThrough = readForm?.elements.namedItem('through');
    const body = compose?.elements.namedItem('body');
    const attachmentInput = compose?.querySelector('[data-chat-files]');
    const attachmentList = compose?.querySelector('[data-chat-file-previews]');
    const submission = compose?.elements.namedItem('submission_id');
    const sendButton = compose?.querySelector('button[type="submit"]');
    const error = compose?.querySelector('[data-chat-error]');
    const quickReplies = compose?.querySelector('[data-chat-quick-replies]');
    const previews = attachmentInput ? initializeFilePreviews(attachmentInput, attachmentList, error, scroller) : null;
    const readOnlyNotice = thread?.querySelector('[data-chat-read-only]');
    const history = thread?.dataset.history === 'true';

    let lastId = Number(messages?.lastElementChild?.dataset.chatMessage || 0);
    let readId = Number(thread?.dataset.readId || 0);
    let threadLoading = false;
    let refreshAfterSend = false;
    let marking = false;
    let sending = false;
    let readOnly = Boolean(body?.disabled);

    const toBottom = () => { scroller.scrollTop = scroller.scrollHeight; };
    const syncControls = () => {
        if (body) body.disabled = readOnly;
        if (sendButton) sendButton.disabled = readOnly || sending;
        if (quickReplies) {
            quickReplies.hidden = readOnly;
            quickReplies.querySelectorAll('[data-quick-reply]').forEach(button => { button.disabled = sending; });
        }
        if (attachmentInput) {
            attachmentInput.disabled = readOnly || sending;
            attachmentList.querySelectorAll('button').forEach(button => { button.disabled = attachmentInput.disabled; });
        }
    };

    if (quickReplies) {
        quickReplies.hidden = readOnly;
        quickReplies.addEventListener('click', event => {
            const option = event.target.closest('[data-quick-reply]');
            if (!option || readOnly || sending) return;
            const draft = body.value.trimEnd();
            const next = draft ? `${draft}\n${option.dataset.quickReply}` : option.dataset.quickReply;
            if (next.length > body.maxLength) {
                error.textContent = 'Pesan terlalu panjang untuk menambahkan teks cepat. Ringkas draf terlebih dahulu.';
                body.focus();
                return;
            }
            body.value = next;
            quickReplies.open = false;
            body.focus();
            body.setSelectionRange(next.length, next.length);
        });
    }

    async function markRead() {
        if (!readForm || marking || history || document.hidden || !isAtBottom(scroller)) return;
        const bounds = scroller.getBoundingClientRect();
        if (bounds.bottom <= 0 || bounds.top >= window.innerHeight) return;
        const through = Number(readThrough.value);
        if (through <= readId) return;
        marking = true;
        try {
            await requestJson(readForm.action, new FormData(readForm));
            readId = through;
            void refreshInbox();
        } catch {
            // A later refresh can retry with the same read position.
        } finally {
            marking = false;
        }
    }

    async function refreshThread(forceBottom = false) {
        if (!thread || history || document.hidden) return;
        if (threadLoading) {
            if (forceBottom) refreshAfterSend = true;
            return;
        }
        threadLoading = true;
        try {
            let more;
            do {
                const url = new URL(thread.dataset.updatesUrl, window.location.href);
                url.searchParams.set('after', String(lastId));
                const update = await requestJson(url);
                if (document.hidden) break;
                readOnly = update.read_only;
                readOnlyNotice.hidden = !readOnly;
                readOnlyNotice.textContent = update.read_only_reason ?? '';
                syncControls();
                if (update.last_id) {
                    const follow = forceBottom || isAtBottom(scroller) && newNotice.hidden;
                    messages.insertAdjacentHTML('beforeend', update.html);
                    lastId = Number(update.last_id);
                    if (update.incoming_id) {
                        readThrough.value = update.incoming_id;
                        thread.querySelector('[data-chat-announcement]').textContent = 'Pesan baru diterima.';
                    }
                    if (follow) {
                        toBottom();
                        newNotice.hidden = true;
                    } else {
                        newNotice.hidden = false;
                    }
                }
                more = update.has_more;
            } while (more);
        } catch {
            // The thread and unsent draft stay visible until the connection returns.
        } finally {
            threadLoading = false;
            if (refreshAfterSend) {
                refreshAfterSend = false;
                void refreshThread(true);
            } else {
                void markRead();
            }
        }
    }

    compose?.addEventListener('submit', async event => {
        if (!window.fetch) return;
        event.preventDefault();
        if (sending || readOnly) return;
        const fileError = previews?.validate();
        if (fileError || (!body.value.trim() && !attachmentInput?.files.length)) {
            error.textContent = fileError || 'Tulis pesan atau pilih lampiran untuk dikirim.';
            (fileError ? attachmentInput : body).focus();
            return;
        }
        sending = true;
        error.textContent = '';
        body.removeAttribute('aria-invalid');
        const data = new FormData(compose);
        syncControls();
        try {
            const result = await requestJson(compose.action, data);
            if (!thread || history) {
                window.location.assign(result.redirect);
                return;
            }
            if (body.value === data.get('body')) body.value = '';
            if (attachmentInput) {
                attachmentInput.value = '';
                previews.render();
            }
            submission.value = result.submission_id;
            toBottom();
            newNotice.hidden = true;
            void refreshThread(true);
            void refreshInbox();
        } catch (failure) {
            error.textContent = failure instanceof TypeError || failure instanceof SyntaxError || failure.name === 'TimeoutError' || !failure.message
                ? 'Pesan belum dapat dikirim. Periksa koneksi dan coba kembali.'
                : failure.message;
            body.setAttribute('aria-invalid', 'true');
        } finally {
            sending = false;
            syncControls();
        }
    });

    if (scroller && !history) {
        toBottom();
        scroller.addEventListener('scroll', markRead, { passive: true });
        window.addEventListener('scroll', markRead, { passive: true });
        newNotice.querySelector('button').addEventListener('click', () => {
            toBottom();
            newNotice.hidden = true;
            void markRead();
            scroller.focus({ preventScroll: true });
        });
        void markRead();
        window.setInterval(refreshThread, 8000);
    }

    if (inbox) window.setInterval(refreshInbox, 20000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            void refreshThread();
            void refreshInbox();
        }
    });
}
