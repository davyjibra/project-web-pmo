<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SIDEBAR USER
|--------------------------------------------------------------------------
|
| Data user biasanya dikirim dari halaman yang memanggil sidebar.
| Fallback digunakan supaya sidebar tetap aman jika variabel $user
| tidak tersedia.
|
*/

$sidebarUser = $user ?? [
    'name' => 'Admin PMO',
    'role' => 'Administrator',
];

/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
|
| Menu utama tetap berasal dari $navigationItems yang didefinisikan
| di index.php.
|
| Jangan hardcode menu di sidebar supaya sidebar tetap reusable.
|
*/

$sidebarNavigationItems = isset($navigationItems) && is_array($navigationItems)
    ? $navigationItems
    : [];

/*
|--------------------------------------------------------------------------
| ACTIVE MENU
|--------------------------------------------------------------------------
|
| Prioritas:
| 1. $activeNavigationId apabila dikirim dari halaman.
| 2. Parameter ?tab=
| 3. Item pertama.
|
*/

$requestedTab = trim((string) ($_GET['tab'] ?? ''));

$activeSidebarId = isset($activeNavigationId)
    ? (string) $activeNavigationId
    : $requestedTab;

if ($activeSidebarId === '' && $sidebarNavigationItems !== []) {
    $activeSidebarId = (string) ($sidebarNavigationItems[0]['id'] ?? '');
}

?>

<aside
    class="sidebar"
    id="sidebar"
    aria-label="Navigasi PMO"
>

    <!-- ========================================
         BRAND
    ========================================= -->

    <div class="sidebar__brand">

        <div
            class="sidebar__logo"
            aria-hidden="true"
        >
            JL
        </div>

        <div>

            <p class="sidebar__eyebrow">
                Ruang Proyek
            </p>

            <h1 class="sidebar__company">
                Kenaikan Tarif TJ
            </h1>

        </div>

    </div>

    <!-- ========================================
         NAVIGATION
    ========================================= -->

    <nav
        class="sidebar__nav"
        aria-label="Menu utama"
    >

        <?php foreach ($sidebarNavigationItems as $index => $item): ?>

            <?php

            $itemId = (string) ($item['id'] ?? '');
            $itemLabel = (string) ($item['label'] ?? '');
            $itemDescription = (string) ($item['description'] ?? '');

            /*
             * Item pertama menjadi fallback aktif apabila belum ada
             * tab yang dipilih.
             */
            $isActive = $activeSidebarId !== ''
                ? $activeSidebarId === $itemId
                : $index === 0;

            /*
             * Link tetap menggunakan hash supaya app.js dapat
             * mengganti panel tanpa reload halaman.
             */
            $itemHref = '#' . rawurlencode($itemId);

            ?>

            <a
                class="
                    sidebar__link
                    <?= $isActive ? ' is-active' : ''; ?>
                "
                href="<?= htmlspecialchars(
                    $itemHref,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
                data-nav-link
                data-tab-target="<?= htmlspecialchars(
                    $itemId,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
                data-tab-title="<?= htmlspecialchars(
                    $itemLabel,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
                data-tab-description="<?= htmlspecialchars(
                    $itemDescription,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
                <?= $isActive
                    ? 'aria-current="page"'
                    : ''; ?>
            >

                <span
                    class="sidebar__dot"
                    aria-hidden="true"
                ></span>

                <span class="sidebar__link-label">
                    <?= htmlspecialchars(
                        $itemLabel,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </span>

            </a>

        <?php endforeach; ?>

    </nav>

    <!-- ========================================
         USER
    ========================================= -->

    <div class="sidebar__user">

        <div class="sidebar__user-info">

            <p class="sidebar__user-name">
                <?= htmlspecialchars(
                    (string) ($sidebarUser['name'] ?? 'Admin PMO'),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </p>

            <p class="sidebar__user-role">
                <?= htmlspecialchars(
                    (string) ($sidebarUser['role'] ?? 'Administrator'),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </p>

        </div>

        <a
            href="/logout"
            class="sidebar__logout"
        >
            Keluar
        </a>

    </div>

</aside>

<!-- ========================================
     MOBILE BACKDROP
========================================= -->

<div
    class="sidebar-backdrop"
    id="sidebarBackdrop"
    aria-hidden="true"
></div>
