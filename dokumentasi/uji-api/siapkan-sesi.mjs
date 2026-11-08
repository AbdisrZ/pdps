// Menyiapkan sesi untuk pengujian API di VS Code (REST Client).
//
// API toko Bagisto berada di grup "web", sehingga setiap request wajib membawa
// cookie sesi + token CSRF. Skrip ini membuka halaman login untuk membuat
// beberapa sesi tamu baru, mengambil cookie & token-nya, lalu menulis file .env
// yang dibaca oleh pengujian-api.http lewat {{$dotenv ...}}.
//
// Sesi:
//   A = untuk uji tambah ke keranjang (stok, tanpa quantity)
//   B = sesi tanpa keranjang (uji ongkir/kupon/login)
//   C = untuk uji alamat checkout
//   D = sudah berisi 1 barang (qty 2) untuk uji ubah jumlah menjadi 0
//
// Jalankan dari terminal VS Code:   node dokumentasi/uji-api/siapkan-sesi.mjs
import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const BASE = 'http://127.0.0.1:8001';
const PRODUK = 16; // Red Nail Polish, stok 5 di database salinan bagisto_uji

async function sesiBaru() {
  const res = await fetch(`${BASE}/customer/login`, { redirect: 'manual' });
  if (!res.ok) throw new Error(`Server ${BASE} tidak merespons (${res.status}). Sudah dijalankan?`);
  const html = await res.text();
  const token = (html.match(/name="_token" value="([^"]+)"/) || [])[1];
  const cookies = res.headers.getSetCookie().map((c) => c.split(';')[0]);
  const sesi = cookies.find((c) => !c.startsWith('XSRF-TOKEN='));
  if (!token || !sesi) throw new Error('Token/cookie sesi tidak ditemukan');
  return { token, cookie: sesi };
}

async function api(s, method, path, body) {
  const res = await fetch(`${BASE}${path}`, {
    method,
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': s.token, Cookie: s.cookie },
    body: body ? JSON.stringify(body) : undefined,
  });
  return { status: res.status, json: await res.json().catch(() => null) };
}

const A = await sesiBaru();
const B = await sesiBaru();
const C = await sesiBaru();
const D = await sesiBaru();

// Sesi D: isi keranjang dengan 2 barang lalu ambil ID item-nya.
const tambah = await api(D, 'POST', '/api/checkout/cart', { product_id: PRODUK, quantity: 2 });
const keranjang = await api(D, 'GET', '/api/checkout/cart');
const itemD = keranjang.json?.data?.items?.[0]?.id;
if (tambah.status !== 200 || !itemD) throw new Error(`Gagal menyiapkan keranjang sesi D (HTTP ${tambah.status})`);

const env = [
  '# Dibuat otomatis oleh siapkan-sesi.mjs - jangan di-commit (berisi cookie sesi lokal).',
  `# Waktu: ${new Date().toLocaleString('id-ID')}`,
  `BASE=${BASE}`,
  `PRODUK=${PRODUK}`,
  ...Object.entries({ A, B, C, D }).flatMap(([k, s]) => [`TOKEN_${k}=${s.token}`, `COOKIE_${k}=${s.cookie}`]),
  `ITEM_D=${itemD}`,
].join('\n');

const dir = dirname(fileURLToPath(import.meta.url));
writeFileSync(join(dir, '.env'), env + '\n');

console.log('Sesi siap: A, B, C, D (item keranjang D = #' + itemD + ', qty 2).');
console.log('Sekarang buka dokumentasi/uji-api/pengujian-api.http dan klik "Send Request" berurutan dari nomor 1.');
