import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const jsonPath = path.join(__dirname, 'data', 'news.json'); // SSOT: data/news.json (see news_data_json_path)

/** @type {Record<string, string>} */
const UKR_MAP = {
  а: 'a',
  б: 'b',
  в: 'v',
  г: 'h',
  ґ: 'g',
  д: 'd',
  е: 'e',
  є: 'ye',
  ж: 'zh',
  з: 'z',
  и: 'y',
  і: 'i',
  ї: 'yi',
  й: 'y',
  к: 'k',
  л: 'l',
  м: 'm',
  н: 'n',
  о: 'o',
  п: 'p',
  р: 'r',
  с: 's',
  т: 't',
  у: 'u',
  ф: 'f',
  х: 'kh',
  ц: 'ts',
  ч: 'ch',
  ш: 'sh',
  щ: 'shch',
  ь: '',
  ю: 'yu',
  я: 'ya',
  '’': '',
  "'": '',
  'ʼ': '',
};

function transliterateForSlug(text) {
  const lower = text.toLocaleLowerCase('uk-UA');
  let out = '';
  for (const char of lower) {
    if (UKR_MAP[char] !== undefined) {
      out += UKR_MAP[char];
      continue;
    }
    if (/[a-z0-9]/.test(char)) {
      out += char;
      continue;
    }
    if (/\s/u.test(char)) {
      out += ' ';
    }
  }
  return out;
}

function sanitizeSlugCandidate(slug) {
  let s = transliterateForSlug(slug.trim());
  s = s.replace(/[^a-z0-9]+/g, '-');
  s = s.replace(/^-+|-+$/g, '');
  s = s.replace(/-+/g, '-');
  return s;
}

function normalizeArticleSlug(slug) {
  const t = slug.trim();
  if (t === '' || t.length > 200) return null;
  if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(t)) return null;
  return t;
}

function slugFromTitleAndDate(title, date) {
  let base = sanitizeSlugCandidate(title);
  if (base === '') base = 'news';
  const suffix = `-${date}`;
  const maxBaseLen = 200 - suffix.length;
  if (maxBaseLen < 1) return null;
  if (base.length > maxBaseLen) {
    base = base.slice(0, maxBaseLen).replace(/^-+|-+$/g, '');
    if (base === '') base = 'news';
  }
  return normalizeArticleSlug(base + suffix);
}

function loadJson(raw) {
  try {
    return JSON.parse(raw);
  } catch {
    const fixed = raw.replace(/(\})\s*(\{)/g, '$1,\n        $2');
    return JSON.parse(fixed);
  }
}

let raw = fs.readFileSync(jsonPath, 'utf8');
const data = loadJson(raw);

if (!data?.items || !Array.isArray(data.items)) {
  console.error('Invalid news.json structure');
  process.exit(1);
}

const ts = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
const backupPath = `${jsonPath}.bak-${ts}`;
fs.copyFileSync(jsonPath, backupPath);

const changes = [];
const seen = new Set();

for (const item of data.items) {
  if (!item || typeof item !== 'object') continue;
  const title = String(item.title ?? '').trim();
  const date = String(item.date ?? '').trim();
  const oldSlug = String(item.slug ?? '');

  if (!title || !/^\d{4}-\d{2}-\d{2}$/.test(date)) {
    console.error('Missing title or date:', item);
    process.exit(1);
  }

  const newSlug = slugFromTitleAndDate(title, date);
  if (!newSlug) {
    console.error('Could not generate slug for:', title, date);
    process.exit(1);
  }
  if (seen.has(newSlug)) {
    console.error('Duplicate slug:', newSlug);
    process.exit(1);
  }
  seen.add(newSlug);

  if (oldSlug !== newSlug || String(item.id ?? '') !== newSlug) {
    changes.push({ old: oldSlug, new: newSlug, title });
  }
  item.id = newSlug;
  item.slug = newSlug;
}

const encoded = `${JSON.stringify(data, null, 4)}\n`;
const temp = `${jsonPath}.tmp-${process.pid}`;
fs.writeFileSync(temp, encoded, 'utf8');
fs.renameSync(temp, jsonPath);

console.log('Backup:', backupPath);
console.log('Items:', data.items.length);
console.log('Slug/id changes:', changes.length);
for (const row of changes) {
  console.log(`  ${row.old} -> ${row.new}`);
}

// verify
for (const item of data.items) {
  const expected = slugFromTitleAndDate(String(item.title).trim(), String(item.date).trim());
  if (item.id !== expected || item.slug !== expected) {
    console.error('Verification failed:', item.title);
    process.exit(1);
  }
}
console.log('Verification OK');
