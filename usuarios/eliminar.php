<?php
// ==============================================================================
// ARCHIVO: usuarios/eliminar.php
// DESCRIPCIÓN: Elimina un usuario (sin HTML). Solo Administrador (rol 1).
//              Si el usuario tiene ventas/arqueos, la llave foránea (ON DELETE
//              RESTRICT) impide el borrado y se redirige con ?error=llave_foranea.
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

// 6. Un administrador no puede eliminar su propia cuenta
if ($id === (int) $_SESSION['id']) {
    header("Location: index.php?error=propio_usuario");
    exit;
}

// 7. DELETE preparado dentro de try-catch
try {
    $stmt = mysqli_prepare($conexion, "DELETE FROM usuarios WHERE id_usuario = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: index.php?msg=eliminado");
} catch (mysqli_sql_exception $e) {
    // 1451 = no se puede borrar: hay filas hijas que referencian a este usuario
    if ((int) $e->getCode() === 1451) {
        header("Location: index.php?error=llave_foranea");
    } else {
        header("Location: index.php?error=no_eliminado");
    }
}
exit;
