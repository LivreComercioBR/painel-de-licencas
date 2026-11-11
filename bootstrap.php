<?php
declare(strict_types=1);

$configPath = '/home1/rona7916/private/rmc-flow-licenses/license-config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    exit('Configuração privada não encontrada. Consulte o README.');
}
$config = require $configPath;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('rmc_flow_license_center');
    session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']), 'samesite' => 'Strict']);
    session_start();
}

function db(): PDO {
    global $config;
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('mysql:host='.$config['db_host'].';dbname='.$config['db_name'].';charset=utf8mb4', $config['db_user'], $config['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
    return $pdo;
}
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function requireCsrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Solicitação inválida.'); } }
function isLogged(): bool { return !empty($_SESSION['rmc_license_admin']); }
function requireLogin(): void { if (!isLogged()) { header('Location: index.php'); exit; } }
function code(): string { return 'RMC-'.strtoupper(bin2hex(random_bytes(3))).'-'.strtoupper(bin2hex(random_bytes(3))).'-'.strtoupper(bin2hex(random_bytes(3))); }
function b64url(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
function signLicense(array $payload): string {
    global $config;
    $private = @file_get_contents($config['private_key_path']);
    if (!$private || !extension_loaded('openssl')) throw new RuntimeException('Chave privada RSA ou extensão OpenSSL indisponível.');
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!openssl_sign($json, $signature, $private, OPENSSL_ALGO_SHA256)) throw new RuntimeException('Não foi possível assinar a licença.');
    return b64url($json).'.'.b64url($signature);
}
function ensureRecoveryEvents(): void {
    db()->exec('CREATE TABLE IF NOT EXISTS license_recovery_events (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        license_id INT UNSIGNED NOT NULL,
        request_nonce VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX license_recovery_events_license_id_index (license_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}
ensureRecoveryEvents();
