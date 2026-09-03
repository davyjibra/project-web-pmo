<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
requireAuthentication();
requireModulePermission('issues');

require_once __DIR__ . '/config/audit.php';
require_once __DIR__ . '/config/pic.php';
require_once __DIR__ . '/database/initialize.php';

$pdo = getDatabaseConnection();
$user = getAuthenticatedUser();

$pageTitle = 'Dashboard PMO | Edit Issue Log';
$errorMessage = '';

$picOptions = getPicNames($pdo);

$allowedPriorities = [
    'Tinggi',
    'Sedang',
    'Rendah',
];

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

$issueId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$issueId) {
    setFlashMessage(
        'error',
        'Issue log yang akan diedit tidak ditemukan.'
    );

    header('Location: /#daftar-risiko');
    exit;
}

$statement = $pdo->prepare(
    'SELECT * FROM issue_logs WHERE id = :id LIMIT 1'
);

$statement->execute([
    ':id' => $issueId,
]);

$issue = $statement->fetch();

if (!$issue) {
    setFlashMessage(
        'error',
        'Data issue log tidak tersedia.'
    );

    header('Location: /#daftar-risiko');
    exit;
}

requireScopedModulePermission(
    'issues',
    (string) $issue['pic']
);

$formData = [
    'code' => (string) $issue['code'],
    'category' => (string) $issue['category'],
    'description' => (string) $issue['description'],
    'priority' => (string) $issue['priority'],
    'level' => (string) $issue['level'],
    'target_resolve' => (string) ($issue['target_resolve'] ?? ''),
    'pic' => (string) $issue['pic'],
    'status' => (string) $issue['status'],
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
            $formData['category'] === '' ||
            $formData['description'] === '' ||
            $formData['pic'] === '' ||
            !in_array($formData['priority'], $allowedPriorities, true) ||
            !in_array($formData['level'], $allowedLevels, true) ||
            !in_array($formData['status'], $allowedStatuses, true) ||
            !in_array($formData['pic'], $picOptions, true)
        ) {
            $errorMessage = 'Mohon lengkapi data issue log dengan benar.';
        } elseif (!canManageScopedRecord('issues', $formData['pic'])) {
            $errorMessage = 'Akun Anda tidak memiliki izin untuk memindahkan issue log ke PIC ini.';
        } else {
            /*
             * Cek apakah kode issue sudah digunakan
             * oleh issue lain.
             */
            $duplicateStatement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM issue_logs
                 WHERE code = :code
                   AND id != :id'
            );

            $duplicateStatement->execute([
                ':code' => $formData['code'],
                ':id' => $issueId,
            ]);

            $duplicateCount = (int) $duplicateStatement->fetchColumn();

            if ($duplicateCount > 0) {
                $errorMessage = 'Kode issue sudah digunakan. Gunakan kode lain.';
            } else {
                $updateStatement = $pdo->prepare(
                    'UPDATE issue_logs SET
                        code = :code,
                        category = :category,
                        description = :description,
                        priority = :priority,
                        level = :level,
                        target_resolve = :target_resolve,
                        pic = :pic,
                        status = :status,
                        updated_at = :updated_at
                     WHERE id = :id'
                );

                $timestamp = date('Y-m-d H:i:s');

                $updateStatement->execute([
                    ':code' => $formData['code'],
                    ':category' => $formData['category'],
                    ':description' => $formData['description'],
                    ':priority' => $formData['priority'],
                    ':level' => $formData['level'],
                    ':target_resolve' => $formData['target_resolve'] !== ''
                        ? $formData['target_resolve']
                        : null,
                    ':pic' => $formData['pic'],
                    ':status' => $formData['status'],
                    ':updated_at' => $timestamp,
                    ':id' => $issueId,
                ]);

                writeAuditLog(
                    $pdo,
                    'Issue Log',
                    'update',
                    $formData['code'] . ' - ' . $formData['category'],
                    [
                        'priority' => $formData['priority'],
                        'status' => $formData['status'],
                        'pic' => $formData['pic'],
                    ]
                );

                setFlashMessage(
                    'success',
                    'Issue log berhasil diperbarui.'
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
                <h2>Edit Issue Log</h2>
                <p>Perbarui deskripsi, prioritas, PIC, dan status issue log.</p>
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
                            Perbarui Issue Log
                        </h3>

                        <p class="section-subtitle">
                            Pastikan isu aktif, target resolve, dan
                            penanggung jawabnya selalu mutakhir.
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
                            Pertahankan kode unik agar histori issue tetap konsisten.
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
                    </label>

                    <label class="form-field form-field--full">
                        <span>Deskripsi Isu</span>

                        <textarea
                            name="description"
                            rows="4"
                            required
                        ><?= htmlspecialchars(
                            $formData['description'],
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?></textarea>
                    </label>

                    <label class="form-field">
                        <span>Prioritas</span>

                        <select name="priority">
                            <?php foreach ($allowedPriorities as $priority): ?>
                                <option
                                    value="<?= htmlspecialchars(
                                        $priority,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>"
                                    <?= $formData['priority'] === $priority
                                        ? ' selected'
                                        : ''; ?>
                                >
                                    <?= htmlspecialchars(
                                        $priority,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                    </label>

                    <label class="form-field">
                        <span>Target Resolve</span>

                        <input
                            type="date"
                            name="target_resolve"
                            value="<?= htmlspecialchars(
                                $formData['target_resolve'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                        >
                    </label>

                    <label class="form-field">
                        <span>PIC</span>

                        <select name="pic" required>
                            <?php foreach ($picOptions as $picOption): ?>
                                <option
                                    value="<?= htmlspecialchars(
                                        $picOption,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>"
                                    <?= $formData['pic'] === $picOption
                                        ? ' selected'
                                        : ''; ?>
                                >
                                    <?= htmlspecialchars(
                                        $picOption,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                            Issue Saat Ini
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
                            Kode & Kategori
                        </span>

                        <strong>
                            <?= htmlspecialchars(
                                (string) $issue['code']
                                . ' - '
                                . (string) $issue['category'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </strong>
                    </div>

                    <div class="insight-card">
                        <span class="insight-card__label">
                            PIC Saat Ini
                        </span>

                        <strong>
                            <?= htmlspecialchars(
                                (string) $issue['pic'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </strong>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>