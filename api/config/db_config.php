<?php
/* ============================================================
   Configuracion de la base de datos (MySQL/MariaDB)
   Usuario/contrase�a por defecto de XAMPP:
   usuario: root  |  sin contrasena
   ============================================================ */
require_once __DIR__ . '/../helpers/env.php';

return array(
    'host'     => pv_env('PV_DB_HOST', '127.0.0.1'),
    'dbname'   => pv_env('PV_DB_NAME', 'pixelvault'),
    'user'     => pv_env('PV_DB_USER', 'root'),
    'password' => pv_env('PV_DB_PASSWORD', ''),
    'charset'  => 'utf8mb4'
);
