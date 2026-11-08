# 03 — Test Plan Berbasis Risiko

| Item | Nilai |
|---|---|
| Sistem yang diuji (SUT) | Bagisto **2.3.19** (Laravel 11.48), storefront + API `api/checkout/*`, area Admin produk |
| Versi baseline | Tag Git `v1.0.0-baseline` (`d45d286`), branch `praktikum` |
| Penyusun | Praktikan (mata kuliah Pengujian dan Pemeliharaan Sistem, UNDIRA), dibantu Claude |
| Tanggal | 2026-11-08 |
| Dokumen terkait | `00-lingkungan.md`, `01-peta-source.md`, `02-baseline.md`, `04-blackbox.md`, `05-whitebox.md` |

## 1. Tujuan
1. Memverifikasi bahwa fungsi berisiko tinggi (P0) berperilaku sesuai kebutuhan di §4: otorisasi kepemilikan data, stok, cart, checkout, autentikasi, dan validasi harga.
2. Menemukan dan mendokumentasikan defect yang **dapat direproduksi**, lalu memperbaiki minimal dua beserta uji regresinya.
3. Mengukur coverage sebelum dan sesudah test baru, sekaligus menjelaskan keterbatasan metrik coverage.

## 2. Ruang lingkup

### 2.1 Dalam lingkup
| Prioritas | Modul | Antarmuka yang diuji |
|---|---|---|
| P0 | Authorization (customer A vs B) | `api/checkout/cart*`, `customer/account/orders/*` |
| P0 | Inventory/stok | `POST`/`PUT api/checkout/cart` lewat `Product/src/Type/*::haveSufficientQuantity` |
| P0 | Cart | `api/checkout/cart` (GET/POST/PUT/DELETE, `selected`, `move-to-wishlist`, `estimate-shipping-methods`) |
| P0 | Checkout (alamat, shipping) | `api/checkout/onepage/{addresses,shipping-methods,payment-methods,orders}` |
| P0 | Authentication customer | `customer/login`, `customer/register`, `api/customer/login` |
| P0 | Validasi harga produk | `Admin/src/Http/Requests/ProductForm.php` + `Core/src/Rules/Decimal.php` |
| P1 | CartRule/kupon, Order/status | `api/checkout/cart/coupon`, `CartRule/src/Helpers/CartRule.php`, cancel order |
| P2 | Search, UI (Playwright), aksesibilitas (axe/Lighthouse), performa API (k6) | Storefront |

### 2.2 Di luar lingkup
- Payment gateway eksternal (PayPal), karena membutuhkan sandbox pihak ketiga. `paypal_smart_button` hanya diuji sebatas cabang penolakan di `validateOrder`.
- Paket `bagisto/bagisto-api` (butuh PHP 8.3+).
- Modul Booking, GDPR, MagicAI, DataTransfer, dan SocialLogin.
- Kompatibilitas multi-browser dan uji beban skala produksi (lihat batas di §8).

## 3. Analisis risiko

Skala 1–5. **Skor = Kemungkinan × Dampak.** Kemungkinan dinilai dari pembacaan kode (`01-peta-source.md`) dan celah coverage (`02-baseline.md` §4). Dampak dinilai dari kerugian bisnis/keamanan.

| ID | Risiko | Modul | K | D | Skor | Dasar penilaian | Respons pengujian |
|---|---|---|---|---|---|---|---|
| R01 | Customer mengakses/mengubah cart, order, invoice, atau alamat milik customer lain (IDOR) | Authorization | 2 | 5 | **10** | Ada cek kepemilikan di `Cart.php:323/350/673` dan `OrderController.php:50/67/104/129`, tetapi tidak ada di `Cart.php:456/523` (H12) | AZ-* (black-box) + white-box `updateOrCreateBillingAddress` |
| R02 | Penjualan melebihi stok (oversell) | Stok | 2 | 5 | **10** | Cek `qty <= totalQuantity()` (`Simple.php:158`), akumulasi qty di `getQtyRequest` (`AbstractType.php:838`) | BVA stok, termasuk akumulasi dan update qty |
| R03 | Harga tersimpan salah (terpotong/negatif) sehingga total order keliru | Harga | 4 | 4 | **16** | `Decimal` tanpa batas atas + kolom `decimal(12,4)` + `strict=false` (H18; sudah teramati 1× pada data impor) | BVA harga di sekitar 99.999.999,9999 |
| R04 | Checkout menerima data alamat tidak valid / crash 500 | Checkout | 4 | 3 | **12** | Email tanpa format (D7), tanpa key `billing` (D8), cart null (H9) | EP/BVA alamat, cabang `rules()` |
| R05 | Endpoint cart mengembalikan 500 atau status HTTP yang tidak konsisten | Cart | 4 | 2 | 8 | D1, D2, D3, D5, H10, H11, H14 | Uji negatif tiap endpoint |
| R06 | Autentikasi lemah (brute force, lintas channel) | Auth | 3 | 4 | **12** | Tidak ada throttle login (H15); API login tanpa `channel_id` (H16) | EP/BVA kredensial, uji rate limit |
| R07 | Kupon/promo berlaku di luar periode atau melebihi kuota | CartRule (P1) | 3 | 3 | 9 | `H:m:s` (H19), `expired_at` tidak dicek (H20), coverage 0–10% | BVA waktu dan `usage_limit` |
| R08 | Minimum order dihitung salah | Checkout | 2 | 3 | 6 | `Cart.php:797-798` (H13), coverage `getOrderAmount` 0% | BVA minimum order |
| R09 | Pembatalan order pada status yang tidak boleh | Order (P1) | 2 | 3 | 6 | `OrderRepository.php:145` (`canCancel`) | EP status order |
| R10 | UI tidak aksesibel / API lambat | P2 | 3 | 2 | 6 | Belum ada data | axe/Lighthouse, k6 |

Urutan eksekusi mengikuti skor: R03 → R04/R06 → R01/R02 → R07 → R05 → R08/R09 → R10. Prioritas P0/P1/P2 dari prompt tetap dihormati, jadi R07 dan R09 (P1) dikerjakan setelah semua P0.

## 4. Katalog kebutuhan (acuan expected result)

Tidak ada SRS resmi untuk instalasi ini. Kebutuhan di bawah **dirumuskan praktikan** dari (a) pesan dan perilaku yang dimaksudkan oleh kode Bagisto itu sendiri (file terjemahan, nama aturan, test bawaan), (b) praktik umum e-commerce, dan (c) OWASP ASVS 4.0 untuk keamanan. Kolom *Sumber* menunjukkan dasarnya. Kebutuhan berlabel **[asumsi]** perlu dikonfirmasi dosen sebelum dipakai untuk menyatakan defect.

| REQ | Kebutuhan | Sumber |
|---|---|---|
| REQ-AZ-01 | Customer/guest hanya dapat membaca, mengubah, dan menghapus item di cart aktif miliknya sendiri. | Test bawaan `CartTest.php:858-1110`; `Cart.php:323` |
| REQ-AZ-02 | Customer hanya dapat melihat, reorder, membatalkan, dan mencetak invoice order miliknya; order milik orang lain menghasilkan 404. | `OrderController.php:55`, `:72`, `:108`, `:133` |
| REQ-AZ-03 | Alamat customer yang dirujuk saat checkout harus milik customer yang login. | OWASP ASVS V4.2.1 (IDOR) **[asumsi]** |
| REQ-ST-01 | Jumlah item di cart (kumulatif) tidak boleh melebihi stok tersedia bila `manage_stock` aktif dan back-order nonaktif. | Pesan `inventory-warning`; `Simple.php:158` |
| REQ-ST-02 | Bila back-order aktif atau `manage_stock` nonaktif, qty melebihi stok diizinkan. | Config `catalog.inventory.stock_options.back_orders`; `Simple.php:154-158` |
| REQ-CT-01 | Menambah produk ke cart: produk wajib ada dan aktif; qty bilangan bulat ≥ 1; bila qty tidak diisi, default = 1. | `CartController.php:51-55`; `AbstractType.php:829` (`?: 1`) |
| REQ-CT-02 | Update qty: qty harus bilangan bulat ≥ 1; qty tidak valid ditolak **tanpa mengubah isi cart**. | Pesan `checkout.cart.illegal` **[asumsi: tanpa efek samping]** |
| REQ-CT-03 | Kesalahan input klien di API dijawab dengan status 4xx yang konsisten (400/422) beserta pesan; tidak boleh 500 dan tidak boleh 200. | Konvensi REST, konsistensi `CartController::store` (400) **[asumsi]** |
| REQ-CT-04 | Respons hapus item hanya menyatakan "berhasil" bila item benar-benar terhapus. | Integritas informasi **[asumsi]** |
| REQ-CO-01 | Alamat billing wajib: nama depan/belakang, email **berformat email**, alamat ≥ 1 baris, kota, negara, provinsi, kode pos, dan telepon (sesuai config). | `CartAddressRequest.php:50-58`; config default `system.php:1286-1302`; format email **[asumsi]** |
| REQ-CO-02 | Telepon: `+` opsional diikuti digit saja. Kode pos: ≥ 2 karakter alfanumerik, boleh spasi/`-` di tengah. | `PhoneNumber.php:23`; `PostCode.php:15` |
| REQ-CO-03 | Shipping method harus salah satu rate yang tersedia untuk cart; selain itu ditolak (403). | `OnepageController.php:100-108`; `Shipping.php:154` |
| REQ-CO-04 | Order hanya dibuat bila ada billing, shipping (untuk item stockable), shipping rate, dan payment; customer suspended/nonaktif ditolak. | `OnepageController.php:206-252` |
| REQ-CO-05 | Minimum order (bila aktif) dibandingkan dengan subtotal (± pajak/diskon sesuai opsi), batas inklusif. | `Cart.php:789-818` |
| REQ-AU-01 | Registrasi: nama wajib; email valid dan unik per channel; password ≥ 6 karakter dan terkonfirmasi. | `RegistrationRequest.php:28-31` |
| REQ-AU-02 | Login: email valid, password ≥ 6; akun nonaktif atau belum terverifikasi ditolak; kredensial salah ditolak. | `LoginRequest.php:16-17`; `SessionController.php`; `API/CustomerController.php:20-44` |
| REQ-AU-03 | Login dibatasi percobaan berulang (rate limiting). | OWASP ASVS V2.2.1 **[asumsi]** |
| REQ-AU-04 | Login web dan API memakai aturan yang sama (termasuk channel). | Konsistensi `SessionController.php:38` **[asumsi]** |
| REQ-PR-01 | Harga dan biaya: desimal non-negatif, maksimal 4 digit desimal. | `Decimal.php:15` |
| REQ-PR-02 | Special price harus lebih kecil dari harga. | `ProductForm.php:86` (`lt:price`) |
| REQ-PR-03 | Harga yang diterima validasi harus tersimpan **persis sama**; nilai di luar kapasitas penyimpanan harus ditolak dengan pesan validasi, bukan dipotong diam-diam. | Integritas data **[asumsi]** |
| REQ-CR-01 | Kupon hanya berlaku dalam periode `starts_from`–`ends_till` (presisi menit) dan selama kuota pemakaian belum habis. | `CartRule.php:142-182`, `:550-556` |
| REQ-CR-02 | Pesan kegagalan kupon menjelaskan alasannya dan dapat diterjemahkan. | Konsistensi pesan `coupon.invalid` / `coupon.error` **[asumsi]** |

## 5. Strategi

| Level | Teknik | Alat | Lokasi | Catatan |
|---|---|---|---|---|
| Unit | White-box (cabang), mock/stub | Pest 3 + Mockery | `Shop/tests/Feature/Praktikum/` | Untuk aturan murni (`PhoneNumber`, `PostCode`, `Decimal`) dan cabang yang sulit dicapai lewat HTTP |
| Integrasi | Black-box EP + BVA lewat HTTP JSON, menyentuh DB test | Pest (`postJson`, `getJson`, …), `DatabaseTransactions` | idem, satu file per modul: `AuthorizationTest`, `StockCartTest`, `CheckoutTest`, `AuthTest`, `ProductPriceTest` | Mengikuti pola `ProductFaker`, `Cart::factory()`, `loginAsCustomer()` |
| Sistem/penerimaan | Skenario end-to-end per kebutuhan (REQ) | Pest feature (alur penuh) + Playwright (P2) | `Praktikum/` dan `tests/e2e-pw` | Ditelusuri di RTM (`09-rtm.md`) |
| Nonfungsional | Performa API, aksesibilitas | k6, axe-core/Lighthouse | `evidence/` | Butuh instalasi alat (izin praktikan) |
| Regresi | Suite P0 + test bawaan terkait setelah tiap perbaikan | Pest | branch `fix/*` | Tag `v1.0.1`, `v1.0.2` |
| Coverage | Line/method (PCOV), dengan analisis cabang manual | PHPUnit 11 + PCOV | `phpunit.coverage.xml` | Branch coverage otomatis tidak tersedia (perlu Xdebug) |

Aturan penetapan defect: suatu FAIL baru disebut defect bila (1) terjadi pada **≥ 2 eksekusi** terpisah, (2) bertentangan dengan REQ di §4, dan (3) REQ tersebut bukan **[asumsi]** yang ditolak dosen. FAIL terhadap REQ berlabel [asumsi] dilaporkan sebagai defect dengan catatan "bergantung konfirmasi kebutuhan".

## 6. Lingkungan dan data uji
- Lingkungan sesuai `00-lingkungan.md` (XAMPP, PHP 8.2.12 ZTS + PCOV, MySQL 8.0.44, DB `bagisto_test`).
- Data uji dibuat di dalam tiap test (factory) dan di-rollback otomatis. Test tidak bergantung pada ID tetap.
- Konfigurasi yang diubah per test (mis. back-order, minimum order) diset lewat `CoreConfig` di dalam transaksi test sehingga ikut di-rollback.
- Nilai default yang relevan: syarat country/state/postcode = 1 (`Admin/src/Config/system.php:1290-1302`); flat rate 10 per unit (`Shipping/src/Config/carriers.php`); back-order, minimum order, dan verifikasi email nonaktif (tidak ada default).

## 7. Kriteria masuk/keluar
- **Masuk:** GUARD-01 lolos; baseline 132/132 lolos (terpenuhi, `02-baseline.md`).
- **Keluar FASE 4:** semua kasus P0 di `04-blackbox.md` dieksekusi dan berstatus PASS/FAIL/BLOCKED; setiap FAIL direproduksi ≥ 2×; coverage sesudah test baru diukur.
- **Keluar proyek:** ≥ 2 defect diperbaiki dengan test merah → hijau; regresi P0 + test bawaan terkait lolos; RTM lengkap; risiko sisa didokumentasikan.

## 8. Batasan dan asumsi
1. Coverage hanya line/method (PCOV). Cabang dianalisis manual (`05-whitebox.md`).
2. `php artisan serve` adalah server single-process, sehingga hasil k6 bukan ukuran kapasitas produksi; dipakai untuk perbandingan relatif.
3. Kebutuhan berlabel [asumsi] bersifat usulan praktikan.
4. Versi diuji 2.3.19, bukan 2.5.1 (alasan di `00-lingkungan.md` §1). Temuan mungkin sudah atau belum diperbaiki di versi terbaru; hal ini dicatat sebagai risiko sisa, bukan diverifikasi.
5. Test baru hanya ditambahkan di `packages/Webkul/Shop/tests/Feature/Praktikum/`. Kode produk tidak diubah sampai defect yang akan diperbaiki disetujui.

## 9. Jadwal/fase dan luaran
| Fase | Luaran |
|---|---|
| 3 | `03-test-plan.md`, `04-blackbox.md`, `05-whitebox.md` |
| 4 | Test Pest P0, `06-hasil-eksekusi.md`, `07-defect-report.md`, coverage sesudah |
| 5 | `08-perbaikan-regresi.md`, branch `fix/*`, tag `v1.0.1` dan `v1.0.2` |
| 6+ | P1, P2, `09-rtm.md`, `10-risiko-sisa.md`, ringkasan demo |
