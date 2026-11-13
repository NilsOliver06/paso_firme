<?php
// ==============================================================================
// ARCHIVO: index.php
// PROYECTO: PasoFirme - Sistema de Calzados
// DESCRIPCIÓN: Panel principal protegido por sesión.
// ==============================================================================

// 1. Iniciamos la sesión para comprobar si el usuario está autenticado
session_start();

// 2. Control de acceso: si no existe sesión activa, expulsamos al login
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

// 3. Obtenemos los datos del usuario guardados en sesión
$nombre_usuario = $_SESSION['nombres'] ?? 'Usuario';
$rol_usuario    = $_SESSION['rol'] ?? 'Invitado';
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

    <!-- BARRA SUPERIOR DE NAVEGACIÓN (Estilo Nike: sobrio, limpio, negro/blanco) -->
    <header class="w-full bg-white border-b border-neutral-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            
            <!-- Logotipo y Nombre -->
            <div class="flex items-center space-x-3">
                <span class="text-2xl font-black tracking-tighter uppercase text-black">PasoFirme</span>
                <span class="text-xs bg-neutral-100 text-neutral-600 px-2.5 py-1 rounded-full font-semibold border border-neutral-200">
                    Sistema Calzados
                </span>
            </div>

            <!-- Perfil del Usuario y Botón Cerrar Sesión -->
            <div class="flex items-center space-x-4">
                <div class="text-right hidden sm:block">
                    <p class="text-sm font-semibold text-neutral-900"><?php echo htmlspecialchars($nombre_usuario); ?></p>
                    <p class="text-xs text-neutral-500 uppercase tracking-wider"><?php echo htmlspecialchars($rol_usuario); ?></p>
                </div>

                <!-- Insignia de Rol para móviles -->
                <span class="inline-block px-3 py-1 text-xs font-bold rounded-full uppercase tracking-wider <?php echo ($rol_usuario === 'Administrador') ? 'bg-black text-white' : 'bg-neutral-200 text-neutral-800'; ?>">
                    <?php echo htmlspecialchars($rol_usuario); ?>
                </span>

                <!-- Enlace para Cerrar Sesión -->
                <a href="logout.php" 
                   class="text-xs font-semibold uppercase tracking-wider text-neutral-500 hover:text-black transition border border-neutral-300 hover:border-black px-3.5 py-2 rounded-full">
                    Salir
                </a>
            </div>
        </div>
    </header>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Saludo de Bienvenida -->
        <div class="bg-white rounded-2xl p-8 border border-neutral-200 shadow-sm mb-8">
            <span class="text-xs font-bold tracking-widest uppercase text-neutral-400">Bienvenido al sistema</span>
            <h1 class="text-3xl font-black tracking-tight text-neutral-900 mt-1">
                Hola, <?php echo htmlspecialchars($nombre_usuario); ?>
            </h1>
            <p class="text-neutral-500 text-sm mt-2">
                Sesión iniciada como <strong class="text-neutral-800"><?php echo htmlspecialchars($rol_usuario); ?></strong>. Tienes acceso a los módulos correspondientes a tu rol.
            </p>
        </div>

        <!-- MÓDULOS DEL SPRINT 1 -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            
            <!-- Módulo 1: Control de Inventario (HU-02) -->
            <div class="bg-white p-6 rounded-2xl border border-neutral-200 shadow-sm flex flex-col justify-between hover:border-black transition">
                <div>
                    <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center font-bold text-black mb-4">
                        📦
                    </div>
                    <h2 class="text-lg font-bold text-neutral-900">Inventario de Calzados</h2>
                    <p class="text-xs text-neutral-500 mt-2">
                        Gestión completa de stock, modelos, colores y tallas disponibles en la tienda.
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-neutral-100 flex justify-between items-center">
                    <span class="text-xs text-neutral-400 font-medium">HU-02</span>
                    <span class="text-xs font-bold uppercase tracking-wider text-black">Próximamente &rarr;</span>
                </div>
            </div>

            <!-- Módulo 2: Buscador en Mostrador (HU-03) -->
            <div class="bg-white p-6 rounded-2xl border border-neutral-200 shadow-sm flex flex-col justify-between hover:border-black transition">
                <div>
                    <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center font-bold text-black mb-4">
                        🔍
                    </div>
                    <h2 class="text-lg font-bold text-neutral-900">Buscador Rápido de Mostrador</h2>
                    <p class="text-xs text-neutral-500 mt-2">
                        Consulta instantánea de disponibilidad de tallas para atención al cliente al paso.
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-neutral-100 flex justify-between items-center">
                    <span class="text-xs text-neutral-400 font-medium">HU-03</span>
                    <span class="text-xs font-bold uppercase tracking-wider text-black">Próximamente &rarr;</span>
                </div>
            </div>

            <!-- Módulo 3: Punto de Venta (Sprint 2) -->
            <div class="bg-white p-6 rounded-2xl border border-neutral-200 shadow-sm flex flex-col justify-between hover:border-black transition opacity-80">
                <div>
                    <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center font-bold text-black mb-4">
                        💳
                    </div>
                    <h2 class="text-lg font-bold text-neutral-900">Ventas y Comprobantes</h2>
                    <p class="text-xs text-neutral-500 mt-2">
                        Registro de ventas, formas de pago (efectivo, QR) y arqueo de caja diario.
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-neutral-100 flex justify-between items-center">
                    <span class="text-xs text-neutral-400 font-medium">Sprint 2</span>
                    <span class="text-xs font-bold uppercase tracking-wider text-neutral-400">En desarrollo</span>
                </div>
            </div>

        </div>

    </main>

    <!-- PIE DE PÁGINA -->
    <footer class="bg-white border-t border-neutral-200 py-4 text-center text-xs text-neutral-400">
        &copy; <?php echo date('Y'); ?> PasoFirme · Tecnología Web I
    </footer>

</body>
</html>
