# Dashboard PMO EIPTJ dan TAJ

Dashboard ini dibuat untuk monitoring proyek EIPTJ dan Terminal Angkutan Jalan dalam bahasa Indonesia. Aplikasi memakai PHP native + SQLite sehingga ringan untuk demo, review internal, dan siap di-upload ke shared hosting yang mendukung PHP + SQLite.

## Pilihan Proyek

Satu aplikasi mendukung dua konteks proyek yang dipilih dari sidebar:

- **EIPTJ**
- **Terminal Angkutan Jalan (TAJ)**

Pilihan proyek disimpan selama sesi pengguna. Dashboard, grafik, aktivitas,
milestone, risiko, issue log, roadmap, RACI, audit trail, dan ekspor hanya
menampilkan data dari proyek aktif. Data awal TAJ memuat 14 aktivitas periode
September-Desember 2026 serta milestone dan peta jalan hasil risalah rapat
24 Agustus 2026; PIC TAJ dibatasi pada **DISHUB** dan **JLI**.

Data EIPTJ mengikuti master data terbaru: 19 aktivitas dari sheet **Timeline
DISHUB** dan 18 aktivitas bisnis/nonteknis, lengkap dengan PIC, rentang
tanggal, progres, status, dan catatan PMO.

## Fitur Utama

- Multi-user login dengan role `Administrator`, `View Only`, dan editor scoped per instansi `DISHUB`, `TJ`, `LRTJ`, `MRTJ`, `JLI`, serta `JPAI/Jatelindo`.
- Dashboard berbasis tab dengan halaman utama yang disusun seperti briefing eksekutif.
- CRUD lengkap untuk kegiatan, milestone, risiko, roadmap, matriks RACI, issue log, dan master PIC.
- Master PIC terpusat dikelola khusus Administrator agar nama penanggung jawab konsisten.
- Filter kegiatan berdasarkan kata kunci, status, area kerja, dan PIC.
- Ringkasan utama menampilkan kondisi proyek dan jumlah aktivitas pada setiap status agar pimpinan cukup membaca halaman ringkasan.
- Audit trail perubahan data terbaru pada area admin di bagian bawah dashboard.
- Halaman cek hosting `/system/check` untuk verifikasi kesiapan PHP + SQLite setelah upload.
- URL rapi tanpa `.php` untuk alur utama.

## Struktur Data

Data saat ini disimpan di file SQLite:

- `data/pmo.sqlite`

Tabel utama:

- `tasks`
- `milestones`
- `risks`
- `roadmaps`
- `raci_matrix`
- `issue_logs`
- `audit_logs`
- `pics`

Saat aplikasi pertama kali dijalankan, file `database/initialize.php` akan membuat tabel yang dibutuhkan dan mengisi data contoh bila database masih kosong.

## Akun Demo

Gunakan akun berikut untuk pengujian:

- `adminpmo` / `JakLingko2026!`
  Akses penuh seluruh modul.
- `viewer` / `TarifTJReadOnly!`
  Hanya bisa melihat dashboard tanpa mengubah data.
- `jli` / `JLITransjakarta2026!`
  Edit data sesuai scope `JLI`.
- `tj` / `TJTransjakarta2026!`
  Edit data sesuai scope `TransJakarta / TJ`.
- `dishub` / `DishubTarif2026!`
  Edit data sesuai scope `DISHUB`.
- `lrtj` / `LRTJTarif2026!`
  Edit data sesuai scope `LRTJ`.
- `mrtj` / `MRTJTarif2026!`
  Edit data sesuai scope `MRTJ`.
- `jpai` / `JPAITarif2026!`
  Edit data sesuai scope `JPAI/Jatelindo`.

## Menjalankan Lokal

Pastikan PHP 8+ tersedia, lalu jalankan dari folder project:

```powershell
php -S localhost:8000
```

Setelah itu buka:

- `http://localhost:8000/login`

## Struktur Tampilan Dashboard

Tab utama yang tersedia:

- `Ringkasan Proyek`
- `Executive Roadmap`
- `Timeline Teknis`
- `Timeline Bisnis & Nonteknis`
- `Risk Register`

Halaman utama `Ringkasan Proyek` dirancang sebagai satu layar ringkasan untuk rapat pimpinan. Fokusnya adalah kondisi proyek dan jumlah aktivitas pada setiap kondisi agar keputusan dapat diambil tanpa membuka tabel detail.

Dua grafik donut menampilkan status keseluruhan dan komposisi area kerja. Kategori status serta area kerja dapat diarahkan kursor untuk melihat ringkasan singkat, lalu diklik untuk membuka detail aktivitas, progres, dan PIC tanpa menambah panjang halaman dashboard.

- `Status Project`
- `Target Terdekat`
- `PIC & Tanggung Jawab Utama`
- `Isu Kritis Saat Ini`

Pemetaan area kerja:

- `Teknis`: FSD, development, integrasi, SIT, UAT, PTR, dan deployment.
- `Bisnis & Nonteknis`: BRD, requirement, fare matrix, governance, kebijakan, kesiapan operator, settlement, dan komunikasi.

Area admin hanya tersedia untuk akun `Administrator` dan berisi:

- `Master PIC`
- `Riwayat Perubahan Terbaru`
- tombol `Ekspor Ringkasan`
- tombol `Cek Hosting`
- tombol `Tambah PIC`

## Cara Update Status Data

- Ringkasan utama: tidak diedit langsung, tetapi otomatis mengikuti perubahan data kegiatan, milestone, risiko, dan issue log.
- Kegiatan Bisnis & Nonteknis: buka tab `Timeline Bisnis & Nonteknis`, lalu klik `Edit` pada baris kegiatan.
- Kegiatan teknis: buka tab `Timeline Teknis`, lalu klik `Edit` pada baris kegiatan teknis.
- Milestone: buka tab `Timeline Teknis`, lalu klik `Edit` pada baris milestone.
- PIC: Administrator membuka area `Master PIC`, lalu tambah atau edit daftar PIC yang dipakai di dropdown.
- Risiko: buka tab `Risk Register`, lalu klik `Edit`.
- Issue log: buka tab `Risk Register`, lalu gunakan tombol `Tambah Issue Log`, `Edit`, atau `Hapus`.
- Roadmap: buka tab `Executive Roadmap`, lalu klik `Edit`.
- RACI: buka tab `Timeline Bisnis & Nonteknis`, lalu scroll ke bagian matriks RACI, lalu klik `Edit`.

Setiap aksi tambah, ubah, dan hapus akan otomatis masuk ke `Riwayat Perubahan Terbaru`.

## Permission Pengguna

- `Administrator`: bisa kelola seluruh data, master PIC, export, dan halaman cek hosting.
- `View Only`: hanya bisa melihat dashboard dan tidak melihat tombol edit/hapus/tambah.
- `Editor scoped`: hanya bisa mengubah data dengan kode PIC / owner instansinya yang cocok secara tepat. Kode `TJ` tidak memberi akses ke `LRTJ` atau `MRTJ`.

Contoh:

- akun `jli` hanya bisa mengubah data yang terkait `JLI`
- akun `tj` hanya bisa mengubah data yang terkait `TJ` atau `TransJakarta`
- akun `dishub` hanya bisa mengubah data yang terkait `DISHUB`
- akun `lrtj` hanya bisa mengubah data yang terkait `LRTJ`
- akun `mrtj` hanya bisa mengubah data yang terkait `MRTJ`
- akun `jpai` hanya bisa mengubah data yang terkait `JPAI/Jatelindo`

## Master PIC

PIC pada form `Kegiatan` dan `Milestone` sekarang tidak lagi diketik manual, tetapi dipilih dari dropdown master agar konsisten.

Alurnya:

1. Tambah atau perbarui daftar PIC di area `Master PIC`.
2. Saat input atau edit kegiatan/milestone, pilih PIC dari dropdown yang tersedia.
3. Jika nama PIC diubah dari master, data kegiatan dan milestone yang memakai nama lama akan ikut diperbarui otomatis.

Field penting pada master PIC:

- `Instansi / kode PIC`
- `Nama Person / PIC`
- `Peran / Unit`

PTO hanya dipakai sebagai istilah kelompok operator transportasi publik. Pada PIC dan dashboard, pihak yang ditampilkan selalu spesifik: `Admin`, `DISHUB`, `TJ`, `LRTJ`, `MRTJ`, `JLI`, atau `JPAI/Jatelindo`.

## Hosting cPanel / Hostinger

Untuk shared hosting, project ini paling aman dipakai dengan 2 opsi:

1. Langsung upload seluruh project bila hosting mendukung PHP + SQLite dan folder `data/` bisa ditulis.
2. Tetap pakai SQLite untuk kebutuhan operasional ringan, lalu backup file database secara rutin.

Panduan deployment yang sudah disiapkan ada di:

- `DEPLOY-HOSTINGER.md`

Setelah upload, login lalu buka halaman pengecekan ini:

- `/system/check`

Halaman tersebut akan memeriksa:

- versi PHP
- ekstensi `PDO SQLite`
- permission folder `data/`
- permission folder `data/sessions/`
- kesiapan file `data/pmo.sqlite`

## Catatan Produksi

- File `.htaccess` sudah disiapkan untuk URL rapi pada Apache.
- Folder `data/` menyimpan database dan session, jadi harus writable.
- Bila akan dipakai di domain produksi, sangat disarankan mengganti akun demo dan password bawaan.
- Untuk import data riil dari Excel, langkah berikutnya yang paling masuk akal adalah menyiapkan importer agar update timeline bisa lebih cepat.
- Jika tetap memakai SQLite di hosting, lakukan backup rutin pada file `data/pmo.sqlite`.
- Jika dashboard akan dipakai oleh pimpinan atau client, fokuskan penggunaan pada halaman `Ringkasan Proyek` dan gunakan tab lain hanya saat butuh detail operasional.
