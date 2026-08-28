<?php
/* ============================================================
   Autenticacion por sesion PHP
   - inicio de sesion del lado del servidor
   - funciones para obtener el usuario actual y verificar rol
   ============================================================ */
require_once __DIR__ . '/../config/db.php';

function session_start_secure() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(array(
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ));
        session_start();
    }
}

/* Devuelve el usuario logueado (array) o null */
function current_user() {
    session_start_secure();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, username, displayname, email, rol, pais, edad,
                                  plataforma, telefono, direccion, moneda, creado_el
                           FROM usuario WHERE id = ?');
    $stmt->execute(array($_SESSION['user_id']));
    $user = $stmt->fetch();
    return $user ? $user : null;
}

/* Devuelve el id del usuario logueado o null */
function current_user_id() {
    $u = current_user();
    return $u ? (int)$u['id'] : null;
}

/* Obliga a estar logueado. Si no, error 401. */
function require_login() {
    $u = current_user();
    if (!$u) {
        json_error('Debes iniciar sesion', 401);
    }
    return $u;
}

/* Obliga al rol admin. Si no, error 403. */
function require_admin() {
    $u = require_login();
    if ($u['rol'] !== 'admin') {
        json_error('No tienes permisos de administrador', 403);
    }
    return $u;
}

/* Permite al propio usuario o a un admin. Para consultas privadas. */
function require_owner_or_admin($userId) {
    $u = require_login();
    if ($u['rol'] === 'admin' || (int)$u['id'] === (int)$userId) {
        return $u;
    }
    json_error('No tienes permisos para esta accion', 403);
}
