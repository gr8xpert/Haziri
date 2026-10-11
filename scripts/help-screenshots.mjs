// Takes the user guide's screenshots (public/help-img) from a demo company: every `screens:` entry in resources/docs/*.md.
// Usage: node scripts/help-screenshots.mjs http://kunsystems.hazri.localhost:8000 [name-prefix,name-prefix] [path/to/chrome]
// An entry is `name=/admin/path`, optionally `>> Button label` to click first (opens a form or modal).
// /admin pages are taken as the demo owner on a desktop screen, /me pages as the demo employee on a phone screen.
// Needs Chrome or Edge; talks to it over the DevTools protocol, so no extra packages.
import { spawn } from 'node:child_process';
import { mkdirSync, mkdtempSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const base = (process.argv[2] ?? '').replace(/\/$/, '');
const only = process.argv[3] ?? '';
if (!base) {
    console.error('Give the demo company URL, e.g. http://kunsystems.hazri.localhost:8000');
    process.exit(1);
}
const chromePath = process.argv[4]
    ?? (process.platform === 'win32' ? 'C:/Program Files/Google/Chrome/Application/chrome.exe' : 'google-chrome');

const shots = [];
for (const file of readdirSync('resources/docs').filter((f) => f.endsWith('.md'))) {
    const head = readFileSync(join('resources/docs', file), 'utf8').replace(/\r\n/g, '\n').match(/^---\n([\s\S]*?)\n---/);
    const line = head?.[1].split('\n').find((l) => l.startsWith('screens:'));
    for (const entry of (line?.slice(8) ?? '').split('|').map((s) => s.trim()).filter(Boolean)) {
        const [name, target] = entry.split(/=(.*)/s).map((s) => s.trim());
        const [path, click] = target.split('>>').map((s) => s.trim());
        if (only.split(',').some((o) => name.startsWith(o))) shots.push({ name, path, click });
    }
}
console.log(`${shots.length} screenshots to take`);

const port = 9334;
const profile = mkdtempSync(join(tmpdir(), 'haziri-help-'));
const chrome = spawn(chromePath, [
    '--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`,
    '--window-size=1366,850', '--hide-scrollbars', 'about:blank',
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
        if (await run(expression)) return true;
        await sleep(250);
    }
    return false;
};
const open = async (url) => {
    await send('Page.navigate', { url });
    await sleep(500);
    await waitFor(`document.readyState === 'complete'`);
};
const screen = async (phone) => {
    // Light theme: easier to read in a printed or bright guide.
    await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: 'light' }] });
    return send('Emulation.setDeviceMetricsOverride', phone
    ? { width: 390, height: 844, deviceScaleFactor: 2, mobile: true }
    : { width: 1366, height: 850, deviceScaleFactor: 1, mobile: false });
};

async function loginAdmin() {
    await screen(false);
    await open(`${base}/admin/login`);
    if (!(await waitFor(`!!document.querySelector('.hz-demo-logins button')`))) throw new Error('No demo login box: is this the demo company?');
    await sleep(800); // Livewire boots
    await run(`document.querySelector('.hz-demo-logins button').click()`);
    await sleep(800);
    await run(`document.querySelector('form button[type=submit]').click()`);
    if (!(await waitFor(`location.pathname === '/admin'`))) throw new Error('Admin login failed');
    await run(`localStorage.setItem('theme', 'light')`); // Filament's own theme switch
}

async function loginStaff() {
    await screen(true);
    await open(`${base}/me/login`);
    await waitFor(`[...document.querySelectorAll('button')].some((b) => b.textContent.includes('Fill it in for me'))`);
    await sleep(600);
    await run(`[...document.querySelectorAll('button')].find((b) => b.textContent.includes('Fill it in for me')).click()`);
    await sleep(400);
    await run(`document.getElementById('me-email').closest('form').requestSubmit()`);
    if (!(await waitFor(`location.pathname === '/me' || location.pathname === '/me/'`))) throw new Error('Staff login failed');
}

const clickLabel = (label) => run(`(() => {
    const want = ${JSON.stringify(label)}.toLowerCase();
    const el = [...document.querySelectorAll('button, a, [role=button]')]
        .filter((e) => e.offsetParent !== null)
        .find((e) => e.textContent.trim().replace(/\\s+/g, ' ').toLowerCase() === want)
        ?? [...document.querySelectorAll('button, a, [role=button]')].filter((e) => e.offsetParent !== null)
            .find((e) => e.textContent.trim().toLowerCase().includes(want));
    if (!el) return false;
    el.click();
    return true;
})()`);

mkdirSync('public/help-img', { recursive: true });
let asStaff = null;
const failed = [];
try {
    await send('Page.enable');
    for (const shot of shots.sort((a, b) => a.path.startsWith('/me') - b.path.startsWith('/me'))) {
        const staff = shot.path.startsWith('/me');
        if (asStaff !== staff) {
            await (staff ? loginStaff() : loginAdmin());
            asStaff = staff;
        }
        await open(base + shot.path);
        await waitFor(`!document.querySelector('[wire\\\\:loading]:not([style*="none"])')`, 4000);
        // Lazy widgets (dashboard, analytics) load after the page.
        await waitFor(`!document.querySelector('[x-intersect*="__lazyLoad"], [x-init*="__lazyLoad"]')`, 15000);
        await sleep(1500); // tables, charts and fonts settle
        if (shot.click) {
            if (!(await clickLabel(shot.click))) {
                failed.push(`${shot.name}: no button "${shot.click}"`);
                continue;
            }
            // A modal, slide-over or new page; give its form time to render.
            await waitFor(`!!document.querySelector('.fi-modal-window:not([style*="display: none"]) .fi-modal-content, .fi-modal.fi-modal-open')`, 10000);
            await sleep(1500);
        }
        const { result } = await send('Page.captureScreenshot', { format: 'jpeg', quality: 80 });
        writeFileSync(`public/help-img/${shot.name}.jpg`, Buffer.from(result.data, 'base64'));
        const status = await run(`document.title`);
        console.log(`${shot.name}  (${status})`);
    }
} finally {
    ws.close();
    chrome.kill();
    await sleep(500);
    try { rmSync(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 400 }); } catch { /* Chrome still closing */ }
}
if (failed.length) {
    console.log(`\nNot taken:\n${failed.join('\n')}`);
}
