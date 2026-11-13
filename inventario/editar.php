<?php
// ==============================================================================
// ARCHIVO: inventario/editar.php
// DESCRIPCIÓN: Edita un registro de inventario (modelo, talla, color, stock).
//              El ID se muestra en un campo de solo lectura. Solo Administrador.
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

// 3. Solo Administrador (rol 1); los demás vuelven al listado
if ($_SESSION['rol'] != 1) {
    header("Location: index.php");
    exit;
}

// 4. Conexión a la BD
require_once '../includes/conexion.php';

/** @var mysqli $conexion */

// 5. Recibe el ID por GET y lo valida como entero positivo.
//    El ID real SIEMPRE se toma de la URL, nunca del campo del formulario,
//    así que aunque alguien modifique el input readonly, no cambia nada.
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id <= 0) {
    header("Location: index.php");
    exit;
}

$error = '';

// 6. PROCESAMIENTO DEL FORMULARIO (POST): UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_modelo = $_POST['id_modelo'] ?? '';
    $talla     = trim($_POST['talla'] ?? '');
    $color     = trim($_POST['color'] ?? '');
    $stock     = $_POST['stock_actual'] ?? '';

    // Validaciones en el servidor
    if (filter_var($id_modelo, FILTER_VALIDATE_INT) === false || (int) $id_modelo <= 0) {
        $error = 'Selecciona un modelo válido.';
    } elseif ($talla === '' || mb_strlen($talla) > 10) {
        $error = 'La talla es obligatoria (máximo 10 caracteres).';
    } elseif ($color === '' || mb_strlen($color) > 50) {
        $error = 'El color es obligatorio (máximo 50 caracteres).';
    } elseif (filter_var($stock, FILTER_VALIDATE_INT) === false || (int) $stock < 0) {
        $error = 'El stock debe ser un número entero mayor o igual a 0.';
    } else {
        $id_modelo = (int) $id_modelo;
        $stock     = (int) $stock;

        // UPDATE preparado. El ID no se modifica: solo se usa en el WHERE.
        $stmt = mysqli_prepare($conexion,
            "UPDATE inventario SET id_modelo = ?, talla = ?, color = ?, stock_actual = ? WHERE id_inventario = ?");
        // "issii" = integer, string, string, integer, integer
        mysqli_stmt_bind_param($stmt, "issii", $id_modelo, $talla, $color, $stock, $id);

        try {
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header("Location: index.php?msg=editado");
            exit;
        } catch (mysqli_sql_exception $e) {
            $error = 'No se pudo actualizar el producto. Verifica los datos.';
        }
    }

    // Si hubo error, se conservan los valores escritos
    $registro = [
        'id_inventario' => $id,
        'id_modelo'     => $_POST['id_modelo'] ?? '',
        'talla'         => $talla,
        'color'         => $color,
        'stock_actual'  => $_POST['stock_actual'] ?? '',
    ];
} else {
    // 7. GET: consulta el registro actual para rellenar el formulario
    $stmt = mysqli_prepare($conexion,
        "SELECT id_inventario, id_modelo, talla, color, stock_actual FROM inventario WHERE id_inventario = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $registro = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    // Si el ID no existe, vuelve al listado
    if (!$registro) {
        header("Location: index.php");
        exit;
    }
}

// 8. Modelos para el <select>: los activos, más el modelo actual del registro
//    (por si fue ocultado/descontinuado y debe seguir apareciendo)
$id_modelo_actual = (int) $registro['id_modelo'];
$stmt = mysqli_prepare($conexion,
    "SELECT id_modelo, codigo_modelo, nombre FROM modelos WHERE estado_activo = 1 OR id_modelo = ? ORDER BY nombre ASC");
mysqli_stmt_bind_param($stmt, "i", $id_modelo_actual);
mysqli_stmt_execute($stmt);
$modelos = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Clases Tailwind compartidas
$clase_input = "w-full px-4 py-3 bg-white border border-neutral-300 rounded-xl text-sm text-neutral-900 placeholder-neutral-400 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition";
$clase_label = "block text-xs font-semibold uppercase tracking-wide text-neutral-700 mb-2";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PasoFirme | Editar Producto</title>
    <!-- Tailwind CSS vía CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900">

    <?php include '../includes/navbar.php'; ?>

    <main class="max-w-2xl mx-auto px-4 sm:px-6 py-8">

        <a href="index.php" class="text-xs font-semibold uppercase tracking-widest text-neutral-400 hover:text-black transition">&larr; Volver al inventario</a>
        <h1 class="text-3xl font-black tracking-tight mt-1 mb-6">Editar Producto</h1>

        <?php if ($error !== ''): ?>
            <div class="mb-6 rounded-xl border border-neutral-300 bg-neutral-100 px-4 py-3 text-sm text-neutral-800" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- El action conserva el id en la URL para que el POST sepa qué registro actualizar -->
        <form action="editar.php?id=<?php echo $id; ?>" method="POST"
              class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-6 sm:p-8 space-y-5">

            <!-- ID: readonly (solo lectura), fondo gris para indicar que no se edita -->
            <div>
                <label for="id_inventario" class="<?php echo $clase_label; ?>">ID</label>
                <input type="text" id="id_inventario" value="<?php echo (int) $registro['id_inventario']; ?>" readonly
                       class="w-full px-4 py-3 bg-neutral-100 border border-neutral-200 rounded-xl text-sm text-neutral-500 font-mono cursor-not-allowed focus:outline-none">
            </div>

            <div>
                <label for="id_modelo" class="<?php echo $clase_label; ?>">Modelo</label>
                <select id="id_modelo" name="id_modelo" required class="<?php echo $clase_input; ?>">
                    <option value="">Selecciona un modelo...</option>
                    <?php foreach ($modelos as $m): ?>
                        <option value="<?php echo (int) $m['id_modelo']; ?>"
                            <?php echo ((string) $m['id_modelo'] === (string) $registro['id_modelo']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($m['codigo_modelo'] . ' — ' . $m['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="talla" class="<?php echo $clase_label; ?>">Talla</label>
                    <input type="text" id="talla" name="talla" maxlength="10" required placeholder="Ej: 42"
                           value="<?php echo htmlspecialchars($registro['talla']); ?>" class="<?php echo $clase_input; ?>">
                </div>
                <div>
                    <label for="color" class="<?php echo $clase_label; ?>">Color</label>
                    <input type="text" id="color" name="color" maxlength="50" required placeholder="Ej: Negro"
                           value="<?php echo htmlspecialchars($registro['color']); ?>" class="<?php echo $clase_input; ?>">
                </div>
            </div>

            <div>
                <label for="stock_actual" class="<?php echo $clase_label; ?>">Stock actual</label>
                <input type="number" id="stock_actual" name="stock_actual" min="0" step="1" required placeholder="0"
                       value="<?php echo htmlspecialchars((string) $registro['stock_actual']); ?>" class="<?php echo $clase_input; ?>">
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
