<?php
// ==============================================================================
// ARCHIVO: index.php
// PROYECTO: PasoFirme - Sistema de Calzados
// DESCRIPCIÓN: Panel principal protegido por sesión.
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

// 3. Obtenemos el nombre para el saludo de bienvenida
$nombre_usuario = $_SESSION['nombres'] ?? 'Usuario';
$rol_usuario    = $_SESSION['rol_nombre'] ?? 'Invitado';
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

    <!-- CABECERA UNIVERSAL (logo, menú FUNCIONES, usuario, rol y salir) -->
    <?php include("includes/navbar.php"); ?>

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
                    <a href="inventario/index.php" class="text-xs font-bold uppercase tracking-wider text-black hover:underline">Ir a Inventario &rarr;</a>
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