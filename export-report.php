<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

requireAuthentication();
requireModulePermission('*');

require_once __DIR__ . '/database/initialize.php';

$pdo = getDatabaseConnection();
$today = date('Y-m-d');

$statement = $pdo->prepare(
    'SELECT
        code,
        name,
        workstream,
        pic,
        start_date,
        end_date,
        CASE
            WHEN progress = 100 THEN "Selesai"
            WHEN end_date < :today AND progress < 100 THEN "Terlambat"
            ELSE status
        END AS status,
        progress,
        priority
    FROM tasks
    ORDER BY priority DESC, end_date ASC'
);
$statement->execute([':today' => $today]);
$rows = $statement->fetchAll();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="laporan-pmo-transjakarta.csv"');

$output = fopen('php://output', 'wb');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Kode', 'Nama Kegiatan', 'Workstream', 'PIC', 'Tanggal Mulai', 'Tenggat', 'Status', 'Progres', 'Prioritas']);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['code'],
        $row['name'],
        $row['workstream'],
        $row['pic'],
        $row['start_date'],
        $row['end_date'],
        $row['status'],
        $row['progress'],
        $row['priority'],
    ]);
}

fclose($output);
exit;
