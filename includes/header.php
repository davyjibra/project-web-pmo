<?php

declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard PMO', ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="<?= htmlspecialchars($bodyClass ?? '', ENT_QUOTES, 'UTF-8'); ?>">
<div class="layout-shell">
