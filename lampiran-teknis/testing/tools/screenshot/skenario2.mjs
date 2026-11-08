// Skenario screenshot tambahan (21-40): registrasi, login nonaktif, pencarian,
// keranjang, checkout lengkap, riwayat pesanan, dan validasi di admin.
// Target: http://127.0.0.1:8001 (database salinan bagisto_uji).
// Usage: node skenario2.mjs <chrome.exe> <profile-dir> <out-dir> [id-filter]
import { launch } from './cdp.mjs';
import { writeFileSync, readFileSync, existsSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const [chrome, profile, outDir, only] = process.argv.slice(2);
const BASE = 'http://127.0.0.1:8001';
mkdirSync(outDir, { recursive: true });
const page = await launch({ chrome, profile, port: 9341 });
const hasilFile = join(outDir, 'hasil2.json');
const hasil = existsSync(hasilFile) ? JSON.parse(readFileSync(hasilFile, 'utf8')) : [];

async function shot(id, judul, fn) {
  if (only && !id.startsWith(only)) return;
  const file = `${id}.png`;
  let ok = true; let catatan = '';
  try {
    catatan = (await fn()) || '';
  } catch (e) {
    ok = false;
    catatan = String(e.message || e);
    const elemen = await page.eval(`[...document.querySelectorAll('input,select,button')].filter(e=>e.offsetParent).map(e=>(e.name||e.innerText||'').trim().slice(0,30)).filter(Boolean).slice(0,40).join(' | ')`).catch(() => '');
    console.log('   elemen terlihat:', elemen);
  }
  await page.screenshot(join(outDir, file));
  const i = hasil.findIndex((h) => h.id === id);
  const row = { id, judul, file, ok, catatan };
  if (i >= 0) hasil[i] = row; else hasil.push(row);
  console.log(ok ? 'OK   ' : 'GAGAL', id, '-', catatan.slice(0, 150));
}

const sleep = (ms) => page.sleep(ms);
async function fresh() { await page.send('Network.enable'); await page.send('Network.clearBrowserCookies'); }
async function clickText(text, scope = 'button') {
  const ok = await page.eval(`(() => {
    const el = [...document.querySelectorAll(${JSON.stringify(scope)})].filter(e => e.offsetParent).find(b => (b.innerText || b.value || '').trim() === ${JSON.stringify(text)});
    if (!el) return false; el.scrollIntoView({block: 'center'}); el.click(); return true;
  })()`);
  if (!ok) throw new Error(`Tombol "${text}" tidak ditemukan`);
}
async function selectValue(selector, value) {
  await page.eval(`(() => {
    const el = document.querySelector(${JSON.stringify(selector)});
    Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value').set.call(el, ${JSON.stringify(value)});
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  })()`);
}
const teks = (re) => page.eval(`(document.body.innerText.match(${re}) || ['(tidak terbaca)'])[0]`);
async function scrollTo(selector) {
  await page.eval(`document.querySelector(${JSON.stringify(selector)})?.scrollIntoView({block: 'center'})`);
  await sleep(500);
}
async function loginCustomer(email, password) {
  await page.goto(`${BASE}/customer/login`, 2000);
  await page.type('input[name=email]', email);
  await page.type('input[name=password]', password);
  await clickText('Masuk');
  await sleep(3500);
}
async function loginAdmin(email = 'admin@example.com', password = 'admin123', wait = 4000) {
  await page.goto(`${BASE}/admin/login`, 2500);
  await page.type('input[name=email]', email);
  await page.type('input[name=password]', password);
  await clickText('Sign In');
  await sleep(wait);
}
async function isiRegistrasi(data) {
  await page.goto(`${BASE}/customer/register`, 2500);
  for (const [k, v] of Object.entries(data)) await page.type(`input[name=${k}]`, v);
  await clickText('Daftar');
  await sleep(3500);
}
async function keranjangIsi(produkUrl = 'red-nail-polish-5') {
  await page.goto(`${BASE}/${produkUrl}`, 3500);
  await clickText('Tambahkan ke Keranjang');
  await sleep(2500);
}
async function isiAlamat(override = {}) {
  const data = {
    'billing.first_name': 'Budi', 'billing.last_name': 'Santoso', 'billing.email': 'budi.santoso@example.com',
    'billing.address.[0]': 'Jl. Uji Coba No. 1', 'billing.state': 'Jawa Barat', 'billing.city': 'Bandung',
    'billing.postcode': '40115', 'billing.phone': '081234567890', ...override,
  };
  await page.waitFor('input[name="billing.first_name"]');
  await selectValue('select[name="billing.country"]', 'ID');
  await sleep(300);
  for (const [k, v] of Object.entries(data)) await page.type(`input[name="${k}"]`, v);
}

try {
  // ---------------- Registrasi & login ----------------
  await fresh();
  await shot('21-registrasi-password-pendek', 'Registrasi dengan password 5 karakter -> ditolak', async () => {
    await isiRegistrasi({ first_name: 'Rina', last_name: 'Uji', email: 'rina.uji1@example.com', password: '12345', password_confirmation: '12345' });
    await scrollTo('input[name=password]');
    return teks(/[^\n]*(minimal|at least|setidaknya|paling sedikit)[^\n]*/i);
  });

  await fresh();
  await shot('22-registrasi-email-terdaftar', 'Registrasi dengan email yang sudah terdaftar -> ditolak', async () => {
    await isiRegistrasi({ first_name: 'Rina', last_name: 'Uji', email: 'customer@example.com', password: 'rahasia123', password_confirmation: 'rahasia123' });
    await scrollTo('input[name=email]');
    return teks(/[^\n]*(sudah|already|digunakan|taken)[^\n]*/i);
  });

  await fresh();
  await shot('23-registrasi-berhasil', 'Registrasi dengan data valid -> akun dibuat', async () => {
    const email = `rina.${Date.now()}@example.com`;
    await isiRegistrasi({ first_name: 'Rina', last_name: 'Uji', email, password: 'rahasia123', password_confirmation: 'rahasia123' });
    return `${await page.eval('location.pathname')} | ${await teks(/[^\n]*(berhasil|success)[^\n]*/i)}`;
  });

  await fresh();
  await shot('24-login-akun-nonaktif', 'Login dengan akun yang dinonaktifkan admin -> ditolak', async () => {
    await loginCustomer('nonaktif@example.com', 'nonaktif123');
    return `${await page.eval('location.pathname')} | ${await teks(/[^\n]*(aktif|activat)[^\n]*/i)}`;
  });

  // ---------------- Pencarian ----------------
  await fresh();
  await shot('25-pencarian-produk', 'Mencari produk "iphone"', async () => {
    await page.goto(`${BASE}/search?query=iphone`, 6000);
    for (const y of [500, 0]) { await page.eval(`scrollTo(0, ${y})`); await sleep(1200); }
    return teks(/[^\n]*(hasil|result)[^\n]*/i);
  });

  // ---------------- Keranjang & checkout (tamu) ----------------
  await fresh();
  await shot('26-halaman-keranjang', 'Halaman keranjang berisi barang beserta subtotal', async () => {
    await keranjangIsi();
    await page.goto(`${BASE}/checkout/cart`, 4000);
    return teks(/[^\n]*(subtotal)[^\n]*/i);
  });

  await shot('27-checkout-email-tidak-valid', 'Form checkout: email "bukan-email"', async () => {
    await page.goto(`${BASE}/checkout/onepage`, 5000);
    await isiAlamat({ 'billing.email': 'bukan-email' });
    await clickText('Lanjut');
    await sleep(3000);
    await scrollTo('input[name="billing.email"]');
    return teks(/[^\n]*(email[^\n]*(valid|benar)|valid[^\n]*email)[^\n]*/i);
  });

  await fresh();
  await shot('28-checkout-kodepos-salah', 'Form checkout: kode pos 1 karakter -> ditolak', async () => {
    await keranjangIsi();
    await page.goto(`${BASE}/checkout/onepage`, 5000);
    await isiAlamat({ 'billing.postcode': '1' });
    await clickText('Lanjut');
    await sleep(3000);
    await scrollTo('input[name="billing.postcode"]');
    return teks(/[^\n]*(kode pos|postcode)[^\n]*(valid)[^\n]*/i);
  });

  await fresh();
  await shot('29-checkout-pilih-ongkir', 'Checkout: alamat valid -> pilihan ongkir muncul', async () => {
    await keranjangIsi();
    await page.goto(`${BASE}/checkout/onepage`, 5000);
    await isiAlamat();
    await clickText('Lanjut');
    await sleep(4000);
    await page.eval(`[...document.querySelectorAll('label,div,p')].find(e => /Reguler|Flat Rate|Gratis Ongkir|Free Shipping/i.test(e.innerText||'') && e.offsetParent)?.scrollIntoView({block:'center'})`);
    await sleep(800);
    return teks(/[^\n]*(Flat Rate|Reguler|Gratis Ongkir|Free Shipping)[^\n]*/i);
  });

  await shot('30-checkout-pilih-pembayaran', 'Checkout: pilih ongkir -> pilihan pembayaran muncul', async () => {
    const ok = await page.eval(`(() => {
      const r = document.querySelector('input[type=radio][value="flatrate_flatrate"], input[type=radio][id*="flatrate"]');
      if (!r) return false; (document.querySelector('label[for="' + r.id + '"]') || r).click(); return true;
    })()`);
    if (!ok) throw new Error('radio ongkir flatrate tidak ditemukan');
    await sleep(4000);
    await page.eval(`[...document.querySelectorAll('label,div,p')].find(e => /Cash On Delivery|Bayar di Tempat|Transfer/i.test(e.innerText||'') && e.offsetParent)?.scrollIntoView({block:'center'})`);
    await sleep(800);
    return teks(/[^\n]*(Cash On Delivery|Bayar di Tempat|Money Transfer|Transfer)[^\n]*/i);
  });

  await shot('31-checkout-pesanan-berhasil', 'Checkout: pilih pembayaran lalu buat pesanan -> berhasil', async () => {
    const ok = await page.eval(`(() => {
      const r = document.querySelector('input[type=radio][value="cashondelivery"], input[type=radio][id*="cashondelivery"]');
      if (!r) return false; (document.querySelector('label[for="' + r.id + '"]') || r).click(); return true;
    })()`);
    if (!ok) throw new Error('radio pembayaran COD tidak ditemukan');
    await sleep(3500);
    const tombol = await page.eval(`[...document.querySelectorAll('button')].filter(b => b.offsetParent).map(b => b.innerText.trim()).filter(Boolean).join(' | ')`);
    const nama = ['Pesan Sekarang', 'Buat Pesanan', 'Place Order', 'Pesan'].find((t) => tombol.split(' | ').includes(t));
    if (!nama) throw new Error('tombol buat pesanan tidak ditemukan: ' + tombol);
    await clickText(nama);
    await sleep(6000);
    return `${await page.eval('location.pathname')} | ${await teks(/[^\n]*(terima kasih|thank|berhasil|#\d+)[^\n]*/i)}`;
  });

  // ---------------- Akun pelanggan ----------------
  await fresh();
  await shot('32-riwayat-pesanan', 'Pelanggan melihat riwayat pesanannya', async () => {
    await loginCustomer('customer@example.com', 'customer123');
    await page.goto(`${BASE}/customer/account/orders`, 4500);
    return page.eval('document.title');
  });

  await shot('33-invoice-orang-lain', 'Pelanggan mencoba mencetak invoice milik orang lain (#1) -> tidak ditemukan', async () => {
    await page.goto(`${BASE}/customer/account/orders/print/Invoice/1`, 3000);
    return page.eval('document.title');
  });

  // ---------------- Admin ----------------
  await fresh();
  await shot('34-admin-login-gagal', 'Admin login dengan password salah -> ditolak', async () => {
    await loginAdmin('admin@example.com', 'salahsalah', 0);
    // Pesan error tampil sebagai notifikasi sesaat: tunggu sampai muncul, lalu langsung potret.
    let pesan = '';
    for (let i = 0; i < 50 && !pesan; i++) {
      await sleep(200);
      pesan = await page.eval(`(document.body && (document.body.innerText.match(/[^\\n]*(credential|invalid)[^\\n]*/i) || [''])[0]) || ''`).catch(() => '');
    }
    if (!pesan) throw new Error('pesan error login admin tidak muncul');
    return `${await page.eval('location.pathname')} | ${pesan}`;
  });

  await fresh();
  await shot('35-admin-dashboard', 'Dashboard admin setelah login', async () => {
    await loginAdmin();
    await page.goto(`${BASE}/admin/dashboard`, 6000);
    return page.eval('location.pathname');
  });

  async function editHarga(fields) {
    await page.goto(`${BASE}/admin/catalog/products/edit/16`, 5000);
    await page.waitFor('input[name=price]');
    for (const [k, v] of Object.entries(fields)) await page.type(`input[name=${k}]`, v);
    await clickText('Save Product');
    await sleep(3500);
    await scrollTo('input[name=price]');
  }

  await shot('36-admin-harga-diskon-tidak-valid', 'Admin: harga diskon sama dengan harga normal -> ditolak', async () => {
    await editHarga({ price: '143900', special_price: '143900' });
    return teks(/[^\n]*(special price|harga khusus)[^\n]*(less|kurang|lebih kecil)[^\n]*/i);
  });

  await shot('37-admin-harga-negatif', 'Admin: harga negatif -> ditolak', async () => {
    await editHarga({ price: '-5000', special_price: '' });
    return teks(/[^\n]*(price|harga)[^\n]*(valid|decimal|desimal)[^\n]*/i);
  });

  await shot('38-admin-harga-valid', 'Admin: harga normal disimpan -> berhasil', async () => {
    if (only) { await fresh(); await loginAdmin(); }
    await page.goto(`${BASE}/admin/catalog/products/edit/16`, 5000);
    await page.waitFor('input[name=price]');
    await page.type('input[name=price]', '143900');
    await page.type('input[name=special_price]', '');
    await clickText('Save Product');
    await sleep(1800);
    return `${await page.eval('location.pathname')} | ${await teks(/[^\n]*(success|berhasil|updated)[^\n]*/i)}`;
  });

  await shot('39-admin-daftar-pesanan', 'Admin melihat daftar pesanan', async () => {
    await page.goto(`${BASE}/admin/sales/orders`, 6000);
    return page.eval('location.pathname');
  });

  await shot('40-admin-detail-pesanan', 'Admin membuka detail pesanan #1', async () => {
    await page.goto(`${BASE}/admin/sales/orders/view/1`, 5000);
    return page.eval('document.title');
  });
} finally {
  writeFileSync(hasilFile, JSON.stringify(hasil, null, 2));
  await page.close();
}
