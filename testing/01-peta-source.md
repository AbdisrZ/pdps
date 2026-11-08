# 01 — Peta Source Modul P0/P1 (Bagisto 2.3.19)

Semua rujukan `file:baris` mengacu pada tag `v2.3.19`. Path relatif terhadap `packages/Webkul/` kecuali disebut lain. Dokumen ini **hasil membaca kode, belum hasil eksekusi.** Semua butir "D"/"H" berstatus **HIPOTESIS** sampai direproduksi di FASE 4.

## A. Route API yang diuji (`Shop/src/Routes/api.php`)

| Route | Method | Controller@aksi | Baris | Middleware auth |
|---|---|---|---|---|
| `api/checkout/cart` | GET/POST/PUT/DELETE | CartController@index/store/update/destroy | 61–67 | tidak ada (guest boleh) |
| `api/checkout/cart/selected` | DELETE | @destroySelected | 69 | tidak ada |
| `api/checkout/cart/move-to-wishlist` | POST | @moveToWishlist | 71 | **tidak ada** (lihat H21) |
| `api/checkout/cart/coupon` | POST/DELETE | @storeCoupon/@destroyCoupon | 73, 77 | tidak ada |
| `api/checkout/cart/estimate-shipping-methods` | POST | @estimateShippingMethods | 75 | tidak ada |
| `api/checkout/onepage/{summary,addresses,shipping-methods,payment-methods,orders}` | GET/POST | OnepageController | 83–91 | tidak ada |
| `api/customer/login` | POST | API\CustomerController@login | 98 | tidak ada, **tanpa throttle** |
| `api/customer/addresses`, `wishlist` | — | — | 101–119 | `customer` |

## B. Cart — `Shop/src/Http/Controllers/API/CartController.php` dan `Checkout/src/Cart.php`

| Fungsi | Baris | Aturan validasi / cabang |
|---|---|---|
| `CartController::store` | 49–88 | Validasi `product_id` = `required\|integer\|exists:products,id`; `is_buy_now` = `integer\|in:0,1`; `quantity` = `integer\|min:1` (**opsional**) (51–55). Cabang: produk `status=0` → Exception → **400** (60–61); `is_buy_now` → `deActivateCart` + redirect (66–70); `InsufficientProductInventoryException` → **400** (78–81); Exception lain → **400** + `redirect_uri` (82–86). |
| `CartController::destroy` | 93–107 | `cart_item_id` = `required\|exists:cart_items,id` (95–97). `exists` mengecek seluruh tabel, bukan milik cart sendiri; kepemilikan dicek di `Cart::removeItem`. |
| `CartController::destroySelected` | 112–124 | **Tidak ada `validate`.** `foreach (request()->input('ids'))` (114). |
| `CartController::moveToWishlist` | 129–143 | Tidak ada validasi. `request()->input('qty')[$index]` (132). |
| `CartController::update` | 148–164 | Tidak ada validasi. Semua Exception → `new JsonResource([...message])`, artinya **HTTP 200** (159–163). |
| `CartController::estimateShippingMethods` | 169–207 | `country`, `state`, `postcode` = `required`; `shipping_method` = `sometimes\|required` (171–176). `$cart = Cart::getCart()` lalu `$cart->id` **tanpa cek null** (178–184). |
| `CartController::storeCoupon` | 212–258 | `code` = `required` (214–216). `if (strlen($code))` (219), yang praktis **selalu true** setelah `required` (`TrimStrings` membuat spasi menjadi string kosong, lalu `required` menolaknya: `vendor/laravel/framework/src/Illuminate/Validation/Concerns/ValidatesAttributes.php:2030`. Catatan: `ConvertEmptyStringsToNull` dihapus Bagisto di `bootstrap/app.php:34`). Karena itu cabang else (tidak ada `return`) **tidak terjangkau**. Cabang lain: kupon tidak ada → 422 `coupon.invalid` (222–227); rule aktif dan kupon sudah terpasang → 422 `already-applied` (229–235); berhasil (237–244); jatuh ke **422 dengan teks hardcode `trans('Coupon not found.')`** (247–250); Exception → 500 (252–257). |
| `CartController::destroyCoupon` | 263–271 | `Cart::removeCouponCode()` tanpa cek cart. |
| `Cart::addProduct` | 256–312 | Membuat cart bila belum ada (260–262). `prepareForCart` mengembalikan string → hapus cart kosong dan lempar Exception (268–275). |
| `Cart::removeItem` | 317–336 | `false` bila tidak ada cart (319–321). **Cek kepemilikan:** `false` bila item tidak ada di `$this->cart->items` (323–325). |
| `Cart::updateItems` | 341–392 | `foreach ($data['qty'])` (343). Item tidak ada → skip (346–348). **Cek kepemilikan:** `cart_id !== $this->cart->id` → skip (350–352). Produk nonaktif → Exception (354–356). `qty <= 0` → **hapus item lalu lempar Exception `illegal`** (358–362). Stok kurang → Exception `inventory-warning` (366–368). |
| `Cart::setCouponCode` | 618–625 | `$this->cart->coupon_code = ...` **tanpa cek null**. |
| `Cart::moveToWishlist` | 671–744 | **Cek kepemilikan:** `$this->cart->items()->find($itemId)` (673), tetapi `$this->cart` tidak dicek null. Wishlist dibuat dengan `customer_id = $this->cart->customer_id` (720), yang bernilai null untuk guest. |
| `Cart::getErrors` | 757–784 | `CART_NOT_FOUND` (759–764), `INSUFFICIENT_QUANTITY` (766–771), `MINIMUM_ORDER_AMOUNT` (773–781). |
| `Cart::getOrderAmount` | 789–806 | Bila `include_discount_amount` aktif: **`$minimumOrderAmount -= $this->cart->tax_total`** (797–798), lalu dikurangi diskon (800–802). |
| `Cart::haveMinimumOrderAmount` | 811–818 | `getOrderAmount() >= min` (817). Batasnya **inklusif** (`>=`). |
| `Cart::validateItems` | 948–1006 | Item nonaktif → dihapus + flash info (965–970). Harga dihitung ulang bila berubah (986–999). |

## C. Inventory/stok — `Product/src/Type/*`

| Fungsi | Baris | Aturan |
|---|---|---|
| `Simple::haveSufficientQuantity` | 152–159 | `manage_stock = 0` → true (154–156). Selain itu `qty <= totalQuantity()`, dan bila gagal hasilnya = config `catalog.inventory.stock_options.back_orders` (158). **Batas BVA: qty = stok → lolos; qty = stok+1 → gagal (bila back-order mati).** |
| `Simple::prepareForCart` | 189–204 | Opsi kustom wajib kosong → string error (191–196). `quantity = handleQuantity((int) $data['quantity'])` (198), lalu ditambah qty yang sudah ada di cart lewat `getQtyRequest` (200). Stok kurang → `InsufficientProductInventoryException` (202–204). |
| `AbstractType::handleQuantity` | 827–830 | `$quantity ?: 1`, sehingga 0 menjadi 1. Namun `$data['quantity']` dibaca dengan **index langsung** (Simple:198). |
| `AbstractType::getQtyRequest` | 838–845 | Qty dijumlahkan dengan item sejenis yang sudah ada di cart (BVA: total kumulatif vs stok). |
| `AbstractType::totalQuantity` / `getInventoryIndex` | 517–524 / 709–717 | Sumber stok = `product_inventory_indices.qty` untuk channel saat ini; 0 bila index tidak ada. |
| `Virtual::haveSufficientQuantity` / `prepareForCart` | 175–… / 212–226 | Pola sama dengan Simple. |
| `Configurable::prepareForCart` / `isItemHaveQuantity` / `haveSufficientQuantity` | 384–397 / 305–308 / 568–… | Stok dicek di **child** (396). Parent dianggap cukup bila salah satu varian cukup (571). |
| `Bundle::prepareForCart` / `haveSufficientQuantity` | 229–262 / 535–… | `bundle_options` kosong → string error (233–241). Cek bundle (243) lalu cek tiap produk opsi `qty * bundleQuantity` (261). |

## D. Checkout — `OnepageController.php` dan `Shop/src/Http/Requests/CartAddressRequest.php`

| Fungsi | Baris | Aturan / cabang |
|---|---|---|
| `CartAddressRequest::rules` | 30–41 | Aturan `billing` hanya dipasang **bila key `billing` ada** (32–34). Aturan `shipping` dipasang bila `billing.use_for_shipping` falsy (36–38). |
| `CartAddressRequest::mergeAddressRules` | 46–66 | `first_name`, `last_name`, `city` = `required` (50–51, 54). **`email` = `required` saja, tanpa aturan `email`** (52). `address` = `required\|array\|min:1` (53). `country`/`state` required tergantung config (55–56). `postcode` = `PostCode` (+ `required` bila config aktif) (57). `phone` = `required` + `PhoneNumber` (58). `vat_id` hanya untuk billing (61–65). **Tidak ada `max` panjang pada field mana pun.** |
| `Core/src/Rules/PhoneNumber.php` | 23 | Regex `/^\+?\d+$/`: "+" opsional diikuti ≥1 digit, tanpa batas panjang. Batas: `"1"` lolos, `"+"` gagal, `"08-12"` gagal, `"0812 3"` gagal. |
| `Core/src/Rules/PostCode.php` | 15 | Regex `/^[a-zA-Z0-9][a-zA-Z0-9\s-]*[a-zA-Z0-9]$/`: **minimal 2 karakter**, awal/akhir alfanumerik, tengah boleh spasi/`-`. Batas: `"1"` gagal, `"12"` lolos, `"1-"` gagal, `"12 34"` lolos. |
| `Core/src/Core.php` `isCountryRequired` / `isStateRequired` / `isPostCodeRequired` | 756–779 | Dibaca dari config `customer.address.requirements.{country,state,postcode}`. |
| `OnepageController::storeAddress` | 42–87 | Guest dan cart tanpa item guest-checkout → redirect login (46–54). `Cart::getCart()->hasGuestCheckoutItems()` **tanpa cek null** (48). `Cart::hasError()` → redirect (56–61). `saveAddresses` (63). Item stockable dan tidak ada rate → redirect (69–75). |
| `Cart::saveAddresses` | 422–431 | **`$params['billing']` diakses langsung** (424); shipping memakai `?? []` (426). |
| `Cart::updateOrCreateBillingAddress` | 436–473 | `parent_address_id = $params['id']` bila `address_type == 'customer'` (456), **tanpa cek pemilik alamat**. `implode(PHP_EOL, $params['address'])` (459). |
| `Cart::updateOrCreateShippingAddress` | 478–542 | Tidak ada item stockable → null (483–485). Tidak ada billing → `BillingAddressNotFoundException` (487–489). `use_for_shipping` → salin billing (505–513). Params kosong → null (515–517). `parent_address_id = $params['id']` (523), tanpa cek pemilik. |
| `Cart::saveShippingMethod` | 559–573 | Tidak ada cart → false (561–563). Kode tidak ada di `Shipping::collectRates()` → false (565–567; `Shipping/src/Shipping.php:154-169`). |
| `OnepageController::storeShippingMethod` | 94–113 | `shipping_method` = `required` (96–98). Error / gagal simpan → **403** (100–108). |
| `OnepageController::storePaymentMethod` | 120–143 | `payment` = `required` (122–124). Gagal → 403 (126–134). |
| `OnepageController::storeOrder` | 148–198 | Ada error → redirect (150–155). `validateOrder` gagal → **500** + pesan (159–165). Redirect payment (169–174). `CouponUsageLimitExceededException` (180–188). |
| `OnepageController::validateOrder` | 206–252 | Customer suspended (212–217) / nonaktif (219–224); minimum order (226–228); shipping address wajib untuk item stockable (230–232); billing wajib (234–236); shipping rate wajib (238–243); payment wajib (245–247); `paypal_smart_button` ditolak (249–251). |

## E. Authorization (kepemilikan)

| Objek | Lokasi | Mekanisme |
|---|---|---|
| Cart item (hapus) | `Checkout/src/Cart.php:323` | Item harus ada di cart aktif milik sesi/customer. |
| Cart item (update qty) | `Checkout/src/Cart.php:350` | `cart_id` harus sama. |
| Cart item (ke wishlist) | `Checkout/src/Cart.php:673` | `$this->cart->items()->find()`. |
| Order view / reorder | `Shop/src/Http/Controllers/Customer/Account/OrderController.php:50-55`, `67-72` | `findOneWhere(['customer_id' => auth id, 'id' => $id])` → 404. |
| Cetak invoice | `OrderController.php:104-108` | `whereHas('order', customer_id = auth id)` → `firstOrFail` (404). |
| Cancel order | `OrderController.php:126-134` | `$customer->orders()->find($id)` → 404; aturan status di `Sales/src/Repositories/OrderRepository.php:145` (`canCancel`). |
| Alamat customer di checkout | `Checkout/src/Cart.php:456`, `523` | **Tidak ada cek pemilik** untuk `id` (lihat H12). |
| Test bawaan terkait | `Shop/tests/Feature/Checkout/CartTest.php:858`, `949`, `1026`, `1110` | Sudah menguji "tidak boleh menghapus cart item milik user lain" untuk 4 kombinasi guest/customer. |

## F. Authentication — Customer

| Lokasi | Aturan |
|---|---|
| `Shop/src/Http/Requests/Customer/RegistrationRequest.php:28-31` | `first_name`, `last_name` = `string\|required`; `email` = `email\|required\|unique:customers,email,NULL,id,channel_id,<channel>`; `password` = `confirmed\|min:6\|required` (**batas: 5 karakter gagal, 6 lolos; tidak ada max**). Ditambah captcha bila aktif (34). |
| `Shop/src/Http/Requests/Customer/LoginRequest.php:16-17` | `email` = `required\|email`; `password` = `required\|min:6`. |
| `Shop/src/Http/Controllers/Customer/SessionController.php:34-…` | Kredensial ditambah `channel_id` (38). Urutan cek: gagal attempt → flash error; `status=0` → logout + warning; `is_verified=0` → logout + info. |
| `Shop/src/Http/Controllers/API/CustomerController.php:18-44` | Urutan cek sama, respons **403**, tetapi `attempt()` **tanpa `channel_id`** (20). |
| `Shop/src/Routes/customer-routes.php` | `throttle:6,1` hanya pada resend verifikasi (60–62). **Login dan registrasi tanpa throttle.** |

## G. Validasi harga produk (Admin)

| Lokasi | Aturan |
|---|---|
| `Admin/src/Http/Requests/ProductForm.php:86` | `special_price` = `nullable`, `Decimal`, **`lt:price`** (batas: special = price → gagal; price − 0.0001 → lolos). |
| `ProductForm.php:84-85` | `special_price_to` = `after_or_equal:special_price_from`. |
| `ProductForm.php:116-137` | Atribut `price` (is_required=1, validation=decimal menurut tabel `attributes`) → `required` + `Decimal`. `cost` → `nullable` + `Decimal`. |
| `Core/src/Rules/Decimal.php:15` | Regex `/^\d*(\.\d{1,4})?$/`: **tidak menerima negatif**, maksimal 4 desimal, **tanpa batas atas**, `"0"` lolos, `".5"` lolos, `"1."` gagal, `"1.12345"` gagal. |
| Skema DB | `product_attribute_values.float_value`, `product_flat.price`, `cart_items.price`, `orders.grand_total` = **`decimal(12,4)`**, sehingga maksimum yang tersimpan 99.999.999,9999. |
| `config/database.php:58` | Koneksi mysql `'strict' => false`: nilai di luar rentang **dipotong diam-diam** oleh MySQL, bukan ditolak. |

## H. CartRule / kupon (P1) — `CartRule/src/Helpers/CartRule.php`

| Fungsi | Baris | Aturan |
|---|---|---|
| `canProcessRule` | 126–185 | Kupon: tidak ada kode di cart → false (129–131); kupon tidak cocok → false (165–167); `times_used >= usage_limit` → false (142–147); pemakaian per customer (149–164); pemakaian rule per customer (170–182). **`cart_rule_coupons.expired_at` tidak dicek di sini.** |
| `getCartRuleQuery` | 538–561 | Filter customer group (546), channel (547), `starts_from <= now` (550) dan `ends_till >= now` (555), keduanya memakai **`format('Y-m-d H:m:s')`**. |
| `checkCouponCode` | 495–510 | Kupon dianggap valid bila `cart_rule_id`-nya ada di `applied_cart_rule_ids`. |

## I. Daftar hipotesis defect (BELUM diverifikasi)

Status "Didukung kode" artinya pembacaan kode mendukung hipotesis tersebut. Status ini **bukan** bukti defect: perilakunya harus direproduksi minimal 2× dan dibandingkan dengan kebutuhan.

| ID | Hipotesis | Rujukan | Status setelah baca kode |
|---|---|---|---|
| D1 | `estimate-shipping-methods` tanpa cart aktif mengakses `$cart->id` pada null → 500 | `CartController.php:178-184` | Didukung kode |
| D2 | `DELETE cart/selected` tanpa `ids` tidak divalidasi → error 500, bukan 422 | `CartController.php:112-116` | Didukung kode |
| D3 | `PUT cart` mengembalikan **200** saat error, tidak konsisten dengan `store()` yang 400 | `CartController.php:159-163` vs `78-86` | Didukung kode |
| D4 | Kupon yang ada tetapi gagal dipasang (mis. expired atau rule nonaktif) mendapat teks hardcode "Coupon not found." | `CartController.php:247-250` | Didukung kode; perlu cek apakah teksnya ter-translate |
| D5 | Apply/remove kupon tanpa cart → error pada null | `CartController.php:230`, `265`; `Cart.php:620` | Didukung kode |
| D6 | removeItem/destroy/destroySelected/moveToWishlist tidak memeriksa kepemilikan cart item | `Cart.php:323`, `350`, `673` | **GUGUR menurut pembacaan kode** (ada pengecekan). Tetap diuji sebagai PASS untuk Authorization. |
| D7 | Email alamat checkout hanya `required`, format bebas | `CartAddressRequest.php:52` | Didukung kode; perlu dibandingkan dengan kebutuhan |
| D8 | Request alamat tanpa `billing` tetapi dengan `shipping` lolos validasi lalu gagal di `$params['billing']` → 500 | `CartAddressRequest.php:32-38`; `Cart.php:424` | Didukung kode |
| H9 | `POST onepage/addresses` tanpa cart (guest) → error pada null | `OnepageController.php:46-48` | Hipotesis baru |
| H10 | `PUT cart` dengan qty ≤ 0 **menghapus item** lalu membalas pesan error (efek samping tersembunyi), HTTP 200 | `Cart.php:358-362` | Hipotesis baru |
| H11 | `PUT cart` tanpa validasi tipe qty (string non-angka, desimal, negatif) | `CartController.php:148-151`; `Cart.php:343-364` | Hipotesis baru |
| H12 | **IDOR alamat:** customer A dapat mengirim `address_type=customer` dengan `id` alamat milik customer B; `parent_address_id` tersimpan tanpa cek pemilik | `Cart.php:456`, `523` | Hipotesis baru (P0 Authorization); dampak nyata perlu ditelusuri |
| H13 | Minimum order dengan opsi `include_discount_amount` mengurangi `tax_total` secara keliru | `Cart.php:797-798` | Hipotesis baru |
| H14 | `POST cart` tanpa `quantity` (padahal aturannya opsional) → "Undefined array key" → 400, bukan default 1 | `CartController.php:54`; `Simple.php:198` | Hipotesis baru |
| H15 | Login web dan API tanpa rate limit (brute force) | `customer-routes.php`; `api.php:98` | Hipotesis/risiko keamanan, butuh acuan kebutuhan |
| H16 | Login API tidak membatasi `channel_id`, tidak seperti login web | `API/CustomerController.php:20` vs `SessionController.php:38` | Hipotesis baru |
| H17 | Harga 0 diterima `Decimal` (`"0"` lolos) | `Decimal.php:15` | Perlu acuan kebutuhan; bisa jadi memang sesuai |
| H18 | **Harga > 99.999.999,9999 tersimpan terpotong tanpa error** (`Decimal` tanpa batas atas + `decimal(12,4)` + `strict=false`) | `Decimal.php:15`; `config/database.php:58` | **Observasi awal 1× pada data impor** (`evidence/H18-harga-terpotong-observasi-awal.txt`): 6 produk Rolex tersimpan 99.999.999,9999. Belum direproduksi lewat form admin. |
| H19 | Validitas tanggal cart rule memakai `H:m:s` (m = bulan), sehingga batas jam mulai/berakhir promo meleset hingga ±59 menit | `CartRule.php:550`, `555` | Hipotesis baru (P1), cocok untuk BVA waktu |
| H20 | `cart_rule_coupons.expired_at` tidak pernah dicek saat validasi kupon | `CartRule.php:126-185` | Hipotesis baru (P1); dampak terbatas karena `expired_at` biasanya = `ends_till` |
| H21 | `move-to-wishlist` terbuka untuk guest (tanpa middleware `customer`); wishlist dibuat dengan `customer_id` null, dan `qty[$index]` tidak divalidasi | `api.php:71`; `CartController.php:131-134`; `Cart.php:720` | Hipotesis baru |

### Hipotesis tambahan dari FASE 3
| ID | Ringkas | Rincian |
|---|---|---|
| H22 | `DELETE cart` membalas `success-remove` walau tidak ada yang dihapus | `04-blackbox.md` §10 |
| H23 | Teks alamat > 255 karakter terpotong diam-diam (`varchar(255)`, `strict=false`) | `04-blackbox.md` §10 |
| H24 | `storeOrder` membalas 500 untuk kesalahan validasi bisnis | `04-blackbox.md` §10 |
| H25 | Kode kupon beda huruf besar/kecil: ditemukan (collation ci) tetapi gagal `===` di `CartRule.php:140` | `05-whitebox.md` §5 |
