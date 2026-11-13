<?php
// ==============================================================================
// ARCHIVO: inventario/index.php
// DESCRIPCIÓN: Listado de inventario (HU-02) con buscador en tiempo real.
//              Los botones de administración solo se muestran a los admins.
// ==============================================================================

// 1. Inicia la sesión solo si aún no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Valida que haya un usuario logueado. Se hace ANTES de imprimir HTML,
//    porque header() no funciona si ya se envió contenido al navegador.
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit;
}

// 3. $es_admin = true si el rol guardado en sesión es 1 (Administrador)
$es_admin = ($_SESSION['rol'] == 1);

// 4. Incluye la conexión a la BD (crea la variable $conexion)
require_once '../includes/conexion.php';

/** @var mysqli $conexion Indicador de tipo para el linter/Intelephense */

// 5. Consulta con INNER JOIN:
//    inventario (i) -> modelos (m) por id_modelo, y modelos -> categorias (c) por id_categoria.
//    No recibe datos del usuario, por eso no necesita sentencia preparada.
$sql = "SELECT
            i.id_inventario,
            m.codigo_modelo,
            m.nombre           AS modelo,
            c.nombre_categoria AS categoria,
            c.publico,
            i.talla,
            i.color,
            i.stock_actual     AS stock,
            m.precio_venta     AS precio
        FROM inventario i
        INNER JOIN modelos m    ON i.id_modelo = m.id_modelo
        INNER JOIN categorias c ON m.id_categoria = c.id_categoria
        WHERE i.estado_activo = 1
        ORDER BY m.nombre ASC, i.talla ASC";

$resultado = mysqli_query($conexion, $sql);

// 6. Convierte el resultado en un arreglo para recorrerlo con foreach
$productos = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
$total = count($productos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PasoFirme | Gestión de Inventario</title>
    <!-- Tailwind CSS vía CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<!-- bg-neutral-50: fondo gris muy claro | min-h-screen: alto mínimo = pantalla completa -->
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900">

    <!-- Barra de navegación reutilizable -->
    <?php include '../includes/navbar.php'; ?>

    <!-- Contenedor principal: ancho máximo centrado con márgenes responsivos -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Encabezado: columna en móvil, fila desde 640px (sm:) -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Gestión de Inventario</h1>
                <p class="text-sm text-neutral-500 mt-1"><?php echo $total; ?> registro(s) por talla y color</p>
            </div>

            <?php if ($es_admin): ?>
                <!-- Solo el administrador ve este botón negro estilo píldora -->
                <a href="registrar.php"
                   class="inline-flex items-center justify-center gap-2 bg-black text-white px-6 py-3 rounded-full text-sm font-semibold uppercase tracking-wide hover:bg-neutral-800 active:scale-[0.98] transition">
                    <span class="text-lg leading-none">+</span> Nuevo Producto
                </a>
            <?php endif; ?>
        </div>

        <!-- Barra de búsqueda: input flexible + botón Limpiar -->
        <div class="flex flex-col sm:flex-row gap-3 mb-6">
            <!-- flex-1: el input ocupa todo el espacio libre -->
            <input type="text" id="buscador"
                   placeholder="Buscar por modelo, categoría, talla, color..."
                   class="flex-1 px-4 py-3 bg-white border border-neutral-300 rounded-full text-sm placeholder-neutral-400 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition">
            <button type="button" id="btn-limpiar"
                    class="px-6 py-3 border border-neutral-300 bg-white rounded-full text-sm font-semibold uppercase tracking-wide text-neutral-600 hover:border-black hover:text-black transition">
                Limpiar
            </button>
        </div>

        <!-- overflow-x-auto: la tabla se desliza horizontalmente en pantallas pequeñas -->
        <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm text-left">
                <thead class="bg-neutral-50 border-b border-neutral-200 text-xs uppercase tracking-wider text-neutral-500">
                    <tr>
                        <th class="px-6 py-4 font-semibold">ID</th>
                        <th class="px-6 py-4 font-semibold">Modelo</th>
                        <th class="px-6 py-4 font-semibold">Categoría</th>
                        <th class="px-6 py-4 font-semibold">Talla</th>
                        <th class="px-6 py-4 font-semibold">Color</th>
                        <th class="px-6 py-4 font-semibold">Stock</th>
                        <th class="px-6 py-4 font-semibold">Precio</th>
                        <th class="px-6 py-4 font-semibold text-center">Acciones</th>
                    </tr>
                </thead>

                <!-- id="tabla-body": el JavaScript recorre las filas de este bloque -->
                <tbody id="tabla-body" class="divide-y divide-neutral-100">
                <?php foreach ($productos as $p):
                    $id    = (int) $p['id_inventario'];
                    $stock = (int) $p['stock'];
                    // Insignia: negro = agotado, gris = poco stock (<=5), gris claro = normal
                    $clase = $stock === 0 ? 'bg-black text-white'
                           : ($stock <= 5 ? 'bg-neutral-300 text-neutral-900' : 'bg-neutral-100 text-neutral-700');
                ?>
                    <!-- Cada fila es un <tr>; el buscador la oculta con la clase "hidden" -->
                    <tr class="fila-producto hover:bg-neutral-50 transition">
                        <td class="px-6 py-4 text-neutral-400 font-mono">#<?php echo $id; ?></td>
                        <td class="px-6 py-4 font-semibold whitespace-nowrap"><?php echo htmlspecialchars($p['modelo']); ?></td>
                        <td class="px-6 py-4 text-neutral-600"><?php echo htmlspecialchars($p['categoria'] . ' · ' . $p['publico']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($p['talla']); ?></td>
                        <td class="px-6 py-4 text-neutral-600"><?php echo htmlspecialchars($p['color']); ?></td>
                        <td class="px-6 py-4">
                            <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold <?php echo $clase; ?>">
                                <?php echo $stock === 0 ? 'Agotado' : $stock; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 font-semibold whitespace-nowrap">Bs. <?php echo number_format((float) $p['precio'], 2, ',', '.'); ?></td>

                        <!-- ACCIONES -->
                        <td class="px-6 py-4">
                            <div class="flex justify-center gap-2">

                                <!-- Ver: visible para todos los roles -->
                                <a href="detalle.php?id=<?php echo $id; ?>" title="Ver"
                                   class="px-3 py-1.5 rounded-lg border border-neutral-200 text-xs font-semibold text-neutral-600 hover:bg-neutral-100 hover:text-black transition">
                                    Ver
                                </a>

                                <?php if ($es_admin): ?>
                                    <!-- Editar y Eliminar: solo se escriben en el HTML si es admin -->
                                    <a href="editar.php?id=<?php echo $id; ?>" title="Editar"
                                       class="px-3 py-1.5 rounded-lg border border-neutral-200 text-xs font-semibold text-neutral-600 hover:bg-neutral-100 hover:text-black transition">
                                        Editar
                                    </a>
                                    <!-- confirm(): si el usuario cancela devuelve false y no se sigue el enlace -->
                                    <a href="eliminar.php?id=<?php echo $id; ?>" title="Eliminar"
                                       onclick="return confirm('¿Seguro que deseas eliminar este producto? Esta acción no se puede deshacer.');"
                                       class="px-3 py-1.5 rounded-lg border border-neutral-200 text-xs font-semibold text-neutral-600 hover:bg-black hover:text-white hover:border-black transition">
                                        Eliminar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                    <!-- Fila de "sin resultados": oculta por defecto, el JS la muestra si nada coincide -->
                    <tr id="fila-vacia" class="<?php echo $total === 0 ? '' : 'hidden'; ?>">
                        <td colspan="8" class="px-6 py-12 text-center text-neutral-400">
                            No se encontraron productos.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>

    <!-- ========== JAVASCRIPT PURO: filtro en tiempo real ========== -->
    <script>
        // Referencias a los elementos de la página
        const buscador   = document.getElementById('buscador');
        const btnLimpiar = document.getElementById('btn-limpiar');
        const filas      = document.querySelectorAll('#tabla-body .fila-producto');
        const filaVacia  = document.getElementById('fila-vacia');

        // Función que oculta o muestra filas según el texto escrito
        function filtrar() {
            // Texto buscado en minúsculas y sin espacios sobrantes
            const texto = buscador.value.toLowerCase().trim();
            let visibles = 0;

            filas.forEach(function (fila) {
                // textContent trae todo el texto de la fila; se compara en minúsculas
                const coincide = fila.textContent.toLowerCase().includes(texto);
                // classList.toggle('hidden', true) oculta; con false muestra
                fila.classList.toggle('hidden', !coincide);
                if (coincide) visibles++;
            });

            // Muestra el mensaje "sin resultados" solo si no quedó ninguna fila visible
            filaVacia.classList.toggle('hidden', visibles > 0);
        }

        // 'input' se dispara con cada tecla escrita, pegada o borrada
        buscador.addEventListener('input', filtrar);

        // Botón Limpiar: vacía el input, vuelve a filtrar (muestra todo) y enfoca el campo
        btnLimpiar.addEventListener('click', function () {
            buscador.value = '';
            filtrar();
            buscador.focus();
        });
    </script>
</body>
</html>
