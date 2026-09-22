<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
requireAuthentication();
requireModulePermission('issues');
require_once __DIR__ . '/config/audit.php';
require_once __DIR__ . '/database/initialize.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /#daftar-risiko');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('error', 'Permintaan hapus issue log tidak valid.');
    header('Location: /#daftar-risiko');
    exit;
}

$issueId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$issueId) {
    setFlashMessage('error', 'ID issue log tidak valid.');
    header('Location: /#daftar-risiko');
    exit;
}

$pdo = getDatabaseConnection();
$activeProjectKey = getActiveProjectKey();
$statement = $pdo->prepare('SELECT code, category, pic, status FROM issue_logs WHERE id = :id AND project_key = :project_key LIMIT 1');
$statement->execute([':id' => $issueId, ':project_key' => $activeProjectKey]);
$issue = $statement->fetch();

if (!is_array($issue)) {
    setFlashMessage('error', 'Issue log tidak ditemukan atau sudah terhapus.');
    header('Location: /#daftar-risiko');
    exit;
}

requireScopedModulePermission('issues', (string) $issue['pic']);

$deleteStatement = $pdo->prepare('DELETE FROM issue_logs WHERE id = :id AND project_key = :project_key');
$deleteStatement->execute([':id' => $issueId, ':project_key' => $activeProjectKey]);

if ($deleteStatement->rowCount() > 0) {
    writeAuditLog($pdo, 'Issue Log', 'delete', (string) $issue['code'] . ' - ' . (string) $issue['category'], [
        'pic' => (string) $issue['pic'],
        'status' => (string) $issue['status'],
    ]);
    setFlashMessage('success', 'Issue log berhasil dihapus.');
} else {
    setFlashMessage('error', 'Issue log tidak ditemukan atau sudah terhapus.');
}

header('Location: /#daftar-risiko');
exit;
