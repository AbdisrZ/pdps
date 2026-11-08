# 00 — Lingkungan Pengujian (FASE 0, read-only)

Tanggal pengintaian: 2026-11-08. Bukti mentah: `evidence/00-lingkungan-raw.txt`.

## 1. Ringkasan versi

| Komponen | Nilai aktual | Sumber/bukti | Catatan |
|---|---|---|---|
| Bagisto | **2.3.19** | `packages/Webkul/Core/src/Core.php:31` (`BAGISTO_VERSION = '2.3.19'`), `git describe` = `v2.3.19` | **Berbeda dari prompt (2.5.1).** v2.5.x butuh PHP `^8.4` dan v2.4.x butuh `>=8.3`; v2.3.19 adalah rilis terakhir yang mendukung PHP 8.2 (`composer.json` → `"php": "^8.2"`). |
| Laravel | 11.48.0 | `php artisan --version` | Prompt tidak menyebut versi; Bagisto 2.5 memakai Laravel 13. |
| PHP | 8.2.12, ZTS, x64, Visual C++ 2019 (VS16) | `php -v`, `php -i` | Sesuai prompt (8.2.12 ZTS). |
| Stack | **XAMPP** (`C:\xampp\php\php.ini`, `C:\xampp\mysql`) | `php --ini` | **Berbeda dari prompt (Laragon).** Folder `C:\laragon` tidak ada. |
| MySQL | 8.0.44 | `select version()` | `sql_mode` global berisi `STRICT_TRANS_TABLES`, tetapi koneksi Laravel `'strict' => false` (`config/database.php:58`). Akibatnya ada di H18. |
| Pest / PHPUnit | Pest v3.8.6 / PHPUnit 11.5.50 | `composer.lock` | `vendor/bin/pest` tersedia. |
| Node | v22.21.0 | `node -v` | Playwright, axe, Lighthouse, dan k6 **belum terpasang** (relevan untuk P2). |

## 2. Driver coverage

- `php -m` tidak memuat **pcov** maupun **xdebug**, dan `C:\xampp\php\ext\` tidak berisi DLL keduanya.
- Build yang dibutuhkan untuk PCOV: **PHP 8.2, Thread Safe (TS), x64, VS16**, yaitu `php_pcov.dll` untuk `8.2-ts-vs16-x64`.
- Tanpa salah satu driver ini, FASE 2 (coverage baseline) tidak bisa dijalankan. Instalasinya butuh unduhan dan perubahan `php.ini`, jadi menunggu persetujuan di FASE 1.

## 3. Database

| Database | Collation | Jumlah tabel | Peran |
|---|---|---|---|
| `bagisto` | utf8mb4_unicode_ci | 131 | **Database utama.** Berisi data demo: 195 produk, 26 customer, 40 order. |
| `bagisto_test` | utf8mb4_0900_ai_ci | 0 | Sudah ada tetapi kosong. Calon DB test. Collation-nya berbeda dari DB utama; dicatat sebagai asumsi dan belum diubah. |

## 4. File environment

- `.env` ada. Isinya **tidak dicetak**; yang dicatat hanya keberadaan key `APP_ENV`, `DB_CONNECTION`, `SESSION_DRIVER`, `CACHE_STORE`.
- **`.env.testing` TIDAK ada.**
- `phpunit.xml` hanya menimpa `APP_ENV=testing`, `CACHE_STORE=array`, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=array`, `BCRYPT_ROUNDS=4`, dan seterusnya. **Tidak ada `DB_DATABASE`**, sesuai peringatan di prompt.
- `tests/TestCase.php:10` memakai trait **`DatabaseTransactions`**, bukan `RefreshDatabase`. Test berjalan di DB yang sedang aktif lalu di-rollback per test, dan tidak ada migrasi otomatis.

> **Risiko langsung:** dalam kondisi sekarang (tanpa `.env.testing`), menjalankan `pest` akan memakai DB utama `bagisto`. Inilah alasan aturan keras nomor 1. **Belum ada satu pun test, migrate, atau install yang dijalankan.**

## 5. Status Git

- Repo hasil `git clone --depth 1` (shallow = true), dengan `HEAD` **detached** di tag `v2.3.19` (commit `2b4d4d2`).
- Remote `origin` = `https://github.com/bagisto/bagisto.git`. Belum ada commit milik praktikan.
- Working tree bersih. Satu-satunya file untracked adalah `prompt.md` (`.env`, `vendor/`, dan `storage` di-ignore).
- Implikasi untuk FASE 1: baseline perlu dibuat di branch baru, misalnya `praktikum`, sebelum di-tag `v1.0.0-baseline`, karena HEAD detached tidak boleh dijadikan tempat commit kerja.

## 6. Test bawaan

Hitungan statis tanpa menjalankan test (bukti: `evidence/existing-tests.txt`): **437 test di 17 file** pada `packages/Webkul/Shop/tests/Feature`, ditambah 66 spec Playwright di `e2e-pw/tests`.

Semua file yang disebut prompt tersedia di v2.3.19:

| File | Test |
|---|---|
| CartTest | 42 |
| CheckoutTest | 38 |
| CouponUsageLimitTest | 11 |
| OrdersTest | 4 |
| LoginPageTest | 7 |
| RegistrationPageTest | 7 |
| AccountTest | 20 |
| ProductTest (API) | 3 |

Folder `Feature/Praktikum/` **belum ada**. Folder itu otomatis terikat ke `ShopTestCase` karena `tests/Pest.php:26` memasang `uses(ShopTestCase::class)->in('../packages/Webkul/Shop/tests')`.

## 7. Infrastruktur test yang dibaca

| File | Isi penting |
|---|---|
| `packages/Webkul/Shop/tests/ShopTestCase.php:9-11` | `extends Tests\TestCase`, `use CoreAssertions, ShopTestBench`. |
| `packages/Webkul/Shop/tests/Concerns/ShopTestBench.php:13-20` | Hanya satu helper: `loginAsCustomer(?Customer)`. Membuat customer lewat `Webkul\Faker\Helpers\Customer` lalu `actingAs`. |
| `packages/Webkul/Core/tests/Concerns/CoreAssertions.php` | `assertModelWise`, `assertPrice`, `prepareCart`, `prepareCartItem`, `prepareAddress`, `prepareOrder*`, `prepareCartRule*`, dan sebagainya. |
| `vendor/bagisto/laravel-datafaker/src/Helpers/Product.php` (v2.3.0) | `getSimpleProductFactory()` (:253; membuat inventory source 1 lalu memicu `catalog.product.update.after`), `getVirtualProductFactory` (:279), `getGroupedProductFactory` (:303), `getDownloadableProductFactory` (:328), `getBundleProductFactory` (:348), `getConfigurableProductFactory` (:375). |
| Pola test bawaan (`CartTest.php:19-80`) | `it(...)` dengan pola Arrange/Act/Assert, `ProductFaker([...])->getSimpleProductFactory()->create()`, `Cart::factory()`, `CartItem::factory()`, `cart()->setCart()`, serta fungsi `Pest\Laravel\{getJson, postJson, putJson, deleteJson}`. |

## 8. Penyimpangan dari prompt dan asumsi

1. **Versi Bagisto 2.3.19, bukan 2.5.1.** Nomor baris dan perilaku di dokumen ini mengacu pada 2.3.19. Hipotesis D1–D8 tetap bisa diuji karena kodenya ada di versi ini (lihat `01-peta-source.md`).
2. **XAMPP, bukan Laragon.** Langkah di FASE 1 (backup, `php.ini`, PCOV) akan disesuaikan dengan path XAMPP.
3. **Saya sendiri yang mengubah `C:\xampp\php\php.ini`** saat instalasi sebelumnya (mengaktifkan `intl` dan `sodium`; backup di `php.ini.bak-bagisto`). Ini dicatat sebagai bagian dari konfigurasi lingkungan.
4. **Data demo di DB utama dibuat dengan skrip impor saya (Claude).** Ada 6 produk yang harganya terpotong ke 99.999.999,9999 (lihat H18 di `01-peta-source.md` dan `evidence/H18-harga-terpotong-observasi-awal.txt`). Data ini belum diperbaiki karena FASE 0 read-only dan DB utama belum di-backup.

## 9. Keputusan praktikan (akhir FASE 0, 2026-11-08)

| Topik | Keputusan |
|---|---|
| Versi sistem yang diuji | **Tetap Bagisto 2.3.19** di XAMPP (PHP 8.2.12). Penyimpangan dari prompt (2.5.1) dicatat di dokumen ini. |
| Driver coverage | **PCOV.** Claude mengunduh `php_pcov.dll` (8.2 TS x64 VS16) ke scratchpad dan memverifikasi build-nya. Praktikan yang menyalinnya ke `C:\xampp\php\ext` dan menambahkan `extension=pcov` di `php.ini`. |
| 6 produk Rolex dengan harga terpotong di DB utama | **Dibiarkan** sebagai observasi awal H18. Akan direproduksi secara terkontrol di `bagisto_test`. |

## 10. FASE 1 — Lingkungan aman

### 10.1 `.env.testing` (dibuat Claude, 2026-11-08)
- Disalin dari `.env`. Hanya 3 key yang diubah: `APP_ENV=testing`, `DB_DATABASE=bagisto_test`, `APP_CURRENCY=USD`.
- Asumsi: `APP_CURRENCY=USD` dipilih karena USD adalah default installer, sehingga test bawaan berjalan pada konfigurasi standar. Untuk DB utama tetap IDR.
- File ini di-ignore Git (`.gitignore:6`), jadi kredensial tidak ikut ter-commit.

### 10.2 Verifikasi DB aktif (aturan keras nomor 1)
Bukti: `evidence/F1-verifikasi-db-test.txt` (baris password disaring).

| Cara memuat env | `database.connections.mysql.database` |
|---|---|
| `php artisan config:show ... --env=testing` | **bagisto_test** |
| `APP_ENV=testing php artisan config:show ...` (mekanisme yang dipakai `phpunit.xml`) | **bagisto_test** |
| tanpa env (pembanding) | bagisto (DB utama) |

`bootstrap/cache/` hanya berisi `packages.php` dan `services.php`. **Tidak ada `config.php`**, jadi `.env.testing` tidak diabaikan karena cache config.

### 10.3 Temuan: `bagisto:install --env=testing` TIDAK AMAN
`packages/Webkul/Installer/src/Console/Commands/Installer.php` selalu membaca dan menulis **`.env` utama**, apa pun nilai `--env`:
- `:190` `updateEnvVariables()` → `updateEnvVariable()` menulis ke `base_path('.env')` (`:550`, `:559`).
- `:192` `loadEnvConfigs()` → `getEnvVariable()` membaca `base_path('.env')` (`:622`) lalu `DB::purge()` dan `DB::reconnect()` ke `DB_DATABASE` dari `.env`, yaitu **`bagisto`** (`:591`, `:596`, `:602`).
- `:203` `db:wipe` lalu `:204` `migrate:fresh`, yang **akan menghapus seluruh DB utama**.

Kesimpulannya, perintah dari prompt **tidak dijalankan**. Alternatif yang diusulkan (menunggu persetujuan):
1. `php artisan migrate --env=testing --seed --seeder="Webkul\Installer\Database\Seeders\DatabaseSeeder"`. DB test masih kosong sehingga `db:wipe` tidak diperlukan. Seeder yang dipakai sama dengan yang dipanggil installer di `Installer.php:207`, dengan parameter default (locale `en`, currency dari `.env.testing`).
2. Tidak membuat admin (setara `--skip-admin-creation`). `storage:link` dan `storage/installed` sudah ada dan dipakai bersama.
3. Sebelum dan sesudah langkah 1 diambil snapshot jumlah tabel/baris di DB utama `bagisto` sebagai bukti bahwa DB utama tidak tersentuh.

### 10.4 PCOV
- Diunduh dari `https://downloads.php.net/~windows/pecl/releases/pcov/1.0.12/php_pcov-1.0.12-8.2-ts-vs16-x64.zip`.
- SHA-256 zip: `0c2fd30246cfc01d6386e0cf317405285c9c7c28a5351dc19c5154cb09402c6c`.
- SHA-256 `php_pcov.dll`: `e161745329973d6f0495d50b7f933589c0aef59b18c169b4c9097d63062af3d0`. Header PE `MZ` valid. Build cocok: 8.2 / TS / VS16 / x64.
- Catatan keterbatasan: PCOV hanya mengukur **line coverage**. Branch/path coverage di PHPUnit membutuhkan Xdebug. Ini menjadi bahan "batas metrik coverage" di `05-whitebox.md`.

### 10.5 Hasil eksekusi FASE 1 (2026-11-08)
Praktikan meminta Claude mengerjakan backup dan pemasangan PCOV sendiri, dan menyetujui alternatif installer pada §10.3.

| Langkah | Hasil | Bukti |
|---|---|---|
| Backup DB utama | `D:\Backup\bagisto_utama_20261108.sql`, 1.550.082 byte, 131 `CREATE TABLE` (= 131 tabel di DB) | `evidence/F1-backup.txt` (SHA-256) |
| Pemasangan PCOV | `C:\xampp\php\ext\php_pcov.dll` (hash sama dengan §10.4). Ditambahkan `extension=pcov`, `pcov.enabled=1`, `pcov.directory="D:\Project Apps\pdps"`, `pcov.exclude="~vendor~"`. Backup ini: `C:\xampp\php\php.ini.bak-pcov`. `php -m` sekarang memuat `pcov`. | — |
| Verifikasi ulang DB target | `bagisto_test` (0 tabel sebelum instalasi) | `evidence/F1-verifikasi-db-test.txt` |
| Instalasi DB test | `php artisan migrate --env=testing --seed --seeder="Webkul\Installer\Database\Seeders\DatabaseSeeder"`, exit 0. Hasil: 131 tabel, 167 migrasi, 1 channel, locale `en`, currency `USD`, 0 produk, 0 customer, **1 admin** (dibuat otomatis oleh `User\AdminsTableSeeder`, bukan oleh langkah pembuatan admin interaktif installer) | `evidence/F1-install-db-test.txt` |
| DB utama tidak tersentuh | Snapshot sebelum dan sesudah **identik** (products=195, orders=40, customers=26, migrations=167, cart=41, UPDATE_TIME_MAX tidak berubah) | `evidence/F1-snapshot-db-utama-{sebelum,sesudah}.txt` |
| Dump bersih DB test | `D:\Backup\bagisto_test_clean.sql`, 248.891 byte, 131 `CREATE TABLE` | `evidence/F1-backup.txt` |

Catatan:
- `mysqldump` yang tersedia adalah client MariaDB 10.4.32 (bawaan XAMPP), sementara servernya MySQL 8.0.44. Dump selesai normal (`-- Dump completed`), tetapi **uji restore belum dilakukan** karena butuh DB sementara yang lalu di-DROP, dan itu perlu izin.
- Selama test berjalan, `bagisto_test` tetap bersih karena `DatabaseTransactions` melakukan rollback per test. Dump bersih dipakai bila suatu saat ada data yang tertinggal.
