// Jelajahi halaman yang butuh sesi (keranjang, checkout) dan tampilkan elemen form-nya.
// Usage: node inspect2.mjs <chrome.exe> <profile-dir>
import { launch } from './cdp.mjs';

const [chrome, profile] = process.argv.slice(2);
const BASE = 'http://127.0.0.1:8001';
const page = await launch({ chrome, profile, port: 9340 });

const dump = async (label) => {
  const info = await page.eval(`(() => {
    const q = (s) => [...document.querySelectorAll(s)];
    return {
      url: location.pathname,
      inputs: q('input,select,textarea').filter(e => e.type !== 'hidden' && e.offsetParent).map(e => [e.tagName, e.name, e.type, e.id, e.placeholder].join('|')).slice(0, 30),
      buttons: q('button,[type=submit],a.primary-button,label').filter(e => e.offsetParent).map(e => (e.innerText || e.value || '').trim().replace(/\\s+/g, ' ').slice(0, 40)).filter(Boolean).slice(0, 40),
    };
  })()`);
  console.log('=====', label, JSON.stringify(info, null, 1));
};

try {
  await page.goto(`${BASE}/customer/register`, 3000);
  await dump('register');

  await page.goto(`${BASE}/red-nail-polish-5`, 3500);
  await page.eval(`[...document.querySelectorAll('button')].find(b => b.innerText.trim() === 'Tambahkan ke Keranjang').click()`);
  await page.sleep(2500);
  await page.goto(`${BASE}/checkout/cart`, 4000);
  await dump('cart');

  await page.goto(`${BASE}/checkout/onepage`, 5000);
  await dump('onepage');
} finally {
  await page.close();
}
