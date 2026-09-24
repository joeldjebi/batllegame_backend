// Socket.IO client: joins the channels declared by <x-realtime> and keeps the page fresh.
//
// Every update re-renders the regions marked data-live="key" from the server (the database
// stays the source of truth) with Alpine morph, so Alpine state survives. A region where the
// user is typing, has changed a field or picked a file is left alone. Private channels also
// carry messages shown as toasts.

import morph from '@alpinejs/morph';

const REFRESH_DELAY = 350;
const TRAILING_DELAY = 2500; // catches updates throttled by the server (votes, likes, scores)

export function setupRealtime(Alpine) {
    Alpine.plugin(morph);

    const config = readConfig();
    if (!config) return;

    // Remember regions the user is editing.
    ['input', 'change'].forEach((type) => document.addEventListener(type, (event) => {
        event.target.closest?.('[data-live]')?.setAttribute('data-dirty', '1');
    }, true));

    let timer = null;
    let trailing = null;
    const schedule = () => {
        clearTimeout(timer);
        clearTimeout(trailing);
        timer = setTimeout(() => refresh(Alpine), REFRESH_DELAY);
        trailing = setTimeout(() => refresh(Alpine), TRAILING_DELAY);
    };

    import('socket.io-client').then(({ io }) => {
        const socket = io(config.url, { transports: ['websocket', 'polling'], withCredentials: true });
        const status = (online) => document.querySelectorAll('[data-realtime-status]').forEach((el) => {
            el.hidden = !online;
        });

        socket.on('connect', () => {
            socket.emit('subscribe', { channels: config.channels, token: config.token });
            status(true);
        });
        socket.on('disconnect', () => status(false));
        socket.on('update', (update) => {
            // Toasts: private channels, plus « vote ouvert » on public pages.
            if (update.message && (!isPublic(update.channel) || update.type === 'match.voting')) {
                window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: update.message } }));
            }
            schedule();
        });
    });
}

function readConfig() {
    const tag = document.querySelector('script[data-realtime]');
    if (!tag) return null;
    try {
        const config = JSON.parse(tag.textContent);
        return config.channels.length || config.token ? config : null;
    } catch {
        return null;
    }
}

const isPublic = (channel) => channel === 'live' || /^competition\.\d+$/.test(channel);

let refreshing = false;

/**
 * Re-render the live regions now (after an AJAX action): the region of the form just sent is
 * no longer « being edited ».
 */
export async function refreshLive(Alpine, source = null) {
    source?.closest?.('[data-live]')?.removeAttribute('data-dirty');
    document.querySelectorAll('[data-live="modals"]').forEach((region) => region.removeAttribute('data-dirty'));
    while (refreshing) await new Promise((resolve) => setTimeout(resolve, 50));
    return refresh(Alpine);
}

async function refresh(Alpine) {
    const regions = [...document.querySelectorAll('[data-live]')];
    if (!regions.length || refreshing || document.hidden) return;
    refreshing = true;

    try {
        const response = await fetch(window.location.href, { headers: { Accept: 'text/html', 'X-Live-Refresh': '1' }, credentials: 'same-origin' });
        // Session expired or page gone: keep what is on screen.
        if (!response.ok || response.redirected) return;

        const fresh = new DOMParser().parseFromString(await response.text(), 'text/html');
        const active = document.activeElement;

        regions.forEach((region) => {
            const next = fresh.querySelector(`[data-live="${CSS.escape(region.dataset.live)}"]`);
            if (!next) return;

            const editing = region.dataset.dirty
                || (active && active !== document.body && region.contains(active) && active.matches('input, textarea, select, [contenteditable], trix-editor'))
                || [...region.querySelectorAll('input[type=file]')].some((input) => input.files?.length);
            if (editing) return;

            Alpine.morph(region, next, { key: (el) => el.id || el.dataset?.key });
        });

        // Components holding server state (e.g. likes) re-read it from the refreshed markup.
        window.dispatchEvent(new CustomEvent('live:refreshed'));
    } catch {
        // Network hiccup: the next update will try again.
    } finally {
        refreshing = false;
    }
}

// Coming back to the tab: catch up once.
document.addEventListener('visibilitychange', () => {
    if (!document.hidden && document.querySelector('script[data-realtime]') && window.Alpine) refresh(window.Alpine);
});
