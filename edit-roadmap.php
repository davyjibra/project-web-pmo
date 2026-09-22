<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
requireAuthentication();
requireModulePermission('roadmaps');
require_once __DIR__ . '/config/audit.php';
require_once __DIR__ . '/database/initialize.php';

$pdo = getDatabaseConnection();
$activeProjectKey = getActiveProjectKey();
$user = getAuthenticatedUser();
$pageTitle = 'Dashboard PMO | Edit Peta Jalan';
$errorMessage = '';
$allowedStatuses = ['Belum Dimulai', 'Sedang Berjalan', 'Menunggu', 'Selesai'];
$roadmapId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$roadmapId) {
    setFlashMessage('error', 'Tahap peta jalan tidak ditemukan.');
    header('Location: /#peta-jalan');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM roadmaps WHERE id = :id AND project_key = :project_key LIMIT 1');
$stmt->execute([':id' => $roadmapId, ':project_key' => $activeProjectKey]);
$roadmap = $stmt->fetch();

if (!$roadmap) {
    setFlashMessage('error', 'Data tahap peta jalan tidak tersedia.');
    header('Location: /#peta-jalan');
    exit;
}

$formData = [
    'phase' => (string) $roadmap['phase'],
    'detail' => (string) $roadmap['detail'],
    'status' => (string) $roadmap['status'],
    'sequence' => (string) $roadmap['sequence'],
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Permintaan tidak valid. Silakan muat ulang halaman lalu coba lagi.';
    } else {
        foreach ($formData as $key => $value) {
            $formData[$key] = trim((string) ($_POST[$key] ?? $value));
        }
        $sequence = (int) $formData['sequence'];
        if (
            $formData['phase'] === '' ||
            $formData['detail'] === '' ||
            !in_array($formData['status'], $allowedStatuses, true) ||
            $sequence < 1
        ) {
            $errorMessage = 'Mohon lengkapi data peta jalan dengan benar.';
        } else {
            $update = $pdo->prepare(
                'UPDATE roadmaps SET phase = :phase, detail = :detail, status = :status, sequence = :sequence, updated_at = :updated_at
                 WHERE id = :id'
            );
            $update->execute([
                ':phase' => $formData['phase'],
                ':detail' => $formData['detail'],
                ':status' => $formData['status'],
                ':sequence' => $sequence,
                ':updated_at' => date('Y-m-d H:i:s'),
                ':id' => $roadmapId,
            ]);
            writeAuditLog($pdo, 'Peta Jalan', 'update', $formData['phase'], [
                'status' => $formData['status'],
                'sequence' => $sequence,
            ]);
            setFlashMessage('success', 'Tahap peta jalan berhasil diperbarui.');
            header('Location: /#peta-jalan');
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
                <h2>Edit Peta Jalan</h2>
                <p>Perbarui tahapan implementasi pada modul peta jalan.</p>
            </div>
            <div class="topbar__controls">
                <a href="/#peta-jalan" class="action-button">Kembali ke Dashboard</a>
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
                        <h3 class="panel__title">Perbarui Tahap</h3>
                        <p class="section-subtitle">Sesuaikan urutan, uraian, dan status fase agar peta jalan tetap mencerminkan kondisi riil proyek.</p>
                    </div>
                </div>
                <form method="post" class="task-form task-form--compact">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <label class="form-field">
                        <span>Nama Fase</span>
                        <input type="text" name="phase" value="<?= htmlspecialchars($formData['phase'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Nama fase sebaiknya tetap stabil kecuali memang ada perubahan struktur implementasi.</small>
                    </label>
                    <label class="form-field">
                        <span>Urutan</span>
                        <input type="number" min="1" name="sequence" value="<?= htmlspecialchars($formData['sequence'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Ubah urutan jika posisi fase dalam alur implementasi perlu disesuaikan.</small>
                    </label>
                    <label class="form-field form-field--full">
                        <span>Uraian</span>
                        <textarea name="detail" rows="4" required><?= htmlspecialchars($formData['detail'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <small class="form-helper">Perbarui deskripsi bila fokus atau keluaran fase berubah.</small>
                    </label>
                    <label class="form-field form-field--full">
                        <span>Status</span>
                        <select name="status">
                            <?php foreach ($allowedStatuses as $status): ?>
                                <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"<?= $formData['status'] === $status ? ' selected' : ''; ?>><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-helper">Status terbaru akan langsung memengaruhi tampilan Peta Jalan di dashboard.</small>
                    </label>
                    <div class="form-actions">
                        <button type="submit" class="action-button action-button--primary">Simpan Perubahan</button>
                        <a href="/#peta-jalan" class="action-button">Batal</a>
                    </div>
                </form>
            </section>

            <aside class="panel panel--soft">
                <div class="panel__header">
                    <div>
                        <h3 class="panel__title">Catatan Fase</h3>
                        <p class="section-subtitle">Terakhir diubah oleh <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>.</p>
                    </div>
                </div>
                <div class="insight-list">
                    <div class="insight-card">
                        <span class="insight-card__label">Fase Saat Ini</span>
                        <strong><?= htmlspecialchars((string) $roadmap['phase'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="insight-card">
                        <span class="insight-card__label">Urutan & Status</span>
                        <strong>Urutan <?= htmlspecialchars((string) $roadmap['sequence'], ENT_QUOTES, 'UTF-8'); ?> dengan status <?= htmlspecialchars((string) $roadmap['status'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="insight-card">
                        <span class="insight-card__label">Dampak</span>
                        <strong>Perubahan ini akan langsung terlihat pada visual Peta Jalan dan audit trail laporan.</strong>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
