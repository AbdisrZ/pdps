# 02 — Baseline Test Bawaan dan Coverage (FASE 2)

Tanggal: 2026-11-08. Commit acuan: tag `v1.0.0-baseline` (`d45d286`), Bagisto 2.3.19, PHP 8.2.12 ZTS + PCOV 1.0.12, MySQL 8.0.44, DB `bagisto_test`.

## 1. Pengaman sebelum eksekusi

| Pemeriksaan | Hasil | Bukti |
|---|---|---|
| GUARD-01: DB aktif **di dalam proses Pest** | `app()->environment()=testing`, `DB::connection()->getDatabaseName()=bagisto_test`, `select database()=bagisto_test` → **PASS** | `evidence/F2-GUARD-01-db-test.txt`; test `packages/Webkul/Shop/tests/Feature/Praktikum/GuardDatabaseTest.php` |
| DB utama tidak berubah selama eksekusi | Snapshot sebelum = sesudah (products 195, orders 40, customers 26, cart 41, cart_items 112; `UPDATE_TIME_MAX` tetap) | `evidence/F2-snapshot-db-utama-{sebelum,sesudah}.txt` |
| DB test kembali bersih (rollback `DatabaseTransactions`) | products/customers/cart/orders = 0 sebelum dan sesudah | `evidence/F2-snapshot-db-test-{sebelum,sesudah}.txt` |

Catatan: `AUTO_INCREMENT` pada tabel test tetap naik (mis. `products` = 157) karena rollback InnoDB tidak memundurkan counter. Ini tidak memengaruhi hasil, tetapi test sebaiknya **tidak** meng-hardcode ID.

## 2. Hasil per file (tanpa coverage, dijalankan satu per satu, tanpa `--parallel`)

Perintah: `vendor/bin/pest packages/Webkul/Shop/tests/Feature/<file> --colors=never`.

| File | Test | Lolos | Gagal | Assertion | Durasi Pest | Bukti |
|---|---|---|---|---|---|---|
| `Checkout/CartTest.php` | 42 | 42 | 0 | 508 | 33,87 s | `evidence/baseline-CartTest.txt` |
| `Checkout/CheckoutTest.php` | 38 | 38 | 0 | 609 | 42,45 s | `evidence/baseline-CheckoutTest.txt` |
| `Checkout/CouponUsageLimitTest.php` | 11 | 11 | 0 | 24 | 2,60 s | `evidence/baseline-CouponUsageLimitTest.txt` |
| `Customers/OrdersTest.php` | 4 | 4 | 0 | 40 | 17,68 s | `evidence/baseline-OrdersTest.txt` |
| `LoginPageTest.php` | 7 | 7 | 0 | 20 | 4,83 s | `evidence/baseline-LoginPageTest.txt` |
| `RegistrationPageTest.php` | 7 | 7 | 0 | 31 | 6,02 s | `evidence/baseline-RegistrationPageTest.txt` |
| `Customers/AccountTest.php` | 20 | 20 | 0 | 92 | 18,28 s | `evidence/baseline-AccountTest.txt` |
| `API/ProductTest.php` | 3 | 3 | 0 | 7 | 2,61 s | `evidence/baseline-ProductTest.txt` |
| **Total** | **132** | **132** | **0** | **1.331** | ≈128 s | |

**Kegagalan bawaan: tidak ada.** Seluruh 132 test bawaan yang dipilih lolos di lingkungan ini, sehingga tidak ada kegagalan yang perlu dipisahkan dari temuan praktikan. Sembilan file bawaan lain (Price*, HomePage, CategoryProduct, Wishlist) tidak termasuk daftar prompt dan belum dijalankan.

## 3. Coverage baseline

- Konfigurasi: `phpunit.coverage.xml`, yaitu salinan `phpunit.xml` yang hanya mengubah `<source>` (diff lengkap di §3.3).
- Perintah: `vendor/bin/pest -c phpunit.coverage.xml <8 file di atas> --coverage-html evidence/coverage-baseline/html --coverage-clover evidence/coverage-baseline/clover.xml --coverage-text=evidence/coverage-baseline/coverage.txt`.
- Hasil: 132 lolos, 115,9 s (`evidence/coverage-baseline/run.txt`).

### 3.1 Ringkasan per direktori

Sumber: `evidence/coverage-baseline/ringkasan-direktori.md`, dihitung dari `clover.xml` dengan `php testing/tools/clover_summary.php evidence/coverage-baseline/clover.xml "D:/Project Apps/pdps" evidence/coverage-baseline`. Totalnya cocok dengan laporan PHPUnit: Lines 1162/3496.

| Direktori (`packages/Webkul/…`) | File | Statement tertutup/total | Statement % | Function (method) tertutup/total | Function % | Branch |
|---|---|---|---|---|---|---|
| `Shop/src/Http/Controllers/API` | 11 | 156/669 | 23,32% | 7/54 | 12,96% | n/a |
| `Shop/src/Http/Requests` | 7 | 71/79 | 89,87% | 18/21 | 85,71% | n/a |
| `Checkout/src` | 42 | 454/885 | 51,30% | 48/81 | 59,26% | n/a |
| ↳ `Checkout/src` tanpa `Database/` | 27 | 413/619 | 66,72% | — | — | n/a |
| `Product/src/Type` | 8 | 458/1625 | 28,18% | 36/158 | 22,78% | n/a |
| `CartRule/src/Helpers` | 1 | 23/238 | 9,66% | 3/12 | 25,00% | n/a |
| **Total cakupan** | **69** | **1162/3496** | **33,24%** | **112/326** | **34,36%** | n/a |

- **Statement** = baris yang dapat dieksekusi menurut PCOV (Clover `statements`).
- **Function** = method yang *seluruh* barisnya tertutup (definisi PHPUnit "covered method"). Karena itu angkanya lebih ketat daripada "method pernah dipanggil".
- **Branch: tidak tersedia.** PCOV hanya merekam line coverage; branch/path coverage PHPUnit butuh Xdebug (`--path-coverage`). Cabang dianalisis manual di `05-whitebox.md`.
- **Penyebut `Checkout/src` ikut menghitung 15 file `Database/`** (migrasi dan factory, 266 statement, 41 tertutup). Migrasi tidak pernah dieksekusi saat test karena DB sudah dimigrasi di FASE 1, sehingga angka 51,30% "terseret turun". Baris pembanding tanpa `Database/` ditampilkan di tabel. Inilah contoh pertama bahwa angka coverage bergantung pada pilihan penyebut.

### 3.2 File yang di-scope
Daftar lengkap 69 file beserta angka per file: `evidence/coverage-baseline/ringkasan-file.md`. Angka per method: `evidence/coverage-baseline/ringkasan-method.md`. Laporan HTML: `evidence/coverage-baseline/html/index.html`.

### 3.3 Diff `phpunit.xml` → `phpunit.coverage.xml`
```diff
+ <!-- Salinan phpunit.xml untuk coverage praktikum: hanya <source> yang dibatasi ke modul P0. -->
-   <directory>app</directory>
-   <directory>packages</directory>
+   <directory suffix=".php">packages/Webkul/Shop/src/Http/Controllers/API</directory>
+   <directory suffix=".php">packages/Webkul/Shop/src/Http/Requests</directory>
+   <directory suffix=".php">packages/Webkul/Checkout/src</directory>
+   <directory suffix=".php">packages/Webkul/Product/src/Type</directory>
+   <directory suffix=".php">packages/Webkul/CartRule/src/Helpers</directory>
```

## 4. Celah: method P0/P1 dengan coverage rendah (bahan FASE 3–4)

Angka berikut adalah line coverage per method dari `ringkasan-method.md`. Rujukan baris = baris deklarasi method.

| Prioritas | Method | Baris | Coverage | Kaitan hipotesis |
|---|---|---|---|---|
| P0 Cart/Kupon | `CartController::storeCoupon` | `Shop/.../API/CartController.php:212` | **0/31 (0%)** | D4, D5, cabang mati `strlen` |
| P0 Cart/Kupon | `CartController::destroyCoupon` | `:263` | 0/5 (0%) | D5 |
| P0 Cart | `CartController::moveToWishlist` + `Cart::moveToWishlist` | `:129`; `Checkout/src/Cart.php:671` | 0/8 dan 0/44 (0%) | D6 (wishlist), H21 |
| P0 Cart | `Cart::setCouponCode` / `removeCouponCode` | `Cart.php:618`, `:630` | 0% | D5 |
| P0 Checkout | `Cart::getOrderAmount` | `Cart.php:789` | **0/8 (0%)** | H13 |
| P0 Checkout | `Cart::getErrors` | `Cart.php:757` | 4/18 (22%) | cabang `INSUFFICIENT_QUANTITY` dan `MINIMUM_ORDER_AMOUNT` belum tersentuh |
| P0 Checkout | `OnepageController::storeOrder` | `OnepageController.php:148` | 13/32 (41%) | cabang error `validateOrder` dan exception kupon |
| P0 Checkout | `OnepageController::storeAddress` | `:42` | 17/29 (59%) | H9 (redirect guest), D8 |
| P0 Checkout | `OnepageController::validateOrder` | `:206` | 13/21 (62%) | customer suspended/nonaktif, minimum order |
| P0 Cart | `CartController::store` | `:49` | 14/26 (54%) | H14, produk nonaktif, stok kurang (400) |
| P0 Cart | `CartController::update` | `:148` | 6/10 (60%) | D3, H10, H11 (catch → 200) |
| P0 Stok | `Simple::prepareForCart` / `Virtual::prepareForCart` | `Product/src/Type/Simple.php:189`; `Virtual.php:212` | 28/52 (54%) | BVA stok, opsi kustom |
| P0 Stok | `Configurable::haveSufficientQuantity` / `isItemHaveQuantity` | `Configurable.php:568`, `:305` | **0%** | stok varian |
| P0 Stok | `Bundle::haveSufficientQuantity` | `Bundle.php:535` | 3/7 (43%) | stok opsi bundle |
| P1 Kupon | `CartRule::canProcessRule`, `process`, `checkCouponCode`, `processShippingDiscount` | `CartRule/src/Helpers/CartRule.php:126`, `:190`, `:495`, `:313` | **0%** | H19, H20, batas `usage_limit` |

### 4.1 Coverage tinggi bukan berarti bebas defect (bahan "batas metrik coverage")

| Method | Coverage baseline | Hipotesis yang tetap terbuka | Mengapa coverage tidak menangkapnya |
|---|---|---|---|
| `CartAddressRequest::rules` / `mergeAddressRules` | **100% / 100%** | D7 (email tanpa format), D8 (tanpa key `billing`) | Defeknya berupa aturan validasi yang **tidak ada**. Baris yang tidak ada tidak bisa "tidak tertutup". |
| `CartController::destroySelected` | **7/7 (100%)** | D2 (`ids` tidak divalidasi) | Sama: tidak ada baris validasi yang bisa diukur. |
| `CartController::estimateShippingMethods` | 25/26 (96%) | D1 (`$cart` null) | Tidak ada `if` untuk null, jadi tidak ada cabang yang terlewat. Kasus "tanpa cart" sama sekali tidak terlihat di metrik. |
| `Cart::updateOrCreateBillingAddress` | 30/30 (100%) | H12 (`parent_address_id` tanpa cek pemilik) | Baris ternary `:456` dihitung "tertutup" begitu baris itu dieksekusi. Line coverage tidak mencatat sisi ternary mana (`customer` atau bukan) yang benar-benar dijalankan (lihat `05-whitebox.md`). |
| `Cart::collectTotals` | 53/53 (100%) | — | Tertutup penuh, tetapi tidak menjamin akurasi aritmetika (pembulatan, `decimal(12,4)`, H18). |

## 5. Kesimpulan FASE 2
1. Baseline bersih: 132/132 lolos dan tidak ada kegagalan bawaan.
2. Coverage statement area P0 = 33,24% (1162/3496). Terendah: CartRule 9,66% dan Controllers/API 23,32%.
3. Celah utama untuk diuji: alur kupon (0%), wishlist dari cart (0%), minimum order (0%), stok configurable (0%), dan cabang error checkout.
4. Beberapa hipotesis (D1, D2, D7, D8, H12) berada di kode yang **sudah 96–100% tertutup**. Ini bukti bahwa test berbasis kebutuhan (black-box EP/BVA) tetap diperlukan walaupun coverage tinggi.
