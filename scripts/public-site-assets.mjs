import { mkdirSync, copyFileSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
const root = dirname(dirname(fileURLToPath(import.meta.url)));
const output = join(root, 'public-site/public/img/norocel');
mkdirSync(output, {recursive:true});
const frame = (bg, content) => `<svg xmlns="http://www.w3.org/2000/svg" width="640" height="400" viewBox="0 0 640 400"><rect width="640" height="400" fill="${bg}"/>${content}</svg>`;
writeFileSync(join(output,'blog-budget.svg'), frame('#edf1fe', '<rect x="135" y="65" width="370" height="275" rx="24" fill="white"/><rect x="165" y="95" width="95" height="10" rx="5" fill="#c5cee5"/><circle cx="260" cy="215" r="68" fill="none" stroke="#edf1fe" stroke-width="20"/><circle cx="260" cy="215" r="68" fill="none" stroke="#3659e3" stroke-width="20" stroke-dasharray="280 147" transform="rotate(-90 260 215)"/><text x="260" y="223" text-anchor="middle" font-family="sans-serif" font-size="24" font-weight="bold" fill="#17233b">4 320</text><rect x="370" y="170" width="90" height="10" rx="5" fill="#3659e3"/><rect x="370" y="204" width="72" height="10" rx="5" fill="#a4b5f3"/><rect x="370" y="238" width="52" height="10" rx="5" fill="#f1c878"/><rect x="370" y="272" width="84" height="10" rx="5" fill="#e6eaf2"/>'));
writeFileSync(join(output,'blog-savings.svg'), frame('#eaf4ee', '<rect x="70" y="112" width="220" height="186" rx="22" fill="#3659e3"/><text x="97" y="158" font-family="sans-serif" font-size="14" fill="#e0e7ff">MDL</text><text x="97" y="206" font-family="sans-serif" font-size="31" font-weight="bold" fill="white">12 450</text><path d="M312 203h44m-10-10 10 10-10 10" stroke="#187b5b" stroke-width="3" fill="none"/><rect x="380" y="72" width="195" height="114" rx="20" fill="white"/><text x="405" y="110" font-family="sans-serif" font-size="14" fill="#64718a">USD</text><text x="405" y="150" font-family="sans-serif" font-size="29" font-weight="bold" fill="#17233b">1 500</text><rect x="380" y="208" width="195" height="114" rx="20" fill="white"/><text x="405" y="246" font-family="sans-serif" font-size="14" fill="#64718a">EUR</text><text x="405" y="286" font-family="sans-serif" font-size="29" font-weight="bold" fill="#17233b">640</text>'));
writeFileSync(join(output,'blog-goals.svg'), frame('#fff4df', '<rect x="120" y="90" width="400" height="220" rx="24" fill="white"/><rect x="151" y="121" width="55" height="55" rx="16" fill="#edf1fe"/><circle cx="178" cy="148" r="17" fill="none" stroke="#3659e3" stroke-width="2"/><circle cx="178" cy="148" r="9" fill="none" stroke="#3659e3" stroke-width="2"/><rect x="229" y="134" width="148" height="10" rx="5" fill="#c5cee5"/><rect x="229" y="155" width="107" height="7" rx="3" fill="#e6eaf2"/><text x="151" y="229" font-family="sans-serif" font-size="30" font-weight="bold" fill="#17233b">850 USD</text><text x="305" y="229" font-family="sans-serif" font-size="17" fill="#64718a">/ 1 800 USD</text><rect x="151" y="254" width="338" height="8" rx="4" fill="#edf1fe"/><rect x="151" y="254" width="159" height="8" rx="4" fill="#3659e3"/>'));
const siteDir = join(root,'public-site/public/site');
for (const subset of ['latin', 'latin-ext', 'cyrillic']) {
 copyFileSync(join(root,`frontend/node_modules/@fontsource-variable/manrope/files/manrope-${subset}-wght-normal.woff2`), join(siteDir,subset === 'latin' ? 'manrope.woff2' : `manrope-${subset}.woff2`));
}
const target = join(root,'backend/public');
mkdirSync(join(target,'site'),{recursive:true});
mkdirSync(join(target,'img/norocel'),{recursive:true});
for (const name of ['site.css','site.js','favicon.svg','manrope.woff2','manrope-latin-ext.woff2','manrope-cyrillic.woff2']) copyFileSync(join(siteDir,name),join(target,'site',name));
for (const name of ['blog-budget.svg','blog-savings.svg','blog-goals.svg','social-preview.png']) copyFileSync(join(output,name),join(target,'img/norocel',name));
const manifest = join(root,'backend/public/manifest.webmanifest');
const data = JSON.parse(readFileSync(manifest,'utf8')); data.start_url = '/app'; writeFileSync(manifest, JSON.stringify(data)+'\n');
console.log('Public site assets synchronized; application start URL /app');
