<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
requireAuthentication();
requireModulePermission('pics');
require_once __DIR__ . '/config/audit.php';
require_once __DIR__ . '/config/pic.php';
require_once __DIR__ . '/database/initialize.php';

$pdo = getDatabaseConnection();
$user = getAuthenticatedUser();
$pageTitle = 'Dashboard PMO | Edit PIC';
$errorMessage = '';
$picId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$picId) {
    setFlashMessage('error', 'PIC yang akan diedit tidak ditemukan.');
    header('Location: /?tab=master-pic');
    exit;
}

$statement = $pdo->prepare('SELECT * FROM pics WHERE id = :id LIMIT 1');
$statement->execute([':id' => $picId]);
$pic = $statement->fetch();

if (!$pic) {
    setFlashMessage('error', 'Data PIC tidak tersedia.');
    header('Location: /?tab=master-pic');
    exit;
}

$formData = [
    'name' => (string) $pic['name'],
    'person_name' => (string) ($pic['person_name'] ?? ''),
    'role_label' => (string) ($pic['role_label'] ?? ''),
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Permintaan tidak valid. Silakan muat ulang halaman lalu coba lagi.';
    } else {
        foreach ($formData as $key => $value) {
            $formData[$key] = trim((string) ($_POST[$key] ?? $value));
        }

        if ($formData['name'] === '') {
            $errorMessage = 'Nama PIC wajib diisi.';
        } else {
            $duplicateStatement = $pdo->prepare('SELECT COUNT(*) FROM pics WHERE name = :name AND id != :id');
            $duplicateStatement->execute([
                ':name' => $formData['name'],
                ':id' => $picId,
            ]);

            if ((int) $duplicateStatement->fetchColumn() > 0) {
                $errorMessage = 'Nama PIC sudah dipakai pada master.';
            } else {
                $previousName = (string) $pic['name'];
                $updateStatement = $pdo->prepare(
                    'UPDATE pics SET name = :name, person_name = :person_name, role_label = :role_label, updated_at = :updated_at WHERE id = :id'
                );
                $updateStatement->execute([
                    ':name' => $formData['name'],
                    ':person_name' => $formData['person_name'] !== '' ? $formData['person_name'] : null,
                    ':role_label' => $formData['role_label'] !== '' ? $formData['role_label'] : null,
                    ':updated_at' => date('Y-m-d H:i:s'),
                    ':id' => $picId,
                ]);

                if ($previousName !== $formData['name']) {
                    $taskUpdate = $pdo->prepare('UPDATE tasks SET pic = :new_name, updated_at = :updated_at WHERE pic = :old_name');
                    $taskUpdate->execute([
                        ':new_name' => $formData['name'],
                        ':updated_at' => date('Y-m-d H:i:s'),
                        ':old_name' => $previousName,
                    ]);

                    $milestoneUpdate = $pdo->prepare('UPDATE milestones SET pic = :new_name, updated_at = :updated_at WHERE pic = :old_name');
                    $milestoneUpdate->execute([
                        ':new_name' => $formData['name'],
                        ':updated_at' => date('Y-m-d H:i:s'),
                        ':old_name' => $previousName,
                    ]);

                    $issueUpdate = $pdo->prepare(
                    'UPDATE issue_logs
                    SET pic = :new_name, updated_at = :updated_at
                    WHERE pic = :old_name'
                    );

                    $issueUpdate->execute([
                        ':new_name' => $formData['name'],
                        ':updated_at' => date('Y-m-d H:i:s'),
                        ':old_name' => $previousName,
                    ]);

                }

                

                writeAuditLog($pdo, 'Master PIC', 'update', $formData['name'], [
                    'person_name' => $formData['person_name'],
                    'role_label' => $formData['role_label'],
                    'previous_name' => $previousName,
                ]);

                setFlashMessage('success', 'Master PIC berhasil diperbarui.');
                header('Location: /?tab=master-pic');
                exit;
            }
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<main class="main-content">
    <div class="dashboard dashboard--narrow">
        <header class="topbar">
            <div class="topbar__heading">
                <h2>Edit PIC</h2>
                <p>Perbarui nama atau peran PIC pada master terpusat.</p>
            </div>
            <div class="topbar__controls">
                <a href="/?tab=master-pic" class="action-button">Kembali ke Master PIC</a>
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
                        <h3 class="panel__title">Perbarui Data PIC</h3>
                        <p class="section-subtitle">Ubah nama atau unit PIC dengan tetap menjaga konsistensi data dashboard.</p>
                    </div>
                </div>
                <form method="post" class="task-form task-form--compact">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <label class="form-field form-field--full">
                        <span>Nama PIC</span>
                        <input type="text" name="name" value="<?= htmlspecialchars($formData['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Jika nama diubah, kegiatan, milestone, dan issue yang memakai nama lama akan ikut diperbarui otomatis.</small>
                    </label>
                    <label class="form-field form-field--full">
                        <span>Nama Person / PIC</span>
                        <input type="text" name="person_name" value="<?= htmlspecialchars($formData['person_name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Contoh: Davy Julian">
                        <small class="form-helper">Isi nama orang yang menjadi contact person utama untuk instansi atau unit ini.</small>
                    </label>
                    <label class="form-field form-field--full">
                        <span>Peran / Unit</span>
                        <input type="text" name="role_label" value="<?= htmlspecialchars($formData['role_label'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Contoh: Koordinator Operasional">
                        <small class="form-helper">Gunakan label yang singkat dan mudah dikenali oleh tim monitoring.</small>
                    </label>
                    <div class="form-actions">
                        <button type="submit" class="action-button action-button--primary">Simpan Perubahan</button>
                        <a href="/?tab=master-pic" class="action-button">Batal</a>
                    </div>
                </form>
            </section>

            <aside class="panel panel--soft">
                <div class="panel__header">
                    <div>
                        <h3 class="panel__title">Catatan Perubahan</h3>
                        <p class="section-subtitle">Terakhir diubah oleh <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>.</p>
                    </div>
                </div>
                <div class="insight-list">
                    <div class="insight-card">
                        <span class="insight-card__label">Nama Saat Ini</span>
                        <strong><?= htmlspecialchars((string) $pic['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="insight-card">
                        <span class="insight-card__label">PIC Saat Ini</span>
                        <strong><?= htmlspecialchars((string) (($pic['person_name'] ?? '') !== '' ? $pic['person_name'] : 'Belum diisi'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="insight-card">
                        <span class="insight-card__label">Peran Saat Ini</span>
                        <strong><?= htmlspecialchars((string) (($pic['role_label'] ?? '') !== '' ? $pic['role_label'] : 'Belum diisi'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="insight-card">
                        <span class="insight-card__label">Dampak</span>
                        <strong>Perubahan pada master PIC akan tercatat di audit trail dan menjaga dropdown tetap konsisten.</strong>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
