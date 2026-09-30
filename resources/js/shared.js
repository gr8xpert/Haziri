// Helpers shared by the employee PWA and the kiosk.

export async function api(url, body = undefined) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const init = { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token ?? '' }, credentials: 'same-origin' };
    if (body !== undefined) {
        init.method = 'POST';
        if (body instanceof FormData) {
            init.body = body;
        } else {
            init.body = JSON.stringify(body);
            init.headers['Content-Type'] = 'application/json';
        }
    }
    const res = await fetch(url, init);
    const data = res.headers.get('content-type')?.includes('json') ? await res.json() : {};
    if (!res.ok) {
        const first = data.errors ? Object.values(data.errors).flat()[0] : null;
        const err = new Error(first || data.message || `Error ${res.status}`);
        err.status = res.status;
        throw err;
    }
    return data;
}

export async function startCamera(video, facingMode = 'user') {
    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode, width: { ideal: 640 } }, audio: false });
    video.srcObject = stream;
    await video.play();
    return stream;
}

export function stopCamera(video) {
    video?.srcObject?.getTracks().forEach((t) => t.stop());
    if (video) video.srcObject = null;
}

/** JPEG data URL, max 480 px wide (keeps uploads < 100 KB on cheap phones). */
export function captureSelfie(video, width = 480) {
    const scale = Math.min(1, width / (video.videoWidth || width));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round((video.videoWidth || width) * scale);
    canvas.height = Math.round((video.videoHeight || width * 0.75) * scale);
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/jpeg', 0.7);
}
