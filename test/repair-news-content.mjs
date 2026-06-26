import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const jsonPath = path.join(__dirname, 'data', 'news.json'); // SSOT: test/data/news.json (see news_data_json_path)
const newsDir = path.join(__dirname, 'news');

const restoreFromHtml = [
  'nove-obladnannia-dlia-tuteshnikh-2026-06-20',
  'film-pro-chornobyl-2026-04-26',
];

function extractLegacyContent(html) {
  const marker = '<div class="news-article__content">';
  const start = html.indexOf(marker);
  if (start === -1) return null;
  const innerStart = start + marker.length;
  const galleryIdx = html.indexOf('<section class="news-article__section', innerStart);
  const sliceEnd = galleryIdx === -1 ? html.length : galleryIdx;
  let chunk = html.slice(innerStart, sliceEnd).trim();
  if (chunk.endsWith('</div>')) {
    chunk = chunk.slice(0, -6).trim();
  }
  return chunk;
}

function collapseWhitespace(text) {
  return text.replace(/\s+/g, ' ').trim();
}

function normalizeParagraphBlock(block) {
  const match = block.match(/^<p(\s[^>]*)?>([\s\S]*)<\/p>$/i);
  if (!match) {
    return collapseWhitespace(block).replace(/>\s+</g, '><');
  }
  const attrs = match[1] ?? '';
  let inner = match[2];
  inner = inner.replace(/\s+/g, ' ');
  inner = inner.replace(/>\s+</g, '><');
  inner = inner.trim();
  return `<p${attrs}>${inner}</p>`;
}

function normalizeContentHtml(html) {
  let s = html.replace(/<!--[\s\S]*?-->/g, '');
  s = s.replace(/\r\n|\r|\n|\t/g, ' ');

  const parts = [];
  const re = /<p(\s[^>]*)?>[\s\S]*?<\/p>/gi;
  let m;
  while ((m = re.exec(s)) !== null) {
    parts.push(normalizeParagraphBlock(m[0]));
  }

  if (parts.length > 0) {
    return parts.join('');
  }

  return collapseWhitespace(s).replace(/>\s+</g, '><');
}

const restored = {};
for (const slug of restoreFromHtml) {
  const file = path.join(newsDir, `${slug}.html`);
  const html = fs.readFileSync(file, 'utf8');
  const extracted = extractLegacyContent(html);
  if (!extracted) {
    console.error(`Failed to extract: ${slug}`);
    process.exit(1);
  }
  restored[slug] = normalizeContentHtml(extracted);
}

const raw = fs.readFileSync(jsonPath, 'utf8');
const data = JSON.parse(raw);

const ts = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
const backupPath = `${jsonPath}.bak-${ts}`;
fs.copyFileSync(jsonPath, backupPath);

for (const item of data.items) {
  const slug = item.slug;
  if (restored[slug]) {
    item.content = restored[slug];
  } else {
    item.content = normalizeContentHtml(item.content || '');
  }
}

const encoded = JSON.stringify(data, null, 4) + '\n';
const tempPath = `${jsonPath}.tmp-${process.pid}`;
fs.writeFileSync(tempPath, encoded, 'utf8');
fs.renameSync(tempPath, jsonPath);

console.log(`Backup: ${backupPath}`);
console.log(`Restored from HTML: ${Object.keys(restored).join(', ')}`);
console.log(`Normalized content for all ${data.items.length} items.`);
