<?php
/* Servicios compartidos por el worker nativo de automatizaciones. */
require_once __DIR__ . '/../config/automation_config.php';

function automation_cfg() {
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/../config/automation_config.php';
        if (!filter_var($cfg['admin_email'], FILTER_VALIDATE_EMAIL)) {
            try {
                $email = db()->query("SELECT email FROM usuario WHERE rol='admin' AND email<>'' ORDER BY id ASC LIMIT 1")->fetchColumn();
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) $cfg['admin_email'] = $email;
            } catch (Throwable $e) {
                /* El correo explícito de .env sigue siendo válido aunque la BD no esté disponible. */
            }
            if (!filter_var($cfg['admin_email'], FILTER_VALIDATE_EMAIL)) {
                $cfg['admin_email'] = 'javierandresn12@gmail.com';
            }
        }
    }
    return $cfg;
}

function automation_mail_is_configured($cfg = null) {
    if ($cfg === null) $cfg = automation_cfg();
    return filter_var($cfg['mail_from'], FILTER_VALIDATE_EMAIL)
        && filter_var($cfg['smtp_username'], FILTER_VALIDATE_EMAIL)
        && $cfg['smtp_host'] !== ''
        && $cfg['smtp_port'] > 0
        && in_array($cfg['smtp_security'], array('ssl', 'starttls'), true)
        && $cfg['smtp_password'] !== '';
}

function automation_json($value) {
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    return $json === false ? '{}' : $json;
}

function automation_run_start($flow, $input = array()) {
    $stmt = db()->prepare('INSERT INTO automatizacion_ejecucion (flujo, estado, entrada, iniciado_el) VALUES (?, \'ejecutando\', ?, NOW())');
    $stmt->execute(array($flow, automation_json($input)));
    return (int)db()->lastInsertId();
}

function automation_run_finish($id, $status, $summary = array(), $error = null) {
    if ($status === 'sin_cambios') {
        $stmt = db()->prepare('DELETE FROM automatizacion_ejecucion WHERE id=?');
        $stmt->execute(array((int)$id));
        return;
    }
    $stmt = db()->prepare('UPDATE automatizacion_ejecucion
                           SET estado=?, salida=?, error=?, finalizado_el=NOW()
                           WHERE id=?');
    $stmt->execute(array($status, automation_json($summary), $error, (int)$id));
}

function automation_queue_email($flow, $dedupeKey, $recipient, $subject, $html, $referenceId = null) {
    $recipient = trim((string)$recipient);
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) return false;

    $stmt = db()->prepare('INSERT IGNORE INTO automatizacion_correo
        (flujo, clave_unica, referencia_id, destinatario, asunto, html, estado, intentos, disponible_el)
        VALUES (?, ?, ?, ?, ?, ?, \'pendiente\', 0, NOW())');
    $stmt->execute(array(
        $flow,
        $dedupeKey,
        $referenceId === null ? null : (int)$referenceId,
        $recipient,
        automation_header_value($subject),
        $html
    ));
    return $stmt->rowCount() > 0;
}

function automation_header_value($value) {
    return trim(str_replace(array("\r", "\n"), '', (string)$value));
}

function automation_html($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function automation_send_queued_emails($limit = 10) {
    $cfg = automation_cfg();
    if (!$cfg['mail_enabled']) return array('enviados' => 0, 'reintentos' => 0, 'deshabilitado' => true);
    if (!automation_mail_is_configured($cfg)) {
        return array('enviados' => 0, 'reintentos' => 0, 'configuracion_faltante' => true);
    }

    $pdo = db();
    /* Reanuda mensajes que una versión anterior pudo cerrar por falta de credenciales. */
    $pdo->exec("UPDATE automatizacion_correo
                SET estado='pendiente', disponible_el=NOW(), ultimo_error=NULL
                WHERE estado='fallido' AND ultimo_error LIKE 'CONFIG:%'");
    /* Libera trabajos que quedaron en estado enviando si el proceso se cerró. */
    $pdo->exec("UPDATE automatizacion_correo
                SET estado='pendiente', disponible_el=NOW(), ultimo_error='El worker se interrumpio durante el envio'
                WHERE estado='enviando' AND actualizado_el < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");

    $stmt = $pdo->prepare("SELECT id, flujo, referencia_id, destinatario, asunto, html, intentos
                           FROM automatizacion_correo
                           WHERE estado='pendiente' AND disponible_el <= NOW()
                           ORDER BY id ASC LIMIT ?");
    $stmt->bindValue(1, max(1, min(50, (int)$limit)), PDO::PARAM_INT);
    $stmt->execute();
    $jobs = $stmt->fetchAll();
    $sent = 0;
    $retried = 0;

    foreach ($jobs as $job) {
        $claim = $pdo->prepare("UPDATE automatizacion_correo SET estado='enviando', intentos=intentos+1 WHERE id=? AND estado='pendiente'");
        $claim->execute(array((int)$job['id']));
        if ($claim->rowCount() !== 1) continue;
        $attempt = (int)$job['intentos'] + 1;
        $failure = '';
        $ok = automation_smtp_send($cfg, $job, $failure);

        if ($ok) {
            $pdo->prepare("UPDATE automatizacion_correo SET estado='enviado', enviado_el=NOW(), ultimo_error=NULL WHERE id=?")
                ->execute(array((int)$job['id']));
            if ($job['flujo'] === 'poscompra_resena' && !empty($job['referencia_id'])) {
                $pdo->prepare('UPDATE pedido SET resena_enviada=1 WHERE id=?')->execute(array((int)$job['referencia_id']));
            }
            $sent++;
        } else {
            $maxAttempts = (int)$cfg['mail_max_attempts'];
            $permanentFailure = strpos($failure, 'CONFIG:') === 0 || preg_match('/codigo 5\d\d/i', $failure);
            if ($attempt >= $maxAttempts || $permanentFailure) {
                $pdo->prepare("UPDATE automatizacion_correo SET estado='fallido', ultimo_error=? WHERE id=?")
                    ->execute(array(automation_limit_text($failure, 1000), (int)$job['id']));
            } else {
                $minutes = array(1 => 1, 2 => 5, 3 => 15, 4 => 60, 5 => 180);
                $delay = isset($minutes[$attempt]) ? $minutes[$attempt] : 180;
                $pdo->prepare("UPDATE automatizacion_correo
                               SET estado='pendiente', disponible_el=DATE_ADD(NOW(), INTERVAL ? MINUTE), ultimo_error=?
                               WHERE id=?")
                    ->execute(array($delay, automation_limit_text($failure, 1000), (int)$job['id']));
                $retried++;
            }
        }
    }

    return array('enviados' => $sent, 'reintentos' => $retried, 'deshabilitado' => false);
}

function automation_smtp_send($cfg, $job, &$failure) {
    if (!filter_var($cfg['mail_from'], FILTER_VALIDATE_EMAIL)) {
        $failure = 'CONFIG: PV_MAIL_FROM no tiene un formato de correo valido';
        return false;
    }
    if (!filter_var($job['destinatario'], FILTER_VALIDATE_EMAIL)) {
        $failure = 'CONFIG: el destinatario no tiene un formato de correo valido';
        return false;
    }
    if ($cfg['smtp_host'] === '' || $cfg['smtp_username'] === '' || $cfg['smtp_password'] === '') {
        $failure = 'CONFIG: completa PV_SMTP_HOST, PV_SMTP_USERNAME y PV_SMTP_PASSWORD en .env';
        return false;
    }
    if (!in_array($cfg['smtp_security'], array('ssl', 'starttls'), true)) {
        $failure = 'CONFIG: PV_SMTP_SECURITY debe ser ssl o starttls';
        return false;
    }
    if (!preg_match('/^[A-Za-z0-9.-]+$/', $cfg['smtp_host'])) {
        $failure = 'CONFIG: PV_SMTP_HOST no es valido';
        return false;
    }

    $scheme = $cfg['smtp_security'] === 'ssl' ? 'ssl://' : 'tcp://';
    $context = stream_context_create(array('ssl' => array(
        'verify_peer' => true,
        'verify_peer_name' => true,
        'peer_name' => $cfg['smtp_host'],
        'allow_self_signed' => false
    )));
    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($scheme . $cfg['smtp_host'] . ':' . (int)$cfg['smtp_port'], $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        $failure = 'No se pudo conectar al servidor SMTP (' . automation_limit_text($errstr, 300) . ')';
        return false;
    }

    stream_set_timeout($socket, 15);
    try {
        automation_smtp_expect($socket, array(220), 'saludo');
        automation_smtp_command($socket, 'EHLO pixelvault.local', array(250), 'EHLO');
        if ($cfg['smtp_security'] === 'starttls') {
            automation_smtp_command($socket, 'STARTTLS', array(220), 'STARTTLS');
            $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($crypto !== true) throw new RuntimeException('No se pudo iniciar TLS con el servidor SMTP');
            automation_smtp_command($socket, 'EHLO pixelvault.local', array(250), 'EHLO despues de TLS');
        }

        automation_smtp_command($socket, 'AUTH LOGIN', array(334), 'autenticacion');
        automation_smtp_command($socket, base64_encode($cfg['smtp_username']), array(334), 'usuario SMTP');
        automation_smtp_command($socket, base64_encode($cfg['smtp_password']), array(235), 'clave SMTP');
        automation_smtp_command($socket, 'MAIL FROM:<' . automation_header_value($cfg['mail_from']) . '>', array(250), 'remitente');
        automation_smtp_command($socket, 'RCPT TO:<' . automation_header_value($job['destinatario']) . '>', array(250, 251), 'destinatario');
        automation_smtp_command($socket, 'DATA', array(354), 'cuerpo del correo');

        $headers = array(
            'Date: ' . date(DATE_RFC2822),
            'From: ' . automation_smtp_encode_header($cfg['mail_from_name']) . ' <' . automation_header_value($cfg['mail_from']) . '>',
            'To: <' . automation_header_value($job['destinatario']) . '>',
            'Subject: ' . automation_smtp_encode_header($job['asunto']),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'X-Mailer: PixelVault PHP Automation',
            'Message-ID: <pv-' . bin2hex(random_bytes(10)) . '@pixelvault.local>'
        );
        $message = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($job['html']), 76, "\r\n");
        $message = preg_replace('/(^|\r\n)\./', '$1..', $message);
        automation_smtp_write($socket, $message . "\r\n.\r\n");
        automation_smtp_expect($socket, array(250), 'aceptacion del mensaje');
        @fwrite($socket, "QUIT\r\n");
        return true;
    } catch (Throwable $e) {
        $failure = $e->getMessage();
        return false;
    } finally {
        if (is_resource($socket)) fclose($socket);
    }
}

function automation_smtp_command($socket, $command, $expectedCodes, $stage) {
    automation_smtp_write($socket, $command . "\r\n");
    automation_smtp_expect($socket, $expectedCodes, $stage);
}

function automation_smtp_expect($socket, $expectedCodes, $stage) {
    $response = '';
    do {
        $line = fgets($socket, 2048);
        if ($line === false) throw new RuntimeException('El servidor SMTP cerró la conexión durante ' . $stage);
        $response .= $line;
    } while (isset($line[3]) && $line[3] === '-');

    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP rechazó ' . $stage . ' (codigo ' . $code . ')');
    }
}

function automation_smtp_write($socket, $text) {
    $offset = 0;
    $length = strlen($text);
    while ($offset < $length) {
        $written = fwrite($socket, substr($text, $offset));
        if ($written === false || $written === 0) throw new RuntimeException('No se pudo escribir al servidor SMTP');
        $offset += $written;
    }
}

function automation_smtp_encode_header($value) {
    $value = automation_header_value($value);
    if (preg_match('/^[\x20-\x7E]*$/', $value)) return $value;
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function automation_limit_text($value, $limit) {
    $value = (string)$value;
    if (function_exists('mb_substr')) return mb_substr($value, 0, (int)$limit, 'UTF-8');
    return substr($value, 0, (int)$limit);
}

function automation_prune_logs() {
    $days = (int)automation_cfg()['retention_days'];
    $stmt = db()->prepare("DELETE FROM automatizacion_ejecucion WHERE iniciado_el < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute(array($days));
    $runs = $stmt->rowCount();
    $stmt = db()->prepare("DELETE FROM automatizacion_correo WHERE estado='enviado' AND enviado_el < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute(array($days));
    return array('ejecuciones_eliminadas' => $runs, 'correos_eliminados' => $stmt->rowCount());
}
