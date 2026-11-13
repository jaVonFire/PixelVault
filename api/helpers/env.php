<?php
/* Lee variables del sistema y, para desarrollo local, del archivo .env. */
function pv_load_env_file() {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;

    $envFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($envFile) || !is_readable($envFile)) return;

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) return;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!preg_match('/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $m)) continue;
        $key = $m[1];
        $value = trim($m[2]);
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = substr($value, -1);
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        if (getenv($key) === false) putenv($key . '=' . $value);
        if (!isset($_ENV[$key])) $_ENV[$key] = $value;
    }
}

function pv_env($key, $default = '') {
    pv_load_env_file();
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}
