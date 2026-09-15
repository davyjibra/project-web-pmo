<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
requireAuthentication();
requireModulePermission('milestones');
require_once __DIR__ . '/config/audit.php';
require_once __DIR__ . '/config/pic.php';
require_once __DIR__ . '/database/initialize.php';

$pdo = getDatabaseConnection();
$user = getAuthenticatedUser();
$pageTitle = 'Dashboard PMO | Tambah Milestone';
$errorMessage = '';
$flashMessage = getFlashMessage();
$allowedStatuses = ['Belum Dimulai', 'Sedang Berjalan', 'Dalam Review', 'Selesai', 'Menunggu'];
$picOptions = getPicNames($pdo);

$formData = [
    'name' => '',
    'target_date' => date('Y-m-d', strtotime('+7 days')),
    'status' => 'Belum Dimulai',
    'pic' => '',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Permintaan tidak valid. Silakan muat ulang halaman lalu coba lagi.';
    } else {
        foreach ($formData as $key => $value) {
            $formData[$key] = trim((string) ($_POST[$key] ?? $value));
        }

        if (
            $formData['name'] === '' ||
            $formData['pic'] === '' ||
            $formData['target_date'] === '' ||
            !in_array($formData['status'], $allowedStatuses, true) ||
            !in_array($formData['pic'], $picOptions, true)
        ) {
            $errorMessage = 'Mohon lengkapi data milestone dengan benar.';
        } elseif (!canManageScopedRecord('milestones', $formData['pic'])) {
            $errorMessage = 'Akun Anda tidak memiliki izin untuk menambah milestone pada PIC ini.';
        } else {
            $insertStatement = $pdo->prepare(
                'INSERT INTO milestones (name, target_date, status, pic, created_at, updated_at)
                 VALUES (:name, :target_date, :status, :pic, :created_at, :updated_at)'
            );

            $timestamp = date('Y-m-d H:i:s');
            $insertStatement->execute([
                ':name' => $formData['name'],
                ':target_date' => $formData['target_date'],
                ':status' => $formData['status'],
                ':pic' => $formData['pic'],
                ':created_at' => $timestamp,
                ':updated_at' => $timestamp,
            ]);

            writeAuditLog($pdo, 'Milestone', 'create', $formData['name'], [
                'target_date' => $formData['target_date'],
                'status' => $formData['status'],
                'pic' => $formData['pic'],
            ]);

            setFlashMessage('success', 'Milestone baru berhasil ditambahkan.');
            header('Location: /milestones/create');
            exit;
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<main class="main-content">
    <div class="dashboard dashboard--narrow">
        <header class="topbar">
            <div class="topbar__heading">
                <h2>Tambah Milestone</h2>
                <p>Tambahkan target penting untuk linimasa implementasi proyek.</p>
            </div>
            <div class="topbar__controls">
                <a href="/#linimasa-teknis" class="action-button">Kembali ke Dashboard</a>
                <a href="/logout" class="action-button">Keluar</a>
            </div>
        </header>

        <?php if ($flashMessage !== null): ?>
            <div class="alert alert--<?= htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert--error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="content-grid content-grid--form-aside">
            <section class="form-card form-card--highlight">
                <div class="panel__header">
                    <div>
                        <h3 class="panel__title">Data Milestone</h3>
                        <p class="section-subtitle">Tambahkan milestone utama untuk menjaga fokus target implementasi dan kesiapan go-live.</p>
                    </div>
                </div>
                <form method="post" class="task-form task-form--compact">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <label class="form-field form-field--full">
                        <span>Nama Milestone</span>
                        <input type="text" name="name" value="<?= htmlspecialchars($formData['name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Contoh: Uji integrasi pembayaran selesai" required>
                        <small class="form-helper">Buat nama milestone yang singkat, jelas, dan mudah dipresentasikan di rapat.</small>
                    </label>
                    <label class="form-field">
                        <span>Tanggal Target</span>
                        <input type="date" name="target_date" value="<?= htmlspecialchars($formData['target_date'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Gunakan target tanggal yang benar-benar menjadi titik kendali proyek.</small>
                    </label>
                    <label class="form-field">
                        <span>Status</span>
                        <select name="status">
                            <?php foreach ($allowedStatuses as $status): ?>
                                <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"<?= $formData['status'] === $status ? ' selected' : ''; ?>><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-helper">Status milestone dipakai untuk memberi gambaran kesiapan teknis secara cepat.</small>
                    </label>
                    <label class="form-field form-field--full">
                        <span>PIC</span>
                        <select name="pic" required>
                            <option value="">Pilih PIC</option>
                            <?php foreach ($picOptions as $picOption): ?>
                                <option value="<?= htmlspecialchars($picOption, ENT_QUOTES, 'UTF-8'); ?>"<?= $formData['pic'] === $picOption ? ' selected' : ''; ?>><?= htmlspecialchars($picOption, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-helper">PIC diambil dari master untuk menghindari typo dan duplikasi nama.</small>
                    </label>
                    <div class="form-actions">
                        <button type="submit" class="action-button action-button--primary">Simpan Milestone</button>
                        <?php if (canManageModule('*')): ?>
                        <a href="/pics/create" class="action-button">Tambah PIC Baru</a>
                        <?php endif; ?>
                        <a href="/#linimasa-teknis" class="action-button">Batal</a>
                    </div>
                </form>
            </section>

            <aside class="panel panel--soft">
                <div class="panel__header">
                    <div>
                        <h3 class="panel__title">Panduan Milestone</h3>
                        <p class="section-subtitle">Diinput oleh <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>.</p>
                    </div>
                </div>
                <div class="insight-list">
                    <div class="insight-card">
                        <span class="insight-card__label">Fokus</span>
                        <strong>Masukkan hanya target yang benar-benar menjadi checkpoint penting implementasi.</strong>
                    </div>
                    <div class="insight-card">
                        <span class="insight-card__label">Kegunaan</span>
                        <strong>Milestone ini akan tampil di Ringkasan Proyek dan tab Linimasa Teknis.</strong>
                    </div>
                    <div class="insight-card">
                        <span class="insight-card__label">Praktik Baik</span>
                        <strong>Gunakan milestone yang bisa diverifikasi hasilnya, bukan aktivitas umum yang terlalu luas.</strong>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
