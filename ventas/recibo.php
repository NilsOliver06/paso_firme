<?php
// ==============================================================================
// ARCHIVO: ventas/recibo.php
// DESCRIPCIÓN: Comprobante formal de venta listo para imprimir.
//              Oculta el navbar y los botones en la hoja impresa con print:hidden.
//              Si recibe &auto_print=1 en la URL, lanza window.print() al cargar.
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

// 4. Recibe y valida el ID de la venta por GET
$id_venta = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($id_venta === false || $id_venta === null || $id_venta <= 0) {
    header("Location: index.php");
    exit;
}

// 5. Consulta la cabecera de la venta + vendedor + cliente (LEFT JOIN por si es cliente al paso)
$stmt = mysqli_prepare($conexion,
    "SELECT v.id_venta, v.fecha_hora, v.total, v.metodo_pago, v.nro_comprobante,
            u.nombres AS vendedor,
            c.nombre_completo AS cliente_nombre,
            c.nit_ci          AS cliente_nit,
            c.telefono        AS cliente_telefono
     FROM ventas v
     INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
     LEFT JOIN clientes c  ON v.id_cliente = c.id_cliente
     WHERE v.id_venta = ?
     LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id_venta);
mysqli_stmt_execute($stmt);
$venta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Si la venta no existe, vuelve al historial
if (!$venta) {
    header("Location: index.php");
    exit;
}

// 6. Consulta todas las líneas de detalle_venta con modelo, código, talla y color
$stmt_det = mysqli_prepare($conexion,
    "SELECT d.cantidad, d.precio_unitario, d.subtotal,
            i.talla, i.color,
            m.codigo_modelo, m.nombre AS modelo
     FROM detalle_venta d
     INNER JOIN inventario i ON d.id_inventario = i.id_inventario
     INNER JOIN modelos m    ON i.id_modelo = m.id_modelo
     WHERE d.id_venta = ?
     ORDER BY d.id_detalle ASC");
mysqli_stmt_bind_param($stmt_det, "i", $id_venta);
mysqli_stmt_execute($stmt_det);
$detalles = mysqli_fetch_all(mysqli_stmt_get_result($stmt_det), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_det);

// ¿Debe abrir el diálogo de impresión automáticamente al cargar?
$auto_print = (($_GET['auto_print'] ?? '') === '1');
$nro_recibo = str_pad((string) (int) $venta['id_venta'], 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PasoFirme | Comprobante #<?php echo $nro_recibo; ?></title>
    <!-- Tailwind CSS vía CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-neutral-100 print:bg-white font-sans text-neutral-900">

    <!-- Navbar envuelto en print:hidden para que NO aparezca al imprimir -->
    <div class="print:hidden">
        <?php include '../includes/navbar.php'; ?>
    </div>

    <main class="max-w-3xl mx-auto px-4 sm:px-6 py-8 print:py-0 print:px-0">

        <!-- Barra de botones superior (oculta al imprimir con print:hidden) -->
        <div class="print:hidden flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
            <a href="index.php"
               class="inline-flex items-center justify-center px-5 py-2.5 border border-neutral-300 bg-white rounded-full text-xs font-semibold uppercase tracking-wide text-neutral-700 hover:border-black hover:text-black transition">
                &larr; Volver a Ventas
            </a>

            <div class="flex gap-3">
                <a href="crear.php"
                   class="inline-flex items-center justify-center px-5 py-2.5 border border-neutral-300 bg-white rounded-full text-xs font-semibold uppercase tracking-wide text-neutral-700 hover:border-black hover:text-black transition">
                    + Nueva Venta
                </a>
                <button type="button" onclick="window.print()"
                        class="inline-flex items-center justify-center gap-2 bg-black text-white px-6 py-2.5 rounded-full text-xs font-semibold uppercase tracking-wide hover:bg-neutral-800 active:scale-[0.98] transition">
                    Imprimir Comprobante
                </button>
            </div>
        </div>

        <!-- ================= COMPROBANTE FORMAL ================= -->
        <section class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-8 sm:p-10 print:shadow-none print:border-0 print:p-0">

            <!-- Encabezado del recibo -->
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 pb-6 border-b border-neutral-200">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-neutral-400">Comprobante de Venta</p>
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tighter uppercase text-black mt-1">
                        Calzados PasoFirme
                    </h1>
                    <p class="text-xs text-neutral-500 mt-1">Tienda de Calzados · Atención en Mostrador</p>
                </div>

                <div class="sm:text-right">
                    <span class="inline-block px-3 py-1 rounded-full bg-neutral-100 border border-neutral-200 font-mono text-xs font-bold text-neutral-800">
                        N° <?php echo $nro_recibo; ?>
                    </span>
                    <p class="text-xs text-neutral-500 mt-2">
                        Fecha: <strong class="text-neutral-800"><?php echo date('d/m/Y H:i', strtotime($venta['fecha_hora'])); ?></strong>
                    </p>
                </div>
            </div>

            <!-- Datos del cliente, vendedor y pago -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 py-6 border-b border-neutral-200 text-sm">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-400">Cliente</p>
                    <p class="font-bold text-neutral-900 mt-1">
                        <?php echo htmlspecialchars($venta['cliente_nombre'] ?: 'Cliente al paso'); ?>
                    </p>
                    <?php if (!empty($venta['cliente_nit'])): ?>
                        <p class="text-xs text-neutral-500 mt-0.5">NIT / CI: <?php echo htmlspecialchars($venta['cliente_nit']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($venta['cliente_telefono'])): ?>
                        <p class="text-xs text-neutral-500 mt-0.5">Tel.: <?php echo htmlspecialchars($venta['cliente_telefono']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="sm:text-right">
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-400">Detalles de Operación</p>
                    <p class="text-neutral-700 mt-1">
                        Vendedor: <strong class="text-neutral-900"><?php echo htmlspecialchars($venta['vendedor']); ?></strong>
                    </p>
                    <p class="text-neutral-700 mt-0.5">
                        Método de pago: <strong class="text-neutral-900"><?php echo htmlspecialchars($venta['metodo_pago']); ?></strong>
                    </p>
                    <?php if ($venta['metodo_pago'] === 'Transferencia QR' && !empty($venta['nro_comprobante'])): ?>
                        <p class="text-xs text-neutral-500 font-mono mt-0.5">
                            Comprobante QR: <?php echo htmlspecialchars($venta['nro_comprobante']); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tabla de productos vendidos -->
            <div class="py-6">
                <table class="min-w-full text-sm text-left">
                    <thead class="border-b border-neutral-200 text-xs uppercase tracking-wider text-neutral-400">
                        <tr>
                            <th class="py-3 pr-4 font-semibold">Descripción (Modelo / Talla / Color)</th>
                            <th class="py-3 px-4 font-semibold text-center">Cant.</th>
                            <th class="py-3 px-4 font-semibold text-right">P. Unit.</th>
                            <th class="py-3 pl-4 font-semibold text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                    <?php foreach ($detalles as $d): ?>
                        <tr>
                            <td class="py-3.5 pr-4">
                                <p class="font-semibold text-neutral-900"><?php echo htmlspecialchars($d['modelo']); ?></p>
                                <p class="text-xs text-neutral-500">
                                    Cód. <?php echo htmlspecialchars($d['codigo_modelo']); ?> ·
                                    Talla <?php echo htmlspecialchars($d['talla']); ?> ·
                                    Color <?php echo htmlspecialchars($d['color']); ?>
                                </p>
                            </td>
                            <td class="py-3.5 px-4 text-center font-medium"><?php echo (int) $d['cantidad']; ?></td>
                            <td class="py-3.5 px-4 text-right whitespace-nowrap text-neutral-600">
                                Bs. <?php echo number_format((float) $d['precio_unitario'], 2, ',', '.'); ?>
                            </td>
                            <td class="py-3.5 pl-4 text-right whitespace-nowrap font-bold text-neutral-900">
                                Bs. <?php echo number_format((float) $d['subtotal'], 2, ',', '.'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Total General -->
            <div class="border-t-2 border-neutral-900 pt-4 flex items-center justify-between">
                <span class="text-sm font-black uppercase tracking-wider text-neutral-700">Total Pagado</span>
                <span class="text-3xl font-black text-black">
                    Bs. <?php echo number_format((float) $venta['total'], 2, ',', '.'); ?>
                </span>
            </div>

            <!-- Pie del comprobante -->
            <div class="mt-8 pt-6 border-t border-neutral-100 text-center text-xs text-neutral-400">
                Gracias por su preferencia · Calzados PasoFirme
            </div>
        </section>
    </main>

<?php if ($auto_print): ?>
    <!-- Impresión automática cuando se llega desde crear.php (&auto_print=1) -->
    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
<?php endif; ?>
</body>
</html>
