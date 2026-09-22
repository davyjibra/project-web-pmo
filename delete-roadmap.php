<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
requireAuthentication();
requireModulePermission('roadmaps');
require_once __DIR__ . '/config/audit.php';
require_once __DIR__ . '/database/initialize.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /#peta-jalan');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('error', 'Permintaan hapus peta jalan tidak valid.');
    header('Location: /#peta-jalan');
    exit;
}

$roadmapId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$roadmapId) {
    setFlashMessage('error', 'ID peta jalan tidak valid.');
    header('Location: /#peta-jalan');
    exit;
}

$pdo = getDatabaseConnection();
$activeProjectKey = getActiveProjectKey();
$roadmapStatement = $pdo->prepare('SELECT phase, status, sequence FROM roadmaps WHERE id = :id AND project_key = :project_key LIMIT 1');
$roadmapStatement->execute([':id' => $roadmapId, ':project_key' => $activeProjectKey]);
$roadmap = $roadmapStatement->fetch();
$delete = $pdo->prepare('DELETE FROM roadmaps WHERE id = :id AND project_key = :project_key');
$delete->execute([':id' => $roadmapId, ':project_key' => $activeProjectKey]);
if ($delete->rowCount() > 0 && is_array($roadmap)) {
    writeAuditLog($pdo, 'Peta Jalan', 'delete', (string) $roadmap['phase'], [
        'status' => (string) $roadmap['status'],
        'sequence' => (int) $roadmap['sequence'],
    ]);
}
setFlashMessage($delete->rowCount() > 0 ? 'success' : 'error', $delete->rowCount() > 0 ? 'Tahap peta jalan berhasil dihapus.' : 'Tahap peta jalan tidak ditemukan atau sudah terhapus.');
header('Location: /#peta-jalan');
exit;
