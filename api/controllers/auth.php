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

/* ============================================================
   Validacion de email REAL (sin enviar ningun codigo al correo)
   Capas aplicadas:
     1. Formato correcto.
     2. Dominio con servidor de correo (registros MX / A por DNS).
     3. Bloqueo de dominios desechables/temporales.
     4. Deteccion de typos frecuentes (gmial.com, gmail.con...).
     5. Sondeo SMTP best-effort: se pregunta al servidor destino
        si el buzon existe (RCPT TO). Si la red no permite la
        conexion, el resultado queda "inconcluso" y no se bloquea.
   Devuelve null si el email es aceptable, o un mensaje de error.
   ============================================================ */
function email_domain_of($email) {
    $at = strrpos($email, '@');
    return $at === false ? '' : strtolower(substr($email, $at + 1));
}

function email_disposable_domains() {
    return array(
        'mailinator.com','guerrillamail.com','10minutemail.com','tempmail.com','temp-mail.org',
        'throwawaymail.com','yopmail.com','trashmail.com','getnada.com','sharklasers.com',
        'guerrillamailblock.com','grr.la','dispostable.com','maildrop.cc','fakeinbox.com',
        'mailnesia.com','mintemail.com','mytemp.email','tempinbox.com','spamgourmet.com',
        'discard.email','emailondeck.com','moakt.com','tempr.email','33mail.com','trbvm.com'
    );
}

/* Local-parts claramente inventados (antes de la @): test, asdf, qwerty...
   Ayuda a frenar correos "de relleno" que caen en dominios reales con catch-all. */
function email_fake_localparts() {
    return array(
        'test','testing','test1','prueba','pruebas','asdf','asdfasdf','asd','asdfg','qwe','qwer',
        'qwerty','qwertyuiop','aaa','aaaa','abc','abcd','abcde','xxx','xxxx','demo','fake','falso',
        'noemail','nocorreo','correo','email','ejemplo','example','nombre','usuario','user','null',
        'none','ninguno','123','1234','12345','123456','111','1111','000','0000'
    );
}

function email_typo_domains() {
    return array(
        'gmial.com' => 'gmail.com', 'gmai.com' => 'gmail.com', 'gmil.com' => 'gmail.com',
        'gmail.co' => 'gmail.com', 'gmail.con' => 'gmail.com', 'gmail.cm' => 'gmail.com',
        'gmaill.com' => 'gmail.com', 'gnail.com' => 'gmail.com', 'gmal.com' => 'gmail.com',
        'hotmial.com' => 'hotmail.com', 'hotmai.com' => 'hotmail.com', 'hotmail.con' => 'hotmail.com',
        'hotmal.com' => 'hotmail.com', 'homail.com' => 'hotmail.com', 'hotamil.com' => 'hotmail.com',
        'outlok.com' => 'outlook.com', 'outloo.com' => 'outlook.com', 'outlook.con' => 'outlook.com',
        'yahoo.con' => 'yahoo.com', 'yahho.com' => 'yahoo.com', 'yaho.com' => 'yahoo.com'
    );
}

function email_has_mx($domain) {
    if (function_exists('checkdnsrr') && @checkdnsrr($domain, 'MX')) {
        return true;
    }
    if (function_exists('getmxrr')) {
        $mx = array();
        if (@getmxrr($domain, $mx) && count($mx) > 0) {
            return true;
        }
    }
    if (function_exists('dns_get_record')) {
        $rec = @dns_get_record($domain, DNS_MX);
        if (is_array($rec) && count($rec) > 0) {
            return true;
        }
        /* Algunos servidores reciben correo sin MX explicito: aceptar A */
        $recA = @dns_get_record($domain, DNS_A);
        if (is_array($recA) && count($recA) > 0) {
            return true;
        }
    }
    return false;
}

/* Hosts de correo (MX) del dominio, ordenados por prioridad */
function email_mx_hosts($domain) {
    $hosts = array();
    if (function_exists('dns_get_record')) {
        $rec = @dns_get_record($domain, DNS_MX);
        if (is_array($rec) && count($rec) > 0) {
            usort($rec, function ($a, $b) {
                return (isset($a['pri']) ? $a['pri'] : 0) <=> (isset($b['pri']) ? $b['pri'] : 0);
            });
            foreach ($rec as $r) {
                if (!empty($r['target'])) $hosts[] = $r['target'];
            }
        }
    }
    if (!$hosts && function_exists('getmxrr')) {
        $mx = array();
        if (@getmxrr($domain, $mx) && count($mx) > 0) {
            $hosts = $mx;
        }
    }
    return $hosts;
}

/* Sondeo SMTP best-effort: true = el buzon existe, false = no existe,
   null = no se pudo determinar (red que bloquea el puerto 25, etc.). */
function email_smtp_probe($email) {
    $hosts = email_mx_hosts(email_domain_of($email));
    if (!$hosts) {
        return null;
    }
    $intentos = 0;
    foreach ($hosts as $host) {
        if ($intentos >= 2) break;
        $intentos++;

        $fp = @fsockopen($host, 25, $errno, $errstr, 4);
        if (!$fp) continue;
        stream_set_timeout($fp, 4);

        $leer = function () use ($fp) {
            $data = '';
            while (($line = fgets($fp, 1024)) !== false) {
                $data .= $line;
                if (strlen($line) < 4 || $line[3] !== '-') break;
            }
            return $data;
        };

        $banner = $leer();
        if (strpos($banner, '220') === false) { fclose($fp); continue; }

        fwrite($fp, "HELO pixelvault.gg\r\n"); $leer();
        fwrite($fp, "MAIL FROM:<no-reply@pixelvault.gg>\r\n"); $r1 = $leer();
        if (strlen(ltrim($r1)) < 1 || ltrim($r1)[0] !== '2') { fclose($fp); continue; }

        fwrite($fp, "RCPT TO:<" . $email . ">\r\n"); $r2 = $leer();
        fwrite($fp, "QUIT\r\n");
        fclose($fp);

        $code = (int)substr(ltrim($r2), 0, 3);
        if ($code >= 200 && $code < 300) return true;   /* buzon valido */
        if ($code >= 500) return false;                 /* buzon inexistente */
        return null;                                    /* grisaceo / indeterminado */
    }
    return null;
}

function validate_email_real($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Email no valido';
    }
    $domain = email_domain_of($email);
    if ($domain === '' || strpos($domain, '.') === false || strpos($domain, '..') !== false) {
        return 'El dominio del email no es valido';
    }

    $localPart = strtolower(substr($email, 0, strpos($email, '@')));
    if (in_array($localPart, email_fake_localparts(), true)) {
        return 'Ese email parece inventado. Usa tu correo personal real';
    }

    $typos = email_typo_domains();
    if (isset($typos[$domain])) {
        return 'El dominio "' . $domain . '" no existe. Quisiste decir "' . $typos[$domain] . '"?';
    }

    if (in_array($domain, email_disposable_domains(), true)) {
        return 'No se permiten correos temporales o desechables';
    }

    if (!email_has_mx($domain)) {
        return 'El dominio del email no existe o no puede recibir correo';
    }

    $probe = email_smtp_probe($email);
    if ($probe === false) {
        return 'Ese buzon de correo no existe. Verifica tu email';
    }
    return null;
}

/* ---- Registro de usuarios (rol por defecto: cliente) ---- */
function auth_register() {
    $d = read_json_body();

    $username    = isset($d['username']) ? trim($d['username']) : '';
    $displayname = isset($d['displayname']) ? trim($d['displayname']) : '';
    $email       = isset($d['email']) ? trim($d['email']) : '';
    $password    = isset($d['password']) ? $d['password'] : '';
    $password2   = isset($d['password2']) ? $d['password2'] : '';
    $pais          = isset($d['pais']) ? $d['pais'] : null;
    $edad          = isset($d['edad']) ? $d['edad'] : null;
    $plataforma    = isset($d['plataforma']) ? $d['plataforma'] : null;
    $juegoFavorito = isset($d['juego_favorito']) ? trim($d['juego_favorito']) : null;
    $telefono      = isset($d['telefono']) ? $d['telefono'] : null;
    $direccion     = isset($d['direccion']) ? $d['direccion'] : null;
    $moneda        = isset($d['moneda']) ? $d['moneda'] : 'USD';
    $metodoPago    = isset($d['metodo_pago']) ? $d['metodo_pago'] : null;

    /* ---- Validaciones ---- */
    if ($username === '' || $email === '' || $password === '') {
        json_error('Usuario, email y contrasena son obligatorios');
    }
    if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
        json_error('Usuario invalido: usa 3-30 caracteres (letras, numeros, punto, guion bajo)');
    }
    if (($emailErr = validate_email_real($email)) !== null) {
        json_error($emailErr);
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
                                 pais, edad, plataforma, juego_favorito, telefono,
                                 direccion, moneda, metodo_pago)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(
        $username, $displayname, $email, $passwordHash, 'cliente',
        $pais, $edad, $plataforma, $juegoFavorito, $telefono, $direccion, $moneda, $metodoPago
    ));

    $id = (int)$pdo->lastInsertId();

    /* Inicia sesion automaticamente */
    session_start_secure();
    $_SESSION['user_id'] = $id;

    $stmt = $pdo->prepare('SELECT id, username, displayname, email, rol, pais, edad, plataforma, juego_favorito, telefono, direccion, moneda, metodo_pago, creado_el FROM usuario WHERE id = ?');
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
                                  plataforma, juego_favorito, telefono, direccion, moneda,
                                  metodo_pago, creado_el
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

/* Actualizar TODOS los datos del perfil del cliente (menos el admin).
   Incluye: usuario, nombre visible, juego favorito, email, pais, edad,
   plataforma, telefono, direccion, moneda y metodo de pago.
   La contrasena se cambia aparte (auth_change_password). */
function auth_update_profile() {
    $u = require_login();
    if ($u['rol'] === 'admin') {
        json_error('La cuenta de administrador no edita sus datos desde el perfil', 403);
    }
    $d = read_json_body();

    $username      = isset($d['username']) ? trim($d['username']) : $u['username'];
    $displayname   = isset($d['displayname']) ? trim($d['displayname']) : $u['displayname'];
    $juegoFavorito = isset($d['juego_favorito']) ? trim($d['juego_favorito']) : $u['juego_favorito'];
    $email         = isset($d['email']) ? trim($d['email']) : $u['email'];
    $pais          = isset($d['pais']) ? $d['pais'] : $u['pais'];
    $edad          = isset($d['edad']) ? $d['edad'] : $u['edad'];
    $plataforma    = isset($d['plataforma']) ? $d['plataforma'] : $u['plataforma'];
    $telefono      = isset($d['telefono']) ? $d['telefono'] : $u['telefono'];
    $direccion     = isset($d['direccion']) ? $d['direccion'] : $u['direccion'];
    $moneda        = isset($d['moneda']) ? strtoupper(trim($d['moneda'])) : $u['moneda'];
    $metodoPago    = isset($d['metodo_pago']) ? $d['metodo_pago'] : $u['metodo_pago'];

    if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
        json_error('Usuario invalido: usa 3-30 caracteres (letras, numeros, punto, guion bajo)');
    }
    if ($moneda === '' || !preg_match('/^[A-Z]{3}$/', $moneda)) {
        $moneda = $u['moneda'];
    }
    $pagosValidos = array('', 'Visa', 'Mastercard', 'PayPal', 'Transferencia', 'Efectivo');
    if ($metodoPago !== null && !in_array($metodoPago, $pagosValidos, true)) {
        $metodoPago = $u['metodo_pago'];
    }

    /* Validacion de email real solo si el correo cambio */
    if (strcasecmp($email, (string)$u['email']) !== 0) {
        if (($emailErr = validate_email_real($email)) !== null) {
            json_error($emailErr);
        }
    }

    /* Unicidad de usuario y email respecto a las demas cuentas */
    $stmt = db()->prepare('SELECT id FROM usuario WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1');
    $stmt->execute(array($username, $email, (int)$u['id']));
    if ($stmt->fetch()) {
        json_error('Ese usuario o email ya esta en uso por otra cuenta');
    }

    $stmt = db()->prepare('UPDATE usuario
                           SET username=?, displayname=?, juego_favorito=?, email=?,
                               pais=?, edad=?, plataforma=?, telefono=?, direccion=?,
                               moneda=?, metodo_pago=?
                           WHERE id=?');
    $stmt->execute(array(
        $username, $displayname, $juegoFavorito, $email,
        $pais, $edad, $plataforma, $telefono, $direccion,
        $moneda, $metodoPago, (int)$u['id']
    ));

    $stmt = db()->prepare('SELECT id, username, displayname, email, rol, pais, edad, plataforma,
                                  juego_favorito, telefono, direccion, moneda, metodo_pago, creado_el
                           FROM usuario WHERE id=?');
    $stmt->execute(array((int)$u['id']));
    json_ok(array('user' => $stmt->fetch(), 'message' => 'Datos actualizados correctamente'));
}

/* Cambiar la contrasena del cliente logueado (el admin no).
   Requiere la contrasena actual por seguridad. */
function auth_change_password() {
    $u = require_login();
    if ($u['rol'] === 'admin') {
        json_error('La cuenta de administrador no cambia su contrasena desde el perfil', 403);
    }

    $d = read_json_body();
    $actual = isset($d['current_password']) ? $d['current_password'] : '';
    $nueva  = isset($d['new_password']) ? $d['new_password'] : '';
    $nueva2 = isset($d['password2']) ? $d['password2'] : '';

    if ($actual === '' || $nueva === '') {
        json_error('Completa la contrasena actual y la nueva');
    }
    if ($nueva !== $nueva2) {
        json_error('Las contrasenas nuevas no coinciden');
    }
    $errPass = password_strength_error($nueva);
    if ($errPass) {
        json_error($errPass);
    }

    $stmt = db()->prepare('SELECT password_hash FROM usuario WHERE id=?');
    $stmt->execute(array((int)$u['id']));
    $row = $stmt->fetch();
    if (!$row || !password_verify($actual, $row['password_hash'])) {
        json_error('La contrasena actual no es correcta', 401);
    }
    if (password_verify($nueva, $row['password_hash'])) {
        json_error('La nueva contrasena debe ser distinta a la actual');
    }

    $hash = password_hash($nueva, PASSWORD_DEFAULT);
    $up = db()->prepare('UPDATE usuario SET password_hash=? WHERE id=?');
    $up->execute(array($hash, (int)$u['id']));

    json_ok(array('message' => 'Contrasena actualizada correctamente'));
}
