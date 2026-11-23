<?php
// ==============================================================================
// ARCHIVO: usuarios/eliminar.php
// DESCRIPCIÓN: Baja lógica / reactivación de un usuario (sin HTML). Solo
//              Administrador (rol 1). Invierte estado_activo (1 <-> 0) para
//              preservar la auditoría de ventas y arqueos de caja.
// ==============================================================================

// 1. Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Sin sesión -> login
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit;
}

// 3. Solo Administrador
if ($_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit;
}

// 4. Conexión
require_once '../includes/conexion.php';

/** @var mysqli $conexion */

// 5. ID por GET, validado como entero positivo
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id <= 0) {
    header("Location: index.php");
    exit;
}

// 6. Un administrador no puede desactivar su propia cuenta
$id_sesion = (int) ($_SESSION['id_usuario'] ?? $_SESSION['id'] ?? 0);
if ($id === $id_sesion) {
    header("Location: index.php?error=propio_usuario");
    exit;
}

// 7. UPDATE preparado que invierte estado_activo (1 -> 0, 0 -> 1)
try {
    $stmt = mysqli_prepare($conexion,
        "UPDATE usuarios SET estado_activo = IF(estado_activo = 1, 0, 1) WHERE id_usuario = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: index.php?msg=estado_actualizado");
} catch (mysqli_sql_exception $e) {
    header("Location: index.php?error=no_eliminado");
}
exit;
