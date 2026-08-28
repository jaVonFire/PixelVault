<?php
/* ============================================================
   API REST - PixelVault
   Punto de entrada unico.
   Frontend (HTML/JS)  ->  api/index.php  ->  Base de datos (MySQL)
   ============================================================ */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/helpers/auth.php';

session_start_secure();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* Lee el cuerpo JSON (para POST/PUT) */
function read_json_body() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : array();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {

    /* =========== AUTENTICACION =========== */
    case 'register':
        if ($method !== 'POST') json_error('Metodo no permitido', 405);
        require_once __DIR__ . '/controllers/auth.php';
        auth_register();
        break;

    case 'login':
        if ($method !== 'POST') json_error('Metodo no permitido', 405);
        require_once __DIR__ . '/controllers/auth.php';
        auth_login();
        break;

    case 'logout':
        require_once __DIR__ . '/controllers/auth.php';
        auth_logout();
        break;

    case 'me':
        if ($method === 'PUT') {
            require_once __DIR__ . '/controllers/auth.php';
            auth_update_profile();
            break;
        }
        $u = current_user();
        json_ok($u ? $u : null, $u ? 200 : 401);
        break;

    /* =========== CATEGORIAS =========== */
    case 'categorias':
        require_once __DIR__ . '/controllers/categorias.php';
        categorias_index();
        break;

    /* =========== PRODUCTOS (CRUD) =========== */
    case 'productos':
        require_once __DIR__ . '/controllers/productos.php';
        productos_index($method);
        break;

    /* =========== CARRITO =========== */
    case 'carrito':
        require_once __DIR__ . '/controllers/carrito.php';
        switch ($method) {
            case 'GET':  carrito_index();  break;
            case 'POST': carrito_add();    break;
            case 'PUT':  carrito_update(); break;
            case 'DELETE': carrito_remove(); break;
            default: json_error('Metodo no permitido', 405);
        }
        break;

    /* =========== PEDIDOS =========== */
    case 'pedidos':
        require_once __DIR__ . '/controllers/pedidos.php';
        switch ($method) {
            case 'POST': pedidos_create(); break;  /* confirmar compra */
            case 'GET':  pedidos_index();  break;  /* historial */
            default: json_error('Metodo no permitido', 405);
        }
        break;

    case 'pedido_estado':
        require_once __DIR__ . '/controllers/pedidos.php';
        pedidos_update_estado();
        break;

    /* =========== DASHBOARD ADMIN =========== */
    case 'dashboard':
        require_once __DIR__ . '/controllers/admin.php';
        dashboard_index();
        break;

    /* =========== CLIENTES ADMIN =========== */
    case 'clientes':
        require_once __DIR__ . '/controllers/admin.php';
        clientes_index();
        break;

    /* =========== RESUMEN ALERTAS (inventario bajo) =========== */
    case 'alertas':
        require_once __DIR__ . '/controllers/productos.php';
        productos_alertas();
        break;

    default:
        json_error('Accion no valida: ' . $action, 404);
}
