<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
requireAuthentication();
require_once __DIR__ . '/config/audit.php';
require_once __DIR__ . '/database/initialize.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /#bisnis-operasional');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('error', 'Permintaan hapus tidak valid.');
    header('Location: /#bisnis-operasional');
    exit;
}

$taskId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$taskId) {
    setFlashMessage('error', 'ID kegiatan tidak valid.');
    header('Location: /#bisnis-operasional');
    exit;
}

$pdo = getDatabaseConnection();
$taskStatement = $pdo->prepare('SELECT code, name, workstream, status, pic FROM tasks WHERE id = :id LIMIT 1');
$taskStatement->execute([':id' => $taskId]);
$task = $taskStatement->fetch();

if (!is_array($task)) {
    setFlashMessage('error', 'Kegiatan tidak ditemukan atau sudah terhapus.');
    header('Location: /#bisnis-operasional');
    exit;
}

requireScopedModulePermission('tasks', (string) $task['pic']);

$deleteStatement = $pdo->prepare('DELETE FROM tasks WHERE id = :id');
$deleteStatement->execute([':id' => $taskId]);

if ($deleteStatement->rowCount() > 0) {
    writeAuditLog($pdo, 'Kegiatan', 'delete', (string) $task['code'] . ' - ' . (string) $task['name'], [
        'workstream' => (string) $task['workstream'],
        'status' => (string) $task['status'],
    ]);
    setFlashMessage('success', 'Kegiatan berhasil dihapus.');
} else {
    setFlashMessage('error', 'Kegiatan tidak ditemukan atau sudah terhapus.');
}

header('Location: /#bisnis-operasional');
exit;
