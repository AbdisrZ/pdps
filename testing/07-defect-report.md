# 07 — Laporan Defect (FASE 4)

Hanya FAIL yang **terbukti** yang dilaporkan di sini: FAIL di 2 run terpisah dengan perilaku yang sama (`06-hasil-eksekusi.md` §1) dan bertentangan dengan kebutuhan REQ (`03-test-plan.md` §4). Satu defect dapat mencakup beberapa kasus uji bila akar penyebabnya sama.

## 0. Konvensi

**Lingkungan (semua defect):** Bagisto **2.3.19** (tag `v1.0.0-baseline`, kode produk tidak diubah), Laravel 11.48.0, PHP 8.2.12 ZTS x64 (XAMPP) + PCOV 1.0.12, MySQL 8.0.44 (`config/database.php:58` `strict => false`), DB `bagisto_test` dari seeder installer (locale `en`, currency USD, flat rate 10/unit), Windows 11, Pest 3.8.6. `APP_DEBUG=true`: detail exception tampil di respons. Di produksi pesan akan generik, tetapi kode status tetap 500.

| Severity | Arti |
|---|---|
| Tinggi | Kerugian finansial/data permanen atau celah keamanan yang mudah dieksploitasi |
| Sedang | Fungsi bisnis salah dalam kondisi wajar, atau celah keamanan dengan prasyarat |
| Rendah | Kesalahan respons/pesan/konsistensi tanpa kehilangan data |

| Prioritas | Arti |
|---|---|
| P1 | Perbaiki sebelum rilis berikutnya |
| P2 | Rencanakan di siklus berikutnya |
| P3 | Perbaiki bila sempat |

**Kolom "Dasar kebutuhan".** Bila REQ berlabel **[asumsi]**, status defect bergantung konfirmasi dosen (aturan `03-test-plan.md` §5). Praktikan belum mengonfirmasi asumsi, sehingga dicatat apa adanya.

Langkah reproduksi otomatis untuk setiap defect:
```
vendor/bin/pest packages/Webkul/Shop/tests/Feature/Praktikum --filter "<ID kasus>"
```
Nilai aktual tersimpan di `evidence/F4-actual-run1/<ID>.json` dan `evidence/F4-actual-run2/<ID>.json`.

## 1. Ringkasan

| ID | Judul | Modul | Severity | Prioritas | Kasus | Dasar kebutuhan | Status |
|---|---|---|---|---|---|---|---|
| DEF-01 | Harga di atas 99.999.999,9999 diterima lalu tersimpan terpotong tanpa error | Harga (Admin) | **Tinggi** | **P1** | PR-09 | REQ-PR-03 [asumsi] | Open |
| DEF-02 | `PUT` cart dengan qty ≤ 0 menghapus item secara diam-diam dan membalas 200 | Cart | Sedang | P2 | CT-11, CT-12 | REQ-CT-02 [asumsi], REQ-CT-03 [asumsi] | Open |
| DEF-03 | Minimum order dengan opsi "include discount" mengurangi pajak, bukan diskon | Checkout | Sedang | P2 | MO-04 | REQ-CO-05 | Open |
| DEF-04 | Minimum order pecahan diabaikan karena pembulatan ke bilangan bulat | Checkout | Rendah | P3 | MO-05 | REQ-CO-05 | Open |
| DEF-05 | Harga "0" lolos validasi tetapi tersimpan `NULL` | Harga (Admin) | Sedang | P2 | PR-02 | REQ-PR-01, REQ-PR-03 [asumsi] | Open |
| DEF-06 | Tambah ke cart tanpa `quantity` ditolak 400 dengan pesan PHP mentah, bukan default 1 | Cart | Rendah | P2 | CT-08 | REQ-CT-01 | Open |
| DEF-07 | Tujuh endpoint cart/checkout membalas 500 untuk input/kondisi klien | Cart, Checkout | Sedang | P2 | CT-13, CT-15, CT-17, CT-19, CT-21, CO-19, CO-21 | REQ-CT-03 [asumsi] | Open |
| DEF-08 | Kegagalan `PUT` cart dibalas HTTP 200 | Cart | Rendah | P3 | CT-14 (+ CT-11, CT-12) | REQ-CT-03 [asumsi] | Open |
| DEF-09 | Checkout tanpa payment dibalas 500, bukan 4xx | Checkout | Rendah | P3 | OR-02 | REQ-CT-03 [asumsi] | Open |
| DEF-10 | Hapus item milik orang lain membalas "berhasil dihapus" | Authorization | Rendah | P3 | AZ-02 (+ AZ-06) | REQ-CT-04 [asumsi] | Open |
| DEF-11 | Email alamat checkout tidak divalidasi formatnya | Checkout | Sedang | P2 | CO-02 | REQ-CO-01 [asumsi format] | Open |
| DEF-12 | Teks alamat > 255 karakter tersimpan terpotong tanpa error | Checkout | Rendah | P3 | CO-18 | REQ-PR-03 dianalogikan [asumsi] | Open |
| DEF-13 | Checkout menerima rujukan alamat tersimpan milik customer lain | Authorization | Rendah | P3 | AZ-13 | REQ-AZ-03 [asumsi] | Open |
| DEF-14 | Login API mengabaikan channel customer (web menolak, API menerima) | Auth | Sedang | P2 | AU-16 | REQ-AU-04 [asumsi] | Open |
| DEF-15 | Tidak ada pembatasan percobaan login (rate limit) | Auth | Sedang | P2 | AU-15 | REQ-AU-03 [asumsi] | Open |

**15 defect dari 22 kasus FAIL.**
- Defect dengan dasar kebutuhan **bukan asumsi**: DEF-03, DEF-04, DEF-06. DEF-05 sebagian, karena REQ-PR-01 menerima 0.
- Sisanya berpijak pada REQ [asumsi] yang perlu dikonfirmasi.

---

## 2. Rincian

### DEF-01 — Harga di atas 99.999.999,9999 diterima lalu tersimpan terpotong tanpa error
- **Modul / kasus:** Validasi harga produk (Admin) / PR-09. Hipotesis H18.
- **Severity / prioritas:** Tinggi / P1. Toko memakai IDR; harga > Rp100 juta realistis (6 produk Rolex di DB utama sudah terkena, lihat `evidence/H18-harga-terpotong-observasi-awal.txt`). Harga yang salah ikut ke cart dan order.
- **Prakondisi:** Login admin. Ada produk simple.
- **Langkah:**
  1. `PUT admin/catalog/products/edit/{id}` dengan `price=100000000` beserta field wajib (`sku`, `url_key`, `name`, `short_description`, `description`, `weight`, `channel`, `locale`).
  2. Baca `product_attribute_values.float_value` (atribut `price`) dan `product_flat.price`.
- **Expected:** Ditolak 422 dengan pesan validasi, atau tersimpan persis `100000000.0000` (REQ-PR-03).
- **Actual (run 1 = run 2):** HTTP **302** (sukses). Tersimpan `99999999.9999` di `product_attribute_values` **dan** `product_flat`. Tidak ada pesan error.
- **Akar penyebab (dugaan dari kode):**
  - `Core/src/Rules/Decimal.php:15` tidak punya batas atas.
  - Kolom `decimal(12,4)`.
  - Koneksi `strict => false` (`config/database.php:58`) membuat MySQL memotong nilai di luar rentang menjadi nilai maksimum alih-alih menolak.
- **Bukti:** `evidence/F4-actual-run{1,2}/PR-09.json`; kontrol batas PR-08 (`99999999.9999` tersimpan persis) PASS.

### DEF-02 — `PUT` cart dengan qty ≤ 0 menghapus item secara diam-diam dan membalas 200
- **Modul / kasus:** Cart / CT-11 (qty 0), CT-12 (qty −1). Hipotesis H10.
- **Severity / prioritas:** Sedang / P2. Item hilang dari cart tanpa permintaan hapus.
- **Prakondisi:** Guest dengan cart berisi item `i` (qty 2).
- **Langkah:** `PUT api/checkout/cart` `{"qty": {"<i>": 0}}` (atau `-1`).
- **Expected:** 4xx; item tetap ada dengan qty 2 (REQ-CT-02, REQ-CT-03).
- **Actual:** HTTP **200**, `data.message` = "Quantity cannot be lesser than one.", dan **item terhapus** (`qty_item_sesudah = null`).
- **Akar penyebab:** `Checkout/src/Cart.php:358-362` memanggil `removeItem()` **sebelum** melempar exception. `CartController::update` menangkap exception dan membalas 200 (`CartController.php:159-163`).
- **Bukti:** `evidence/F4-actual-run{1,2}/CT-11.json`, `CT-12.json`.

### DEF-03 — Minimum order dengan opsi "include discount" mengurangi pajak, bukan diskon
- **Modul / kasus:** Checkout (minimum order) / MO-04 (unit). Hipotesis H13.
- **Severity / prioritas:** Sedang / P2. Hanya terjadi bila admin mengaktifkan `sales.order_settings.minimum_order.include_discount_amount`. Order yang sah bisa ditolak (atau sebaliknya bila pajak besar).
- **Prakondisi:** Config `include_discount_amount = 1`. Cart dengan `sub_total = 100`, `tax_total = 10`, `discount_amount = 0`.
- **Langkah:** Panggil `cart()->getOrderAmount()`.
- **Expected:** 100 (subtotal − diskon 0), REQ-CO-05.
- **Actual:** **90**.
- **Akar penyebab:** `Checkout/src/Cart.php:797-798`: `if (include_discount_amount) { $minimumOrderAmount -= $this->cart->tax_total; ...` Pengurangan diskon yang benar baru ada di `:800-802`.
- **Bukti:** `evidence/F4-actual-run{1,2}/MO-04.json`.

### DEF-04 — Minimum order pecahan diabaikan karena pembulatan ke bilangan bulat
- **Modul / kasus:** Checkout / MO-05 (unit). Hipotesis H26.
- **Severity / prioritas:** Rendah / P3. Selisih < 1 satuan mata uang.
- **Prakondisi:** `minimum_order.enable = 1`, `minimum_order_amount = 100.50` (config bertipe `numeric`, `Admin/src/Config/system.php:2359`). Cart `sub_total = 100.25`.
- **Langkah:** `cart()->haveMinimumOrderAmount()`.
- **Expected:** `false` (100,25 < 100,50), REQ-CO-05.
- **Actual:** `true`; `getOrderAmount()` = 100.
- **Akar penyebab:** Tipe kembalian `getOrderAmount(): int` (`Cart.php:789`) dan cast `(int)` pada nilai minimum (`Cart.php:817`).
- **Bukti:** `evidence/F4-actual-run{1,2}/MO-05.json`. Kontrol MO-01..03 (minimum bulat 100) PASS.

### DEF-05 — Harga "0" lolos validasi tetapi tersimpan `NULL`
- **Modul / kasus:** Harga (Admin) / PR-02. Hipotesis H17 → H27.
- **Severity / prioritas:** Sedang / P2. Atribut wajib `price` menjadi kosong. Dampak hilir (tampilan dan harga di cart) belum diuji.
- **Langkah:** `PUT admin/catalog/products/edit/{id}` dengan `price=0`.
- **Expected:** Tersimpan `0.0000` (REQ-PR-01 menerima 0 lewat `Decimal`; REQ-PR-03 nilai yang diterima tersimpan persis).
- **Actual:** HTTP 302 (sukses), `float_value` = **NULL**.
- **Akar penyebab:** `Product/src/Repositories/ProductAttributeValueRepository.php:44-49`: `if ($attribute->type === 'price' && empty($data[...])) $data[...] = null;`. Di PHP `empty("0") === true`, sedangkan `empty("0.0") === false`, sehingga perlakuannya tidak konsisten.
- **Bukti:** `evidence/F4-actual-run{1,2}/PR-02.json`.

### DEF-06 — Tambah ke cart tanpa `quantity` ditolak 400 dengan pesan PHP mentah
- **Modul / kasus:** Cart / CT-08. Hipotesis H14.
- **Severity / prioritas:** Rendah / P2. Klien API pihak ketiga yang mengikuti aturan validasi (quantity opsional) akan gagal, dan pesan internal PHP bocor ke klien.
- **Langkah:** `POST api/checkout/cart` `{"product_id": <id>}`.
- **Expected:** 200, item qty 1 (REQ-CT-01: `quantity` opsional di `CartController.php:54`; default 1 di `AbstractType.php:829`).
- **Actual:** HTTP **400**, `message` = `Undefined array key "quantity"`, tidak ada item.
- **Akar penyebab:** `Product/src/Type/Simple.php:198` membaca `$data['quantity']` tanpa default sebelum `handleQuantity()`.
- **Bukti:** `evidence/F4-actual-run{1,2}/CT-08.json`.

### DEF-07 — Tujuh endpoint cart/checkout membalas 500 untuk input/kondisi klien
- **Severity / prioritas:** Sedang / P2. Kesalahan input/kondisi klien seharusnya 4xx (RFC 9110 §15.5/§15.6). Pada `APP_DEBUG=true`, path file server dan pesan internal bocor.

| Sub | Kasus | Hipotesis | Langkah | Actual | Lokasi |
|---|---|---|---|---|---|
| 07a | CT-13 | H11 | `PUT api/checkout/cart` `{"qty":{"<i>":"abc"}}` | 500 `TypeError: Simple::haveSufficientQuantity(): Argument #1 ($qty) must be of type int, string given` (qty tetap 2) | `Product/src/Type/Simple.php:152` |
| 07b | CT-15 | D2 | `DELETE api/checkout/cart/selected` `{}` | 500 `foreach() argument must be of type array\|object, null given` | `CartController.php:114` |
| 07c | CT-17 | D1 | guest tanpa cart: `POST api/checkout/cart/estimate-shipping-methods` `{country, state, postcode}` | 500 `Attempt to read property "id" on null` | `CartController.php:184` |
| 07d | CT-19 | H21 | guest dengan cart: `POST api/checkout/cart/move-to-wishlist` `{ids:[i], qty:[1]}` | 500 `Integrity constraint violation: 1048 Column 'customer_id' cannot be null` | `Cart.php:718-726`; route tanpa middleware `customer` (`Shop/src/Routes/api.php:71`) |
| 07e | CT-21 | D5 | guest tanpa cart: `DELETE api/checkout/cart/coupon` | 500 `Attempt to assign property "coupon_code" on null` | `Checkout/src/Cart.php:620` |
| 07f | CO-19 | D8 | `POST api/checkout/onepage/addresses` `{"shipping":{...valid, use_for_shipping:0}}` tanpa `billing` | 500 `Undefined array key "billing"` | `CartAddressRequest.php:32-38` → `Cart.php:424` |
| 07g | CO-21 | H9 | guest tanpa cart: `POST api/checkout/onepage/addresses` (alamat valid) | 500 `Call to a member function hasGuestCheckoutItems() on null` | `OnepageController.php:48` |

- **Expected (semua):** 4xx (REQ-CT-03).
- **Bukti:** `evidence/F4-actual-run{1,2}/{CT-13,CT-15,CT-17,CT-19,CT-21,CO-19,CO-21}.json`.
- **Catatan:** Kontrol CT-20 (apply kupon tanpa cart) **tidak** 500, karena exception tertangkap di `CartController.php:252`. Jadi perilaku antar-endpoint pun tidak konsisten.

### DEF-08 — Kegagalan `PUT` cart dibalas HTTP 200
- **Modul / kasus:** Cart / CT-14 (`PUT` tanpa `qty`); status CT-11/CT-12 juga 200. Hipotesis D3.
- **Severity / prioritas:** Rendah / P3.
- **Langkah:** `PUT api/checkout/cart` `{}`.
- **Expected:** 4xx (REQ-CT-03), konsisten dengan `store()` yang membalas 400.
- **Actual:** HTTP 200 dengan pesan error di `data.message`.
- **Akar penyebab:** `CartController.php:159-163` membungkus exception menjadi `JsonResource` (200), sedangkan `:78-86` memakai `response()->json(..., 400)`.
- **Bukti:** `evidence/F4-actual-run{1,2}/CT-14.json`.

### DEF-09 — Checkout tanpa payment dibalas 500, bukan 4xx
- **Modul / kasus:** Checkout / OR-02. Hipotesis H24.
- **Severity / prioritas:** Rendah / P3. Order memang **tidak** dibuat (bagian REQ-CO-04 terpenuhi); yang salah hanya kode status.
- **Langkah:** Guest dengan cart → alamat valid → `flatrate_flatrate` → `POST api/checkout/onepage/orders` tanpa memilih payment.
- **Expected:** 4xx dengan pesan `specify-payment-method`.
- **Actual:** HTTP **500**, `message` = "Please specify a payment method."; `order_bertambah` = 0.
- **Akar penyebab:** `OnepageController.php:159-165` membalas `validateOrder()` exception dengan 500.
- **Bukti:** `evidence/F4-actual-run{1,2}/OR-02.json`.

### DEF-10 — Hapus item milik orang lain membalas "berhasil dihapus"
- **Modul / kasus:** Authorization / AZ-02; juga teramati pada AZ-06 (guest). Hipotesis H22.
- **Severity / prioritas:** Rendah / P3. Data A **aman** (AZ-01 PASS); yang salah adalah informasi ke pengguna.
- **Langkah:** Login B (punya cart sendiri) → `DELETE api/checkout/cart` `{"cart_item_id": <item milik A>}`.
- **Expected:** Pesan bukan `success-remove` (REQ-CT-04).
- **Actual:** HTTP 200, "Item is successfully removed from the cart."; item A masih ada.
- **Akar penyebab:** `CartController.php:99-106` mengabaikan nilai balik `Cart::removeItem()` (`false` di `Cart.php:323-325`). Validasi `exists:cart_items,id` (`:96`) mengecek seluruh tabel, bukan cart sendiri.
- **Catatan:** Test bawaan `CartTest.php:858+` mengesahkan 200 (`assertOk()`), sehingga menurut vendor ini mungkin "by design".
- **Bukti:** `evidence/F4-actual-run{1,2}/AZ-02.json`, `AZ-06.json`.

### DEF-11 — Email alamat checkout tidak divalidasi formatnya
- **Modul / kasus:** Checkout / CO-02. Hipotesis D7.
- **Severity / prioritas:** Sedang / P2. Email notifikasi order guest tidak terkirim. Field ini juga mengisi `customer_email` cart (`Cart.php:549`).
- **Langkah:** Guest dengan cart → `POST api/checkout/onepage/addresses` dengan `billing.email = "bukan-email"`.
- **Expected:** 422 (REQ-CO-01 [asumsi format]).
- **Actual:** HTTP 200, alamat tersimpan.
- **Akar penyebab:** `CartAddressRequest.php:52`: hanya `['required']`. Sebagai pembanding, registrasi memakai `email|required` (`RegistrationRequest.php:30`).
- **Bukti:** `evidence/F4-actual-run{1,2}/CO-02.json`. Kontrol CO-03 (email kosong) PASS.

### DEF-12 — Teks alamat > 255 karakter tersimpan terpotong tanpa error
- **Modul / kasus:** Checkout / CO-18. Hipotesis H23.
- **Severity / prioritas:** Rendah / P3.
- **Langkah:** `POST api/checkout/onepage/addresses` dengan `billing.first_name` 256 karakter.
- **Expected:** 422, atau tersimpan utuh.
- **Actual:** HTTP 200; tersimpan **255** karakter.
- **Akar penyebab:** Tidak ada aturan `max` (`CartAddressRequest.php:50-58`); kolom `addresses.first_name varchar(255)`; `strict => false`.
- **Bukti:** `evidence/F4-actual-run{1,2}/CO-18.json`. Kontrol CO-17 (255 karakter) PASS.

### DEF-13 — Checkout menerima rujukan alamat tersimpan milik customer lain
- **Modul / kasus:** Authorization / AZ-13. Hipotesis H12.
- **Severity / prioritas:** Rendah / P3.
  - Data alamat A **tidak** terbaca dan **tidak** berubah. Isi alamat yang tersimpan adalah data yang dikirim B.
  - `parent_address_id` hanya dipakai untuk preselect alamat di UI (`Shop/src/Resources/views/checkout/onepage/address/customer.blade.php:410-412`) dan ditampilkan di `AddressResource`.
  - Masalahnya adalah integritas referensi: ID objek milik orang lain diterima tanpa cek.
- **Langkah:** Login B, cart berisi item → `POST api/checkout/onepage/addresses` dengan `billing.address_type = "customer"`, `billing.id = <ID alamat A>`, dan data alamat valid.
- **Expected:** Ditolak, atau `parent_address_id` ≠ ID alamat A (REQ-AZ-03 [asumsi]).
- **Actual:** HTTP 200; `parent_address_id` = ID alamat A.
- **Akar penyebab:** `Checkout/src/Cart.php:456` (dan `:523` untuk shipping) mengambil `$params['id']` tanpa cek `customer_id`.
- **Bukti:** `evidence/F4-actual-run{1,2}/AZ-13.json`. Kontrol AZ-14 (alamat sendiri) PASS.

### DEF-14 — Login API mengabaikan channel customer
- **Modul / kasus:** Auth / AU-16. Hipotesis H16.
- **Severity / prioritas:** Sedang / P2. Pada instalasi multi-channel, akun milik channel lain dapat masuk ke channel ini lewat API, sedangkan lewat web ditolak.
- **Prakondisi:** Channel kedua (factory) dan customer ber-`channel_id` = channel kedua, password diketahui. Request ke channel `default`.
- **Langkah:**
  1. `POST customer/login` (web) dengan kredensial benar.
  2. `POST api/customer/login` dengan kredensial yang sama.
- **Expected:** Keduanya menolak (REQ-AU-04 [asumsi]).
- **Actual:** Web: tidak login (PASS). API: HTTP **200**, `auth('customer')->check() = true`.
- **Akar penyebab:** `Shop/src/Http/Controllers/API/CustomerController.php:20`: `attempt($request->only(['email','password']))` tanpa `channel_id`, berbeda dengan `SessionController.php:38`.
- **Bukti:** `evidence/F4-actual-run{1,2}/AU-16.json`.

### DEF-15 — Tidak ada pembatasan percobaan login
- **Modul / kasus:** Auth / AU-15. Hipotesis H15.
- **Severity / prioritas:** Sedang / P2 (brute force kredensial).
- **Langkah:** 10× `POST customer/login` dan 10× `POST api/customer/login` dengan password salah, berturut-turut, untuk email yang sama.
- **Expected:** Setelah N percobaan muncul 429 (REQ-AU-03 [asumsi], OWASP ASVS V2.2.1).
- **Actual:** Web 10× 302, API 10× 403, **tidak ada 429**.
- **Akar penyebab:** Route login di `Shop/src/Routes/customer-routes.php` dan `api.php:98` tanpa middleware `throttle`. Throttle hanya ada pada resend verifikasi (`customer-routes.php:60-62`).
- **Keterbatasan:** Diuji dalam proses test (cache `array`), bukan lewat server HTTP sungguhan; dipakai hanya untuk membuktikan ketiadaan throttle di route.
- **Bukti:** `evidence/F4-actual-run{1,2}/AU-15.json`.

---

## 3. Bukan defect (dicatat untuk transparansi)

| Kasus | Alasan |
|---|---|
| ST-08, PR-01..11 (run awal) | Kesalahan pada test, bukan SUT. Sudah diperbaiki (`06-hasil-eksekusi.md` §2). |
| SH-05 | Expected awal keliru (asumsi kode). Perilaku aktual sesuai REQ-CO-03/04. |
| D6 (AZ-01, 03–06) | Kepemilikan cart item diperiksa dengan benar. Hipotesis gugur. |
| ST-01..12 | Semua batas stok berperilaku sesuai REQ-ST-01/02. |
