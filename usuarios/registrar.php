<?php
// ==============================================================================
// ARCHIVO: usuarios/registrar.php
// DESCRIPCIÓN: Crea un usuario generando automáticamente el correo institucional
//              con el formato: inicial_rol.ci@pasofirme.com (ej. a.1234567@pasofirme.com).
//              La contraseña se guarda encriptada con bcrypt. Solo Administrador (rol 1).
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
$val = ['nombres' => '', 'ci' => '', 'correo' => '', 'id_rol' => ''];

// 5. POST: validación + generación de correo institucional + INSERT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $val['nombres'] = trim($_POST['nombres'] ?? '');
    $val['ci']      = trim($_POST['ci'] ?? '');
    $val['id_rol']  = (string) ($_POST['id_rol'] ?? '');
    $pass           = (string) ($_POST['password'] ?? '');

    if ($val['nombres'] === '' || mb_strlen($val['nombres']) > 100) {
        $error = 'El nombre es obligatorio (máximo 100 caracteres).';
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u', $val['nombres'])) {
        $error = 'El campo nombres solo debe contener letras y espacios (sin números ni símbolos).';
    } elseif ($val['ci'] === '' || mb_strlen($val['ci']) > 20 || !preg_match('/^[0-9]+$/', $val['ci'])) {
        $error = 'El Carnet de Identidad (CI) es obligatorio y solo debe contener números (máximo 20 dígitos).';
    } elseif (mb_strlen($pass) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif (filter_var($val['id_rol'], FILTER_VALIDATE_INT) === false || (int) $val['id_rol'] <= 0) {
        $error = 'Selecciona un rol válido.';
    } else {
        $id_rol = (int) $val['id_rol'];

        // 5.1 Consulta el nombre_rol según el id_rol elegido
        $stmt_rol = mysqli_prepare($conexion, "SELECT nombre_rol FROM roles WHERE id_rol = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt_rol, "i", $id_rol);
        mysqli_stmt_execute($stmt_rol);
        $fila_rol = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_rol));
        mysqli_stmt_close($stmt_rol);

        if (!$fila_rol) {
            $error = 'El rol seleccionado no existe.';
        } else {
            // 5.2 Genera SIEMPRE el correo en el servidor: inicial_rol.ci@pasofirme.com
            $inicial_rol   = strtolower(substr(trim($fila_rol['nombre_rol']), 0, 1));
            $correo_gen    = $inicial_rol . '.' . $val['ci'] . '@pasofirme.com';
            $val['correo'] = $correo_gen;

            // 5.3 Valida que ni el CI ni el correo generado existan antes del INSERT
            $stmt_dup = mysqli_prepare($conexion,
                "SELECT ci, correo FROM usuarios WHERE ci = ? OR correo = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt_dup, "ss", $val['ci'], $correo_gen);
            mysqli_stmt_execute($stmt_dup);
            $duplicado = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_dup));
            mysqli_stmt_close($stmt_dup);

            if ($duplicado) {
                if ((string) $duplicado['ci'] === $val['ci']) {
                    $error = 'Ya existe un usuario registrado con ese Carnet de Identidad (CI).';
                } else {
                    $error = 'Ya existe un usuario con el correo institucional ' . $correo_gen . '.';
                }
            } else {
                // 5.4 Encripta la contraseña con bcrypt ANTES de guardarla
                $hash = password_hash($pass, PASSWORD_BCRYPT);

                $stmt = mysqli_prepare($conexion,
                    "INSERT INTO usuarios (nombres, ci, correo, password, id_rol) VALUES (?, ?, ?, ?, ?)");
                // "ssssi" = string, string, string, string, integer
                mysqli_stmt_bind_param($stmt, "ssssi", $val['nombres'], $val['ci'], $correo_gen, $hash, $id_rol);

                try {
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    header("Location: index.php?msg=creado");
                    exit;
                } catch (mysqli_sql_exception $e) {
                    $error = ($e->getCode() === 1062)
                        ? 'El CI o el correo generado ya se encuentran registrados.'
                        : 'No se pudo registrar el usuario. Verifica los datos.';
                }
            }
        }
    }
}

// 6. Roles para el <select>
$roles = mysqli_fetch_all(
    mysqli_query($conexion, "SELECT id_rol, nombre_rol FROM roles ORDER BY id_rol ASC"),
    MYSQLI_ASSOC
);

$clase_input = "w-full px-4 py-3 bg-white border border-neutral-300 rounded-xl text-sm text-neutral-900 placeholder-neutral-400 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition";
$clase_ro    = "w-full px-4 py-3 bg-neutral-100 border border-neutral-200 rounded-xl text-sm text-neutral-600 font-mono cursor-not-allowed focus:outline-none";
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
                       pattern="^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$"
                       title="Solo se permiten letras y espacios"
                       placeholder="Ej: Armando Mendoza"
                       value="<?php echo htmlspecialchars($val['nombres']); ?>" class="<?php echo $clase_input; ?>">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="ci" class="<?php echo $clase_label; ?>">Carnet de Identidad (CI)</label>
                    <input type="text" id="ci" name="ci" maxlength="20" inputmode="numeric" required
                           pattern="^[0-9]+$"
                           title="Solo se permiten números"
                           placeholder="Ej: 1234567"
                           value="<?php echo htmlspecialchars($val['ci']); ?>" class="<?php echo $clase_input; ?>">
                </div>

                <div>
                    <label for="id_rol" class="<?php echo $clase_label; ?>">Rol</label>
                    <select id="id_rol" name="id_rol" required class="<?php echo $clase_input; ?>">
                        <option value="" data-inicial="">Selecciona un rol...</option>
                        <?php foreach ($roles as $r):
                            $inicial = strtolower(substr(trim($r['nombre_rol']), 0, 1));
                        ?>
                            <option value="<?php echo (int) $r['id_rol']; ?>"
                                    data-inicial="<?php echo htmlspecialchars($inicial); ?>"
                                <?php echo ((string) $r['id_rol'] === $val['id_rol']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($r['nombre_rol']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Correo institucional: readonly, se previsualiza en JS y se genera en PHP -->
            <div>
                <label for="correo" class="<?php echo $clase_label; ?>">
                    Correo institucional <span class="normal-case text-neutral-400 font-normal">(generado automáticamente)</span>
                </label>
                <input type="email" id="correo" name="correo" readonly
                       placeholder="Se generará al ingresar CI y Rol (ej. a.1234567@pasofirme.com)"
                       value="<?php echo htmlspecialchars($val['correo']); ?>" class="<?php echo $clase_ro; ?>">
            </div>

            <div>
                <label for="password" class="<?php echo $clase_label; ?>">Contraseña</label>
                <input type="password" id="password" name="password" minlength="6" required placeholder="Mínimo 6 caracteres"
                       autocomplete="new-password" class="<?php echo $clase_input; ?>">
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

    <!-- ========== JAVASCRIPT: vista previa en tiempo real del correo institucional ========== -->
    <script>
        const inputCi     = document.getElementById('ci');
        const selectRol   = document.getElementById('id_rol');
        const inputCorreo = document.getElementById('correo');

        function actualizarCorreo() {
            // Limpia cualquier carácter no numérico para la vista previa
            const ci = inputCi.value.replace(/\D+/g, '');
            const opcion = selectRol.options[selectRol.selectedIndex];
            const inicial = opcion ? (opcion.dataset.inicial || '') : '';

            if (ci !== '' && inicial !== '') {
                inputCorreo.value = inicial + '.' + ci + '@pasofirme.com';
            } else {
                inputCorreo.value = '';
            }
        }

        inputCi.addEventListener('input', actualizarCorreo);
        selectRol.addEventListener('change', actualizarCorreo);
        actualizarCorreo();
    </script>
</body>
</html>
