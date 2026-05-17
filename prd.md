# Product Requirements Document

## Queenlin, Caster Schedule Application

## 1. Ringkasan Produk

**Queenlin** adalah aplikasi berbasis web yang digunakan untuk menampilkan jadwal caster event secara rapi, cepat, dan mudah dibaca oleh pengunjung. Aplikasi ini membantu pengunjung melihat tanggal mana yang masih kosong, tanggal mana yang sudah terisi, serta detail event yang sedang atau akan berlangsung.

Saat ini, pengelolaan jadwal caster masih dilakukan secara manual melalui pengetikan jadwal di Discord. Cara tersebut kurang efisien karena pengunjung harus membaca chat atau text schedule secara manual, sementara admin juga perlu memperbarui informasi event secara berulang. Queenlin dibuat untuk menggantikan proses tersebut menjadi sistem jadwal yang lebih visual, terstruktur, dan mudah dikelola.

Aplikasi ini akan memiliki halaman publik untuk pengunjung dan halaman admin untuk mengelola event caster.

---

## 2. Tujuan Produk

Tujuan utama Queenlin adalah:

1. Memudahkan pengunjung melihat jadwal caster yang kosong maupun yang sudah terisi.
2. Menampilkan detail event secara lengkap, seperti poster, judul event, tema, map, prizepool, waktu, dan deskripsi.
3. Membantu admin menambahkan, mengubah, dan menghapus event caster melalui halaman admin.
4. Mengurangi ketergantungan terhadap pengecekan jadwal manual melalui Discord.
5. Membuat tampilan jadwal lebih modern, rapi, dan mudah dipahami dengan visual state warna.

---

## 3. Masalah yang Ingin Diselesaikan

Saat ini pengunjung yang ingin mengecek jadwal caster harus melihat teks atau jadwal manual di Discord. Masalah dari cara ini adalah:

1. Jadwal sulit dibaca jika event sudah banyak.
2. Pengunjung tidak langsung tahu tanggal mana yang kosong.
3. Informasi event bisa tercecer di chat.
4. Admin harus mengatur jadwal secara manual.
5. Tidak ada indikator visual untuk melihat apakah suatu tanggal masih longgar, cukup padat, atau sangat padat.

Queenlin menyelesaikan masalah tersebut dengan menyediakan sistem schedule berbasis web yang lebih jelas, visual, dan terpusat.

---

## 4. Target Pengguna

### 4.1 Pengunjung

Pengunjung adalah orang yang ingin melihat ketersediaan jadwal caster. Mereka biasanya ingin mengetahui:

1. Tanggal mana yang masih kosong.
2. Event apa saja yang sudah masuk pada tanggal tertentu.
3. Jam event berlangsung.
4. Detail event, seperti poster, tema, map, prizepool, dan deskripsi.
5. Apakah jadwal pada tanggal tersebut masih memungkinkan untuk booking event baru.

### 4.2 Admin

Admin adalah pihak internal yang mengelola jadwal Queenlin. Admin memiliki akses khusus untuk:

1. Login ke halaman admin.
2. Menambahkan event baru.
3. Mengedit detail event.
4. Menghapus event.
5. Mengunggah poster event.
6. Mengelola status jadwal.

---

## 5. Ruang Lingkup Produk

### 5.1 Fitur yang Masuk Scope

Queenlin akan memiliki fitur utama berikut:

1. Homepage dengan branding Queenlin.
2. Halaman Schedule.
3. Filter bulan dan tanggal.
4. Detail event berdasarkan tanggal.
5. Visual state warna untuk tingkat kepadatan jadwal.
6. Halaman admin dengan login.
7. CRUD event caster.
8. Upload poster event.
9. Footer “Managed by Squad Limpul”.
10. Tampilan hijau soft yang modern dan Gen Z-able.

### 5.2 Fitur yang Belum Masuk Scope Awal

Untuk versi awal, fitur berikut sebaiknya belum dimasukkan agar development lebih cepat:

1. Sistem booking otomatis oleh visitor.
2. Payment gateway.
3. Multi-role selain admin.
4. Notifikasi WhatsApp otomatis.
5. Integrasi langsung ke Discord.
6. Kalender sinkron dengan Google Calendar.
7. Sistem approval event.

Fitur tersebut bisa masuk ke versi berikutnya setelah versi awal stabil.

---

## 6. Struktur Halaman

## 6.1 Homepage

Homepage adalah halaman utama aplikasi Queenlin.

### Tujuan Homepage

Homepage bertujuan untuk memperkenalkan Queenlin sebagai platform pengecekan jadwal caster yang mudah, rapi, dan visual.

### Konten Homepage

Homepage minimal berisi:

1. Nama aplikasi: **Queenlin**
2. Tagline, contoh:
   **“Check caster schedules faster, cleaner, and easier.”**
3. Deskripsi singkat aplikasi.
4. Tombol utama: **Lihat Jadwal**
5. Section ringkas tentang fungsi Queenlin.
6. Footer: **Managed by Squad Limpul**

### Gaya Visual Homepage

Tema visual menggunakan warna hijau soft dengan nuansa modern, clean, dan Gen Z-able. Tampilan tidak boleh terlalu formal, tetapi tetap rapi dan mudah digunakan.

Contoh arah visual:

1. Background hijau muda atau gradient soft green.
2. Card putih atau hijau sangat muda.
3. Button hijau solid.
4. Aksen orange dan merah untuk indikator kepadatan jadwal.
5. Font modern dan rounded.

---

## 6.2 Schedule Page

Halaman Schedule adalah fitur utama Queenlin.

### Tujuan Schedule Page

Pengunjung dapat melihat tanggal kosong, tanggal yang sudah terisi event, serta detail event pada tanggal tertentu.

### Komponen Schedule Page

Halaman ini terdiri dari:

1. Pilihan bulan.
2. Pilihan tanggal.
3. Kalender atau date list.
4. Indikator warna pada tanggal.
5. Daftar event pada tanggal yang dipilih.
6. Detail event.
7. Empty state jika tanggal belum memiliki event.

### Data yang Ditampilkan pada Event

Setiap event minimal menampilkan:

1. Poster event.
2. Judul event.
3. Tema event.
4. Nama map event.
5. Prizepool event.
6. Tanggal event.
7. Jam mulai.
8. Jam selesai.
9. Deskripsi event.
10. Status kepadatan jadwal.

### Jam Operasional Event

Event biasanya berlangsung dari:

**15.00 WIB sampai 00.00 WIB**

Sistem perlu menggunakan rentang waktu ini sebagai acuan utama dalam menampilkan jadwal.

---

## 7. Visual State Jadwal

Visual state digunakan agar pengunjung cepat memahami tingkat kepadatan jadwal tanpa harus membaca semua detail event satu per satu.

### 7.1 State Kosong

**Warna:** Hijau atau netral
**Makna:** Belum ada event pada tanggal tersebut.
**Label:** Kosong / Available

### 7.2 State Lumayan Banyak

**Warna:** Orange
**Makna:** Sudah ada beberapa event, tetapi jadwal belum terlalu padat.
**Label:** Lumayan Padat

### 7.3 State Padat

**Warna:** Merah
**Makna:** Event pada tanggal tersebut sudah banyak atau jadwal cukup ketat.
**Label:** Padat

### Catatan Kritis

Jangan hanya menentukan warna berdasarkan jumlah event, karena satu event bisa berlangsung lama. Lebih akurat jika warna ditentukan berdasarkan total durasi event dalam rentang 15.00 sampai 00.00.

Contoh aturan yang lebih logis:

| Kondisi        | Total Durasi Event | Warna      | Status        |
| -------------- | -----------------: | ---------- | ------------- |
| Kosong         |              0 jam | Hijau      | Kosong        |
| Ringan         |     1 sampai 2 jam | Hijau muda | Tersedia      |
| Lumayan banyak |     3 sampai 5 jam | Orange     | Lumayan Padat |
| Padat          |     6 sampai 7 jam | Merah      | Padat         |

Dengan aturan ini, sistem lebih akurat daripada sekadar menghitung jumlah event.

---

## 8. Admin Page

Halaman admin berada di:

**/admin**

Halaman ini hanya bisa diakses oleh admin yang sudah login.

### 8.1 Login Admin

Admin harus login sebelum masuk ke dashboard.

Field login:

1. Email atau username.
2. Password.

Keamanan login:

1. Password harus di-hash.
2. Menggunakan CSRF protection.
3. Rate limit login untuk mencegah brute force.
4. Session timeout.
5. Halaman admin tidak boleh bisa diakses tanpa autentikasi.

---

## 8.2 Dashboard Admin

Dashboard admin berfungsi untuk mengelola event caster.

Fitur dashboard:

1. Melihat daftar semua event.
2. Filter event berdasarkan bulan dan tanggal.
3. Menambahkan event baru.
4. Mengedit event.
5. Menghapus event.
6. Melihat status kepadatan tanggal.
7. Upload poster event.

---

## 8.3 Form Tambah Event

Admin dapat menambahkan event baru melalui form.

Field yang dibutuhkan:

| Field            | Tipe            | Wajib                    |
| ---------------- | --------------- | ------------------------ |
| Judul event      | Text            | Ya                       |
| Poster event     | Image upload    | Tidak, tetapi disarankan |
| Tema event       | Text            | Ya                       |
| Nama map event   | Text            | Ya                       |
| Prizepool        | Text/Number     | Tidak                    |
| Tanggal event    | Date            | Ya                       |
| Jam mulai        | Time            | Ya                       |
| Jam selesai      | Time            | Ya                       |
| Deskripsi event  | Textarea        | Tidak                    |
| Status publikasi | Draft/Published | Ya                       |

### Validasi Form

Sistem harus melakukan validasi:

1. Judul event tidak boleh kosong.
2. Tanggal event harus valid.
3. Jam mulai tidak boleh lebih besar dari jam selesai.
4. Jam event sebaiknya berada dalam rentang 15.00 sampai 00.00 WIB.
5. Poster hanya boleh berupa file gambar.
6. Ukuran poster dibatasi, misalnya maksimal 2 MB.
7. Prizepool boleh kosong, karena tidak semua event memiliki hadiah.

---

## 9. User Flow

### 9.1 User Flow Pengunjung

1. Pengunjung membuka homepage Queenlin.
2. Pengunjung klik tombol **Lihat Jadwal**.
3. Pengunjung masuk ke halaman Schedule.
4. Pengunjung memilih bulan.
5. Pengunjung memilih tanggal.
6. Sistem menampilkan status tanggal tersebut.
7. Sistem menampilkan daftar event pada tanggal tersebut.
8. Pengunjung membaca detail event.
9. Jika tanggal kosong, pengunjung dapat mengetahui bahwa slot masih tersedia.

---

### 9.2 User Flow Admin

1. Admin membuka halaman **/admin**.
2. Admin login menggunakan akun admin.
3. Admin masuk ke dashboard.
4. Admin klik **Tambah Event**.
5. Admin mengisi detail event.
6. Admin upload poster jika ada.
7. Admin klik simpan.
8. Event tampil di halaman Schedule.
9. Visual state tanggal otomatis berubah berdasarkan kepadatan jadwal.

---

## 10. Kebutuhan Fungsional

| Kode   | Requirement                                         | Prioritas |
| ------ | --------------------------------------------------- | --------- |
| FR-001 | Sistem menampilkan homepage Queenlin                | High      |
| FR-002 | Sistem menampilkan halaman schedule                 | High      |
| FR-003 | Pengunjung dapat memilih bulan                      | High      |
| FR-004 | Pengunjung dapat memilih tanggal                    | High      |
| FR-005 | Sistem menampilkan event berdasarkan tanggal        | High      |
| FR-006 | Sistem menampilkan poster event                     | High      |
| FR-007 | Sistem menampilkan detail event                     | High      |
| FR-008 | Sistem menampilkan visual state jadwal              | High      |
| FR-009 | Admin dapat login                                   | High      |
| FR-010 | Admin dapat menambahkan event                       | High      |
| FR-011 | Admin dapat mengedit event                          | High      |
| FR-012 | Admin dapat menghapus event                         | High      |
| FR-013 | Admin dapat upload poster                           | Medium    |
| FR-014 | Sistem menampilkan footer “Managed by Squad Limpul” | Medium    |

---

## 11. Kebutuhan Non-Fungsional

| Kode    | Requirement        | Penjelasan                                        |
| ------- | ------------------ | ------------------------------------------------- |
| NFR-001 | Fast loading       | Halaman harus ringan dan cepat dibuka             |
| NFR-002 | Mobile responsive  | Tampilan harus nyaman di HP                       |
| NFR-003 | Secure admin login | Admin page harus aman                             |
| NFR-004 | Clean UI           | Tampilan harus rapi dan mudah dipahami            |
| NFR-005 | Scalable data      | Event bisa bertambah tanpa merusak performa       |
| NFR-006 | Image optimization | Poster tidak boleh membuat halaman berat          |
| NFR-007 | SEO basic          | Homepage dan schedule dapat terbaca mesin pencari |
| NFR-008 | Maintainable code  | Struktur kode mudah dikembangkan                  |

---

## 12. Rekomendasi Teknologi

### Stack Utama yang Direkomendasikan

**Backend:** Laravel
**Frontend:** Blade + Tailwind CSS + Alpine.js
**Database:** MySQL
**Authentication:** Laravel Breeze atau Laravel Fortify
**Storage:** Laravel Storage untuk poster event
**Deployment:** VPS, shared hosting premium, atau Laravel Forge jika ingin lebih rapi

### Kenapa Laravel Cocok?

Laravel cocok karena:

1. Cepat untuk membuat admin panel.
2. Sudah memiliki sistem keamanan bawaan yang kuat.
3. Mudah membuat fitur login admin.
4. Cocok untuk CRUD event.
5. Mudah mengelola upload gambar.
6. Performa cukup baik jika menggunakan cache dan query yang benar.
7. Maintenance lebih mudah dibanding membuat sistem terlalu kompleks dari awal.

### Catatan Kritis

Jangan langsung memakai stack yang terlalu berat seperti Next.js + API terpisah jika kebutuhan awalnya hanya homepage, schedule, dan admin CRUD. Itu bisa membuat development lebih lama tanpa manfaat besar di versi awal.

---

## 13. Struktur Database Awal

### Table: users

Digunakan untuk akun admin.

| Field      | Type      | Keterangan       |
| ---------- | --------- | ---------------- |
| id         | bigint    | Primary key      |
| name       | varchar   | Nama admin       |
| email      | varchar   | Email login      |
| password   | varchar   | Password hash    |
| created_at | timestamp | Waktu dibuat     |
| updated_at | timestamp | Waktu diperbarui |

---

### Table: events

Digunakan untuk menyimpan event caster.

| Field       | Type         | Keterangan       |
| ----------- | ------------ | ---------------- |
| id          | bigint       | Primary key      |
| title       | varchar      | Judul event      |
| poster      | varchar/null | Path poster      |
| theme       | varchar      | Tema event       |
| map_name    | varchar      | Nama map         |
| prizepool   | varchar/null | Prizepool        |
| event_date  | date         | Tanggal event    |
| start_time  | time         | Jam mulai        |
| end_time    | time         | Jam selesai      |
| description | text/null    | Deskripsi event  |
| status      | enum         | draft/published  |
| created_by  | bigint       | Admin pembuat    |
| created_at  | timestamp    | Waktu dibuat     |
| updated_at  | timestamp    | Waktu diperbarui |

---

## 14. Acceptance Criteria

Produk dianggap berhasil pada versi pertama jika:

1. Pengunjung dapat membuka homepage Queenlin.
2. Pengunjung dapat membuka halaman schedule.
3. Pengunjung dapat memilih bulan dan tanggal.
4. Sistem menampilkan jadwal kosong jika tidak ada event.
5. Sistem menampilkan detail event jika tanggal sudah terisi.
6. Tanggal dengan jadwal padat memiliki indikator warna.
7. Admin dapat login ke halaman /admin.
8. Admin dapat menambahkan event baru.
9. Event yang ditambahkan admin muncul di halaman schedule.
10. Admin dapat mengedit dan menghapus event.
11. Footer “Managed by Squad Limpul” muncul di halaman publik.
12. Tampilan nyaman digunakan di desktop dan mobile.

---

## 15. MVP Version

Versi awal Queenlin sebaiknya fokus pada fitur berikut:

1. Homepage.
2. Schedule page.
3. Filter bulan dan tanggal.
4. Detail event.
5. Admin login.
6. CRUD event.
7. Upload poster.
8. Visual state jadwal.
9. Footer branding.

Ini sudah cukup untuk menggantikan sistem manual Discord dan menjadi produk yang bisa langsung dipakai.

---

## 16. Future Improvement

Setelah MVP stabil, fitur lanjutan yang bisa dikembangkan:

1. Booking request dari visitor.
2. Form request event.
3. Integrasi Discord webhook.
4. Export jadwal ke PDF.
5. Reminder event.
6. Notifikasi WhatsApp.
7. Google Calendar sync.
8. Multi-admin.
9. Statistik event per bulan.
10. Public share link per event.