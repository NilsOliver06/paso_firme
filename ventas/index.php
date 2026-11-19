<?php
// ==============================================================================
// ARCHIVO: ventas/index.php
// DESCRIPCIÓN: Listado histórico de ventas (acceso para Administrador y Vendedor).
//              Por políticas de auditoría del negocio, las ventas registradas son
//              inmutables (NO existen acciones de Editar ni Eliminar).
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

// 4. Filtro opcional por fecha (YYYY-MM-DD) enviado por GET
$fecha_filtro = trim((string) ($_GET['fecha'] ?? ''));
if ($fecha_filtro !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_filtro)) {
    $fecha_filtro = '';
}

// 5. Consulta histórica con JOIN a usuarios (vendedor) y LEFT JOIN a clientes
//    (LEFT JOIN porque id_cliente es NULL cuando es un "Cliente al paso")
if ($fecha_filtro !== '') {
    $stmt = mysqli_prepare($conexion,
        "SELECT v.id_venta, v.fecha_hora, v.total, v.metodo_pago, v.nro_comprobante,
                u.nombres AS vendedor,
                COALESCE(c.nombre_completo, 'Cliente al paso') AS cliente
         FROM ventas v
         INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
         LEFT JOIN clientes c  ON v.id_cliente = c.id_cliente
         WHERE DATE(v.fecha_hora) = ?
         ORDER BY v.fecha_hora DESC, v.id_venta DESC");
    mysqli_stmt_bind_param($stmt, "s", $fecha_filtro);
    mysqli_stmt_execute($stmt);
    $ventas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $resultado = mysqli_query($conexion,
        "SELECT v.id_venta, v.fecha_hora, v.total, v.metodo_pago, v.nro_comprobante,
                u.nombres AS vendedor,
                COALESCE(c.nombre_completo, 'Cliente al paso') AS cliente
         FROM ventas v
         INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
         LEFT JOIN clientes c  ON v.id_cliente = c.id_cliente
         ORDER BY v.fecha_hora DESC, v.id_venta DESC");
    $ventas = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
}

$total_registros = count($ventas);
$suma_listado = 0.0;
foreach ($ventas as $v) {
    $suma_listado += (float) $v['total'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PasoFirme | Historial de Ventas</title>
    <!-- Tailwind CSS vía CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900">

    <?php include '../includes/navbar.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Cabecera: título + botón "+ Nueva Venta" -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Historial de Ventas</h1>
                <p class="text-sm text-neutral-500 mt-1">
                    <?php echo $total_registros; ?> venta(s) registrada(s) · Total:
                    <strong class="text-neutral-900">Bs. <?php echo number_format($suma_listado, 2, ',', '.'); ?></strong>
                </p>
            </div>

            <a href="crear.php"
               class="inline-flex items-center justify-center gap-2 bg-black text-white px-6 py-3 rounded-full text-sm font-semibold uppercase tracking-wide hover:bg-neutral-800 active:scale-[0.98] transition">
                <span class="text-lg leading-none">+</span> Nueva Venta
            </a>
        </div>

        <!-- Barra de filtros: buscador rápido en JS + filtro por fecha -->
        <div class="flex flex-col md:flex-row gap-3 mb-6">
            <!-- Buscador instantáneo en cliente, vendedor, N° de venta o método de pago -->
            <input type="text" id="buscador"
                   placeholder="Buscar por N° de venta, vendedor, cliente o método de pago..."
                   class="flex-1 px-4 py-3 bg-white border border-neutral-300 rounded-full text-sm placeholder-neutral-400 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition">

            <!-- Filtro por fecha (GET) -->
            <form action="index.php" method="GET" class="flex flex-wrap sm:flex-nowrap gap-2">
                <input type="date" name="fecha" value="<?php echo htmlspecialchars($fecha_filtro); ?>"
                       aria-label="Filtrar por fecha"
                       class="px-4 py-3 bg-white border border-neutral-300 rounded-full text-sm text-neutral-700 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition">
                <button type="submit"
                        class="px-5 py-3 bg-neutral-900 text-white rounded-full text-xs font-semibold uppercase tracking-wide hover:bg-neutral-700 transition">
                    Filtrar fecha
                </button>
                <a href="index.php" id="btn-limpiar"
                   class="px-5 py-3 border border-neutral-300 bg-white rounded-full text-xs font-semibold uppercase tracking-wide text-neutral-600 hover:border-black hover:text-black transition flex items-center justify-center">
                    Limpiar
                </a>
            </form>
        </div>

        <!-- Tabla de ventas (solo lectura por auditoría: sin Editar ni Eliminar) -->
        <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm text-left">
                <thead class="bg-neutral-50 border-b border-neutral-200 text-xs uppercase tracking-wider text-neutral-500">
                    <tr>
                        <th class="px-6 py-4 font-semibold">N° Venta</th>
                        <th class="px-6 py-4 font-semibold">Fecha / Hora</th>
                        <th class="px-6 py-4 font-semibold">Vendedor</th>
                        <th class="px-6 py-4 font-semibold">Cliente</th>
                        <th class="px-6 py-4 font-semibold">Método de Pago</th>
                        <th class="px-6 py-4 font-semibold text-right">Total</th>
                        <th class="px-6 py-4 font-semibold text-center">Acción</th>
                    </tr>
                </thead>
                <tbody id="tabla-body" class="divide-y divide-neutral-100">
                <?php foreach ($ventas as $v):
                    $id     = (int) $v['id_venta'];
                    $es_qr  = ($v['metodo_pago'] === 'Transferencia QR');
                    $badge  = $es_qr ? 'bg-neutral-900 text-white' : 'bg-neutral-100 text-neutral-800';
                ?>
                    <tr class="fila-venta hover:bg-neutral-50 transition">
                        <td class="px-6 py-4 font-mono text-neutral-500 font-semibold">
                            #<?php echo str_pad((string) $id, 5, '0', STR_PAD_LEFT); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-neutral-600">
                            <?php echo date('d/m/Y H:i', strtotime($v['fecha_hora'])); ?>
                        </td>
                        <td class="px-6 py-4 font-medium whitespace-nowrap">
                            <?php echo htmlspecialchars($v['vendedor']); ?>
                        </td>
                        <td class="px-6 py-4 text-neutral-600 whitespace-nowrap">
                            <?php echo htmlspecialchars($v['cliente']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide <?php echo $badge; ?>">
                                <?php echo htmlspecialchars($v['metodo_pago']); ?>
                            </span>
                            <?php if ($es_qr && !empty($v['nro_comprobante'])): ?>
                                <span class="text-xs text-neutral-400 font-mono ml-1" title="N° Comprobante">
                                    (<?php echo htmlspecialchars($v['nro_comprobante']); ?>)
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right font-black whitespace-nowrap">
                            Bs. <?php echo number_format((float) $v['total'], 2, ',', '.'); ?>
                        </td>
                        <!-- Única acción permitida por políticas de auditoría: Ver Recibo -->
                        <td class="px-6 py-4 text-center">
                            <a href="recibo.php?id=<?php echo $id; ?>"
                               class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full border border-neutral-300 text-xs font-semibold uppercase tracking-wide text-neutral-700 hover:border-black hover:bg-black hover:text-white transition">
                                Ver Recibo
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                    <tr id="fila-vacia" class="<?php echo $total_registros === 0 ? '' : 'hidden'; ?>">
                        <td colspan="7" class="px-6 py-12 text-center text-neutral-400">
                            No se encontraron ventas registradas.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>

    <!-- ========== JAVASCRIPT: filtro rápido en tiempo real ========== -->
    <script>
        const buscador  = document.getElementById('buscador');
        const filas     = document.querySelectorAll('#tabla-body .fila-venta');
        const filaVacia = document.getElementById('fila-vacia');

        function filtrar() {
            const texto = buscador.value.toLowerCase().trim();
            let visibles = 0;

            filas.forEach(function (fila) {
                const coincide = fila.textContent.toLowerCase().includes(texto);
                fila.classList.toggle('hidden', !coincide);
                if (coincide) { visibles++; }
            });

            filaVacia.classList.toggle('hidden', visibles > 0);
        }

        buscador.addEventListener('input', filtrar);
    </script>
</body>
</html>
