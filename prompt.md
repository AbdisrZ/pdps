KONTEKS
Saya mahasiswa Teknik Informatika (UNDIRA), mata kuliah "Pengujian dan Pemeliharaan Sistem". Sistem yang saya uji adalah Bagisto 2.5.1 (Laravel) di folder ini (D:\Project Apps\pdps), Windows + PowerShell, PHP 8.2.12 ZTS (Laragon), MySQL Laragon, dijalankan dengan `php artisan serve` di 127.0.0.1:8000.

Luaran yang dinilai (semuanya harus punya bukti yang bisa diperiksa):
1. Test plan berbasis risiko; kasus black-box (equivalence partitioning + boundary value) dan white-box (branch coverage + batas metrik coverage)
2. Unit/integration test dengan hasil expected vs actual
3. Defect report yang bisa direproduksi (severity + prioritas)
4. System/acceptance test yang menelusuri kebutuhan, plus uji nonfungsional (performa API atau aksesibilitas UI)
5. Minimal dua defect/perubahan diperbaiki lalu diuji regresi, tercatat di Git (branch, commit, tag)
6. Analisis defect, coverage, dan risiko sisa; Requirement Traceability Matrix

Prioritas modul: P0 = Authorization (customer A vs order/cart item customer B), Inventory/stok, Cart, Checkout (alamat, shipping), Authentication, validasi harga produk. P1 = CartRule/kupon, Order/status. P2 = Search, UI (Playwright), accessibility (axe/Lighthouse), performance (k6).

ATURAN KERAS (jangan dilanggar)
- JANGAN menjalankan pest/phpunit/artisan migrate/bagisto:install sebelum kamu memverifikasi bahwa koneksi DB yang aktif adalah database test `bagisto_test`, bukan database utama saya. phpunit.xml TIDAK mengganti database. Verifikasi dengan `php artisan config:show database.connections.mysql` di bawah env yang sama dengan yang dipakai test, dan tampilkan nama database-nya ke saya (jangan pernah mencetak password atau isi .env).
- Sebelum menyentuh database apa pun, minta saya membuat cadangan database utama. Jangan pernah menjalankan perintah destruktif (drop, truncate, migrate:fresh, rm -rf, git reset --hard, git clean) tanpa persetujuan eksplisit saya.
- Jangan ubah kode di packages/ atau app/ (kode produk) sebelum baseline Git ditandai dan saya menyetujui defect mana yang diperbaiki. Test baru boleh ditambah hanya di folder baru: packages/Webkul/Shop/tests/Feature/Praktikum/ (sudah terikat ke ShopTestCase lewat tests/Pest.php).
- Jangan menyatakan sesuatu "bug" sebelum direproduksi minimal dua kali dan dibandingkan dengan kebutuhan. Jika perilaku aktual sesuai kebutuhan, catat PASS. Kandidat di bawah hanyalah HIPOTESIS dari pembacaan kode, bukan temuan.
- Jangan mengarang batas validasi, nama kelas, atau path. Semua angka batas harus berasal dari kode yang kamu baca; sertakan file:baris sebagai rujukan.
- Jangan pakai --parallel. Jangan mengunduh/menginstal paket baru (composer require, npm install) tanpa bertanya dulu. Tidak perlu memasang bagisto/bagisto-api (PHP saya 8.2, paket itu butuh 8.3+); API yang diuji adalah route api/checkout/* bawaan storefront.
- Semua catatan berbahasa Indonesia. Simpan semua dokumen di folder `testing/` dan semua bukti mentah (output perintah, coverage HTML, screenshot, JSON) di `evidence/`, dengan nama file yang menyebut ID kasus.
- Jika butuh keputusan saya, kumpulkan semua pertanyaan jadi satu daftar pendek (gunakan tool tanya jika ada), jangan bertanya satu-satu. Selain itu, ambil keputusan masuk akal sendiri dan catat asumsinya.

ALUR KERJA (kerjakan berurutan; berhenti dan lapor di akhir tiap fase, tunggu "lanjut" dari saya)

FASE 0 - Pengintaian (read-only)
- Catat lingkungan: versi Bagisto/Laravel/PHP/MySQL, driver coverage yang aktif (pcov/xdebug; cek build TS), isi .env.testing jika ada (tanpa kredensial), status git (bersih? ada commit?).
- Daftar test bawaan di Shop/tests (jumlah test per file; jangan jalankan dulu) -> evidence/existing-tests.txt
- Baca: ShopTestCase.php, Concerns/ShopTestBench.php (helper pembuatan produk/customer/cart), tests/Pest.php, phpunit.xml.
- Baca source untuk modul P0 dan susun peta file:baris: Shop/Http/Controllers/API/CartController.php dan OnepageController.php, Shop/Http/Requests/CartAddressRequest.php (+ Core/Rules/PhoneNumber.php, PostCode.php), Checkout/src/Cart.php (addProduct, removeItem, updateItems, setCouponCode), Product/src/Type/{Simple,Configurable,Virtual,Bundle,AbstractType}.php bagian cek stok, CartRule/src/Helpers/CartRule.php, Sales (kepemilikan order di route customer/orders), Customer (register/login rules), Admin (validasi harga produk).
- Output: testing/00-lingkungan.md dan testing/01-peta-source.md (modul -> file:baris -> aturan validasi/cabang yang ditemukan).

FASE 1 - Lingkungan aman
- Beri saya langkah persis untuk: cadangan DB utama, membuat DB kosong bagisto_test, .env.testing, aktivasi PCOV (build 8.2 x64 TS) bila belum ada. Minta saya melakukan bagian yang butuh akses di luar folder proyek (HeidiSQL, php.ini).
- Setelah saya konfirmasi, verifikasi DB aktif = bagisto_test, jalankan `php artisan bagisto:install --env=testing --skip-env-check --skip-admin-creation` (jika opsi ini tidak bekerja, beri tahu saya alternatifnya, jangan improvisasi di DB utama), lalu minta saya mengekspor dump bersih (bagisto_test_clean.sql).
- Buat commit baseline dan tag `v1.0.0-baseline` setelah saya setuju.

FASE 2 - Baseline test bawaan
- Jalankan per file (CartTest, CheckoutTest, CouponUsageLimitTest, OrdersTest, LoginPageTest, RegistrationPageTest, AccountTest, ProductTest), catat lolos/gagal/waktu ke evidence/baseline-*.txt. Kegagalan bawaan dicatat terpisah, bukan sebagai defect temuan saya.
- Buat phpunit.coverage.xml (salinan phpunit.xml dengan <source> dibatasi ke: Shop/Http/Controllers/API, Shop/Http/Requests, Checkout/src, Product/src/Type, CartRule/src/Helpers). Jalankan coverage baseline -> evidence/coverage-baseline/ dan ringkas angka statement/branch/function per direktori beserta denominator dan file yang di-scope.
- Output: testing/02-baseline.md. Tandai file/metode P0 dengan branch coverage rendah sebagai "celah" yang layak diuji.

FASE 3 - Desain pengujian (T1)
- testing/03-test-plan.md: ruang lingkup, risiko (kemungkinan x dampak), strategi, tools, lingkungan, batas.
- testing/04-blackbox.md: tabel EP + BVA per modul P0 dengan nilai batas AKTUAL dari kode (rujuk file:baris), expected result dari kebutuhan, dan kolom actual/status yang diisi nanti.
- testing/05-whitebox.md: untuk tiap fungsi P0 penting, daftar cabang (if/else/exception), kasus uji yang menutupnya, dan cabang yang tidak terjangkau (mis. cabang mati di CartController::storeCoupon pada `strlen($code)`), sebagai bahan "keterbatasan metrik coverage".
- Hipotesis defect untuk diverifikasi (JANGAN diperlakukan sebagai fakta): (D1) estimate-shipping-methods tanpa cart aktif membaca $cart->id tanpa cek null; (D2) destroy_selected tanpa 'ids' tidak divalidasi; (D3) PUT api/checkout/cart mengembalikan HTTP 200 saat error, tidak seperti store() yang 400; (D4) kupon expired/gagal pasang mendapat pesan "Coupon not found." (teks hardcode); (D5) apply/remove kupon tanpa cart; (D6) apakah Cart::removeItem/destroy/destroySelected/moveToWishlist memeriksa kepemilikan cart item (baca kodenya dulu; jika ada pengecekan, D6 gugur); (D7) email di alamat checkout hanya 'required' tanpa format email; (D8) request tanpa key billing tapi dengan shipping.
- Tambahkan hipotesis lain yang kamu temukan sendiri dari kode, ditandai jelas sebagai hipotesis.

FASE 4 - Eksekusi P0 (T2)
- Tulis test Pest di Shop/tests/Feature/Praktikum/ mengikuti pola helper di ShopTestBench dan gaya test bawaan (closure it()/test(), Pest v3). Satu file per modul: Authorization, StockCart, Checkout, Auth, ProductPrice. Pakai $this->postJson/getJson dst. Unit test boleh pakai mock; integration test harus menyentuh DB test.
- Jalankan, simpan hasil per test (expected/actual/status) ke testing/06-hasil-eksekusi.md dan evidence/. Reproduksi ulang setiap FAIL minimal dua kali.
- Hitung ulang coverage setelah test baru (evidence/coverage-after/) dan bandingkan dengan baseline per direktori.
- Tulis testing/07-defect-report.md hanya untuk FAIL yang terbukti dan bukan perilaku sesuai kebutuhan: ID, judul, modul, severity, prioritas, lingkungan (versi), prakondisi, langkah reproduksi, expected, actual, bukti, status.
- BERHENTI di sini, tampilkan ringkasan defect, dan tanya saya dua defect mana yang akan diperbaiki. Jika hampir tidak ada defect terbukti, sampaikan itu apa adanya dan beri opsi (tanya dosen soal mutasi/versi lama) tanpa memaksa atau menyisipkan bug sendiri.

FASE 5 dan seterusnya (hanya setelah saya setujui)
- Perbaikan: untuk tiap defect terpilih, buat branch fix/<nama>, tulis test merah yang mereproduksi, commit, perbaiki kode seminimal mungkin, test hijau, jalankan seluruh suite P0 + test bawaan terkait (regresi), commit dan tag v1.0.1, v1.0.2. Simpan perbandingan sebelum/sesudah dan analisis akar penyebab di testing/08-perbaikan-regresi.md.
- Lalu P1 (kupon, order), kemudian P2 (Playwright, axe/Lighthouse, k6 dengan catatan keterbatasan `artisan serve` single-process), RTM (testing/09-rtm.md), analisis risiko sisa (testing/10-risiko-sisa.md), dan ringkasan satu halaman untuk demo UAS.

FORMAT LAPORAN TIAP FASE
Ringkasan 5-10 baris: apa yang selesai, file yang dibuat, angka penting (lolos/gagal, coverage), asumsi yang kamu ambil, dan pertanyaan yang butuh jawaban saya. Jangan mengulang isi dokumen di chat.

Mulai dari FASE 0 sekarang.
