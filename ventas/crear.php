<?php
// ==============================================================================
// ARCHIVO: ventas/crear.php
// DESCRIPCIÓN: Punto de Venta. Acceso: Administrador y Vendedor.
//   - Exige una caja ABIERTA (al cargar y al procesar la venta).
//   - Catálogo con stock, carrito, cliente opcional y método de pago.
//   - Registro transaccional: ventas + detalle_venta + descuento de stock.
//   - Solo el Administrador puede configurar la URL del QR de pago.
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
$es_admin   = ($_SESSION['rol'] == 1);
$error      = '';

/**
 * Devuelve el id_arqueo de la caja ABIERTA, o null si no hay ninguna.
 * @param mysqli $con
 * @param bool   $bloquear Si es true, bloquea la fila en modo compartido (solo dentro de una transacción)
 * @return int|null
 */
function caja_abierta(mysqli $con, bool $bloquear = false): ?int
{
    $sql = "SELECT id_arqueo FROM arqueo_caja WHERE estado = 'ABIERTA' ORDER BY id_arqueo DESC LIMIT 1"
         . ($bloquear ? " FOR SHARE" : "");
    $fila = mysqli_fetch_assoc(mysqli_query($con, $sql));
    return $fila ? (int) $fila['id_arqueo'] : null;
}

// ------------------------------------------------------------------------------
// 4. CONFIGURAR QR (POST separado, SOLO Administrador)
// ------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_qr'])) {
    if (!$es_admin) {
        header("Location: crear.php");
        exit;
    }
    $url_qr = trim($_POST['qr_pago_url'] ?? '');

    // Solo URLs http/https válidas
    if ($url_qr === '' || filter_var($url_qr, FILTER_VALIDATE_URL) === false || !preg_match('#^https?://#i', $url_qr)) {
        $error = 'La URL del QR no es válida (debe comenzar con http:// o https://).';
    } else {
        try {
            // Inserta la clave si no existe o actualiza su valor si ya existe
            $stmt = mysqli_prepare($conexion,
                "INSERT INTO configuracion (clave, valor) VALUES ('qr_pago_url', ?)
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
            mysqli_stmt_bind_param($stmt, "s", $url_qr);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header("Location: crear.php?msg=qr");
            exit;
        } catch (mysqli_sql_exception $e) {
            $error = 'No se pudo guardar la configuración del QR.';
        }
    }
}

// ------------------------------------------------------------------------------
// 5. PROCESAR LA VENTA (POST con el botón "confirmar_venta")
//    Todo se revalida en el servidor: caja, stock y precios NO se toman del navegador.
// ------------------------------------------------------------------------------
$carrito_previo = []; // para reconstruir el carrito si hay un error
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_venta'])) {

    // 5.1 Lee el carrito (JSON) y lo normaliza: id => cantidad (sumando repetidos)
    $items = [];
    $decodificado = json_decode((string) ($_POST['carrito'] ?? '[]'), true);
    if (is_array($decodificado)) {
        foreach ($decodificado as $linea) {
            $id_inv   = filter_var($linea['id'] ?? null, FILTER_VALIDATE_INT);
            $cantidad = filter_var($linea['cantidad'] ?? null, FILTER_VALIDATE_INT);
            if ($id_inv !== false && $cantidad !== false && $id_inv > 0 && $cantidad > 0) {
                $items[$id_inv] = ($items[$id_inv] ?? 0) + $cantidad;
            }
        }
    }
    foreach ($items as $k => $v) {
        $carrito_previo[] = ['id' => $k, 'cantidad' => $v];
    }

    // 5.2 Lee y valida cliente, método de pago y comprobante
    $metodo = (string) ($_POST['metodo_pago'] ?? '');
    $id_cliente = null;
    if (($_POST['id_cliente'] ?? '') !== '') {
        $c = filter_var($_POST['id_cliente'], FILTER_VALIDATE_INT);
        $id_cliente = ($c !== false && $c > 0) ? $c : -1; // -1 = inválido
    }
    $nro_comprobante = null;
    if ($metodo === 'Transferencia QR') {
        $texto = trim($_POST['nro_comprobante'] ?? '');
        $nro_comprobante = $texto === '' ? null : $texto;
    }

    if (count($items) === 0) {
        $error = 'El carrito está vacío.';
    } elseif (!in_array($metodo, ['Efectivo', 'Transferencia QR'], true)) {
        $error = 'Selecciona un método de pago válido.';
    } elseif ($id_cliente === -1) {
        $error = 'El cliente seleccionado no es válido.';
    } elseif ($nro_comprobante !== null && mb_strlen($nro_comprobante) > 100) {
        $error = 'El número de comprobante no puede superar los 100 caracteres.';
    } else {
        // 5.3 TRANSACCIÓN: o se guarda todo, o no se guarda nada
        mysqli_begin_transaction($conexion);
        try {
            // Regla estricta: debe haber una caja ABIERTA (se revalida aquí)
            $id_arqueo = caja_abierta($conexion, true);
            if ($id_arqueo === null) {
                throw new RuntimeException('No hay una caja abierta. Abre la caja antes de vender.');
            }

            // Verifica que el cliente exista
            if ($id_cliente !== null) {
                $stmt = mysqli_prepare($conexion, "SELECT COUNT(*) FROM clientes WHERE id_cliente = ?");
                mysqli_stmt_bind_param($stmt, "i", $id_cliente);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $existe);
                mysqli_stmt_fetch($stmt);
                mysqli_stmt_close($stmt);
                if ((int) $existe === 0) {
                    throw new RuntimeException('El cliente seleccionado no existe.');
                }
            }

            // 5.4 Revalida CADA ítem con bloqueo de fila (FOR UPDATE) y toma el precio real de la BD
            $lineas = [];
            $total  = 0.0;
            $stmt = mysqli_prepare($conexion,
                "SELECT i.stock_actual, m.precio_venta, m.nombre, i.talla, i.color
                 FROM inventario i
                 INNER JOIN modelos m ON i.id_modelo = m.id_modelo
                 WHERE i.id_inventario = ? AND i.estado_activo = 1
                 FOR UPDATE");
            foreach ($items as $id_inv => $cantidad) {
                mysqli_stmt_bind_param($stmt, "i", $id_inv);
                mysqli_stmt_execute($stmt);
                $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

                if (!$fila) {
                    throw new RuntimeException('Un producto del carrito ya no está disponible.');
                }
                if ((int) $fila['stock_actual'] < $cantidad) {
                    throw new RuntimeException(
                        'Stock insuficiente para ' . $fila['nombre'] . ' (talla ' . $fila['talla'] . ', ' . $fila['color']
                        . '): disponible ' . (int) $fila['stock_actual'] . ', solicitado ' . $cantidad . '.');
                }
                $precio   = (float) $fila['precio_venta'];
                $subtotal = round($precio * $cantidad, 2);
                $total   += $subtotal;
                $lineas[] = ['id' => $id_inv, 'cantidad' => $cantidad, 'precio' => $precio, 'subtotal' => $subtotal];
            }
            mysqli_stmt_close($stmt);
            $total = round($total, 2);

            // 5.5 INSERT en ventas (incluye id_arqueo y nro_comprobante)
            $stmt = mysqli_prepare($conexion,
                "INSERT INTO ventas (total, metodo_pago, nro_comprobante, id_usuario, id_cliente, id_arqueo)
                 VALUES (?, ?, ?, ?, ?, ?)");
            // "dssiii" = double, string, string (acepta NULL), integer, integer (acepta NULL), integer
            mysqli_stmt_bind_param($stmt, "dssiii", $total, $metodo, $nro_comprobante, $id_usuario, $id_cliente, $id_arqueo);
            mysqli_stmt_execute($stmt);
            $id_venta = (int) mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt);

            // 5.6 INSERT de cada línea en detalle_venta + descuento de stock
            $stmt_det = mysqli_prepare($conexion,
                "INSERT INTO detalle_venta (id_venta, id_inventario, cantidad, precio_unitario, subtotal)
                 VALUES (?, ?, ?, ?, ?)");
            $stmt_stock = mysqli_prepare($conexion,
                "UPDATE inventario SET stock_actual = stock_actual - ? WHERE id_inventario = ?");
            foreach ($lineas as $l) {
                // "iiidd" = integer, integer, integer, double, double
                mysqli_stmt_bind_param($stmt_det, "iiidd", $id_venta, $l['id'], $l['cantidad'], $l['precio'], $l['subtotal']);
                mysqli_stmt_execute($stmt_det);

                // "ii" = integer, integer
                mysqli_stmt_bind_param($stmt_stock, "ii", $l['cantidad'], $l['id']);
                mysqli_stmt_execute($stmt_stock);
            }
            mysqli_stmt_close($stmt_det);
            mysqli_stmt_close($stmt_stock);

            // 5.7 Todo salió bien: confirma y redirige al recibo (impresión automática)
            mysqli_commit($conexion);
            header("Location: recibo.php?id=" . $id_venta . "&auto_print=1");
            exit;

        } catch (RuntimeException $e) {
            // Errores de negocio (sin caja, sin stock...): se deshace y se muestra el mensaje
            mysqli_rollback($conexion);
            $error = $e->getMessage();
        } catch (mysqli_sql_exception $e) {
            // Errores de base de datos: se deshace todo
            mysqli_rollback($conexion);
            $error = 'No se pudo registrar la venta. No se guardó ningún cambio.';
        }
    }
}

// ------------------------------------------------------------------------------
// 6. DATOS PARA LA VISTA
// ------------------------------------------------------------------------------
$hay_caja = (caja_abierta($conexion) !== null);

$productos = [];
$clientes  = [];
$qr_url    = '';

if ($hay_caja) {
    // Catálogo: JOIN inventario + modelos + categorias, solo activos y con stock
    $productos = mysqli_fetch_all(
        mysqli_query($conexion,
            "SELECT i.id_inventario, m.nombre, m.codigo_modelo, c.nombre_categoria, i.talla, i.color,
                    i.stock_actual, m.precio_venta
             FROM inventario i
             INNER JOIN modelos m    ON i.id_modelo = m.id_modelo
             INNER JOIN categorias c ON m.id_categoria = c.id_categoria
             WHERE i.estado_activo = 1 AND i.stock_actual > 0
             ORDER BY m.nombre ASC, i.talla ASC"),
        MYSQLI_ASSOC
    );
    $clientes = mysqli_fetch_all(
        mysqli_query($conexion, "SELECT id_cliente, nombre_completo, nit_ci FROM clientes ORDER BY nombre_completo ASC"),
        MYSQLI_ASSOC
    );
}

// URL del QR (se lee siempre, el admin la necesita para configurarla)
$fila_qr = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT valor FROM configuracion WHERE clave = 'qr_pago_url'"));
$qr_url  = $fila_qr ? (string) $fila_qr['valor'] : '';
if (!preg_match('#^https?://#i', $qr_url)) {
    $qr_url = ''; // solo se muestran URLs http/https
}

// Datos que usa el JavaScript: id => {nombre, talla, color, stock, precio}
$datos_js = [];
foreach ($productos as $p) {
    $datos_js[(int) $p['id_inventario']] = [
        'nombre' => $p['nombre'] . ' · ' . $p['codigo_modelo'],
        'talla'  => $p['talla'],
        'color'  => $p['color'],
        'stock'  => (int) $p['stock_actual'],
        'precio' => (float) $p['precio_venta'],
    ];
}

$msg = (($_GET['msg'] ?? '') === 'qr') ? 'URL del QR actualizada correctamente.' : '';

$clase_input = "w-full px-4 py-3 bg-white border border-neutral-300 rounded-xl text-sm text-neutral-900 placeholder-neutral-400 focus:outline-none focus:border-black focus:ring-1 focus:ring-black transition";
$clase_label = "block text-xs font-semibold uppercase tracking-wide text-neutral-700 mb-2";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PasoFirme | Punto de Venta</title>
    <!-- Tailwind CSS vía CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900">

    <?php include '../includes/navbar.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Cabecera -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Punto de Venta</h1>
                <p class="text-sm text-neutral-500 mt-1">Registra ventas de mostrador.</p>
            </div>

            <?php if ($es_admin): ?>
                <!-- Solo el Administrador ve este botón discreto -->
                <button type="button" id="btn-config-qr"
                        class="self-start sm:self-auto text-xs font-semibold uppercase tracking-wide text-neutral-500 hover:text-black border border-neutral-300 hover:border-black px-4 py-2 rounded-full transition">
                    Configurar QR
                </button>
            <?php endif; ?>
        </div>

        <?php if ($msg !== ''): ?>
            <div class="mb-6 rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm text-neutral-800" role="alert">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="mb-6 rounded-xl border border-neutral-400 bg-neutral-100 px-4 py-3 text-sm font-medium text-neutral-900" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

<?php if (!$hay_caja): ?>
        <!-- ================= BLOQUEO: NO HAY CAJA ABIERTA ================= -->
        <section class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-8 sm:p-12 text-center max-w-xl mx-auto">
            <span class="inline-block px-3 py-1 rounded-full bg-black text-white text-xs font-bold uppercase tracking-wide">Punto de venta bloqueado</span>
            <h2 class="text-2xl font-black mt-4">No hay una caja abierta</h2>
            <p class="text-sm text-neutral-500 mt-2">Para registrar ventas primero debes abrir un turno de caja.</p>
            <a href="../caja/index.php"
               class="inline-flex mt-6 bg-black text-white px-8 py-3 rounded-full text-sm font-semibold uppercase tracking-wide hover:bg-neutral-800 active:scale-[0.98] transition">
                Abrir caja
            </a>
        </section>

<?php else: ?>
        <!-- grid de 3 columnas en PC: catálogo (2) + carrito (1); apilado en móvil -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- ================= CATÁLOGO ================= -->
            <section class="lg:col-span-2">
                <input type="text" id="buscador" placeholder="Buscar por modelo, código, categoría, talla o color..."
                       class="<?php echo $clase_input; ?> rounded-full mb-4">

                <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead class="bg-neutral-50 border-b border-neutral-200 text-xs uppercase tracking-wider text-neutral-500">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Producto</th>
                                <th class="px-4 py-3 font-semibold">Talla</th>
                                <th class="px-4 py-3 font-semibold">Color</th>
                                <th class="px-4 py-3 font-semibold text-center">Stock</th>
                                <th class="px-4 py-3 font-semibold text-right">Precio</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody id="catalogo" class="divide-y divide-neutral-100">
                        <?php foreach ($productos as $p): $id = (int) $p['id_inventario']; ?>
                            <tr class="fila-producto hover:bg-neutral-50 transition">
                                <td class="px-4 py-3">
                                    <p class="font-semibold"><?php echo htmlspecialchars($p['nombre']); ?></p>
                                    <p class="text-xs text-neutral-400"><?php echo htmlspecialchars($p['codigo_modelo'] . ' · ' . $p['nombre_categoria']); ?></p>
                                </td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($p['talla']); ?></td>
                                <td class="px-4 py-3 text-neutral-600"><?php echo htmlspecialchars($p['color']); ?></td>
                                <td class="px-4 py-3 text-center"><?php echo (int) $p['stock_actual']; ?></td>
                                <td class="px-4 py-3 text-right whitespace-nowrap font-semibold">Bs. <?php echo number_format((float) $p['precio_venta'], 2, ',', '.'); ?></td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" data-agregar="<?php echo $id; ?>"
                                            class="px-4 py-1.5 bg-black text-white rounded-full text-xs font-semibold uppercase tracking-wide hover:bg-neutral-800 active:scale-[0.98] transition">
                                        Agregar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                            <tr id="sin-resultados" class="<?php echo count($productos) === 0 ? '' : 'hidden'; ?>">
                                <td colspan="6" class="px-4 py-10 text-center text-neutral-400">No hay productos disponibles con stock.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ================= CARRITO Y PAGO ================= -->
            <aside class="lg:col-span-1">
                <form id="form-venta" action="crear.php" method="POST"
                      class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-6 space-y-5 lg:sticky lg:top-24">
                    <h2 class="text-xl font-black">Carrito</h2>

                    <!-- Líneas del carrito (las genera el JavaScript) -->
                    <div id="carrito-lineas" class="space-y-3 max-h-80 overflow-y-auto"></div>
                    <p id="carrito-vacio" class="text-sm text-neutral-400 text-center py-6">Aún no agregaste productos.</p>

                    <div class="flex items-center justify-between border-t border-neutral-200 pt-4">
                        <span class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Total</span>
                        <span id="total-general" class="text-3xl font-black">Bs. 0,00</span>
                    </div>

                    <!-- El carrito viaja como JSON en este campo oculto -->
                    <input type="hidden" name="carrito" id="carrito-json" value="[]">

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label for="id_cliente" class="block text-xs font-semibold uppercase tracking-wide text-neutral-700">Cliente</label>
                            <!-- Acceso rápido para registrar un cliente nuevo -->
                            <a href="../clientes/registrar.php"
                               class="text-xs font-semibold uppercase tracking-wide text-neutral-500 hover:text-black transition">
                                + Nuevo Cliente
                            </a>
                        </div>
                        <select id="id_cliente" name="id_cliente" class="<?php echo $clase_input; ?>">
                            <option value="">Cliente al paso</option>
                            <?php foreach ($clientes as $cl): ?>
                                <option value="<?php echo (int) $cl['id_cliente']; ?>">
                                    <?php echo htmlspecialchars($cl['nombre_completo'] . ($cl['nit_ci'] ? ' · ' . $cl['nit_ci'] : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="metodo_pago" class="<?php echo $clase_label; ?>">Método de pago</label>
                        <select id="metodo_pago" name="metodo_pago" required class="<?php echo $clase_input; ?>">
                            <option value="Efectivo">Efectivo</option>
                            <option value="Transferencia QR">Transferencia QR</option>
                        </select>
                    </div>

                    <!-- Panel QR: solo visible si el método es Transferencia QR -->
                    <div id="panel-qr" class="hidden space-y-4">
                        <?php if ($qr_url !== ''): ?>
                            <div class="flex justify-center bg-neutral-50 border border-neutral-200 rounded-xl p-4">
                                <img src="<?php echo htmlspecialchars($qr_url); ?>" alt="QR de pago" class="w-48 h-48 object-contain">
                            </div>
                        <?php else: ?>
                            <p class="text-sm text-neutral-500 bg-neutral-100 rounded-xl px-4 py-3">
                                No hay un QR configurado<?php echo $es_admin ? '. Usa «Configurar QR».' : '. Pide al administrador que lo configure.'; ?>
                            </p>
                        <?php endif; ?>
                        <div>
                            <label for="nro_comprobante" class="<?php echo $clase_label; ?>">N.º de comprobante <span class="normal-case text-neutral-400 font-normal">(opcional)</span></label>
                            <input type="text" id="nro_comprobante" name="nro_comprobante" maxlength="100"
                                   class="<?php echo $clase_input; ?>">
                        </div>
                    </div>

                    <button type="submit" name="confirmar_venta" id="btn-confirmar" disabled
                            class="w-full bg-black text-white px-6 py-3 rounded-full text-sm font-semibold uppercase tracking-wide hover:bg-neutral-800 active:scale-[0.98] transition disabled:opacity-40 disabled:cursor-not-allowed">
                        Confirmar venta
                    </button>
                </form>
            </aside>
        </div>
<?php endif; ?>
    </main>

<?php if ($es_admin): ?>
    <!-- ================= MODAL: CONFIGURAR QR (solo Administrador) ================= -->
    <div id="modal-qr" class="hidden fixed inset-0 z-[60] bg-black/50 flex items-center justify-center px-4">
        <form action="crear.php" method="POST" class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 sm:p-8 space-y-5">
            <h2 class="text-xl font-black">Configurar QR de pago</h2>
            <div>
                <label for="qr_pago_url" class="<?php echo $clase_label; ?>">URL de la imagen del QR</label>
                <input type="url" id="qr_pago_url" name="qr_pago_url" required maxlength="2000" placeholder="https://..."
                       value="<?php echo htmlspecialchars($qr_url); ?>" class="<?php echo $clase_input; ?>">
            </div>
            <div class="flex gap-3">
                <button type="button" id="btn-cerrar-qr"
                        class="flex-1 px-6 py-3 border border-neutral-300 rounded-full text-sm font-semibold uppercase tracking-wide text-neutral-600 hover:border-black hover:text-black transition">
                    Cancelar
                </button>
                <button type="submit" name="guardar_qr"
                        class="flex-1 bg-black text-white px-6 py-3 rounded-full text-sm font-semibold uppercase tracking-wide hover:bg-neutral-800 transition">
                    Guardar
                </button>
            </div>
        </form>
    </div>
    <script>
        // Abre y cierra el modal de configuración del QR
        const modalQr = document.getElementById('modal-qr');
        document.getElementById('btn-config-qr').addEventListener('click', function () { modalQr.classList.remove('hidden'); });
        document.getElementById('btn-cerrar-qr').addEventListener('click', function () { modalQr.classList.add('hidden'); });
    </script>
<?php endif; ?>

<?php if ($hay_caja): ?>
    <!-- ================= JAVASCRIPT DEL PUNTO DE VENTA ================= -->
    <script>
        // Datos del catálogo entregados por PHP: id => {nombre, talla, color, stock, precio}
        const PRODUCTOS = <?php echo json_encode($datos_js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        // Carrito: id => cantidad
        const carrito = {};

        // Si hubo un error al procesar, se reconstruye el carrito que el usuario tenía
        const CARRITO_PREVIO = <?php echo json_encode($carrito_previo); ?>;
        CARRITO_PREVIO.forEach(function (l) {
            if (PRODUCTOS[l.id]) { carrito[l.id] = Math.min(l.cantidad, PRODUCTOS[l.id].stock); }
        });

        const lineasDiv   = document.getElementById('carrito-lineas');
        const vacioP      = document.getElementById('carrito-vacio');
        const totalSpan   = document.getElementById('total-general');
        const jsonInput   = document.getElementById('carrito-json');
        const btnConfirmar = document.getElementById('btn-confirmar');

        // Escapa texto antes de insertarlo como HTML (evita inyección)
        function esc(t) {
            const d = document.createElement('div');
            d.textContent = t;
            return d.innerHTML;
        }
        function bs(n) {
            return 'Bs. ' + n.toLocaleString('es-BO', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        // Agrega una unidad; valida que no supere el stock
        function agregar(id) {
            const p = PRODUCTOS[id];
            const actual = carrito[id] || 0;
            if (actual + 1 > p.stock) {
                alert('Stock máximo alcanzado: solo hay ' + p.stock + ' unidad(es) de este producto.');
                return;
            }
            carrito[id] = actual + 1;
            dibujar();
        }

        // Cambia la cantidad manualmente (validando el stock)
        function cambiarCantidad(id, valor) {
            const p = PRODUCTOS[id];
            let n = parseInt(valor, 10);
            if (isNaN(n) || n < 1) { n = 1; }
            if (n > p.stock) {
                alert('No puedes pedir más de ' + p.stock + ' unidad(es): es el stock disponible.');
                n = p.stock;
            }
            carrito[id] = n;
            dibujar();
        }

        function quitar(id) {
            delete carrito[id];
            dibujar();
        }

        // Redibuja el carrito, calcula subtotales y total general
        function dibujar() {
            const ids = Object.keys(carrito);
            let total = 0;
            const envio = [];
            lineasDiv.innerHTML = '';

            ids.forEach(function (id) {
                const p = PRODUCTOS[id];
                const cant = carrito[id];
                const subtotal = p.precio * cant;
                total += subtotal;
                envio.push({id: parseInt(id, 10), cantidad: cant});

                const div = document.createElement('div');
                div.className = 'border border-neutral-200 rounded-xl p-3';
                div.innerHTML =
                    '<div class="flex justify-between gap-2">' +
                        '<div><p class="text-sm font-semibold">' + esc(p.nombre) + '</p>' +
                        '<p class="text-xs text-neutral-400">Talla ' + esc(p.talla) + ' · ' + esc(p.color) + '</p></div>' +
                        '<button type="button" data-quitar="' + id + '" class="text-neutral-400 hover:text-black text-lg leading-none" title="Quitar">&times;</button>' +
                    '</div>' +
                    '<div class="flex items-center justify-between mt-2">' +
                        '<input type="number" min="1" max="' + p.stock + '" value="' + cant + '" data-cantidad="' + id + '" ' +
                              'class="w-20 px-3 py-1.5 border border-neutral-300 rounded-lg text-sm focus:outline-none focus:border-black">' +
                        '<span class="text-sm font-bold">' + bs(subtotal) + '</span>' +
                    '</div>';
                lineasDiv.appendChild(div);
            });

            vacioP.classList.toggle('hidden', ids.length > 0);
            totalSpan.textContent = bs(total);
            jsonInput.value = JSON.stringify(envio);
            btnConfirmar.disabled = (ids.length === 0);
        }

        // Clics en "Agregar" del catálogo
        document.getElementById('catalogo').addEventListener('click', function (e) {
            const btn = e.target.closest('[data-agregar]');
            if (btn) { agregar(btn.dataset.agregar); }
        });

        // Clics en "quitar" y cambios de cantidad dentro del carrito
        lineasDiv.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-quitar]');
            if (btn) { quitar(btn.dataset.quitar); }
        });
        lineasDiv.addEventListener('change', function (e) {
            if (e.target.dataset.cantidad) { cambiarCantidad(e.target.dataset.cantidad, e.target.value); }
        });

        // Buscador rápido: oculta las filas del catálogo que no coinciden
        const buscador = document.getElementById('buscador');
        const filas = document.querySelectorAll('#catalogo .fila-producto');
        const sinResultados = document.getElementById('sin-resultados');
        buscador.addEventListener('input', function () {
            const t = buscador.value.toLowerCase().trim();
            let visibles = 0;
            filas.forEach(function (f) {
                const ok = f.textContent.toLowerCase().includes(t);
                f.classList.toggle('hidden', !ok);
                if (ok) { visibles++; }
            });
            sinResultados.classList.toggle('hidden', visibles > 0);
        });

        // Método de pago: muestra el panel del QR solo con "Transferencia QR"
        const metodo = document.getElementById('metodo_pago');
        const panelQr = document.getElementById('panel-qr');
        metodo.addEventListener('change', function () {
            panelQr.classList.toggle('hidden', metodo.value !== 'Transferencia QR');
        });

        // Confirmación final antes de enviar la venta
        document.getElementById('form-venta').addEventListener('submit', function (e) {
            if (!confirm('¿Confirmar la venta por ' + totalSpan.textContent + '?')) { e.preventDefault(); }
        });

        dibujar();
    </script>
<?php endif; ?>
</body>
</html>
