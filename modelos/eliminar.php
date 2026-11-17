<?php
// ==============================================================================
// ARCHIVO: modelos/eliminar.php
// DESCRIPCIÓN: Baja lógica de un modelo (sin vista). NO se usa DELETE: se marca
//              estado_activo = 0 para conservar el historial. Solo Administrador.
// ==============================================================================

// 1. Inicia la sesión solo si aún no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Sin sesión -> login
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit;
}

// 3. Solo Administrador (rol 1)
if ($_SESSION['rol'] != 1) {
    header("Location: index.php");
    exit;
}

// 4. Conexión a la BD
require_once '../includes/conexion.php';

/** @var mysqli $conexion */

// 5. ID por GET, validado como entero positivo
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if ($id !== false && $id !== null && $id > 0) {
    // 6. Baja lógica con sentencia preparada
    $stmt = mysqli_prepare($conexion, "UPDATE modelos SET estado_activo = 0 WHERE id_modelo = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// 7. Vuelve al catálogo
header("Location: index.php?msg=eliminado");
exit;
