<?php
/* ============================================================
   Controlador: Categorias
   ============================================================ */

function categorias_index() {
    $stmt = db()->query('SELECT id, nombre, activa FROM categoria ORDER BY nombre');
    json_ok(array('categorias' => $stmt->fetchAll()));
}
