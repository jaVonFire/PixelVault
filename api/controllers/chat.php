<?php
/* Chatbot propio: guardarrail, memoria en MySQL y llamada directa a Gemini. */

function chat_mensaje() {
    $cfg = require __DIR__ . '/../config/gemini_config.php';
    $d = read_json_body();
    $mensaje = isset($d['mensaje']) ? trim((string)$d['mensaje']) : '';
    if ($mensaje === '') json_error('El mensaje es obligatorio');
    $mensaje = chat_limit_text($mensaje, $cfg['max_len']);

    $lang = (isset($d['lang']) && $d['lang'] === 'en') ? 'en' : 'es';
    $user = current_user();
    $sessionId = chat_sesion_id();
    $history = chat_history($sessionId);
    $catalog = chat_catalogo($mensaje);
    $reply = chat_guardrail_reply($mensaje, $lang, !empty($history));
    $source = 'guardarrail';
    $offlineReason = 'gemini_error';

    if ($reply === null) {
        if (!empty($cfg['api_key'])) {
            for ($attempt = 1; $attempt <= (int)$cfg['retries']; $attempt++) {
                $reply = chat_call_gemini($cfg, $mensaje, $lang, $user, $catalog, $history);
                if ($reply !== null || $attempt >= (int)$cfg['retries']) break;
                usleep((int)$cfg['retry_delay_ms'] * 1000);
            }
            $source = $reply === null ? 'respaldo' : 'gemini';
        } else {
            $source = 'local';
            $offlineReason = 'missing_key';
        }
        if ($reply === null) $reply = chat_local_reply($mensaje, $lang, $catalog, $offlineReason);
    }

    chat_log_store(array(
        'sesion' => $sessionId,
        'usuario' => $user ? $user['username'] : null,
        'lang' => $lang,
        'mensaje' => $mensaje,
        'reply' => $reply,
        'fuente' => $source
    ));

    json_ok(array('reply' => $reply, 'fuente' => $source, 'workflow' => 'PixelVault - Chatbot PHP'));
}

function chat_limit_text($text, $limit) {
    if (function_exists('mb_substr')) return mb_substr($text, 0, $limit, 'UTF-8');
    return substr($text, 0, $limit);
}

function chat_sesion_id() {
    session_start_secure();
    if (empty($_SESSION['chat_sesion'])) $_SESSION['chat_sesion'] = bin2hex(random_bytes(16));
    return $_SESSION['chat_sesion'];
}

function chat_normalize($text) {
    $text = strtolower((string)$text);
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($ascii !== false) $text = strtolower($ascii);
    }
    return preg_replace('/\s+/', ' ', trim($text));
}

function chat_has_any($text, $terms) {
    foreach ($terms as $term) {
        if (strpos($text, $term) !== false) return true;
    }
    return false;
}

/* Respuestas fijas para inyecciones y preguntas claramente fuera de la tienda. */
function chat_guardrail_reply($mensaje, $lang, $hasHistory) {
    $text = chat_normalize($mensaje);
    $injection = array(
        'ignora', 'ignore previous', 'olvida tus instrucciones', 'olvida lo anterior',
        'dame tu prompt', 'system prompt', 'instrucciones del sistema', 'reveal your',
        'jailbreak', 'developer mode', 'sin restricciones', 'prompt original'
    );
    $store = array(
        'hola', 'holis', 'hey', 'hi', 'hello', 'buenas', 'saludos', 'que tal', 'que onda',
        'gracias', 'thanks', 'thank you', 'adios', 'bye', 'chao', 'producto', 'juego', 'game',
        'precio', 'costo', 'cuanto', 'stock', 'disponib', 'catalog', 'tienda', 'carrito', 'cart',
        'pedido', 'order', 'factura', 'invoice', 'moneda', 'currency', 'envio', 'shipping', 'delivery',
        'devolucion', 'refund', 'garantia', 'pago', 'paypal', 'cuenta', 'login', 'registro', 'password',
        'perfil', 'ayuda', 'help', 'privacidad', 'dato', 'seguridad', 'recomienda', 'recomiend',
        'comprar', 'buy', 'cupon', 'coupon', 'promocion', 'punto', 'nivel', 'resena', 'review',
        'calificacion', 'soporte', 'contacto', 'hay ', 'tienen', 'have', 'available', 'busca', 'busco', 'venden'
    );
    $outside = array(
        'politica partidista', 'politica internacional', 'presidente', 'eleccion', 'gobierno', 'religion', 'iglesia', 'futbol',
        'seleccion', 'mundial', 'guerra', 'droga', 'porn', 'suicidio', 'racis', 'hackear',
        'python', 'javascript', 'programar', 'examen', 'universidad', 'matematicas', 'cancion',
        'musica', 'pelicula', 'novia', 'novio', 'horoscopo', 'gossip'
    );

    if (chat_has_any($text, $injection)) {
        return $lang === 'en'
            ? 'I can help with PixelVault products, stock, cart, shipping, payments and your account. What can I look up for you?'
            : 'Puedo ayudarte con productos, stock, carrito, envios, pagos y tu cuenta de PixelVault. Que necesitas buscar?';
    }
    if ($text === '' || strlen($text) < 2) {
        return $lang === 'en' ? 'Could you write your question again?' : 'Me puedes escribir la pregunta otra vez?';
    }
    if (chat_has_any($text, $store)) return null;
    if (chat_has_any($text, $outside)) {
        return $lang === 'en'
            ? 'I am the PixelVault assistant, so I can only help with the store: products, stock, cart, shipping, payments and your account.'
            : 'Soy el asistente de PixelVault y solo puedo ayudar con la tienda: productos, stock, carrito, envios, pagos y tu cuenta.';
    }
    $followups = array('si', 'no', 'dale', 'bueno', 'ok', 'claro', 'guiame', 'cuentame', 'ese', 'esa', 'otro', 'otra', 'listo', 'bien');
    if ($hasHistory && (strlen($text) <= 25 || chat_has_any($text, $followups))) return null;
    return null;
}

function chat_history($sessionId) {
    $stmt = db()->prepare('SELECT mensaje, reply FROM chat_log WHERE sesion = ? ORDER BY id DESC LIMIT 8');
    $stmt->execute(array($sessionId));
    $rows = array_reverse($stmt->fetchAll());
    $contents = array();
    foreach ($rows as $row) {
        if ($row['mensaje'] !== '') $contents[] = array('role' => 'user', 'parts' => array(array('text' => chat_limit_text($row['mensaje'], 300))));
        if (!empty($row['reply'])) $contents[] = array('role' => 'model', 'parts' => array(array('text' => chat_limit_text($row['reply'], 300))));
    }
    return $contents;
}

function chat_catalogo($mensaje) {
    $text = chat_normalize($mensaje);
    $recommendation = chat_has_any($text, array('recommend', 'recomiend', 'mejores', 'best'));
    $generalRequest = chat_has_any($text, array('what do you have', 'what products', 'what games', 'que productos', 'que juegos', 'que tienen', 'que venden', 'catalogo', 'catalog'));
    $stop = array('que','cual','cuales','como','para','una','uno','por','con','del','las','los','the','what','which','how','are','you','your','can','could','would','does','do','there','please','and','or','for','me','my','with','have','quiero','tienen','venden','hay','precio','precios','stock','producto','productos','juego','juegos','buy','comprar','show','find','search','muestrame','dame','recommend','recomiendame','best','mejores');
    $words = preg_split('/[^a-z0-9]+/', $text);
    $terms = array();
    foreach ($words as $word) {
        if (strlen($word) >= 3 && !in_array($word, $stop, true) && !in_array($word, $terms, true)) $terms[] = $word;
        if (count($terms) >= 5) break;
    }

    if (!$terms) {
        if (!$recommendation && !$generalRequest) return array();
        $stmt = db()->query('SELECT p.nombre, p.precio, p.cantidad, c.nombre AS categoria
                             FROM producto p JOIN categoria c ON c.id=p.categoria_id
                             WHERE p.estado=\'activo\' ORDER BY p.id LIMIT 8');
        return $stmt->fetchAll();
    }

    $sql = 'SELECT p.nombre, p.precio, p.cantidad, c.nombre AS categoria
            FROM producto p JOIN categoria c ON c.id = p.categoria_id
            WHERE p.estado = \'activo\'';
    $params = array();
    if ($terms) {
        $likes = array();
        foreach ($terms as $term) {
            $likes[] = '(p.nombre LIKE ? OR c.nombre LIKE ?)';
            $params[] = '%' . $term . '%';
            $params[] = '%' . $term . '%';
        }
        $sql .= ' AND (' . implode(' OR ', $likes) . ')';
    }
    $sql .= ' ORDER BY p.id LIMIT 12';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    if (!$rows && ($recommendation || $generalRequest)) {
        $stmt = db()->query('SELECT p.nombre, p.precio, p.cantidad, c.nombre AS categoria
                             FROM producto p JOIN categoria c ON c.id = p.categoria_id
                             WHERE p.estado = \'activo\' ORDER BY p.id LIMIT 8');
        $rows = $stmt->fetchAll();
    }
    return $rows;
}

function chat_call_gemini($cfg, $mensaje, $lang, $user, $catalog, $history) {
    $catalogText = '(sin coincidencias exactas en el catalogo)';
    if ($catalog) {
        $lines = array();
        foreach ($catalog as $product) {
            $lines[] = '- ' . $product['nombre'] . ' | ' . $product['categoria'] . ' | USD ' . $product['precio'] . ' | stock: ' . $product['cantidad'];
        }
        $catalogText = implode("\n", $lines);
    }
    $customer = $user ? $user['username'] . ' (' . $user['rol'] . ', moneda ' . $user['moneda'] . ')' : 'visitante sin cuenta';
    $systemText = $lang === 'en'
        ? "You are Vault-Bot, PixelVault's shopping assistant. Answer the user's actual question directly; do not use a generic canned reply when the question is valid. If a question is unrelated to PixelVault, politely say you can only help with the store. Use only the catalog below for product, price and stock facts. Never invent availability or prices. PixelVault advertises shipping in Latin America and Spain in 3 to 7 business days, 30-day returns for sealed items, and Visa, Mastercard, PayPal and bank transfer. Purchases require an account. Keep replies concise and friendly. Ask for clarification only when needed. If you do not know a store detail, say so.\n\nCustomer: {$customer}\nCatalog:\n{$catalogText}"
        : "Eres Vault-Bot, el asistente de compras de PixelVault. Responde directamente la pregunta concreta del usuario; no uses una respuesta generica si la pregunta es valida. Si la pregunta no se relaciona con PixelVault, explica amablemente que solo puedes ayudar con la tienda. Usa solo el catalogo de abajo para afirmar productos, precios o stock. No inventes disponibilidad ni precios. PixelVault informa envios en Latinoamerica y Espana de 3 a 7 dias habiles, devoluciones durante 30 dias para productos sellados y pagos con Visa, Mastercard, PayPal o transferencia. Para comprar se requiere una cuenta. Responde breve y con tono cercano. Pide aclaracion solo cuando sea necesario. Si no conoces un dato de la tienda, dilo.\n\nCliente: {$customer}\nCatalogo:\n{$catalogText}";

    $contents = $history;
    $contents[] = array('role' => 'user', 'parts' => array(array('text' => $mensaje)));
    $body = array(
        'systemInstruction' => array('parts' => array(array('text' => $systemText))),
        'contents' => $contents,
        'generationConfig' => array('temperature' => 0.4, 'maxOutputTokens' => (int)$cfg['max_output_tokens'])
    );
    $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) return null;
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($cfg['model']) . ':generateContent';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'x-goog-api-key: ' . $cfg['api_key']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => (int)$cfg['timeout']
        ));
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === false) error_log('PixelVault Gemini request failed: ' . curl_error($ch));
        curl_close($ch);
        if (!is_string($response) || $status < 200 || $status >= 300) return null;
    } else {
        $context = stream_context_create(array('http' => array(
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nx-goog-api-key: " . $cfg['api_key'],
            'content' => $json,
            'timeout' => (int)$cfg['timeout'],
            'ignore_errors' => true
        )));
        $response = @file_get_contents($url, false, $context);
        if ($response === false || empty($http_response_header[0]) || !preg_match('/\s2\d\d\s/', $http_response_header[0])) return null;
    }

    $decoded = json_decode($response, true);
    $parts = isset($decoded['candidates'][0]['content']['parts']) ? $decoded['candidates'][0]['content']['parts'] : array();
    $reply = '';
    foreach ($parts as $part) if (!empty($part['text'])) $reply .= $part['text'];
    $reply = trim($reply);
    return $reply === '' ? null : chat_limit_text($reply, 900);
}

function chat_local_reply($mensaje, $lang, $catalog, $offlineReason = 'gemini_error') {
    $text = chat_normalize($mensaje);
    if ($catalog && chat_has_any($text, array('producto','productos','juego','juegos','game','precio','stock','catalog','comprar','buy','tienen','venden','have','recommend','recomiend','mejores','best','what do you have','what products','what games','que productos','que juegos','que tienen','que venden'))) {
        $items = array_slice($catalog, 0, 3);
        $lines = array();
        foreach ($items as $item) {
            $lines[] = $item['nombre'] . ' — USD ' . number_format((float)$item['precio'], 2) . ' (' . (int)$item['cantidad'] . ' en stock)';
        }
        if ($lines) return ($lang === 'en' ? 'Here are a few catalog matches: ' : 'Encontré estas opciones en el catálogo: ') . implode('; ', $lines) . ($lang === 'en' ? '. Would you like another category?' : '. Quieres que busque otra categoría?');
    }
    $productQuestion = chat_has_any($text, array('producto','productos','juego','juegos','game','precio','stock','disponib','agotad','catalog','tienda','venden','tienen','have','available','recommend','recomiend','mejores','best'));
    if ($productQuestion && !$catalog) {
        return $lang === 'en'
            ? 'I could not find an exact match for that product in the current catalog. Try its full name or another keyword.'
            : 'No encontré una coincidencia exacta en el catálogo actual. Prueba con el nombre completo del producto u otra palabra.';
    }
    $groups = $lang === 'en' ? array(
        array(array('hello','hi','hey','good morning','good afternoon','how are you'), 'Hi! What product or store question can I help you with?'),
        array(array('shipping','delivery'), 'Shipping is listed as 3 to 7 business days in Latin America and Spain.'),
        array(array('return','refund','warranty'), 'Returns are listed for sealed products within 30 days.'),
        array(array('payment','pay','visa','paypal'), 'The listed payment methods are Visa, Mastercard, PayPal and bank transfer.'),
        array(array('cart','checkout','buy','purchase'), 'Log in, add products to your cart, then confirm the order from the cart.'),
        array(array('currency','money','dollar'), 'Use the currency selector to display prices in another currency.'),
        array(array('login','log in','register','account','profile'), 'Sign in or create an account from My Account to save a cart and place an order.'),
        array(array('privacy','data','password','security'), 'Passwords are stored as hashes. Do not send payment or password details in chat.'),
        array(array('point','points','level','xp'), 'Purchases earn one point per whole unit of the order total. Loyalty levels start at 100, 300 and 600 points.'),
        array(array('thank','thanks'), 'You are welcome! Let me know if you need help with a product or order.'),
        array(array('bye','goodbye','see you'), 'Goodbye! Thanks for visiting PixelVault.')
    ) : array(
        array(array('hola','holis','hey','buenas','saludos','que tal'), '¡Hola! ¿Qué producto o tema de la tienda quieres consultar?'),
        array(array('envio','envios','shipping','delivery'), 'La tienda informa envios de 3 a 7 dias habiles en Latinoamerica y Espana.'),
        array(array('devolucion','reembolso','garantia','refund'), 'La politica informada permite devoluciones de productos sellados durante 30 dias.'),
        array(array('pago','pagar','visa','paypal','transferencia'), 'Los medios de pago informados son Visa, Mastercard, PayPal y transferencia.'),
        array(array('carrito','comprar','compra','pedido','checkout'), 'Inicia sesion, agrega productos al carrito y confirma el pedido desde el carrito.'),
        array(array('moneda','divisa','dolar','pesos'), 'Usa el selector de moneda para cambiar como se muestran los precios.'),
        array(array('login','iniciar sesion','registr','cuenta','perfil','contrasena'), 'Inicia sesion o crea una cuenta en Mi Cuenta para guardar el carrito y confirmar pedidos.'),
        array(array('privacidad','dato','seguridad','contrasena'), 'Las contrasenas se guardan como hashes. No compartas contrasenas ni datos de pago por el chat.'),
        array(array('punto','puntos','nivel','xp','experiencia'), 'Las compras acumulan un punto por cada unidad entera del total. Los niveles empiezan en 100, 300 y 600 puntos.'),
        array(array('gracias','agradezco'), '¡Con gusto! Dime si necesitas ayuda con un producto o pedido.'),
        array(array('adios','chao','hasta luego','nos vemos'), '¡Hasta luego! Gracias por visitar PixelVault.')
    );
    foreach ($groups as $group) if (chat_has_any($text, $group[0])) return $group[1];
    return $lang === 'en'
        ? ($offlineReason === 'missing_key'
            ? 'Gemini is not configured, so I cannot answer that reliably yet. Add a Gemini API key to the local .env file, or ask about a product or a known store policy.'
            : 'I could not verify that answer with Gemini, so I will not guess. Please retry or ask about a product or a known store policy.')
        : ($offlineReason === 'missing_key'
            ? 'Gemini no está configurado y no puedo responder eso con fiabilidad todavía. Agrega una clave de Gemini al archivo local .env, o pregúntame por un producto o una política conocida.'
            : 'No pude verificar esa respuesta con Gemini y no voy a adivinar. Inténtalo otra vez o pregúntame por un producto o una política conocida.');
}

function chat_log_store($row) {
    try {
        $stmt = db()->prepare('INSERT INTO chat_log (sesion, usuario, lang, mensaje, reply, fuente) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute(array($row['sesion'], $row['usuario'], $row['lang'], $row['mensaje'], $row['reply'], $row['fuente']));
    } catch (Throwable $e) {
        error_log('PixelVault chat log insert failed: ' . $e->getMessage());
    }
}
