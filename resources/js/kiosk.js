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
    screen: 'input', // input | done | error
    result: {},
    pending: 0,
    clock: '',
    scanning: false,

    async init() {
        this.tick();
        setInterval(() => this.tick(), 1000);
        await startCamera(this.$refs.video, 'user').catch(() => {});
        this.refreshPending();
        setInterval(() => this.flush(), 15000);
        window.addEventListener('online', () => this.flush());
        if (this.mode === 'qr') this.scanLoop();
    },

    tick() {
        this.clock = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
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
