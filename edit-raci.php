<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
requireAuthentication();
requireModulePermission('raci');
require_once __DIR__ . '/config/audit.php';
require_once __DIR__ . '/database/initialize.php';

$pdo = getDatabaseConnection();
$user = getAuthenticatedUser();
$pageTitle = 'Dashboard PMO | Edit RACI';
$errorMessage = '';
$raciId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$raciId) {
    setFlashMessage('error', 'Baris RACI tidak ditemukan.');
    header('Location: /#matriks-raci');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM raci_matrix WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $raciId]);
$raci = $stmt->fetch();
if (!$raci) {
    setFlashMessage('error', 'Data RACI tidak tersedia.');
    header('Location: /#matriks-raci');
    exit;
}

$formData = [
    'activity' => (string) $raci['activity'],
    'responsible' => (string) $raci['responsible'],
    'accountable' => (string) $raci['accountable'],
    'consulted' => (string) $raci['consulted'],
    'informed' => (string) $raci['informed'],
    'sequence' => (string) $raci['sequence'],
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
            $formData['activity'] === '' ||
            $formData['responsible'] === '' ||
            $formData['accountable'] === '' ||
            $formData['consulted'] === '' ||
            $formData['informed'] === '' ||
            $sequence < 1
        ) {
            $errorMessage = 'Mohon lengkapi data RACI dengan benar.';
        } else {
            $update = $pdo->prepare(
                'UPDATE raci_matrix SET activity = :activity, responsible = :responsible, accountable = :accountable, consulted = :consulted, informed = :informed, sequence = :sequence, updated_at = :updated_at
                 WHERE id = :id'
            );
            $update->execute([
                ':activity' => $formData['activity'],
                ':responsible' => $formData['responsible'],
                ':accountable' => $formData['accountable'],
                ':consulted' => $formData['consulted'],
                ':informed' => $formData['informed'],
                ':sequence' => $sequence,
                ':updated_at' => date('Y-m-d H:i:s'),
                ':id' => $raciId,
            ]);
            writeAuditLog($pdo, 'Matriks RACI', 'update', $formData['activity'], [
                'responsible' => $formData['responsible'],
                'accountable' => $formData['accountable'],
                'sequence' => $sequence,
            ]);
            setFlashMessage('success', 'Baris RACI berhasil diperbarui.');
            header('Location: /#matriks-raci');
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
                <h2>Edit Matriks RACI</h2>
                <p>Perbarui pembagian peran pada aktivitas proyek.</p>
            </div>
            <div class="topbar__controls">
                <a href="/#matriks-raci" class="action-button">Kembali ke Dashboard</a>
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
                        <h3 class="panel__title">Perbarui RACI</h3>
                        <p class="section-subtitle">Sesuaikan pembagian peran agar akuntabilitas dan jalur koordinasi tetap jelas.</p>
                    </div>
                </div>
                <form method="post" class="task-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <label class="form-field form-field--wide">
                        <span>Aktivitas</span>
                        <input type="text" name="activity" value="<?= htmlspecialchars($formData['activity'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Perbarui aktivitas jika ruang lingkup yang dibahas memang berubah.</small>
                    </label>
                    <label class="form-field">
                        <span>Responsible</span>
                        <input type="text" name="responsible" value="<?= htmlspecialchars($formData['responsible'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Peran pelaksana utama aktivitas ini.</small>
                    </label>
                    <label class="form-field">
                        <span>Accountable</span>
                        <input type="text" name="accountable" value="<?= htmlspecialchars($formData['accountable'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Pihak yang bertanggung jawab penuh terhadap hasil akhirnya.</small>
                    </label>
                    <label class="form-field">
                        <span>Consulted</span>
                        <input type="text" name="consulted" value="<?= htmlspecialchars($formData['consulted'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Pihak yang perlu dikonsultasikan sebelum keputusan dijalankan.</small>
                    </label>
                    <label class="form-field">
                        <span>Informed</span>
                        <input type="text" name="informed" value="<?= htmlspecialchars($formData['informed'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Pihak yang wajib menerima update tanpa harus ikut memutuskan.</small>
                    </label>
                    <label class="form-field form-field--full">
                        <span>Urutan</span>
                        <input type="number" min="1" name="sequence" value="<?= htmlspecialchars($formData['sequence'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Urutan akan mengatur susunan tampilan baris pada tab Matriks RACI.</small>
                    </label>
                    <div class="form-actions">
                        <button type="submit" class="action-button action-button--primary">Simpan Perubahan</button>
                        <a href="/#matriks-raci" class="action-button">Batal</a>
                    </div>
                </form>
            </section>

            <aside class="panel panel--soft">
                <div class="panel__header">
                    <div>
                        <h3 class="panel__title">Catatan RACI</h3>
                        <p class="section-subtitle">Terakhir diubah oleh <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>.</p>
                    </div>
                </div>
                <div class="insight-list">
                    <div class="insight-card">
                        <span class="insight-card__label">Aktivitas Saat Ini</span>
                        <strong><?= htmlspecialchars((string) $raci['activity'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="insight-card">
                        <span class="insight-card__label">Peran Inti</span>
                        <strong><?= htmlspecialchars((string) $raci['responsible'], ENT_QUOTES, 'UTF-8'); ?> / <?= htmlspecialchars((string) $raci['accountable'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="insight-card">
                        <span class="insight-card__label">Dampak</span>
                        <strong>Perubahan akan langsung tercermin pada tab Matriks RACI dan audit trail laporan.</strong>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
