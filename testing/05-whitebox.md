# 05 — Desain White-box: Branch Coverage dan Batas Metrik Coverage

Rujukan `file:baris` mengacu pada Bagisto 2.3.19 (path relatif `packages/Webkul/`). Kolom **Hit baseline** adalah jumlah eksekusi baris *badan cabang* menurut PCOV pada coverage baseline. Sumbernya `evidence/F3-baseline-line-hits.txt`, dibuat dengan `php testing/tools/clover_lines.php evidence/coverage-baseline/clover.xml <file> <baris>`. Nilai `0` berarti cabang itu belum pernah dijalankan oleh 132 test bawaan. Tanda `≈` berarti hit tidak dapat dipisahkan per sisi kondisi (lihat §4).

Kasus penutup merujuk ID di `04-blackbox.md`. ID `WB-xx` adalah kasus tambahan khusus white-box (§3).

Legenda kolom **Sisi**: **T** = kondisi benar, **F** = kondisi salah.

---

## 1. Cart API — `Shop/src/Http/Controllers/API/CartController.php`

### 1.1 `store()` (49–88)
| Cabang | Baris | Kondisi | Sisi | Hit baseline (baris badan) | Kasus penutup |
|---|---|---|---|---|---|
| A1 | 51–55 | validasi gagal | T | (ditangani framework) | CT-01..07, CT-10 |
| A2 | 57 | `findOrFail` gagal | T | — | **Tidak terjangkau secara praktis**: `exists:products,id` di :52 sudah menjamin produk ada (hanya mungkin bila terjadi race penghapusan) |
| A3 | 60 | `! $product->status` | T | `:61` = 0 | CT-09 |
| A4 | 66 | `is_buy_now` | T | `:67` = 0, `:69` = 0 | WB-01 |
| A5 | 66 | `is_buy_now` | F | `:72` = 12 | CT-05, ST-01 |
| A6 | 78 | `catch InsufficientProductInventoryException` | T | `:79` = **0** | ST-04, ST-05, ST-09 |
| A7 | 82 | `catch \Exception` (lain) | T | `:84` = 0 | CT-09, CT-08 (H14) |

### 1.2 `update()` (148–164)
| Cabang | Baris | Kondisi | Sisi | Hit baseline | Kasus penutup |
|---|---|---|---|---|---|
| B1 | 151–158 | `updateItems` sukses | T | `:151` = 2 | ST-07 |
| B2 | 159–163 | `catch \Exception` → JSON **200** | T | `:160` = 0 | CT-11..14, ST-08 (D3) |

### 1.3 `destroySelected()` (112–124) — tanpa `if`
| Cabang | Baris | Kondisi | Hit baseline | Kasus penutup |
|---|---|---|---|---|
| C1 | 114 | `foreach` 0 iterasi (`ids = []`) | `:114` = 2 (≈) | WB-02 |
| C2 | 114 | `foreach` ≥ 1 iterasi | `:115` = 2 | AZ-04 |
| C3 | 114 | `ids` tidak ada (null) | ≈ | CT-15 (D2) — **cabang implisit yang tidak tampak di kode** |

### 1.4 `estimateShippingMethods()` (169–207)
| Cabang | Baris | Kondisi | Sisi | Hit baseline | Kasus penutup |
|---|---|---|---|---|---|
| D1a | 171–176 | validasi gagal | T | (framework) | CT-18 |
| D1b | 178–184 | `$cart` null | — | **tidak ada `if`** | CT-17 (D1) |
| D2 | 193 | `has('shipping_method')` | T | `:194` = 0 | WB-03 |
| D3 | 193 | idem | F | `:197` (≈) | test bawaan |

### 1.5 `storeCoupon()` (212–258)
| Cabang | Baris | Kondisi | Sisi | Hit baseline | Kasus penutup |
|---|---|---|---|---|---|
| E1 | 219 | `strlen($code)` | T | `:220` = 0 | CR-01..04 |
| E2 | 219 | idem | **F** | — | **Cabang mati.** `code` sudah `required` (:215). Middleware global `TrimStrings` aktif, sehingga `"   "` menjadi `""`. (`ConvertEmptyStringsToNull` justru **dihapus** Bagisto di `bootstrap/app.php:34`.) Aturan `required` menolak string yang kosong setelah trim (`vendor/laravel/framework/src/Illuminate/Validation/Concerns/ValidatesAttributes.php:2030`). `"0"` lolos `required` tetapi `strlen("0") = 1`. Sisi F tidak bisa dicapai lewat HTTP, sehingga method ini tidak bisa 100% branch coverage. |
| E3 | 222 | `! $coupon` | T | `:223` = 0 | CR-01 |
| E4 | 229 | `cart_rule->status` | T | `:230` = 0 | CR-03, CR-04 |
| E5 | 229 | idem | F | `:247` = 0 | CR-02 (D4) |
| E6 | 230 | sudah terpasang | T | `:231` = 0 | CR-04 |
| E7 | 239 | setelah `collectTotals`, kode masih terpasang | T | `:240` = 0 | CR-03 |
| E8 | 239 | idem | F → jatuh ke :247 | 0 | CR-05/06/07, WB-04 (H25) |
| E9 | 252 | `catch \Exception` | T | `:253` = 0 | CT-20 (D5, bila cart null memicu `ErrorException`) |

### 1.6 `destroyCoupon()` (263–271) — tanpa cabang; cart null tidak dicek → CT-21 (D5).

## 2. Cart domain — `Checkout/src/Cart.php`

### 2.1 `addProduct()` (256–312)
| Cabang | Baris | Kondisi | Sisi | Hit baseline | Kasus penutup |
|---|---|---|---|---|---|
| F1 | 260 | `! $this->cart` | T | `:261` = 18 | ST-01 |
| F2 | 268 | `prepareForCart` mengembalikan string | T | `:269` = 0 | WB-05 (opsi kustom wajib kosong) |
| F3 | 269 | cart kosong setelah gagal | T/F | `:270` = 0 / `:272` = 0 | WB-05 (T), WB-06 (F) |
| F4 | 286 | item baru | T | `:287` = 18 | ST-01 |
| F5 | 289–291 | item ada, child bundle beda parent | T | `:293` = 0 | test bawaan bundle (tidak dalam scope P0) |
| F6 | 296 | item ada, update | T | `:297` = 0 | ST-06 (penambahan qty produk sama) |

### 2.2 `removeItem()` (317–336)
| Cabang | Baris | Kondisi | Sisi | Hit baseline | Kasus penutup |
|---|---|---|---|---|---|
| G1 | 319 | `! $this->cart` | T | `:320` = 0 | AZ-06 (guest tanpa cart) |
| G2 | 323 | item bukan milik cart | T | `:324` = 4 | AZ-01, AZ-04 (+ test bawaan) |
| G3 | 323 | item milik cart | F | `:327` = 6 | test bawaan |

### 2.3 `updateItems()` (341–392)
| Cabang | Baris | Kondisi | Sisi | Hit baseline | Kasus penutup |
|---|---|---|---|---|---|
| H1 | 343 | key `qty` tidak ada | — | (implisit) | CT-14 (D3) |
| H2 | 346 | item tidak ditemukan | T | `:347` = 0 | WB-07 (`qty: {999999: 1}`) |
| H3 | 350 | item milik cart lain | T | `:351` = 0 | AZ-03 |
| H4 | 354 | produk nonaktif | T | `:355` = 0 | WB-08 |
| H5 | 358 | `quantity <= 0` | T | `:359` = 0, `:361` = 0 | CT-11, CT-12 (H10) |
| H6 | 366 | stok tidak cukup | T | `:367` = **0** | ST-08 |
| H7 | 366 | stok cukup | F | `:364` = 2 | ST-07 |

### 2.4 Alamat — `saveAddresses` (422–431), `updateOrCreateBillingAddress` (436–473), `updateOrCreateShippingAddress` (478–542)
| Cabang | Baris | Kondisi | Sisi | Hit baseline | Kasus penutup |
|---|---|---|---|---|---|
| I1 | 424 | key `billing` tidak ada | — | (implisit) | CO-19 (D8) |
| I2 | 456 | `address_type == 'customer'` (ternary) | T / F | `:456` = 10 (≈) | AZ-13, AZ-14 (T); CO-01 (F) |
| I3 | 464 | billing sudah ada → update | T / F | ≈ | WB-09 (kirim alamat 2×) |
| I4 | 483 | tidak ada item stockable | T | `:484` = 6 | test bawaan (virtual/downloadable) |
| I5 | 487 | billing belum ada → `BillingAddressNotFoundException` | T | `:488` = 0 | **Praktis tidak terjangkau** lewat `saveAddresses`: billing selalu dibuat di :424 sebelum :426 |
| I6 | 505 | `use_for_shipping` | T / F | `:506` = 2 / `:519` = 2 | CO-01 / test bawaan |
| I7 | 515 | params shipping kosong | T | `:516` = 0 | WB-10 (`use_for_shipping=0` tanpa shipping yang lolos validasi — tidak mungkin karena `CartAddressRequest:36-38`, sehingga **praktis tidak terjangkau via HTTP**) |
| I8 | 523 | ternary `customer` (shipping) | T / F | ≈ | AZ-13 varian shipping |

### 2.5 `saveShippingMethod()` (559–573)
| Cabang | Baris | Kondisi | Sisi | Hit | Kasus |
|---|---|---|---|---|---|
| J1 | 561 | `! $this->cart` | T | `:562` = 0 | WB-11 |
| J2 | 565 | kode tidak ada di rate | T | `:566` = 0 | SH-04, SH-05 |
| J3 | 565 | ada | F | `:569` = 2 | SH-02 |

### 2.6 `getErrors()` (757–784), `getOrderAmount()` (789–806), `haveMinimumOrderAmount()` (811–818)
| Cabang | Baris | Kondisi | Sisi | Hit | Kasus |
|---|---|---|---|---|---|
| K1 | 759 | cart null | T | `:761` = 0 | CT-21 / CO-21 |
| K2 | 766 | stok tidak cukup | T | `:768` = 0 | WB-12 (stok berkurang setelah item masuk cart) |
| K3 | 773 | minimum order tidak terpenuhi | T | `:777` = 0 | MO-01 |
| K4 | 813 | minimum order nonaktif | T | `:814` = 31 | test bawaan |
| K5 | 817 | perbandingan `>=` | T / F | `:817` = 0 | MO-01 (F), MO-02 (T, batas), MO-03 |
| K6 | 793 | `include_tax_to_amount` | T | `:794` = 0 | WB-13 |
| K7 | 797 | `include_discount_amount` | T | `:798` = 0 | MO-04 (H13) |
| K8 | 800 | ada diskon | T | `:801` = 0 | WB-14 (dengan kupon) |

## 3. Stok — `Product/src/Type/Simple.php`, `AbstractType.php`

| Cabang | Baris | Kondisi | Sisi | Hit | Kasus |
|---|---|---|---|---|---|
| L1 | Simple:154 | `! manage_stock` | T | `:155` = 0 | ST-11 |
| L2 | Simple:158 | `$qty <= totalQuantity()` | T | `:158` = 41 (≈) | ST-01..03 |
| L3 | Simple:158 | idem F → operand kedua `?:` = config back-order **true** | F/T | ≈ | ST-10 |
| L4 | Simple:158 | idem F → back-order **false** | F/F | ≈ | ST-04, ST-09 |
| L5 | Simple:191–193 | opsi kustom wajib ada **dan** data kosong (`&&`) | T | `:195` = 0 | WB-05 |
| L6 | Simple:202 | stok kurang → throw | T | `:203` = **0** | ST-04, ST-05 |
| L7 | Simple:208 | ada opsi kustom | T | `:209` = 0 | WB-15 (di luar P0 inti; opsional) |
| L8 | Simple:221–224 | ekstensi file tidak didukung | T | `:225` = 0 | WB-15 |
| L9 | AbstractType:519 | index inventory tidak ada → 0 | T | `:520` = 8 | test bawaan |
| L10 | AbstractType:829 | `$quantity ?: 1` | 0 → 1 | `:829` = 18 (≈) | CT-08 (H14) |
| L11 | AbstractType:840 | produk sudah ada di cart → qty dijumlah | T | `:841` = **0** | ST-05, ST-06 |

## 4. Checkout — `Shop/src/Http/Requests/CartAddressRequest.php`, `OnepageController.php`

### 4.1 `CartAddressRequest::rules()` (30–41) dan `mergeAddressRules()` (46–66)
| Cabang | Baris | Kondisi | Sisi | Hit | Kasus |
|---|---|---|---|---|---|
| M1 | 32 | `has('billing')` | T | `:33` = 14 | CO-01 |
| M2 | 32 | idem | F | ≈ (tidak ada baris badan) | CO-19 (D8) |
| M3 | 36 | `! billing.use_for_shipping` | T | `:37` = 9 | CO-20 |
| M4 | 36 | idem | F | ≈ | CO-01 |
| M5 | 55 | `isCountryRequired()` (ternary) | T | `:55` = 14 (≈) | CO-07 |
| M6 | 55 | idem | **F** | ≈ — config default = 1 sehingga sisi F **tidak pernah dijalankan** walau baris 100% | WB-16 (set config 0) |
| M7 | 56, 57 | ternary state/postcode | F | ≈ | WB-16 |
| M8 | 61 | `addressType == 'billing'` | T / F | `:62` = 14 / ≈ | CO-01 / CO-20 |

### 4.2 `OnepageController::storeAddress()` (42–87)
| Cabang | Baris | Kondisi | Sisi | Hit | Kasus |
|---|---|---|---|---|---|
| N1 | 46–48 | guest **dan** cart tanpa item guest-checkout | T | `:50` = 0 | WB-17 |
| N2 | 48 | `Cart::getCart()` null (operand kedua `&&`) | — | tidak ada cek | CO-21 (H9) |
| N3 | 56 | `Cart::hasError()` | T | `:57` = 0 | WB-12 |
| N4 | 69 | item stockable | T / F | `:70` = 4 / `:83` = 6 | CO-01 / test bawaan |
| N5 | 70 | tidak ada rate | T | `:71` = 0 | WB-18 (semua carrier dinonaktifkan) |

### 4.3 `storeShippingMethod()` (94–113)
| Cabang | Baris | Kondisi | Sisi | Hit | Kasus |
|---|---|---|---|---|---|
| O1 | 101 | `Cart::hasError()` | T | `:105` = 0 (≈ gabungan `\|\|`) | WB-12 |
| O2 | 102 | `! $validatedData['shipping_method']` | T | ≈ | **WB-19: `shipping_method = "0"`** lolos `required`, tetapi falsy, sehingga satu-satunya cara mencapai operand ini |
| O3 | 103 | `! saveShippingMethod()` | T | ≈ | SH-04, SH-05 |
| O4 | 100–104 | semua F | F | `:110` = 2 | SH-02 |

### 4.4 `storeOrder()` (148–198) dan `validateOrder()` (206–252)
| Cabang | Baris | Kondisi | Sisi | Hit | Kasus |
|---|---|---|---|---|---|
| P1 | 150 | `Cart::hasError()` | T | `:151` = 0 | WB-12 |
| P2 | 159–165 | `validateOrder` melempar → **500** | T | `:161` = 0 | OR-02 (H24), OR-03 |
| P3 | 169 | redirect payment (PayPal) | T | `:170` = 0 | di luar lingkup (§2.2 test plan) |
| P4 | 180 | `CouponUsageLimitExceededException` | T | `:181` = 0 | P1: CR-05 varian |
| P5 | 212–214 | customer suspended | T | `:216` = 0 | OR-03 |
| P6 | 219–221 | customer nonaktif | T | `:223` = 0 | WB-20 |
| P7 | 226 | minimum order | T | `:227` = 0 | MO-01 |
| P8 | 230 | stockable tanpa shipping address | T | `:231` = 0 | WB-21 |
| P9 | 234 | tanpa billing | T | `:235` = 0 | WB-21 |
| P10 | 238–240 | tanpa shipping rate | T | `:242` = 0 | WB-22 |
| P11 | 245 | tanpa payment | T | `:246` = 0 | OR-02 |
| P12 | 249 | `paypal_smart_button` | T | `:250` = 0 | WB-23 |

## 5. CartRule (P1) — `CartRule/src/Helpers/CartRule.php::canProcessRule()` (126–185)
| Cabang | Baris | Kondisi | Sisi | Kasus |
|---|---|---|---|---|
| Q1 | 128 | rule berkupon | T / F | CR-03 / rule otomatis |
| Q2 | 129 | cart tanpa kode | T | WB-24 |
| Q3 | 138–140 | kupon ditemukan **dan** `$coupon->code === cart.coupon_code` | T / F | CR-03 / **WB-04 (H25)** |
| Q4 | 142–145 | `usage_limit` habis | T | CR-05 |
| Q5 | 149–162 | `usage_per_customer` habis | T | P1 |
| Q6 | 170–180 | `usage_per_customer` rule | T | P1 |

**H25 (hipotesis baru, white-box).** Operand `$coupon->code === $this->cart->coupon_code` (:140) tampak selalu benar karena query :136 sudah `where('code', ...)`. Namun collation tabel `utf8mb4_unicode_ci` **tidak membedakan huruf besar/kecil**, sedangkan `===` membedakannya. Akibatnya, kupon `HEMAT10` yang diketik `hemat10`:
1. Ditemukan di `CartController::storeCoupon:220` (collation ci).
2. Disimpan sebagai `hemat10` di cart.
3. Lolos query :136, tetapi gagal `===` di :140, sehingga rule tidak diterapkan.
4. Respons yang diterima pengguna adalah teks hardcode "Coupon not found." (:249).

Ini hanya terlihat dari analisis cabang, bukan dari tabel EP.

## 6. Kasus tambahan white-box (WB)

| ID | Tujuan (cabang) | Input/prakondisi ringkas | Expected |
|---|---|---|---|
| WB-01 | A4 `is_buy_now = 1` | `POST cart {product_id, quantity:1, is_buy_now:1}` dengan cart lama berisi item lain | 200 + `redirect`; cart lama dinonaktifkan |
| WB-02 | C1 `ids = []` | `DELETE cart/selected {ids: []}` | 200, cart tidak berubah |
| WB-03 | D2 estimasi + `shipping_method` | `estimate-shipping-methods` dengan `shipping_method: flatrate_flatrate` | 200, `cart.shipping_method` terisi |
| WB-04 | Q3-F / E8 (H25) | kupon `HEMAT10` aktif, kirim `code: "hemat10"` | Kupon berlaku (kode tidak case-sensitive) atau ditolak dengan pesan jelas; konsisten di semua lapisan |
| WB-05 | F2/F3-T, L5 | produk dengan opsi kustom wajib, tanpa `customizable_options` | 400; cart kosong dihapus |
| WB-06 | F3-F | idem tetapi cart sudah berisi produk lain | 400; cart lama tetap ada |
| WB-07 | H2 | `PUT cart {qty: {999999: 1}}` | Item lain tidak berubah; tidak error |
| WB-08 | H4 | produk di cart lalu dinonaktifkan, `PUT` qty | Pesan `inactive` |
| WB-09 | I3 | kirim alamat 2× dengan nilai berbeda | Satu baris billing, terupdate |
| WB-10 | I7 | dicatat **tidak terjangkau via HTTP** | — |
| WB-11 | J1 | panggil `Cart::saveShippingMethod` tanpa cart (unit) | `false` |
| WB-12 | K2/N3/O1/P1 | item di cart, lalu stok sumber diturunkan di bawah qty | `hasError()` = true; alamat/shipping/order di-redirect |
| WB-13 | K6 | minimum order + `include_tax_to_amount` | Pembanding = subtotal + pajak |
| WB-14 | K8 | minimum order + `include_discount_amount` + kupon | Pembanding = subtotal − diskon |
| WB-15 | L7/L8 | opsi kustom tipe file dengan ekstensi tidak didukung | Pesan `invalid-file-extension` (opsional) |
| WB-16 | M6/M7 | config `customer.address.requirements.country = 0` | `country` kosong diterima |
| WB-17 | N1 | guest, produk tanpa `guest_checkout` | `redirect=true` ke login |
| WB-18 | N5 | semua carrier nonaktif | `redirect=true` ke cart |
| WB-19 | O2 | `shipping_method: "0"` | 403 |
| WB-20 | P6 | customer `status = 0` saat order | Order tidak dibuat |
| WB-21 | P8/P9 | order tanpa alamat | Order tidak dibuat |
| WB-22 | P10 | order tanpa shipping method | Order tidak dibuat |
| WB-23 | P12 | payment `paypal_smart_button` | Order tidak dibuat |
| WB-24 | Q2 | rule berkupon, cart tanpa kode | Diskon tidak diterapkan |

## 7. Cabang tidak terjangkau / cabang mati

| Lokasi | Cabang | Alasan |
|---|---|---|
| `CartController.php:219` | sisi F `strlen($code)` | `required` (:215) menolak string kosong setelah `TrimStrings` (`ValidatesAttributes.php:2030`) |
| `CartController.php:57` | `findOrFail` gagal | Dijamin `exists:products,id` (:52), kecuali race |
| `Cart.php:487-489` | `BillingAddressNotFoundException` | `saveAddresses` selalu membuat billing (:424) sebelum shipping (:426) |
| `Cart.php:515-517` | shipping params kosong | `CartAddressRequest:36-38` mewajibkan shipping bila `use_for_shipping` falsy |
| `OnepageController.php:102` | `! $validatedData['shipping_method']` | Hanya terjangkau dengan string `"0"` (WB-19). Bukan mati, tetapi nyaris mati |
| `CartRule.php:140` | sisi F `===` | Hanya terjangkau bila beda huruf besar/kecil (H25) |

Konsekuensinya, **100% branch coverage tidak mungkin** dicapai pada `storeCoupon`, `store`, `updateOrCreateShippingAddress` lewat uji HTTP. Target realistis per method dinyatakan di §9.

## 8. Batas metrik coverage (bahan analisis)

1. **PCOV = line coverage saja.** Satu baris berisi ternary, `?:`, `&&`, atau `||` dihitung "tertutup" bila dieksekusi sekali, apa pun sisi yang diambil:
   - `Simple.php:158` (`<=` + `?:` back-order) memiliki 3 hasil logis; baseline 41 hit, tetapi sisi back-order tidak pernah teruji.
   - `CartAddressRequest.php:55-57` 100% line, padahal sisi "tidak wajib" tidak pernah dijalankan (M6).
   - `Cart.php:456` (I2), `OnepageController.php:46-48`/`100-104`.
   - Branch/path coverage PHPUnit butuh Xdebug (`--path-coverage`). Tabel cabang di dokumen ini menggantikannya secara manual.
2. **Kode yang tidak ada tidak bisa diukur.** D1 (cek null), D2 dan D7 (aturan validasi), D8 (key wajib), serta H12 (cek pemilik) adalah *missing code*. Method terkait sudah 96–100% line coverage di baseline (`02-baseline.md` §4.1).
3. **Coverage ≠ verifikasi.** Coverage mencatat eksekusi, bukan assertion. Contoh: `CartTest.php:858+` mengeksekusi `destroy` untuk item milik orang lain (G2 tertutup), tetapi hanya `assertOk()`. Kebenaran pesan `success-remove` (H22) tidak diverifikasi.
4. **Penyebut mempengaruhi persentase.** `Checkout/src` 51,30% vs 66,72% tanpa migrasi/factory (`02-baseline.md` §3.1).
5. **Perilaku bergantung data/lingkungan.** H18 (pemotongan `decimal(12,4)`) dan H23 (`varchar(255)`) terjadi di MySQL dengan `strict=false`, di luar kode PHP. Baris PHP yang menyimpan tetap "tertutup" baik nilainya terpotong maupun tidak.
6. **Loop:** line coverage tidak membedakan 0, 1, atau n iterasi (C1–C3, `updateItems:343`).
7. **Pengecualian implisit:** akses array/property pada null (D1, D5, D8, H9) tidak memiliki baris cabang, sehingga tidak muncul sebagai "baris tidak tertutup".

## 9. Target coverage FASE 4
| Target | Nilai | Alasan |
|---|---|---|
| Line coverage method P0 yang disasar (`store`, `update`, `storeCoupon`, `updateItems`, `Simple::prepareForCart`, `haveSufficientQuantity`, `storeAddress`, `validateOrder`, `getErrors`, `getOrderAmount`) | ≥ 80% per method, kecuali baris yang tercantum di §7 | Sisa baris adalah cabang mati atau di luar lingkup (PayPal, opsi file) |
| Cabang di tabel §1–§4 | Semua cabang **terjangkau** ditutup oleh ≥ 1 kasus; cabang tak terjangkau didokumentasikan | Menggantikan branch coverage otomatis |
| Total direktori | Dilaporkan sebelum/sesudah apa adanya, tanpa target angka | Penyebut berisi kode non-P0 (Bundle, Grouped, Downloadable, migrasi) |
