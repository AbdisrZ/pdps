# LAPORAN TUGAS BESAR
## Pengujian dan Pemeliharaan Sistem
### Spesifikasi Kebutuhan, Pengujian, dan Perbaikan Sistem Toko Online Bagisto 2.3.19

| | |
|---|---|
| **Mata kuliah** | Pengujian dan Pemeliharaan Sistem |
| **Program studi** | Teknik Informatika, Universitas Dirgantara Marsekal Suryadarma (UNDIRA) |
| **Objek** | Bagisto 2.3.19, aplikasi toko online open source berbasis Laravel |
| **Tanggal** | November 2026 |
| **Repositori** | <https://github.com/AbdisrZ/pdps> (branch `praktikum`) |
| **Laporan daring** | <https://abdisrz.github.io/pdps/> (berisi 59 screenshot bukti) |

**Disusun oleh:**

| No | Nama | NIM |
|---|---|---|
| 1 | ABDI SOLEH R | ……………… |
| 2 | YUNITA YASMAN | ……………… |
| 3 | BAGUS FAHREZI | ……………… |

---

## Daftar Isi

- **Bagian I — Konteks**
  1. [Pendahuluan](#1-pendahuluan)
  2. [Ruang lingkup](#2-ruang-lingkup)
  3. [Spesifikasi Kebutuhan Perangkat Lunak (SRS)](#3-spesifikasi-kebutuhan-perangkat-lunak-srs)
  4. [Metode dan referensi](#4-metode-dan-referensi)
- **Bagian II — Data Inti**
  5. [Daftar test case](#5-daftar-test-case)
  6. [Daftar defect](#6-daftar-defect)
- **Bagian III — Sintesis**
  7. [Ringkasan hasil](#7-ringkasan-hasil)
  8. [Analisis](#8-analisis)
  9. [Kesimpulan](#9-kesimpulan)
- **Bagian IV — Tindakan**
  10. [Rekomendasi tindak lanjut](#10-rekomendasi-tindak-lanjut)
- [Lampiran](#lampiran)

---

# BAGIAN I — KONTEKS

## 1. Pendahuluan

### 1.1 Latar belakang
Toko online menangani uang, stok barang, dan data pribadi pelanggan, sehingga kesalahan kecil dapat berdampak langsung pada kerugian penjual maupun pembeli. Kami memilih **Bagisto**, aplikasi toko online open source berbasis Laravel, sebagai objek pengujian. Fiturnya lengkap seperti toko sungguhan: pendaftaran dan login pelanggan, keranjang belanja, stok, checkout dengan ongkos kirim dan pembayaran, kupon, serta halaman admin untuk mengelola produk dan pesanan.

Bagisto diinstal dari repositori resminya dan dijalankan secara lokal. Versi yang diuji adalah **2.3.19**: versi terbaru (2.5) memerlukan PHP 8.4, sedangkan lingkungan kami menggunakan PHP 8.2, dan 2.3.19 adalah rilis terakhir yang mendukung PHP 8.2. Toko diisi data contoh berupa 195 produk dengan harga Rupiah, 25 pelanggan, dan 40 pesanan.

### 1.2 Tujuan
1. Merumuskan kebutuhan perangkat lunak (SRS) untuk fungsi-fungsi utama toko sebagai acuan pengujian.
2. Menguji fungsi berisiko tinggi terhadap kebutuhan tersebut dan menemukan defect yang dapat direproduksi.
3. Memperbaiki minimal dua defect, membuktikan perbaikannya, dan memastikan tidak ada fungsi lain yang rusak (uji regresi).
4. Menganalisis hasil pengujian, termasuk cakupan dan keterbatasannya, lalu menyusun rekomendasi tindak lanjut.

### 1.3 Sasaran pembaca
Dosen penguji, anggota kelompok, dan pihak yang ingin memeriksa ulang hasil pengujian melalui repositori.

### 1.4 Definisi dan istilah

| Istilah | Arti |
|---|---|
| Test case | Satu skenario uji dengan input, langkah, dan hasil yang diharapkan |
| Defect / bug | Perilaku sistem yang tidak sesuai kebutuhan dan dapat direproduksi |
| EP (Equivalence Partitioning) | Teknik membagi input ke dalam kelompok yang diperlakukan sama; EP-V = kelompok valid, EP-I = kelompok tidak valid |
| BVA (Boundary Value Analysis) | Teknik menguji nilai tepat di batas, sedikit di bawah, dan sedikit di atasnya |
| Regresi | Pengujian ulang untuk memastikan perbaikan tidak merusak fungsi lain |
| Baseline | Kondisi awal sistem sebelum diubah (tag Git `v1.0.0-baseline`) |
| Coverage | Persentase baris kode yang dijalankan oleh test |
| API | Antarmuka yang diakses aplikasi lain melalui permintaan HTTP (`GET`, `POST`, `PUT`, `DELETE`) |
| Severity / prioritas | Tingkat dampak defect / urutan penanganannya |

## 2. Ruang lingkup

### 2.1 Modul yang diuji

| Prioritas | Modul | Fungsi yang diuji |
|---|---|---|
| Utama | Hak akses (authorization) | Pelanggan tidak dapat mengakses keranjang, pesanan, invoice, dan alamat milik pelanggan lain |
| Utama | Stok barang | Pembelian tidak boleh melebihi stok, termasuk penambahan bertahap dan perubahan jumlah |
| Utama | Keranjang | Tambah, ubah jumlah, hapus, dan pindah ke wishlist melalui API dan halaman web |
| Utama | Checkout | Validasi alamat (email, telepon, kode pos), pilihan ongkos kirim, pembayaran, pembuatan pesanan, minimum order |
| Utama | Autentikasi | Pendaftaran, login web dan API, akun nonaktif atau belum terverifikasi |
| Utama | Harga produk (admin) | Validasi harga, harga diskon, dan kapasitas penyimpanan harga |
| Pendukung | Pencarian, riwayat pesanan, dashboard dan daftar pesanan admin | Diuji secara manual lewat halaman web |

### 2.2 Modul yang tidak diuji

| Modul | Alasan |
|---|---|
| Kupon / promo (cart rule) | Dijadwalkan setelah modul utama; kasus uji sudah dirancang (8 kasus) tetapi belum dijalankan |
| Pembayaran eksternal (PayPal) | Memerlukan akun sandbox pihak ketiga |
| Produk booking, GDPR, MagicAI, impor/ekspor data, login media sosial | Di luar fungsi inti toko yang diprioritaskan |
| Pengujian performa dan beban | Server pengembangan (`php artisan serve`) tidak mewakili kondisi produksi |
| Kompatibilitas lintas browser dan perangkat | Hanya diuji dengan Chrome di desktop |

## 3. Spesifikasi Kebutuhan Perangkat Lunak (SRS)

### 3.1 Gambaran umum produk
Bagisto adalah aplikasi web e-commerce yang terdiri atas dua bagian:
- **Storefront** untuk pengunjung dan pelanggan: katalog, pencarian, keranjang, checkout, dan akun pelanggan.
- **Panel admin** untuk pengelola toko: produk, harga, pesanan, pelanggan, dan konfigurasi.

Storefront juga menyediakan API (`/api/checkout/*`, `/api/customer/*`) yang dipakai halaman web itu sendiri dan dapat dipanggil aplikasi lain.

### 3.2 Pengguna sistem

| Pengguna | Deskripsi | Hak utama |
|---|---|---|
| Tamu (guest) | Pengunjung tanpa akun | Melihat katalog, keranjang, dan checkout sebagai tamu (bila produk mengizinkan) |
| Pelanggan | Pengunjung terdaftar dan login | Seluruh hak tamu, ditambah riwayat pesanan, invoice, alamat tersimpan, dan wishlist miliknya sendiri |
| Admin | Pengelola toko | Mengelola produk, harga, pesanan, pelanggan, dan konfigurasi |

### 3.3 Lingkungan operasi
- Server: PHP 8.2.12, MySQL 8.0.44, Laravel 11.48 (XAMPP di Windows 11).
- Klien: browser modern (diuji dengan Google Chrome) dan klien HTTP untuk API (diuji dengan VS Code REST Client).
- Mata uang toko: Rupiah (IDR) untuk data utama; database pengujian otomatis memakai USD bawaan installer.

### 3.4 Sumber kebutuhan dan asumsi
Bagisto tidak menyediakan dokumen kebutuhan resmi. Kebutuhan di bawah ini kami rumuskan dari tiga sumber:
1. **Perilaku yang dimaksudkan oleh Bagisto sendiri**: aturan validasi di kode, pesan kesalahan bawaan, dan test bawaannya.
2. **Praktik umum toko online.**
3. **Standar keamanan OWASP ASVS 4.0** dan semantik kode status HTTP (RFC 9110).

Kebutuhan bertanda **[asumsi]** adalah rumusan kelompok yang belum dikonfirmasi pemilik sistem. Defect yang berpijak pada kebutuhan ini dilaporkan dengan catatan tersebut.

### 3.5 Kebutuhan fungsional

| ID | Kebutuhan | Sumber |
|---|---|---|
| **REQ-AZ-01** | Pelanggan/tamu hanya dapat membaca, mengubah, dan menghapus item pada keranjang aktif miliknya sendiri. | Test bawaan Bagisto; kode keranjang |
| **REQ-AZ-02** | Pelanggan hanya dapat melihat, memesan ulang, membatalkan, dan mencetak invoice pesanan miliknya; pesanan milik orang lain menghasilkan 404. | Kode pengendali pesanan |
| **REQ-AZ-03** | Alamat tersimpan yang dirujuk saat checkout harus milik pelanggan yang sedang login. | OWASP ASVS V4.2.1 **[asumsi]** |
| **REQ-ST-01** | Jumlah barang di keranjang (kumulatif) tidak boleh melebihi stok tersedia bila pengelolaan stok aktif dan back-order nonaktif. | Pesan bawaan "jumlah tidak tersedia"; aturan stok |
| **REQ-ST-02** | Bila back-order aktif atau pengelolaan stok nonaktif, jumlah melebihi stok diizinkan. | Konfigurasi Bagisto |
| **REQ-CT-01** | Tambah ke keranjang: produk wajib ada dan aktif; jumlah bilangan bulat ≥ 1; bila jumlah tidak diisi, default 1. | Aturan validasi dan fungsi default jumlah |
| **REQ-CT-02** | Ubah jumlah: jumlah harus bilangan bulat ≥ 1; jumlah tidak valid ditolak **tanpa mengubah isi keranjang**. | Pesan bawaan **[asumsi: tanpa efek samping]** |
| **REQ-CO-01** | Alamat billing wajib berisi nama depan/belakang, email **berformat email**, alamat, kota, negara, provinsi, kode pos, dan telepon. | Aturan validasi alamat; format email **[asumsi]** |
| **REQ-CO-02** | Telepon: tanda `+` opsional diikuti angka saja. Kode pos: minimal 2 karakter alfanumerik, boleh berisi spasi/tanda hubung di tengah. | Aturan validasi telepon dan kode pos |
| **REQ-CO-03** | Metode pengiriman harus salah satu tarif yang tersedia untuk keranjang; selain itu ditolak. | Kode checkout |
| **REQ-CO-04** | Pesanan hanya dibuat bila alamat billing, alamat pengiriman (untuk barang fisik), metode pengiriman, dan pembayaran lengkap; pelanggan yang diblokir atau nonaktif ditolak. | Kode validasi pesanan |
| **REQ-CO-05** | Minimum order (bila aktif) dibandingkan dengan subtotal, ditambah pajak bila opsi pajak aktif dan dikurangi diskon bila opsi diskon aktif; batasnya inklusif. | Konfigurasi dan kode minimum order |
| **REQ-AU-01** | Pendaftaran: nama wajib; email valid dan unik; password minimal 6 karakter dan terkonfirmasi. | Aturan validasi pendaftaran |
| **REQ-AU-02** | Login: email valid, password minimal 6 karakter; akun nonaktif, belum terverifikasi, atau kredensial salah ditolak. | Aturan validasi dan pengendali login |
| **REQ-PR-01** | Harga dan biaya: angka desimal non-negatif dengan maksimal 4 digit desimal. | Aturan validasi harga |
| **REQ-PR-02** | Harga diskon (special price) harus lebih kecil dari harga normal. | Aturan validasi produk |
| **REQ-CR-01** | Kupon hanya berlaku dalam periode berlakunya dan selama kuota pemakaian belum habis. *(Belum diuji)* | Kode cart rule |

### 3.6 Kebutuhan non-fungsional

| ID | Kategori | Kebutuhan | Sumber |
|---|---|---|---|
| **REQ-CT-03** | Keandalan / penanganan kesalahan | Kesalahan input klien di API dijawab dengan kode 4xx (400/422) yang konsisten beserta pesan yang jelas, tidak boleh 500 dan tidak boleh 200. | RFC 9110 **[asumsi]** |
| **REQ-CT-04** | Integritas informasi | Respons "berhasil dihapus" hanya diberikan bila item benar-benar terhapus. | **[asumsi]** |
| **REQ-PR-03** | Integritas data | Nilai yang diterima validasi harus tersimpan persis sama; nilai di luar kapasitas penyimpanan ditolak dengan pesan validasi, bukan dipotong diam-diam. | **[asumsi]** |
| **REQ-AU-03** | Keamanan | Login dibatasi terhadap percobaan berulang (rate limiting). | OWASP ASVS V2.2.1 **[asumsi]** |
| **REQ-AU-04** | Keamanan / konsistensi | Login web dan API memakai aturan yang sama, termasuk pembatasan channel toko. | **[asumsi]** |
| **REQ-CR-02** | Kegunaan | Pesan kegagalan kupon menjelaskan alasannya dan dapat diterjemahkan. *(Belum diuji)* | **[asumsi]** |

### 3.7 Batasan desain yang ditemukan
- Kolom harga dan total di database bertipe `decimal(12,4)`, sehingga nilai maksimum yang dapat disimpan adalah 99.999.999,9999.
- Koneksi database dikonfigurasi `strict = false`, sehingga nilai di luar kapasitas dipotong oleh MySQL tanpa error. Hal yang sama berlaku untuk teks melebihi panjang kolom (255 karakter).

## 4. Metode dan referensi

### 4.1 Pendekatan
1. **Berbasis risiko.** Modul diprioritaskan berdasarkan kemungkinan terjadinya kesalahan dikalikan dampaknya. Risiko tertinggi: harga tersimpan salah, data checkout tidak valid, autentikasi lemah, hak akses, dan oversell stok.
2. **Black-box.** Kasus uji dirancang dengan *Equivalence Partitioning* dan *Boundary Value Analysis*. Seluruh nilai batas diambil dari kode, misalnya stok, panjang kode pos, panjang password, dan kapasitas harga.
3. **White-box.** Cabang logika (if/else, exception) pada fungsi-fungsi utama dipetakan, lalu ditandai mana yang sudah dan belum dijalankan oleh test bawaan Bagisto.
4. **Hasil yang diharapkan diturunkan dari SRS (bagian 3)**, bukan dari perilaku sistem.
5. **Aturan penetapan defect:** sebuah kegagalan dinyatakan defect bila terjadi pada **dua kali** eksekusi terpisah dengan perilaku sama dan bertentangan dengan kebutuhan.

### 4.2 Lingkungan dan pengamanan data
| Database | Isi | Kegunaan |
|---|---|---|
| `bagisto` | Data toko asli (dicadangkan sebelum pengujian) | Tidak disentuh selama pengujian |
| `bagisto_test` | Diisi ulang dari data bawaan Bagisto | Test otomatis; setiap test dibatalkan (rollback) di akhir |
| `bagisto_uji` | Salinan dari cadangan database asli | Pengujian manual lewat halaman web dan API |

Kami membuktikan bahwa database asli tidak berubah dengan mencatat jumlah produk, pesanan, dan pelanggan sebelum dan sesudah setiap tahap. Hasilnya selalu sama: 195 / 40 / 26.

### 4.3 Alat dan teknik pengujian

| Jenis pengujian | Alat | Jumlah |
|---|---|---|
| Test otomatis (integrasi dan unit) | Pest 3.8 / PHPUnit 11.5 | 112 kasus |
| Test bawaan Bagisto (baseline dan regresi) | Pest | 132 + 52 test |
| Pengukuran coverage | PCOV 1.0.12 | Line dan method coverage |
| Pengujian API manual | VS Code + ekstensi REST Client | 12 request |
| Pengujian lewat halaman web | Google Chrome (dijalankan skrip, otomatis mengambil screenshot) | 28 skenario |
| Pengelolaan versi | Git + GitHub (branch, commit, tag) | Tag `v1.0.0-baseline`, `v1.0.1`, `v1.0.2` |

### 4.4 Referensi
1. Bagisto. *Bagisto — Laravel eCommerce* (kode sumber v2.3.19). <https://github.com/bagisto/bagisto>
2. Laravel. *Laravel 11.x Documentation: Validation, HTTP Tests, Database Testing*. <https://laravel.com/docs/11.x>
3. Pest PHP. *Pest Documentation*. <https://pestphp.com/docs>
4. IEEE. *IEEE Std 830-1998, Recommended Practice for Software Requirements Specifications*.
5. IEEE. *IEEE Std 829-2008 / ISO/IEC/IEEE 29119-3, Software Test Documentation*.
6. OWASP. *Application Security Verification Standard (ASVS) 4.0.3*. <https://owasp.org/www-project-application-security-verification-standard/>
7. IETF. *RFC 9110: HTTP Semantics* (2022), §15 Status Codes. <https://www.rfc-editor.org/rfc/rfc9110>
8. Huachao Mao. *REST Client for Visual Studio Code*. <https://marketplace.visualstudio.com/items?itemName=humao.rest-client>

---

# BAGIAN II — DATA INTI

## 5. Daftar test case

### 5.1 Ringkasan per modul (test otomatis)

| Modul | Kode | Jumlah | Lolos | Gagal | Kebutuhan yang diuji |
|---|---|---|---|---|---|
| Hak akses | AZ | 14 | 12 | 2 | REQ-AZ-01/02/03, REQ-CT-04 |
| Stok | ST | 12 | 12 | 0 | REQ-ST-01/02 |
| Keranjang | CT | 21 | 12 | 9 | REQ-CT-01/02/03 |
| Alamat checkout | CO | 21 | 17 | 4 | REQ-CO-01/02, REQ-CT-03 |
| Ongkir, pesanan, minimum order | SH, OR, MO | 14 | 11 | 3 | REQ-CO-03/04/05 |
| Autentikasi | AU | 16 | 14 | 2 | REQ-AU-01..04 |
| Harga (admin) | PR | 14 | 12 | 2 | REQ-PR-01/02/03 |
| **Total** | | **112** | **90** | **22** | |

Status di tabel 5.2 adalah hasil eksekusi awal yang identik pada dua kali run. Dua kasus yang defect-nya kemudian diperbaiki ditandai "FAIL → PASS" beserta versi perbaikannya. File test ada di [`packages/Webkul/Shop/tests/Feature/Praktikum/`](https://github.com/AbdisrZ/pdps/tree/praktikum/packages/Webkul/Shop/tests/Feature/Praktikum).

### 5.2 Daftar lengkap test case otomatis

| ID | Modul | Skenario / input | Teknik | Hasil yang diharapkan | Status | Defect |
|---|---|---|---|---|---|---|
| AZ-01 | Hak akses | B menghapus item milik A — login B, `DELETE api/checkout/cart` `{cart_item_id: iA}` | EP-I | `iA` tetap ada, cart A tidak berubah | PASS | – |
| AZ-02 | Hak akses | Pesan respons AZ-01 — idem | EP-I | Respons tidak menyatakan `success-remove` bila tidak ada yang dihapus | FAIL | DEF-10 |
| AZ-03 | Hak akses | B mengubah qty item A — login B, `PUT api/checkout/cart` `{qty: {iA: 5}}` | EP-I | Qty `iA` tidak berubah | PASS | – |
| AZ-04 | Hak akses | B menghapus terpilih berisi item A — login B, `DELETE api/checkout/cart/selected` `{ids: [iA]}` | EP-I | `iA` tetap ada | PASS | – |
| AZ-05 | Hak akses | B memindahkan item A ke wishlist — login B, `POST api/checkout/cart/move-to-wishlist` `{ids:[iA], qty:[1]}` | EP-I | `iA` tetap ada; tidak ada wishlist baru untuk B | PASS | – |
| AZ-06 | Hak akses | Guest menghapus item milik A — tanpa login, `DELETE api/checkout/cart` `{cart_item_id: iA}` | EP-I | `iA` tetap ada | PASS | – |
| AZ-07 | Hak akses | A melihat order sendiri — login A, `GET customer/account/orders/view/{oA}` | EP-V | 200 | PASS | – |
| AZ-08 | Hak akses | B melihat order A — login B, `GET .../orders/view/{oA}` | EP-I | 404 | PASS | – |
| AZ-09 | Hak akses | B reorder order A — login B, `GET .../orders/reorder/{oA}` | EP-I | 404; cart B tidak bertambah | PASS | – |
| AZ-10 | Hak akses | B membatalkan order A — login B, `POST .../orders/cancel/{oA}` | EP-I | 404; status `oA` tidak berubah | PASS | – |
| AZ-11 | Hak akses | B mencetak invoice A — login B, `GET .../orders/print/Invoice/{invA}` | EP-I | 404 | PASS | – |
| AZ-12 | Hak akses | Guest membuka daftar order — tanpa login, `GET customer/account/orders` | EP-I | Redirect ke login | PASS | – |
| AZ-13 | Hak akses | B merujuk alamat milik A saat checkout — login B, `POST api/checkout/onepage/addresses` `billing: {address_type: "custo… | EP-I | Ditolak (4xx) atau `parent_address_id` tidak berisi `adrA` | FAIL | DEF-13 |
| AZ-14 | Hak akses | B merujuk alamatnya sendiri — idem dengan `id = adrB` | EP-V | 200, `parent_address_id = adrB` | PASS | – |
| ST-01 | Stok | qty di tengah rentang — `POST cart {product_id: P, quantity: 2}` | EP-V | 200; item qty = 2 | PASS | – |
| ST-02 | Stok | qty = S − 1 = 4 — `quantity: 4` | BVA | 200 | PASS | – |
| ST-03 | Stok | qty = S = 5 (batas) — `quantity: 5` | BVA | 200; item qty = 5 | PASS | – |
| ST-04 | Stok | qty = S + 1 = 6 — `quantity: 6` | BVA | 400, pesan `inventory-warning`; tidak ada item tersimpan | PASS | – |
| ST-05 | Stok | sudah ada 5 di cart, tambah 1 — 2× `POST` (5 lalu 1) | BVA | `POST` kedua 400; qty tetap 5 | PASS | – |
| ST-06 | Stok | sudah ada 4, tambah 1 — 2× `POST` (4 lalu 1) | BVA | 200; qty = 5 | PASS | – |
| ST-07 | Stok | `PUT` qty → S — `PUT cart {qty: {i: 5}}` | BVA | Qty = 5, pesan sukses | PASS | – |
| ST-08 | Stok | `PUT` qty → S + 1 — `PUT cart {qty: {i: 6}}` | BVA | Ditolak dengan pesan `inventory-warning`; qty tetap lama | PASS | – |
| ST-09 | Stok | stok 0 — S = 0, `quantity: 1` | EP-I | 400 | PASS | – |
| ST-10 | Stok | back-order aktif — config `catalog.inventory.stock_options.back_orders = 1`, `quantity: 6` | EP-V | 200 | PASS | – |
| ST-11 | Stok | `manage_stock = 0` — `quantity: 50` | EP-V | 200 | PASS | – |
| ST-12 | Stok | sumber 5, terpesan 3 → tersedia 2 — `quantity: 2` lalu (cart baru) `quantity: 3` | BVA | 2 → 200; 3 → 400 | PASS | – |
| CT-01 | Keranjang | `product_id` kosong — `{quantity: 1}` | EP-I | 422 error `product_id` | PASS | – |
| CT-02 | Keranjang | `product_id` tidak ada — `{product_id: 999999, quantity: 1}` | EP-I | 422 | PASS | – |
| CT-03 | Keranjang | `product_id` bukan integer — `{product_id: "abc"}` | EP-I | 422 | PASS | – |
| CT-04 | Keranjang | `quantity = 0` (di bawah min 1) — `quantity: 0` | BVA | 422 | PASS | – |
| CT-05 | Keranjang | `quantity = 1` (batas min) — `quantity: 1` | BVA | 200 | PASS | – |
| CT-06 | Keranjang | `quantity` negatif — `quantity: -1` | EP-I | 422 | PASS | – |
| CT-07 | Keranjang | `quantity` desimal — `quantity: 1.5` | EP-I | 422 | PASS | – |
| CT-08 | Keranjang | `quantity` tidak dikirim — `{product_id: P}` | EP-V | 200, qty default 1 | FAIL | DEF-06 |
| CT-09 | Keranjang | produk nonaktif — `status = 0` | EP-I | 400 pesan `inactive-add` | PASS | – |
| CT-10 | Keranjang | `is_buy_now` di luar {0,1} — `is_buy_now: 2` | EP-I | 422 | PASS | – |
| CT-11 | Keranjang | `PUT` qty = 0 — `{qty: {i: 0}}` | BVA | 4xx; item tetap ada | FAIL | DEF-02 |
| CT-12 | Keranjang | `PUT` qty negatif — `{qty: {i: -1}}` | EP-I | 4xx; item tetap ada | FAIL | DEF-02 |
| CT-13 | Keranjang | `PUT` qty non-angka — `{qty: {i: "abc"}}` | EP-I | 4xx; qty tidak berubah | FAIL | DEF-07 |
| CT-14 | Keranjang | `PUT` tanpa key `qty` — `{}` | EP-I | 4xx (bukan 200) | FAIL | DEF-08 |
| CT-15 | Keranjang | `DELETE selected` tanpa `ids` — `{}` | EP-I | 422 (bukan 500) | FAIL | DEF-07 |
| CT-16 | Keranjang | `DELETE` tanpa `cart_item_id` — `{}` | EP-I | 422 | PASS | – |
| CT-17 | Keranjang | `estimate-shipping-methods` tanpa cart — guest baru, data alamat valid | EP-I | 4xx (bukan 500) | FAIL | DEF-07 |
| CT-18 | Keranjang | `estimate-shipping-methods` tanpa `country` — `{state, postcode}` | EP-I | 422 | PASS | – |
| CT-19 | Keranjang | `move-to-wishlist` oleh guest — guest dengan cart berisi item | EP-I | 401/403 atau ditolak; tidak ada wishlist ber-`customer_id` null | FAIL | DEF-07 |
| CT-20 | Keranjang | Pasang kupon tanpa cart — guest baru, `POST cart/coupon {code: "X"}` | EP-I | 4xx (bukan 500) | PASS | – |
| CT-21 | Keranjang | Hapus kupon tanpa cart — guest baru, `DELETE cart/coupon` | EP-I | 4xx/200 tanpa error server (bukan 500) | FAIL | DEF-07 |
| CO-01 | Alamat checkout | semua valid — data dasar | EP-V | 200, `redirect=false`, berisi shipping methods | PASS | – |
| CO-02 | Alamat checkout | email — `"bukan-email"` | EP-I | 422 | FAIL | DEF-11 |
| CO-03 | Alamat checkout | email — kosong | EP-I | 422 | PASS | – |
| CO-04 | Alamat checkout | first_name — kosong | EP-I | 422 | PASS | – |
| CO-05 | Alamat checkout | address — `[]` (array kosong) | EP-I | 422 | PASS | – |
| CO-06 | Alamat checkout | address — `"Jl. Uji"` (string, bukan array) | EP-I | 422 | PASS | – |
| CO-07 | Alamat checkout | country — kosong | EP-I | 422 | PASS | – |
| CO-08 | Alamat checkout | phone — `"1"` (1 digit, batas min regex) | BVA | 200 | PASS | – |
| CO-09 | Alamat checkout | phone — `"+"` (0 digit) | BVA | 422 | PASS | – |
| CO-10 | Alamat checkout | phone — `"+6281234567890"` | EP-V | 200 | PASS | – |
| CO-11 | Alamat checkout | phone — `"0812-3456"` | EP-I | 422 | PASS | – |
| CO-12 | Alamat checkout | phone — `"0812 3456"` | EP-I | 422 | PASS | – |
| CO-13 | Alamat checkout | postcode — `"1"` (1 karakter, di bawah min 2) | BVA | 422 | PASS | – |
| CO-14 | Alamat checkout | postcode — `"12"` (2 karakter, batas) | BVA | 200 | PASS | – |
| CO-15 | Alamat checkout | postcode — `"1-"` (diakhiri `-`) | EP-I | 422 | PASS | – |
| CO-16 | Alamat checkout | postcode — `"12 34"` (spasi di tengah) | EP-V | 200 | PASS | – |
| CO-17 | Alamat checkout | first_name — 255 karakter | BVA | 200, tersimpan utuh 255 karakter | PASS | – |
| CO-18 | Alamat checkout | first_name — 256 karakter | BVA | 422 atau tersimpan utuh, tidak terpotong diam-diam | FAIL | DEF-12 |
| CO-19 | Alamat checkout | struktur — tanpa key `billing`, hanya `shipping` valid | EP-I | 422 (bukan 500) | FAIL | DEF-07 |
| CO-20 | Alamat checkout | struktur — `use_for_shipping: 0`, tanpa `shipping` | EP-I | 422 (sudah ada test bawaan, dipakai sebagai kontrol) | PASS | – |
| CO-21 | Alamat checkout | prasyarat — guest tanpa cart mengirim alamat valid | EP-I | 4xx/redirect (bukan 500) | FAIL | DEF-07 |
| SH-01 | Ongkir | `shipping_method` kosong — `{}` | EP-I | 422 | PASS | – |
| SH-02 | Ongkir | `flatrate_flatrate` — setelah alamat valid | EP-V | 200, daftar payment method | PASS | – |
| SH-03 | Ongkir | `free_free` — idem | EP-V | 200 | PASS | – |
| SH-04 | Ongkir | kode tidak dikenal — `"kurir_palsu"` | EP-I | 403 | PASS | – |
| SH-05 | Ongkir | sebelum alamat diisi — cart tanpa alamat, `flatrate_flatrate` | EP-V | 200 (tarif flat rate memang tersedia tanpa alamat); pesanan tanpa alamat tetap ditolak. Hasil yang diharapkan dikoreksi saat ekse… | PASS | – |
| SH-06 | Ongkir | ongkir per unit — qty 1 → 10; qty 3 → 30 | BVA | `shipping_amount` = 10 × qty | PASS | – |
| OR-01 | Pesanan | checkout lengkap — alamat + `flatrate_flatrate` + `cashondelivery` | EP-V | Order dibuat, cart nonaktif | PASS | – |
| OR-02 | Pesanan | tanpa payment — alamat + shipping, tanpa payment | EP-I | Order tidak dibuat; status 4xx dengan pesan `specify-payment-method` | FAIL | DEF-09 |
| OR-03 | Pesanan | customer `is_suspended = 1` — checkout lengkap | EP-I | Order tidak dibuat | PASS | – |
| MO-01 | Minimum order | minimum order: subtotal = min − 0,01 — `enable=1`, `minimum_order_amount=100`, subtotal 99,99 | BVA | Ditolak | PASS | – |
| MO-02 | Minimum order | subtotal = min — subtotal 100,00 | BVA | Diterima (batas inklusif `>=`, `Cart.php:817`) | PASS | – |
| MO-03 | Minimum order | subtotal = min + 0,01 — 100,01 | BVA | Diterima | PASS | – |
| MO-04 | Minimum order | opsi `include_discount_amount=1`, pajak > 0, tanpa diskon — subtotal 100, pajak 10, min 100 | EP | Jumlah pembanding = 100 (subtotal − diskon 0), sehingga diterima. Bila kode mengurangi pajak (`Cart.php:797-798`), pembanding = 9… | FAIL → **PASS** (v1.0.1) | DEF-03 |
| MO-05 | Minimum order | minimum pecahan — `minimum_order_amount=100.50`, subtotal 100,25 | BVA | Minimum tidak terpenuhi (REQ-CO-05). Config bertipe `numeric` (`Admin/src/Config/system.php:2359`) sehingga desimal sah, sedangka… | FAIL | DEF-04 |
| AU-01 | Autentikasi | registrasi valid — nama, email baru, password 6 + konfirmasi | EP-V | Akun dibuat, redirect | PASS | – |
| AU-02 | Autentikasi | password 5 karakter — `"12345"` | BVA | 422 `password` | PASS | – |
| AU-03 | Autentikasi | password 6 karakter (batas) — `"123456"` | BVA | Akun dibuat | PASS | – |
| AU-04 | Autentikasi | konfirmasi berbeda — `password_confirmation` ≠ | EP-I | 422 | PASS | – |
| AU-05 | Autentikasi | email tidak valid — `"abc@"` | EP-I | 422 | PASS | – |
| AU-06 | Autentikasi | email sudah terdaftar (channel sama) — email A | EP-I | 422 `unique` | PASS | – |
| AU-07 | Autentikasi | first_name kosong — — | EP-I | 422 | PASS | – |
| AU-08 | Autentikasi | login valid — email + password benar | EP-V | Login, redirect home | PASS | – |
| AU-09 | Autentikasi | password salah — — | EP-I | Tidak login, flash `invalid-credentials` | PASS | – |
| AU-10 | Autentikasi | password 5 karakter — — | BVA | 422 | PASS | – |
| AU-11 | Autentikasi | akun `status = 0` — kredensial benar | EP-I | Tidak login, warning `not-activated` | PASS | – |
| AU-12 | Autentikasi | akun `is_verified = 0` — kredensial benar | EP-I | Tidak login, info `verify-first` | PASS | – |
| AU-13 | Autentikasi | API login valid — `POST api/customer/login` | EP-V | 200 | PASS | – |
| AU-14 | Autentikasi | API login salah — — | EP-I | 403 | PASS | – |
| AU-15 | Autentikasi | percobaan login salah berulang — 10× password salah (web dan API) | BVA | Setelah N percobaan, permintaan berikutnya ditolak (429) | FAIL | DEF-15 |
| AU-16 | Autentikasi | API login customer channel lain — customer `channel_id` = channel ke-2 | EP-I | 403, sama dengan web | FAIL | DEF-14 |
| PR-01 | Harga (admin) | bilangan bulat — `"150000"` | EP-V | Tersimpan 150000 | PASS | – |
| PR-02 | Harga (admin) | batas bawah 0 — `"0"` | BVA | Diterima (`Decimal` lolos); dicatat untuk konfirmasi kebutuhan | FAIL | DEF-05 |
| PR-03 | Harga (admin) | negatif — `"-1"` | EP-I | 422 | PASS | – |
| PR-04 | Harga (admin) | 4 desimal (batas) — `"1.1234"` | BVA | Tersimpan 1.1234 | PASS | – |
| PR-05 | Harga (admin) | 5 desimal — `"1.12345"` | BVA | 422 | PASS | – |
| PR-06 | Harga (admin) | non-angka — `"abc"` | EP-I | 422 | PASS | – |
| PR-07 | Harga (admin) | kosong (required) — `""` | EP-I | 422 | PASS | – |
| PR-08 | Harga (admin) | tepat kapasitas — `"99999999.9999"` | BVA | Tersimpan persis | PASS | – |
| PR-09 | Harga (admin) | kapasitas + 0,0001 — `"100000000"` | BVA | 422, atau tersimpan persis 100000000 (REQ-PR-03 [asumsi]); tidak boleh menjadi 99999999.9999 | FAIL → **PASS** (v1.0.2) | DEF-01 |
| PR-10 | Harga (admin) | `special_price` = `price` — price 100, special 100 | BVA | 422 | PASS | – |
| PR-11 | Harga (admin) | `special_price` = `price` − 0,0001 — special 99.9999 | BVA | Diterima | PASS | – |
| PR-12 | Harga (admin) | `special_price` > `price` — special 101 | EP-I | 422 | PASS | – |
| PR-13 | Harga (admin) | `special_price_to` < `special_price_from` — to = from − 1 hari | EP-I | 422 | PASS | – |
| PR-14 | Harga (admin) | `cost` negatif — `"-5"` | EP-I | 422 | PASS | – |

Keterangan teknik: **EP-V** = partisi valid, **EP-I** = partisi tidak valid, **BVA** = nilai batas, **EP** = partisi yang hasilnya bergantung interpretasi kebutuhan.

### 5.3 Test case manual lewat halaman web
Dijalankan di server uji dengan database salinan `bagisto_uji`. Screenshot setiap kasus ada di [laporan daring](https://abdisrz.github.io/pdps/#sbukti), bagian 10.

| ID | Skenario | Hasil yang diharapkan | Hasil aktual | Status |
|---|---|---|---|---|
| W-02 | Login pelanggan dengan password salah | Ditolak | Tetap di halaman login | PASS |
| W-03 | Login pelanggan dengan password benar | Masuk ke akun | Halaman profil tampil | PASS |
| W-04 | Membuka pesanan milik sendiri (#8) | Detail tampil | Detail tampil | PASS |
| W-05 | Membuka pesanan milik pelanggan lain (#1) | 404 | "404 Halaman Tidak Ditemukan" | PASS |
| W-06 | Membeli 6 barang, stok 5 | Ditolak | "Jumlah yang diminta tidak tersedia" | PASS |
| W-07 | Membeli 5 barang, stok 5 | Berhasil | Masuk keranjang | PASS |
| W-08 | Melihat harga produk Rolex (data sebelum perbaikan) | Harga asli ≈ Rp224 juta | Tampil Rp100.000.000,00 | FAIL (DEF-01) |
| W-09 | Admin mengisi harga 100.000.000 (sesudah v1.0.2) | Ditolak dengan pesan | "must not be greater than 99999999.9999" | PASS |
| W-21 | Pendaftaran dengan password 5 karakter | Ditolak | "minimal mengandung 6 karakter" | PASS |
| W-22 | Pendaftaran dengan email yang sudah terdaftar | Ditolak | "email sudah digunakan" | PASS |
| W-23 | Pendaftaran dengan data valid | Akun dibuat | "Akun berhasil dibuat" | PASS |
| W-24 | Login akun nonaktif | Ditolak | "memerlukan persetujuan admin untuk aktivasi" | PASS |
| W-25 | Pencarian "iphone" | Hasil relevan tampil | Hasil tampil; batas filter harga Rp100.000.000 (efek DEF-01) | PASS |
| W-26 | Halaman keranjang | Barang dan subtotal tampil | Tampil | PASS |
| W-27 | Form checkout dengan email "bukan-email" | Ditolak | Ditolak oleh pengecekan di browser | PASS* |
| W-28 | Form checkout dengan kode pos 1 karakter | Ditolak | "Kode Pos is not valid" | PASS |
| W-29 | Alamat valid lalu klik Lanjut | Pilihan ongkir muncul | Flat Rate dan Gratis Ongkir tampil | PASS |
| W-30 | Memilih ongkir | Pilihan pembayaran muncul | COD dan Money Transfer tampil | PASS |
| W-31 | Memilih COD lalu membuat pesanan | Pesanan dibuat | "ID pesanan Anda adalah #41" | PASS |
| W-32 | Riwayat pesanan pelanggan | Daftar pesanan sendiri | Tampil | PASS |
| W-33 | Mencetak invoice milik pelanggan lain | 404 | 404 | PASS |
| W-34 | Admin login dengan password salah | Ditolak | "Please check your credentials" | PASS |
| W-35 | Dashboard admin | Ringkasan tampil | Tampil, termasuk pesanan #41 | PASS |
| W-36 | Admin: harga diskon = harga normal | Ditolak | "special price … must be less than" | PASS |
| W-37 | Admin: harga negatif | Ditolak | "The price must be valid" | PASS |
| W-38 | Admin: harga normal disimpan | Berhasil | "Product updated successfully" | PASS |
| W-39 | Daftar pesanan admin | Total pesanan sesuai harga barang | Pesanan #36 bertotal tepat IDR 100.000.000 | FAIL (DEF-01) |
| W-40 | Detail pesanan admin | Tampil | Tampil | PASS |

\* W-27 lolos di halaman web, tetapi skenario yang sama lewat API (API-10) **diterima**. Artinya pengecekan email hanya ada di tampilan, tidak di server (DEF-11).

### 5.4 Test case manual API (VS Code REST Client)
File: [`dokumentasi/uji-api/pengujian-api.http`](https://github.com/AbdisrZ/pdps/blob/praktikum/dokumentasi/uji-api/pengujian-api.http).

| ID | Request | Hasil yang diharapkan | Hasil aktual | Status |
|---|---|---|---|---|
| API-01 | `POST /api/checkout/cart` jumlah 6 (stok 5) | 400 | 400 "Jumlah yang diminta tidak tersedia" | PASS |
| API-02 | `POST /api/checkout/cart` jumlah 5 | 200 | 200 | PASS |
| API-03 | `POST /api/checkout/cart` tanpa `quantity` | 200, jumlah 1 | 400 `Undefined array key "quantity"` | FAIL (DEF-06) |
| API-04 | `GET /api/checkout/cart` (sebelum) | Keranjang berisi barang | Berisi barang | PASS |
| API-05 | `PUT /api/checkout/cart` jumlah 0 | 4xx, barang tetap | 200 "Jumlah tidak boleh kurang dari satu" | FAIL (DEF-02, DEF-08) |
| API-06 | `GET /api/checkout/cart` (sesudah) | Barang masih ada | `"data": null` (barang terhapus) | FAIL (DEF-02) |
| API-07 | `POST …/estimate-shipping-methods` tanpa keranjang | 4xx | 500 | FAIL (DEF-07) |
| API-08 | `DELETE /api/checkout/cart/coupon` tanpa keranjang | Bukan 500 | 500 | FAIL (DEF-07) |
| API-09 | `POST /api/checkout/cart` jumlah 1 (persiapan) | 200 | 200 | PASS |
| API-10 | `POST …/onepage/addresses` email "bukan-email" | 422 | 200 (diterima) | FAIL (DEF-11) |
| API-11 | `POST …/onepage/addresses` kode pos "1" | 422 | 422 | PASS |
| API-12 | `POST /api/customer/login` password salah | 403 | 403 | PASS |

## 6. Daftar defect

Severity: **Tinggi** = kerugian finansial/data atau celah keamanan yang mudah dieksploitasi; **Sedang** = fungsi bisnis salah dalam kondisi wajar; **Rendah** = kesalahan respons/pesan tanpa kehilangan data. Prioritas: **P1** = segera, **P2** = siklus berikutnya, **P3** = bila sempat. Kolom "No. web" adalah nomor yang dipakai di laporan daring.

| ID | No. web | Judul | Modul | Severity | Prioritas | Test case | Kebutuhan dilanggar | Status |
|---|---|---|---|---|---|---|---|---|
| DEF-01 | 1 | Harga di atas 99.999.999,9999 diterima lalu tersimpan terpotong tanpa error | Harga (admin) | **Tinggi** | **P1** | PR-09, W-08, W-39 | REQ-PR-03 [asumsi] | **Diperbaiki v1.0.2** |
| DEF-02 | 5 | Ubah jumlah menjadi ≤ 0 menghapus barang secara diam-diam dan membalas 200 | Keranjang | Sedang | P2 | CT-11, CT-12, API-05/06 | REQ-CT-02 [asumsi], REQ-CT-03 | Terbuka |
| DEF-03 | 2 | Minimum order dengan opsi "hitung diskon" mengurangi pajak, bukan diskon | Checkout | Sedang | P2 | MO-04 | REQ-CO-05 | **Diperbaiki v1.0.1** |
| DEF-04 | 3 | Minimum order pecahan diabaikan karena pembulatan ke bilangan bulat | Checkout | Rendah | P3 | MO-05 | REQ-CO-05 | Terbuka |
| DEF-05 | 4 | Harga "0" lolos validasi tetapi tersimpan NULL | Harga (admin) | Sedang | P2 | PR-02 | REQ-PR-01, REQ-PR-03 | Terbuka |
| DEF-06 | 6 | Tambah ke keranjang tanpa jumlah ditolak dengan pesan PHP mentah, bukan default 1 | Keranjang | Rendah | P2 | CT-08, API-03 | REQ-CT-01 | Terbuka |
| DEF-07 | 7 | Tujuh endpoint keranjang/checkout membalas 500 untuk input/kondisi klien | Keranjang, checkout | Sedang | P2 | CT-13, CT-15, CT-17, CT-19, CT-21, CO-19, CO-21, API-07/08 | REQ-CT-03 [asumsi] | Terbuka |
| DEF-08 | 8 | Kegagalan ubah jumlah dibalas HTTP 200 | Keranjang | Rendah | P3 | CT-14 | REQ-CT-03 [asumsi] | Terbuka |
| DEF-09 | 9 | Checkout tanpa pembayaran dibalas 500, bukan 4xx | Checkout | Rendah | P3 | OR-02 | REQ-CT-03 [asumsi] | Terbuka |
| DEF-10 | 10 | Menghapus barang milik orang lain dibalas "berhasil dihapus" | Hak akses | Rendah | P3 | AZ-02 | REQ-CT-04 [asumsi] | Terbuka |
| DEF-11 | 11 | Format email alamat checkout tidak diperiksa server | Checkout | Sedang | P2 | CO-02, API-10 | REQ-CO-01 [asumsi] | Terbuka |
| DEF-12 | 12 | Teks alamat > 255 karakter tersimpan terpotong tanpa error | Checkout | Rendah | P3 | CO-18 | REQ-PR-03 (dianalogikan) | Terbuka |
| DEF-13 | 13 | Checkout menerima rujukan alamat tersimpan milik pelanggan lain (isi alamat tidak bocor) | Hak akses | Rendah | P3 | AZ-13 | REQ-AZ-03 [asumsi] | Terbuka |
| DEF-14 | 14 | Login API mengabaikan channel toko (web menolak, API menerima) | Autentikasi | Sedang | P2 | AU-16 | REQ-AU-04 [asumsi] | Terbuka |
| DEF-15 | 15 | Tidak ada pembatasan percobaan login | Autentikasi | Sedang | P2 | AU-15 | REQ-AU-03 [asumsi] | Terbuka |

### 6.1 Rincian defect yang diperbaiki

**DEF-01 — Harga terpotong (diperbaiki di v1.0.2)**
- **Langkah reproduksi:** login admin → edit produk → isi harga `100000000` → simpan → periksa harga tersimpan.
- **Diharapkan:** ditolak dengan pesan validasi, atau tersimpan persis.
- **Aktual sebelum perbaikan:** tersimpan sukses dengan nilai `99999999.9999`, tanpa peringatan.
- **Akar penyebab:** aturan validasi harga tidak punya batas atas; kolom `decimal(12,4)`; koneksi database `strict = false`.
- **Perbaikan:** aturan `numeric` + `max:99999999.9999` untuk atribut bertipe harga di form produk admin. Perubahannya 11 baris di 1 file ([commit 4dad895](https://github.com/AbdisrZ/pdps/commit/4dad89565fa36063d4fb4852ca4e86e52cfcf523)).
- **Verifikasi:** test FX2 4/7 → 7/7; PR-09 FAIL → PASS; 52 test bawaan admin produk lolos sebelum dan sesudah perbaikan.

**DEF-03 — Minimum order mengurangi pajak (diperbaiki di v1.0.1)**
- **Langkah reproduksi:** aktifkan opsi "include discount amount" pada minimum order → keranjang subtotal 100, pajak 10, tanpa diskon → hitung jumlah pembanding.
- **Diharapkan:** 100.
- **Aktual sebelum perbaikan:** 90.
- **Akar penyebab:** satu baris di `Cart::getOrderAmount()` mengurangi `tax_total` di dalam blok opsi diskon.
- **Perbaikan:** baris tersebut dihapus, 2 baris berkurang ([commit 141dcdd](https://github.com/AbdisrZ/pdps/commit/141dcdd2110258a9e8738b2d5177513f9ba32732)).
- **Verifikasi:** test FX1 3/5 → 5/5; MO-04 FAIL → PASS; test bawaan keranjang/checkout/kupon tetap 42/42, 38/38, 11/11.

Rincian ke-15 defect (prakondisi, langkah, akar penyebab per baris kode, dan bukti mentah) ada di [`lampiran-teknis/testing/07-defect-report.md`](https://github.com/AbdisrZ/pdps/blob/praktikum/lampiran-teknis/testing/07-defect-report.md).

---

# BAGIAN III — SINTESIS

## 7. Ringkasan hasil

| Indikator | Nilai |
|---|---|
| Test bawaan Bagisto (baseline) | 132 / 132 lolos |
| Test case otomatis buatan kelompok | 112 kasus: **90 lolos, 22 gagal** (identik pada 2 kali eksekusi) |
| Test case manual | 28 skenario web (26 lolos, 2 bukti defect) dan 12 request API (6 lolos, 6 gagal) |
| Defect ditemukan | **15**: 1 Tinggi, 7 Sedang, 7 Rendah |
| Defect diperbaiki | **2** (DEF-03 → v1.0.1, DEF-01 → v1.0.2) |
| Hasil regresi setelah perbaikan | Kegagalan 22 → **20**; hanya MO-04 dan PR-09 yang berubah (FAIL → PASS); 132/132 + 52/52 test bawaan tetap lolos |
| Line coverage area utama | **33,24% → 37,84%** (+4,61 poin); Controller API +9,27 poin |
| Perubahan kode Bagisto | 2 file, +11 / −2 baris |
| Bukti | 59 screenshot, log JUnit 2 run, nilai aktual per kasus (JSON), laporan coverage |

## 8. Analisis

### 8.1 Bagian yang terbukti kuat
- **Stok (12/12 lolos).** Seluruh batas stok bekerja: tepat stok diterima, stok+1 ditolak, penambahan bertahap dan perubahan jumlah tidak dapat melampaui stok, stok yang sudah dipesan ikut diperhitungkan. Temuan penting: sebelum pengujian ini, cabang kode penolakan stok **tidak pernah dijalankan** oleh 132 test bawaan Bagisto. Kini cabang itu teruji dan terbukti benar.
- **Hak akses pesanan dan keranjang.** Pelanggan lain tidak dapat membuka, membatalkan, memesan ulang, mencetak invoice, maupun mengubah keranjang milik orang lain (selalu 404 atau tidak berpengaruh).
- **Validasi formulir di halaman web.** Pendaftaran, login, kode pos, harga negatif, dan harga diskon ditolak dengan pesan yang jelas.

### 8.2 Pola defect
| Pola | Defect | Penjelasan |
|---|---|---|
| Penanganan input tidak lengkap / tidak wajar | DEF-06, 07, 08, 09 | Akses ke data yang tidak ada (keranjang kosong, field hilang) tidak diperiksa lebih dulu, sehingga muncul error server 500 atau pesan PHP mentah. Ini pola terbanyak (10 kasus uji). |
| Integritas data angka dan teks | DEF-01, 04, 05, 12 | Kombinasi validasi tanpa batas atas, tipe kolom terbatas, dan `strict = false` membuat nilai dipotong atau diubah diam-diam. Efek DEF-01 terlihat sampai ke total pesanan (#36 = IDR 100.000.000) dan filter harga. |
| Logika bisnis keliru | DEF-02, 03 | Barang terhapus padahal pesan menyatakan ditolak; perhitungan minimum order memakai komponen yang salah. |
| Validasi hanya di sisi tampilan | DEF-11 | Email tidak valid ditolak di halaman web tetapi diterima lewat API. Pengujian lewat satu jalur saja tidak cukup. |
| Keamanan dan konsistensi | DEF-10, 13, 14, 15 | Login API tidak mengikuti aturan channel seperti login web, tidak ada pembatasan percobaan login, dan referensi ID milik orang lain diterima. |

### 8.3 Analisis coverage dan keterbatasannya
- Coverage baris pada area utama naik dari 33,24% menjadi 37,84%. Kenaikan terbesar ada pada method yang diuji langsung: `CartController::store` 54% → 92% dan `update` 60% → 100%.
- **Coverage tinggi tidak menjamin bebas defect.** Validasi alamat checkout sudah 100% tertutup sejak awal, tetapi DEF-11 tetap ada karena defect-nya berupa aturan yang tidak ditulis. Kode yang tidak ada tidak dapat "tidak tertutup".
- Alat coverage yang dipakai (PCOV) hanya mengukur baris, bukan cabang. Satu baris berisi kondisi `? :` dianggap tertutup walaupun hanya satu sisinya yang dijalankan. Untuk itu cabang dianalisis secara manual (lampiran `05-whitebox.md`).

### 8.4 Risiko yang masih tersisa
| Risiko | Tingkat | Keterangan |
|---|---|---|
| 13 defect masih terbuka | Sedang | Terutama DEF-02 (barang hilang dari keranjang) dan DEF-14/15 (keamanan login) |
| Batas harga hanya di form admin | Sedang | Jalur lain (impor produk, API admin) belum dilindungi; data lama yang terpotong tidak terkoreksi otomatis |
| Modul kupon belum diuji | Sedang | Hasil pembacaan kode menunjukkan dugaan kesalahan format jam pada masa berlaku promo |
| Kebutuhan berlabel [asumsi] | Rendah–Sedang | 11 defect bergantung pada 9 kebutuhan berlabel [asumsi] yang belum dikonfirmasi pemilik sistem |
| Versi yang diuji 2.3.19 | Rendah | Sebagian defect mungkin sudah diperbaiki di versi 2.5; belum diverifikasi |

### 8.5 Evaluasi proses pengujian
- Dua kegagalan awal ternyata berasal dari kesalahan test kami sendiri, dan satu hasil yang diharapkan (SH-05) kami koreksi karena tidak diturunkan dari kebutuhan. Semuanya dicatat terbuka dan tidak dihitung sebagai defect.
- Aturan "dua kali eksekusi" terbukti berguna: semua defect konsisten, tidak ada hasil acak.
- Persiapan database terpisah mencegah kerusakan data. Perintah instalasi bawaan Bagisto (`bagisto:install`) ternyata dapat mengosongkan database utama walaupun dijalankan untuk lingkungan test.

## 9. Kesimpulan
1. Fungsi yang paling kritis bagi toko, yaitu **pengelolaan stok** dan **hak akses pesanan**, telah memenuhi kebutuhan (REQ-ST-01/02, REQ-AZ-01/02).
2. Ditemukan **15 defect**. Satu berseverity tinggi (harga terpotong), dan sebagian besar terkait penanganan input yang tidak lengkap serta integritas data angka.
3. **Dua defect telah diperbaiki** dengan perubahan kode minimal (2 file, 13 baris) dan dibuktikan melalui test yang gagal sebelum perbaikan dan lolos sesudahnya. Uji regresi menunjukkan tidak ada fungsi lain yang rusak.
4. Pengujian dari beberapa jalur (test otomatis, halaman web, dan API) saling melengkapi. DEF-11 hanya terlihat ketika hasil halaman web dibandingkan dengan API.
5. Spesifikasi kebutuhan (SRS) yang disusun menjadi acuan yang konsisten: setiap test case dan defect dapat ditelusuri ke ID kebutuhan.

---

# BAGIAN IV — TINDAKAN

## 10. Rekomendasi tindak lanjut

| No | Tindakan | Defect / area | Prioritas | Penanggung jawab yang disarankan |
|---|---|---|---|---|
| 1 | Tolak jumlah ≤ 0 sebelum menghapus item, dan kembalikan kode 4xx pada kegagalan ubah jumlah | DEF-02, DEF-08 | P1 | Pengembang modul keranjang |
| 2 | Tambahkan pengecekan keranjang kosong dan validasi field wajib (`ids`, `billing`, `quantity`) sebelum diproses | DEF-06, DEF-07 | P1 | Pengembang API storefront |
| 3 | Tambahkan aturan `email` pada validasi alamat checkout di server | DEF-11 | P1 | Pengembang checkout |
| 4 | Pasang pembatasan percobaan login (`throttle`) pada login web dan API; samakan pengecekan channel pada login API | DEF-14, DEF-15 | P1 | Pengembang autentikasi |
| 5 | Terapkan batas harga yang sama pada jalur impor produk dan API admin; koreksi data lama yang terpotong | Lanjutan DEF-01 | P2 | Pengembang katalog, admin toko |
| 6 | Pertimbangkan `strict = true` pada koneksi database, atau tambahkan aturan `max` pada semua field teks dan angka | DEF-01, DEF-12 | P2 | Arsitek sistem |
| 7 | Perbaiki penyimpanan harga "0" dan perhitungan minimum order pecahan | DEF-04, DEF-05 | P2 | Pengembang katalog dan checkout |
| 8 | Ubah respons pesanan tanpa pembayaran menjadi 4xx; perbaiki pesan hapus item milik orang lain; validasi kepemilikan alamat | DEF-09, DEF-10, DEF-13 | P3 | Pengembang storefront |
| 9 | Jalankan 8 kasus uji kupon yang sudah dirancang (termasuk dugaan format jam masa berlaku promo) | Modul kupon | P2 | Tim pengujian |
| 10 | Konfirmasi 9 kebutuhan berlabel [asumsi] kepada pemilik sistem / dosen | SRS bagian 3 | P2 | Tim pengujian |
| 11 | Tambahkan pengujian performa (k6) dan aksesibilitas (axe/Lighthouse) di server yang mewakili produksi | Non-fungsional | P3 | Tim pengujian |
| 12 | Masukkan seluruh test kelompok ke pipeline CI agar regresi otomatis terdeteksi | Semua | P3 | Tim pengembang |

---

## Lampiran

| Lampiran | Lokasi |
|---|---|
| Laporan daring dengan 59 screenshot | <https://abdisrz.github.io/pdps/> |
| Repositori dan riwayat Git (tag v1.0.0-baseline, v1.0.1, v1.0.2) | <https://github.com/AbdisrZ/pdps> |
| Rencana uji berbasis risiko | [`lampiran-teknis/testing/03-test-plan.md`](https://github.com/AbdisrZ/pdps/blob/praktikum/lampiran-teknis/testing/03-test-plan.md) |
| Desain kasus uji black-box (EP/BVA) | [`lampiran-teknis/testing/04-blackbox.md`](https://github.com/AbdisrZ/pdps/blob/praktikum/lampiran-teknis/testing/04-blackbox.md) |
| Analisis white-box dan batas metrik coverage | [`lampiran-teknis/testing/05-whitebox.md`](https://github.com/AbdisrZ/pdps/blob/praktikum/lampiran-teknis/testing/05-whitebox.md) |
| Hasil eksekusi per kasus | [`lampiran-teknis/testing/06-hasil-eksekusi.md`](https://github.com/AbdisrZ/pdps/blob/praktikum/lampiran-teknis/testing/06-hasil-eksekusi.md) |
| Laporan defect lengkap | [`lampiran-teknis/testing/07-defect-report.md`](https://github.com/AbdisrZ/pdps/blob/praktikum/lampiran-teknis/testing/07-defect-report.md) |
| Perbaikan dan uji regresi | [`lampiran-teknis/testing/08-perbaikan-regresi.md`](https://github.com/AbdisrZ/pdps/blob/praktikum/lampiran-teknis/testing/08-perbaikan-regresi.md) |
| File test otomatis | [`packages/Webkul/Shop/tests/Feature/Praktikum/`](https://github.com/AbdisrZ/pdps/tree/praktikum/packages/Webkul/Shop/tests/Feature/Praktikum) |
| File pengujian API | [`dokumentasi/uji-api/pengujian-api.http`](https://github.com/AbdisrZ/pdps/blob/praktikum/dokumentasi/uji-api/pengujian-api.http) |
| Bukti mentah (log, JSON, coverage) | [`lampiran-teknis/evidence/`](https://github.com/AbdisrZ/pdps/tree/praktikum/lampiran-teknis/evidence) |
