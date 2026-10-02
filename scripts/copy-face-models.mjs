// Copies the face models used by resources/js/face.js from @vladmandic/human into public/models/human/,
// so they are served from our own server (no third-party CDN). Runs before every `npm run build`.
import { copyFileSync, existsSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const from = 'node_modules/@vladmandic/human/models';
const to = 'public/models/human';
const models = ['blazeface', 'facemesh', 'faceres', 'antispoof', 'liveness'];

if (!existsSync(from)) {
    console.warn(`face models: ${from} not found — run npm install`);
    process.exit(0);
}
mkdirSync(to, { recursive: true });
for (const name of models) {
    for (const ext of ['json', 'bin']) {
        copyFileSync(join(from, `${name}.${ext}`), join(to, `${name}.${ext}`));
    }
}
console.log(`face models: copied ${models.length} models to ${to}`);
