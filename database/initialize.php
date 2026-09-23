<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$pdo = getDatabaseConnection();

// Ubah menjadi true hanya jika memang ingin mengisi data demo.
$enableDemoSeed = false;


/*
|--------------------------------------------------------------------------
| TABLE: TASKS
|--------------------------------------------------------------------------
*/

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL,
        name TEXT NOT NULL,
        workstream TEXT NOT NULL,
        pic TEXT NOT NULL,
        start_date TEXT NOT NULL,
        end_date TEXT NOT NULL,
        status TEXT NOT NULL,
        progress INTEGER NOT NULL CHECK(progress >= 0 AND progress <= 100),
        priority INTEGER NOT NULL DEFAULT 1,
        notes TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )'
);

// Satukan kategori lama agar timeline hanya terbagi menjadi Teknis dan Bisnis & Nonteknis.
$pdo->exec(
    "UPDATE tasks
     SET workstream = 'Bisnis & Nonteknis'
     WHERE workstream IN ('Bisnis & Operasional', 'Komunikasi', 'Kebijakan')"
);

$pdo->exec(
    "UPDATE tasks
     SET workstream = 'Bisnis & Nonteknis'
     WHERE code IN ('TJT-TEC-01', 'TJT-TEC-02', 'TJT-TEC-03')"
);


/*
|--------------------------------------------------------------------------
| TABLE: MILESTONES
|--------------------------------------------------------------------------
*/

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS milestones (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        target_date TEXT NOT NULL,
        status TEXT NOT NULL,
        pic TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )'
);


/*
|--------------------------------------------------------------------------
| TABLE: RISKS
|--------------------------------------------------------------------------
*/

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS risks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL,
        title TEXT NOT NULL,
        category TEXT NOT NULL,
        level TEXT NOT NULL,
        owner TEXT NOT NULL,
        status TEXT NOT NULL,
        mitigation TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )'
);


/*
|--------------------------------------------------------------------------
| TABLE: ROADMAPS
|--------------------------------------------------------------------------
*/

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS roadmaps (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        phase TEXT NOT NULL,
        detail TEXT NOT NULL,
        status TEXT NOT NULL,
        sequence INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )'
);


/*
|--------------------------------------------------------------------------
| TABLE: RACI
|--------------------------------------------------------------------------
*/

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS raci_matrix (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        activity TEXT NOT NULL,
        responsible TEXT NOT NULL,
        accountable TEXT NOT NULL,
        consulted TEXT NOT NULL,
        informed TEXT NOT NULL,
        sequence INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )'
);


/*
|--------------------------------------------------------------------------
| TABLE: AUDIT LOG
|--------------------------------------------------------------------------
*/

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module TEXT NOT NULL,
        action TEXT NOT NULL,
        entity_label TEXT NOT NULL,
        performed_by TEXT NOT NULL,
        performed_role TEXT NOT NULL,
        context TEXT,
        created_at TEXT NOT NULL
    )'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS data_imports (
        source_key TEXT PRIMARY KEY,
        imported_at TEXT NOT NULL
    )'
);


/*
|--------------------------------------------------------------------------
| TABLE: MASTER PIC
|--------------------------------------------------------------------------
*/

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS pics (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        person_name TEXT,
        role_label TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )'
);


/*
|--------------------------------------------------------------------------
| MIGRATION: PIC PERSON NAME
|--------------------------------------------------------------------------
*/

$picColumns = $pdo->query(
    "PRAGMA table_info('pics')"
)->fetchAll();

$picColumnNames = array_map(
    static fn (array $column): string => (string) ($column['name'] ?? ''),
    $picColumns
);

if (!in_array('person_name', $picColumnNames, true)) {
    $pdo->exec(
        'ALTER TABLE pics ADD COLUMN person_name TEXT'
    );
}


/*
|--------------------------------------------------------------------------
| TABLE: ISSUE LOG
|--------------------------------------------------------------------------
*/

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS issue_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL,
        category TEXT NOT NULL,
        description TEXT NOT NULL,
        priority TEXT NOT NULL,
        level TEXT NOT NULL,
        target_resolve TEXT,
        pic TEXT NOT NULL,
        status TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )'
);

/* Setiap catatan operasional terikat pada satu proyek. Data lama adalah EIPTJ. */
$projectScopedTables = [
    'tasks',
    'milestones',
    'risks',
    'roadmaps',
    'raci_matrix',
    'issue_logs',
    'audit_logs',
];

foreach ($projectScopedTables as $projectScopedTable) {
    $columns = $pdo->query("PRAGMA table_info($projectScopedTable)")->fetchAll();
    $columnNames = array_map(
        static fn (array $column): string => (string) ($column['name'] ?? ''),
        $columns
    );

    if (!in_array('project_key', $columnNames, true)) {
        $pdo->exec(
            "ALTER TABLE $projectScopedTable ADD COLUMN project_key TEXT NOT NULL DEFAULT 'eiptj'"
        );
    }
}


/*
|--------------------------------------------------------------------------
| UNIQUE INDEX UNTUK KODE
|--------------------------------------------------------------------------
|
| Layer keamanan database.
| Walaupun create/edit sudah melakukan validasi kode duplikat,
| SQLite juga akan menolak kode yang sama.
|
*/

$pdo->exec('DROP INDEX IF EXISTS idx_tasks_code_unique');
$pdo->exec('DROP INDEX IF EXISTS idx_risks_code_unique');
$pdo->exec('DROP INDEX IF EXISTS idx_issue_logs_code_unique');
$pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_tasks_project_code_unique ON tasks (project_key, code)');
$pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_risks_project_code_unique ON risks (project_key, code)');
$pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_issue_logs_project_code_unique ON issue_logs (project_key, code)');


/*
|--------------------------------------------------------------------------
| NORMALISASI PIC OPERATOR
|--------------------------------------------------------------------------
|
| PTO adalah kelompok operator, bukan PIC. Data lama dimigrasikan ke
| instansi spesifik agar pihak yang perlu ditindaklanjuti terlihat jelas.
|
*/

$standardPicEntries = [
    ['Admin', 'Admin PMO', 'Administrator PMO'],
    ['DISHUB', null, 'Regulator / Project Sponsor'],
    ['TJ', null, 'TransJakarta / Operator Utama'],
    ['LRTJ', null, 'LRT Jakarta / Operator'],
    ['MRTJ', null, 'MRT Jakarta / Operator'],
    ['JLI', null, 'JakLingko Indonesia'],
    ['JPAI/Jatelindo', null, 'Mitra strategis / vendor'],
];

$insertStandardPic = $pdo->prepare(
    'INSERT OR IGNORE INTO pics (
        name, person_name, role_label, created_at, updated_at
    ) VALUES (
        :name, :person_name, :role_label, :created_at, :updated_at
    )'
);

$picTimestamp = date('Y-m-d H:i:s');
foreach ($standardPicEntries as [$name, $personName, $roleLabel]) {
    $insertStandardPic->execute([
        ':name' => $name,
        ':person_name' => $personName,
        ':role_label' => $roleLabel,
        ':created_at' => $picTimestamp,
        ':updated_at' => $picTimestamp,
    ]);
}

$legacyPicMappings = [
    'PTO' => 'TJ, LRTJ, MRTJ',
    'JLI & PTO' => 'JLI, TJ, LRTJ, MRTJ',
    'JLI, PTO. DISHUB' => 'JLI, TJ, LRTJ, MRTJ, DISHUB',
    'PTO & JLI' => 'TJ, LRTJ, MRTJ, JLI',
    'DISHUB & PTO' => 'DISHUB, TJ, LRTJ, MRTJ',
    'DISHUB, PTO, JLI' => 'DISHUB, TJ, LRTJ, MRTJ, JLI',
    'DISHUB, PTO' => 'DISHUB, TJ, LRTJ, MRTJ',
    'PTO, JLI' => 'TJ, LRTJ, MRTJ, JLI',
];

foreach ($legacyPicMappings as $legacyPic => $replacementPic) {
    foreach (['tasks', 'milestones', 'issue_logs'] as $tableName) {
        $statement = $pdo->prepare(
            'UPDATE ' . $tableName . ' SET pic = :replacement WHERE pic = :legacy'
        );
        $statement->execute([
            ':replacement' => $replacementPic,
            ':legacy' => $legacyPic,
        ]);
    }

    $riskStatement = $pdo->prepare(
        'UPDATE risks SET owner = :replacement WHERE owner = :legacy'
    );
    $riskStatement->execute([
        ':replacement' => $replacementPic,
        ':legacy' => $legacyPic,
    ]);
}

$deleteLegacyPic = $pdo->prepare('DELETE FROM pics WHERE name = :name');
foreach (array_keys($legacyPicMappings) as $legacyPic) {
    $deleteLegacyPic->execute([':name' => $legacyPic]);
}


/*
|--------------------------------------------------------------------------
| HELPER: EXCEL SERIAL DATE
|--------------------------------------------------------------------------
*/

function excelSerialToDateString(int|float|string $value): string
{
    $serial = (int) round((float) $value);
    $baseDate = new DateTimeImmutable('1899-12-30');

    return $baseDate
        ->modify('+' . $serial . ' days')
        ->format('Y-m-d');
}


/*
|--------------------------------------------------------------------------
| HITUNG DATA SAAT INI
|--------------------------------------------------------------------------
*/

$taskCount = (int) $pdo
    ->query('SELECT COUNT(*) FROM tasks')
    ->fetchColumn();

$milestoneCount = (int) $pdo
    ->query('SELECT COUNT(*) FROM milestones')
    ->fetchColumn();

$riskCount = (int) $pdo
    ->query('SELECT COUNT(*) FROM risks')
    ->fetchColumn();

$roadmapCount = (int) $pdo
    ->query('SELECT COUNT(*) FROM roadmaps')
    ->fetchColumn();

$raciCount = (int) $pdo
    ->query('SELECT COUNT(*) FROM raci_matrix')
    ->fetchColumn();

$picCount = (int) $pdo
    ->query('SELECT COUNT(*) FROM pics')
    ->fetchColumn();

$issueLogCount = (int) $pdo
    ->query('SELECT COUNT(*) FROM issue_logs')
    ->fetchColumn();


/*
|--------------------------------------------------------------------------
| UPDATE MASTER DATA EIPTJ: TIMELINE DISHUB
|--------------------------------------------------------------------------
| Sumber: Perubahan Tarif TJ - Master Data.xlsx, sheet Timeline DISHUB.
| Import ini menggantikan aktivitas Teknis lama satu kali agar data lama
| tidak bercampur dengan master data terbaru.
*/

$eiptjTimelineSource = 'eiptj_timeline_dishub_2026_09_23';
$eiptjTimelineImported = $pdo->prepare(
    'SELECT COUNT(*) FROM data_imports WHERE source_key = :source_key'
);
$eiptjTimelineImported->execute([':source_key' => $eiptjTimelineSource]);

if ((int) $eiptjTimelineImported->fetchColumn() === 0) {
    $pdo->prepare(
        "DELETE FROM tasks WHERE project_key = 'eiptj' AND workstream = 'Teknis'"
    )->execute();

    $eiptjTaskInsert = $pdo->prepare(
        'INSERT INTO tasks (
            project_key, code, name, workstream, pic, start_date, end_date,
            status, progress, priority, notes, created_at, updated_at
        ) VALUES (
            :project_key, :code, :name, :workstream, :pic, :start_date, :end_date,
            :status, :progress, :priority, :notes, :created_at, :updated_at
        )'
    );

    $eiptjTechnicalTasks = [
        ['Perencanaan', 'Final Requirement/Regulasi/BRD Approval', 'DISHUB', 46209, 46224, 50, 'Sedang Berjalan', 'Belum di TTD oleh stakeholder versi 1.4.'],
        ['Perencanaan', 'Data Fare Matrix Transjakarta', 'TJ', 46218, 46224, 80, 'Sedang Berjalan', 'Distance Matrix, Shelter Code, Terminal ID, Data Halte, dan Bus Stop perlu diperbarui.'],
        ['Perencanaan', 'Final Bisnis Proses Baru/Perubahan (use case positif dan negatif)', 'DISHUB, TJ, JLI', 46218, 46224, 90, 'Sedang Berjalan', 'FSD masih berjalan.'],
        ['Design & Development (FSD/DEV)', 'L1 Device Acceptance KUE', 'JLI', 46213, 46229, 100, 'Selesai', 'Library dan dokumentasi integrasi untuk LRT, MRT, dan TJ sudah dikirimkan.'],
        ['Design & Development (FSD/DEV)', 'L1 Device Acceptance QR STT', 'JLI', 46213, 46229, 100, 'Selesai', 'Selesai.'],
        ['Design & Development (FSD/DEV)', 'L1 Device Acceptance ABT', 'JLI', 46213, 46348, 100, 'Selesai', 'Selesai.'],
        ['Design & Development (FSD/DEV)', 'MaaS QR STT', 'JLI', 46213, 46234, 100, 'Selesai', 'Selesai.'],
        ['Design & Development (FSD/DEV)', 'Tarif Langganan (Period Pass)', 'JLI', 46213, 46371, 10, 'Sedang Berjalan', 'Proses assessment, target Desember 2026.'],
        ['Design & Development (FSD/DEV)', 'PAYG', 'JLI', 46296, 46371, 0, 'Belum Dimulai', 'Proses assessment, target minggu ke-3 atau ke-4 November 2026.'],
        ['Design & Development (FSD/DEV)', 'CCHS', 'JLI', 46225, 46244, 50, 'Sedang Berjalan', 'Progres 7 September: KUE 95%, QR-STT 50%, ABT 0%.'],
        ['Integrasi Sistem', 'Sinkronisasi dengan operator transportasi publik', 'JLI, TJ, LRTJ, MRTJ', 46227, 46229, 0, 'Belum Dimulai', 'Masih menunggu jadwal dan diskusi bisnis TJ dengan JLI.'],
        ['Pengujian / Testing', 'SIT (Pengujian Integrasi)', 'JLI, TJ, LRTJ, MRTJ', 46230, 46239, 15, 'Sedang Berjalan', 'SIT internal KUE dan STT selesai 21 September 2026.'],
        ['Pengujian / Testing', 'UAT (Pengujian Skema dan Bisnis Proses)', 'JLI, TJ, LRTJ, MRTJ, DISHUB', 46240, 46246, 0, 'Belum Dimulai', 'Menunggu kesiapan SIT.'],
        ['Pengujian / Testing', 'PTR (Piloting/Uji Coba Terbatas)', 'JLI, TJ, LRTJ, MRTJ, DISHUB', 46247, 46261, 0, 'Belum Dimulai', 'Menunggu UAT.'],
        ['Implementasi / Deployment', 'Deployment Tahap 0 (TransBandara, Halte BRT)', 'TJ', 46234, 46234, 0, 'Belum Dimulai', 'Menunggu kesiapan implementasi.'],
        ['Implementasi / Deployment', 'Deployment Tahap 0 (BRT, MRT, dan LRTJ)', 'TJ, JLI', 46234, 46234, 0, 'Belum Dimulai', 'Menunggu kesiapan implementasi.'],
        ['Implementasi / Deployment', 'Deployment Tahap I (1 BRT, 1 MRT, 1 LRT, 1 NBRT, 1 MT)', 'JLI, TJ, LRTJ, MRTJ', 46260, 46265, 0, 'Belum Dimulai', 'Menunggu PTR.'],
        ['Implementasi / Deployment', 'Deployment Tahap II (BRT, NBRT, MT, MRT, LRTJ)', 'JLI, TJ, LRTJ, MRTJ', 46379, 46387, 0, 'Belum Dimulai', 'Menunggu tahap I.'],
        ['Operational', 'Monitor Operasional', 'JLI, TJ, LRTJ, MRTJ', 46388, 46418, 0, 'Belum Dimulai', 'Menunggu deployment.'],
    ];

    $timestamp = date('Y-m-d H:i:s');
    foreach ($eiptjTechnicalTasks as $index => [$phase, $name, $pic, $start, $end, $progress, $status, $notes]) {
        $eiptjTaskInsert->execute([
            ':project_key' => 'eiptj',
            ':code' => 'EIPTJ-TEC-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
            ':name' => $name,
            ':workstream' => 'Teknis',
            ':pic' => $pic,
            ':start_date' => excelSerialToDateString($start),
            ':end_date' => excelSerialToDateString($end),
            ':status' => $status,
            ':progress' => $progress,
            ':priority' => in_array($phase, ['Perencanaan', 'Integrasi Sistem', 'Pengujian / Testing'], true) ? 5 : 3,
            ':notes' => $phase . '. ' . $notes,
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);
    }

    $pdo->prepare(
        'INSERT INTO data_imports (source_key, imported_at) VALUES (:source_key, :imported_at)'
    )->execute([
        ':source_key' => $eiptjTimelineSource,
        ':imported_at' => $timestamp,
    ]);
}

$eiptjBusinessSource = 'eiptj_business_timeline_2026_09_23';
$eiptjBusinessImported = $pdo->prepare(
    'SELECT COUNT(*) FROM data_imports WHERE source_key = :source_key'
);
$eiptjBusinessImported->execute([':source_key' => $eiptjBusinessSource]);

if ((int) $eiptjBusinessImported->fetchColumn() === 0) {
    $pdo->prepare(
        "DELETE FROM tasks WHERE project_key = 'eiptj' AND workstream = 'Bisnis & Nonteknis'"
    )->execute();

    $eiptjBusinessInsert = $pdo->prepare(
        'INSERT INTO tasks (
            project_key, code, name, workstream, pic, start_date, end_date,
            status, progress, priority, notes, created_at, updated_at
        ) VALUES (
            :project_key, :code, :name, :workstream, :pic, :start_date, :end_date,
            :status, :progress, :priority, :notes, :created_at, :updated_at
        )'
    );

    $eiptjBusinessTasks = [
        ['Governance & Project Alignment', 'Kick-off Project & Steering Committee', 'DISHUB', 46209, 46227, 50, 'Hanya MOM', 5],
        ['Governance & Project Alignment', 'PMO & Working Group Lintas Instansi', 'DISHUB, TJ, LRTJ, MRTJ', 46223, 46234, 0, 'Belum terbentuk', 5],
        ['Fare Policy & Business Rules', 'Finalisasi Kebijakan Tarif & Fare Matrix', 'DISHUB', 46223, 46227, 0, 'Hanya MOM', 5],
        ['Fare Policy & Business Rules', 'Persetujuan Standar Sektor KUE & Tarif Integrasi', 'DISHUB, TJ, LRTJ, MRTJ', 46223, 46227, 60, 'Hanya MOM', 5],
        ['Fare Policy & Business Rules', 'Persetujuan TOB & TOM Terintegrasi', 'DISHUB, TJ', 46223, 46227, 0, 'Belum jelas', 5],
        ['Fare Policy & Business Rules', 'Persetujuan Business Rules TransBandara', 'DISHUB, TJ', 46223, 46230, 60, 'Hanya MOM', 4],
        ['Fare Policy & Business Rules', 'Persetujuan Business Rules Tarif Angkutan Umum', 'DISHUB, TJ, LRTJ, MRTJ', 46223, 46264, 60, 'Hanya MOM', 4],
        ['Fare Policy & Business Rules', 'Persetujuan Tanggal Implementasi', 'DISHUB', 46228, 46230, 0, 'Tanggal implementasi belum ditetapkan.', 5],
        ['Operator Readiness', 'Rapat Penyelarasan Operator', 'DISHUB, TJ, LRTJ, MRTJ', 46223, 46227, 0, 'Menunggu konfirmasi TJ.', 5],
        ['Operator Readiness', 'Kesepakatan Kesiapan Operasional Operator', 'DISHUB, TJ, LRTJ, MRTJ, JLI', 46223, 46261, 0, 'Kesepakatan bisnis proses belum final.', 5],
        ['Transaction Readiness', 'Kesepakatan Migrasi Transaksi TB, NBRT & MT', 'DISHUB, TJ, JLI', 46223, 46230, 0, 'Menunggu konfirmasi TJ.', 5],
        ['Transaction Readiness', 'Kesepakatan Migrasi Transaksi 9 Stasiun MRT Jakarta', 'DISHUB, MRTJ, JLI', 46223, 46261, 60, 'Menunggu konfirmasi MRT Jakarta.', 4],
        ['Settlement Readiness', 'Settlement Non BRT (TOB/TOM)', 'TJ, LRTJ, MRTJ, JLI', 46227, 46259, 0, 'MID/TID perlu difinalisasi.', 4],
        ['Settlement Readiness', 'Reconcile Non BRT (TOB/TOM)', 'TJ, LRTJ, MRTJ, JLI', 46227, 46259, 0, 'MID/TID perlu difinalisasi.', 4],
        ['Communication & Change Management', 'Penyusunan Materi Sosialisasi Internal dan Eksternal', 'DISHUB, TJ, LRTJ, MRTJ, JLI', 46227, 46418, 20, 'Materi dalam penyelarasan.', 3],
        ['Communication & Change Management', 'Penjadwalan & Inventarisasi Spot/Media Sosialisasi', 'DISHUB, TJ, LRTJ, MRTJ, JLI', 46227, 46418, 0, 'Belum dimulai.', 3],
        ['Operational Readiness Review', 'Update Operational Procedure', 'TJ, LRTJ, MRTJ, JLI', 46230, 46350, 50, 'Pembaruan proses bisnis berjalan.', 4],
        ['Operational Readiness Review', 'Update Incident Management SOP', 'TJ, LRTJ, MRTJ, JLI', 46230, 46350, 50, 'Pembaruan SOP berjalan.', 4],
    ];

    $timestamp = date('Y-m-d H:i:s');
    foreach ($eiptjBusinessTasks as $index => [$phase, $name, $pic, $start, $end, $progress, $notes, $priority]) {
        $eiptjBusinessInsert->execute([
            ':project_key' => 'eiptj',
            ':code' => 'EIPTJ-BUS-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
            ':name' => $name,
            ':workstream' => 'Bisnis & Nonteknis',
            ':pic' => $pic,
            ':start_date' => excelSerialToDateString($start),
            ':end_date' => excelSerialToDateString($end),
            ':status' => $progress === 0 ? 'Belum Dimulai' : 'Sedang Berjalan',
            ':progress' => $progress,
            ':priority' => $priority,
            ':notes' => $phase . '. ' . $notes,
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);
    }

    $pdo->prepare(
        'INSERT INTO data_imports (source_key, imported_at) VALUES (:source_key, :imported_at)'
    )->execute([
        ':source_key' => $eiptjBusinessSource,
        ':imported_at' => $timestamp,
    ]);
}

/* Excel sumber menulis 31/11/2026; November hanya sampai tanggal 30. */
$pdo->exec(
    "UPDATE tasks
     SET end_date = '2026-11-30'
     WHERE project_key = 'eiptj'
       AND code IN ('EIPTJ-BUS-17', 'EIPTJ-BUS-18')"
);


/*
|--------------------------------------------------------------------------
| DATA AWAL: TERMINAL ANGKUTAN JALAN
|--------------------------------------------------------------------------
| Timeline bersumber dari materi rapat DISHUB - TAJ - JLI, 24 Agustus 2026.
| PIC TAJ disederhanakan hanya menjadi DISHUB dan JLI sesuai arahan PMO.
*/

$tajTaskCountStatement = $pdo->prepare(
    "SELECT COUNT(*) FROM tasks WHERE project_key = 'taj'"
);
$tajTaskCountStatement->execute();

if ((int) $tajTaskCountStatement->fetchColumn() === 0) {
    $tajTaskInsert = $pdo->prepare(
        'INSERT INTO tasks (
            project_key, code, name, workstream, pic, start_date, end_date,
            status, progress, priority, notes, created_at, updated_at
        ) VALUES (
            :project_key, :code, :name, :workstream, :pic, :start_date, :end_date,
            :status, :progress, :priority, :notes, :created_at, :updated_at
        )'
    );

    $tajTasks = [
        ['TAJ-01', 'Final negosiasi dan rilis PO', 'Bisnis & Nonteknis', 'DISHUB', '2026-09-01', '2026-09-06'],
        ['TAJ-02', 'PT Sepakat merilis PO ke JLI', 'Bisnis & Nonteknis', 'DISHUB', '2026-09-07', '2026-09-13'],
        ['TAJ-03', 'JLI dan partner produksi sesuai PO', 'Teknis', 'JLI', '2026-09-07', '2026-11-15'],
        ['TAJ-04', 'Proses pembuatan rekening bank', 'Bisnis & Nonteknis', 'DISHUB', '2026-09-01', '2026-09-13'],
        ['TAJ-05', 'Proses pembuatan merchant', 'Bisnis & Nonteknis', 'DISHUB', '2026-09-14', '2026-09-30'],
        ['TAJ-06', 'Proses peminjaman KUE dan SAM Dev', 'Teknis', 'DISHUB', '2026-09-21', '2026-09-30'],
        ['TAJ-07', 'Membangun akseptansi KUE', 'Teknis', 'JLI', '2026-10-01', '2026-11-08'],
        ['TAJ-08', 'SIT dan UAT bersama TAJ', 'Teknis', 'JLI', '2026-11-01', '2026-11-15'],
        ['TAJ-09', 'PTR', 'Teknis', 'JLI', '2026-11-16', '2026-11-22'],
        ['TAJ-10', 'Pekerjaan sipil dan infrastruktur', 'Teknis', 'JLI', '2026-10-05', '2026-11-08'],
        ['TAJ-11', 'Instalasi infrastruktur di kantor terminal', 'Teknis', 'JLI', '2026-10-12', '2026-11-08'],
        ['TAJ-12', 'Instalasi perangkat akses dan AFC', 'Teknis', 'JLI', '2026-11-01', '2026-11-22'],
        ['TAJ-13', 'Pelatihan petugas TAJ', 'Bisnis & Nonteknis', 'JLI', '2026-12-01', '2026-12-06'],
        ['TAJ-14', 'BAST proyek', 'Bisnis & Nonteknis', 'DISHUB', '2026-12-07', '2026-12-13'],
    ];

    $timestamp = date('Y-m-d H:i:s');
    foreach ($tajTasks as [$code, $name, $workstream, $pic, $startDate, $endDate]) {
        $tajTaskInsert->execute([
            ':project_key' => 'taj',
            ':code' => $code,
            ':name' => $name,
            ':workstream' => $workstream,
            ':pic' => $pic,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':status' => 'Belum Dimulai',
            ':progress' => 0,
            ':priority' => 3,
            ':notes' => 'Rencana awal berdasarkan timeline rapat. Perbarui status setelah konfirmasi PIC.',
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);
    }
}

/* Risalah rapat 24 Agustus 2026: target dan ketergantungan pelaksanaan TAJ. */
$tajMilestoneCount = $pdo->query(
    "SELECT COUNT(*) FROM milestones WHERE project_key = 'taj'"
)->fetchColumn();

if ((int) $tajMilestoneCount === 0) {
    $tajMilestoneInsert = $pdo->prepare(
        'INSERT INTO milestones (
            project_key, name, target_date, status, pic, created_at, updated_at
        ) VALUES (
            :project_key, :name, :target_date, :status, :pic, :created_at, :updated_at
        )'
    );

    $tajMilestones = [
        ['Dokumen e-Procurement dan material list selesai', '2026-08-28', 'Dalam Review', 'JLI'],
        ['Kurasi produk digitalisasi terminal dan tautan e-Proc selesai', '2026-09-03', 'Dalam Review', 'DISHUB'],
        ['Pemilihan produk e-Procurement dan negosiasi', '2026-09-09', 'Dalam Review', 'DISHUB'],
        ['PO dikirim ke JLI untuk memulai proses', '2026-09-10', 'Dalam Review', 'DISHUB'],
        ['Pembukaan rekening dan pengurusan AM Card, MID, serta EDC', '2026-09-30', 'Sedang Berjalan', 'DISHUB'],
        ['Pekerjaan, testing SIT/UAT, dan implementasi terminal selesai', '2026-12-04', 'Belum Dimulai', 'JLI'],
        ['PTR sampai transaksi bank', '2026-12-05', 'Belum Dimulai', 'JLI'],
        ['BAST pekerjaan dan proses invoicing', '2026-12-10', 'Belum Dimulai', 'DISHUB'],
    ];

    $timestamp = date('Y-m-d H:i:s');
    foreach ($tajMilestones as [$name, $targetDate, $status, $pic]) {
        $tajMilestoneInsert->execute([
            ':project_key' => 'taj',
            ':name' => $name,
            ':target_date' => $targetDate,
            ':status' => $status,
            ':pic' => $pic,
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);
    }
}

$tajRoadmapCount = $pdo->query(
    "SELECT COUNT(*) FROM roadmaps WHERE project_key = 'taj'"
)->fetchColumn();

if ((int) $tajRoadmapCount === 0) {
    $tajRoadmapInsert = $pdo->prepare(
        'INSERT INTO roadmaps (
            project_key, phase, detail, status, sequence, created_at, updated_at
        ) VALUES (
            :project_key, :phase, :detail, :status, :sequence, :created_at, :updated_at
        )'
    );

    $tajRoadmapItems = [
        ['Dokumen pengadaan', '26-28 Agustus 2026: JLI dan partner menyelesaikan dokumen e-Procurement, template onboarding, dan material list component.', 'Selesai'],
        ['Kurasi dan pemilihan', '31 Agustus-9 September 2026: kurasi produk digitalisasi terminal, pencarian produk e-Proc, dan negosiasi.', 'Sedang Berjalan'],
        ['PO ke JLI', '9-10 September 2026: TAJ menandatangani negosiasi dan PT STN menerbitkan PO ke JLI.', 'Sedang Berjalan'],
        ['Pekerjaan dan pengujian', '11 September-4 Desember 2026: JLI bersama partner melakukan pekerjaan, SIT, UAT, dan implementasi Terminal Tanah Merdeka.', 'Belum Dimulai'],
        ['Kesiapan rekening', '11-30 September 2026: pembukaan rekening dan pengurusan penerima AM Card, MID, serta EDC.', 'Sedang Berjalan'],
        ['PTR dan BAST', '4-10 Desember 2026: PTR sampai transaksi bank, BAST pekerjaan, lalu proses invoicing.', 'Belum Dimulai'],
        ['Ketergantungan utama', 'Target implementasi dan BAST 10 Desember 2026 bergantung pada kontrak atau PO TAJ melalui e-Procurement.', 'Menunggu'],
    ];

    $timestamp = date('Y-m-d H:i:s');
    foreach ($tajRoadmapItems as $sequence => [$phase, $detail, $status]) {
        $tajRoadmapInsert->execute([
            ':project_key' => 'taj',
            ':phase' => $phase,
            ':detail' => $detail,
            ':status' => $status,
            ':sequence' => $sequence + 1,
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);
    }
}


/*
|--------------------------------------------------------------------------
| DEMO DATA: TASKS
|--------------------------------------------------------------------------
*/

if ($enableDemoSeed && $taskCount === 0) {
    $taskInsert = $pdo->prepare(
        'INSERT INTO tasks (
            code,
            name,
            workstream,
            pic,
            start_date,
            end_date,
            status,
            progress,
            priority,
            notes,
            created_at,
            updated_at
        ) VALUES (
            :code,
            :name,
            :workstream,
            :pic,
            :start_date,
            :end_date,
            :status,
            :progress,
            :priority,
            :notes,
            :created_at,
            :updated_at
        )'
    );

    $tasks = [
        [
            'TJT-TEC-01',
            'Final requirement, regulasi, dan BRD approval versi 1.4',
            'Bisnis & Nonteknis',
            'DISHUB',
            excelSerialToDateString(46209),
            excelSerialToDateString(46224),
            'Sedang Berjalan',
            50,
            5,
            'Belum tanda tangan stakeholder.',
        ],
        [
            'TJT-TEC-02',
            'Data Fare Matrix TransJakarta (Stop ID, GTFS, rute, dan data halte)',
            'Bisnis & Nonteknis',
            'TransJakarta',
            excelSerialToDateString(46218),
            excelSerialToDateString(46224),
            'Sedang Berjalan',
            80,
            5,
            'Distance Matrix Non BRT dan data bus stop masih perlu finalisasi.',
        ],
        [
            'TJT-TEC-03',
            'Final use-case skenario transaksi positif dan negatif',
            'Bisnis & Nonteknis',
            'DISHUB',
            excelSerialToDateString(46218),
            excelSerialToDateString(46224),
            'Sedang Berjalan',
            90,
            4,
            'FSD masih in progress.',
        ],
        [
            'TJT-TEC-04',
            'Library acceptance Reader Device KUE',
            'Teknis',
            'JLI',
            excelSerialToDateString(46213),
            excelSerialToDateString(46229),
            'Sedang Berjalan',
            71,
            5,
            'MRT dan LRT masih in progress.',
        ],
        [
            'TJT-TEC-05',
            'Pengembangan MaaS QR STT',
            'Teknis',
            'JLI',
            excelSerialToDateString(46213),
            excelSerialToDateString(46234),
            'Sedang Berjalan',
            20,
            4,
            'Masih tahap pengembangan awal.',
        ],
        [
            'TJT-TEC-06',
            'Sinkronisasi PTO dan joint development EIPTJ',
            'Teknis',
            'JLI & PTO',
            excelSerialToDateString(46227),
            excelSerialToDateString(46233),
            'Sedang Berjalan',
            0,
            4,
            'Belum ada penegasan keputusan bersama.',
        ],
        [
            'TJT-TEC-07',
            'SIT pengujian integrasi',
            'Teknis',
            'JLI & PTO',
            excelSerialToDateString(46237),
            excelSerialToDateString(46239),
            'Belum Dimulai',
            0,
            5,
            'Menunggu kesiapan integrasi.',
        ],
        [
            'TJT-BO-01',
            'Kick-off project dan steering committee',
            'Bisnis & Nonteknis',
            'DISHUB',
            excelSerialToDateString(46209),
            excelSerialToDateString(46227),
            'Sedang Berjalan',
            50,
            5,
            'Baru tersedia MOM, belum keputusan formal.',
        ],
        [
            'TJT-BO-02',
            'PMO dan working group lintas instansi',
            'Bisnis & Nonteknis',
            'DISHUB & PTO',
            excelSerialToDateString(46223),
            excelSerialToDateString(46234),
            'Menunggu',
            0,
            5,
            'Working group belum terbentuk.',
        ],
        [
            'TJT-BO-03',
            'Finalisasi kebijakan tarif dan fare matrix',
            'Bisnis & Nonteknis',
            'DISHUB',
            excelSerialToDateString(46223),
            excelSerialToDateString(46227),
            'Sedang Berjalan',
            60,
            5,
            'Masih berbasis MOM dan butuh pengesahan kebijakan.',
        ],
        [
            'TJT-BO-04',
            'Kesepakatan kesiapan operasional PTO',
            'Bisnis & Nonteknis',
            'DISHUB, PTO, JLI',
            excelSerialToDateString(46223),
            excelSerialToDateString(46261),
            'Sedang Berjalan',
            0,
            4,
            'Kesepakatan bisnis proses tarif integrasi belum final.',
        ],
        [
            'TJT-BO-05',
            'Kesepakatan migrasi transaksi TB, NBRT, dan MT',
            'Bisnis & Nonteknis',
            'DISHUB, TJ, JLI',
            excelSerialToDateString(46223),
            excelSerialToDateString(46230),
            'Sedang Berjalan',
            0,
            5,
            'Masih menunggu penegasan implementasi TransJakarta.',
        ],
        [
            'TJT-BO-06',
            'Settlement dan rekonsiliasi Non BRT (TOB/TOM)',
            'Bisnis & Nonteknis',
            'PTO & JLI',
            excelSerialToDateString(46227),
            excelSerialToDateString(46259),
            'Sedang Berjalan',
            0,
            4,
            'Masih menunggu detail MID/TID dan flow settlement.',
        ],
        [
            'TJT-BO-07',
            'Materi sosialisasi internal dan eksternal',
            'Bisnis & Nonteknis',
            'DISHUB, PTO, JLI',
            excelSerialToDateString(46227),
            excelSerialToDateString(46418),
            'Sedang Berjalan',
            20,
            4,
            'Materi masih dalam tahap penyelarasan.',
        ],
        [
            'TJT-BO-08',
            'Update operational procedure dan incident management SOP',
            'Bisnis & Nonteknis',
            'PTO & JLI',
            excelSerialToDateString(46230),
            '2026-11-30',
            'Sedang Berjalan',
            50,
            4,
            'SOP operasi dan incident management sedang diperbarui.',
        ],
    ];

    foreach (
        $tasks as [
            $code,
            $name,
            $workstream,
            $pic,
            $startDate,
            $endDate,
            $status,
            $progress,
            $priority,
            $notes,
        ]
    ) {
        $normalizedStatus = $progress === 100
            ? 'Selesai'
            : $status;

        $timestamp = date('Y-m-d H:i:s');

        $taskInsert->execute([
            ':code' => $code,
            ':name' => $name,
            ':workstream' => $workstream,
            ':pic' => $pic,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':status' => $normalizedStatus,
            ':progress' => $progress,
            ':priority' => $priority,
            ':notes' => $notes,
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);
    }
}


/*
|--------------------------------------------------------------------------
| DEMO DATA: MILESTONES
|--------------------------------------------------------------------------
*/

if ($enableDemoSeed && $milestoneCount === 0) {
    $milestoneInsert = $pdo->prepare(
        'INSERT INTO milestones (
            name,
            target_date,
            status,
            pic,
            created_at,
            updated_at
        ) VALUES (
            :name,
            :target_date,
            :status,
            :pic,
            :created_at,
            :updated_at
        )'
    );

    $milestones = [
        [
            'BRD final dan regulasi disetujui',
            excelSerialToDateString(46224),
            'Dalam Review',
            'DISHUB',
        ],
        [
            'Sinkronisasi joint development PTO selesai',
            excelSerialToDateString(46233),
            'Sedang Berjalan',
            'JLI & PTO',
        ],
        [
            'SIT pengujian integrasi selesai',
            excelSerialToDateString(46239),
            'Belum Dimulai',
            'JLI & PTO',
        ],
        [
            'Deployment tahap 0 (TransBandara dan halte BRT)',
            excelSerialToDateString(46250),
            'Belum Dimulai',
            'TransJakarta',
        ],
        [
            'Go-live tahap awal tarif baru dan tarif integrasi',
            excelSerialToDateString(46260),
            'Belum Dimulai',
            'DISHUB & PTO',
        ],
    ];

    foreach ($milestones as [$name, $targetDate, $status, $pic]) {
        $timestamp = date('Y-m-d H:i:s');

        $milestoneInsert->execute([
            ':name' => $name,
            ':target_date' => $targetDate,
            ':status' => $status,
            ':pic' => $pic,
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);
    }
}


/*
|--------------------------------------------------------------------------
| DEMO DATA: RISKS
|--------------------------------------------------------------------------
*/

if ($enableDemoSeed && $riskCount === 0) {
    $riskInsert = $pdo->prepare(
        'INSERT INTO risks (
            code,
            title,
            category,
            level,
            owner,
            status,
            mitigation,
            created_at,
            updated_at
        ) VALUES (
            :code,
            :title,
            :category,
            :level,
            :owner,
            :status,
            :mitigation,
            :created_at,
            :updated_at
        )'
    );

    $risks = [
        [
            'R-01',
            'Kebijakan penyesuaian tarif belum diperoleh sesuai target waktu',
            'Tata Kelola',
            'Tinggi',
            'DISHUB',
            'Terbuka',
            'Koordinasi intensif dan percepatan persetujuan atau surat penegasan paralel sebelum kebijakan rilis.',
        ],
        [
            'R-02',
            'Belum tercapainya kesepakatan implementasi pada TransJakarta',
            'Tata Kelola',
            'Tinggi',
            'DISHUB, TJ, JLI',
            'Terbuka',
            'Forum koordinasi dan penyelarasan ruang lingkup implementasi khusus TransJakarta.',
        ],
        [
            'R-03',
            'Belum ada kesepakatan migrasi transaksi Bus Non-BRT dan Mikrotrans ke CCHS JakLingko',
            'Kebijakan Operasional',
            'Tinggi',
            'DISHUB, TJ, JLI',
            'Mitigasi Berjalan',
            'Koordinasi intensif untuk keputusan subsidi, tarif integrasi, dan migrasi transaksi.',
        ],
        [
            'R-04',
            'Perubahan kebijakan memengaruhi ruang lingkup proyek selama pelaksanaan',
            'Manajemen Proyek',
            'Sedang',
            'DISHUB, TJ',
            'Dipantau',
            'Seluruh perubahan wajib melalui change control dan persetujuan steering committee.',
        ],
        [
            'R-06',
            'Data Fare Matrix belum lengkap atau tidak sesuai',
            'Teknis',
            'Tinggi',
            'TJ, JLI',
            'Mitigasi Berjalan',
            'Verifikasi dan validasi konfigurasi sebelum implementasi.',
        ],
        [
            'R-07',
            'Gangguan layanan saat go-live menyebabkan pelanggan tidak terkendali',
            'Operasional',
            'Sedang',
            'DISHUB, PTO, JLI',
            'Terbuka',
            'Perkuat sosialisasi mulai H-90 dan siapkan skenario command center go-live.',
        ],
    ];

    foreach (
        $risks as [
            $code,
            $title,
            $category,
            $level,
            $owner,
            $status,
            $mitigation,
        ]
    ) {
        $timestamp = date('Y-m-d H:i:s');

        $riskInsert->execute([
            ':code' => $code,
            ':title' => $title,
            ':category' => $category,
            ':level' => $level,
            ':owner' => $owner,
            ':status' => $status,
            ':mitigation' => $mitigation,
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);
    }
}


/*
|--------------------------------------------------------------------------
| DEMO DATA: ROADMAPS
|--------------------------------------------------------------------------
*/

if ($enableDemoSeed && $roadmapCount === 0) {
    $roadmapInsert = $pdo->prepare(
        'INSERT INTO roadmaps (
            phase,
            detail,
            status,
            sequence,
            created_at,
            updated_at
        ) VALUES (
            :phase,
            :detail,
            :status,
            :sequence,
            :created_at,
            :updated_at
        )'
    );

    $roadmaps = [
        [
            'Perencanaan',
            'Final requirement, BRD approval, dan penyelarasan regulasi sebagai dasar perubahan tarif TransJakarta.',
            'Sedang Berjalan',
            1,
        ],
        [
            'Design & Development',
            'Library acceptance, fare matrix, MaaS QR STT, dan konfigurasi sistem inti implementasi tarif baru.',
            'Sedang Berjalan',
            2,
        ],
        [
            'Testing & Integration',
            'Sinkronisasi PTO, SIT, UAT, dan uji coba terbatas sebelum deployment.',
            'Belum Dimulai',
            3,
        ],
        [
            'Deployment Bertahap',
            'Tahap 0, Tahap I, dan Tahap II untuk BRT, NBRT, Mikrotrans, MRT, dan LRTJ.',
            'Belum Dimulai',
            4,
        ],
        [
            'Operational Readiness',
            'Monitoring operasional, settlement, SOP, dan kesiapan komunikasi publik menjelang implementasi penuh.',
            'Menunggu',
            5,
        ],
    ];

    foreach (
        $roadmaps as [
            $phase,
            $detail,
            $status,
            $sequence,
        ]
    ) {
        $timestamp = date('Y-m-d H:i:s');

        $roadmapInsert->execute([
            ':phase' => $phase,
            ':detail' => $detail,
            ':status' => $status,
            ':sequence' => $sequence,
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);
    }
}


/*
|--------------------------------------------------------------------------
| DEMO DATA: RACI
|--------------------------------------------------------------------------
*/

if ($enableDemoSeed && $raciCount === 0) {
    $raciInsert = $pdo->prepare(
        'INSERT INTO raci_matrix (
            activity,
            responsible,
            accountable,
            consulted,
            informed,
            sequence,
            created_at,
            updated_at
        ) VALUES (
            :activity,
            :responsible,
            :accountable,
            :consulted,
            :informed,
            :sequence,
            :created_at,
            :updated_at
        )'
    );

    $raciRows = [
        [
            'Finalisasi dan persetujuan kebijakan tarif baru',
            'DISHUB Jakarta & TransJakarta',
            'Pemprov Jakarta (Gubernur)',
            'DISHUB BAJ',
            'JLI',
            1,
        ],
        [
            'Koordinasi dan regulasi implementasi tarif',
            'TransJakarta, MRT Jakarta, LRT Jakarta, JLI',
            'DISHUB Jakarta (Kadishub)',
            'DISHUB BAJ & BKA',
            'Pemprov Jakarta',
            2,
        ],
        [
            'Persiapan dan validasi data fare matrix serta konfigurasi sistem',
            'TransJakarta & JLI',
            'JLI',
            'DISHUB',
            'MRT Jakarta & LRT Jakarta',
            3,
        ],
        [
            'Pengembangan sistem dan konfigurasi tarif / produk baru',
            'TransJakarta & JLI',
            'JLI',
            'DISHUB',
            'PTO terkait',
            4,
        ],
        [
            'Sosialisasi dan kesiapan operasional go-live',
            'DISHUB, PTO, JLI',
            'DISHUB Jakarta',
            'TransJakarta',
            'Pemprov Jakarta',
            5,
        ],
    ];

    foreach (
        $raciRows as [
            $activity,
            $responsible,
            $accountable,
            $consulted,
            $informed,
            $sequence,
        ]
    ) {
        $timestamp = date('Y-m-d H:i:s');

        $raciInsert->execute([
            ':activity' => $activity,
            ':responsible' => $responsible,
            ':accountable' => $accountable,
            ':consulted' => $consulted,
            ':informed' => $informed,
            ':sequence' => $sequence,
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);
    }
}


/*
|--------------------------------------------------------------------------
| DEMO DATA: MASTER PIC
|--------------------------------------------------------------------------
*/

if ($enableDemoSeed && $picCount === 0) {
    $picInsert = $pdo->prepare(
        'INSERT INTO pics (
            name,
            person_name,
            role_label,
            created_at,
            updated_at
         ) VALUES (
            :name,
            :person_name,
            :role_label,
            :created_at,
            :updated_at
         )'
    );

    $seedPics = [
        [
            'Admin PMO',
            'Admin PMO',
            'Administrator',
        ],
        [
            'DISHUB',
            null,
            'Regulator / Project Sponsor',
        ],
        [
            'TransJakarta',
            null,
            'Operator Utama',
        ],
        [
            'TJ',
            null,
            'TransJakarta / Operator',
        ],
        [
            'JLI',
            null,
            'JakLingko Indonesia',
        ],
        [
            'PTO',
            null,
            'Operator Transportasi',
        ],
        [
            'JLI & PTO',
            null,
            'Tim Integrasi Bersama',
        ],
        [
            'DISHUB & PTO',
            null,
            'Working Group Lintas Instansi',
        ],
        [
            'DISHUB, TJ, JLI',
            null,
            'Koordinasi Tarif Integrasi',
        ],
        [
            'DISHUB, PTO, JLI',
            null,
            'Steering dan Operasional',
        ],
        [
            'PTO & JLI',
            null,
            'Tim Settlement & Operasional',
        ],
        [
            'DISHUB, PTO',
            null,
            'Koordinasi Operasional',
        ],
    ];

    foreach (
        $seedPics as [
            $name,
            $personName,
            $roleLabel,
        ]
    ) {
        $timestamp = date('Y-m-d H:i:s');

        $picInsert->execute([
            ':name' => $name,
            ':person_name' => $personName,
            ':role_label' => $roleLabel,
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);
    }
}


/*
|--------------------------------------------------------------------------
| DEMO DATA: ISSUE LOG
|--------------------------------------------------------------------------
*/

if ($enableDemoSeed && $issueLogCount === 0) {
    $issueInsert = $pdo->prepare(
        'INSERT INTO issue_logs (
            code,
            category,
            description,
            priority,
            level,
            target_resolve,
            pic,
            status,
            created_at,
            updated_at
        ) VALUES (
            :code,
            :category,
            :description,
            :priority,
            :level,
            :target_resolve,
            :pic,
            :status,
            :created_at,
            :updated_at
        )'
    );

    $issueLogs = [
        [
            '1',
            'Data & Integrasi',
            'Standar koordinat bus stop harus sama antara TransJakarta dan EIPTJ untuk TransJabodetabek versus Non-BRT.',
            'Tinggi',
            'Terbuka',
            excelSerialToDateString(46227),
            'TJ & JLI',
            'Terbuka',
        ],
        [
            '2',
            'Rute & Produk',
            'Konfirmasi rute SH1 apakah diposisikan sebagai layanan TransBandara atau TransJabodetabek.',
            'Tinggi',
            'Terbuka',
            excelSerialToDateString(46230),
            'DISHUB & TJ',
            'Dipantau',
        ],
        [
            '3',
            'Perubahan Operasi',
            'Perubahan rute harus diinformasikan ke EIPTJ sebelum jadwal baru berlaku ke publik.',
            'Sedang',
            'Terbuka',
            excelSerialToDateString(46230),
            'DISHUB, TJ, JLI',
            'Terbuka',
        ],
    ];

    foreach (
        $issueLogs as [
            $code,
            $category,
            $description,
            $priority,
            $level,
            $targetResolve,
            $pic,
            $status,
        ]
    ) {
        $timestamp = date('Y-m-d H:i:s');

        $issueInsert->execute([
            ':code' => $code,
            ':category' => $category,
            ':description' => $description,
            ':priority' => $priority,
            ':level' => $level,
            ':target_resolve' => $targetResolve,
            ':pic' => $pic,
            ':status' => $status,
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);
    }
}


/* Jalankan ulang normalisasi setelah data seed agar tidak ada PIC PTO baru. */
foreach ($legacyPicMappings as $legacyPic => $replacementPic) {
    foreach (['tasks', 'milestones', 'issue_logs'] as $tableName) {
        $statement = $pdo->prepare(
            'UPDATE ' . $tableName . ' SET pic = :replacement WHERE pic = :legacy'
        );
        $statement->execute([
            ':replacement' => $replacementPic,
            ':legacy' => $legacyPic,
        ]);
    }

    $riskStatement = $pdo->prepare(
        'UPDATE risks SET owner = :replacement WHERE owner = :legacy'
    );
    $riskStatement->execute([
        ':replacement' => $replacementPic,
        ':legacy' => $legacyPic,
    ]);
}

// PTO hanya boleh muncul sebagai penjelasan konsep, bukan nama pihak pada data.
$operatorLabel = 'operator transportasi publik (TJ, LRTJ, MRTJ)';
foreach ([
    ['tasks', 'name'],
    ['tasks', 'notes'],
    ['milestones', 'name'],
    ['risks', 'title'],
    ['risks', 'mitigation'],
    ['issue_logs', 'description'],
] as [$tableName, $columnName]) {
    $pdo->prepare(
        'UPDATE ' . $tableName . '
         SET ' . $columnName . ' = REPLACE(' . $columnName . ', \'PTO\', :operator_label)
         WHERE ' . $columnName . ' LIKE \'%PTO%\''
    )->execute([':operator_label' => $operatorLabel]);
}

foreach (array_keys($legacyPicMappings) as $legacyPic) {
    $deleteLegacyPic->execute([':name' => $legacyPic]);
}


/*
|--------------------------------------------------------------------------
| SINKRONISASI MASTER PIC
|--------------------------------------------------------------------------
|
| Setiap PIC yang sudah digunakan pada Task, Milestone, atau Issue Log
| dipastikan tersedia juga di tabel Master PIC.
|
*/

$syncPicInsert = $pdo->prepare(
    'INSERT OR IGNORE INTO pics (
        name,
        person_name,
        role_label,
        created_at,
        updated_at
     ) VALUES (
        :name,
        :person_name,
        :role_label,
        :created_at,
        :updated_at
     )'
);

$picNamesToSync = $pdo->query(
    "SELECT pic AS name FROM tasks
     UNION
     SELECT pic AS name FROM milestones
     UNION
     SELECT pic AS name FROM issue_logs"
)->fetchAll();

foreach ($picNamesToSync as $picRow) {
    $picName = trim(
        (string) ($picRow['name'] ?? '')
    );

    if ($picName === '') {
        continue;
    }

    $timestamp = date('Y-m-d H:i:s');

    $syncPicInsert->execute([
        ':name' => $picName,
        ':person_name' => null,
        ':role_label' => null,
        ':created_at' => $timestamp,
        ':updated_at' => $timestamp,
    ]);
}
