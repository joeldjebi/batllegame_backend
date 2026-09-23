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

window.Alpine = Alpine;
Alpine.start();
