<?php
require_once __DIR__ . '/../services/product_vision.php';

function vision_analizar() {
    $user = require_login();
    if (empty($_POST['privacy_consent']) || $_POST['privacy_consent'] !== '1') {
        json_error('Debes aceptar el uso de la imagen para realizar el reconocimiento.', 400);
    }
    if (empty($_FILES['image']) || !isset($_FILES['image']['error'])) {
        json_error('Selecciona una foto del producto.', 400);
    }
    $file = $_FILES['image'];
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        $code = in_array((int)$file['error'], array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true) ? 413 : 400;
        json_error($code === 413 ? 'La imagen supera el limite permitido.' : 'No se pudo recibir la imagen.', $code);
    }
    if (!is_uploaded_file($file['tmp_name']) || (int)$file['size'] < 1 || (int)$file['size'] > 1800000) {
        json_error('La imagen JPEG debe estar comprimida y pesar menos de 1.8 MB.', 413);
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) finfo_close($finfo);
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($mime !== 'image/jpeg' || !$imageInfo || (int)$imageInfo[2] !== IMAGETYPE_JPEG) {
        json_error('La foto debe estar convertida a JPEG desde el selector de imagen.', 415);
    }
    $width = (int)$imageInfo[0];
    $height = (int)$imageInfo[1];
    if ($width < 64 || $height < 64 || $width > 8000 || $height > 8000 || $width * $height > 32000000) {
        json_error('La resolucion de la imagen no es valida.', 400);
    }

    $pdo = db();
    $uid = (int)$user['id'];
    vision_purge_expired_images($pdo);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM vision_solicitud WHERE usuario_id=? AND creado_el >= DATE_SUB(NOW(), INTERVAL 1 DAY)');
    $stmt->execute(array($uid));
    if ((int)$stmt->fetchColumn() >= 30) json_error('Alcanzaste el limite diario de 30 reconocimientos.', 429);

    $dir = dirname(__DIR__, 2) . '/storage/vision';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        json_error('No se pudo preparar el almacenamiento privado de imagenes.', 500);
    }
    $name = bin2hex(random_bytes(20)) . '.jpg';
    $path = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $path)) json_error('No se pudo guardar la imagen de forma privada.', 500);
    @chmod($path, 0600);
    $hash = hash_file('sha256', $path);
    $cfg = require __DIR__ . '/../config/gemini_config.php';

    try {
        $stmt = $pdo->prepare("INSERT INTO vision_solicitud
            (usuario_id, archivo_ruta, sha256, estado, modelo, consentido_el, creado_el)
            VALUES (?, ?, ?, 'procesando', ?, NOW(), NOW())");
        $stmt->execute(array($uid, $name, $hash, $cfg['model']));
        $requestId = (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        @unlink($path);
        error_log('PixelVault vision audit insert failed: ' . $e->getMessage());
        json_error('No se pudo registrar la solicitud de reconocimiento.', 500);
    }

    $catalog = vision_catalog_products($pdo);
    if (!$catalog) {
        $pdo->prepare("UPDATE vision_solicitud SET estado='sin_coincidencia', descripcion='El catalogo no tiene productos activos.', resultado_json='{}', finalizado_el=NOW() WHERE id=?")
            ->execute(array($requestId));
        json_ok(array('id' => $requestId, 'description' => '', 'low_confidence' => true, 'candidates' => array()));
    }

    try {
        $result = vision_gemini_analyze($path, $catalog, isset($_POST['lang']) && $_POST['lang'] === 'en' ? 'en' : 'es');
    } catch (Throwable $e) {
        error_log('PixelVault vision analysis failed: ' . $e->getMessage());
        $result = array('ok' => false, 'reason' => 'provider');
    }
    if (empty($result['ok'])) {
        $reason = isset($result['reason']) ? $result['reason'] : 'provider';
        $pdo->prepare("UPDATE vision_solicitud SET estado='error', error_codigo=?, finalizado_el=NOW() WHERE id=?")
            ->execute(array($reason, $requestId));
        $lang = isset($_POST['lang']) && $_POST['lang'] === 'en' ? 'en' : 'es';
        $status = $reason === 'rate_limit' ? 429 : (($reason === 'missing_key' || $reason === 'overloaded') ? 503 : 502);
        json_error(vision_error_message($reason, $lang), $status);
    }

    $candidateIds = array();
    foreach ($result['candidates'] as $candidate) {
        $candidateIds[] = array('product_id' => (int)$candidate['product_id'], 'confidence' => (float)$candidate['confidence']);
    }
    $topConfidence = $result['candidates'] ? (float)$result['candidates'][0]['confidence'] : null;
    $state = $result['candidates'] ? 'coincidencia' : 'sin_coincidencia';
    $saved = json_encode(array('candidates' => $candidateIds), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    $pdo->prepare('UPDATE vision_solicitud SET estado=?, descripcion=?, confianza=?, resultado_json=?, error_codigo=NULL, finalizado_el=NOW() WHERE id=?')
        ->execute(array($state, $result['description'], $topConfidence, $saved, $requestId));

    $publicCandidates = array();
    foreach ($result['candidates'] as $candidate) {
        $publicCandidates[] = array(
            'product_id' => (int)$candidate['product_id'],
            'confidence' => (float)$candidate['confidence'],
            'nombre' => (string)$candidate['nombre'],
            'categoria' => (string)$candidate['categoria'],
            'precio' => (float)$candidate['precio'],
            'stock' => (int)$candidate['stock']
        );
    }
    json_ok(array(
        'id' => $requestId,
        'description' => $result['description'],
        'low_confidence' => $topConfidence === null || $topConfidence < 0.75,
        'candidates' => $publicCandidates
    ));
}

function vision_error_message($reason, $lang) {
    $messages = array(
        'es' => array(
            'missing_key' => 'Falta configurar GEMINI_API_KEY para el reconocimiento visual.',
            'invalid_key' => 'Gemini rechazo la clave de API. Revisa la configuracion de la cuenta en .env.',
            'invalid_model' => 'El modelo configurado para Gemini no esta disponible.',
            'rate_limit' => 'Gemini alcanzo temporalmente el limite de solicitudes o la cuota gratuita. Intenta mas tarde.',
            'overloaded' => 'Gemini esta temporalmente saturado. La foto no se pudo analizar; vuelve a intentarlo en unos segundos.',
            'blocked_image' => 'Gemini no pudo procesar esta imagen. Recortala para mostrar solo el producto y vuelve a intentarlo.',
            'invalid_response' => 'Gemini no devolvio una coincidencia que pudiera verificar. Prueba con una foto mas clara y centrada.',
            'invalid_request' => 'Gemini rechazo el formato de la solicitud. Revisa la configuracion del modelo.',
            'transport' => 'No hubo conexion con Gemini. Revisa Internet e intenta otra vez.',
            'provider' => 'El servicio de Gemini tuvo un problema temporal. Intenta de nuevo en un momento.'
        ),
        'en' => array(
            'missing_key' => 'GEMINI_API_KEY is missing for visual recognition.',
            'invalid_key' => 'Gemini rejected the API key. Check the account configuration in .env.',
            'invalid_model' => 'The configured Gemini model is unavailable.',
            'rate_limit' => 'Gemini temporarily reached its request limit or free quota. Please try again later.',
            'overloaded' => 'Gemini is temporarily overloaded. The photo could not be analyzed; please try again in a few seconds.',
            'blocked_image' => 'Gemini could not process this image. Crop it to show only the product and try again.',
            'invalid_response' => 'Gemini did not return a match that could be verified. Try a clearer, centered photo.',
            'invalid_request' => 'Gemini rejected the request format. Check the model configuration.',
            'transport' => 'Could not connect to Gemini. Check the internet connection and try again.',
            'provider' => 'Gemini had a temporary service problem. Please try again shortly.'
        )
    );
    $language = $lang === 'en' ? 'en' : 'es';
    return isset($messages[$language][$reason]) ? $messages[$language][$reason] : $messages[$language]['provider'];
}

function vision_confirmar() {
    $user = require_login();
    if ($user['rol'] !== 'cliente') json_error('Solo una cuenta de cliente puede agregar productos al carrito.', 403);
    $data = read_json_body();
    $requestId = isset($data['vision_id']) ? (int)$data['vision_id'] : 0;
    $productId = isset($data['producto_id']) ? (int)$data['producto_id'] : 0;
    if ($requestId <= 0 || $productId <= 0) json_error('La seleccion del producto no es valida.', 400);

    require_once __DIR__ . '/carrito.php';
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id, estado, resultado_json FROM vision_solicitud WHERE id=? AND usuario_id=? FOR UPDATE');
        $stmt->execute(array($requestId, (int)$user['id']));
        $request = $stmt->fetch();
        if (!$request) {
            $pdo->rollBack();
            json_error('No se encontro ese reconocimiento.', 404);
        }
        if ($request['estado'] !== 'coincidencia') {
            $pdo->rollBack();
            json_error('Este reconocimiento ya fue usado o no tiene coincidencias disponibles.', 409);
        }
        $saved = json_decode((string)$request['resultado_json'], true);
        $allowed = false;
        if (isset($saved['candidates']) && is_array($saved['candidates'])) {
            foreach ($saved['candidates'] as $candidate) {
                if (isset($candidate['product_id']) && (int)$candidate['product_id'] === $productId) { $allowed = true; break; }
            }
        }
        if (!$allowed) {
            $pdo->rollBack();
            json_error('El producto no pertenece a las coincidencias reconocidas.', 403);
        }

        $cartResult = carrito_agregar_producto($pdo, (int)$user['id'], $productId, 1);
        if (!$cartResult['ok']) {
            $pdo->rollBack();
            json_error($cartResult['error'], $cartResult['code']);
        }
        $stmt = $pdo->prepare("UPDATE vision_solicitud SET estado='agregado', producto_confirmado_id=?, confirmado_el=NOW() WHERE id=?");
        $stmt->execute(array($productId, $requestId));
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('PixelVault vision confirmation failed: ' . $e->getMessage());
        json_error('No se pudo agregar el producto al carrito.', 500);
    }
    json_ok(array('message' => 'Producto agregado al carrito.'));
}
