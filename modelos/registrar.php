<?php
// ==============================================================================
// ARCHIVO: modelos/registrar.php
// DESCRIPCIÓN: Formulario para crear un modelo en el catálogo (tabla modelos).
//              Solo Administrador (rol 1).
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

// 3. Solo Administrador (rol 1); los demás vuelven al catálogo
if ($_SESSION['rol'] != 1) {
    header("Location: index.php");
    exit;
}

// 4. Conexión a la BD
require_once '../includes/conexion.php';

/** @var mysqli $conexion */

$error = '';
$val = ['codigo_modelo' => '', 'nombre' => '', 'precio_venta' => '', 'url_imagen' => '', 'id_categoria' => ''];

// 5. PROCESAMIENTO DEL POST: INSERT en modelos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $val['codigo_modelo'] = trim($_POST['codigo_modelo'] ?? '');
    $val['nombre']        = trim($_POST['nombre'] ?? '');
    $val['precio_venta']  = trim((string) ($_POST['precio_venta'] ?? ''));
    $val['url_imagen']    = trim($_POST['url_imagen'] ?? '');
    $val['id_categoria']  = (string) ($_POST['id_categoria'] ?? '');

    if ($val['codigo_modelo'] === '' || mb_strlen($val['codigo_modelo']) > 50) {
        $error = 'El código del modelo es obligatorio (máximo 50 caracteres).';
    } elseif ($val['nombre'] === '' || mb_strlen($val['nombre']) > 150) {
        $error = 'El nombre es obligatorio (máximo 150 caracteres).';
    } elseif (!is_numeric($val['precio_venta']) || (float) $val['precio_venta'] <= 0 || (float) $val['precio_venta'] > 99999999.99) {
        $error = 'El precio debe ser un número mayor a 0.';
    } elseif ($val['url_imagen'] !== '' && (mb_strlen($val['url_imagen']) > 255 || filter_var($val['url_imagen'], FILTER_VALIDATE_URL) === false)) {
        $error = 'La URL de la imagen no es válida (máximo 255 caracteres).';
    } elseif (filter_var($val['id_categoria'], FILTER_VALIDATE_INT) === false || (int) $val['id_categoria'] <= 0) {
        $error = 'Selecciona una categoría válida.';
    } else {
        $precio = round((float) $val['precio_venta'], 2);
        $id_cat = (int) $val['id_categoria'];
        $url    = $val['url_imagen'] === '' ? null : $val['url_imagen']; // opcional -> NULL

        $stmt = mysqli_prepare($conexion,
            "INSERT INTO modelos (codigo_modelo, nombre, precio_venta, url_imagen, id_categoria) VALUES (?, ?, ?, ?, ?)");
        // "ssdsi" = string, string, double, string (acepta NULL), integer
        mysqli_stmt_bind_param($stmt, "ssdsi", $val['codigo_modelo'], $val['nombre'], $precio, $url, $id_cat);

        try {
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header("Location: index.php?msg=creado");
            exit;
        } catch (mysqli_sql_exception $e) {
            // 1062 = duplicado (codigo_modelo es UNIQUE)
            $error = ($e->getCode() === 1062)
                ? 'Ya existe un modelo con ese código.'
                : 'No se pudo crear el modelo. Verifica los datos.';
        }
    }
}

// 6. Categorías para el <select>
$categorias = mysqli_fetch_all(
    mysqli_query($conexion, "SELECT id_categoria, nombre_categoria, publico FROM categorias ORDER BY nombre_categoria ASC"),
    MYSQLI_ASSOC
);

// Clases Tailwind compartidas
$clase_input = "w-full px-4 py-3 bg-white border border-neutral-300 rounded-xl text-sm text-neutral-900 placeholder-neutral-400 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition";
$clase_label = "block text-xs font-semibold uppercase tracking-wide text-neutral-700 mb-2";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PasoFirme | Nuevo Modelo</title>
    <!-- Tailwind CSS vía CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900">

    <?php include '../includes/navbar.php'; ?>

    <main class="max-w-2xl mx-auto px-4 sm:px-6 py-8">

        <a href="index.php" class="text-xs font-semibold uppercase tracking-widest text-neutral-400 hover:text-black transition">&larr; Volver al catálogo</a>
        <h1 class="text-3xl font-black tracking-tight mt-1 mb-6">Nuevo Modelo</h1>

        <?php if ($error !== ''): ?>
            <div class="mb-6 rounded-xl border border-neutral-300 bg-neutral-100 px-4 py-3 text-sm text-neutral-800" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="registrar.php" method="POST"
              class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-6 sm:p-8 space-y-5">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="codigo_modelo" class="<?php echo $clase_label; ?>">Código del modelo</label>
                    <input type="text" id="codigo_modelo" name="codigo_modelo" maxlength="50" required placeholder="Ej: AM-270"
                           value="<?php echo htmlspecialchars($val['codigo_modelo']); ?>" class="<?php echo $clase_input; ?>">
                </div>
                <div>
                    <label for="precio_venta" class="<?php echo $clase_label; ?>">Precio de venta (Bs.)</label>
                    <input type="number" id="precio_venta" name="precio_venta" min="0.01" step="0.01" required placeholder="0.00"
                           value="<?php echo htmlspecialchars($val['precio_venta']); ?>" class="<?php echo $clase_input; ?>">
                </div>
            </div>

            <div>
                <label for="nombre" class="<?php echo $clase_label; ?>">Nombre</label>
                <input type="text" id="nombre" name="nombre" maxlength="150" required placeholder="Ej: Zapatilla Air Runner"
                       value="<?php echo htmlspecialchars($val['nombre']); ?>" class="<?php echo $clase_input; ?>">
            </div>

            <div>
                <label for="id_categoria" class="<?php echo $clase_label; ?>">Categoría</label>
                <select id="id_categoria" name="id_categoria" required class="<?php echo $clase_input; ?>">
                    <option value="">Selecciona una categoría...</option>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?php echo (int) $c['id_categoria']; ?>"
                            <?php echo ((string) $c['id_categoria'] === $val['id_categoria']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['nombre_categoria'] . ' · ' . $c['publico']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (count($categorias) === 0): ?>
                    <p class="text-xs text-neutral-500 mt-2">No hay categorías registradas en la base de datos.</p>
                <?php endif; ?>
            </div>

            <div>
                <label for="url_imagen" class="<?php echo $clase_label; ?>">URL de la imagen <span class="normal-case text-neutral-400 font-normal">(opcional)</span></label>
                <input type="url" id="url_imagen" name="url_imagen" maxlength="255" placeholder="https://..."
                       value="<?php echo htmlspecialchars($val['url_imagen']); ?>" class="<?php echo $clase_input; ?>">
            </div>

            <div class="flex flex-col-reverse sm:flex-row gap-3 pt-2">
                <a href="index.php"
                   class="flex-1 text-center px-6 py-3 border border-neutral-300 rounded-full text-sm font-semibold uppercase tracking-wide text-neutral-600 hover:border-black hover:text-black transition">
                    Cancelar
                </a>
                <button type="submit"
                        class="flex-1 bg-black text-white px-6 py-3 rounded-full text-sm font-semibold uppercase tracking-wide hover:bg-neutral-800 active:scale-[0.98] transition">
                    Crear modelo
                </button>
            </div>
        </form>
    </main>
</body>
</html>
