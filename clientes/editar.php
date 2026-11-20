<?php
// ==============================================================================
// ARCHIVO: clientes/editar.php
// DESCRIPCIÓN: Edita un cliente. Acceso para Administrador y Vendedor.
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

// 4. ID por GET (el ID real siempre sale de la URL)
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id <= 0) {
    header("Location: index.php");
    exit;
}

// 5. Carga el cliente actual
$stmt = mysqli_prepare($conexion,
    "SELECT id_cliente, nombre_completo, telefono, nit_ci FROM clientes WHERE id_cliente = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$cliente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$cliente) {
    header("Location: index.php");
    exit;
}

$error = '';

// 6. POST: validación estricta + UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente['nombre_completo'] = trim($_POST['nombre_completo'] ?? '');
    $cliente['telefono']        = trim($_POST['telefono'] ?? '');
    $cliente['nit_ci']          = trim($_POST['nit_ci'] ?? '');

    if ($cliente['nombre_completo'] === '' || mb_strlen($cliente['nombre_completo']) > 150) {
        $error = 'El nombre completo es obligatorio (máximo 150 caracteres).';
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u', $cliente['nombre_completo'])) {
        $error = 'El nombre completo solo debe contener letras y espacios (sin números ni símbolos).';
    } elseif ($cliente['telefono'] !== '' && (mb_strlen($cliente['telefono']) > 20 || !preg_match('/^[0-9]+$/', $cliente['telefono']))) {
        $error = 'El teléfono solo debe contener números (máximo 20 dígitos).';
    } elseif ($cliente['nit_ci'] !== '' && (mb_strlen($cliente['nit_ci']) > 30 || !preg_match('/^[0-9-]+$/', $cliente['nit_ci']))) {
        $error = 'El NIT/CI solo debe contener números y guiones (máximo 30 caracteres).';
    } else {
        $telefono = $cliente['telefono'] === '' ? null : $cliente['telefono'];
        $nit_ci   = $cliente['nit_ci'] === '' ? null : $cliente['nit_ci'];

        $stmt = mysqli_prepare($conexion,
            "UPDATE clientes SET nombre_completo = ?, telefono = ?, nit_ci = ? WHERE id_cliente = ?");
        // "sssi" = string, string (NULL), string (NULL), integer
        mysqli_stmt_bind_param($stmt, "sssi", $cliente['nombre_completo'], $telefono, $nit_ci, $id);

        try {
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header("Location: index.php?msg=editado");
            exit;
        } catch (mysqli_sql_exception $e) {
            $error = 'No se pudo actualizar el cliente. Verifica los datos.';
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
    <title>PasoFirme | Editar Cliente</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900">

    <?php include '../includes/navbar.php'; ?>

    <main class="max-w-2xl mx-auto px-4 sm:px-6 py-8">

        <a href="index.php" class="text-xs font-semibold uppercase tracking-widest text-neutral-400 hover:text-black transition">&larr; Volver a clientes</a>
        <h1 class="text-3xl font-black tracking-tight mt-1 mb-6">Editar Cliente</h1>

        <?php if ($error !== ''): ?>
            <div class="mb-6 rounded-xl border border-neutral-300 bg-neutral-100 px-4 py-3 text-sm text-neutral-800" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="editar.php?id=<?php echo $id; ?>" method="POST"
              class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-6 sm:p-8 space-y-5">

            <div>
                <label for="nombre_completo" class="<?php echo $clase_label; ?>">Nombre completo</label>
                <input type="text" id="nombre_completo" name="nombre_completo" maxlength="150" required
                       pattern="^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$"
                       title="Solo se permiten letras y espacios"
                       value="<?php echo htmlspecialchars($cliente['nombre_completo']); ?>" class="<?php echo $clase_input; ?>">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="telefono" class="<?php echo $clase_label; ?>">Teléfono <span class="normal-case text-neutral-400 font-normal">(opcional)</span></label>
                    <input type="text" id="telefono" name="telefono" maxlength="20" inputmode="numeric"
                           pattern="^[0-9]+$"
                           title="Solo se permiten números"
                           value="<?php echo htmlspecialchars((string) ($cliente['telefono'] ?? '')); ?>" class="<?php echo $clase_input; ?>">
                </div>
                <div>
                    <label for="nit_ci" class="<?php echo $clase_label; ?>">NIT / CI <span class="normal-case text-neutral-400 font-normal">(opcional)</span></label>
                    <input type="text" id="nit_ci" name="nit_ci" maxlength="30"
                           pattern="^[0-9\-]+$"
                           title="Solo se permiten números y guiones"
                           value="<?php echo htmlspecialchars((string) ($cliente['nit_ci'] ?? '')); ?>" class="<?php echo $clase_input; ?>">
                </div>
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
