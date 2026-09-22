<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
requireAuthentication();

require_once __DIR__ . '/config/audit.php';
require_once __DIR__ . '/config/pic.php';
require_once __DIR__ . '/database/initialize.php';

$pdo = getDatabaseConnection();
$activeProjectKey = getActiveProjectKey();
$user = getAuthenticatedUser();
$pageTitle = 'Dashboard PMO | Edit Kegiatan';
$errorMessage = '';

$allowedWorkstreams = [
    'Teknis',
    'Bisnis & Nonteknis',
];

$allowedStatuses = [
    'Belum Dimulai',
    'Sedang Berjalan',
    'Menunggu',
    'Selesai',
];

$picOptions = getPicNames($pdo);

$taskId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$taskId) {
    setFlashMessage('error', 'Kegiatan yang akan diedit tidak ditemukan.');
    header('Location: /#bisnis-operasional');
    exit;
}

$taskStatement = $pdo->prepare(
    'SELECT * FROM tasks WHERE id = :id AND project_key = :project_key LIMIT 1'
);

$taskStatement->execute([
    ':id' => $taskId,
    ':project_key' => $activeProjectKey,
]);

$task = $taskStatement->fetch();

if (!$task) {
    setFlashMessage('error', 'Data kegiatan tidak tersedia.');
    header('Location: /#bisnis-operasional');
    exit;
}

requireScopedModulePermission(
    'tasks',
    (string) $task['pic']
);

$formData = [
    'code' => (string) $task['code'],
    'name' => (string) $task['name'],
    'workstream' => (string) $task['workstream'],
    'pic' => (string) $task['pic'],
    'start_date' => (string) $task['start_date'],
    'end_date' => (string) $task['end_date'],
    'status' => (string) $task['status'],
    'progress' => (string) $task['progress'],
    'priority' => (string) $task['priority'],
    'notes' => (string) ($task['notes'] ?? ''),
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

        $progress = (int) $formData['progress'];
        $priority = (int) $formData['priority'];

        if (
            $formData['code'] === '' ||
            $formData['name'] === '' ||
            $formData['pic'] === '' ||
            !in_array($formData['workstream'], $allowedWorkstreams, true) ||
            !in_array($formData['status'], $allowedStatuses, true) ||
            !in_array($formData['pic'], $picOptions, true)
        ) {
            $errorMessage = 'Mohon lengkapi seluruh data kegiatan dengan benar.';
        } elseif (!canManageScopedRecord('tasks', $formData['pic'])) {
            $errorMessage = 'Akun Anda tidak memiliki izin untuk memindahkan kegiatan ke PIC ini.';
        } elseif ($progress < 0 || $progress > 100) {
            $errorMessage = 'Nilai progres harus berada antara 0 sampai 100.';
        } elseif ($priority < 1 || $priority > 5) {
            $errorMessage = 'Prioritas harus berada antara 1 sampai 5.';
        } elseif ($formData['end_date'] < $formData['start_date']) {
            $errorMessage = 'Tanggal tenggat tidak boleh lebih awal dari tanggal mulai.';
        } elseif ($formData['status'] === 'Selesai' && $progress !== 100) {
            $errorMessage = 'Status selesai harus memiliki progres 100.';
        } else {
            /*
             * Cek apakah kode kegiatan sudah digunakan
             * oleh kegiatan lain.
             *
             * ID kegiatan saat ini dikecualikan agar user
             * tetap boleh mempertahankan kode yang sama.
             */
            $duplicateStatement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM tasks
                 WHERE code = :code
                   AND id != :id
                   AND project_key = :project_key'
            );

            $duplicateStatement->execute([
                ':code' => $formData['code'],
                ':id' => $taskId,
                ':project_key' => $activeProjectKey,
            ]);

            $duplicateCount = (int) $duplicateStatement->fetchColumn();

            if ($duplicateCount > 0) {
                $errorMessage = 'Kode kegiatan sudah digunakan. Gunakan kode lain.';
            } else {
                $updateStatement = $pdo->prepare(
                    'UPDATE tasks SET
                        code = :code,
                        name = :name,
                        workstream = :workstream,
                        pic = :pic,
                        start_date = :start_date,
                        end_date = :end_date,
                        status = :status,
                        progress = :progress,
                        priority = :priority,
                        notes = :notes,
                        updated_at = :updated_at
                     WHERE id = :id'
                );

                $timestamp = date('Y-m-d H:i:s');

                $updateStatement->execute([
                    ':code' => $formData['code'],
                    ':name' => $formData['name'],
                    ':workstream' => $formData['workstream'],
                    ':pic' => $formData['pic'],
                    ':start_date' => $formData['start_date'],
                    ':end_date' => $formData['end_date'],
                    ':status' => $formData['status'],
                    ':progress' => $progress,
                    ':priority' => $priority,
                    ':notes' => $formData['notes'],
                    ':updated_at' => $timestamp,
                    ':id' => $taskId,
                ]);

                writeAuditLog(
                    $pdo,
                    'Kegiatan',
                    'update',
                    $formData['code'] . ' - ' . $formData['name'],
                    [
                        'workstream' => $formData['workstream'],
                        'status' => $formData['status'],
                        'progress' => $progress,
                        'priority' => $priority,
                    ]
                );

                setFlashMessage(
                    'success',
                    'Kegiatan berhasil diperbarui.'
                );

                header('Location: /#bisnis-operasional');
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
                <h2>Edit Kegiatan</h2>
                <p>Perbarui detail kegiatan proyek yang sudah terdaftar di dashboard.</p>
            </div>

            <div class="topbar__controls">
                <a href="/#bisnis-operasional" class="action-button">Kembali ke Daftar</a>
                <a href="/logout" class="action-button">Keluar</a>
            </div>
        </header>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert--error">
                <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="content-grid content-grid--form-aside">
            <section class="form-card form-card--highlight">
                <div class="panel__header">
                    <div>
                        <h3 class="panel__title">Perbarui Data Kegiatan</h3>
                        <p class="section-subtitle">
                            Sesuaikan progres, PIC, dan tenggat agar monitoring operasional
                            tetap relevan dengan kondisi terbaru.
                        </p>
                    </div>
                </div>

                <form method="post" class="task-form">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>"
                    >

                    <label class="form-field">
                        <span>Kode</span>
                        <input
                            type="text"
                            name="code"
                            value="<?= htmlspecialchars($formData['code'], ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >
                        <small class="form-helper">
                            Pertahankan kode unik agar histori kegiatan tetap konsisten.
                        </small>
                    </label>

                    <label class="form-field form-field--wide">
                        <span>Nama Kegiatan</span>
                        <input
                            type="text"
                            name="name"
                            value="<?= htmlspecialchars($formData['name'], ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >
                        <small class="form-helper">
                            Perbarui nama hanya bila ada perubahan ruang lingkup atau output kegiatan.
                        </small>
                    </label>

                    <label class="form-field">
                        <span>Workstream</span>

                        <select name="workstream">
                            <?php foreach ($allowedWorkstreams as $workstream): ?>
                                <option
                                    value="<?= htmlspecialchars($workstream, ENT_QUOTES, 'UTF-8'); ?>"
                                    <?= $formData['workstream'] === $workstream ? ' selected' : ''; ?>
                                >
                                    <?= htmlspecialchars($workstream, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <small class="form-helper">
                            Pastikan workstream sesuai agar distribusi dashboard tidak bias.
                        </small>
                    </label>

                    <label class="form-field">
                        <span>PIC</span>

                        <select name="pic" required>
                            <option value="">Pilih PIC</option>

                            <?php foreach ($picOptions as $picOption): ?>
                                <option
                                    value="<?= htmlspecialchars($picOption, ENT_QUOTES, 'UTF-8'); ?>"
                                    <?= $formData['pic'] === $picOption ? ' selected' : ''; ?>
                                >
                                    <?= htmlspecialchars($picOption, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <small class="form-helper">
                            Gunakan master PIC untuk menjaga konsistensi nama penanggung jawab.
                        </small>
                    </label>

                    <label class="form-field">
                        <span>Tanggal Mulai</span>
                        <input
                            type="date"
                            name="start_date"
                            value="<?= htmlspecialchars($formData['start_date'], ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >
                        <small class="form-helper">
                            Tanggal mulai bisa disesuaikan jika ada perubahan baseline kegiatan.
                        </small>
                    </label>

                    <label class="form-field">
                        <span>Tenggat</span>
                        <input
                            type="date"
                            name="end_date"
                            value="<?= htmlspecialchars($formData['end_date'], ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >
                        <small class="form-helper">
                            Update tenggat bila ada revisi target atau hasil rapat koordinasi.
                        </small>
                    </label>

                    <label class="form-field">
                        <span>Status</span>

                        <select name="status">
                            <?php foreach ($allowedStatuses as $status): ?>
                                <option
                                    value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"
                                    <?= $formData['status'] === $status ? ' selected' : ''; ?>
                                >
                                    <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <small class="form-helper">
                            Status terbaru akan langsung mengubah ringkasan dan tabel operasional.
                        </small>
                    </label>

                    <label class="form-field">
                        <span>Progres (%)</span>
                        <input
                            type="number"
                            name="progress"
                            min="0"
                            max="100"
                            value="<?= htmlspecialchars($formData['progress'], ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >
                        <small class="form-helper">
                            Progres 100 akan dianggap selesai oleh sistem.
                        </small>
                    </label>

                    <label class="form-field">
                        <span>Prioritas (1-5)</span>
                        <input
                            type="number"
                            name="priority"
                            min="1"
                            max="5"
                            value="<?= htmlspecialchars($formData['priority'], ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >
                        <small class="form-helper">
                            Naikkan prioritas bila kegiatan perlu perhatian manajerial lebih tinggi.
                        </small>
                    </label>

                    <label class="form-field form-field--full">
                        <span>Catatan</span>
                        <textarea
                            name="notes"
                            rows="4"
                        ><?= htmlspecialchars($formData['notes'], ENT_QUOTES, 'UTF-8'); ?></textarea>

                        <small class="form-helper">
                            Tambahkan kendala, keputusan, atau tindak lanjut singkat bila perlu.
                        </small>
                    </label>

                    <div class="form-actions">
                        <button
                            type="submit"
                            class="action-button action-button--primary"
                        >
                            Simpan Perubahan
                        </button>

                        <?php if (canManageModule('*')): ?>
                        <a href="/pics/create" class="action-button">
                            Tambah PIC Baru
                        </a>
                        <?php endif; ?>

                        <a href="/#bisnis-operasional" class="action-button">
                            Batal
                        </a>
                    </div>
                </form>
            </section>

            <aside class="panel panel--soft">
                <div class="panel__header">
                    <div>
                        <h3 class="panel__title">Catatan Update</h3>

                        <p class="section-subtitle">
                            Terakhir diubah oleh
                            <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>.
                        </p>
                    </div>
                </div>

                <div class="insight-list">
                    <div class="insight-card">
                        <span class="insight-card__label">Nama Saat Ini</span>

                        <strong>
                            <?= htmlspecialchars(
                                $task['code'] . ' - ' . $task['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </strong>
                    </div>

                    <div class="insight-card">
                        <span class="insight-card__label">Status Saat Ini</span>

                        <strong>
                            <?= htmlspecialchars(
                                (string) $task['status'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                            dengan progres
                            <?= htmlspecialchars(
                                (string) $task['progress'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>%
                        </strong>
                    </div>

                    <div class="insight-card">
                        <span class="insight-card__label">Dampak</span>

                        <strong>
                            Perubahan akan langsung tercermin pada timeline area kerja
                            dan audit trail laporan.
                        </strong>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
