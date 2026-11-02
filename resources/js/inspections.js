const initializeInspectionUploads = () => {
    document.querySelectorAll('[data-condition-upload]').forEach(form => {
        if (form.dataset.conditionInitialized) return;
        form.dataset.conditionInitialized = 'true';
        const input = form.querySelector('[data-condition-photos]');
        const preview = form.querySelector('[data-condition-preview]');
        const error = form.querySelector('[data-condition-error]');
        if (!input || !preview || !error) return;
        let urls = [];
        const render = () => {
            urls.forEach(url => URL.revokeObjectURL(url));
            urls = [];
            preview.replaceChildren();
            const files = [...input.files];
            const invalid = files.length > 5 || files.some(file => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024);
            error.hidden = !invalid;
            error.textContent = invalid ? 'Pilih maksimal 5 foto JPG, PNG, atau WebP, masing-masing maksimal 5 MB.' : '';
            input.setCustomValidity(invalid ? error.textContent : '');
            preview.hidden = invalid || files.length === 0;
            if (invalid) return;
            files.forEach(file => {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                urls.push(img.src);
                img.alt = 'Preview foto kondisi';
                img.className = 'h-40 w-full rounded-md border border-zinc-200 bg-white object-contain';
                img.addEventListener('error', () => { img.replaceWith(document.createTextNode('Preview tidak tersedia: ' + file.name)); });
                preview.append(img);
            });
        };
        input.addEventListener('change', render);
        window.addEventListener('pagehide', () => urls.forEach(url => URL.revokeObjectURL(url)));
        window.addEventListener('pageshow', render);
    });
};

const initializeInspectionStatus = () => {
    const panel = document.querySelector('[data-inspection-panel]');
    if (!panel) return;
    let fingerprint;
    let pending = false;
    const update = async () => {
        if (document.hidden || pending) return;
        pending = true;
        try {
            const response = await fetch(panel.dataset.statusUrl, {headers: {Accept: 'application/json'}});
            if (!response.ok) return;
            const state = await response.json();
            if (fingerprint && fingerprint !== state.fingerprint) panel.querySelector('[data-inspection-changed]').hidden = false;
            fingerprint ??= state.fingerprint;
        } catch { /* Formulir tetap dapat digunakan tanpa koneksi pembaruan. */ }
        finally { pending = false; }
    };
    const clocks = () => panel.querySelectorAll('[data-inspection-deadline]').forEach(element => {
        const remaining = Math.max(0, Math.ceil((Date.parse(element.dataset.inspectionDeadline) - Date.now()) / 1000));
        element.textContent = remaining ? `(${Math.floor(remaining / 60)} menit ${remaining % 60} detik)` : '(batas unggah telah lewat; muat ulang untuk status terbaru)';
    });
    update();
    clocks();
    let pollTimer = setInterval(update, 30000);
    let clockTimer = setInterval(clocks, 1000);
    document.addEventListener('visibilitychange', update);
    window.addEventListener('pagehide', () => { clearInterval(pollTimer); clearInterval(clockTimer); pollTimer = null; clockTimer = null; });
    window.addEventListener('pageshow', () => {
        if (pollTimer === null) pollTimer = setInterval(update, 30000);
        if (clockTimer === null) clockTimer = setInterval(clocks, 1000);
        update();
        clocks();
    });
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => { initializeInspectionUploads(); initializeInspectionStatus(); });
else { initializeInspectionUploads(); initializeInspectionStatus(); }
