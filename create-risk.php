<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
requireAuthentication();
requireModulePermission('risks');

require_once __DIR__ . '/config/audit.php';
require_once __DIR__ . '/database/initialize.php';

$pdo = getDatabaseConnection();
$user = getAuthenticatedUser();

$pageTitle = 'Dashboard PMO | Tambah Risiko';
$errorMessage = '';
$flashMessage = getFlashMessage();

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
    'title' => '',
    'category' => '',
    'level' => 'Sedang',
    'owner' => '',
    'status' => 'Terbuka',
    'mitigation' => '',
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
            $errorMessage = 'Akun Anda tidak memiliki izin untuk menambah risiko pada owner ini.';
        } else {
            /*
             * Cek apakah kode risiko sudah pernah digunakan.
             */
            $duplicateStatement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM risks
                 WHERE code = :code'
            );

            $duplicateStatement->execute([
                ':code' => $formData['code'],
            ]);

            $duplicateCount = (int) $duplicateStatement->fetchColumn();

            if ($duplicateCount > 0) {
                $errorMessage = 'Kode risiko sudah digunakan. Gunakan kode lain.';
            } else {
                $insertStatement = $pdo->prepare(
                    'INSERT INTO risks (
                        code,
                        title,
                        category,
                        level,
                        owner,
                        status,
                        mitigation,
                        created_at,
                        updated_at
                    ) VALUES (
                        :code,
                        :title,
                        :category,
                        :level,
                        :owner,
                        :status,
                        :mitigation,
                        :created_at,
                        :updated_at
                    )'
                );

                $timestamp = date('Y-m-d H:i:s');

                $insertStatement->execute([
                    ':code' => $formData['code'],
                    ':title' => $formData['title'],
                    ':category' => $formData['category'],
                    ':level' => $formData['level'],
                    ':owner' => $formData['owner'],
                    ':status' => $formData['status'],
                    ':mitigation' => $formData['mitigation'],
                    ':created_at' => $timestamp,
                    ':updated_at' => $timestamp,
                ]);

                writeAuditLog(
                    $pdo,
                    'Risiko',
                    'create',
                    $formData['code'] . ' - ' . $formData['title'],
                    [
                        'level' => $formData['level'],
                        'status' => $formData['status'],
                        'owner' => $formData['owner'],
                    ]
                );

                setFlashMessage(
                    'success',
                    'Risiko baru berhasil ditambahkan.'
                );

                header('Location: /risks/create');
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
                <h2>Tambah Risiko</h2>
                <p>Tambahkan risiko proyek untuk dipantau pada dashboard PMO.</p>
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
                            Data Risiko
                        </h3>

                        <p class="section-subtitle">
                            Tambahkan risiko prioritas dengan deskripsi yang jelas
                            agar mudah dipantau dan dimitigasi.
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
                            placeholder="Contoh: RSK-006"
                            required
                        >

                        <small class="form-helper">
                            Gunakan kode unik agar risiko mudah dilacak pada rapat
                            mitigasi dan laporan.
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
                            placeholder="Contoh: Keterlambatan approval regulator"
                            required
                        >

                        <small class="form-helper">
                            Tuliskan risiko secara spesifik, bukan terlalu umum,
                            agar tindak lanjut lebih terarah.
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
                            placeholder="Contoh: Jadwal"
                            required
                        >

                        <small class="form-helper">
                            Kategori membantu tim memilah risiko per domain utama.
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
                            Level risiko dipakai untuk menentukan prioritas
                            pengawalan dan eskalasi.
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
                            placeholder="Contoh: Koordinator Teknis"
                            required
                        >

                        <small class="form-helper">
                            Isi pihak yang paling bertanggung jawab untuk mitigasi
                            risiko ini.
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
                            Status membantu melihat apakah risiko masih terbuka,
                            dipantau, atau sudah tertutup.
                        </small>
                    </label>

                    <label class="form-field form-field--full">
                        <span>Rencana Mitigasi</span>

                        <textarea
                            name="mitigation"
                            rows="4"
                            placeholder="Jelaskan langkah mitigasi utama yang akan dilakukan."
                            required
                        ><?= htmlspecialchars(
                            $formData['mitigation'],
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?></textarea>

                        <small class="form-helper">
                            Cantumkan langkah nyata yang bisa ditindaklanjuti,
                            bukan hanya deskripsi masalah.
                        </small>
                    </label>

                    <div class="form-actions">
                        <button
                            type="submit"
                            class="action-button action-button--primary"
                        >
                            Simpan Risiko
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
                            Panduan Risiko
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
                            Fokus
                        </span>

                        <strong>
                            Masukkan risiko yang benar-benar memengaruhi jadwal,
                            kualitas, atau kesiapan implementasi.
                        </strong>
                    </div>

                    <div class="insight-card">
                        <span class="insight-card__label">
                            Prioritas
                        </span>

                        <strong>
                            Risiko level tinggi akan lebih menonjol di dashboard
                            dan laporan manajerial.
                        </strong>
                    </div>

                    <div class="insight-card">
                        <span class="insight-card__label">
                            Mitigasi
                        </span>

                        <strong>
                            Gunakan langkah mitigasi yang jelas agar mudah
                            ditindaklanjuti dalam review mingguan.
                        </strong>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>