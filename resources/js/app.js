import {
    ArrowRight,
    Download,
    ArrowLeft,
    ArrowUpRight,
    CalendarDays,
    CheckCircle2,
    ChevronDown,
    ClipboardCheck,
    Clock,
    CreditCard,
    createIcons,
    Handshake,
    LayoutDashboard,
    Menu,
    PackageCheck,
    Package,
    Inbox,
    PanelLeftClose,
    PanelLeftOpen,
    Plus,
    Pencil,
    ReceiptText,
    Search,
    SlidersHorizontal,
    ShieldCheck,
    Store,
    UserCheck,
    X,
} from 'lucide';
import { initializeBookingForm } from './booking';

const formatRupiah = (value) => `Rp ${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value || 0))}`;
const onlyDigits = (value) => String(value || '').replace(/\D/g, '');

document.addEventListener('DOMContentLoaded', () => {
    createIcons({
        icons: {
            ArrowRight,
            Download,
            ArrowLeft,
            ArrowUpRight,
            CalendarDays,
            CheckCircle2,
            ChevronDown,
            ClipboardCheck,
            Clock,
            CreditCard,
            Handshake,
            LayoutDashboard,
            Menu,
            PackageCheck,
            Package,
            Inbox,
            PanelLeftClose,
            PanelLeftOpen,
            Plus,
            Pencil,
            ReceiptText,
            Search,
            SlidersHorizontal,
            ShieldCheck,
            Store,
            UserCheck,
            X,
        },
    });

    document.querySelectorAll('[data-item-photo]').forEach((image) => {
        const showFallback = () => {
            image.hidden = true;
            image.closest('[data-item-image]').querySelector('[data-photo-fallback]').hidden = false;
        };
        image.addEventListener('error', showFallback, { once: true });
        if (image.complete && image.naturalWidth === 0) showFallback();
    });

    document.querySelectorAll('[data-user-avatar]').forEach((avatar) => {
        const image = avatar.querySelector('[data-avatar-photo]');
        const showInitial = () => {
            image.hidden = true;
            avatar.querySelector('[data-avatar-initial]').hidden = false;
        };
        image.addEventListener('error', showInitial);
        if (image.hasAttribute('src') && image.complete && !image.naturalWidth) showInitial();
    });

    const profilePhotoForm = document.querySelector('[data-profile-photo-upload]');
    if (profilePhotoForm) {
        const input = profilePhotoForm.querySelector('[data-profile-photo-input]');
        const avatar = profilePhotoForm.querySelector('[data-profile-avatar]');
        const image = avatar.querySelector('[data-avatar-photo]');
        const initial = avatar.querySelector('[data-avatar-initial]');
        const error = profilePhotoForm.querySelector('[data-profile-photo-error]');
        const status = profilePhotoForm.querySelector('[data-profile-photo-status]');
        const reset = profilePhotoForm.querySelector('[data-profile-photo-reset]');
        const remove = profilePhotoForm.querySelector('[data-profile-photo-remove]');
        const savedPhoto = image.getAttribute('src');
        let previewUrl = null;

        const releasePreview = () => {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        };
        const showPhoto = (url) => {
            image.hidden = !url;
            initial.hidden = Boolean(url);
            if (url) image.src = url;
            else image.removeAttribute('src');
        };
        const restorePhoto = () => {
            releasePreview();
            showPhoto(remove?.checked ? null : savedPhoto);
            reset.hidden = true;
            status.textContent = remove?.checked ? 'Foto akan dihapus setelah profil disimpan.' : '';
        };
        input.addEventListener('change', () => {
            const file = input.files[0];
            error.textContent = '';
            input.removeAttribute('aria-invalid');
            if (!file) return restorePhoto();
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
                input.value = '';
                restorePhoto();
                error.textContent = 'Pilih foto JPG, PNG, atau WebP dengan ukuran maksimal 5 MB.';
                input.setAttribute('aria-invalid', 'true');
                return;
            }
            releasePreview();
            if (remove) remove.checked = false;
            previewUrl = URL.createObjectURL(file);
            showPhoto(previewUrl);
            reset.hidden = false;
            status.textContent = 'Foto baru akan digunakan setelah profil disimpan.';
        });
        reset.addEventListener('click', () => {
            input.value = '';
            error.textContent = '';
            input.removeAttribute('aria-invalid');
            restorePhoto();
            input.focus();
        });
        remove?.addEventListener('change', () => {
            input.value = '';
            error.textContent = '';
            input.removeAttribute('aria-invalid');
            restorePhoto();
        });
        if (remove?.checked) restorePhoto();
        window.addEventListener('pagehide', releasePreview);
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) input.dispatchEvent(new Event('change'));
        });
    }

    document.querySelectorAll('[data-catalog-sort]').forEach((select) => {
        select.addEventListener('change', () => select.form.requestSubmit());
    });

    const ownerWorkspace = document.querySelector('[data-owner-workspace]');
    if (ownerWorkspace) {
        const sidebar = ownerWorkspace.querySelector('[data-owner-sidebar]');
        const collapseButton = ownerWorkspace.querySelector('[data-owner-collapse]');
        const openButton = ownerWorkspace.querySelector('[data-owner-open]');
        const backdrop = ownerWorkspace.querySelector('[data-owner-backdrop]');
        const content = ownerWorkspace.querySelector('.owner-content');
        const desktop = window.matchMedia('(min-width: 1024px)');
        let previousOverflow = document.body.style.overflow;

        const syncCollapsed = () => {
            const collapsed = document.documentElement.dataset.ownerCollapsed === 'true';
            const label = collapsed ? 'Buka sidebar' : 'Ciutkan sidebar';
            collapseButton.setAttribute('aria-expanded', String(!collapsed));
            collapseButton.setAttribute('aria-label', label);
            collapseButton.title = label;
            collapseButton.querySelector('[data-collapse-icon]').toggleAttribute('hidden', collapsed);
            collapseButton.querySelector('[data-expand-icon]').toggleAttribute('hidden', !collapsed);
        };
        syncCollapsed();
        collapseButton.addEventListener('click', () => {
            const collapsed = document.documentElement.dataset.ownerCollapsed !== 'true';
            document.documentElement.dataset.ownerCollapsed = String(collapsed);
            try { localStorage.setItem('pinjemin.ownerSidebarCollapsed', String(collapsed)); } catch {}
            syncCollapsed();
        });

        const setDrawerOpen = (isOpen, restoreFocus = false) => {
            const wasOpen = ownerWorkspace.dataset.drawerOpen === 'true';
            const open = isOpen && !desktop.matches;
            ownerWorkspace.dataset.drawerOpen = String(open);
            openButton.setAttribute('aria-expanded', String(open));
            sidebar.inert = !desktop.matches && !open;
            content.inert = open;
            backdrop.hidden = !open;
            if (open) {
                if (!wasOpen) previousOverflow = document.body.style.overflow;
                document.body.style.overflow = 'hidden';
                sidebar.setAttribute('role', 'dialog');
                sidebar.setAttribute('aria-modal', 'true');
                sidebar.querySelector('[data-owner-close]').focus();
            } else {
                document.body.style.overflow = previousOverflow;
                sidebar.removeAttribute('role');
                sidebar.removeAttribute('aria-modal');
                if (restoreFocus) openButton.focus();
            }
        };
        setDrawerOpen(false);
        openButton.addEventListener('click', () => setDrawerOpen(true));
        ownerWorkspace.querySelector('[data-owner-close]').addEventListener('click', () => setDrawerOpen(false, true));
        backdrop.addEventListener('click', () => setDrawerOpen(false, true));
        sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setDrawerOpen(false)));
        desktop.addEventListener('change', () => setDrawerOpen(false));
        document.addEventListener('keydown', event => {
            if (ownerWorkspace.dataset.drawerOpen !== 'true') return;
            if (event.key === 'Escape') {
                setDrawerOpen(false, true);
                return;
            }
            if (event.key !== 'Tab') return;
            const focusable = [...sidebar.querySelectorAll('a, button')].filter(el => !el.disabled && el.getBoundingClientRect().width > 0);
            const first = focusable[0];
            const last = focusable.at(-1);
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    }

    document.querySelectorAll('[data-photo-upload]').forEach(upload => {
        const input = upload.querySelector('[data-photo-input]');
        const preview = upload.querySelector('[data-photo-preview]');
        const status = upload.querySelector('[data-photo-status]');
        const error = upload.querySelector('[data-photo-error]');
        const remaining = Math.max(0, 5 - Number(upload.dataset.existingCount || 0));
        let objectUrls = [];
        const releaseUrls = () => {
            objectUrls.forEach(url => URL.revokeObjectURL(url));
            objectUrls = [];
        };
        const renderPreviews = () => {
            releaseUrls();
            preview.replaceChildren();
            const files = [...input.files];
            const messages = [];
            if (files.length > remaining) messages.push(`Maksimal ${remaining} foto tambahan; total foto barang tidak boleh lebih dari 5.`);
            files.forEach((file, index) => {
                if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                    messages.push(`${file.name}: gunakan JPG, PNG, atau WEBP.`);
                    return;
                }
                if (file.size > 5 * 1024 * 1024) messages.push(`${file.name}: ukuran maksimum 5 MB.`);
                const figure = document.createElement('figure');
                figure.className = 'photo-preview-card';
                const image = document.createElement('img');
                const objectUrl = URL.createObjectURL(file);
                objectUrls.push(objectUrl);
                image.src = objectUrl;
                image.alt = `Preview ${file.name}`;
                const caption = document.createElement('figcaption');
                caption.className = 'photo-preview-caption';
                const metadata = document.createElement('div');
                metadata.className = 'min-w-0';
                const name = document.createElement('p');
                name.className = 'photo-preview-name';
                name.textContent = file.name;
                const size = document.createElement('p');
                size.className = 'photo-preview-size';
                size.textContent = `${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(file.size / 1024)} KB`;
                metadata.append(name, size);
                caption.append(metadata);
                if ('DataTransfer' in window) {
                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'owner-icon-button';
                    remove.setAttribute('aria-label', `Hapus pilihan ${file.name}`);
                    remove.title = `Hapus pilihan ${file.name}`;
                    const icon = document.createElement('i');
                    icon.dataset.lucide = 'x';
                    icon.className = 'h-4 w-4';
                    icon.setAttribute('aria-hidden', 'true');
                    remove.append(icon);
                    remove.addEventListener('click', () => {
                        const transfer = new DataTransfer();
                        files.forEach((selected, position) => { if (position !== index) transfer.items.add(selected); });
                        input.files = transfer.files;
                        renderPreviews();
                        input.focus();
                    });
                    caption.append(remove);
                }
                image.addEventListener('load', () => { size.textContent += ` / ${image.naturalWidth} x ${image.naturalHeight} px`; }, { once: true });
                image.addEventListener('error', () => {
                    image.remove();
                    name.textContent = `${file.name} (preview tidak tersedia)`;
                }, { once: true });
                figure.append(image, caption);
                preview.append(figure);
            });
            const message = messages.join(' ');
            input.setCustomValidity(message);
            error.textContent = message;
            error.hidden = !message;
            input.setAttribute('aria-invalid', String(Boolean(message)));
            status.textContent = files.length ? `${files.length} foto dipilih` : '';
            createIcons({ icons: { X }, root: preview });
        };
        input.addEventListener('change', renderPreviews);
        window.addEventListener('pagehide', releaseUrls);
        window.addEventListener('pageshow', event => { if (event.persisted) renderPreviews(); });
    });

    const header = document.querySelector('[data-site-header]');
    const navProgress = header?.querySelector('[data-nav-progress]');
    const navGroup = header?.querySelector('[data-nav-group]');
    const syncNavIndicator = () => {
        if (!navGroup) return;
        const activeLink = navGroup.querySelector('.is-active');
        if (!activeLink || navGroup.getBoundingClientRect().width === 0) {
            navGroup.classList.remove('has-indicator');
            return;
        }
        navGroup.style.setProperty('--indicator-x', `${activeLink.offsetLeft}px`);
        navGroup.style.setProperty('--indicator-width', `${activeLink.offsetWidth}px`);
        navGroup.classList.add('has-indicator');
    };
    syncNavIndicator();
    document.fonts.ready.then(syncNavIndicator);
    if (navGroup && 'ResizeObserver' in window) {
        new ResizeObserver(syncNavIndicator).observe(navGroup);
    }
    window.addEventListener('resize', syncNavIndicator, { passive: true });

    let headerFrame = null;
    const syncHeader = () => {
        headerFrame = null;
        if (!header?.hasAttribute('data-transparent-header')) return;
        header.classList.toggle('is-scrolled', window.scrollY > 56);
        if (navProgress) {
            const scrollRange = document.documentElement.scrollHeight - window.innerHeight;
            const progress = scrollRange > 0 ? Math.min(1, Math.max(0, window.scrollY / scrollRange)) : 0;
            navProgress.style.setProperty('--nav-progress', String(progress));
        }
    };

    syncHeader();
    const scheduleHeader = () => {
        if (headerFrame === null) headerFrame = requestAnimationFrame(syncHeader);
    };
    window.addEventListener('scroll', scheduleHeader, { passive: true });
    window.addEventListener('resize', scheduleHeader, { passive: true });

    const mobileMenus = [];
    document.querySelectorAll('[data-mobile-menu-toggle]').forEach((button) => {
        const target = document.querySelector(button.dataset.mobileMenuToggle);
        if (!target) return;

        const setOpen = (isOpen) => {
            target.dataset.open = String(isOpen);
            button.setAttribute('aria-expanded', String(isOpen));
            button.setAttribute('aria-label', isOpen ? 'Tutup menu' : 'Buka menu');
            target.classList.toggle('hidden', !isOpen);
            target.classList.toggle('flex', isOpen);
            header?.classList.toggle('has-open-menu', isOpen);
        };
        mobileMenus.push({ button, target, setOpen });
        button.addEventListener('click', () => {
            setOpen(target.dataset.open !== 'true');
        });
        target.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => setOpen(false));
        });
    });

    const closeDropdowns = () => {
        document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
            dropdown.querySelector('[data-dropdown-menu]')?.classList.add('hidden');
            dropdown.querySelector('[data-dropdown-trigger]')?.setAttribute('aria-expanded', 'false');
        });
    };

    document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
        const trigger = dropdown.querySelector('[data-dropdown-trigger]');
        const menu = dropdown.querySelector('[data-dropdown-menu]');
        if (!trigger || !menu) return;

        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            const isHidden = menu.classList.contains('hidden');
            closeDropdowns();
            menu.classList.toggle('hidden', !isHidden);
            trigger.setAttribute('aria-expanded', String(isHidden));
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-dropdown]')) closeDropdowns();
        mobileMenus.forEach(({ button, target, setOpen }) => {
            if (!target.contains(event.target) && !button.contains(event.target)) setOpen(false);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const openDropdown = document.querySelector('[data-dropdown-trigger][aria-expanded="true"]');
        closeDropdowns();
        openDropdown?.focus();
        mobileMenus.forEach(({ button, target, setOpen }) => {
            if (target.dataset.open !== 'true') return;
            setOpen(false);
            button.focus();
        });
    });

    window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => {
        if (event.matches) mobileMenus.forEach(({ setOpen }) => setOpen(false));
    });

    document.querySelectorAll('[data-faq-group]').forEach((group) => {
        group.querySelectorAll('details').forEach((details) => {
            details.addEventListener('toggle', () => {
                if (!details.open) return;
                group.querySelectorAll('details').forEach((other) => {
                    if (other !== details) other.open = false;
                });
            });
        });
    });

    document.querySelectorAll('[data-currency-display]').forEach((display) => {
        const raw = document.getElementById(display.dataset.target);
        if (!raw) return;

        const sync = () => {
            const digits = onlyDigits(display.value);
            raw.value = digits;
            display.value = digits ? formatRupiah(digits) : '';
        };

        display.value = raw.value || display.value;
        sync();
        display.addEventListener('input', sync);
        display.addEventListener('blur', sync);
    });

    let pendingConfirmForm = null;
    const modal = document.querySelector('[data-confirm-modal]');
    const modalBackdrop = modal?.querySelector('[data-confirm-backdrop]');
    const modalPanel = modal?.querySelector('[data-confirm-panel]');
    const modalTitle = modal?.querySelector('[data-confirm-title]');
    const modalDescription = modal?.querySelector('[data-confirm-description]');
    const modalSubmit = modal?.querySelector('[data-confirm-submit]');
    let previousModalFocus;

    const closeModal = () => {
        if (!modal) return;
        modalBackdrop?.classList.add('opacity-0');
        modalPanel?.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 140);
        pendingConfirmForm = null;
        previousModalFocus?.focus();
    };

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === 'true') return;
            event.preventDefault();
            previousModalFocus = document.activeElement;
            pendingConfirmForm = form;
            if (modalTitle) modalTitle.textContent = form.dataset.confirmTitle || 'Konfirmasi aksi';
            if (modalDescription) modalDescription.textContent = form.dataset.confirmMessage || form.dataset.confirmDescription || 'Lanjutkan aksi ini?';
            if (modalSubmit) modalSubmit.textContent = form.dataset.confirmButton || 'Lanjutkan';
            if (modalSubmit) modalSubmit.disabled = false;
            modal?.classList.remove('hidden');
            modal?.classList.add('flex');
            requestAnimationFrame(() => {
                modalBackdrop?.classList.remove('opacity-0');
                modalPanel?.classList.remove('opacity-0', 'scale-95');
                modalSubmit?.focus();
            });
        });
    });

    document.querySelector('[data-confirm-cancel]')?.addEventListener('click', closeModal);
    document.querySelector('[data-confirm-backdrop]')?.addEventListener('click', closeModal);
    modalSubmit?.addEventListener('click', () => {
        if (!pendingConfirmForm) return;
        if (!pendingConfirmForm.reportValidity()) return;
        pendingConfirmForm.dataset.confirmed = 'true';
        pendingConfirmForm.requestSubmit();
    });

    document.addEventListener('keydown', (event) => {
        if (!modal || modal.classList.contains('hidden')) return;
        if (event.key === 'Escape') closeModal();
        if (event.key === 'Tab') {
            const buttons = [...modal.querySelectorAll('button:not(:disabled)')];
            const first = buttons[0];
            const last = buttons.at(-1);
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        }
    });

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            queueMicrotask(() => {
                if (event.defaultPrevented) return;
                form.querySelectorAll('button[type="submit"]').forEach((button) => {
                    const loading = button.querySelector('[data-submit-loading]');
                    const label = button.querySelector('[data-submit-label]');
                    if (!loading || !label) return;
                    label.classList.add('hidden');
                    loading.classList.remove('hidden');
                    loading.classList.add('inline-flex');
                    button.disabled = true;
                });
            });
        });
    });

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('reveal');
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.12 });

        document.querySelectorAll('[data-reveal], .landing-reveal').forEach((element) => {
            if (element.getBoundingClientRect().top >= window.innerHeight && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                element.classList.add('is-pending');
            }
            observer.observe(element);
        });
    }

    const homeSections = [...document.querySelectorAll('#beranda, #tentang, #cara-kerja, #faq, #kontak')];
    const collage = document.querySelector('[data-home-collage]');
    if (collage) {
        const photos = [...collage.querySelectorAll('[data-collage-photo]')];
        const steps = document.querySelector('[data-scroll-line]');
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const precisePointer = window.matchMedia('(hover: hover) and (pointer: fine) and (min-width: 1024px)');
        let frame = null;
        let heroVisible = true;
        let pointerX = 0;
        let pointerY = 0;
        const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

        const updateCollage = () => {
            frame = null;
            if (reducedMotion.matches) return;
            if (heroVisible) {
                const bounds = collage.getBoundingClientRect();
                photos.forEach((photo) => {
                    const limit = window.innerWidth < 768 ? 12 : 32;
                    const offset = clamp(-bounds.top * Number(photo.dataset.speed), -limit, limit);
                    photo.style.setProperty('--photo-offset', `${offset}px`);
                });
                collage.style.setProperty('--collage-x', `${pointerX * 8}px`);
                collage.style.setProperty('--collage-y', `${pointerY * 8}px`);
                collage.style.setProperty('--tilt-x', `${-pointerY * 3}deg`);
                collage.style.setProperty('--tilt-y', `${pointerX * 3}deg`);
            }
            if (steps) {
                const bounds = steps.getBoundingClientRect();
                const progress = clamp((window.innerHeight * 0.8 - bounds.top) / (bounds.height + window.innerHeight * 0.3), 0, 1);
                steps.style.setProperty('--steps-progress', String(progress));
            }
        };
        const scheduleCollage = () => {
            if (frame === null && !reducedMotion.matches) frame = requestAnimationFrame(updateCollage);
        };
        const resetPointer = () => {
            pointerX = 0;
            pointerY = 0;
            scheduleCollage();
        };
        collage.addEventListener('pointermove', (event) => {
            if (!precisePointer.matches || !heroVisible || reducedMotion.matches) return;
            const bounds = collage.getBoundingClientRect();
            pointerX = clamp(((event.clientX - bounds.left) / bounds.width - 0.5) * 2, -1, 1);
            pointerY = clamp(((event.clientY - bounds.top) / bounds.height - 0.5) * 2, -1, 1);
            scheduleCollage();
        }, { passive: true });
        collage.addEventListener('pointerleave', resetPointer);
        precisePointer.addEventListener('change', resetPointer);
        reducedMotion.addEventListener('change', () => {
            if (frame !== null) cancelAnimationFrame(frame);
            frame = null;
            resetPointer();
        });
        window.addEventListener('scroll', scheduleCollage, { passive: true });
        window.addEventListener('resize', scheduleCollage, { passive: true });
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(([entry]) => {
                heroVisible = entry.isIntersecting;
                collage.classList.toggle('is-motion-paused', !heroVisible);
                if (heroVisible) scheduleCollage();
            }).observe(collage);
        }
        document.addEventListener('visibilitychange', () => {
            collage.classList.toggle('is-motion-paused', document.hidden || !heroVisible);
        });
        scheduleCollage();
    }

    const promoDeck = document.querySelector('[data-promo-deck]');
    if (promoDeck) {
        const cards = [...promoDeck.querySelectorAll('[data-promo-card]')];
        const previousButton = promoDeck.querySelector('[data-promo-prev]');
        const nextButton = promoDeck.querySelector('[data-promo-next]');
        const dots = [...promoDeck.querySelectorAll('[data-promo-dot]')];
        const status = promoDeck.querySelector('[data-promo-status]');
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let activeIndex = 0;
        let autoTimer = null;
        let isAnimating = false;
        let isDragging = false;
        let isHovered = false;
        let hasFocus = false;
        let isVisible = true;
        let pointerId = null;
        let dragStartX = 0;
        let dragStartY = 0;
        let dragStartedAt = 0;
        let dragCard = null;

        const clearAutoTimer = () => {
            if (autoTimer !== null) window.clearTimeout(autoTimer);
            autoTimer = null;
        };

        const canAutoPlay = () => !reducedMotion.matches
            && !document.hidden
            && isVisible
            && !isHovered
            && !hasFocus
            && !isDragging;

        const updateDeck = (announce = false) => {
            cards.forEach((card, index) => {
                const position = (index - activeIndex + cards.length) % cards.length;
                card.dataset.deckPosition = String(position);
                card.setAttribute('aria-hidden', String(position !== 0));
            });

            dots.forEach((dot, index) => {
                if (index === activeIndex) dot.setAttribute('aria-current', 'true');
                else dot.removeAttribute('aria-current');
            });

            if (status) {
                const description = cards[activeIndex].querySelector('img')?.alt || '';
                status.textContent = `Poster ${activeIndex + 1} dari ${cards.length}: ${description}`;
                if (!announce) status.setAttribute('aria-live', 'off');
                else status.setAttribute('aria-live', 'polite');
            }
        };

        const scheduleAutoPlay = (delay = 5000) => {
            clearAutoTimer();
            if (!canAutoPlay()) return;
            autoTimer = window.setTimeout(() => {
                moveDeck(1, false);
            }, delay);
        };

        const resetCardPosition = (card) => {
            card.classList.remove('is-dragging', 'is-exiting');
            card.style.removeProperty('--drag-x');
            card.style.removeProperty('--drag-y');
            card.style.removeProperty('--drag-rotation');
        };

        const moveDeck = (direction, isUserAction = true, requestedIndex = null) => {
            if (isAnimating || cards.length < 2) return;
            clearAutoTimer();

            const outgoingCard = cards[activeIndex];
            const nextIndex = requestedIndex ?? (activeIndex + direction + cards.length) % cards.length;
            if (nextIndex === activeIndex) {
                scheduleAutoPlay(isUserAction ? 6000 : 5000);
                return;
            }

            const finish = () => {
                resetCardPosition(outgoingCard);
                activeIndex = nextIndex;
                updateDeck(isUserAction);
                isAnimating = false;
                scheduleAutoPlay(isUserAction ? 6000 : 5000);
            };

            if (reducedMotion.matches) {
                finish();
                return;
            }

            isAnimating = true;
            outgoingCard.classList.remove('is-dragging');
            outgoingCard.classList.add('is-exiting');
            outgoingCard.style.setProperty('--drag-x', direction > 0 ? '-135%' : '135%');
            outgoingCard.style.setProperty('--drag-y', '-10px');
            outgoingCard.style.setProperty('--drag-rotation', direction > 0 ? '-9deg' : '9deg');
            window.setTimeout(finish, 300);
        };

        const endDrag = (event, cancelled = false) => {
            if (!isDragging || event.pointerId !== pointerId || !dragCard) return;

            const distanceX = event.clientX - dragStartX;
            const elapsed = Math.max(performance.now() - dragStartedAt, 1);
            const velocity = distanceX / elapsed;
            const threshold = dragCard.getBoundingClientRect().width * 0.22;
            const shouldMove = !cancelled && (Math.abs(distanceX) >= threshold || Math.abs(velocity) > 0.45);
            const direction = distanceX < 0 ? 1 : -1;
            const releasedCard = dragCard;

            if (releasedCard.hasPointerCapture?.(pointerId)) {
                releasedCard.releasePointerCapture(pointerId);
            }

            isDragging = false;
            pointerId = null;
            dragCard = null;

            if (shouldMove) {
                resetCardPosition(releasedCard);
                moveDeck(direction);
                return;
            }

            resetCardPosition(releasedCard);
            scheduleAutoPlay(6000);
        };

        promoDeck.classList.add('is-enhanced');
        updateDeck();
        scheduleAutoPlay();

        promoDeck.addEventListener('pointerdown', (event) => {
            const card = event.target.closest('[data-promo-card]');
            if (!card || card.dataset.deckPosition !== '0' || isAnimating) return;
            clearAutoTimer();
            isDragging = true;
            pointerId = event.pointerId;
            dragStartX = event.clientX;
            dragStartY = event.clientY;
            dragStartedAt = performance.now();
            dragCard = card;
            card.setPointerCapture?.(event.pointerId);
            card.classList.add('is-dragging');
        });

        promoDeck.addEventListener('pointermove', (event) => {
            if (!isDragging || event.pointerId !== pointerId || !dragCard) return;
            const distanceX = event.clientX - dragStartX;
            const distanceY = event.clientY - dragStartY;
            if (Math.abs(distanceY) > Math.abs(distanceX) && Math.abs(distanceX) < 12) return;
            dragCard.style.setProperty('--drag-x', `${distanceX}px`);
            dragCard.style.setProperty('--drag-y', `${Math.max(-16, Math.min(16, distanceY * 0.18))}px`);
            dragCard.style.setProperty('--drag-rotation', `${Math.max(-8, Math.min(8, distanceX / 28))}deg`);
        });

        promoDeck.addEventListener('pointerup', endDrag);
        promoDeck.addEventListener('pointercancel', (event) => endDrag(event, true));
        previousButton?.addEventListener('click', () => moveDeck(-1));
        nextButton?.addEventListener('click', () => moveDeck(1));
        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                const direction = index > activeIndex ? 1 : -1;
                moveDeck(direction, true, index);
            });
        });

        promoDeck.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                moveDeck(-1);
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                moveDeck(1);
            }
        });
        promoDeck.addEventListener('pointerenter', () => {
            isHovered = true;
            clearAutoTimer();
        });
        promoDeck.addEventListener('pointerleave', () => {
            isHovered = false;
            scheduleAutoPlay(6000);
        });
        promoDeck.addEventListener('focusin', () => {
            hasFocus = true;
            clearAutoTimer();
        });
        promoDeck.addEventListener('focusout', (event) => {
            if (promoDeck.contains(event.relatedTarget)) return;
            hasFocus = false;
            scheduleAutoPlay(6000);
        });
        reducedMotion.addEventListener('change', () => {
            clearAutoTimer();
            cards.forEach(resetCardPosition);
            isAnimating = false;
            scheduleAutoPlay();
        });
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) clearAutoTimer();
            else scheduleAutoPlay(6000);
        });

        if ('IntersectionObserver' in window) {
            new IntersectionObserver(([entry]) => {
                isVisible = entry.isIntersecting;
                if (isVisible) scheduleAutoPlay(6000);
                else clearAutoTimer();
            }, { threshold: 0.15 }).observe(promoDeck);
        }
    }

    if (document.getElementById('beranda')) {
        let sectionFrame = null;
        const syncSection = () => {
            const activeSection = homeSections.filter((section) => section.getBoundingClientRect().top <= Math.min(window.innerHeight * 0.35, 300)).at(-1) || homeSections[0];
            document.querySelectorAll('[data-section-link]').forEach((link) => {
                const isActive = link.dataset.sectionLink === activeSection.id;
                link.classList.toggle('is-active', isActive);
                if (isActive) link.setAttribute('aria-current', 'location');
                else link.removeAttribute('aria-current');
            });
            syncNavIndicator();
            sectionFrame = null;
        };
        syncSection();
        window.addEventListener('scroll', () => {
            if (sectionFrame === null) sectionFrame = requestAnimationFrame(syncSection);
        }, { passive: true });
    }

    document.querySelectorAll('[data-gallery]').forEach((gallery) => {
        const main = gallery.querySelector('[data-gallery-main]');
        if (!main) return;

        gallery.querySelectorAll('[data-gallery-thumb]').forEach((thumb) => {
            thumb.addEventListener('click', () => {
                main.classList.add('opacity-0');
                setTimeout(() => {
                    main.setAttribute('src', thumb.dataset.gallerySrc);
                    main.setAttribute('alt', thumb.dataset.galleryAlt || main.getAttribute('alt') || '');
                    main.classList.remove('opacity-0');
                }, 120);
            });
        });
    });

    document.querySelectorAll('[data-delivery-settings]').forEach(settings => {
        const enabled = settings.querySelector('[data-delivery-enabled]');
        const free = settings.querySelector('[data-free-delivery]');
        const sync = () => {
            free.disabled = !enabled.checked;
            if (!enabled.checked) free.checked = false;
        };
        enabled.addEventListener('change', sync);
        sync();
    });
    document.querySelectorAll('[data-payment-policy]').forEach(settings => {
        const deposit = settings.querySelector('[data-deposit-enabled]');
        const cash = settings.querySelector('[data-cash-enabled]');
        const sync = () => {
            cash.disabled = !deposit.checked;
            if (!deposit.checked) cash.checked = false;
        };
        deposit.addEventListener('change', sync);
        sync();
    });
    document.querySelectorAll('[data-private-image-upload]').forEach(form => {
        const input = form.querySelector('[data-private-image-input]');
        const preview = form.querySelector('[data-private-image-preview]');
        const image = preview?.querySelector('img');
        if (!input || !preview || !image) return;
        let objectUrl;
        input.addEventListener('change', () => {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            const file = input.files?.[0];
            if (!file || !file.type.startsWith('image/')) {
                preview.hidden = true;
                return;
            }
            objectUrl = URL.createObjectURL(file);
            image.src = objectUrl;
            preview.hidden = false;
        });
    });
    initializeBookingForm(document.querySelector('[data-booking-form]'));
});
