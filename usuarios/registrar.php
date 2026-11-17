<?php
// ==============================================================================
// ARCHIVO: usuarios/registrar.php
// DESCRIPCIÓN: Crea un usuario. La contraseña se guarda encriptada (bcrypt).
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

$error = '';
$val = ['nombres' => '', 'correo' => '', 'id_rol' => ''];

// 5. POST: validación + INSERT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $val['nombres'] = trim($_POST['nombres'] ?? '');
    $val['correo']  = trim($_POST['correo'] ?? '');
    $val['id_rol']  = (string) ($_POST['id_rol'] ?? '');
    $pass           = (string) ($_POST['password'] ?? ''); // la contraseña nunca se vuelve a mostrar

    if ($val['nombres'] === '' || mb_strlen($val['nombres']) > 100) {
        $error = 'El nombre es obligatorio (máximo 100 caracteres).';
    } elseif (filter_var($val['correo'], FILTER_VALIDATE_EMAIL) === false || mb_strlen($val['correo']) > 100) {
        $error = 'Ingresa un correo válido.';
    } elseif (mb_strlen($pass) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif (filter_var($val['id_rol'], FILTER_VALIDATE_INT) === false || (int) $val['id_rol'] <= 0) {
        $error = 'Selecciona un rol válido.';
    } else {
        // Encripta la contraseña con bcrypt ANTES de guardarla
        $hash   = password_hash($pass, PASSWORD_BCRYPT);
        $id_rol = (int) $val['id_rol'];

        $stmt = mysqli_prepare($conexion,
            "INSERT INTO usuarios (nombres, correo, password, id_rol) VALUES (?, ?, ?, ?)");
        // "sssi" = string, string, string, integer
        mysqli_stmt_bind_param($stmt, "sssi", $val['nombres'], $val['correo'], $hash, $id_rol);

        try {
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header("Location: index.php?msg=creado");
            exit;
        } catch (mysqli_sql_exception $e) {
            // 1062 = correo duplicado (UNIQUE)
            $error = ($e->getCode() === 1062)
                ? 'Ya existe un usuario con ese correo.'
                : 'No se pudo registrar el usuario. Verifica los datos.';
        }
    }
}

// 6. Roles para el <select>
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
    <title>PasoFirme | Nuevo Usuario</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900">

    <?php include '../includes/navbar.php'; ?>

    <main class="max-w-2xl mx-auto px-4 sm:px-6 py-8">

        <a href="index.php" class="text-xs font-semibold uppercase tracking-widest text-neutral-400 hover:text-black transition">&larr; Volver a usuarios</a>
        <h1 class="text-3xl font-black tracking-tight mt-1 mb-6">Nuevo Usuario</h1>

        <?php if ($error !== ''): ?>
            <div class="mb-6 rounded-xl border border-neutral-300 bg-neutral-100 px-4 py-3 text-sm text-neutral-800" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="registrar.php" method="POST"
              class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-6 sm:p-8 space-y-5">

            <div>
                <label for="nombres" class="<?php echo $clase_label; ?>">Nombres</label>
                <input type="text" id="nombres" name="nombres" maxlength="100" required
                       value="<?php echo htmlspecialchars($val['nombres']); ?>" class="<?php echo $clase_input; ?>">
            </div>

            <div>
                <label for="correo" class="<?php echo $clase_label; ?>">Correo</label>
                <input type="email" id="correo" name="correo" maxlength="100" required placeholder="usuario@pasofirme.com"
                       value="<?php echo htmlspecialchars($val['correo']); ?>" class="<?php echo $clase_input; ?>">
            </div>

            <div>
                <label for="password" class="<?php echo $clase_label; ?>">Contraseña</label>
                <input type="password" id="password" name="password" minlength="6" required placeholder="Mínimo 6 caracteres"
                       autocomplete="new-password" class="<?php echo $clase_input; ?>">
            </div>

            <div>
                <label for="id_rol" class="<?php echo $clase_label; ?>">Rol</label>
                <select id="id_rol" name="id_rol" required class="<?php echo $clase_input; ?>">
                    <option value="">Selecciona un rol...</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo (int) $r['id_rol']; ?>"
                            <?php echo ((string) $r['id_rol'] === $val['id_rol']) ? 'selected' : ''; ?>>
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
                    Crear usuario
                </button>
            </div>
        </form>
    </main>
</body>
</html>
