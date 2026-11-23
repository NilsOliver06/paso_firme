<?php
// ==============================================================================
// ARCHIVO: reportes/index.php
// PROYECTO: PasoFirme - Sistema de Calzados
// DESCRIPCIÓN: Balance General y Reporte Gerencial. Acceso exclusivo para
//              Administradores (rol = 1). PHP procedural con mysqli preparadas.
// ==============================================================================

// 1. Sesión e inicialización
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Control de acceso estricto: solo Administrador (rol = 1)
if (!isset($_SESSION['id'])) {
    header("Location: /paso_firme/login.php");
    exit;
}
if ($_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/conexion.php';

// 3. Filtros de fecha — por defecto: primer día del mes actual → hoy
$hoy           = date('Y-m-d');
$primer_dia    = date('Y-m-01');
$fecha_inicio  = !empty($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : $primer_dia;
$fecha_fin     = !empty($_GET['fecha_fin'])    ? $_GET['fecha_fin']    : $hoy;

// Normalizar: fin siempre a las 23:59:59 para incluir todo el día
$dt_inicio = $fecha_inicio . ' 00:00:00';
$dt_fin    = $fecha_fin    . ' 23:59:59';

$admin_nombre = htmlspecialchars($_SESSION['nombres'] ?? 'Administrador');
$fecha_emision = date('d/m/Y H:i');

// ============================================================
// SECCIÓN 1: RESUMEN FINANCIERO (KPIs)
// ============================================================

// 1a. Ingresos totales, cantidad de ventas y ticket promedio
$sql_kpi = "SELECT
                COUNT(*)                             AS total_ventas,
                COALESCE(SUM(total), 0)              AS ingresos_totales,
                COALESCE(AVG(total), 0)              AS ticket_promedio
            FROM ventas
            WHERE fecha_hora BETWEEN ? AND ?";
$stmt = mysqli_prepare($conexion, $sql_kpi);
mysqli_stmt_bind_param($stmt, 'ss', $dt_inicio, $dt_fin);
mysqli_stmt_execute($stmt);
$kpi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// 1b. Desglose por método de pago
$sql_metodo = "SELECT
                   metodo_pago,
                   COALESCE(SUM(total), 0) AS subtotal
               FROM ventas
               WHERE fecha_hora BETWEEN ? AND ?
               GROUP BY metodo_pago";
$stmt = mysqli_prepare($conexion, $sql_metodo);
mysqli_stmt_bind_param($stmt, 'ss', $dt_inicio, $dt_fin);
mysqli_stmt_execute($stmt);
$res_metodo = mysqli_stmt_get_result($stmt);
$pagos = [];
while ($row = mysqli_fetch_assoc($res_metodo)) {
    $pagos[$row['metodo_pago']] = $row['subtotal'];
}
mysqli_stmt_close($stmt);
$pago_efectivo      = $pagos['Efectivo']         ?? 0;
$pago_transferencia = $pagos['Transferencia QR'] ?? 0;

// 1c. Resumen de Arqueos de Caja del periodo
$sql_arqueo = "SELECT
                   COUNT(*)                       AS total_arqueos,
                   COALESCE(SUM(monto_apertura),0) AS suma_apertura,
                   COALESCE(SUM(monto_sistema), 0) AS suma_sistema,
                   COALESCE(SUM(monto_fisico),  0) AS suma_fisico,
                   COALESCE(SUM(descuadre),     0) AS suma_descuadre
               FROM arqueo_caja
               WHERE fecha BETWEEN ? AND ?";
$stmt = mysqli_prepare($conexion, $sql_arqueo);
mysqli_stmt_bind_param($stmt, 'ss', $fecha_inicio, $fecha_fin);
mysqli_stmt_execute($stmt);
$arqueo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// ============================================================
// SECCIÓN 2: TOP MODELOS MÁS VENDIDOS
// ============================================================
$sql_modelos = "SELECT
                    m.nombre                        AS modelo,
                    m.codigo_modelo                 AS codigo,
                    SUM(dv.cantidad)                AS pares_vendidos,
                    SUM(dv.subtotal)                AS ingresos_generados
                FROM detalle_venta dv
                INNER JOIN ventas        v  ON v.id_venta     = dv.id_venta
                INNER JOIN inventario    i  ON i.id_inventario = dv.id_inventario
                INNER JOIN modelos       m  ON m.id_modelo     = i.id_modelo
                WHERE v.fecha_hora BETWEEN ? AND ?
                GROUP BY m.id_modelo, m.nombre, m.codigo_modelo
                ORDER BY pares_vendidos DESC
                LIMIT 15";
$stmt = mysqli_prepare($conexion, $sql_modelos);
mysqli_stmt_bind_param($stmt, 'ss', $dt_inicio, $dt_fin);
mysqli_stmt_execute($stmt);
$res_modelos = mysqli_stmt_get_result($stmt);
$top_modelos = [];
while ($row = mysqli_fetch_assoc($res_modelos)) {
    $top_modelos[] = $row;
}
mysqli_stmt_close($stmt);

// ============================================================
// SECCIÓN 3: RENDIMIENTO POR VENDEDOR
// ============================================================
$sql_vendedores = "SELECT
                       u.id_usuario,
                       u.nombres                       AS vendedor,
                       u.estado_activo,
                       r.nombre_rol                    AS rol_nombre,
                       COUNT(DISTINCT v.id_venta)      AS num_ventas,
                       COALESCE(SUM(dv.cantidad),  0)  AS pares_vendidos,
                       COALESCE(SUM(v.total),      0)  AS total_recaudado
                   FROM usuarios u
                   INNER JOIN roles r ON r.id_rol = u.id_rol
                   LEFT JOIN ventas v ON v.id_usuario = u.id_usuario
                       AND v.fecha_hora BETWEEN ? AND ?
                   LEFT JOIN detalle_venta dv ON dv.id_venta = v.id_venta
                   GROUP BY u.id_usuario, u.nombres, u.estado_activo, r.nombre_rol
                   HAVING num_ventas > 0
                   ORDER BY total_recaudado DESC";
$stmt = mysqli_prepare($conexion, $sql_vendedores);
mysqli_stmt_bind_param($stmt, 'ss', $dt_inicio, $dt_fin);
mysqli_stmt_execute($stmt);
$res_vendedores = mysqli_stmt_get_result($stmt);
$vendedores = [];
while ($row = mysqli_fetch_assoc($res_vendedores)) {
    $vendedores[] = $row;
}
mysqli_stmt_close($stmt);

// Detalle por vendedor: productos que vendió en el periodo
$detalle_vendedor = [];
foreach ($vendedores as $v_row) {
    $uid = $v_row['id_usuario'];
    $sql_det = "SELECT
                    m.nombre          AS modelo,
                    m.codigo_modelo   AS codigo,
                    i.talla,
                    i.color,
                    SUM(dv.cantidad)  AS pares,
                    SUM(dv.subtotal)  AS subtotal
                FROM detalle_venta dv
                INNER JOIN ventas     vt ON vt.id_venta      = dv.id_venta
                INNER JOIN inventario i  ON i.id_inventario  = dv.id_inventario
                INNER JOIN modelos    m  ON m.id_modelo       = i.id_modelo
                WHERE vt.id_usuario = ?
                  AND vt.fecha_hora BETWEEN ? AND ?
                GROUP BY m.id_modelo, m.nombre, m.codigo_modelo, i.talla, i.color
                ORDER BY pares DESC";
    $stmt2 = mysqli_prepare($conexion, $sql_det);
    mysqli_stmt_bind_param($stmt2, 'iss', $uid, $dt_inicio, $dt_fin);
    mysqli_stmt_execute($stmt2);
    $res_det = mysqli_stmt_get_result($stmt2);
    $detalle_vendedor[$uid] = [];
    while ($d = mysqli_fetch_assoc($res_det)) {
        $detalle_vendedor[$uid][] = $d;
    }
    mysqli_stmt_close($stmt2);
}

// ============================================================
// SECCIÓN 4: ALERTA DE REPOSICIÓN (Stock Bajo / Agotado)
// ============================================================
$sql_stock = "SELECT
                  m.nombre         AS modelo,
                  m.codigo_modelo  AS codigo,
                  i.talla,
                  i.color,
                  i.stock_actual
              FROM inventario i
              INNER JOIN modelos m ON m.id_modelo = i.id_modelo
              WHERE i.estado_activo = 1
                AND i.stock_actual <= 5
              ORDER BY i.stock_actual ASC, m.nombre ASC";
$stmt = mysqli_prepare($conexion, $sql_stock);
mysqli_stmt_execute($stmt);
$res_stock = mysqli_stmt_get_result($stmt);
$alertas_stock = [];
while ($row = mysqli_fetch_assoc($res_stock)) {
    $alertas_stock[] = $row;
}
mysqli_stmt_close($stmt);

mysqli_close($conexion);

// Helpers de formato
function bs(float $v): string {
    return 'Bs. ' . number_format($v, 2, '.', ',');
}
function fmt_int(int $v): string {
    return number_format($v, 0, '.', ',');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Gerencial — PasoFirme</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .print\:hidden  { display: none !important; }
            .print\:shadow-none { box-shadow: none !important; }
            .print\:border-neutral-300 { border-color: #d4d4d4 !important; }
            .break-inside-avoid { break-inside: avoid; }
            body { font-size: 11pt; }
            header { display: none !important; }
        }
    </style>
</head>
<body class="bg-neutral-50 text-neutral-900 antialiased">

<?php require_once '../includes/navbar.php'; ?>

<main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    <!-- ================================================================
         BARRA DE FILTROS (oculta al imprimir)
         ================================================================ -->
    <div class="print:hidden mb-8 bg-white border border-neutral-200 rounded-2xl p-6 shadow-sm">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div class="flex flex-col gap-1">
                <label class="text-xs font-bold uppercase tracking-widest text-neutral-500">Desde</label>
                <input type="date" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>"
                       class="border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black">
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-bold uppercase tracking-widest text-neutral-500">Hasta</label>
                <input type="date" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>"
                       class="border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black">
            </div>
            <button type="submit"
                    class="bg-black text-white text-sm font-bold uppercase tracking-widest px-6 py-2.5 rounded-full hover:bg-neutral-800 transition">
                Consultar
            </button>
            <button type="button" onclick="window.print()"
                    class="ml-auto flex items-center gap-2 bg-neutral-900 text-white text-sm font-bold uppercase tracking-widest px-6 py-2.5 rounded-full hover:bg-neutral-700 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/>
                </svg>
                Imprimir Reporte / Guardar PDF
            </button>
        </form>
    </div>

    <!-- ================================================================
         ENCABEZADO DEL BALANCE
         ================================================================ -->
    <div class="bg-black text-white rounded-2xl print:rounded-none p-8 mb-8 print:shadow-none break-inside-avoid">
        <p class="text-xs font-bold uppercase tracking-[0.3em] text-neutral-400 mb-1">PasoFirme — Sistema de Calzados</p>
        <h1 class="text-4xl font-black uppercase tracking-tight leading-none">Balance General</h1>
        <p class="text-lg font-semibold mt-1 text-neutral-300">Reporte Gerencial</p>
        <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div>
                <p class="text-neutral-500 uppercase tracking-wider text-xs font-semibold">Periodo consultado</p>
                <p class="font-bold text-white mt-0.5">
                    <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> —
                    <?php echo date('d/m/Y', strtotime($fecha_fin)); ?>
                </p>
            </div>
            <div>
                <p class="text-neutral-500 uppercase tracking-wider text-xs font-semibold">Fecha de emisión</p>
                <p class="font-bold text-white mt-0.5"><?php echo $fecha_emision; ?></p>
            </div>
            <div>
                <p class="text-neutral-500 uppercase tracking-wider text-xs font-semibold">Generado por</p>
                <p class="font-bold text-white mt-0.5"><?php echo $admin_nombre; ?></p>
            </div>
        </div>
    </div>

    <!-- ================================================================
         SECCIÓN 1 — RESUMEN FINANCIERO
         ================================================================ -->
    <section class="mb-8 break-inside-avoid">
        <h2 class="text-xs font-black uppercase tracking-[0.3em] text-neutral-400 mb-4">01 — Resumen Financiero</h2>

        <!-- KPIs principales -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
            <div class="bg-white border border-neutral-200 print:border-neutral-300 rounded-2xl p-6 print:shadow-none shadow-sm">
                <p class="text-xs font-bold uppercase tracking-widest text-neutral-400">Ingresos Totales</p>
                <p class="text-3xl font-black mt-2 text-neutral-900"><?php echo bs((float)$kpi['ingresos_totales']); ?></p>
                <p class="text-xs text-neutral-400 mt-1">Periodo <?php echo date('d/m', strtotime($fecha_inicio)); ?> – <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></p>
            </div>
            <div class="bg-white border border-neutral-200 print:border-neutral-300 rounded-2xl p-6 print:shadow-none shadow-sm">
                <p class="text-xs font-bold uppercase tracking-widest text-neutral-400">Ventas Realizadas</p>
                <p class="text-3xl font-black mt-2 text-neutral-900"><?php echo fmt_int((int)$kpi['total_ventas']); ?></p>
                <p class="text-xs text-neutral-400 mt-1">Transacciones registradas</p>
            </div>
            <div class="bg-white border border-neutral-200 print:border-neutral-300 rounded-2xl p-6 print:shadow-none shadow-sm">
                <p class="text-xs font-bold uppercase tracking-widest text-neutral-400">Ticket Promedio</p>
                <p class="text-3xl font-black mt-2 text-neutral-900"><?php echo bs((float)$kpi['ticket_promedio']); ?></p>
                <p class="text-xs text-neutral-400 mt-1">Promedio por venta</p>
            </div>
        </div>

        <!-- Desglose por método de pago + Arqueo -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Métodos de pago -->
            <div class="bg-white border border-neutral-200 print:border-neutral-300 rounded-2xl p-6 print:shadow-none shadow-sm">
                <p class="text-xs font-black uppercase tracking-widest text-neutral-400 mb-4">Desglose por Método de Pago</p>
                <div class="flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-neutral-900 inline-block"></span>
                            <span class="text-sm font-semibold text-neutral-700">Efectivo</span>
                        </div>
                        <span class="text-sm font-black text-neutral-900"><?php echo bs((float)$pago_efectivo); ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-neutral-400 inline-block"></span>
                            <span class="text-sm font-semibold text-neutral-700">Transferencia QR</span>
                        </div>
                        <span class="text-sm font-black text-neutral-900"><?php echo bs((float)$pago_transferencia); ?></span>
                    </div>
                    <div class="border-t border-neutral-100 pt-3 flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-neutral-400">Total</span>
                        <span class="text-sm font-black text-neutral-900"><?php echo bs((float)$kpi['ingresos_totales']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Arqueos de Caja -->
            <div class="bg-white border border-neutral-200 print:border-neutral-300 rounded-2xl p-6 print:shadow-none shadow-sm">
                <p class="text-xs font-black uppercase tracking-widest text-neutral-400 mb-4">Arqueos de Caja del Periodo</p>
                <div class="flex flex-col gap-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-neutral-600">Turnos registrados</span>
                        <span class="font-black"><?php echo (int)$arqueo['total_arqueos']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-neutral-600">Σ Monto apertura</span>
                        <span class="font-bold"><?php echo bs((float)$arqueo['suma_apertura']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-neutral-600">Σ Monto sistema</span>
                        <span class="font-bold"><?php echo bs((float)$arqueo['suma_sistema']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-neutral-600">Σ Monto físico contado</span>
                        <span class="font-bold"><?php echo bs((float)$arqueo['suma_fisico']); ?></span>
                    </div>
                    <div class="border-t border-neutral-100 pt-3 flex justify-between">
                        <span class="font-black uppercase text-xs tracking-wider <?php echo $arqueo['suma_descuadre'] != 0 ? 'text-red-600' : 'text-green-700'; ?>">
                            Σ Descuadre total (auditoría)
                        </span>
                        <span class="font-black <?php echo $arqueo['suma_descuadre'] != 0 ? 'text-red-600' : 'text-green-700'; ?>">
                            <?php echo bs((float)$arqueo['suma_descuadre']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================================================================
         SECCIÓN 2 — TOP MODELOS MÁS VENDIDOS
         ================================================================ -->
    <section class="mb-8 break-inside-avoid">
        <h2 class="text-xs font-black uppercase tracking-[0.3em] text-neutral-400 mb-4">02 — Top Modelos Más Vendidos</h2>
        <div class="bg-white border border-neutral-200 print:border-neutral-300 rounded-2xl print:shadow-none shadow-sm overflow-hidden">
            <?php if (empty($top_modelos)): ?>
                <p class="text-sm text-neutral-400 text-center py-10">Sin ventas registradas en el periodo seleccionado.</p>
            <?php else: ?>
            <table class="w-full text-sm">
                <thead class="bg-neutral-100 border-b border-neutral-200">
                    <tr>
                        <th class="text-left px-5 py-3 text-xs font-black uppercase tracking-widest text-neutral-500">#</th>
                        <th class="text-left px-5 py-3 text-xs font-black uppercase tracking-widest text-neutral-500">Modelo</th>
                        <th class="text-left px-5 py-3 text-xs font-black uppercase tracking-widest text-neutral-500">Código</th>
                        <th class="text-right px-5 py-3 text-xs font-black uppercase tracking-widest text-neutral-500">Pares Vendidos</th>
                        <th class="text-right px-5 py-3 text-xs font-black uppercase tracking-widest text-neutral-500">Ingresos Generados</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    <?php foreach ($top_modelos as $idx => $mod): ?>
                    <tr class="hover:bg-neutral-50 transition">
                        <td class="px-5 py-3 font-black text-neutral-300 text-lg"><?php echo str_pad($idx + 1, 2, '0', STR_PAD_LEFT); ?></td>
                        <td class="px-5 py-3 font-semibold text-neutral-900"><?php echo htmlspecialchars($mod['modelo']); ?></td>
                        <td class="px-5 py-3 text-neutral-500 font-mono text-xs"><?php echo htmlspecialchars($mod['codigo']); ?></td>
                        <td class="px-5 py-3 text-right font-black text-neutral-900"><?php echo fmt_int((int)$mod['pares_vendidos']); ?></td>
                        <td class="px-5 py-3 text-right font-bold text-neutral-900"><?php echo bs((float)$mod['ingresos_generados']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </section>

    <!-- ================================================================
         SECCIÓN 3 — RENDIMIENTO POR VENDEDOR
         ================================================================ -->
    <section class="mb-8">
        <h2 class="text-xs font-black uppercase tracking-[0.3em] text-neutral-400 mb-4">03 — Rendimiento por Vendedor</h2>

        <?php if (empty($vendedores)): ?>
            <div class="bg-white border border-neutral-200 print:border-neutral-300 rounded-2xl p-10 text-center print:shadow-none shadow-sm">
                <p class="text-sm text-neutral-400">Sin ventas registradas en el periodo seleccionado.</p>
            </div>
        <?php else: ?>
            <?php foreach ($vendedores as $vend): ?>
            <div class="bg-white border border-neutral-200 print:border-neutral-300 rounded-2xl print:shadow-none shadow-sm mb-4 overflow-hidden break-inside-avoid">
                <!-- Encabezado del vendedor -->
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-neutral-100 bg-neutral-50">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-black text-white flex items-center justify-center text-sm font-black uppercase">
                            <?php echo mb_substr($vend['vendedor'], 0, 1); ?>
                        </div>
                        <div>
                            <p class="font-black text-neutral-900 text-sm"><?php echo htmlspecialchars($vend['vendedor']); ?></p>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-xs text-neutral-400"><?php echo htmlspecialchars($vend['rol_nombre']); ?></span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider <?php echo ((int)$vend['estado_activo'] === 1) ? 'bg-green-100 text-green-800' : 'bg-neutral-200 text-neutral-600'; ?>">
                                    <?php echo ((int)$vend['estado_activo'] === 1) ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <div class="text-center">
                            <p class="text-xs font-bold uppercase tracking-wider text-neutral-400">Ventas</p>
                            <p class="font-black text-neutral-900 text-lg"><?php echo (int)$vend['num_ventas']; ?></p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs font-bold uppercase tracking-wider text-neutral-400">Pares</p>
                            <p class="font-black text-neutral-900 text-lg"><?php echo fmt_int((int)$vend['pares_vendidos']); ?></p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs font-bold uppercase tracking-wider text-neutral-400">Recaudado</p>
                            <p class="font-black text-neutral-900 text-lg"><?php echo bs((float)$vend['total_recaudado']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Tabla compacta de productos vendidos por este vendedor -->
                <?php $det = $detalle_vendedor[$vend['id_usuario']] ?? []; ?>
                <?php if (!empty($det)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-neutral-50 border-b border-neutral-100">
                            <tr>
                                <th class="text-left px-5 py-2 font-bold uppercase tracking-widest text-neutral-400">Modelo</th>
                                <th class="text-left px-5 py-2 font-bold uppercase tracking-widest text-neutral-400">Código</th>
                                <th class="text-left px-5 py-2 font-bold uppercase tracking-widest text-neutral-400">Talla</th>
                                <th class="text-left px-5 py-2 font-bold uppercase tracking-widest text-neutral-400">Color</th>
                                <th class="text-right px-5 py-2 font-bold uppercase tracking-widest text-neutral-400">Pares</th>
                                <th class="text-right px-5 py-2 font-bold uppercase tracking-widest text-neutral-400">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-50">
                            <?php foreach ($det as $d): ?>
                            <tr class="hover:bg-neutral-50 transition">
                                <td class="px-5 py-2 font-semibold text-neutral-800"><?php echo htmlspecialchars($d['modelo']); ?></td>
                                <td class="px-5 py-2 font-mono text-neutral-500"><?php echo htmlspecialchars($d['codigo']); ?></td>
                                <td class="px-5 py-2 text-neutral-600"><?php echo htmlspecialchars($d['talla']); ?></td>
                                <td class="px-5 py-2 text-neutral-600"><?php echo htmlspecialchars($d['color']); ?></td>
                                <td class="px-5 py-2 text-right font-bold text-neutral-900"><?php echo (int)$d['pares']; ?></td>
                                <td class="px-5 py-2 text-right font-bold text-neutral-900"><?php echo bs((float)$d['subtotal']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <!-- ================================================================
         SECCIÓN 4 — ALERTA DE REPOSICIÓN
         ================================================================ -->
    <section class="mb-10 break-inside-avoid">
        <h2 class="text-xs font-black uppercase tracking-[0.3em] text-neutral-400 mb-4">04 — Alerta de Reposición (Stock ≤ 5)</h2>
        <div class="bg-white border border-neutral-200 print:border-neutral-300 rounded-2xl print:shadow-none shadow-sm overflow-hidden">
            <?php if (empty($alertas_stock)): ?>
                <div class="flex flex-col items-center py-12 gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm font-bold text-green-700">Sin alertas — Todos los productos tienen stock suficiente.</p>
                </div>
            <?php else: ?>
                <!-- Badge de alerta -->
                <div class="flex items-center gap-3 px-5 py-4 border-b border-neutral-100 bg-red-50">
                    <span class="inline-flex items-center gap-1.5 bg-red-600 text-white text-xs font-black uppercase tracking-widest px-3 py-1.5 rounded-full">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                        </svg>
                        <?php echo count($alertas_stock); ?> ítem(s) con stock bajo o agotado
                    </span>
                    <span class="text-xs text-red-700 font-semibold">Se requiere reposición urgente.</span>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-neutral-50 border-b border-neutral-100">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-black uppercase tracking-widest text-neutral-500">Modelo</th>
                            <th class="text-left px-5 py-3 text-xs font-black uppercase tracking-widest text-neutral-500">Código</th>
                            <th class="text-left px-5 py-3 text-xs font-black uppercase tracking-widest text-neutral-500">Talla</th>
                            <th class="text-left px-5 py-3 text-xs font-black uppercase tracking-widest text-neutral-500">Color</th>
                            <th class="text-center px-5 py-3 text-xs font-black uppercase tracking-widest text-neutral-500">Stock Actual</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <?php foreach ($alertas_stock as $item): ?>
                        <tr class="hover:bg-red-50/50 transition">
                            <td class="px-5 py-3 font-semibold text-neutral-900"><?php echo htmlspecialchars($item['modelo']); ?></td>
                            <td class="px-5 py-3 font-mono text-xs text-neutral-500"><?php echo htmlspecialchars($item['codigo']); ?></td>
                            <td class="px-5 py-3 text-neutral-700"><?php echo htmlspecialchars($item['talla']); ?></td>
                            <td class="px-5 py-3 text-neutral-700"><?php echo htmlspecialchars($item['color']); ?></td>
                            <td class="px-5 py-3 text-center">
                                <?php if ((int)$item['stock_actual'] === 0): ?>
                                    <span class="inline-block bg-red-600 text-white text-xs font-black px-2.5 py-1 rounded-full uppercase tracking-wider">Agotado</span>
                                <?php elseif ((int)$item['stock_actual'] <= 2): ?>
                                    <span class="inline-block bg-red-500 text-white text-xs font-black px-2.5 py-1 rounded-full"><?php echo (int)$item['stock_actual']; ?> par(es)</span>
                                <?php else: ?>
                                    <span class="inline-block bg-amber-400 text-neutral-900 text-xs font-black px-2.5 py-1 rounded-full"><?php echo (int)$item['stock_actual']; ?> par(es)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>

    <!-- Pie de página del reporte -->
    <footer class="text-center text-xs text-neutral-400 pb-8 print:mt-8">
        <p class="font-semibold uppercase tracking-widest">PasoFirme — Sistema de Calzados</p>
        <p class="mt-0.5">Documento generado el <?php echo $fecha_emision; ?> por <?php echo $admin_nombre; ?> — Uso exclusivo gerencial. Confidencial.</p>
    </footer>

</main>
</body>
</html>
