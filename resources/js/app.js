import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import intersect from '@alpinejs/intersect';

Alpine.plugin(collapse);
Alpine.plugin(focus);
Alpine.plugin(intersect);

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Scroll-driven parallax: CSS reads var(--scroll-y) (unitless pixels), updated once per frame.
if (!reducedMotion) {
    let ticking = false;
    const update = () => {
        document.documentElement.style.setProperty('--scroll-y', String(window.scrollY));
        ticking = false;
    };
    window.addEventListener('scroll', () => {
        if (!ticking) {
            ticking = true;
            requestAnimationFrame(update);
        }
    }, { passive: true });
    update();
}

// Pointer parallax on a container: children use var(--px) / var(--py) in [-1, 1].
Alpine.data('pointerParallax', () => ({
    move(event) {
        if (reducedMotion) return;
        const rect = this.$el.getBoundingClientRect();
        this.$el.style.setProperty('--px', ((event.clientX - rect.left) / rect.width - 0.5) * 2);
        this.$el.style.setProperty('--py', ((event.clientY - rect.top) / rect.height - 0.5) * 2);
    },
    reset() {
        this.$el.style.setProperty('--px', 0);
        this.$el.style.setProperty('--py', 0);
    },
}));

// Animated number, started when it scrolls into view (x-intersect.once="start").
Alpine.data('counter', (target) => ({
    value: reducedMotion ? target : 0,
    start() {
        if (reducedMotion || target === 0) return (this.value = target);
        const duration = 1400;
        const started = performance.now();
        const step = (now) => {
            const progress = Math.min(1, (now - started) / duration);
            this.value = Math.round(target * (1 - Math.pow(1 - progress, 3)));
            if (progress < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    },
    get formatted() {
        return this.value.toLocaleString('fr-FR');
    },
}));

// Rotating words in a headline.
Alpine.data('rotator', (words, interval = 2200) => ({
    words,
    index: 0,
    init() {
        if (reducedMotion) return;
        setInterval(() => (this.index = (this.index + 1) % this.words.length), interval);
    },
}));

// Theme: "light" | "dark" | "system", persisted per browser.
Alpine.store('theme', {
    mode: localStorage.getItem('theme') ?? 'system',

    init() {
        this.apply();
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => this.apply());
    },

    set(mode) {
        this.mode = mode;
        localStorage.setItem('theme', mode);
        this.apply();
    },

    get isDark() {
        return this.mode === 'dark' || (this.mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    },

    apply() {
        document.documentElement.classList.toggle('dark', this.isDark);
    },
});

// Toast notifications: window.dispatchEvent(new CustomEvent('toast', { detail: { type, message } })).
Alpine.data('toaster', (initial = []) => ({
    toasts: [],

    init() {
        initial.forEach((toast) => this.push(toast));
        window.addEventListener('toast', (event) => this.push(event.detail));
    },

    push({ type = 'success', message }) {
        const id = Date.now() + Math.random();
        this.toasts.push({ id, type, message, visible: true });
        setTimeout(() => this.dismiss(id), type === 'error' ? 7000 : 4500);
    },

    dismiss(id) {
        const toast = this.toasts.find((t) => t.id === id);
        if (toast) toast.visible = false;
        setTimeout(() => (this.toasts = this.toasts.filter((t) => t.id !== id)), 300);
    },
}));

// Live countdown to an ISO date: x-data="countdown('2026-09-30T18:00:00+00:00')" x-text="label".
Alpine.data('countdown', (iso) => ({
    now: Date.now(),
    init() {
        this.timer = setInterval(() => (this.now = Date.now()), 1000);
    },
    destroy() {
        clearInterval(this.timer);
    },
    get remaining() {
        return Math.max(0, new Date(iso).getTime() - this.now);
    },
    get urgent() {
        return this.remaining < 24 * 3600 * 1000;
    },
    get label() {
        const s = Math.floor(this.remaining / 1000);
        if (s === 0) return 'Terminé';
        const d = Math.floor(s / 86400), h = Math.floor((s % 86400) / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
        const pad = (n) => String(n).padStart(2, '0');
        return d > 0 ? `${d} j ${pad(h)} h ${pad(m)} min` : `${pad(h)} h ${pad(m)} min ${pad(sec)} s`;
    },
}));

// File picker with drag & drop and a client-side size check (the server validates again).
Alpine.data('dropzone', (maxMb) => ({
    file: null,
    error: null,
    dragging: false,
    pick(files) {
        const file = files?.[0];
        if (!file) return;
        this.error = file.size > maxMb * 1024 * 1024 ? `Fichier trop lourd : ${maxMb} Mo maximum.` : null;
        this.file = this.error ? null : file;
        if (this.error) this.$refs.input.value = '';
        else {
            const transfer = new DataTransfer();
            transfer.items.add(file);
            this.$refs.input.files = transfer.files;
        }
    },
    get size() {
        return this.file ? (this.file.size / 1048576).toFixed(1).replace('.', ',') + ' Mo' : '';
    },
}));

window.Alpine = Alpine;
Alpine.start();
