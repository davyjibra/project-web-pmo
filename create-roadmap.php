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
$pageTitle = 'Dashboard PMO | Tambah Peta Jalan';
$errorMessage = '';
$flashMessage = getFlashMessage();
$allowedStatuses = ['Belum Dimulai', 'Sedang Berjalan', 'Menunggu', 'Selesai'];

$formData = [
    'phase' => '',
    'detail' => '',
    'status' => 'Belum Dimulai',
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
            $formData['phase'] === '' ||
            $formData['detail'] === '' ||
            !in_array($formData['status'], $allowedStatuses, true) ||
            $sequence < 1
        ) {
            $errorMessage = 'Mohon lengkapi data peta jalan dengan benar.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO roadmaps (project_key, phase, detail, status, sequence, created_at, updated_at)
                 VALUES (:project_key, :phase, :detail, :status, :sequence, :created_at, :updated_at)'
            );
            $timestamp = date('Y-m-d H:i:s');
            $stmt->execute([
                ':project_key' => $activeProjectKey,
                ':phase' => $formData['phase'],
                ':detail' => $formData['detail'],
                ':status' => $formData['status'],
                ':sequence' => $sequence,
                ':created_at' => $timestamp,
                ':updated_at' => $timestamp,
            ]);
            writeAuditLog($pdo, 'Peta Jalan', 'create', $formData['phase'], [
                'status' => $formData['status'],
                'sequence' => $sequence,
            ]);
            setFlashMessage('success', 'Tahap peta jalan berhasil ditambahkan.');
            header('Location: /roadmaps/create');
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
                <h2>Tambah Peta Jalan</h2>
                <p>Tambahkan fase implementasi baru ke modul peta jalan.</p>
            </div>
            <div class="topbar__controls">
                <a href="/#peta-jalan" class="action-button">Kembali ke Dashboard</a>
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
                        <h3 class="panel__title">Data Tahap Peta Jalan</h3>
                        <p class="section-subtitle">Tambahkan fase implementasi secara berurutan agar alur proyek mudah dibaca oleh manajemen.</p>
                    </div>
                </div>
            <form method="post" class="task-form task-form--compact">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <label class="form-field">
                        <span>Nama Fase</span>
                        <input type="text" name="phase" value="<?= htmlspecialchars($formData['phase'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Contoh: Persiapan" required>
                        <small class="form-helper">Gunakan nama fase yang singkat dan mewakili satu tahapan besar proyek.</small>
                    </label>
                    <label class="form-field">
                        <span>Urutan</span>
                        <input type="number" min="1" name="sequence" value="<?= htmlspecialchars($formData['sequence'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Urutan menentukan posisi fase pada tampilan peta jalan.</small>
                    </label>
                    <label class="form-field form-field--full">
                        <span>Uraian</span>
                        <textarea name="detail" rows="4" placeholder="Jelaskan fokus utama atau keluaran fase ini." required><?= htmlspecialchars($formData['detail'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <small class="form-helper">Tuliskan fokus fase dalam 1-2 kalimat agar mudah dipahami lintas tim.</small>
                    </label>
                    <label class="form-field form-field--full">
                        <span>Status</span>
                        <select name="status">
                            <?php foreach ($allowedStatuses as $status): ?>
                                <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"<?= $formData['status'] === $status ? ' selected' : ''; ?>><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-helper">Status fase menunjukkan posisi terkini dari tahapan besar implementasi.</small>
                    </label>
                    <div class="form-actions">
                        <button type="submit" class="action-button action-button--primary">Simpan Tahap</button>
                        <a href="/#peta-jalan" class="action-button">Batal</a>
                    </div>
                </form>
            </section>

            <aside class="panel panel--soft">
                <div class="panel__header">
                    <div>
                        <h3 class="panel__title">Panduan Peta Jalan</h3>
                        <p class="section-subtitle">Diinput oleh <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>.</p>
                    </div>
                </div>
                <div class="insight-list">
                    <div class="insight-card">
                        <span class="insight-card__label">Cakupan</span>
                        <strong>Satu fase sebaiknya mewakili satu kelompok pekerjaan besar, bukan aktivitas detail harian.</strong>
                    </div>
                    <div class="insight-card">
                        <span class="insight-card__label">Urutan</span>
                        <strong>Pastikan sequence sesuai alur implementasi agar peta jalan mudah dibaca secara kronologis.</strong>
                    </div>
                    <div class="insight-card">
                        <span class="insight-card__label">Kegunaan</span>
                        <strong>Tahap yang disimpan akan langsung muncul di tab Peta Jalan pada dashboard.</strong>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
