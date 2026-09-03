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

$pdo->exec(
    'CREATE UNIQUE INDEX IF NOT EXISTS idx_tasks_code_unique
     ON tasks (code)'
);

$pdo->exec(
    'CREATE UNIQUE INDEX IF NOT EXISTS idx_risks_code_unique
     ON risks (code)'
);

$pdo->exec(
    'CREATE UNIQUE INDEX IF NOT EXISTS idx_issue_logs_code_unique
     ON issue_logs (code)'
);


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
