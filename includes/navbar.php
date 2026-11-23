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

            <!-- Enlaces principales: visibles para todos los roles (Admin y Vendedor) -->
            <a href="/paso_firme/modelos/index.php"
               class="text-sm font-bold uppercase tracking-wide text-neutral-800 hover:text-black px-2 py-2 transition">
                Catálogo
            </a>

            <a href="/paso_firme/ventas/index.php"
               class="text-sm font-bold uppercase tracking-wide text-neutral-800 hover:text-black px-2 py-2 transition">
                Ventas
            </a>

            <a href="/paso_firme/caja/index.php"
               class="text-sm font-bold uppercase tracking-wide text-neutral-800 hover:text-black px-2 py-2 transition">
                Caja
            </a>

            <a href="/paso_firme/clientes/index.php"
               class="text-sm font-bold uppercase tracking-wide text-neutral-800 hover:text-black px-2 py-2 transition">
                Clientes
            </a>

            <?php if ($_SESSION['rol'] == 1): ?>
            <!--
              MENÚ DESPLEGABLE "FUNCIONES" (solo Administrador):
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
                            Gestión de Inventario
                        </a>

                        <a href="/paso_firme/modelos/index.php"
                           class="block px-4 py-3 text-sm text-neutral-700 hover:bg-neutral-100 hover:text-black transition">
                            Gestión de Modelos
                        </a>

                        <a href="/paso_firme/usuarios/index.php"
                           class="block px-4 py-3 text-sm text-neutral-700 hover:bg-neutral-100 hover:text-black transition">
                            Gestión de Usuarios
                        </a>

                        <a href="/paso_firme/ventas/index.php"
                           class="block px-4 py-3 text-sm text-neutral-700 hover:bg-neutral-100 hover:text-black transition">
                            Historial de Ventas
                        </a>

                        <a href="/paso_firme/caja/index.php"
                           class="block px-4 py-3 text-sm text-neutral-700 hover:bg-neutral-100 hover:text-black transition">
                            Control de Caja
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
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


<!-- ============================================================================
     MODAL GLOBAL DE CONFIRMACIÓN (reemplaza al confirm() nativo del navegador)
     Uso: abrirModalConfirmacion({ titulo, mensaje, textoConfirmar, esPeligro, onConfirmar })
     ============================================================================ -->
<div id="modal-confirmacion" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm print:hidden" role="dialog" aria-modal="true" aria-labelledby="modal-conf-titulo">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-6 sm:p-8">
        <h3 id="modal-conf-titulo" class="text-xl font-black uppercase tracking-tight text-neutral-900">Confirmar</h3>
        <p id="modal-conf-mensaje" class="mt-3 text-sm text-neutral-600 whitespace-pre-line"></p>
        <div class="mt-8 flex flex-col-reverse sm:flex-row gap-3">
            <button type="button" id="modal-conf-cancelar"
                    class="flex-1 px-6 py-3 rounded-full bg-neutral-100 text-neutral-700 text-sm font-semibold uppercase tracking-wide hover:bg-neutral-200 transition">
                Cancelar
            </button>
            <button type="button" id="modal-conf-aceptar"
                    class="flex-1 px-6 py-3 rounded-full text-white text-sm font-semibold uppercase tracking-wide transition bg-black hover:bg-neutral-800">
                Confirmar
            </button>
        </div>
    </div>
</div>

<script>
    (function () {
        const modal    = document.getElementById('modal-confirmacion');
        const titulo   = document.getElementById('modal-conf-titulo');
        const mensaje  = document.getElementById('modal-conf-mensaje');
        const btnOk    = document.getElementById('modal-conf-aceptar');
        const btnNo    = document.getElementById('modal-conf-cancelar');
        let callback   = null;

        function cerrar() {
            modal.classList.add('hidden');
            callback = null;
        }

        window.abrirModalConfirmacion = function (opc) {
            opc = opc || {};
            titulo.textContent  = opc.titulo || 'Confirmar acción';
            mensaje.textContent = opc.mensaje || '';
            btnOk.textContent   = opc.textoConfirmar || 'Confirmar';
            btnOk.className = 'flex-1 px-6 py-3 rounded-full text-white text-sm font-semibold uppercase tracking-wide transition ' +
                (opc.esPeligro ? 'bg-red-600 hover:bg-red-700' : 'bg-black hover:bg-neutral-800');
            callback = typeof opc.onConfirmar === 'function' ? opc.onConfirmar : null;
            modal.classList.remove('hidden');
            btnNo.focus();
        };

        // Para enlaces <a>: onclick="return confirmarEnlace(this, {...})"
        window.confirmarEnlace = function (enlace, opc) {
            opc = opc || {};
            opc.onConfirmar = function () { window.location.href = enlace.href; };
            window.abrirModalConfirmacion(opc);
            return false;
        };

        // Para formularios: confirmarFormulario(form, e, {...}) dentro del evento submit
        window.confirmarFormulario = function (form, e, opc) {
            if (form.dataset.confirmado === '1') { return; }  // ya confirmado: dejar enviar
            e.preventDefault();
            const submitter = e.submitter || null;
            opc = opc || {};
            opc.onConfirmar = function () {
                form.dataset.confirmado = '1';
                if (form.requestSubmit) { form.requestSubmit(submitter); } else { form.submit(); }
            };
            window.abrirModalConfirmacion(opc);
        };

        btnOk.addEventListener('click', function () {
            const cb = callback;
            cerrar();
            if (cb) { cb(); }
        });
        btnNo.addEventListener('click', cerrar);
        modal.addEventListener('click', function (e) { if (e.target === modal) { cerrar(); } });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) { cerrar(); }
        });
    })();
</script>