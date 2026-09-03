# Deploy ke Hostinger

Project ini sudah disiapkan agar URL bersih seperti `/tasks/create` dan `/system/check` tetap bisa bekerja di hosting Apache melalui file `.htaccess`.

## Upload file

1. Login ke `hPanel` Hostinger.
2. Buka `Websites` lalu pilih domain.
3. Masuk ke `File Manager`.
4. Upload seluruh isi project ini ke folder `public_html`.
5. Pastikan file `.htaccess` ikut terbawa, karena file ini penting untuk URL rapi.

## Hal penting

1. Pastikan file `.htaccess` ikut ter-upload ke `public_html`.
2. Pastikan folder `data/` ikut ter-upload.
3. Pastikan folder `data/` dan `data/sessions/` writable oleh PHP.
4. Pastikan hosting mengaktifkan `PDO_SQLITE`.

## Jika tetap pakai SQLite

Project ini saat ini menggunakan file database:

- `data/pmo.sqlite`

Kalau hosting mendukung `PDO_SQLITE`, project bisa langsung memakai file itu.

## Langkah verifikasi setelah upload

1. Buka `/login` dan masuk ke dashboard.
2. Buka `/system/check`.
3. Pastikan hasil untuk `PDO SQLite`, `data/`, `data/sessions/`, dan `data/pmo.sqlite` tidak menunjukkan error.
4. Lakukan uji tambah satu data kegiatan untuk memastikan database benar-benar bisa ditulis.
5. Buka halaman `Ringkasan Tarif TJ` dan pastikan tiga kartu grafik persen tampil normal dengan warna `hijau / kuning / merah`.
6. Pastikan blok `Status Project`, `Target Terdekat`, `PIC & Tanggung Jawab Utama`, dan `Isu Kritis Saat Ini` tampil normal.
7. Scroll ke area admin bawah untuk memastikan `Master PIC` dan `Riwayat Perubahan Terbaru` tampil normal.
8. Uji login memakai satu akun editor scoped dan satu akun `viewer` untuk memastikan permission berjalan sesuai role.

## Jika nanti pindah ke MariaDB

Gunakan project ini sebagai antarmuka, lalu sesuaikan:

- `config/database.php`

dan impor skema/data ke database hosting.

## URL yang dipakai

- `/login`
- `/logout`
- `/system/check`
- `/tasks/create`
- `/tasks/edit?id=1`
- `/milestones/create`
- `/risks/create`
- `/issues/create`
- `/issues/edit?id=1`
- `/roadmaps/create`
- `/raci/create`
- `/pics/create`
- `/pics/edit?id=1`
- `/reports/export`

## Catatan keamanan

- File `data/.htaccess` memblokir akses langsung ke database dan session file.
- File `.htaccess` juga memblokir akses langsung ke file implementation seperti `create-task.php`.
- Halaman `/system/check` membutuhkan login, jadi tidak terbuka untuk publik.
