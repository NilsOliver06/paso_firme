<?php
// ==============================================================================
// ARCHIVO: usuarios/editar.php
// DESCRIPCIÓN: Edita nombres, correo y rol de un usuario (sin contraseña).
//              Solo Administrador (rol 1).
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

// 5. ID por GET (el ID real siempre sale de la URL)
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id <= 0) {
    header("Location: index.php");
    exit;
}

// 6. Carga el usuario actual (sin traer la contraseña)
$stmt = mysqli_prepare($conexion, "SELECT id_usuario, nombres, correo, id_rol FROM usuarios WHERE id_usuario = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$usuario) {
    header("Location: index.php");
    exit;
}

// ¿Está editando su propia cuenta? (para evitar que se quite el rol de admin por accidente)
$es_yo = ($id === (int) $_SESSION['id']);

$error = '';

// 7. POST: validación + UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario['nombres'] = trim($_POST['nombres'] ?? '');
    $usuario['correo']  = trim($_POST['correo'] ?? '');
    $usuario['id_rol']  = (string) ($_POST['id_rol'] ?? '');

    if ($usuario['nombres'] === '' || mb_strlen($usuario['nombres']) > 100) {
        $error = 'El nombre es obligatorio (máximo 100 caracteres).';
    } elseif (filter_var($usuario['correo'], FILTER_VALIDATE_EMAIL) === false || mb_strlen($usuario['correo']) > 100) {
        $error = 'Ingresa un correo válido.';
    } elseif (filter_var($usuario['id_rol'], FILTER_VALIDATE_INT) === false || (int) $usuario['id_rol'] <= 0) {
        $error = 'Selecciona un rol válido.';
    } elseif ($es_yo && (int) $usuario['id_rol'] !== 1) {
        $error = 'No puedes quitarte a ti mismo el rol de Administrador.';
    } else {
        $id_rol = (int) $usuario['id_rol'];

        $stmt = mysqli_prepare($conexion,
            "UPDATE usuarios SET nombres = ?, correo = ?, id_rol = ? WHERE id_usuario = ?");
        // "ssii" = string, string, integer, integer
        mysqli_stmt_bind_param($stmt, "ssii", $usuario['nombres'], $usuario['correo'], $id_rol, $id);

        try {
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            // Si editó su propio nombre, se actualiza en la sesión para la cabecera
            if ($es_yo) {
                $_SESSION['nombres'] = $usuario['nombres'];
            }
            header("Location: index.php?msg=editado");
            exit;
        } catch (mysqli_sql_exception $e) {
            $error = ($e->getCode() === 1062)
                ? 'Ya existe otro usuario con ese correo.'
                : 'No se pudo actualizar el usuario. Verifica los datos.';
        }
    }
}

// 8. Roles para el <select>
$roles = mysqli_fetch_all(
    mysqli_query($conexion, "SELECT id_rol, nombre_rol FROM roles ORDER BY id_rol ASC"),
    MYSQLI_ASSOC
);

$clase_input = "w-full px-4 py-3 bg-white border border-neutral-300 rounded-xl text-sm text-neutral-900 placeholder-neutral-400 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition";
$clase_label = "block text-xs font-semibold uppercase tracking-wide text-neutral-700 mb-2";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PasoFirme | Editar Usuario</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900">

    <?php include '../includes/navbar.php'; ?>

    <main class="max-w-2xl mx-auto px-4 sm:px-6 py-8">

        <a href="index.php" class="text-xs font-semibold uppercase tracking-widest text-neutral-400 hover:text-black transition">&larr; Volver a usuarios</a>
        <h1 class="text-3xl font-black tracking-tight mt-1 mb-6">Editar Usuario</h1>

        <?php if ($error !== ''): ?>
            <div class="mb-6 rounded-xl border border-neutral-300 bg-neutral-100 px-4 py-3 text-sm text-neutral-800" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="editar.php?id=<?php echo $id; ?>" method="POST"
              class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-6 sm:p-8 space-y-5">

            <div>
                <label for="nombres" class="<?php echo $clase_label; ?>">Nombres</label>
                <input type="text" id="nombres" name="nombres" maxlength="100" required
                       value="<?php echo htmlspecialchars($usuario['nombres']); ?>" class="<?php echo $clase_input; ?>">
            </div>

            <div>
                <label for="correo" class="<?php echo $clase_label; ?>">Correo</label>
                <input type="email" id="correo" name="correo" maxlength="100" required
                       value="<?php echo htmlspecialchars($usuario['correo']); ?>" class="<?php echo $clase_input; ?>">
            </div>

            <div>
                <label for="id_rol" class="<?php echo $clase_label; ?>">Rol</label>
                <select id="id_rol" name="id_rol" required class="<?php echo $clase_input; ?>">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo (int) $r['id_rol']; ?>"
                            <?php echo ((string) $r['id_rol'] === (string) $usuario['id_rol']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r['nombre_rol']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex flex-col-reverse sm:flex-row gap-3 pt-2">
                <a href="index.php"
                   class="flex-1 text-center px-6 py-3 border border-neutral-300 rounded-full text-sm font-semibold uppercase tracking-wide text-neutral-600 hover:border-black hover:text-black transition">
                    Cancelar
                </a>
                <button type="submit"
                        class="flex-1 bg-black text-white px-6 py-3 rounded-full text-sm font-semibold uppercase tracking-wide hover:bg-neutral-800 active:scale-[0.98] transition">
                    Guardar cambios
                </button>
            </div>
        </form>
    </main>
</body>
</html>
