<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
requireAuthentication();
requireModulePermission('raci');
require_once __DIR__ . '/config/audit.php';
require_once __DIR__ . '/database/initialize.php';

$pdo = getDatabaseConnection();
$activeProjectKey = getActiveProjectKey();
$user = getAuthenticatedUser();
$pageTitle = 'Dashboard PMO | Tambah RACI';
$errorMessage = '';
$flashMessage = getFlashMessage();

$formData = [
    'activity' => '',
    'responsible' => '',
    'accountable' => '',
    'consulted' => '',
    'informed' => '',
    'sequence' => '1',
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
            $stmt = $pdo->prepare(
                'INSERT INTO raci_matrix (project_key, activity, responsible, accountable, consulted, informed, sequence, created_at, updated_at)
                 VALUES (:project_key, :activity, :responsible, :accountable, :consulted, :informed, :sequence, :created_at, :updated_at)'
            );
            $timestamp = date('Y-m-d H:i:s');
            $stmt->execute([
                ':project_key' => $activeProjectKey,
                ':activity' => $formData['activity'],
                ':responsible' => $formData['responsible'],
                ':accountable' => $formData['accountable'],
                ':consulted' => $formData['consulted'],
                ':informed' => $formData['informed'],
                ':sequence' => $sequence,
                ':created_at' => $timestamp,
                ':updated_at' => $timestamp,
            ]);
            writeAuditLog($pdo, 'Matriks RACI', 'create', $formData['activity'], [
                'responsible' => $formData['responsible'],
                'accountable' => $formData['accountable'],
                'sequence' => $sequence,
            ]);
            setFlashMessage('success', 'Baris RACI berhasil ditambahkan.');
            header('Location: /raci/create');
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
                <h2>Tambah Matriks RACI</h2>
                <p>Tambahkan pembagian peran baru untuk aktivitas proyek.</p>
            </div>
            <div class="topbar__controls">
                <a href="/#matriks-raci" class="action-button">Kembali ke Dashboard</a>
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
                        <h3 class="panel__title">Data RACI</h3>
                        <p class="section-subtitle">Tambahkan pembagian peran yang jelas agar keputusan dan eksekusi aktivitas lebih terstruktur.</p>
                    </div>
                </div>
                <form method="post" class="task-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <label class="form-field form-field--wide">
                        <span>Aktivitas</span>
                        <input type="text" name="activity" value="<?= htmlspecialchars($formData['activity'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Contoh: Finalisasi parameter tarif" required>
                        <small class="form-helper">Tulis aktivitas yang cukup spesifik agar pembagian peran tidak ambigu.</small>
                    </label>
                    <label class="form-field">
                        <span>Responsible</span>
                        <input type="text" name="responsible" value="<?= htmlspecialchars($formData['responsible'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Pihak yang menjalankan pekerjaan utama.</small>
                    </label>
                    <label class="form-field">
                        <span>Accountable</span>
                        <input type="text" name="accountable" value="<?= htmlspecialchars($formData['accountable'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Pihak yang bertanggung jawab akhir atas keputusan atau hasil.</small>
                    </label>
                    <label class="form-field">
                        <span>Consulted</span>
                        <input type="text" name="consulted" value="<?= htmlspecialchars($formData['consulted'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Pihak yang perlu dilibatkan untuk memberi masukan.</small>
                    </label>
                    <label class="form-field">
                        <span>Informed</span>
                        <input type="text" name="informed" value="<?= htmlspecialchars($formData['informed'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Pihak yang wajib menerima informasi perkembangan aktivitas.</small>
                    </label>
                    <label class="form-field form-field--full">
                        <span>Urutan</span>
                        <input type="number" min="1" name="sequence" value="<?= htmlspecialchars($formData['sequence'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Urutan membantu menampilkan baris RACI secara lebih terstruktur di dashboard.</small>
                    </label>
                    <div class="form-actions">
                        <button type="submit" class="action-button action-button--primary">Simpan RACI</button>
                        <a href="/#matriks-raci" class="action-button">Batal</a>
                    </div>
                </form>
            </section>

            <aside class="panel panel--soft">
                <div class="panel__header">
                    <div>
                        <h3 class="panel__title">Panduan RACI</h3>
                        <p class="section-subtitle">Diinput oleh <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>.</p>
                    </div>
                </div>
                <div class="insight-list">
                    <div class="insight-card">
                        <span class="insight-card__label">Tujuan</span>
                        <strong>Gunakan RACI untuk aktivitas yang benar-benar butuh kejelasan peran lintas tim.</strong>
                    </div>
                    <div class="insight-card">
                        <span class="insight-card__label">Praktik Baik</span>
                        <strong>Hindari pengisian terlalu umum agar peran Responsible dan Accountable tetap jelas dibedakan.</strong>
                    </div>
                    <div class="insight-card">
                        <span class="insight-card__label">Kegunaan</span>
                        <strong>Baris yang disimpan akan langsung muncul di tab Matriks RACI untuk referensi tim.</strong>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
