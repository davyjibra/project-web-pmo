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

$pageTitle = 'Dashboard PMO | Tambah Issue Log';
$errorMessage = '';
$flashMessage = getFlashMessage();

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

$formData = [
    'code' => '',
    'category' => '',
    'description' => '',
    'priority' => 'Sedang',
    'level' => 'Sedang',
    'target_resolve' => date('Y-m-d', strtotime('+7 days')),
    'pic' => '',
    'status' => 'Terbuka',
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
            $errorMessage = 'Akun Anda tidak memiliki izin untuk menambah issue log pada PIC ini.';
        } else {
            /*
             * Pastikan kode Issue Log tidak digunakan
             * oleh issue lain.
             */
            $duplicateStatement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM issue_logs
                 WHERE code = :code'
            );

            $duplicateStatement->execute([
                ':code' => $formData['code'],
            ]);

            $duplicateCount = (int) $duplicateStatement->fetchColumn();

            if ($duplicateCount > 0) {
                $errorMessage = 'Kode issue sudah digunakan. Gunakan kode lain.';
            } else {
                $insertStatement = $pdo->prepare(
                    'INSERT INTO issue_logs (
                        code,
                        category,
                        description,
                        priority,
                        level,
                        target_resolve,
                        pic,
                        status,
                        created_at,
                        updated_at
                    ) VALUES (
                        :code,
                        :category,
                        :description,
                        :priority,
                        :level,
                        :target_resolve,
                        :pic,
                        :status,
                        :created_at,
                        :updated_at
                    )'
                );

                $timestamp = date('Y-m-d H:i:s');

                $insertStatement->execute([
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
                    ':created_at' => $timestamp,
                    ':updated_at' => $timestamp,
                ]);

                writeAuditLog(
                    $pdo,
                    'Issue Log',
                    'create',
                    $formData['code'] . ' - ' . $formData['category'],
                    [
                        'priority' => $formData['priority'],
                        'status' => $formData['status'],
                        'pic' => $formData['pic'],
                    ]
                );

                setFlashMessage(
                    'success',
                    'Issue log baru berhasil ditambahkan.'
                );

                header('Location: /issues/create');
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
                <h2>Tambah Issue Log</h2>
                <p>Tambahkan isu operasional atau integrasi yang perlu ditangani cepat.</p>
            </div>

            <div class="topbar__controls">
                <a href="/#daftar-risiko" class="action-button">
                    Kembali ke Dashboard
                </a>

                <a href="/logout" class="action-button">
                    Keluar
                </a>
            </div>
        </header>

        <?php if ($flashMessage !== null): ?>
            <div class="alert alert--<?= htmlspecialchars(
                $flashMessage['type'],
                ENT_QUOTES,
                'UTF-8'
            ); ?>">
                <?= htmlspecialchars(
                    $flashMessage['message'],
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </div>
        <?php endif; ?>

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
                            Data Issue Log
                        </h3>

                        <p class="section-subtitle">
                            Gunakan issue log untuk isu yang butuh penyelesaian
                            cepat di luar risk register.
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
                            placeholder="Contoh: ISS-001"
                            required
                        >

                        <small class="form-helper">
                            Gunakan kode unik agar issue mudah dilacak.
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
                            placeholder="Contoh: Integrasi"
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
                            <option value="">
                                Pilih PIC
                            </option>

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
                            Simpan Issue
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
                            Panduan Issue Log
                        </h3>

                        <p class="section-subtitle">
                            Diinput oleh
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
                            Gunakan kapan
                        </span>

                        <strong>
                            Pakai untuk isu aktif yang perlu diselesaikan cepat
                            dan jelas PIC-nya.
                        </strong>
                    </div>

                    <div class="insight-card">
                        <span class="insight-card__label">
                            Bedanya
                        </span>

                        <strong>
                            Issue log lebih cocok untuk masalah operasional
                            aktual, bukan risiko potensial.
                        </strong>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>