<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PROJECT CONTEXT
|--------------------------------------------------------------------------
| Satu aplikasi memantau beberapa proyek. Konteks proyek disimpan dalam
| sesi supaya tautan CRUD dan ekspor tetap memakai proyek yang sama.
*/

const PMO_PROJECTS = [
    'eiptj' => [
        'label' => 'EIPTJ',
        'short_label' => 'EIPTJ',
        'description' => 'Implementasi sistem tarif integrasi EIPTJ.',
    ],
    'taj' => [
        'label' => 'Terminal Angkutan Jalan',
        'short_label' => 'TAJ',
        'description' => 'Kesiapan implementasi Terminal Angkutan Jalan.',
    ],
];

function getActiveProjectKey(): string
{
    $requestedProject = trim((string) ($_GET['project'] ?? ''));

    if ($requestedProject !== '' && isset(PMO_PROJECTS[$requestedProject])) {
        $_SESSION['active_project_key'] = $requestedProject;
    }

    $activeProject = (string) ($_SESSION['active_project_key'] ?? 'eiptj');

    if (!isset(PMO_PROJECTS[$activeProject])) {
        $activeProject = 'eiptj';
        $_SESSION['active_project_key'] = $activeProject;
    }

    return $activeProject;
}

function getActiveProject(): array
{
    return PMO_PROJECTS[getActiveProjectKey()];
}

function isTajProject(): bool
{
    return getActiveProjectKey() === 'taj';
}

function isAllowedProjectPic(string $pic): bool
{
    if (!isTajProject()) {
        return true;
    }

    $picTokens = preg_split('/[,;&\/]+/', strtoupper($pic)) ?: [];
    $picTokens = array_values(array_filter(array_map('trim', $picTokens)));

    return $picTokens !== []
        && count(array_diff($picTokens, ['DISHUB', 'JLI'])) === 0;
}
