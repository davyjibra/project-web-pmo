<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
requireAuthentication();
require_once __DIR__ . '/config/audit.php';
require_once __DIR__ . '/database/initialize.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /#linimasa-teknis');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('error', 'Permintaan hapus milestone tidak valid.');
    header('Location: /#linimasa-teknis');
    exit;
}

$milestoneId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$milestoneId) {
    setFlashMessage('error', 'ID milestone tidak valid.');
    header('Location: /#linimasa-teknis');
    exit;
}

$pdo = getDatabaseConnection();
$milestoneStatement = $pdo->prepare('SELECT name, target_date, status, pic FROM milestones WHERE id = :id LIMIT 1');
$milestoneStatement->execute([':id' => $milestoneId]);
$milestone = $milestoneStatement->fetch();

if (!is_array($milestone)) {
    setFlashMessage('error', 'Milestone tidak ditemukan atau sudah terhapus.');
    header('Location: /#linimasa-teknis');
    exit;
}

requireScopedModulePermission('milestones', (string) $milestone['pic']);

$deleteStatement = $pdo->prepare('DELETE FROM milestones WHERE id = :id');
$deleteStatement->execute([':id' => $milestoneId]);

if ($deleteStatement->rowCount() > 0) {
    writeAuditLog($pdo, 'Milestone', 'delete', (string) $milestone['name'], [
        'target_date' => (string) $milestone['target_date'],
        'status' => (string) $milestone['status'],
    ]);
    setFlashMessage('success', 'Milestone berhasil dihapus.');
} else {
    setFlashMessage('error', 'Milestone tidak ditemukan atau sudah terhapus.');
}

header('Location: /#linimasa-teknis');
exit;
