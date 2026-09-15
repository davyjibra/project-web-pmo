<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
requireAuthentication();
require_once __DIR__ . '/config/audit.php';
require_once __DIR__ . '/config/pic.php';
require_once __DIR__ . '/database/initialize.php';

$pdo = getDatabaseConnection();
$user = getAuthenticatedUser();
$pageTitle = 'Dashboard PMO | Edit Milestone';
$errorMessage = '';
$allowedStatuses = ['Belum Dimulai', 'Sedang Berjalan', 'Dalam Review', 'Selesai', 'Menunggu'];
$picOptions = getPicNames($pdo);
$milestoneId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$milestoneId) {
    setFlashMessage('error', 'Milestone yang akan diedit tidak ditemukan.');
    header('Location: /#linimasa-teknis');
    exit;
}

$statement = $pdo->prepare('SELECT * FROM milestones WHERE id = :id LIMIT 1');
$statement->execute([':id' => $milestoneId]);
$milestone = $statement->fetch();

if (!$milestone) {
    setFlashMessage('error', 'Data milestone tidak tersedia.');
    header('Location: /#linimasa-teknis');
    exit;
}

requireScopedModulePermission('milestones', (string) $milestone['pic']);

$formData = [
    'name' => (string) $milestone['name'],
    'target_date' => (string) $milestone['target_date'],
    'status' => (string) $milestone['status'],
    'pic' => (string) $milestone['pic'],
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
            $errorMessage = 'Akun Anda tidak memiliki izin untuk memindahkan milestone ke PIC ini.';
        } else {
            $updateStatement = $pdo->prepare(
                'UPDATE milestones SET
                    name = :name,
                    target_date = :target_date,
                    status = :status,
                    pic = :pic,
                    updated_at = :updated_at
                 WHERE id = :id'
            );

            $updateStatement->execute([
                ':name' => $formData['name'],
                ':target_date' => $formData['target_date'],
                ':status' => $formData['status'],
                ':pic' => $formData['pic'],
                ':updated_at' => date('Y-m-d H:i:s'),
                ':id' => $milestoneId,
            ]);

            writeAuditLog($pdo, 'Milestone', 'update', $formData['name'], [
                'target_date' => $formData['target_date'],
                'status' => $formData['status'],
                'pic' => $formData['pic'],
            ]);

            setFlashMessage('success', 'Milestone berhasil diperbarui.');
            header('Location: /#linimasa-teknis');
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
                <h2>Edit Milestone</h2>
                <p>Perbarui target penting pada linimasa teknis proyek.</p>
            </div>
            <div class="topbar__controls">
                <a href="/#linimasa-teknis" class="action-button">Kembali ke Dashboard</a>
                <a href="/logout" class="action-button">Keluar</a>
            </div>
        </header>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert--error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="content-grid content-grid--form-aside">
            <section class="form-card form-card--highlight">
                <div class="panel__header">
                    <div>
                        <h3 class="panel__title">Perbarui Milestone</h3>
                        <p class="section-subtitle">Pastikan target teknis, status, dan PIC selalu sesuai perkembangan terbaru proyek.</p>
                    </div>
                </div>
                <form method="post" class="task-form task-form--compact">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <label class="form-field form-field--full">
                        <span>Nama Milestone</span>
                        <input type="text" name="name" value="<?= htmlspecialchars($formData['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Ubah nama milestone hanya jika checkpoint yang dimonitor memang berubah.</small>
                    </label>
                    <label class="form-field">
                        <span>Tanggal Target</span>
                        <input type="date" name="target_date" value="<?= htmlspecialchars($formData['target_date'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Perbarui bila ada revisi jadwal atau keputusan penyesuaian timeline.</small>
                    </label>
                    <label class="form-field">
                        <span>Status</span>
                        <select name="status">
                            <?php foreach ($allowedStatuses as $status): ?>
                                <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"<?= $formData['status'] === $status ? ' selected' : ''; ?>><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-helper">Status terbaru akan langsung muncul di ringkasan milestone dan linimasa teknis.</small>
                    </label>
                    <label class="form-field form-field--full">
                        <span>PIC</span>
                        <select name="pic" required>
                            <option value="">Pilih PIC</option>
                            <?php foreach ($picOptions as $picOption): ?>
                                <option value="<?= htmlspecialchars($picOption, ENT_QUOTES, 'UTF-8'); ?>"<?= $formData['pic'] === $picOption ? ' selected' : ''; ?>><?= htmlspecialchars($picOption, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-helper">Pilih PIC yang benar-benar bertanggung jawab pada milestone ini.</small>
                    </label>
                    <div class="form-actions">
                        <button type="submit" class="action-button action-button--primary">Simpan Perubahan</button>
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
                        <h3 class="panel__title">Catatan Milestone</h3>
                        <p class="section-subtitle">Terakhir diubah oleh <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>.</p>
                    </div>
                </div>
                <div class="insight-list">
                    <div class="insight-card">
                        <span class="insight-card__label">Target Saat Ini</span>
                        <strong><?= htmlspecialchars((string) $milestone['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="insight-card">
                        <span class="insight-card__label">Jadwal</span>
                        <strong><?= htmlspecialchars((string) $milestone['target_date'], ENT_QUOTES, 'UTF-8'); ?> dengan status <?= htmlspecialchars((string) $milestone['status'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="insight-card">
                        <span class="insight-card__label">Dampak</span>
                        <strong>Perubahan milestone akan langsung tercermin di Ringkasan Proyek, Linimasa Teknis, dan audit trail.</strong>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
