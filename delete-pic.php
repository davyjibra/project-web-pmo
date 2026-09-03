<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
requireAuthentication();
requireModulePermission('pics');

require_once __DIR__ . '/config/audit.php';
require_once __DIR__ . '/database/initialize.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /?tab=master-pic');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('error', 'Permintaan hapus PIC tidak valid.');
    header('Location: /?tab=master-pic');
    exit;
}

$picId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$picId) {
    setFlashMessage('error', 'ID PIC tidak valid.');
    header('Location: /?tab=master-pic');
    exit;
}

$pdo = getDatabaseConnection();

$picStatement = $pdo->prepare(
    'SELECT name, role_label FROM pics WHERE id = :id LIMIT 1'
);

$picStatement->execute([
    ':id' => $picId,
]);

$pic = $picStatement->fetch();

if (!$pic) {
    setFlashMessage('error', 'PIC tidak ditemukan.');
    header('Location: /?tab=master-pic');
    exit;
}

$usageStatement = $pdo->prepare(
    'SELECT
        (SELECT COUNT(*) FROM tasks WHERE pic = :name) +
        (SELECT COUNT(*) FROM milestones WHERE pic = :name) +
        (SELECT COUNT(*) FROM issue_logs WHERE pic = :name) AS usage_count'
);

$usageStatement->execute([
    ':name' => (string) $pic['name'],
]);

$usageCount = (int) $usageStatement->fetchColumn();

if ($usageCount > 0) {
    setFlashMessage(
        'error',
        'PIC masih dipakai pada kegiatan, milestone, atau issue sehingga belum bisa dihapus.'
    );

    header('Location: /?tab=master-pic');
    exit;
}

$deleteStatement = $pdo->prepare(
    'DELETE FROM pics WHERE id = :id'
);

$deleteStatement->execute([
    ':id' => $picId,
]);

if ($deleteStatement->rowCount() > 0) {
    writeAuditLog(
        $pdo,
        'Master PIC',
        'delete',
        (string) $pic['name'],
        [
            'role_label' => (string) ($pic['role_label'] ?? ''),
        ]
    );

    setFlashMessage('success', 'PIC berhasil dihapus dari master.');
} else {
    setFlashMessage('error', 'PIC tidak ditemukan atau sudah terhapus.');
}

header('Location: /?tab=master-pic');
exit;