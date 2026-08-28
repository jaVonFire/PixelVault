<?php
/* ============================================================
   Conexion a la base de datos con PDO
   ============================================================ */

function db() {
    static $pdo = null;
    if ($pdo === null) {
        $cfg = require __DIR__ . '/db_config.php';
        $dsn = "mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset={$cfg['charset']}";
        $pdo = new PDO($dsn, $cfg['user'], $cfg['password'], array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ));
    }
    return $pdo;
}
