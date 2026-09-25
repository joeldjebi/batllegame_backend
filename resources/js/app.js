import Alpine from 'alpinejs';
import anchor from '@alpinejs/anchor';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import intersect from '@alpinejs/intersect';
import { refreshLive, setupRealtime } from './realtime';

Alpine.plugin(anchor);
Alpine.plugin(collapse);
Alpine.plugin(focus);
Alpine.plugin(intersect);

// Rich text editor, loaded only on pages that use <x-ui.rich-editor>. File attachments are disabled.
if (document.querySelector('trix-editor')) {
    Promise.all([import('trix'), import('trix/dist/trix.css')]);
    document.addEventListener('trix-file-accept', (event) => event.preventDefault());
}

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
    mode: localStorage.getItem('theme') ?? 'light',

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
        return d > 0 ? `${d} j ${pad(h)} h ${pad(m)} min ${pad(sec)} s` : `${pad(h)} h ${pad(m)} min ${pad(sec)} s`;
    },
}));

// Pre-selection likes in AJAX (fan page): like, unlike, move the like; counters updated at once.
// Initial state comes from <script type="application/json" x-ref="state">, re-read after a live refresh.
Alpine.data('preselectionLikes', ({ likeUrl, unlikeUrl, loginUrl, loggedIn }) => ({
    myLike: null,
    counts: null,
    canLike: false,
    busy: false,

    init() {
        this.load();
        window.addEventListener('live:refreshed', () => this.busy || this.load());
    },

    load() {
        try {
            const state = JSON.parse(this.$refs.state.textContent);
            this.myLike = state.my_like;
            this.counts = state.counts;
            this.canLike = state.can_like;
        } catch {
            // Keep the current state.
        }
    },

    isLiked(id) {
        return this.myLike === id;
    },

    count(id) {
        // Only the entries whose count the viewer may see are listed (e.g. an artist's own entry).
        return this.counts && id in this.counts ? this.counts[id] : null;
    },

    label(id) {
        const n = this.count(id);
        if (n === null) return this.isLiked(id) ? 'Votre like' : '';
        return `${n} like${n > 1 ? 's' : ''}`;
    },

    async toggle(id) {
        if (!loggedIn) return (window.location.href = loginUrl);
        if (this.busy || !this.canLike) return;

        const unlike = this.isLiked(id);
        const previous = { myLike: this.myLike, counts: this.counts ? { ...this.counts } : null };

        // Optimistic update: one like per competition, moving it frees the previous entry.
        if (this.counts) {
            if (this.myLike !== null && this.myLike in this.counts) this.counts[this.myLike] = Math.max(0, this.counts[this.myLike] - 1);
            if (!unlike && id in this.counts) this.counts[id] += 1;
        }
        this.myLike = unlike ? null : id;
        this.busy = true;

        try {
            const response = await fetch(unlike ? unlikeUrl : likeUrl.replace('__ENTRY__', id), {
                method: unlike ? 'DELETE' : 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const body = await response.json().catch(() => ({}));

            if (response.status === 401 || response.status === 419) return window.location.reload();
            if (!response.ok) throw new Error(body.message || 'Action impossible pour le moment.');

            this.myLike = body.my_like;
            this.counts = body.counts;
            this.canLike = body.can_like;
            window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: body.message } }));
        } catch (error) {
            Object.assign(this, previous);
            window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: error.message } }));
        } finally {
            this.busy = false;
        }
    },
}));

// Share a link: the native share sheet when the device has one, otherwise a menu of networks.
Alpine.data('share', ({ url, title, text }) => ({
    open: false,
    copied: false,
    async trigger() {
        if (navigator.share) {
            try {
                await navigator.share({ url, title, text });
                return;
            } catch (error) {
                if (error?.name === 'AbortError') return; // closed by the user
            }
        }
        this.open = !this.open;
    },
    async copy() {
        try {
            await navigator.clipboard.writeText(url);
        } catch {
            const input = Object.assign(document.createElement('textarea'), { value: url });
            document.body.append(input);
            input.select();
            document.execCommand('copy');
            input.remove();
        }
        this.copied = true;
        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: 'Lien copié : partagez-le autour de vous !' } }));
        setTimeout(() => ((this.copied = false), (this.open = false)), 1500);
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
setupRealtime(Alpine);
// Group phase assistant (back-office): preview and suggestions for N entrants.
// Mirrors App\Services\Competition\GroupPlan (blocking problems are checked again on the server).
const ROUND_NAMES = { 2: 'la finale', 4: 'les demi-finales', 8: 'les quarts de finale', 16: 'les huitièmes de finale', 32: 'les seizièmes de finale', 64: 'les 32es de finale' };

const groupSizes = (n, g) => (g < 1 ? [] : Array.from({ length: g }, (_, i) => Math.floor(n / g) + (i < n % g ? 1 : 0)));
const nextPowerOfTwo = (n) => 2 ** Math.ceil(Math.log2(Math.max(2, n)));
const plural = (n, word, many = word + 's') => `${n} ${n > 1 ? many : word}`;

function describeSizes(sizes) {
    const counts = sizes.reduce((acc, size) => ({ ...acc, [size]: (acc[size] ?? 0) + 1 }), {});
    return Object.entries(counts).sort((a, b) => b[0] - a[0]).map(([size, count]) => `${plural(count, 'poule')} de ${size}`).join(' et ');
}

Alpine.data('groupPlanner', ({ entrants, groups, qualifiers, suggest = false }) => ({
    n: entrants || null,
    g: groups || 2,
    q: qualifiers || 2,

    // New phase: start from the best format for the expected participants.
    init() {
        if (suggest && this.suggestions.length) this.apply(this.suggestions[0]);
    },

    get sizes() { return this.n ? groupSizes(this.n, this.g) : []; },
    get smallest() { return this.sizes.length ? Math.min(...this.sizes) : 0; },
    get qualified() { return this.g * this.q; },

    get problem() {
        if (!this.n) return null;
        if (this.g < 1 || this.q < 1) return 'Au moins une poule et un qualifié par poule.';
        const max = Math.floor(this.n / 2);
        if (this.g > max) return max < 1 ? 'Il faut au moins 2 participants.' : `Trop de poules pour ${this.n} participants : ${max} au maximum (2 artistes minimum par poule).`;
        if (this.q >= this.smallest) return `Trop de qualifiés : la plus petite poule compte ${this.smallest} artistes, qualifiez-en ${this.smallest - 1} au maximum par poule.`;
        return null;
    },

    get summary() {
        if (!this.n || this.problem) return null;
        const bracket = nextPowerOfTwo(this.qualified);
        const byes = bracket - this.qualified;
        return {
            groups: describeSizes(this.sizes),
            matches: `${plural(this.n, 'prestation')} : chaque artiste se présente, le public (1 vote par phase) et le jury classent chaque poule`,
            next: this.g === 1 && this.q === 1
                ? 'Le 1er de la poule gagne la phase.'
                : `${plural(this.qualified, 'qualifié')} → ${ROUND_NAMES[bracket] ?? `un tableau de ${bracket}`}` + (byes ? ` (${plural(byes, 'exempté')} au 1er tour)` : ''),
            uneven: new Set(this.sizes).size > 1,
        };
    },

    // Best formats for n: groups of 3 to 6, a power of two of qualifiers, balanced groups.
    get suggestions() {
        const n = this.n;
        if (!n || n < 4) return [];
        const options = [];
        for (let g = 1; g <= Math.floor(n / 3); g++) {
            const sizes = groupSizes(n, g);
            const smallest = Math.min(...sizes);
            for (const q of [1, 2, 4]) {
                if (q >= smallest || (g === 1 && q === 1)) continue;
                const total = g * q;
                const average = n / g;
                const score = (total === nextPowerOfTwo(total) ? 0 : 6) + Math.abs(average - 4) * 2 + (new Set(sizes).size > 1 ? 1 : 0) + (g === 1 ? 4 : 0) + (q === 4 ? 2 : 0) + (q === 1 ? 1.5 : 0);
                options.push({ g, q, score, label: `${describeSizes(sizes)} · ${q} qualifié${q > 1 ? 's' : ''}`, next: ROUND_NAMES[nextPowerOfTwo(total)] ?? `tableau de ${nextPowerOfTwo(total)}` });
            }
        }
        return options.sort((a, b) => a.score - b.score).slice(0, 3);
    },

    apply(option) {
        this.g = option.g;
        this.q = option.q;
    },

    // Follow an expected size set elsewhere (e.g. the artists retained by the pre-selection),
    // without overwriting what the organizer typed or what came back after a validation error.
    lastSynced: null,
    sync(expected) {
        if (!expected || expected === this.lastSynced) return;
        this.lastSynced = expected;
        if (this.n === expected) return;
        this.n = expected;
        this.$nextTick(() => this.suggestions.length && this.apply(this.suggestions[0]));
    },
}));

// Full-page competition creation: steps, and the live summary of the structure up to the final.
const ROUND_TITLES = ['Finale', 'Demi-finales', 'Quarts de finale', 'Huitièmes de finale', 'Seizièmes de finale'];
const bracketRounds = (entrants) => {
    const rounds = Math.ceil(Math.log2(Math.max(2, entrants)));
    return Array.from({ length: rounds }, (_, i) => ROUND_TITLES[rounds - 1 - i] ?? `Tour ${i + 1}`);
};

Alpine.data('competitionWizard', (initial) => ({
    step: 1,
    name: '',
    mode: 'presentiel',
    max: null,
    fee: 0,
    withPre: false,
    selection: 16,
    like: 50,
    preEnd: '',
    type: 'poules',
    vote: 'mixte',
    jury: 60,
    groupsCount: 2,
    qualsCount: 2,
    ...initial,

    get expected() { return this.withPre ? this.selection : this.max; },

    get structure() {
        const steps = [];
        if (this.withPre) steps.push({ title: 'Présélection', detail: `${this.selection || '?'} artistes retenus` });
        if (this.type === 'plus_tard') return [...steps, { title: 'Format à définir', detail: 'Dans l\'onglet Phases & matchs' }];
        if (this.type === 'poules') {
            steps.push({ title: 'Poules', detail: `${this.groupsCount} poule(s), ${this.qualsCount} qualifié(s) par poule` });
            bracketRounds(this.groupsCount * this.qualsCount).forEach((title) => steps.push({ title, detail: null }));
        } else if (this.type === 'elimination') {
            bracketRounds(this.expected || 8).forEach((title) => steps.push({ title, detail: null }));
        } else {
            steps.push({ title: 'Double élimination', detail: 'Tableau principal et tableau des perdants' }, { title: 'Grande finale', detail: null });
        }
        return steps;
    },

    date(value) {
        if (!value) return '—';
        return new Date(value).toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    },

    stepFields(n) {
        return [...this.$refs['step' + n].querySelectorAll('input, select, textarea')];
    },

    go(n) {
        // Back freely; forward only through valid steps.
        for (let i = this.step; i < n; i++) {
            const invalid = this.stepFields(i).find((field) => !field.checkValidity());
            if (invalid) {
                this.step = i;
                return this.$nextTick(() => invalid.reportValidity());
            }
        }
        this.step = n;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    submit(event) {
        const invalid = [...this.$root.querySelectorAll('input, select')].find((field) => !field.checkValidity());
        if (!invalid) return;
        event.preventDefault();
        this.step = Number(invalid.closest('[data-step]').dataset.step);
        this.$nextTick(() => invalid.reportValidity());
    },
}));

// Client-side pagination of a filtered list: each row has x-effect="track(index, matches)" and x-show="visible(index)";
// filters live in the same component (extra state), any change of them goes back to page 1.
Alpine.data('pager', (state = {}, perPage = 15) => ({
    ...state,
    page: 1,
    perPage,
    matched: {},

    init() {
        Object.keys(state).forEach((key) => this.$watch(key, () => { this.page = 1; }));
    },

    // x-effect of each row: record whether it matches the filters.
    track(index, matches) {
        if (this.matched[index] !== matches) this.matched[index] = matches;
    },

    // x-show of each row: matching and on the current page.
    visible(index) {
        if (!this.matched[index]) return false;
        const position = Object.keys(this.matched).filter((i) => Number(i) < index && this.matched[i]).length;
        return Math.floor(position / this.perPage) + 1 === this.page;
    },

    get total() { return Object.values(this.matched).filter(Boolean).length; },
    get pages() { return Math.max(1, Math.ceil(this.total / this.perPage)); },
    get from() { return this.total ? (this.page - 1) * this.perPage + 1 : 0; },
    get to() { return Math.min(this.total, this.page * this.perPage); },

    go(page) {
        this.page = Math.min(Math.max(1, page), this.pages);
    },
}));

// Back-office forms sent in AJAX (e.g. reviewing a performance): toast with the result, the popup
// closes and the live regions of the page re-render in place, no reload.
Alpine.data('ajaxForm', ({ modal = null } = {}) => ({
    busy: false,

    async send() {
        if (this.busy) return;
        const form = this.$el.tagName === 'FORM' ? this.$el : this.$el.querySelector('form');
        this.busy = true;
        const buttons = [...form.querySelectorAll('button')];
        buttons.forEach((button) => { button.disabled = true; });

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const body = await response.json().catch(() => ({}));

            if (response.status === 401 || response.status === 419) return window.location.reload();
            if (!response.ok) {
                const first = body.errors ? Object.values(body.errors)[0]?.[0] : null;
                throw new Error(first || body.message || 'Action impossible pour le moment.');
            }

            window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: body.message } }));
            if (modal) window.dispatchEvent(new CustomEvent('close-modal', { detail: modal }));
            await refreshLive(Alpine, form);
        } catch (error) {
            window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: error.message } }));
        } finally {
            this.busy = false;
            buttons.forEach((button) => { button.disabled = false; });
        }
    },
}));

Alpine.start();
