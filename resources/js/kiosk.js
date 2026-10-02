import Alpine from 'alpinejs';
import { api, captureSelfie, startCamera } from './shared';

/*
 | Tablet kiosk (spec §6.5). PIN keypad or QR scan (BarcodeDetector), selfie on every punch.
 | Offline: punches are queued in IndexedDB with the time they were taken and a client id, and replayed
 | with backoff; the server dedupes replays. Confirmation shows for 3 s, then the screen resets.
 */
const DB = 'hazri-kiosk';

function idb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB, 1);
        req.onupgradeneeded = () => req.result.createObjectStore('queue', { keyPath: 'client_id' });
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

/** This tablet's identity for locked kiosks: a random key kept in the browser, approved once by HR. */
function deviceKey() {
    let key = localStorage.getItem('kiosk-device-key');
    if (!key) {
        const bytes = crypto.getRandomValues(new Uint8Array(32));
        key = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
        localStorage.setItem('kiosk-device-key', key);
    }
    return key;
}

async function queueOp(mode, fn) {
    const db = await idb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction('queue', mode);
        const out = fn(tx.objectStore('queue'));
        tx.oncomplete = () => resolve(out?.result);
        tx.onerror = () => reject(tx.error);
    });
}

Alpine.data('kiosk', ({ token, requirePhoto, modes }) => ({
    token,
    requirePhoto,
    modes,
    mode: modes.includes('pin') ? 'pin' : 'qr',
    pin: '',
    kioskPin: '',
    askKioskPin: false,
    direction: 'auto',
    screen: 'input', // input | done | notice | error | locked
    access: { ok: true },
    result: {},
    pending: 0,
    clock: '',
    period: '',
    today: '',
    scanning: false,
    cameraOn: false,
    online: navigator.onLine,

    async init() {
        this.tick();
        setInterval(() => this.tick(), 1000);
        await this.checkAccess();
        setInterval(() => this.screen === 'locked' && this.checkAccess(), 10000);
        this.cameraOn = await startCamera(this.$refs.video, 'user').then(() => true).catch(() => false);
        this.refreshPending();
        setInterval(() => this.flush(), 15000);
        window.addEventListener('online', () => {
            this.online = true;
            this.flush();
        });
        window.addEventListener('offline', () => (this.online = false));
        if (this.mode === 'qr') this.scanLoop();
    },

    /** Is this tablet allowed to punch (approved tablet / office internet)? */
    async checkAccess() {
        try {
            const cfg = await fetch(`/kiosk/${this.token}/config?device_key=${encodeURIComponent(deviceKey())}`, { headers: { Accept: 'application/json' } }).then((r) => r.json());
            this.lock(cfg.access);
        } catch (e) {
            /* offline: keep the current state; punches queue as usual */
        }
    },

    lock(access) {
        this.access = access ?? { ok: true };
        if (!this.access.ok) this.screen = 'locked';
        else if (this.screen === 'locked') this.screen = 'input';
    },

    tick() {
        const now = new Date();
        const parts = new Intl.DateTimeFormat([], { hour: '2-digit', minute: '2-digit' }).formatToParts(now);
        this.clock = parts.filter((x) => x.type !== 'dayPeriod').map((x) => x.value).join('').trim();
        this.period = parts.find((x) => x.type === 'dayPeriod')?.value ?? '';
        this.today = now.toLocaleDateString([], { weekday: 'long', day: 'numeric', month: 'long' });
    },

    press(key) {
        if (key === 'del') {
            this.askKioskPin ? (this.kioskPin = this.kioskPin.slice(0, -1)) : (this.pin = this.pin.slice(0, -1));
        } else if (key === 'ok') {
            this.submit();
        } else if (this.askKioskPin) {
            if (this.kioskPin.length < 4) this.kioskPin += key;
            if (this.kioskPin.length === 4) this.submit();
        } else if (this.pin.length < 10) {
            this.pin += key;
        }
    },

    async scanLoop() {
        if (!('BarcodeDetector' in window) || this.scanning) return;
        this.scanning = true;
        const detector = new window.BarcodeDetector({ formats: ['qr_code'] });
        while (this.mode === 'qr') {
            if (this.screen === 'input' && this.$refs.video?.readyState >= 2) {
                try {
                    const codes = await detector.detect(this.$refs.video);
                    if (codes[0]?.rawValue?.startsWith('hz1.')) {
                        await this.submit({ qr: codes[0].rawValue });
                    }
                } catch (e) { /* keep scanning */ }
            }
            await new Promise((r) => setTimeout(r, 400));
        }
        this.scanning = false;
    },

    async submit(extra = {}) {
        if (this.mode === 'pin' && !this.pin) return;
        const payload = {
            mode: this.mode,
            pin: this.mode === 'pin' ? this.pin : null,
            kiosk_pin: this.kioskPin || null,
            direction: this.direction,
            photo: this.$refs.video?.srcObject ? captureSelfie(this.$refs.video) : null,
            client_time: new Date().toISOString(),
            client_id: crypto.randomUUID(),
            device_key: deviceKey(),
            ...extra,
        };
        if (this.requirePhoto && !payload.photo) {
            return this.fail('Camera is not available.');
        }
        try {
            const r = await api(`/kiosk/${this.token}/punch`, payload);
            this.done(r);
        } catch (e) {
            if (e.status === 422 && /kiosk PIN/i.test(e.message) && this.mode === 'pin' && !this.askKioskPin) {
                this.askKioskPin = true; // maybe this employee has a kiosk PIN
                return;
            }
            if (e.status === 403) return this.checkAccess();
            if (e.status && e.status < 500) return this.fail(e.message);
            // Network / server down: keep it and show a friendly confirmation anyway.
            await queueOp('readwrite', (s) => s.put({ ...payload, offline: true }));
            await this.refreshPending();
            this.done({ name: '', direction: this.direction === 'auto' ? '' : this.direction, time: new Date().toTimeString().slice(0, 5), queued: true });
        }
    },

    async flush() {
        if (!navigator.onLine) return;
        const items = await queueOp('readonly', (s) => s.getAll());
        for (const item of items ?? []) {
            try {
                await api(`/kiosk/${this.token}/punch`, item);
                await queueOp('readwrite', (s) => s.delete(item.client_id));
            } catch (e) {
                if (e.status === 403) break; // tablet not approved (yet): keep the queue until it is
                if (e.status && e.status < 500) await queueOp('readwrite', (s) => s.delete(item.client_id)); // rejected: don't retry forever
                else break;
            }
        }
        this.refreshPending();
    },

    async refreshPending() {
        this.pending = (await queueOp('readonly', (s) => s.count())) ?? 0;
    },

    done(r) {
        this.result = r;
        if (r.double_tap) {
            // A second tap within the double-tap window: nothing new was saved.
            this.screen = 'notice';
            setTimeout(() => this.reset(), 3000);
            return;
        }
        this.screen = 'done';
        if (r.name && 'speechSynthesis' in window) {
            speechSynthesis.speak(new SpeechSynthesisUtterance(`${r.direction === 'out' ? 'Goodbye' : 'Welcome'} ${r.name}`));
        }
        setTimeout(() => this.reset(), 3000);
    },

    fail(message) {
        this.result = { message };
        this.screen = 'error';
        setTimeout(() => this.reset(), 3000);
    },

    reset() {
        this.pin = '';
        this.kioskPin = '';
        this.askKioskPin = false;
        this.direction = 'auto';
        this.screen = 'input';
    },

    greeting() {
        const h = new Date().getHours();
        return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
    },
}));

window.Alpine = Alpine;
Alpine.start();

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}
