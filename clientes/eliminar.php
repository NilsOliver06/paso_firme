<?php
// ==============================================================================
// ARCHIVO: clientes/eliminar.php
// DESCRIPCIÓN: Elimina un cliente (sin HTML). Acceso: Administrador y Vendedor.
//              ventas.id_cliente tiene ON DELETE SET NULL, así que el historial
//              de ventas se conserva (esas ventas pasan a "Cliente al paso").
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

// 3. Conexión
require_once '../includes/conexion.php';

/** @var mysqli $conexion */

// 4. ID por GET, validado como entero positivo
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id <= 0) {
    header("Location: index.php");
    exit;
}

// 5. DELETE preparado dentro de try-catch
try {
    $stmt = mysqli_prepare($conexion, "DELETE FROM clientes WHERE id_cliente = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: index.php?msg=eliminado");
} catch (mysqli_sql_exception $e) {
    header("Location: index.php?error=no_eliminado");
}
exit;
