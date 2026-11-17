<?php
// ==============================================================================
// ARCHIVO: inventario/registrar.php
// DESCRIPCIÓN: Registro en dos pestañas (HU-02). Solo accesible para el Administrador:
//   1) Agregar talla/color/stock a un modelo existente  -> INSERT en inventario
//   2) Crear un nuevo modelo del catálogo               -> INSERT en modelos
// ==============================================================================

// 1. Inicia la sesión solo si aún no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Sin sesión -> login (antes de imprimir HTML para que header() funcione)
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

// Estado del formulario: pestaña activa, mensaje de error y valores escritos
$tab_activa = 'inventario';
$error = '';
$inv = ['id_modelo' => '', 'talla' => '', 'color' => '', 'stock_actual' => ''];
$mod = ['codigo_modelo' => '', 'nombre' => '', 'precio_venta' => '', 'url_imagen' => '', 'id_categoria' => ''];

// ------------------------------------------------------------------------------
// 5A. ENVÍO DEL FORMULARIO 1: agregar a modelo existente
//     Se identifica porque el botón submit se llama "guardar_inventario".
// ------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_inventario'])) {
    $tab_activa = 'inventario';

    $inv['id_modelo']    = (string) ($_POST['id_modelo'] ?? '');
    $inv['talla']        = trim($_POST['talla'] ?? '');
    $inv['color']        = trim($_POST['color'] ?? '');
    $inv['stock_actual'] = (string) ($_POST['stock_actual'] ?? '');

    if (filter_var($inv['id_modelo'], FILTER_VALIDATE_INT) === false || (int) $inv['id_modelo'] <= 0) {
        $error = 'Selecciona un modelo válido.';
    } elseif ($inv['talla'] === '' || mb_strlen($inv['talla']) > 10) {
        $error = 'La talla es obligatoria (máximo 10 caracteres).';
    } elseif ($inv['color'] === '' || mb_strlen($inv['color']) > 50) {
        $error = 'El color es obligatorio (máximo 50 caracteres).';
    } elseif (filter_var($inv['stock_actual'], FILTER_VALIDATE_INT) === false || (int) $inv['stock_actual'] < 0) {
        $error = 'El stock debe ser un número entero mayor o igual a 0.';
    } else {
        $id_modelo = (int) $inv['id_modelo'];
        $stock     = (int) $inv['stock_actual'];

        $stmt = mysqli_prepare($conexion,
            "INSERT INTO inventario (id_modelo, talla, color, stock_actual) VALUES (?, ?, ?, ?)");
        // "issi" = integer, string, string, integer
        mysqli_stmt_bind_param($stmt, "issi", $id_modelo, $inv['talla'], $inv['color'], $stock);

        try {
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header("Location: index.php?msg=creado");
            exit;
        } catch (mysqli_sql_exception $e) {
            $error = 'No se pudo registrar el producto. Verifica los datos.';
        }
    }
}

// ------------------------------------------------------------------------------
// 5B. ENVÍO DEL FORMULARIO 2: crear nuevo modelo
//     Se identifica porque el botón submit se llama "guardar_modelo".
// ------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_modelo'])) {
    $tab_activa = 'modelo';

    $mod['codigo_modelo'] = trim($_POST['codigo_modelo'] ?? '');
    $mod['nombre']        = trim($_POST['nombre'] ?? '');
    $mod['precio_venta']  = trim((string) ($_POST['precio_venta'] ?? ''));
    $mod['url_imagen']    = trim($_POST['url_imagen'] ?? '');
    $mod['id_categoria']  = (string) ($_POST['id_categoria'] ?? '');

    if ($mod['codigo_modelo'] === '' || mb_strlen($mod['codigo_modelo']) > 50) {
        $error = 'El código del modelo es obligatorio (máximo 50 caracteres).';
    } elseif ($mod['nombre'] === '' || mb_strlen($mod['nombre']) > 150) {
        $error = 'El nombre es obligatorio (máximo 150 caracteres).';
    } elseif (!is_numeric($mod['precio_venta']) || (float) $mod['precio_venta'] <= 0 || (float) $mod['precio_venta'] > 99999999.99) {
        $error = 'El precio debe ser un número mayor a 0.';
    } elseif ($mod['url_imagen'] !== '' && (mb_strlen($mod['url_imagen']) > 255 || filter_var($mod['url_imagen'], FILTER_VALIDATE_URL) === false)) {
        $error = 'La URL de la imagen no es válida (máximo 255 caracteres).';
    } elseif (filter_var($mod['id_categoria'], FILTER_VALIDATE_INT) === false || (int) $mod['id_categoria'] <= 0) {
        $error = 'Selecciona una categoría válida.';
    } else {
        $precio    = round((float) $mod['precio_venta'], 2);
        $id_cat    = (int) $mod['id_categoria'];
        // url_imagen es opcional: si está vacía se guarda NULL
        $url       = $mod['url_imagen'] === '' ? null : $mod['url_imagen'];

        $stmt = mysqli_prepare($conexion,
            "INSERT INTO modelos (codigo_modelo, nombre, precio_venta, url_imagen, id_categoria) VALUES (?, ?, ?, ?, ?)");
        // "ssdsi" = string, string, double, string (acepta NULL), integer
        mysqli_stmt_bind_param($stmt, "ssdsi", $mod['codigo_modelo'], $mod['nombre'], $precio, $url, $id_cat);

        try {
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header("Location: index.php?msg=modelo_creado");
            exit;
        } catch (mysqli_sql_exception $e) {
            // 1062 = valor duplicado (codigo_modelo es UNIQUE)
            $error = ($e->getCode() === 1062)
                ? 'Ya existe un modelo con ese código.'
                : 'No se pudo crear el modelo. Verifica los datos.';
        }
    }
}

// 6. Datos para los <select>
$modelos = mysqli_fetch_all(
    mysqli_query($conexion, "SELECT id_modelo, codigo_modelo, nombre FROM modelos WHERE estado_activo = 1 ORDER BY nombre ASC"),
    MYSQLI_ASSOC
);
$categorias = mysqli_fetch_all(
    mysqli_query($conexion, "SELECT id_categoria, nombre_categoria, publico FROM categorias ORDER BY nombre_categoria ASC"),
    MYSQLI_ASSOC
);

// Clases Tailwind compartidas
$clase_input = "w-full px-4 py-3 bg-white border border-neutral-300 rounded-xl text-sm text-neutral-900 placeholder-neutral-400 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition";
$clase_label = "block text-xs font-semibold uppercase tracking-wide text-neutral-700 mb-2";
$clase_btn   = "flex-1 bg-black text-white px-6 py-3 rounded-full text-sm font-semibold uppercase tracking-wide hover:bg-neutral-800 active:scale-[0.98] transition";
$clase_cancel = "flex-1 text-center px-6 py-3 border border-neutral-300 rounded-full text-sm font-semibold uppercase tracking-wide text-neutral-600 hover:border-black hover:text-black transition";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PasoFirme | Nuevo Producto</title>
    <!-- Tailwind CSS vía CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900">

    <?php include '../includes/navbar.php'; ?>

    <main class="max-w-2xl mx-auto px-4 sm:px-6 py-8">

        <a href="index.php" class="text-xs font-semibold uppercase tracking-widest text-neutral-400 hover:text-black transition">&larr; Volver al inventario</a>
        <h1 class="text-3xl font-black tracking-tight mt-1 mb-6">Nuevo Producto</h1>

        <!-- ================= PESTAÑAS ================= -->
        <!-- Cada botón tiene data-tab; el JavaScript del final muestra el panel correspondiente -->
        <div class="flex gap-2 mb-6 p-1 bg-neutral-200 rounded-full">
            <button type="button" data-tab="inventario"
                    class="tab-btn flex-1 px-4 py-2.5 rounded-full text-xs sm:text-sm font-semibold uppercase tracking-wide transition">
                Agregar a Modelo Existente
            </button>
            <button type="button" data-tab="modelo"
                    class="tab-btn flex-1 px-4 py-2.5 rounded-full text-xs sm:text-sm font-semibold uppercase tracking-wide transition">
                Crear Nuevo Modelo
            </button>
        </div>

        <?php if ($error !== ''): ?>
            <div class="mb-6 rounded-xl border border-neutral-300 bg-neutral-100 px-4 py-3 text-sm text-neutral-800" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- ================= PANEL 1: AGREGAR A MODELO EXISTENTE ================= -->
        <section id="panel-inventario" class="tab-panel hidden">
            <form action="registrar.php" method="POST"
                  class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-6 sm:p-8 space-y-5">

                <div>
                    <label for="id_modelo" class="<?php echo $clase_label; ?>">Modelo</label>
                    <select id="id_modelo" name="id_modelo" required class="<?php echo $clase_input; ?>">
                        <option value="">Selecciona un modelo...</option>
                        <?php foreach ($modelos as $m): ?>
                            <option value="<?php echo (int) $m['id_modelo']; ?>"
                                <?php echo ((string) $m['id_modelo'] === $inv['id_modelo']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['codigo_modelo'] . ' — ' . $m['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (count($modelos) === 0): ?>
                        <p class="text-xs text-neutral-500 mt-2">No hay modelos activos. Crea uno en la pestaña «Crear Nuevo Modelo».</p>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="talla" class="<?php echo $clase_label; ?>">Talla</label>
                        <input type="text" id="talla" name="talla" maxlength="10" required placeholder="Ej: 42"
                               value="<?php echo htmlspecialchars($inv['talla']); ?>" class="<?php echo $clase_input; ?>">
                    </div>
                    <div>
                        <label for="color" class="<?php echo $clase_label; ?>">Color</label>
                        <input type="text" id="color" name="color" maxlength="50" required placeholder="Ej: Negro"
                               value="<?php echo htmlspecialchars($inv['color']); ?>" class="<?php echo $clase_input; ?>">
                    </div>
                </div>

                <div>
                    <label for="stock_actual" class="<?php echo $clase_label; ?>">Stock actual</label>
                    <input type="number" id="stock_actual" name="stock_actual" min="0" step="1" required placeholder="0"
                           value="<?php echo htmlspecialchars($inv['stock_actual']); ?>" class="<?php echo $clase_input; ?>">
                </div>

                <div class="flex flex-col-reverse sm:flex-row gap-3 pt-2">
                    <a href="index.php" class="<?php echo $clase_cancel; ?>">Cancelar</a>
                    <!-- name="guardar_inventario": así el PHP sabe qué formulario se envió -->
                    <button type="submit" name="guardar_inventario" class="<?php echo $clase_btn; ?>">Guardar producto</button>
                </div>
            </form>
        </section>

        <!-- ================= PANEL 2: CREAR NUEVO MODELO ================= -->
        <section id="panel-modelo" class="tab-panel hidden">
            <form action="registrar.php" method="POST"
                  class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-6 sm:p-8 space-y-5">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="codigo_modelo" class="<?php echo $clase_label; ?>">Código del modelo</label>
                        <input type="text" id="codigo_modelo" name="codigo_modelo" maxlength="50" required placeholder="Ej: AM-270"
                               value="<?php echo htmlspecialchars($mod['codigo_modelo']); ?>" class="<?php echo $clase_input; ?>">
                    </div>
                    <div>
                        <label for="precio_venta" class="<?php echo $clase_label; ?>">Precio de venta (Bs.)</label>
                        <input type="number" id="precio_venta" name="precio_venta" min="0.01" step="0.01" required placeholder="0.00"
                               value="<?php echo htmlspecialchars($mod['precio_venta']); ?>" class="<?php echo $clase_input; ?>">
                    </div>
                </div>

                <div>
                    <label for="nombre" class="<?php echo $clase_label; ?>">Nombre</label>
                    <input type="text" id="nombre" name="nombre" maxlength="150" required placeholder="Ej: Zapatilla Air Runner"
                           value="<?php echo htmlspecialchars($mod['nombre']); ?>" class="<?php echo $clase_input; ?>">
                </div>

                <div>
                    <label for="id_categoria" class="<?php echo $clase_label; ?>">Categoría</label>
                    <select id="id_categoria" name="id_categoria" required class="<?php echo $clase_input; ?>">
                        <option value="">Selecciona una categoría...</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?php echo (int) $c['id_categoria']; ?>"
                                <?php echo ((string) $c['id_categoria'] === $mod['id_categoria']) ? 'selected' : ''; ?>>
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
                           value="<?php echo htmlspecialchars($mod['url_imagen']); ?>" class="<?php echo $clase_input; ?>">
                </div>

                <div class="flex flex-col-reverse sm:flex-row gap-3 pt-2">
                    <a href="index.php" class="<?php echo $clase_cancel; ?>">Cancelar</a>
                    <!-- name="guardar_modelo": identifica este formulario en el PHP -->
                    <button type="submit" name="guardar_modelo" class="<?php echo $clase_btn; ?>">Crear modelo</button>
                </div>
            </form>
        </section>
    </main>

    <!-- ========== JAVASCRIPT: cambio de pestañas ========== -->
    <script>
        // La pestaña inicial la decide PHP (si hubo error, se reabre la pestaña donde ocurrió)
        const pestanaInicial = <?php echo json_encode($tab_activa); ?>;
        const botones = document.querySelectorAll('.tab-btn');
        const paneles = document.querySelectorAll('.tab-panel');

        function mostrarPestana(nombre) {
            // Muestra solo el panel elegido
            paneles.forEach(function (p) {
                p.classList.toggle('hidden', p.id !== 'panel-' + nombre);
            });
            // Estilo de los botones: activo = negro, inactivo = gris
            botones.forEach(function (b) {
                const activo = b.dataset.tab === nombre;
                b.classList.toggle('bg-black', activo);
                b.classList.toggle('text-white', activo);
                b.classList.toggle('text-neutral-600', !activo);
            });
        }

        botones.forEach(function (b) {
            b.addEventListener('click', function () { mostrarPestana(b.dataset.tab); });
        });

        mostrarPestana(pestanaInicial);
    </script>
</body>
</html>
