<?php
// ==============================================================================
// ARCHIVO: inventario/detalle.php
// DESCRIPCIÓN: Ficha de detalle de un calzado (modelo + talla/color/stock).
//              Accesible para Administrador y Vendedor.
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

// 3. ¿Es administrador? (controla la visibilidad del botón "Editar Producto")
$es_admin = ($_SESSION['rol'] == 1);

// 4. Conexión a la BD
require_once '../includes/conexion.php';

/** @var mysqli $conexion */

// 5. Recibe el ID por GET y lo valida como entero positivo
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id <= 0) {
    header("Location: index.php");
    exit;
}

// 6. Consulta con JOIN: inventario -> modelos -> categorias (consulta preparada)
$stmt = mysqli_prepare($conexion,
    "SELECT i.id_inventario, i.talla, i.color, i.stock_actual, i.estado_activo, i.fecha_actualizacion,
            m.codigo_modelo, m.nombre AS modelo, m.precio_venta, m.url_imagen,
            c.nombre_categoria, c.publico
     FROM inventario i
     INNER JOIN modelos m    ON i.id_modelo = m.id_modelo
     INNER JOIN categorias c ON m.id_categoria = c.id_categoria
     WHERE i.id_inventario = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$p = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Si el registro no existe, vuelve al listado
if (!$p) {
    header("Location: index.php");
    exit;
}

// 7. Preparación de datos para la vista
$stock = (int) $p['stock_actual'];
if ($stock === 0) {
    $estado_stock = ['Agotado', 'bg-black text-white'];
} elseif ($stock <= 5) {
    $estado_stock = ['Pocas unidades', 'bg-neutral-300 text-neutral-900'];
} else {
    $estado_stock = ['Disponible', 'bg-neutral-100 text-neutral-700'];
}

// Solo se usa la imagen si es una URL http/https (evita esquemas peligrosos como javascript:)
$imagen = (string) ($p['url_imagen'] ?? '');
if (!preg_match('#^https?://#i', $imagen)) {
    $imagen = '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PasoFirme | <?php echo htmlspecialchars($p['modelo']); ?></title>
    <!-- Tailwind CSS vía CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900">

    <?php include '../includes/navbar.php'; ?>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <a href="index.php" class="text-xs font-semibold uppercase tracking-widest text-neutral-400 hover:text-black transition">&larr; Inventario</a>

        <!-- grid-cols-1 en móvil (imagen arriba, info abajo); 2 columnas desde 1024px (lg:) -->
        <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">

            <!-- ============ IZQUIERDA: IMAGEN ============ -->
            <!-- aspect-square mantiene un cuadrado perfecto; object-cover recorta sin deformar -->
            <div class="bg-neutral-100 rounded-3xl border border-neutral-200 overflow-hidden aspect-square flex items-center justify-center">
                <?php if ($imagen !== ''): ?>
                    <img src="<?php echo htmlspecialchars($imagen); ?>"
                         alt="<?php echo htmlspecialchars($p['modelo']); ?>"
                         class="w-full h-full object-cover"
                         onerror="this.style.display='none'; document.getElementById('sin-imagen').classList.remove('hidden');">
                <?php endif; ?>
                <!-- Marcador visible si no hay imagen o falla la carga -->
                <div id="sin-imagen" class="<?php echo $imagen !== '' ? 'hidden' : ''; ?> text-center text-neutral-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4-4a3 3 0 014 0l4 4m-2-2l1-1a3 3 0 014 0l2 2M4 6h16a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V7a1 1 0 011-1z"/>
                    </svg>
                    <p class="text-sm font-medium">Sin imagen disponible</p>
                </div>
            </div>

            <!-- ============ DERECHA: INFORMACIÓN ============ -->
            <div class="flex flex-col">

                <!-- Categoría y código -->
                <p class="text-xs font-bold uppercase tracking-widest text-neutral-400">
                    <?php echo htmlspecialchars($p['nombre_categoria'] . ' · ' . $p['publico']); ?>
                </p>

                <!-- Nombre del modelo -->
                <h1 class="text-4xl font-black tracking-tight mt-2"><?php echo htmlspecialchars($p['modelo']); ?></h1>
                <p class="text-sm text-neutral-500 mt-1 font-mono">Cód. <?php echo htmlspecialchars($p['codigo_modelo']); ?> · ID #<?php echo (int) $p['id_inventario']; ?></p>

                <!-- Precio destacado (formato moneda) -->
                <p class="text-4xl font-black mt-6">Bs. <?php echo number_format((float) $p['precio_venta'], 2, ',', '.'); ?></p>

                <?php if ((int) $p['estado_activo'] === 0): ?>
                    <p class="mt-3 inline-block self-start px-3 py-1 rounded-full bg-neutral-200 text-neutral-700 text-xs font-bold uppercase tracking-wide">
                        Dado de baja
                    </p>
                <?php endif; ?>

                <!-- Bloques de información: 3 columnas -->
                <div class="grid grid-cols-3 gap-3 mt-8">
                    <div class="bg-white border border-neutral-200 rounded-2xl p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Talla</p>
                        <p class="text-2xl font-black mt-1"><?php echo htmlspecialchars($p['talla']); ?></p>
                    </div>
                    <div class="bg-white border border-neutral-200 rounded-2xl p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Color</p>
                        <p class="text-lg font-bold mt-2 break-words"><?php echo htmlspecialchars($p['color']); ?></p>
                    </div>
                    <div class="bg-white border border-neutral-200 rounded-2xl p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Stock</p>
                        <p class="text-2xl font-black mt-1"><?php echo $stock; ?></p>
                    </div>
                </div>

                <!-- Estado de disponibilidad -->
                <div class="mt-4">
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide <?php echo $estado_stock[1]; ?>">
                        <?php echo $estado_stock[0]; ?>
                    </span>
                    <span class="text-xs text-neutral-400 ml-2">
                        Actualizado: <?php echo date('d/m/Y H:i', strtotime($p['fecha_actualizacion'])); ?>
                    </span>
                </div>

                <!-- Botones: secundario para todos, principal solo para el admin -->
                <div class="flex flex-col-reverse sm:flex-row gap-3 mt-10">
                    <a href="index.php"
                       class="flex-1 text-center px-6 py-3 border border-neutral-300 rounded-full text-sm font-semibold uppercase tracking-wide text-neutral-600 hover:border-black hover:text-black transition">
                        Volver al Inventario
                    </a>
                    <?php if ($es_admin): ?>
                        <a href="editar.php?id=<?php echo (int) $p['id_inventario']; ?>"
                           class="flex-1 text-center bg-black text-white px-6 py-3 rounded-full text-sm font-semibold uppercase tracking-wide hover:bg-neutral-800 active:scale-[0.98] transition">
                            Editar Producto
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
