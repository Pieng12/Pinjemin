import { availableGuides, guideStage, guideInstruction } from './guide-catalog';

export function initializeGuides({ root, config, startTour, closeAssistant, showGuides, announce }) {
    const guides = new Map(availableGuides(config).map(guide => [guide.id, guide]));
    const key = 'pinjemin.assistant.guide';
    const lifetime = 30 * 60 * 1000;
    const picker = root.querySelector('[data-guide-picker]');
    const detail = root.querySelector('[data-guide-detail]');
    const list = root.querySelector('[data-guide-list]');
    const feedback = root.querySelector('[data-guide-feedback]');
    let state = null;
    let selected = null;
    let pickerRequested = false;
    let timer = null;
    let storageWorks = true;
    const remove = () => { try { sessionStorage.removeItem(key); } catch {} };
    const read = (clearInvalid = true) => {
        try {
            const saved = JSON.parse(sessionStorage.getItem(key));
            const valid = saved?.version === 1 && guides.has(saved.goal) && Number.isFinite(saved.expiresAt) && saved.expiresAt > Date.now() && saved.expiresAt <= Date.now() + lifetime && Array.isArray(saved.seen);
            const enteringAccount = valid && saved.scope === 'guest' && config.scope !== 'guest' && saved.awaitingAuth === true;
            if (valid && (saved.scope === config.scope || enteringAccount)) {
                return { version: 1, goal: saved.goal, scope: config.scope, expiresAt: saved.expiresAt, seen: saved.seen.filter(value => typeof value === 'string' && value.length < 80).slice(-12), awaitingAuth: saved.awaitingAuth === true, auto: saved.auto === true };
            }
            if (clearInvalid) remove();
        } catch { storageWorks = false; if (clearInvalid) remove(); }
        return null;
    };
    const save = () => {
        if (!state) return;
        state.expiresAt = Date.now() + lifetime;
        try { sessionStorage.setItem(key, JSON.stringify(state)); storageWorks = true; } catch { storageWorks = false; }
    };
    const message = text => {
        feedback.textContent = text;
        feedback.hidden = !text;
        if (text) announce(text);
    };
    const stageKey = (goal, stage) => `${goal}:${stage}${stage === 'auth' ? `:${config.page}` : stage === 'booking' ? (document.querySelector('[data-payment-form]') ? ':payment' : document.querySelector('[data-inspection-panel]') ? ':inspection' : ':waiting') : ''}`;
    const render = () => {
        const guide = guides.get(selected);
        picker.hidden = !!guide;
        detail.hidden = !guide;
        if (!guide) return;
        const stage = guideStage(config, guide.id);
        const active = state?.goal === guide.id;
        const seen = active && state.seen.includes(stageKey(guide.id, stage));
        root.querySelector('[data-guide-title]').textContent = guide.title;
        root.querySelector('[data-guide-description]').textContent = guide.description;
        const roadmap = root.querySelector('[data-guide-roadmap]');
        roadmap.replaceChildren();
        guide.steps.forEach(([id, title, description], index) => {
            const item = document.createElement('li');
            if (id === stage || (stage === 'bookings' && id === 'catalog')) item.setAttribute('aria-current', 'step');
            const number = document.createElement('span');
            number.className = 'assistant-guide-number';
            number.textContent = String(index + 1).padStart(2, '0');
            const copy = document.createElement('div');
            const heading = document.createElement('strong');
            heading.textContent = title;
            const text = document.createElement('p');
            text.textContent = description;
            copy.append(heading, text);
            item.append(number, copy);
            roadmap.append(item);
        });
        const guest = config.scope === 'guest';
        const instruction = stage ? guideInstruction(guide.id, stage) : null;
        root.querySelector('[data-guide-instruction]').textContent = instruction?.text || (guest ? 'Mulai dari masuk atau daftar. Setelah berhasil masuk, panduan akan diarahkan ke halaman yang sesuai.' : `Panduan dimulai dari ${guide.entryLabel}. Panduan akan membuka halaman tersebut dan menyorot kontrol yang relevan.`);
        const start = root.querySelector('[data-guide-start]');
        start.textContent = guest && stage !== 'auth' ? 'Mulai dari masuk akun' : stage ? (seen ? 'Ulangi sorotan langkah ini' : 'Mulai sorotan langkah ini') : `Mulai dari ${guide.entryLabel}`;
        const usePage = root.querySelector('[data-guide-use-page]');
        usePage.hidden = !stage || !seen;
        usePage.textContent = instruction?.label || 'Lanjutkan pada halaman ini';
        root.querySelector('[data-guide-end]').hidden = !active;
    };
    const select = id => {
        if (!guides.has(id)) return;
        clearTimeout(timer);
        pickerRequested = false;
        selected = id;
        message('');
        render();
        root.querySelector('[data-assistant-guides]').scrollTop = 0;
        root.querySelector('[data-guide-back]').focus({ preventScroll: true });
    };
    const navigate = destination => {
        const value = config.navigation?.[destination]?.url;
        if (!value) { message('Halaman tujuan belum tersedia untuk akun ini. Pilih panduan yang lain.'); return; }
        const url = new URL(value, window.location.href);
        if (url.origin !== location.origin || !['http:', 'https:'].includes(url.protocol)) return;
        save();
        if (!storageWorks) {
            message('Penyimpanan browser tidak tersedia. Anda tetap dapat membuka halaman tujuan dan menjalankan panduan halaman di sana.');
            const link = document.createElement('a');
            link.href = url.href;
            link.className = 'assistant-guide-fallback-link';
            link.textContent = 'Buka halaman tujuan';
            feedback.append(link);
            return;
        }
        closeAssistant();
        window.location.assign(url.href);
    };
    const launch = id => {
        const guide = guides.get(id);
        if (!guide) return;
        selected = id;
        if (!state || state.goal !== id || state.expiresAt <= Date.now()) state = { version: 1, goal: id, scope: config.scope, seen: [], awaitingAuth: false, auto: true, expiresAt: Date.now() + lifetime };
        state.auto = true;
        const stage = guideStage(config, id);
        if (config.scope === 'guest') {
            state.awaitingAuth = true;
            save();
            if (stage !== 'auth') { navigate('login'); return; }
        }
        if (!stage) { navigate(guide.entry); return; }
        state.awaitingAuth = stage === 'auth';
        state.seen = [...new Set([...state.seen, stageKey(id, stage)])].slice(-12);
        save();
        render();
        startTour(id);
    };
    const reset = () => {
        clearTimeout(timer);
        remove();
        state = null;
        selected = null;
        pickerRequested = true;
        message('');
        render();
    };
    state = read();
    selected = state?.goal || null;
    [...guides.values()].forEach((guide, index) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'assistant-guide-choice';
        button.dataset.guideChoice = guide.id;
        const number = document.createElement('span');
        number.className = 'assistant-guide-choice-number';
        number.textContent = String(index + 1).padStart(2, '0');
        const copy = document.createElement('span');
        const heading = document.createElement('strong');
        heading.textContent = guide.title;
        const text = document.createElement('small');
        text.textContent = guide.description;
        const arrow = document.createElement('span');
        arrow.className = 'assistant-guide-choice-arrow';
        arrow.textContent = '→';
        arrow.setAttribute('aria-hidden', 'true');
        copy.append(heading, text);
        button.append(number, copy, arrow);
        list.append(button);
    });
    if (!guides.size) {
        picker.querySelector('.assistant-guide-intro p:last-child').textContent = 'Tentang Pinjemin dan fungsi tombol pada halaman ini, atau tanyakan informasi melalui Informasi Layanan.';
    }
    root.addEventListener('click', event => {
        const choice = event.target.closest('[data-guide-choice]');
        if (choice) select(choice.dataset.guideChoice);
    });
    root.querySelector('[data-guide-back]').addEventListener('click', () => { clearTimeout(timer); pickerRequested = true; selected = null; message(''); render(); });
    root.querySelector('[data-guide-start]').addEventListener('click', () => launch(selected));
    root.querySelector('[data-guide-end]').addEventListener('click', () => { reset(); message('Panduan diakhiri. Pilih tujuan lain kapan saja.'); });
    root.querySelector('[data-guide-use-page]').addEventListener('click', () => {
        const instruction = guideInstruction(selected, guideStage(config, selected));
        closeAssistant();
        const target = [...document.querySelectorAll(instruction.selector)].find(node => node.getClientRects().length > 0 && getComputedStyle(node).visibility !== 'hidden');
        if (target) { target.scrollIntoView({ behavior: 'instant', block: 'center' }); target.focus({ preventScroll: true }); }
    });
    const resume = () => {
        if (!state || !state.auto) return;
        if (state.expiresAt <= Date.now()) { reset(); return; }
        if (state.awaitingAuth && config.scope !== 'guest') {
            state.awaitingAuth = false;
            save();
            if (!guideStage(config, state.goal)) { navigate(guides.get(state.goal).entry); return; }
        }
        const stage = guideStage(config, state.goal);
        if (stage && !state.seen.includes(stageKey(state.goal, stage))) {
            selected = state.goal;
            showGuides();
            launch(state.goal);
        }
    };
    render();
    timer = setTimeout(resume, 200);
    window.addEventListener('pagehide', () => clearTimeout(timer));
    return {
        reset,
        refresh() { state = read(false); if (state && !selected && !pickerRequested) selected = state.goal; if (!state) selected = null; render(); },
        completed(completed, goal) {
            if (goal !== 'page' && guides.has(goal)) { pickerRequested = false; selected = goal; }
            render();
            message(completed ? (goal === 'page' ? 'Sorotan halaman selesai. Pilih tujuan di bawah untuk melanjutkan dengan panduan bertahap.' : 'Sorotan bagian ini selesai. Ikuti petunjuk berikutnya untuk melanjutkan.') : 'Sorotan ditutup. Anda dapat mengulanginya atau melanjutkan panduan.');
            if (goal !== 'page') requestAnimationFrame(() => {
                const panel = root.querySelector('[data-assistant-guides]');
                panel.scrollTop = panel.scrollHeight;
            });
        },
    };
}
