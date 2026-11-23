<?php
// ==============================================================================
// ARCHIVO: clientes/index.php
// DESCRIPCIÓN: Listado de clientes con buscador rápido. Acceso para
//              Administrador y Vendedor (solo se exige sesión activa).
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

// 3. Conexión a la BD
require_once '../includes/conexion.php';

/** @var mysqli $conexion */

// 4. Lista todos los clientes
$clientes = mysqli_fetch_all(
    mysqli_query($conexion,
        "SELECT id_cliente, nombre_completo, telefono, nit_ci FROM clientes ORDER BY nombre_completo ASC"),
    MYSQLI_ASSOC
);
$total = count($clientes);

// 5. Mensajes de resultado enviados por las otras páginas (?msg= / ?error=)
$mensajes = [
    'creado'    => 'Cliente registrado correctamente.',
    'editado'   => 'Cliente actualizado correctamente.',
    'eliminado' => 'Cliente eliminado correctamente.',
];
$errores = ['no_eliminado' => 'No se pudo eliminar el cliente.'];
$msg = $mensajes[$_GET['msg'] ?? ''] ?? '';
$err = $errores[$_GET['error'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PasoFirme | Clientes</title>
    <!-- Tailwind CSS vía CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900">

    <?php include '../includes/navbar.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Clientes</h1>
                <p class="text-sm text-neutral-500 mt-1"><?php echo $total; ?> cliente(s) registrado(s)</p>
            </div>
            <a href="registrar.php"
               class="inline-flex items-center justify-center gap-2 bg-black text-white px-6 py-3 rounded-full text-sm font-semibold uppercase tracking-wide hover:bg-neutral-800 active:scale-[0.98] transition">
                <span class="text-lg leading-none">+</span> Nuevo Cliente
            </a>
        </div>

        <?php if ($msg !== ''): ?>
            <div class="mb-6 rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm text-neutral-800" role="alert">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>
        <?php if ($err !== ''): ?>
            <div class="mb-6 rounded-xl border border-neutral-400 bg-neutral-100 px-4 py-3 text-sm font-medium text-neutral-900" role="alert">
                <?php echo htmlspecialchars($err); ?>
            </div>
        <?php endif; ?>

        <!-- Buscador rápido en JavaScript -->
        <input type="text" id="buscador" placeholder="Buscar por nombre, teléfono o NIT/CI..."
               class="w-full mb-6 px-4 py-3 bg-white border border-neutral-300 rounded-full text-sm placeholder-neutral-400 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition">

        <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm text-left">
                <thead class="bg-neutral-50 border-b border-neutral-200 text-xs uppercase tracking-wider text-neutral-500">
                    <tr>
                        <th class="px-6 py-4 font-semibold">ID</th>
                        <th class="px-6 py-4 font-semibold">Nombre completo</th>
                        <th class="px-6 py-4 font-semibold">Teléfono</th>
                        <th class="px-6 py-4 font-semibold">NIT / CI</th>
                        <th class="px-6 py-4 font-semibold text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tabla-body" class="divide-y divide-neutral-100">
                <?php foreach ($clientes as $c): $id = (int) $c['id_cliente']; ?>
                    <tr class="fila-cliente hover:bg-neutral-50 transition">
                        <td class="px-6 py-4 text-neutral-400 font-mono">#<?php echo $id; ?></td>
                        <td class="px-6 py-4 font-semibold whitespace-nowrap"><?php echo htmlspecialchars($c['nombre_completo']); ?></td>
                        <td class="px-6 py-4 text-neutral-600"><?php echo htmlspecialchars((string) ($c['telefono'] ?? '—')); ?></td>
                        <td class="px-6 py-4 text-neutral-600"><?php echo htmlspecialchars((string) ($c['nit_ci'] ?? '—')); ?></td>
                        <td class="px-6 py-4">
                            <div class="flex justify-center gap-2">
                                <a href="editar.php?id=<?php echo $id; ?>"
                                   class="px-3 py-1.5 rounded-lg border border-neutral-200 text-xs font-semibold text-neutral-600 hover:bg-neutral-100 hover:text-black transition">
                                    Editar
                                </a>
                                <!-- confirm(): si el usuario cancela, el enlace no se sigue -->
                                <a href="eliminar.php?id=<?php echo $id; ?>"
                                   onclick="return confirmarEnlace(this, {titulo: 'Eliminar cliente', mensaje: '¿Seguro que deseas eliminar a este cliente? Sus ventas anteriores se conservarán como «Cliente al paso».', textoConfirmar: 'Eliminar', esPeligro: true});"
                                   class="px-3 py-1.5 rounded-lg border border-neutral-200 text-xs font-semibold text-neutral-600 hover:bg-black hover:text-white hover:border-black transition">
                                    Eliminar
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    <tr id="fila-vacia" class="<?php echo $total === 0 ? '' : 'hidden'; ?>">
                        <td colspan="5" class="px-6 py-12 text-center text-neutral-400">No se encontraron clientes.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>

    <!-- ========== JAVASCRIPT: buscador en tiempo real ========== -->
    <script>
        const buscador  = document.getElementById('buscador');
        const filas     = document.querySelectorAll('#tabla-body .fila-cliente');
        const filaVacia = document.getElementById('fila-vacia');

        buscador.addEventListener('input', function () {
            const texto = buscador.value.toLowerCase().trim();
            let visibles = 0;
            filas.forEach(function (fila) {
                const coincide = fila.textContent.toLowerCase().includes(texto);
                fila.classList.toggle('hidden', !coincide);
                if (coincide) { visibles++; }
            });
            filaVacia.classList.toggle('hidden', visibles > 0);
        });
    </script>
</body>
</html>
