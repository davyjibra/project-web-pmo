<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function writeAuditLog(PDO $pdo, string $module, string $action, string $entityLabel, array $context = []): void
{
    $user = getAuthenticatedUser();
    $statement = $pdo->prepare(
        'INSERT INTO audit_logs (
            module,
            action,
            entity_label,
            performed_by,
            performed_role,
            context,
            created_at,
            project_key
        ) VALUES (
            :module,
            :action,
            :entity_label,
            :performed_by,
            :performed_role,
            :context,
            :created_at,
            :project_key
        )'
    );

    $statement->execute([
        ':module' => $module,
        ':action' => $action,
        ':entity_label' => $entityLabel,
        ':performed_by' => (string) ($user['name'] ?? 'Admin PMO'),
        ':performed_role' => (string) ($user['role'] ?? 'Administrator'),
        ':context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':created_at' => date('Y-m-d H:i:s'),
        ':project_key' => getActiveProjectKey(),
    ]);
}

function formatAuditActionLabel(string $action): string
{
    $labels = [
        'create' => 'Tambah',
        'update' => 'Perbarui',
        'delete' => 'Hapus',
    ];

    return $labels[$action] ?? ucfirst($action);
}
