<?php
// ==============================================================================
// ARCHIVO: inventario.php
// PROYECTO: PasoFirme - Sistema de Calzados
// HISTORIA DE USUARIO: HU-02 (Listado de inventario por talla y color)
// DESCRIPCIÓN: Lista todo el inventario uniendo las tablas inventario, modelos
//              y categorias. Desde aquí se accede a ver, editar y eliminar.
// ==============================================================================

// ------------------------------------------------------------------------------
// PASO 1: CONTROL DE ACCESO
// ------------------------------------------------------------------------------
// Inicia (o reanuda) la sesión para poder leer $_SESSION.
session_start();

// Si no existe 'id_usuario' en la sesión, nadie ha iniciado sesión:
// se expulsa al visitante hacia el login y se detiene el script con exit.
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

// Datos del usuario logueado (se muestran en la barra superior).
$nombre_usuario = $_SESSION['nombres'] ?? 'Usuario';
$rol_usuario    = $_SESSION['rol'] ?? '';

// ------------------------------------------------------------------------------
// PASO 2: CONEXIÓN A LA BASE DE DATOS
// ------------------------------------------------------------------------------
// Trae la variable $conexion creada en includes/conexion.php.
require_once 'includes/conexion.php';

// ------------------------------------------------------------------------------
// PASO 3: CONSULTA SQL CON JOIN
// ------------------------------------------------------------------------------
// - inventario (i) guarda talla, color y stock.
// - modelos (m) guarda el nombre y el precio de venta.
// - categorias (c) guarda el nombre de la categoría.
// INNER JOIN une las filas cuyas llaves coinciden:
//   i.id_modelo = m.id_modelo   y   m.id_categoria = c.id_categoria
// Los alias (AS) dan nombres claros a las columnas del resultado.
// NOTA: aquí se usa mysqli_query (no prepare) porque la consulta NO recibe
//       ningún dato escrito por el usuario, así que no hay riesgo de inyección SQL.
$sql = "SELECT
            i.id_inventario,
            m.nombre            AS modelo,
            c.nombre_categoria  AS categoria,
            i.talla,
            i.color,
            i.stock_actual      AS stock,
            m.precio_venta      AS precio
        FROM inventario i
        INNER JOIN modelos m    ON i.id_modelo = m.id_modelo
        INNER JOIN categorias c ON m.id_categoria = c.id_categoria
        ORDER BY m.nombre ASC, i.talla ASC";

// Ejecuta la consulta y guarda el resultado.
$resultado = mysqli_query($conexion, $sql);

// Convierte el resultado en un arreglo asociativo: cada fila es
// ['id_inventario' => 1, 'modelo' => '...', ...]. Facilita recorrerlo con foreach.
$productos = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

// Total de registros, para mostrarlo en el encabezado.
$total = count($productos);

// ------------------------------------------------------------------------------
// PASO 4: MENSAJES DE RETROALIMENTACIÓN
// ------------------------------------------------------------------------------
// Las páginas registrar/editar/eliminar pueden redirigir aquí con ?msg=...
// para informar al usuario del resultado de la operación.
$mensajes = [
    'creado'    => 'Producto registrado correctamente.',
    'editado'   => 'Producto actualizado correctamente.',
    'eliminado' => 'Producto eliminado correctamente.',
];
$msg = $mensajes[$_GET['msg'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <!-- Necesario para que el diseño sea responsivo en celulares -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PasoFirme | Gestión de Inventario</title>

    <!-- Tailwind CSS vía CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<!--
  BODY:
  - min-h-screen flex flex-col : ocupa toda la pantalla y apila header/main/footer en columna
  - bg-neutral-100             : fondo gris muy claro
-->
<body class="min-h-screen flex flex-col bg-neutral-100 font-sans text-neutral-900">

    <!-- ======================= BARRA SUPERIOR ======================= -->
    <!-- sticky top-0: la barra queda fija arriba al hacer scroll -->
    <header class="w-full bg-white border-b border-neutral-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <!-- Logotipo: enlaza de vuelta al panel principal -->
            <a href="index.php" class="text-2xl font-black tracking-tighter uppercase text-black">PasoFirme</a>

            <div class="flex items-center gap-4">
                <!-- hidden sm:block: se oculta en móvil y aparece desde 640px -->
                <div class="text-right hidden sm:block">
                    <p class="text-sm font-semibold"><?php echo htmlspecialchars($nombre_usuario); ?></p>
                    <p class="text-xs text-neutral-500 uppercase tracking-wider"><?php echo htmlspecialchars($rol_usuario); ?></p>
                </div>
                <a href="logout.php"
                   class="text-xs font-semibold uppercase tracking-wider text-neutral-500 hover:text-black border border-neutral-300 hover:border-black px-3.5 py-2 rounded-full transition">
                    Salir
                </a>
            </div>
        </div>
    </header>

    <!-- ======================= CONTENIDO ======================= -->
    <!-- flex-1: empuja el footer hacia abajo | max-w-7xl mx-auto: ancho máximo centrado -->
    <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!--
          ENCABEZADO DE LA SECCIÓN:
          - flex-col en móvil (título arriba, botón abajo)
          - sm:flex-row desde 640px (título a la izquierda, botón a la derecha)
          - sm:items-center sm:justify-between: alinea y separa ambos extremos
        -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <!-- Enlace de regreso al panel -->
                <a href="index.php" class="text-xs font-semibold uppercase tracking-widest text-neutral-400 hover:text-black transition">&larr; Panel principal</a>
                <h1 class="text-3xl font-black tracking-tight mt-1">Gestión de Inventario</h1>
                <p class="text-sm text-neutral-500 mt-1"><?php echo $total; ?> registro(s) por talla y color</p>
            </div>

            <!--
              BOTÓN PRINCIPAL:
              - bg-black text-white rounded-full : píldora negra estilo Nike
              - hover:bg-neutral-800             : se aclara al pasar el mouse
              - active:scale-[0.98]              : efecto de "presionado"
              - inline-flex items-center gap-2   : alinea el ícono "+" con el texto
            -->
            <a href="registrar_calzado.php"
               class="inline-flex items-center justify-center gap-2 bg-black text-white px-6 py-3 rounded-full text-sm font-semibold uppercase tracking-wide hover:bg-neutral-800 active:scale-[0.98] transition">
                <span class="text-lg leading-none">+</span> Nuevo Producto
            </a>
        </div>

        <?php if ($msg !== ''): ?>
            <!-- Alerta de éxito en escala de grises -->
            <div class="mb-6 rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm text-neutral-800" role="alert">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <!--
          CONTENEDOR DE LA TABLA:
          - overflow-x-auto : en pantallas pequeñas la tabla se desliza horizontalmente
                              en lugar de romper el diseño (clave de la responsividad)
          - rounded-2xl border shadow-sm : tarjeta blanca con bordes suaves
        -->
        <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm overflow-x-auto">
            <!-- min-w-full: la tabla ocupa todo el ancho | text-left: texto alineado a la izquierda -->
            <table class="min-w-full text-sm text-left">

                <!-- Encabezados: fondo gris claro, texto pequeño en mayúsculas -->
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

                <!-- divide-y: línea divisoria fina entre cada fila -->
                <tbody class="divide-y divide-neutral-100">

                <?php if ($total === 0): ?>
                    <!-- Estado vacío: colspan="8" hace que la celda ocupe las 8 columnas -->
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-neutral-400">
                            No hay productos registrados. Usa <strong class="text-neutral-700">+ Nuevo Producto</strong> para agregar el primero.
                        </td>
                    </tr>
                <?php else: ?>

                    <?php
                    // PASO 5: Recorremos cada producto y generamos una fila <tr>.
                    // htmlspecialchars() evita que texto guardado en la BD se ejecute
                    // como HTML/JavaScript (protección contra ataques XSS).
                    foreach ($productos as $p):
                        // Convertimos el ID a entero para usarlo con seguridad en las URLs.
                        $id    = (int) $p['id_inventario'];
                        $stock = (int) $p['stock'];
                    ?>
                    <!-- hover:bg-neutral-50: resalta la fila al pasar el mouse -->
                    <tr class="hover:bg-neutral-50 transition">
                        <td class="px-6 py-4 text-neutral-400 font-mono">#<?php echo $id; ?></td>
                        <!-- whitespace-nowrap: evita que el nombre se parta en varias líneas -->
                        <td class="px-6 py-4 font-semibold whitespace-nowrap"><?php echo htmlspecialchars($p['modelo']); ?></td>
                        <td class="px-6 py-4 text-neutral-600"><?php echo htmlspecialchars($p['categoria']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($p['talla']); ?></td>
                        <td class="px-6 py-4 text-neutral-600"><?php echo htmlspecialchars($p['color']); ?></td>
                        <td class="px-6 py-4">
                            <?php
                            // Insignia de stock: negro = agotado, gris oscuro = poco stock (≤5), gris claro = normal.
                            if ($stock === 0) {
                                $clase = 'bg-black text-white';
                            } elseif ($stock <= 5) {
                                $clase = 'bg-neutral-300 text-neutral-900';
                            } else {
                                $clase = 'bg-neutral-100 text-neutral-700';
                            }
                            ?>
                            <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold <?php echo $clase; ?>">
                                <?php echo $stock === 0 ? 'Agotado' : $stock; ?>
                            </span>
                        </td>
                        <!-- number_format(valor, 2, ',', '.') muestra el precio como 1.250,00 -->
                        <td class="px-6 py-4 font-semibold whitespace-nowrap">Bs. <?php echo number_format((float) $p['precio'], 2, ',', '.'); ?></td>

                        <!-- ============ COLUMNA ACCIONES ============ -->
                        <td class="px-6 py-4">
                            <!-- flex justify-center gap-2: los 3 botones en fila, centrados y separados -->
                            <div class="flex justify-center gap-2">

                                <!-- VER DETALLES: botón gris con ícono de ojo -->
                                <a href="detalle_calzado.php?id=<?php echo $id; ?>" title="Ver detalles"
                                   class="p-2 rounded-lg border border-neutral-200 text-neutral-600 hover:bg-neutral-100 hover:text-black transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-7.5 9.75-7.5 9.75 7.5 9.75 7.5-3.75 7.5-9.75 7.5S2.25 12 2.25 12z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </a>

                                <!-- EDITAR: botón gris con ícono de lápiz -->
                                <a href="editar_calzado.php?id=<?php echo $id; ?>" title="Editar"
                                   class="p-2 rounded-lg border border-neutral-200 text-neutral-600 hover:bg-neutral-100 hover:text-black transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.86 3.49a2.1 2.1 0 113 3L7.5 18.85 3 20l1.15-4.5L16.86 3.49z"/>
                                    </svg>
                                </a>

                                <!--
                                  ELIMINAR: botón con ícono de papelera.
                                  onclick="return confirm(...)": JavaScript muestra una ventana
                                  de confirmación. Si el usuario pulsa "Cancelar", confirm()
                                  devuelve false y el enlace NO se sigue.
                                  hover:bg-black hover:text-white: se vuelve negro para advertir.
                                -->
                                <a href="eliminar_calzado.php?id=<?php echo $id; ?>" title="Eliminar"
                                   onclick="return confirm('¿Seguro que deseas eliminar este producto? Esta acción no se puede deshacer.');"
                                   class="p-2 rounded-lg border border-neutral-200 text-neutral-600 hover:bg-black hover:text-white hover:border-black transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12M9 7V4h6v3"/>
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- ======================= PIE DE PÁGINA ======================= -->
    <footer class="bg-white border-t border-neutral-200 py-4 text-center text-xs text-neutral-400">
        &copy; <?php echo date('Y'); ?> PasoFirme · Tecnología Web I
    </footer>

</body>
</html>
