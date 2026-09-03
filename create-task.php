<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
requireAuthentication();
requireModulePermission('tasks');
require_once __DIR__ . '/config/audit.php';
require_once __DIR__ . '/config/pic.php';
require_once __DIR__ . '/database/initialize.php';

$pdo = getDatabaseConnection();
$user = getAuthenticatedUser();
$pageTitle = 'Dashboard PMO | Tambah Kegiatan';
$errorMessage = '';
$flashMessage = getFlashMessage();
$picOptions = getPicNames($pdo);

$formData = [
    'code' => '',
    'name' => '',
    'workstream' => 'Teknis',
    'pic' => '',
    'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d', strtotime('+7 days')),
    'status' => 'Belum Dimulai',
    'progress' => '0',
    'priority' => '3',
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Permintaan tidak valid. Silakan muat ulang halaman lalu coba lagi.';
    } else {
        foreach ($formData as $key => $value) {
            $formData[$key] = trim((string) ($_POST[$key] ?? $value));
        }

        $progress = (int) $formData['progress'];
        $priority = (int) $formData['priority'];
        $allowedWorkstreams = ['Teknis', 'Bisnis & Nonteknis'];
        $allowedStatuses = ['Belum Dimulai', 'Sedang Berjalan', 'Menunggu', 'Selesai'];

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
            $errorMessage = 'Akun Anda tidak memiliki izin untuk menambah kegiatan pada PIC ini.';
        } elseif ($progress < 0 || $progress > 100) {
            $errorMessage = 'Nilai progres harus berada antara 0 sampai 100.';
        } elseif ($priority < 1 || $priority > 5) {
            $errorMessage = 'Prioritas harus berada antara 1 sampai 5.';
        } elseif ($formData['end_date'] < $formData['start_date']) {
            $errorMessage = 'Tanggal tenggat tidak boleh lebih awal dari tanggal mulai.';
        } elseif ($formData['status'] === 'Selesai' && $progress !== 100) {
    $errorMessage = 'Status selesai harus memiliki progres 100.';
} else {
    $duplicateStatement = $pdo->prepare(
        'SELECT COUNT(*) FROM tasks WHERE code = :code'
    );

    $duplicateStatement->execute([
        ':code' => $formData['code'],
    ]);

    if ((int) $duplicateStatement->fetchColumn() > 0) {
        $errorMessage = 'Kode kegiatan sudah digunakan. Gunakan kode lain.';
    } else {
        $insertStatement = $pdo->prepare(
            'INSERT INTO tasks (
                code, name, workstream, pic, start_date, end_date,
                status, progress, priority, notes, created_at, updated_at
            ) VALUES (
                :code, :name, :workstream, :pic, :start_date, :end_date,
                :status, :progress, :priority, :notes, :created_at, :updated_at
            )'
        );

        $timestamp = date('Y-m-d H:i:s');

        $insertStatement->execute([
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
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);

        writeAuditLog(
            $pdo,
            'Kegiatan',
            'create',
            $formData['code'] . ' - ' . $formData['name'],
            [
                'workstream' => $formData['workstream'],
                'status' => $formData['status'],
                'pic' => $formData['pic'],
            ]
        );

        setFlashMessage(
            'success',
            'Kegiatan baru berhasil ditambahkan ke dashboard.'
        );

        header('Location: /tasks/create');
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
                <h2>Tambah Kegiatan</h2>
                <p>Form input sederhana untuk memperbarui daftar kegiatan proyek.</p>
            </div>
            <div class="topbar__controls">
                <a href="/" class="action-button">Kembali ke Dashboard</a>
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
                        <h3 class="panel__title">Data Kegiatan</h3>
                        <p class="section-subtitle">Lengkapi data kegiatan secara ringkas dan konsisten agar monitoring operasional tetap rapi.</p>
                    </div>
                </div>
                <form method="post" class="task-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <label class="form-field">
                        <span>Kode</span>
                        <input type="text" name="code" value="<?= htmlspecialchars($formData['code'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Contoh: TJK-011" required>
                        <small class="form-helper">Gunakan kode unik agar kegiatan mudah direferensikan saat rapat dan ekspor laporan.</small>
                    </label>
                    <label class="form-field form-field--wide">
                        <span>Nama Kegiatan</span>
                        <input type="text" name="name" value="<?= htmlspecialchars($formData['name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Contoh: Finalisasi uji integrasi pembayaran" required>
                        <small class="form-helper">Pakai nama yang spesifik dan langsung menjelaskan output aktivitas.</small>
                    </label>
                    <label class="form-field">
                        <span>Workstream</span>
                        <select name="workstream">
                            <?php foreach (['Teknis', 'Bisnis & Nonteknis'] as $workstream): ?>
                                <option value="<?= htmlspecialchars($workstream, ENT_QUOTES, 'UTF-8'); ?>"<?= $formData['workstream'] === $workstream ? ' selected' : ''; ?>>
                                    <?= htmlspecialchars($workstream, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-helper">Pilih area kerja utama agar distribusi progres per workstream tetap akurat.</small>
                    </label>
                    <label class="form-field">
                        <span>PIC</span>
                        <select name="pic" required>
                            <option value="">Pilih PIC</option>
                            <?php foreach ($picOptions as $picOption): ?>
                                <option value="<?= htmlspecialchars($picOption, ENT_QUOTES, 'UTF-8'); ?>"<?= $formData['pic'] === $picOption ? ' selected' : ''; ?>><?= htmlspecialchars($picOption, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-helper">PIC diambil dari master agar nama penanggung jawab konsisten di seluruh dashboard.</small>
                    </label>
                    <label class="form-field">
                        <span>Tanggal Mulai</span>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($formData['start_date'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Gunakan tanggal mulai aktual atau rencana kick-off kegiatan.</small>
                    </label>
                    <label class="form-field">
                        <span>Tenggat</span>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($formData['end_date'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Tanggal ini akan dipakai untuk deteksi kegiatan jatuh tempo dan terlambat.</small>
                    </label>
                    <label class="form-field">
                        <span>Status</span>
                        <select name="status">
                            <?php foreach (['Belum Dimulai', 'Sedang Berjalan', 'Menunggu', 'Selesai'] as $status): ?>
                                <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"<?= $formData['status'] === $status ? ' selected' : ''; ?>>
                                    <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-helper">Status dipakai di kartu ringkasan dan distribusi operasional pada dashboard.</small>
                    </label>
                    <label class="form-field">
                        <span>Progres (%)</span>
                        <input type="number" name="progress" min="0" max="100" value="<?= htmlspecialchars($formData['progress'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Isi 100 hanya jika kegiatan benar-benar selesai.</small>
                    </label>
                    <label class="form-field">
                        <span>Prioritas (1-5)</span>
                        <input type="number" name="priority" min="1" max="5" value="<?= htmlspecialchars($formData['priority'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <small class="form-helper">Angka lebih tinggi berarti lebih prioritas untuk ditinjau manajemen.</small>
                    </label>
                    <label class="form-field form-field--full">
                        <span>Catatan</span>
                        <textarea name="notes" rows="4" placeholder="Tambahkan konteks, kendala, atau tindak lanjut singkat."><?= htmlspecialchars($formData['notes'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <small class="form-helper">Opsional, tetapi berguna untuk memberi konteks singkat pada progres kegiatan.</small>
                    </label>
                    <div class="form-actions">
                        <button type="submit" class="action-button action-button--primary">Simpan Kegiatan</button>
                        <a href="/pics/create" class="action-button">Tambah PIC Baru</a>
                        <a href="/#bisnis-operasional" class="action-button">Lihat Daftar Kegiatan</a>
                    </div>
                </form>
            </section>

            <aside class="panel panel--soft">
                <div class="panel__header">
                    <div>
                        <h3 class="panel__title">Panduan Input</h3>
                        <p class="section-subtitle">Diinput oleh <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>.</p>
                    </div>
                </div>
                <div class="insight-list">
                    <div class="insight-card">
                        <span class="insight-card__label">Fokus</span>
                        <strong>Masukkan hanya satu kegiatan per output kerja agar monitoring lebih jelas.</strong>
                    </div>
                    <div class="insight-card">
                        <span class="insight-card__label">Prioritas</span>
                        <strong>Kegiatan dengan prioritas tinggi akan lebih mudah muncul di ringkasan prioritas dashboard.</strong>
                    </div>
                    <div class="insight-card">
                        <span class="insight-card__label">Dampak</span>
                        <strong>Setelah disimpan, kegiatan langsung masuk ke timeline sesuai area kerja dan laporan.</strong>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
