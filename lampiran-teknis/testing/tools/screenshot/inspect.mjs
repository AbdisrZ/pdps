// Tampilkan input/tombol yang dirender di halaman (untuk menyusun skenario screenshot).
// Usage: node inspect.mjs <chrome.exe> <profile-dir> <url> [<url> ...]
import { launch } from './cdp.mjs';

const [chrome, profile, ...urls] = process.argv.slice(2);
const page = await launch({ chrome, profile, port: 9334 });

try {
  for (const url of urls) {
    await page.goto(url, 2500);
    const info = await page.eval(`(() => {
      const q = (s) => [...document.querySelectorAll(s)];
      return {
        url: location.href,
        title: document.title,
        inputs: q('input,select,textarea').filter(e => e.type !== 'hidden').map(e => [e.tagName, e.name, e.type, e.id].join('|')).slice(0, 25),
        buttons: q('button,[type=submit]').map(e => (e.innerText || e.value || '').trim().slice(0, 40) + ' | ' + (e.className || '').slice(0, 60)).filter(Boolean).slice(0, 25),
      };
    })()`);
    console.log(JSON.stringify(info, null, 1));
  }
} finally {
  await page.close();
}
