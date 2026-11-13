<?php
// ==============================================================================
// ARCHIVO: inventario/registrar.php
// DESCRIPCIÓN: Formulario para registrar una nueva talla/color en el inventario
//              (HU-02). Solo accesible para el Administrador (rol = 1).
// ==============================================================================

// 1. Inicia la sesión solo si aún no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Si no hay sesión, al login (se valida antes de imprimir HTML para que header() funcione)
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit;
}

// 3. Solo el Administrador (rol 1) puede registrar. Si no lo es, vuelve al listado
//    (inventario/index.php).
if ($_SESSION['rol'] != 1) {
    header("Location: index.php");
    exit;
}

// 4. Conexión a la BD
require_once '../includes/conexion.php';

/** @var mysqli $conexion */

// Variables para mantener lo escrito si hay un error de validación
$error = '';
$val = ['id_modelo' => '', 'talla' => '', 'color' => '', 'stock_actual' => ''];

// 5. PROCESAMIENTO DEL FORMULARIO (solo cuando se envía por POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Recibe y limpia los datos
    $val['id_modelo']    = (string) ($_POST['id_modelo'] ?? '');
    $val['talla']        = trim($_POST['talla'] ?? '');
    $val['color']        = trim($_POST['color'] ?? '');
    $val['stock_actual'] = (string) ($_POST['stock_actual'] ?? '');

    // Validaciones en el servidor (el HTML "required" se puede saltar, esto no)
    if (filter_var($val['id_modelo'], FILTER_VALIDATE_INT) === false || (int) $val['id_modelo'] <= 0) {
        $error = 'Selecciona un modelo válido.';
    } elseif ($val['talla'] === '' || mb_strlen($val['talla']) > 10) {
        $error = 'La talla es obligatoria (máximo 10 caracteres).';
    } elseif ($val['color'] === '' || mb_strlen($val['color']) > 50) {
        $error = 'El color es obligatorio (máximo 50 caracteres).';
    } elseif (filter_var($val['stock_actual'], FILTER_VALIDATE_INT) === false || (int) $val['stock_actual'] < 0) {
        $error = 'El stock debe ser un número entero mayor o igual a 0.';
    } else {
        $id_modelo = (int) $val['id_modelo'];
        $stock     = (int) $val['stock_actual'];

        // INSERT con sentencia preparada: los ? evitan la inyección SQL
        $stmt = mysqli_prepare($conexion,
            "INSERT INTO inventario (id_modelo, talla, color, stock_actual) VALUES (?, ?, ?, ?)");
        // "issi" = integer, string, string, integer
        mysqli_stmt_bind_param($stmt, "issi", $id_modelo, $val['talla'], $val['color'], $stock);

        try {
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            // Éxito: vuelve al listado
            header("Location: index.php?msg=creado");
            exit;
        } catch (mysqli_sql_exception $e) {
            // Por ejemplo, un modelo que no existe (falla la llave foránea)
            $error = 'No se pudo registrar el producto. Verifica los datos.';
        }
    }
}

// 6. Carga dinámica de modelos activos para el <select>
$modelos = mysqli_fetch_all(
    mysqli_query($conexion, "SELECT id_modelo, codigo_modelo, nombre FROM modelos WHERE estado_activo = 1 ORDER BY nombre ASC"),
    MYSQLI_ASSOC
);

// Clases Tailwind compartidas por todos los campos (evita repetir código)
$clase_input = "w-full px-4 py-3 bg-white border border-neutral-300 rounded-xl text-sm text-neutral-900 placeholder-neutral-400 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition";
$clase_label = "block text-xs font-semibold uppercase tracking-wide text-neutral-700 mb-2";
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

    <!-- Barra de navegación reutilizable -->
    <?php include '../includes/navbar.php'; ?>

    <!-- max-w-2xl: formulario angosto y centrado -->
    <main class="max-w-2xl mx-auto px-4 sm:px-6 py-8">

        <a href="index.php" class="text-xs font-semibold uppercase tracking-widest text-neutral-400 hover:text-black transition">&larr; Volver al inventario</a>
        <h1 class="text-3xl font-black tracking-tight mt-1 mb-6">Nuevo Producto</h1>

        <?php if ($error !== ''): ?>
            <div class="mb-6 rounded-xl border border-neutral-300 bg-neutral-100 px-4 py-3 text-sm text-neutral-800" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Tarjeta blanca con el formulario; method POST envía a esta misma página -->
        <form action="registrar.php" method="POST"
              class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-6 sm:p-8 space-y-5">

            <!-- Modelo: opciones cargadas desde la tabla modelos -->
            <div>
                <label for="id_modelo" class="<?php echo $clase_label; ?>">Modelo</label>
                <select id="id_modelo" name="id_modelo" required class="<?php echo $clase_input; ?>">
                    <option value="">Selecciona un modelo...</option>
                    <?php foreach ($modelos as $m): ?>
                        <option value="<?php echo (int) $m['id_modelo']; ?>"
                            <?php echo ((string) $m['id_modelo'] === $val['id_modelo']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($m['codigo_modelo'] . ' — ' . $m['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (count($modelos) === 0): ?>
                    <p class="text-xs text-neutral-500 mt-2">No hay modelos activos. Registra modelos en la base de datos primero.</p>
                <?php endif; ?>
            </div>

            <!-- grid-cols-1 en móvil, 2 columnas desde 640px (sm:) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="talla" class="<?php echo $clase_label; ?>">Talla</label>
                    <input type="text" id="talla" name="talla" maxlength="10" required placeholder="Ej: 42"
                           value="<?php echo htmlspecialchars($val['talla']); ?>" class="<?php echo $clase_input; ?>">
                </div>
                <div>
                    <label for="color" class="<?php echo $clase_label; ?>">Color</label>
                    <input type="text" id="color" name="color" maxlength="50" required placeholder="Ej: Negro"
                           value="<?php echo htmlspecialchars($val['color']); ?>" class="<?php echo $clase_input; ?>">
                </div>
            </div>

            <div>
                <label for="stock_actual" class="<?php echo $clase_label; ?>">Stock actual</label>
                <input type="number" id="stock_actual" name="stock_actual" min="0" step="1" required placeholder="0"
                       value="<?php echo htmlspecialchars($val['stock_actual']); ?>" class="<?php echo $clase_input; ?>">
            </div>

            <!-- Botones: negro redondeado (guardar) y gris (cancelar) -->
            <div class="flex flex-col-reverse sm:flex-row gap-3 pt-2">
                <a href="index.php"
                   class="flex-1 text-center px-6 py-3 border border-neutral-300 rounded-full text-sm font-semibold uppercase tracking-wide text-neutral-600 hover:border-black hover:text-black transition">
                    Cancelar
                </a>
                <button type="submit"
                        class="flex-1 bg-black text-white px-6 py-3 rounded-full text-sm font-semibold uppercase tracking-wide hover:bg-neutral-800 active:scale-[0.98] transition">
                    Guardar producto
                </button>
            </div>
        </form>
    </main>
</body>
</html>
