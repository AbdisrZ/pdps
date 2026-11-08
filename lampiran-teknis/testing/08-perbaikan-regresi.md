# 08 — Perbaikan Defect dan Uji Regresi (FASE 5)

Defect yang dipilih praktikan: **DEF-03** dan **DEF-01** (`07-defect-report.md`). Setiap perbaikan mengikuti alur:
1. Branch `fix/*`.
2. Test merah yang mereproduksi (2 run), lalu commit.
3. Perbaikan minimal, lalu test hijau (2 run).
4. Regresi.
5. Commit, tag, lalu merge `--no-ff` ke `praktikum`.

## 1. Riwayat Git
```
*   06de19a Merge fix/harga-batas-maksimum (DEF-01, v1.0.2)
|\
| * 4dad895 fix(admin): tolak harga di atas kapasitas kolom decimal(12,4) (DEF-01)   <- tag v1.0.2
| * ce80528 test(red): reproduksi DEF-01 ...
|/
*   a34e896 Merge fix/min-order-include-discount (DEF-03, v1.0.1)
|\
| * 141dcdd fix(checkout): minimum order include_discount tidak lagi mengurangi pajak (DEF-03)   <- tag v1.0.1
| * 9b9338d test(red): reproduksi DEF-03 ...
|/
* ed89f2e test: eksekusi pengujian P0 dan laporan defect (FASE 4)
...
* d45d286 chore: baseline praktikum ...   <- tag v1.0.0-baseline
```
Total perubahan kode produk terhadap `v1.0.0-baseline`: **2 file, +11/−2 baris** (`git diff v1.0.0-baseline --stat -- packages/Webkul/*/src app`).

## 2. DEF-03 — Minimum order "include discount" mengurangi pajak (v1.0.1)

**Analisis akar penyebab.** Di `Checkout/src/Cart.php::getOrderAmount()` (789–806), blok opsi `include_discount_amount` memuat `$minimumOrderAmount -= $this->cart->tax_total;` (baris 798 pada baseline) sebelum pengurangan diskon (800–802). Pajak sudah diatur oleh opsi terpisah `include_tax_to_amount` (793–795). Baris 798 tampaknya salah salin dari blok pajak, karena variabelnya `tax_total` padahal nama opsinya "discount". Akibatnya:
- Pajak dikurangkan walaupun tidak pernah ditambahkan.
- Bila kedua opsi aktif, pajak saling meniadakan sehingga opsi "include tax" tidak berpengaruh.

**Perbaikan** (`141dcdd`): menghapus baris 798 (−2 baris, termasuk baris kosong).
```diff
         if (core()->getConfigData('sales.order_settings.minimum_order.include_discount_amount')) {
-            $minimumOrderAmount -= $this->cart->tax_total;
-
             if ($this->cart->discount_amount) {
```

**Test** `Praktikum/FixMinOrderTest.php` (unit, `Cart::getOrderAmount()` dengan cart in-memory):

| ID | Opsi pajak / diskon | sub / tax / disc | Expected | Sebelum | Sesudah |
|---|---|---|---|---|---|
| FX1-01 | 0 / 1 | 100 / 10 / 0 | 100 | **90** FAIL | 100 PASS |
| FX1-02 | 0 / 1 | 100 / 0 / 15 | 85 | 85 PASS | 85 PASS |
| FX1-03 | 1 / 1 | 100 / 10 / 15 | 95 | **85** FAIL | 95 PASS |
| FX1-04 | 1 / 0 | 100 / 10 / 15 | 110 | 110 PASS | 110 PASS |
| FX1-05 | 0 / 0 | 100 / 10 / 15 | 100 | 100 PASS | 100 PASS |

Bukti: merah `evidence/F5-DEF-03-merah-run{1,2}.txt` (2 failed, 3 passed) dan `evidence/F5-DEF-03-actual-merah/`; hijau `evidence/F5-DEF-03-hijau-run{1,2}.txt` (5 passed) dan `evidence/F5-DEF-03-actual-hijau/`.

**Regresi:**
- Suite Praktikum: hanya **MO-04** berubah, FAIL → PASS (`evidence/F5-DEF-03-regresi-praktikum*.{txt,xml}`).
- Test bawaan yang menyentuh `Cart.php`: CartTest 42/42, CheckoutTest 38/38, CouponUsageLimitTest 11/11 (`evidence/F5-DEF-03-regresi-*.txt`), sama dengan baseline (`02-baseline.md` §2).

**Yang tidak diperbaiki:** DEF-04 (`getOrderAmount(): int` memotong pecahan) sengaja dibiarkan karena bukan defect terpilih. FX1 memakai bilangan bulat agar tidak tercampur.

## 3. DEF-01 — Harga di atas kapasitas tersimpan terpotong (v1.0.2)

**Analisis akar penyebab.** Tiga faktor bertemu:
1. `Core/src/Rules/Decimal.php:15` hanya memeriksa format (`^\d*(\.\d{1,4})?$`), tanpa batas atas.
2. Kolom harga `decimal(12,4)` (maks. 99.999.999,9999).
3. Koneksi `strict => false` (`config/database.php:58`), sehingga MySQL memotong nilai di luar rentang menjadi nilai maksimum alih-alih menolak.

Validasi aplikasi adalah satu-satunya lapisan yang dapat menolak, dan lapisan itu tidak mengetahui kapasitas kolom.

**Pilihan perbaikan yang dipertimbangkan:**
| Opsi | Alasan tidak/dipilih |
|---|---|
| Ubah `Decimal` agar punya batas | `Decimal` juga dipakai untuk field lain (mis. `special_price`, atribut teks validasi decimal), sehingga dampaknya lebih luas dari defect |
| Set `strict => true` | Mengubah perilaku seluruh query aplikasi; risiko regresi besar |
| Perlebar kolom (migrasi) | Mengubah skema 4+ tabel; di luar "perbaikan minimal" |
| **Tambah `numeric` + `max:99999999.9999` untuk atribut bertipe price di `ProductForm`** | **Dipilih**: lokal, kecil, dan menghasilkan pesan validasi yang jelas |

**Perbaikan** (`4dad895`, `Admin/src/Http/Requests/ProductForm.php`):
```diff
+    public const MAX_PRICE = '99999999.9999';
 ...
             if ($attribute->type == AttributeTypeEnum::PRICE->value) {
                 $validations[] = new Decimal;
+
+                $validations[] = 'numeric';
+
+                $validations[] = 'max:'.self::MAX_PRICE;
             }
```
Aturan `numeric` diperlukan agar `max` membandingkan nilai, bukan panjang string. Sebelum mengubah kode, diverifikasi bahwa penambahan `numeric` **tidak** mengubah hasil `lt:price` pada `special_price`. Tujuh pasangan nilai (mis. 2000/1500, 100/100, 1000/999) memberi hasil identik dengan dan tanpa `numeric`.

**Test** `Praktikum/FixHargaMaksimumTest.php` (integration, `PUT admin/catalog/products/edit/{id}`):

| ID | Input | Expected | Sebelum | Sesudah |
|---|---|---|---|---|
| FX2-01 | price 100000000 | 422, nilai lama tetap | 302, tersimpan **99999999.9999** FAIL | 422 "The price field must not be greater than 99999999.9999." PASS |
| FX2-03 | cost 100000000 | 422 | 302, tersimpan 99999999.9999 FAIL | 422 PASS |
| FX2-04 | price 1000000000000 | 422 | 302, tersimpan 99999999.9999 FAIL | 422 PASS |
| FX2-02 | price 99999999.9999 | tersimpan persis | PASS | PASS |
| FX2-05 | price 150000 | tersimpan persis | PASS | PASS |
| FX2-06 | price 0.0001 | tersimpan persis | PASS | PASS |
| FX2-07 | cost 99999999.9999 | tersimpan persis | PASS | PASS |

Bukti: merah `evidence/F5-DEF-01-merah-run{1,2}.txt` (3 failed, 4 passed) dan `evidence/F5-DEF-01-actual-merah/`; hijau `evidence/F5-DEF-01-hijau-run{1,2}.txt` (7 passed) dan `evidence/F5-DEF-01-actual-hijau/` (termasuk `PR-09.json`).

**Regresi:**
- Suite Praktikum: hanya **PR-09** berubah, FAIL → PASS. Ekspektasi PR-09 menerima "422 atau tersimpan persis".
- Test bawaan Admin `Catalog/Products` (ProductTest + 6 tipe produk): **52/52 sebelum perbaikan** (`evidence/F5-DEF-01-pembanding-AdminCatalogProducts-sebelum.txt`, dijalankan dengan `ProductForm.php` versi `v1.0.1`) dan **52/52 sesudah** (`evidence/F5-DEF-01-regresi-AdminCatalogProducts.txt`).

**Keterbatasan perbaikan (risiko sisa):**
- Hanya jalur form Admin (`ProductForm`) yang dilindungi. Jalur lain yang menulis harga tanpa `ProductForm` belum dicakup: import DataTransfer, repository langsung seperti skrip impor data demo, mass update.
- Nilai yang sudah terpotong di DB utama (6 produk Rolex) tidak diperbaiki otomatis.

## 4. Regresi akhir pada branch `praktikum` (kedua perbaikan aktif)

| Suite | Hasil | Pembanding baseline/FASE 4 | Bukti |
|---|---|---|---|
| GUARD-01 | 1/1 | — | — |
| Praktikum (125 test = 113 FASE 4 + 5 FX1 + 7 FX2) | 105 passed, **20 failed** | FASE 4: 22 failed. Berubah hanya **MO-04** dan **PR-09** (FAIL → PASS) | `evidence/F5-final-praktikum*.{txt,xml}` |
| CartTest | 42/42 | 42/42 | `evidence/F5-final-bawaan-CartTest.txt` |
| CheckoutTest | 38/38 | 38/38 | `…-CheckoutTest.txt` |
| CouponUsageLimitTest | 11/11 | 11/11 | `…-CouponUsageLimitTest.txt` |
| OrdersTest | 4/4 | 4/4 | `…-OrdersTest.txt` |
| LoginPageTest | 7/7 | 7/7 | `…-LoginPageTest.txt` |
| RegistrationPageTest | 7/7 | 7/7 | `…-RegistrationPageTest.txt` |
| AccountTest | 20/20 | 20/20 | `…-AccountTest.txt` |
| API/ProductTest | 3/3 | 3/3 | `…-ProductTest.txt` |
| Admin Catalog/Products | 52/52 | 52/52 (sebelum fix) | `evidence/F5-final-AdminCatalogProducts.txt` |

Ke-20 FAIL yang tersisa adalah 13 defect terbuka lainnya (`07-defect-report.md`). Tidak ada test yang sebelumnya lolos menjadi gagal.
