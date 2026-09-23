// Battle Game realtime server (Socket.IO).
//
// Laravel publishes updates with POST /publish (shared secret); browsers and the mobile app
// subscribe to channels over Socket.IO. Public channels (competition.{id}, live) are open;
// private ones (bo.*, jury.*, user.*, admin) require a token signed by Laravel
// (App\Realtime\RealtimeToken) that lists the channels the holder may join.
//
// Run: npm run realtime   (reads backend/.env: REALTIME_PORT, REALTIME_SECRET, APP_URL, REALTIME_ALLOWED_ORIGINS)

import { createServer } from 'node:http';
import { createHmac, timingSafeEqual } from 'node:crypto';
import { existsSync, readFileSync } from 'node:fs';
import { Server } from 'socket.io';

// Minimal .env loader (works on Node 18+); real environment variables win.
const envFile = new URL('../.env', import.meta.url);
if (existsSync(envFile)) {
    for (const line of readFileSync(envFile, 'utf8').split(/\r?\n/)) {
        const match = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
        if (match && process.env[match[1]] === undefined) {
            process.env[match[1]] = match[2].replace(/^(['"])(.*)\1$/, '$2');
        }
    }
}

const port = Number(process.env.REALTIME_PORT || 6001);
const secret = process.env.REALTIME_SECRET || '';
const origins = (process.env.REALTIME_ALLOWED_ORIGINS || process.env.APP_URL || 'http://localhost:8000')
    .split(',').map((o) => o.trim()).filter(Boolean);

if (secret.length < 16) {
    console.error('[realtime] REALTIME_SECRET is missing or too short (16+ chars).');
    process.exit(1);
}

const PUBLIC = /^(competition\.\d+|live)$/;
const PRIVATE = /^(bo\.competition\.\d+|bo\.organizer\.\d+|jury\.competition\.\d+|user\.\d+|admin)$/;
const MAX_BODY = 512 * 1024;

const safeEqual = (a, b) => {
    const x = Buffer.from(String(a));
    const y = Buffer.from(String(b));
    return x.length === y.length && timingSafeEqual(x, y);
};

/** Verify "<base64url payload>.<base64url hmac>" and return the allowed channels. */
function channelsFromToken(token) {
    if (typeof token !== 'string' || !token.includes('.')) return [];
    const [payload, signature] = token.split('.');
    const expected = createHmac('sha256', secret).update(payload).digest('base64url');
    if (!safeEqual(signature, expected)) return [];
    try {
        const data = JSON.parse(Buffer.from(payload, 'base64url').toString('utf8'));
        if (!data.exp || data.exp * 1000 < Date.now()) return [];
        return Array.isArray(data.c) ? data.c.filter((c) => typeof c === 'string' && PRIVATE.test(c)) : [];
    } catch {
        return [];
    }
}

const http = createServer((req, res) => {
    const reply = (status, body) => {
        res.writeHead(status, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify(body));
    };

    if (req.method === 'GET' && req.url === '/health') {
        return reply(200, { ok: true, clients: io.engine.clientsCount });
    }

    if (req.method !== 'POST' || req.url !== '/publish') {
        return reply(404, { error: 'not_found' });
    }

    if (!safeEqual(req.headers.authorization || '', `Bearer ${secret}`)) {
        return reply(401, { error: 'unauthorized' });
    }

    let raw = '';
    req.on('data', (chunk) => {
        raw += chunk;
        if (raw.length > MAX_BODY) req.destroy();
    });
    req.on('end', () => {
        let messages;
        try {
            messages = JSON.parse(raw).messages;
        } catch {
            return reply(422, { error: 'invalid_json' });
        }
        if (!Array.isArray(messages)) return reply(422, { error: 'messages_required' });

        let sent = 0;
        for (const { channels = [], type, data = {}, message = null } of messages) {
            for (const channel of channels) {
                if (!PUBLIC.test(channel) && !PRIVATE.test(channel)) continue;
                io.to(channel).emit('update', { channel, type, data, message, at: Date.now() });
                sent++;
            }
        }
        reply(202, { sent });
    });
});

const io = new Server(http, {
    cors: { origin: origins, credentials: true },
    serveClient: false,
});

io.on('connection', (socket) => {
    // subscribe({ channels: [...public], token }) -> ack({ joined: [...] })
    socket.on('subscribe', (payload = {}, ack) => {
        const wanted = Array.isArray(payload.channels) ? payload.channels : [];
        const allowed = new Set(channelsFromToken(payload.token));
        const joined = wanted.filter((c) => typeof c === 'string' && (PUBLIC.test(c) || allowed.has(c)));
        // A token alone subscribes to everything it grants.
        allowed.forEach((c) => joined.includes(c) || joined.push(c));
        joined.forEach((c) => socket.join(c));
        if (typeof ack === 'function') ack({ joined });
    });

    socket.on('unsubscribe', (payload = {}) => {
        (Array.isArray(payload.channels) ? payload.channels : []).forEach((c) => socket.leave(c));
    });
});

http.on('error', (error) => {
    if (error.code === 'EADDRINUSE') {
        console.error(`[realtime] Port ${port} déjà utilisé : un serveur temps réel tourne déjà (npm run realtime dans un autre terminal ?). Arrêtez-le ou changez REALTIME_PORT.`);
        process.exit(1);
    }
    throw error;
});

http.listen(port, () => console.log(`[realtime] Socket.IO listening on :${port} (origins: ${origins.join(', ')})`));
