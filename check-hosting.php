<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
requireAuthentication();
requireModulePermission('*');
require_once __DIR__ . '/database/initialize.php';

$pageTitle = 'Dashboard PMO | Cek Hosting';
$user = getAuthenticatedUser();
$flashMessage = getFlashMessage();

$dataDirectory = realpath(__DIR__ . '/data') ?: __DIR__ . '/data';
$sessionDirectory = realpath(__DIR__ . '/data/sessions') ?: __DIR__ . '/data/sessions';
$databasePath = __DIR__ . '/data/pmo.sqlite';
$phpVersion = PHP_VERSION;
$pdoSqliteAvailable = extension_loaded('pdo_sqlite');
$sqlite3Available = extension_loaded('sqlite3');
$phpVersionOk = version_compare($phpVersion, '8.0.0', '>=');
$dataDirectoryWritable = is_dir($dataDirectory) && is_writable($dataDirectory);
$sessionDirectoryWritable = is_dir($sessionDirectory) && is_writable($sessionDirectory);
$databaseExists = file_exists($databasePath);
$databaseWritable = $databaseExists ? is_writable($databasePath) : $dataDirectoryWritable;

$checks = [
    [
        'label' => 'Versi PHP',
        'status' => $phpVersionOk ? 'Lulus' : 'Perlu perhatian',
        'note' => 'Versi saat ini: ' . $phpVersion . '. Minimal yang direkomendasikan adalah PHP 8.0.',
        'type' => $phpVersionOk ? 'success' : 'warning',
    ],
    [
        'label' => 'PDO SQLite',
        'status' => $pdoSqliteAvailable ? 'Aktif' : 'Tidak aktif',
        'note' => $pdoSqliteAvailable ? 'Ekstensi PDO SQLite terdeteksi dan siap dipakai.' : 'Ekstensi PDO SQLite belum aktif. Hosting harus mengaktifkannya.',
        'type' => $pdoSqliteAvailable ? 'success' : 'error',
    ],
    [
        'label' => 'SQLite3 Extension',
        'status' => $sqlite3Available ? 'Aktif' : 'Opsional',
        'note' => $sqlite3Available ? 'Ekstensi SQLite3 juga tersedia.' : 'SQLite3 tidak wajib selama PDO SQLite aktif.',
        'type' => $sqlite3Available ? 'success' : 'warning',
    ],
    [
        'label' => 'Folder data/',
        'status' => $dataDirectoryWritable ? 'Writable' : 'Tidak writable',
        'note' => 'Lokasi aplikasi: data/',
        'type' => $dataDirectoryWritable ? 'success' : 'error',
    ],
    [
        'label' => 'Folder data/sessions/',
        'status' => $sessionDirectoryWritable ? 'Writable' : 'Tidak writable',
        'note' => 'Lokasi aplikasi: data/sessions/',
        'type' => $sessionDirectoryWritable ? 'success' : 'error',
    ],
    [
        'label' => 'File database SQLite',
        'status' => $databaseWritable ? ($databaseExists ? 'Siap ditulis' : 'Bisa dibuat') : 'Tidak writable',
        'note' => 'Lokasi aplikasi: data/pmo.sqlite',
        'type' => $databaseWritable ? 'success' : 'error',
    ],
];

$blockingIssues = array_values(array_filter(
    $checks,
    static fn (array $check): bool => $check['type'] === 'error'
));

$summaryLabel = $blockingIssues === [] ? 'Hosting siap untuk SQLite' : 'Masih ada hal yang perlu dibenahi';
$summaryClass = $blockingIssues === [] ? 'health-card--controlled' : 'health-card--warning';
$summaryText = $blockingIssues === []
    ? 'Konfigurasi utama untuk PHP + SQLite sudah terpenuhi. Anda bisa lanjut upload, login, lalu verifikasi data.'
    : 'Masih ada pemeriksaan yang gagal. Selesaikan poin yang berstatus error sebelum dipakai di domain produksi.';

require __DIR__ . '/includes/header.php';
?>
<main class="main-content">
    <div class="dashboard">
        <header class="topbar">
            <div class="topbar__heading">
                <h2>Cek Hosting SQLite</h2>
                <p>Halaman ini membantu memastikan server siap menjalankan dashboard tanpa MySQL.</p>
            </div>
            <div class="topbar__controls">
                <a href="/?tab=laporan" class="action-button">Kembali ke Laporan</a>
                <a href="/logout" class="action-button">Keluar</a>
            </div>
        </header>

        <?php if ($flashMessage !== null): ?>
            <div class="alert alert--<?= htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <section class="content-grid content-grid--summary">
            <div class="stack">
                <article class="health-card <?= htmlspecialchars($summaryClass, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="health-card__header">
                        <div>
                            <p class="health-card__eyebrow">Ringkasan Pemeriksaan</p>
                            <h3><?= htmlspecialchars($summaryLabel, ENT_QUOTES, 'UTF-8'); ?></h3>
                        </div>
                        <span class="badge"><?= htmlspecialchars((string) count($checks), ENT_QUOTES, 'UTF-8'); ?> cek</span>
                    </div>
                    <p class="health-card__summary"><?= htmlspecialchars($summaryText, ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="health-card__metrics">
                        <div>
                            <strong><?= htmlspecialchars((string) count($blockingIssues), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span>Kendala blokir</span>
                        </div>
                        <div>
                            <strong><?= htmlspecialchars((string) (count($checks) - count($blockingIssues)), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span>Cek lolos / non-blokir</span>
                        </div>
                        <div>
                            <strong><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span>Pengguna aktif</span>
                        </div>
                    </div>
                </article>

                <article class="panel">
                    <div class="panel__header">
                        <div>
                            <h3 class="panel__title">Checklist Kesiapan Server</h3>
                            <p class="section-subtitle">Gunakan hasil ini setelah upload file ke hosting untuk memastikan SQLite bisa dipakai dengan aman.</p>
                        </div>
                    </div>
                    <div class="audit-list">
                        <?php foreach ($checks as $check): ?>
                            <article class="audit-item">
                                <div class="audit-item__top">
                                    <span class="badge badge--<?= htmlspecialchars($check['type'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($check['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <strong><?= htmlspecialchars($check['label'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                </div>
                                <p class="audit-item__meta"><?= htmlspecialchars($check['note'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </article>
            </div>

            <aside class="stack">
                <article class="panel">
                    <div class="panel__header">
                        <div>
                            <h3 class="panel__title">Langkah Setelah Upload</h3>
                            <p class="section-subtitle">Urutan aman untuk verifikasi di hosting.</p>
                        </div>
                    </div>
                    <div class="insight-list">
                        <div class="insight-card">
                            <span class="insight-card__label">1. Login</span>
                            <strong>Buka `/login` lalu masuk memakai akun admin yang tersedia.</strong>
                        </div>
                        <div class="insight-card">
                            <span class="insight-card__label">2. Cek hosting</span>
                            <strong>Buka halaman ini untuk memastikan SQLite, folder `data/`, dan `sessions/` siap dipakai.</strong>
                        </div>
                        <div class="insight-card">
                            <span class="insight-card__label">3. Uji CRUD</span>
                            <strong>Tambah lalu edit satu data kegiatan untuk memastikan database benar-benar bisa ditulis.</strong>
                        </div>
                    </div>
                </article>

                <article class="panel">
                    <div class="panel__header">
                        <div>
                            <h3 class="panel__title">Catatan Deploy</h3>
                            <p class="section-subtitle">Poin penting saat tetap memakai SQLite di shared hosting.</p>
                        </div>
                    </div>
                    <div class="insight-list">
                        <div class="insight-card">
                            <span class="insight-card__label">Permission</span>
                            <strong>Pastikan folder `data/` dan `data/sessions/` writable oleh PHP.</strong>
                        </div>
                        <div class="insight-card">
                            <span class="insight-card__label">Keamanan</span>
                            <strong>Akses langsung ke folder `data/` sudah diblokir oleh `.htaccess`, jadi jangan pindahkan file database ke folder publik lain.</strong>
                        </div>
                        <div class="insight-card">
                            <span class="insight-card__label">Backup</span>
                            <strong>Karena database berupa file lokal, lakukan backup berkala untuk `data/pmo.sqlite`.</strong>
                        </div>
                    </div>
                </article>
            </aside>
        </section>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
