<?php
// ==============================================================================
// ARCHIVO: auth.php
// DESCRIPCIÓN: Procesa el formulario de login (HU-01). Valida las credenciales
//              contra la BD y crea la sesión del usuario.
// ==============================================================================

// 1. Inicia (o reanuda) la sesión. Permite usar el arreglo $_SESSION para
//    recordar al usuario entre una página y otra.
session_start();

// 2. Incluye la conexión a la BD. require_once detiene el script si el archivo
//    no existe y evita incluirlo dos veces. Deja disponible la variable $conexion.
require_once 'includes/conexion.php';

// 3. Seguridad: este archivo solo debe responder a envíos POST del formulario.
//    Si alguien lo abre escribiendo la URL (método GET), se le devuelve al login.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit; // exit evita que el código siguiente se ejecute tras redirigir
}

// 4. Recibe los datos del formulario. El operador ?? asigna '' si el campo no llegó.
//    trim() elimina espacios al inicio y al final del correo.
$correo   = trim($_POST['correo'] ?? '');
$password = $_POST['password'] ?? '';

// 5. Validación básica: si algún campo está vacío, vuelve al login con error=2
//    (login.php muestra "Por favor completa todos los campos").
if ($correo === '' || $password === '') {
    header("Location: login.php?error=2");
    exit;
}

// 6. Consulta preparada con INNER JOIN entre usuarios (u) y roles (r).
//    - El JOIN une ambas tablas por id_rol para obtener nombre_rol.
//    - El signo ? es un marcador: el dato del usuario NUNCA se pega al SQL,
//      lo que evita la inyección SQL.
$sql = "SELECT u.id_usuario, u.nombres, u.password, r.nombre_rol
        FROM usuarios u
        INNER JOIN roles r ON u.id_rol = r.id_rol
        WHERE u.correo = ?
        LIMIT 1";

// 7. Prepara la sentencia: el servidor MySQL compila la consulta una sola vez.
$stmt = mysqli_prepare($conexion, $sql);

// 8. Vincula el valor real al marcador ?. "s" indica que el parámetro es string.
mysqli_stmt_bind_param($stmt, "s", $correo);

// 9. Ejecuta la consulta ya preparada y con el parámetro asignado.
mysqli_stmt_execute($stmt);

// 10. Inicializamos variables tipadas y vinculamos cada columna del SELECT
$id_usuario    = 0;
$nombres       = '';
$hash_guardado = '';
$nombre_rol    = '';
mysqli_stmt_bind_result($stmt, $id_usuario, $nombres, $hash_guardado, $nombre_rol);

// 11. Obtiene la fila. mysqli_stmt_fetch devuelve true si hubo un resultado
//     (el correo existe) y llena las variables del paso anterior.
$existe = mysqli_stmt_fetch($stmt);

// 12. Libera recursos de la sentencia (buena práctica).
mysqli_stmt_close($stmt);

// 13. Verifica credenciales:
//     - $existe: el correo está registrado.
//     - !empty($hash_guardado): asegura que exista el hash en formato string.
//     - password_verify(): compara la contraseña escrita con el hash guardado
//       en la BD (las contraseñas nunca se guardan en texto plano).
if ($existe && !empty($hash_guardado) && password_verify($password, (string)$hash_guardado)) {

    // 14. Regenera el ID de sesión para evitar ataques de "fijación de sesión".
    session_regenerate_id(true);

    // 15. Guarda en la sesión los datos del usuario. Otras páginas los leerán
    //     para saber quién está conectado y qué permisos tiene (Administrador/Vendedor).
    $_SESSION['id_usuario'] = $id_usuario;
    $_SESSION['nombres']    = $nombres;
    $_SESSION['rol']        = $nombre_rol;

    // 16. Login correcto: redirige al panel principal.
    header("Location: index.php");
    exit;

} else {
    // 17. Login fallido (correo inexistente o contraseña incorrecta).
    //     Se usa el mismo error para ambos casos para no revelar qué dato falló.
    header("Location: login.php?error=1");
    exit;
}
