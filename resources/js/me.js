import Alpine from 'alpinejs';
import { captureSelfie, stopCamera, startCamera, api } from './shared';
import { loadFaceEngine, readFace } from './face';

const CODE_NAMES = {
    P: 'Present', L: 'Late', HD: 'Half day', A: 'Absent', WO: 'Weekly off', H: 'Holiday', OD: 'Official duty',
    WFH: 'Work from home', LV: 'Paid leave', CL: 'Casual leave', SL: 'Sick leave', AL: 'Annual leave', UL: 'Unpaid leave',
    MO: 'Missing punch-out', EX: 'Exempted',
};

const TONES = {
    ok: { cell: 'bg-emerald-50 dark:bg-emerald-500/10', text: 'text-emerald-700 dark:text-emerald-400', chip: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' },
    warn: { cell: 'bg-amber-50 dark:bg-amber-500/10', text: 'text-amber-700 dark:text-amber-400', chip: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300' },
    bad: { cell: 'bg-rose-50 dark:bg-rose-500/10', text: 'text-rose-700 dark:text-rose-400', chip: 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300' },
    leave: { cell: 'bg-sky-50 dark:bg-sky-500/10', text: 'text-sky-700 dark:text-sky-400', chip: 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300' },
    off: { cell: 'bg-zinc-100/70 dark:bg-white/[0.03]', text: 'text-zinc-400 dark:text-zinc-500', chip: 'bg-zinc-100 text-zinc-600 dark:bg-white/10 dark:text-zinc-300' },
    none: { cell: '', text: 'text-zinc-300 dark:text-zinc-600', chip: 'bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-400' },
};

function toneKey(code) {
    const c = (code || '').replace('½', '');
    if (!c) return 'none';
    if (['P', 'OD', 'WFH', 'EX'].includes(c)) return 'ok';
    if (['L', 'HD', 'MO'].includes(c)) return 'warn';
    if (['A', 'UL'].includes(c)) return 'bad';
    if (['WO', 'H', '–', '-'].includes(c)) return 'off';
    return 'leave';
}

function isoDate(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

// Employee self-service PWA (spec §6.6). Server data comes from /me/attendance.
Alpine.data('meApp', ({ canPunch, faceCheck, leaveTypes, locale }) => ({
    tab: 'home',
    data: {},
    loaded: false,
    month: null,
    busy: false,
    camera: false,
    message: '',
    messageOk: true,
    toast: '',
    toastOk: true,
    toastTimer: null,
    fixType: 'add_punch',
    fixDate: '',
    leaveFrom: '',
    selected: null,
    todayIso: isoDate(new Date()),
    canPunch,
    faceCheck, // company checks the selfie's face against the enrolled one
    leaveTypes,
    locale: locale || undefined,

    async load() {
        const q = this.month ? `?month=${this.month}` : '';
        try {
            this.data = await api(`/me/attendance${q}`);
            this.month = this.data.month;
            this.selected = (this.data.calendar ?? []).find((d) => d.date === this.todayIso) ?? null;
        } catch (e) {
            this.flash(e.message || 'Could not load your attendance. Check your connection.', false);
        } finally {
            this.loaded = true;
        }
    },

    shiftMonth(delta) {
        const [y, m] = this.month.split('-').map(Number);
        const d = new Date(y, m - 1 + delta, 1);
        this.month = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
        this.selected = null;
        this.load();
    },

    /* ---------- labels ---------- */

    todayLabel() {
        return new Date().toLocaleDateString(this.locale, { weekday: 'long', day: 'numeric', month: 'long' });
    },

    dayLabel(iso) {
        const [y, m, d] = iso.split('-').map(Number);
        return new Date(y, m - 1, d).toLocaleDateString(this.locale, { weekday: 'short', day: 'numeric', month: 'short' });
    },

    monthLabel() {
        if (!this.month) return '';
        const [y, m] = this.month.split('-').map(Number);
        return new Date(y, m - 1, 1).toLocaleDateString(this.locale, { month: 'long', year: 'numeric' });
    },

    statusLine() {
        const t = this.data.today;
        if (!this.loaded) return '';
        if (!t || !t.in) return 'You have not punched in yet.';
        if (t.in && !t.out) return `At work since ${t.in}.`;
        return `Worked ${t.in} to ${t.out}.`;
    },

    /** Monday-first weekday initials in the user's locale. */
    weekdays() {
        // 1 Jan 2024 was a Monday.
        return Array.from({ length: 7 }, (_, i) => new Date(2024, 0, 1 + i).toLocaleDateString(this.locale, { weekday: 'narrow' }));
    },

    /** Empty cells before day 1 so dates line up under the right weekday. */
    leadBlanks() {
        const first = this.data.calendar?.[0]?.date;
        if (!first) return [];
        const [y, m, d] = first.split('-').map(Number);
        const offset = (new Date(y, m - 1, d).getDay() + 6) % 7;
        return Array.from({ length: offset }, (_, i) => i);
    },

    codeTone(code) {
        return TONES[toneKey(code)];
    },

    codeName(code) {
        if (!code) return 'No record';
        const c = code.replace('½', '');
        const name = CODE_NAMES[c] ?? 'Leave';
        return code.endsWith('½') ? `${name} (half)` : name;
    },

    monthCount(key) {
        return (this.data.calendar ?? []).filter((d) => toneKey(d.code) === key).length;
    },

    requestTone(status) {
        return {
            approved: TONES.ok.chip,
            pending: TONES.warn.chip,
            rejected: TONES.bad.chip,
        }[status] ?? TONES.off.chip;
    },

    startCorrection(date) {
        this.fixDate = date;
        this.tab = 'fix';
    },

    /* ---------- actions ---------- */

    async punch() {
        this.busy = true;
        this.message = '';
        try {
            const position = await new Promise((resolve, reject) =>
                navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }),
            );
            this.camera = true;
            await this.$nextTick();
            let photo = null;
            let face = null;
            try {
                await startCamera(this.$refs.video);
                await new Promise((r) => setTimeout(r, 800)); // let exposure settle
                if (this.faceCheck) face = await this.readOwnFace();
                photo = captureSelfie(this.$refs.video);
            } catch (e) {
                // No camera permission: punch without a photo; HR sees it as unverified.
            } finally {
                stopCamera(this.$refs.video);
                this.camera = false;
            }
            const r = await api('/me/punch', {
                lat: position.coords.latitude,
                lng: position.coords.longitude,
                accuracy: Math.round(position.coords.accuracy),
                photo,
                ...(face ? { face } : {}),
            });
            this.messageOk = r.outcome === 'stored';
            this.message = r.message; // the server's own reason: duplicate, distance, office internet, face
            await this.load();
        } catch (e) {
            this.messageOk = false;
            this.message = e.message || 'Could not punch. Allow location access and try again.';
        } finally {
            this.busy = false;
        }
    },

    /** Descriptor of the face in the selfie camera (null if none within ~3 s); matching happens on the server. */
    async readOwnFace() {
        try {
            const human = await loadFaceEngine();
            for (let i = 0; i < 12; i++) {
                const f = await readFace(human, this.$refs.video).catch(() => ({ ok: false }));
                if (f.ok) return f.embedding;
                await new Promise((r) => setTimeout(r, 250));
            }
        } catch (e) {
            /* engine could not load on this phone: the punch goes to HR for review */
        }
        return null;
    },

    async applyLeave(form) {
        this.busy = true;
        try {
            await api('/me/leave', new FormData(form));
            form.reset();
            this.leaveFrom = '';
            this.flash('Leave request sent');
            await this.load();
        } catch (e) {
            this.flash(e.message, false);
        } finally {
            this.busy = false;
        }
    },

    async requestCorrection(form) {
        this.busy = true;
        try {
            await api('/me/corrections', Object.fromEntries(new FormData(form)));
            form.reset();
            this.fixDate = '';
            this.fixType = 'add_punch';
            this.flash('Request sent');
        } catch (e) {
            this.flash(e.message, false);
        } finally {
            this.busy = false;
        }
    },

    /** Ask a colleague to swap shifts. */
    async requestSwap(form) {
        this.busy = true;
        try {
            await api('/me/shift-swaps', Object.fromEntries(new FormData(form)));
            form.reset();
            this.flash('Swap request sent to your colleague');
            await this.load();
        } catch (e) {
            this.flash(e.message, false);
        } finally {
            this.busy = false;
        }
    },

    async answerSwap(s, accept) {
        try {
            await api(`/me/shift-swaps/${s.id}/answer`, { accept });
            this.flash(accept ? 'Accepted. It now waits for approval.' : 'Declined');
            await this.load();
        } catch (e) {
            this.flash(e.message, false);
        }
    },

    /** Take or give back an optional holiday. */
    async toggleOptional(h) {
        try {
            const r = await api(`/me/optional-holidays/${h.id}`, {});
            h.taken = r.taken;
            this.flash(r.taken ? `You are off on ${h.date}.` : `You will work on ${h.date}.`);
        } catch (e) {
            this.flash(e.message, false);
        }
    },

    async ack(a) {
        await api(`/me/announcements/${a.id}/ack`, {});
        a.acked = true;
    },

    flash(text, ok = true) {
        this.toast = text;
        this.toastOk = ok;
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => (this.toast = ''), 3500);
    },
}));

window.Alpine = Alpine;
Alpine.start();

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}
