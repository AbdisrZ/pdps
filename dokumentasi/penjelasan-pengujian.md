# Dokumentasi Pengujian Sistem Bagisto

Mata kuliah Pengujian dan Pemeliharaan Sistem — UNDIRA

## 1. Apa yang kami uji dan kenapa

Kami menginstal **Bagisto**, aplikasi toko online open source berbasis Laravel, langsung dari GitHub (`github.com/bagisto/bagisto`), lalu menjalankannya secara lokal di laptop memakai XAMPP. Kami memilih Bagisto karena fiturnya lengkap seperti toko online sungguhan: ada login pelanggan, keranjang belanja, stok barang, checkout, ongkir, kupon, dan halaman admin untuk mengatur harga produk. Banyak bagian yang bisa dijadikan bahan latihan test case.

Versi yang kami pakai adalah **Bagisto 2.3.19**, bukan versi paling baru. Versi terbaru (2.5) membutuhkan PHP 8.4, sedangkan PHP di XAMPP kami versi 8.2. Versi 2.3.19 adalah versi terakhir yang masih berjalan di PHP 8.2.

Supaya toko terlihat "hidup", kami mengisinya dengan data contoh: sekitar 195 produk lengkap dengan gambar dan harga Rupiah, 25 pelanggan, dan 40 pesanan.

## 2. Persiapan: memisahkan database asli dan database untuk testing

Sebelum menguji, kami memastikan pengujian tidak merusak data toko yang asli.

1. **Backup database asli.** Database toko (`bagisto`) kami cadangkan dulu ke file `D:\Backup\bagisto_utama_20261108.sql`.
2. **Membuat database khusus testing.** Kami membuat database terpisah bernama `bagisto_test`. Database ini **tidak disalin** dari database asli, melainkan diisi ulang dari awal memakai data bawaan Bagisto (kategori, bahasa, mata uang, akun admin), sehingga kondisinya bersih dan sama setiap kali pengujian dimulai.
3. **Mengarahkan aplikasi ke database testing.** Kami membuat file pengaturan `.env.testing` agar setiap test otomatis memakai `bagisto_test`. Lalu kami cek ulang bahwa yang aktif saat test memang `bagisto_test`.
4. **Hal yang sempat berbahaya.** Petunjuk instalasi Bagisto menyarankan perintah `bagisto:install`. Setelah kami pelajari, perintah itu ternyata selalu membaca pengaturan database **asli** lalu mengosongkannya, walaupun sudah diberi pilihan untuk testing. Jadi perintah itu tidak kami pakai. Sebagai gantinya kami memakai perintah migrasi biasa yang hanya menyentuh `bagisto_test`.
5. **Bukti data asli aman.** Kami mencatat jumlah produk, pesanan, dan pelanggan di database asli sebelum dan sesudah semua pengujian. Hasilnya selalu sama: 195 produk, 40 pesanan, 26 pelanggan.

Setiap test berjalan di dalam "transaksi" yang otomatis dibatalkan di akhir test, jadi `bagisto_test` juga kembali kosong setelah pengujian.

## 3. Mempelajari alur sistem

Sebelum membuat test case, kami mempelajari dulu bagaimana Bagisto bekerja di bagian-bagian penting:

| Bagian | Yang kami pelajari |
|---|---|
| Login & daftar akun | Aturan password minimal 6 karakter, email harus unik, akun nonaktif atau belum diverifikasi tidak boleh masuk |
| Keranjang (cart) | Cara menambah, mengubah jumlah, dan menghapus barang, termasuk dari sisi API (`POST`, `PUT`, `DELETE` ke `api/checkout/cart`) |
| Stok barang | Barang tidak boleh dibeli melebihi stok; stok yang sudah dipesan orang lain ikut dikurangi |
| Checkout | Urutan isi alamat → pilih ongkir → pilih pembayaran → buat pesanan, beserta aturan isi alamat (nomor telepon, kode pos) |
| Hak akses | Pelanggan A seharusnya tidak bisa melihat atau mengubah keranjang dan pesanan pelanggan B |
| Harga produk (admin) | Aturan harga di halaman admin, misalnya harga diskon harus lebih kecil dari harga normal |

Kami juga menjalankan test bawaan yang sudah disediakan Bagisto. Hasilnya **132 test lolos semua**, jadi kami tahu sistemnya stabil sebelum kami uji lebih jauh.

## 4. Dari mana kami menemukan celah untuk diuji

Kami memakai tiga cara:

1. **Membaca aturan validasinya.** Contohnya, di bagian alamat checkout kami melihat field email hanya diwajibkan "harus diisi", tanpa dicek apakah formatnya benar-benar email. Dari situ muncul pertanyaan: bagaimana kalau kami isi `bukan-email`?
2. **Mencoba nilai batas.** Kalau stok barang 5, kami coba beli 4, 5, dan 6. Kalau kode pos minimal 2 karakter, kami coba 1 dan 2. Kalau kolom harga di database maksimal 99.999.999,9999, kami coba tepat di batas itu dan sedikit di atasnya.
3. **Mencoba sebagai orang lain.** Kami login sebagai pelanggan B, lalu mencoba menghapus isi keranjang, membuka pesanan, dan mencetak invoice milik pelanggan A.

Selain itu kami melihat bagian mana yang belum pernah dijalankan oleh test bawaan Bagisto. Ternyata bagian penolakan stok (misalnya "stok tidak cukup") sama sekali belum pernah dites, jadi bagian itu kami prioritaskan.

## 5. Cara kami menguji

Pengujian dilakukan dengan **test otomatis** memakai Pest (alat testing untuk Laravel). Setiap test mengirim permintaan ke aplikasi seperti yang dilakukan browser atau aplikasi lain:

- `POST` untuk menambah barang ke keranjang, mengisi alamat, login, dan mendaftar
- `PUT` untuk mengubah jumlah barang dan mengubah harga produk di admin
- `GET` untuk membuka halaman pesanan dan invoice
- `DELETE` untuk menghapus barang dari keranjang

Untuk setiap kasus kami mencatat:
- **apa yang seharusnya terjadi** (diambil dari aturan sistem, bukan dari hasil sistem)
- **apa yang benar-benar terjadi**, yaitu kode respons, pesan, dan isi database
- **status: lolos atau gagal**

Setiap hasil disimpan sebagai file bukti. Semua test kami jalankan **dua kali**, dan sebuah masalah baru kami sebut defect kalau hasilnya gagal di kedua percobaan dengan perilaku yang sama.

Total ada **112 kasus uji** yang dibagi per bagian: hak akses (14), stok (12), keranjang (21), alamat checkout (21), ongkir/pesanan/minimum order (14), login & daftar (16), dan harga produk (14).

## 6. Hasil pengujian

Dari 112 kasus, **90 lolos** dan **22 gagal**. Ke-22 kegagalan itu berasal dari **15 masalah (defect)**, karena satu masalah bisa muncul di beberapa kasus.

### 6.1 Bagian yang ternyata aman

- **Stok barang.** Semua 12 kasus lolos. Barang tidak bisa dibeli melebihi stok, termasuk saat ditambah sedikit-sedikit atau diubah jumlahnya.
- **Hak akses pesanan.** Pelanggan B tidak bisa membuka, membatalkan, memesan ulang, atau mencetak invoice pesanan milik A; sistem menjawab "tidak ditemukan" (404).
- **Isi keranjang orang lain.** Pelanggan B tidak bisa menghapus atau mengubah barang di keranjang A.
- **Aturan daftar akun & login.** Password kurang dari 6 karakter ditolak, email ganda ditolak, akun nonaktif tidak bisa masuk.

### 6.2 Masalah yang kami temukan

| No | Bagian | Yang kami temukan | Cara kami mengujinya |
|---|---|---|---|
| 1 | Harga (admin) | Harga di atas Rp99.999.999 diterima, tapi diam-diam tersimpan menjadi 99.999.999,9999 | Mengubah harga produk menjadi 100.000.000 lewat `PUT`, lalu mengecek isi database |
| 2 | Minimum order | Saat opsi "hitung dengan diskon" aktif, sistem malah mengurangi pajak, sehingga subtotal 100 + pajak 10 dihitung 90 | Menjalankan fungsi perhitungan dengan angka contoh |
| 3 | Minimum order | Minimum 100,50 dianggap terpenuhi oleh belanja 100,25 karena angka di belakang koma dibuang | Sama seperti nomor 2 |
| 4 | Harga (admin) | Harga diisi "0" diterima, tapi tersimpan kosong (NULL) | Mengubah harga menjadi 0 lalu mengecek database |
| 5 | Keranjang | Mengubah jumlah barang menjadi 0 atau −1 **menghapus barang** dari keranjang, padahal sistem membalas "berhasil" | `PUT` jumlah 0, lalu cek apakah barang masih ada |
| 6 | Keranjang | Menambah barang tanpa menyebut jumlah ditolak dengan pesan error PHP, padahal seharusnya otomatis 1 | `POST` tanpa field `quantity` |
| 7 | Keranjang & checkout | Tujuh permintaan membuat server error (500), misalnya cek ongkir tanpa keranjang, hapus kupon tanpa keranjang, dan alamat tanpa data billing | Mengirim permintaan dalam kondisi tidak lengkap |
| 8 | Keranjang | Ubah jumlah yang gagal tetap dibalas "OK" (200) | `PUT` tanpa data jumlah |
| 9 | Checkout | Buat pesanan tanpa memilih pembayaran dibalas error server (500). Pesanan memang tidak dibuat | Checkout sampai akhir tanpa memilih pembayaran |
| 10 | Hak akses | Menghapus barang milik orang lain dibalas "berhasil dihapus", padahal barangnya tidak terhapus | Login sebagai B, hapus barang milik A |
| 11 | Checkout | Email di alamat checkout tidak dicek formatnya; `bukan-email` diterima | Mengisi alamat dengan email asal |
| 12 | Checkout | Nama lebih dari 255 huruf terpotong tanpa peringatan | Mengisi nama 256 huruf |
| 13 | Hak akses | Checkout menerima nomor alamat tersimpan milik pelanggan lain. Data alamat orang lain **tidak** bocor, hanya nomornya yang tercatat | Login sebagai B, kirim nomor alamat milik A |
| 14 | Login | Akun dari toko (channel) lain ditolak lewat halaman web, tapi **berhasil masuk lewat API** | Membuat channel kedua, lalu login lewat web dan API |
| 15 | Login | Tidak ada batas percobaan login; 10 kali salah password berturut-turut tidak diblokir | Mencoba login salah 10 kali |

Catatan jujur: beberapa masalah di atas (misalnya nomor 10, 11, 14, 15) bergantung pada aturan yang **kami tetapkan sendiri**, karena Bagisto tidak punya dokumen kebutuhan resmi. Kami menandainya agar bisa dikonfirmasi ke dosen.

Selama pengujian kami juga sempat salah. Dua kegagalan awal ternyata kesalahan di test kami sendiri, bukan di sistem, dan satu harapan hasil kami koreksi karena ternyata tidak sesuai aturan sistem. Ketiganya sudah diperbaiki dan tidak dihitung sebagai defect.

## 7. Perbaikan pertama — versi v1.0.1 (minimum order)

Kami memilih memperbaiki **masalah nomor 2**.

- **Penyebab:** Di file `Cart.php`, bagian yang seharusnya mengurangi *diskon* ternyata juga mengurangi *pajak*. Sepertinya baris itu salah salin dari bagian pajak.
- **Perbaikan:** Satu baris yang salah itu dihapus.
- **Cara kami membuktikan:**
  - Sebelum diperbaiki, kami menulis test yang gagal: subtotal 100 + pajak 10 harusnya 100, tapi hasilnya 90.
  - Setelah diperbaiki, test yang sama lolos.
  - Lalu kami jalankan ulang semua test keranjang dan checkout untuk memastikan tidak ada yang rusak: 42/42, 38/38, dan 11/11 tetap lolos.
- **Di Git:** dikerjakan di branch `fix/min-order-include-discount`, lalu diberi tag **v1.0.1**.

## 8. Perbaikan kedua — versi v1.0.2 (harga produk)

Lalu kami memperbaiki **masalah nomor 1**, karena dampaknya paling besar untuk toko berbasis Rupiah: harga di atas Rp100 juta sangat mungkin terjadi, misalnya untuk jam tangan mewah atau laptop.

- **Penyebab:** Aturan harga di halaman admin hanya mengecek format angka, tanpa batas maksimal. Kolom harga di database hanya muat sampai 99.999.999,9999, dan database diatur untuk memotong angka yang terlalu besar tanpa memberi error.
- **Perbaikan:** Kami menambahkan aturan "harga maksimal 99.999.999,9999" di form produk admin. Sekarang harga yang terlalu besar ditolak dengan pesan yang jelas: *"The price field must not be greater than 99999999.9999."*
- **Cara kami membuktikan:**
  - Sebelum diperbaiki, test harga 100.000.000 gagal (tersimpan terpotong). Setelah diperbaiki, harga itu ditolak dan harga lama tidak berubah.
  - Harga normal (150.000), harga terkecil (0,0001), dan harga tepat di batas tetap bisa disimpan.
  - Kami jalankan juga 52 test bawaan untuk halaman produk admin **sebelum dan sesudah** perbaikan. Hasilnya sama-sama 52/52 lolos.
- **Di Git:** dikerjakan di branch `fix/harga-batas-maksimum`, lalu diberi tag **v1.0.2**.

## 9. Pengecekan ulang setelah kedua perbaikan

Setelah kedua perbaikan digabung, semua test dijalankan ulang:

- Test kami: kegagalan turun dari 22 menjadi **20**. Yang berubah dari gagal ke lolos hanya dua kasus yang memang diperbaiki. Tidak ada test yang sebelumnya lolos lalu menjadi gagal.
- Semua test bawaan Bagisto yang terkait tetap lolos (132/132 ditambah 52/52 test admin produk).

## 10. Riwayat Git

| Tag | Isi |
|---|---|
| `v1.0.0-baseline` | Kondisi awal Bagisto 2.3.19 tanpa perubahan kode, plus dokumen awal |
| `v1.0.1` | Perbaikan minimum order (masalah nomor 2) |
| `v1.0.2` | Perbaikan batas harga produk (masalah nomor 1) |

Semua pekerjaan ada di branch `praktikum`. Perubahan kode Bagisto secara total sangat kecil: hanya 2 file, 11 baris ditambah dan 2 baris dihapus.

> Riwayat ini masih tersimpan di komputer lokal. Untuk menaruhnya di GitHub sendiri, buat repo kosong di GitHub lalu jalankan:
> `git remote add saya <url-repo-anda>` → `git push saya praktikum --tags`

## 11. Kesimpulan

- Bagian paling penting toko (stok dan hak akses pesanan) ternyata **aman**.
- Masalah yang paling banyak berkaitan dengan **penanganan input yang tidak lengkap atau tidak wajar**: sistem sering menjawab dengan error server, bukan pesan yang jelas.
- Masalah yang paling berbahaya ada di **penyimpanan angka**: harga besar terpotong diam-diam. Masalah ini sudah kami perbaiki.
- Yang belum kami kerjakan: pengujian kupon, pengujian tampilan dengan browser otomatis, pengujian kecepatan, dan perbaikan 13 masalah lainnya.

## Lampiran

Semua file teknis (dokumen rinci, hasil per kasus, laporan coverage, dan skrip bantu) ada di folder `lampiran-teknis/`. File test ada di `packages/Webkul/Shop/tests/Feature/Praktikum/`.

## Bukti screenshot pengujian

Screenshot diambil otomatis dengan browser Chrome yang dijalankan oleh skrip (`lampiran-teknis/testing/tools/screenshot/skenario.mjs`), supaya langkahnya bisa diulang persis sama.

Untuk pengujian lewat browser, toko dijalankan di `http://127.0.0.1:8001` dengan **database salinan** `bagisto_uji`. Database ini disalin dari backup database asli, sehingga datanya sama, tetapi semua perubahan saat pengujian tidak menyentuh database asli. Kami sempat memastikan server benar-benar memakai database salinan dengan membuat akun khusus yang hanya ada di `bagisto_uji`, lalu mencoba login dengan akun itu. Di database salinan, stok produk *Red Nail Polish* kami ubah menjadi 5.

Semua gambar ada di folder `dokumentasi/screenshot/`.

| No | File | Yang diuji | Hasil |
|---|---|---|---|
| 01 | `01-beranda-toko.png` | Halaman kategori toko dengan data salinan | Berjalan normal |
| 02 | `02-login-gagal.png` | Login dengan password salah | Ditolak (sesuai) |
| 03 | `03-login-berhasil.png` | Login dengan password benar | Masuk ke akun (sesuai) |
| 04 | `04-pesanan-sendiri.png` | Membuka pesanan sendiri (#8) | Tampil (sesuai) |
| 05 | `05-pesanan-orang-lain.png` | Membuka pesanan milik orang lain (#1) | 404 tidak ditemukan (sesuai) |
| 06 | `06-stok-melebihi.png` | Beli 6 barang, stok 5 | "Jumlah yang diminta tidak tersedia" (sesuai) |
| 07 | `07-stok-cukup.png` | Beli 5 barang, stok 5 | Berhasil masuk keranjang (sesuai) |
| 08 | `08-harga-rolex-terpotong.png` | Harga Rolex (data lama, sebelum perbaikan) | Tampil Rp 100.000.000,00, padahal harga asli sekitar Rp 224 juta (masalah no. 1) |
| 09 | `09-admin-harga-terlalu-besar.png` | Admin isi harga 100.000.000 | Ditolak dengan pesan jelas (sudah diperbaiki v1.0.2) |
| 15 | `15-pest-hasil-pengujian.png` | Hasil 112 kasus uji otomatis | 91 lolos (termasuk test pengaman), 22 gagal |
| 16–17 | `16-…sebelum.png`, `17-…sesudah.png` | Perbaikan 1 (minimum order) | Sebelum: 2 gagal; sesudah: semua lolos |
| 18–19 | `18-…sebelum.png`, `19-…sesudah.png` | Perbaikan 2 (batas harga) | Sebelum: 3 gagal; sesudah: semua lolos |
| 20 | `20-pest-regresi-akhir.png` | Pengecekan ulang setelah kedua perbaikan | Test bawaan tetap lolos semua |

### Pengujian API langsung dari VS Code (REST Client)

Request API ditulis dalam file `dokumentasi/uji-api/pengujian-api.http` dan dijalankan di VS Code dengan ekstensi REST Client (klik *Send Request*). Sebelumnya, `node dokumentasi/uji-api/siapkan-sesi.mjs` dijalankan untuk menyiapkan cookie sesi dan token CSRF yang diwajibkan API Bagisto. Screenshot ada di `dokumentasi/screenshot-vscode/`; tiap request punya dua gambar: `x1.png` (request & kode status) dan `x2.png` (isi jawaban).

| Request | Screenshot | Yang diuji | Hasil |
|---|---|---|---|
| 1 | `a1`, `a2` | Tambah 6 barang (stok 5) | 400, "Jumlah yang diminta tidak tersedia" (sesuai) |
| 2 | `b1`, `b2` | Tambah 5 barang | 200 (sesuai) |
| 3 | `c1`, `c2` | Tambah tanpa `quantity` | 400 `Undefined array key "quantity"` (masalah no. 6) |
| 4 | `d1`, `d2` | Keranjang sebelum | 200, berisi 2 barang |
| 5 | `e1`, `e2` | Ubah jumlah menjadi 0 | 200 dengan pesan "Jumlah tidak boleh kurang dari satu" |
| 6 | `f1`, `f2` | Keranjang sesudah | `data: null`, barang ikut terhapus (masalah no. 5) |
| 7 | `g1`, `g2` | Cek ongkir tanpa keranjang | 500 (masalah no. 7) |
| 8 | `h1`, `h2` | Hapus kupon tanpa keranjang | 500 (masalah no. 7) |
| 9 | `i1`, `i2` | Tambah 1 barang (persiapan) | 200 |
| 10 | `j1`, `j2` | Alamat dengan email "bukan-email" | 200, diterima (masalah no. 11) |
| 11 | `k1`, `k2` | Kode pos 1 karakter (pembanding) | 422, ditolak (sesuai) |
| 12 | `l1`, `l2` | Login API password salah | 403, ditolak (sesuai) |
