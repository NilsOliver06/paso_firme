<?php
// ==============================================================================
// ARCHIVO: inventario/eliminar.php
// DESCRIPCIÓN: Baja lógica de un registro de inventario (sin HTML).
//              NO se usa DELETE: se marca estado_activo = 0 para conservar el
//              historial de ventas (detalle_venta referencia a inventario).
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

// 3. Solo el Administrador (rol 1) puede dar de baja
if ($_SESSION['rol'] != 1) {
    header("Location: index.php");
    exit;
}

// 4. Conexión a la BD
require_once '../includes/conexion.php';

/** @var mysqli $conexion */

// 5. Recibe el ID por GET y lo valida como entero positivo
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if ($id !== false && $id !== null && $id > 0) {
    // 6. Baja lógica con sentencia preparada: estado_activo = 0
    $stmt = mysqli_prepare($conexion, "UPDATE inventario SET estado_activo = 0 WHERE id_inventario = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// 7. Vuelve al listado (inventario/index.php)
header("Location: index.php?msg=eliminado");
exit;
