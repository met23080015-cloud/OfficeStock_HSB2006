import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const src = path.join(root, 'src');
const dist = path.join(root, 'dist');

fs.rmSync(dist, { recursive: true, force: true });
fs.cpSync(src, dist, { recursive: true });

const apiBase = process.env.API_BASE_URL || process.env.NEXT_PUBLIC_API_BASE_URL || '';
const config = `window.OFFICE_STOCK_CONFIG = ${JSON.stringify({
  API_BASE_URL: apiBase.replace(/\/+$/, ''),
  BUILD_TIME_UTC: new Date().toISOString()
}, null, 2)};\n`;

fs.writeFileSync(path.join(dist, 'config.js'), config, 'utf8');

if (!apiBase) {
  console.warn('WARNING: API_BASE_URL is empty. Set it in Vercel before production deployment.');
} else {
  console.log(`OfficeStock frontend configured for API: ${apiBase}`);
}

console.log(`Build completed: ${dist}`);
