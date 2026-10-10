// Retakes the landing page dashboard screenshot from a demo company (logs in with its demo owner login).
// Usage: node scripts/marketing-screenshots.mjs http://kunsystems.hazri.localhost:8000 [path/to/chrome]
// Needs Chrome or Edge; talks to it over the DevTools protocol, so no extra packages.
import { spawn } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const base = (process.argv[2] ?? '').replace(/\/$/, '');
if (!base) {
    console.error('Give the demo company URL, e.g. http://kunsystems.hazri.localhost:8000');
    process.exit(1);
}
const chromePath = process.argv[3]
    ?? (process.platform === 'win32' ? 'C:/Program Files/Google/Chrome/Application/chrome.exe' : 'google-chrome');
const port = 9333;
const profile = mkdtempSync(join(tmpdir(), 'haziri-shot-'));
const chrome = spawn(chromePath, [
    '--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`,
    '--window-size=1440,900', '--force-device-scale-factor=1', '--hide-scrollbars', 'about:blank',
], { stdio: 'ignore' });

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function devtools() {
    for (let i = 0; i < 50; i++) {
        try {
            const tabs = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
            const page = tabs.find((t) => t.type === 'page');
            if (page) return page.webSocketDebuggerUrl;
        } catch { /* Chrome still starting */ }
        await sleep(200);
    }
    throw new Error('Chrome did not start');
}

const ws = new WebSocket(await devtools());
await new Promise((r) => ws.addEventListener('open', r, { once: true }));
let nextId = 0;
const pending = new Map();
ws.addEventListener('message', (e) => {
    const msg = JSON.parse(e.data);
    if (msg.id && pending.has(msg.id)) {
        pending.get(msg.id)(msg);
        pending.delete(msg.id);
    }
});
const send = (method, params = {}) => new Promise((resolve) => {
    const id = ++nextId;
    pending.set(id, resolve);
    ws.send(JSON.stringify({ id, method, params }));
});
const run = async (expression) => (await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true })).result?.result?.value;
const waitFor = async (expression, ms = 20000) => {
    for (let t = 0; t < ms; t += 250) {
        if (await run(expression)) return;
        await sleep(250);
    }
    throw new Error(`Timed out waiting for: ${expression}`);
};

try {
    await send('Page.enable');
    await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
    await send('Page.navigate', { url: `${base}/admin/login` });
    await waitFor(`!!document.querySelector('.hz-demo-logins button')`);
    await sleep(800); // Livewire boots
    await run(`document.querySelector('.hz-demo-logins button').click()`);
    await sleep(800);
    await run(`document.querySelector('form button[type=submit]').click()`);
    await waitFor(`location.pathname === '/admin' && !!document.querySelector('.fi-wi-stats-overview, .fi-wi')`);
    await sleep(2500); // widgets and fonts settle

    const shot = await send('Page.captureScreenshot', { format: 'jpeg', quality: 82, clip: { x: 0, y: 0, width: 1440, height: 900, scale: 1 } });
    const out = 'public/images/marketing/hazri-dashboard.jpg';
    writeFileSync(out, Buffer.from(shot.result.data, 'base64'));
    console.log(`Saved ${out}`);
} finally {
    ws.close();
    chrome.kill();
    await sleep(500);
    rmSync(profile, { recursive: true, force: true });
}
