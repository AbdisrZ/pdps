# 04 — Desain Black-box: Equivalence Partitioning (EP) dan Boundary Value Analysis (BVA)

Semua nilai batas diambil dari kode Bagisto 2.3.19 (rujukan `file:baris`, path relatif `packages/Webkul/` kecuali disebut lain). Expected result diturunkan dari kebutuhan (REQ) di `03-test-plan.md` §4, **bukan** dari perilaku kode. Kolom **Actual** dan **Status** diisi di FASE 4 (`06-hasil-eksekusi.md`).

Legenda teknik:
- **EP-V** = kelas ekuivalen valid
- **EP-I** = kelas ekuivalen invalid
- **BVA** = nilai batas (di batas, tepat di bawah, tepat di atas)
- **Hx/Dx** = kasus yang menguji hipotesis dari `01-peta-source.md` §I

Konvensi status: `PASS` (sesuai expected), `FAIL` (tidak sesuai, direproduksi ≥ 2×), `BLOCKED` (tidak dapat dieksekusi), `N/R` (belum dijalankan).

---

## 1. Authorization (P0) — REQ-AZ-01/02/03, REQ-CT-04

Prakondisi umum: customer **A** dan **B** sama-sama terdaftar dan aktif; A punya cart aktif berisi item `iA`; A punya order `oA` beserta invoice `invA`; A punya alamat tersimpan `adrA`.

| ID | Teknik | Skenario | Input | Rujukan | Expected | Actual | Status |
|---|---|---|---|---|---|---|---|
| AZ-01 | EP-I | B menghapus item milik A | login B, `DELETE api/checkout/cart` `{cart_item_id: iA}` | `CartController.php:93-107`; `Cart.php:323` | `iA` tetap ada, cart A tidak berubah (REQ-AZ-01) | | N/R |
| AZ-02 | EP-I (H22) | Pesan respons AZ-01 | idem | `CartController.php:103-106` | Respons **tidak** menyatakan `success-remove` bila tidak ada yang dihapus (REQ-CT-04) | | N/R |
| AZ-03 | EP-I | B mengubah qty item A | login B, `PUT api/checkout/cart` `{qty: {iA: 5}}` | `Cart.php:350` | Qty `iA` tidak berubah | | N/R |
| AZ-04 | EP-I | B menghapus terpilih berisi item A | login B, `DELETE api/checkout/cart/selected` `{ids: [iA]}` | `CartController.php:114`; `Cart.php:323` | `iA` tetap ada | | N/R |
| AZ-05 | EP-I | B memindahkan item A ke wishlist | login B, `POST api/checkout/cart/move-to-wishlist` `{ids:[iA], qty:[1]}` | `Cart.php:673` | `iA` tetap ada; tidak ada wishlist baru untuk B | | N/R |
| AZ-06 | EP-I | Guest menghapus item milik A | tanpa login, `DELETE api/checkout/cart` `{cart_item_id: iA}` | `Cart.php:319-325` | `iA` tetap ada | | N/R |
| AZ-07 | EP-V (kontrol) | A melihat order sendiri | login A, `GET customer/account/orders/view/{oA}` | `OrderController.php:50-55` | 200 | | N/R |
| AZ-08 | EP-I | B melihat order A | login B, `GET .../orders/view/{oA}` | `OrderController.php:55` | **404** (REQ-AZ-02) | | N/R |
| AZ-09 | EP-I | B reorder order A | login B, `GET .../orders/reorder/{oA}` | `OrderController.php:67-72` | 404; cart B tidak bertambah | | N/R |
| AZ-10 | EP-I | B membatalkan order A | login B, `POST .../orders/cancel/{oA}` | `OrderController.php:129-134` | 404; status `oA` tidak berubah | | N/R |
| AZ-11 | EP-I | B mencetak invoice A | login B, `GET .../orders/print/Invoice/{invA}` | `OrderController.php:104-108` | 404 | | N/R |
| AZ-12 | EP-I | Guest membuka daftar order | tanpa login, `GET customer/account/orders` | middleware `customer` (`customer-routes.php:69`) | Redirect ke login | | N/R |
| AZ-13 | EP-I (H12) | B merujuk alamat milik A saat checkout | login B, `POST api/checkout/onepage/addresses` `billing: {address_type: "customer", id: adrA, ...data valid, use_for_shipping: 1}` | `Cart.php:456`, `:523` | Ditolak (4xx) **atau** `parent_address_id` tidak berisi `adrA` (REQ-AZ-03 [asumsi]) | | N/R |
| AZ-14 | EP-V (kontrol H12) | B merujuk alamatnya sendiri | idem dengan `id = adrB` | `Cart.php:456` | 200, `parent_address_id = adrB` | | N/R |

## 2. Inventory/stok (P0) — REQ-ST-01/02

Prakondisi: produk simple **P** aktif dengan `manage_stock = 1`, stok sumber inventory 1 = **S = 5** (index qty = 5), back-order nonaktif (default), cart kosong. Batas stok berasal dari `Product/src/Type/Simple.php:158` (`$qty <= totalQuantity()`), sumber stok `AbstractType.php:517-524` dan `Product/src/Helpers/Indexers/Inventory.php:180-199` (stok sumber − qty terpesan).

| ID | Teknik | Kelas/batas | Input | Expected | Actual | Status |
|---|---|---|---|---|---|---|
| ST-01 | EP-V | qty di tengah rentang | `POST cart {product_id: P, quantity: 2}` | 200; item qty = 2 | | N/R |
| ST-02 | BVA | qty = S − 1 = 4 | `quantity: 4` | 200 | | N/R |
| ST-03 | BVA | qty = S = 5 (batas) | `quantity: 5` | 200; item qty = 5 | | N/R |
| ST-04 | BVA | qty = S + 1 = 6 | `quantity: 6` | **400**, pesan `inventory-warning`; tidak ada item tersimpan | | N/R |
| ST-05 | BVA (kumulatif) | sudah ada 5 di cart, tambah 1 | 2× `POST` (5 lalu 1) | `POST` kedua 400; qty tetap 5 (`AbstractType.php:838-845`) | | N/R |
| ST-06 | BVA (kumulatif) | sudah ada 4, tambah 1 | 2× `POST` (4 lalu 1) | 200; qty = 5 | | N/R |
| ST-07 | BVA (update) | `PUT` qty → S | `PUT cart {qty: {i: 5}}` | Qty = 5, pesan sukses | | N/R |
| ST-08 | BVA (update) | `PUT` qty → S + 1 | `PUT cart {qty: {i: 6}}` | Ditolak dengan pesan `inventory-warning`; **qty tetap lama** (`Cart.php:366-368`) | | N/R |
| ST-09 | EP-I | stok 0 | S = 0, `quantity: 1` | 400 | | N/R |
| ST-10 | EP-V | back-order aktif | config `catalog.inventory.stock_options.back_orders = 1`, `quantity: 6` | 200 (REQ-ST-02; `Simple.php:158`) | | N/R |
| ST-11 | EP-V | `manage_stock = 0` | `quantity: 50` | 200 (`Simple.php:154-156`) | | N/R |
| ST-12 | BVA (stok terpesan) | sumber 5, terpesan 3 → tersedia 2 | `quantity: 2` lalu (cart baru) `quantity: 3` | 2 → 200; 3 → 400 | | N/R |

## 3. Cart — validasi input dan konsistensi respons (P0) — REQ-CT-01..04

Batas dari `Shop/src/Http/Controllers/API/CartController.php:51-55`: `product_id` = `required|integer|exists:products,id`; `is_buy_now` = `integer|in:0,1`; `quantity` = `integer|min:1` (opsional).

| ID | Teknik | Kelas/batas | Input | Expected | Actual | Status |
|---|---|---|---|---|---|---|
| CT-01 | EP-I | `product_id` kosong | `{quantity: 1}` | 422 error `product_id` | | N/R |
| CT-02 | EP-I | `product_id` tidak ada | `{product_id: 999999, quantity: 1}` | 422 | | N/R |
| CT-03 | EP-I | `product_id` bukan integer | `{product_id: "abc"}` | 422 | | N/R |
| CT-04 | BVA | `quantity = 0` (di bawah min 1) | `quantity: 0` | 422 | | N/R |
| CT-05 | BVA | `quantity = 1` (batas min) | `quantity: 1` | 200 | | N/R |
| CT-06 | EP-I | `quantity` negatif | `quantity: -1` | 422 | | N/R |
| CT-07 | EP-I | `quantity` desimal | `quantity: 1.5` | 422 | | N/R |
| CT-08 | EP-V (H14) | `quantity` tidak dikirim | `{product_id: P}` | 200, qty default 1 (REQ-CT-01; `AbstractType.php:829`) | | N/R |
| CT-09 | EP-I | produk nonaktif | `status = 0` | 400 pesan `inactive-add` (`CartController.php:60-61`) | | N/R |
| CT-10 | EP-I | `is_buy_now` di luar {0,1} | `is_buy_now: 2` | 422 | | N/R |
| CT-11 | BVA (H10, D3) | `PUT` qty = 0 | `{qty: {i: 0}}` | 4xx; **item tetap ada** (REQ-CT-02/03) | | N/R |
| CT-12 | EP-I (H10, D3) | `PUT` qty negatif | `{qty: {i: -1}}` | 4xx; item tetap ada | | N/R |
| CT-13 | EP-I (H11) | `PUT` qty non-angka | `{qty: {i: "abc"}}` | 4xx; qty tidak berubah | | N/R |
| CT-14 | EP-I (D3) | `PUT` tanpa key `qty` | `{}` | 4xx (bukan 200) | | N/R |
| CT-15 | EP-I (D2) | `DELETE selected` tanpa `ids` | `{}` | 422 (bukan 500) | | N/R |
| CT-16 | EP-I (kontrol) | `DELETE` tanpa `cart_item_id` | `{}` | 422 (`CartController.php:95-97`) | | N/R |
| CT-17 | EP-I (D1) | `estimate-shipping-methods` tanpa cart | guest baru, data alamat valid | 4xx (bukan 500) | | N/R |
| CT-18 | EP-I | `estimate-shipping-methods` tanpa `country` | `{state, postcode}` | 422 (`:171-176`) | | N/R |
| CT-19 | EP-I (H21) | `move-to-wishlist` oleh guest | guest dengan cart berisi item | 401/403 atau ditolak; tidak ada wishlist ber-`customer_id` null | | N/R |
| CT-20 | EP-I (D5) | Pasang kupon tanpa cart | guest baru, `POST cart/coupon {code: "X"}` | 4xx (bukan 500) | | N/R |
| CT-21 | EP-I (D5) | Hapus kupon tanpa cart | guest baru, `DELETE cart/coupon` | 4xx/200 tanpa error server (bukan 500) | | N/R |

## 4. Checkout — alamat (P0) — REQ-CO-01/02

Prakondisi: cart berisi 1 produk simple stockable (guest checkout aktif). Endpoint `POST api/checkout/onepage/addresses`. Data valid dasar `billing`: `first_name`, `last_name`, `email`, `address: ["Jl. Uji 1"]`, `city`, `country: "ID"`, `state: "Jawa Barat"`, `postcode: "40115"`, `phone: "081234567890"`, `use_for_shipping: 1`. Aturan: `Shop/src/Http/Requests/CartAddressRequest.php:50-58`; syarat country/state/postcode aktif secara default (`Admin/src/Config/system.php:1290`, `:1296`, `:1302`).

| ID | Teknik | Field | Nilai | Rujukan | Expected | Actual | Status |
|---|---|---|---|---|---|---|---|
| CO-01 | EP-V | semua valid | data dasar | — | 200, `redirect=false`, berisi shipping methods | | N/R |
| CO-02 | EP-I (D7) | email | `"bukan-email"` | `:52` | **422** (REQ-CO-01 [asumsi format]) | | N/R |
| CO-03 | EP-I | email | kosong | `:52` | 422 | | N/R |
| CO-04 | EP-I | first_name | kosong | `:50` | 422 | | N/R |
| CO-05 | EP-I | address | `[]` (array kosong) | `:53` `min:1` | 422 | | N/R |
| CO-06 | EP-I | address | `"Jl. Uji"` (string, bukan array) | `:53` `array` | 422 | | N/R |
| CO-07 | EP-I | country | kosong | `:55` | 422 | | N/R |
| CO-08 | BVA | phone | `"1"` (1 digit, batas min regex) | `PhoneNumber.php:23` | 200 | | N/R |
| CO-09 | BVA | phone | `"+"` (0 digit) | idem | 422 | | N/R |
| CO-10 | EP-V | phone | `"+6281234567890"` | idem | 200 | | N/R |
| CO-11 | EP-I | phone | `"0812-3456"` | idem | 422 | | N/R |
| CO-12 | EP-I | phone | `"0812 3456"` | idem | 422 | | N/R |
| CO-13 | BVA | postcode | `"1"` (1 karakter, di bawah min 2) | `PostCode.php:15` | 422 | | N/R |
| CO-14 | BVA | postcode | `"12"` (2 karakter, batas) | idem | 200 | | N/R |
| CO-15 | EP-I | postcode | `"1-"` (diakhiri `-`) | idem | 422 | | N/R |
| CO-16 | EP-V | postcode | `"12 34"` (spasi di tengah) | idem | 200 | | N/R |
| CO-17 | BVA (H23) | first_name | 255 karakter | kolom `addresses.first_name varchar(255)` | 200, tersimpan utuh 255 karakter | | N/R |
| CO-18 | BVA (H23) | first_name | 256 karakter | idem; `config/database.php:58` `strict=false` | 422 **atau** tersimpan utuh, tidak terpotong diam-diam (REQ-PR-03 dianalogikan [asumsi]) | | N/R |
| CO-19 | EP-I (D8) | struktur | tanpa key `billing`, hanya `shipping` valid | `CartAddressRequest.php:32-38`; `Cart.php:424` | 422 (bukan 500) | | N/R |
| CO-20 | EP-I | struktur | `use_for_shipping: 0`, tanpa `shipping` | `:36-38` | 422 (sudah ada test bawaan, dipakai sebagai kontrol) | | N/R |
| CO-21 | EP-I (H9) | prasyarat | guest **tanpa cart** mengirim alamat valid | `OnepageController.php:46-48` | 4xx/redirect (bukan 500) | | N/R |

## 5. Checkout — shipping, order, minimum order (P0) — REQ-CO-03/04/05

Default: flat rate = 10 per unit (`Shipping/src/Config/carriers.php`; rumus `Shipping/src/Carriers/FlatRate.php:55-59`); free = 0.

| ID | Teknik | Kelas/batas | Input | Expected | Actual | Status |
|---|---|---|---|---|---|---|
| SH-01 | EP-I | `shipping_method` kosong | `{}` | 422 (`OnepageController.php:96-98`) | | N/R |
| SH-02 | EP-V | `flatrate_flatrate` | setelah alamat valid | 200, daftar payment method | | N/R |
| SH-03 | EP-V | `free_free` | idem | 200 | | N/R |
| SH-04 | EP-I | kode tidak dikenal | `"kurir_palsu"` | **403** (`:100-108`; `Shipping.php:154-169`) | | N/R |
| SH-05 | EP-I | sebelum alamat diisi | cart tanpa alamat, `flatrate_flatrate` | 403 (rate belum ada) | | N/R |
| SH-06 | BVA | ongkir per unit | qty 1 → 10; qty 3 → 30 | `shipping_amount` = 10 × qty | | N/R |
| OR-01 | EP-V | checkout lengkap | alamat + `flatrate_flatrate` + `cashondelivery` | Order dibuat, cart nonaktif | | N/R |
| OR-02 | EP-I (H24) | tanpa payment | alamat + shipping, tanpa payment | Order **tidak** dibuat; status 4xx dengan pesan `specify-payment-method` (REQ-CO-04, REQ-CT-03) | | N/R |
| OR-03 | EP-I | customer `is_suspended = 1` | checkout lengkap | Order tidak dibuat (`OnepageController.php:212-217`) | | N/R |
| MO-01 | BVA | minimum order: subtotal = min − 0,01 | `enable=1`, `minimum_order_amount=100`, subtotal 99,99 | Ditolak (`MINIMUM_ORDER_AMOUNT`) | | N/R |
| MO-02 | BVA | subtotal = min | subtotal 100,00 | Diterima (batas inklusif `>=`, `Cart.php:817`) | | N/R |
| MO-03 | BVA | subtotal = min + 0,01 | 100,01 | Diterima | | N/R |
| MO-04 | EP (H13) | opsi `include_discount_amount=1`, pajak > 0, tanpa diskon | subtotal 100, pajak 10, min 100 | Jumlah pembanding = 100 (subtotal − diskon 0), sehingga diterima. Bila kode mengurangi pajak (`Cart.php:797-798`), pembanding = 90 dan order ditolak. | | N/R |

## 6. Authentication customer (P0) — REQ-AU-01..04

Aturan: `Shop/src/Http/Requests/Customer/RegistrationRequest.php:28-31`; `LoginRequest.php:16-17`.

| ID | Teknik | Kelas/batas | Input | Expected | Actual | Status |
|---|---|---|---|---|---|---|
| AU-01 | EP-V | registrasi valid | nama, email baru, password 6 + konfirmasi | Akun dibuat, redirect | | N/R |
| AU-02 | BVA | password 5 karakter | `"12345"` | 422 `password` | | N/R |
| AU-03 | BVA | password 6 karakter (batas) | `"123456"` | Akun dibuat | | N/R |
| AU-04 | EP-I | konfirmasi berbeda | `password_confirmation` ≠ | 422 | | N/R |
| AU-05 | EP-I | email tidak valid | `"abc@"` | 422 | | N/R |
| AU-06 | EP-I | email sudah terdaftar (channel sama) | email A | 422 `unique` | | N/R |
| AU-07 | EP-I | first_name kosong | — | 422 | | N/R |
| AU-08 | EP-V | login valid | email + password benar | Login, redirect home | | N/R |
| AU-09 | EP-I | password salah | — | Tidak login, flash `invalid-credentials` | | N/R |
| AU-10 | BVA | password 5 karakter | — | 422 (`LoginRequest.php:17`) | | N/R |
| AU-11 | EP-I | akun `status = 0` | kredensial benar | Tidak login, warning `not-activated` | | N/R |
| AU-12 | EP-I | akun `is_verified = 0` | kredensial benar | Tidak login, info `verify-first` | | N/R |
| AU-13 | EP-V | API login valid | `POST api/customer/login` | 200 | | N/R |
| AU-14 | EP-I | API login salah | — | 403 | | N/R |
| AU-15 | BVA (H15) | percobaan login salah berulang | 10× password salah (web dan API) | Setelah N percobaan, permintaan berikutnya ditolak (429) (REQ-AU-03 [asumsi]) | | N/R |
| AU-16 | EP-I (H16) | API login customer channel lain | customer `channel_id` = channel ke-2 | 403, sama dengan web (REQ-AU-04 [asumsi]) | | N/R |

## 7. Validasi harga produk — Admin (P0) — REQ-PR-01..03

Endpoint `PUT admin/catalog/products/edit/{id}` (login admin). Aturan: `Admin/src/Http/Requests/ProductForm.php:86`, `:116-137`; `Core/src/Rules/Decimal.php:15` regex `/^\d*(\.\d{1,4})?$/`. Kapasitas kolom `decimal(12,4)` → maksimum 99.999.999,9999 (`product_attribute_values.float_value`); `config/database.php:58` `strict=false`.

| ID | Teknik | Kelas/batas | `price` / field | Expected | Actual | Status |
|---|---|---|---|---|---|---|
| PR-01 | EP-V | bilangan bulat | `"150000"` | Tersimpan 150000 | | N/R |
| PR-02 | BVA (H17) | batas bawah 0 | `"0"` | Diterima (`Decimal` lolos); dicatat untuk konfirmasi kebutuhan | | N/R |
| PR-03 | EP-I | negatif | `"-1"` | 422 | | N/R |
| PR-04 | BVA | 4 desimal (batas) | `"1.1234"` | Tersimpan 1.1234 | | N/R |
| PR-05 | BVA | 5 desimal | `"1.12345"` | 422 | | N/R |
| PR-06 | EP-I | non-angka | `"abc"` | 422 | | N/R |
| PR-07 | EP-I | kosong (required) | `""` | 422 | | N/R |
| PR-08 | BVA (H18) | tepat kapasitas | `"99999999.9999"` | Tersimpan persis | | N/R |
| PR-09 | BVA (H18) | kapasitas + 0,0001 | `"100000000"` | **422**, atau tersimpan persis 100000000 (REQ-PR-03 [asumsi]); tidak boleh menjadi 99999999.9999 | | N/R |
| PR-10 | BVA | `special_price` = `price` | price 100, special 100 | 422 (`lt:price`) | | N/R |
| PR-11 | BVA | `special_price` = `price` − 0,0001 | special 99.9999 | Diterima | | N/R |
| PR-12 | EP-I | `special_price` > `price` | special 101 | 422 | | N/R |
| PR-13 | EP-I | `special_price_to` < `special_price_from` | to = from − 1 hari | 422 (`ProductForm.php:85`) | | N/R |
| PR-14 | EP-I | `cost` negatif | `"-5"` | 422 | | N/R |

## 8. Kupon (P1, dieksekusi setelah P0) — REQ-CR-01/02

| ID | Teknik | Kelas/batas | Expected | Status |
|---|---|---|---|---|
| CR-01 | EP-I | kode tidak ada | 422 `coupon.invalid` (`CartController.php:222-227`) | N/R |
| CR-02 | EP-I (D4) | kupon ada, rule `status = 0` | 422 dengan pesan yang menjelaskan alasan dan ter-translate (REQ-CR-02 [asumsi]); bukan teks hardcode "Coupon not found." (`:249`) | N/R |
| CR-03 | EP-V | kupon valid | 200 `success-apply` | N/R |
| CR-04 | EP-I | kupon dipasang 2× | 422 `already-applied` | N/R |
| CR-05 | BVA | `usage_limit = 1`, `times_used = 0` / `1` | 0 → berlaku; 1 → tidak berlaku (`CartRule.php:142-147`) | N/R |
| CR-06 | BVA (H19) | `ends_till` = sekarang − 1 menit | Tidak berlaku (REQ-CR-01) | N/R |
| CR-07 | BVA (H19) | `starts_from` = sekarang + 1 menit | Belum berlaku | N/R |
| CR-08 | EP (H20) | `coupon.expired_at` lewat, `ends_till` null | Tidak berlaku | N/R |

## 9. Ringkasan jumlah kasus
Dihitung otomatis dari kolom *Teknik* pada tabel di atas.

| Modul | EP-V | EP-I | BVA | EP netral* | Total | Kaitan hipotesis |
|---|---|---|---|---|---|---|
| Authorization (AZ) | 2 | 12 | 0 | 0 | 14 | H12, H22 |
| Stok (ST) | 3 | 1 | 8 | 0 | 12 | — |
| Cart (CT) | 1 | 17 | 3 | 0 | 21 | D1, D2, D3, D5, H10, H11, H14, H21 |
| Checkout alamat (CO) | 3 | 12 | 6 | 0 | 21 | D7, D8, H9, H23 |
| Shipping/order/min (SH, OR, MO) | 3 | 5 | 4 | 1 | 13 | H13, H24 |
| Auth (AU) | 3 | 9 | 4 | 0 | 16 | H15, H16 |
| Harga (PR) | 1 | 6 | 7 | 0 | 14 | H17, H18 |
| Kupon P1 (CR) | 1 | 3 | 3 | 1 | 8 | D4, H19, H20 |
| **Total** | **17** | **65** | **35** | **2** | **119** | |

*EP netral = kasus partisi yang hasilnya bergantung pada interpretasi kebutuhan (MO-04, CR-08).

## 10. Hipotesis tambahan yang muncul saat desain (FASE 3)
| ID | Hipotesis | Rujukan |
|---|---|---|
| H22 | `DELETE api/checkout/cart` mengembalikan 200 + pesan `success-remove` walaupun item bukan milik pengguna dan tidak dihapus. Test bawaan `CartTest.php:858+` hanya memeriksa `assertOk()` dan bahwa data tetap ada. | `CartController.php:99-106` |
| H23 | Field teks alamat tanpa aturan `max`, kolom `varchar(255)`, dan koneksi `strict=false`, sehingga teks > 255 karakter terpotong diam-diam. | `CartAddressRequest.php:50-58`; tabel `addresses`; `config/database.php:58` |
| H24 | `storeOrder` membalas **500** untuk kesalahan validasi bisnis (payment/alamat belum diisi), padahal itu kesalahan klien. | `OnepageController.php:159-165` |
