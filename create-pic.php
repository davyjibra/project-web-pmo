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
$pageTitle = 'Dashboard PMO | Tambah PIC';
$errorMessage = '';
$flashMessage = getFlashMessage();

$formData = [
    'name' => '',
    'person_name' => '',
    'role_label' => '',
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
        } elseif (isValidPicName($pdo, $formData['name'])) {
            $errorMessage = 'Nama PIC sudah ada di master.';
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO pics (name, person_name, role_label, created_at, updated_at)
                 VALUES (:name, :person_name, :role_label, :created_at, :updated_at)'
            );
            $timestamp = date('Y-m-d H:i:s');
            $statement->execute([
                ':name' => $formData['name'],
                ':person_name' => $formData['person_name'] !== '' ? $formData['person_name'] : null,
                ':role_label' => $formData['role_label'] !== '' ? $formData['role_label'] : null,
                ':created_at' => $timestamp,
                ':updated_at' => $timestamp,
            ]);

            writeAuditLog($pdo, 'Master PIC', 'create', $formData['name'], [
                'person_name' => $formData['person_name'],
                'role_label' => $formData['role_label'],
            ]);

            setFlashMessage('success', 'PIC baru berhasil ditambahkan.');
            header('Location: /pics/create');
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
                <h2>Tambah PIC</h2>
                <p>Tambahkan penanggung jawab ke master PIC agar bisa dipilih dari dropdown.</p>
            </div>
            <div class="topbar__controls">
                <a href="/?tab=master-pic" class="action-button">Kembali ke Master PIC</a>
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
                        <h3 class="panel__title">Data PIC</h3>
                        <p class="section-subtitle">Tambahkan PIC baru agar bisa langsung dipakai pada dropdown kegiatan dan milestone.</p>
                    </div>
                </div>
                <form method="post" class="task-form task-form--compact">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <label class="form-field form-field--full">
                        <span>Nama PIC</span>
                        <input type="text" name="name" value="<?= htmlspecialchars($formData['name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Contoh: Andi Prasetyo" required>
                        <small class="form-helper">Gunakan nama lengkap agar mudah dicari dan konsisten di seluruh dashboard.</small>
                    </label>
                    <label class="form-field form-field--full">
                        <span>Nama Person / PIC</span>
                        <input type="text" name="person_name" value="<?= htmlspecialchars($formData['person_name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Contoh: Davy Julian">
                        <small class="form-helper">Isi nama orang jika entri ini mewakili instansi seperti DISHUB, JLI, atau PTO.</small>
                    </label>
                    <label class="form-field form-field--full">
                        <span>Peran / Unit</span>
                        <input type="text" name="role_label" value="<?= htmlspecialchars($formData['role_label'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Contoh: Lead Teknis">
                        <small class="form-helper">Opsional, tetapi sebaiknya diisi untuk memudahkan identifikasi PIC di tim.</small>
                    </label>
                    <div class="form-actions">
                        <button type="submit" class="action-button action-button--primary">Simpan PIC</button>
                        <a href="/?tab=master-pic" class="action-button">Batal</a>
                    </div>
                </form>
            </section>

            <aside class="panel panel--soft">
                <div class="panel__header">
                    <div>
                        <h3 class="panel__title">Panduan Singkat</h3>
                        <p class="section-subtitle">Diinput oleh <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>.</p>
                    </div>
                </div>
                <div class="insight-list">
                    <div class="insight-card">
                        <span class="insight-card__label">Kapan ditambah</span>
                        <strong>Tambahkan PIC baru saat nama belum tersedia di master dropdown.</strong>
                    </div>
                    <div class="insight-card">
                        <span class="insight-card__label">Praktik baik</span>
                        <strong>Isi peran atau unit agar admin lain lebih cepat mengenali penanggung jawab.</strong>
                    </div>
                    <div class="insight-card">
                        <span class="insight-card__label">Dampak</span>
                        <strong>Setelah disimpan, PIC langsung bisa dipilih pada form kegiatan dan milestone.</strong>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
