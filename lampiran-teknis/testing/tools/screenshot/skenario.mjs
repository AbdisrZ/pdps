// Jalankan skenario pengujian di browser (Chrome headless) dan simpan screenshot.
// Target: toko di http://127.0.0.1:8001 yang memakai database salinan `bagisto_uji`.
// Usage: node skenario.mjs <chrome.exe> <profile-dir> <out-dir> <evidence-dir>
import { launch } from './cdp.mjs';
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

const [chrome, profile, outDir, evidenceDir] = process.argv.slice(2);
const BASE = 'http://127.0.0.1:8001';
mkdirSync(outDir, { recursive: true });

const page = await launch({ chrome, profile, port: 9335 });
const hasil = [];

async function shot(id, judul, fn, opts = {}) {
  const file = `${id}.png`;
  try {
    const catatan = (await fn()) || '';
    await page.screenshot(join(outDir, file), opts);
    hasil.push({ id, judul, file, ok: true, catatan });
    console.log('OK  ', id, judul, catatan);
  } catch (e) {
    try { await page.screenshot(join(outDir, file), opts); } catch {}
    hasil.push({ id, judul, file, ok: false, catatan: String(e.message || e) });
    console.log('GAGAL', id, judul, e.message);
  }
}

async function clearSession() {
  await page.send('Network.enable');
  await page.send('Network.clearBrowserCookies');
}

async function clickByText(text, scope = 'button') {
  const ok = await page.eval(`(() => {
    const el = [...document.querySelectorAll(${JSON.stringify(scope)})].find(b => (b.innerText || '').trim() === ${JSON.stringify(text)});
    if (!el) return false; el.scrollIntoView({block: 'center'}); el.click(); return true;
  })()`);
  if (!ok) throw new Error(`Tombol "${text}" tidak ditemukan`);
}

async function loginCustomer(email, password) {
  await page.goto(`${BASE}/customer/login`, 2000);
  await page.type('input[name=email]', email);
  await page.type('input[name=password]', password);
  await clickByText('Masuk');
  await page.sleep(3500);
}

// Panel hasil uji API: dijalankan dari halaman toko (origin sama, cookie & CSRF sesi aktif).
async function apiPanel(judul, langkah) {
  return page.eval(`(async () => {
    const token = decodeURIComponent((document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/) || [])[1] || '');
    const call = async (method, url, body) => {
      const res = await fetch(url, { method, credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
        body: body ? JSON.stringify(body) : undefined });
      let data; const text = await res.text();
      try { data = JSON.parse(text); } catch { data = text.slice(0, 300); }
      return { status: res.status, data };
    };
    const ringkas = (d) => {
      if (d && typeof d === 'object') {
        const o = {};
        for (const k of ['message', 'errors', 'redirect', 'redirect_uri']) if (k in d) o[k] = d[k];
        if (d.data && typeof d.data === 'object') {
          const x = {};
          for (const k of ['message', 'items_count', 'items_qty', 'grand_total', 'redirect']) if (k in d.data) x[k] = d.data[k];
          if (Object.keys(x).length) o.data = x;
        }
        return Object.keys(o).length ? o : d;
      }
      return d;
    };
    const langkah = ${langkah};
    const rows = [];
    for (const l of langkah) {
      const r = await call(l.method, l.url, l.body);
      rows.push({ ...l, status: r.status, resp: ringkas(r.data) });
    }
    const esc = (s) => String(s).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
    const warna = (s) => s >= 500 ? '#c0392b' : s >= 400 ? '#b9770e' : '#1e8449';
    document.head.innerHTML = '<meta charset="utf-8"><title>Uji API</title>';
    document.body.innerHTML = '<div style="font:15px/1.5 Segoe UI,sans-serif;background:#f4f5f7;min-height:100vh;padding:28px">'
      + '<div style="max-width:1200px;margin:0 auto">'
      + '<div style="font-size:13px;color:#2f6f5e;font-weight:600;letter-spacing:.04em">UJI API · ' + esc(location.origin) + ' · ' + new Date().toLocaleString('id-ID') + '</div>'
      + '<h1 style="margin:4px 0 18px;font-size:24px">' + esc(${JSON.stringify(judul)}) + '</h1>'
      + rows.map((r, i) => '<div style="background:#fff;border:1px solid #dde1e6;border-radius:10px;padding:14px 18px;margin-bottom:12px">'
        + '<div style="display:flex;gap:10px;align-items:center;margin-bottom:8px"><b>Langkah ' + (i + 1) + '</b>'
        + '<span style="color:#555">' + esc(r.ket || '') + '</span>'
        + '<span style="margin-left:auto;font-weight:700;color:#fff;background:' + warna(r.status) + ';border-radius:999px;padding:2px 12px">HTTP ' + r.status + '</span></div>'
        + '<div style="font-family:Consolas,monospace;font-size:13.5px;background:#f6f8fa;border-radius:6px;padding:8px 10px;margin-bottom:6px"><b>' + r.method + '</b> ' + esc(r.url)
        + (r.body ? '<br>' + esc(JSON.stringify(r.body)) : '') + '</div>'
        + '<div style="font-size:12px;color:#666;margin:6px 0 2px">Respons</div>'
        + '<pre style="margin:0;font-family:Consolas,monospace;font-size:13px;background:#1f2328;color:#e6edf3;border-radius:6px;padding:10px 12px;white-space:pre-wrap">' + esc(JSON.stringify(r.resp, null, 2)) + '</pre></div>').join('')
      + '</div></div>';
    return rows.map(r => r.method + ' ' + r.url + ' -> ' + r.status).join('; ');
  })()`);
}

// Render file output Pest (teks) sebagai "terminal" lalu screenshot.
function terminalHtml(judul, teks) {
  const clean = teks.replace(/\x1b\[[0-9;]*m/g, '').replace(/\r/g, '');
  const esc = (s) => s.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
  const lines = clean.split('\n').filter((l) => !/^\s*\d+▕/.test(l)).map((l) => {
    let c = '#d0d7de';
    if (/✓|PASS|passed/.test(l)) c = '#7ee787';
    if (/⨯|FAIL|failed/.test(l)) c = '#ff7b72';
    if (/Tests:|Duration/.test(l)) c = '#ffffff';
    return `<div style="color:${c}">${esc(l) || '&nbsp;'}</div>`;
  }).join('');
  return `<!doctype html><meta charset="utf-8"><body style="margin:0;background:#0d1117">
  <div style="font:13px/1.45 Consolas,monospace;padding:18px 22px">
  <div style="font:600 14px Segoe UI,sans-serif;color:#8b949e;margin-bottom:10px">${esc(judul)}</div>${lines}</div></body>`;
}

async function terminalShot(id, judul, file, filter) {
  await shot(id, judul, async () => {
    let teks = readFileSync(join(evidenceDir, file), 'utf8');
    if (filter) teks = filter(teks);
    const tmp = join(profile, `${id}.html`);
    writeFileSync(tmp, terminalHtml(judul, teks));
    await page.goto(pathToFileURL(tmp).href, 400);
    return `sumber: ${file}`;
  }, { full: true });
}

try {
  // ---------- UI toko ----------
  await clearSession();
  await shot('01-beranda-toko', 'Halaman depan toko (database salinan bagisto_uji)', async () => {
    await page.goto(`${BASE}/`, 4000);
  });

  await shot('02-login-gagal', 'Login dengan password salah ditolak', async () => {
    await loginCustomer('customer@example.com', 'passwordsalah');
    return await page.eval('location.pathname');
  });

  await shot('03-login-berhasil', 'Login pelanggan dengan password benar', async () => {
    await loginCustomer('customer@example.com', 'customer123');
    await page.goto(`${BASE}/customer/account/profile`, 2500);
    return await page.eval('location.pathname');
  });

  await shot('04-pesanan-sendiri', 'Pelanggan membuka pesanan miliknya sendiri (#8)', async () => {
    await page.goto(`${BASE}/customer/account/orders/view/8`, 3000);
    return await page.eval('document.title');
  });

  await shot('05-pesanan-orang-lain', 'Pelanggan mencoba membuka pesanan milik orang lain (#1) -> tidak ditemukan', async () => {
    await page.goto(`${BASE}/customer/account/orders/view/1`, 2500);
    return await page.eval('document.title');
  });

  // ---------- Stok ----------
  const plusSelector = `(() => {
    const qc = document.querySelector('[class*="quantity"], v-quantity-changer');
    const cands = [...document.querySelectorAll('span,button,i')].filter(e => /icon-plus/.test(e.className) || e.getAttribute('aria-label') === 'Increase Quantity');
    return cands.length;
  })()`;
  async function setQty(n) {
    const count = await page.eval(plusSelector);
    if (!count) throw new Error('tombol tambah qty tidak ditemukan');
    for (let i = 1; i < n; i++) {
      await page.eval(`[...document.querySelectorAll('span,button,i')].find(e => /icon-plus/.test(e.className) || e.getAttribute('aria-label') === 'Increase Quantity').click()`);
      await page.sleep(150);
    }
  }

  await clearSession();
  await shot('06-stok-melebihi', 'Beli 6 barang padahal stok 5 -> ditolak', async () => {
    await page.goto(`${BASE}/red-nail-polish-5`, 3500);
    await setQty(6);
    await clickByText('Tambahkan ke Keranjang');
    await page.sleep(1300);
    return await page.eval(`(document.body.innerText.match(/(jumlah yang diminta[^\\n]*|requested quantity[^\\n]*)/i) || ['(pesan tidak terbaca)'])[0]`);
  });

  await clearSession();
  await shot('07-stok-cukup', 'Beli 5 barang (tepat sama dengan stok) -> berhasil', async () => {
    await page.goto(`${BASE}/red-nail-polish-5`, 3500);
    await setQty(5);
    await clickByText('Tambahkan ke Keranjang');
    await page.sleep(1300);
    return await page.eval(`(document.body.innerText.match(/(berhasil[^\\n]*|success[^\\n]*)/i) || ['(pesan tidak terbaca)'])[0]`);
  });

  await shot('08-harga-rolex-terpotong', 'Bukti masalah harga: Rolex tampil Rp 100.000.000,00 (tersimpan 99.999.999,9999; harga asli sekitar Rp 224 juta)', async () => {
    await page.goto(`${BASE}/rolex-submariner-watch-98`, 3500);
  });

  // ---------- Admin: batas harga (sudah diperbaiki v1.0.2) ----------
  await clearSession();
  await shot('09-admin-harga-terlalu-besar', 'Admin mengisi harga 100.000.000 -> ditolak (setelah perbaikan v1.0.2)', async () => {
    await page.goto(`${BASE}/admin/login`, 2500);
    await page.type('input[name=email]', 'admin@example.com');
    await page.type('input[name=password]', 'admin123');
    await clickByText('Sign In');
    await page.sleep(4000);
    await page.goto(`${BASE}/admin/catalog/products/edit/16`, 5000);
    await page.waitFor('input[name=price]');
    await page.type('input[name=price]', '100000000');
    await clickByText('Save Product');
    await page.sleep(3000);
    await page.eval(`document.querySelector('input[name=price]').scrollIntoView({block: 'center'})`);
    await page.sleep(500);
    return await page.eval(`(document.body.innerText.match(/[^\\n]*(greater than|lebih besar)[^\\n]*/i) || ['(pesan tidak terbaca)'])[0]`);
  });

  // ---------- Uji API (guest baru) ----------
  await clearSession();
  await shot('10-api-stok', 'API: tambah ke keranjang melebihi stok', async () => {
    await page.goto(`${BASE}/red-nail-polish-5`, 2500);
    return apiPanel('Tambah ke keranjang melebihi stok (stok produk = 5)', JSON.stringify([
      { ket: 'Minta 6 barang (stok 5)', method: 'POST', url: '/api/checkout/cart', body: { product_id: 16, quantity: 6 } },
      { ket: 'Minta 5 barang (tepat stok)', method: 'POST', url: '/api/checkout/cart', body: { product_id: 16, quantity: 5 } },
    ]));
  });

  await clearSession();
  await shot('11-api-tanpa-quantity', 'API: tambah ke keranjang tanpa jumlah (masalah no. 6)', async () => {
    await page.goto(`${BASE}/red-nail-polish-5`, 2500);
    return apiPanel('Tambah ke keranjang tanpa field quantity', JSON.stringify([
      { ket: 'Tanpa quantity (seharusnya otomatis 1)', method: 'POST', url: '/api/checkout/cart', body: { product_id: 16 } },
    ]));
  });

  await clearSession();
  await shot('12-api-qty-nol-hapus-item', 'API: ubah jumlah menjadi 0 menghapus barang (masalah no. 5)', async () => {
    await page.goto(`${BASE}/red-nail-polish-5`, 2500);
    const itemId = await page.eval(`(async () => {
      const token = decodeURIComponent((document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/) || [])[1] || '');
      const h = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': token };
      await fetch('/api/checkout/cart', { method: 'POST', headers: h, body: JSON.stringify({ product_id: 16, quantity: 2 }) });
      const c = await (await fetch('/api/checkout/cart', { headers: h })).json();
      return c.data.items[0].id;
    })()`);
    return apiPanel('Ubah jumlah barang di keranjang menjadi 0', JSON.stringify([
      { ket: 'Isi keranjang sebelum (2 barang)', method: 'GET', url: '/api/checkout/cart' },
      { ket: 'Ubah jumlah item #' + itemId + ' menjadi 0', method: 'PUT', url: '/api/checkout/cart', body: { qty: { [itemId]: 0 } } },
      { ket: 'Isi keranjang sesudah', method: 'GET', url: '/api/checkout/cart' },
    ]));
  });

  await clearSession();
  await shot('13-api-ongkir-tanpa-keranjang', 'API: cek ongkir tanpa keranjang -> error server (masalah no. 7)', async () => {
    await page.goto(`${BASE}/customer/login`, 2000);
    return apiPanel('Cek ongkir saat belum punya keranjang', JSON.stringify([
      { ket: 'Estimasi ongkir tanpa keranjang', method: 'POST', url: '/api/checkout/cart/estimate-shipping-methods', body: { country: 'ID', state: 'Jawa Barat', postcode: '40115' } },
      { ket: 'Hapus kupon tanpa keranjang', method: 'DELETE', url: '/api/checkout/cart/coupon' },
    ]));
  });

  await clearSession();
  await shot('14-api-email-tidak-valid', 'API: alamat checkout dengan email tidak valid diterima (masalah no. 11)', async () => {
    await page.goto(`${BASE}/red-nail-polish-5`, 2500);
    const alamat = { first_name: 'Budi', last_name: 'Santoso', address: ['Jl. Uji Coba No. 1'], city: 'Bandung', country: 'ID', state: 'Jawa Barat', postcode: '40115', phone: '081234567890', use_for_shipping: 1 };
    return apiPanel('Isi alamat checkout dengan email asal-asalan', JSON.stringify([
      { ket: 'Masukkan 1 barang ke keranjang', method: 'POST', url: '/api/checkout/cart', body: { product_id: 16, quantity: 1 } },
      { ket: 'Email "bukan-email"', method: 'POST', url: '/api/checkout/onepage/addresses', body: { billing: { ...alamat, email: 'bukan-email' } } },
      { ket: 'Pembanding: kode pos 1 karakter', method: 'POST', url: '/api/checkout/onepage/addresses', body: { billing: { ...alamat, email: 'budi@example.com', postcode: '1' } } },
    ]));
  });

  // ---------- Hasil test otomatis (Pest) ----------
  const hanyaHasil = (t) => t.split('\n').filter((l) => /✓|⨯|Tests:|Duration|PASS|FAIL/.test(l) && !/FAILED/.test(l)).join('\n');
  await terminalShot('15-pest-hasil-pengujian', 'Hasil 112 kasus uji otomatis (FASE pengujian, run 1)', 'F4-run1-output.txt', hanyaHasil);
  await terminalShot('16-pest-minorder-sebelum', 'Perbaikan 1 (minimum order): test SEBELUM diperbaiki -> gagal', 'F5-DEF-03-merah-run1.txt', hanyaHasil);
  await terminalShot('17-pest-minorder-sesudah', 'Perbaikan 1 (minimum order): test SESUDAH diperbaiki -> lolos', 'F5-DEF-03-hijau-run1.txt', hanyaHasil);
  await terminalShot('18-pest-harga-sebelum', 'Perbaikan 2 (batas harga): test SEBELUM diperbaiki -> gagal', 'F5-DEF-01-merah-run1.txt', hanyaHasil);
  await terminalShot('19-pest-harga-sesudah', 'Perbaikan 2 (batas harga): test SESUDAH diperbaiki -> lolos', 'F5-DEF-01-hijau-run1.txt', hanyaHasil);

  const bawaan = ['CartTest', 'CheckoutTest', 'CouponUsageLimitTest', 'OrdersTest', 'LoginPageTest', 'RegistrationPageTest', 'AccountTest', 'ProductTest']
    .map((n) => `${n.padEnd(24)} ${(readFileSync(join(evidenceDir, `F5-final-bawaan-${n}.txt`), 'utf8').match(/Tests:[^\n]*/) || ['?'])[0]}`)
    .concat([`${'Admin Catalog/Products'.padEnd(24)} ${(readFileSync(join(evidenceDir, 'F5-final-AdminCatalogProducts.txt'), 'utf8').match(/Tests:[^\n]*/) || ['?'])[0]}`,
      `${'Test kami (Praktikum)'.padEnd(24)} ${(readFileSync(join(evidenceDir, 'F5-final-praktikum.txt'), 'utf8').match(/Tests:[^\n]*/) || ['?'])[0]}`]);
  writeFileSync(join(profile, 'regresi.txt'), bawaan.join('\n'));
  await shot('20-pest-regresi-akhir', 'Pengecekan ulang setelah kedua perbaikan (test bawaan tetap lolos)', async () => {
    const tmp = join(profile, '20.html');
    writeFileSync(tmp, terminalHtml('Pengecekan ulang setelah kedua perbaikan (regresi)', bawaan.join('\n')));
    await page.goto(pathToFileURL(tmp).href, 400);
  }, { full: true });
} finally {
  writeFileSync(join(outDir, 'hasil.json'), JSON.stringify(hasil, null, 2));
  await page.close();
}
