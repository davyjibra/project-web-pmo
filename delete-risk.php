<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
requireAuthentication();
require_once __DIR__ . '/config/audit.php';
require_once __DIR__ . '/database/initialize.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /#daftar-risiko');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('error', 'Permintaan hapus risiko tidak valid.');
    header('Location: /#daftar-risiko');
    exit;
}

$riskId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$riskId) {
    setFlashMessage('error', 'ID risiko tidak valid.');
    header('Location: /#daftar-risiko');
    exit;
}

$pdo = getDatabaseConnection();
$activeProjectKey = getActiveProjectKey();
$riskStatement = $pdo->prepare('SELECT code, title, level, status, owner FROM risks WHERE id = :id AND project_key = :project_key LIMIT 1');
$riskStatement->execute([':id' => $riskId, ':project_key' => $activeProjectKey]);
$risk = $riskStatement->fetch();

if (!is_array($risk)) {
    setFlashMessage('error', 'Risiko tidak ditemukan atau sudah terhapus.');
    header('Location: /#daftar-risiko');
    exit;
}

requireScopedModulePermission('risks', (string) $risk['owner']);

$deleteStatement = $pdo->prepare('DELETE FROM risks WHERE id = :id AND project_key = :project_key');
$deleteStatement->execute([':id' => $riskId, ':project_key' => $activeProjectKey]);

if ($deleteStatement->rowCount() > 0) {
    writeAuditLog($pdo, 'Risiko', 'delete', (string) $risk['code'] . ' - ' . (string) $risk['title'], [
        'level' => (string) $risk['level'],
        'status' => (string) $risk['status'],
    ]);
    setFlashMessage('success', 'Risiko berhasil dihapus.');
} else {
    setFlashMessage('error', 'Risiko tidak ditemukan atau sudah terhapus.');
}

header('Location: /#daftar-risiko');
exit;
