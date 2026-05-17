# Queenlin

Queenlin adalah aplikasi web untuk menampilkan dan mengelola jadwal caster event. Aplikasi ini menyediakan halaman publik untuk melihat ketersediaan jadwal, serta halaman admin untuk mengelola event, tanggal penuh, dan pengiriman jadwal ke Discord.

## Fitur

- Halaman publik untuk melihat jadwal caster.
- Kalender event dengan indikator tanggal kosong, terisi, dan penuh.
- Admin panel untuk membuat, mengubah, dan menghapus event.
- Upload poster event.
- Autentikasi admin dengan Laravel Fortify.
- Integrasi Discord untuk pengiriman jadwal bulanan dan detail event harian.
- Queue, session, cache, dan job berbasis database.

## Tech Stack

- PHP 8.3+
- Laravel 13
- Livewire 4
- Laravel Fortify
- Flux UI
- MySQL
- Vite
- Tailwind CSS
- Alpine.js
- Pest PHP

## Instalasi Lokal

Clone repository:

```bash
git clone https://github.com/USERNAME/NAMA_REPO.git
cd NAMA_REPO
```

Install dependency PHP dan JavaScript:

```bash
composer install
npm install
```

Salin file environment:

```bash
cp .env.example .env
```

Generate app key:

```bash
php artisan key:generate
```

Buat database MySQL:

```sql
CREATE DATABASE queenlin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Sesuaikan konfigurasi database di `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=queenlin
DB_USERNAME=queenlin
DB_PASSWORD=
```

Isi akun admin di `.env`:

```env
ADMIN_NAME="Queenlin Admin"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=password-yang-kuat
```

Jalankan migration dan seeder:

```bash
php artisan migrate --seed
```

Buat storage link untuk file upload:

```bash
php artisan storage:link
```

Jalankan aplikasi:

```bash
composer run dev
```

Aplikasi akan berjalan melalui Laravel server dan Vite development server.

## Build Production

Install dependency tanpa package development:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Optimasi Laravel:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Pastikan worker queue dan scheduler aktif di server production.

## Scheduler dan Queue

Project ini memakai Laravel scheduler untuk command Discord:

```bash
php artisan schedule:run
```

Cron production yang umum dipakai:

```bash
* * * * * cd /path/to/queenlin && php artisan schedule:run >> /dev/null 2>&1
```

Queue worker:

```bash
php artisan queue:work
```

Command Discord manual:

```bash
php artisan discord:dispatch-monthly-schedules --month=2026-05
php artisan discord:dispatch-event-details --date=2026-05-17
```

## Backup Database

Database utama project ini memakai MySQL. Backup database sebaiknya disimpan sebagai file `.sql.gz` dan jangan di-commit ke GitHub.

Buat folder backup:

```bash
mkdir -p database/backups
```

Backup database:

```bash
mysqldump -u DB_USERNAME -p DB_DATABASE | gzip > database/backups/queenlin-$(date +%Y%m%d-%H%M%S).sql.gz
```

Contoh jika nama database mengikuti `.env.example`:

```bash
mysqldump -u queenlin -p queenlin | gzip > database/backups/queenlin-$(date +%Y%m%d-%H%M%S).sql.gz
```

Restore backup:

```bash
gunzip < database/backups/NAMA_FILE.sql.gz | mysql -u DB_USERNAME -p DB_DATABASE
```

Contoh:

```bash
gunzip < database/backups/queenlin-20260517-103000.sql.gz | mysql -u queenlin -p queenlin
```

Jika ingin backup sebelum upload ke GitHub, jalankan:

```bash
mkdir -p database/backups
mysqldump -u queenlin -p queenlin | gzip > database/backups/queenlin-$(date +%Y%m%d-%H%M%S).sql.gz
```

File hasil backup tetap aman di lokal karena folder backup diabaikan oleh Git.

## Testing dan Formatting

Jalankan test:

```bash
php artisan test
```

Jalankan lint:

```bash
composer run lint
```

Jalankan pengecekan CI lokal:

```bash
composer run test
```

## Upload ke GitHub

Pastikan file rahasia tidak ikut masuk commit:

```bash
git status
```

Tambahkan dan commit perubahan:

```bash
git add .
git commit -m "Add project documentation"
```

Hubungkan repository jika belum ada remote:

```bash
git remote add origin https://github.com/USERNAME/NAMA_REPO.git
```

Push ke GitHub:

```bash
git branch -M main
git push -u origin main
```

## Catatan Keamanan

Jangan upload file berikut ke GitHub:

- `.env`
- file backup `.env`
- file dump database seperti `.sql` atau `.sql.gz`
- token Discord
- password database
- credentials production

Gunakan `.env.example` sebagai template konfigurasi tanpa data rahasia.
