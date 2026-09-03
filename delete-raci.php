<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
requireAuthentication();
requireModulePermission('raci');
require_once __DIR__ . '/config/audit.php';
require_once __DIR__ . '/database/initialize.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /#matriks-raci');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('error', 'Permintaan hapus RACI tidak valid.');
    header('Location: /#matriks-raci');
    exit;
}

$raciId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$raciId) {
    setFlashMessage('error', 'ID RACI tidak valid.');
    header('Location: /#matriks-raci');
    exit;
}

$pdo = getDatabaseConnection();
$raciStatement = $pdo->prepare('SELECT activity, responsible, accountable, sequence FROM raci_matrix WHERE id = :id LIMIT 1');
$raciStatement->execute([':id' => $raciId]);
$raci = $raciStatement->fetch();
$delete = $pdo->prepare('DELETE FROM raci_matrix WHERE id = :id');
$delete->execute([':id' => $raciId]);
if ($delete->rowCount() > 0 && is_array($raci)) {
    writeAuditLog($pdo, 'Matriks RACI', 'delete', (string) $raci['activity'], [
        'responsible' => (string) $raci['responsible'],
        'accountable' => (string) $raci['accountable'],
        'sequence' => (int) $raci['sequence'],
    ]);
}
setFlashMessage($delete->rowCount() > 0 ? 'success' : 'error', $delete->rowCount() > 0 ? 'Baris RACI berhasil dihapus.' : 'Baris RACI tidak ditemukan atau sudah terhapus.');
header('Location: /#matriks-raci');
exit;
