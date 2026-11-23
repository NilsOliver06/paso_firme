<?php
// ==============================================================================
// ARCHIVO: modelos/index.php
// DESCRIPCIÓN: Catálogo visual de modelos activos en formato de cuadrícula.
//              Todos los roles lo ven; solo el Administrador ve las acciones.
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

// 3. ¿Es administrador? Controla los botones de gestión
$es_admin = ($_SESSION['rol'] == 1);

// 4. Conexión a la BD
require_once '../includes/conexion.php';

/** @var mysqli $conexion */

// 5. Consulta con JOIN: modelos activos con el nombre de su categoría
$resultado = mysqli_query($conexion,
    "SELECT m.id_modelo, m.codigo_modelo, m.nombre, m.precio_venta, m.url_imagen,
            c.nombre_categoria, c.publico
     FROM modelos m
     INNER JOIN categorias c ON m.id_categoria = c.id_categoria
     WHERE m.estado_activo = 1
     ORDER BY m.nombre ASC");
$modelos = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
$total = count($modelos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PasoFirme | Catálogo de Modelos</title>
    <!-- Tailwind CSS vía CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900">

    <?php include '../includes/navbar.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Cabecera: título a la izquierda y botón (solo admin) a la derecha -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Catálogo</h1>
                <p class="text-sm text-neutral-500 mt-1"><?php echo $total; ?> modelo(s) disponible(s)</p>
            </div>

            <?php if ($es_admin): ?>
                <a href="registrar.php"
                   class="inline-flex items-center justify-center gap-2 bg-black text-white px-6 py-3 rounded-full text-sm font-semibold uppercase tracking-wide hover:bg-neutral-800 active:scale-[0.98] transition">
                    <span class="text-lg leading-none">+</span> Nuevo Modelo
                </a>
            <?php endif; ?>
        </div>

        <?php if ($total === 0): ?>
            <div class="bg-white rounded-2xl border border-neutral-200 p-12 text-center text-neutral-400">
                No hay modelos activos en el catálogo.
            </div>
        <?php else: ?>

        <!--
          GRID RESPONSIVO:
          - grid-cols-1       : 1 columna en móviles
          - sm:grid-cols-2    : 2 columnas desde 640px
          - lg:grid-cols-3    : 3 columnas desde 1024px
          - xl:grid-cols-4    : 4 columnas desde 1280px
        -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">

            <?php foreach ($modelos as $m):
                $id = (int) $m['id_modelo'];
                // Solo se usa la imagen si es una URL http/https
                $imagen = (string) ($m['url_imagen'] ?? '');
                if (!preg_match('#^https?://#i', $imagen)) {
                    $imagen = '';
                }
            ?>
            <!-- TARJETA: flex-col para que el pie quede siempre abajo; overflow-hidden redondea la imagen -->
            <article class="group bg-white rounded-2xl border border-neutral-200 shadow-sm overflow-hidden flex flex-col hover:border-black hover:shadow-md transition">

                <!-- Imagen: altura fija (h-56) y object-cover para recortar sin deformar -->
                <div class="h-56 bg-neutral-100 flex items-center justify-center overflow-hidden">
                    <?php if ($imagen !== ''): ?>
                        <img src="<?php echo htmlspecialchars($imagen); ?>"
                             alt="<?php echo htmlspecialchars($m['nombre']); ?>"
                             loading="lazy"
                             class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                             onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');">
                    <?php endif; ?>
                    <!-- Marcador si no hay imagen o falla la carga -->
                    <div class="<?php echo $imagen !== '' ? 'hidden' : ''; ?> text-center text-neutral-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 mx-auto mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4-4a3 3 0 014 0l4 4m-2-2l1-1a3 3 0 014 0l2 2M4 6h16a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V7a1 1 0 011-1z"/>
                        </svg>
                        <p class="text-xs font-medium">Sin imagen</p>
                    </div>
                </div>

                <!-- Información: flex-1 hace que ocupe el espacio sobrante -->
                <div class="p-5 flex-1 flex flex-col">
                    <p class="text-xs font-bold uppercase tracking-widest text-neutral-400">
                        <?php echo htmlspecialchars($m['nombre_categoria'] . ' · ' . $m['publico']); ?>
                    </p>
                    <h2 class="text-lg font-black leading-tight mt-1"><?php echo htmlspecialchars($m['nombre']); ?></h2>
                    <p class="text-xs text-neutral-500 font-mono mt-1">Cód. <?php echo htmlspecialchars($m['codigo_modelo']); ?></p>

                    <!-- mt-auto empuja el precio hacia abajo para alinear todas las tarjetas -->
                    <p class="text-2xl font-black mt-auto pt-4">
                        Bs. <?php echo number_format((float) $m['precio_venta'], 2, ',', '.'); ?>
                    </p>
                </div>

                <?php if ($es_admin): ?>
                    <!-- Pie de tarjeta: acciones solo para el Administrador -->
                    <div class="px-5 pb-5 flex gap-2">
                        <a href="editar.php?id=<?php echo $id; ?>"
                           class="flex-1 text-center px-3 py-2 rounded-full border border-neutral-300 text-xs font-semibold uppercase tracking-wide text-neutral-600 hover:border-black hover:text-black transition">
                            Editar
                        </a>
                        <a href="eliminar.php?id=<?php echo $id; ?>"
                           onclick="return confirmarEnlace(this, {titulo: 'Eliminar modelo', mensaje: '¿Seguro que deseas eliminar este modelo del catálogo?', textoConfirmar: 'Eliminar', esPeligro: true});"
                           class="flex-1 text-center px-3 py-2 rounded-full border border-neutral-300 text-xs font-semibold uppercase tracking-wide text-neutral-600 hover:bg-black hover:text-white hover:border-black transition">
                            Eliminar
                        </a>
                    </div>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>

        </div>
        <?php endif; ?>
    </main>
</body>
</html>
