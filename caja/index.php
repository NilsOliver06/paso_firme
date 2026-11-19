<?php
// ==============================================================================
// ARCHIVO: caja/index.php
// DESCRIPCIÓN: Módulo de control de caja (turnos y arqueo).
//   - Si no hay caja ABIERTA: formulario para abrir turno (monto_apertura).
//   - Si hay caja ABIERTA: panel del turno en tiempo real + cierre de arqueo.
//   - Siempre: historial de arqueos cerrados.
//   Acceso: Administrador y Vendedor.
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

$id_usuario = (int) $_SESSION['id'];
$error = '';

/**
 * Convierte un texto numérico en monto con 2 decimales, o null si no es válido (>= 0)
 * @param mixed $valor
 * @return float|null
 */
function parsear_monto(mixed $valor): ?float
{
    $valor = trim((string) $valor);
    if ($valor === '' || !is_numeric($valor)) {
        return null;
    }
    $n = round((float) $valor, 2);
    return ($n >= 0 && $n <= 99999999.99) ? $n : null;
}

// ------------------------------------------------------------------------------
// 4. ABRIR TURNO (POST con el botón "abrir_caja")
// ------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['abrir_caja'])) {
    $apertura = parsear_monto($_POST['monto_apertura'] ?? '');

    if ($apertura === null) {
        $error = 'El monto de apertura debe ser un número mayor o igual a 0.';
    } else {
        try {
            // Verifica que NO exista ya una caja abierta (evita turnos duplicados)
            $res = mysqli_query($conexion, "SELECT COUNT(*) AS n FROM arqueo_caja WHERE estado = 'ABIERTA'");
            if ((int) mysqli_fetch_assoc($res)['n'] > 0) {
                $error = 'Ya existe una caja abierta.';
            } else {
                // INSERT preparado: estado ABIERTA, fecha de hoy y usuario de la sesión
                $stmt = mysqli_prepare($conexion,
                    "INSERT INTO arqueo_caja (estado, fecha, monto_apertura, id_usuario)
                     VALUES ('ABIERTA', CURDATE(), ?, ?)");
                // "di" = double, integer
                mysqli_stmt_bind_param($stmt, "di", $apertura, $id_usuario);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                header("Location: index.php?msg=abierta");
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            $error = 'No se pudo abrir la caja. Intenta nuevamente.';
        }
    }
}

// ------------------------------------------------------------------------------
// 5. CERRAR TURNO (POST con el botón "cerrar_caja")
//    Los totales se RECALCULAN en el servidor; nunca se confían los del navegador.
// ------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cerrar_caja'])) {
    $fisico = parsear_monto($_POST['monto_fisico'] ?? '');

    if ($fisico === null) {
        $error = 'El monto físico contado debe ser un número mayor o igual a 0.';
    } else {
        // Transacción: el cierre se aplica completo o no se aplica
        mysqli_begin_transaction($conexion);
        try {
            // Bloquea la caja abierta mientras se cierra (FOR UPDATE)
            $res = mysqli_query($conexion,
                "SELECT id_arqueo, monto_apertura FROM arqueo_caja WHERE estado = 'ABIERTA' LIMIT 1 FOR UPDATE");
            $caja = mysqli_fetch_assoc($res);

            if (!$caja) {
                mysqli_rollback($conexion);
                $error = 'No hay ninguna caja abierta para cerrar.';
            } else {
                $id_arqueo = (int) $caja['id_arqueo'];

                // Total en efectivo de este turno
                $stmt = mysqli_prepare($conexion,
                    "SELECT COALESCE(SUM(total), 0) FROM ventas WHERE id_arqueo = ? AND metodo_pago = 'Efectivo'");
                mysqli_stmt_bind_param($stmt, "i", $id_arqueo);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $total_efectivo);
                mysqli_stmt_fetch($stmt);
                mysqli_stmt_close($stmt);

                // Fórmulas del arqueo
                $monto_sistema = round((float) $caja['monto_apertura'] + (float) $total_efectivo, 2);
                $descuadre     = round($fisico - $monto_sistema, 2);

                // UPDATE preparado: cierra el turno
                $stmt = mysqli_prepare($conexion,
                    "UPDATE arqueo_caja
                     SET monto_sistema = ?, monto_fisico = ?, descuadre = ?, estado = 'CERRADA', fecha_cierre = NOW()
                     WHERE id_arqueo = ?");
                // "dddi" = double, double, double, integer
                mysqli_stmt_bind_param($stmt, "dddi", $monto_sistema, $fisico, $descuadre, $id_arqueo);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                mysqli_commit($conexion);
                header("Location: index.php?msg=cerrada");
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            mysqli_rollback($conexion);
            $error = 'No se pudo cerrar la caja. No se guardó ningún cambio.';
        }
    }
}

// ------------------------------------------------------------------------------
// 6. VERIFICACIÓN DE CAJA ACTIVA (consulta preparada)
// ------------------------------------------------------------------------------
$stmt = mysqli_prepare($conexion,
    "SELECT a.id_arqueo, a.fecha_apertura, a.monto_apertura, u.nombres
     FROM arqueo_caja a
     INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
     WHERE a.estado = ? LIMIT 1");
$estado_abierta = 'ABIERTA';
mysqli_stmt_bind_param($stmt, "s", $estado_abierta);
mysqli_stmt_execute($stmt);
$caja_abierta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Si hay caja abierta: calcula con SQL las ventas del turno (por método de pago)
$efectivo = $qr = $total_general = $esperado = 0.0;
$num_ventas = 0;
if ($caja_abierta) {
    $id_arqueo = (int) $caja_abierta['id_arqueo'];
    $stmt = mysqli_prepare($conexion,
        "SELECT
            COALESCE(SUM(CASE WHEN metodo_pago = 'Efectivo'         THEN total END), 0) AS efectivo,
            COALESCE(SUM(CASE WHEN metodo_pago = 'Transferencia QR' THEN total END), 0) AS qr,
            COALESCE(SUM(total), 0) AS total_general,
            COUNT(*) AS num_ventas
         FROM ventas WHERE id_arqueo = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_arqueo);
    mysqli_stmt_execute($stmt);
    $tot = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $efectivo      = (float) $tot['efectivo'];
    $qr            = (float) $tot['qr'];
    $total_general = (float) $tot['total_general'];
    $num_ventas    = (int) $tot['num_ventas'];
    // Total esperado en caja = apertura + ventas en efectivo (el QR no entra al cajón)
    $esperado      = round((float) $caja_abierta['monto_apertura'] + $efectivo, 2);
}

// ------------------------------------------------------------------------------
// 7. HISTORIAL DE ARQUEOS CERRADOS (últimos 50)
// ------------------------------------------------------------------------------
$historial = mysqli_fetch_all(
    mysqli_query($conexion,
        "SELECT a.fecha, a.fecha_cierre, a.monto_apertura, a.monto_sistema, a.monto_fisico, a.descuadre, u.nombres
         FROM arqueo_caja a
         INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
         WHERE a.estado = 'CERRADA'
         ORDER BY a.fecha_cierre DESC, a.id_arqueo DESC
         LIMIT 50"),
    MYSQLI_ASSOC
);

// Mensajes de éxito (vienen por ?msg= tras redirigir)
$mensajes = ['abierta' => 'Turno de caja abierto correctamente.', 'cerrada' => 'Turno cerrado y arqueo registrado correctamente.'];
$msg = $mensajes[$_GET['msg'] ?? ''] ?? '';

/**
 * Formatea dinero como "Bs. 1.250,00"
 * @param mixed $n
 * @return string
 */
function bs(mixed $n): string
{
    return 'Bs. ' . number_format((float) $n, 2, ',', '.');
}

$clase_input = "w-full px-4 py-3 bg-white border border-neutral-300 rounded-xl text-sm text-neutral-900 placeholder-neutral-400 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition";
$clase_label = "block text-xs font-semibold uppercase tracking-wide text-neutral-700 mb-2";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PasoFirme | Control de Caja</title>
    <!-- Tailwind CSS vía CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900">

    <?php include '../includes/navbar.php'; ?>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <div class="mb-8">
            <h1 class="text-3xl font-black tracking-tight">Control de Caja</h1>
            <p class="text-sm text-neutral-500 mt-1">Apertura de turno, seguimiento de ventas y arqueo.</p>
        </div>

        <?php if ($msg !== ''): ?>
            <div class="mb-6 rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm text-neutral-800" role="alert">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="mb-6 rounded-xl border border-neutral-300 bg-neutral-100 px-4 py-3 text-sm text-neutral-800" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

<?php if (!$caja_abierta): ?>
        <!-- ================= SIN CAJA ABIERTA: ABRIR TURNO ================= -->
        <section class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-6 sm:p-8 max-w-xl">
            <span class="inline-block px-3 py-1 rounded-full bg-neutral-200 text-neutral-700 text-xs font-bold uppercase tracking-wide">Caja cerrada</span>
            <h2 class="text-xl font-black mt-3">Abrir Turno de Caja</h2>
            <p class="text-sm text-neutral-500 mt-1">Ingresa el efectivo con el que inicia la caja.</p>

            <form action="index.php" method="POST" class="mt-6 space-y-5">
                <div>
                    <label for="monto_apertura" class="<?php echo $clase_label; ?>">Monto de apertura (Bs.)</label>
                    <input type="number" id="monto_apertura" name="monto_apertura" min="0" step="0.01" required placeholder="0.00"
                           class="<?php echo $clase_input; ?>">
                </div>
                <button type="submit" name="abrir_caja"
                        class="w-full bg-black text-white px-6 py-3 rounded-full text-sm font-semibold uppercase tracking-wide hover:bg-neutral-800 active:scale-[0.98] transition">
                    Abrir turno
                </button>
            </form>
        </section>

<?php else: ?>
        <!-- ================= CAJA ABIERTA: PANEL DEL TURNO ================= -->
        <section class="mb-8">
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <span class="inline-block px-3 py-1 rounded-full bg-black text-white text-xs font-bold uppercase tracking-wide">Turno abierto</span>
                <span class="text-sm text-neutral-500">
                    Abierto por <strong class="text-neutral-800"><?php echo htmlspecialchars($caja_abierta['nombres']); ?></strong>
                    el <?php echo date('d/m/Y H:i', strtotime($caja_abierta['fecha_apertura'])); ?>
                    · <?php echo $num_ventas; ?> venta(s)
                </span>
            </div>

            <!-- Tarjetas de resumen: 1 columna en móvil, 2 en tablet, 3 en PC -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="bg-white border border-neutral-200 rounded-2xl p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Monto de apertura</p>
                    <p class="text-2xl font-black mt-1"><?php echo bs($caja_abierta['monto_apertura']); ?></p>
                </div>
                <div class="bg-white border border-neutral-200 rounded-2xl p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Vendido en efectivo</p>
                    <p class="text-2xl font-black mt-1"><?php echo bs($efectivo); ?></p>
                </div>
                <div class="bg-white border border-neutral-200 rounded-2xl p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Vendido por Transferencia QR</p>
                    <p class="text-2xl font-black mt-1"><?php echo bs($qr); ?></p>
                </div>
                <div class="bg-white border border-neutral-200 rounded-2xl p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Total vendido en el turno</p>
                    <p class="text-2xl font-black mt-1"><?php echo bs($total_general); ?></p>
                </div>
                <!-- Tarjeta destacada: lo que debería haber en caja -->
                <div class="sm:col-span-2 bg-black text-white rounded-2xl p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Total esperado en caja (apertura + efectivo)</p>
                    <p class="text-3xl font-black mt-1"><?php echo bs($esperado); ?></p>
                </div>
            </div>
        </section>

        <!-- ================= CERRAR TURNO / ARQUEO ================= -->
        <section class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-6 sm:p-8 max-w-xl mb-10">
            <h2 class="text-xl font-black">Cerrar Turno / Arqueo</h2>
            <p class="text-sm text-neutral-500 mt-1">Cuenta el efectivo físico en caja e ingrésalo para cerrar el turno.</p>

            <!-- data-*: valores que usa el JavaScript para el mensaje de confirmación -->
            <form id="form-cierre" action="index.php" method="POST" class="mt-6 space-y-5"
                  data-esperado="<?php echo htmlspecialchars((string) $esperado); ?>"
                  data-vendido="<?php echo htmlspecialchars((string) $total_general); ?>">
                <div>
                    <label for="monto_fisico" class="<?php echo $clase_label; ?>">Monto físico contado (Bs.)</label>
                    <input type="number" id="monto_fisico" name="monto_fisico" min="0" step="0.01" required placeholder="0.00"
                           class="<?php echo $clase_input; ?>">
                </div>
                <button type="submit" name="cerrar_caja"
                        class="w-full bg-black text-white px-6 py-3 rounded-full text-sm font-semibold uppercase tracking-wide hover:bg-neutral-800 active:scale-[0.98] transition">
                    Cerrar turno
                </button>
            </form>
        </section>
<?php endif; ?>

        <!-- ================= HISTORIAL DE CIERRES ================= -->
        <section>
            <h2 class="text-xl font-black mb-4">Historial de cierres</h2>

            <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm overflow-x-auto">
                <table class="min-w-full text-sm text-left">
                    <thead class="bg-neutral-50 border-b border-neutral-200 text-xs uppercase tracking-wider text-neutral-500">
                        <tr>
                            <th class="px-6 py-4 font-semibold">Fecha</th>
                            <th class="px-6 py-4 font-semibold">Responsable</th>
                            <th class="px-6 py-4 font-semibold text-right">Apertura</th>
                            <th class="px-6 py-4 font-semibold text-right">Sistema</th>
                            <th class="px-6 py-4 font-semibold text-right">Físico</th>
                            <th class="px-6 py-4 font-semibold text-right">Descuadre</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                    <?php if (count($historial) === 0): ?>
                        <tr><td colspan="6" class="px-6 py-10 text-center text-neutral-400">Aún no hay arqueos cerrados.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($historial as $h):
                        $d = round((float) $h['descuadre'], 2);
                        // Verde = cuadra (0) | Rojo = falta dinero (<0) | Gris oscuro = sobra dinero (>0)
                        if ($d == 0.0)      { $clase_d = 'bg-green-100 text-green-800'; }
                        elseif ($d < 0)     { $clase_d = 'bg-red-100 text-red-700'; }
                        else                { $clase_d = 'bg-neutral-200 text-neutral-800'; }
                    ?>
                        <tr class="hover:bg-neutral-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php echo date('d/m/Y', strtotime($h['fecha'])); ?>
                                <?php if ($h['fecha_cierre']): ?>
                                    <span class="text-xs text-neutral-400 ml-1"><?php echo date('H:i', strtotime($h['fecha_cierre'])); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 font-semibold whitespace-nowrap"><?php echo htmlspecialchars($h['nombres']); ?></td>
                            <td class="px-6 py-4 text-right whitespace-nowrap"><?php echo bs($h['monto_apertura']); ?></td>
                            <td class="px-6 py-4 text-right whitespace-nowrap"><?php echo bs($h['monto_sistema']); ?></td>
                            <td class="px-6 py-4 text-right whitespace-nowrap"><?php echo bs($h['monto_fisico']); ?></td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold <?php echo $clase_d; ?>">
                                    <?php echo ($d > 0 ? '+' : '') . bs($d); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

<?php if ($caja_abierta): ?>
    <!-- ========== JAVASCRIPT: confirmación antes de cerrar el turno ========== -->
    <script>
        const formCierre = document.getElementById('form-cierre');

        formCierre.addEventListener('submit', function (e) {
            const fisico   = parseFloat(document.getElementById('monto_fisico').value) || 0;
            const esperado = parseFloat(formCierre.dataset.esperado);
            const vendido  = parseFloat(formCierre.dataset.vendido);
            const dif      = fisico - esperado;
            const fmt = function (n) { return 'Bs. ' + n.toLocaleString('es-BO', {minimumFractionDigits: 2, maximumFractionDigits: 2}); };

            const texto = 'Vas a cerrar el turno de caja.\n\n' +
                          'Se vendió en total: ' + fmt(vendido) + '\n' +
                          'Dejas en caja (contado): ' + fmt(fisico) + '\n' +
                          'Esperado en caja: ' + fmt(esperado) + '\n' +
                          'Descuadre: ' + (dif > 0 ? '+' : '') + fmt(dif) + '\n\n' +
                          '¿Confirmas el cierre?';

            // Si el usuario cancela, no se envía el formulario
            if (!confirm(texto)) {
                e.preventDefault();
            }
        });
    </script>
<?php endif; ?>
</body>
</html>
