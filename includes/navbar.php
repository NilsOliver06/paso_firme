<?php
// ==============================================================================
// ARCHIVO: includes/navbar.php
// DESCRIPCIÓN: Cabecera universal de la aplicación. Se incluye desde cualquier
//              página (raíz o subcarpetas). Usa rutas absolutas desde la raíz
//              del servidor (/paso_firme/...), por lo que funciona en cualquier nivel.
// ==============================================================================

// 1. Inicia la sesión solo si aún no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Si no hay sesión iniciada, redirige al login (ruta absoluta)
if (!isset($_SESSION['id'])) {
    header("Location: /paso_firme/login.php");
    exit;
}

// 3. $es_admin = true si el rol es 1 (Administrador)
$es_admin = ($_SESSION['rol'] == 1);

// 4. Datos para mostrar en la cabecera
$nav_nombre = $_SESSION['nombres'] ?? 'Usuario';
$nav_rol    = $es_admin ? 'Administrador' : 'Vendedor';
?>
<!-- CABECERA: fondo blanco, borde inferior sutil, fija arriba (sticky) y por encima del contenido (z-50) -->
<header class="w-full bg-white border-b border-neutral-200 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">

        <!-- ================= IZQUIERDA ================= -->
        <div class="flex items-center gap-3 sm:gap-5">

            <!-- Logo -->
            <a href="/paso_firme/index.php" class="text-2xl font-black tracking-tighter uppercase text-black">PasoFirme</a>

            <!-- Badge gris claro (oculto en móviles muy pequeños) -->
            <span class="hidden sm:inline-block text-xs bg-neutral-100 text-neutral-600 px-2.5 py-1 rounded-full font-semibold border border-neutral-200">
                Sistema Calzados
            </span>

            <!--
              MENÚ DESPLEGABLE "FUNCIONES":
              - relative : referencia de posición para el menú absoluto
              - group    : permite que los hijos reaccionen con group-hover
            -->
            <div class="relative group">
                <button type="button"
                        class="flex items-center gap-1 text-sm font-bold uppercase tracking-wide text-neutral-800 hover:text-black px-2 py-2 transition">
                    Funciones
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <!--
                  Menú oculto por defecto (hidden); al pasar el mouse sobre el grupo se muestra (group-hover:block).
                  pt-2 crea un puente invisible para no perder el hover al bajar el cursor.
                -->
                <div class="absolute left-0 top-full pt-2 hidden group-hover:block w-48">
                    <div class="bg-white border border-neutral-200 rounded-xl shadow-lg overflow-hidden py-1">
                        <a href="/paso_firme/inventario/index.php"
                           class="block px-4 py-3 text-sm text-neutral-700 hover:bg-neutral-100 hover:text-black transition">
                            Inventario
                        </a>

                        <?php if ($_SESSION['rol'] == 1): ?>
                            <!-- Solo el Administrador ve "Usuarios" -->
                            <a href="/paso_firme/usuarios/index.php"
                               class="block px-4 py-3 text-sm text-neutral-700 hover:bg-neutral-100 hover:text-black transition">
                                Usuarios
                            </a>
                        <?php endif; ?>

                        <a href="/paso_firme/ventas/index.php"
                           class="block px-4 py-3 text-sm text-neutral-700 hover:bg-neutral-100 hover:text-black transition">
                            Ventas
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= DERECHA ================= -->
        <div class="flex items-center gap-3 sm:gap-4">
            <!-- Nombre del usuario (oculto en móvil) -->
            <p class="hidden sm:block text-sm font-semibold text-neutral-900">
                <?php echo htmlspecialchars($nav_nombre); ?>
            </p>

            <!-- Badge de rol: negro para Administrador, gris para Vendedor -->
            <span class="inline-block px-3 py-1 text-xs font-bold rounded-full uppercase tracking-wider <?php echo $es_admin ? 'bg-black text-white' : 'bg-neutral-200 text-neutral-800'; ?>">
                <?php echo htmlspecialchars($nav_rol); ?>
            </span>

            <!-- Botón SALIR: redondeado, con borde -->
            <a href="/paso_firme/logout.php"
               class="text-xs font-semibold uppercase tracking-wider text-neutral-600 hover:text-black border border-neutral-300 hover:border-black px-4 py-2 rounded-full transition">
                Salir
            </a>
        </div>

    </div>
</header>
