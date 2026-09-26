<?php
declare(strict_types=1);

/**
 * One-off: replace APP_ENCRYPTION_KEY and re-encrypt everything sealed with it.
 *
 * The key seals stored secrets (ac_seal / ac_open in _partials/accounting.php):
 * platform_config values such as the AI assistant key and the "Draft a fix"
 * GitHub token, and each accounting connection's access/refresh tokens. The
 * old key's value is in public git history (commit 1c065f0), so it's replaced.
 *
 * What it does, all-or-nothing:
 *   1. opens every sealed value with the CURRENT key — if any can't be opened
 *      it stops and changes nothing;
 *   2. makes a new random key and re-seals every value with it;
 *   3. inside one transaction: writes the re-sealed values and the new key
 *      (platform_config.APP_ENCRYPTION_KEY, which the app reads before .env),
 *      re-reads and opens every value with the new key, and only then commits.
 * No secret or key is ever printed. Nothing needs reconnecting.
 *
 * Afterwards: delete any APP_ENCRYPTION_KEY line from the server's .env — the
 * app no longer uses it (the database value wins) and it's the leaked value.
 *
 * Run via web: /rotate_encryption_key.php (super-admin, confirm first).
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); require_run_confirmation(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);
require_once __DIR__ . '/_partials/accounting.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Same format as ac_seal / ac_open, but with an explicit key.
$seal = static function (string $plain, string $key): string {
    $iv  = random_bytes(12);
    $tag = '';
    $ct  = openssl_encrypt($plain, 'aes-256-gcm', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ct === false) throw new RuntimeException('openssl_encrypt failed');
    return 'enc:v1:' . base64_encode($iv . $tag . $ct);
};
$open = static function (string $stored, string $key): ?string {
    if (str_starts_with($stored, 'raw:')) {
        $d = base64_decode(substr($stored, 4), true);
        return $d === false ? null : $d;
    }
    if (!str_starts_with($stored, 'enc:v1:') || $key === '') return null;
    $blob = base64_decode(substr($stored, 7), true);
    if ($blob === false || strlen($blob) < 28) return null;
    $pt = openssl_decrypt(substr($blob, 28), 'aes-256-gcm', hash('sha256', $key, true),
                          OPENSSL_RAW_DATA, substr($blob, 0, 12), substr($blob, 12, 16));
    return $pt === false ? null : $pt;
};
$isSealed = static fn ($v): bool => is_string($v) && (str_starts_with($v, 'enc:v1:') || str_starts_with($v, 'raw:'));

if (!function_exists('openssl_encrypt') || !pc_table_exists()) {
    echo "Can't run: OpenSSL or the platform_config table is missing. Nothing changed.\n";
    exit(1);
}

// Where the current key comes from (for the report only).
$dbKey  = (string) ($pdo->query("SELECT value FROM platform_config WHERE name = 'APP_ENCRYPTION_KEY'")->fetchColumn() ?: '');
$envKey = (string) (env('APP_ENCRYPTION_KEY', '') ?? '');
$oldKey = $dbKey !== '' ? $dbKey : $envKey;
echo "Current key comes from: " . ($dbKey !== '' ? 'the database (platform_config)' : ($envKey !== '' ? 'the server .env' : 'nowhere (secrets stored unencrypted)')) . "\n";

// ---- 1. Collect and open everything ------------------------------------
$items = [];   // [type, idRef, column, plaintext]
$bad   = [];

foreach ($pdo->query('SELECT name, value FROM platform_config') as $r) {
    if ($r['name'] === 'APP_ENCRYPTION_KEY' || !$isSealed($r['value'])) continue;
    $pt = $open((string) $r['value'], $oldKey);
    if ($pt === null) { $bad[] = 'setting ' . $r['name']; continue; }
    $items[] = ['pc', (string) $r['name'], 'value', $pt];
}

$hasConn = true;
try { $pdo->query('SELECT 1 FROM accounting_connections LIMIT 0'); } catch (Throwable $e) { $hasConn = false; }
if ($hasConn) {
    foreach ($pdo->query('SELECT id, access_token, refresh_token FROM accounting_connections') as $r) {
        foreach (['access_token', 'refresh_token'] as $col) {
            if (!$isSealed($r[$col])) continue;
            $pt = $open((string) $r[$col], $oldKey);
            if ($pt === null) { $bad[] = "accounting connection #{$r['id']} {$col}"; continue; }
            $items[] = ['ac', (int) $r['id'], $col, $pt];
        }
    }
}

$nPc = count(array_filter($items, static fn ($i) => $i[0] === 'pc'));
$nAc = count($items) - $nPc;
echo "Found {$nPc} encrypted setting(s) and {$nAc} accounting token(s).\n";

if ($bad) {
    echo "\nSTOPPED — these can't be opened with the current key, so nothing was changed:\n";
    foreach ($bad as $b) echo "  - {$b}\n";
    echo "Re-enter those secrets in the admin screens, then run this again.\n";
    exit(1);
}

// ---- 2 + 3. New key, re-seal, write, verify, commit --------------------
$newKey = bin2hex(random_bytes(32));
$pdo->beginTransaction();
try {
    $upPc = $pdo->prepare('UPDATE platform_config SET value = ? WHERE name = ?');
    foreach ($items as [$type, $ref, $col, $pt]) {
        if ($type === 'pc') {
            $upPc->execute([$seal($pt, $newKey), $ref]);
        } else {
            $pdo->prepare("UPDATE accounting_connections SET {$col} = ? WHERE id = ?")
                ->execute([$seal($pt, $newKey), $ref]);
        }
    }
    $pdo->prepare(
        "INSERT INTO platform_config (name, value) VALUES ('APP_ENCRYPTION_KEY', ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)"
    )->execute([$newKey]);

    // Verify inside the transaction: every value opens with the new key and
    // matches what it was.
    $getPc = $pdo->prepare('SELECT value FROM platform_config WHERE name = ?');
    foreach ($items as [$type, $ref, $col, $pt]) {
        if ($type === 'pc') {
            $getPc->execute([$ref]);
            $stored = (string) $getPc->fetchColumn();
        } else {
            $g = $pdo->prepare("SELECT {$col} FROM accounting_connections WHERE id = ?");
            $g->execute([$ref]);
            $stored = (string) $g->fetchColumn();
        }
        if (!hash_equals($pt, (string) $open($stored, $newKey))) {
            throw new RuntimeException('verification failed for ' . ($type === 'pc' ? "setting {$ref}" : "connection #{$ref} {$col}"));
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "\nFAILED — rolled back, nothing changed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nDone. New key saved in the database; {$nPc} setting(s) and {$nAc} token(s) re-encrypted and verified.\n";
echo $envKey !== ''
    ? "NEXT: delete the APP_ENCRYPTION_KEY line from the server .env (Cloudways) — it's the old, leaked value and is no longer used.\n"
    : "The server .env has no APP_ENCRYPTION_KEY — nothing to tidy up there.\n";
