<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
requireAuthentication();
require_once __DIR__ . '/config/audit.php';
require_once __DIR__ . '/config/pic.php';
require_once __DIR__ . '/database/initialize.php';

$pdo = getDatabaseConnection();
$user = getAuthenticatedUser();
$activeProjectKey = getActiveProjectKey();
$activeProject = getActiveProject();
$projectDashboardTitle = $activeProjectKey === 'taj'
    ? 'Kondisi Implementasi Terminal Angkutan Jalan'
    : 'Kondisi Implementasi EIPTJ';
$projectSnapshotDate = $activeProjectKey === 'taj'
    ? '24 Agu 2026'
    : '17 Jul 2026';
$flashMessage = getFlashMessage();
$today = date('Y-m-d');
$lastUpdatedStatement = $pdo->prepare(
    'SELECT MAX(updated_at) FROM (
        SELECT updated_at FROM tasks WHERE project_key = :project_key
        UNION ALL SELECT updated_at FROM milestones WHERE project_key = :project_key
        UNION ALL SELECT updated_at FROM risks WHERE project_key = :project_key
        UNION ALL SELECT updated_at FROM roadmaps WHERE project_key = :project_key
        UNION ALL SELECT updated_at FROM raci_matrix WHERE project_key = :project_key
    )'
);
$lastUpdatedStatement->execute([':project_key' => $activeProjectKey]);
$lastUpdated = (string) $lastUpdatedStatement->fetchColumn();

$taskStatement = $pdo->prepare(
    'SELECT
        id,
        code,
        name,
        workstream,
        pic,
        start_date,
        end_date,
        notes,
        CASE
            WHEN progress = 100 THEN "Selesai"
            WHEN end_date < :today AND progress < 100 THEN "Terlambat"
            ELSE status
        END AS derived_status,
        progress,
        priority
    FROM tasks
    WHERE project_key = :project_key'
);
$taskStatement->execute([':today' => $today, ':project_key' => $activeProjectKey]);
$tasks = $taskStatement->fetchAll();

$totalTasks = count($tasks);
$completedTasks = 0;
$inProgressTasks = 0;
$notStartedTasks = 0;
$lateTasks = 0;
$waitingTasks = 0;
$totalProgress = 0;
$workstreamProgress = [
    'Teknis' => ['sum' => 0, 'count' => 0],
    'Bisnis & Nonteknis' => ['sum' => 0, 'count' => 0],
];

foreach ($tasks as &$task) {
    $progress = max(0, min(100, (int) $task['progress']));
    $status = $task['derived_status'];
    $task['progress'] = $progress;
    $task['status'] = $status;

    $totalProgress += $progress;

    if ($status === 'Selesai') {
        $completedTasks++;
    } elseif ($status === 'Sedang Berjalan') {
        $inProgressTasks++;
    } elseif ($status === 'Belum Dimulai') {
        $notStartedTasks++;
    } elseif ($status === 'Terlambat') {
        $lateTasks++;
    } elseif ($status === 'Menunggu') {
        $waitingTasks++;
    }

    if (isset($workstreamProgress[$task['workstream']])) {
        $workstreamProgress[$task['workstream']]['sum'] += $progress;
        $workstreamProgress[$task['workstream']]['count']++;
    }
}
unset($task);

$overallProgress = $totalTasks > 0 ? (int) round($totalProgress / $totalTasks) : 0;

$workstreamAverages = [];
foreach ($workstreamProgress as $label => $item) {
    $workstreamAverages[$label] = $item['count'] > 0 ? (int) round($item['sum'] / $item['count']) : 0;
}

$taskSearch = trim((string) ($_GET['task_search'] ?? ''));
$taskStatusFilter = trim((string) ($_GET['task_status'] ?? 'Semua Status'));
$taskWorkstreamFilter = trim((string) ($_GET['task_workstream'] ?? 'Semua Workstream'));
$taskPicFilter = trim((string) ($_GET['task_pic'] ?? 'Semua PIC'));
$taskFilterOptions = [
    'statuses' => ['Semua Status', 'Belum Dimulai', 'Sedang Berjalan', 'Menunggu', 'Selesai', 'Terlambat'],
    'workstreams' => ['Semua Area Bisnis & Nonteknis', 'Bisnis & Nonteknis'],
    'pics' => ['Semua PIC'],
];
$riskSearch = trim((string) ($_GET['risk_search'] ?? ''));
$riskLevelFilter = trim((string) ($_GET['risk_level'] ?? 'Semua Level'));
$riskStatusFilter = trim((string) ($_GET['risk_status'] ?? 'Semua Status'));
$riskFilterOptions = [
    'levels' => ['Semua Level', 'Tinggi', 'Sedang', 'Rendah'],
    'statuses' => ['Semua Status', 'Terbuka', 'Mitigasi Berjalan', 'Dipantau', 'Selesai'],
];
$picSearch = trim((string) ($_GET['pic_search'] ?? ''));
$picRoleFilter = trim((string) ($_GET['pic_role'] ?? 'Semua Peran'));

$priorityTasks = $tasks;
usort(
    $priorityTasks,
    static function (array $left, array $right): int {
        $priorityComparison = (int) $right['priority'] <=> (int) $left['priority'];

        if ($priorityComparison !== 0) {
            return $priorityComparison;
        }

        return strcmp((string) $left['end_date'], (string) $right['end_date']);
    }
);
$priorityTasks = array_slice($priorityTasks, 0, 5);

$allTasks = $tasks;
usort(
    $allTasks,
    static function (array $left, array $right): int {
        return strcmp((string) $left['end_date'], (string) $right['end_date']);
    }
);

$technicalTasks = array_values(array_filter(
    $allTasks,
    static fn (array $task): bool => (string) $task['workstream'] === 'Teknis'
));

$operationalTasks = array_values(array_filter(
    $allTasks,
    static fn (array $task): bool => (string) $task['workstream'] !== 'Teknis'
));

foreach ($operationalTasks as $task) {
    if (!in_array((string) $task['pic'], $taskFilterOptions['pics'], true)) {
        $taskFilterOptions['pics'][] = (string) $task['pic'];
    }
}

$filteredTasks = array_values(array_filter(
    $operationalTasks,
    static function (array $task) use ($taskSearch, $taskStatusFilter, $taskWorkstreamFilter, $taskPicFilter): bool {
        if ($taskSearch !== '') {
            $haystack = implode(' ', [
                (string) $task['code'],
                (string) $task['name'],
                (string) $task['workstream'],
                (string) $task['pic'],
            ]);

            if (stripos($haystack, $taskSearch) === false) {
                return false;
            }
        }

        if ($taskStatusFilter !== 'Semua Status' && $task['status'] !== $taskStatusFilter) {
            return false;
        }

        if ($taskWorkstreamFilter !== 'Semua Area BO' && $task['workstream'] !== $taskWorkstreamFilter) {
            return false;
        }

        if ($taskPicFilter !== 'Semua PIC' && $task['pic'] !== $taskPicFilter) {
            return false;
        }

        return true;
    }
));

$milestoneStatement = $pdo->prepare(
    'SELECT id, name, target_date, status, pic
     FROM milestones
     WHERE project_key = :project_key
     ORDER BY target_date ASC'
);
$milestoneStatement->execute([':project_key' => $activeProjectKey]);
$milestones = $milestoneStatement->fetchAll();

$riskStatement = $pdo->prepare(
    'SELECT id, code, title, category, level, owner, status
     FROM risks
     WHERE project_key = :project_key
     ORDER BY
        CASE level
            WHEN "Tinggi" THEN 1
            WHEN "Sedang" THEN 2
            ELSE 3
        END,
        code ASC'
);
$riskStatement->execute([':project_key' => $activeProjectKey]);
$risks = $riskStatement->fetchAll();

$filteredRisks = array_values(array_filter(
    $risks,
    static function (array $risk) use ($riskSearch, $riskLevelFilter, $riskStatusFilter): bool {
        if ($riskSearch !== '') {
            $haystack = implode(' ', [
                (string) $risk['code'],
                (string) $risk['title'],
                (string) $risk['category'],
                (string) $risk['owner'],
            ]);

            if (stripos($haystack, $riskSearch) === false) {
                return false;
            }
        }

        if ($riskLevelFilter !== 'Semua Level' && $risk['level'] !== $riskLevelFilter) {
            return false;
        }

        if ($riskStatusFilter !== 'Semua Status' && $risk['status'] !== $riskStatusFilter) {
            return false;
        }

        return true;
    }
));

$priorityRiskCount = count(array_filter(
    $risks,
    static fn (array $risk): bool => $risk['level'] === 'Tinggi'
));

$openRiskCount = 0;

$statusGroups = [
    'Belum Dimulai' => 0,
    'Sedang Berjalan' => 0,
    'Menunggu' => 0,
    'Selesai' => 0,
    'Terlambat' => 0,
];

$boStatusGroups = $statusGroups;
foreach ($boStatusGroups as $label => $value) {
    $boStatusGroups[$label] = 0;
}

foreach ($tasks as $task) {
    if (isset($statusGroups[$task['status']])) {
        $statusGroups[$task['status']]++;
    }
}

foreach ($operationalTasks as $task) {
    if (isset($boStatusGroups[$task['status']])) {
        $boStatusGroups[$task['status']]++;
    }
}

foreach ($risks as $risk) {
    if ($risk['status'] !== 'Selesai') {
        $openRiskCount++;
    }
}

$roadmapStatement = $pdo->prepare(
    'SELECT id, phase, detail, status, sequence
     FROM roadmaps
     WHERE project_key = :project_key
     ORDER BY sequence ASC, id ASC'
);
$roadmapStatement->execute([':project_key' => $activeProjectKey]);
$roadmapItems = $roadmapStatement->fetchAll();

$raciStatement = $pdo->prepare(
    'SELECT id, activity, responsible, accountable, consulted, informed, sequence
     FROM raci_matrix
     WHERE project_key = :project_key
     ORDER BY sequence ASC, id ASC'
);
$raciStatement->execute([':project_key' => $activeProjectKey]);
$raciRows = $raciStatement->fetchAll();

$picStatement = $pdo->query(
    'SELECT id, name, person_name, role_label, updated_at
     FROM pics
     ORDER BY name ASC'
);
$picRows = $picStatement->fetchAll();

if (!userCanAccessValue('*')) {
    $picRows = array_values(array_filter(
        $picRows,
        static fn (array $pic): bool => userCanAccessValue((string) $pic['name'])
    ));
}
$picWithoutRoleCount = 0;
$picRoleOptions = ['Semua Peran'];

foreach ($picRows as $picRow) {
    if (!isset($picRow['role_label']) || trim((string) $picRow['role_label']) === '') {
        $picWithoutRoleCount++;
    }

    $roleLabel = trim((string) ($picRow['role_label'] ?? ''));
    if ($roleLabel !== '' && !in_array($roleLabel, $picRoleOptions, true)) {
        $picRoleOptions[] = $roleLabel;
    }
}

$filteredPicRows = array_values(array_filter(
    $picRows,
    static function (array $pic) use ($picSearch, $picRoleFilter): bool {
        if ($picSearch !== '') {
            $haystack = implode(' ', [
                (string) $pic['name'],
                (string) ($pic['person_name'] ?? ''),
                (string) ($pic['role_label'] ?? ''),
            ]);

            if (stripos($haystack, $picSearch) === false) {
                return false;
            }
        }

        if ($picRoleFilter !== 'Semua Peran' && trim((string) ($pic['role_label'] ?? '')) !== $picRoleFilter) {
            return false;
        }

        return true;
    }
));

$auditStatement = $pdo->prepare(
    'SELECT module, action, entity_label, performed_by, created_at
     FROM audit_logs
     WHERE project_key = :project_key
     ORDER BY created_at DESC, id DESC
     LIMIT 8'
);
$auditStatement->execute([':project_key' => $activeProjectKey]);
$recentAuditLogs = $auditStatement->fetchAll();

$issueStatement = $pdo->prepare(
    'SELECT id, code, category, description, priority, level, target_resolve, pic, status
     FROM issue_logs
     WHERE project_key = :project_key
     ORDER BY id ASC'
);
$issueStatement->execute([':project_key' => $activeProjectKey]);
$issueLogs = $issueStatement->fetchAll();

$canManageTasks = canManageModule('tasks');
$canManageMilestones = canManageModule('milestones');
$canManageRisks = canManageModule('risks');
$canManageRoadmaps = canManageModule('roadmaps');
$canManageRaci = canManageModule('raci');
$canManagePics = canManageModule('pics');
$canManageIssues = canManageModule('issues');
$canExportReports = canManageModule('*');
$canUseAdminUtilities = canManageModule('*') || $canManagePics;
$isAdministrator = canManageModule('*');

$dueSoonTasks = 0;
foreach ($tasks as $task) {
    if ($task['status'] !== 'Selesai' && $task['end_date'] >= $today && $task['end_date'] <= date('Y-m-d', strtotime('+7 days'))) {
        $dueSoonTasks++;
    }
}

$projectHealthLabel = 'Terkendali';
$projectHealthClass = 'health-card--controlled';
$projectHealthSummary = 'Mayoritas aktivitas berjalan sesuai rencana dan belum ada eskalasi besar.';

if ($lateTasks >= 3 || $priorityRiskCount >= 4) {
    $projectHealthLabel = 'Kritis';
    $projectHealthClass = 'health-card--critical';
    $projectHealthSummary = 'Perlu eskalasi cepat ke pimpinan karena backlog terlambat atau risiko tinggi sudah dominan.';
} elseif ($lateTasks > 0 || $priorityRiskCount > 0 || $dueSoonTasks >= 4) {
    $projectHealthLabel = 'Perlu Perhatian';
    $projectHealthClass = 'health-card--warning';
    $projectHealthSummary = 'Masih terkendali, tetapi ada area yang butuh follow-up mingguan lebih ketat.';
}

$reportCards = [
    ['label' => 'Status implementasi', 'value' => $overallProgress . '%', 'note' => 'Progres rata-rata seluruh workstream.'],
    ['label' => 'Kegiatan terlambat', 'value' => (string) $lateTasks, 'note' => 'Memerlukan tindak lanjut eskalasi.'],
    ['label' => 'Risiko tinggi', 'value' => (string) $priorityRiskCount, 'note' => 'Dipantau dalam review mingguan PMO.'],
];

$nextMilestone = $milestones[0] ?? null;
$scheduleHealthPercent = $totalTasks > 0 ? (int) round((($totalTasks - $lateTasks) / $totalTasks) * 100) : 0;
$riskHealthPercent = count($risks) > 0 ? (int) round(((count($risks) - $priorityRiskCount) / count($risks)) * 100) : 100;
$overallHealthTone = $projectHealthLabel === 'Kritis' ? 'critical' : ($projectHealthLabel === 'Perlu Perhatian' ? 'warning' : 'safe');
$scheduleTone = $lateTasks >= 3 ? 'critical' : ($lateTasks > 0 ? 'warning' : 'safe');
$riskTone = $priorityRiskCount >= 3 ? 'critical' : ($priorityRiskCount > 0 ? 'warning' : 'safe');
$executiveSummaryCards = [
    [
        'label' => 'Status Project',
        'value' => $projectHealthLabel,
        'note' => $projectHealthSummary,
        'percent' => $overallProgress,
        'tone' => $overallHealthTone,
    ],
    [
        'label' => 'Target Terdekat',
        'value' => is_array($nextMilestone) ? (string) $nextMilestone['name'] : 'Belum ada target',
        'note' => is_array($nextMilestone)
            ? 'PIC ' . (string) $nextMilestone['pic'] . ' | target ' . formatReadableDate((string) $nextMilestone['target_date'])
            : 'Belum ada milestone aktif yang tercatat.',
        'percent' => $scheduleHealthPercent,
        'tone' => $scheduleTone,
    ],
    [
        'label' => 'Perlu Eskalasi',
        'value' => (string) ($lateTasks + $priorityRiskCount),
        'note' => $lateTasks . ' kegiatan terlambat dan ' . $priorityRiskCount . ' risiko tinggi aktif.',
        'percent' => $riskHealthPercent,
        'tone' => $riskTone,
    ],
];

$navigationItems = [
    [
        'id' => 'dashboard',
        'label' => 'Ringkasan Proyek',
        'description' => 'Ringkasan grafik dan tabel status implementasi proyek aktif.',
    ],
    [
        'id' => 'peta-jalan',
        'label' => 'Peta Jalan Eksekutif',
        'description' => 'Tahapan eksekutif berdasarkan roadmap proyek aktif.',
    ],
    [
        'id' => 'linimasa-teknis',
        'label' => 'Linimasa Teknis',
        'description' => 'FSD, development, SIT, UAT, PTR, dan deployment proyek aktif.',
    ],
    [
        'id' => 'bisnis-operasional',
        'label' => 'Linimasa Bisnis & Nonteknis',
        'description' => 'BRD, governance, kesiapan operasional, komunikasi, dan RACI proyek aktif.',
    ],
    [
        'id' => 'daftar-risiko',
        'label' => 'Daftar Risiko',
        'description' => 'Daftar risiko dan log isu utama proyek aktif.',
    ],
];

$pageTitle = 'Ringkasan Proyek PMO';

function formatStatusClass(string $status): string
{
    $normalized = strtolower(trim($status));
    $normalized = str_replace(['&', ' '], ['', '-'], $normalized);

    return 'badge--' . $normalized;
}

function formatReadableDate(string $date): string
{
    $timestamp = strtotime($date);

    return $timestamp ? date('d M Y', $timestamp) : $date;
}

function formatDateTime(string $date): string
{
    $timestamp = strtotime($date);

    return $timestamp ? date('d M Y, H:i', $timestamp) : $date;
}

function containsAnyKeyword(string $value, array $keywords): bool
{
    foreach ($keywords as $keyword) {
        if ($keyword !== '' && stripos($value, $keyword) !== false) {
            return true;
        }
    }

    return false;
}

$picRoleLookup = [];
$picPersonLookup = [];
foreach ($picRows as $picRow) {
    $normalizedPicName = strtolower(trim((string) $picRow['name']));
    $picRoleLookup[$normalizedPicName] = trim((string) ($picRow['role_label'] ?? ''));
    $picPersonLookup[$normalizedPicName] = trim((string) ($picRow['person_name'] ?? ''));
}

$stakeholderDefinitions = [
    [
        'label' => 'Admin',
        'aliases' => ['Admin'],
        'default_role' => 'Administrasi dan koordinasi PMO',
    ],
    [
        'label' => 'DISHUB',
        'aliases' => ['DISHUB'],
        'default_role' => 'Project sponsor dan regulator kebijakan tarif',
    ],
    [
        'label' => 'JLI',
        'aliases' => ['JLI'],
        'default_role' => 'Lead integrasi sistem dan koordinasi implementasi',
    ],
    [
        'label' => 'TJ',
        'aliases' => ['TransJakarta', 'TJ'],
        'default_role' => 'Operator utama dan penyedia data fare matrix',
    ],
    [
        'label' => 'LRTJ',
        'aliases' => ['LRTJ', 'LRT Jakarta'],
        'default_role' => 'Kesiapan layanan LRT Jakarta dan integrasi operasional',
    ],
    [
        'label' => 'MRTJ',
        'aliases' => ['MRTJ', 'MRT Jakarta'],
        'default_role' => 'Kesiapan layanan MRT Jakarta dan integrasi operasional',
    ],
    [
        'label' => 'JPAI/Jatelindo',
        'aliases' => ['JPAI', 'Jatelindo'],
        'default_role' => 'Mitra strategis atau vendor pendukung implementasi',
    ],
];

$stakeholderSummaries = [];
foreach ($stakeholderDefinitions as $definition) {
    $taskMatches = array_values(array_filter(
        $tasks,
        static fn (array $task): bool => containsAnyKeyword((string) $task['pic'], $definition['aliases'])
    ));
    usort(
        $taskMatches,
        static fn (array $left, array $right): int => strcmp((string) $left['end_date'], (string) $right['end_date'])
    );

    $milestoneMatches = array_values(array_filter(
        $milestones,
        static fn (array $milestone): bool => containsAnyKeyword((string) $milestone['pic'], $definition['aliases'])
    ));
    usort(
        $milestoneMatches,
        static fn (array $left, array $right): int => strcmp((string) $left['target_date'], (string) $right['target_date'])
    );

    $riskMatches = array_values(array_filter(
        $risks,
        static fn (array $risk): bool => containsAnyKeyword((string) $risk['owner'], $definition['aliases'])
    ));

    $nextTask = null;
    foreach ($taskMatches as $taskMatch) {
        if ((string) $taskMatch['status'] !== 'Selesai') {
            $nextTask = $taskMatch;
            break;
        }
    }

    $nextMilestone = null;
    foreach ($milestoneMatches as $milestoneMatch) {
        if ((string) $milestoneMatch['status'] !== 'Selesai') {
            $nextMilestone = $milestoneMatch;
            break;
        }
    }

    $focusText = 'Belum ada fokus aktif yang tercatat.';
    if (is_array($nextTask)) {
        $focusText = $nextTask['name'] . ' (target ' . formatReadableDate((string) $nextTask['end_date']) . ')';
    } elseif (is_array($nextMilestone)) {
        $focusText = $nextMilestone['name'] . ' (milestone ' . formatReadableDate((string) $nextMilestone['target_date']) . ')';
    }

    $stakeholderSummaries[] = [
        'label' => $definition['label'],
        'person_name' => (($picPersonLookup[strtolower($definition['label'])] ?? '') !== '') ? (string) $picPersonLookup[strtolower($definition['label'])] : 'Belum diisi',
        'role' => ($picRoleLookup[strtolower($definition['label'])] ?? '') !== '' ? (string) $picRoleLookup[strtolower($definition['label'])] : $definition['default_role'],
        'task_count' => count($taskMatches),
        'milestone_count' => count($milestoneMatches),
        'risk_count' => count($riskMatches),
        'focus' => $focusText,
        'status' => $nextTask !== null || $nextMilestone !== null ? 'Aktif' : 'Belum terpetakan',
    ];
}

$criticalItems = [];
foreach ($priorityTasks as $task) {
    if ((string) $task['status'] === 'Terlambat' || (int) $task['priority'] >= 5) {
        $criticalItems[] = [
            'type' => 'Aktivitas',
            'title' => (string) $task['name'],
            'owner' => (string) $task['pic'],
            'meta' => 'Target ' . formatReadableDate((string) $task['end_date']) . ' | Status ' . (string) $task['status'],
        ];
    }
}

foreach ($risks as $risk) {
    if ((string) $risk['level'] === 'Tinggi') {
        $criticalItems[] = [
            'type' => 'Risiko',
            'title' => (string) $risk['title'],
            'owner' => (string) $risk['owner'],
            'meta' => 'Status ' . (string) $risk['status'],
        ];
    }
}

foreach ($issueLogs as $issue) {
    if ((string) $issue['priority'] === 'Tinggi' || (string) $issue['status'] !== 'Selesai') {
        $criticalItems[] = [
            'type' => 'Isu',
            'title' => (string) $issue['description'],
            'owner' => (string) $issue['pic'],
            'meta' => 'Target resolve ' . formatReadableDate((string) $issue['target_resolve']) . ' | Status ' . (string) $issue['status'],
        ];
    }
}

$criticalItems = array_slice($criticalItems, 0, 5);

/**
 * Escape output HTML untuk komponen dashboard baru.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Membentuk satu baris data dashboard.
 */
function dashboardRow(
    string $stream,
    string $phase,
    string $activity,
    string $pic,
    string $start,
    string $end,
    string $status,
    int $progress,
    string $note = ''
): array {
    return [
        'stream' => $stream,
        'phase' => $phase,
        'activity' => $activity,
        'pic' => $pic,
        'start' => $start,
        'end' => $end,
        'status' => $status,
        'progress' => max(0, min(100, $progress)),
        'note' => $note,
    ];
}

/**
 * Metadata traffic-light dashboard.
 *
 * Excel:
 * 🔴 = Merah
 * 🟠 = Kuning
 * 🟢 = Hijau
 * ⚫ = Belum Mulai
 */
function trafficMeta(string $status): array
{
    return match ($status) {
        'red' => [
            'label' => 'Genting',
            'description' => 'Target atau keputusan terhambat dan perlu eskalasi segera.',
            'badge_class' => 'traffic-badge--red',
            'dot_class' => 'traffic-dot--red',
        ],

        'yellow' => [
            'label' => 'Rawan',
            'description' => 'Masih berjalan, tetapi ada ketergantungan atau tindak lanjut yang perlu dipantau.',
            'badge_class' => 'traffic-badge--yellow',
            'dot_class' => 'traffic-dot--yellow',
        ],

        'green' => [
            'label' => 'Aman',
            'description' => 'Berjalan sesuai rencana dan cukup dipantau melalui review rutin.',
            'badge_class' => 'traffic-badge--green',
            'dot_class' => 'traffic-dot--green',
        ],

        default => [
            'label' => 'Belum Mulai',
            'description' => 'Belum memasuki periode pelaksanaan.',
            'badge_class' => 'traffic-badge--black',
            'dot_class' => 'traffic-dot--black',
        ],
    };
}

/*
|--------------------------------------------------------------------------
| SNAPSHOT DATA EXCEL
|--------------------------------------------------------------------------
|
| Sumber:
| EIPTJ TJ _Perubahan Tarif _Timeline Jaklingko 17 JUL 2026 v3.xlsx
|
| Sheet:
| (2) TEKNIK TIMELINE
| (3) BO (Non Teknis) TIMELINE
|
| Mapping:
| 🔴 Excel = red
| 🟠 Excel = yellow
| 🟢 Excel = green
| ⚫ Excel = black
|
| Beberapa tanggal dari Excel terlihat tidak konsisten.
| Untuk tahap ini nilainya dipertahankan sesuai sumber.
|
*/

$dashboardRows = [

    /*
    |--------------------------------------------------------------------------
    | TEKNIK TIMELINE
    |--------------------------------------------------------------------------
    */

    dashboardRow(
        'Bisnis & Nonteknis',
        'BRD & Kebutuhan Bisnis',
        'Final Requirement/Regulasi/BRD Approval : BRD versi 1.4',
        'DISHUB',
        '06 Jul 2026',
        '21 Jul 2026',
        'red',
        50,
        'Belum ttd Stakeholder'
    ),

    dashboardRow(
        'Bisnis & Nonteknis',
        'BRD & Kebutuhan Bisnis',
        'Data Fare Matrix Transjakarta (Stop ID, GTFS, Rute, dll)',
        'TJ',
        '15 Jul 2026',
        '21 Jul 2026',
        'red',
        80,
        'Distance Matrix Non BRT (Shelter Code (Plat No/No Body), Terminal ID, Data Halte, Bus Stop Terupdate.)'
    ),

    dashboardRow(
        'Bisnis & Nonteknis',
        'BRD & Kebutuhan Bisnis',
        'Final Use-Case skanario (30 positif UC & 14 negatif UC)',
        'DISHUB',
        '15 Jul 2026',
        '21 Jul 2026',
        'red',
        90,
        'FSD in progress'
    ),

    dashboardRow(
        'Teknis',
        'Design & Development (FSD/DEV)',
        'Reader Device (L1): Library Acceptance KUE',
        'JLI',
        '10 Jul 2026',
        '26 Jul 2026',
        'yellow',
        71,
        'MRT & LRT inprogress'
    ),

    dashboardRow(
        'Teknis',
        'Design & Development (FSD/DEV)',
        'Reader Device (L1): Library Acceptance QR STT',
        'JLI',
        '10 Jul 2026',
        '26 Jul 2026',
        'green',
        100
    ),

    dashboardRow(
        'Teknis',
        'Design & Development (FSD/DEV)',
        'Reader Device (L1): Library Acceptance ABT - Tahap 2',
        'JLi',
        '01 Sep 2026',
        '22 Nov 2026',
        'green',
        100
    ),

    dashboardRow(
        'Teknis',
        'Design & Development (FSD/DEV)',
        'MaaS QR STT',
        'JLI',
        '10 Jul 2026',
        '31 Jul 2026',
        'yellow',
        20
    ),

    dashboardRow(
        'Teknis',
        'Design & Development (FSD/DEV)',
        'CCHS',
        'JLI',
        '25 Jul 2026',
        '10 Aug 2026',
        'yellow',
        10
    ),

    dashboardRow(
        'Teknis',
        'Design & Development (FSD/DEV)',
        'PAYG - Tahap 2',
        'JLI',
        '01 Oct 2026',
        '15 Dec 2026',
        'black',
        0
    ),

    dashboardRow(
        'Teknis',
        'Design & Development (FSD/DEV)',
        'Tarif Langganan (Period Pass) - Tahap 2',
        'JLI',
        '01 Oct 2026',
        '15 Dec 2026',
        'black',
        0
    ),

    dashboardRow(
        'Teknis',
        'Integrasi Sistem',
        'Sinkronisasi dengan PTO (Joint Development)',
        'JLI & PTO',
        '24 Jul 2026',
        '30 Jul 2026',
        'yellow',
        0,
        'Belum ada penegasan'
    ),

    dashboardRow(
        'Teknis',
        'Pengujian / Testing',
        'SIT (Pengujian Integrasi)',
        'JLI & PTO',
        '03 Aug 2026',
        '05 Aug 2026',
        'black',
        0
    ),

    dashboardRow(
        'Teknis',
        'Pengujian / Testing',
        'UAT (Pengujian Skema dan Bisnis Proses)',
        'JLI, PTO. DISHUB',
        '06 Aug 2026',
        '12 Aug 2026',
        'black',
        0
    ),

    dashboardRow(
        'Teknis',
        'Pengujian / Testing',
        'PTR (Piloting/Uji Coba Terbatas)',
        'JLI, PTO. DISHUB',
        '13 Aug 2026',
        '25 Dec 2026',
        'black',
        0
    ),

    dashboardRow(
        'Teknis',
        'Implementrasi / Deployment',
        'Deployment Tahap 0 (TransBandara, Halte BRT)',
        'TJ',
        '16 Aug 2026',
        '16 Aug 2026',
        'black',
        0
    ),

    dashboardRow(
        'Teknis',
        'Implementrasi / Deployment',
        'Deployment Tahap 0 (BRT, MRT dan LRTJ)',
        'TJ & JLI',
        '16 Aug 2026',
        '16 Aug 2026',
        'black',
        0
    ),

    dashboardRow(
        'Teknis',
        'Implementrasi / Deployment',
        'Deployment Tahap I (1 BRT, 1 MRT, 1 LRT, 1 NBRT, 1 MT)',
        'PTO & JLI',
        '26 Aug 2026',
        '31 Aug 2026',
        'black',
        0
    ),

    dashboardRow(
        'Teknis',
        'Implementrasi / Deployment',
        'Deployment Tahap II (BRT, NBRT, MT, MRT, LRTJ)',
        'PTO & JLI',
        '25 Dec 2026',
        '31 Dec 2026',
        'black',
        0
    ),

    dashboardRow(
        'Teknis',
        'Operational',
        'Monitor Operasional',
        'PTO & JLI',
        '01 Jan 2027',
        '31 Jan 2017',
        'black',
        0
    ),

    /*
    |--------------------------------------------------------------------------
    | BO / NON TEKNIS TIMELINE
    |--------------------------------------------------------------------------
    */

    dashboardRow(
        'Bisnis & Nonteknis',
        'Governance & Project Alignment',
        'Kick-off Project & Streeting Committee',
        'DISHUB',
        '06 Jul 2026',
        '24 Jul 2026',
        'red',
        50,
        'Hanya MOM'
    ),

    dashboardRow(
        'Bisnis & Nonteknis',
        'Governance & Project Alignment',
        'PMO & Working Group Lintas Instansi',
        'DISHUB & PTO',
        '20 Jul 2026',
        '31 Jul 2026',
        'yellow',
        0,
        'Belum terbentuk'
    ),

    dashboardRow(
        'Bisnis & Nonteknis',
        'Fare Policy & Business Rules',
        'Finalisasi Kebijakan tarif & Fare Matrix',
        'DISHUB',
        '20 Jul 2026',
        '24 Jul 2026',
        'red',
        0,
        'Hanya MOM'
    ),

    dashboardRow(
        'Bisnis & Nonteknis',
        'Fare Policy & Business Rules',
        'Persetujuan Standar Sektor KUE & Tarif Integrasi',
        'DISHUB, PTO',
        '20 Jul 2026',
        '24 Jul 2026',
        'red',
        60,
        'Hanya MOM'
    ),

    dashboardRow(
        'Bisnis & Nonteknis',
        'Fare Policy & Business Rules',
        'Persetujuan TOB & TOM Terintegrasi',
        'DISHUB, TJ,',
        '20 Jul 2026',
        '24 Jul 2026',
        'red',
        0,
        'Belum Jelas'
    ),

    dashboardRow(
        'Bisnis & Nonteknis',
        'Fare Policy & Business Rules',
        "Persetujuan Business Rules TransBandara (Aug'26)",
        'DISHUB, TJ',
        '20 Jul 2026',
        '27 Jul 2026',
        'yellow',
        60,
        'Hanya MOM'
    ),

    dashboardRow(
        'Bisnis & Nonteknis',
        'Fare Policy & Business Rules',
        "Persetujuan Business Rules Tarif Angkutan Umum (Jan'27)",
        'DISHUB, PTO',
        '20 Jul 2026',
        '30 Aug 2026',
        'yellow',
        60,
        'Hanya MOM'
    ),

    dashboardRow(
        'Bisnis & Nonteknis',
        'Fare Policy & Business Rules',
        'Persetujuan tanggal Implementasi',
        'DISHUB',
        '25 Jul 2026',
        '27 Jul 2026',
        'yellow',
        0,
        'Tgl?'
    ),

    dashboardRow(
        'Bisnis & Nonteknis',
        'Operator Readiness',
        'Rapat Penyelarasan PTO',
        'DISHUB, PTO',
        '20 Jul 2026',
        '24 Jul 2026',
        'red',
        0,
        'TJ?'
    ),

    dashboardRow(
        'Bisnis & Nonteknis',
        'Operator Readiness',
        'Kesepakatan Kesiapan Operasional PTO',
        'DISHUB, PTO, JLI',
        '20 Jul 2026',
        '27 Aug 2026',
        'yellow',
        0
    ),

    dashboardRow(
        'Bisnis & Nonteknis',
        'Transaction Readiness',
        'Kesepakatan Migrasi Transaksi TB, NBRT & MT',
        'DISHUB, TJ, JLI',
        '20 Jul 2026',
        '27 Jul 2026',
        'yellow',
        0,
        'TJ?'
    ),

    dashboardRow(
        'Bisnis & Nonteknis',
        'Transaction Readiness',
        'Kesepakatan Migrasi Transaksi 9 Stasiun MRT Jakarta',
        'DISHUB, MRT, JLI',
        '20 Jul 2026',
        '27 Aug 2026',
        'yellow',
        60,
        'Menunggu konf MRTZ'
    ),

    dashboardRow(
        'Bisnis & Nonteknis',
        'Settelement Readiness',
        'Settlement Non BRT (TOB/TOM)',
        'PTO, JLI',
        '24 Jul 2026',
        '25 Aug 2026',
        'yellow',
        0,
        'MID/TID'
    ),

    dashboardRow(
        'Bisnis & Nonteknis',
        'Settelement Readiness',
        'Reconcile Non BRT (TOB/TOM)',
        'PTO, JLI',
        '24 Jul 2026',
        '25 Aug 2026',
        'yellow',
        0,
        'MID/TID'
    ),

    dashboardRow(
        'Bisnis & Nonteknis',
        'Communication & Change Mgmt',
        'Penyusunan Materi Sosialisasi Internal dan Ekternal',
        'DISHUB, PTO, JLI',
        '24 Jul 2026',
        '31 Jan 2027',
        'yellow',
        20
    ),

    dashboardRow(
        'Bisnis & Nonteknis',
        'Communication & Change Mgmt',
        'Penjadwalan & Inventarisasi spot/media sosialisasi',
        'DISHUB, PTO, JLI',
        '24 Jul 2026',
        '31 Jan 2027',
        'yellow',
        0
    ),

    dashboardRow(
        'Bisnis & Nonteknis',
        'Operational Readiness Review',
        'Update Operatonal Prosedure',
        'PTO, JLI',
        '27 Jul 2026',
        '31/11/2026',
        'yellow',
        50
    ),

    dashboardRow(
        'Bisnis & Nonteknis',
        'Operational Readiness Review',
        'Update Incident Management SOP',
        'PTO, JLI',
        '27 Jul 2026',
        '31/11/2026',
        'yellow',
        50
    ),
];

/* Grafik TAJ selalu dihitung dari aktivitas TAJ yang tersimpan di database. */
if ($activeProjectKey === 'taj') {
    $dashboardRows = array_map(
        static function (array $task): array {
            $trafficStatus = match ((string) $task['status']) {
                'Terlambat' => 'red',
                'Sedang Berjalan', 'Menunggu' => 'yellow',
                'Selesai' => 'green',
                default => 'black',
            };

            return dashboardRow(
                (string) $task['workstream'],
                'Timeline Terminal Angkutan Jalan',
                (string) $task['name'],
                (string) $task['pic'],
                date('d M Y', strtotime((string) $task['start_date'])),
                date('d M Y', strtotime((string) $task['end_date'])),
                $trafficStatus,
                (int) $task['progress'],
                (string) ($task['notes'] ?? '')
            );
        },
        $tasks
    );
}

if ($activeProjectKey === 'eiptj') {
    $dashboardRows = array_map(
        static function (array $task): array {
            $trafficStatus = match ((string) $task['status']) {
                'Terlambat' => 'red',
                'Sedang Berjalan', 'Menunggu' => 'yellow',
                'Selesai' => 'green',
                default => 'black',
            };

            return dashboardRow(
                (string) $task['workstream'],
                trim((string) strtok((string) ($task['notes'] ?? ''), '.')) ?: 'Timeline DISHUB',
                (string) $task['name'],
                (string) $task['pic'],
                date('d M Y', strtotime((string) $task['start_date'])),
                date('d M Y', strtotime((string) $task['end_date'])),
                $trafficStatus,
                (int) $task['progress'],
                (string) ($task['notes'] ?? '')
            );
        },
        $tasks
    );
}

/* PTO adalah kelompok operator, bukan PIC yang dapat ditindaklanjuti. */
$dashboardPicMappings = [
    'PTO' => 'TJ, LRTJ, MRTJ',
    'JLI & PTO' => 'JLI, TJ, LRTJ, MRTJ',
    'JLI, PTO. DISHUB' => 'JLI, TJ, LRTJ, MRTJ, DISHUB',
    'PTO & JLI' => 'TJ, LRTJ, MRTJ, JLI',
    'DISHUB & PTO' => 'DISHUB, TJ, LRTJ, MRTJ',
    'DISHUB, PTO, JLI' => 'DISHUB, TJ, LRTJ, MRTJ, JLI',
    'DISHUB, PTO' => 'DISHUB, TJ, LRTJ, MRTJ',
    'PTO, JLI' => 'TJ, LRTJ, MRTJ, JLI',
    'DISHUB, MRT, JLI' => 'DISHUB, MRTJ, JLI',
    'DISHUB, TJ,' => 'DISHUB, TJ',
];

foreach ($dashboardRows as &$dashboardRow) {
    $dashboardRow['pic'] = $dashboardPicMappings[(string) $dashboardRow['pic']]
        ?? (string) $dashboardRow['pic'];
    $dashboardRow['activity'] = str_replace(
        'PTO',
        'operator transportasi publik (TJ, LRTJ, MRTJ)',
        (string) $dashboardRow['activity']
    );
}
unset($dashboardRow);

/*
|--------------------------------------------------------------------------
| PERHITUNGAN DASHBOARD
|--------------------------------------------------------------------------
*/

$statusCounts = [
    'red' => 0,
    'yellow' => 0,
    'green' => 0,
    'black' => 0,
];

$streamStats = [
    'Teknis' => [
        'red' => 0,
        'yellow' => 0,
        'green' => 0,
        'black' => 0,
        'total' => 0,
    ],

        'Bisnis & Nonteknis' => [
        'red' => 0,
        'yellow' => 0,
        'green' => 0,
        'black' => 0,
        'total' => 0,
    ],
];

$phaseStats = [];

$dashboardProgressTotal = 0;
$totalActivities = count($dashboardRows);

foreach ($dashboardRows as $row) {
    $status = (string) $row['status'];
    $stream = (string) $row['stream'];
    $phase = (string) $row['phase'];
    $progress = (int) $row['progress'];

    /*
     * Hitung total berdasarkan traffic light.
     */
    $statusCounts[$status]++;

    /*
     * Hitung status per workstream.
     */
    $streamStats[$stream][$status]++;
    $streamStats[$stream]['total']++;

    /*
     * Hitung progress per fase.
     */
    $phaseKey = $stream . '|' . $phase;

    if (!isset($phaseStats[$phaseKey])) {
        $phaseStats[$phaseKey] = [
            'stream' => $stream,
            'phase' => $phase,
            'progress_sum' => 0,
            'count' => 0,
            'red' => 0,
            'yellow' => 0,
            'green' => 0,
            'black' => 0,
        ];
    }

    $phaseStats[$phaseKey]['progress_sum'] += $progress;
    $phaseStats[$phaseKey]['count']++;
    $phaseStats[$phaseKey][$status]++;

    $dashboardProgressTotal += $progress;
}

/*
 * Rata-rata progress per fase.
 */
foreach ($phaseStats as &$phaseStat) {
    $phaseStat['progress'] = $phaseStat['count'] > 0
        ? (int) round(
            $phaseStat['progress_sum'] / $phaseStat['count']
        )
        : 0;
}

unset($phaseStat);

/*
 * Overall progress seluruh aktivitas.
 *
 * Aktivitas hitam / belum mulai dihitung 0%.
 */
$dashboardOverallProgress = $totalActivities > 0
    ? (int) round(
        $dashboardProgressTotal / $totalActivities
    )
    : 0;

/*
 * Tabel utama hanya:
 *
 * Merah
 * Kuning
 * Hijau
 *
 * Black / belum mulai tidak memenuhi tabel utama.
 */
$activeTrafficCount =
    $statusCounts['red']
    + $statusCounts['yellow']
    + $statusCounts['green'];

$trafficStatusOrder = ['red', 'yellow', 'green', 'black'];
$trafficStatusColors = [
    'red' => '#d94b4b',
    'yellow' => '#e5a51d',
    'green' => '#2d9a66',
    'black' => '#6f7f8d',
];
$trafficGradientStops = [];
$trafficGradientPosition = 0;

foreach ($trafficStatusOrder as $trafficStatus) {
    $trafficPercentage = $totalActivities > 0
        ? ($statusCounts[$trafficStatus] / $totalActivities) * 100
        : 0;
    $trafficGradientEnd = $trafficGradientPosition + $trafficPercentage;
    $trafficGradientStops[] = $trafficStatusColors[$trafficStatus]
        . ' ' . number_format($trafficGradientPosition, 3, '.', '') . '% '
        . number_format($trafficGradientEnd, 3, '.', '') . '%';
    $trafficGradientPosition = $trafficGradientEnd;
}

$trafficGradient = implode(', ', $trafficGradientStops);
$progressGradient = sprintf(
    '#0f8f8c 0%% %1$d%%, #e3eaee %1$d%% 100%%',
    $dashboardOverallProgress
);

$streamOrder = ['Teknis', 'Bisnis & Nonteknis'];
$streamTrafficGradients = [];

foreach ($streamOrder as $stream) {
    $streamGradientStops = [];
    $streamGradientPosition = 0;

    foreach ($trafficStatusOrder as $trafficStatus) {
        $streamPercentage = $streamStats[$stream]['total'] > 0
            ? ($streamStats[$stream][$trafficStatus] / $streamStats[$stream]['total']) * 100
            : 0;
        $streamGradientEnd = $streamGradientPosition + $streamPercentage;
        $streamGradientStops[] = $trafficStatusColors[$trafficStatus]
            . ' ' . number_format($streamGradientPosition, 3, '.', '') . '% '
            . number_format($streamGradientEnd, 3, '.', '') . '%';
        $streamGradientPosition = $streamGradientEnd;
    }

    $streamTrafficGradients[$stream] = implode(', ', $streamGradientStops);
}

// Kondisi proyek mengikuti tingkat status tertinggi yang masih aktif.
$dashboardHealthStatus = 'green';
if ($statusCounts['red'] > 0) {
    $dashboardHealthStatus = 'red';
} elseif ($statusCounts['yellow'] > 0) {
    $dashboardHealthStatus = 'yellow';
} elseif ($statusCounts['green'] === 0 && $statusCounts['black'] > 0) {
    $dashboardHealthStatus = 'black';
}
$dashboardHealthMeta = trafficMeta($dashboardHealthStatus);

/*
|--------------------------------------------------------------------------
| FILTER STATUS
|--------------------------------------------------------------------------
*/

$allowedFilters = [
    'all',
    'red',
    'yellow',
    'green',
];

$statusFilter = strtolower(
    trim(
        (string) ($_GET['status'] ?? 'all')
    )
);

if (!in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = 'all';
}

$tableRows = array_values(
    array_filter(
        $dashboardRows,
        static function (array $row) use ($statusFilter): bool {
            /*
             * Jangan tampilkan black / belum mulai.
             */
            if ($row['status'] === 'black') {
                return false;
            }

            /*
             * Tampilkan semua traffic light aktif.
             */
            if ($statusFilter === 'all') {
                return true;
            }

            /*
             * Filter per warna.
             */
            return $row['status'] === $statusFilter;
        }
    )
);

$statusFilterLabels = [
    'all' => 'Semua',
    'red' => 'Merah',
    'yellow' => 'Kuning',
    'green' => 'Hijau',
];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>
<main class="main-content">
    <button
        type="button"
        id="sidebarToggle"
        class="mobile-menu-button"
        aria-label="Buka menu navigasi"
        aria-controls="sidebar"
        aria-expanded="false"
    >
        <span></span>
        <span></span>
        <span></span>
    </button>
    <div class="dashboard">
        <header class="topbar" data-animate>
            <div class="topbar__heading">
                <p class="topbar__eyebrow">PMO <?= htmlspecialchars($activeProject['short_label'], ENT_QUOTES, 'UTF-8'); ?></p>
                <h2 id="pageTitleHeading">Ringkasan Proyek</h2>
                <p id="pageTitleDescription">Ringkasan grafik dan tabel status <?= htmlspecialchars(strtolower($activeProject['description']), ENT_QUOTES, 'UTF-8'); ?> Detail roadmap, timeline, risiko, issue, RACI, dan data pendukung tersedia di menu lain.</p>
            </div>
            <div class="topbar__controls">
                <div class="topbar__meta-group">
                    <div class="topbar__meta-card">
                        <span class="topbar__meta-label">Data per</span>
                        <strong><?= htmlspecialchars($projectSnapshotDate, ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="topbar__meta-card">
                        <span class="topbar__meta-label">Pengguna aktif</span>
                        <strong><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                </div>
                <?php if ($canManageTasks): ?>
                    <a href="/tasks/create" class="action-button action-button--primary">Tambah Aktivitas</a>
                <?php endif; ?>
                <?php if ($canExportReports): ?>
                    <a href="/reports/export" class="action-button">Ekspor Ringkasan</a>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($flashMessage !== null): ?>
            <div class="alert alert--<?= htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8'); ?>" data-animate>
                <?= htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="tab-panels">
            <section
                class="tab-panel is-active"
                id="dashboard"
                data-tab-panel
                data-animate
            >

                <section class="high-level-dashboard" aria-labelledby="highLevelDashboardTitle">
                    <div class="high-level-dashboard__intro">
                        <div>
                            <p class="dashboard-chart-card__eyebrow">Ringkasan Eksekutif</p>
                            <h3 id="highLevelDashboardTitle" class="panel__title"><?= htmlspecialchars($projectDashboardTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="section-subtitle">Arahkan kursor untuk ringkasan, lalu klik grafik atau kategori untuk melihat detail aktivitas dan PIC.</p>
                        </div>
                    </div>

                    <div class="high-level-dashboard__content">
                        <div class="dashboard-donut-pair" aria-label="Grafik ringkasan project">
                            <div class="traffic-pie__group traffic-pie__group--progress">
                            <button
                                type="button"
                                class="traffic-pie traffic-pie--interactive traffic-pie--progress"
                                style="--traffic-pie: conic-gradient(<?= e($progressGradient); ?>);"
                                data-dashboard-detail-trigger="all"
                                data-dashboard-detail-label="Seluruh aktivitas"
                                data-tooltip="Klik untuk melihat seluruh aktivitas, progres, dan PIC."
                                aria-controls="dashboardStatusDetail"
                                aria-label="Komposisi status seluruh aktivitas. Klik untuk melihat detail."
                            >
                                <div class="traffic-pie__center">
                                    <strong><?= e((string) $dashboardOverallProgress); ?>%</strong>
                                    <span>progres keseluruhan</span>
                                </div>
                            </button>
                            </div>

                            <div class="traffic-pie__group">
                            <button
                                type="button"
                                class="traffic-pie traffic-pie--interactive traffic-pie--stream"
                                style="--traffic-pie: conic-gradient(<?= e($streamTrafficGradients['Teknis']); ?>);"
                                data-dashboard-detail-trigger="stream:Teknis"
                                data-dashboard-detail-label="Aktivitas Teknis"
                                data-tooltip="Teknis: G <?= e((string) $streamStats['Teknis']['red']); ?> | R <?= e((string) $streamStats['Teknis']['yellow']); ?> | A <?= e((string) $streamStats['Teknis']['green']); ?> | BM <?= e((string) $streamStats['Teknis']['black']); ?>. Klik untuk detail."
                                aria-controls="dashboardStatusDetail"
                                aria-label="Status aktivitas Teknis. Klik untuk melihat detail aktivitas Teknis."
                            >
                                <div class="traffic-pie__center">
                                    <strong><?= e((string) $streamStats['Teknis']['total']); ?> aktivitas</strong>
                                    <span>Teknis</span>
                                </div>
                            </button>
                            <div class="traffic-pie__annotations" aria-label="Rincian status aktivitas Teknis">
                                <?php foreach ($trafficStatusOrder as $status): ?>
                                    <?php $meta = trafficMeta($status); ?>
                                    <span class="traffic-pie__annotation traffic-pie__annotation--<?= e($status); ?>">
                                        <?= e($meta['label']); ?> <?= e((string) $streamStats['Teknis'][$status]); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            </div>

                            <div class="traffic-pie__group">
                            <button
                                type="button"
                                class="traffic-pie traffic-pie--interactive traffic-pie--stream"
                                style="--traffic-pie: conic-gradient(<?= e($streamTrafficGradients['Bisnis & Nonteknis']); ?>);"
                                data-dashboard-detail-trigger="stream:Bisnis &amp; Nonteknis"
                                data-dashboard-detail-label="Aktivitas Bisnis & Nonteknis"
                                data-tooltip="Bisnis & Nonteknis: G <?= e((string) $streamStats['Bisnis & Nonteknis']['red']); ?> | R <?= e((string) $streamStats['Bisnis & Nonteknis']['yellow']); ?> | A <?= e((string) $streamStats['Bisnis & Nonteknis']['green']); ?> | BM <?= e((string) $streamStats['Bisnis & Nonteknis']['black']); ?>. Klik untuk detail."
                                aria-controls="dashboardStatusDetail"
                                aria-label="Status aktivitas Bisnis dan Nonteknis. Klik untuk melihat detail aktivitas Bisnis dan Nonteknis."
                            >
                                <div class="traffic-pie__center">
                                    <strong><?= e((string) $streamStats['Bisnis & Nonteknis']['total']); ?> aktivitas</strong>
                                    <span>Bisnis</span>
                                </div>
                            </button>
                            <div class="traffic-pie__annotations" aria-label="Rincian status aktivitas Bisnis dan Nonteknis">
                                <?php foreach ($trafficStatusOrder as $status): ?>
                                    <?php $meta = trafficMeta($status); ?>
                                    <span class="traffic-pie__annotation traffic-pie__annotation--<?= e($status); ?>">
                                        <?= e($meta['label']); ?> <?= e((string) $streamStats['Bisnis & Nonteknis'][$status]); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            </div>
                        </div>

                        <div class="traffic-pie__legend" aria-label="Pilih detail status">
                            <?php foreach ($trafficStatusOrder as $status): ?>
                                <?php
                                $meta = trafficMeta($status);
                                ?>
                                <button
                                    type="button"
                                    class="traffic-pie__legend-button traffic-pie__legend-button--<?= e($status); ?>"
                                    data-dashboard-detail-trigger="<?= e($status); ?>"
                                    data-dashboard-detail-label="<?= e($meta['label']); ?>"
                                    data-tooltip="<?= e((string) $statusCounts[$status]); ?> aktivitas | Klik untuk melihat PIC dan progres"
                                    aria-controls="dashboardStatusDetail"
                                >
                                    <span class="traffic-dot <?= e($meta['dot_class']); ?>" aria-hidden="true"></span>
                                    <span><?= e($meta['label']); ?></span>
                                    <b><?= e((string) $statusCounts[$status]); ?> aktivitas</b>
                                </button>
                            <?php endforeach; ?>
                        </div>

                    </div>
                </section>

                <section
                    id="dashboardStatusDetail"
                    class="dashboard-drilldown"
                    hidden
                    aria-live="polite"
                    aria-labelledby="dashboardDetailTitle"
                >
                    <div class="dashboard-drilldown__header">
                        <div>
                            <p class="dashboard-chart-card__eyebrow">Detail Ringkasan</p>
                            <h3 id="dashboardDetailTitle" class="panel__title" data-dashboard-detail-title>Detail aktivitas</h3>
                            <p class="section-subtitle" data-dashboard-detail-summary></p>
                        </div>
                        <button type="button" class="dashboard-drilldown__close" data-dashboard-detail-close>Tutup</button>
                    </div>
                    <div class="dashboard-drilldown__list">
                        <?php foreach ($dashboardRows as $row): ?>
                            <article
                                class="dashboard-detail-card"
                                data-dashboard-detail-row
                                data-status="<?= e((string) $row['status']); ?>"
                                data-stream="<?= e((string) $row['stream']); ?>"
                            >
                                <?php $meta = trafficMeta((string) $row['status']); ?>
                                <div class="dashboard-detail-card__top">
                                    <span class="traffic-badge <?= e($meta['badge_class']); ?>">
                                        <span class="traffic-dot <?= e($meta['dot_class']); ?>" aria-hidden="true"></span>
                                        <?= e($meta['label']); ?>
                                    </span>
                                    <strong><?= e((string) $row['progress']); ?>%</strong>
                                </div>
                                <h4><?= e((string) $row['activity']); ?></h4>
                                <p><?= e((string) $row['stream']); ?> | <?= e((string) $row['phase']); ?></p>
                                <small>PIC: <?= e((string) $row['pic']); ?></small>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- ========================================
                     MINI SUMMARY
                ========================================= -->

                <div class="dashboard-summary-strip dashboard-summary-strip--legacy">

                    <div class="dashboard-summary-strip__main">

                        <span class="dashboard-summary-strip__label">
                            Status implementasi <?= htmlspecialchars($activeProject['short_label'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>

                        <strong class="dashboard-summary-strip__value">
                            <?= e((string) $dashboardOverallProgress); ?>%
                        </strong>

                    </div>

                    <div
                        class="dashboard-summary-strip__statuses"
                        aria-label="Ringkasan status"
                    >

                        <?php foreach (['red', 'yellow', 'green', 'black'] as $status): ?>

                            <?php
                            $meta = trafficMeta($status);
                            ?>

                            <div class="dashboard-status-count">

                                <span
                                    class="traffic-dot <?= e($meta['dot_class']); ?>"
                                    aria-hidden="true"
                                ></span>

                                <span>
                                    <?= e($meta['label']); ?>
                                </span>

                                <strong>
                                    <?= e((string) $statusCounts[$status]); ?>
                                </strong>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

                <!-- ========================================
                     CHART GRID
                ========================================= -->

                <?php if (false): /* Grafik batang lama digantikan donut ringkasan. */ ?>
                <div class="dashboard-chart-grid dashboard-chart-grid--legacy">

                    <!-- ====================================
                         CHART 01
                    ===================================== -->

                    <article
                        class="panel dashboard-chart-card"
                        data-animate
                    >

                        <div class="panel__header">

                            <div>

                                <p class="dashboard-chart-card__eyebrow">
                                    Grafik 01
                                </p>

                                <h3 class="panel__title">
                                    Kondisi Utama per Area Kerja
                                </h3>

                                <p class="section-subtitle">
                                    Perbandingan kondisi aktivitas Teknis dan
                                    Bisnis & Nonteknis berdasarkan status
                                    sumber. Fokuskan rapat pada Genting dan Rawan.
                                </p>

                            </div>

                        </div>

                        <!-- Legend -->

                        <div
                            class="status-chart-legend"
                            aria-label="Legenda grafik status"
                        >

                            <?php foreach (['red', 'yellow', 'green', 'black'] as $status): ?>

                                <?php
                                $meta = trafficMeta($status);
                                ?>

                                <span class="status-chart-legend__item">

                                    <span
                                        class="traffic-dot <?= e($meta['dot_class']); ?>"
                                        aria-hidden="true"
                                    ></span>

                                    <?= e($meta['label']); ?>

                                </span>

                            <?php endforeach; ?>

                        </div>

                        <!-- Stacked Chart -->

                        <div class="stacked-status-chart">

                            <?php foreach ($streamStats as $stream => $stats): ?>

                                <div class="stacked-status-chart__row">

                                    <div class="stacked-status-chart__label">

                                        <strong>
                                            <?= e($stream); ?>
                                        </strong>

                                        <span>
                                            <?= e((string) $stats['total']); ?>
                                            aktivitas
                                        </span>

                                    </div>

                                    <div
                                        class="stacked-status-chart__track"
                                        aria-label="Distribusi status <?= e($stream); ?>"
                                    >

                                        <?php foreach (['red', 'yellow', 'green', 'black'] as $status): ?>

                                            <?php

                                            $count = (int) $stats[$status];

                                            $width = $stats['total'] > 0
                                                ? ($count / $stats['total']) * 100
                                                : 0;

                                            ?>

                                            <?php if ($count > 0): ?>

                                                <div
                                                    class="
                                                        stacked-status-chart__segment
                                                        stacked-status-chart__segment--<?= e($status); ?>
                                                    "
                                                    style="
                                                        width:
                                                        <?= e(number_format($width, 4, '.', '')); ?>%
                                                    "
                                                    title="
                                                        <?= e(trafficMeta($status)['label']); ?>:
                                                        <?= e((string) $count); ?>
                                                    "
                                                >

                                                    <span>
                                                        <?= e((string) $count); ?>
                                                    </span>

                                                </div>

                                            <?php endif; ?>

                                        <?php endforeach; ?>

                                    </div>

                                    <div class="stacked-status-chart__numbers">

                                        <span>
                                            <b><?= e((string) $stats['red']); ?></b>
                                            genting
                                        </span>

                                        <span>
                                            <b><?= e((string) $stats['yellow']); ?></b>
                                            rawan
                                        </span>

                                        <span>
                                            <b><?= e((string) $stats['green']); ?></b>
                                            aman
                                        </span>

                                        <span>
                                            <b><?= e((string) $stats['black']); ?></b>
                                            belum mulai
                                        </span>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </article>

                    <!-- ====================================
                         CHART 02
                    ===================================== -->

                    <article
                        class="panel dashboard-chart-card"
                        data-animate
                    >

                        <div class="panel__header">

                            <div>

                                <h3 class="panel__title">
                                    Arti Status untuk Rapat Pimpinan
                                </h3>

                                <p class="section-subtitle">
                                    Warna dipakai sebagai tingkat perhatian,
                                    bukan sekadar penanda visual.
                                </p>

                            </div>

                        </div>

                        <div class="traffic-guidance">
                            <?php foreach (['red', 'yellow', 'green', 'black'] as $status): ?>
                                <?php $meta = trafficMeta($status); ?>
                                <div class="traffic-guidance__item traffic-guidance__item--<?= e($status); ?>">
                                    <span class="traffic-dot <?= e($meta['dot_class']); ?>" aria-hidden="true"></span>
                                    <div>
                                        <strong><?= e($meta['label']); ?></strong>
                                        <p><?= e($meta['description']); ?></p>
                                    </div>
                                    <b><?= e((string) $statusCounts[$status]); ?></b>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    </article>

                </div>
                <?php endif; ?>

                <!-- ========================================
                     STATUS TABLE
                ========================================= -->

                <article
                    class="panel dashboard-status-table dashboard-status-table--legacy"
                    data-animate
                >

                    <div
                        class="
                            panel__header
                            dashboard-status-table__header
                        "
                    >

                        <div>

                            <p class="dashboard-chart-card__eyebrow">
                                Tabel Status Excel
                            </p>

                            <h3 class="panel__title">
                                Aktivitas Merah, Kuning, dan Hijau
                            </h3>

                            <p class="section-subtitle">
                                Dashboard hanya menampilkan status lampu
                                aktif supaya tetap ringkas.
                                Aktivitas hitam/belum mulai tetap dihitung
                                di grafik, tetapi tidak memenuhi tabel utama.
                            </p>

                        </div>

                        <!-- Filter -->

                        <div
                            class="status-filter-group"
                            aria-label="Filter status tabel"
                        >

                            <?php foreach ($statusFilterLabels as $value => $label): ?>

                                <a
                                    href="/?status=<?= e($value); ?>#dashboard"
                                    class="
                                        status-filter
                                        <?= $statusFilter === $value
                                            ? ' is-active'
                                            : ''; ?>
                                        status-filter--<?= e($value); ?>
                                    "
                                >
                                    <?= e($label); ?>
                                </a>

                            <?php endforeach; ?>

                        </div>

                    </div>

                    <div
                        class="
                            results-summary
                            dashboard-status-table__summary
                        "
                    >

                        Menampilkan

                        <strong>
                            <?= e((string) count($tableRows)); ?>
                        </strong>

                        dari

                        <strong>
                            <?= e((string) $activeTrafficCount); ?>
                        </strong>

                        aktivitas berstatus genting, rawan, atau aman.

                    </div>

                    <!-- ====================================
                         TABLE
                    ===================================== -->

                    <div
                        class="
                            table-wrapper
                            dashboard-status-table__wrapper
                        "
                    >

                        <table class="dashboard-traffic-table">

                            <thead>

                            <tr>

                                <th>
                                    Area
                                </th>

                                <th>
                                    Fase
                                </th>

                                <th>
                                    Aktivitas
                                </th>

                                <th>
                                    PIC
                                </th>

                                <th>
                                    Periode
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Progress
                                </th>

                                <th>
                                    Keterangan
                                </th>

                            </tr>

                            </thead>

                            <tbody>

                            <?php foreach ($tableRows as $row): ?>

                                <?php
                                $meta = trafficMeta(
                                    (string) $row['status']
                                );
                                ?>

                                <tr
                                    class="
                                        traffic-row
                                        traffic-row--<?= e(
                                            (string) $row['status']
                                        ); ?>
                                    "
                                >

                                    <!-- Area -->

                                    <td>

                                        <span class="workstream-label">
                                            <?= e(
                                                (string) $row['stream']
                                            ); ?>
                                        </span>

                                    </td>

                                    <!-- Phase -->

                                    <td>
                                        <?= e(
                                            (string) $row['phase']
                                        ); ?>
                                    </td>

                                    <!-- Activity -->

                                    <td>

                                        <strong class="traffic-table-activity">
                                            <?= e(
                                                (string) $row['activity']
                                            ); ?>
                                        </strong>

                                    </td>

                                    <!-- PIC -->

                                    <td>
                                        <?= e(
                                            (string) $row['pic']
                                        ); ?>
                                    </td>

                                    <!-- Period -->

                                    <td>

                                        <span class="traffic-table-period">

                                            <?= e(
                                                (string) $row['start']
                                            ); ?>

                                            <span aria-hidden="true">
                                                →
                                            </span>

                                            <?= e(
                                                (string) $row['end']
                                            ); ?>

                                        </span>

                                    </td>

                                    <!-- Status -->

                                    <td>

                                        <span
                                            class="
                                                traffic-badge
                                                <?= e(
                                                    $meta['badge_class']
                                                ); ?>
                                            "
                                        >

                                            <span
                                                class="
                                                    traffic-dot
                                                    <?= e(
                                                        $meta['dot_class']
                                                    ); ?>
                                                "
                                                aria-hidden="true"
                                            ></span>

                                            <?= e(
                                                $meta['label']
                                            ); ?>

                                        </span>

                                    </td>

                                    <!-- Progress -->

                                    <td>

                                        <div class="table-progress">

                                            <div class="table-progress__top">

                                                <strong>
                                                    <?= e(
                                                        (string) $row['progress']
                                                    ); ?>%
                                                </strong>

                                            </div>

                                            <div class="table-progress__track">

                                                <div
                                                    class="
                                                        table-progress__fill
                                                        table-progress__fill--<?= e(
                                                            (string) $row['status']
                                                        ); ?>
                                                    "
                                                    style="
                                                        width:
                                                        <?= e(
                                                            (string) $row['progress']
                                                        ); ?>%
                                                    "
                                                ></div>

                                            </div>

                                        </div>

                                    </td>

                                    <!-- Note -->

                                    <td>

                                        <?php if ($row['note'] !== ''): ?>

                                            <?= e(
                                                (string) $row['note']
                                            ); ?>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                —
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                </article>

            </section>

            <section class="tab-panel" id="peta-jalan" data-tab-panel data-animate hidden>
                <div class="panel">
                    <div class="panel__header">
                        <div>
                            <h3 class="panel__title">Executive Roadmap</h3>
                            <p class="section-subtitle">Fase besar proyek aktif dari perencanaan sampai kesiapan operasional.</p>
                        </div>
                        <div class="topbar__controls">
                            <?php if ($canManageRoadmaps): ?>
                                <a href="/roadmaps/create" class="action-button action-button--primary">Tambah Tahap</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="roadmap-list">
                        <?php foreach ($roadmapItems as $item): ?>
                            <article class="roadmap-item">
                                <div class="roadmap-item__phase"><?= htmlspecialchars($item['phase'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="roadmap-item__content">
                                    <p class="roadmap-item__detail"><?= htmlspecialchars($item['detail'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <div class="roadmap-item__side">
                                        <span class="badge <?= htmlspecialchars(formatStatusClass($item['status']), ENT_QUOTES, 'UTF-8'); ?>">
                                            <?= htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                        <div class="table-actions table-actions--top">
                                            <?php if ($canManageRoadmaps): ?>
                                                <a href="/roadmaps/edit?id=<?= htmlspecialchars((string) $item['id'], ENT_QUOTES, 'UTF-8'); ?>" class="action-button action-button--small">Edit</a>
                                                <form method="post" action="/roadmaps/delete" class="inline-form" data-confirm="Hapus tahap peta jalan ini dari dashboard?">
                                                    <input type="hidden" name="id" value="<?= htmlspecialchars((string) $item['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button type="submit" class="action-button action-button--danger action-button--small">Hapus</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="tab-panel" id="linimasa-teknis" data-tab-panel data-animate hidden>
                <div class="panel">
                    <div class="panel__header">
                        <div>
                            <h3 class="panel__title">Timeline Teknis</h3>
                            <p class="section-subtitle">Aktivitas FSD, development, integrasi, SIT, UAT, PTR, dan deployment proyek aktif.</p>
                        </div>
                        <div class="topbar__controls">
                            <?php if ($canManageMilestones): ?>
                                <a href="/milestones/create" class="action-button action-button--primary">Tambah Milestone</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="milestone-list milestone-list--dense">
                        <?php foreach ($milestones as $milestone): ?>
                            <div class="milestone milestone--timeline">
                                <div class="milestone__top">
                                    <p class="milestone__title"><?= htmlspecialchars($milestone['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <span class="badge <?= htmlspecialchars(formatStatusClass($milestone['status']), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= htmlspecialchars($milestone['status'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>
                                <p class="milestone__meta">Target teknis: <?= htmlspecialchars(formatReadableDate($milestone['target_date']), ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="milestone__meta">PIC: <?= htmlspecialchars($milestone['pic'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <div class="table-actions table-actions--top">
                                    <?php if (canManageScopedRecord('milestones', (string) $milestone['pic'])): ?>
                                        <a href="/milestones/edit?id=<?= htmlspecialchars((string) $milestone['id'], ENT_QUOTES, 'UTF-8'); ?>" class="action-button action-button--small">Edit</a>
                                        <form method="post" action="/milestones/delete" class="inline-form" data-confirm="Hapus milestone ini dari dashboard?">
                                            <input type="hidden" name="id" value="<?= htmlspecialchars((string) $milestone['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="action-button action-button--danger action-button--small">Hapus</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="table-wrapper">
                        <table>
                            <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Aktivitas Teknis</th>
                                <th>PIC</th>
                                <th>Mulai</th>
                                <th>Selesai</th>
                                <th>Status</th>
                                <th>Progres</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($technicalTasks as $task): ?>
                                <tr>
                                    <td><?= htmlspecialchars($task['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($task['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($task['pic'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars(formatReadableDate($task['start_date']), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars(formatReadableDate($task['end_date']), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="badge <?= htmlspecialchars(formatStatusClass($task['status']), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($task['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td><span class="progress-pill"><?= htmlspecialchars((string) $task['progress'], ENT_QUOTES, 'UTF-8'); ?>%</span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="tab-panel" id="bisnis-operasional" data-tab-panel data-animate hidden>
                <div class="section-stack">
                    <article class="panel">
                        <div class="panel__header">
                            <div>
                            <h3 class="panel__title">Timeline Bisnis &amp; Nonteknis</h3>
                            <p class="section-subtitle">Aktivitas BRD, governance, kesiapan operasional, settlement, dan komunikasi proyek aktif.</p>
                            </div>
                        </div>
                        <div class="status-summary">
                            <?php foreach ($boStatusGroups as $label => $value): ?>
                                <div class="status-summary__item">
                                    <span class="badge <?= htmlspecialchars(formatStatusClass($label), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <strong><?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?> kegiatan</strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="panel">
                        <div class="panel__header">
                            <div>
                                <h3 class="panel__title">Aktivitas Bisnis &amp; Nonteknis</h3>
                                <p class="section-subtitle">Daftar aktivitas non-teknis untuk menjaga kesiapan organisasi, operasional, dan publikasi proyek aktif.</p>
                            </div>
                            <div class="topbar__controls">
                                <?php if ($canManageTasks): ?>
                                    <a href="/tasks/create" class="action-button action-button--primary">Tambah Data</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <form method="get" action="/" class="filter-toolbar">
                            <input type="hidden" name="tab" value="bisnis-operasional">
                            <label class="filter-field filter-field--search">
                                <span>Cari aktivitas BO</span>
                                <input type="search" name="task_search" value="<?= htmlspecialchars($taskSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Kode, aktivitas, workstream, atau PIC">
                            </label>
                            <label class="filter-field">
                                <span>Status</span>
                                <select name="task_status">
                                    <?php foreach ($taskFilterOptions['statuses'] as $option): ?>
                                        <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>"<?= $taskStatusFilter === $option ? ' selected' : ''; ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="filter-field">
                                <span>Area BO</span>
                                <select name="task_workstream">
                                    <?php foreach ($taskFilterOptions['workstreams'] as $option): ?>
                                        <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>"<?= $taskWorkstreamFilter === $option ? ' selected' : ''; ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="filter-field">
                                <span>PIC</span>
                                <select name="task_pic">
                                    <?php foreach ($taskFilterOptions['pics'] as $option): ?>
                                        <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>"<?= $taskPicFilter === $option ? ' selected' : ''; ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <div class="filter-actions">
                                <button type="submit" class="action-button action-button--primary">Terapkan Filter</button>
                                <a href="/?tab=bisnis-operasional" class="action-button">Reset</a>
                            </div>
                        </form>
                        <div class="results-summary">
                            <strong><?= htmlspecialchars((string) count($filteredTasks), ENT_QUOTES, 'UTF-8'); ?></strong> kegiatan tampil dari total <?= htmlspecialchars((string) $totalTasks, ENT_QUOTES, 'UTF-8'); ?> kegiatan.
                        </div>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Nama Kegiatan</th>
                                    <th>Workstream</th>
                                    <th>PIC</th>
                                    <th>Tenggat</th>
                                    <th>Status</th>
                                    <th>Progres</th>
                                    <th>Prioritas</th>
                                    <th>Aksi</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if ($filteredTasks === []): ?>
                                    <tr>
                                        <td colspan="9">
                                            <div class="empty-state">
                                                <strong>Tidak ada kegiatan yang cocok dengan filter saat ini.</strong>
                                                <p>Coba ubah status, workstream, PIC, atau kata kunci pencarian.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($filteredTasks as $task): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($task['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars($task['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars($task['workstream'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars($task['pic'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars(formatReadableDate($task['end_date']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><span class="badge <?= htmlspecialchars(formatStatusClass($task['status']), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($task['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td><span class="progress-pill"><?= htmlspecialchars((string) $task['progress'], ENT_QUOTES, 'UTF-8'); ?>%</span></td>
                                        <td><?= htmlspecialchars((string) $task['priority'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <div class="table-actions">
                                                <?php if (canManageScopedRecord('tasks', (string) $task['pic'])): ?>
                                                    <a href="/tasks/edit?id=<?= htmlspecialchars((string) $task['id'], ENT_QUOTES, 'UTF-8'); ?>" class="action-button action-button--small">Edit</a>
                                                    <form method="post" action="/tasks/delete" class="inline-form" data-confirm="Hapus kegiatan ini dari dashboard?">
                                                        <input type="hidden" name="id" value="<?= htmlspecialchars((string) $task['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <button type="submit" class="action-button action-button--danger action-button--small">Hapus</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <article class="panel">
                        <div class="panel__header">
                            <div>
                                <h3 class="panel__title">RACI Ringkas</h3>
                                <p class="section-subtitle">Peran inti lintas instansi untuk keputusan dan eksekusi aktivitas proyek aktif.</p>
                            </div>
                            <div class="topbar__controls">
                                <a href="/raci/create" class="action-button action-button--primary">Tambah RACI</a>
                            </div>
                        </div>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                <tr>
                                    <th>Aktivitas</th>
                                    <th>Responsible</th>
                                    <th>Accountable</th>
                                    <th>Consulted</th>
                                    <th>Informed</th>
                                    <th>Aksi</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($raciRows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['activity'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars($row['responsible'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars($row['accountable'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars($row['consulted'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars($row['informed'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <div class="table-actions">
                                                <?php if ($canManageRaci): ?>
                                                    <a href="/raci/edit?id=<?= htmlspecialchars((string) $row['id'], ENT_QUOTES, 'UTF-8'); ?>" class="action-button action-button--small">Edit</a>
                                                    <form method="post" action="/raci/delete" class="inline-form" data-confirm="Hapus baris RACI ini dari dashboard?">
                                                        <input type="hidden" name="id" value="<?= htmlspecialchars((string) $row['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <button type="submit" class="action-button action-button--danger action-button--small">Hapus</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </div>
            </section>

            <section class="tab-panel" id="daftar-risiko" data-tab-panel data-animate hidden>
                <div class="stack">
                    <article class="panel">
                        <div class="panel__header">
                            <div>
                                <h3 class="panel__title">Risk Register</h3>
                                <p class="section-subtitle">Risiko utama dan issue log proyek aktif dalam satu tampilan review.</p>
                            </div>
                            <div class="topbar__controls">
                                <?php if ($canManageRisks): ?>
                                    <a href="/risks/create" class="action-button action-button--primary">Tambah Risiko</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <form method="get" action="/" class="filter-toolbar">
                            <input type="hidden" name="tab" value="daftar-risiko">
                            <label class="filter-field filter-field--search">
                                <span>Cari risiko</span>
                                <input type="search" name="risk_search" value="<?= htmlspecialchars($riskSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Kode, risiko, kategori, atau pemilik">
                            </label>
                            <label class="filter-field">
                                <span>Level</span>
                                <select name="risk_level">
                                    <?php foreach ($riskFilterOptions['levels'] as $option): ?>
                                        <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>"<?= $riskLevelFilter === $option ? ' selected' : ''; ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="filter-field">
                                <span>Status</span>
                                <select name="risk_status">
                                    <?php foreach ($riskFilterOptions['statuses'] as $option): ?>
                                        <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>"<?= $riskStatusFilter === $option ? ' selected' : ''; ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <div class="filter-actions">
                                <button type="submit" class="action-button action-button--primary">Terapkan Filter</button>
                                <a href="/?tab=daftar-risiko" class="action-button">Reset</a>
                            </div>
                        </form>
                        <div class="results-summary">
                            <strong><?= htmlspecialchars((string) count($filteredRisks), ENT_QUOTES, 'UTF-8'); ?></strong> risiko tampil dari total <?= htmlspecialchars((string) count($risks), ENT_QUOTES, 'UTF-8'); ?> risiko.
                        </div>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Kategori</th>
                                    <th>Risiko</th>
                                    <th>Level</th>
                                    <th>Pemilik</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if ($filteredRisks === []): ?>
                                    <tr>
                                        <td colspan="7">
                                            <div class="empty-state">
                                                <strong>Tidak ada risiko yang cocok dengan filter saat ini.</strong>
                                                <p>Coba ubah level, status, atau kata kunci pencarian.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($filteredRisks as $risk): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($risk['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars($risk['category'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars($risk['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars($risk['level'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars($risk['owner'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><span class="badge <?= htmlspecialchars(formatStatusClass($risk['status']), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($risk['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td>
                                            <div class="table-actions">
                                                <?php if (canManageScopedRecord('risks', (string) $risk['owner'])): ?>
                                                    <a href="/risks/edit?id=<?= htmlspecialchars((string) $risk['id'], ENT_QUOTES, 'UTF-8'); ?>" class="action-button action-button--small">Edit</a>
                                                    <form method="post" action="/risks/delete" class="inline-form" data-confirm="Hapus risiko ini dari dashboard?">
                                                        <input type="hidden" name="id" value="<?= htmlspecialchars((string) $risk['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <button type="submit" class="action-button action-button--danger action-button--small">Hapus</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <article class="panel">
                        <div class="panel__header">
                            <div>
                                <h3 class="panel__title">Issue Log</h3>
                                <p class="section-subtitle">Daftar isu terbuka yang perlu penyelesaian cepat di luar risk register.</p>
                            </div>
                        </div>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Kategori</th>
                                    <th>Deskripsi Isu</th>
                                    <th>Prioritas</th>
                                    <th>Target Resolve</th>
                                    <th>PIC</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($issueLogs as $issue): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) $issue['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars((string) $issue['category'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars((string) $issue['description'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars((string) $issue['priority'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars(formatReadableDate((string) $issue['target_resolve']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars((string) $issue['pic'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><span class="badge <?= htmlspecialchars(formatStatusClass((string) $issue['status']), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $issue['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td>
                                            <div class="table-actions">
                                                <?php if (canManageScopedRecord('issues', (string) $issue['pic'])): ?>
                                                    <a href="/issues/edit?id=<?= htmlspecialchars((string) $issue['id'], ENT_QUOTES, 'UTF-8'); ?>" class="action-button action-button--small">Edit</a>
                                                    <form method="post" action="/issues/delete" class="inline-form" data-confirm="Hapus issue log ini dari dashboard?">
                                                        <input type="hidden" name="id" value="<?= htmlspecialchars((string) $issue['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <button type="submit" class="action-button action-button--danger action-button--small">Hapus</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($canManageIssues): ?>
                            <div class="table-actions table-actions--top">
                                <a href="/issues/create" class="action-button action-button--primary">Tambah Issue Log</a>
                            </div>
                        <?php endif; ?>
                    </article>
                </div>
            </section>
        </div>

        <?php if ($isAdministrator): ?>
        <section class="section-stack admin-strip" data-animate>
            <section class="master-pic-hero">
                <div class="master-pic-hero__content">
                    <p class="master-pic-hero__eyebrow">Utilitas Admin</p>
                    <h3>Area pendukung proyek aktif</h3>
                    <p>Bagian ini dipakai untuk ekspor ringkasan, pengecekan hosting, audit trail, dan master PIC tanpa mengganggu fokus pembacaan dashboard utama.</p>
                </div>
                <div class="master-pic-hero__actions">
                    <?php if ($canExportReports): ?>
                        <a href="/reports/export" class="action-button">Ekspor Ringkasan</a>
                    <?php endif; ?>
                    <?php if (canManageModule('*')): ?>
                        <a href="/system/check" class="action-button">Cek Hosting</a>
                    <?php endif; ?>
                    <?php if ($canManagePics): ?>
                        <a href="/pics/create" class="action-button action-button--primary">Tambah PIC</a>
                    <?php endif; ?>
                </div>
            </section>

            <div class="admin-strip__body">
                <article class="panel panel--master-pic">
                    <div class="panel__header">
                        <div>
                            <h3 class="panel__title">Master PIC</h3>
                            <p class="section-subtitle">Kelola PIC terpusat yang dipakai di kegiatan dan milestone proyek aktif.</p>
                        </div>
                    </div>
                    <form method="get" action="/" class="filter-toolbar">
                        <label class="filter-field filter-field--search">
                            <span>Cari PIC</span>
                            <input type="search" name="pic_search" value="<?= htmlspecialchars($picSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nama PIC atau peran / unit">
                        </label>
                        <label class="filter-field">
                            <span>Peran / Unit</span>
                            <select name="pic_role">
                                <?php foreach ($picRoleOptions as $option): ?>
                                    <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>"<?= $picRoleFilter === $option ? ' selected' : ''; ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="filter-actions">
                            <button type="submit" class="action-button action-button--primary">Terapkan Filter</button>
                            <a href="/" class="action-button">Reset</a>
                        </div>
                    </form>
                    <div class="results-summary">
                        <strong><?= htmlspecialchars((string) count($filteredPicRows), ENT_QUOTES, 'UTF-8'); ?></strong> PIC tampil dari total <?= htmlspecialchars((string) count($picRows), ENT_QUOTES, 'UTF-8'); ?> PIC.
                    </div>
                    <div class="table-wrapper table-wrapper--master-pic">
                        <table>
                            <thead>
                            <tr>
                                <th>Instansi / Kode PIC</th>
                                <th>Nama Person / PIC</th>
                                <th>Peran / Unit</th>
                                <th>Pembaruan</th>
                                <th>Aksi</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if ($filteredPicRows === []): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <strong>Tidak ada PIC yang cocok dengan filter saat ini.</strong>
                                            <p>Coba ubah peran atau kata kunci pencarian.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($filteredPicRows as $pic): ?>
                                <tr>
                                    <td>
                                        <div class="master-pic-person">
                                            <span class="master-pic-person__avatar"><?= htmlspecialchars(strtoupper(substr((string) $pic['name'], 0, 1)), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <div>
                                                <strong><?= htmlspecialchars((string) $pic['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <p class="master-pic-person__meta">Terdaftar di master dropdown PIC</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($pic['person_name'] !== null && trim((string) $pic['person_name']) !== ''): ?>
                                            <strong><?= htmlspecialchars((string) $pic['person_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <?php else: ?>
                                            <span class="master-pic-role master-pic-role--muted">Belum diisi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($pic['role_label'] !== null && trim((string) $pic['role_label']) !== ''): ?>
                                            <span class="master-pic-role"><?= htmlspecialchars((string) $pic['role_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php else: ?>
                                            <span class="master-pic-role master-pic-role--muted">Belum diisi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars(formatDateTime((string) $pic['updated_at']), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <?php if ($canManagePics): ?>
                                                <a href="/pics/edit?id=<?= htmlspecialchars((string) $pic['id'], ENT_QUOTES, 'UTF-8'); ?>" class="action-button action-button--small">Edit</a>
                                                <form method="post" action="/pics/delete" class="inline-form" data-confirm="Hapus PIC ini dari master? Pastikan tidak sedang dipakai di kegiatan atau milestone.">
                                                    <input type="hidden" name="id" value="<?= htmlspecialchars((string) $pic['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button type="submit" class="action-button action-button--danger action-button--small">Hapus</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="panel panel--audit-trail">
                    <div class="panel__header">
                        <div>
                            <h3 class="panel__title">Riwayat Perubahan Terbaru</h3>
                            <p class="section-subtitle">Jejak perubahan data terbaru untuk kebutuhan kontrol internal.</p>
                        </div>
                    </div>
                    <div class="audit-list">
                        <?php if ($recentAuditLogs === []): ?>
                            <div class="empty-state">
                                <strong>Belum ada riwayat perubahan.</strong>
                                <p>Riwayat akan muncul otomatis setiap ada tambah, edit, atau hapus data.</p>
                            </div>
                        <?php endif; ?>
                        <?php foreach ($recentAuditLogs as $log): ?>
                            <article class="audit-item">
                                <div class="audit-item__top">
                                    <span class="badge"><?= htmlspecialchars(formatAuditActionLabel((string) $log['action']), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <strong><?= htmlspecialchars((string) $log['module'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                </div>
                                <p class="audit-item__label"><?= htmlspecialchars((string) $log['entity_label'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="audit-item__meta">Oleh <?= htmlspecialchars((string) $log['performed_by'], ENT_QUOTES, 'UTF-8'); ?> pada <?= htmlspecialchars(formatDateTime((string) $log['created_at']), ENT_QUOTES, 'UTF-8'); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </article>
            </div>
        </section>
        <?php endif; ?>

        <footer class="page-footer">
            Dashboard PMO untuk PT Jakarta Lingko Indonesia.
        </footer>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
