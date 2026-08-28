<?php
/* ============================================================
   Controlador: Autenticacion y usuarios
   ============================================================ */

/* ---- Validaciones compartidas de contrasena ---- */
function password_strength_error($password) {
    if (strlen($password) < 8) {
        return 'La contrasena debe tener al menos 8 caracteres';
    }
    if (strlen($password) > 72) {
        return 'La contrasena no puede superar los 72 caracteres';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'La contrasena debe tener al menos una mayuscula';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'La contrasena debe tener al menos una minuscula';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'La contrasena debe tener al menos un numero';
    }
    return null;
}

/* ---- Proteccion contra fuerza bruta por sesion ---- */
/* Limita los intentos de login fallidos: tras 5 fallos consecutivos
   bloquea durante 15 minutos. No toca la base de datos. */
function brute_force_blocked() {
    session_start_secure();
    if (isset($_SESSION['login_attempts'])
        && $_SESSION['login_attempts'] >= 5
        && time() < $_SESSION['login_locked_until']) {
        $mins = (int)ceil(($_SESSION['login_locked_until'] - time()) / 60);
        json_error("Demasiados intentos fallidos. Intenta de nuevo en $mins min.", 429);
    }
}

function brute_force_record_failure() {
    session_start_secure();
    $now = time();
    if (!isset($_SESSION['login_attempts']) || $now >= $_SESSION['login_locked_until']) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_locked_until'] = 0;
    }
    $_SESSION['login_attempts']++;
    if ($_SESSION['login_attempts'] >= 5) {
        $_SESSION['login_locked_until'] = $now + 15 * 60;
    }
}

function brute_force_reset() {
    session_start_secure();
    unset($_SESSION['login_attempts']);
    unset($_SESSION['login_locked_until']);
}

/* ---- Registro de usuarios (rol por defecto: cliente) ---- */
function auth_register() {
    $d = read_json_body();

    $username    = isset($d['username']) ? trim($d['username']) : '';
    $displayname = isset($d['displayname']) ? trim($d['displayname']) : '';
    $email       = isset($d['email']) ? trim($d['email']) : '';
    $password    = isset($d['password']) ? $d['password'] : '';
    $password2   = isset($d['password2']) ? $d['password2'] : '';
    $pais        = isset($d['pais']) ? $d['pais'] : null;
    $edad        = isset($d['edad']) ? $d['edad'] : null;
    $plataforma  = isset($d['plataforma']) ? $d['plataforma'] : null;
    $telefono    = isset($d['telefono']) ? $d['telefono'] : null;
    $direccion   = isset($d['direccion']) ? $d['direccion'] : null;
    $moneda      = isset($d['moneda']) ? $d['moneda'] : 'USD';

    /* ---- Validaciones ---- */
    if ($username === '' || $email === '' || $password === '') {
        json_error('Usuario, email y contrasena son obligatorios');
    }
    if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
        json_error('Usuario invalido: usa 3-30 caracteres (letras, numeros, punto, guion bajo)');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Email no valido');
    }
    if ($password !== $password2) {
        json_error('Las contrasenas no coinciden');
    }
    $errPass = password_strength_error($password);
    if ($errPass) {
        json_error($errPass);
    }

    $pdo = db();

    /* Unicidad de username y email */
    $stmt = $pdo->prepare('SELECT id FROM usuario WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute(array($username, $email));
    if ($stmt->fetch()) {
        json_error('El usuario o email ya estan registrados');
    }

    /* Contrasena nunca en texto plano: hash bcrypt */
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $sql = 'INSERT INTO usuario (username, displayname, email, password_hash, rol,
                                 pais, edad, plataforma, telefono, direccion, moneda)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(
        $username, $displayname, $email, $passwordHash, 'cliente',
        $pais, $edad, $plataforma, $telefono, $direccion, $moneda
    ));

    $id = (int)$pdo->lastInsertId();

    /* Inicia sesion automaticamente */
    session_start_secure();
    $_SESSION['user_id'] = $id;

    $stmt = $pdo->prepare('SELECT id, username, displayname, email, rol, pais, edad, plataforma, telefono, direccion, moneda, creado_el FROM usuario WHERE id = ?');
    $stmt->execute(array($id));
    json_ok(array('user' => $stmt->fetch(), 'message' => 'Cuenta creada correctamente'), 201);
}

/* ---- Inicio de sesion ---- */
function auth_login() {
    $d = read_json_body();
    $id = isset($d['username']) ? trim($d['username']) : '';
    $password = isset($d['password']) ? $d['password'] : '';

    if ($id === '' || $password === '') {
        json_error('Usuario/email y contrasena son obligatorios');
    }

    /* Bloqueo temporal si hubo demasiados intentos fallidos */
    brute_force_blocked();

    $stmt = db()->prepare('SELECT id, username, displayname, email, password_hash, rol, pais, edad,
                                  plataforma, telefono, direccion, moneda, creado_el
                           FROM usuario WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute(array($id, $id));
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        brute_force_record_failure();
        json_error('Credenciales incorrectas', 401);
    }

    brute_force_reset();

    /* Re-hashear la contrasena si el algoritmo cambio en el futuro */
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $up = db()->prepare('UPDATE usuario SET password_hash = ? WHERE id = ?');
        $up->execute(array($newHash, (int)$user['id']));
    }

    session_start_secure();
    $_SESSION['user_id'] = (int)$user['id'];

    unset($user['password_hash']);
    json_ok(array('user' => $user, 'message' => 'Sesion iniciada'));
}

/* ---- Cierre de sesion ---- */
function auth_logout() {
    session_start_secure();
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    json_ok(array('message' => 'Sesion cerrada'));
}

/* Actualizar datos editables del perfil (no los sensibles) */
function auth_update_profile() {
    $u = require_login();
    $d = read_json_body();

    $displayname = isset($d['displayname']) ? trim($d['displayname']) : $u['displayname'];
    $email = isset($d['email']) ? trim($d['email']) : $u['email'];
    $plataforma = isset($d['plataforma']) ? $d['plataforma'] : $u['plataforma'];
    $telefono = isset($d['telefono']) ? $d['telefono'] : $u['telefono'];
    $direccion = isset($d['direccion']) ? $d['direccion'] : $u['direccion'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Email no valido');
    }

    /* Verificar que el email no este en uso por otra cuenta */
    $stmt = db()->prepare('SELECT id FROM usuario WHERE email = ? AND id <> ? LIMIT 1');
    $stmt->execute(array($email, (int)$u['id']));
    if ($stmt->fetch()) {
        json_error('Ese email ya esta en uso por otra cuenta');
    }

    $stmt = db()->prepare('UPDATE usuario SET displayname=?, email=?, plataforma=?, telefono=?, direccion=? WHERE id=?');
    $stmt->execute(array($displayname, $email, $plataforma, $telefono, $direccion, (int)$u['id']));

    $stmt = db()->prepare('SELECT id, username, displayname, email, rol, pais, edad, plataforma, telefono, direccion, moneda, creado_el
                           FROM usuario WHERE id=?');
    $stmt->execute(array((int)$u['id']));
    json_ok(array('user' => $stmt->fetch(), 'message' => 'Perfil actualizado'));
}
