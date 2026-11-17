<?php
// ==============================================================================
// ARCHIVO: usuarios/index.php
// DESCRIPCIÓN: Listado de usuarios del sistema. TOTALMENTE restringido al
//              Administrador (rol 1).
// ==============================================================================

// 1. Inicia la sesión solo si aún no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Sin sesión -> login
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit;
}

// 3. Solo Administrador; los demás vuelven al dashboard
if ($_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit;
}

// 4. Conexión a la BD
require_once '../includes/conexion.php';

/** @var mysqli $conexion */

// 5. Consulta con JOIN: usuarios + nombre de su rol
//    (nunca se selecciona la columna password)
$usuarios = mysqli_fetch_all(
    mysqli_query($conexion,
        "SELECT u.id_usuario, u.nombres, u.correo, u.id_rol, r.nombre_rol
         FROM usuarios u
         INNER JOIN roles r ON u.id_rol = r.id_rol
         ORDER BY u.nombres ASC"),
    MYSQLI_ASSOC
);
$total = count($usuarios);

// 6. Mensajes de resultado enviados por las otras páginas (?msg= / ?error=)
$mensajes = [
    'creado'    => 'Usuario registrado correctamente.',
    'editado'   => 'Usuario actualizado correctamente.',
    'eliminado' => 'Usuario eliminado correctamente.',
];
$errores = [
    'llave_foranea'   => 'No se puede eliminar: el usuario tiene ventas o arqueos registrados.',
    'propio_usuario'  => 'No puedes eliminar tu propia cuenta.',
    'no_eliminado'    => 'No se pudo eliminar el usuario.',
];
$msg = $mensajes[$_GET['msg'] ?? ''] ?? '';
$err = $errores[$_GET['error'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PasoFirme | Gestión de Usuarios</title>
    <!-- Tailwind CSS vía CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900">

    <?php include '../includes/navbar.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Gestión de Usuarios</h1>
                <p class="text-sm text-neutral-500 mt-1"><?php echo $total; ?> usuario(s) registrado(s)</p>
            </div>
            <a href="registrar.php"
               class="inline-flex items-center justify-center gap-2 bg-black text-white px-6 py-3 rounded-full text-sm font-semibold uppercase tracking-wide hover:bg-neutral-800 active:scale-[0.98] transition">
                <span class="text-lg leading-none">+</span> Nuevo Usuario
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

        <!-- overflow-x-auto: la tabla se desliza en pantallas pequeñas -->
        <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm text-left">
                <thead class="bg-neutral-50 border-b border-neutral-200 text-xs uppercase tracking-wider text-neutral-500">
                    <tr>
                        <th class="px-6 py-4 font-semibold">ID</th>
                        <th class="px-6 py-4 font-semibold">Nombres</th>
                        <th class="px-6 py-4 font-semibold">Correo</th>
                        <th class="px-6 py-4 font-semibold">Rol</th>
                        <th class="px-6 py-4 font-semibold text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                <?php if ($total === 0): ?>
                    <tr><td colspan="5" class="px-6 py-12 text-center text-neutral-400">No hay usuarios registrados.</td></tr>
                <?php endif; ?>

                <?php foreach ($usuarios as $u):
                    $id = (int) $u['id_usuario'];
                    $es_yo = ($id === (int) $_SESSION['id']);
                    $badge = ((int) $u['id_rol'] === 1) ? 'bg-black text-white' : 'bg-neutral-200 text-neutral-800';
                ?>
                    <tr class="hover:bg-neutral-50 transition">
                        <td class="px-6 py-4 text-neutral-400 font-mono">#<?php echo $id; ?></td>
                        <td class="px-6 py-4 font-semibold whitespace-nowrap">
                            <?php echo htmlspecialchars($u['nombres']); ?>
                            <?php if ($es_yo): ?><span class="ml-2 text-xs font-medium text-neutral-400">(tú)</span><?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-neutral-600"><?php echo htmlspecialchars($u['correo']); ?></td>
                        <td class="px-6 py-4">
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide <?php echo $badge; ?>">
                                <?php echo htmlspecialchars($u['nombre_rol']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex justify-center gap-2">
                                <a href="editar.php?id=<?php echo $id; ?>"
                                   class="px-3 py-1.5 rounded-lg border border-neutral-200 text-xs font-semibold text-neutral-600 hover:bg-neutral-100 hover:text-black transition">
                                    Editar
                                </a>
                                <?php if (!$es_yo): ?>
                                    <a href="eliminar.php?id=<?php echo $id; ?>"
                                       onclick="return confirm('¿Seguro que deseas eliminar este usuario? Esta acción no se puede deshacer.');"
                                       class="px-3 py-1.5 rounded-lg border border-neutral-200 text-xs font-semibold text-neutral-600 hover:bg-black hover:text-white hover:border-black transition">
                                        Eliminar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
