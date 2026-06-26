import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const jsonPath = path.join(path.dirname(fileURLToPath(import.meta.url)), 'data', 'news.json'); // SSOT: test/data/news.json (see news_data_json_path)
const raw = fs.readFileSync(jsonPath, 'utf8');
const data = JSON.parse(raw);

if (!Array.isArray(data.items)) {
  console.error('Invalid items');
  process.exit(1);
}

let touched = 0;
for (const item of data.items) {
  if (!item || typeof item !== 'object') continue;
  if (!Object.prototype.hasOwnProperty.call(item, 'views')) {
    item.views = 0;
    touched += 1;
  }
  if (!Object.prototype.hasOwnProperty.call(item, 'likes')) {
    item.likes = 0;
    touched += 1;
  }
}

if (touched > 0) {
  const ts = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
  fs.copyFileSync(jsonPath, `${jsonPath}.bak-${ts}`);
  fs.writeFileSync(jsonPath, `${JSON.stringify(data, null, 4)}\n`, 'utf8');
}

console.log('Items:', data.items.length, 'fields added:', touched);
