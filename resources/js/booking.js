import flatpickr from 'flatpickr';
import { Indonesian } from 'flatpickr/dist/l10n/id.js';

const dateKey = (date) => flatpickr.formatDate(date, 'Y-m-d');
const monthKey = (year, month) => new Date(Date.UTC(year, month, 1)).toISOString().slice(0, 7);
const money = (value) => `Rp ${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value)}`;

export function initializeBookingForm(form) {
    if (!form) return;
    const start = form.querySelector('[name="start_date"]');
    const end = form.querySelector('[name="end_date"]');
    const quantity = form.querySelector('[name="quantity"]');
    const calendar = form.querySelector('[data-booking-calendar]');
    const range = form.querySelector('[data-booking-range]');
    const error = form.querySelector('[data-calendar-error]');
    const message = form.querySelector('[data-availability-message]');
    const nativeLabels = [...form.querySelectorAll('[data-booking-native-date]')];
    const dayStock = new Map();
    let today = form.dataset.today;
    let calendarRequest;
    let estimateRequest;
    let calendarReady = false;
    let verified = false;
    let submitting = false;
    let calendarEpoch = 0;
    const units = () => Number(quantity.value || 1);
    const selectedRangeIsBlocked = () => {
        if (!start.value || !end.value) return false;
        return [...dayStock].some(([day, stock]) => day >= start.value && day <= end.value && stock < units());
    };
    const updatePrices = () => {
        const days = start.value && end.value && end.value >= start.value
            ? Math.round((Date.parse(`${end.value}T00:00:00Z`) - Date.parse(`${start.value}T00:00:00Z`)) / 86400000) + 1 : 0;
        const price = Number(form.dataset.dailyPrice);
        const deposit = Number(form.dataset.deposit) * units();
        form.querySelector('[data-rental-days]').textContent = `${days} hari`;
        form.querySelector('[data-price-formula]').textContent = `${money(price)} x ${days} hari x ${units()} unit`;
        form.querySelector('[data-subtotal]').textContent = money(price * days * units());
        form.querySelector('[data-deposit-total]').textContent = money(deposit);
        form.querySelector('[data-total]').textContent = money(price * days * units() + deposit);
    };
    const checkAvailability = async () => {
        estimateRequest?.abort();
        updatePrices();
        if (!start.value || !end.value || start.value < today || end.value < start.value) {
            message.textContent = 'Periode sewa belum lengkap atau tidak valid.';
            return false;
        }
        if (!Number.isInteger(units()) || units() < 1 || units() > Number(quantity.max)) return false;
        if (selectedRangeIsBlocked()) {
            message.textContent = 'Ada tanggal dengan stok tidak cukup di dalam periode ini.';
            return false;
        }
        const controller = new AbortController();
        estimateRequest = controller;
        try {
            const response = await fetch(`${form.dataset.availabilityUrl}?${new URLSearchParams({ start_date: start.value, end_date: end.value })}`, {
                headers: { Accept: 'application/json' }, signal: controller.signal, cache: 'no-store',
            });
            if (!response.ok) {
                message.textContent = 'Periode ini tidak valid atau barang tidak tersedia.';
                return false;
            }
            const data = await response.json();
            if (controller.signal.aborted) return false;
            message.textContent = `Tersedia ${data.available_quantity} dari ${data.item_quantity} unit pada periode ini.`;
            return data.available_quantity >= units();
        } catch (exception) {
            if (exception.name === 'AbortError') return false;
            message.textContent = 'Ketersediaan akan diperiksa kembali saat pengajuan diterima.';
            return true;
        }
    };

    const picker = flatpickr(range, {
        locale: Indonesian, mode: 'range', dateFormat: 'Y-m-d', altInput: true,
        appendTo: form.querySelector('[data-calendar-surface]'),
        altFormat: 'j M Y', minDate: today, inline: true, disableMobile: true,
        monthSelectorType: 'static', animate: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        ariaDateFormat: 'j F Y',
        disable: [(date) => !dayStock.has(dateKey(date)) || dayStock.get(dateKey(date)) < units()],
        onDayCreate: (_, __, ___, day) => {
            const key = dateKey(day.dateObj);
            day.dataset.bookingDate = key;
            if (key >= today && dayStock.has(key)) {
                const stock = dayStock.get(key);
                day.classList.toggle('booking-day-unavailable', stock < units());
                day.title = stock < units() ? 'Stok tidak cukup' : `Tersedia ${stock} unit`;
                day.setAttribute('aria-label', `${day.getAttribute('aria-label')}, ${day.title}`);
            }
        },
        onChange: (dates) => {
            start.value = dates[0] ? dateKey(dates[0]) : '';
            end.value = dates[1] ? dateKey(dates[1]) : '';
            error.textContent = '';
            checkAvailability();
        },
        onMonthChange: () => loadCalendar(),
        onYearChange: () => loadCalendar(),
        onOpen: () => loadCalendar(),
    });
    calendar.hidden = false;
    picker.altInput.id = 'rental-range-display';
    calendar.querySelector('label').htmlFor = picker.altInput.id;
    form.querySelector('[data-booking-clear]').addEventListener('click', () => {
        picker.clear(true);
        picker.altInput.focus();
    });
    const enableCalendar = () => {
        nativeLabels.forEach(label => { label.hidden = true; });
        start.required = false;
        end.required = false;
        picker.calendarContainer.hidden = false;
        picker.altInput.hidden = false;
    };
    const loadCalendar = async () => {
        const epoch = ++calendarEpoch;
        calendarRequest?.abort();
        const controller = new AbortController();
        calendarRequest = controller;
        try {
            const months = [-1, 0, 1].map(offset => monthKey(picker.currentYear, picker.currentMonth + offset));
            const responses = await Promise.all(months.map(async month => {
                const response = await fetch(`${form.dataset.calendarUrl}?${new URLSearchParams({ month })}`, {
                    headers: { Accept: 'application/json' }, cache: 'no-store', signal: controller.signal,
                });
                if (!response.ok) throw new Error('Calendar unavailable');
                return response.json();
            }));
            if (epoch !== calendarEpoch) return;
            responses.forEach(data => {
                today = data.today;
                quantity.max = data.item_quantity;
                data.days.forEach(day => dayStock.set(day.date, day.available_quantity));
            });
            enableCalendar();
            error.textContent = '';
            picker.set('minDate', today);
            if (!calendarReady && start.value && end.value) {
                picker.setDate([start.value, end.value], false);
            }
            calendarReady = true;
            picker.redraw();
            if (selectedRangeIsBlocked()) {
                error.textContent = 'Stok pada periode pilihanmu berubah. Pilih periode lain atau kurangi jumlah unit.';
            }
            checkAvailability();
        } catch (exception) {
            if (exception.name === 'AbortError') return;
            picker.calendarContainer.hidden = true;
            picker.altInput.hidden = true;
            nativeLabels.forEach(label => { label.hidden = false; });
            start.required = true;
            end.required = true;
            error.textContent = 'Kalender belum dapat dimuat. Ketersediaan tanggal diperiksa saat pengajuan.';
        }
    };
    const recipient = form.querySelector('[data-recipient-fields]');
    const updateRecipient = () => {
        if (!recipient) return;
        const delivery = form.querySelector('[name="fulfillment_method"]:checked')?.value === 'delivery';
        recipient.hidden = !delivery;
        recipient.querySelectorAll('input, textarea').forEach(input => {
            input.required = delivery;
            input.disabled = !delivery;
        });
    };
    form.querySelectorAll('[name="fulfillment_method"]').forEach(input => input.addEventListener('change', updateRecipient));
    updateRecipient();
    [start, end].forEach(input => input.addEventListener('input', checkAvailability));
    quantity.addEventListener('input', () => {
        picker.redraw();
        checkAvailability();
        loadCalendar();
    });
    form.addEventListener('submit', async event => {
        if (verified) return;
        event.preventDefault();
        if (submitting) return;
        submitting = true;
        const button = event.submitter;
        if (button) button.disabled = true;
        const available = await checkAvailability();
        submitting = false;
        if (button) button.disabled = false;
        if (!available) {
            error.textContent = 'Lengkapi periode yang valid dengan stok yang cukup sebelum mengajukan sewa.';
            if (picker.altInput.hidden) {
                start.focus();
            } else {
                picker.altInput.focus();
            }
            return;
        }
        verified = true;
        form.requestSubmit(button);
        verified = false;
    });
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) loadCalendar();
    });
    window.addEventListener('focus', loadCalendar);
    let visible = true;
    const observer = new IntersectionObserver(entries => {
        visible = entries[0].isIntersecting;
        if (visible) loadCalendar();
    });
    observer.observe(calendar);
    const refreshWhenVisible = () => {
        if (visible && !document.hidden) loadCalendar();
    };
    let interval = window.setInterval(refreshWhenVisible, 30000);
    window.addEventListener('pagehide', () => {
        calendarRequest?.abort();
        estimateRequest?.abort();
        window.clearInterval(interval);
        observer.disconnect();
    });
    window.addEventListener('pageshow', event => {
        if (event.persisted) {
            observer.observe(calendar);
            interval = window.setInterval(refreshWhenVisible, 30000);
            loadCalendar();
        }
    });
    loadCalendar();
    updatePrices();
}
