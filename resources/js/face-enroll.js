/*
 | Face enrolment page (admin panel). Uses the panel's Alpine; takes three frontal, real-looking samples and hands the
 | descriptors to the Livewire page (saveSamples). The camera picture itself is never uploaded.
 */
import { FACE_HINTS, loadFaceEngine, readFace } from './face';
import { startCamera } from './shared';

const register = () => window.Alpine.data('faceEnroll', () => ({
    cameraOn: false,
    busy: false,
    status: '',

    async start() {
        try {
            await startCamera(this.$refs.video, 'user');
            this.cameraOn = true;
            this.status = 'Loading face recognition…';
            await loadFaceEngine();
            this.status = 'Ready. Choose the employee and press Capture face.';
        } catch (e) {
            this.status = 'The camera could not start. Allow camera access in the browser.';
        }
    },

    async capture() {
        if (this.busy) return;
        this.busy = true;
        const human = await loadFaceEngine();
        const samples = [];
        const scores = [];
        const until = Date.now() + 15000;
        while (samples.length < 3 && Date.now() < until) {
            const f = await readFace(human, this.$refs.video).catch(() => ({ ok: false, reason: 'none' }));
            if (!f.ok) {
                this.status = FACE_HINTS[f.reason] ?? FACE_HINTS.none;
            } else if (f.real < 0.5 || f.live < 0.5) {
                this.status = 'That does not look like a live face. Use the real person, not a photo.';
            } else if (Math.abs(f.yaw) > 0.2) {
                this.status = 'Look straight at the camera';
            } else {
                samples.push(f.embedding);
                scores.push(f.score);
                this.status = `Sample ${samples.length} of 3 — keep still`;
                await new Promise((r) => setTimeout(r, 500));
                continue;
            }
            await new Promise((r) => setTimeout(r, 150));
        }
        if (samples.length < 3) {
            this.status = 'Could not get a clear face. Try again with more light on the face.';
        } else {
            this.status = 'Saving…';
            await this.$wire.saveSamples(samples, scores);
            this.status = 'Saved.';
        }
        this.busy = false;
    },
}));

if (window.Alpine) register();
else document.addEventListener('alpine:init', register);
