/*
 | Face engine for the kiosk and face enrolment, on @vladmandic/human. Loaded on demand (its own chunk, models from
 | /models/human/ on our server), so pages without face recognition never download it.
 | Only the 1024-number face descriptor is sent to the server; matching happens there.
 */
let enginePromise = null;

export function loadFaceEngine() {
    enginePromise ??= (async () => {
        const mod = await import('@vladmandic/human');
        const Human = mod.Human ?? mod.default;
        const human = new Human({
            modelBasePath: '/models/human/',
            debug: false,
            face: {
                enabled: true,
                detector: { rotation: true, maxDetected: 1, minConfidence: 0.6 },
                mesh: { enabled: true },
                iris: { enabled: false },
                description: { enabled: true },
                emotion: { enabled: false },
                antispoof: { enabled: true },
                liveness: { enabled: true },
            },
            body: { enabled: false },
            hand: { enabled: false },
            object: { enabled: false },
            gesture: { enabled: false },
            segmentation: { enabled: false },
        });
        await human.load();
        await human.warmup();
        return human;
    })();
    return enginePromise;
}

/**
 * The single face in front of the camera, or a reason there isn't a usable one.
 * yaw: head turn (radians, negative / positive = the two sides); real / live: anti-spoof and liveness scores (0..1).
 */
export async function readFace(human, video) {
    const result = await human.detect(video);
    const faces = result.face ?? [];
    if (faces.length === 0) return { ok: false, reason: 'none' };
    if (faces.length > 1) return { ok: false, reason: 'many' };
    const f = faces[0];
    const width = video.videoWidth || 640;
    if ((f.box?.[2] ?? 0) < width * 0.18) return { ok: false, reason: 'far' };
    return {
        ok: (f.score ?? 0) >= 0.7 && Array.isArray(f.embedding) && f.embedding.length > 0,
        reason: 'ok',
        score: f.score ?? 0,
        yaw: f.rotation?.angle?.yaw ?? 0,
        real: f.real ?? 1,
        live: f.live ?? 1,
        embedding: Array.from(f.embedding ?? []),
    };
}

export const FACE_HINTS = {
    none: 'Look at the camera',
    many: 'One person at a time, please',
    far: 'Come a little closer',
};
