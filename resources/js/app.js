import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';

Alpine.plugin(collapse);
Alpine.plugin(focus);

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
