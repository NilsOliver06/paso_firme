<?php
// ==============================================================================
// ARCHIVO: clientes/registrar.php
// DESCRIPCIÓN: Registra un cliente. Acceso para Administrador y Vendedor.
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

$error = '';
$val = ['nombre_completo' => '', 'telefono' => '', 'nit_ci' => ''];

// 4. POST: validación estricta + INSERT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $val['nombre_completo'] = trim($_POST['nombre_completo'] ?? '');
    $val['telefono']        = trim($_POST['telefono'] ?? '');
    $val['nit_ci']          = trim($_POST['nit_ci'] ?? '');

    if ($val['nombre_completo'] === '' || mb_strlen($val['nombre_completo']) > 150) {
        $error = 'El nombre completo es obligatorio (máximo 150 caracteres).';
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u', $val['nombre_completo'])) {
        $error = 'El nombre completo solo debe contener letras y espacios (sin números ni símbolos).';
    } elseif ($val['telefono'] !== '' && (mb_strlen($val['telefono']) > 20 || !preg_match('/^[0-9]+$/', $val['telefono']))) {
        $error = 'El teléfono solo debe contener números (máximo 20 dígitos).';
    } elseif ($val['nit_ci'] !== '' && (mb_strlen($val['nit_ci']) > 30 || !preg_match('/^[0-9-]+$/', $val['nit_ci']))) {
        $error = 'El NIT/CI solo debe contener números y guiones (máximo 30 caracteres).';
    } else {
        // Los campos opcionales vacíos se guardan como NULL
        $telefono = $val['telefono'] === '' ? null : $val['telefono'];
        $nit_ci   = $val['nit_ci'] === '' ? null : $val['nit_ci'];

        $stmt = mysqli_prepare($conexion,
            "INSERT INTO clientes (nombre_completo, telefono, nit_ci) VALUES (?, ?, ?)");
        // "sss" = tres strings (los opcionales aceptan NULL)
        mysqli_stmt_bind_param($stmt, "sss", $val['nombre_completo'], $telefono, $nit_ci);

        try {
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header("Location: index.php?msg=creado");
            exit;
        } catch (mysqli_sql_exception $e) {
            $error = 'No se pudo registrar el cliente. Verifica los datos.';
        }
    }
}

$clase_input = "w-full px-4 py-3 bg-white border border-neutral-300 rounded-xl text-sm text-neutral-900 placeholder-neutral-400 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition";
$clase_label = "block text-xs font-semibold uppercase tracking-wide text-neutral-700 mb-2";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PasoFirme | Nuevo Cliente</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900">

    <?php include '../includes/navbar.php'; ?>

    <main class="max-w-2xl mx-auto px-4 sm:px-6 py-8">

        <a href="index.php" class="text-xs font-semibold uppercase tracking-widest text-neutral-400 hover:text-black transition">&larr; Volver a clientes</a>
        <h1 class="text-3xl font-black tracking-tight mt-1 mb-6">Nuevo Cliente</h1>

        <?php if ($error !== ''): ?>
            <div class="mb-6 rounded-xl border border-neutral-300 bg-neutral-100 px-4 py-3 text-sm text-neutral-800" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="registrar.php" method="POST"
              class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-6 sm:p-8 space-y-5">

            <div>
                <label for="nombre_completo" class="<?php echo $clase_label; ?>">Nombre completo</label>
                <input type="text" id="nombre_completo" name="nombre_completo" maxlength="150" required
                       pattern="^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$"
                       title="Solo se permiten letras y espacios"
                       placeholder="Ej: María López"
                       value="<?php echo htmlspecialchars($val['nombre_completo']); ?>" class="<?php echo $clase_input; ?>">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="telefono" class="<?php echo $clase_label; ?>">Teléfono <span class="normal-case text-neutral-400 font-normal">(opcional)</span></label>
                    <input type="text" id="telefono" name="telefono" maxlength="20" inputmode="numeric"
                           pattern="^[0-9]+$"
                           title="Solo se permiten números"
                           placeholder="Ej: 70012345"
                           value="<?php echo htmlspecialchars($val['telefono']); ?>" class="<?php echo $clase_input; ?>">
                </div>
                <div>
                    <label for="nit_ci" class="<?php echo $clase_label; ?>">NIT / CI <span class="normal-case text-neutral-400 font-normal">(opcional)</span></label>
                    <input type="text" id="nit_ci" name="nit_ci" maxlength="30"
                           pattern="^[0-9\-]+$"
                           title="Solo se permiten números y guiones"
                           placeholder="Ej: 1234567-1"
                           value="<?php echo htmlspecialchars($val['nit_ci']); ?>" class="<?php echo $clase_input; ?>">
                </div>
            </div>

            <div class="flex flex-col-reverse sm:flex-row gap-3 pt-2">
                <a href="index.php"
                   class="flex-1 text-center px-6 py-3 border border-neutral-300 rounded-full text-sm font-semibold uppercase tracking-wide text-neutral-600 hover:border-black hover:text-black transition">
                    Cancelar
                </a>
                <button type="submit"
                        class="flex-1 bg-black text-white px-6 py-3 rounded-full text-sm font-semibold uppercase tracking-wide hover:bg-neutral-800 active:scale-[0.98] transition">
                    Guardar cliente
                </button>
            </div>
        </form>
    </main>
</body>
</html>
