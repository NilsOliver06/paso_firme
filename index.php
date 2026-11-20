<?php
// ==============================================================================
// ARCHIVO: index.php
// PROYECTO: PasoFirme - Sistema de Calzados
// DESCRIPCIÓN: Panel principal (dashboard) protegido por sesión.
//              Hero, indicadores (KPIs) en tiempo real y accesos a módulos.
// ==============================================================================

// 1. Iniciamos la sesión solo si aún no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Control de acceso: si no existe sesión activa, expulsamos al login
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit;
}

// 3. Conexión a la BD
require_once 'includes/conexion.php';

/** @var mysqli $conexion */

// 4. Datos del usuario para el saludo
$nombre_usuario = $_SESSION['nombres'] ?? 'Usuario';
$es_admin       = ($_SESSION['rol'] == 1);
$rol_usuario    = $es_admin ? 'Administrador' : 'Vendedor';

// ------------------------------------------------------------------------------
// 5. INDICADORES (KPIs) EN TIEMPO REAL
// ------------------------------------------------------------------------------

// 5.1 Estado de la caja: ¿existe un arqueo con estado ABIERTA?
$fila = mysqli_fetch_assoc(mysqli_query($conexion,
    "SELECT COUNT(*) AS n FROM arqueo_caja WHERE estado = 'ABIERTA'"));
$caja_abierta = ((int) $fila['n'] > 0);

// 5.2 Total vendido hoy y número de ventas de hoy
$fila = mysqli_fetch_assoc(mysqli_query($conexion,
    "SELECT COALESCE(SUM(total), 0) AS total, COUNT(*) AS n
     FROM ventas WHERE DATE(fecha_hora) = CURDATE()"));
$ventas_hoy_total = (float) $fila['total'];
$ventas_hoy_n     = (int) $fila['n'];

// 5.3 Productos (variantes talla/color) activos con stock bajo (<= 5)
$fila = mysqli_fetch_assoc(mysqli_query($conexion,
    "SELECT COUNT(*) AS n FROM inventario WHERE estado_activo = 1 AND stock_actual <= 5"));
$stock_bajo = (int) $fila['n'];

// 5.4 Total de clientes registrados
$fila = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) AS n FROM clientes"));
$total_clientes = (int) $fila['n'];

// ------------------------------------------------------------------------------
// 6. MÓDULOS: arreglo con ícono SVG (path), título, descripción y enlace
//    ('solo_admin' => true oculta la tarjeta a los vendedores)
// ------------------------------------------------------------------------------
$modulos = [
    [
        'titulo' => 'Inventario',
        'desc'   => 'Stock por talla y color: registra, edita y da de baja productos.',
        'url'    => 'inventario/index.php',
        'icono'  => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        'solo_admin' => false,
    ],
    [
        'titulo' => 'Catálogo de Modelos',
        'desc'   => 'Explora visualmente modelos, precios y características.',
        'url'    => 'modelos/index.php',
        'icono'  => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
        'solo_admin' => false,
    ],
    [
        'titulo' => 'Punto de Venta',
        'desc'   => 'Registra ventas de mostrador con pago en efectivo o QR.',
        'url'    => 'ventas/crear.php',
        'icono'  => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3a1 1 0 00.7 1.7H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
        'solo_admin' => false,
    ],
    [
        'titulo' => 'Historial de Ventas',
        'desc'   => 'Consulta ventas registradas y reimprime comprobantes.',
        'url'    => 'ventas/index.php',
        'icono'  => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'solo_admin' => false,
    ],
    [
        'titulo' => 'Control de Caja',
        'desc'   => 'Abre y cierra turnos, controla el efectivo y el arqueo diario.',
        'url'    => 'caja/index.php',
        'icono'  => 'M17 9V7a5 5 0 00-10 0v2M5 9h14a1 1 0 011 1v9a2 2 0 01-2 2H6a2 2 0 01-2-2v-9a1 1 0 011-1zm7 4v3',
        'solo_admin' => false,
    ],
    [
        'titulo' => 'Clientes',
        'desc'   => 'Registra y administra los datos de tus clientes.',
        'url'    => 'clientes/index.php',
        'icono'  => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
        'solo_admin' => false,
    ],
    [
        'titulo' => 'Gestión de Usuarios',
        'desc'   => 'Administra el personal, sus roles y correos institucionales.',
        'url'    => 'usuarios/index.php',
        'icono'  => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        'solo_admin' => true,
    ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PasoFirme | Panel Principal</title>

    <!-- Tailwind CSS vía CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-neutral-100 font-sans text-neutral-900 flex flex-col">

    <!-- CABECERA UNIVERSAL -->
    <?php include("includes/navbar.php"); ?>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- ================= HERO BANNER ================= -->
        <section class="relative overflow-hidden bg-black text-white rounded-3xl p-8 sm:p-12">
            <!-- Círculos decorativos en grises sutiles -->
            <div class="pointer-events-none absolute -right-20 -top-20 w-72 h-72 rounded-full bg-white/5"></div>
            <div class="pointer-events-none absolute -right-4 top-24 w-40 h-40 rounded-full bg-white/5"></div>

            <div class="relative max-w-3xl">
                <span class="inline-block px-3 py-1 rounded-full bg-white/10 text-xs font-bold uppercase tracking-widest text-neutral-300">
                    <?php echo htmlspecialchars($rol_usuario); ?>
                </span>
                <h1 class="text-4xl sm:text-5xl font-black tracking-tighter mt-4 leading-tight">
                    Hola, <?php echo htmlspecialchars($nombre_usuario); ?>
                </h1>
                <p class="text-neutral-300 text-sm sm:text-base mt-4 leading-relaxed">
                    <strong class="text-white">Calzados PasoFirme</strong> — tienda minorista de calzado integral en
                    Cochabamba con más de 3 años de trayectoria: líneas deportiva, casual, formal, urbana y escolar.
                </p>

                <div class="mt-8 flex flex-col sm:flex-row gap-3">
                    <a href="ventas/crear.php"
                       class="group inline-flex items-center justify-center gap-2 bg-white text-black px-7 py-3.5 rounded-full text-sm font-bold uppercase tracking-wide hover:bg-neutral-200 active:scale-[0.98] transition">
                        Nueva venta
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                    <a href="modelos/index.php"
                       class="inline-flex items-center justify-center px-7 py-3.5 rounded-full border border-white/30 text-sm font-semibold uppercase tracking-wide text-white hover:bg-white/10 transition">
                        Ver catálogo
                    </a>
                </div>
            </div>
        </section>

        <!-- ================= INDICADORES (KPIs) ================= -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">

            <!-- Estado de la caja -->
            <div class="bg-white rounded-2xl border border-neutral-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Estado de caja</p>
                <div class="flex items-center gap-2 mt-2">
                    <span class="w-2.5 h-2.5 rounded-full <?php echo $caja_abierta ? 'bg-green-500' : 'bg-neutral-400'; ?>"></span>
                    <p class="text-2xl font-black"><?php echo $caja_abierta ? 'Abierta' : 'Cerrada'; ?></p>
                </div>
                <a href="caja/index.php" class="inline-block mt-2 text-xs font-semibold text-neutral-500 hover:text-black transition">
                    <?php echo $caja_abierta ? 'Ver turno' : 'Abrir caja'; ?> &rarr;
                </a>
            </div>

            <!-- Ventas de hoy -->
            <div class="bg-white rounded-2xl border border-neutral-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Ventas de hoy</p>
                <p class="text-2xl font-black mt-2">Bs. <?php echo number_format($ventas_hoy_total, 2, ',', '.'); ?></p>
                <p class="text-xs text-neutral-500 mt-2"><?php echo $ventas_hoy_n; ?> venta(s) registrada(s)</p>
            </div>

            <!-- Stock bajo -->
            <div class="bg-white rounded-2xl border border-neutral-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Stock bajo (≤ 5)</p>
                <p class="text-2xl font-black mt-2"><?php echo $stock_bajo; ?></p>
                <a href="inventario/index.php" class="inline-block mt-2 text-xs font-semibold text-neutral-500 hover:text-black transition">
                    Revisar inventario &rarr;
                </a>
            </div>

            <!-- Clientes -->
            <div class="bg-white rounded-2xl border border-neutral-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Clientes registrados</p>
                <p class="text-2xl font-black mt-2"><?php echo $total_clientes; ?></p>
                <a href="clientes/index.php" class="inline-block mt-2 text-xs font-semibold text-neutral-500 hover:text-black transition">
                    Ver clientes &rarr;
                </a>
            </div>
        </section>

        <!-- ================= ACCESOS A MÓDULOS ================= -->
        <section>
            <h2 class="text-xs font-bold uppercase tracking-widest text-neutral-400 mb-4">Módulos del sistema</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($modulos as $m):
                    if ($m['solo_admin'] && !$es_admin) { continue; } // oculta módulos solo-admin
                ?>
                    <!--
                      Tarjeta:
                      - group                    : permite animar la flecha al pasar el mouse
                      - hover:border-black       : borde negro al hover
                      - hover:-translate-y-0.5   : se eleva ligeramente
                    -->
                    <a href="<?php echo htmlspecialchars($m['url']); ?>"
                       class="group bg-white rounded-2xl border border-neutral-200 p-6 flex flex-col justify-between hover:border-black hover:-translate-y-0.5 hover:shadow-md transition">
                        <div>
                            <div class="w-11 h-11 rounded-xl bg-neutral-100 group-hover:bg-black group-hover:text-white flex items-center justify-center text-neutral-800 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="<?php echo $m['icono']; ?>"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-bold mt-4"><?php echo htmlspecialchars($m['titulo']); ?></h3>
                            <p class="text-sm text-neutral-500 mt-1"><?php echo htmlspecialchars($m['desc']); ?></p>
                        </div>
                        <div class="mt-6 flex items-center gap-1 text-xs font-bold uppercase tracking-wider text-black">
                            Abrir
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

    </main>

    <!-- PIE DE PÁGINA -->
    <footer class="bg-white border-t border-neutral-200 py-4 text-center text-xs text-neutral-400">
        &copy; <?php echo date('Y'); ?> PasoFirme · Tecnología Web I
    </footer>

</body>
</html>
