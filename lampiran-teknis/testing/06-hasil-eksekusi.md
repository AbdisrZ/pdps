# 06 — Hasil Eksekusi Pengujian P0 (FASE 4)

Tanggal eksekusi: 2026-11-08. Lingkungan: `00-lingkungan.md` (Bagisto 2.3.19, PHP 8.2.12 ZTS + PCOV, MySQL 8.0.44, DB `bagisto_test`). Commit acuan kode produk: `v1.0.0-baseline` (**kode produk tidak diubah**).

## 1. Ringkasan

| Item | Nilai |
|---|---|
| File test baru | `packages/Webkul/Shop/tests/Feature/Praktikum/{AuthorizationTest, StockCartTest, CheckoutTest, AuthTest, ProductPriceTest, GuardDatabaseTest}.php` + helper `Concerns/PraktikumHelpers.php` |
| Kasus P0 dieksekusi | **112** (111 dari `04-blackbox.md` §1–§7 + MO-05 yang ditambahkan saat FASE 4) + GUARD-01 |
| Jenis test | Integration (HTTP + DB test): 110 kasus. Unit (model in-memory, tanpa HTTP): MO-04, MO-05 |
| PASS | **90** |
| FAIL | **22**, semuanya FAIL di **kedua** run dengan perilaku aktual yang sama |
| Tidak stabil antar-run | 0 |
| P1 kupon (CR-01..08) | Belum dieksekusi (dijadwalkan sesudah perbaikan, sesuai prioritas) |
| Run 1 | 113 test, 22 failed, 91 passed (342 assertion), 80,5 s (`evidence/F4-run1-output.txt`, `F4-run1-junit.xml`) |
| Run 2 | 113 test, 22 failed, 91 passed (342 assertion), 84,1 s (`evidence/F4-run2-output.txt`, `F4-run2-junit.xml`) |
| Perbandingan status per kasus | `evidence/F4-ringkasan-status.tsv` (`testing/tools/junit_ids.php`): **identik** antar run |
| Pengaman | GUARD-01 lolos sebelum tiap run; DB utama identik sebelum/sesudah (`evidence/F4-snapshot-db-utama-*.txt`); DB test kembali 0 baris |

Perintah reproduksi:
```
vendor/bin/pest packages/Webkul/Shop/tests/Feature/Praktikum/GuardDatabaseTest.php
vendor/bin/pest packages/Webkul/Shop/tests/Feature/Praktikum --log-junit evidence/F4-runN-junit.xml
php testing/tools/junit_ids.php evidence/F4-run1-junit.xml evidence/F4-run2-junit.xml
```

### 1.1 Rekap per modul
| Modul | Kasus | PASS | FAIL | Kasus FAIL |
|---|---|---|---|---|
| Authorization (AZ) | 14 | 12 | 2 | AZ-02, AZ-13 |
| Stok (ST) | 12 | 12 | 0 | — |
| Cart (CT) | 21 | 12 | 9 | CT-08, CT-11, CT-12, CT-13, CT-14, CT-15, CT-17, CT-19, CT-21 |
| Checkout alamat (CO) | 21 | 17 | 4 | CO-02, CO-18, CO-19, CO-21 |
| Shipping/order/min (SH, OR, MO) | 14 | 11 | 3 | OR-02, MO-04, MO-05 |
| Auth (AU) | 16 | 14 | 2 | AU-15, AU-16 |
| Harga (PR) | 14 | 12 | 2 | PR-02, PR-09 |
| **Total** | **112** | **90** | **22** | |

### 1.2 Status hipotesis setelah eksekusi
| Hipotesis | Kasus | Hasil |
|---|---|---|
| D1 estimate-shipping tanpa cart | CT-17 | **Terbukti**: 500 `Attempt to read property "id" on null` (`CartController.php:184`) |
| D2 destroy_selected tanpa ids | CT-15 | **Terbukti**: 500 `foreach() argument must be of type array\|object, null given` (`:114`) |
| D3 PUT cart error → 200 | CT-11, CT-12, CT-14 | **Terbukti**: 200 dengan pesan error |
| D4 pesan "Coupon not found." | CR-02 | Belum diuji (P1) |
| D5 kupon tanpa cart | CT-20, CT-21 | **Sebagian**: apply → 4xx (PASS); remove → 500 `Attempt to assign property "coupon_code" on null` (`Cart.php:620`) |
| D6 kepemilikan cart item | AZ-01, AZ-03..06 | **Gugur**: semua PASS, data milik A tidak berubah |
| D7 email checkout tanpa format | CO-02 | **Terbukti**: `bukan-email` diterima (200) |
| D8 tanpa key billing | CO-19 | **Terbukti**: 500 `Undefined array key "billing"` (`Cart.php:424`) |
| H9 alamat tanpa cart | CO-21 | **Terbukti**: 500 `Call to a member function hasGuestCheckoutItems() on null` (`OnepageController.php:48`) |
| H10 qty ≤ 0 menghapus item | CT-11, CT-12 | **Terbukti**: item terhapus (`qty_item_sesudah=null`), respons 200 |
| H11 qty non-angka | CT-13 | **Terbukti**: 500 `TypeError ... must be of type int, string given` (`Simple.php:152`) |
| H12 alamat milik orang lain | AZ-13 | **Terbukti** (dampak rendah, §4) |
| H13 minimum order mengurangi pajak | MO-04 | **Terbukti**: `getOrderAmount()` = 90, bukan 100 |
| H14 tanpa quantity | CT-08 | **Terbukti**: 400 `Undefined array key "quantity"` |
| H15 tanpa rate limit | AU-15 | **Terbukti**: 10/10 percobaan web 302, API 403, tidak ada 429 |
| H16 API login lintas channel | AU-16 | **Terbukti**: web menolak, API menerima (200, terautentikasi) |
| H17 harga 0 diterima | PR-02 | Validasi menerima, **tetapi tersimpan `NULL`** (lihat H27) |
| H18 harga terpotong | PR-08, PR-09 | **Terbukti**: 99.999.999,9999 tersimpan persis (PASS); 100.000.000 → 302 sukses, tersimpan 99.999.999,9999 |
| H21 wishlist guest | CT-19 | **Terbukti**: 500 `Integrity constraint violation: 1048 Column 'customer_id' cannot be null`. Constraint DB mencegah data yatim, tetapi endpoint tetap terbuka untuk guest. |
| H22 pesan sukses palsu | AZ-02, AZ-06 | **Terbukti**: 200 + "Item is successfully removed from the cart." walau item tidak dihapus |
| H23 teks > 255 terpotong | CO-17, CO-18 | **Terbukti**: 255 utuh (PASS); 256 → 200, tersimpan 255 karakter |
| H24 storeOrder 500 | OR-02 | **Terbukti**: 500 "Please specify a payment method." (order tidak dibuat) |
| H26 minimum pecahan | MO-05 | **Terbukti**: minimum 100,50 terpenuhi oleh subtotal 100,25 (`getOrderAmount` = 100) |
| H27 harga "0" → NULL | PR-02 | **Terbukti**: tersimpan `NULL` (`ProductAttributeValueRepository.php:44-49`) |
| Stok/oversell (R02) | ST-01..12 | **Tidak ada defect**: semua batas stok (stok, stok+1, kumulatif, update, back-order, manage_stock, stok terpesan) PASS |

## 2. Koreksi selama eksekusi (dicatat terbuka)

| Kasus | Jenis koreksi | Uraian | Dampak ke status |
|---|---|---|---|
| ST-08 | **Kesalahan test** (bukan defect) | Run awal gagal karena test membaca `message` di root JSON, padahal `CartController::update` membungkus pesan di `data.message` (`CartController.php:160-162`, `JsonResource`). Qty memang tetap 2 (sesuai REQ-ST-01). Assertion diperbaiki ke `data.message`. | PASS |
| PR-01..PR-11 (valid) | **Kesalahan test** | Helper memanggil `$r->json('errors')` pada respons redirect 302 → "Invalid JSON". Diganti `prkErrors()` yang aman untuk non-JSON. | PR-01/04/08/11 PASS |
| SH-05 | **Koreksi expected** | Expected awal di `04-blackbox.md` "403 (rate belum ada)" bukan turunan REQ, melainkan asumsi saya tentang kode. REQ-CO-03 hanya mensyaratkan kode = rate yang tersedia, dan flat rate memang tersedia tanpa alamat. Urutan alamat → order dijaga oleh REQ-CO-04, dan diverifikasi bahwa order tanpa alamat ditolak. | PASS (expected dikoreksi, ditandai di `04-blackbox.md`) |
| MO-05 | **Kasus baru** | Ditambahkan karena `getOrderAmount(): int` (H26) ditemukan saat menulis MO-*. | FAIL |

## 3. Hasil per kasus

Kolom *Actual* diringkas otomatis dari `evidence/F4-actual-run1/<ID>.json` (`testing/tools/gen_hasil.php`). Rekaman run 2 ada di `evidence/F4-actual-run2/`. Perbedaan run 1 vs run 2 hanya pada nilai acak (ID, kode channel faker), bukan pada perilaku.

### 3.1 Authorization

| ID | Expected (04-blackbox) | Actual (run 1) | Run 1 | Run 2 | Status | Bukti |
|---|---|---|---|---|---|---|
| AZ-01 | `iA` tetap ada, cart A tidak berubah (REQ-AZ-01) | HTTP 200; "Item is successfully removed from the cart."; item_A_masih_ada=true | PASS | PASS | PASS | `evidence/F4-actual-run1/AZ-01.json` |
| AZ-02 | Respons **tidak** menyatakan `success-remove` bila tidak ada yang dihapus (REQ-CT-04) | HTTP 200; "Item is successfully removed from the cart."; pesan_sukses=Item is successfully removed from the cart.; item_A_masih_ada=true | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/AZ-02.json` |
| AZ-03 | Qty `iA` tidak berubah | HTTP 200; "Quantity updated successfully"; qty_item_A=2 | PASS | PASS | PASS | `evidence/F4-actual-run1/AZ-03.json` |
| AZ-04 | `iA` tetap ada | HTTP 200; "Selected items successfully removed from cart."; item_A_masih_ada=true | PASS | PASS | PASS | `evidence/F4-actual-run1/AZ-04.json` |
| AZ-05 | `iA` tetap ada; tidak ada wishlist baru untuk B | HTTP 200; "Selected items successfully moved to wishlist."; item_A_masih_ada=true; wishlist_B_untuk_produk_A=0 | PASS | PASS | PASS | `evidence/F4-actual-run1/AZ-05.json` |
| AZ-06 | `iA` tetap ada | HTTP 200; "Item is successfully removed from the cart."; item_A_masih_ada=true | PASS | PASS | PASS | `evidence/F4-actual-run1/AZ-06.json` |
| AZ-07 | 200 | HTTP 200 | PASS | PASS | PASS | `evidence/F4-actual-run1/AZ-07.json` |
| AZ-08 | **404** (REQ-AZ-02) | HTTP 404 | PASS | PASS | PASS | `evidence/F4-actual-run1/AZ-08.json` |
| AZ-09 | 404; cart B tidak bertambah | HTTP 404; cart_aktif_B=null | PASS | PASS | PASS | `evidence/F4-actual-run1/AZ-09.json` |
| AZ-10 | 404; status `oA` tidak berubah | HTTP 404; status_sebelum=processing; status_sesudah=processing | PASS | PASS | PASS | `evidence/F4-actual-run1/AZ-10.json` |
| AZ-11 | 404 | HTTP 404; invoice_id=12; invoice_ada=true | PASS | PASS | PASS | `evidence/F4-actual-run1/AZ-11.json` |
| AZ-12 | Redirect ke login | HTTP 302; location=http://localhost:8000/customer/login | PASS | PASS | PASS | `evidence/F4-actual-run1/AZ-12.json` |
| AZ-13 | Ditolak (4xx) **atau** `parent_address_id` tidak berisi `adrA` (REQ-AZ-03 [asumsi]) | HTTP 200; alamat_A_id=359; billing_B_parent_address_id=359 | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/AZ-13.json` |
| AZ-14 | 200, `parent_address_id = adrB` | HTTP 200; alamat_B_id=362; billing_parent_address_id=362 | PASS | PASS | PASS | `evidence/F4-actual-run1/AZ-14.json` |

### 3.2 Stok dan Cart

| ID | Expected (04-blackbox) | Actual (run 1) | Run 1 | Run 2 | Status | Bukti |
|---|---|---|---|---|---|---|
| CT-01 | 422 error `product_id` | HTTP 422; "The product id field is required."; error: product_id | PASS | PASS | PASS | `evidence/F4-actual-run1/CT-01.json` |
| CT-02 | 422 | HTTP 422; "The selected product id is invalid."; error: product_id | PASS | PASS | PASS | `evidence/F4-actual-run1/CT-02.json` |
| CT-03 | 422 | HTTP 422; "The product id field must be an integer."; error: product_id | PASS | PASS | PASS | `evidence/F4-actual-run1/CT-03.json` |
| CT-04 | 422 | HTTP 422; "The quantity field must be at least 1."; error: quantity | PASS | PASS | PASS | `evidence/F4-actual-run1/CT-04.json` |
| CT-05 | 200 | HTTP 200; "Item Added Successfully"; qty_item=1 | PASS | PASS | PASS | `evidence/F4-actual-run1/CT-05.json` |
| CT-06 | 422 | HTTP 422; "The quantity field must be at least 1."; error: quantity | PASS | PASS | PASS | `evidence/F4-actual-run1/CT-06.json` |
| CT-07 | 422 | HTTP 422; "The quantity field must be an integer."; error: quantity | PASS | PASS | PASS | `evidence/F4-actual-run1/CT-07.json` |
| CT-08 | 200, qty default 1 (REQ-CT-01; `AbstractType.php:829`) | HTTP 400; "Undefined array key "quantity""; qty_item=null | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/CT-08.json` |
| CT-09 | 400 pesan `inactive-add` (`CartController.php:60-61`) | HTTP 400; "Inactive item cannot be added to cart." | PASS | PASS | PASS | `evidence/F4-actual-run1/CT-09.json` |
| CT-10 | 422 | HTTP 422; "The selected is buy now is invalid."; error: is_buy_now | PASS | PASS | PASS | `evidence/F4-actual-run1/CT-10.json` |
| CT-11 | 4xx; **item tetap ada** (REQ-CT-02/03) | HTTP 200; "Quantity cannot be lesser than one."; qty_item_sesudah=null | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/CT-11.json` |
| CT-12 | 4xx; item tetap ada | HTTP 200; "Quantity cannot be lesser than one."; qty_item_sesudah=null | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/CT-12.json` |
| CT-13 | 4xx; qty tidak berubah | HTTP 500; "Webkul\Product\Type\Simple::haveSufficientQuantity(): Argument #1 ($qty) must be of type i…"; qty_item_sesudah=2 | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/CT-13.json` |
| CT-14 | 4xx (bukan 200) | HTTP 200; "Undefined array key "qty"" | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/CT-14.json` |
| CT-15 | 422 (bukan 500) | HTTP 500; "foreach() argument must be of type array\|object, null given" | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/CT-15.json` |
| CT-16 | 422 (`CartController.php:95-97`) | HTTP 422; "The cart item id field is required."; error: cart_item_id | PASS | PASS | PASS | `evidence/F4-actual-run1/CT-16.json` |
| CT-17 | 4xx (bukan 500) | HTTP 500; "Attempt to read property "id" on null" | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/CT-17.json` |
| CT-18 | 422 (`:171-176`) | HTTP 422; "The country field is required."; error: country | PASS | PASS | PASS | `evidence/F4-actual-run1/CT-18.json` |
| CT-19 | 401/403 atau ditolak; tidak ada wishlist ber-`customer_id` null | HTTP 500; "SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'customer_id' cannot be null …"; wishlist_tanpa_customer=0; item_masih_di_cart=true | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/CT-19.json` |
| CT-20 | 4xx (bukan 500) | HTTP 422; "Coupon code is invalid." | PASS | PASS | PASS | `evidence/F4-actual-run1/CT-20.json` |
| CT-21 | 4xx/200 tanpa error server (bukan 500) | HTTP 500; "Attempt to assign property "coupon_code" on null" | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/CT-21.json` |
| ST-01 | 200; item qty = 2 | HTTP 200; "Item Added Successfully"; qty_item=2 | PASS | PASS | PASS | `evidence/F4-actual-run1/ST-01.json` |
| ST-02 | 200 | HTTP 200; "Item Added Successfully"; qty_item=4 | PASS | PASS | PASS | `evidence/F4-actual-run1/ST-02.json` |
| ST-03 | 200; item qty = 5 | HTTP 200; "Item Added Successfully"; qty_item=5 | PASS | PASS | PASS | `evidence/F4-actual-run1/ST-03.json` |
| ST-04 | **400**, pesan `inventory-warning`; tidak ada item tersimpan | HTTP 400; "The requested quantity is not available, please try again later."; qty_item=null | PASS | PASS | PASS | `evidence/F4-actual-run1/ST-04.json` |
| ST-05 | `POST` kedua 400; qty tetap 5 (`AbstractType.php:838-845`) | HTTP 400; "The requested quantity is not available, please try again later."; qty_item=5 | PASS | PASS | PASS | `evidence/F4-actual-run1/ST-05.json` |
| ST-06 | 200; qty = 5 | HTTP 200; "Item Added Successfully"; qty_item=5 | PASS | PASS | PASS | `evidence/F4-actual-run1/ST-06.json` |
| ST-07 | Qty = 5, pesan sukses | HTTP 200; "Quantity updated successfully"; qty_item=5 | PASS | PASS | PASS | `evidence/F4-actual-run1/ST-07.json` |
| ST-08 | Ditolak dengan pesan `inventory-warning`; **qty tetap lama** (`Cart.php:366-368`) | HTTP 200; "The requested quantity is not available, please try again later."; qty_item=2 | PASS | PASS | PASS | `evidence/F4-actual-run1/ST-08.json` |
| ST-09 | 400 | HTTP 400; "The requested quantity is not available, please try again later."; qty_item=null | PASS | PASS | PASS | `evidence/F4-actual-run1/ST-09.json` |
| ST-10 | 200 (REQ-ST-02; `Simple.php:158`) | HTTP 200; "Item Added Successfully"; qty_item=6 | PASS | PASS | PASS | `evidence/F4-actual-run1/ST-10.json` |
| ST-11 | 200 (`Simple.php:154-156`) | HTTP 200; "Item Added Successfully"; manage_stock=false; qty_item=50 | PASS | PASS | PASS | `evidence/F4-actual-run1/ST-11.json` |
| ST-12 | 2 → 200; 3 → 400 | HTTP 200; "Item Added Successfully"; status_qty3=400; qty_item=2 | PASS | PASS | PASS | `evidence/F4-actual-run1/ST-12.json` |

### 3.3 Checkout - alamat

| ID | Expected (04-blackbox) | Actual (run 1) | Run 1 | Run 2 | Status | Bukti |
|---|---|---|---|---|---|---|
| CO-01 | 200, `redirect=false`, berisi shipping methods | HTTP 200 | PASS | PASS | PASS | `evidence/F4-actual-run1/CO-01.json` |
| CO-02 | **422** (REQ-CO-01 [asumsi format]) | HTTP 200 | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/CO-02.json` |
| CO-03 | 422 | HTTP 422; "The billing.email field is required."; error: billing.email | PASS | PASS | PASS | `evidence/F4-actual-run1/CO-03.json` |
| CO-04 | 422 | HTTP 422; "The billing.first name field is required."; error: billing.first_name | PASS | PASS | PASS | `evidence/F4-actual-run1/CO-04.json` |
| CO-05 | 422 | HTTP 422; "The billing.address field is required."; error: billing.address | PASS | PASS | PASS | `evidence/F4-actual-run1/CO-05.json` |
| CO-06 | 422 | HTTP 422; "The billing.address field must be an array."; error: billing.address | PASS | PASS | PASS | `evidence/F4-actual-run1/CO-06.json` |
| CO-07 | 422 | HTTP 422; "The billing.country field is required."; error: billing.country | PASS | PASS | PASS | `evidence/F4-actual-run1/CO-07.json` |
| CO-08 | 200 | HTTP 200 | PASS | PASS | PASS | `evidence/F4-actual-run1/CO-08.json` |
| CO-09 | 422 | HTTP 422; "The billing.phone must be valid phone number."; error: billing.phone | PASS | PASS | PASS | `evidence/F4-actual-run1/CO-09.json` |
| CO-10 | 200 | HTTP 200 | PASS | PASS | PASS | `evidence/F4-actual-run1/CO-10.json` |
| CO-11 | 422 | HTTP 422; "The billing.phone must be valid phone number."; error: billing.phone | PASS | PASS | PASS | `evidence/F4-actual-run1/CO-11.json` |
| CO-12 | 422 | HTTP 422; "The billing.phone must be valid phone number."; error: billing.phone | PASS | PASS | PASS | `evidence/F4-actual-run1/CO-12.json` |
| CO-13 | 422 | HTTP 422; "The billing.postcode must be valid postcode."; error: billing.postcode | PASS | PASS | PASS | `evidence/F4-actual-run1/CO-13.json` |
| CO-14 | 200 | HTTP 200 | PASS | PASS | PASS | `evidence/F4-actual-run1/CO-14.json` |
| CO-15 | 422 | HTTP 422; "The billing.postcode must be valid postcode."; error: billing.postcode | PASS | PASS | PASS | `evidence/F4-actual-run1/CO-15.json` |
| CO-16 | 200 | HTTP 200 | PASS | PASS | PASS | `evidence/F4-actual-run1/CO-16.json` |
| CO-17 | 200, tersimpan utuh 255 karakter | HTTP 200; panjang_tersimpan=255 | PASS | PASS | PASS | `evidence/F4-actual-run1/CO-17.json` |
| CO-18 | 422 **atau** tersimpan utuh, tidak terpotong diam-diam (REQ-PR-03 dianalogikan [asumsi]) | HTTP 200; panjang_dikirim=256; panjang_tersimpan=255 | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/CO-18.json` |
| CO-19 | 422 (bukan 500) | HTTP 500; "Undefined array key "billing"" | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/CO-19.json` |
| CO-20 | 422 (sudah ada test bawaan, dipakai sebagai kontrol) | HTTP 422; "The shipping.first name field is required. (and 8 more errors)"; error: shipping.first_name, shipping.last_name, shipping.email, shipping.address, shipping.city, shipping.country, shipping.state, shipping.postcode, shipping.phone | PASS | PASS | PASS | `evidence/F4-actual-run1/CO-20.json` |
| CO-21 | 4xx/redirect (bukan 500) | HTTP 500; "Call to a member function hasGuestCheckoutItems() on null" | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/CO-21.json` |

### 3.4 Checkout - shipping, order, minimum order

| ID | Expected (04-blackbox) | Actual (run 1) | Run 1 | Run 2 | Status | Bukti |
|---|---|---|---|---|---|---|
| MO-01 | Ditolak (`MINIMUM_ORDER_AMOUNT`) | HTTP 200; harga=99.99; order_dibuat=false | PASS | PASS | PASS | `evidence/F4-actual-run1/MO-01.json` |
| MO-02 | Diterima (batas inklusif `>=`, `Cart.php:817`) | HTTP 200; harga=100; order_dibuat=true | PASS | PASS | PASS | `evidence/F4-actual-run1/MO-02.json` |
| MO-03 | Diterima | HTTP 200; harga=100.01; order_dibuat=true | PASS | PASS | PASS | `evidence/F4-actual-run1/MO-03.json` |
| MO-04 | Jumlah pembanding = 100 (subtotal − diskon 0), sehingga diterima. Bila kode mengurangi pajak (`Cart.php:797-798`), pembanding = 90 dan order ditolak. | sub_total=100; tax_total=10; discount=0; getOrderAmount=90 | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/MO-04.json` |
| MO-05 | Minimum **tidak** terpenuhi (REQ-CO-05). Config bertipe `numeric` (`Admin/src/Config/system.php:2359`) sehingga desimal sah, sedangkan `getOrderAmount(): int` (`Cart.php:789`) dan `(int)` di `:817` memotong pecahan. | minimum=100.50; sub_total=100.25; getOrderAmount=100; haveMinimumOrderAmount=true | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/MO-05.json` |
| OR-01 | Order dibuat, cart nonaktif | HTTP pending; order_id=54; cart_aktif=null | PASS | PASS | PASS | `evidence/F4-actual-run1/OR-01.json` |
| OR-02 | Order **tidak** dibuat; status 4xx dengan pesan `specify-payment-method` (REQ-CO-04, REQ-CT-03) | HTTP 500; "Please specify a payment method."; order_bertambah=0 | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/OR-02.json` |
| OR-03 | Order tidak dibuat (`OnepageController.php:212-217`) | HTTP 500; "Your account has been suspended."; order_bertambah=0 | PASS | PASS | PASS | `evidence/F4-actual-run1/OR-03.json` |
| SH-01 | 422 (`OnepageController.php:96-98`) | HTTP 422; "The shipping method field is required."; error: shipping_method | PASS | PASS | PASS | `evidence/F4-actual-run1/SH-01.json` |
| SH-02 | 200, daftar payment method | HTTP 200 | PASS | PASS | PASS | `evidence/F4-actual-run1/SH-02.json` |
| SH-03 | 200 | HTTP 200 | PASS | PASS | PASS | `evidence/F4-actual-run1/SH-03.json` |
| SH-04 | **403** (`:100-108`; `Shipping.php:154-169`) | HTTP 403 | PASS | PASS | PASS | `evidence/F4-actual-run1/SH-04.json` |
| SH-05 | **Dikoreksi:** 200 (flat rate tersedia tanpa alamat, sesuai REQ-CO-03), tetapi order tanpa alamat tetap ditolak (REQ-CO-04). *Expected awal "403 (rate belum ada)" adalah asumsi tentang kode, bukan turunan REQ (lihat `06-hasil-eksekusi.md` §3).* | HTTP 200; status_order_tanpa_alamat=500; pesan_order=Shipping address is missing.; order_bertambah=0 | PASS | PASS | PASS | `evidence/F4-actual-run1/SH-05.json` |
| SH-06 | `shipping_amount` = 10 × qty | base_shipping_amount_per_qty={"1":10,"3":30} | PASS | PASS | PASS | `evidence/F4-actual-run1/SH-06.json` |

### 3.5 Authentication

| ID | Expected (04-blackbox) | Actual (run 1) | Run 1 | Run 2 | Status | Bukti |
|---|---|---|---|---|---|---|
| AU-01 | Akun dibuat, redirect | HTTP 302; location=http://localhost:8000/customer/login; akun_dibuat=true | PASS | PASS | PASS | `evidence/F4-actual-run1/AU-01.json` |
| AU-02 | 422 `password` | HTTP 422; "The password field must be at least 6 characters."; error: password | PASS | PASS | PASS | `evidence/F4-actual-run1/AU-02.json` |
| AU-03 | Akun dibuat | HTTP 302; akun_dibuat=true | PASS | PASS | PASS | `evidence/F4-actual-run1/AU-03.json` |
| AU-04 | 422 | HTTP 422; "The password field confirmation does not match."; error: password | PASS | PASS | PASS | `evidence/F4-actual-run1/AU-04.json` |
| AU-05 | 422 | HTTP 422; "The email field must be a valid email address."; error: email | PASS | PASS | PASS | `evidence/F4-actual-run1/AU-05.json` |
| AU-06 | 422 `unique` | HTTP 422; "The email has already been taken."; error: email | PASS | PASS | PASS | `evidence/F4-actual-run1/AU-06.json` |
| AU-07 | 422 | HTTP 422; "The first name field is required."; error: first_name | PASS | PASS | PASS | `evidence/F4-actual-run1/AU-07.json` |
| AU-08 | Login, redirect home | HTTP 302; location=http://localhost:8000; login=true | PASS | PASS | PASS | `evidence/F4-actual-run1/AU-08.json` |
| AU-09 | Tidak login, flash `invalid-credentials` | HTTP 302; flash_error=Please check your credentials and try again.; login=false | PASS | PASS | PASS | `evidence/F4-actual-run1/AU-09.json` |
| AU-10 | 422 (`LoginRequest.php:17`) | HTTP 422; "The password field must be at least 6 characters."; error: password | PASS | PASS | PASS | `evidence/F4-actual-run1/AU-10.json` |
| AU-11 | Tidak login, warning `not-activated` | HTTP 302; flash_warning=Your activation seeks admin approval; login=false | PASS | PASS | PASS | `evidence/F4-actual-run1/AU-11.json` |
| AU-12 | Tidak login, info `verify-first` | HTTP 302; flash_info=Verify your email account first.; login=false | PASS | PASS | PASS | `evidence/F4-actual-run1/AU-12.json` |
| AU-13 | 200 | HTTP 200 | PASS | PASS | PASS | `evidence/F4-actual-run1/AU-13.json` |
| AU-14 | 403 | HTTP 403; "Please check your credentials and try again." | PASS | PASS | PASS | `evidence/F4-actual-run1/AU-14.json` |
| AU-15 | Setelah N percobaan, permintaan berikutnya ditolak (429) (REQ-AU-03 [asumsi]) | status_web=[302,302,302,302,302,302,302,302,302,302]; status_api=[403,403,403,403,403,403,403,403,403,403] | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/AU-15.json` |
| AU-16 | 403, sama dengan web (REQ-AU-04 [asumsi]) | HTTP 200; channel_customer=nulla; channel_aktif=default; web_login=false; api_status=200; api_login=true | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/AU-16.json` |

### 3.6 Validasi harga (Admin)

| ID | Expected (04-blackbox) | Actual (run 1) | Run 1 | Run 2 | Status | Bukti |
|---|---|---|---|---|---|---|
| PR-01 | Tersimpan 150000 | HTTP 302; tersimpan=150000.0000; errors=null | PASS | PASS | PASS | `evidence/F4-actual-run1/PR-01.json` |
| PR-02 | Diterima (`Decimal` lolos); dicatat untuk konfirmasi kebutuhan | HTTP 302; tersimpan=null; errors=null | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/PR-02.json` |
| PR-03 | 422 | HTTP 422; "The price must be valid."; error: price; price_sebelum=522.8300; price_sesudah=522.8300 | PASS | PASS | PASS | `evidence/F4-actual-run1/PR-03.json` |
| PR-04 | Tersimpan 1.1234 | HTTP 302; tersimpan=1.1234; errors=null | PASS | PASS | PASS | `evidence/F4-actual-run1/PR-04.json` |
| PR-05 | 422 | HTTP 422; "The price must be valid."; error: price; price_sebelum=132.9800; price_sesudah=132.9800 | PASS | PASS | PASS | `evidence/F4-actual-run1/PR-05.json` |
| PR-06 | 422 | HTTP 422; "The price must be valid."; error: price; price_sebelum=367.8800; price_sesudah=367.8800 | PASS | PASS | PASS | `evidence/F4-actual-run1/PR-06.json` |
| PR-07 | 422 | HTTP 422; "The price field is required."; error: price; price_sebelum=761.8700; price_sesudah=761.8700 | PASS | PASS | PASS | `evidence/F4-actual-run1/PR-07.json` |
| PR-08 | Tersimpan persis | HTTP 302; tersimpan=99999999.9999; errors=null | PASS | PASS | PASS | `evidence/F4-actual-run1/PR-08.json` |
| PR-09 | **422**, atau tersimpan persis 100000000 (REQ-PR-03 [asumsi]); tidak boleh menjadi 99999999.9999 | HTTP 302; tersimpan_attribute_values=99999999.9999; tersimpan_product_flat=99999999.9999; errors=null | FAIL | FAIL | **FAIL** | `evidence/F4-actual-run1/PR-09.json` |
| PR-10 | 422 (`lt:price`) | HTTP 422; "The special price field must be less than 100."; error: special_price; price_sebelum=958.2700; price_sesudah=958.2700 | PASS | PASS | PASS | `evidence/F4-actual-run1/PR-10.json` |
| PR-11 | Diterima | HTTP 302; tersimpan=99.9999; errors=null | PASS | PASS | PASS | `evidence/F4-actual-run1/PR-11.json` |
| PR-12 | 422 | HTTP 422; "The special price field must be less than 100."; error: special_price; price_sebelum=290.4700; price_sesudah=290.4700 | PASS | PASS | PASS | `evidence/F4-actual-run1/PR-12.json` |
| PR-13 | 422 (`ProductForm.php:85`) | HTTP 422; "The special price to field must be a date after or equal to special price from."; error: special_price_to; price_sebelum=503.9900; price_sesudah=503.9900 | PASS | PASS | PASS | `evidence/F4-actual-run1/PR-13.json` |
| PR-14 | 422 | HTTP 422; "The cost must be valid."; error: cost; price_sebelum=434.1600; price_sesudah=434.1600 | PASS | PASS | PASS | `evidence/F4-actual-run1/PR-14.json` |

## 4. Analisis singkat temuan

- **Stok tahan oversell.** Ke-12 kasus batas stok lolos, termasuk akumulasi qty, update qty, dan stok terpesan. Cabang penolakan stok yang di baseline 0 eksekusi (`Simple.php:203`, `CartController.php:79`, `AbstractType.php:841`, `Cart.php:367`) kini teruji dan berperilaku benar.
- **Otorisasi cart dan order kuat.** D6 gugur. Akses lintas customer ke order, invoice, reorder, dan cancel menghasilkan 404. Kelemahan yang ditemukan hanya bersifat informasi/referensi:
  - AZ-02/AZ-06: pesan sukses palsu.
  - AZ-13: `parent_address_id` menerima ID alamat orang lain. Data alamat A **tidak** terbaca dan **tidak** berubah, karena `parent_address_id` hanya dipakai untuk preselect di UI (`Shop/src/Resources/views/checkout/onepage/address/customer.blade.php:410-412`).
- **Pola dominan: penanganan error.** 9 dari 22 FAIL adalah respons **500** untuk input/kondisi klien (CT-13, CT-15, CT-17, CT-19, CT-21, CO-19, CO-21, OR-02, dan CT-08 sebagai 400 dengan pesan PHP mentah). Semuanya berasal dari akses null/array tanpa pengecekan, yang merupakan *missing code* dan tidak terlihat di metrik coverage (`05-whitebox.md` §8).
- **Integritas data angka/teks.** PR-09 (harga terpotong), PR-02 (harga 0 → NULL), CO-18 (nama terpotong), MO-04/MO-05 (aritmetika minimum order). PR-09 dan CO-18 bersumber dari kombinasi validasi tanpa batas atas dan `strict=false` (`config/database.php:58`).
- **Keamanan autentikasi.** AU-16 (login API lintas channel) dan AU-15 (tanpa rate limit).

## 5. Coverage sesudah test baru

Perintah: suite baseline (8 file bawaan) + `Praktikum/` dengan `phpunit.coverage.xml` → `evidence/coverage-after/` (245 test: 223 lolos, 22 gagal = FAIL yang sama). Perbandingan lengkap: `evidence/coverage-after/perbandingan.md` (`testing/tools/coverage_compare.php`).

| Direktori | Statement baseline | Statement sesudah | Δ | Method baseline | Method sesudah | Δ |
|---|---|---|---|---|---|---|
| `Shop/src/Http/Controllers/API` | 156/669 (23,32%) | 218/669 (32,59%) | +9,27 pp | 7/54 (12,96%) | 11/54 (20,37%) | +7,41 pp |
| `Shop/src/Http/Requests` | 71/79 (89,87%) | 71/79 (89,87%) | +0,00 pp | 18/21 | 18/21 | +0,00 pp |
| `Checkout/src` | 454/885 (51,30%) | 502/885 (56,72%) | +5,42 pp | 48/81 (59,26%) | 51/81 (62,96%) | +3,70 pp |
| ↳ tanpa `Database/` | 413/619 (66,72%) | 461/619 (74,47%) | +7,75 pp | — | — | — |
| `Product/src/Type` | 458/1625 (28,18%) | 509/1625 (31,32%) | +3,14 pp | 36/158 (22,78%) | 38/158 (24,05%) | +1,27 pp |
| `CartRule/src/Helpers` | 23/238 (9,66%) | 23/238 (9,66%) | +0,00 pp | 3/12 | 3/12 | +0,00 pp |
| **Total** | **1162/3496 (33,24%)** | **1323/3496 (37,84%)** | **+4,61 pp** | 112/326 (34,36%) | 121/326 (37,12%) | +2,76 pp |

Method P0 yang disasar (target `05-whitebox.md` §9: ≥ 80%):

| Method | Baseline | Sesudah | Target tercapai? |
|---|---|---|---|
| `CartController::store` | 53,85% | 92,31% | Ya |
| `CartController::update` | 60,00% | 100% | Ya |
| `CartController::moveToWishlist` | 0% | 100% | Ya |
| `Cart::updateItems` | 80,65% | 93,55% | Ya |
| `Cart::removeItem` | 88,89% | 100% | Ya |
| `Cart::haveMinimumOrderAmount` | 66,67% | 100% | Ya |
| `Simple::haveSufficientQuantity` | 66,67% | 100% | Ya |
| `AbstractType::getQtyRequest` | 66,67% | 100% | Ya |
| `OnepageController::storeShippingMethod` | 72,73% | 100% | Ya |
| `Cart::getOrderAmount` | 0% | 75,00% | Belum (cabang `include_tax_to_amount` dan diskon > 0: WB-13/WB-14 belum ditulis) |
| `OnepageController::validateOrder` | 61,90% | 76,19% | Belum (WB-20..23 belum ditulis) |
| `OnepageController::storeAddress` | 58,62% | 72,41% | Belum (N1 guest tanpa item guest-checkout, N5 tanpa rate: WB-17/18) |
| `OnepageController::storeOrder` | 40,62% | 65,62% | Belum (cabang PayPal di luar lingkup; exception kupon = P1) |
| `Cart::getErrors` | 22,22% | 55,56% | Belum (WB-12 stok turun setelah masuk cart) |
| `Simple::prepareForCart` | 53,85% | 55,77% | Tidak ditargetkan penuh (cabang opsi kustom/file di luar lingkup P0) |
| `CartController::storeCoupon` | 0% | 32,26% | P1 (CR-*) belum dijalankan |
| `CartRule` helper | 9,66% | 9,66% | P1 belum dijalankan |

Catatan:
- `Shop/src/Http/Requests` tidak berubah (89,87%) walaupun 4 defect validasi alamat ditemukan di sana (D7, D8, H23). Ini sejalan dengan analisis di `05-whitebox.md` §8 butir 2: coverage tidak naik karena defeknya berupa aturan yang **tidak ada**.
- Sebagian kasus WB-* dari `05-whitebox.md` §6 belum ditulis. Cabang yang tersisa sudah didaftar di tabel di atas dan menjadi bahan risiko sisa (`10-risiko-sisa.md`).
