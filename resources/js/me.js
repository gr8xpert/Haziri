import Alpine from 'alpinejs';
import { captureSelfie, stopCamera, startCamera, api } from './shared';

// Employee self-service PWA (spec §6.6). Server data comes from /me/attendance.
Alpine.data('meApp', ({ canPunch, leaveTypes }) => ({
    tab: 'home',
    data: {},
    month: null,
    busy: false,
    camera: false,
    message: '',
    messageOk: true,
    toast: '',
    fixType: 'add_punch',
    canPunch,
    leaveTypes,

    async load() {
        const q = this.month ? `?month=${this.month}` : '';
        this.data = await api(`/me/attendance${q}`);
        this.month = this.data.month;
    },

    shiftMonth(delta) {
        const [y, m] = this.month.split('-').map(Number);
        const d = new Date(y, m - 1 + delta, 1);
        this.month = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
        this.load();
    },

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
            try {
                await startCamera(this.$refs.video);
                await new Promise((r) => setTimeout(r, 800)); // let exposure settle
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
            });
            this.messageOk = r.inside_geofence;
            this.message = r.inside_geofence
                ? `${r.direction === 'in' ? '✓ In' : '✓ Out'} ${r.time}`
                : `Saved at ${r.time}, but you are ${r.distance_m ?? '?'} m from the office — HR will review it.`;
            await this.load();
        } catch (e) {
            this.messageOk = false;
            this.message = e.message || 'Could not punch. Allow location access and try again.';
        } finally {
            this.busy = false;
        }
    },

    async applyLeave(form) {
        try {
            await api('/me/leave', new FormData(form));
            form.reset();
            this.flash('Leave request sent');
            await this.load();
        } catch (e) {
            this.flash(e.message);
        }
    },

    async requestCorrection(form) {
        try {
            await api('/me/corrections', Object.fromEntries(new FormData(form)));
            form.reset();
            this.flash('Request sent');
        } catch (e) {
            this.flash(e.message);
        }
    },

    async ack(a) {
        await api(`/me/announcements/${a.id}/ack`, {});
        a.acked = true;
    },

    codeColour(code) {
        if (!code) return 'text-gray-300';
        if (['A', 'UL'].includes(code)) return 'text-red-600';
        if (['L', 'HD', 'MO'].includes(code)) return 'text-amber-600';
        if (['WO', 'H'].includes(code)) return 'text-gray-400';
        return 'text-emerald-700';
    },

    flash(text) {
        this.toast = text;
        setTimeout(() => (this.toast = ''), 3500);
    },
}));

window.Alpine = Alpine;
Alpine.start();

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}
