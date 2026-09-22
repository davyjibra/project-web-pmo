<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
requireAuthentication();

require_once __DIR__ . '/config/audit.php';
require_once __DIR__ . '/database/initialize.php';

$pdo = getDatabaseConnection();
$activeProjectKey = getActiveProjectKey();
$user = getAuthenticatedUser();

$pageTitle = 'Dashboard PMO | Edit Risiko';
$errorMessage = '';

$allowedLevels = [
    'Tinggi',
    'Sedang',
    'Rendah',
];

$allowedStatuses = [
    'Terbuka',
    'Mitigasi Berjalan',
    'Dipantau',
    'Selesai',
];

$riskId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$riskId) {
    setFlashMessage(
        'error',
        'Risiko yang akan diedit tidak ditemukan.'
    );

    header('Location: /#daftar-risiko');
    exit;
}

$statement = $pdo->prepare(
    'SELECT * FROM risks WHERE id = :id AND project_key = :project_key LIMIT 1'
);

$statement->execute([
    ':id' => $riskId,
    ':project_key' => $activeProjectKey,
]);

$risk = $statement->fetch();

if (!$risk) {
    setFlashMessage(
        'error',
        'Data risiko tidak tersedia.'
    );

    header('Location: /#daftar-risiko');
    exit;
}

requireScopedModulePermission(
    'risks',
    (string) $risk['owner']
);

$formData = [
    'code' => (string) $risk['code'],
    'title' => (string) $risk['title'],
    'category' => (string) $risk['category'],
    'level' => (string) $risk['level'],
    'owner' => (string) $risk['owner'],
    'status' => (string) $risk['status'],
    'mitigation' => (string) $risk['mitigation'],
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Permintaan tidak valid. Silakan muat ulang halaman lalu coba lagi.';
    } else {
        foreach ($formData as $key => $value) {
            $formData[$key] = trim(
                (string) ($_POST[$key] ?? $value)
            );
        }

        if (
            $formData['code'] === '' ||
            $formData['title'] === '' ||
            $formData['category'] === '' ||
            $formData['owner'] === '' ||
            $formData['mitigation'] === '' ||
            !in_array($formData['level'], $allowedLevels, true) ||
            !in_array($formData['status'], $allowedStatuses, true)
        ) {
            $errorMessage = 'Mohon lengkapi data risiko dengan benar.';
        } elseif (!canManageScopedRecord('risks', $formData['owner'])) {
            $errorMessage = 'Akun Anda tidak memiliki izin untuk memindahkan risiko ke owner ini.';
        } else {
            /*
             * Cek apakah kode risiko sudah digunakan
             * oleh risiko lain.
             *
             * Risiko yang sedang diedit dikecualikan
             * menggunakan AND id != :id.
             */
            $duplicateStatement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM risks
                 WHERE code = :code
                   AND id != :id
                   AND project_key = :project_key'
            );

            $duplicateStatement->execute([
                ':code' => $formData['code'],
                ':id' => $riskId,
                ':project_key' => $activeProjectKey,
            ]);

            $duplicateCount = (int) $duplicateStatement->fetchColumn();

            if ($duplicateCount > 0) {
                $errorMessage = 'Kode risiko sudah digunakan. Gunakan kode lain.';
            } else {
                $updateStatement = $pdo->prepare(
                    'UPDATE risks SET
                        code = :code,
                        title = :title,
                        category = :category,
                        level = :level,
                        owner = :owner,
                        status = :status,
                        mitigation = :mitigation,
                        updated_at = :updated_at
                     WHERE id = :id'
                );

                $timestamp = date('Y-m-d H:i:s');

                $updateStatement->execute([
                    ':code' => $formData['code'],
                    ':title' => $formData['title'],
                    ':category' => $formData['category'],
                    ':level' => $formData['level'],
                    ':owner' => $formData['owner'],
                    ':status' => $formData['status'],
                    ':mitigation' => $formData['mitigation'],
                    ':updated_at' => $timestamp,
                    ':id' => $riskId,
                ]);

                writeAuditLog(
                    $pdo,
                    'Risiko',
                    'update',
                    $formData['code'] . ' - ' . $formData['title'],
                    [
                        'level' => $formData['level'],
                        'status' => $formData['status'],
                        'owner' => $formData['owner'],
                    ]
                );

                setFlashMessage(
                    'success',
                    'Risiko berhasil diperbarui.'
                );

                header('Location: /#daftar-risiko');
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
                <h2>Edit Risiko</h2>
                <p>Perbarui informasi risiko proyek beserta rencana mitigasinya.</p>
            </div>

            <div class="topbar__controls">
                <a
                    href="/#daftar-risiko"
                    class="action-button"
                >
                    Kembali ke Dashboard
                </a>

                <a
                    href="/logout"
                    class="action-button"
                >
                    Keluar
                </a>
            </div>
        </header>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert--error">
                <?= htmlspecialchars(
                    $errorMessage,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </div>
        <?php endif; ?>

        <div class="content-grid content-grid--form-aside">
            <section class="form-card form-card--highlight">
                <div class="panel__header">
                    <div>
                        <h3 class="panel__title">
                            Perbarui Risiko
                        </h3>

                        <p class="section-subtitle">
                            Sesuaikan level, status, dan mitigasi risiko agar
                            pengawalan proyek tetap akurat.
                        </p>
                    </div>
                </div>

                <form method="post" class="task-form">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            getCsrfToken(),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                    >

                    <label class="form-field">
                        <span>Kode</span>

                        <input
                            type="text"
                            name="code"
                            value="<?= htmlspecialchars(
                                $formData['code'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            required
                        >

                        <small class="form-helper">
                            Pertahankan kode unik agar riwayat risiko tetap mudah dilacak.
                        </small>
                    </label>

                    <label class="form-field form-field--wide">
                        <span>Judul Risiko</span>

                        <input
                            type="text"
                            name="title"
                            value="<?= htmlspecialchars(
                                $formData['title'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            required
                        >

                        <small class="form-helper">
                            Perbarui judul bila formulasi risiko perlu dibuat lebih presisi.
                        </small>
                    </label>

                    <label class="form-field">
                        <span>Kategori</span>

                        <input
                            type="text"
                            name="category"
                            value="<?= htmlspecialchars(
                                $formData['category'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            required
                        >

                        <small class="form-helper">
                            Kategori membantu tim membandingkan pola risiko antar area kerja.
                        </small>
                    </label>

                    <label class="form-field">
                        <span>Level</span>

                        <select name="level">
                            <?php foreach ($allowedLevels as $level): ?>
                                <option
                                    value="<?= htmlspecialchars(
                                        $level,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>"
                                    <?= $formData['level'] === $level
                                        ? ' selected'
                                        : ''; ?>
                                >
                                    <?= htmlspecialchars(
                                        $level,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <small class="form-helper">
                            Perubahan level akan memengaruhi prioritas risiko di dashboard.
                        </small>
                    </label>

                    <label class="form-field">
                        <span>Pemilik Risiko</span>

                        <input
                            type="text"
                            name="owner"
                            value="<?= htmlspecialchars(
                                $formData['owner'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            required
                        >

                        <small class="form-helper">
                            Pastikan pemilik risiko adalah pihak yang bisa
                            mendorong mitigasi berjalan.
                        </small>
                    </label>

                    <label class="form-field">
                        <span>Status</span>

                        <select name="status">
                            <?php foreach ($allowedStatuses as $status): ?>
                                <option
                                    value="<?= htmlspecialchars(
                                        $status,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>"
                                    <?= $formData['status'] === $status
                                        ? ' selected'
                                        : ''; ?>
                                >
                                    <?= htmlspecialchars(
                                        $status,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <small class="form-helper">
                            Status terbaru akan langsung tercermin di Daftar Risiko dan laporan.
                        </small>
                    </label>

                    <label class="form-field form-field--full">
                        <span>Rencana Mitigasi</span>

                        <textarea
                            name="mitigation"
                            rows="4"
                            required
                        ><?= htmlspecialchars(
                            $formData['mitigation'],
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?></textarea>

                        <small class="form-helper">
                            Perbarui mitigasi sesuai tindakan terbaru atau hasil rapat eskalasi.
                        </small>
                    </label>

                    <div class="form-actions">
                        <button
                            type="submit"
                            class="action-button action-button--primary"
                        >
                            Simpan Perubahan
                        </button>

                        <a
                            href="/#daftar-risiko"
                            class="action-button"
                        >
                            Batal
                        </a>
                    </div>
                </form>
            </section>

            <aside class="panel panel--soft">
                <div class="panel__header">
                    <div>
                        <h3 class="panel__title">
                            Catatan Risiko
                        </h3>

                        <p class="section-subtitle">
                            Terakhir diubah oleh
                            <?= htmlspecialchars(
                                $user['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>.
                        </p>
                    </div>
                </div>

                <div class="insight-list">
                    <div class="insight-card">
                        <span class="insight-card__label">
                            Risiko Saat Ini
                        </span>

                        <strong>
                            <?= htmlspecialchars(
                                $risk['code'] . ' - ' . $risk['title'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </strong>
                    </div>

                    <div class="insight-card">
                        <span class="insight-card__label">
                            Level & Status
                        </span>

                        <strong>
                            <?= htmlspecialchars(
                                (string) $risk['level'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>,
                            <?= htmlspecialchars(
                                (string) $risk['status'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </strong>
                    </div>

                    <div class="insight-card">
                        <span class="insight-card__label">
                            Dampak
                        </span>

                        <strong>
                            Perubahan akan langsung terlihat pada Daftar Risiko
                            dan audit trail laporan.
                        </strong>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
