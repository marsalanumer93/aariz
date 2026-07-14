<?php
declare(strict_types=1);

function loadEnv(string $path): array
{
    $vars = [];
    if (!is_readable($path)) {
        return $vars;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $vars[trim($key)] = trim($value);
    }
    return $vars;
}

$env = loadEnv(__DIR__ . '/.env');

$host = $env['DB_HOST'] ?? '';
$port = $env['DB_PORT'] ?? '3306';
$name = $env['DB_NAME'] ?? '';
$user = $env['DB_USER'] ?? '';
$pass = $env['DB_PASS'] ?? '';

$status = 'unknown';
$message = '';
$startTime = microtime(true);

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    $status = 'connected';
    $message = 'Successfully connected to MySQL ' . $version;
} catch (PDOException $e) {
    $status = 'error';
    $message = $e->getMessage();
}

$elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
$isSuccess = $status === 'connected';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DB Connectivity Test</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 640px; margin: 40px auto; padding: 0 20px; color: #222; }
        h1 { font-size: 1.4rem; }
        .status { display: inline-block; padding: 4px 12px; border-radius: 4px; font-weight: 600; }
        .status.connected { background: #d1fae5; color: #065f46; }
        .status.error { background: #fee2e2; color: #991b1b; }
        table { border-collapse: collapse; margin-top: 20px; width: 100%; }
        td, th { text-align: left; padding: 6px 10px; border-bottom: 1px solid #e5e7eb; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>Database Connectivity Test</h1>
    <p class="status <?= $isSuccess ? 'connected' : 'error' ?>">
        <?= $isSuccess ? 'CONNECTED' : 'CONNECTION FAILED' ?>
    </p>
    <table>
        <tr><th>Host</th><td><?= htmlspecialchars($host) ?></td></tr>
        <tr><th>Port</th><td><?= htmlspecialchars($port) ?></td></tr>
        <tr><th>Database</th><td><?= htmlspecialchars($name) ?></td></tr>
        <tr><th>User</th><td><?= htmlspecialchars($user) ?></td></tr>
        <tr><th>Response time</th><td><?= $elapsedMs ?> ms</td></tr>
        <tr><th>Message</th><td><code><?= htmlspecialchars($message) ?></code></td></tr>
    </table>
</body>
</html>
