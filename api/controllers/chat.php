<?php
/* ============================================================
   Controlador: Chatbot IA (proxy hacia n8n)
   POST /api/index.php?action=chat   body: { mensaje, lang }

   Aqui vive la "capa de negocio" de la IA:
     - valida y acota lo que escribe el usuario
     - arma el contexto (sesion, idioma, usuario, catalogo real)
     - reenvia el POST al webhook de n8n y devuelve la respuesta

   La memoria conversacional NO esta aqui: n8n la lee y escribe en
   la tabla chat_log. Si n8n no responde, el frontend cae en su bot
   local de palabras clave.
   ============================================================ */

function chat_mensaje() {
    $cfg = require __DIR__ . '/../config/n8n_config.php';

    $d       = read_json_body();
    $mensaje = isset($d['mensaje']) ? trim((string)$d['mensaje']) : '';
    if ($mensaje === '') {
        json_error('El mensaje es obligatorio');
    }
    if (function_exists('mb_substr')) {
        $mensaje = mb_substr($mensaje, 0, $cfg['max_len']);
    } else {
        $mensaje = substr($mensaje, 0, $cfg['max_len']);
    }

    $lang = (isset($d['lang']) && $d['lang'] === 'en') ? 'en' : 'es';
    $u    = current_user();

    /* El historial NO se guarda aqui: n8n lo lee y escribe en la tabla
       chat_log, que es la memoria real de la conversacion. */
    $payload = array(
        'sesion'   => chat_sesion_id(),
        'mensaje'  => $mensaje,
        'lang'     => $lang,
        'usuario'  => $u ? $u['username'] : null,
        'rol'      => $u ? $u['rol'] : 'visitante',
        'moneda'   => $u ? $u['moneda'] : 'USD',
        'catalogo' => chat_catalogo($mensaje)
    );

    if (!$cfg['activo']) {
        json_error('n8n desactivado', 503);
    }

    $r = chat_post_n8n($cfg, $payload);

    if ($r === null || !isset($r['reply']) || trim($r['reply']) === '') {
        json_error('n8n no disponible', 503);
    }

    json_ok(array(
        'reply'    => $r['reply'],
        'fuente'   => isset($r['fuente']) ? $r['fuente'] : 'n8n',
        'workflow' => isset($r['workflow']) ? $r['workflow'] : ''
    ));
}

/* ============================================================
   Identificador anonimo de conversacion.
   Sirve de llave en chat_log para que n8n recupere el historial
   aunque el visitante no tenga cuenta.
   ============================================================ */
function chat_sesion_id() {
    session_start_secure();
    if (empty($_SESSION['chat_sesion'])) {
        $_SESSION['chat_sesion'] = bin2hex(random_bytes(8));
    }
    return $_SESSION['chat_sesion'];
}

/* ============================================================
   POST hacia el webhook de n8n (cURL con respaldo a streams)
   Devuelve el arreglo JSON de n8n, o null si algo fallo.
   ============================================================ */
function chat_post_n8n($cfg, $payload) {
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $headers = array('Content-Type: application/json', 'Accept: application/json');
    if ($cfg['token'] !== '') {
        $headers[] = 'X-N8N-Token: ' . $cfg['token'];
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($cfg['webhook']);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => $cfg['timeout']
        ));
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code < 200 || $code >= 300) {
            return null;
        }
        return json_decode($body, true);
    }

    $ctx = stream_context_create(array('http' => array(
        'method'        => 'POST',
        'header'        => implode("\r\n", $headers),
        'content'       => $json,
        'timeout'       => $cfg['timeout'],
        'ignore_errors' => true
    )));
    $body = @file_get_contents($cfg['webhook'], false, $ctx);
    if ($body === false) {
        return null;
    }
    return json_decode($body, true);
}

/* ============================================================
   Contexto del catalogo: solo se consulta si la pregunta
   parece tratada de productos, precios o stock.
   ============================================================ */
function chat_catalogo($mensaje) {
    $patron = '/product|juego|game|precio|costo|cuanto|stock|disponib|agotad|catalog|tienda|vent|precio de|hay |tienen|juegos/i';
    if (!preg_match($patron, $mensaje)) {
        return array();
    }
    $stmt = db()->query('SELECT p.nombre, p.precio, p.cantidad, c.nombre AS categoria
                         FROM producto p JOIN categoria c ON c.id = p.categoria_id
                         WHERE p.estado = "activo" ORDER BY p.id LIMIT 12');
    return $stmt->fetchAll();
}