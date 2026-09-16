<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

if (isAuthenticated()) {
    header('Location: /');
    exit;
}

$errorMessage = '';
$username = '';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$demoAccounts = getDemoAccounts();

if ($requestMethod === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (attemptLogin($username, $password)) {
        header('Location: /');
        exit;
    }

    $errorMessage = 'Nama pengguna atau kata sandi tidak sesuai.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Dashboard PMO</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="auth-page">
<main class="auth-layout">
    <section class="auth-card">
        <div class="auth-brand">
            <img class="sidebar__logo" src="/assets/images/jli-logo.png" alt="PT Jakarta Lingko Indonesia">
            <div>
                <p class="sidebar__eyebrow">PT JakLingko Indonesia</p>
                <h1 class="auth-title">Dashboard PMO</h1>
            </div>
        </div>
        <div class="auth-copy">
            <h2>Masuk ke sistem</h2>
            <p>Gunakan akun internal untuk mengakses ringkasan implementasi perubahan tarif TransJakarta.</p>
        </div>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert--error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="post" class="auth-form">
            <label class="form-field">
                <span>Nama Pengguna</span>
                <input type="text" name="username" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>
            <label class="form-field">
                <span>Kata Sandi</span>
                <input type="password" name="password" required>
            </label>
            <button type="submit" class="action-button action-button--primary action-button--block">Masuk</button>
        </form>

        <div class="auth-helper">
            <p><strong>Akun demo</strong></p>
            <?php foreach ($demoAccounts as $account): ?>
                <p><strong><?= htmlspecialchars($account['name'], ENT_QUOTES, 'UTF-8'); ?></strong> - <?= htmlspecialchars($account['role'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p>Username: <code><?= htmlspecialchars($account['username'], ENT_QUOTES, 'UTF-8'); ?></code></p>
                <p>Password: <code><?= htmlspecialchars($account['password'], ENT_QUOTES, 'UTF-8'); ?></code></p>
            <?php endforeach; ?>
        </div>
    </section>
</main>
</body>
</html>
