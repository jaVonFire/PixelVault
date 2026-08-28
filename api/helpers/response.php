<?php
/* ============================================================
   Helpers de respuesta JSON
   ============================================================ */

function json_ok($data = null, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    if ($data === null) {
        $data = new stdClass();
    }
    echo json_encode($data);
    exit;
}

function json_error($msg, $code = 400) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error' => $msg));
    exit;
}
