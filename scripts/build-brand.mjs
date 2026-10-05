import { readFileSync, writeFileSync, mkdirSync, copyFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import sharp from "../frontend/node_modules/sharp/lib/index.js";

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const output = join(root, "backend/public/icons");
mkdirSync(output, { recursive: true });

// Trace the supplied wordmark into vector outlines, including the counters.
// The N, coin and pocket are separate SVG paths so the loader can animate them.
const { data, info } = await sharp(join(root, "logo.png"))
  .removeAlpha()
  .raw()
  .toBuffer({ resolveWithObject: true });
const dark = (x, y) => {
  if (x < 400 || x >= 1145 || y < 550 || y >= 735) return false;
  const i = (y * info.width + x) * info.channels;
  return data[i] * 0.2126 + data[i + 1] * 0.7152 + data[i + 2] * 0.0722 < 125;
};
const edges = new Map();
const edge = (x, y, a, b) => edges.set(`${x},${y}`, [a, b]);
for (let y = 550; y < 735; y++)
  for (let x = 400; x < 1145; x++) {
    if (!dark(x, y)) continue;
    if (!dark(x, y - 1)) edge(x, y, x + 1, y);
    if (!dark(x + 1, y)) edge(x + 1, y, x + 1, y + 1);
    if (!dark(x, y + 1)) edge(x + 1, y + 1, x, y + 1);
    if (!dark(x - 1, y)) edge(x, y + 1, x, y);
  }
function simplify(points, tolerance = 0.8) {
  if (points.length < 3) return points;
  const [ax, ay] = points[0],
    [bx, by] = points.at(-1);
  const dx = bx - ax,
    dy = by - ay;
  let max = 0,
    index = 0;
  for (let i = 1; i < points.length - 1; i++) {
    const [x, y] = points[i];
    const t = Math.max(
      0,
      Math.min(1, ((x - ax) * dx + (y - ay) * dy) / (dx * dx + dy * dy || 1)),
    );
    const distance = Math.hypot(x - ax - t * dx, y - ay - t * dy);
    if (distance > max) {
      max = distance;
      index = i;
    }
  }
  return max > tolerance
    ? [
        ...simplify(points.slice(0, index + 1), tolerance).slice(0, -1),
        ...simplify(points.slice(index), tolerance),
      ]
    : [points[0], points.at(-1)];
}
const contours = [];
while (edges.size) {
  const start = edges.keys().next().value;
  let key = start;
  const points = [start.split(",").map(Number)];
  do {
    const next = edges.get(key);
    if (!next) break;
    edges.delete(key);
    points.push(next);
    key = next.join(",");
  } while (key !== start);
  if (points.length > 25)
    contours.push(
      simplify(points)
        .map(([x, y], i) => `${i ? "L" : "M"}${x - 103} ${y - 480}`)
        .join("") + "Z",
    );
}
const defs = `<defs>
  <linearGradient id="ribbon" x1="40" y1="35" x2="240" y2="264" gradientUnits="userSpaceOnUse"><stop stop-color="#5082ff"/><stop offset="1" stop-color="#3159ed"/></linearGradient>
  <linearGradient id="fold" x1="36" y1="130" x2="105" y2="255" gradientUnits="userSpaceOnUse"><stop stop-color="#1d35b3"/><stop offset="1" stop-color="#335ff5"/></linearGradient>
  <linearGradient id="pocket" x1="226" y1="82" x2="249" y2="177" gradientUnits="userSpaceOnUse"><stop stop-color="#3564f5"/><stop offset="1" stop-color="#1934b5"/></linearGradient>
  <linearGradient id="gold" x1="155" y1="30" x2="217" y2="153" gradientUnits="userSpaceOnUse"><stop stop-color="#ffd85b"/><stop offset="1" stop-color="#ffb32b"/></linearGradient>
  <clipPath id="coin-space"><path d="M0 -120H280V204L0 -76Z"/></clipPath>
</defs>`;
const ribbon = `<path fill="url(#ribbon)" d="M14 88C14 59 37 32 66 32C81 32 94 39 106 51L232 177C246 190 270 180 270 159V212C270 241 247 263 219 263C204 263 192 257 180 245L108 173V216C108 242 87 264 61 264C35 264 14 243 14 216Z"/>
<path fill="url(#fold)" d="M14 164C22 131 53 116 77 136L108 166V216C108 242 87 264 61 264C35 264 14 243 14 216Z"/>`;
const pocket = `<path fill="url(#pocket)" d="M270 82C237 80 211 101 211 131C211 145 218 157 229 170C244 187 270 175 270 155Z"/>`;
const coin = `<g class="coin"><circle cx="191" cy="90" r="69" fill="url(#gold)"/><path d="M192 47C211 48 225 58 232 76" fill="none" stroke="#ffe695" stroke-width="10" stroke-linecap="round"/><circle cx="172" cy="51" r="6" fill="#ffe695"/></g>`;
const drawing = `${defs}<g class="mark"><g clip-path="url(#coin-space)">${coin}</g>${ribbon}${pocket}</g>`;
const svg = (viewBox, content) =>
  `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${viewBox}">${content}</svg>\n`;
const mark = svg("0 0 280 280", drawing);
writeFileSync(join(output, "mark.svg"), mark);
for (const [name, color] of [
  ["logo.svg", "#111d49"],
  ["logo-light.svg", "#eef2fb"],
]) {
  writeFileSync(
    join(output, name),
    svg(
      "0 0 1040 280",
      `${drawing}<path fill="${color}" fill-rule="evenodd" d="${contours.join("")}"/>`,
    ),
  );
}
writeFileSync(
  join(output, "loader.svg"),
  svg(
    "0 -120 280 420",
    `<style>
  .coin{animation:coin-drop 2.2s infinite;transform-origin:191px 90px}
  .mark{animation:pocket-settle 2.2s infinite;transform-origin:210px 250px}
  @keyframes coin-drop{
    0%,8%{transform:translate(14px,-130px) rotate(-14deg);opacity:0}
    15%{opacity:1;transform:translate(12px,-115px) rotate(-12deg);animation-timing-function:cubic-bezier(.5,0,.9,.6)}
    43%{transform:translate(0,0) rotate(0);opacity:1;animation-timing-function:ease-out}
    51%{transform:translate(0,-13px) rotate(4deg)}
    60%,72%{transform:translate(0,0) rotate(0);opacity:1}
    87%,100%{transform:translate(12px,98px) rotate(8deg);opacity:0}
  }
  @keyframes pocket-settle{0%,41%,60%,100%{transform:scale(1)}46%{transform:scale(1.025,.975)}53%{transform:scale(.99,1.01)}}
  @media(prefers-reduced-motion:reduce){.coin,.mark{animation:none}}
</style>${drawing}`,
  ),
);
copyFileSync(join(output, "mark.svg"), join(output, "favicon.svg"));
const png = async (size, inset, background = "#ffffff") => {
  const symbol = await sharp(Buffer.from(mark))
    .resize(size - 2 * inset)
    .png()
    .toBuffer();
  return sharp({
    create: { width: size, height: size, channels: 4, background },
  })
    .composite([{ input: symbol, left: inset, top: inset }])
    .png()
    .toBuffer();
};
for (const size of [192, 512]) {
  writeFileSync(
    join(output, `icon-${size}.png`),
    await png(size, Math.round(size * 0.06)),
  );
  // Everything stays inside the central 80% safe circle, including the coin.
  writeFileSync(
    join(output, `maskable-${size}.png`),
    await png(size, Math.round(size * 0.22)),
  );
}
writeFileSync(join(output, "apple-touch-icon.png"), await png(180, 16));
const sizes = [16, 32, 48];
const images = await Promise.all(
  sizes.map((size) => png(size, 0, { r: 255, g: 255, b: 255, alpha: 0 })),
);
const header = Buffer.alloc(6 + 16 * sizes.length);
header.writeUInt16LE(1, 2);
header.writeUInt16LE(sizes.length, 4);
let offset = header.length;
images.forEach((image, i) => {
  const p = 6 + i * 16;
  header[p] = header[p + 1] = sizes[i];
  header.writeUInt16LE(1, p + 4);
  header.writeUInt16LE(32, p + 6);
  header.writeUInt32LE(image.length, p + 8);
  header.writeUInt32LE(offset, p + 12);
  offset += image.length;
});
writeFileSync(
  join(root, "backend/public/favicon.ico"),
  Buffer.concat([header, ...images]),
);
copyFileSync(join(root, "assets/brand/loader.css"), join(output, "loader.css"));
copyFileSync(join(root, "assets/brand/boot.js"), join(output, "boot.js"));
for (const directory of ["frontend/public", "public-site/public"]) {
  const target = join(root, directory, "icons");
  mkdirSync(target, { recursive: true });
  for (const name of [
    "mark.svg",
    "logo.svg",
    "logo-light.svg",
    "loader.svg",
    "loader.css",
    "boot.js",
    "favicon.svg",
    "apple-touch-icon.png",
    "icon-192.png",
    "icon-512.png",
    "maskable-192.png",
    "maskable-512.png",
  ])
    copyFileSync(join(output, name), join(target, name));
  copyFileSync(
    join(root, "backend/public/favicon.ico"),
    join(root, directory, "favicon.ico"),
  );
}
copyFileSync(
  join(output, "favicon.svg"),
  join(root, "public-site/public/site/favicon.svg"),
);
const social = svg(
  "0 0 1200 630",
  `<rect width="1200" height="630" fill="#f5f7fc"/>
  <g transform="translate(65 50) scale(.42)">${drawing}<path fill="#111d49" fill-rule="evenodd" d="${contours.join("")}"/></g>
  <g font-family="Segoe UI, sans-serif" fill="#17233b">
    <text x="70" y="310" font-size="60" font-weight="700">Деньги проще,</text>
    <text x="70" y="390" font-size="60" font-weight="700">когда всё видно.</text>
    <text x="75" y="525" font-size="26" fill="#64718a">Счета · накопления · финансовые цели</text>
  </g>
  <rect x="780" y="140" width="350" height="265" rx="28" fill="#3659e3"/>
  <g font-family="Segoe UI, sans-serif" fill="white"><text x="815" y="200" font-size="23">Основной бюджет</text><text x="812" y="305" font-size="64">12 450</text><text x="818" y="350" font-size="24">MDL</text></g>
  <rect x="845" y="374" width="315" height="134" rx="24" fill="white" stroke="#e6eaf2"/>
  <g font-family="Segoe UI, sans-serif"><text x="872" y="421" font-size="22" fill="#64718a">Накопления</text><text x="872" y="471" font-size="33" fill="#17233b">1 500 USD</text></g>`,
);
await sharp(Buffer.from(social))
  .resize(1200, 630)
  .png()
  .toFile(join(root, "public-site/public/img/norocel/social-preview.png"));
console.log(
  "Norocel logo, animated loader, favicons and PWA icons generated from logo.png",
);
