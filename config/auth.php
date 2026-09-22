<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| ERROR HANDLING
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');


function renderFriendlyErrorPage(): void
{
    if (headers_sent()) {
        return;
    }

    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Terjadi Kendala</title>
        <link rel="stylesheet" href="/assets/css/style.css">
    </head>

    <body class="auth-page">
        <main class="auth-layout">
            <section class="auth-card">
                <div class="auth-copy">
                    <h1 class="auth-title">
                        Terjadi kendala pada sistem
                    </h1>

                    <p>
                        Halaman tidak bisa ditampilkan untuk sementara.
                        Silakan muat ulang atau hubungi admin internal
                        untuk pengecekan log server.
                    </p>
                </div>

                <div class="auth-helper">
                    <p>
                        Detail teknis disembunyikan dari tampilan agar
                        informasi server tetap aman.
                    </p>
                </div>
            </section>
        </main>
    </body>
    </html>
    <?php
}


set_exception_handler(
    static function (Throwable $exception): void {
        error_log($exception->__toString());
        renderFriendlyErrorPage();
    }
);


set_error_handler(
    static function (
        int $severity,
        string $message,
        string $file = '',
        int $line = 0
    ): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException(
            $message,
            0,
            $severity,
            $file,
            $line
        );
    }
);


register_shutdown_function(
    static function (): void {
        $lastError = error_get_last();

        if ($lastError === null) {
            return;
        }

        $fatalTypes = [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_USER_ERROR,
        ];

        if (!in_array($lastError['type'], $fatalTypes, true)) {
            return;
        }

        error_log(
            sprintf(
                'Fatal error [%d]: %s in %s on line %d',
                $lastError['type'],
                $lastError['message'],
                $lastError['file'],
                $lastError['line']
            )
        );

        renderFriendlyErrorPage();
    }
);


/*
|--------------------------------------------------------------------------
| SESSION SECURITY
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    $sessionDirectory = __DIR__ . '/../data/sessions';

    if (!is_dir($sessionDirectory)) {
        @mkdir(
            $sessionDirectory,
            0755,
            true
        );
    }

    if (is_dir($sessionDirectory) && is_writable($sessionDirectory)) {
        session_save_path($sessionDirectory);
    }

    /*
     * Tolak session ID yang dibuat sendiri oleh client
     * jika ID tersebut belum dikenal oleh server.
     */
    ini_set('session.use_strict_mode', '1');

    /*
     * Session hanya menggunakan cookie.
     */
    ini_set('session.use_only_cookies', '1');

    /*
     * Tentukan apakah koneksi sedang menggunakan HTTPS.
     */
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ||
        ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}


if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/project.php';


/*
|--------------------------------------------------------------------------
| LOGIN RATE LIMIT
|--------------------------------------------------------------------------
|
| Maksimal 5 percobaan gagal dalam 15 menit.
| Setelah batas tercapai, kombinasi username + IP
| dikunci selama 15 menit.
|
*/

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_ATTEMPT_WINDOW = 900;
const LOGIN_LOCK_DURATION = 900;


/*
|--------------------------------------------------------------------------
| DEMO USERS
|--------------------------------------------------------------------------
*/

const DEMO_USERS = [
    'adminpmo' => [
        'password' => 'JakLingko2026!',
        'name' => 'Admin PMO',
        'role' => 'Administrator',
        'organization' => 'PMO',
        'view_only' => false,
        'modules' => ['*'],
        'scope_keywords' => ['*'],
    ],

    'viewer' => [
        'password' => 'TarifTJReadOnly!',
        'name' => 'Viewer Pimpinan',
        'role' => 'View Only',
        'organization' => 'Steering Committee',
        'view_only' => true,
        'modules' => [],
        'scope_keywords' => ['*'],
    ],

    'jli' => [
        'password' => 'JLITransjakarta2026!',
        'name' => 'PIC JLI',
        'role' => 'Editor JLI',
        'organization' => 'JLI',
        'view_only' => false,
        'modules' => [
            'tasks',
            'milestones',
            'issues',
            'risks',
        ],
        'scope_keywords' => [
            'JLI',
            'JakLingko',
        ],
    ],

    'tj' => [
        'password' => 'TJTransjakarta2026!',
        'name' => 'PIC TransJakarta',
        'role' => 'Editor TransJakarta',
        'organization' => 'TransJakarta',
        'view_only' => false,
        'modules' => [
            'tasks',
            'milestones',
            'issues',
            'risks',
        ],
        'scope_keywords' => [
            'TransJakarta',
            'TJ',
        ],
    ],

    'dishub' => [
        'password' => 'DishubTarif2026!',
        'name' => 'PIC DISHUB',
        'role' => 'Editor DISHUB',
        'organization' => 'DISHUB',
        'view_only' => false,
        'modules' => [
            'tasks',
            'milestones',
            'issues',
            'risks',
        ],
        'scope_keywords' => [
            'DISHUB',
        ],
    ],

    'lrtj' => [
        'password' => 'LRTJTarif2026!',
        'name' => 'PIC LRT Jakarta',
        'role' => 'Editor LRTJ',
        'organization' => 'LRTJ',
        'view_only' => false,
        'modules' => [
            'tasks',
            'milestones',
            'issues',
            'risks',
        ],
        'scope_keywords' => [
            'LRTJ',
            'LRT Jakarta',
        ],
    ],

    'mrtj' => [
        'password' => 'MRTJTarif2026!',
        'name' => 'PIC MRT Jakarta',
        'role' => 'Editor MRTJ',
        'organization' => 'MRTJ',
        'view_only' => false,
        'modules' => [
            'tasks',
            'milestones',
            'issues',
            'risks',
        ],
        'scope_keywords' => [
            'MRTJ',
            'MRT Jakarta',
        ],
    ],

    'jpai' => [
        'password' => 'JPAITarif2026!',
        'name' => 'PIC JPAI/Jatelindo',
        'role' => 'Editor JPAI/Jatelindo',
        'organization' => 'JPAI/Jatelindo',
        'view_only' => false,
        'modules' => [
            'tasks',
            'milestones',
            'issues',
            'risks',
        ],
        'scope_keywords' => [
            'JPAI',
            'Jatelindo',
        ],
    ],
];


/*
|--------------------------------------------------------------------------
| RATE LIMIT HELPERS
|--------------------------------------------------------------------------
*/

function getLoginRateLimitDirectory(): string
{
    return __DIR__ . '/../data/login-attempts';
}


function getLoginClientIp(): string
{
    /*
     * Sengaja menggunakan REMOTE_ADDR.
     *
     * Jangan langsung percaya X-Forwarded-For karena header tersebut
     * dapat dipalsukan apabila server tidak dikonfigurasi sebagai
     * trusted reverse proxy.
     */
    $ip = trim(
        (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
    );

    return $ip !== ''
        ? $ip
        : 'unknown';
}


function getLoginRateLimitKey(string $username): string
{
    $normalizedUsername = strtolower(
        trim($username)
    );

    return hash(
        'sha256',
        getLoginClientIp() . '|' . $normalizedUsername
    );
}


function getLoginRateLimitFile(string $username): string
{
    return getLoginRateLimitDirectory()
        . '/'
        . getLoginRateLimitKey($username)
        . '.json';
}


function getDefaultLoginAttemptState(): array
{
    return [
        'attempts' => 0,
        'window_started_at' => 0,
        'locked_until' => 0,
    ];
}


function readLoginAttemptState(string $username): array
{
    $directory = getLoginRateLimitDirectory();

    if (!is_dir($directory)) {
        @mkdir(
            $directory,
            0755,
            true
        );
    }

    if (!is_dir($directory)) {
        return getDefaultLoginAttemptState();
    }

    $file = getLoginRateLimitFile($username);

    if (!is_file($file)) {
        return getDefaultLoginAttemptState();
    }

    $contents = @file_get_contents($file);

    if (!is_string($contents) || $contents === '') {
        return getDefaultLoginAttemptState();
    }

    $decoded = json_decode(
        $contents,
        true
    );

    if (!is_array($decoded)) {
        return getDefaultLoginAttemptState();
    }

    return [
        'attempts' => max(
            0,
            (int) ($decoded['attempts'] ?? 0)
        ),

        'window_started_at' => max(
            0,
            (int) ($decoded['window_started_at'] ?? 0)
        ),

        'locked_until' => max(
            0,
            (int) ($decoded['locked_until'] ?? 0)
        ),
    ];
}


function writeLoginAttemptState(
    string $username,
    array $state
): void {
    $directory = getLoginRateLimitDirectory();

    if (!is_dir($directory)) {
        @mkdir(
            $directory,
            0755,
            true
        );
    }

    if (!is_dir($directory) || !is_writable($directory)) {
        /*
         * Jika filesystem tidak writable, gunakan session
         * sebagai fallback agar aplikasi tidak error.
         */
        $_SESSION['login_rate_limit_fallback'][
            getLoginRateLimitKey($username)
        ] = $state;

        return;
    }

    $json = json_encode(
        $state,
        JSON_UNESCAPED_SLASHES
    );

    if (!is_string($json)) {
        return;
    }

    @file_put_contents(
        getLoginRateLimitFile($username),
        $json,
        LOCK_EX
    );
}


function deleteLoginAttemptState(string $username): void
{
    $file = getLoginRateLimitFile($username);

    if (is_file($file)) {
        @unlink($file);
    }

    $key = getLoginRateLimitKey($username);

    if (
        isset(
            $_SESSION['login_rate_limit_fallback'][$key]
        )
    ) {
        unset(
            $_SESSION['login_rate_limit_fallback'][$key]
        );
    }
}


function getLoginAttemptState(string $username): array
{
    $fileState = readLoginAttemptState($username);

    /*
     * Kalau file rate-limit tersedia atau pernah berisi data,
     * gunakan file sebagai sumber utama.
     */
    if (
        $fileState['attempts'] > 0
        || $fileState['locked_until'] > 0
    ) {
        return $fileState;
    }

    /*
     * Fallback ke session apabila filesystem tidak writable.
     */
    $key = getLoginRateLimitKey($username);

    $sessionState =
        $_SESSION['login_rate_limit_fallback'][$key]
        ?? null;

    if (!is_array($sessionState)) {
        return $fileState;
    }

    return [
        'attempts' => max(
            0,
            (int) ($sessionState['attempts'] ?? 0)
        ),

        'window_started_at' => max(
            0,
            (int) ($sessionState['window_started_at'] ?? 0)
        ),

        'locked_until' => max(
            0,
            (int) ($sessionState['locked_until'] ?? 0)
        ),
    ];
}


function getLoginLockRemainingSeconds(
    string $username
): int {
    $state = getLoginAttemptState($username);
    $now = time();

    if ($state['locked_until'] <= $now) {
        return 0;
    }

    return $state['locked_until'] - $now;
}


function isLoginRateLimited(string $username): bool
{
    $state = getLoginAttemptState($username);
    $now = time();

    /*
     * Masih dalam masa lock.
     */
    if ($state['locked_until'] > $now) {
        return true;
    }

    /*
     * Lock sudah kedaluwarsa.
     */
    if ($state['locked_until'] > 0) {
        deleteLoginAttemptState($username);

        return false;
    }

    /*
     * Window percobaan lama sudah kedaluwarsa.
     */
    if (
        $state['window_started_at'] > 0
        && ($now - $state['window_started_at']) > LOGIN_ATTEMPT_WINDOW
    ) {
        deleteLoginAttemptState($username);
    }

    return false;
}


function recordFailedLogin(string $username): void
{
    $state = getLoginAttemptState($username);
    $now = time();

    /*
     * Window baru.
     */
    if (
        $state['window_started_at'] === 0
        ||
        ($now - $state['window_started_at']) > LOGIN_ATTEMPT_WINDOW
    ) {
        $state = [
            'attempts' => 0,
            'window_started_at' => $now,
            'locked_until' => 0,
        ];
    }

    $state['attempts']++;

    /*
     * Sudah mencapai batas.
     */
    if ($state['attempts'] >= LOGIN_MAX_ATTEMPTS) {
        $state['locked_until'] =
            $now + LOGIN_LOCK_DURATION;
    }

    writeLoginAttemptState(
        $username,
        $state
    );
}


function clearFailedLoginAttempts(string $username): void
{
    deleteLoginAttemptState($username);
}


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

function isAuthenticated(): bool
{
    return isset($_SESSION['user'])
        && is_array($_SESSION['user']);
}


function requireAuthentication(): void
{
    $username = strtolower(trim((string) ($_SESSION['user']['username'] ?? '')));

    if (!isAuthenticated() || !isset(DEMO_USERS[$username])) {
        unset($_SESSION['user']);
        header('Location: /login');
        exit;
    }
}


function attemptLogin(
    string $username,
    string $password
): bool {
    $normalizedUsername = strtolower(
        trim($username)
    );

    /*
     * Tolak lebih awal jika username + IP sedang terkunci.
     */
    if (isLoginRateLimited($normalizedUsername)) {
        return false;
    }

    $account =
        DEMO_USERS[$normalizedUsername]
        ?? null;

    $isValidAccount = is_array($account);

    /*
     * Gunakan dummy password jika username tidak dikenal agar
     * alur perbandingan tidak terlalu berbeda.
     */
    $expectedPassword = $isValidAccount
        ? (string) $account['password']
        : '__invalid_account_password__';

    $passwordMatches = hash_equals(
        $expectedPassword,
        $password
    );

    if (!$isValidAccount || !$passwordMatches) {
        recordFailedLogin(
            $normalizedUsername
        );

        return false;
    }

    /*
     * Login berhasil.
     * Hapus histori percobaan gagal.
     */
    clearFailedLoginAttempts(
        $normalizedUsername
    );

    /*
     * Regenerasi session ID untuk mencegah session fixation.
     */
    session_regenerate_id(true);

    /*
     * Token CSRF lama tidak digunakan setelah login baru.
     */
    unset($_SESSION['csrf_token']);

    $_SESSION['user'] = [
        'name' => (string) $account['name'],
        'role' => (string) $account['role'],
        'username' => $normalizedUsername,
        'organization' => (string) $account['organization'],
        'view_only' => (bool) $account['view_only'],
        'modules' => is_array($account['modules'])
            ? $account['modules']
            : [],
        'scope_keywords' => is_array($account['scope_keywords'])
            ? $account['scope_keywords']
            : [],
        'login_at' => date('Y-m-d H:i:s'),
    ];

    return true;
}


/*
|--------------------------------------------------------------------------
| AUTHENTICATED USER
|--------------------------------------------------------------------------
*/

function getAuthenticatedUser(): array
{
    return $_SESSION['user'] ?? [
        'name' => 'Admin PMO',
        'role' => 'Administrator',
        'username' => 'adminpmo',
        'organization' => 'PMO',
        'view_only' => false,
        'modules' => ['*'],
        'scope_keywords' => ['*'],
        'login_at' => date('Y-m-d H:i:s'),
    ];
}


/*
|--------------------------------------------------------------------------
| DEMO ACCOUNT LIST
|--------------------------------------------------------------------------
*/

function getDemoAccounts(): array
{
    return array_map(
        static function (
            string $username,
            array $account
        ): array {
            return [
                'username' => $username,
                'password' => (string) $account['password'],
                'name' => (string) $account['name'],
                'role' => (string) $account['role'],
                'organization' => (string) $account['organization'],
                'view_only' => (bool) $account['view_only'],
            ];
        },
        array_keys(DEMO_USERS),
        array_values(DEMO_USERS)
    );
}


/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
*/

function isViewOnlyUser(): bool
{
    $user = getAuthenticatedUser();

    return (bool) (
        $user['view_only']
        ?? false
    );
}


function canManageModule(string $module): bool
{
    $user = getAuthenticatedUser();

    $modules = is_array(
        $user['modules']
        ?? null
    )
        ? $user['modules']
        : [];

    return !isViewOnlyUser()
        && (
            in_array('*', $modules, true)
            ||
            in_array($module, $modules, true)
        );
}


function userCanAccessValue(string $value): bool
{
    $user = getAuthenticatedUser();

    $keywords = is_array(
        $user['scope_keywords']
        ?? null
    )
        ? $user['scope_keywords']
        : ['*'];

    if (in_array('*', $keywords, true)) {
        return true;
    }

    $normalizedValue = strtolower(trim($value));
    $scopeTokens = preg_split('/[,;&.\/]+/', $normalizedValue) ?: [];
    $scopeTokens = array_values(array_filter(array_map(
        static fn (string $token): string => trim($token),
        $scopeTokens
    )));

    foreach ($keywords as $keyword) {
        $normalizedKeyword = strtolower(trim((string) $keyword));

        if ($normalizedKeyword === '') {
            continue;
        }

        if (
            $normalizedValue === $normalizedKeyword
            || in_array($normalizedKeyword, $scopeTokens, true)
        ) {
            return true;
        }
    }

    return false;
}


function canManageScopedRecord(
    string $module,
    string $scopeValue
): bool {
    return canManageModule($module)
        && userCanAccessValue($scopeValue);
}


function requireModulePermission(
    string $module
): void {
    if (!canManageModule($module)) {
        setFlashMessage(
            'error',
            'Akun Anda tidak memiliki izin untuk mengubah data pada modul ini.'
        );

        header('Location: /');
        exit;
    }
}


function requireScopedModulePermission(
    string $module,
    string $scopeValue
): void {
    if (
        !canManageScopedRecord(
            $module,
            $scopeValue
        )
    ) {
        setFlashMessage(
            'error',
            'Akun Anda tidak memiliki izin untuk mengubah data ini.'
        );

        header('Location: /');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

function getCsrfToken(): string
{
    if (
        !isset($_SESSION['csrf_token'])
        ||
        !is_string($_SESSION['csrf_token'])
    ) {
        $_SESSION['csrf_token'] =
            bin2hex(
                random_bytes(32)
            );
    }

    return $_SESSION['csrf_token'];
}


function verifyCsrfToken(
    ?string $token
): bool {
    return is_string($token)
        &&
        isset($_SESSION['csrf_token'])
        &&
        hash_equals(
            $_SESSION['csrf_token'],
            $token
        );
}


/*
|--------------------------------------------------------------------------
| FLASH MESSAGE
|--------------------------------------------------------------------------
*/

function setFlashMessage(
    string $type,
    string $message
): void {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message,
    ];
}


function getFlashMessage(): ?array
{
    if (
        !isset($_SESSION['flash_message'])
        ||
        !is_array($_SESSION['flash_message'])
    ) {
        return null;
    }

    $flashMessage =
        $_SESSION['flash_message'];

    unset(
        $_SESSION['flash_message']
    );

    return $flashMessage;
}


/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params =
            session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
