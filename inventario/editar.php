<?php
// ==============================================================================
// ARCHIVO: inventario/editar.php
// DESCRIPCIÓN: Edita simultáneamente DOS tablas desde un solo formulario:
//   - inventario: talla, color y stock del registro
//   - modelos   : precio_venta y url_imagen del modelo asociado
//   El ID, el código y el nombre del modelo se muestran en solo lectura.
//   Solo Administrador (rol 1).
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

// 5. Recibe el ID por GET y lo valida. El ID real SIEMPRE sale de la URL,
//    nunca de un campo del formulario.
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id <= 0) {
    header("Location: index.php");
    exit;
}

// 6. Consulta inicial con JOIN: datos de inventario + modelos (+ categoría para mostrarla)
$stmt = mysqli_prepare($conexion,
    "SELECT i.id_inventario, i.id_modelo, i.talla, i.color, i.stock_actual,
            m.codigo_modelo, m.nombre, m.precio_venta, m.url_imagen, m.id_categoria,
            c.nombre_categoria, c.publico
     FROM inventario i
     INNER JOIN modelos m    ON i.id_modelo = m.id_modelo
     INNER JOIN categorias c ON m.id_categoria = c.id_categoria
     WHERE i.id_inventario = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$registro = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Si el ID no existe, vuelve al listado
if (!$registro) {
    header("Location: index.php");
    exit;
}

$error = '';

// 7. PROCESAMIENTO DEL FORMULARIO (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $talla  = trim($_POST['talla'] ?? '');
    $color  = trim($_POST['color'] ?? '');
    $stock  = (string) ($_POST['stock_actual'] ?? '');
    $precio = trim((string) ($_POST['precio_venta'] ?? ''));
    $url    = trim($_POST['url_imagen'] ?? '');

    // Valores escritos: se muestran de nuevo si hay error
    $registro['talla']        = $talla;
    $registro['color']        = $color;
    $registro['stock_actual'] = $stock;
    $registro['precio_venta'] = $precio;
    $registro['url_imagen']   = $url;

    // Validaciones en el servidor
    if ($talla === '' || mb_strlen($talla) > 10) {
        $error = 'La talla es obligatoria (máximo 10 caracteres).';
    } elseif ($color === '' || mb_strlen($color) > 50) {
        $error = 'El color es obligatorio (máximo 50 caracteres).';
    } elseif (filter_var($stock, FILTER_VALIDATE_INT) === false || (int) $stock < 0) {
        $error = 'El stock debe ser un número entero mayor o igual a 0.';
    } elseif (!is_numeric($precio) || (float) $precio <= 0 || (float) $precio > 99999999.99) {
        $error = 'El precio debe ser un número mayor a 0.';
    } elseif ($url !== '' && (mb_strlen($url) > 255 || filter_var($url, FILTER_VALIDATE_URL) === false)) {
        $error = 'La URL de la imagen no es válida (máximo 255 caracteres).';
    } else {
        $stock_int   = (int) $stock;
        $precio_num  = round((float) $precio, 2);
        $url_guardar = $url === '' ? null : $url;      // url_imagen es opcional
        $id_modelo   = (int) $registro['id_modelo'];   // viene de la BD, no del formulario

        // TRANSACCIÓN: ambos UPDATE se aplican juntos o ninguno se aplica.
        // Así evitamos dejar los datos a medias si el segundo UPDATE falla.
        mysqli_begin_transaction($conexion);
        try {
            // UPDATE 1: tabla inventario (talla, color, stock). El ID solo va en el WHERE.
            $stmt1 = mysqli_prepare($conexion,
                "UPDATE inventario SET talla = ?, color = ?, stock_actual = ? WHERE id_inventario = ?");
            // "ssii" = string, string, integer, integer
            mysqli_stmt_bind_param($stmt1, "ssii", $talla, $color, $stock_int, $id);
            mysqli_stmt_execute($stmt1);
            mysqli_stmt_close($stmt1);

            // UPDATE 2: tabla modelos (precio y url_imagen) del modelo asociado
            $stmt2 = mysqli_prepare($conexion,
                "UPDATE modelos SET precio_venta = ?, url_imagen = ? WHERE id_modelo = ?");
            // "dsi" = double, string (acepta NULL), integer
            mysqli_stmt_bind_param($stmt2, "dsi", $precio_num, $url_guardar, $id_modelo);
            mysqli_stmt_execute($stmt2);
            mysqli_stmt_close($stmt2);

            // Todo salió bien: confirma los cambios
            mysqli_commit($conexion);
            header("Location: index.php?msg=editado");
            exit;
        } catch (mysqli_sql_exception $e) {
            // Algo falló: deshace TODO lo hecho dentro de la transacción
            mysqli_rollback($conexion);
            $error = 'No se pudo actualizar el producto. No se guardó ningún cambio.';
        }
    }
}

// Clases Tailwind compartidas
$clase_input = "w-full px-4 py-3 bg-white border border-neutral-300 rounded-xl text-sm text-neutral-900 placeholder-neutral-400 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition";
$clase_ro    = "w-full px-4 py-3 bg-neutral-100 border border-neutral-200 rounded-xl text-sm text-neutral-500 cursor-not-allowed focus:outline-none";
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

        <!-- El action conserva el id en la URL para saber qué registro actualizar -->
        <form action="editar.php?id=<?php echo $id; ?>" method="POST" class="space-y-6">

            <!-- ============ BLOQUE 1: DATOS DEL MODELO ============ -->
            <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-6 sm:p-8 space-y-5">
                <h2 class="text-sm font-black uppercase tracking-widest text-neutral-400">Modelo (afecta a todas sus tallas y colores)</h2>

                <!-- Solo lectura: cambiar nombre/código afectaría a todo el inventario del modelo -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <div>
                        <label class="<?php echo $clase_label; ?>">ID</label>
                        <input type="text" value="<?php echo (int) $registro['id_inventario']; ?>" readonly class="<?php echo $clase_ro; ?> font-mono">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="<?php echo $clase_label; ?>">Código del modelo</label>
                        <input type="text" value="<?php echo htmlspecialchars($registro['codigo_modelo']); ?>" readonly class="<?php echo $clase_ro; ?>">
                    </div>
                </div>

                <div>
                    <label class="<?php echo $clase_label; ?>">Nombre del modelo</label>
                    <input type="text" value="<?php echo htmlspecialchars($registro['nombre']); ?>" readonly class="<?php echo $clase_ro; ?>">
                </div>

                <div>
                    <label class="<?php echo $clase_label; ?>">Categoría</label>
                    <input type="text" value="<?php echo htmlspecialchars($registro['nombre_categoria'] . ' · ' . $registro['publico']); ?>" readonly class="<?php echo $clase_ro; ?>">
                </div>

                <!-- Editables (se guardan en la tabla modelos) -->
                <div>
                    <label for="precio_venta" class="<?php echo $clase_label; ?>">Precio de venta (Bs.)</label>
                    <input type="number" id="precio_venta" name="precio_venta" min="0.01" step="0.01" required
                           value="<?php echo htmlspecialchars((string) $registro['precio_venta']); ?>" class="<?php echo $clase_input; ?>">
                </div>

                <div>
                    <label for="url_imagen" class="<?php echo $clase_label; ?>">URL de la imagen <span class="normal-case text-neutral-400 font-normal">(opcional)</span></label>
                    <input type="url" id="url_imagen" name="url_imagen" maxlength="255" placeholder="https://..."
                           value="<?php echo htmlspecialchars((string) ($registro['url_imagen'] ?? '')); ?>" class="<?php echo $clase_input; ?>">
                </div>
            </div>

            <!-- ============ BLOQUE 2: DATOS DEL INVENTARIO ============ -->
            <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-6 sm:p-8 space-y-5">
                <h2 class="text-sm font-black uppercase tracking-widest text-neutral-400">Inventario (este registro)</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="talla" class="<?php echo $clase_label; ?>">Talla</label>
                        <input type="text" id="talla" name="talla" maxlength="10" required
                               value="<?php echo htmlspecialchars($registro['talla']); ?>" class="<?php echo $clase_input; ?>">
                    </div>
                    <div>
                        <label for="color" class="<?php echo $clase_label; ?>">Color</label>
                        <input type="text" id="color" name="color" maxlength="50" required
                               value="<?php echo htmlspecialchars($registro['color']); ?>" class="<?php echo $clase_input; ?>">
                    </div>
                </div>

                <div>
                    <label for="stock_actual" class="<?php echo $clase_label; ?>">Stock actual</label>
                    <input type="number" id="stock_actual" name="stock_actual" min="0" step="1" required
                           value="<?php echo htmlspecialchars((string) $registro['stock_actual']); ?>" class="<?php echo $clase_input; ?>">
                </div>
            </div>

            <div class="flex flex-col-reverse sm:flex-row gap-3">
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
