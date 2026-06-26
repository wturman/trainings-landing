import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const jsonPath = path.join(path.dirname(fileURLToPath(import.meta.url)), 'data', 'news.json');
const data = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));

if (!Array.isArray(data.items)) {
  console.error('Invalid items');
  process.exit(1);
}

const emptyEngagement = () => ({ views_ips: {}, likes_ips: [] });
let touched = 0;

for (const item of data.items) {
  if (!item || typeof item !== 'object') continue;
  if (!item.engagement || typeof item.engagement !== 'object') {
    item.engagement = emptyEngagement();
    touched += 1;
    continue;
  }
  let changed = false;
  if (!item.engagement.views_ips || typeof item.engagement.views_ips !== 'object' || Array.isArray(item.engagement.views_ips)) {
    item.engagement.views_ips = {};
    changed = true;
  }
  if (!Array.isArray(item.engagement.likes_ips)) {
    item.engagement.likes_ips = [];
    changed = true;
  }
  if (changed) touched += 1;
}

if (touched > 0) {
  const ts = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
  fs.copyFileSync(jsonPath, `${jsonPath}.bak-${ts}`);
  fs.writeFileSync(jsonPath, `${JSON.stringify(data, null, 4)}\n`, 'utf8');
}

console.log('Items:', data.items.length, 'engagement init/merge:', touched);
